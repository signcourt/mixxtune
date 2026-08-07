<?php

namespace App\Services\V2;

use App\Models\Distribution\Release;
use App\Models\UpcCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpcService
{
    public function normalise(
        string $code
    ): string {
        return preg_replace(
            '/[^0-9]/',
            '',
            trim($code)
        );
    }

    public function validate(
        string $code
    ): string {
        $code = $this->normalise($code);

        if (
            !in_array(
                strlen($code),
                [12, 13],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'upc' =>
                    'UPC/EAN must contain 12 or 13 digits.',
            ]);
        }

        if (!$this->hasValidCheckDigit($code)) {
            throw ValidationException::withMessages([
                'upc' =>
                    'UPC/EAN check digit is invalid.',
            ]);
        }

        return $code;
    }

    public function assignManual(
        Release $release,
        string $code,
        User $user,
        ?string $notes = null
    ): Release {
        $code = $this->validate($code);

        $duplicate = Release::query()
            ->where('upc', $code)
            ->where('id', '!=', $release->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'upc' =>
                    'This UPC is already assigned to another release.',
            ]);
        }

        return DB::transaction(function () use (
            $release,
            $code,
            $user,
            $notes
        ) {
            $release->update([
                'upc' => $code,
                'upc_assigned_at' => now(),
                'upc_assigned_by' =>
                    $user->id,
                'updated_by' =>
                    $user->id,
            ]);

            UpcCode::query()->updateOrCreate(
                [
                    'code' => $code,
                ],
                [
                    'public_id' =>
                        (string) Str::ulid(),

                    'prefix' =>
                        substr($code, 0, 6),

                    'status' =>
                        'assigned',

                    'release_id' =>
                        $release->id,

                    'assigned_by' =>
                        $user->id,

                    'assigned_at' =>
                        now(),

                    'notes' =>
                        $notes,
                ]
            );

            $this->log(
                $code,
                'manual_assigned',
                $user,
                $release,
                $notes
            );

            return $release->fresh();
        });
    }

    public function generate(
        Release $release,
        User $user,
        string $prefix = '890000',
        ?string $notes = null
    ): Release {
        if ($release->upc) {
            throw ValidationException::withMessages([
                'upc' =>
                    'This release already has a UPC.',
            ]);
        }

        $prefix = $this->normalise(
            $prefix
        );

        if (
            strlen($prefix) < 6
            || strlen($prefix) > 10
        ) {
            throw ValidationException::withMessages([
                'prefix' =>
                    'UPC prefix must contain 6 to 10 digits.',
            ]);
        }

        return DB::transaction(function () use (
            $release,
            $user,
            $prefix,
            $notes
        ) {
            $last = UpcCode::query()
                ->where(
                    'prefix',
                    $prefix
                )
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('code');

            $bodyLength = 11;

            $lastSequence = $last
                ? (int) substr(
                    $last,
                    strlen($prefix),
                    $bodyLength
                        - strlen($prefix)
                )
                : 0;

            $sequence =
                $lastSequence + 1;

            $sequenceLength =
                $bodyLength
                - strlen($prefix);

            $body =
                $prefix
                . str_pad(
                    (string) $sequence,
                    $sequenceLength,
                    '0',
                    STR_PAD_LEFT
                );

            $code =
                $body
                . $this->calculateCheckDigit(
                    $body
                );

            $record = UpcCode::query()->create([
                'public_id' =>
                    (string) Str::ulid(),

                'code' =>
                    $code,

                'prefix' =>
                    $prefix,

                'status' =>
                    'assigned',

                'release_id' =>
                    $release->id,

                'assigned_by' =>
                    $user->id,

                'assigned_at' =>
                    now(),

                'notes' =>
                    $notes,
            ]);

            $release->update([
                'upc' => $record->code,
                'upc_assigned_at' => now(),
                'upc_assigned_by' =>
                    $user->id,
                'updated_by' =>
                    $user->id,
            ]);

            $this->log(
                $record->code,
                'auto_generated',
                $user,
                $release,
                $notes
            );

            return $release->fresh();
        });
    }

    private function calculateCheckDigit(
        string $body
    ): int {
        $sum = 0;

        foreach (
            str_split($body) as $index => $digit
        ) {
            $position = $index + 1;

            $sum +=
                (int) $digit
                * (
                    $position % 2 === 1
                        ? 3
                        : 1
                );
        }

        return (10 - ($sum % 10)) % 10;
    }

    private function hasValidCheckDigit(
        string $code
    ): bool {
        $body = substr($code, 0, -1);
        $checkDigit =
            (int) substr($code, -1);

        return $this->calculateCheckDigit(
            $body
        ) === $checkDigit;
    }

    private function log(
        string $code,
        string $action,
        User $user,
        Release $release,
        ?string $notes
    ): void {
        if (
            !Schema::hasTable(
                'identifier_assignment_logs'
            )
        ) {
            return;
        }

        DB::table(
            'identifier_assignment_logs'
        )->insert([
            'public_id' =>
                (string) Str::ulid(),

            'identifier_type' =>
                'upc',

            'identifier_code' =>
                $code,

            'action' =>
                $action,

            'release_id' =>
                $release->id,

            'track_id' =>
                null,

            'performed_by' =>
                $user->id,

            'notes' =>
                $notes,

            'ip_address' =>
                request()?->ip(),

            'user_agent' =>
                request()?->userAgent(),

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }
}
