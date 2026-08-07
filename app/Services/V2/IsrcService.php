<?php

namespace App\Services\V2;

use App\Models\Distribution\Track;
use App\Models\IsrcCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IsrcService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ReleaseAuditService $audit
    ) {
    }

    public function normalise(
        string $code
    ): string {
        return strtoupper(
            preg_replace(
                '/[^A-Z0-9]/i',
                '',
                trim($code)
            )
        );
    }

    public function format(
        string $code
    ): string {
        $code = $this->normalise($code);

        if (strlen($code) !== 12) {
            return $code;
        }

        return sprintf(
            '%s-%s-%s-%s',
            substr($code, 0, 2),
            substr($code, 2, 3),
            substr($code, 5, 2),
            substr($code, 7, 5)
        );
    }

    public function validate(
        string $code
    ): string {
        $normalised = $this->normalise(
            $code
        );

        if (
            !preg_match(
                '/^[A-Z]{2}[A-Z0-9]{3}[0-9]{7}$/',
                $normalised
            )
        ) {
            throw ValidationException::withMessages([
                'isrc' =>
                    'ISRC must contain 12 valid characters.',
            ]);
        }

        return $this->format($normalised);
    }

    public function assignManual(
        Track $track,
        string $code,
        User $user,
        ?string $notes = null
    ): Track {
        $formatted = $this->validate($code);

        $duplicate = Track::query()
            ->where('isrc', $formatted)
            ->where('id', '!=', $track->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'isrc' =>
                    'This ISRC is already assigned to another track.',
            ]);
        }

        return DB::transaction(function () use (
            $track,
            $formatted,
            $user,
            $notes
        ) {
            $track->update([
                'isrc' => $formatted,
                'isrc_is_auto_generated' =>
                    false,
                'isrc_assigned_at' => now(),
                'isrc_assigned_by' =>
                    $user->id,
                'updated_by' => $user->id,
            ]);

            IsrcCode::query()->updateOrCreate(
                [
                    'code' => $formatted,
                ],
                [
                    'public_id' =>
                        (string) Str::ulid(),

                    'country_code' =>
                        substr(
                            $this->normalise(
                                $formatted
                            ),
                            0,
                            2
                        ),

                    'registrant_code' =>
                        substr(
                            $this->normalise(
                                $formatted
                            ),
                            2,
                            3
                        ),

                    'reference_year' =>
                        (int) (
                            '20'
                            . substr(
                                $this->normalise(
                                    $formatted
                                ),
                                5,
                                2
                            )
                        ),

                    'designation_code' =>
                        (int) substr(
                            $this->normalise(
                                $formatted
                            ),
                            7,
                            5
                        ),

                    'status' => 'assigned',
                    'track_id' => $track->id,
                    'assigned_by' =>
                        $user->id,
                    'assigned_at' => now(),
                    'notes' => $notes,
                ]
            );

            $this->log(
                $formatted,
                'manual_assigned',
                $user,
                $track,
                $notes
            );

            $freshTrack =
                $track->fresh([
                    'release',
                ]);

            if ($freshTrack->release) {
                $this->notifications
                    ->identifierAssigned(
                        $freshTrack->release,
                        'isrc',
                        $freshTrack->isrc
                    );

                $this->audit
                    ->identifierAssigned(
                        $freshTrack->release,
                        'isrc',
                        $freshTrack->isrc,
                        $freshTrack,
                        $user
                    );
            }

            return $freshTrack;
        });
    }

    public function generate(
        Track $track,
        User $user,
        string $countryCode = 'IN',
        string $registrantCode = 'MXT',
        ?int $year = null,
        ?string $notes = null
    ): Track {
        if ($track->isrc) {
            throw ValidationException::withMessages([
                'isrc' =>
                    'This track already has an ISRC.',
            ]);
        }

        $countryCode = strtoupper(
            trim($countryCode)
        );

        $registrantCode = strtoupper(
            trim($registrantCode)
        );

        $year = $year ?: (int) date('Y');

        if (
            !preg_match(
                '/^[A-Z]{2}$/',
                $countryCode
            )
        ) {
            throw ValidationException::withMessages([
                'country_code' =>
                    'Country code must contain 2 letters.',
            ]);
        }

        if (
            !preg_match(
                '/^[A-Z0-9]{3}$/',
                $registrantCode
            )
        ) {
            throw ValidationException::withMessages([
                'registrant_code' =>
                    'Registrant code must contain 3 characters.',
            ]);
        }

        return DB::transaction(function () use (
            $track,
            $user,
            $countryCode,
            $registrantCode,
            $year,
            $notes
        ) {
            $lastDesignation =
                IsrcCode::query()
                    ->where(
                        'country_code',
                        $countryCode
                    )
                    ->where(
                        'registrant_code',
                        $registrantCode
                    )
                    ->where(
                        'reference_year',
                        $year
                    )
                    ->lockForUpdate()
                    ->max(
                        'designation_code'
                    );

            $designation =
                ((int) $lastDesignation) + 1;

            if ($designation > 99999) {
                throw ValidationException::withMessages([
                    'isrc' =>
                        'ISRC yearly sequence limit reached.',
                ]);
            }

            $code = sprintf(
                '%s-%s-%s-%05d',
                $countryCode,
                $registrantCode,
                substr(
                    (string) $year,
                    -2
                ),
                $designation
            );

            $record = IsrcCode::query()->create([
                'public_id' =>
                    (string) Str::ulid(),

                'code' => $code,

                'country_code' =>
                    $countryCode,

                'registrant_code' =>
                    $registrantCode,

                'reference_year' =>
                    $year,

                'designation_code' =>
                    $designation,

                'status' =>
                    'assigned',

                'track_id' =>
                    $track->id,

                'assigned_by' =>
                    $user->id,

                'assigned_at' =>
                    now(),

                'notes' =>
                    $notes,
            ]);

            $track->update([
                'isrc' => $record->code,
                'isrc_is_auto_generated' =>
                    true,
                'isrc_assigned_at' =>
                    now(),
                'isrc_assigned_by' =>
                    $user->id,
                'updated_by' =>
                    $user->id,
            ]);

            $this->log(
                $record->code,
                'auto_generated',
                $user,
                $track,
                $notes
            );

            $freshTrack =
                $track->fresh([
                    'release',
                ]);

            if ($freshTrack->release) {
                $this->notifications
                    ->identifierAssigned(
                        $freshTrack->release,
                        'isrc',
                        $freshTrack->isrc
                    );

                $this->audit
                    ->identifierAssigned(
                        $freshTrack->release,
                        'isrc',
                        $freshTrack->isrc,
                        $freshTrack,
                        $user
                    );
            }

            return $freshTrack;
        });
    }

    private function log(
        string $code,
        string $action,
        User $user,
        Track $track,
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
                'isrc',

            'identifier_code' =>
                $code,

            'action' =>
                $action,

            'release_id' =>
                $track->release_id,

            'track_id' =>
                $track->id,

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
