<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Reports\GeneratedReport;
use App\Services\V2\GeneratedReportService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReportAnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Html;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GeneratedReportController extends Controller
{
    public function store(
        Request $request,
        GeneratedReportService $service
    ): JsonResponse {
        $validated = $request->validate([
            'from_month' => [
                'required',
                'regex:/^\d{4}-\d{2}$/',
            ],

            'to_month' => [
                'required',
                'regex:/^\d{4}-\d{2}$/',
            ],

            'scope' => [
                'nullable',
                'string',
                'max:50',
            ],

            'report_mode' => [
                'nullable',
                Rule::in([
                    'single',
                    'multiple',
                ]),
            ],

            'selected_columns' => [
                'required',
                'array',
                'min:1',
                'max:30',
            ],

            'selected_columns.*' => [
                'required',
                'string',
                'max:100',
            ],

            'filters' => [
                'nullable',
                'array',
            ],

            'filters.master_label_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'filters.level_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'filters.artist_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        $report = $service->generate(
            $request->user(),
            $validated
        );

        return response()->json([
            'message' =>
                'Report generated successfully.',

            'report' =>
                $this->serializeReport(
                    $report
                ),
        ], 201);
    }

    public function index(
        Request $request,
        ReportAnalyticsService $analytics,
        PermissionService $permissions
    ): JsonResponse {
        $reports =
            GeneratedReport::query()
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->where(
                    'report_type',
                    'requested'
                )
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(
                    fn (
                        GeneratedReport $report
                    ) =>
                        $this->serializeReport(
                            $report,
                            $this->staleState(
                                $request,
                                $report,
                                $analytics,
                                $permissions
                            )
                        )
                )
                ->values();

        return response()->json([
            'reports' => $reports,
        ]);
    }

    public function download(
        Request $request,
        string $publicId,
        ReportAnalyticsService $analytics,
        PermissionService $permissions
    ): BinaryFileResponse {
        $report =
            $this->ownedReport(
                $request,
                $publicId
            );

        abort_if(
            $this->staleState(
                $request,
                $report,
                $analytics,
                $permissions
            )['is_stale'],
            409,
            'This report is outdated. Generate a new report.'
        );


        abort_unless(
            $report->status === 'completed',
            409,
            'Report is not ready for download.'
        );

        abort_unless(
            $report->file_path,
            404,
            'Report file not found.'
        );

        abort_unless(
            Storage::disk('local')
                ->exists(
                    $report->file_path
                ),
            404,
            'Report file not found.'
        );

        return response()->download(
            Storage::disk('local')
                ->path(
                    $report->file_path
                ),
            $report->file_name
                ?: basename(
                    $report->file_path
                ),
            [
                'Content-Type' =>
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );
    }

    public function pdf(
        Request $request,
        string $publicId,
        ReportAnalyticsService $analytics,
        PermissionService $permissions
    ) {
        $report =
            $this->ownedReport(
                $request,
                $publicId
            );

        abort_if(
            $this->staleState(
                $request,
                $report,
                $analytics,
                $permissions
            )['is_stale'],
            409,
            'This report is outdated. Generate a new report.'
        );


        abort_unless(
            $report->status === 'completed',
            409,
            'Report is not ready for PDF export.'
        );

        abort_unless(
            $report->file_path,
            404,
            'Report file not found.'
        );

        abort_unless(
            Storage::disk('local')
                ->exists(
                    $report->file_path
                ),
            404,
            'Report file not found.'
        );

        return $this->pdfFromWorkbook(
            Storage::disk('local')
                ->path(
                    $report->file_path
                ),
            pathinfo(
                $report->file_name
                    ?: basename(
                        $report->file_path
                    ),
                PATHINFO_FILENAME
            ).'.pdf'
        );
    }

    public function automaticDownload(
        Request $request,
        string $month,
        GeneratedReportService $service,
        PermissionService $permissions
    ) {
        $this->validateMonth(
            $month
        );

        $permissions->authorize(
            $request->user(),
            'reports.view'
        );

        $report =
            $service->generate(
                $request->user(),
                $this->automaticPayload(
                    $month
                )
            );

        abort_unless(
            $report->status === 'completed'
                && $report->file_path
                && Storage::disk('local')
                    ->exists(
                        $report->file_path
                    ),
            404,
            'Automatic report file not found.'
        );

        $absolutePath =
            Storage::disk('local')
                ->path(
                    $report->file_path
                );

        $fileName =
            'royalty-report-'
            .$month
            .'.xlsx';

        $report->delete();

        return response()
            ->download(
                $absolutePath,
                $fileName,
                [
                    'Content-Type' =>
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

                    'X-Content-Type-Options' =>
                        'nosniff',
                ]
            )
            ->deleteFileAfterSend(
                true
            );
    }

    public function automaticPdf(
        Request $request,
        string $month,
        ReportAnalyticsService $analytics,
        PermissionService $permissions
    ) {
        $this->validateMonth(
            $month
        );

        $permissions->authorize(
            $request->user(),
            'reports.view'
        );

        $query =
            $analytics->scopedQuery(
                $request->user(),
                $permissions
            );

        $query->where(
            'reporting_month',
            $month
        );

        $totalRows =
            (clone $query)->count();

        $grossRevenue =
            (float)
            (clone $query)->sum(
                'earnings'
            );

        $mappedRevenue =
            (float)
            (clone $query)
                ->where(
                    'mapping_status',
                    'mapped'
                )
                ->sum(
                    'earnings'
                );

        $unmappedRevenue =
            (float)
            (clone $query)
                ->where(
                    'mapping_status',
                    'unmapped'
                )
                ->sum(
                    'earnings'
                );

        $summaryRows =
            (clone $query)
                ->leftJoin(
                    'labels as pdf_labels',
                    function ($join) {
                        $join
                            ->on(
                                'pdf_labels.id',
                                '=',
                                'report_rows.revenue_owner_id'
                            )
                            ->where(
                                'report_rows.revenue_owner_type',
                                '=',
                                'label'
                            );
                    }
                )
                ->select(
                    'report_rows.revenue_owner_type',
                    'report_rows.revenue_owner_id',
                    'report_rows.mapping_status',
                    'pdf_labels.name as owner_name'
                )
                ->selectRaw(
                    'COUNT(*) AS rows_count'
                )
                ->selectRaw(
                    'COALESCE(SUM(report_rows.earnings), 0) AS gross'
                )
                ->groupBy(
                    'report_rows.revenue_owner_type',
                    'report_rows.revenue_owner_id',
                    'report_rows.mapping_status',
                    'pdf_labels.name'
                )
                ->orderByDesc(
                    'gross'
                )
                ->get();

        $mappingPercentage =
            abs($grossRevenue) > 0.00000001
                ? (
                    $mappedRevenue
                    / $grossRevenue
                ) * 100
                : 0.0;

        $periodLabel =
            \Carbon\Carbon::createFromFormat(
                'Y-m',
                $month
            )->format(
                'F Y'
            );

        return Pdf::loadView(
            'pdf.monthly-report-summary',
            [
                'periodLabel' =>
                    $periodLabel,

                'currency' =>
                    'INR',

                'grossRevenue' =>
                    $grossRevenue,

                'mappedRevenue' =>
                    $mappedRevenue,

                'unmappedRevenue' =>
                    $unmappedRevenue,

                'mappingPercentage' =>
                    $mappingPercentage,

                'summaryRows' =>
                    $summaryRows,

                'totalRows' =>
                    $totalRows,

                'generatedAt' =>
                    now()
                        ->timezone(
                            config(
                                'app.timezone',
                                'UTC'
                            )
                        )
                        ->format(
                            'd M Y, h:i A'
                        ),
            ]
        )
            ->setPaper(
                'a4',
                'portrait'
            )
            ->download(
                'royalty-report-'
                .$month
                .'.pdf'
            );
    }


    public function destroy(
        Request $request,
        string $publicId
    ): JsonResponse {
        $report =
            $this->ownedReport(
                $request,
                $publicId
            );

        if (
            $report->file_path
            && Storage::disk('local')
                ->exists(
                    $report->file_path
                )
        ) {
            Storage::disk('local')
                ->delete(
                    $report->file_path
                );
        }

        $report->delete();

        return response()->json([
            'message' =>
                'Report deleted successfully.',
        ]);
    }

    private function validateMonth(
        string $month
    ): void {
        abort_unless(
            preg_match(
                '/^\d{4}-(0[1-9]|1[0-2])$/',
                $month
            ) === 1,
            422,
            'Invalid reporting month.'
        );
    }

    private function automaticPayload(
        string $month
    ): array {
        return [
            'from_month' =>
                $month,

            'to_month' =>
                $month,

            'scope' =>
                'full_catalogue',

            'report_mode' =>
                'single',

            'selected_columns' => [
                'Reporting Month',
                'Sales Month',
                'Track Artist',
                'Track Title',
                'Album Title',
                'Album Artist',
                'Label',
                'ISRC',
                'UPC',
                'Platform',
                'Country / Region',
                'CMS',
                'Sale Type',
                'Quantity / Streams',
                'Currency',
                'Collected Revenue',
                'Assigned Rate',
                'User Revenue',
            ],
        ];
    }

    private function pdfFromWorkbook(
        string $absolutePath,
        string $downloadName
    ) {
        $spreadsheet =
            IOFactory::load(
                $absolutePath
            );

        try {
            $writer =
                new Html(
                    $spreadsheet
                );

            ob_start();

            try {
                $writer->save(
                    'php://output'
                );

                $tableHtml =
                    (string)
                        ob_get_clean();
            } catch (\Throwable $e) {
                ob_end_clean();

                throw $e;
            }

            $html =
                '<!doctype html>'
                .'<html>'
                .'<head>'
                .'<meta charset="UTF-8">'
                .'<style>'
                .'@page{margin:18px;}'
                .'body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#111827;}'
                .'h1{font-size:16px;margin:0 0 14px 0;}'
                .'table{border-collapse:collapse;width:100%;}'
                .'td,th{border:1px solid #d1d5db;padding:4px 5px;vertical-align:top;}'
                .'thead td,thead th{background:#f3f4f6;font-weight:bold;}'
                .'</style>'
                .'</head>'
                .'<body>'
                .'<h1>Royalty Report</h1>'
                .$tableHtml
                .'</body>'
                .'</html>';

            return Pdf::loadHTML(
                $html
            )
                ->setPaper(
                    'a4',
                    'landscape'
                )
                ->download(
                    $downloadName
                );
        } finally {
            $spreadsheet
                ->disconnectWorksheets();
        }
    }

    private function staleState(
        Request $request,
        GeneratedReport $report,
        ReportAnalyticsService $analytics,
        PermissionService $permissions
    ): array {
        $query =
            $analytics->scopedQuery(
                $request->user(),
                $permissions
            );

        $query->whereBetween(
            'reporting_month',
            [
                $report->from_month,
                $report->to_month,
            ]
        );

        $filters =
            is_array($report->filters)
                ? $report->filters
                : [];

        $query =
            $analytics->applyFilters(
                $query,
                $filters
            );

        $currentRows =
            (clone $query)->count();

        $currentGross =
            round(
                (float)
                (clone $query)->sum(
                    'earnings'
                ),
                8
            );

        $savedRows =
            (int) $report->rows_count;

        $savedGross =
            round(
                (float)
                $report->gross_amount,
                8
            );

        $isStale =
            $savedRows !== $currentRows
            || abs(
                $savedGross
                - $currentGross
            ) > 0.0001;

        return [
            'is_stale' =>
                $isStale,

            'reason' =>
                $isStale
                    ? 'Current authorised data differs from this saved report. Generate a new report.'
                    : null,
        ];
    }


    private function ownedReport(
        Request $request,
        string $publicId
    ): GeneratedReport {
        return GeneratedReport::query()
            ->where(
                'public_id',
                $publicId
            )
            ->where(
                'user_id',
                $request->user()->id
            )
            ->where(
                'report_type',
                'requested'
            )
            ->firstOrFail();
    }

    private function serializeReport(
        GeneratedReport $report,
        ?array $staleState = null
    ): array {
        return [
            'id' =>
                $report->public_id,

            'from_month' =>
                $report->from_month,

            'to_month' =>
                $report->to_month,

            'scope' =>
                $report->scope,

            'report_mode' =>
                $report->report_mode,

            'selected_columns' =>
                $report->selected_columns
                ?? [],

            'currency' =>
                $report->currency,

            'gross_amount' =>
                (float)
                    $report->gross_amount,

            'net_amount' =>
                (float)
                    $report->net_amount,

            'rows_count' =>
                (int)
                    $report->rows_count,

            'status' =>
                $report->status,

            'is_stale' =>
                (bool) (
                    $staleState['is_stale']
                    ?? false
                ),

            'stale_reason' =>
                $staleState['reason']
                ?? null,

            'file_name' =>
                $report->file_name,

            'generated_at' =>
                optional(
                    $report->generated_at
                )->toIso8601String(),

            'created_at' =>
                optional(
                    $report->created_at
                )->toIso8601String(),
        ];
    }
}
