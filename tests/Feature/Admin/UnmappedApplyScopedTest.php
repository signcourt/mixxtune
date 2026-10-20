<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * MIXXTUNE_UNMAPPED_APPLY_SCOPED_V1
 * All data is generated inside the SQLite :memory: test database.
 */
class UnmappedApplyScopedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /* MySQL has REGEXP_REPLACE; give SQLite the same function for this test */
        $pdo = DB::connection()->getPdo();
        if (method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('REGEXP_REPLACE', fn ($value, $pattern, $replacement) => preg_replace('/'.$pattern.'/', (string) $replacement, (string) $value), 3);
        }
    }

    private function import(string $financialStatus): int
    {
        return (int) DB::table('report_imports')->insertGetId([
            'public_id' => (string) Str::ulid(), 'original_filename' => 'gen.csv', 'stored_path' => 'testing/gen.csv',
            'status' => 'completed', 'financial_status' => $financialStatus, 'total_rows' => 1, 'imported_rows' => 1,
            'duplicate_rows' => 0, 'failed_rows' => 0, 'started_at' => now(), 'completed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function row(int $importId): int
    {
        return (int) DB::table('report_rows')->insertGetId([
            'report_import_id' => $importId, 'row_hash' => hash('sha256', uniqid('gen-', true)),
            'track_title' => 'Gen Song', 'track_artist' => 'Gen Singer', 'isrc' => 'IN-GEN-26-00001',
            'platform' => 'Spotify', 'currency' => 'INR', 'country_code' => 'IN', 'sale_type' => 'Stream',
            'sale_month' => '2026-07', 'streams' => 10, 'sale_units' => 10, 'collected_revenue' => 10,
            'label_rate' => 0, 'earnings' => 0, 'mapping_status' => 'unmapped', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_saved_rules_apply_only_to_the_selected_report_and_never_to_approved_ones(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create(['created_by' => $super->id, 'revenue_share_percentage' => 80]);

        $first = $this->import('review_pending');
        $second = $this->import('review_pending');
        $approved = $this->import('approved_scheduled');
        $rowFirst = $this->row($first);
        $rowSecond = $this->row($second);
        $rowApproved = $this->row($approved);

        DB::table('revenue_mapping_rules')->insert([
            'identifier_type' => 'isrc', 'identifier_value' => 'INGEN2600001', 'owner_type' => 'label',
            'owner_id' => $label->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($super)->post('/super-admin/unmapped-revenue/apply-all', ['report_import_id' => $first])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('mapped', DB::table('report_rows')->where('id', $rowFirst)->value('mapping_status'));
        $this->assertEquals(8.0, (float) DB::table('report_rows')->where('id', $rowFirst)->value('earnings'));
        $this->assertSame('unmapped', DB::table('report_rows')->where('id', $rowSecond)->value('mapping_status'));

        $this->actingAs($super)->post('/super-admin/unmapped-revenue/apply-all', ['report_import_id' => $approved])
            ->assertSessionHasErrors('apply');

        $this->actingAs($super)->post('/super-admin/unmapped-revenue/apply-all', [])
            ->assertSessionHasNoErrors();

        $this->assertSame('mapped', DB::table('report_rows')->where('id', $rowSecond)->value('mapping_status'));
        $this->assertSame('unmapped', DB::table('report_rows')->where('id', $rowApproved)->value('mapping_status'));
    }
}
