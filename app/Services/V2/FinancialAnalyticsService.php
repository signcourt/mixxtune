<?php

namespace App\Services\V2;

use App\Models\User;
use App\Services\V2\AdminAssignmentService;
use App\Services\V2\PermissionService;
use App\Services\V2\LabelAccess\LabelTeamAccessService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FinancialAnalyticsService
{
    public function __construct(
        private readonly AdminAssignmentService $assignments
    ) {
    }

    /**
     * Canonical financial analytics source.
     *
     * Financial/payable analytics MUST originate from
     * royalty_statements / royalty_allocations.
     *
     * Raw DSP analytics remains the responsibility of
     * ReportAnalyticsService and report_rows.
     */
    public function scopedStatements(
        User $user,
        PermissionService $permissions
    ): Builder {
        $role = $permissions->role($user);

        $query = DB::table(
            'royalty_statements as rs'
        );

        if ($role === 'super_admin') {
            return $query;
        }

        if ($role === 'artist') {
            $artistId = DB::table('artists')
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->value('id');

            return $artistId
                ? $query->where(
                    'rs.artist_id',
                    $artistId
                )
                : $query->whereRaw('1 = 0');
        }

        if ($role === 'label') {
            $labelIds = app(
                LabelTeamAccessService::class
            )->accessibleLabelIds($user);

            $artistIds = app(
                LabelTeamAccessService::class
            )->accessibleArtistIds($user);

            if (
                $labelIds->isEmpty()
                && $artistIds->isEmpty()
            ) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(
                function ($builder) use (
                    $labelIds,
                    $artistIds
                ) {
                    if ($labelIds->isNotEmpty()) {
                        $builder->whereIn(
                            'rs.label_id',
                            $labelIds
                        );
                    }

                    if ($artistIds->isNotEmpty()) {
                        if ($labelIds->isNotEmpty()) {
                            $builder->orWhereIn(
                                'rs.artist_id',
                                $artistIds
                            );
                        } else {
                            $builder->whereIn(
                                'rs.artist_id',
                                $artistIds
                            );
                        }
                    }
                }
            );
        }

        if ($role === 'admin') {
            $labelIds =
                $this->assignments->labelIds($user);

            $artistIds =
                $this->assignments->artistIds($user);

            if (
                $labelIds->isEmpty()
                && $artistIds->isEmpty()
            ) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(
                function ($builder) use (
                    $labelIds,
                    $artistIds
                ) {
                    if ($labelIds->isNotEmpty()) {
                        $builder->whereIn(
                            'rs.label_id',
                            $labelIds
                        );
                    }

                    if ($artistIds->isNotEmpty()) {
                        if ($labelIds->isNotEmpty()) {
                            $builder->orWhereIn(
                                'rs.artist_id',
                                $artistIds
                            );
                        } else {
                            $builder->whereIn(
                                'rs.artist_id',
                                $artistIds
                            );
                        }
                    }
                }
            );
        }

        return $query->whereRaw('1 = 0');
    }

    public function applyMonthFilters(
        Builder $query,
        array $filters
    ): Builder {
        if (! empty($filters['month'])) {
            $query->where(
                'rs.statement_month',
                $filters['month']
            );

            return $query;
        }

        if (! empty($filters['from_month'])) {
            $query->where(
                'rs.statement_month',
                '>=',
                $filters['from_month']
            );
        }

        if (! empty($filters['to_month'])) {
            $query->where(
                'rs.statement_month',
                '<=',
                $filters['to_month']
            );
        }

        return $query;
    }

    /**
     * Apply Analytics hierarchy selectors on top of the
     * authenticated financial statement scope.
     *
     * These filters can only narrow scopedStatements();
     * they can never broaden account visibility.
     */
    public function applyHierarchyFilters(
        Builder $query,
        array $filters
    ): Builder {
        foreach (
            [
                'master_label_id',
                'level_id',
            ]
            as $filterKey
        ) {
            if (empty($filters[$filterKey])) {
                continue;
            }

            $labelId =
                (int) $filters[$filterKey];

            $labelIds = app(
                LabelHierarchyService::class
            )->descendantIds(
                $labelId,
                true
            );

            $artistIds = DB::table('artists')
                ->whereNull('deleted_at')
                ->whereIn(
                    'label_id',
                    $labelIds
                )
                ->pluck('id');

            $query->where(
                function ($builder) use (
                    $labelIds,
                    $artistIds
                ) {
                    if ($labelIds->isNotEmpty()) {
                        $builder->whereIn(
                            'rs.label_id',
                            $labelIds
                        );
                    }

                    if ($artistIds->isNotEmpty()) {
                        if ($labelIds->isNotEmpty()) {
                            $builder->orWhereIn(
                                'rs.artist_id',
                                $artistIds
                            );
                        } else {
                            $builder->whereIn(
                                'rs.artist_id',
                                $artistIds
                            );
                        }
                    }

                    if (
                        $labelIds->isEmpty()
                        && $artistIds->isEmpty()
                    ) {
                        $builder->whereRaw(
                            '1 = 0'
                        );
                    }
                }
            );
        }

        if (! empty($filters['artist_id'])) {
            $query->where(
                'rs.artist_id',
                (int) $filters['artist_id']
            );
        }

        return $query;
    }


    /**
     * Apply report-row dimensions to financial statements
     * without joining report_rows into the statement query.
     *
     * EXISTS keeps each royalty statement cardinality at one
     * row, preventing statement revenue multiplication when a
     * statement owns multiple allocations.
     */
    public function applyDimensionFilters(
        Builder $query,
        array $filters
    ): Builder {
        $dimensionFilters = [
            'sale_month' => 'sale_month',
            'platform' => 'platform',
            'sale_type' => 'sale_type',
            'currency' => 'currency',
            'country' => 'country_code',
            'cms' => 'cms',
        ];

        $hasDimensionFilter = false;

        foreach ($dimensionFilters as $filterKey => $column) {
            if (! empty($filters[$filterKey])) {
                $hasDimensionFilter = true;
                break;
            }
        }

        if (
            ! $hasDimensionFilter
            && empty($filters['isrc'])
            && empty($filters['upc'])
        ) {
            return $query;
        }

        return $query->whereExists(
            function ($subquery) use (
                $filters,
                $dimensionFilters
            ) {
                $subquery
                    ->selectRaw('1')
                    ->from('royalty_allocations as fa')
                    ->join(
                        'report_rows as fr',
                        'fr.id',
                        '=',
                        'fa.report_row_id'
                    )
                    ->whereColumn(
                        'fa.royalty_statement_id',
                        'rs.id'
                    );

                foreach (
                    $dimensionFilters
                    as $filterKey => $column
                ) {
                    if (empty($filters[$filterKey])) {
                        continue;
                    }

                    $subquery->where(
                        'fr.' . $column,
                        $filters[$filterKey]
                    );
                }

                if (! empty($filters['isrc'])) {
                    $subquery->where(
                        'fr.isrc',
                        'like',
                        '%' . $filters['isrc'] . '%'
                    );
                }

                if (! empty($filters['upc'])) {
                    $subquery->where(
                        'fr.upc',
                        'like',
                        '%' . $filters['upc'] . '%'
                    );
                }
            }
        );
    }


    public function summary(
        Builder $query
    ): array {
        $row = (clone $query)
            ->selectRaw(
                'COALESCE(SUM(rs.gross_earnings), 0) as gross'
            )
            ->selectRaw(
                'COALESCE(SUM(rs.commission_amount), 0) as commission'
            )
            ->selectRaw(
                'COALESCE(SUM(rs.tax_amount), 0) as tax'
            )
            ->selectRaw(
                'COALESCE(SUM(rs.other_deductions), 0) as other_deductions'
            )
            ->selectRaw(
                'COALESCE(SUM(rs.net_payable), 0) as net'
            )
            ->selectRaw(
                'COUNT(*) as statements_count'
            )
            ->first();

        return [
            'gross_earnings' =>
                round((float) $row->gross, 8),

            'commission_amount' =>
                round((float) $row->commission, 8),

            'tax_amount' =>
                round((float) $row->tax, 8),

            'other_deductions' =>
                round(
                    (float) $row->other_deductions,
                    8
                ),

            'net_payable' =>
                round((float) $row->net, 8),

            'statements_count' =>
                (int) $row->statements_count,
        ];
    }


    /**
     * Allocation-safe platform/store financial breakdown.
     *
     * Monetary values originate only from royalty_allocations.
     * report_rows supplies descriptive DSP metadata only.
     */
    public function platformBreakdown(
        Builder $statementQuery
    ): array {
        $statementIds = (clone $statementQuery)
            ->select('rs.id');

        return DB::table('royalty_allocations as ra')
            ->join(
                'report_rows as rr',
                'rr.id',
                '=',
                'ra.report_row_id'
            )
            ->whereIn(
                'ra.royalty_statement_id',
                $statementIds
            )
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(rr.platform), ''), 'Unknown') as platform"
            )
            ->selectRaw(
                'COUNT(*) as allocation_count'
            )
            ->selectRaw(
                'COUNT(DISTINCT ra.report_row_id) as report_rows'
            )
            ->selectRaw(
                'COALESCE(SUM(ra.gross_amount), 0) as gross_earnings'
            )
            ->selectRaw(
                'COALESCE(SUM(ra.net_amount), 0) as net_payable'
            )
            ->groupByRaw(
                "COALESCE(NULLIF(TRIM(rr.platform), ''), 'Unknown')"
            )
            ->orderByDesc('gross_earnings')
            ->get()
            ->map(
                fn ($row) => [
                    'platform' =>
                        $row->platform,

                    'allocation_count' =>
                        (int) $row->allocation_count,

                    'report_rows' =>
                        (int) $row->report_rows,

                    'gross_earnings' =>
                        round(
                            (float) $row->gross_earnings,
                            8
                        ),

                    'net_payable' =>
                        round(
                            (float) $row->net_payable,
                            8
                        ),
                ]
            )
            ->all();
    }

    /**
     * Allocation-safe country/region financial breakdown.
     */
    public function countryBreakdown(
        Builder $statementQuery
    ): array {
        $statementIds = (clone $statementQuery)
            ->select('rs.id');

        return DB::table('royalty_allocations as ra')
            ->join(
                'report_rows as rr',
                'rr.id',
                '=',
                'ra.report_row_id'
            )
            ->whereIn(
                'ra.royalty_statement_id',
                $statementIds
            )
            ->selectRaw(
                "COALESCE(NULLIF(TRIM(rr.country_code), ''), 'Unknown') as country"
            )
            ->selectRaw(
                'COUNT(*) as allocation_count'
            )
            ->selectRaw(
                'COUNT(DISTINCT ra.report_row_id) as report_rows'
            )
            ->selectRaw(
                'COALESCE(SUM(ra.gross_amount), 0) as gross_earnings'
            )
            ->selectRaw(
                'COALESCE(SUM(ra.net_amount), 0) as net_payable'
            )
            ->groupByRaw(
                "COALESCE(NULLIF(TRIM(rr.country_code), ''), 'Unknown')"
            )
            ->orderByDesc('gross_earnings')
            ->get()
            ->map(
                fn ($row) => [
                    'country' =>
                        $row->country,

                    'allocation_count' =>
                        (int) $row->allocation_count,

                    'report_rows' =>
                        (int) $row->report_rows,

                    'gross_earnings' =>
                        round(
                            (float) $row->gross_earnings,
                            8
                        ),

                    'net_payable' =>
                        round(
                            (float) $row->net_payable,
                            8
                        ),
                ]
            )
            ->all();
    }

    public function monthly(
        Builder $query
    ): array {
        return (clone $query)
            ->selectRaw(
                'rs.statement_month as month'
            )
            ->selectRaw(
                'SUM(rs.gross_earnings) as gross_earnings'
            )
            ->selectRaw(
                'SUM(rs.commission_amount) as commission_amount'
            )
            ->selectRaw(
                'SUM(rs.net_payable) as net_payable'
            )
            ->groupBy(
                'rs.statement_month'
            )
            ->orderBy(
                'rs.statement_month'
            )
            ->get()
            ->map(
                fn ($row) => [
                    'month' =>
                        $row->month,

                    'gross_earnings' =>
                        round(
                            (float) $row->gross_earnings,
                            8
                        ),

                    'commission_amount' =>
                        round(
                            (float) $row->commission_amount,
                            8
                        ),

                    'net_payable' =>
                        round(
                            (float) $row->net_payable,
                            8
                        ),
                ]
            )
            ->all();
    }
}
