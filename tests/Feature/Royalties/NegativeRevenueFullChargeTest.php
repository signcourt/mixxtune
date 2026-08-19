<?php

namespace Tests\Feature\Royalties;

use App\Models\User;
use App\Services\V2\OwnershipRoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NegativeRevenueFullChargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_negative_revenue_is_charged_at_100_percent(): void
    {
        $month = '2026-08';

        $labelUser = User::factory()->create([
            'role' => 'label',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $labelId = DB::table('labels')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'name' => 'Negative Rule Label',
            'slug' => 'negative-rule-label',
            'user_id' => $labelUser->id,
            'revenue_share_percentage' => 80,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $importId = DB::table('report_imports')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'original_filename' => 'negative-rule.csv',
            'stored_path' => 'tests/negative-rule.csv',
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


        foreach ([100.00, -10.00] as $index => $earning) {
            DB::table('report_rows')->insert([
                'report_import_id' => $importId,
                'reporting_month' => $month,
                'row_hash' => hash(
                    'sha256',
                    'negative-rule-'.$index.'-'.$earning
                ),
                'label_name' => 'Negative Rule Label',
                'track_title' => 'Negative Rule Track '.($index + 1),
                'platform' => 'Spotify',
                'currency' => 'INR',
                'sale_date' => '2026-08-15',
                'sale_month' => $month,
                'earnings' => $earning,
                'mapping_status' => 'mapped',
                'mapped_at' => now(),
                'label_id' => $labelId,
                'revenue_owner_type' => 'label',
                'revenue_owner_id' => $labelId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(OwnershipRoyaltyService::class)
            ->generateMonthlyStatements(
                $month,
                0,
                'INR'
            );

        $statement = DB::table('royalty_statements')
            ->where('statement_month', $month)
            ->where('label_id', $labelId)
            ->first();

        $this->assertNotNull($statement);

        $allocations = DB::table('royalty_allocations')
            ->where(
                'royalty_statement_id',
                $statement->id
            )
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $allocations);

        /*
         * Positive:
         * 100 × 80% = 80
         */
        $this->assertEquals(
            80.00,
            (float) $allocations[0]->gross_amount
        );

        $this->assertEquals(
            80.00,
            (float) $allocations[0]->share_percentage
        );

        /*
         * Negative:
         * -10 × 100% = -10
         */
        $this->assertEquals(
            -10.00,
            (float) $allocations[1]->gross_amount
        );

        $this->assertEquals(
            100.00,
            (float) $allocations[1]->share_percentage
        );

        /*
         * Final user entitlement:
         * +80 -10 = 70
         */
        $this->assertEquals(
            70.00,
            (float) $statement->gross_earnings
        );

        $this->assertEquals(
            70.00,
            (float) $statement->net_payable
        );
    }
}
