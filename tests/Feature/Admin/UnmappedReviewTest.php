<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* MIXXTUNE_UNMAPPED_V2 (W46) */
class UnmappedReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $pdo = DB::connection()->getPdo();
        if (method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('REGEXP_REPLACE', fn ($value, $pattern, $replacement) => preg_replace('/'.$pattern.'/', (string) $replacement, (string) $value), 3);
        }
    }

    private function import(string $financialStatus, string $file): int
    {
        return (int) DB::table('report_imports')->insertGetId([
            'public_id' => (string) Str::ulid(), 'original_filename' => $file, 'stored_path' => 'testing/'.$file,
            'status' => 'completed', 'financial_status' => $financialStatus, 'total_rows' => 1, 'imported_rows' => 1,
            'duplicate_rows' => 0, 'failed_rows' => 0, 'reporting_month' => '2026-07-01', 'started_at' => now(), 'completed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function row(int $importId, string $isrc, float $revenue, string $title = 'Song'): void
    {
        DB::table('report_rows')->insert([
            'report_import_id' => $importId, 'row_hash' => hash('sha256', uniqid('w46-', true)),
            'track_title' => $title, 'track_artist' => 'Singer', 'isrc' => $isrc,
            'platform' => 'Spotify', 'currency' => 'INR', 'country_code' => 'IN', 'sale_type' => 'Stream',
            'sale_month' => '2026-07', 'streams' => 10, 'sale_units' => 10, 'collected_revenue' => $revenue,
            'label_rate' => 0, 'earnings' => 0, 'mapping_status' => 'unmapped', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function super(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    public function test_default_list_shows_only_rows_that_can_still_be_mapped(): void
    {
        $open = $this->import('review_pending', 'july.csv');
        $locked = $this->import('published', 'june.csv');
        $this->row($open, 'INAAA2600001', 100);
        $this->row($open, 'INAAA2600002', 50);
        $this->row($locked, 'INBBB2600001', 70);

        $this->actingAs($this->super())->get('/super-admin/unmapped-revenue')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Admin/UnmappedRevenue/Index')
            ->where('scope', 'open')
            ->where('summary.rows', 2)
            ->where('scopeCounts.open_rows', 2)
            ->where('scopeCounts.locked_rows', 1)
            ->where('scopeCounts.locked_revenue', 70)
            ->has('rows.data', 2)
            ->has('imports', 2)
            ->where('imports.0.file', 'june.csv')
            ->where('imports.0.open', false)
            ->where('imports.1.rows', 2));
    }

    public function test_locked_scope_and_report_choice(): void
    {
        $super = $this->super();
        $open = $this->import('review_pending', 'july.csv');
        $locked = $this->import('approved_scheduled', 'june.csv');
        $this->row($open, 'INAAA2600001', 100);
        $this->row($locked, 'INBBB2600001', 70);

        $this->actingAs($super)->get('/super-admin/unmapped-revenue?scope=locked')->assertInertia(fn (Assert $p) => $p
            ->where('scope', 'locked')->where('summary.rows', 1)->where('rows.data.0.isrc', 'INBBB2600001'));

        /* choosing a locked report switches to the locked view by itself */
        $this->actingAs($super)->get('/super-admin/unmapped-revenue?report_import_id='.$locked)->assertInertia(fn (Assert $p) => $p
            ->where('scope', 'locked')->where('summary.rows', 1));

        $this->actingAs($super)->get('/super-admin/unmapped-revenue?report_import_id='.$open)->assertInertia(fn (Assert $p) => $p
            ->where('scope', 'open')->where('summary.rows', 1)->where('rows.data.0.isrc', 'INAAA2600001'));
    }

    public function test_search_treats_percent_and_underscore_as_plain_text(): void
    {
        $open = $this->import('review_pending', 'july.csv');
        $this->row($open, 'INAAA2600001', 10, '100% Love');
        $this->row($open, 'INAAA2600002', 10, '1000 Love');

        $this->actingAs($this->super())->get('/super-admin/unmapped-revenue?search=100%25')->assertInertia(fn (Assert $p) => $p
            ->where('summary.rows', 1));
    }
}
