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

        /*
         * The group contains:
         *
         * positive row => configured 80%
         * negative row => effective 100%
         *
         * Therefore the account breakdown must
         * be explicitly marked Mixed instead of
         * presenting a misleading single rate.
         */
        $this->assertNull(
            $breakdown[0][
                'assigned_rate'
            ]
        );

        $this->assertTrue(
            $breakdown[0][
                'rate_is_mixed'
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

    public function test_active_label_revenue_share_is_canonical_rate(): void
    {
        $masterUserId =
            DB::table('users')
                ->insertGetId([
                    'name' =>
                        'Master Label',
                    'email' =>
                        'master-profit@example.com',
                    'password' =>
                        bcrypt('password'),
                    'role' =>
                        'label',
                    'account_status' =>
                        'active',
                    'email_verified_at' =>
                        now(),
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

        $masterLabelId =
            DB::table('labels')
                ->insertGetId([
                    'public_id' =>
                        (string) Str::ulid(),
                    'name' =>
                        'Master Profit Label',
                    'slug' =>
                        'master-profit-label',
                    'user_id' =>
                        $masterUserId,
                    'revenue_share_percentage' =>
                        100,
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

        $artistUserId =
            DB::table('users')
                ->insertGetId([
                    'name' =>
                        'Profit Artist',
                    'email' =>
                        'profit-artist@example.com',
                    'password' =>
                        bcrypt('password'),
                    'role' =>
                        'artist',
                    'account_status' =>
                        'active',
                    'email_verified_at' =>
                        now(),
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

        $artistId =
            DB::table('artists')
                ->insertGetId([
                    'public_id' =>
                        (string) Str::ulid(),
                    'user_id' =>
                        $artistUserId,
                    'label_id' =>
                        $masterLabelId,
                    'stage_name' =>
                        'Profit Artist',
                    'slug' =>
                        'profit-artist',
                    'revenue_share_percentage' =>
                        100,
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

        DB::table(
            'label_revenue_shares'
        )->insert([
            'master_label_id' =>
                $masterLabelId,
            'beneficiary_type' =>
                'artist',
            'beneficiary_id' =>
                $artistId,
            'revenue_share_percent' =>
                80,
            'show_revenue_share' =>
                true,
            'is_active' =>
                true,
            'effective_from' =>
                '2026-08-01',
            'created_at' =>
                now(),
            'updated_at' =>
                now(),
        ]);

        $service =
            app(
                SuperAdminProfitService::class
            );

        $row = (object) [
            'earnings' => 100,
            'sale_month' =>
                '2026-08',
            'sale_date' =>
                '2026-08-15',
            'label_id' =>
                $masterLabelId,
            'artist_id' =>
                $artistId,
            'revenue_owner_type' =>
                'label',
            'revenue_owner_id' =>
                $masterLabelId,
        ];

        $result =
            $service->calculateRow(
                $row
            );

        $this->assertEquals(
            80.0,
            $result[
                'assigned_rate'
            ]
        );

        $this->assertEquals(
            80.0,
            $result[
                'user_earning'
            ]
        );

        $this->assertEquals(
            20.0,
            $result[
                'super_admin_profit'
            ]
        );
    }



    public function test_month_filter_falls_back_to_sale_month_when_reporting_month_is_missing(): void
    {
        $userId =
            DB::table('users')
                ->insertGetId([
                    'name' =>
                        'Legacy Month Label',
                    'email' =>
                        'legacy-month-profit@example.com',
                    'password' =>
                        bcrypt('password'),
                    'role' =>
                        'label',
                    'account_status' =>
                        'active',
                    'email_verified_at' =>
                        now(),
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

        $labelId =
            DB::table('labels')
                ->insertGetId([
                    'public_id' =>
                        (string) Str::ulid(),
                    'name' =>
                        'Legacy Month Label',
                    'slug' =>
                        'legacy-month-label',
                    'user_id' =>
                        $userId,
                    'revenue_share_percentage' =>
                        80,
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

        $importId =
            DB::table('report_imports')
                ->insertGetId([
                    'public_id' =>
                        (string) Str::ulid(),
                    'original_filename' =>
                        'legacy-month-profit.csv',
                    'stored_path' =>
                        'tests/legacy-month-profit.csv',
                    'status' =>
                        'completed',
                    'total_rows' =>
                        1,
                    'imported_rows' =>
                        1,
                    'duplicate_rows' =>
                        0,
                    'failed_rows' =>
                        0,
                    'started_at' =>
                        now(),
                    'completed_at' =>
                        now(),
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

        DB::table('report_rows')
            ->insert([
                'report_import_id' =>
                    $importId,

                'row_hash' =>
                    hash(
                        'sha256',
                        'legacy-month-profit-row'
                    ),

                'label_name' =>
                    'Legacy Month Label',

                'track_title' =>
                    'Legacy Month Track',

                'platform' =>
                    'Spotify',

                'currency' =>
                    'INR',

                'sale_date' =>
                    '2026-08-15',

                'sale_month' =>
                    '2026-08',

                'reporting_month' =>
                    null,

                'earnings' =>
                    100,

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
            100.0,
            $summary[
                'collected_revenue'
            ]
        );

        $this->assertEquals(
            80.0,
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

        $this->assertTrue(
            $service
                ->months()
                ->contains(
                    '2026-08'
                )
        );
    }


    public function test_breakdown_marks_group_as_mixed_when_effective_rates_differ(): void
    {
        $userId =
            DB::table('users')
                ->insertGetId([
                    'name' =>
                        'Mixed Rate Label',
                    'email' =>
                        'mixed-rate-profit@example.com',
                    'password' =>
                        bcrypt('password'),
                    'role' =>
                        'label',
                    'account_status' =>
                        'active',
                    'email_verified_at' =>
                        now(),
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

        $labelId =
            DB::table('labels')
                ->insertGetId([
                    'public_id' =>
                        (string) Str::ulid(),
                    'name' =>
                        'Mixed Rate Label',
                    'slug' =>
                        'mixed-rate-profit-label',
                    'user_id' =>
                        $userId,
                    'revenue_share_percentage' =>
                        80,
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

        $importId =
            DB::table('report_imports')
                ->insertGetId([
                    'public_id' =>
                        (string) Str::ulid(),
                    'original_filename' =>
                        'mixed-rate-profit.csv',
                    'stored_path' =>
                        'tests/mixed-rate-profit.csv',
                    'status' =>
                        'completed',
                    'total_rows' =>
                        2,
                    'imported_rows' =>
                        2,
                    'duplicate_rows' =>
                        0,
                    'failed_rows' =>
                        0,
                    'started_at' =>
                        now(),
                    'completed_at' =>
                        now(),
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

        DB::table('report_rows')
            ->insert([
                [
                    'report_import_id' =>
                        $importId,

                    'row_hash' =>
                        hash(
                            'sha256',
                            'mixed-rate-positive'
                        ),

                    'label_name' =>
                        'Mixed Rate Label',

                    'track_title' =>
                        'Mixed Positive',

                    'platform' =>
                        'Spotify',

                    'currency' =>
                        'INR',

                    'reporting_month' =>
                        '2026-08',

                    'sale_month' =>
                        '2026-08',

                    'sale_date' =>
                        '2026-08-15',

                    'earnings' =>
                        100,

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
                ],
                [
                    'report_import_id' =>
                        $importId,

                    'row_hash' =>
                        hash(
                            'sha256',
                            'mixed-rate-negative'
                        ),

                    'label_name' =>
                        'Mixed Rate Label',

                    'track_title' =>
                        'Mixed Negative',

                    'platform' =>
                        'Spotify',

                    'currency' =>
                        'INR',

                    'reporting_month' =>
                        '2026-08',

                    'sale_month' =>
                        '2026-08',

                    'sale_date' =>
                        '2026-08-16',

                    'earnings' =>
                        -10,

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
                ],
            ]);

        $service =
            app(
                SuperAdminProfitService::class
            );

        $row =
            $service->breakdown(
                $service->baseQuery(
                    '2026-08',
                    null,
                    'label',
                    $labelId
                )
            )->first();

        $this->assertNotNull(
            $row
        );

        $this->assertNull(
            $row[
                'assigned_rate'
            ]
        );

        $this->assertTrue(
            $row[
                'rate_is_mixed'
            ]
        );

        $this->assertEquals(
            90.0,
            $row[
                'collected_revenue'
            ]
        );

        $this->assertEquals(
            70.0,
            $row[
                'user_earning'
            ]
        );

        $this->assertEquals(
            20.0,
            $row[
                'super_admin_profit'
            ]
        );
    }


    public function test_owner_options_and_effective_month_contract(): void
    {
        $userId =
            DB::table('users')
                ->insertGetId([
                    'name' =>
                        'Owner Selector Label',
                    'email' =>
                        'owner-selector@example.com',
                    'password' =>
                        bcrypt('password'),
                    'role' =>
                        'label',
                    'account_status' =>
                        'active',
                    'email_verified_at' =>
                        now(),
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

        $labelId =
            DB::table('labels')
                ->insertGetId([
                    'public_id' =>
                        (string) Str::ulid(),
                    'name' =>
                        'Owner Selector Label',
                    'slug' =>
                        'owner-selector-label',
                    'user_id' =>
                        $userId,
                    'revenue_share_percentage' =>
                        80,
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

        $importId =
            DB::table('report_imports')
                ->insertGetId([
                    'public_id' =>
                        (string) Str::ulid(),
                    'original_filename' =>
                        'owner-selector.csv',
                    'stored_path' =>
                        'tests/owner-selector.csv',
                    'status' =>
                        'completed',
                    'total_rows' =>
                        1,
                    'imported_rows' =>
                        1,
                    'duplicate_rows' =>
                        0,
                    'failed_rows' =>
                        0,
                    'started_at' =>
                        now(),
                    'completed_at' =>
                        now(),
                    'created_at' =>
                        now(),
                    'updated_at' =>
                        now(),
                ]);

        DB::table('report_rows')
            ->insert([
                'report_import_id' =>
                    $importId,
                'row_hash' =>
                    hash(
                        'sha256',
                        'owner-selector-row'
                    ),
                'label_name' =>
                    'Owner Selector Label',
                'track_title' =>
                    'Owner Selector Track',
                'platform' =>
                    'Spotify',
                'currency' =>
                    'INR',
                'sale_date' =>
                    '2026-08-15',
                'sale_month' =>
                    '2026-08',
                'reporting_month' =>
                    null,
                'earnings' =>
                    100,
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

        $service =
            app(
                SuperAdminProfitService::class
            );

        $owner =
            $service
                ->owners()
                ->first(
                    fn (array $item) =>
                        $item['type']
                            === 'label'
                        && (int) $item['id']
                            === $labelId
                );

        $this->assertNotNull(
            $owner
        );

        $this->assertEquals(
            'Owner Selector Label',
            $owner['name']
        );

        $this->assertEquals(
            '2026-08',
            $service->effectiveMonth(
                (object) [
                    'reporting_month' =>
                        null,
                    'sale_month' =>
                        '2026-08',
                ]
            )
        );

        $this->assertEquals(
            '2026-06',
            $service->effectiveMonth(
                (object) [
                    'reporting_month' =>
                        '2026-06',
                    'sale_month' =>
                        '2026-08',
                ]
            )
        );

        $summary =
            $service->summary(
                $service->baseQuery(
                    null,
                    null,
                    'label',
                    $labelId
                )
            );

        $this->assertEquals(
            100.0,
            $summary[
                'collected_revenue'
            ]
        );

        $this->assertEquals(
            80.0,
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
