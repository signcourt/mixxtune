<?php

namespace App\Services\V3;

use App\Models\Finance\RecoupmentExpense;
use App\Models\Finance\RecoupmentPlan;
use App\Models\Finance\RecoupmentRecovery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        /*
         * MIXXTUNE_RECOVERY_NET_BASIS_V1: base_percentage is display-only and
         * defaults to 100; recovery_uplift_percentage is "Recovery % of earning"
         * (0-100) and is NO LONGER capped by the base share.
         */
        $basePercentage = round(
            (float) ($data['base_percentage'] ?? 100),
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

        if ($uplift < 0 || $uplift > 100) {
            throw ValidationException::withMessages([
                'recovery_uplift_percentage' =>
                    'Recovery % must be between 0 and 100.',
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
                // W170: extra points taken off the applicable rate while the
                // advance is outstanding (0-100).
                'advance_charge_percentage' => max(0, min(100, round((float) ($data['advance_charge_percentage'] ?? 0), 4))),
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

    /*
     * MIXXTUNE_INSTANT_WALLET_SWEEP_V1
     *
     * Right after an advance is created, recover from the owner's EXISTING
     * wallet balance immediately (not just from future report earnings). Takes
     * recovery% of the current available balance, capped by the outstanding,
     * writes a recovery row and a wallet debit, and lowers the wallet. Runs in
     * its own transaction, is idempotent per plan+balance snapshot, and never
     * drives a wallet below zero.
     */
    public function sweepWalletBalance(RecoupmentPlan $plan, ?int $actorId = null): ?RecoupmentRecovery
    {
        return DB::transaction(function () use ($plan, $actorId) {
            $lockedPlan = RecoupmentPlan::query()->whereKey($plan->id)->lockForUpdate()->firstOrFail();

            if (! $lockedPlan->isActive()) {
                return null;
            }

            $outstanding = round((float) $lockedPlan->outstanding_amount, 8);
            if ($outstanding <= 0) {
                return null;
            }

            // The owner's own wallet (user-level; not a per-label/artist sub-wallet).
            $wallet = DB::table('wallets')
                ->where('user_id', $lockedPlan->user_id)
                ->whereNull('label_id')
                ->whereNull('artist_id')
                ->lockForUpdate()
                ->first();

            if (! $wallet || (float) $wallet->available_balance <= 0) {
                return null;
            }

            $recoveryPct = $this->calculator->recoveryPercentage($lockedPlan);
            $available = round((float) $wallet->available_balance, 8);

            /*
             * MIXXTUNE_ADVANCE_CHARGE_ON_WALLET_V1
             *
             * The advance charge is a flat % of the net amount that lands in the
             * wallet (whatever the source - digital/YouTube publisher, IPRS, ...),
             * NOT a reduction of the label rate. Of the base we sweep:
             *   commission = base * charge%   -> publisher/company profit
             *   recoup     = base - commission -> reduces the advance
             * recoup is still capped by the outstanding advance.
             */
            $charge = round((float) $lockedPlan->advance_charge_percentage, 8);
            // base = recovery% of the available balance, capped by balance
            $base = round(min($available * ($recoveryPct / 100), $available), 8);
            $commission = $charge > 0 ? round($base * ($charge / 100), 8) : 0.0;
            $recoupBase = round($base - $commission, 8);
            // the advance only takes what it still owes
            $take = round(min($recoupBase, $outstanding), 8);
            if ($take <= 0 && $commission <= 0) {
                return null;
            }
            // total pulled from the wallet = advance repayment + commission
            $sweep = round($take + $commission, 8);

            $balanceBefore = round((float) $wallet->available_balance, 8);
            DB::table('wallets')->where('id', $wallet->id)->update([
                'available_balance' => DB::raw('available_balance - ('.$sweep.')'),
                'lifetime_debits' => DB::raw('lifetime_debits + '.$sweep),
                'updated_at' => now(),
            ]);

            // Post the advance-repayment debit first, then the commission debit.
            $runningBefore = $balanceBefore;
            $txId = null;
            if ($take > 0) {
                $runningAfter = round($runningBefore - $take, 8);
                $txId = DB::table('wallet_transactions')->insertGetId([
                    'public_id' => (string) Str::ulid(),
                    'wallet_id' => $wallet->id,
                    'user_id' => $lockedPlan->user_id,
                    'artist_id' => $lockedPlan->artist_id,
                    'label_id' => $lockedPlan->label_id,
                    'transaction_type' => 'recoupment_recovery',
                    'direction' => 'debit',
                    'amount' => $take,
                    'currency' => (string) $wallet->currency,
                    'balance_before' => (string) $runningBefore,
                    'balance_after' => (string) $runningAfter,
                    'reference_type' => 'recoupment_plan',
                    'reference_id' => $lockedPlan->id,
                    'description' => 'Advance recovered from wallet balance ('.($lockedPlan->plan_number ?: '#'.$lockedPlan->id).')',
                    'status' => 'posted',
                    'effective_at' => now(),
                    'posted_at' => now(),
                    'metadata' => json_encode(['recoupment_plan_id' => $lockedPlan->id, 'source' => 'instant_wallet_sweep']),
                    'created_by' => $actorId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $runningBefore = $runningAfter;
            }
            if ($commission > 0) {
                $runningAfter = round($runningBefore - $commission, 8);
                DB::table('wallet_transactions')->insert([
                    'public_id' => (string) Str::ulid(),
                    'wallet_id' => $wallet->id,
                    'user_id' => $lockedPlan->user_id,
                    'artist_id' => $lockedPlan->artist_id,
                    'label_id' => $lockedPlan->label_id,
                    'transaction_type' => 'advance_charge_commission',
                    'direction' => 'debit',
                    'amount' => $commission,
                    'currency' => (string) $wallet->currency,
                    'balance_before' => (string) $runningBefore,
                    'balance_after' => (string) $runningAfter,
                    'reference_type' => 'recoupment_plan',
                    'reference_id' => $lockedPlan->id,
                    'description' => 'Advance charge ('.rtrim(rtrim(number_format($charge, 4), '0'), '.').'%) on wallet balance ('.($lockedPlan->plan_number ?: '#'.$lockedPlan->id).')',
                    'status' => 'posted',
                    'effective_at' => now(),
                    'posted_at' => now(),
                    'metadata' => json_encode(['recoupment_plan_id' => $lockedPlan->id, 'source' => 'instant_wallet_sweep', 'advance_charge_percentage' => $charge]),
                    'created_by' => $actorId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($take <= 0) {
                // only commission was taken; no recovery row to create
                $this->recalculatePlanTotals($lockedPlan);

                return null;
            }

            $recovery = RecoupmentRecovery::create([
                'recoupment_plan_id' => $lockedPlan->id,
                'royalty_statement_id' => null,
                'wallet_transaction_id' => $txId,
                'source_amount' => $available,
                'base_percentage' => round((float) $lockedPlan->base_percentage, 8),
                'recovery_percentage' => $recoveryPct,
                'calculated_recovery_amount' => round($available * ($recoveryPct / 100), 8),
                'applied_recovery_amount' => $take,
                'outstanding_before' => $outstanding,
                'outstanding_after' => round($outstanding - $take, 8),
                'reporting_month' => now()->format('Y-m'),
                'reference' => 'Instant wallet sweep on advance creation',
                'idempotency_key' => 'wallet_sweep:'.$lockedPlan->id.':'.$txId,
            ]);

            $this->recalculatePlanTotals($lockedPlan);

            // MIXXTUNE_ADVANCE_RECOVERY_INVOICE_V1: one invoice per recovery.
            try {
                app(\App\Services\V2\InvoiceService::class)->generateFromRecovery($recovery, $actorId);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('advance-recovery invoice (sweep) failed: '.$e->getMessage(), ['recovery_id' => $recovery->id]);
            }

            return $recovery;
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

            // MIXXTUNE_ADVANCE_RECOVERY_INVOICE_V1: one invoice per recovery.
            try {
                app(\App\Services\V2\InvoiceService::class)->generateFromRecovery($recovery, $context['actor_id'] ?? null);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('advance-recovery invoice (statement) failed: '.$e->getMessage(), ['recovery_id' => $recovery->id]);
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
