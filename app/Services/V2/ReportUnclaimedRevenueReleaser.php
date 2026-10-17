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

            if (
                Schema::hasTable('recoupment_plans')
                && DB::table('recoupment_plans')
                    ->where('user_id', $userId)
                    ->where('status', 'active')
                    ->where('outstanding_amount', '>', 0)
                    ->exists()
            ) {
                throw new RuntimeException('Owner has an active recoupment plan; release needs recoupment handling.');
            }

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

            DB::table('wallets')->where('id', $wallet->id)->update([
                'available_balance' => DB::raw('available_balance + ('.$amount.')'),
                'lifetime_credits' => DB::raw('lifetime_credits + '.$amount),
                'updated_at' => now(),
            ]);

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
            ];

            $txId = DB::table('wallet_transactions')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'artist_id' => $hold->owner_type === 'artist' ? (int) $hold->owner_id : null,
                'label_id' => $hold->owner_type === 'label' ? (int) $hold->owner_id : null,
                'transaction_type' => 'royalty_import',
                'direction' => 'credit',
                'amount' => $amount,
                'currency' => $currency,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => 'report_import_posting',
                'reference_id' => (int) $posting->id,
                'description' => trim('Royalty import '.$month).' (released hold)',
                'status' => 'posted',
                'effective_at' => $releasedAt,
                'posted_at' => $releasedAt,
                'metadata' => json_encode($releaseMeta),
                'created_by' => $actorUserId,
                'created_at' => $releasedAt,
                'updated_at' => $releasedAt,
            ]);

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
                'wallet_transaction_id' => (int) $txId,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
            ];
        });
    }
}
