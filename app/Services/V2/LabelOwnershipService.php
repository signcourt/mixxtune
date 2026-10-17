<?php

namespace App\Services\V2;

use App\Mail\LabelOwnershipOtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/*
 * MIXXTUNE_LABEL_OWNERSHIP_OTP_V1
 *
 * Assign / transfer label ownership (labels.user_id) to a Label account.
 * Every change requires an e-mailed one-time code that is bound to the
 * exact payload (label ids + target), so a code can never be reused for
 * a different change. New owner accounts are created as `pending` and
 * receive the normal invitation e-mail (activation stays system-controlled).
 */
class LabelOwnershipService
{
    public const EXPIRY_MINUTES = 5;
    public const RESEND_SECONDS = 60;
    public const MAX_ATTEMPTS = 5;

    public function fingerprint(array $labelIds, ?int $targetUserId, ?array $newUser): string
    {
        $ids = array_values(array_unique(array_map('intval', $labelIds)));
        sort($ids);

        return hash('sha256', json_encode([
            'labels' => $ids,
            'target' => $targetUserId,
            'new' => $newUser ? [
                'name' => trim((string) ($newUser['name'] ?? '')),
                'email' => strtolower(trim((string) ($newUser['email'] ?? ''))),
                'phone' => trim((string) ($newUser['phone'] ?? '')),
            ] : null,
        ]));
    }

    private function cacheKey(User $admin): string
    {
        return 'mixxtune:label-ownership-otp:'.$admin->id;
    }

    public function sendOtp(User $admin, string $fingerprint, int $labelCount, string $targetDescription): void
    {
        $key = $this->cacheKey($admin);
        $existing = Cache::get($key);

        if (
            is_array($existing)
            && (int) ($existing['sent_at'] ?? 0) > now()->subSeconds(self::RESEND_SECONDS)->getTimestamp()
        ) {
            throw ValidationException::withMessages([
                'otp' => 'Please wait 60 seconds before requesting another code.',
            ]);
        }

        $otp = (string) random_int(100000, 999999);

        Cache::put($key, [
            'hash' => Hash::make($otp),
            'fingerprint' => $fingerprint,
            'sent_at' => now()->getTimestamp(),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES)->getTimestamp(),
            'attempts' => 0,
        ], now()->addMinutes(self::EXPIRY_MINUTES + 1));

        Mail::to($admin->email)->send(
            new LabelOwnershipOtpMail($otp, $admin->name ?: 'Admin', $labelCount, $targetDescription)
        );
    }

    public function verifyOtp(User $admin, string $fingerprint, string $otp): bool
    {
        $key = $this->cacheKey($admin);
        $record = Cache::get($key);

        if (!is_array($record)) {
            return false;
        }

        if ((int) ($record['expires_at'] ?? 0) < now()->getTimestamp()) {
            Cache::forget($key);

            return false;
        }

        if ((int) ($record['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            return false;
        }

        $record['attempts'] = (int) ($record['attempts'] ?? 0) + 1;
        Cache::put($key, $record, now()->addMinutes(self::EXPIRY_MINUTES + 1));

        if (!hash_equals((string) ($record['fingerprint'] ?? ''), $fingerprint)) {
            return false;
        }

        if (!Hash::check($otp, (string) ($record['hash'] ?? ''))) {
            return false;
        }

        Cache::forget($key);

        return true;
    }

    /*
     * audit_logs exists in production; if it is ever missing (e.g. a test
     * database) the same record goes to the application log instead.
     */
    private function recordAudit(AuditLogService $audit, string $action, string $module, string $description, array $old, array $new, $auditable, ?Request $request): void
    {
        if (Schema::hasTable('audit_logs')) {
            $audit->record($action, $module, $description, $old, $new, $auditable, $request);

            return;
        }

        Log::info('MIXXTUNE_LABEL_OWNERSHIP_AUDIT', compact('action', 'module', 'description', 'old', 'new'));
    }

    public function preview(array $labelIds): array
    {
        return DB::table('labels as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
            ->whereIn('l.id', $labelIds)
            ->whereNull('l.deleted_at')
            ->orderBy('l.name')
            ->get(['l.id', 'l.name', 'l.user_id', 'u.name as owner_name', 'u.email as owner_email'])
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => $r->name,
                'current_owner_id' => $r->user_id ? (int) $r->user_id : null,
                'current_owner' => $r->owner_name ? $r->owner_name.' <'.$r->owner_email.'>' : null,
            ])
            ->all();
    }

    public function apply(User $admin, array $labelIds, ?int $targetUserId, ?array $newUser, ?Request $request = null): array
    {
        $labelIds = array_values(array_unique(array_map('intval', $labelIds)));
        $audit = app(AuditLogService::class);

        [$target, $created, $changes] = DB::transaction(function () use ($admin, $labelIds, $targetUserId, $newUser, $audit, $request) {
            $created = false;

            if ($newUser) {
                $email = strtolower(trim((string) $newUser['email']));

                if (User::query()->where('email', $email)->exists()) {
                    throw ValidationException::withMessages([
                        'new_email' => 'A user with this e-mail already exists.',
                    ]);
                }

                $phone = trim((string) ($newUser['phone'] ?? ''));

                $target = User::query()->create([
                    'name' => trim((string) $newUser['name']),
                    'email' => $email,
                    'phone' => $phone !== '' ? $phone : null,
                    'role' => 'label',
                    'account_status' => 'pending',
                    'password' => Hash::make(Str::random(32)),
                    'email_verified_at' => null,
                    'invitation_status' => 'pending',
                    'invitation_sent_at' => null,
                    'invitation_expires_at' => null,
                    'invitation_count' => 0,
                ]);
                $created = true;

                $this->recordAudit($audit, 
                    'user.created',
                    'users',
                    "User {$target->email} created from Labels page (owner assignment).",
                    [],
                    $target->only(['id', 'name', 'email', 'role', 'account_status']),
                    $target,
                    $request
                );
            } else {
                $target = User::query()->whereKey($targetUserId)->lockForUpdate()->first();
            }

            if (!$target || $target->role !== 'label') {
                throw ValidationException::withMessages([
                    'target_user_id' => 'Owner must be a Label account.',
                ]);
            }

            $labels = DB::table('labels')
                ->whereIn('id', $labelIds)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->get(['id', 'name', 'user_id']);

            if ($labels->count() !== count($labelIds)) {
                throw ValidationException::withMessages([
                    'label_ids' => 'One or more labels were not found.',
                ]);
            }

            $changes = [];

            foreach ($labels as $label) {
                $old = $label->user_id ? (int) $label->user_id : null;

                if ($old === (int) $target->id) {
                    $changes[] = [
                        'label_id' => (int) $label->id,
                        'label_name' => $label->name,
                        'old_owner_id' => $old,
                        'new_owner_id' => (int) $target->id,
                        'status' => 'unchanged',
                    ];

                    continue;
                }

                DB::table('labels')->where('id', $label->id)->update([
                    'user_id' => $target->id,
                    'updated_by' => $admin->id,
                    'updated_at' => now(),
                ]);

                $status = $old ? 'transferred' : 'assigned';

                $this->recordAudit($audit, 
                    'label.owner_'.$status,
                    'labels',
                    "Label {$label->name} (#{$label->id}) owner {$status}: "
                        .($old ? "user #{$old}" : 'none')." -> user #{$target->id} (OTP confirmed by #{$admin->id}).",
                    ['user_id' => $old],
                    ['user_id' => (int) $target->id],
                    \App\Models\Core\Label::query()->find($label->id),
                    $request
                );

                $changes[] = [
                    'label_id' => (int) $label->id,
                    'label_name' => $label->name,
                    'old_owner_id' => $old,
                    'new_owner_id' => (int) $target->id,
                    'status' => $status,
                ];
            }

            return [$target, $created, $changes];
        });

        $invitationError = null;

        if ($created) {
            try {
                app(UserInvitationService::class)->send($target);
            } catch (\Throwable $exception) {
                report($exception);
                $invitationError = $exception->getMessage();
            }
        }

        return [
            'owner' => $target->fresh()->only(['id', 'name', 'email', 'account_status']),
            'created' => $created,
            'changes' => $changes,
            'invitation_error' => $invitationError,
        ];
    }
}
