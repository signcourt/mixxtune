<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\MasterReportBatch;
use App\Models\MasterReportRow;
use App\Services\MasterReportAnalyzerService;
use App\Services\V2\PermissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasterReportAnalyzerController extends Controller
{
    private const EXPORTABLE_COLUMNS = [
        'source_row_number' => 'Source Row',
        'store' => 'Store',
        'report_month' => 'Report Month',
        'track_title' => 'Track Title',
        'version' => 'Version',
        'artist' => 'Artist',
        'label' => 'Label',
        'isrc' => 'ISRC',
        'upc' => 'UPC',
        'release_title' => 'Release / Album',
        'country' => 'Country',
        'streams' => 'Streams / Units',
        'gross_revenue' => 'Gross Revenue',
        'vendor_revenue' => 'Vendor Revenue',
        'deduction_amount' => 'Deduction',
        'deduction_percent' => 'Deduction %',
        'currency' => 'Currency',
        'content_type' => 'Content Type',
        'usage_type' => 'Usage Type',
        'subscription_type' => 'Subscription Type',
        'validation_status' => 'Validation Status',
    ];

    public function index(
        Request $request,
        PermissionService $permissions
    ): JsonResponse {
        $this->authorizeSuperAdmin($request, $permissions);

        $batches = MasterReportBatch::query()
            ->with('uploader:id,name,email')
            ->latest('id')
            ->paginate(
                min(max((int) $request->integer('per_page', 25), 1), 100)
            );

        return response()->json([
            'success' => true,
            'batches' => $batches,
        ]);
    }

    public function upload(
        Request $request,
        PermissionService $permissions,
        MasterReportAnalyzerService $analyzer
    ): JsonResponse {
        $this->authorizeSuperAdmin($request, $permissions);

        $validated = validator(
            $request->all(),
            [
                'report_file' => [
                    'required',
                    'file',
                    'mimes:csv,xlsx,xls',
                    'max:512000',
                ],
            ]
        )->validate();

        $batch = $analyzer->analyze(
            $validated['report_file'],
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Master report analyzed successfully.',
            'batch' => $batch,
        ], 201);
    }

    public function overview(
        Request $request,
        MasterReportBatch $masterReportBatch,
        PermissionService $permissions
    ): JsonResponse {
        $this->authorizeSuperAdmin($request, $permissions);

        $query = $this->filteredRows(
            $request,
            $masterReportBatch
        );

        $totals = (clone $query)
            ->selectRaw(
                'COUNT(*) AS total_rows,
                 COUNT(DISTINCT NULLIF(store, "")) AS total_stores,
                 COUNT(DISTINCT NULLIF(isrc, "")) AS total_isrcs,
                 COUNT(DISTINCT NULLIF(country, "")) AS total_countries,
                 COUNT(DISTINCT NULLIF(label, "")) AS total_labels,
                 COALESCE(SUM(streams), 0) AS total_streams,
                 COALESCE(SUM(gross_revenue), 0) AS gross_revenue,
                 COALESCE(SUM(vendor_revenue), 0) AS vendor_revenue,
                 COALESCE(SUM(deduction_amount), 0) AS deduction_amount'
            )
            ->first();

        $gross = (float) $totals->gross_revenue;
        $deduction = (float) $totals->deduction_amount;

        $warnings = (clone $query)
            ->where('validation_status', '<>', 'valid')
            ->count();

        return response()->json([
            'success' => true,
            'batch' => $masterReportBatch,
            'summary' => [
                'total_rows' => (int) $totals->total_rows,
                'total_stores' => (int) $totals->total_stores,
                'total_isrcs' => (int) $totals->total_isrcs,
                'total_countries' => (int) $totals->total_countries,
                'total_labels' => (int) $totals->total_labels,
                'total_streams' => (float) $totals->total_streams,
                'gross_revenue' => $gross,
                'vendor_revenue' =>
                    (float) $totals->vendor_revenue,
                'deduction_amount' => $deduction,
                'deduction_percent' =>
                    $gross != 0.0
                        ? ($deduction / $gross) * 100
                        : 0,
                'warning_rows' => $warnings,
            ],
        ]);
    }

    public function stores(
        Request $request,
        MasterReportBatch $masterReportBatch,
        PermissionService $permissions
    ): JsonResponse {
        $this->authorizeSuperAdmin($request, $permissions);

        $query = $this->filteredRows(
            $request,
            $masterReportBatch
        );

        $stores = $query
            ->selectRaw(
                'COALESCE(NULLIF(store, ""), "Unknown") AS store,
                 COUNT(*) AS source_rows,
                 COUNT(DISTINCT NULLIF(isrc, "")) AS unique_isrcs,
                 COALESCE(SUM(streams), 0) AS streams,
                 COALESCE(SUM(gross_revenue), 0) AS gross_revenue,
                 COALESCE(SUM(vendor_revenue), 0) AS vendor_revenue,
                 COALESCE(SUM(deduction_amount), 0) AS deduction_amount'
            )
            ->groupBy('store')
            ->orderByDesc('gross_revenue')
            ->get()
            ->map(function ($row): array {
                $gross = (float) $row->gross_revenue;
                $deduction = (float) $row->deduction_amount;

                return [
                    'store' => $row->store,
                    'source_rows' => (int) $row->source_rows,
                    'unique_isrcs' => (int) $row->unique_isrcs,
                    'streams' => (float) $row->streams,
                    'gross_revenue' => $gross,
                    'vendor_revenue' =>
                        (float) $row->vendor_revenue,
                    'deduction_amount' => $deduction,
                    'deduction_percent' =>
                        $gross != 0.0
                            ? ($deduction / $gross) * 100
                            : 0,
                ];
            });

        return response()->json([
            'success' => true,
            'stores' => $stores,
        ]);
    }

    public function tracks(
        Request $request,
        MasterReportBatch $masterReportBatch,
        PermissionService $permissions
    ): JsonResponse {
        $this->authorizeSuperAdmin($request, $permissions);

        $perPage = min(
            max((int) $request->integer('per_page', 50), 1),
            250
        );

        $rows = $this->filteredRows(
            $request,
            $masterReportBatch
        )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'rows' => $rows,
        ]);
    }

    public function reconciliation(
        Request $request,
        MasterReportBatch $masterReportBatch,
        PermissionService $permissions
    ): JsonResponse {
        $this->authorizeSuperAdmin($request, $permissions);

        $query = $this->filteredRows(
            $request,
            $masterReportBatch
        );

        $summary = (clone $query)
            ->selectRaw(
                'COALESCE(SUM(gross_revenue), 0) AS gross_revenue,
                 COALESCE(SUM(vendor_revenue), 0) AS vendor_revenue,
                 COALESCE(SUM(deduction_amount), 0) AS deduction_amount'
            )
            ->first();

        $gross = (float) $summary->gross_revenue;
        $vendor = (float) $summary->vendor_revenue;
        $difference = (float) $summary->deduction_amount;

        $rows = (clone $query)
            ->where(function (Builder $q): void {
                $q->where('deduction_amount', '<>', 0)
                    ->orWhere(
                        'validation_status',
                        '<>',
                        'valid'
                    );
            })
            ->orderByDesc('deduction_amount')
            ->paginate(
                min(
                    max(
                        (int) $request->integer('per_page', 50),
                        1
                    ),
                    250
                )
            )
            ->withQueryString();

        return response()->json([
            'success' => true,
            'summary' => [
                'gross_revenue' => $gross,
                'vendor_revenue' => $vendor,
                'difference' => $difference,
                'difference_percent' =>
                    $gross != 0.0
                        ? ($difference / $gross) * 100
                        : 0,
                'reason' => 'Unexplained Difference',
                'reason_note' =>
                    'No deduction reason is invented. A specific reason is shown only when the source report provides one.',
            ],
            'rows' => $rows,
        ]);
    }

    public function filterOptions(
        Request $request,
        MasterReportBatch $masterReportBatch,
        PermissionService $permissions
    ): JsonResponse {
        $this->authorizeSuperAdmin($request, $permissions);

        $base = MasterReportRow::query()
            ->where('batch_id', $masterReportBatch->id);

        return response()->json([
            'success' => true,
            'options' => [
                'stores' =>
                    $this->distinctValues($base, 'store'),
                'months' =>
                    $this->distinctValues($base, 'report_month'),
                'labels' =>
                    $this->distinctValues($base, 'label'),
                'artists' =>
                    $this->distinctValues($base, 'artist'),
                'countries' =>
                    $this->distinctValues($base, 'country'),
                'currencies' =>
                    $this->distinctValues($base, 'currency'),
            ],
            'exportable_columns' => self::EXPORTABLE_COLUMNS,
        ]);
    }

    public function export(
        Request $request,
        MasterReportBatch $masterReportBatch,
        PermissionService $permissions
    ): StreamedResponse|BinaryFileResponse {
        $this->authorizeSuperAdmin($request, $permissions);

        $validated = validator(
            $request->all(),
            [
                'format' => [
                    'nullable',
                    Rule::in(['csv', 'xlsx']),
                ],
                'scope' => [
                    'nullable',
                    Rule::in(['all', 'filtered', 'selected']),
                ],
                'columns' => [
                    'nullable',
                    'array',
                    'min:1',
                ],
                'columns.*' => [
                    'string',
                    Rule::in(
                        array_keys(self::EXPORTABLE_COLUMNS)
                    ),
                ],
                'row_ids' => [
                    'nullable',
                    'array',
                ],
                'row_ids.*' => [
                    'integer',
                    'min:1',
                ],
            ]
        )->validate();

        $format = $validated['format'] ?? 'csv';
        $scope = $validated['scope'] ?? 'filtered';

        $columns = $validated['columns']
            ?? array_keys(self::EXPORTABLE_COLUMNS);

        if ($scope === 'all') {
            $query = MasterReportRow::query()
                ->where(
                    'batch_id',
                    $masterReportBatch->id
                );
        } else {
            $query = $this->filteredRows(
                $request,
                $masterReportBatch
            );
        }

        if ($scope === 'selected') {
            $rowIds = $validated['row_ids'] ?? [];

            abort_if(
                $rowIds === [],
                422,
                'Select at least one row for selected export.'
            );

            $query->whereIn('id', $rowIds);
        }

        $filename =
            'master-report-'.$masterReportBatch->batch_code.
            '-'.now()->format('Ymd-His').'.'.$format;

        if ($format === 'xlsx') {
            return $this->exportXlsx(
                $query,
                $columns,
                $filename
            );
        }

        return $this->exportCsv(
            $query,
            $columns,
            $filename
        );
    }

    public function downloadOriginal(
        Request $request,
        MasterReportBatch $masterReportBatch,
        PermissionService $permissions
    ) {
        $this->authorizeSuperAdmin($request, $permissions);

        abort_if(
            empty($masterReportBatch->stored_path),
            404,
            'Original report file not found.'
        );

        abort_unless(
            Storage::disk('local')->exists(
                $masterReportBatch->stored_path
            ),
            404,
            'Original report file not found.'
        );

        return Storage::disk('local')->download(
            $masterReportBatch->stored_path,
            $masterReportBatch->original_filename
        );
    }

    public function destroy(
        Request $request,
        MasterReportBatch $masterReportBatch,
        PermissionService $permissions
    ): JsonResponse {
        $this->authorizeSuperAdmin($request, $permissions);

        $storedPath = $masterReportBatch->stored_path;

        $masterReportBatch->delete();

        if ($storedPath) {
            Storage::disk('local')->delete($storedPath);
        }

        return response()->json([
            'success' => true,
            'message' => 'Master report analysis deleted.',
        ]);
    }

    private function authorizeSuperAdmin(
        Request $request,
        PermissionService $permissions
    ): void {
        $role = $permissions->role($request->user());

        abort_unless(
            $role === 'super_admin',
            403,
            'Super Admin access required.'
        );
    }

    private function filteredRows(
        Request $request,
        MasterReportBatch $batch
    ): Builder {
        $query = MasterReportRow::query()
            ->where('batch_id', $batch->id);

        $exactFilters = [
            'store' => 'store',
            'month' => 'report_month',
            'report_month' => 'report_month',
            'label' => 'label',
            'artist' => 'artist',
            'isrc' => 'isrc',
            'upc' => 'upc',
            'country' => 'country',
            'currency' => 'currency',
            'validation_status' => 'validation_status',
        ];

        foreach ($exactFilters as $input => $column) {
            if ($request->filled($input)) {
                $query->where(
                    $column,
                    (string) $request->input($input)
                );
            }
        }

        if ($request->filled('track')) {
            $term = trim(
                (string) $request->input('track')
            );

            $query->where(
                'track_title',
                'like',
                '%'.$term.'%'
            );
        }

        if ($request->filled('search')) {
            $term = trim(
                (string) $request->input('search')
            );

            $query->where(
                function (Builder $q) use ($term): void {
                    $like = '%'.$term.'%';

                    $q->where('track_title', 'like', $like)
                        ->orWhere('artist', 'like', $like)
                        ->orWhere('label', 'like', $like)
                        ->orWhere('isrc', 'like', $like)
                        ->orWhere('upc', 'like', $like)
                        ->orWhere('release_title', 'like', $like);
                }
            );
        }

        $rangeFilters = [
            'streams_min' => ['streams', '>='],
            'streams_max' => ['streams', '<='],
            'gross_min' => ['gross_revenue', '>='],
            'gross_max' => ['gross_revenue', '<='],
            'vendor_min' => ['vendor_revenue', '>='],
            'vendor_max' => ['vendor_revenue', '<='],
            'deduction_min' => ['deduction_amount', '>='],
            'deduction_max' => ['deduction_amount', '<='],
            'deduction_percent_min' =>
                ['deduction_percent', '>='],
            'deduction_percent_max' =>
                ['deduction_percent', '<='],
        ];

        foreach (
            $rangeFilters as $input => [$column, $operator]
        ) {
            if (
                $request->filled($input) &&
                is_numeric($request->input($input))
            ) {
                $query->where(
                    $column,
                    $operator,
                    (float) $request->input($input)
                );
            }
        }

        return $query;
    }

    private function distinctValues(
        Builder $base,
        string $column
    ): array {
        return (clone $base)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct()
            ->orderBy($column)
            ->limit(1000)
            ->pluck($column)
            ->values()
            ->all();
    }

    private function exportCsv(
        Builder $query,
        array $columns,
        string $filename
    ): StreamedResponse {
        return response()->streamDownload(
            function () use ($query, $columns): void {
                $handle = fopen('php://output', 'w');

                fputcsv(
                    $handle,
                    array_map(
                        fn (string $column): string =>
                            self::EXPORTABLE_COLUMNS[$column],
                        $columns
                    )
                );

                $query
                    ->orderBy('id')
                    ->chunkById(
                        1000,
                        function ($rows) use (
                            $handle,
                            $columns
                        ): void {
                            foreach ($rows as $row) {
                                $values = [];

                                foreach ($columns as $column) {
                                    $values[] = $row->{$column};
                                }

                                fputcsv($handle, $values);
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

    private function exportXlsx(
        Builder $query,
        array $columns,
        string $filename
    ): BinaryFileResponse {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($columns as $index => $column) {
            $sheet->setCellValue(
                [$index + 1, 1],
                self::EXPORTABLE_COLUMNS[$column]
            );
        }

        $excelRow = 2;

        $query
            ->orderBy('id')
            ->chunkById(
                1000,
                function ($rows) use (
                    $sheet,
                    $columns,
                    &$excelRow
                ): void {
                    foreach ($rows as $row) {
                        foreach (
                            $columns as $index => $column
                        ) {
                            $sheet->setCellValue(
                                [$index + 1, $excelRow],
                                $row->{$column}
                            );
                        }

                        $excelRow++;
                    }
                }
            );

        $directory = storage_path(
            'app/master-report-analyzer/exports'
        );

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $path = $directory.'/'.$filename;

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        $spreadsheet->disconnectWorksheets();

        return response()
            ->download($path, $filename)
            ->deleteFileAfterSend(true);
    }
}
