<?php

namespace App\Services\V3;

use App\Models\Finance\FinancialAdjustment;
use App\Models\User;
use App\Services\V2\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinancialAdjustmentService
{
    public function __construct(
        private WalletService $wallet
    ) {
    }

    public function post(
        User $user,
        string $direction,
        float $amount,
        string $reason,
        array $context = [],
        ?User $createdBy = null
    ): FinancialAdjustment {
        $direction = strtolower(
            trim($direction)
        );

        if (
            ! in_array(
                $direction,
                ['credit', 'debit'],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'direction' =>
                    'Direction must be credit or debit.',
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Adjustment amount must be greater than zero.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' =>
                    'Adjustment reason is required.',
            ]);
        }

        $idempotencyKey = isset(
            $context['idempotency_key']
        )
            ? trim(
                (string)
                    $context['idempotency_key']
            )
            : null;

        if ($idempotencyKey === '') {
            $idempotencyKey = null;
        }

        try {
            return DB::transaction(
                function () use (
                    $user,
                    $direction,
                    $amount,
                    $reason,
                    $context,
                    $createdBy,
                    $idempotencyKey
                ) {
                /*
                 * Retry safety:
                 * return the original financial entry before
                 * performing any wallet mutation.
                 */
                if ($idempotencyKey !== null) {
                    $existing =
                        FinancialAdjustment::query()
                            ->where(
                                'idempotency_key',
                                $idempotencyKey
                            )
                            ->first();

                    if ($existing) {
                        return $existing->load(
                            'walletTransaction'
                        );
                    }
                }

                $adjustment =
                    FinancialAdjustment::query()
                        ->create([
                            'user_id' =>
                                $user->id,

                            'adjustment_number' =>
                                $this->generateNumber(),

                            'idempotency_key' =>
                                $idempotencyKey,

                            'type' =>
                                $context['type']
                                ?? 'manual',

                            'direction' =>
                                $direction,

                            'amount' =>
                                $amount,

                            'reference_number' =>
                                $context[
                                    'reference_number'
                                ]
                                ?? null,

                            'reason' =>
                                trim($reason),

                            'internal_note' =>
                                $context[
                                    'internal_note'
                                ]
                                ?? null,

                            'effective_date' =>
                                $context[
                                    'effective_date'
                                ]
                                ?? now()->toDateString(),

                            'status' =>
                                'posted',

                            'created_by' =>
                                $createdBy?->id,
                        ]);

                $reference = [
                    'currency' =>
                        $context['currency']
                        ?? 'INR',

                    'reference_type' =>
                        FinancialAdjustment::class,

                    'reference_id' =>
                        $adjustment->id,

                    'reference_code' =>
                        $adjustment
                            ->adjustment_number,

                    'description' =>
                        $reason,

                    'created_by' =>
                        $createdBy?->id,

                    'meta' => [
                        'adjustment_number' =>
                            $adjustment
                                ->adjustment_number,

                        'adjustment_type' =>
                            $adjustment->type,
                    ],
                ];

                if ($direction === 'credit') {
                    $transaction =
                        $this->wallet
                            ->creditAvailable(
                                $user,
                                $amount,
                                'manual_adjustment',
                                $reference
                            );
                } else {
                    $transaction =
                        $this->wallet
                            ->debitAvailable(
                                $user,
                                $amount,
                                'manual_adjustment',
                                $reference
                            );
                }

                $adjustment->update([
                    'wallet_transaction_id' =>
                        $transaction->id,
                ]);

                return $adjustment->fresh([
                    'walletTransaction',
                ]);
                }
            );
        } catch (
            UniqueConstraintViolationException $exception
        ) {
            /*
             * Two requests may pass the pre-check before
             * either transaction commits.
             *
             * The database unique constraint is the final
             * arbiter. After this transaction has rolled
             * back, return the committed winning adjustment
             * only when this exact idempotency key now
             * exists.
             *
             * Any other unique violation remains a genuine
             * database error and must not be swallowed.
             */
            if ($idempotencyKey !== null) {
                $existing =
                    FinancialAdjustment::query()
                        ->where(
                            'idempotency_key',
                            $idempotencyKey
                        )
                        ->first();

                if ($existing) {
                    return $existing->load(
                        'walletTransaction'
                    );
                }
            }

            throw $exception;
        }
    }

    public function reverse(
        FinancialAdjustment $adjustment,
        string $reason,
        ?User $reversedBy = null
    ): FinancialAdjustment {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' =>
                    'Reversal reason is required.',
            ]);
        }

        return DB::transaction(
            function () use (
                $adjustment,
                $reason,
                $reversedBy
            ) {
                $locked =
                    FinancialAdjustment::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $adjustment->id
                        );

                if (
                    $locked->status
                    !== 'posted'
                ) {
                    throw ValidationException::withMessages([
                        'adjustment' =>
                            'Only posted adjustments can be reversed.',
                    ]);
                }

                if (
                    $locked->reversal_of_id
                    !== null
                ) {
                    throw ValidationException::withMessages([
                        'adjustment' =>
                            'A reversal entry cannot be reversed.',
                    ]);
                }

                if (
                    $locked->reversals()
                        ->exists()
                ) {
                    throw ValidationException::withMessages([
                        'adjustment' =>
                            'This adjustment has already been reversed.',
                    ]);
                }

                $user = User::query()
                    ->findOrFail(
                        $locked->user_id
                    );

                $reverseDirection =
                    $locked->direction
                    === 'credit'
                        ? 'debit'
                        : 'credit';

                $reversal =
                    FinancialAdjustment::query()
                        ->create([
                            'user_id' =>
                                $user->id,

                            'reversal_of_id' =>
                                $locked->id,

                            'adjustment_number' =>
                                $this->generateNumber(),

                            'type' =>
                                'reversal',

                            'direction' =>
                                $reverseDirection,

                            'amount' =>
                                $locked->amount,

                            'reference_number' =>
                                $locked
                                    ->adjustment_number,

                            'reason' =>
                                trim($reason),

                            'effective_date' =>
                                now()->toDateString(),

                            'status' =>
                                'posted',

                            'created_by' =>
                                $reversedBy?->id,
                        ]);

                $reference = [
                    'currency' =>
                        $locked
                            ->walletTransaction
                            ?->currency
                        ?? 'INR',

                    'reference_type' =>
                        FinancialAdjustment::class,

                    'reference_id' =>
                        $reversal->id,

                    'reference_code' =>
                        $reversal
                            ->adjustment_number,

                    'description' =>
                        $reason,

                    'created_by' =>
                        $reversedBy?->id,

                    'meta' => [
                        'reversal_of' =>
                            $locked
                                ->adjustment_number,
                    ],
                ];

                if (
                    $reverseDirection
                    === 'credit'
                ) {
                    $transaction =
                        $this->wallet
                            ->creditAvailable(
                                $user,
                                (float) $locked
                                    ->amount,
                                'adjustment_reversal',
                                $reference
                            );
                } else {
                    $transaction =
                        $this->wallet
                            ->debitAvailable(
                                $user,
                                (float) $locked
                                    ->amount,
                                'adjustment_reversal',
                                $reference
                            );
                }

                $reversal->update([
                    'wallet_transaction_id' =>
                        $transaction->id,
                ]);

                $locked->update([
                    'status' =>
                        'reversed',

                    'reversed_by' =>
                        $reversedBy?->id,

                    'reversed_at' =>
                        now(),
                ]);

                return $reversal->fresh([
                    'walletTransaction',
                    'reversalOf',
                ]);
            }
        );
    }

    private function generateNumber(): string
    {
        return 'ADJ-'
            .now()->format('Ymd-His')
            .'-'
            .strtoupper(
                substr(
                    (string) Str::uuid(),
                    0,
                    8
                )
            );
    }
}
