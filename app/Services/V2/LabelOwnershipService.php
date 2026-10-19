<?php

namespace App\Services\V2;

use App\Http\Controllers\V2\Admin\UserManagementController;
use App\Mail\LabelOwnershipOtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/*
 * MIXXTUNE_LABEL_OWNERSHIP_OTP_V1
 *
 * Assign / transfer label ownership (labels.user_id) to a Label account.
 * Every change needs an e-mailed one-time code bound to the exact payload.
 * New owners are created through the standard Users module (same as the
 * Users > Create page: client id, profile/KYC/payout, invitation), as
 * role=label and pending until the invitation is accepted.
 */
class LabelOwnershipService
{
    public const EXPIRY_MINUTES = 5;
    public const RESEND_SECONDS = 60;
    public const MAX_ATTEMPTS = 5;

    public const NEW_USER_FIELDS = [
        'name', 'username', 'email', 'phone', 'address_line_1', 'address_line_2', 'country', 'country_code', 'state_code', 'state', 'city', 'postal_code',
        'revenue_share_percentage', 'account_holder_name', 'bank_account_number', 'bank_name', 'ifsc_code',
        'branch_name', 'upi_id', 'pan_number', 'gst_number',
    ];

    public function normalizeNewUser(?array $newUser): ?array
    {
        if (!$newUser) {
            return null;
        }

        $out = [];

        foreach (self::NEW_USER_FIELDS as $field) {
            $value = $newUser[$field] ?? null;
            $value = (is_string($value) || is_numeric($value)) ? trim((string) $value) : null;
            $out[$field] = $value === '' ? null : $value;
        }

        if ($out['email'] !== null) {
            $out['email'] = strtolower($out['email']);
        }

        foreach (['ifsc_code', 'pan_number', 'gst_number', 'state_code', 'country_code'] as $field) {
            if ($out[$field] !== null) {
                $out[$field] = strtoupper($out[$field]);
            }
        }

        $out['country'] = $out['country'] ?? 'India';
        $out['country_code'] = $out['country_code'] ?? 'IN';

        return $out;
    }

    public function fingerprint(array $labelIds, ?int $targetUserId, ?array $newUser): string
    {
        $ids = array_values(array_unique(array_map('intval', $labelIds)));
        sort($ids);

        return hash('sha256', json_encode([
            'labels' => $ids,
            'target' => $targetUserId,
            'new' => $this->normalizeNewUser($newUser),
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

    /*
     * Create the owner through UserManagementController@store exactly like
     * the Users > Create page does (role=label, invitation e-mail).
     */
    private function createOwnerViaUsersModule(User $admin, array $newUser, array $unownedLabelIds, ?Request $request): array
    {
        $payload = $newUser;
        $payload['role'] = 'label';
        $payload['send_invitation'] = true;
        $payload['label_ids'] = array_values($unownedLabelIds);
        $payload['new_labels'] = [];
        $payload['artist_ids'] = [];

        foreach ($payload as $key => $value) {
            if ($value === null) {
                unset($payload[$key]);
            }
        }

        $sub = Request::create('/v2/admin/users', 'POST', $payload);
        $sub->setUserResolver(fn () => $admin);
        $session = app('session.store');
        $sub->setLaravelSession($session);

        if ($request) {
            $sub->server->set('REMOTE_ADDR', $request->ip());
            $sub->headers->set('User-Agent', (string) $request->userAgent());
        }

        $session->forget(['errors', 'warning', 'success']);
        $previous = app('request');
        app()->instance('request', $sub);

        try {
            app()->call([app(UserManagementController::class), 'store'], ['request' => $sub]);
        } finally {
            app()->instance('request', $previous);
        }

        $warning = $session->pull('warning');
        $session->forget('success');
        $owner = User::query()->where('email', (string) $payload['email'])->first();

        if (!$owner) {
            $errors = $session->pull('errors');
            $messages = $errors ? $errors->getBag('default')->getMessages() : [];

            throw ValidationException::withMessages(
                $messages ?: ['new_user' => ['The owner account could not be created.']]
            );
        }

        return [$owner, $warning];
    }

    public function apply(User $admin, array $labelIds, ?int $targetUserId, ?array $newUser, ?Request $request = null): array
    {
        $labelIds = array_values(array_unique(array_map('intval', $labelIds)));
        $audit = app(AuditLogService::class);
        $created = false;
        $invitationWarning = null;
        $recalcError = null;

        $before = DB::table('labels')->whereIn('id', $labelIds)->get(['id', 'user_id', 'revenue_share_percentage'])->keyBy('id');

        if ($newUser) {
            $unowned = $before->filter(fn ($row) => !$row->user_id)->keys()->map(fn ($id) => (int) $id)->all();
            /* MIXXTUNE_LABEL_RATE_HISTORY_V1: the net % change is logged below. */
            [$owner, $invitationWarning] = LabelRateHistoryService::muted(
                fn () => $this->createOwnerViaUsersModule($admin, $this->normalizeNewUser($newUser), $unowned, $request)
            );
            $created = true;
            $targetUserId = (int) $owner->id;
        }

        [$target, $changes] = DB::transaction(function () use ($admin, $labelIds, $targetUserId, $before, $audit, $request) {
            $target = User::query()->whereKey($targetUserId)->lockForUpdate()->first();

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
                $old = isset($before[$label->id]) && $before[$label->id]->user_id ? (int) $before[$label->id]->user_id : null;

                if ((int) ($label->user_id ?? 0) !== (int) $target->id) {
                    DB::table('labels')->where('id', $label->id)->update([
                        'user_id' => $target->id,
                        'updated_by' => $admin->id,
                        'updated_at' => now(),
                    ]);
                }

                if ($old === (int) $target->id) {
                    $changes[] = ['label_id' => (int) $label->id, 'label_name' => $label->name, 'old_owner_id' => $old, 'new_owner_id' => (int) $target->id, 'status' => 'unchanged'];

                    continue;
                }

                $status = $old ? 'transferred' : 'assigned';

                $this->recordAudit(
                    $audit,
                    'label.owner_'.$status,
                    'labels',
                    "Label {$label->name} (#{$label->id}) owner {$status}: "
                        .($old ? "user #{$old}" : 'none')." -> user #{$target->id} (OTP confirmed by #{$admin->id}).",
                    ['user_id' => $old],
                    ['user_id' => (int) $target->id],
                    \App\Models\Core\Label::query()->find($label->id),
                    $request
                );

                $changes[] = ['label_id' => (int) $label->id, 'label_name' => $label->name, 'old_owner_id' => $old, 'new_owner_id' => (int) $target->id, 'status' => $status];
            }

            return [$target, $changes];
        });

        if ($created) {
            /*
             * Every label keeps the revenue % it had before (the Users module
             * may set the owner's % on labels it touches). Then pending-review earnings
             * are recalculated through the canonical service.
             */
            foreach ($before as $labelId => $row) {
                if ($row->revenue_share_percentage !== null) {
                    DB::table('labels')->where('id', $labelId)->update(['revenue_share_percentage' => $row->revenue_share_percentage]);
                }
            }

            $history = app(LabelRateHistoryService::class);
            $after = DB::table('labels')->whereIn('id', $labelIds)->pluck('revenue_share_percentage', 'id');

            foreach ($before as $labelId => $row) {
                $label = \App\Models\Core\Label::query()->find($labelId);

                if ($label) {
                    $history->record($label, $row->revenue_share_percentage, $after[$labelId] ?? null, 'label_ownership', 'New owner '.$target->email.' created.');
                }
            }

            try {
                $recalculator = app(ReportEarningsRecalculationService::class);

                foreach ($labelIds as $labelId) {
                    $label = \App\Models\Core\Label::query()->find($labelId);

                    if ($label) {
                        $recalculator->recalculateForLabel($label);
                    }
                }
            } catch (\Throwable $exception) {
                report($exception);
                $recalcError = $exception->getMessage();
            }
        }

        return [
            'owner' => $target->fresh()->only(['id', 'name', 'email', 'account_status']),
            'created' => $created,
            'changes' => $changes,
            'invitation_error' => $invitationWarning,
            'recalculation_error' => $recalcError,
        ];
    }
}
