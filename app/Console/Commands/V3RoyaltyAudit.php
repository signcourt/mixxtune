<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V3RoyaltyAudit extends Command
{
    protected $signature =
        'v3:royalty-audit
        {--month= : Audit one sale month, for example 2025-07}';

    protected $description =
        'Audit report mapping, track splits and artist royalty readiness without changing data';

    public function handle(): int
    {
        $requiredTables = [
            'report_rows',
            'tracks',
            'releases',
            'track_splits',
            'contributors',
            'royalty_statements',
            'royalty_allocations',
        ];

        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                $this->error("Required table missing: {$table}");

                return self::FAILURE;
            }
        }

        $month = trim(
            (string) $this->option('month')
        );

        $base = DB::table('report_rows')
            ->where('mapping_status', 'mapped');

        if ($month !== '') {
            $base->where('sale_month', $month);
        }

        $totalRows = (clone $base)->count();

        $totalEarnings = (float) (
            (clone $base)->sum('earnings')
        );

        $rowsWithTrack = (clone $base)
            ->whereNotNull('track_id')
            ->count();

        $rowsWithoutTrack = (clone $base)
            ->whereNull('track_id')
            ->count();

        $splitReadyRows = (clone $base)
            ->whereNotNull('track_id')
            ->whereExists(function ($query) {
                $query
                    ->selectRaw('1')
                    ->from('track_splits as ts')
                    ->join(
                        'contributors as c',
                        'c.id',
                        '=',
                        'ts.contributor_id'
                    )
                    ->whereColumn(
                        'ts.track_id',
                        'report_rows.track_id'
                    )
                    ->where(
                        'ts.status',
                        'active'
                    )
                    ->where(
                        'ts.split_type',
                        'master'
                    )
                    ->whereNotNull(
                        'c.artist_id'
                    )
                    ->where(
                        'c.can_receive_splits',
                        true
                    )
                    ->whereNull(
                        'c.deleted_at'
                    );
            })
            ->count();

        $rowsMissingSplit = (clone $base)
            ->whereNotNull('track_id')
            ->whereNotExists(function ($query) {
                $query
                    ->selectRaw('1')
                    ->from('track_splits as ts')
                    ->join(
                        'contributors as c',
                        'c.id',
                        '=',
                        'ts.contributor_id'
                    )
                    ->whereColumn(
                        'ts.track_id',
                        'report_rows.track_id'
                    )
                    ->where(
                        'ts.status',
                        'active'
                    )
                    ->where(
                        'ts.split_type',
                        'master'
                    )
                    ->whereNotNull(
                        'c.artist_id'
                    )
                    ->where(
                        'c.can_receive_splits',
                        true
                    )
                    ->whereNull(
                        'c.deleted_at'
                    );
            })
            ->count();

        $splitCoverageEarnings = (float) (
            (clone $base)
                ->whereNotNull('track_id')
                ->whereExists(function ($query) {
                    $query
                        ->selectRaw('1')
                        ->from('track_splits as ts')
                        ->join(
                            'contributors as c',
                            'c.id',
                            '=',
                            'ts.contributor_id'
                        )
                        ->whereColumn(
                            'ts.track_id',
                            'report_rows.track_id'
                        )
                        ->where(
                            'ts.status',
                            'active'
                        )
                        ->where(
                            'ts.split_type',
                            'master'
                        )
                        ->whereNotNull(
                            'c.artist_id'
                        )
                        ->where(
                            'c.can_receive_splits',
                            true
                        )
                        ->whereNull(
                            'c.deleted_at'
                        );
                })
                ->sum('earnings')
        );

        $invalidSplitTotals = DB::table(
            'track_splits'
        )
            ->where(
                'status',
                'active'
            )
            ->where(
                'split_type',
                'master'
            )
            ->selectRaw(
                '
                track_id,
                SUM(percentage) as total_percentage,
                COUNT(*) as split_count
                '
            )
            ->groupBy('track_id')
            ->havingRaw(
                'ROUND(SUM(percentage), 4) <> 100'
            )
            ->count();

        $artistEstimate = DB::table(
            'report_rows as rr'
        )
            ->join(
                'track_splits as ts',
                'ts.track_id',
                '=',
                'rr.track_id'
            )
            ->join(
                'contributors as c',
                'c.id',
                '=',
                'ts.contributor_id'
            )
            ->where(
                'rr.mapping_status',
                'mapped'
            )
            ->where(
                'ts.status',
                'active'
            )
            ->where(
                'ts.split_type',
                'master'
            )
            ->whereNotNull(
                'c.artist_id'
            )
            ->where(
                'c.can_receive_splits',
                true
            )
            ->whereNull(
                'c.deleted_at'
            )
            ->when(
                $month !== '',
                fn ($query) =>
                    $query->where(
                        'rr.sale_month',
                        $month
                    )
            )
            ->selectRaw(
                '
                c.artist_id,
                COUNT(DISTINCT rr.id) as report_rows,
                COUNT(DISTINCT rr.track_id) as tracks,
                SUM(
                    rr.earnings
                    * ts.percentage
                    / 100
                ) as estimated_royalty
                '
            )
            ->groupBy(
                'c.artist_id'
            )
            ->orderByDesc(
                'estimated_royalty'
            )
            ->get();

        $artistEstimateTotal = (float) (
            $artistEstimate->sum(
                'estimated_royalty'
            )
        );

        $labelStatementTotal =
            (float) DB::table(
                'royalty_statements'
            )
                ->whereNotNull('label_id')
                ->when(
                    $month !== '',
                    fn ($query) =>
                        $query->where(
                            'statement_month',
                            $month
                        )
                )
                ->sum('net_payable');

        $artistStatementTotal =
            (float) DB::table(
                'royalty_statements'
            )
                ->whereNotNull('artist_id')
                ->when(
                    $month !== '',
                    fn ($query) =>
                        $query->where(
                            'statement_month',
                            $month
                        )
                )
                ->sum('net_payable');

        $scopeLabel =
            $month !== ''
                ? "Sale month: {$month}"
                : 'All mapped sale months';

        $this->newLine();
        $this->info(
            "Revenue Engine V3 Audit — {$scopeLabel}"
        );

        $this->table(
            ['Check', 'Value'],
            [
                [
                    'Mapped report rows',
                    number_format($totalRows),
                ],
                [
                    'Mapped earnings',
                    number_format(
                        $totalEarnings,
                        8
                    ),
                ],
                [
                    'Rows with track',
                    number_format($rowsWithTrack),
                ],
                [
                    'Rows without track',
                    number_format($rowsWithoutTrack),
                ],
                [
                    'Split-ready rows',
                    number_format($splitReadyRows),
                ],
                [
                    'Rows missing valid split',
                    number_format($rowsMissingSplit),
                ],
                [
                    'Split-covered earnings',
                    number_format(
                        $splitCoverageEarnings,
                        8
                    ),
                ],
                [
                    'Invalid track split totals',
                    number_format(
                        $invalidSplitTotals
                    ),
                ],
                [
                    'Estimated Artist royalties',
                    number_format(
                        $artistEstimateTotal,
                        8
                    ),
                ],
                [
                    'Existing Artist statements',
                    number_format(
                        $artistStatementTotal,
                        8
                    ),
                ],
                [
                    'Existing Label statements',
                    number_format(
                        $labelStatementTotal,
                        8
                    ),
                ],
            ]
        );

        $this->newLine();
        $this->info(
            'Estimated Artist Allocation by Artist'
        );

        if ($artistEstimate->isEmpty()) {
            $this->warn(
                'No split-ready Artist allocations found.'
            );
        } else {
            $this->table(
                [
                    'Artist ID',
                    'Rows',
                    'Tracks',
                    'Estimated Royalty',
                ],
                $artistEstimate
                    ->map(
                        fn ($row) => [
                            $row->artist_id,
                            number_format(
                                $row->report_rows
                            ),
                            number_format(
                                $row->tracks
                            ),
                            number_format(
                                (float) $row
                                    ->estimated_royalty,
                                8
                            ),
                        ]
                    )
                    ->all()
            );
        }

        $difference = round(
            $splitCoverageEarnings
            - $artistEstimateTotal,
            8
        );

        $this->newLine();

        if (abs($difference) <= 0.01) {
            $this->info(
                'PASS: Split-covered revenue reconciles with estimated Artist allocations.'
            );
        } else {
            $this->warn(
                'WARNING: Reconciliation difference = '
                .number_format(
                    $difference,
                    8
                )
            );
        }

        if ($invalidSplitTotals > 0) {
            $this->warn(
                'Some tracks do not total 100%. Do not generate Artist statements yet.'
            );
        }

        if (
            $rowsMissingSplit > 0
            || $rowsWithoutTrack > 0
        ) {
            $this->warn(
                'Some mapped report rows are not ready for Artist allocation.'
            );
        }

        $this->newLine();
        $this->comment(
            'Audit only: no database records were changed.'
        );

        return self::SUCCESS;
    }
}
