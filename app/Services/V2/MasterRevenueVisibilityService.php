<?php

namespace App\Services\V2;

use App\Models\Core\Label;
use Illuminate\Support\Facades\DB;

class MasterRevenueVisibilityService
{
    public function summary(
        Label $label,
        ?string $statementMonth = null
    ): array {
        $isMaster =
            $label->parent_label_id === null;

        $own = $this->statementTotals(
            $label->id,
            $statementMonth
        );

        if (! $isMaster) {
            $share = DB::table(
                'label_revenue_shares'
            )
                ->where(
                    'beneficiary_type',
                    'label'
                )
                ->where(
                    'beneficiary_id',
                    $label->id
                )
                ->where(
                    'is_active',
                    true
                )
                ->orderByDesc('id')
                ->first();

            return [
                'is_master' => false,
                'managed_revenue' =>
                    (float) $own['net'],
                'allocated_revenue' =>
                    (float) $own['net'],
                'retained_revenue' => 0.0,
                'payable_revenue' =>
                    (float) $own['net'],
                'gross_statement_revenue' =>
                    (float) $own['gross'],
                'share_percent' =>
                    $share &&
                    (bool) $share
                        ->show_revenue_share
                        ? (float)
                            $share
                                ->revenue_share_percent
                        : null,
                'share_visible' =>
                    $share
                        ? (bool)
                            $share
                                ->show_revenue_share
                        : false,
                'children' => [],
            ];
        }

        $childLabels = Label::query()
            ->where(
                'parent_label_id',
                $label->id
            )
            ->whereNull('deleted_at')
            ->get([
                'id',
                'name',
            ]);

        $shares = DB::table(
            'label_revenue_shares'
        )
            ->where(
                'master_label_id',
                $label->id
            )
            ->where(
                'beneficiary_type',
                'label'
            )
            ->where(
                'is_active',
                true
            )
            ->get()
            ->keyBy('beneficiary_id');

        $children = [];
        $allocated = 0.0;
        $sourceRevenue = 0.0;

        foreach ($childLabels as $child) {
            $totals =
                $this->statementTotals(
                    $child->id,
                    $statementMonth
                );

            $childNet =
                (float) $totals['net'];

            $share =
                $shares->get(
                    $child->id
                );

            $percent =
                $share
                    ? (float)
                        $share
                            ->revenue_share_percent
                    : 0.0;

            /*
             * Child statement contains the
             * already-allocated beneficiary
             * amount.
             *
             * Reconstruct source/master-managed
             * revenue only when percentage > 0.
             */
            $grossSource =
                $percent > 0
                    ? $childNet /
                        ($percent / 100)
                    : 0.0;

            $allocated += $childNet;
            $sourceRevenue +=
                $grossSource;

            $children[] = [
                'id' => $child->id,
                'name' => $child->name,
                'allocated_revenue' =>
                    $childNet,
                'managed_revenue' =>
                    $grossSource,
                'master_retained' =>
                    max(
                        0,
                        $grossSource -
                            $childNet
                    ),
                'share_percent' =>
                    $percent,
                'share_visible' =>
                    $share
                        ? (bool)
                            $share
                                ->show_revenue_share
                        : true,
            ];
        }

        /*
         * Master statement is authoritative
         * for actual master payable.
         *
         * Never add managed revenue to payable:
         * that would double count child revenue.
         */
        $masterPayable =
            (float) $own['net'];

        return [
            'is_master' => true,

            'managed_revenue' =>
                $sourceRevenue,

            'allocated_revenue' =>
                $allocated,

            'retained_revenue' =>
                $masterPayable,

            'payable_revenue' =>
                $masterPayable,

            'gross_statement_revenue' =>
                (float) $own['gross'],

            'share_percent' => null,
            'share_visible' => false,

            'children' => $children,
        ];
    }

    private function statementTotals(
        int $labelId,
        ?string $statementMonth = null
    ): array {
        if (
            ! DB::getSchemaBuilder()
                ->hasTable(
                    'royalty_statements'
                )
        ) {
            return [
                'gross' => 0.0,
                'net' => 0.0,
            ];
        }

        $query = DB::table(
            'royalty_statements'
        )
            ->where(
                'payee_type',
                'label'
            )
            ->where(
                'payee_id',
                $labelId
            );

        if (
            $statementMonth !== null
            && $statementMonth !== ''
        ) {
            $query->where(
                'statement_month',
                $statementMonth
            );
        }

        return [
            'gross' =>
                (float)
                    (clone $query)
                        ->sum(
                            'gross_earnings'
                        ),

            'net' =>
                (float)
                    (clone $query)
                        ->sum(
                            'net_payable'
                        ),
        ];
    }
}
