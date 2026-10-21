<?php

namespace App\Services\V2;

use App\Mail\BankChangeOtpMail;
use App\Models\Finance\PayoutProfile;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/*
 * MIXXTUNE_BANK_CHANGE_PROTECTION_V1
 *
 * Changing an owner's existing bank / UPI details needs:
 *  1. a one-time code e-mailed to the person making the change (unlock is
 *     valid 10 minutes, for that one owner only);
 *  2. afterwards: payout KYC goes back to "submitted" (must be verified
 *     again), the owner is e-mailed, and no withdrawal of that owner can be
 *     marked Paid for 48 hours.
 * Changes made outside a logged-in request (artisan / server scripts) are
 * not gated.
 */
class BankChangeGuard
{
    public const FIELDS = ['account_holder_name', 'bank_account_number', 'ifsc_code', 'upi_id'];

    public const HOLD_HOURS = 48;

    public const UNLOCK_MINUTES = 10;

    public const OTP_MINUTES = 10;

    public const RESEND_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    public const AUDIT_ACTION = 'payout.bank_changed';

    /*
     * Always the file store: the code is created inside the (rolled back)
     * user-save transaction, so a database cache would lose it.
     */
    public static function store(): \Illuminate\Contracts\Cache\Repository
    {
        /* tests: per-test in-memory store, so tests never touch the server's cache folder */
        return Cache::store(app()->runningUnitTests() ? 'array' : 'file');
    }

    private function cache(): \Illuminate\Contracts\Cache\Repository
    {
        return static::store();
    }

    private function pendingKey(User $actor): string
    {
        return 'mixxtune:bankchg:pending:'.$actor->id;
    }

    private function unlockKey(User $actor, int $ownerUserId): string
    {
        return 'mixxtune:bankchg:unlock:'.$actor->id.':'.$ownerUserId;
    }

    public static function normalize($value): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim((string) $value)));
    }

    /** Field names (from FIELDS) whose value really changes on this save. */
    public function changedFields(PayoutProfile $profile): array
    {
        $changed = [];

        foreach (self::FIELDS as $field) {
            if (!$profile->isDirty($field)) {
                continue;
            }

            if (self::normalize($profile->getOriginal($field)) !== self::normalize($profile->getAttribute($field))) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    /** True when the profile already had some bank / UPI details (so this is a change, not first setup). */
    public function hadBankDetails(PayoutProfile $profile): bool
    {
        foreach (['bank_account_number', 'ifsc_code', 'upi_id'] as $field) {
            if (self::normalize($profile->getOriginal($field)) !== '') {
                return true;
            }
        }

        return false;
    }

    public function isUnlocked(User $actor, int $ownerUserId): bool
    {
        return (bool) $this->cache()->get($this->unlockKey($actor, $ownerUserId));
    }

    public function consumeUnlock(User $actor, int $ownerUserId): void
    {
        $this->cache()->forget($this->unlockKey($actor, $ownerUserId));
    }

    public static function maskEmail(?string $email): string
    {
        $email = (string) $email;
        $at = strpos($email, '@');

        if ($at === false || $at < 1) {
            return $email;
        }

        return substr($email, 0, min(2, $at)).str_repeat('*', max(1, $at - 2)).substr($email, $at);
    }

    /**
     * Send (or re-use a recently sent) code for changing this owner's bank
     * details. Returns true when a new e-mail went out.
     */
    public function sendCode(User $actor, User $owner, bool $force = false): bool
    {
        $key = $this->pendingKey($actor);
        $existing = $this->cache()->get($key);

        if (
            is_array($existing)
            && (int) ($existing['owner_id'] ?? 0) === (int) $owner->id
            && (int) ($existing['sent_at'] ?? 0) > now()->subSeconds(self::RESEND_SECONDS)->getTimestamp()
        ) {
            if ($force) {
                throw ValidationException::withMessages([
                    'otp' => 'Please wait 60 seconds before requesting another code.',
                ]);
            }

            return false;
        }

        $otp = (string) random_int(100000, 999999);

        $this->cache()->put($key, [
            'owner_id' => (int) $owner->id,
            'owner_name' => (string) ($owner->name ?: $owner->email),
            'hash' => Hash::make($otp),
            'sent_at' => now()->getTimestamp(),
            'expires_at' => now()->addMinutes(self::OTP_MINUTES)->getTimestamp(),
            'attempts' => 0,
        ], now()->addMinutes(self::OTP_MINUTES + 1));

        try {
            Mail::to($actor->email)->send(new BankChangeOtpMail(
                $otp,
                (string) ($actor->name ?: 'Admin'),
                (string) ($owner->name ?: $owner->email)
            ));
        } catch (\Throwable $e) {
            $this->cache()->forget($key);
            report($e);

            throw ValidationException::withMessages([
                'bank_change_otp' => 'Could not e-mail the confirmation code. Bank details were not changed. Please try again later.',
            ]);
        }

        return true;
    }

    public function pending(User $actor): ?array
    {
        $record = $this->cache()->get($this->pendingKey($actor));

        return is_array($record) ? $record : null;
    }

    /** Check the code; on success unlock bank changes of that owner for this actor. */
    public function unlock(User $actor, string $otp): array
    {
        $key = $this->pendingKey($actor);
        $record = $this->cache()->get($key);

        if (!is_array($record) || (int) ($record['expires_at'] ?? 0) < now()->getTimestamp()) {
            $this->cache()->forget($key);

            throw ValidationException::withMessages([
                'otp' => 'The code has expired. Press Save again to get a new code.',
            ]);
        }

        if ((int) ($record['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'otp' => 'Too many wrong codes. Press Save again in a few minutes to get a new code.',
            ]);
        }

        $record['attempts'] = (int) ($record['attempts'] ?? 0) + 1;
        $this->cache()->put($key, $record, now()->addMinutes(self::OTP_MINUTES + 1));

        if (!preg_match('/^\d{6}$/', $otp) || !Hash::check($otp, (string) ($record['hash'] ?? ''))) {
            throw ValidationException::withMessages([
                'otp' => 'Wrong code. Please check the e-mail and try again.',
            ]);
        }

        $this->cache()->forget($key);
        $this->cache()->put($this->unlockKey($actor, (int) $record['owner_id']), true, now()->addMinutes(self::UNLOCK_MINUTES));

        return [
            'owner_id' => (int) $record['owner_id'],
            'owner_name' => (string) ($record['owner_name'] ?? ''),
            'unlock_minutes' => self::UNLOCK_MINUTES,
        ];
    }

    /** When the last bank change of this profile was saved (from the audit log). */
    public function lastChangeAt(PayoutProfile $profile): ?CarbonInterface
    {
        if (!Schema::hasTable('audit_logs')) {
            return null;
        }

        $at = DB::table('audit_logs')
            ->where('action', self::AUDIT_ACTION)
            ->where('auditable_type', PayoutProfile::class)
            ->where('auditable_id', $profile->id)
            ->max('created_at');

        return $at ? \Illuminate\Support\Carbon::parse($at) : null;
    }

    /** End of the 48-hour payout hold, or null when there is no active hold. */
    public function holdUntil(PayoutProfile $profile): ?CarbonInterface
    {
        $changedAt = $this->lastChangeAt($profile);

        if (!$changedAt) {
            return null;
        }

        $until = $changedAt->copy()->addHours(self::HOLD_HOURS);

        return $until->isFuture() ? $until : null;
    }

    /** Throws when a withdrawal of this owner must not be marked Paid yet. */
    public function assertPayoutAllowed(int $ownerUserId): void
    {
        $profile = PayoutProfile::query()->where('user_id', $ownerUserId)->first();

        /* MIXXTUNE_WITHDRAWALS_V2: no payout profile = nowhere to pay (was allowed before) */
        if (!$profile) {
            throw ValidationException::withMessages([
                'payment_reference' => 'This owner has no payout / KYC profile. Add bank + PAN on the user page and verify the KYC first.',
            ]);
        }

        $until = $this->holdUntil($profile);

        if ($until) {
            throw ValidationException::withMessages([
                'payment_reference' => 'Bank details of this owner were changed recently. For safety, payouts are on hold until '
                    .$until->copy()->timezone(config('app.timezone'))->format('d M Y, h:i A').' ('.self::HOLD_HOURS.' hours after the change).',
            ]);
        }

        if ($profile->kyc_status !== 'verified') {
            throw ValidationException::withMessages([
                'payment_reference' => 'Payout KYC of this owner is "'.($profile->kyc_status ?: 'pending').'". Verify the KYC (bank details) first, then mark the withdrawal as paid.',
            ]);
        }
    }
}
