<?php

namespace App\Services\V3;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RevenueCalculationService
{
    public function preview(
        ?string $saleMonth = null
    ): array {
        $query = DB::table('report_rows as rr')
            ->join(
                'releases as r',
                'r.id',
                '=',
                'rr.release_id'
            )
            ->leftJoin(
                'labels as l',
                'l.id',
                '=',
                'rr.label_id'
            )
            ->where(
                'rr.mapping_status',
                'mapped'
            )
            ->whereNotNull(
                'rr.release_id'
            );

        if (
            $saleMonth !== null
            && $saleMonth !== ''
        ) {
            $query->where(
                'rr.sale_month',
                $saleMonth
            );
        }

        $sourceRows = $query
            ->select([
                'rr.id',
                'rr.release_id',
                'rr.track_id',
                'rr.artist_id',
                'rr.label_id',
                'rr.sale_month',
                'rr.currency',
                'rr.earnings',

                'l.name as label_name',

                DB::raw(
                    'COALESCE(
                        l.royalty_share_percentage,
                        100
                    ) as beneficiary_percentage'
                ),

                DB::raw(
                    'COALESCE(
                        l.parent_commission_percentage,
                        0
                    ) as parent_commission_percentage'
                ),
            ])
            ->orderBy('rr.id')
            ->get();

        $calculations = $sourceRows->map(
            fn ($row) =>
                $this->calculateRow($row)
        );

        return [
            'summary' =>
                $this->buildSummary(
                    $calculations
                ),

            'by_month' =>
                $this->groupByMonth(
                    $calculations
                ),

            'by_label' =>
                $this->groupByLabel(
                    $calculations
                ),

            'rows_without_track' =>
                $calculations
                    ->whereNull(
                        'track_id'
                    )
                    ->count(),

            'calculation_rows' =>
                $calculations,
        ];
    }

    private function calculateRow(
        object $row
    ): array {
        $sourceRevenue = round(
            (float) $row->earnings,
            8
        );

        $beneficiaryPercentage =
            $this->boundPercentage(
                (float) $row
                    ->beneficiary_percentage
            );

        $parentCommissionPercentage =
            $this->boundPercentage(
                (float) $row
                    ->parent_commission_percentage
            );

        /*
         * Current legacy meaning:
         * beneficiary payable =
         * source net revenue
         * × royalty percentage.
         */
        $beneficiaryPool = round(
            $sourceRevenue
            * (
                $beneficiaryPercentage
                / 100
            ),
            8
        );

        $companyRetention = round(
            $sourceRevenue
            - $beneficiaryPool,
            8
        );

        $parentCommission = round(
            $beneficiaryPool
            * (
                $parentCommissionPercentage
                / 100
            ),
            8
        );

        $netBeneficiaryPool = round(
            $beneficiaryPool
            - $parentCommission,
            8
        );

        /*
         * Split allocation is previewed only when
         * a mapped track and active master splits
         * exist.
         */
        $artistAllocations =
            $row->track_id
                ? $this->calculateTrackSplits(
                    (int) $row->track_id,
                    $netBeneficiaryPool
                )
                : collect();

        $artistPayable = round(
            (float) $artistAllocations
                ->sum('amount'),
            8
        );

        /*
         * Current V3 preview assumes the configured
         * beneficiary pool is passed to contributors.
         * Any remainder stays unallocated until a
         * formal label/artist agreement rule exists.
         */
        $unallocated = round(
            $netBeneficiaryPool
            - $artistPayable,
            8
        );

        $reconciledTotal = round(
            $companyRetention
            + $parentCommission
            + $artistPayable
            + $unallocated,
            8
        );

        return [
            'report_row_id' =>
                (int) $row->id,

            'release_id' =>
                $row->release_id
                    ? (int) $row->release_id
                    : null,

            'track_id' =>
                $row->track_id
                    ? (int) $row->track_id
                    : null,

            'artist_id' =>
                $row->artist_id
                    ? (int) $row->artist_id
                    : null,

            'label_id' =>
                $row->label_id
                    ? (int) $row->label_id
                    : null,

            'label_name' =>
                $row->label_name
                ?: 'Unassigned Label',

            'sale_month' =>
                $row->sale_month,

            'currency' =>
                $row->currency
                ?: 'INR',

            'source_revenue' =>
                $sourceRevenue,

            'beneficiary_percentage' =>
                $beneficiaryPercentage,

            'beneficiary_pool' =>
                $beneficiaryPool,

            'company_retention' =>
                $companyRetention,

            'parent_commission_percentage' =>
                $parentCommissionPercentage,

            'parent_commission' =>
                $parentCommission,

            'net_beneficiary_pool' =>
                $netBeneficiaryPool,

            'artist_payable' =>
                $artistPayable,

            'unallocated_amount' =>
                $unallocated,

            'reconciled_total' =>
                $reconciledTotal,

            'difference' =>
                round(
                    $sourceRevenue
                    - $reconciledTotal,
                    8
                ),

            'artist_allocations' =>
                $artistAllocations
                    ->values()
                    ->all(),
        ];
    }

    private function calculateTrackSplits(
        int $trackId,
        float $pool
    ): Collection {
        $splits = DB::table(
            'track_splits as ts'
        )
            ->join(
                'contributors as c',
                'c.id',
                '=',
                'ts.contributor_id'
            )
            ->where(
                'ts.track_id',
                $trackId
            )
            ->where(
                'ts.split_type',
                'master'
            )
            ->where(
                'ts.status',
                'active'
            )
            ->where(
                'c.status',
                'active'
            )
            ->where(
                'c.can_receive_splits',
                true
            )
            ->whereNotNull(
                'c.artist_id'
            )
            ->whereNull(
                'c.deleted_at'
            )
            ->select([
                'ts.id as split_id',
                'ts.contributor_id',
                'ts.percentage',
                'c.artist_id',
                'c.user_id',
                'c.name as contributor_name',
            ])
            ->orderBy('ts.id')
            ->get();

        return $splits->map(
            function ($split) use ($pool) {
                $percentage =
                    $this->boundPercentage(
                        (float) $split
                            ->percentage
                    );

                return [
                    'split_id' =>
                        (int) $split->split_id,

                    'contributor_id' =>
                        (int) $split
                            ->contributor_id,

                    'artist_id' =>
                        (int) $split
                            ->artist_id,

                    'user_id' =>
                        $split->user_id
                            ? (int) $split
                                ->user_id
                            : null,

                    'contributor_name' =>
                        $split
                            ->contributor_name,

                    'percentage' =>
                        $percentage,

                    'amount' =>
                        round(
                            $pool
                            * (
                                $percentage
                                / 100
                            ),
                            8
                        ),
                ];
            }
        );
    }

    private function buildSummary(
        Collection $rows
    ): array {
        return [
            'rows' =>
                $rows->count(),

            'source_revenue' =>
                round(
                    (float) $rows
                        ->sum(
                            'source_revenue'
                        ),
                    8
                ),

            'company_retention' =>
                round(
                    (float) $rows
                        ->sum(
                            'company_retention'
                        ),
                    8
                ),

            'beneficiary_pool' =>
                round(
                    (float) $rows
                        ->sum(
                            'beneficiary_pool'
                        ),
                    8
                ),

            'parent_commission' =>
                round(
                    (float) $rows
                        ->sum(
                            'parent_commission'
                        ),
                    8
                ),

            'artist_payable' =>
                round(
                    (float) $rows
                        ->sum(
                            'artist_payable'
                        ),
                    8
                ),

            'unallocated_amount' =>
                round(
                    (float) $rows
                        ->sum(
                            'unallocated_amount'
                        ),
                    8
                ),

            'reconciliation_difference' =>
                round(
                    (float) $rows
                        ->sum(
                            'difference'
                        ),
                    8
                ),
        ];
    }

    private function groupByMonth(
        Collection $rows
    ): Collection {
        return $rows
            ->groupBy(
                fn ($row) =>
                    $row['sale_month']
                    ?: 'unknown'
            )
            ->map(
                fn ($items, $month) => [
                    'sale_month' =>
                        $month,

                    ...$this->buildSummary(
                        collect($items)
                    ),
                ]
            )
            ->sortKeys()
            ->values();
    }

    private function groupByLabel(
        Collection $rows
    ): Collection {
        return $rows
            ->groupBy(
                fn ($row) =>
                    ($row['label_id']
                        ?? 0)
                    .'|'
                    .$row['label_name']
            )
            ->map(
                function (
                    $items,
                    $key
                ) {
                    $first =
                        collect($items)
                            ->first();

                    return [
                        'label_id' =>
                            $first[
                                'label_id'
                            ],

                        'label_name' =>
                            $first[
                                'label_name'
                            ],

                        'beneficiary_percentage' =>
                            $first[
                                'beneficiary_percentage'
                            ],

                        ...$this->buildSummary(
                            collect($items)
                        ),
                    ];
                }
            )
            ->sortByDesc(
                'source_revenue'
            )
            ->values();
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
