<?php

namespace Tests\Feature\Royalties;

use App\Models\Core\Label;
use App\Services\V2\MasterRevenueVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MasterRevenueVisibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function label(
        string $name,
        ?int $parentId = null
    ): Label {
        return Label::query()->create([
            'public_id' =>
                (string) Str::ulid(),

            'parent_label_id' =>
                $parentId,

            'label_type' =>
                $parentId
                    ? 'sub_label'
                    : 'master',

            'name' =>
                $name,

            'slug' =>
                Str::slug($name)
                .'-'
                .Str::lower(
                    Str::random(8)
                ),

            'currency' =>
                'INR',

            'royalty_share_percentage' =>
                100,

            'parent_commission_percentage' =>
                0,

            'status' =>
                'active',

            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function statement(
        string $type,
        int $id,
        float $gross,
        float $net
    ): void {
        DB::table(
            'royalty_statements'
        )->insert([
            'public_id' =>
                (string) Str::ulid(),

            'artist_id' =>
                $type === 'artist'
                    ? $id
                    : null,

            'label_id' =>
                $type === 'label'
                    ? $id
                    : null,

            'statement_month' =>
                '2026-07',

            'currency' =>
                'INR',

            'gross_earnings' =>
                $gross,

            'commission_amount' =>
                0,

            'tax_amount' =>
                0,

            'other_deductions' =>
                0,

            'net_payable' =>
                $net,

            'status' =>
                'approved',

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }

    public function test_master_summary_contains_direct_label_and_artist_without_double_counting(): void
    {
        $master = $this->label(
            'Sanatan Records'
        );

        $child = $this->label(
            'Mixx Tune',
            $master->id
        );

        $artistId = DB::table(
            'artists'
        )->insertGetId([
            'public_id' =>
                (string) Str::ulid(),

            'label_id' =>
                $master->id,

            'stage_name' =>
                'Direct Artist',

            'slug' =>
                'direct-artist-'
                .Str::lower(
                    Str::random(8)
                ),

            'email' =>
                'artist-'
                .Str::lower(
                    Str::random(8)
                )
                .'@example.test',

            'country' =>
                'IN',

            'timezone' =>
                'Asia/Kolkata',

            'currency' =>
                'INR',

            'account_status' =>
                'active',

            'kyc_status' =>
                'pending',

            'can_receive_splits' =>
                true,

            'can_create_releases' =>
                true,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        DB::table(
            'label_revenue_shares'
        )->insert([
            [
                'master_label_id' =>
                    $master->id,

                'beneficiary_type' =>
                    'label',

                'beneficiary_id' =>
                    $child->id,

                'revenue_share_percent' =>
                    80,

                'show_revenue_share' =>
                    true,

                'is_active' =>
                    true,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ],
            [
                'master_label_id' =>
                    $master->id,

                'beneficiary_type' =>
                    'artist',

                'beneficiary_id' =>
                    $artistId,

                'revenue_share_percent' =>
                    70,

                'show_revenue_share' =>
                    false,

                'is_active' =>
                    true,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ],
        ]);

        /*
         * Child source:
         * 100 managed
         * 80 child
         * 20 master
         *
         * Artist source:
         * 100 managed
         * 70 artist
         * 30 master
         *
         * Master statement therefore = 50.
         */
        $this->statement(
            'label',
            $child->id,
            80,
            80
        );

        $this->statement(
            'artist',
            $artistId,
            70,
            70
        );

        $this->statement(
            'label',
            $master->id,
            50,
            50
        );

        $summary = app(
            MasterRevenueVisibilityService::class
        )->summary(
            $master,
            '2026-07'
        );

        $this->assertTrue(
            $summary['is_master']
        );

        $this->assertEqualsWithDelta(
            200,
            $summary['managed_revenue'],
            0.00000001
        );

        $this->assertEqualsWithDelta(
            150,
            $summary['allocated_revenue'],
            0.00000001
        );

        $this->assertEqualsWithDelta(
            50,
            $summary['retained_revenue'],
            0.00000001
        );

        $this->assertEqualsWithDelta(
            50,
            $summary['payable_revenue'],
            0.00000001
        );

        $this->assertCount(
            2,
            $summary['children']
        );

        $labelRow = collect(
            $summary['children']
        )->firstWhere(
            'type',
            'label'
        );

        $artistRow = collect(
            $summary['children']
        )->firstWhere(
            'type',
            'artist'
        );

        $this->assertNotNull(
            $labelRow
        );

        $this->assertNotNull(
            $artistRow
        );

        $this->assertEqualsWithDelta(
            80,
            $labelRow[
                'allocated_revenue'
            ],
            0.00000001
        );

        $this->assertEqualsWithDelta(
            20,
            $labelRow[
                'master_retained'
            ],
            0.00000001
        );

        $this->assertEqualsWithDelta(
            70,
            $artistRow[
                'allocated_revenue'
            ],
            0.00000001
        );

        $this->assertEqualsWithDelta(
            30,
            $artistRow[
                'master_retained'
            ],
            0.00000001
        );

        $this->assertFalse(
            $artistRow[
                'share_visible'
            ]
        );
    }

    public function test_artist_under_child_label_is_not_a_direct_master_beneficiary(): void
    {
        $master = $this->label(
            'Master'
        );

        $child = $this->label(
            'Child',
            $master->id
        );

        DB::table(
            'artists'
        )->insert([
            'public_id' =>
                (string) Str::ulid(),

            'label_id' =>
                $child->id,

            'stage_name' =>
                'Nested Artist',

            'slug' =>
                'nested-artist-'
                .Str::lower(
                    Str::random(8)
                ),

            'email' =>
                'nested-'
                .Str::lower(
                    Str::random(8)
                )
                .'@example.test',

            'country' =>
                'IN',

            'timezone' =>
                'Asia/Kolkata',

            'currency' =>
                'INR',

            'account_status' =>
                'active',

            'kyc_status' =>
                'pending',

            'can_receive_splits' =>
                true,

            'can_create_releases' =>
                true,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        $summary = app(
            MasterRevenueVisibilityService::class
        )->summary(
            $master,
            '2026-07'
        );

        $artistRows = collect(
            $summary['children']
        )->where(
            'type',
            'artist'
        );

        $this->assertCount(
            0,
            $artistRows
        );
    }
}
