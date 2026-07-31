<?php

namespace App\Services\Royalties;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoyaltyPostingService
{
    public function preview(
        ?string $reportingMonth = null,
        ?string $saleMonth = null
    ): array
    {
        $rows = $this->baseQuery(
            $reportingMonth,
            $saleMonth
        )
            ->select([
                'revenue_label_mappings.id as mapping_id',
                'revenue_label_mappings.label_id',
                'revenue_label_mappings.revenue_label_name',
                'revenue_label_mappings.royalty_percentage',
                'revenue_rows.reporting_month',
                'revenue_rows.sale_month',
                'revenue_rows.currency',
                DB::raw('COUNT(DISTINCT revenue_rows.isrc) as unique_tracks'),
                DB::raw('COALESCE(SUM(revenue_rows.streams), 0) as total_streams'),
                DB::raw('COALESCE(SUM(revenue_rows.gross_amount), 0) as gross_amount'),
                DB::raw('COALESCE(SUM(revenue_rows.net_amount), 0) as net_amount'),
            ])
            ->groupBy(
                'revenue_label_mappings.id',
                'revenue_label_mappings.label_id',
                'revenue_label_mappings.revenue_label_name',
                'revenue_label_mappings.royalty_percentage',
                'revenue_rows.reporting_month',
                'revenue_rows.sale_month',
                'revenue_rows.currency'
            )
            ->orderBy('revenue_rows.sale_month')
            ->orderBy('revenue_label_mappings.revenue_label_name')
            ->get();

        return $rows->map(function ($row) {
            $percentage = (float) $row->royalty_percentage;
            $netAmount = (float) $row->net_amount;
            $payable = round($netAmount * $percentage / 100, 8);

            return [
                'mapping_id' => (int) $row->mapping_id,
                'label_id' => (int) $row->label_id,
                'label_name' => $row->revenue_label_name,
                'reporting_month' => $row->reporting_month,
                'sale_month' => $row->sale_month,
                'currency' => $row->currency ?: 'INR',
                'unique_tracks' => (int) $row->unique_tracks,
                'total_streams' => (int) $row->total_streams,
                'gross_amount' => (float) $row->gross_amount,
                'net_amount' => $netAmount,
                'share_percentage' => $percentage,
                'beneficiary_amount' => $payable,
            ];
        })->values()->all();
    }

    public function post(
        ?string $reportingMonth = null,
        ?string $saleMonth = null,
        ?int $userId = null
    ): array {
        $previewRows = $this->preview(
            $reportingMonth,
            $saleMonth
        );

        $created = 0;
        $skipped = 0;
        $postedRows = [];

        DB::transaction(function () use (
            $previewRows,
            $userId,
            &$created,
            &$skipped,
            &$postedRows
        ) {
            foreach ($previewRows as $row) {
                $reportingMonth = date(
                    'Y-m-01',
                    strtotime((string) $row['reporting_month'])
                );

                $saleMonth = date(
                    'Y-m-01',
                    strtotime((string) $row['sale_month'])
                );

                $statementMonth = date(
                    'Y-m',
                    strtotime($reportingMonth)
                );

                $exists = DB::table('royalty_ledgers')
                    ->where(
                        'revenue_label_mapping_id',
                        $row['mapping_id']
                    )
                    ->where('label_id', $row['label_id'])
                    ->whereDate(
                        'reporting_month',
                        $reportingMonth
                    )
                    ->whereDate(
                        'sale_month',
                        $saleMonth
                    )
                    ->where('currency', $row['currency'])
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $nextSequence = DB::table('royalty_ledgers')
                    ->whereYear(
                        'reporting_month',
                        date('Y', strtotime($reportingMonth))
                    )
                    ->whereMonth(
                        'reporting_month',
                        date('m', strtotime($reportingMonth))
                    )
                    ->lockForUpdate()
                    ->count() + 1;

                $ledgerNumber = sprintf(
                    'RL-%s-%06d',
                    date('Ym', strtotime($reportingMonth)),
                    $nextSequence
                );

                $ledgerId = DB::table('royalty_ledgers')->insertGetId([
                    'public_id' => (string) Str::ulid(),
                    'ledger_number' => $ledgerNumber,
                    'release_id' => null,
                    'track_id' => null,
                    'artist_id' => null,
                    'user_id' => null,
                    'label_id' => $row['label_id'],
                    'revenue_label_mapping_id' => $row['mapping_id'],
                    'statement_month' => $statementMonth,
                    'reporting_month' => $reportingMonth,
                    'sale_month' => $saleMonth,
                    'store_name' => 'ALL STORES',
                    'territory' => null,
                    'currency' => $row['currency'],
                    'gross_amount' => $row['gross_amount'],
                    'label_share' => $row['net_amount'],
                    'artist_share' => 0,
                    'split_percentage' => $row['share_percentage'],
                    'payable_amount' => $row['beneficiary_amount'],
                    'streams' => $row['total_streams'],
                    'status' => 'draft',
                    'metadata' => json_encode([
                        'label_name' => $row['label_name'],
                        'unique_tracks' => $row['unique_tracks'],
                        'source' => 'revenue_label_mapping',
                        'reporting_month' => $reportingMonth,
                        'sale_month' => $saleMonth,
                    ]),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $created++;

                $postedRows[] = [
                    'ledger_id' => $ledgerId,
                    'ledger_number' => $ledgerNumber,
                    ...$row,
                ];
            }
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
            'rows' => $postedRows,
        ];
    }

    private function baseQuery(
        ?string $reportingMonth = null,
        ?string $saleMonth = null
    )
    {
        $query = DB::table('revenue_rows')
            ->join(
                'revenue_label_mappings',
                function ($join) {
                    $join->on(
                        DB::raw('LOWER(TRIM(revenue_rows.label_name))'),
                        '=',
                        'revenue_label_mappings.normalized_name'
                    );
                }
            )
            ->where('revenue_label_mappings.status', 'mapped')
            ->whereNotNull('revenue_label_mappings.label_id');

        if (
            $reportingMonth !== null &&
            $reportingMonth !== ''
        ) {
            $query->whereDate(
                'revenue_rows.reporting_month',
                $reportingMonth
            );
        }

        if ($saleMonth !== null && $saleMonth !== '') {
            $query->whereDate(
                'revenue_rows.sale_month',
                $saleMonth
            );
        }

        return $query;
    }
}
