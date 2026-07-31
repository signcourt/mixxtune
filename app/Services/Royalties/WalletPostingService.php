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

            if (!$ledger) {
                throw new RuntimeException(
                    'Royalty ledger nahi mila.'
                );
            }

            if ($ledger->status !== 'approved') {
                throw new RuntimeException(
                    'Sirf approved ledger wallet me credit ho sakta hai.'
                );
            }

            if (!$ledger->label_id) {
                throw new RuntimeException(
                    'Ledger ke saath label linked nahi hai.'
                );
            }

            $currency = strtoupper(
                trim((string) ($ledger->currency ?: 'INR'))
            );

            $amount = round(
                (float) $ledger->payable_amount,
                8
            );

            if ($amount <= 0) {
                throw new RuntimeException(
                    'Ledger payable amount zero ya invalid hai.'
                );
            }

            $duplicate = DB::table('wallet_transactions')
                ->where('reference_type', 'royalty_ledger')
                ->where('reference_id', $ledger->id)
                ->where('transaction_type', 'royalty_credit')
                ->where('direction', 'credit')
                ->exists();

            if ($duplicate) {
                throw new RuntimeException(
                    'Is ledger ka wallet credit pehle se maujood hai.'
                );
            }

            $wallet = DB::table('wallets')
                ->where('label_id', $ledger->label_id)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                $walletId = DB::table('wallets')->insertGetId([
                    'public_id' => (string) Str::ulid(),
                    'user_id' => null,
                    'artist_id' => null,
                    'label_id' => $ledger->label_id,
                    'currency' => $currency,
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

            $balanceBefore = round(
                (float) $wallet->available_balance,
                8
            );

            $balanceAfter = round(
                $balanceBefore + $amount,
                8
            );

            DB::table('wallets')
                ->where('id', $wallet->id)
                ->update([
                    'available_balance' => $balanceAfter,
                    'lifetime_credits' => DB::raw(
                        'lifetime_credits + ' . $amount
                    ),
                    'updated_at' => now(),
                ]);

            $transactionId = DB::table(
                'wallet_transactions'
            )->insertGetId([
                'public_id' => (string) Str::ulid(),
                'wallet_id' => $wallet->id,
                'user_id' => $ledger->user_id
                    ?? $userId
                    ?? 1,
                'artist_id' => null,
                'label_id' => $ledger->label_id,
                'transaction_type' => 'royalty_credit',
                'direction' => 'credit',
                'amount' => $amount,
                'currency' => $currency,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => 'royalty_ledger',
                'reference_id' => $ledger->id,
                'description' => sprintf(
                    'Royalty credit for ledger %s',
                    $ledger->ledger_number
                        ?: $ledger->public_id
                ),
                'status' => 'posted',
                'effective_at' => now(),
                'posted_at' => now(),
                'metadata' => json_encode([
                    'ledger_number' => $ledger->ledger_number,
                    'reporting_month' => $ledger->reporting_month,
                    'sale_month' => $ledger->sale_month,
                    'statement_month' => $ledger->statement_month,
                ]),
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('royalty_ledgers')
                ->where('id', $ledger->id)
                ->update([
                    'status' => 'wallet_credited',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);

            return [
                'wallet_id' => (int) $wallet->id,
                'transaction_id' => (int) $transactionId,
                'ledger_id' => (int) $ledger->id,
                'amount' => $amount,
                'currency' => $currency,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ];
        });
    }
}
