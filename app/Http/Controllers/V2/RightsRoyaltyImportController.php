<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Finance\RightsRoyaltyImport;
use App\Services\Royalties\RightsRoyaltyImportService;
use App\Services\Royalties\RightsRoyaltyPublishingService;
use App\Services\V2\ActionOtpGuard;
use App\Services\V2\FinanceAudit;
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
        Request $request,
        RightsRoyaltyImport $import,
        RightsRoyaltyPublishingService $publishing
    ) {
        /* W54: plain words before any code is e-mailed */
        if ($import->status !== 'review_required') {
            return response()->json([
                'ok' => false,
                'message' => $import->status === 'approved' || $import->status === 'published'
                    ? 'This report is already '.$import->status.'.'
                    : 'This report cannot be approved in its current state ('.$import->status.').',
            ], 422);
        }

        /* W54: e-mailed code, same as revenue report approval */
        app(ActionOtpGuard::class)->require(
            $request->user(),
            'rights.approve:'.$import->public_id,
            'approve rights royalty report '.$import->source.' '.$import->statement_month
        );

        try {
            $approved = $publishing->approve(
                $import,
                auth()->id()
            );

            FinanceAudit::log(
                'finance.rights_import_approved',
                'Rights royalty report approved: '.$approved->source.' '.$approved->statement_month.' ('.$approved->original_filename.').',
                FinanceAudit::pick($approved, ['public_id', 'source', 'statement_month', 'currency', 'status', 'mapped_amount', 'unmapped_amount']),
                $approved
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
        Request $request,
        RightsRoyaltyImport $import,
        RightsRoyaltyPublishingService $publishing
    ) {
        if (!in_array($import->status, ['approved', 'published'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Approve the report first, then publish it.',
            ], 422);
        }

        /* published again = nothing new is created (idempotent), so no code needed */
        if ($import->status === 'approved') {
            app(ActionOtpGuard::class)->require(
                $request->user(),
                'rights.publish:'.$import->public_id,
                'publish rights royalty report '.$import->source.' '.$import->statement_month
            );
        }

        try {
            $result = $publishing->publish(
                $import,
                auth()->id()
            );

            if ((int) $result['created'] > 0) FinanceAudit::log(
                'finance.rights_import_published',
                'Rights royalty report published: '.$import->source.' '.$import->statement_month.' - '.(int) $result['created'].' ledger line(s) created.',
                ['public_id' => $import->public_id, 'created_ledgers' => (int) $result['created'], 'skipped_ledgers' => (int) $result['skipped']],
                $result['import']
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
