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
                    'user_id' => $user->id,
                ],
                [
                    'public_id' =>
                        (string) Str::ulid(),

                    'currency' =>
                        strtoupper($currency),
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

                $wallet->refresh();

                $before =
                    (float) $wallet
                        ->pending_balance;

                $after =
                    $before + $amount;

                $wallet->update([
                    'pending_balance' =>
                        $after,

                    'lifetime_earnings' =>
                        (float) $wallet
                            ->lifetime_earnings
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

                $wallet->refresh();

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

                $wallet->refresh();

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

                    'withdrawn_balance' =>
                        (float) $wallet
                            ->withdrawn_balance
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

        $referenceCode =
            $reference['reference_code']
            ?? null;

        if (
            $referenceType
            && $referenceId
            && WalletTransaction::query()
                ->where(
                    'wallet_account_id',
                    $wallet->id
                )
                ->where(
                    'category',
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

            'wallet_account_id' =>
                $wallet->id,

            'type' =>
                $type,

            'category' =>
                $category,

            'amount' =>
                $amount,

            'balance_before' =>
                $before,

            'balance_after' =>
                $after,

            'currency' =>
                $wallet->currency,

            'reference_type' =>
                $referenceType,

            'reference_id' =>
                $referenceId,

            'reference_code' =>
                $referenceCode,

            'description' =>
                $reference['description']
                ?? null,

            'meta' =>
                $reference['meta']
                ?? [],

            'created_by' =>
                $reference['created_by']
                ?? auth()->id(),
        ]);
    }
}
