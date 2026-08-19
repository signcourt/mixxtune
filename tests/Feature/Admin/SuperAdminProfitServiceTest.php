<?php

namespace Tests\Feature\Admin;

use App\Services\V2\Admin\SuperAdminProfitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperAdminProfitServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_positive_and_negative_rows_follow_profit_rule(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Profit Label',
            'email' => 'profit-label@example.com',
            'password' => bcrypt('password'),
            'role' => 'label',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $labelId = DB::table('labels')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'name' => 'Profit Label',
            'slug' => 'profit-label',
            'user_id' => $userId,
            'revenue_share_percentage' => 80,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $importId =
            DB::table('report_imports')
                ->insertGetId([
                    'public_id' =>
                        (string) Str::ulid(),
                    'original_filename' =>
                        'profit-test.csv',
                    'stored_path' =>
                        'tests/profit-test.csv',
                    'status' => 'completed',
                    'total_rows' => 2,
                    'imported_rows' => 2,
                    'duplicate_rows' => 0,
                    'failed_rows' => 0,
                    'started_at' => now(),
                    'completed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        foreach (
            [100.00, -10.00]
            as $index => $earning
        ) {
            DB::table('report_rows')
                ->insert([
                    'report_import_id' =>
                        $importId,

                    'row_hash' =>
                        hash(
                            'sha256',
                            'profit-'
                            .$index
                            .'-'
                            .$earning
                        ),

                    'label_name' =>
                        'Profit Label',

                    'track_title' =>
                        'Profit Track',

                    'platform' =>
                        'Spotify',

                    'currency' =>
                        'INR',

                    'sale_date' =>
                        '2026-08-15',

                    'sale_month' =>
                        '2026-08',

                    'reporting_month' =>
                        '2026-08',

                    'earnings' =>
                        $earning,

                    'mapping_status' =>
                        'mapped',

                    'mapped_at' =>
                        now(),

                    'label_id' =>
                        $labelId,

                    'revenue_owner_type' =>
                        'label',

                    'revenue_owner_id' =>
                        $labelId,

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);
        }

        $service =
            app(
                SuperAdminProfitService::class
            );

        $summary =
            $service->summary(
                $service->baseQuery(
                    '2026-08'
                )
            );

        $this->assertEquals(
            90.0,
            $summary[
                'collected_revenue'
            ]
        );

        $this->assertEquals(
            70.0,
            $summary[
                'user_earning'
            ]
        );

        $this->assertEquals(
            20.0,
            $summary[
                'super_admin_profit'
            ]
        );

        $this->assertEquals(
            -10.0,
            $summary[
                'negative_adjustments'
            ]
        );

        $breakdown =
            $service->breakdown(
                $service->baseQuery(
                    '2026-08'
                )
            );

        $this->assertCount(
            1,
            $breakdown
        );

        $this->assertEquals(
            80.0,
            $breakdown[0][
                'assigned_rate'
            ]
        );

        $this->assertEquals(
            90.0,
            $breakdown[0][
                'collected_revenue'
            ]
        );

        $this->assertEquals(
            70.0,
            $breakdown[0][
                'user_earning'
            ]
        );

        $this->assertEquals(
            20.0,
            $breakdown[0][
                'super_admin_profit'
            ]
        );
    }

    public function test_negative_row_uses_100_percent_effective_rate(): void
    {
        $service =
            app(
                SuperAdminProfitService::class
            );

        $row = (object) [
            'earnings' => -10,
            'revenue_owner_type' =>
                'label',
            'revenue_owner_id' =>
                999999,
        ];

        $result =
            $service->calculateRow(
                $row
            );

        $this->assertEquals(
            100.0,
            $result[
                'assigned_rate'
            ]
        );

        $this->assertEquals(
            -10.0,
            $result[
                'user_earning'
            ]
        );

        $this->assertEquals(
            0.0,
            $result[
                'super_admin_profit'
            ]
        );
    }
}
