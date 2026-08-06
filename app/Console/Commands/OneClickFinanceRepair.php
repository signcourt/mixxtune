<?php

namespace App\Console\Commands;

use App\Services\V2\RoyaltyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OneClickFinanceRepair extends Command
{
    protected $signature =
        'system:finance-repair
        {--month= : Generate only this month, e.g. 2025-07}
        {--commission=0 : Commission percentage}';

    protected $description =
        'Repair report mappings and generate royalty statements safely';

    public function handle(
        RoyaltyService $royalties
    ): int {
        $this->info('Starting finance repair...');

        if (!Schema::hasTable('report_rows')) {
            $this->error('report_rows table missing.');
            return self::FAILURE;
        }

        if (!Schema::hasTable('royalty_statements')) {
            $this->error('royalty_statements table missing.');
            return self::FAILURE;
        }

        if (!Schema::hasTable('royalty_allocations')) {
            $this->error('royalty_allocations table missing.');
            return self::FAILURE;
        }

        $countryColumn = collect(
            DB::select("
                SHOW COLUMNS
                FROM report_rows
                LIKE 'country_code'
            ")
        )->first();

        if (
            $countryColumn
            && preg_match(
                '/varchar\((\d+)\)/i',
                $countryColumn->Type,
                $match
            )
            && (int) $match[1] < 100
        ) {
            DB::statement("
                ALTER TABLE report_rows
                MODIFY country_code VARCHAR(100) NULL
            ");

            $this->line(
                'country_code changed to VARCHAR(100).'
            );
        }

        if (
            $this->getApplication()
                ?->has('reports:repair-sale-months')
        ) {
            $this->call(
                'reports:repair-sale-months'
            );
        }

        if (
            $this->getApplication()
                ?->has('reports:backfill-mapping')
        ) {
            $this->call(
                'reports:backfill-mapping',
                ['--batch' => 500]
            );
        }

        $month = trim(
            (string) $this->option('month')
        );

        $months = $month !== ''
            ? collect([$month])
            : DB::table('report_rows')
                ->whereNotNull('artist_id')
                ->whereNotNull('sale_month')
                ->whereRaw(
                    "sale_month REGEXP '^[0-9]{4}-[0-9]{2}$'"
                )
                ->distinct()
                ->orderBy('sale_month')
                ->pluck('sale_month');

        $commission =
            (float) $this->option('commission');

        foreach ($months as $saleMonth) {
            $this->line(
                'Generating statements: '.$saleMonth
            );

            $result =
                $royalties
                    ->generateMonthlyStatements(
                        $saleMonth,
                        $commission
                    );

            $this->line(
                'Created: '.$result['created']
                .' | Updated: '.$result['updated']
                .' | Failed: '.$result['failed_count']
            );

            foreach ($result['failed'] as $failure) {
                $this->warn(
                    'Artist '.$failure['artist_id']
                    .': '.$failure['message']
                );
            }
        }

        $this->newLine();

        $this->table(
            ['Check', 'Value'],
            [
                [
                    'Report rows',
                    DB::table('report_rows')->count(),
                ],
                [
                    'Mapped report rows',
                    DB::table('report_rows')
                        ->whereNotNull('artist_id')
                        ->count(),
                ],
                [
                    'Unmapped report rows',
                    DB::table('report_rows')
                        ->whereNull('artist_id')
                        ->count(),
                ],
                [
                    'Royalty statements',
                    DB::table('royalty_statements')
                        ->count(),
                ],
                [
                    'Royalty allocations',
                    DB::table('royalty_allocations')
                        ->count(),
                ],
                [
                    'Statement net total',
                    DB::table('royalty_statements')
                        ->sum('net_payable'),
                ],
            ]
        );

        $this->info('Finance repair completed.');

        return self::SUCCESS;
    }
}
