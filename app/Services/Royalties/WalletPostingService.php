<?php

namespace App\Services\Royalties;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WalletPostingService
{
    public function creditLedger(
        int $ledgerId,
        ?int $userId = null
    ): array {
        return DB::transaction(function () use (
            $ledgerId,
            $userId
        ) {
            $ledger = DB::table('royalty_ledgers')
                ->where('id', $ledgerId)
                ->lockForUpdate()
                ->first();

            if (! $ledger) {
                throw new \RuntimeException(
                    'Royalty ledger not found.'
                );
            }

            if (
                in_array(
                    (string) $ledger->status,
                    ['wallet_credited', 'paid'],
                    true
                )
            ) {
                throw new \RuntimeException(
                    'Royalty ledger is already wallet credited.'
                );
            }

            /*
             * Wallet materialization is a separate controlled step.
             * Draft/cancelled ledgers must never affect wallet balances.
             */
            if ((string) $ledger->status !== 'approved') {
                throw new \RuntimeException(
                    'Only approved royalty ledger can be posted to wallet.'
                );
            }

            $beneficiaryType =
                (string) ($ledger->beneficiary_type ?? '');

            $beneficiaryId =
                (int) ($ledger->beneficiary_id ?? 0);

            /*
             * Legacy ledgers may not have beneficiary_type/id.
             * Fall back only when exactly one concrete owner exists.
             */
            if (
                ! in_array(
                    $beneficiaryType,
                    ['label', 'artist'],
                    true
                )
                || $beneficiaryId <= 0
            ) {
                if (
                    ! empty($ledger->label_id)
                    && empty($ledger->artist_id)
                ) {
                    $beneficiaryType = 'label';
                    $beneficiaryId = (int) $ledger->label_id;
                } elseif (
                    ! empty($ledger->artist_id)
                    && empty($ledger->label_id)
                ) {
                    $beneficiaryType = 'artist';
                    $beneficiaryId = (int) $ledger->artist_id;
                } else {
                    throw new \RuntimeException(
                        'Royalty ledger beneficiary is missing or ambiguous.'
                    );
                }
            }

            if (
                $beneficiaryType === 'label'
                && (
                    empty($ledger->label_id)
                    || (int) $ledger->label_id !== $beneficiaryId
                )
            ) {
                throw new \RuntimeException(
                    'Royalty ledger label beneficiary mismatch.'
                );
            }

            if (
                $beneficiaryType === 'artist'
                && (
                    empty($ledger->artist_id)
                    || (int) $ledger->artist_id !== $beneficiaryId
                )
            ) {
                throw new \RuntimeException(
                    'Royalty ledger artist beneficiary mismatch.'
                );
            }

            $amount = round(
                (float) $ledger->payable_amount,
                8
            );

            if ($amount == 0.0) {
                throw new \RuntimeException(
                    'Royalty ledger payable amount is zero.'
                );
            }

            $currency = strtoupper(
                trim((string) ($ledger->currency ?: 'INR'))
            );

            /*
             * Idempotency is enforced while the ledger itself is locked.
             * The database reference index is not unique.
             */
            $duplicate = DB::table('wallet_transactions')
                ->where(
                    'reference_type',
                    'royalty_ledger'
                )
                ->where(
                    'reference_id',
                    $ledger->id
                )
                ->where(
                    'transaction_type',
                    'royalty_credit'
                )
                ->exists();

            if ($duplicate) {
                throw new \RuntimeException(
                    'Royalty ledger already has a wallet transaction.'
                );
            }

            $walletQuery = DB::table('wallets')
                ->where(
                    $beneficiaryType.'_id',
                    $beneficiaryId
                )
                ->where(
                    'currency',
                    $currency
                );

            $wallet = $walletQuery
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                $ownerUserId =
                    $ledger->user_id
                    ? (int) $ledger->user_id
                    : null;

                if (! $ownerUserId) {
                    $ownerUserId =
                        DB::table(
                            $beneficiaryType === 'label'
                                ? 'labels'
                                : 'artists'
                        )
                        ->where('id', $beneficiaryId)
                        ->value('user_id');
                }

                $walletId = DB::table('wallets')
                    ->insertGetId([
                        'public_id' =>
                            (string) \Illuminate\Support\Str::ulid(),

                        'user_id' =>
                            $ownerUserId,

                        'artist_id' =>
                            $beneficiaryType === 'artist'
                                ? $beneficiaryId
                                : null,

                        'label_id' =>
                            $beneficiaryType === 'label'
                                ? $beneficiaryId
                                : null,

                        'currency' =>
                            $currency,

                        'available_balance' => 0,
                        'pending_balance' => 0,
                        'lifetime_credits' => 0,
                        'lifetime_debits' => 0,
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                $wallet = DB::table('wallets')
                    ->where('id', $walletId)
                    ->lockForUpdate()
                    ->first();
            }

            $transactionUserId =
                $ledger->user_id
                ?? $wallet->user_id
                ?? $userId;

            if (! $transactionUserId) {
                throw new \RuntimeException(
                    'Wallet transaction user could not be resolved.'
                );
            }

            $balanceBefore = round(
                (float) $wallet->available_balance,
                8
            );

            $balanceAfter = round(
                $balanceBefore + $amount,
                8
            );

            $walletUpdate = [
                'available_balance' =>
                    $balanceAfter,

                'updated_at' =>
                    now(),
            ];

            if ($amount > 0) {
                $walletUpdate['lifetime_credits'] =
                    DB::raw(
                        'lifetime_credits + '
                        .abs($amount)
                    );
            } else {
                /*
                 * Negative royalty is an authoritative adjustment.
                 * Available balance may therefore become negative.
                 */
                $walletUpdate['lifetime_debits'] =
                    DB::raw(
                        'lifetime_debits + '
                        .abs($amount)
                    );
            }

            DB::table('wallets')
                ->where('id', $wallet->id)
                ->update($walletUpdate);

            $transactionId =
                DB::table('wallet_transactions')
                ->insertGetId([
                    'public_id' =>
                        (string) \Illuminate\Support\Str::ulid(),

                    'wallet_id' =>
                        $wallet->id,

                    'user_id' =>
                        $transactionUserId,

                    'artist_id' =>
                        $beneficiaryType === 'artist'
                            ? $beneficiaryId
                            : null,

                    'label_id' =>
                        $beneficiaryType === 'label'
                            ? $beneficiaryId
                            : null,

                    /*
                     * Keep the existing transaction_type stable for
                     * backwards compatibility. Direction carries the
                     * monetary sign.
                     */
                    'transaction_type' =>
                        'royalty_credit',

                    'direction' =>
                        $amount > 0
                            ? 'credit'
                            : 'debit',

                    'amount' =>
                        abs($amount),

                    'currency' =>
                        $currency,

                    'financial_month' =>
                        $ledger->reporting_month
                        ?? $ledger->sale_month
                        ?? $ledger->statement_month
                        ?? null,

                    'balance_before' =>
                        $balanceBefore,

                    'balance_after' =>
                        $balanceAfter,

                    'reference_type' =>
                        'royalty_ledger',

                    'reference_id' =>
                        $ledger->id,

                    'description' =>
                        sprintf(
                            'Royalty %s for ledger %s',
                            $amount > 0
                                ? 'credit'
                                : 'debit',
                            $ledger->ledger_number
                                ?: $ledger->public_id
                        ),

                    'status' =>
                        'posted',

                    'effective_at' =>
                        now(),

                    'posted_at' =>
                        now(),

                    'metadata' =>
                        json_encode([
                            'ledger_number' =>
                                $ledger->ledger_number,

                            'reporting_month' =>
                                $ledger->reporting_month,

                            'sale_month' =>
                                $ledger->sale_month,

                            'statement_month' =>
                                $ledger->statement_month,

                            'beneficiary_type' =>
                                $beneficiaryType,

                            'beneficiary_id' =>
                                $beneficiaryId,

                            'signed_payable_amount' =>
                                $amount,
                        ]),

                    'created_by' =>
                        $userId,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);

            DB::table('royalty_ledgers')
                ->where('id', $ledger->id)
                ->update([
                    'status' =>
                        'wallet_credited',

                    'updated_by' =>
                        $userId,

                    'updated_at' =>
                        now(),
                ]);

            return [
                'wallet_id' =>
                    (int) $wallet->id,

                'transaction_id' =>
                    (int) $transactionId,

                'ledger_id' =>
                    (int) $ledger->id,

                'beneficiary_type' =>
                    $beneficiaryType,

                'beneficiary_id' =>
                    $beneficiaryId,

                'direction' =>
                    $amount > 0
                        ? 'credit'
                        : 'debit',

                'amount' =>
                    abs($amount),

                'signed_amount' =>
                    $amount,

                'currency' =>
                    $currency,

                'balance_before' =>
                    $balanceBefore,

                'balance_after' =>
                    $balanceAfter,
            ];
        });
    }
}
