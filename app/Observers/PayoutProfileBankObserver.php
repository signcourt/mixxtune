<?php

namespace App\Observers;

use App\Mail\BankDetailsChangedMail;
use App\Models\Finance\PayoutProfile;
use App\Models\User;
use App\Services\V2\AuditLogService;
use App\Services\V2\BankChangeGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/* MIXXTUNE_BANK_CHANGE_PROTECTION_V1: see App\Services\V2\BankChangeGuard. */
class PayoutProfileBankObserver
{
    /** Per-save details handed from updating() to updated(), keyed by profile id. */
    private static array $pending = [];

    private static function maskAccount($value): ?string
    {
        $value = BankChangeGuard::normalize($value);

        if ($value === '') {
            return null;
        }

        return str_repeat('•', max(strlen($value) - 4, 0)).substr($value, -4);
    }

    private static function snapshot(PayoutProfile $profile, bool $original): array
    {
        $get = fn (string $field) => $original ? $profile->getOriginal($field) : $profile->getAttribute($field);

        return [
            'account_holder_name' => $get('account_holder_name') ?: null,
            'bank_account' => self::maskAccount($get('bank_account_number')),
            'ifsc_code' => $get('ifsc_code') ?: null,
            'upi_id' => $get('upi_id') ?: null,
        ];
    }

    public function updating(PayoutProfile $profile): void
    {
        $actor = auth()->user();

        if (!$actor instanceof User) {
            return;
        }

        $guard = app(BankChangeGuard::class);
        $changed = $guard->changedFields($profile);

        if ($changed === [] || !$guard->hadBankDetails($profile)) {
            return;
        }

        if (!$guard->isUnlocked($actor, (int) $profile->user_id)) {
            $owner = User::query()->find($profile->user_id);

            if (!$owner) {
                return;
            }

            $guard->sendCode($actor, $owner);

            throw ValidationException::withMessages([
                'bank_change_otp' => 'Changing bank details of '.($owner->name ?: $owner->email)
                    .' needs a confirmation code. A 6-digit code was e-mailed to '
                    .BankChangeGuard::maskEmail($actor->email)
                    .'. Enter it in the box that opened, then press Save again. Nothing was saved yet.',
            ]);
        }

        $wasVerified = $profile->getOriginal('kyc_status') === 'verified';

        if ($wasVerified) {
            $profile->kyc_status = 'submitted';
            $profile->verified_at = null;
            $profile->verified_by = null;
        }

        self::$pending[$profile->id] = [
            'actor_id' => (int) $actor->id,
            'fields' => $changed,
            'old' => self::snapshot($profile, true),
            'new' => self::snapshot($profile, false),
            'kyc_reset' => $wasVerified,
        ];
    }

    public function updated(PayoutProfile $profile): void
    {
        $info = self::$pending[$profile->id] ?? null;
        unset(self::$pending[$profile->id]);

        if (!$info) {
            return;
        }

        $guard = app(BankChangeGuard::class);
        $actor = User::query()->find($info['actor_id']);
        $owner = User::query()->find($profile->user_id);

        if ($actor) {
            $guard->consumeUnlock($actor, (int) $profile->user_id);
        }

        if ($info['kyc_reset'] && $owner && $owner->kyc_status === 'verified') {
            DB::table('users')->where('id', $owner->id)->update(['kyc_status' => 'submitted', 'updated_at' => now()]);
        }

        $holdUntil = now()->addHours(BankChangeGuard::HOLD_HOURS);

        /*
         * The audit row is also what the 48-hour payout hold reads, so if it
         * cannot be written the whole save is rolled back.
         */
        app(AuditLogService::class)->record(
            BankChangeGuard::AUDIT_ACTION,
            'finance',
            'Bank / UPI details changed for '.($owner?->name ?: 'user #'.$profile->user_id)
                .' (fields: '.implode(', ', $info['fields']).'). Payouts on hold until '.$holdUntil->toDateTimeString().' UTC'
                .($info['kyc_reset'] ? '; payout KYC set back to submitted.' : '.'),
            $info['old'] + ['user_id' => (int) $profile->user_id],
            $info['new'] + ['user_id' => (int) $profile->user_id, 'hold_until' => $holdUntil->toIso8601String(), 'kyc_reset' => $info['kyc_reset']],
            $profile
        );

        if ($owner && filled($owner->email)) {
            $mail = new BankDetailsChangedMail(
                (string) ($owner->name ?: 'there'),
                $info['old'],
                $info['new'],
                now(),
                $holdUntil
            );
            $email = $owner->email;

            DB::afterCommit(function () use ($email, $mail) {
                try {
                    Mail::to($email)->send($mail);
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        }
    }
}
