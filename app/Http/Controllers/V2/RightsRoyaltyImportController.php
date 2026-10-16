<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Finance\RightsRoyaltyImport;
use App\Services\Royalties\RightsRoyaltyImportService;
use App\Services\Royalties\RightsRoyaltyPublishingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RightsRoyaltyImportController extends Controller
{
    /*
     * MIXXTUNE_RIGHTS_ROYALTY_UPLOAD_API_V1
     */

    public function inspect(
        Request $request,
        RightsRoyaltyImportService $service
    ) {
        $validated = $request->validate([
            'report_file' => [
                'required',
                'file',
                'mimes:csv,txt,xlsx,xls',
                'max:51200',
            ],
        ]);

        return response()->json(
            $service->inspect(
                $validated['report_file']
            )
        );
    }

    public function store(
        Request $request,
        RightsRoyaltyImportService $service
    ) {
        $validated = $request->validate([
            'source' => [
                'required',
                'string',
                Rule::in([
                    'IPRS',
                    'PRS',
                    'TPL',
                    'OTHER',
                ]),
            ],

            'statement_month' => [
                'required',
                'date_format:Y-m',
            ],

            'currency' => [
                'required',
                'string',
                'size:3',
            ],

            'report_file' => [
                'required',
                'file',
                'mimes:csv,txt,xlsx,xls',
                'max:51200',
            ],

            'mapping' => [
                'required',
                'array',
            ],

            'mapping.isrc' => [
                'required',
                'string',
            ],

            'mapping.amount' => [
                'required',
                'string',
            ],

            'mapping.track_title' => [
                'nullable',
                'string',
            ],

            'mapping.rights_type' => [
                'nullable',
                'string',
            ],

            'mapping.territory' => [
                'nullable',
                'string',
            ],
        ]);

        $import = $service->import(
            $validated['report_file'],
            $validated,
            $request->user()->id
        );

        return response()->json([
            'message' =>
                'Royalty report processed successfully.',

            'import' =>
                $this->serializeImport($import),
        ], 201);
    }

    public function show(
        RightsRoyaltyImport $import
    ) {
        $rows = $import->rows()
            ->orderBy('source_row_number')
            ->paginate(100);

        return response()->json([
            'import' =>
                $this->serializeImport($import),

            'rows' => $rows,
        ]);
    }

    private function serializeImport(
        RightsRoyaltyImport $import
    ): array {
        $beneficiary = (float) $import
            ->rows()
            ->where('mapping_status', 'mapped')
            ->sum('beneficiary_amount');

        $company = (float) $import
            ->rows()
            ->where('mapping_status', 'mapped')
            ->sum('company_amount');

        return [
            'public_id' =>
                $import->public_id,

            'source' =>
                $import->source,

            'statement_month' =>
                $import->statement_month,

            'currency' =>
                $import->currency,

            'original_filename' =>
                $import->original_filename,

            'status' =>
                $import->status,

            'total_rows' =>
                $import->total_rows,

            'mapped_rows' =>
                $import->mapped_rows,

            'unmapped_rows' =>
                $import->unmapped_rows,

            'gross_amount' =>
                $import->gross_amount,

            'mapped_amount' =>
                $import->mapped_amount,

            'unmapped_amount' =>
                $import->unmapped_amount,

            'beneficiary_amount' =>
                round($beneficiary, 8),

            'company_amount' =>
                round($company, 8),

            'processed_at' =>
                optional(
                    $import->processed_at
                )?->toISOString(),
        ];
    }

    /*
     * MIXXTUNE_RIGHTS_LIFECYCLE_V1
     *
     * Super Admin routes only.
     * Approval freezes mapping/allocation snapshots.
     * Publish materializes royalty ledgers only.
     */

    public function approve(
        RightsRoyaltyImport $import,
        RightsRoyaltyPublishingService $publishing
    ) {
        try {
            $approved = $publishing->approve(
                $import,
                auth()->id()
            );

            return response()->json([
                'ok' => true,
                'message' =>
                    'Rights royalty import approved.',
                'import' =>
                    $this->serializeImport($approved),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function publish(
        RightsRoyaltyImport $import,
        RightsRoyaltyPublishingService $publishing
    ) {
        try {
            $result = $publishing->publish(
                $import,
                auth()->id()
            );

            return response()->json([
                'ok' => true,
                'message' =>
                    'Rights royalty import published.',
                'created_ledgers' =>
                    (int) $result['created'],
                'skipped_ledgers' =>
                    (int) $result['skipped'],
                'import' =>
                    $this->serializeImport(
                        $result['import']
                    ),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

}
