<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Reports\GeneratedReport;
use App\Services\V2\GeneratedReportService;
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
        Request $request
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
                            $report
                        )
                )
                ->values();

        return response()->json([
            'reports' => $reports,
        ]);
    }

    public function download(
        Request $request,
        string $publicId
    ): BinaryFileResponse {
        $report =
            $this->ownedReport(
                $request,
                $publicId
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
        GeneratedReport $report
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
