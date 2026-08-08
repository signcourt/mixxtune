<?php

namespace App\Services\V2;

use App\Models\Core\Label;
use App\Models\Finance\LabelRevenueShare;
use Illuminate\Validation\ValidationException;

class MasterLabelRevenueService
{
    public function calculate(
        Label $masterLabel,
        string $beneficiaryType,
        int $beneficiaryId,
        float $grossRevenue
    ): array {
        if ($masterLabel->parent_label_id !== null) {
            throw ValidationException::withMessages([
                'master_label' =>
                    'Revenue distribution can only originate from a master label.',
            ]);
        }

        $share = LabelRevenueShare::query()
            ->where(
                'master_label_id',
                $masterLabel->id
            )
            ->where(
                'beneficiary_type',
                $beneficiaryType
            )
            ->where(
                'beneficiary_id',
                $beneficiaryId
            )
            ->where('is_active', true)
            ->where(function ($query) {
                $query
                    ->whereNull('effective_from')
                    ->orWhere(
                        'effective_from',
                        '<=',
                        now()->toDateString()
                    );
            })
            ->where(function ($query) {
                $query
                    ->whereNull('effective_to')
                    ->orWhere(
                        'effective_to',
                        '>=',
                        now()->toDateString()
                    );
            })
            ->first();

        /*
         * No configured child split:
         * master retains 100%.
         */
        $childPercent = $share
            ? (float) $share->revenue_share_percent
            : 0.0;

        $masterPercent =
            100.0 - $childPercent;

        $gross = round(
            $grossRevenue,
            8
        );

        $childRevenue = round(
            $gross * ($childPercent / 100),
            8
        );

        /*
         * Calculate master as the difference so
         * rounding can never create extra revenue.
         */
        $masterRevenue = round(
            $gross - $childRevenue,
            8
        );

        return [
            'master_label_id' =>
                $masterLabel->id,

            'beneficiary_type' =>
                $beneficiaryType,

            'beneficiary_id' =>
                $beneficiaryId,

            'gross_revenue' =>
                $gross,

            'child_share_percent' =>
                $childPercent,

            'master_share_percent' =>
                $masterPercent,

            'child_revenue' =>
                $childRevenue,

            'master_revenue' =>
                $masterRevenue,

            'show_revenue_share' =>
                $share
                    ? (bool) $share->show_revenue_share
                    : false,

            'share_configured' =>
                $share !== null,
        ];
    }
}
