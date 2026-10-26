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
    public const MANUAL_ONLY = 'UPC is not made automatically. Enter the UPC allotted to Mixx Tune: ISRC & UPC -> Pending UPC -> Enter UPC.';

    /* W116 */
    public const NO_LIST = 'No free UPC in the list. Upload the UPCs allotted to Mixx Tune (ISRC & UPC -> Code lists), or use Enter UPC.';

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ReleaseAuditService $audit
    ) {
    }

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

        /* W82: every typed UPC is checked - a wrong last digit means a typing mistake */
        if (!$this->gs1Valid($code)) {
            throw ValidationException::withMessages([
                'upc' =>
                    'This UPC is not valid: its last digit (check digit) does not match. Please check the number.',
            ]);
        }

        return $code;
    }

    /* GS1: from the right, digits next to the check digit are weighted 3, then 1, 3, 1 ... */
    public function gs1Valid(string $code): bool
    {
        $digits = array_map('intval', str_split($code));
        $check = array_pop($digits);
        $sum = 0;
        foreach (array_reverse($digits) as $i => $d) {
            $sum += $d * ($i % 2 === 0 ? 3 : 1);
        }

        return ((10 - ($sum % 10)) % 10) === $check;
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

        /* W58: replacing an existing UPC needs the e-mailed code and keeps the old one in the log */
        $old = (string) ($release->upc ?? '');
        if (!$duplicate && $old !== '' && $this->normalise($old) !== $code) {
            app(ActionOtpGuard::class)->require($user, 'identifier.upc:'.$release->id.':'.$code, 'change UPC '.$old.' of release #'.$release->id.' to '.$code);
            $notes = trim('Replaced '.$old.'. '.(string) $notes);
        }

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

            $freshRelease =
                $release->fresh();

            $this->notifications
                ->identifierAssigned(
                    $freshRelease,
                    'upc',
                    $freshRelease->upc
                );

            $this->audit
                ->identifierAssigned(
                    $freshRelease,
                    'upc',
                    $freshRelease->upc,
                    null,
                    $user
                );

            return $freshRelease;
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

        /* W58: never invent codes for old catalogue */
        if (\App\Support\LegacyRelease::is($release)) {
            throw ValidationException::withMessages([
                'upc' => \App\Support\LegacyRelease::message('UPC'),
            ]);
        }

        /*
         * W116 MIXXTUNE_CODE_LISTS (replaces W82 "manual only"): a UPC is never made up - it is the next
         * free one from the list of UPCs allotted to Mixx Tune (ISRC & UPC -> Code lists -> Upload).
         */
        return DB::transaction(function () use ($release, $user, $notes) {
            $locked = Release::query()->whereKey($release->id)->lockForUpdate()->firstOrFail();
            if ($locked->upc) {
                throw ValidationException::withMessages(['upc' => 'This release already has a UPC.']);
            }
            $record = app(IdentifierPool::class)->takeUpc();
            if (!$record) {
                throw ValidationException::withMessages(['upc' => self::NO_LIST]);
            }
            $record->forceFill(['status' => 'assigned', 'release_id' => $locked->id, 'assigned_by' => $user->id, 'assigned_at' => now(), 'notes' => $notes])->save();
            $locked->update([
                'upc' => $record->code,
                'upc_assigned_at' => now(),
                'upc_assigned_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            $this->log($record->code, 'list_assigned', $user, $locked, $notes);
            $freshRelease = $locked->fresh();
            $this->notifications->identifierAssigned($freshRelease, 'upc', $freshRelease->upc);
            $this->audit->identifierAssigned($freshRelease, 'upc', $freshRelease->upc, null, $user);

            return $freshRelease;
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
