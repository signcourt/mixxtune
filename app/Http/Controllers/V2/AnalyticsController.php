<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\PermissionService;
use App\Services\V2\ReportAnalyticsService;
use App\Services\V2\MasterRevenueVisibilityService;
use App\Services\V2\FinancialAnalyticsService;
use App\Models\Core\Label;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    private function filters(Request $request): array
    {
        return [
            'month' => trim(
                (string) $request->input('month', '')
            ),

            'sale_month' => trim(
                (string) $request->input(
                    'sale_month',
                    ''
                )
            ),

            'from_month' => trim(
                (string) $request->input('from_month', '')
            ),

            'to_month' => trim(
                (string) $request->input('to_month', '')
            ),

            'platform' => trim(
                (string) $request->input('platform', '')
            ),

            'sale_type' => trim(
                (string) $request->input('sale_type', '')
            ),

            'country' => trim(
                (string) $request->input('country', '')
            ),

            'cms' => trim(
                (string) $request->input('cms', '')
            ),

            'master_label_id' =>
                $request->filled(
                    'master_label_id'
                )
                    ? (int) $request->input(
                        'master_label_id'
                    )
                    : null,

            'level_id' =>
                $request->filled(
                    'level_id'
                )
                    ? (int) $request->input(
                        'level_id'
                    )
                    : null,

            'artist_id' =>
                $request->filled(
                    'artist_id'
                )
                    ? (int) $request->input(
                        'artist_id'
                    )
                    : null,
        ];
    }

    private function selectedStatementMonths(
        array $filters
    ): array {
        if (!empty($filters['month'])) {
            return [
                $filters['month'],
            ];
        }

        /*
         * Revenue visibility must follow months that
         * actually exist in imported report data.
         *
         * Do not pull standalone/future royalty
         * statements into Analytics when there is no
         * matching report month, otherwise values from
         * different periods can be combined.
         */
        $query = DB::table(
            'report_rows'
        )
            ->whereNotNull(
                'reporting_month'
            )
            ->where(
                'reporting_month',
                '!=',
                ''
            );

        if (!empty($filters['from_month'])) {
            $query->where(
                'reporting_month',
                '>=',
                $filters['from_month']
            );
        }

        if (!empty($filters['to_month'])) {
            $query->where(
                'reporting_month',
                '<=',
                $filters['to_month']
            );
        }

        return $query
            ->distinct()
            ->orderBy(
                'reporting_month'
            )
            ->pluck(
                'reporting_month'
            )
            ->values()
            ->all();
    }

    private function emptyRevenueVisibility(): array
    {
        return [
            'available' => false,
            'is_master' => false,
            'managed_revenue' => 0.0,
            'allocated_revenue' => 0.0,
            'retained_revenue' => 0.0,
            'payable_revenue' => 0.0,
            'gross_statement_revenue' => 0.0,
            'share_percent' => null,
            'share_visible' => false,
            'children' => [],
        ];
    }

    private function mergeRevenueSummaries(
        array $summaries
    ): array {
        if (empty($summaries)) {
            return $this
                ->emptyRevenueVisibility();
        }

        $result =
            $this->emptyRevenueVisibility();

        $result['available'] = true;

        $children = [];

        foreach ($summaries as $summary) {
            $result['is_master'] =
                (bool)
                    ($summary['is_master']
                        ?? false);

            foreach (
                [
                    'managed_revenue',
                    'allocated_revenue',
                    'retained_revenue',
                    'payable_revenue',
                    'gross_statement_revenue',
                ] as $key
            ) {
                $result[$key] +=
                    (float)
                        ($summary[$key]
                            ?? 0);
            }

            if (
                $result['share_percent']
                    === null
                && array_key_exists(
                    'share_percent',
                    $summary
                )
            ) {
                $result['share_percent'] =
                    $summary[
                        'share_percent'
                    ];
            }

            $result['share_visible'] =
                $result['share_visible']
                || (bool)
                    ($summary[
                        'share_visible'
                    ] ?? false);

            foreach (
                $summary['children'] ?? []
                as $child
            ) {
                $key =
                    ($child['type'] ?? '')
                    .':'
                    .($child['id'] ?? '');

                if (!isset($children[$key])) {
                    $children[$key] = [
                        'id' =>
                            $child['id']
                                ?? null,

                        'type' =>
                            $child['type']
                                ?? null,

                        'name' =>
                            $child['name']
                                ?? 'Unknown',

                        'allocated_revenue' =>
                            0.0,

                        'managed_revenue' =>
                            0.0,

                        'master_retained' =>
                            0.0,

                        'share_percent' =>
                            $child[
                                'share_percent'
                            ] ?? null,

                        'share_visible' =>
                            (bool)
                                ($child[
                                    'share_visible'
                                ] ?? false),
                    ];
                }

                foreach (
                    [
                        'allocated_revenue',
                        'managed_revenue',
                        'master_retained',
                    ] as $amountKey
                ) {
                    $children[$key][
                        $amountKey
                    ] +=
                        (float)
                            ($child[
                                $amountKey
                            ] ?? 0);
                }
            }
        }

        foreach (
            [
                'managed_revenue',
                'allocated_revenue',
                'retained_revenue',
                'payable_revenue',
                'gross_statement_revenue',
            ] as $key
        ) {
            $result[$key] =
                round(
                    $result[$key],
                    8
                );
        }

        foreach ($children as &$child) {
            foreach (
                [
                    'allocated_revenue',
                    'managed_revenue',
                    'master_retained',
                ] as $key
            ) {
                $child[$key] =
                    round(
                        $child[$key],
                        8
                    );
            }
        }

        unset($child);

        $result['children'] =
            array_values(
                $children
            );

        return $result;
    }

    private function revenueVisibility(
        Request $request,
        PermissionService $permissions,
        MasterRevenueVisibilityService $visibility,
        array $filters
    ): array {
        $user = $request->user();

        $role =
            $permissions->role(
                $user
            );

        /*
         * Super Admin and Admin dashboards contain
         * multiple independent accounts.
         *
         * Do not collapse those accounts into a
         * fake master-label revenue relationship.
         */
        if (
            in_array(
                $role,
                [
                    'super_admin',
                    'admin',
                ],
                true
            )
        ) {
            return $this
                ->emptyRevenueVisibility();
        }

        $months =
            $this->selectedStatementMonths(
                $filters
            );

        /*
         * No statement month means there is no
         * canonical payable revenue to expose yet.
         */
        if (empty($months)) {
            return $this
                ->emptyRevenueVisibility();
        }

        $summaries = [];

        if ($role === 'label') {
            $label = Label::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereNull(
                    'deleted_at'
                )
                ->first();

            if (!$label) {
                return $this
                    ->emptyRevenueVisibility();
            }

            foreach ($months as $month) {
                $summaries[] =
                    $visibility->summary(
                        $label,
                        $month
                    );
            }

            return $this
                ->mergeRevenueSummaries(
                    $summaries
                );
        }

        if ($role === 'artist') {
            $artistId =
                DB::table('artists')
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->whereNull(
                        'deleted_at'
                    )
                    ->value('id');

            if (!$artistId) {
                return $this
                    ->emptyRevenueVisibility();
            }

            foreach ($months as $month) {
                $summaries[] =
                    $visibility
                        ->artistSummary(
                            (int) $artistId,
                            $month
                        );
            }

            return $this
                ->mergeRevenueSummaries(
                    $summaries
                );
        }

        return $this
            ->emptyRevenueVisibility();
    }

    public function index(
        Request $request,
        PermissionService $permissions,
        ReportAnalyticsService $analytics,
        MasterRevenueVisibilityService $revenueVisibility,
        FinancialAnalyticsService $financialAnalytics
    ): Response {
        $user = $request->user();

        $permissions->authorize(
            $user,
            'reports.view'
        );

        $role = $permissions->role($user);

        $filters = $this->filters($request);

        $base = $analytics->scopedQuery(
            $user,
            $permissions
        );

        $months = (clone $base)
            ->whereNotNull('reporting_month')
            ->where('reporting_month', '!=', '')
            ->distinct()
            ->orderByDesc('reporting_month')
            ->pluck('reporting_month')
            ->values();

        /*
         * MIXX TUNE REPORTING PERIOD DEFAULT
         * ==================================
         *
         * Analytics opens on the latest available
         * Reporting Month.
         *
         * Sale Month remains optional and defaults
         * to All.
         */
        if (
            empty($filters['month'])
            && empty($filters['from_month'])
            && empty($filters['to_month'])
            && $months->isNotEmpty()
        ) {
            $filters['month'] =
                (string) $months->first();
        }

        $saleMonthBase =
            clone $base;

        if (!empty($filters['month'])) {
            $saleMonthBase->where(
                'reporting_month',
                $filters['month']
            );
        }

        $saleMonths =
            $saleMonthBase
                ->whereNotNull('sale_month')
                ->where('sale_month', '!=', '')
                ->distinct()
                ->orderByDesc('sale_month')
                ->pluck('sale_month')
                ->values();

        $platforms = (clone $base)
            ->whereNotNull('platform')
            ->where('platform', '!=', '')
            ->distinct()
            ->orderBy('platform')
            ->pluck('platform')
            ->values();

        $saleTypeOptions = (clone $base)
            ->whereNotNull('sale_type')
            ->where('sale_type', '!=', '')
            ->distinct()
            ->orderBy('sale_type')
            ->pluck('sale_type')
            ->values();

        $countries = (clone $base)
            ->whereNotNull('country_code')
            ->where('country_code', '!=', '')
            ->distinct()
            ->orderBy('country_code')
            ->pluck('country_code')
            ->values();

        $cmsOptions = (clone $base)
            ->whereNotNull('cms')
            ->where('cms', '!=', '')
            ->distinct()
            ->orderBy('cms')
            ->pluck('cms')
            ->values();

        $filtered = $analytics->applyFilters(
            clone $base,
            $filters
        );


        /*
         * MIXX_TUNE_ANALYTICS_HIERARCHY_OPTIONS
         *
         * Options come only from rows already visible
         * through scopedQuery(). This prevents the
         * hierarchy selectors from exposing accounts
         * outside the authenticated security scope.
         */
        $visibleLabelIds = (clone $base)
            ->whereNotNull('label_id')
            ->distinct()
            ->pluck('label_id')
            ->map(
                fn ($id) => (int) $id
            )
            ->values();

        $visibleArtistIds = (clone $base)
            ->whereNotNull('artist_id')
            ->distinct()
            ->pluck('artist_id')
            ->map(
                fn ($id) => (int) $id
            )
            ->values();

        $visibleLabels =
            $visibleLabelIds->isEmpty()
                ? collect()
                : DB::table('labels')
                    ->whereIn(
                        'id',
                        $visibleLabelIds
                    )
                    ->whereNull('deleted_at')
                    ->get([
                        'id',
                        'name',
                        'parent_label_id',
                    ]);

        $hierarchy = app(
            \App\Services\V2\LabelHierarchyService::class
        );

        $levelOptions = $visibleLabels
            ->map(
                function ($label) use (
                    $hierarchy
                ) {
                    $path = [];

                    $currentId =
                        (int) $label->id;

                    $visited = [];

                    while (
                        $currentId
                        && !isset(
                            $visited[$currentId]
                        )
                    ) {
                        $visited[$currentId] =
                            true;

                        $current =
                            DB::table('labels')
                                ->where(
                                    'id',
                                    $currentId
                                )
                                ->whereNull(
                                    'deleted_at'
                                )
                                ->first([
                                    'id',
                                    'name',
                                    'parent_label_id',
                                ]);

                        if (!$current) {
                            break;
                        }

                        array_unshift(
                            $path,
                            $current->name
                        );

                        $currentId =
                            $current
                                ->parent_label_id
                                    ? (int)
                                        $current
                                            ->parent_label_id
                                    : 0;
                    }

                    return [
                        'id' =>
                            (int) $label->id,

                        'name' =>
                            $label->name,

                        'parent_label_id' =>
                            $label
                                ->parent_label_id
                                    ? (int)
                                        $label
                                            ->parent_label_id
                                    : null,

                        'root_id' =>
                            (int)
                            $hierarchy
                                ->rootLabelId(
                                    (int)
                                    $label->id
                                ),

                        'path' =>
                            implode(
                                ' › ',
                                $path
                            ),
                    ];
                }
            )
            ->sortBy('path')
            ->values();

        $masterIds = $levelOptions
            ->pluck('root_id')
            ->unique()
            ->values();

        $masterOptions =
            $masterIds->isEmpty()
                ? collect()
                : DB::table('labels')
                    ->whereIn(
                        'id',
                        $masterIds
                    )
                    ->whereNull('deleted_at')
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                    ])
                    ->map(
                        fn ($label) => [
                            'id' =>
                                (int) $label->id,

                            'name' =>
                                $label->name,
                        ]
                    )
                    ->values();

        $artistOptions =
            $visibleArtistIds->isEmpty()
                ? collect()
                : DB::table('artists')
                    ->whereIn(
                        'id',
                        $visibleArtistIds
                    )
                    ->whereNull('deleted_at')
                    ->orderBy('stage_name')
                    ->get([
                        'id',
                        'stage_name',
                        'label_id',
                    ])
                    ->map(
                        fn ($artist) => [
                            'id' =>
                                (int) $artist->id,

                            'name' =>
                                $artist->stage_name,

                            'label_id' =>
                                $artist->label_id
                                    ? (int)
                                        $artist
                                            ->label_id
                                    : null,
                        ]
                    )
                    ->values();

        $hierarchyOptions = [
            'masters' =>
                $masterOptions,

            'levels' =>
                $levelOptions,

            'artists' =>
                $artistOptions,
        ];

        /*
         * Historical trend ignores all month selectors.
         * Store and region filters remain active.
         */
        $trendFilters = $filters;
        $trendFilters['month'] = '';
        $trendFilters['from_month'] = '';
        $trendFilters['to_month'] = '';

        $trendQuery = $analytics->applyFilters(
            clone $base,
            $trendFilters
        );

        $monthlyTrend = $analytics->monthlyTrend(
            clone $trendQuery,
            24
        );

        $growthMonth =
            $filters['month']
            ?: (
                $filters['to_month']
                ?: null
            );

        $growth = $analytics->growthSummary(
            $monthlyTrend,
            $growthMonth
        );

        /*
         * Canonical payable financial analytics.
         *
         * Raw DSP analytics above remains sourced from
         * report_rows through ReportAnalyticsService.
         * Payable financial values originate only from
         * royalty statements and allocations.
         */
        $financialStatements =
            $financialAnalytics->scopedStatements(
                $user,
                $permissions
            );

        $financialStatements =
            $financialAnalytics->applyMonthFilters(
                $financialStatements,
                $filters
            );

        $financialStatements =
            $financialAnalytics->applyHierarchyFilters(
                $financialStatements,
                $filters
            );

        $financialData = [
            'summary' =>
                $financialAnalytics->summary(
                    clone $financialStatements
                ),

            'monthly' =>
                $financialAnalytics->monthly(
                    clone $financialStatements
                ),

            'platforms' =>
                $financialAnalytics->platformBreakdown(
                    clone $financialStatements
                ),

            'countries' =>
                $financialAnalytics->countryBreakdown(
                    clone $financialStatements
                ),
        ];

        return Inertia::render(
            'V2/Analytics/Index',
            [
                'role' => $role,

                'filters' => $filters,

                'financialAnalytics' =>
                    $financialData,

                'hierarchyOptions' =>
                    $hierarchyOptions,

                'filterOptions' => [
                    'months' => $months,
                    'saleMonths' => $saleMonths,
                    'platforms' => $platforms,
                    'countries' => $countries,
                    'saleTypes' => $saleTypeOptions,
                    'cms' => $cmsOptions,
                ],

                'summary' => $analytics->summary(
                    clone $filtered
                ),

                'growth' => $growth,

                'monthlyTrend' => $monthlyTrend,

                'topPlatforms' =>
                    $analytics->topPlatforms(
                        clone $filtered,
                        10
                    ),

                'topCountries' =>
                    $analytics->topCountries(
                        clone $filtered,
                        10
                    ),

                'topTracks' =>
                    $analytics->topTracks(
                        clone $filtered,
                        10
                    ),

                'topArtists' =>
                    $analytics->topArtists(
                        clone $filtered
                    ),

                'topAlbums' =>
                    $analytics->topAlbums(
                        clone $filtered
                    ),

                'topLabels' =>
                    $analytics->topLabels(
                        clone $filtered
                    ),

                'saleTypes' =>
                    $analytics->saleTypeSummary(
                        clone $filtered
                    ),

                'currencySummary' =>
                    $analytics->currencySummary(
                        clone $filtered
                    ),

                'cmsSummary' =>
                    $analytics->cmsSummary(
                        clone $filtered
                    ),

                'revenueVisibility' =>
                    $this->revenueVisibility(
                        $request,
                        $permissions,
                        $revenueVisibility,
                        $filters
                    ),
            ]
        );
    }

    public function export(
        Request $request,
        PermissionService $permissions,
        ReportAnalyticsService $analytics
    ): StreamedResponse {
        $user = $request->user();

        $permissions->authorize(
            $user,
            'reports.view'
        );

        /*
         * IMPORTANT:
         * Export uses the exact same role/account security
         * boundary as the Analytics dashboard.
         */
        $query = $analytics->scopedQuery(
            $user,
            $permissions
        );

        $query = $analytics->applyFilters(
            $query,
            $this->filters($request)
        );

        $filename =
            'mixx-tune-analytics-' .
            now()->format('Ymd-His') .
            '.csv';

        return response()->streamDownload(
            function () use (
                $query,
                $user,
                $permissions
            ) {
                $handle = fopen(
                    'php://output',
                    'w'
                );

                /*
                 * UTF-8 BOM for clean Excel display.
                 */
                fwrite(
                    $handle,
                    "\xEF\xBB\xBF"
                );

                $role =
                    $permissions->role(
                        $user
                    );

                $accountRate = 100.0;

                if ($role === 'artist') {
                    $rate =
                        DB::table('artists')
                            ->where(
                                'user_id',
                                $user->id
                            )
                            ->value(
                                'revenue_share_percentage'
                            );

                    if ($rate !== null) {
                        $accountRate =
                            (float) $rate;
                    }
                } elseif ($role === 'label') {
                    $rate =
                        DB::table('labels')
                            ->where(
                                'user_id',
                                $user->id
                            )
                            ->orderBy('id')
                            ->value(
                                'revenue_share_percentage'
                            );

                    if ($rate !== null) {
                        $accountRate =
                            (float) $rate;
                    }
                }

                $accountRate =
                    max(
                        0.0,
                        min(
                            100.0,
                            $accountRate
                        )
                    );

                $rateHeading =
                    match ($role) {
                        'label' =>
                            'Label Rate',

                        'artist' =>
                            'Artist Rate',

                        default =>
                            'Assigned Rate',
                    };

                fputcsv(
                    $handle,
                    [
                        'Sale Month',
                        'Sale Date',
                        'Track',
                        'Track Artist',
                        'Album',
                        'Album Artist',
                        'Label',
                        'ISRC',
                        'UPC',
                        'Store',
                        'Country',
                        'CMS',
                        'Sale Type',
                        'Currency',
                        'Streams',
                        'Units',
                        'Collected Revenue',
                        $rateHeading,
                        'Earning',
                    ]
                );

                $query
                    ->orderBy('id')
                    ->chunkById(
                        1000,
                        function ($rows) use (
                            $handle,
                            $role,
                            $accountRate
                        ) {
                            foreach ($rows as $row) {
                                $collected =
                                    round(
                                        (float) (
                                            $row->earnings
                                            ?? 0
                                        ),
                                        8
                                    );

                                /*
                                 * Negative adjustment rule:
                                 * effective rate is always 100%.
                                 * Stored account rate is untouched.
                                 */
                                $effectiveRate =
                                    $collected < 0
                                        ? 100.0
                                        : $accountRate;

                                $earning =
                                    $collected < 0
                                        ? $collected
                                        : round(
                                            $collected
                                            * (
                                                $effectiveRate
                                                / 100
                                            ),
                                            8
                                        );

                                /*
                                 * Admin/Super Admin exports retain
                                 * source economics at 100%.
                                 */
                                if (
                                    $role === 'admin'
                                    || $role === 'super_admin'
                                ) {
                                    $effectiveRate = 100.0;
                                    $earning = $collected;
                                }

                                fputcsv(
                                    $handle,
                                    [
                                        $row->sale_month,
                                        optional(
                                            $row->sale_date
                                        )->format('Y-m-d'),
                                        $row->track_title,
                                        $row->track_artist,
                                        $row->album_title,
                                        $row->album_artist,
                                        $row->label_name,
                                        $row->isrc,
                                        $row->upc,
                                        $row->platform,
                                        $row->country_code,
                                        $row->cms,
                                        $row->sale_type,
                                        $row->currency,
                                        $row->streams,
                                        $row->sale_units,
                                        $collected,
                                        $effectiveRate,
                                        $earning,
                                    ]
                                );
                            }
                        }
                    );

                fclose($handle);
            },
            $filename,
            [
                'Content-Type' =>
                    'text/csv; charset=UTF-8',
            ]
        );
    }
}
