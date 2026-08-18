<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Services\V2\PermissionService;
use App\Services\V2\ReportAnalyticsService;
use App\Services\V2\MasterRevenueVisibilityService;
use App\Services\V2\LabelAccess\LabelTeamAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions,
        ReportAnalyticsService $analytics,
        MasterRevenueVisibilityService $revenueVisibility
    ): Response {
        $permissions->authorize(
            $request->user(),
            'reports.view'
        );

        $filters = [
            'search' => trim(
                (string) $request->input(
                    'search',
                    ''
                )
            ),

            'month' => trim(
                (string) $request->input(
                    'month',
                    ''
                )
            ),

            'platform' => trim(
                (string) $request->input(
                    'platform',
                    ''
                )
            ),

            'country' => trim(
                (string) $request->input(
                    'country',
                    ''
                )
            ),

            'isrc' => trim(
                (string) $request->input(
                    'isrc',
                    ''
                )
            ),

            'master_label_id' => $request->filled(
                'master_label_id'
            )
                ? (int) $request->input(
                    'master_label_id'
                )
                : null,

            'level_id' => $request->filled(
                'level_id'
            )
                ? (int) $request->input(
                    'level_id'
                )
                : null,

            'artist_id' => $request->filled(
                'artist_id'
            )
                ? (int) $request->input(
                    'artist_id'
                )
                : null,
        ];

        $base = $analytics->scopedQuery(
            $request->user(),
            $permissions
        );

        $query = $analytics->applyFilters(
            clone $base,
            $filters
        );

        $platforms = (clone $base)
            ->whereNotNull('platform')
            ->distinct()
            ->orderBy('platform')
            ->pluck('platform');

        $months = (clone $base)
            ->whereNotNull('reporting_month')
            ->distinct()
            ->orderByDesc('reporting_month')
            ->pluck('reporting_month');

        $countries = (clone $base)
            ->whereNotNull('country_code')
            ->distinct()
            ->orderBy('country_code')
            ->pluck('country_code');

        /*
         * REAL AUTOMATIC MONTHLY REPORTS
         * ==============================
         *
         * Build these cards from the authenticated
         * user's already-authorized base query.
         *
         * Never use global report_rows totals here.
         */
        $automaticReports =
            (clone $base)
                ->whereNotNull('reporting_month')
                ->where('reporting_month', '!=', '')
                ->select('reporting_month')
                ->selectRaw(
                    'COUNT(*) as rows_count'
                )
                ->selectRaw(
                    'COALESCE(SUM(earnings), 0) as amount'
                )
                ->groupBy('reporting_month')
                ->orderByDesc('reporting_month')
                ->get()
                ->map(
                    function ($row) {
                        $month =
                            (string) $row->reporting_month;

                        try {
                            $period =
                                \Carbon\Carbon::createFromFormat(
                                    'Y-m',
                                    $month
                                )->format('F Y');
                        } catch (\Throwable $e) {
                            $period = $month;
                        }

                        return [
                            'id' =>
                                'automatic-'.$month,

                            'month' =>
                                $month,

                            'period' =>
                                $period,

                            'type' =>
                                'Full catalogue single report',

                            'amount' =>
                                (float) $row->amount,

                            'rows_count' =>
                                (int) $row->rows_count,

                            'status' =>
                                'ready',
                        ];
                    }
                )
                ->values();


        /*
         * Report hierarchy filter options are built
         * only from labels/artists already visible in
         * the scoped base query.
         */
        $visibleLabelIds = (clone $base)
            ->whereNotNull('label_id')
            ->distinct()
            ->pluck('label_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $visibleArtistIds = (clone $base)
            ->whereNotNull('artist_id')
            ->distinct()
            ->pluck('artist_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $visibleLabels = $visibleLabelIds->isEmpty()
            ? collect()
            : \Illuminate\Support\Facades\DB::table(
                'labels'
            )
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

        $labelById = $visibleLabels->keyBy('id');

        $hierarchy = app(
            \App\Services\V2\LabelHierarchyService::class
        );

        $levelOptions = $visibleLabels
            ->map(function ($label) use ($hierarchy) {
                $path = [];
                $currentId = (int) $label->id;
                $visited = [];

                while (
                    $currentId
                    && !isset($visited[$currentId])
                ) {
                    $visited[$currentId] = true;

                    $current =
                        \Illuminate\Support\Facades\DB::table(
                            'labels'
                        )
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
                        $current->parent_label_id
                            ? (int)
                                $current->parent_label_id
                            : 0;
                }

                return [
                    'id' =>
                        (int) $label->id,

                    'name' =>
                        $label->name,

                    'parent_label_id' =>
                        $label->parent_label_id
                            ? (int)
                                $label->parent_label_id
                            : null,

                    'root_id' =>
                        (int)
                        $hierarchy->rootLabelId(
                            (int) $label->id
                        ),

                    'path' =>
                        implode(
                            ' › ',
                            $path
                        ),
                ];
            })
            ->sortBy('path')
            ->values();

        $masterIds = $levelOptions
            ->pluck('root_id')
            ->unique()
            ->values();

        $masterOptions = $masterIds->isEmpty()
            ? collect()
            : \Illuminate\Support\Facades\DB::table(
                'labels'
            )
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

        $artistOptions = $visibleArtistIds->isEmpty()
            ? collect()
            : \Illuminate\Support\Facades\DB::table(
                'artists'
            )
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
                                    $artist->label_id
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

        $role = $permissions->role(
            $request->user()
        );

        $revenueSummary = null;

        if ($role === 'label') {
            $teamAccess = app(
                LabelTeamAccessService::class
            );

            $label = $teamAccess->effectiveLabel(
                $request->user()
            );

            /*
             * Revenue summary is shown only when the
             * team user has finance/report visibility.
             * Catalogue rows themselves are already
             * restricted by ReportAnalyticsService.
             */
            if (
                $label
                && $teamAccess->allows(
                    $request->user(),
                    'reports.view'
                )
            ) {
                $revenueSummary =
                    $revenueVisibility->summary(
                        $label,
                        $filters['month'] ?: null
                    );
            }
        }

        if ($role === 'artist') {
            $artist = DB::table('artists')
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->whereNull('deleted_at')
                ->first();

            if ($artist) {
                $revenueSummary =
                    $revenueVisibility
                        ->artistSummary(
                            (int) $artist->id,
                            $filters['month'] ?: null
                        );
            }
        }

        return Inertia::render(
            'V2/Reports/Index',
            [
                'role' => $role,

                'revenueSummary' =>
                    $revenueSummary,

                'filters' =>
                    $filters,

                'hierarchyOptions' =>
                    $hierarchyOptions,

                'summary' =>
                    $analytics->summary(
                        clone $query
                    ),

                'monthlyTrend' =>
                    $analytics->monthlyTrend(
                        clone $query
                    ),

                'topPlatforms' =>
                    $analytics->topPlatforms(
                        clone $query
                    ),

                'topCountries' =>
                    $analytics->topCountries(
                        clone $query
                    ),

                'topTracks' =>
                    $analytics->topTracks(
                        clone $query
                    ),

                'currencySummary' =>
                    $analytics->currencySummary(
                        clone $query
                    ),

                'platforms' =>
                    $platforms,

                'months' =>
                    $months,

                'automaticReports' =>
                    $automaticReports,

                'countries' =>
                    $countries,

                'rows' =>
                    $query
                        ->orderByDesc('sale_date')
                        ->orderByDesc('id')
                        ->paginate(50)
                        ->withQueryString(),
            ]
        );
    }
}
