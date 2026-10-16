<?php

namespace App\Services\V2;

use App\Models\Finance\WalletAccount;
use App\Models\Finance\WalletTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function account(
        User $user,
        string $currency = 'INR'
    ): WalletAccount {
        return WalletAccount::query()
            ->firstOrCreate(
                [
                    'user_id' =>
                        $user->id,

                    'currency' =>
                        strtoupper($currency),
                ],
                [
                    'public_id' =>
                        (string) Str::ulid(),

                    'artist_id' =>
                        null,

                    'label_id' =>
                        null,

                    'available_balance' =>
                        0,

                    'pending_balance' =>
                        0,

                    'lifetime_credits' =>
                        0,

                    'lifetime_debits' =>
                        0,

                    'status' =>
                        'active',
                ]
            );
    }

    public function creditPending(
        User $user,
        float $amount,
        string $category,
        array $reference = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Credit amount must be greater than zero.',
            ]);
        }

        return DB::transaction(
            function () use (
                $user,
                $amount,
                $category,
                $reference
            ) {
                $wallet = $this->account(
                    $user,
                    $reference['currency']
                        ?? 'INR'
                );

                /*
                 * Lock the resolved wallet row before reading
                 * or mutating balances.
                 *
                 * refresh() alone does not serialize concurrent
                 * financial writes and can cause lost updates.
                 */
                $wallet = WalletAccount::query()
                    ->whereKey($wallet->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $before =
                    (float) $wallet
                        ->pending_balance;

                $after =
                    $before + $amount;

                $wallet->update([
                    'pending_balance' =>
                        $after,

                    'lifetime_credits' =>
                        (float) $wallet
                            ->lifetime_credits
                        + $amount,
                ]);

                return $this->transaction(
                    $wallet,
                    'credit',
                    $category,
                    $amount,
                    $before,
                    $after,
                    $reference
                );
            }
        );
    }

    public function debitPending(
        User $user,
        float $amount,
        string $category,
        array $reference = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Debit amount must be greater than zero.',
            ]);
        }

        return DB::transaction(
            function () use (
                $user,
                $amount,
                $category,
                $reference
            ) {
                $wallet = $this->account(
                    $user,
                    $reference['currency']
                        ?? 'INR'
                );

                $wallet = WalletAccount::query()
                    ->whereKey($wallet->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $before =
                    (float) $wallet
                        ->pending_balance;

                /*
                 * Revenue adjustments are authoritative.
                 *
                 * A negative royalty must be carried in full
                 * even when the account has no positive pending
                 * balance yet.
                 *
                 * Example:
                 *   pending before = 50
                 *   adjustment     = -100
                 *   pending after  = -50
                 */
                $after =
                    $before - $amount;

                $wallet->update([
                    'pending_balance' =>
                        $after,

                    'lifetime_debits' =>
                        (float) $wallet
                            ->lifetime_debits
                        + $amount,
                ]);

                return $this->transaction(
                    $wallet,
                    'debit',
                    $category,
                    $amount,
                    $before,
                    $after,
                    $reference
                );
            }
        );
    }

    public function releasePending(
        User $user,
        float $amount,
        array $reference = []
    ): array {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Release amount must be greater than zero.',
            ]);
        }

        return DB::transaction(
            function () use (
                $user,
                $amount,
                $reference
            ) {
                $wallet = $this->account(
                    $user,
                    $reference['currency']
                        ?? 'INR'
                );

                /*
                 * Lock the resolved wallet row before reading
                 * or mutating balances.
                 *
                 * refresh() alone does not serialize concurrent
                 * financial writes and can cause lost updates.
                 */
                $wallet = WalletAccount::query()
                    ->whereKey($wallet->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $pendingBefore =
                    (float) $wallet
                        ->pending_balance;

                if ($pendingBefore < $amount) {
                    throw ValidationException::withMessages([
                        'amount' =>
                            'Pending balance is insufficient.',
                    ]);
                }

                $availableBefore =
                    (float) $wallet
                        ->available_balance;

                $wallet->update([
                    'pending_balance' =>
                        $pendingBefore - $amount,

                    'available_balance' =>
                        $availableBefore + $amount,
                ]);

                $pendingTransaction =
                    $this->transaction(
                        $wallet,
                        'debit',
                        'pending_release',
                        $amount,
                        $pendingBefore,
                        $pendingBefore - $amount,
                        $reference
                    );

                $availableTransaction =
                    $this->transaction(
                        $wallet,
                        'credit',
                        'available_credit',
                        $amount,
                        $availableBefore,
                        $availableBefore + $amount,
                        $reference
                    );

                return [
                    'wallet' =>
                        $wallet->fresh(),

                    'pending_transaction' =>
                        $pendingTransaction,

                    'available_transaction' =>
                        $availableTransaction,
                ];
            }
        );
    }

    public function creditAvailable(
        User $user,
        float $amount,
        string $category,
        array $reference = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Credit amount must be greater than zero.',
            ]);
        }

        return DB::transaction(
            function () use (
                $user,
                $amount,
                $category,
                $reference
            ) {
                $wallet = $this->account(
                    $user,
                    $reference['currency']
                        ?? 'INR'
                );

                /*
                 * Lock the resolved wallet row before reading
                 * or mutating balances.
                 *
                 * refresh() alone does not serialize concurrent
                 * financial writes and can cause lost updates.
                 */
                $wallet = WalletAccount::query()
                    ->whereKey($wallet->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $before =
                    (float) $wallet
                        ->available_balance;

                $after =
                    $before + $amount;

                $wallet->update([
                    'available_balance' =>
                        $after,

                    'lifetime_credits' =>
                        (float) $wallet
                            ->lifetime_credits
                        + $amount,
                ]);

                return $this->transaction(
                    $wallet,
                    'credit',
                    $category,
                    $amount,
                    $before,
                    $after,
                    $reference
                );
            }
        );
    }

    public function releasePendingDebit(
        User $user,
        float $amount,
        array $reference = []
    ): array {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Release debit amount must be greater than zero.',
            ]);
        }

        return DB::transaction(
            function () use (
                $user,
                $amount,
                $reference
            ) {
                $wallet = $this->account(
                    $user,
                    $reference['currency']
                        ?? 'INR'
                );

                $wallet = WalletAccount::query()
                    ->whereKey($wallet->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * Approval already posted the negative royalty
                 * into pending_balance:
                 *
                 *     0 - 100 = -100 pending
                 *
                 * Making the statement available transfers that
                 * liability out of pending and into available:
                 *
                 *     pending  -100 -> 0
                 *     available   0 -> -100
                 *
                 * Lifetime debit was recorded at approval time,
                 * so it MUST NOT be incremented again here.
                 */
                $pendingBefore =
                    (float) $wallet
                        ->pending_balance;

                $availableBefore =
                    (float) $wallet
                        ->available_balance;

                $pendingAfter =
                    $pendingBefore + $amount;

                $availableAfter =
                    $availableBefore - $amount;

                $wallet->update([
                    'pending_balance' =>
                        $pendingAfter,

                    'available_balance' =>
                        $availableAfter,
                ]);

                /*
                 * releasePending() returns two transactions.
                 * Keep the same audit model here:
                 *
                 * 1. pending reversal / transfer
                 * 2. available debit
                 */
                $pendingTransaction =
                    $this->transaction(
                        $wallet,
                        'credit',
                        'royalty_pending_release',
                        $amount,
                        $pendingBefore,
                        $pendingAfter,
                        array_merge(
                            $reference,
                            [
                                'description' =>
                                    $reference['description']
                                    ?? 'Negative royalty pending released',
                            ]
                        )
                    );

                $availableTransaction =
                    $this->transaction(
                        $wallet,
                        'debit',
                        'royalty_available',
                        $amount,
                        $availableBefore,
                        $availableAfter,
                        array_merge(
                            $reference,
                            [
                                'description' =>
                                    $reference['description']
                                    ?? 'Negative royalty made available',
                            ]
                        )
                    );

                return [
                    'pending' =>
                        $pendingTransaction,

                    'available' =>
                        $availableTransaction,
                ];
            }
        );
    }

    public function debitAvailable(
        User $user,
        float $amount,
        string $category,
        array $reference = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Debit amount must be greater than zero.',
            ]);
        }

        return DB::transaction(
            function () use (
                $user,
                $amount,
                $category,
                $reference
            ) {
                $wallet = $this->account(
                    $user,
                    $reference['currency']
                        ?? 'INR'
                );

                /*
                 * Lock the resolved wallet row before reading
                 * or mutating balances.
                 *
                 * refresh() alone does not serialize concurrent
                 * financial writes and can cause lost updates.
                 */
                $wallet = WalletAccount::query()
                    ->whereKey($wallet->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $before =
                    (float) $wallet
                        ->available_balance;

                if ($before < $amount) {
                    throw ValidationException::withMessages([
                        'amount' =>
                            'Available wallet balance is insufficient.',
                    ]);
                }

                $after =
                    $before - $amount;

                $wallet->update([
                    'available_balance' =>
                        $after,

                    'lifetime_debits' =>
                        (float) $wallet
                            ->lifetime_debits
                        + $amount,
                ]);

                return $this->transaction(
                    $wallet,
                    'debit',
                    $category,
                    $amount,
                    $before,
                    $after,
                    $reference
                );
            }
        );
    }

    private function transaction(
        WalletAccount $wallet,
        string $type,
        string $category,
        float $amount,
        float $before,
        float $after,
        array $reference
    ): WalletTransaction {
        $referenceType =
            $reference['reference_type']
            ?? null;

        $referenceId =
            $reference['reference_id']
            ?? null;

        if (
            $referenceType
            && $referenceId
            && WalletTransaction::query()
                ->where(
                    'wallet_id',
                    $wallet->id
                )
                ->where(
                    'transaction_type',
                    $category
                )
                ->where(
                    'reference_type',
                    $referenceType
                )
                ->where(
                    'reference_id',
                    $referenceId
                )
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'reference' =>
                    'This wallet transaction has already been recorded.',
            ]);
        }

        return WalletTransaction::query()->create([
            'public_id' =>
                (string) Str::ulid(),

            'wallet_id' =>
                $wallet->id,

            'user_id' =>
                $wallet->user_id,

            'transaction_type' =>
                $category,

            'direction' =>
                $type,

            'amount' =>
                $amount,

            'balance_before' =>
                $before,

            'balance_after' =>
                $after,

            'currency' =>
                $wallet->currency,
 
              'financial_month' =>
                  $reference['financial_month']
                  ?? $reference['reporting_month']
                  ?? $reference['statement_month']
                  ?? $reference['sale_month']
                  ?? null,


            'reference_type' =>
                $referenceType,

            'reference_id' =>
                $referenceId,

            'description' =>
                $reference['description']
                ?? null,

            'status' =>
                'posted',

            'effective_at' =>
                now(),

            'posted_at' =>
                now(),

            'metadata' =>
                $reference['meta']
                ?? [],

            'created_by' =>
                $reference['created_by']
                ?? auth()->id(),
        ]);
    }
}
