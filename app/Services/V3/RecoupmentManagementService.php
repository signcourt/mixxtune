<?php

namespace App\Services\V3;

use App\Models\Finance\RecoupmentExpense;
use App\Models\Finance\RecoupmentPlan;
use App\Models\Finance\RecoupmentRecovery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecoupmentManagementService
{
    public function __construct(
        private readonly RecoupmentCalculationService $calculator
    ) {
    }

    public function createPlan(
        User $user,
        array $data,
        ?User $createdBy = null
    ): RecoupmentPlan {
        $basePercentage = round(
            (float) ($data['base_percentage'] ?? 0),
            4
        );

        $uplift = round(
            (float) (
                $data['recovery_uplift_percentage'] ?? 0
            ),
            4
        );

        if ($basePercentage < 0 || $basePercentage > 100) {
            throw ValidationException::withMessages([
                'base_percentage' =>
                    'Base percentage must be between 0 and 100.',
            ]);
        }

        if ($uplift < 0 || $uplift > $basePercentage) {
            throw ValidationException::withMessages([
                'recovery_uplift_percentage' =>
                    'Recovery uplift cannot exceed the base percentage.',
            ]);
        }

        return DB::transaction(function () use (
            $user,
            $data,
            $createdBy,
            $basePercentage,
            $uplift
        ) {
            $activeExists = RecoupmentPlan::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if ($activeExists) {
                throw ValidationException::withMessages([
                    'user_id' =>
                        'This account already has an active recoupment plan.',
                ]);
            }

            $plan = RecoupmentPlan::create([
                'user_id' => $user->id,
                'label_id' => $data['label_id'] ?? null,
                'artist_id' => $data['artist_id'] ?? null,
                'plan_number' =>
                    $data['plan_number']
                    ?? $this->generatePlanNumber(),
                'title' =>
                    $data['title']
                    ?? 'Recoupment Plan',
                'base_percentage' =>
                    $basePercentage,
                'recovery_uplift_percentage' =>
                    $uplift,
                'maximum_recovery_percentage' =>
                    isset(
                        $data['maximum_recovery_percentage']
                    )
                        ? round(
                            (float)
                            $data[
                                'maximum_recovery_percentage'
                            ],
                            4
                        )
                        : null,
                'total_recoverable_amount' => 0,
                'total_recovered_amount' => 0,
                'outstanding_amount' => 0,
                'recovery_method' =>
                    $data['recovery_method']
                    ?? 'percentage',
                'status' => 'active',
                'starts_on' =>
                    $data['starts_on']
                    ?? now()->toDateString(),
                'notes' =>
                    $data['notes'] ?? null,
                'created_by' =>
                    $createdBy?->id,
            ]);

            if (
                isset($data['initial_amount'])
                && (float) $data['initial_amount'] > 0
            ) {
                $this->addExpense(
                    $plan,
                    [
                        'category' =>
                            $data['initial_category']
                            ?? 'advance',
                        'reference_number' =>
                            $data['reference_number']
                            ?? null,
                        'title' =>
                            $data['initial_title']
                            ?? 'Initial Advance',
                        'description' =>
                            $data['initial_description']
                            ?? null,
                        'amount' =>
                            (float) $data['initial_amount'],
                        'is_recoverable' => true,
                        'expense_date' =>
                            $data['expense_date']
                            ?? now()->toDateString(),
                    ],
                    $createdBy
                );
            }

            return $plan->fresh();
        });
    }

    public function addExpense(
        RecoupmentPlan $plan,
        array $data,
        ?User $createdBy = null
    ): RecoupmentExpense {
        if ($plan->status === 'cancelled') {
            throw ValidationException::withMessages([
                'plan' =>
                    'Cannot add expense to a cancelled plan.',
            ]);
        }

        $amount = round(
            (float) ($data['amount'] ?? 0),
            8
        );

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' =>
                    'Expense amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use (
            $plan,
            $data,
            $createdBy,
            $amount
        ) {
            $expense = RecoupmentExpense::create([
                'recoupment_plan_id' => $plan->id,
                'category' =>
                    $data['category'] ?? 'other',
                'reference_number' =>
                    $data['reference_number'] ?? null,
                'title' =>
                    $data['title'] ?? 'Recoverable Expense',
                'description' =>
                    $data['description'] ?? null,
                'amount' => $amount,
                'is_recoverable' =>
                    (bool) ($data['is_recoverable'] ?? true),
                'expense_date' =>
                    $data['expense_date']
                    ?? now()->toDateString(),
                'created_by' =>
                    $createdBy?->id,
            ]);

            $this->recalculatePlanTotals($plan);

            return $expense;
        });
    }

    public function recalculatePlanTotals(
        RecoupmentPlan $plan
    ): RecoupmentPlan {
        $recoverableTotal = round(
            (float) $plan->expenses()
                ->where('is_recoverable', true)
                ->sum('amount'),
            8
        );

        $recoveredTotal = round(
            (float) $plan->recoveries()
                ->sum('applied_recovery_amount'),
            8
        );

        $outstanding = max(
            0,
            round(
                $recoverableTotal - $recoveredTotal,
                8
            )
        );

        $updates = [
            'total_recoverable_amount' =>
                $recoverableTotal,
            'total_recovered_amount' =>
                $recoveredTotal,
            'outstanding_amount' =>
                $outstanding,
        ];

        if (
            $outstanding <= 0
            && $recoverableTotal > 0
        ) {
            $updates['status'] = 'completed';
            $updates['completed_on'] =
                now()->toDateString();
        } elseif (
            $outstanding > 0
            && $plan->status === 'completed'
        ) {
            /*
             * If a new recoverable expense is added after
             * completion, reactivate the same plan.
             */
            $updates['status'] = 'active';
            $updates['completed_on'] = null;
        }

        $plan->update($updates);

        return $plan->fresh();
    }

    public function applyRecovery(
        RecoupmentPlan $plan,
        float $sourceAmount,
        array $context = []
    ): ?RecoupmentRecovery {
        $plan = $plan->fresh();

        if (! $plan->isActive()) {
            return null;
        }

        $preview = $this->calculator->preview(
            $plan,
            $sourceAmount
        );

        if (
            (float)
            $preview['applied_recovery_amount']
            <= 0
        ) {
            return null;
        }

        return DB::transaction(function () use (
            $plan,
            $preview,
            $context
        ) {
            $lockedPlan = RecoupmentPlan::query()
                ->whereKey($plan->id)
                ->lockForUpdate()
                ->firstOrFail();

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

            /*
             * Automated royalty/report posting may be retried.
             * Return the original recovery instead of charging
             * the account twice.
             *
             * The database unique constraint is the final
             * integrity guard for the same plan/source pair.
             */
            if ($idempotencyKey !== null) {
                $existingRecovery =
                    RecoupmentRecovery::query()
                        ->where(
                            'recoupment_plan_id',
                            $lockedPlan->id
                        )
                        ->where(
                            'idempotency_key',
                            $idempotencyKey
                        )
                        ->first();

                if ($existingRecovery) {
                    return $existingRecovery;
                }
            }

            /*
             * Recalculate after obtaining row lock so two
             * simultaneous postings cannot over-recover.
             */
            $freshPreview = $this->calculator->preview(
                $lockedPlan,
                (float) $preview['source_amount']
            );

            if (
                (float)
                $freshPreview[
                    'applied_recovery_amount'
                ]
                <= 0
            ) {
                return null;
            }

            $recovery = RecoupmentRecovery::create([
                'recoupment_plan_id' =>
                    $lockedPlan->id,
                'royalty_statement_id' =>
                    $context[
                        'royalty_statement_id'
                    ] ?? null,
                'wallet_transaction_id' =>
                    $context[
                        'wallet_transaction_id'
                    ] ?? null,
                'source_amount' =>
                    $freshPreview['source_amount'],
                'base_percentage' =>
                    $freshPreview[
                        'base_percentage'
                    ],
                'recovery_percentage' =>
                    $freshPreview[
                        'recovery_percentage'
                    ],
                'calculated_recovery_amount' =>
                    $freshPreview[
                        'calculated_recovery_amount'
                    ],
                'applied_recovery_amount' =>
                    $freshPreview[
                        'applied_recovery_amount'
                    ],
                'outstanding_before' =>
                    $freshPreview[
                        'outstanding_before'
                    ],
                'outstanding_after' =>
                    $freshPreview[
                        'outstanding_after'
                    ],
                'reporting_month' =>
                    $context['reporting_month']
                    ?? null,
                'reference' =>
                    $context['reference']
                    ?? null,
                'idempotency_key' =>
                    $idempotencyKey,
            ]);

            $lockedPlan->update([
                'total_recovered_amount' =>
                    round(
                        (float)
                        $lockedPlan
                            ->total_recovered_amount
                        + (float)
                        $freshPreview[
                            'applied_recovery_amount'
                        ],
                        8
                    ),
                'outstanding_amount' =>
                    $freshPreview[
                        'outstanding_after'
                    ],
            ]);

            if (
                (float)
                $freshPreview[
                    'outstanding_after'
                ]
                <= 0
            ) {
                $lockedPlan->update([
                    'status' => 'completed',
                    'completed_on' =>
                        now()->toDateString(),
                ]);
            }

            return $recovery;
        });
    }

    /**
     * Apply recoupment to an already allocated royalty
     * statement payable.
     *
     * Unlike applyRecovery(), the supplied amount here is the
     * beneficiary's existing statement payable, not raw/source
     * revenue. The calculator reconstructs the contractual
     * source base before applying the recovery percentage.
     *
     * Idempotency lookup intentionally happens after locking
     * the plan and BEFORE checking active/completed state.
     * Therefore a retry can still return the original recovery
     * even if the first posting completed the plan.
     */
    public function applyStatementRecovery(
        RecoupmentPlan $plan,
        float $allocatedGross,
        float $currentPayable,
        array $context = []
    ): ?RecoupmentRecovery {
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

        return DB::transaction(function () use (
            $plan,
            $allocatedGross,
            $currentPayable,
            $context,
            $idempotencyKey
        ) {
            $lockedPlan = RecoupmentPlan::query()
                ->whereKey($plan->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($idempotencyKey !== null) {
                $existingRecovery =
                    RecoupmentRecovery::query()
                        ->where(
                            'recoupment_plan_id',
                            $lockedPlan->id
                        )
                        ->where(
                            'idempotency_key',
                            $idempotencyKey
                        )
                        ->first();

                if ($existingRecovery) {
                    return $existingRecovery;
                }
            }

            if (! $lockedPlan->isActive()) {
                return null;
            }

            $preview =
                $this->calculator
                    ->previewAllocatedStatement(
                        $lockedPlan,
                        $allocatedGross,
                        $currentPayable
                    );

            if (
                (float)
                $preview[
                    'applied_recovery_amount'
                ]
                <= 0
            ) {
                return null;
            }

            $recovery = RecoupmentRecovery::create([
                'recoupment_plan_id' =>
                    $lockedPlan->id,

                'royalty_statement_id' =>
                    $context[
                        'royalty_statement_id'
                    ] ?? null,

                'wallet_transaction_id' =>
                    $context[
                        'wallet_transaction_id'
                    ] ?? null,

                /*
                 * For audit purposes source_amount stores the
                 * reconstructed contractual source revenue.
                 */
                'source_amount' =>
                    $preview[
                        'reconstructed_source_amount'
                    ],

                'base_percentage' =>
                    $preview[
                        'base_percentage'
                    ],

                'recovery_percentage' =>
                    $preview[
                        'recovery_percentage'
                    ],

                'calculated_recovery_amount' =>
                    $preview[
                        'calculated_recovery_amount'
                    ],

                'applied_recovery_amount' =>
                    $preview[
                        'applied_recovery_amount'
                    ],

                'outstanding_before' =>
                    $preview[
                        'outstanding_before'
                    ],

                'outstanding_after' =>
                    $preview[
                        'outstanding_after'
                    ],

                'reporting_month' =>
                    $context[
                        'reporting_month'
                    ] ?? null,

                'reference' =>
                    $context[
                        'reference'
                    ] ?? null,

                'idempotency_key' =>
                    $idempotencyKey,
            ]);

            $lockedPlan->update([
                'total_recovered_amount' =>
                    round(
                        (float)
                        $lockedPlan
                            ->total_recovered_amount
                        + (float)
                        $preview[
                            'applied_recovery_amount'
                        ],
                        8
                    ),

                'outstanding_amount' =>
                    $preview[
                        'outstanding_after'
                    ],
            ]);

            if (
                (float)
                $preview[
                    'outstanding_after'
                ]
                <= 0
            ) {
                $lockedPlan->update([
                    'status' => 'completed',

                    'completed_on' =>
                        now()->toDateString(),
                ]);
            }

            return $recovery;
        });
    }

    public function cancelPlan(
        RecoupmentPlan $plan,
        ?User $completedBy = null
    ): RecoupmentPlan {
        if ($plan->status === 'completed') {
            throw ValidationException::withMessages([
                'plan' =>
                    'Completed recoupment plan cannot be cancelled.',
            ]);
        }

        $plan->update([
            'status' => 'cancelled',
            'completed_on' =>
                now()->toDateString(),
            'completed_by' =>
                $completedBy?->id,
        ]);

        return $plan->fresh();
    }

    private function generatePlanNumber(): string
    {
        return 'RCP-'
            .now()->format('Ymd-His')
            .'-'
            .strtoupper(
                substr(
                    bin2hex(random_bytes(3)),
                    0,
                    6
                )
            );
    }
}
