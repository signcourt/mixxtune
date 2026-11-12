<?php

namespace App\Services\V2;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * MIXXTUNE_UNCLAIMED_REVENUE_RELEASE_V1
 *
 * Releases a report-import unclaimed revenue hold to the owner's
 * canonical user wallet after the owner (label / artist) has been
 * linked to an active user.
 *
 * Exactly-once: the hold row is locked and must still be pristine
 * "held"; the posting must still be "held_unclaimed". Money is added
 * in SQL (same pattern as MIXXTUNE_WALLET_DECIMAL_SAFE_V1).
 * Owners with an active recoupment plan are refused so that recovery
 * is never silently skipped.
 */
class ReportUnclaimedRevenueReleaser
{
    public function release(int $holdId, int $actorUserId, ?string $note = null): array
    {
        return DB::transaction(function () use ($holdId, $actorUserId, $note) {
            $fmt = fn ($v) => number_format((float) $v, 8, '.', '');

            $hold = DB::table('report_unclaimed_revenues')
                ->where('id', $holdId)
                ->lockForUpdate()
                ->first();

            if (! $hold) {
                throw new RuntimeException('Unclaimed revenue hold not found.');
            }

            if (
                (string) $hold->status !== 'held'
                || $hold->claimed_by_user_id
                || $hold->wallet_id
                || $hold->wallet_transaction_id
                || $hold->claimed_at
            ) {
                throw new RuntimeException('Unclaimed revenue hold is not releasable.');
            }

            $posting = DB::table('report_import_postings')
                ->where('id', $hold->report_import_posting_id)
                ->lockForUpdate()
                ->first();

            if (! $posting) {
                throw new RuntimeException('Hold posting is missing.');
            }

            if (
                (string) $posting->status !== 'held_unclaimed'
                || $posting->wallet_id
                || $posting->wallet_transaction_id
            ) {
                throw new RuntimeException('Hold posting is not in held state.');
            }

            if (
                (string) $posting->owner_type !== (string) $hold->owner_type
                || (int) $posting->owner_id !== (int) $hold->owner_id
                || (string) $posting->currency !== (string) $hold->currency
                || $fmt($posting->earnings) !== $fmt($hold->net_amount)
            ) {
                throw new RuntimeException('Hold does not match posting.');
            }

            $amount = $fmt($hold->net_amount);

            if ((float) $amount <= 0) {
                throw new RuntimeException('Only positive holds can be released.');
            }

            $ownerTable = match ((string) $hold->owner_type) {
                'label' => 'labels',
                'artist' => 'artists',
                default => throw new RuntimeException('Unsupported hold owner type.'),
            };

            $owner = DB::table($ownerTable)->where('id', $hold->owner_id)->first();

            if (! $owner) {
                throw new RuntimeException('Hold owner is missing.');
            }

            if (empty($owner->user_id)) {
                throw new RuntimeException('Hold owner is not linked to a user yet.');
            }

            $userId = (int) $owner->user_id;

            $user = DB::table('users')->where('id', $userId)->first();

            if (! $user) {
                throw new RuntimeException('Owner user is missing.');
            }

            if (($user->account_status ?? null) !== 'active') {
                throw new RuntimeException('Owner user is not active.');
            }

            /*
             * MIXXTUNE_HOLD_RELEASE_RECOUPMENT_V1
             *
             * If the owner has an active advance (recoupment plan with an
             * outstanding balance), recover from THIS release first - exactly
             * the way the publisher recovers at publish time - then credit only
             * what is left to the wallet. Idempotency is keyed to the hold, so a
             * retry never double-recovers. The advance's outstanding falls by the
             * recovered amount.
             */
            $grossForRecovery = (float) $hold->gross_amount;
            $netForRecovery = (float) $amount;
            $recoveredAmount = 0.0;
            $recoveryId = null;

            if (Schema::hasTable('recoupment_plans')) {
                $plan = $this->findActivePlan((string) $hold->owner_type, (int) $hold->owner_id, $userId);

                if ($plan) {
                    $recovery = app(\App\Services\V3\RecoupmentManagementService::class)
                        ->applyStatementRecovery(
                            $plan,
                            $grossForRecovery,
                            $netForRecovery,
                            [
                                'royalty_statement_id' => (int) $posting->royalty_statement_id,
                                'reporting_month' => (string) (DB::table('report_imports')->where('id', $posting->report_import_id)->value('reporting_month') ?? ''),
                                'reference' => 'Hold release #'.$hold->id.' (import posting '.$posting->id.')',
                                'idempotency_key' => 'report_hold_release:'.$hold->id,
                            ]
                        );

                    if ($recovery) {
                        $recoveredAmount = round((float) $recovery->applied_recovery_amount, 8);
                        $recoveryId = (int) $recovery->id;
                    }
                }
            }

            // What actually reaches the wallet = net minus advance recovery.
            $creditAmount = $fmt(max(0.0, round((float) $amount - $recoveredAmount, 8)));

            $currency = (string) $hold->currency;

            /* MIXXTUNE_CANONICAL_USER_WALLET */
            $wallet = DB::table('wallets')
                ->where('user_id', $userId)
                ->where('currency', $currency)
                ->whereNull('label_id')
                ->whereNull('artist_id')
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                $walletId = DB::table('wallets')->insertGetId([
                    'public_id' => (string) Str::ulid(),
                    'user_id' => $userId,
                    'artist_id' => null,
                    'label_id' => null,
                    'currency' => $currency,
                    'available_balance' => 0,
                    'pending_balance' => 0,
                    'lifetime_credits' => 0,
                    'lifetime_debits' => 0,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $wallet = DB::table('wallets')->where('id', $walletId)->lockForUpdate()->first();
            }

            /* MIXXTUNE_WALLET_DECIMAL_SAFE_V1: add in SQL, read back */
            $before = (string) $wallet->available_balance;

            if ((float) $creditAmount > 0) {
                DB::table('wallets')->where('id', $wallet->id)->update([
                    'available_balance' => DB::raw('available_balance + ('.$creditAmount.')'),
                    'lifetime_credits' => DB::raw('lifetime_credits + '.$creditAmount),
                    'updated_at' => now(),
                ]);
            }

            $after = (string) DB::table('wallets')->where('id', $wallet->id)->value('available_balance');

            $month = (string) (DB::table('report_imports')
                ->where('id', $posting->report_import_id)
                ->value('reporting_month') ?? '');

            $releasedAt = now();

            $releaseMeta = [
                'released_from_hold' => (string) $hold->hold_key,
                'unclaimed_revenue_id' => (int) $hold->id,
                'released_by_user_id' => $actorUserId,
                'released_at' => $releasedAt->toDateTimeString(),
                'note' => $note,
                'gross_amount' => $fmt($hold->net_amount),
                'advance_recovered' => $fmt($recoveredAmount),
                'credited_to_wallet' => $creditAmount,
                'recoupment_recovery_id' => $recoveryId,
            ];

            // Only record a wallet credit when something actually reaches the wallet
            // (a fully-recovered release puts everything against the advance).
            $txId = null;
            if ((float) $creditAmount > 0) {
                $desc = trim('Royalty import '.$month).' (released hold)';
                if ($recoveredAmount > 0) {
                    $desc .= ' — advance recovered '.$fmt($recoveredAmount);
                }
                $txId = DB::table('wallet_transactions')->insertGetId([
                    'public_id' => (string) Str::ulid(),
                    'wallet_id' => $wallet->id,
                    'user_id' => $userId,
                    'artist_id' => $hold->owner_type === 'artist' ? (int) $hold->owner_id : null,
                    'label_id' => $hold->owner_type === 'label' ? (int) $hold->owner_id : null,
                    'transaction_type' => 'royalty_import',
                    'direction' => 'credit',
                    'amount' => $creditAmount,
                    'currency' => $currency,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'reference_type' => 'report_import_posting',
                    'reference_id' => (int) $posting->id,
                    'description' => $desc,
                    'status' => 'posted',
                    'effective_at' => $releasedAt,
                    'posted_at' => $releasedAt,
                    'metadata' => json_encode($releaseMeta),
                    'created_by' => $actorUserId,
                    'created_at' => $releasedAt,
                    'updated_at' => $releasedAt,
                ]);
            }

            $postingMeta = json_decode((string) ($posting->metadata ?? ''), true);
            $postingMeta = is_array($postingMeta) ? $postingMeta : [];
            $postingMeta['hold_release'] = $releaseMeta;

            DB::table('report_import_postings')->where('id', $posting->id)->update([
                'status' => 'posted',
                'wallet_id' => $wallet->id,
                'wallet_transaction_id' => $txId,
                'posted_at' => $releasedAt,
                'last_error' => null,
                'metadata' => json_encode($postingMeta),
                'updated_at' => $releasedAt,
            ]);

            $holdMeta = json_decode((string) ($hold->metadata ?? ''), true);
            $holdMeta = is_array($holdMeta) ? $holdMeta : [];
            $holdMeta['release'] = $releaseMeta;

            DB::table('report_unclaimed_revenues')->where('id', $hold->id)->update([
                'status' => 'released',
                'claimed_by_user_id' => $userId,
                'wallet_id' => $wallet->id,
                'wallet_transaction_id' => $txId,
                'claimed_at' => $releasedAt,
                'metadata' => json_encode($holdMeta),
                'updated_at' => $releasedAt,
            ]);

            return [
                'hold_id' => (int) $hold->id,
                'user_id' => $userId,
                'wallet_id' => (int) $wallet->id,
                'wallet_transaction_id' => $txId !== null ? (int) $txId : null,
                'amount' => $amount,                 // full held net (gross of advance)
                'advance_recovered' => $fmt($recoveredAmount),
                'credited' => $creditAmount,         // what reached the wallet
                'balance_before' => $before,
                'balance_after' => $after,
            ];
        });
    }

    /**
     * Find the owner's active advance plan the same way the publisher does:
     * prefer an owner-specific plan, then fall back to a user-level plan.
     */
    private function findActivePlan(string $ownerType, int $ownerId, int $userId): ?\App\Models\Finance\RecoupmentPlan
    {
        $base = \App\Models\Finance\RecoupmentPlan::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('outstanding_amount', '>', 0);

        if ($ownerType === 'label') {
            $plan = (clone $base)->where('label_id', $ownerId)->whereNull('artist_id')->first();
        } else {
            $plan = (clone $base)->where('artist_id', $ownerId)->whereNull('label_id')->first();
        }

        if (! $plan) {
            $plan = (clone $base)->whereNull('label_id')->whereNull('artist_id')->first();
        }

        return $plan;
    }
}
