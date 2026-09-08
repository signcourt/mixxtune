<?php

namespace App\Services\V3;

use App\Models\Finance\RecoupmentPlan;

class RecoupmentCalculationService
{
    /**
     * Calculate the effective beneficiary percentage while
     * a recoupment plan is active.
     *
     * Example:
     * base beneficiary percentage = 20
     * recovery uplift            = 10
     * effective beneficiary      = 10
     *
     * The extra 10 percentage points are retained for recovery.
     */
    public function effectiveBeneficiaryPercentage(
        RecoupmentPlan $plan
    ): float {
        $base = (float) $plan->base_percentage;

        if (! $plan->isActive()) {
            return $this->clampPercentage($base);
        }

        /*
         * Use the same capped recovery percentage used by
         * recovery calculations. This keeps effective payable
         * percentage consistent with maximum recovery limits.
         */
        $recoveryPercentage =
            $this->recoveryPercentage($plan);

        return $this->clampPercentage(
            $base - $recoveryPercentage
        );
    }

    /**
     * Percentage of source revenue assigned to recoupment.
     */
    public function recoveryPercentage(
        RecoupmentPlan $plan
    ): float {
        if (! $plan->isActive()) {
            return 0.0;
        }

        $uplift = max(
            0,
            (float) $plan->recovery_uplift_percentage
        );

        $maximum = $plan->maximum_recovery_percentage;

        if ($maximum !== null) {
            $uplift = min(
                $uplift,
                max(0, (float) $maximum)
            );
        }

        /*
         * Recovery cannot exceed the beneficiary's contractual
         * base share, otherwise beneficiary payable becomes
         * negative.
         */
        $uplift = min(
            $uplift,
            max(0, (float) $plan->base_percentage)
        );

        return $this->clampPercentage($uplift);
    }

    /**
     * Preview the recovery effect against a revenue amount.
     *
     * No database writes are performed here.
     */
    public function preview(
        RecoupmentPlan $plan,
        float $sourceAmount
    ): array {
        $sourceAmount = max(0, $sourceAmount);

        $basePercentage = $this->clampPercentage(
            (float) $plan->base_percentage
        );

        $recoveryPercentage = $this->recoveryPercentage(
            $plan
        );

        $effectiveBeneficiaryPercentage =
            $this->effectiveBeneficiaryPercentage(
                $plan
            );

        $baseBeneficiaryAmount = round(
            $sourceAmount * ($basePercentage / 100),
            8
        );

        $calculatedRecovery = round(
            $sourceAmount * ($recoveryPercentage / 100),
            8
        );

        $outstanding = max(
            0,
            (float) $plan->outstanding_amount
        );

        /*
         * Never recover more than the remaining outstanding
         * balance.
         */
        $appliedRecovery = min(
            $calculatedRecovery,
            $outstanding
        );

        $beneficiaryPayable = max(
            0,
            round(
                $baseBeneficiaryAmount - $appliedRecovery,
                8
            )
        );

        $outstandingAfter = max(
            0,
            round(
                $outstanding - $appliedRecovery,
                8
            )
        );

        return [
            'source_amount' =>
                round($sourceAmount, 8),

            'base_percentage' =>
                $basePercentage,

            'recovery_percentage' =>
                $recoveryPercentage,

            'effective_beneficiary_percentage' =>
                $effectiveBeneficiaryPercentage,

            'base_beneficiary_amount' =>
                $baseBeneficiaryAmount,

            'calculated_recovery_amount' =>
                $calculatedRecovery,

            'applied_recovery_amount' =>
                round($appliedRecovery, 8),

            'beneficiary_payable' =>
                round($beneficiaryPayable, 8),

            'outstanding_before' =>
                round($outstanding, 8),

            'outstanding_after' =>
                $outstandingAfter,

            'will_complete' =>
                $outstanding > 0
                && $outstandingAfter <= 0,
        ];
    }

    /**
     * Preview recoupment against an already allocated royalty
     * statement payable.
     *
     * Royalty statements are generated after ownership/revenue
     * share allocation. Therefore statement payable must NOT be
     * multiplied by base_percentage a second time.
     *
     * Example:
     * source gross       = 2500
     * beneficiary base   = 70%
     * statement payable  = 1750
     * recovery uplift    = 10 percentage points
     *
     * Reconstructed source gross = 1750 / 0.70 = 2500
     * Recovery                  = 2500 * 0.10 = 250
     * Final payable             = 1750 - 250 = 1500
     */
    public function previewStatementPayable(
        RecoupmentPlan $plan,
        float $statementPayable
    ): array {
        $statementPayable = max(
            0,
            round($statementPayable, 8)
        );

        $basePercentage = $this->clampPercentage(
            (float) $plan->base_percentage
        );

        $recoveryPercentage =
            $this->recoveryPercentage($plan);

        /*
         * A zero contractual base cannot be used to reconstruct
         * source gross. In that case there is no percentage-point
         * recovery to apply to this statement.
         */
        if (
            $statementPayable <= 0
            || $basePercentage <= 0
            || $recoveryPercentage <= 0
        ) {
            return [
                'statement_payable_before' =>
                    $statementPayable,

                'reconstructed_source_amount' =>
                    0.0,

                'base_percentage' =>
                    $basePercentage,

                'recovery_percentage' =>
                    $recoveryPercentage,

                'calculated_recovery_amount' =>
                    0.0,

                'applied_recovery_amount' =>
                    0.0,

                'statement_payable_after' =>
                    $statementPayable,

                'outstanding_before' =>
                    round(
                        max(
                            0,
                            (float)
                            $plan->outstanding_amount
                        ),
                        8
                    ),

                'outstanding_after' =>
                    round(
                        max(
                            0,
                            (float)
                            $plan->outstanding_amount
                        ),
                        8
                    ),

                'will_complete' => false,
            ];
        }

        $sourceAmount = round(
            $statementPayable
            / ($basePercentage / 100),
            8
        );

        $calculatedRecovery = round(
            $sourceAmount
            * ($recoveryPercentage / 100),
            8
        );

        $outstanding = round(
            max(
                0,
                (float) $plan->outstanding_amount
            ),
            8
        );

        /*
         * Recovery is capped by BOTH:
         *
         * 1. remaining recoverable balance
         * 2. this statement's payable amount
         *
         * The second cap guarantees that recoupment can never
         * create a negative royalty payable.
         */
        $appliedRecovery = round(
            min(
                $calculatedRecovery,
                $outstanding,
                $statementPayable
            ),
            8
        );

        $statementPayableAfter = round(
            max(
                0,
                $statementPayable
                - $appliedRecovery
            ),
            8
        );

        $outstandingAfter = round(
            max(
                0,
                $outstanding
                - $appliedRecovery
            ),
            8
        );

        return [
            'statement_payable_before' =>
                $statementPayable,

            'reconstructed_source_amount' =>
                $sourceAmount,

            'base_percentage' =>
                $basePercentage,

            'recovery_percentage' =>
                $recoveryPercentage,

            'calculated_recovery_amount' =>
                $calculatedRecovery,

            'applied_recovery_amount' =>
                $appliedRecovery,

            'statement_payable_after' =>
                $statementPayableAfter,

            'outstanding_before' =>
                $outstanding,

            'outstanding_after' =>
                $outstandingAfter,

            'will_complete' =>
                $outstanding > 0
                && $outstandingAfter <= 0,
        ];
    }

    /**
     * Preview recoupment for a royalty statement whose gross
     * has already been allocated to the beneficiary.
     *
     * $allocatedGross is the beneficiary amount BEFORE
     * statement-level commission/tax/other deductions.
     *
     * $currentPayable is the amount that remains payable BEFORE
     * this recoupment deduction.
     *
     * Source revenue is reconstructed from allocated gross and
     * contractual beneficiary percentage. Recovery is then
     * capped by both outstanding recoupment and current payable.
     */
    public function previewAllocatedStatement(
        RecoupmentPlan $plan,
        float $allocatedGross,
        float $currentPayable
    ): array {
        $allocatedGross = max(
            0,
            round($allocatedGross, 8)
        );

        $currentPayable = max(
            0,
            round($currentPayable, 8)
        );

        $basePercentage = $this->clampPercentage(
            (float) $plan->base_percentage
        );

        $recoveryPercentage =
            $this->recoveryPercentage($plan);

        $outstanding = round(
            max(
                0,
                (float) $plan->outstanding_amount
            ),
            8
        );

        if (
            $allocatedGross <= 0
            || $currentPayable <= 0
            || $basePercentage <= 0
            || $recoveryPercentage <= 0
        ) {
            return [
                'allocated_gross' =>
                    $allocatedGross,

                'current_payable_before' =>
                    $currentPayable,

                'reconstructed_source_amount' =>
                    0.0,

                'base_percentage' =>
                    $basePercentage,

                'recovery_percentage' =>
                    $recoveryPercentage,

                'calculated_recovery_amount' =>
                    0.0,

                'applied_recovery_amount' =>
                    0.0,

                'current_payable_after' =>
                    $currentPayable,

                'outstanding_before' =>
                    $outstanding,

                'outstanding_after' =>
                    $outstanding,

                'will_complete' =>
                    false,
            ];
        }

        $sourceAmount = round(
            $allocatedGross
            / ($basePercentage / 100),
            8
        );

        $calculatedRecovery = round(
            $sourceAmount
            * ($recoveryPercentage / 100),
            8
        );

        $appliedRecovery = round(
            min(
                $calculatedRecovery,
                $outstanding,
                $currentPayable
            ),
            8
        );

        $currentPayableAfter = round(
            max(
                0,
                $currentPayable
                - $appliedRecovery
            ),
            8
        );

        $outstandingAfter = round(
            max(
                0,
                $outstanding
                - $appliedRecovery
            ),
            8
        );

        return [
            'allocated_gross' =>
                $allocatedGross,

            'current_payable_before' =>
                $currentPayable,

            'reconstructed_source_amount' =>
                $sourceAmount,

            'base_percentage' =>
                $basePercentage,

            'recovery_percentage' =>
                $recoveryPercentage,

            'calculated_recovery_amount' =>
                $calculatedRecovery,

            'applied_recovery_amount' =>
                $appliedRecovery,

            'current_payable_after' =>
                $currentPayableAfter,

            'outstanding_before' =>
                $outstanding,

            'outstanding_after' =>
                $outstandingAfter,

            'will_complete' =>
                $outstanding > 0
                && $outstandingAfter <= 0,
        ];
    }

    private function clampPercentage(float $value): float
    {
        return round(
            min(100, max(0, $value)),
            4
        );
    }
}
