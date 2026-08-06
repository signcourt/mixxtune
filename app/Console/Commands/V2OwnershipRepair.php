<?php

namespace App\Console\Commands;

use App\Services\V2\CatalogueOwnershipService;
use App\Services\V2\OwnershipRoyaltyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class V2OwnershipRepair extends Command
{
    protected $signature =
        'v2:ownership-repair
        {--month= : Generate statements for one month}
        {--commission=0 : Commission percentage}
        {--rebuild-pending : Remove pending/generated statements before rebuild}';

    protected $description =
        'Remap reports by ISRC/UPC and generate owner-based royalty statements';

    public function handle(
        CatalogueOwnershipService $ownership,
        OwnershipRoyaltyService $royalties
    ): int {
        $this->info(
            'Starting V2 ownership repair...'
        );

        $result =
            $ownership->remapReports();

        $this->line(
            'Mapped rows: '
            .$result['mapped']
        );

        $this->line(
            'Unmapped rows: '
            .$result['unmapped']
        );

        if ($this->option('rebuild-pending')) {
            $statementIds = DB::table(
                'royalty_statements'
            )
                ->whereIn(
                    'status',
                    [
                        'pending',
                        'generated',
                    ]
                )
                ->pluck('id');

            if ($statementIds->isNotEmpty()) {
                DB::table(
                    'royalty_allocations'
                )
                    ->whereIn(
                        'royalty_statement_id',
                        $statementIds
                    )
                    ->delete();

                DB::table(
                    'royalty_statements'
                )
                    ->whereIn(
                        'id',
                        $statementIds
                    )
                    ->delete();
            }

            $this->line(
                'Pending/generated statements cleared.'
            );
        }

        $month = trim(
            (string) $this->option('month')
        );

        $months = $month !== ''
            ? collect([$month])
            : DB::table('report_rows')
                ->where(
                    'mapping_status',
                    'mapped'
                )
                ->whereNotNull(
                    'sale_month'
                )
                ->whereRaw(
                    "sale_month REGEXP '^[0-9]{4}-[0-9]{2}$'"
                )
                ->distinct()
                ->orderBy('sale_month')
                ->pluck('sale_month');

        foreach ($months as $saleMonth) {
            $royaltyResult =
                $royalties
                    ->generateMonthlyStatements(
                        $saleMonth,
                        (float) $this->option(
                            'commission'
                        )
                    );

            $this->line(
                $saleMonth
                .' | Created: '
                .$royaltyResult['created']
                .' | Updated: '
                .$royaltyResult['updated']
                .' | Failed: '
                .$royaltyResult['failed_count']
            );

            foreach (
                $royaltyResult['failed']
                as $failure
            ) {
                $this->warn(
                    json_encode($failure)
                );
            }
        }

        $this->newLine();

        $this->table(
            ['Check', 'Value'],
            [
                [
                    'Total report rows',
                    DB::table(
                        'report_rows'
                    )->count(),
                ],
                [
                    'Mapped rows',
                    DB::table(
                        'report_rows'
                    )
                        ->where(
                            'mapping_status',
                            'mapped'
                        )
                        ->count(),
                ],
                [
                    'Unmapped rows',
                    DB::table(
                        'report_rows'
                    )
                        ->where(
                            'mapping_status',
                            'unmapped'
                        )
                        ->count(),
                ],
                [
                    'Label-owned rows',
                    DB::table(
                        'report_rows'
                    )
                        ->where(
                            'revenue_owner_type',
                            'label'
                        )
                        ->count(),
                ],
                [
                    'Artist-owned rows',
                    DB::table(
                        'report_rows'
                    )
                        ->where(
                            'revenue_owner_type',
                            'artist'
                        )
                        ->count(),
                ],
                [
                    'Statements',
                    DB::table(
                        'royalty_statements'
                    )->count(),
                ],
                [
                    'Transfer history',
                    DB::table(
                        'catalogue_transfers'
                    )->count(),
                ],
            ]
        );

        $this->info(
            'V2 ownership repair completed.'
        );

        return self::SUCCESS;
    }
}
