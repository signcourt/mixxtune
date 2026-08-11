<?php

namespace App\Services\V2;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientIdService
{
    public function generate(
        string $role,
        string $countryCode,
        string $stateCode,
        ?Carbon $date = null
    ): string {
        $countryCode = strtoupper(
            trim($countryCode)
        );

        $stateCode = strtoupper(
            trim($stateCode)
        );

        $roleCode = $this->roleCode($role);

        if (! preg_match('/^[A-Z]{2}$/', $countryCode)) {
            throw ValidationException::withMessages([
                'country' =>
                    'Client ID country code must contain exactly two letters.',
            ]);
        }

        if (! preg_match('/^[A-Z]{2}$/', $stateCode)) {
            throw ValidationException::withMessages([
                'state_code' =>
                    'State code must contain exactly two letters.',
            ]);
        }

        $date ??= now();

        $prefix =
            'MT'
            .$countryCode
            .$stateCode
            .$roleCode
            .$date->format('ymd');

        return DB::transaction(
            function () use ($prefix) {
                $lastClientId =
                    User::query()
                        ->whereNotNull('client_id')
                        ->where(
                            'client_id',
                            'like',
                            $prefix.'%'
                        )
                        ->lockForUpdate()
                        ->orderByDesc('client_id')
                        ->value('client_id');

                $sequence = 1;

                if ($lastClientId) {
                    $lastSequence = (int) substr(
                        $lastClientId,
                        -5
                    );

                    $sequence = $lastSequence + 1;
                }

                if ($sequence > 99999) {
                    throw ValidationException::withMessages([
                        'client_id' =>
                            'Client ID sequence limit reached for this date.',
                    ]);
                }

                return $prefix
                    .str_pad(
                        (string) $sequence,
                        5,
                        '0',
                        STR_PAD_LEFT
                    );
            },
            3
        );
    }

    private function roleCode(string $role): string
    {
        return match ($role) {
            'label' => 'L',
            'artist' => 'A',

            default =>
                throw ValidationException::withMessages([
                    'role' =>
                        'Client IDs can only be generated for label or artist accounts.',
                ]),
        };
    }
}
