<?php

namespace Tests\Feature\Royalties;

use App\Models\Core\Label;
use App\Models\Finance\LabelRevenueShare;
use App\Services\V2\OwnershipRoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MasterChildStatementIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function label(
        string $name,
        ?int $parentId = null
    ): Label {
        return Label::query()->create([
            'public_id' => (string) Str::ulid(),
            'parent_label_id' => $parentId,
            'label_type' => $parentId ? 'sub_label' : 'master',
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(8)),
            'currency' => 'INR',
            'royalty_share_percentage' => 100,
            'parent_commission_percentage' => 0,
            'status' => 'active',
        ]);
    }

    public function test_master_child_80_20_split_is_applied_to_monthly_statements(): void
    {
        $master = $this->label('Sanatan Records');
        $child = $this->label('Mixx Tune', $master->id);

        LabelRevenueShare::query()->create([
            'master_label_id' => $master->id,
            'beneficiary_type' => 'label',
            'beneficiary_id' => $child->id,
            'revenue_share_percent' => 80,
            'show_revenue_share' => true,
            'is_active' => true,
        ]);

        $importId = DB::table('report_imports')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'original_filename' => 'master-child-test.csv',
            'stored_path' => 'testing/master-child-test.csv',
            'status' => 'completed',
            'total_rows' => 1,
            'imported_rows' => 1,
            'duplicate_rows' => 0,
            'failed_rows' => 0,
            'started_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('report_rows')->insert([
            'report_import_id' => $importId,
            'row_hash' => hash('sha256', 'master-child-statement-test'),
            'reporting_month' => '2026-07',
            'sale_date' => '2026-07-15',
            'sale_month' => '2026-07',
            'track_title' => 'Integration Test Track',
            'track_artist' => 'Integration Test Artist',
            'earnings' => 100,
            'mapping_status' => 'mapped',
            'revenue_owner_type' => 'label',
            'revenue_owner_id' => $child->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(OwnershipRoyaltyService::class)
            ->generateMonthlyStatements('2026-07', 0, 'INR');

        $childStatement = DB::table('royalty_statements')
            ->where('label_id', $child->id)
            ->where('statement_month', '2026-07')
            ->first();

        $masterStatement = DB::table('royalty_statements')
            ->where('label_id', $master->id)
            ->where('statement_month', '2026-07')
            ->first();

        $this->assertNotNull($childStatement);
        $this->assertNotNull($masterStatement);

        $this->assertEqualsWithDelta(
            80,
            (float) $childStatement->net_payable,
            0.00000001
        );

        $this->assertEqualsWithDelta(
            20,
            (float) $masterStatement->net_payable,
            0.00000001
        );

        $this->assertEqualsWithDelta(
            100,
            (float) $childStatement->net_payable
                + (float) $masterStatement->net_payable,
            0.00000001
        );

        $this->assertEqualsWithDelta(
            100,
            (float) DB::table('report_rows')->value('earnings'),
            0.00000001
        );
    }
}
