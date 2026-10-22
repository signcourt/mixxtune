<?php

namespace App\Services\V2;

use App\Models\Finance\KycEditUnlock;
use App\Models\Finance\PayoutProfile;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/*
 * W80 MIXXTUNE_KYC_EDIT_LOCK
 *
 * Who may change the payout KYC / bank details of an account:
 * - the owner fills it once (no profile yet, or still "pending", or sent
 *   back as "rejected") and sends it for verification;
 * - once "submitted" or "verified" it is locked for the owner;
 * - a Super Admin can open it again for ONE change (owner asks through
 *   Support, signs the change form, the Super Admin uploads it). The
 *   opening is used up when the owner sends the details again, or ends
 *   after UNLOCK_DAYS.
 */
class KycEditLock
{
    public const UNLOCK_DAYS = 7;

    public function activeUnlock(int $userId): ?KycEditUnlock
    {
        if (!Schema::hasTable('kyc_edit_unlocks')) {
            return null;
        }

        return KycEditUnlock::query()
            ->where('user_id', $userId)
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->first();
    }

    /** ['can_edit' => bool, 'state' => first_time|sent_back|unlocked|waiting|verified, 'unlock' => ?KycEditUnlock] */
    public function state(User $owner, ?PayoutProfile $profile = null): array
    {
        $profile ??= PayoutProfile::query()->where('user_id', $owner->id)->first();
        $status = $profile?->kyc_status ?: 'pending';
        $unlock = $this->activeUnlock((int) $owner->id);

        if (in_array($status, ['submitted', 'verified'], true)) {
            return [
                'can_edit' => $unlock !== null,
                'state' => $unlock ? 'unlocked' : ($status === 'verified' ? 'verified' : 'waiting'),
                'unlock' => $unlock,
            ];
        }

        return [
            'can_edit' => true,
            'state' => $status === 'rejected' ? 'sent_back' : ($unlock ? 'unlocked' : 'first_time'),
            'unlock' => $unlock,
        ];
    }

    public function markUsed(int $userId): void
    {
        $unlock = $this->activeUnlock($userId);
        $unlock?->forceFill(['used_at' => now()])->save();
    }
}
