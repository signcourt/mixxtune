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
        /*
         * FF165_ADMIN_ALIAS_GUARD_REPORT
         * The shared controller serves both normal account
         * routes and /v2/admin aliases.
         *
         * Only the admin alias requires admin/super_admin.
         */
        if (
            $request->route()
            && str_starts_with(
                (string) $request->route()->getName(),
                'v2.admin.reports.'
            )
        ) {
            $adminAliasRole =
                $permissions->role(
                    $request->user()
                );

            abort_unless(
                in_array(
                    $adminAliasRole,
                    [
                        'admin',
                        'super_admin',
                    ],
                    true
                ),
                403,
                'Admin access required.'
            );
        }

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

        $months = (clone $base)
            ->whereNotNull('reporting_month')
            ->distinct()
            ->orderByDesc('reporting_month')
            ->pluck('reporting_month');



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
                    'COALESCE(SUM(collected_revenue), 0) as gross_amount'
                )
                ->selectRaw(
                    'COALESCE(SUM(earnings), 0) as amount'
                )
                ->selectRaw(
                    "SUM(CASE
                        WHEN mapping_status = 'mapped'
                        AND NOT (
                            (
                                label_id IS NOT NULL
                                AND EXISTS (
                                    SELECT 1
                                    FROM labels
                                    WHERE labels.id = report_rows.label_id
                                    AND labels.revenue_share_percentage IS NOT NULL
                                )
                            )
                            OR
                            (
                                label_id IS NULL
                                AND artist_id IS NOT NULL
                                AND EXISTS (
                                    SELECT 1
                                    FROM artists
                                    WHERE artists.id = report_rows.artist_id
                                    AND artists.revenue_share_percentage IS NOT NULL
                                )
                            )
                            OR
                            (
                                revenue_owner_type = 'label'
                                AND revenue_owner_id IS NOT NULL
                                AND EXISTS (
                                    SELECT 1
                                    FROM labels AS owner_labels
                                    WHERE owner_labels.id = report_rows.revenue_owner_id
                                    AND owner_labels.revenue_share_percentage IS NOT NULL
                                )
                            )
                            OR
                            (
                                revenue_owner_type = 'artist'
                                AND revenue_owner_id IS NOT NULL
                                AND EXISTS (
                                    SELECT 1
                                    FROM artists AS owner_artists
                                    WHERE owner_artists.id = report_rows.revenue_owner_id
                                    AND owner_artists.revenue_share_percentage IS NOT NULL
                                )
                            )
                        )
                        THEN 1
                        ELSE 0
                    END) as rate_pending_rows"
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

                            'gross_amount' =>
                                (float) $row->gross_amount,

                            'rate_pending' =>
                                (int) $row->rate_pending_rows > 0,

                            'rate_pending_rows' =>
                                (int) $row->rate_pending_rows,

                            'rows_count' =>
                                (int) $row->rows_count,

                            'generatedAt' =>
                                now()->format('d M Y'),

                            'status' =>
                                (int) $row->rate_pending_rows > 0
                                    ? 'rate_pending'
                                    : 'ready',
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

        /*
         * MIXXTUNE_REPORTS_V2 (W49)
         * The page uses only months, hierarchy and the automatic reports.
         * Before, seven more heavy queries ran on every visit (summary,
         * trend, top tracks / stores / countries, currencies and a 50-row
         * table) over every report row and were thrown away - slow with
         * 90,000+ rows a month. "Generated" showed today's date; it now
         * shows when that month was published.
         */
        $publishedOn = \Illuminate\Support\Facades\Schema::hasColumn('report_imports', 'published_at')
            ? \Illuminate\Support\Facades\DB::table('report_imports')
                ->where('financial_status', 'published')
                ->whereNotNull('published_at')
                ->get(['reporting_month', 'published_at'])
                ->groupBy(fn ($r) => substr((string) $r->reporting_month, 0, 7))
                ->map(fn ($g) => $g->max('published_at'))
            : collect();

        $automaticReports = $automaticReports->map(function ($r) use ($publishedOn) {
            $at = $publishedOn[$r['month']] ?? null;
            $r['generatedAt'] = $at ? \Carbon\Carbon::parse($at)->timezone('Asia/Kolkata')->format('d M Y') : '—';

            return $r;
        })->values();

        $staff = in_array($role, ['admin', 'super_admin'], true);

        return Inertia::render(
            'V2/Reports/Index',
            [
                'role' => $role,
                'filters' => $filters,
                'hierarchyOptions' => $hierarchyOptions,
                'months' => $months,
                'automaticReports' => $automaticReports,
                'unpublished' => $staff && \Illuminate\Support\Facades\Schema::hasColumn('report_imports', 'financial_status')
                    ? \Illuminate\Support\Facades\DB::table('report_imports')
                        ->whereIn('status', ['completed', 'completed_with_errors'])
                        ->whereIn('financial_status', ['review_pending', 'approved_scheduled'])
                        ->orderByDesc('id')
                        ->limit(10)
                        ->get(['id', 'original_filename', 'reporting_month', 'financial_status', 'imported_rows'])
                        ->map(fn ($r) => [
                            'id' => (int) $r->id,
                            'file' => $r->original_filename,
                            'month' => $r->reporting_month ? substr((string) $r->reporting_month, 0, 7) : null,
                            'step' => $r->financial_status === 'approved_scheduled' ? 'approved, not published yet' : 'waiting for review',
                            'rows' => (int) $r->imported_rows,
                        ])->values()
                    : [],
            ]
        );
    }
}
