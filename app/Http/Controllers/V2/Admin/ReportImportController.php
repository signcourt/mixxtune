<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportRevenueReportJob;
use App\Models\Reports\ReportImport;
use App\Services\V2\PermissionService;
use App\Services\V2\ReportImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ReportImportController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Admin access required.'
        );

        return Inertia::render(
            'V2/Admin/Reports/Imports',
            [
                'role' => $role,

                /*
                 * MIXXTUNE_REPORT_IMPORTS_V2 (W47): only the columns the list
                 * needs (was every column incl. the big column map / error
                 * text), the review -> publish step, and the REAL upload
                 * limit (Master Settings value capped by the server's PHP
                 * upload_max_filesize / post_max_size - a bigger file was
                 * cut off by PHP with a blank "413" page).
                 */
                'imports' =>
                    ReportImport::query()
                        ->select(array_values(array_filter([
                            'id', 'original_filename', 'reporting_month', 'status', 'total_rows', 'imported_rows',
                            'duplicate_rows', 'failed_rows', 'created_at', 'completed_at', 'uploaded_by',
                            \Illuminate\Support\Facades\Schema::hasColumn('report_imports', 'financial_status') ? 'financial_status' : null,
                            \Illuminate\Support\Facades\Schema::hasColumn('report_imports', 'publish_at') ? 'publish_at' : null,
                            \Illuminate\Support\Facades\Schema::hasColumn('report_imports', 'published_at') ? 'published_at' : null,
                            \Illuminate\Support\Facades\Schema::hasColumn('report_imports', 'review_earnings') ? 'review_earnings' : null,
                        ])))
                        ->selectRaw('SUBSTR(error_message, 1, 300) as error_short')
                        ->withCount([
                            'rows as unmapped_rows_count' => function ($query) {
                                $query
                                    ->whereNull('label_id')
                                    ->whereNotNull('isrc')
                                    ->where('isrc', '!=', '');
                            },
                        ])
                        ->orderByDesc('id')
                        ->paginate(25),

                'uploadLimitMb' => $this->uploadLimitMb(),
                'settingLimitMb' => \App\Services\V2\PlatformRules::reportMaxMb(),
            ]
        );
    }


    /* MIXXTUNE_REPORT_IMPORTS_V2: the biggest file that can really be uploaded */
    private function uploadLimitMb(): int
    {
        $toMb = function ($v) {
            $v = trim((string) $v);
            if ($v === '' || $v === '0' || $v === '-1') {
                return PHP_INT_MAX;
            }
            $n = (float) $v;
            $u = strtolower(substr($v, -1));

            return (int) floor(match ($u) {
                'g' => $n * 1024,
                'm' => $n,
                'k' => $n / 1024,
                default => $n / 1048576,
            });
        };

        return max(1, min(
            \App\Services\V2\PlatformRules::reportMaxMb(),
            $toMb(ini_get('upload_max_filesize')),
            $toMb(ini_get('post_max_size'))
        ));
    }

    public function store(
        Request $request,
        PermissionService $permissions,
        ReportImportService $importer
    ): RedirectResponse|JsonResponse {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Admin access required.'
        );

        $validated = validator(
            $request->all(),
            [
                'reporting_month' => [
                    'required',
                    'date_format:Y-m',
                ],

                'report_file' => [
                    'required',
                    'file',
                    'mimes:csv,txt',
                    /* MIXXTUNE_SETTINGS_WIRED_V1: Master Settings > Upload Rules > Report Maximum MB */
                    'max:'.($this->uploadLimitMb() * 1024),
                ],
            ],
            [
                'report_file.max' => 'The file is bigger than '.$this->uploadLimitMb().' MB, the most this server accepts. Split the report into smaller CSV files (same reporting month) and upload each.',
                'report_file.mimes' => 'Upload the report as a .csv file.',
            ]
        )->validate();

        /*
         * Store file + audit record only.
         * Heavy CSV processing runs in the queue so the
         * browser request never waits for the full report.
         */
        $import = $importer->queueImport(
            $validated['report_file'],
            $request->user(),
            $validated['reporting_month']
        );

        try {
            ImportRevenueReportJob::dispatch(
                $import->id
            );
        } catch (\Throwable $exception) {
            $import->update([
                'status' =>
                    'failed',

                'completed_at' =>
                    now(),

                'error_message' =>
                    mb_substr(
                        $exception->getMessage(),
                        0,
                        65000
                    ),
            ]);

            throw $exception;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' =>
                    true,

                'message' =>
                    'Report uploaded and queued for import.',

                'import_id' =>
                    $import->id,

                'status' =>
                    $import->status,
            ], 202);
        }

        return back()->with(
            'success',
            'Report uploaded successfully and queued for background import.'
        );
    }

    public function show(
        Request $request,
        ReportImport $reportImport,
        PermissionService $permissions
    ): Response {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                ['admin', 'super_admin'],
                true
            ),
            403,
            'Admin access required.'
        );

        $validated = validator(
            $request->query(),
            [
                'search' => [
                    'nullable',
                    'string',
                    'max:191',
                ],
                'mapping_status' => [
                    'nullable',
                    'string',
                    'max:50',
                ],
            ]
        )->validate();

        $search = trim(
            (string) ($validated['search'] ?? '')
        );

        $mappingStatus = trim(
            (string) ($validated['mapping_status'] ?? '')
        );

        $rowsQuery = $reportImport
            ->rows()
            ->select([
                'id',
                'report_import_id',
                'reporting_month',
                'track_artist',
                'album_title',
                'album_artist',
                'label_name',
                'track_title',
                'isrc',
                'upc',
                'platform',
                'currency',
                'country_code',
                'cms',
                'sale_type',
                'sale_date',
                'sale_month',
                'streams',
                'sale_units',
                'label_rate',
                'collected_revenue',
                'earnings',
                'mapping_status',
                'revenue_owner_type',
                'revenue_owner_id',
                'mapped_at',
            ]);

        if ($search !== '') {
            $rowsQuery->where(
                function ($query) use ($search): void {
                    $like = '%'.$search.'%';

                    $query
                        ->where('track_title', 'like', $like)
                        ->orWhere('track_artist', 'like', $like)
                        ->orWhere('album_title', 'like', $like)
                        ->orWhere('album_artist', 'like', $like)
                        ->orWhere('label_name', 'like', $like)
                        ->orWhere('isrc', 'like', $like)
                        ->orWhere('upc', 'like', $like)
                        ->orWhere('platform', 'like', $like)
                        ->orWhere('country_code', 'like', $like);
                }
            );
        }

        if ($mappingStatus !== '') {
            $rowsQuery->where(
                'mapping_status',
                $mappingStatus
            );
        }

        $rows = $rowsQuery
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        $summary = DB::table('report_rows')
            ->where(
                'report_import_id',
                $reportImport->id
            )
            ->selectRaw(
                'COUNT(*) as row_count,
                 COALESCE(SUM(collected_revenue), 0) as collected_revenue,
                 COALESCE(SUM(earnings), 0) as earnings,
                 COALESCE(SUM(streams), 0) as streams,
                 COALESCE(SUM(sale_units), 0) as sale_units'
            )
            ->first();

        $financialBase = DB::table('report_rows')
            ->where(
                'report_import_id',
                $reportImport->id
            );

        $reviewRowCount =
            (clone $financialBase)->count();

        $reviewMappedRows = (clone $financialBase)
            ->where(
                'mapping_status',
                'mapped'
            )
            ->count();

        $reviewUnmappedRows = (clone $financialBase)
            ->where(function ($query) {
                $query
                    ->whereNull('mapping_status')
                    ->orWhere(
                        'mapping_status',
                        '!=',
                        'mapped'
                    );
            })
            ->count();

        $reviewUnmappedRevenue = round(
            (float) (clone $financialBase)
                ->where(function ($query) {
                    $query
                        ->whereNull('mapping_status')
                        ->orWhere(
                            'mapping_status',
                            '!=',
                            'mapped'
                        );
                })
                ->sum('collected_revenue'),
            8
        );

        $reviewMissingRateRows =
            (clone $financialBase)
                ->where(
                    'mapping_status',
                    'mapped'
                )
                ->where(
                    'collected_revenue',
                    '>',
                    0
                )
                ->where(function ($query) {
                    $query
                        ->whereNull('label_rate')
                        ->orWhere(
                            'label_rate',
                            '<=',
                            0
                        );
                })
                ->count();

        $reviewMissingRateRevenue = round(
            (float) (clone $financialBase)
                ->where(
                    'mapping_status',
                    'mapped'
                )
                ->where(
                    'collected_revenue',
                    '>',
                    0
                )
                ->where(function ($query) {
                    $query
                        ->whereNull('label_rate')
                        ->orWhere(
                            'label_rate',
                            '<=',
                            0
                        );
                })
                ->sum('collected_revenue'),
            8
        );

        $reviewInvalidEarningsRows =
            (clone $financialBase)
                ->where(
                    'mapping_status',
                    'mapped'
                )
                ->where(
                    'collected_revenue',
                    '>',
                    0
                )
                ->whereNull('earnings')
                ->count();

        $reviewCollectedRevenue = round(
            (float) (clone $financialBase)
                ->sum('collected_revenue'),
            8
        );

        $reviewEarnings = round(
            (float) (clone $financialBase)
                ->where(
                    'mapping_status',
                    'mapped'
                )
                ->sum('earnings'),
            8
        );

        $reviewReady =
            $reviewRowCount > 0
            && $reviewUnmappedRows === 0
            && $reviewMissingRateRows === 0
            && $reviewInvalidEarningsRows === 0
            && in_array(
                (string) $reportImport->status,
                [
                    'completed',
                    'completed_with_errors',
                ],
                true
            );

        $mappingCounts = DB::table('report_rows')
            ->where(
                'report_import_id',
                $reportImport->id
            )
            ->select(
                'mapping_status',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('mapping_status')
            ->orderBy('mapping_status')
            ->get();

        /*
         * FF383-C:
         * Actionable commercial-rate blockers for the
         * Super Admin final financial review.
         *
         * Collected Revenue remains the source amount.
         * This query does not recalculate or mutate rows.
         */
        $missingRateLabels = DB::table(
            'report_rows as rr'
        )
            ->join(
                'labels as l',
                function ($join) {
                    $join
                        ->on(
                            'l.id',
                            '=',
                            'rr.revenue_owner_id'
                        )
                        ->where(
                            'rr.revenue_owner_type',
                            '=',
                            'label'
                        );
                }
            )
            ->leftJoin(
                'users as u',
                'u.id',
                '=',
                'l.user_id'
            )
            ->where(
                'rr.report_import_id',
                $reportImport->id
            )
            ->where(
                'rr.mapping_status',
                'mapped'
            )
            ->where(
                'rr.collected_revenue',
                '>',
                0
            )
            ->whereNotNull(
                'rr.revenue_owner_id'
            )
            ->where(
                function ($query) {
                    $query
                        ->whereNull(
                            'rr.label_rate'
                        )
                        ->orWhere(
                            'rr.label_rate',
                            '<=',
                            0
                        );
                }
            )
            ->groupBy(
                'l.id',
                'l.name',
                'l.user_id',
                'l.revenue_share_percentage',
                'u.name',
                'u.email'
            )
            ->selectRaw(
                '
                    l.id AS label_id,
                    l.name AS label_name,
                    l.user_id,
                    u.name AS user_name,
                    u.email,
                    l.revenue_share_percentage AS current_rate,
                    COUNT(*) AS affected_rows,
                    ROUND(
                        SUM(rr.collected_revenue),
                        8
                    ) AS collected_revenue
                '
            )
            ->orderByDesc(
                DB::raw(
                    'SUM(rr.collected_revenue)'
                )
            )
            ->get()
            ->map(
                function ($item) {
                    return [
                        'label_id' =>
                            (int) $item->label_id,

                        'label_name' =>
                            $item->label_name,

                        'user_id' =>
                            $item->user_id
                                ? (int) $item->user_id
                                : null,

                        'user_name' =>
                            $item->user_name,

                        'email' =>
                            $item->email,

                        'current_rate' =>
                            $item->current_rate !== null
                                ? (float) $item->current_rate
                                : null,

                        'affected_rows' =>
                            (int) $item->affected_rows,

                        'collected_revenue' =>
                            (float) $item->collected_revenue,
                    ];
                }
            )
            ->values();

        return Inertia::render(
            'V2/Admin/Reports/ImportShow',
            [
                'role' => $role,

                'reportImport' => [
                    'id' => $reportImport->id,
                    'public_id' => $reportImport->public_id,
                    'original_filename' =>
                        $reportImport->original_filename,
                    'reporting_month' =>
                        $reportImport->reporting_month,
                    'status' => $reportImport->status,
                    'total_rows' =>
                        $reportImport->total_rows,
                    'imported_rows' =>
                        $reportImport->imported_rows,
                    'duplicate_rows' =>
                        $reportImport->duplicate_rows,
                    'failed_rows' =>
                        $reportImport->failed_rows,
                    'created_at' =>
                        $reportImport->created_at,
                    'completed_at' =>
                        $reportImport->completed_at,
                ],

                'rows' => $rows,

                'summary' => [
                    'row_count' =>
                        (int) ($summary->row_count ?? 0),
                    'collected_revenue' =>
                        (string) ($summary->collected_revenue ?? '0'),
                    'earnings' =>
                        (string) ($summary->earnings ?? '0'),
                    'streams' =>
                        (string) ($summary->streams ?? '0'),
                    'sale_units' =>
                        (string) ($summary->sale_units ?? '0'),
                ],

                'mappingCounts' =>
                    $mappingCounts,

                'missingRateLabels' =>
                $missingRateLabels,

            'financialReview' => [
                    'financial_status' =>
                        $reportImport->financial_status
                        ?? 'review_pending',

                    'publish_at' =>
                        $reportImport->publish_at,

                    'reviewed_at' =>
                        $reportImport->reviewed_at,

                    'approved_at' =>
                        $reportImport->approved_at,

                    'published_at' =>
                        $reportImport->published_at,

                    'review_note' =>
                        $reportImport->review_note,

                    'row_count' =>
                        $reviewRowCount,

                    'mapped_rows' =>
                        $reviewMappedRows,

                    'unmapped_rows' =>
                        $reviewUnmappedRows,

                    'unmapped_revenue' =>
                        (string) $reviewUnmappedRevenue,

                    'missing_rate_rows' =>
                        $reviewMissingRateRows,

                    'missing_rate_revenue' =>
                        (string) $reviewMissingRateRevenue,

                    'invalid_earnings_rows' =>
                        $reviewInvalidEarningsRows,

                    'collected_revenue' =>
                        (string) $reviewCollectedRevenue,

                    'earnings' =>
                        (string) $reviewEarnings,

                    'ready' =>
                        $reviewReady,
                ],

                'filters' => [
                    'search' => $search,
                    'mapping_status' => $mappingStatus,
                ],
            ]
        );
    }


    /**
     * Final financial approval gate.
     *
     * This action DOES NOT publish revenue and DOES NOT
     * post wallet transactions. It only freezes the current
     * review totals and schedules the batch for publishing.
     */

    /**
     * MIXXTUNE_BULK_MISSING_REVENUE_RATE_V1
     *
     * Save explicitly-entered commercial rates for label owners
     * blocking a review_pending revenue import.
     *
     * No default/guessed rates are applied.
     */
    public function saveMissingRevenueRates(
        Request $request,
        ReportImport $reportImport,
        PermissionService $permissions,
        \App\Services\V2\ReportEarningsRecalculationService $recalculator
    ): RedirectResponse {
        $role = $permissions->role($request->user());

        abort_unless(
            $role === 'super_admin',
            403,
            'Super Admin access required.'
        );

        if (
            (string) $reportImport->financial_status
            !== 'review_pending'
        ) {
            return back()->withErrors([
                'rates' =>
                    'Revenue rates can only be changed while the import is under financial review.',
            ]);
        }

        $validated = validator(
            $request->all(),
            [
                'rates' => [
                    'required',
                    'array',
                    'min:1',
                ],
                'rates.*' => [
                    'nullable',
                    'numeric',
                    'gt:0',
                    'lte:100',
                ],
            ]
        )->validate();

        $submitted = collect($validated['rates'] ?? [])
            ->filter(
                fn ($rate) =>
                    $rate !== null
                    && $rate !== ''
            );

        if ($submitted->isEmpty()) {
            return back()->withErrors([
                'rates' =>
                    'Enter at least one Revenue Rate.',
            ]);
        }

        $allowedLabelIds = DB::table('report_rows')
            ->where(
                'report_import_id',
                $reportImport->id
            )
            ->where(
                'mapping_status',
                'mapped'
            )
            ->where(
                'revenue_owner_type',
                'label'
            )
            ->where(
                'collected_revenue',
                '>',
                0
            )
            ->where(function ($query) {
                $query
                    ->whereNull('label_rate')
                    ->orWhere(
                        'label_rate',
                        '<=',
                        0
                    );
            })
            ->whereNotNull('revenue_owner_id')
            ->distinct()
            ->pluck('revenue_owner_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $allowed = array_fill_keys(
            $allowedLabelIds,
            true
        );

        $saved = 0;

        DB::transaction(
            function () use (
                $submitted,
                $allowed,
                $recalculator,
                &$saved
            ): void {
                foreach ($submitted as $labelId => $rate) {
                    $labelId = (int) $labelId;

                    abort_unless(
                        isset($allowed[$labelId]),
                        422,
                        'Submitted label is not an unresolved rate blocker for this import.'
                    );

                    $label = \App\Models\Core\Label::query()
                        ->whereKey($labelId)
                        ->lockForUpdate()
                        ->first();

                    abort_if(
                        !$label,
                        422,
                        'Revenue label was not found.'
                    );

                    $label->revenue_share_percentage =
                        round((float) $rate, 4);

                    $label->save();

                    /*
                     * Authoritative recalculation service only mutates
                     * review_pending report imports.
                     */
                    $recalculator->recalculateForLabel(
                        $label
                    );

                    $saved++;
                }
            }
        );

        return back()->with(
            'success',
            $saved.' Revenue Rate'.
            ($saved === 1 ? '' : 's').
            ' saved and pending financial rows recalculated.'
        );
    }


    /*
     * MIXXTUNE_REPORT_IMPORT_REDETECT_UNMAPPED_V1
     *
     * Re-runs canonical catalogue ISRC detection for only
     * currently unmapped rows of one review_pending import.
     *
     * No wallet posting.
     * No publication.
     * Approved/published snapshots are immutable.
     */
    public function reDetectUnmapped(
        \Illuminate\Http\Request $request,
        \App\Models\Reports\ReportImport $reportImport,
        \App\Services\V2\CatalogueOwnershipService $ownership,
        \App\Services\V2\ReportEarningsRecalculationService $earnings
    ) {
        abort_unless(
            $request->user()?->role === 'super_admin',
            403
        );

        abort_unless(
            $reportImport->financial_status === 'review_pending',
            422,
            'Only review_pending imports can be refreshed.'
        );

        abort_unless(
            in_array(
                $reportImport->status,
                [
                    'completed',
                    'completed_with_errors',
                ],
                true
            ),
            422,
            'Import processing must be completed before refresh.'
        );

        $before = \Illuminate\Support\Facades\DB::table(
            'report_rows'
        )
            ->where(
                'report_import_id',
                $reportImport->id
            )
            ->where(function ($query) {
                $query
                    ->whereNull('mapping_status')
                    ->orWhere(
                        'mapping_status',
                        'unmapped'
                    );
            })
            ->count();

        $result = $ownership->remapReports(
            true,
            null,
            (int) $reportImport->id
        );

        /*
         * Mapping changes ownership first.
         * Recalculate commercial earnings only for owners
         * touched by this import. The recalculation service
         * itself is protected by review_pending snapshot lock.
         */
        $owners = \Illuminate\Support\Facades\DB::table(
            'report_rows'
        )
            ->where(
                'report_import_id',
                $reportImport->id
            )
            ->where(
                'mapping_status',
                'mapped'
            )
            ->whereNotNull('revenue_owner_id')
            ->select(
                'revenue_owner_type',
                'revenue_owner_id'
            )
            ->distinct()
            ->get();

        foreach ($owners as $owner) {
            if (
                $owner->revenue_owner_type === 'label'
            ) {
                $label =
                    \App\Models\Core\Label::query()
                        ->find(
                            (int) $owner->revenue_owner_id
                        );

                if ($label) {
                    $earnings->recalculateForLabelInImport(
                        $label,
                        (int) $reportImport->id
                    );
                }
            } elseif (
                $owner->revenue_owner_type === 'artist'
            ) {
                $artist =
                    \App\Models\Core\Artist::query()
                        ->find(
                            (int) $owner->revenue_owner_id
                        );

                if ($artist) {
                    $earnings->recalculateForArtistInImport(
                        $artist,
                        (int) $reportImport->id
                    );
                }
            }
        }

        $after = \Illuminate\Support\Facades\DB::table(
            'report_rows'
        )
            ->where(
                'report_import_id',
                $reportImport->id
            )
            ->where(function ($query) {
                $query
                    ->whereNull('mapping_status')
                    ->orWhere(
                        'mapping_status',
                        'unmapped'
                    );
            })
            ->count();

        $newlyMapped = max(
            0,
            $before - $after
        );

        return back()->with(
            'success',
            'Catalogue refresh complete: '.
                number_format($newlyMapped).
                ' row(s) newly mapped, '.
                number_format($after).
                ' row(s) still unmapped. '.
                'No wallet or publication posting was performed.'
        );
    }


    public function approveAndSchedule(
        Request $request,
        ReportImport $reportImport,
        PermissionService $permissions
    ): RedirectResponse {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            $role === 'super_admin',
            403,
            'Super Admin access required.'
        );

        $validated = validator(
            $request->all(),
            [
                'publish_at' => [
                    'required',
                    'date',
                    'after_or_equal:today',
                ],
                'review_note' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]
        )->validate();

        if (
            !in_array(
                (string) $reportImport->status,
                [
                    'completed',
                    'completed_with_errors',
                ],
                true
            )
        ) {
            return back()->withErrors([
                'approval' =>
                    'Import processing must be complete before financial approval.',
            ]);
        }

        if (
            in_array(
                (string) $reportImport->financial_status,
                [
                    'approved_scheduled',
                    'published',
                ],
                true
            )
        ) {
            return back()->withErrors([
                'approval' =>
                    'This revenue import has already been approved.',
            ]);
        }

        $base = DB::table('report_rows')
            ->where(
                'report_import_id',
                $reportImport->id
            );

        $rowCount = (clone $base)->count();

        $mappedRows = (clone $base)
            ->where(
                'mapping_status',
                'mapped'
            )
            ->count();

        $unmappedRows = (clone $base)
            ->where(function ($query) {
                $query
                    ->whereNull('mapping_status')
                    ->orWhere(
                        'mapping_status',
                        '!=',
                        'mapped'
                    );
            })
            ->count();

        $unmappedRevenue = round(
            (float) (clone $base)
                ->where(function ($query) {
                    $query
                        ->whereNull('mapping_status')
                        ->orWhere(
                            'mapping_status',
                            '!=',
                            'mapped'
                        );
                })
                ->sum('collected_revenue'),
            8
        );

        $missingRateRows = (clone $base)
            ->where(
                'mapping_status',
                'mapped'
            )
            ->where(
                'collected_revenue',
                '>',
                0
            )
            ->where(function ($query) {
                $query
                    ->whereNull('label_rate')
                    ->orWhere(
                        'label_rate',
                        '<=',
                        0
                    );
            })
            ->count();

        $missingRateRevenue = round(
            (float) (clone $base)
                ->where(
                    'mapping_status',
                    'mapped'
                )
                ->where(
                    'collected_revenue',
                    '>',
                    0
                )
                ->where(function ($query) {
                    $query
                        ->whereNull('label_rate')
                        ->orWhere(
                            'label_rate',
                            '<=',
                            0
                        );
                })
                ->sum('collected_revenue'),
            8
        );

        $invalidEarningsRows = (clone $base)
            ->where(
                'mapping_status',
                'mapped'
            )
            ->where(
                'collected_revenue',
                '>',
                0
            )
            ->whereNull('earnings')
            ->count();

        /*
         * MIXXTUNE_CURRENCY_APPROVAL_GATE
         *
         * Every mapped financial row must have a
         * frozen currency before approval.
         */
        $missingCurrencyRows =
            DB::table('report_rows')
                ->where(
                    'report_import_id',
                    $reportImport->id
                )
                ->where(
                    'mapping_status',
                    'mapped'
                )
                ->where(function ($query) {
                    $query
                        ->whereNull('currency')
                        ->orWhere(
                            'currency',
                            ''
                        );
                })
                ->count();

        abort_if(
            $missingCurrencyRows > 0,
            422,
            'Approval blocked: '
            .$missingCurrencyRows
            .' mapped financial rows '
            .'do not have a resolved currency.'
        );


        /*
         * MIXXTUNE_FINANCIAL_OWNER_APPROVAL_GATE
         *
         * Every mapped revenue owner must resolve to a real,
         * active User before an import can enter the financial
         * publication lifecycle.
         */
        $invalidLabelOwnerRows =
            DB::table('report_rows as rr')
                ->leftJoin(
                    'labels as financial_label',
                    'financial_label.id',
                    '=',
                    'rr.revenue_owner_id'
                )
                ->leftJoin(
                    'users as financial_user',
                    'financial_user.id',
                    '=',
                    'financial_label.user_id'
                )
                ->where(
                    'rr.report_import_id',
                    $reportImport->id
                )
                ->where(
                    'rr.mapping_status',
                    'mapped'
                )
                ->where(
                    'rr.revenue_owner_type',
                    'label'
                )
                ->where(function ($query) {
                    $query
                        ->whereNull('financial_label.id')
                        ->orWhereNull(
                            'financial_label.user_id'
                        )
                        ->orWhereNull(
                            'financial_user.id'
                        )
                        ->orWhereNull(
                            'financial_user.account_status'
                        )
                        ->orWhere(
                            'financial_user.account_status',
                            '!=',
                            'active'
                        );
                })
                ->count();

        $invalidArtistOwnerRows =
            DB::table('report_rows as rr')
                ->leftJoin(
                    'artists as financial_artist',
                    'financial_artist.id',
                    '=',
                    'rr.revenue_owner_id'
                )
                ->leftJoin(
                    'users as financial_user',
                    'financial_user.id',
                    '=',
                    'financial_artist.user_id'
                )
                ->where(
                    'rr.report_import_id',
                    $reportImport->id
                )
                ->where(
                    'rr.mapping_status',
                    'mapped'
                )
                ->where(
                    'rr.revenue_owner_type',
                    'artist'
                )
                ->where(function ($query) {
                    $query
                        ->whereNull('financial_artist.id')
                        ->orWhereNull(
                            'financial_artist.user_id'
                        )
                        ->orWhereNull(
                            'financial_user.id'
                        )
                        ->orWhereNull(
                            'financial_user.account_status'
                        )
                        ->orWhere(
                            'financial_user.account_status',
                            '!=',
                            'active'
                        );
                })
                ->count();

        $unsupportedOwnerRows =
            DB::table('report_rows')
                ->where(
                    'report_import_id',
                    $reportImport->id
                )
                ->where(
                    'mapping_status',
                    'mapped'
                )
                ->where(function ($query) {
                    $query
                        ->whereNull('revenue_owner_type')
                        ->orWhereNull('revenue_owner_id')
                        ->orWhereNotIn(
                            'revenue_owner_type',
                            ['label', 'artist']
                        );
                })
                ->count();

        $invalidFinancialOwnerRows =
            $invalidLabelOwnerRows
            + $invalidArtistOwnerRows
            + $unsupportedOwnerRows;

        if ($invalidFinancialOwnerRows > 0) {
            return back()->withErrors([
                'approval' =>
                    'Approval blocked: '
                    .number_format(
                        $invalidFinancialOwnerRows
                    )
                    .' mapped rows do not resolve to a valid '
                    .'financial-owner user. Label rows: '
                    .number_format(
                        $invalidLabelOwnerRows
                    )
                    .'; Artist rows: '
                    .number_format(
                        $invalidArtistOwnerRows
                    )
                    .'; Unsupported owner rows: '
                    .number_format(
                        $unsupportedOwnerRows
                    )
                    .'.',
            ]);
        }

        if ($rowCount <= 0) {
            return back()->withErrors([
                'approval' =>
                    'This import has no stored revenue rows.',
            ]);
        }

        if ($unmappedRows > 0) {
            return back()->withErrors([
                'approval' =>
                    'Approval blocked: '.
                    number_format($unmappedRows).
                    ' rows are not mapped. Unmapped Collected Revenue: '.
                    number_format($unmappedRevenue, 8, '.', '').
                    '.',
            ]);
        }

        if ($missingRateRows > 0) {
            return back()->withErrors([
                'approval' =>
                    'Approval blocked: '.
                    number_format($missingRateRows).
                    ' positive-revenue mapped rows have a missing or zero commercial rate. Collected Revenue affected: '.
                    number_format($missingRateRevenue, 8, '.', '').
                    '.',
            ]);
        }

        if ($invalidEarningsRows > 0) {
            return back()->withErrors([
                'approval' =>
                    'Approval blocked: '.
                    number_format($invalidEarningsRows).
                    ' mapped positive-revenue rows do not have calculated earnings.',
            ]);
        }

        $collectedRevenue = round(
            (float) (clone $base)
                ->sum('collected_revenue'),
            8
        );

        $earnings = round(
            (float) (clone $base)
                ->where(
                    'mapping_status',
                    'mapped'
                )
                ->sum('earnings'),
            8
        );

        /* MIXXTUNE_TWO_STEP_ACTIONS_V1: e-mailed code before a report is approved for payout. */
        app(\App\Services\V2\ActionOtpGuard::class)->require(
            $request->user(),
            'report.approve:'.$reportImport->id,
            'approve revenue report #'.$reportImport->id.' for payout'
        );

        DB::transaction(
            function () use (
                $reportImport,
                $request,
                $validated,
                $rowCount,
                $mappedRows,
                $unmappedRows,
                $collectedRevenue,
                $earnings
            ): void {
                $locked = ReportImport::query()
                    ->whereKey($reportImport->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    in_array(
                        (string) $locked->financial_status,
                        [
                            'approved_scheduled',
                            'published',
                        ],
                        true
                    )
                ) {
                    throw new \RuntimeException(
                        'Revenue import was already approved.'
                    );
                }

                $locked->update([
                    'financial_status' =>
                        'approved_scheduled',

                    'publish_at' =>
                        $validated['publish_at'],

                    'reviewed_at' =>
                        now(),

                    'reviewed_by' =>
                        $request->user()->id,

                    'approved_at' =>
                        now(),

                    'approved_by' =>
                        $request->user()->id,

                    'review_row_count' =>
                        $rowCount,

                    'review_mapped_rows' =>
                        $mappedRows,

                    'review_unmapped_rows' =>
                        $unmappedRows,

                    'review_collected_revenue' =>
                        $collectedRevenue,

                    'review_earnings' =>
                        $earnings,

                    'review_note' =>
                        $validated['review_note']
                        ?? null,
                ]);
            }
        );

        /* MIXXTUNE_FINANCE_SAFETY_V1 */
        \App\Services\V2\FinanceAudit::log(
            'finance.report_approved',
            'Revenue report #'.$reportImport->id.' approved; publication scheduled for '.$validated['publish_at'].'.',
            [
                'report_import_id' => $reportImport->id,
                'publish_at' => $validated['publish_at'],
                'rows' => $rowCount,
                'mapped_rows' => $mappedRows,
                'unmapped_rows' => $unmappedRows,
                'collected_revenue' => $collectedRevenue,
                'earnings' => $earnings,
                'review_note' => $validated['review_note'] ?? null,
            ],
            $reportImport
        );

        return back()->with(
            'success',
            'Financial review approved. Revenue is scheduled for publication on the selected date.'
        );
    }


    public function update(
        Request $request,
        ReportImport $reportImport,
        PermissionService $permissions
    ): RedirectResponse {

        /*
         * MIXXTUNE_APPROVED_IMPORT_IMMUTABILITY_UPDATE
         *
         * Once Final Financial Review is approved, the import
         * becomes an immutable financial snapshot.
         *
         * Mapping/rates/calculations must not be changed or the
         * batch deleted after approval.
         */
        if (
            in_array(
                (string) $reportImport->financial_status,
                [
                    'approved_scheduled',
                    'published',
                ],
                true
            )
        ) {
            return back()->withErrors([
                'financial_status' =>
                    'Approved or published revenue imports are locked and cannot be modified.',
            ]);
        }

        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                ['admin', 'super_admin'],
                true
            ),
            403,
            'Admin access required.'
        );

        $validated = validator(
            $request->all(),
            [
                'reporting_month' => [
                    'required',
                    'date_format:Y-m',
                ],
            ]
        )->validate();

        DB::transaction(
            function () use ($reportImport, $validated): void {
                $reportImport->update([
                    'reporting_month' =>
                        $validated['reporting_month'],
                ]);

                DB::table('report_rows')
                    ->where(
                        'report_import_id',
                        $reportImport->id
                    )
                    ->update([
                        'reporting_month' =>
                            $validated['reporting_month'],
                        'updated_at' => now(),
                    ]);
            }
        );

        return back()->with(
            'success',
            'Reporting month updated successfully.'
        );
    }

    public function destroy(
        Request $request,
        ReportImport $reportImport,
        PermissionService $permissions
    ): RedirectResponse {

        /*
         * MIXXTUNE_APPROVED_IMPORT_IMMUTABILITY_DESTROY
         *
         * Once Final Financial Review is approved, the import
         * becomes an immutable financial snapshot.
         *
         * Mapping/rates/calculations must not be changed or the
         * batch deleted after approval.
         */
        if (
            in_array(
                (string) $reportImport->financial_status,
                [
                    'approved_scheduled',
                    'published',
                ],
                true
            )
        ) {
            return back()->withErrors([
                'financial_status' =>
                    'Approved or published revenue imports are locked and cannot be modified.',
            ]);
        }

        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                ['admin', 'super_admin'],
                true
            ),
            403,
            'Admin access required.'
        );

        $hasRoyaltyAllocations =
            DB::table('royalty_allocations')
                ->join(
                    'report_rows',
                    'report_rows.id',
                    '=',
                    'royalty_allocations.report_row_id'
                )
                ->where(
                    'report_rows.report_import_id',
                    $reportImport->id
                )
                ->exists();

        if ($hasRoyaltyAllocations) {
            return back()->withErrors([
                'delete' =>
                    'This report cannot be deleted because royalty allocations already exist.',
            ]);
        }

        $storedPath = $reportImport->stored_path;
        $errorPath = $reportImport->error_file_path;

        /* MIXXTUNE_FINANCE_SAFETY_V1 */
        \App\Services\V2\FinanceAudit::log(
            'finance.report_deleted',
            'Revenue report #'.$reportImport->id.' deleted.',
            [],
            $reportImport,
            \App\Services\V2\FinanceAudit::pick($reportImport, ['id', 'original_filename', 'reporting_month', 'financial_status', 'status', 'row_count'])
        );

        DB::transaction(
            function () use ($reportImport): void {
                $reportImport->delete();
            }
        );

        if ($storedPath) {
            Storage::disk('local')->delete($storedPath);
        }

        if ($errorPath) {
            Storage::disk('local')->delete($errorPath);
        }

        return back()->with(
            'success',
            'Report deleted successfully.'
        );
    }


    public function download(
        Request $request,
        ReportImport $reportImport,
        PermissionService $permissions
    ) {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Admin access required.'
        );

        abort_if(
            empty($reportImport->stored_path),
            404,
            'Report file not found.'
        );

        abort_unless(
            Storage::disk('local')->exists(
                $reportImport->stored_path
            ),
            404,
            'Report file not found.'
        );

        $extension = pathinfo(
            $reportImport->stored_path,
            PATHINFO_EXTENSION
        );

        $downloadName =
            trim(
                (string) $reportImport->original_filename
            );

        if ($downloadName === '') {
            $downloadName =
                'report-'.$reportImport->id.
                ($extension ? '.'.$extension : '');
        }

        return Storage::disk('local')->download(
            $reportImport->stored_path,
            $downloadName
        );
    }

}
