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

        $beneficiary =
            $this->boundPercentage(
                (float) (
                    $label
                        ->royalty_share_percentage
                    ?? 100
                )
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
        return [
            'source' =>
                'system_default',

            'agreement_id' =>
                null,

            'beneficiary_percentage' =>
                100.0,

            'company_retention_percentage' =>
                0.0,

            'parent_commission_percentage' =>
                0.0,

            'currency' =>
                'INR',
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
