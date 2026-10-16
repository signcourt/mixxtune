<?php

namespace App\Jobs;

use App\Services\V2\LabelHierarchyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AutoResolveUnmappedRevenueJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;
    public int $tries = 2;
    public bool $failOnTimeout = true;

    public function __construct(
        public int $reportImportId,
        public ?int $userId = null
    ) {
        $this->onQueue('default');
    }

    public function handle(
        LabelHierarchyService $hierarchy
    ): void {
        $reportImport = DB::table('report_imports')
            ->where('id', $this->reportImportId)
            ->first([
                'id',
                'financial_status',
                'status',
            ]);

        if (!$reportImport) {
            Log::warning('Auto Resolve skipped: report import missing', [
                'report_import_id' => $this->reportImportId,
            ]);

            return;
        }

        if ($reportImport->financial_status !== 'review_pending') {
            Log::info('Auto Resolve skipped: import no longer review_pending', [
                'report_import_id' => $this->reportImportId,
                'financial_status' => $reportImport->financial_status,
            ]);

            return;
        }

        if (!in_array(
            $reportImport->status,
            ['completed', 'completed_with_errors'],
            true
        )) {
            Log::info('Auto Resolve skipped: processing incomplete', [
                'report_import_id' => $this->reportImportId,
                'status' => $reportImport->status,
            ]);

            return;
        }

        $normalizedSql = "
            REGEXP_REPLACE(
                UPPER(TRIM(isrc)),
                '[^A-Z0-9]',
                ''
            )
        ";

        $groups = DB::table('report_rows')
            ->where('report_import_id', $this->reportImportId)
            ->where(function ($query) {
                $query->whereNull('mapping_status')
                    ->orWhere('mapping_status', 'unmapped');
            })
            ->whereNotNull('isrc')
            ->where('isrc', '!=', '')
            ->selectRaw($normalizedSql.' as normalized_isrc')
            ->selectRaw('MIN(label_name) as report_label')
            ->groupByRaw($normalizedSql)
            ->get();

        $stats = [
            'isrc_checked' => 0,
            'rules_created' => 0,
            'mapped_rows' => 0,
            'skipped_existing_rule' => 0,
            'skipped_missing_label' => 0,
            'skipped_ambiguous_label' => 0,
            'skipped_missing_rate' => 0,
            'skipped_blank_label' => 0,
        ];

        foreach ($groups as $group) {
            $identifier = strtoupper(
                preg_replace(
                    '/[^A-Z0-9]/',
                    '',
                    (string) $group->normalized_isrc
                )
            );

            if ($identifier === '') {
                continue;
            }

            $stats['isrc_checked']++;

            $reportLabel = trim((string) $group->report_label);

            if ($reportLabel === '') {
                $stats['skipped_blank_label']++;
                continue;
            }

            $existingRule = DB::table('revenue_mapping_rules')
                ->where('identifier_type', 'isrc')
                ->where('identifier_value', $identifier)
                ->exists();

            if ($existingRule) {
                $stats['skipped_existing_rule']++;
                continue;
            }

            $matches = DB::table('labels')
                ->whereNull('deleted_at')
                ->whereRaw(
                    'LOWER(TRIM(name)) = ?',
                    [mb_strtolower($reportLabel)]
                )
                ->get([
                    'id',
                    'name',
                    'revenue_share_percentage',
                ]);

            if ($matches->isEmpty()) {
                $stats['skipped_missing_label']++;
                continue;
            }

            if ($matches->count() !== 1) {
                $stats['skipped_ambiguous_label']++;
                continue;
            }

            $label = $matches->first();

            if (
                $label->revenue_share_percentage === null ||
                !is_numeric($label->revenue_share_percentage) ||
                (float) $label->revenue_share_percentage < 0 ||
                (float) $label->revenue_share_percentage > 100
            ) {
                $stats['skipped_missing_rate']++;
                continue;
            }

            $catalogueLabelId = (int) $label->id;

            $financialOwnerId = (int) $hierarchy->rootLabelId(
                $catalogueLabelId
            );

            if ($financialOwnerId <= 0) {
                continue;
            }

            /*
             * Claim the permanent rule atomically.
             * Existing rule is never overwritten.
             */
            $created = DB::table('revenue_mapping_rules')
                ->insertOrIgnore([
                    'identifier_type' => 'isrc',
                    'identifier_value' => $identifier,
                    'owner_type' => 'label',
                    'owner_id' => $financialOwnerId,
                    'catalogue_label_id' => $catalogueLabelId,
                    'artist_id' => null,
                    'track_id' => null,
                    'release_id' => null,
                    'source' => 'auto_resolve_report_label',
                    'notes' => 'Auto-resolved from unique exact report label match.',
                    'created_by' => $this->userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            if (!$created) {
                $stats['skipped_existing_rule']++;
                continue;
            }

            $stats['rules_created']++;

            /*
             * Apply only to review_pending report rows.
             * Collected Revenue remains source amount.
             */
            $rate = (float) $label->revenue_share_percentage;
            $decimalRate = $rate / 100;

            $rows = DB::table('report_rows')
                ->join(
                    'report_imports',
                    'report_imports.id',
                    '=',
                    'report_rows.report_import_id'
                )
                ->where(
                    'report_imports.financial_status',
                    'review_pending'
                )
                ->where(function ($query) {
                    $query->whereNull('report_rows.mapping_status')
                        ->orWhere(
                            'report_rows.mapping_status',
                            'unmapped'
                        );
                })
                ->whereRaw(
                    "REGEXP_REPLACE(
                        UPPER(TRIM(report_rows.isrc)),
                        '[^A-Z0-9]',
                        ''
                    ) = ?",
                    [$identifier]
                )
                ->select([
                    'report_rows.id',
                    'report_rows.collected_revenue',
                ])
                ->get();

            foreach ($rows as $row) {
                $collected = (float) ($row->collected_revenue ?? 0);

                if ($collected < 0) {
                    $labelRate = 1;
                    $earnings = round($collected, 8);
                } else {
                    $labelRate = $decimalRate;
                    $earnings = round(
                        $collected * $decimalRate,
                        8
                    );
                }

                DB::table('report_rows')
                    ->where('id', $row->id)
                    ->where(function ($query) {
                        $query->whereNull('mapping_status')
                            ->orWhere(
                                'mapping_status',
                                'unmapped'
                            );
                    })
                    ->update([
                        'label_id' => $catalogueLabelId,
                        'artist_id' => null,
                        'revenue_owner_type' => 'label',
                        'revenue_owner_id' => $financialOwnerId,
                        'mapping_status' => 'mapped',
                        'mapped_at' => now(),
                        'label_rate' => $labelRate,
                        'earnings' => $earnings,
                        'updated_at' => now(),
                    ]);

                $stats['mapped_rows']++;
            }
        }

        $remaining = DB::table('report_rows')
            ->where('report_import_id', $this->reportImportId)
            ->where(function ($query) {
                $query->whereNull('mapping_status')
                    ->orWhere('mapping_status', 'unmapped');
            })
            ->count();

        Log::info('MIXXTUNE_AUTO_RESOLVE_JOB_COMPLETE', [
            'report_import_id' => $this->reportImportId,
            'user_id' => $this->userId,
            'stats' => $stats,
            'remaining_unmapped_rows' => $remaining,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('MIXXTUNE_AUTO_RESOLVE_JOB_FAILED', [
            'report_import_id' => $this->reportImportId,
            'user_id' => $this->userId,
            'error' => $exception?->getMessage(),
            'exception' => $exception,
        ]);
    }
}
