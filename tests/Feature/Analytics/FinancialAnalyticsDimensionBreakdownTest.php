<?php

namespace Tests\Feature\Analytics;

use App\Models\User;
use App\Services\V2\FinancialAnalyticsService;
use App\Services\V2\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialAnalyticsDimensionBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private function createReportImport(): int
    {
        return DB::table('report_imports')
            ->insertGetId([
                'public_id' =>
                    (string) \Illuminate\Support\Str::ulid(),

                'original_filename' =>
                    'k56zt3-financial-dimension.csv',

                'stored_path' =>
                    'tests/k56zt3-financial-dimension.csv',

                'status' =>
                    'completed',

                'total_rows' =>
                    4,

                'imported_rows' =>
                    4,

                'duplicate_rows' =>
                    0,

                'failed_rows' =>
                    0,

                'uploaded_by' =>
                    null,

                'started_at' =>
                    now(),

                'completed_at' =>
                    now(),

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
        ]);
    }

    private function serviceScope(
        User $user
    ): array {
        $service = app(
            FinancialAnalyticsService::class
        );

        $permissions = app(
            PermissionService::class
        );

        $query = $service->scopedStatements(
            $user,
            $permissions
        );

        return [
            $service,
            $query,
        ];
    }

    public function test_canonical_dimension_breakdowns_are_allocation_safe(): void
    {
        $user = $this->createSuperAdmin();
        $reportImportId =
            $this->createReportImport();

        $reportRowA = DB::table('report_rows')->insertGetId([
                        'report_import_id' =>
                $reportImportId,

'reporting_month' => '2026-06',
            'row_hash' => hash('sha256', 'K56ZT2-ROW-A'),
            'sale_month' => '2026-05',
            'platform' => 'Spotify',
            'country_code' => 'IN',
            'sale_type' => 'Subscription',
            'currency' => 'INR',
            'cms' => 'WMG',
            'isrc' => 'TEST00000001',
            'upc' => '100000000001',
            'earnings' => 9999,
            'streams' => 100,
            'sale_units' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reportRowB = DB::table('report_rows')->insertGetId([
                        'report_import_id' =>
                $reportImportId,

'reporting_month' => '2026-06',
            'row_hash' => hash('sha256', 'K56ZT2-ROW-B'),
            'sale_month' => '2026-05',
            'platform' => 'YouTube',
            'country_code' => 'US',
            'sale_type' => 'Ad Supported',
            'currency' => 'INR',
            'cms' => 'BLV',
            'isrc' => 'TEST00000002',
            'upc' => '100000000002',
            'earnings' => 8888,
            'streams' => 200,
            'sale_units' => 200,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $statementId = DB::table(
            'royalty_statements'
        )->insertGetId([
            'public_id' =>
                (string) \Illuminate\Support\Str::ulid(),

            'statement_month' => '2026-06',
            'gross_earnings' => 300,
            'net_payable' => 240,
            'currency' => 'INR',
            'status' => 'finalized',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('royalty_allocations')->insert([
            [
                'public_id' =>
                    (string) \Illuminate\Support\Str::ulid(),

                'royalty_statement_id' => $statementId,
                'report_row_id' => $reportRowA,
                'gross_amount' => 100,
                'net_amount' => 80,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'public_id' =>
                    (string) \Illuminate\Support\Str::ulid(),

                'royalty_statement_id' => $statementId,
                'report_row_id' => $reportRowB,
                'gross_amount' => 200,
                'net_amount' => 160,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        [$service, $scope] =
            $this->serviceScope($user);

        $saleTypes =
            $service->saleTypeBreakdown(
                clone $scope
            );

        $currencies =
            $service->currencyBreakdown(
                clone $scope
            );

        $cms =
            $service->cmsBreakdown(
                clone $scope
            );

        $subscription = collect($saleTypes)
            ->firstWhere(
                'sale_type',
                'Subscription'
            );

        $adSupported = collect($saleTypes)
            ->firstWhere(
                'sale_type',
                'Ad Supported'
            );

        $inr = collect($currencies)
            ->firstWhere(
                'currency',
                'INR'
            );

        $wmg = collect($cms)
            ->firstWhere(
                'cms',
                'WMG'
            );

        $blv = collect($cms)
            ->firstWhere(
                'cms',
                'BLV'
            );

        $this->assertNotNull($subscription);
        $this->assertNotNull($adSupported);
        $this->assertNotNull($inr);
        $this->assertNotNull($wmg);
        $this->assertNotNull($blv);

        $this->assertSame(
            100.0,
            $subscription['gross_earnings']
        );

        $this->assertSame(
            80.0,
            $subscription['net_payable']
        );

        $this->assertSame(
            200.0,
            $adSupported['gross_earnings']
        );

        $this->assertSame(
            160.0,
            $adSupported['net_payable']
        );

        $this->assertSame(
            300.0,
            $inr['gross_earnings']
        );

        $this->assertSame(
            240.0,
            $inr['net_payable']
        );

        $this->assertSame(
            100.0,
            $wmg['gross_earnings']
        );

        $this->assertSame(
            200.0,
            $blv['gross_earnings']
        );

        /*
         * Critical proof:
         * raw report earnings must never become
         * canonical financial revenue.
         */
        $this->assertNotSame(
            9999.0,
            $subscription['gross_earnings']
        );

        $this->assertNotSame(
            8888.0,
            $adSupported['gross_earnings']
        );
    }

    public function test_dimension_filter_limits_breakdowns_to_matching_allocations(): void
    {
        $user = $this->createSuperAdmin();
        $reportImportId =
            $this->createReportImport();

        $rowA = DB::table('report_rows')->insertGetId([
                        'report_import_id' =>
                $reportImportId,

'reporting_month' => '2026-06',
            'row_hash' => hash('sha256', 'K56ZT2-FILTER-A'),
            'sale_month' => '2026-05',
            'platform' => 'Spotify',
            'country_code' => 'IN',
            'sale_type' => 'Subscription',
            'currency' => 'INR',
            'cms' => 'WMG',
            'isrc' => 'FILTER0000001',
            'upc' => '200000000001',
            'earnings' => 5000,
            'streams' => 10,
            'sale_units' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rowB = DB::table('report_rows')->insertGetId([
                        'report_import_id' =>
                $reportImportId,

'reporting_month' => '2026-06',
            'row_hash' => hash('sha256', 'K56ZT2-FILTER-B'),
            'sale_month' => '2026-05',
            'platform' => 'YouTube',
            'country_code' => 'US',
            'sale_type' => 'Ad Supported',
            'currency' => 'INR',
            'cms' => 'BLV',
            'isrc' => 'FILTER0000002',
            'upc' => '200000000002',
            'earnings' => 6000,
            'streams' => 20,
            'sale_units' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $statementId = DB::table(
            'royalty_statements'
        )->insertGetId([
            'public_id' =>
                (string) \Illuminate\Support\Str::ulid(),

            'statement_month' => '2026-06',
            'gross_earnings' => 1000,
            'net_payable' => 900,
            'currency' => 'INR',
            'status' => 'finalized',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('royalty_allocations')->insert([
            [
                'public_id' =>
                    (string) \Illuminate\Support\Str::ulid(),

                'royalty_statement_id' => $statementId,
                'report_row_id' => $rowA,
                'gross_amount' => 125,
                'net_amount' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'public_id' =>
                    (string) \Illuminate\Support\Str::ulid(),

                'royalty_statement_id' => $statementId,
                'report_row_id' => $rowB,
                'gross_amount' => 875,
                'net_amount' => 800,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        [$service, $scope] =
            $this->serviceScope($user);

        $filters = [
            'platform' => 'Spotify',
        ];

        $saleTypes =
            $service->saleTypeBreakdown(
                clone $scope,
                $filters
            );

        $currencies =
            $service->currencyBreakdown(
                clone $scope,
                $filters
            );

        $cms =
            $service->cmsBreakdown(
                clone $scope,
                $filters
            );

        $this->assertCount(
            1,
            $saleTypes
        );

        $this->assertCount(
            1,
            $currencies
        );

        $this->assertCount(
            1,
            $cms
        );

        $this->assertSame(
            'Subscription',
            $saleTypes[0]['sale_type']
        );

        $this->assertSame(
            125.0,
            $saleTypes[0]['gross_earnings']
        );

        $this->assertSame(
            100.0,
            $saleTypes[0]['net_payable']
        );

        $this->assertSame(
            'INR',
            $currencies[0]['currency']
        );

        $this->assertSame(
            125.0,
            $currencies[0]['gross_earnings']
        );

        $this->assertSame(
            'WMG',
            $cms[0]['cms']
        );

        $this->assertSame(
            125.0,
            $cms[0]['gross_earnings']
        );
    }
}
