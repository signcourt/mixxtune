<?php

namespace App\Services\V3;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AgreementResolver
{
    public function resolveLabelAgreement(
        ?int $labelId,
        ?string $saleMonth = null
    ): array {
        if (!$labelId) {
            return $this->defaultAgreement();
        }

        $agreement =
            $this->findStoredAgreement(
                $labelId,
                $saleMonth
            );

        if ($agreement) {
            return [
                'source' =>
                    'revenue_agreement',

                'agreement_id' =>
                    (int) $agreement->id,

                'beneficiary_percentage' =>
                    $this->boundPercentage(
                        (float) $agreement
                            ->beneficiary_percentage
                    ),

                'company_retention_percentage' =>
                    $this->boundPercentage(
                        (float) $agreement
                            ->company_retention_percentage
                    ),

                'parent_commission_percentage' =>
                    $this->boundPercentage(
                        (float) $agreement
                            ->parent_commission_percentage
                    ),

                'currency' =>
                    $agreement->currency
                    ?: 'INR',
            ];
        }

        return $this->legacyLabelFallback(
            $labelId
        );
    }

    private function findStoredAgreement(
        int $labelId,
        ?string $saleMonth
    ): ?object {
        if (
            !Schema::hasTable(
                'revenue_agreements'
            )
        ) {
            return null;
        }

        $effectiveDate =
            $this->normaliseEffectiveDate(
                $saleMonth
            );

        return DB::table(
            'revenue_agreements'
        )
            ->where(
                'subject_type',
                'label'
            )
            ->where(
                'subject_id',
                $labelId
            )
            ->where(
                'status',
                'active'
            )
            ->when(
                $effectiveDate,
                function (
                    $query
                ) use (
                    $effectiveDate
                ) {
                    $query
                        ->where(
                            function (
                                $builder
                            ) use (
                                $effectiveDate
                            ) {
                                $builder
                                    ->whereNull(
                                        'effective_from'
                                    )
                                    ->orWhereDate(
                                        'effective_from',
                                        '<=',
                                        $effectiveDate
                                    );
                            }
                        )
                        ->where(
                            function (
                                $builder
                            ) use (
                                $effectiveDate
                            ) {
                                $builder
                                    ->whereNull(
                                        'effective_to'
                                    )
                                    ->orWhereDate(
                                        'effective_to',
                                        '>=',
                                        $effectiveDate
                                    );
                            }
                        );
                }
            )
            ->orderByDesc('priority')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    private function legacyLabelFallback(
        int $labelId
    ): array {
        $label = DB::table('labels')
            ->where('id', $labelId)
            ->whereNull('deleted_at')
            ->first();

        if (!$label) {
            return $this->defaultAgreement();
        }

        /*
         * FF179_FINANCIAL_HOLD
         *
         * Missing commercial terms must never
         * silently become a 100% beneficiary payout.
         *
         * NULL means the account has no authoritative
         * configured agreement/rate and must remain
         * on financial hold.
         */
        /*
         * FF179R_CANONICAL_REVENUE_RATE
         *
         * labels.revenue_share_percentage is the
         * authoritative account-level commercial
         * rate used by the current platform.
         *
         * Do not fall back to legacy
         * royalty_share_percentage because old/default
         * values such as 100% are not evidence of an
         * approved commercial agreement.
         */
        if (
            $label->revenue_share_percentage === null
        ) {
            return $this->holdAgreement(
                'labels_fallback_missing_rate'
            );
        }

        $beneficiary =
            $this->boundPercentage(
                (float)
                    $label
                        ->revenue_share_percentage
            );

        return [
            'source' =>
                'labels_fallback',

            'agreement_id' =>
                null,

            'beneficiary_percentage' =>
                $beneficiary,

            'company_retention_percentage' =>
                round(
                    100 - $beneficiary,
                    4
                ),

            'parent_commission_percentage' =>
                $this->boundPercentage(
                    (float) (
                        $label
                            ->parent_commission_percentage
                        ?? 0
                    )
                ),

            'currency' =>
                $label->currency
                ?: 'INR',
        ];
    }

    private function defaultAgreement(): array
    {
        /*
         * FF179_FINANCIAL_HOLD
         *
         * No subject / no authoritative agreement
         * is not permission to pay 100%.
         */
        return $this->holdAgreement(
            'system_default_missing_rate'
        );
    }

    private function holdAgreement(
        string $source
    ): array {
        return [
            'source' => $source,

            'agreement_id' => null,

            /*
             * NULL is intentional:
             * unresolved commercial terms = HOLD.
             */
            'beneficiary_percentage' => null,

            'company_retention_percentage' => null,

            'parent_commission_percentage' => null,

            'currency' => 'INR',

            'financial_hold' => true,
        ];
    }

    private function normaliseEffectiveDate(
        ?string $saleMonth
    ): ?string {
        if (!$saleMonth) {
            return null;
        }

        try {
            return Carbon::parse(
                strlen($saleMonth) === 7
                    ? "{$saleMonth}-01"
                    : $saleMonth
            )->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function boundPercentage(
        float $percentage
    ): float {
        return round(
            max(
                0,
                min(
                    100,
                    $percentage
                )
            ),
            4
        );
    }
}
