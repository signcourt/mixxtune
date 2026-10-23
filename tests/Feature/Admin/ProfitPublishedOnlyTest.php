<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* W92 MIXXTUNE_PROFIT_PUBLISHED_ONLY: profit counts published reports; reports in review are shown apart */
class ProfitPublishedOnlyTest extends TestCase
{
    use RefreshDatabase;

    private function row(int $labelId, int $importId, float $collected, float $earnings, string $key): void
    {
        DB::table('report_rows')->insert([
            'report_import_id' => $importId,
            'reporting_month' => '2026-08',
            'row_hash' => hash('sha256', $key),
            'label_name' => 'Profit Stage Label',
            'track_title' => 'Track '.$key,
            'platform' => 'Spotify',
            'currency' => 'INR',
            'sale_date' => '2026-08-15',
            'sale_month' => '2026-08',
            'collected_revenue' => $collected,
            'label_rate' => 0.80,
            'earnings' => $earnings,
            'mapping_status' => 'mapped',
            'mapped_at' => now(),
            'label_id' => $labelId,
            'revenue_owner_type' => 'label',
            'revenue_owner_id' => $labelId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function import(string $status): int
    {
        return DB::table('report_imports')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'original_filename' => $status.'.csv',
            'stored_path' => 'tests/'.$status.'.csv',
            'status' => 'completed',
            'financial_status' => $status,
            'total_rows' => 1, 'imported_rows' => 1, 'duplicate_rows' => 0, 'failed_rows' => 0,
            'started_at' => now(), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_review_report_is_not_counted_but_shown_apart(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $owner = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $labelId = DB::table('labels')->insertGetId([
            'public_id' => (string) Str::ulid(), 'name' => 'Profit Stage Label', 'slug' => 'profit-stage-label',
            'user_id' => $owner->id, 'revenue_share_percentage' => 80, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->row($labelId, $this->import('published'), 100, 80, 'published');   /* profit 20 */
        $this->row($labelId, $this->import('review_pending'), 50, 40, 'review');  /* profit 10 */

        $close = fn (float $expected) => fn ($v) => abs((float) $v - $expected) < 0.0001;

        $this->actingAs($super)->get('/super-admin/dashboard')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('stats.profit_earnings', $close(20.0))
            ->where('stats.profit_in_review', $close(10.0)));

        $this->actingAs($super)->get('/admin/profit')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('filters.stage', 'published')
            ->where('summary.super_admin_profit', $close(20.0))
            ->where('inReview.super_admin_profit', $close(10.0)));

        $this->actingAs($super)->get('/admin/profit?stage=all')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('summary.super_admin_profit', $close(30.0))
            ->where('inReview', null));

        $this->actingAs($super)->get('/admin/profit?stage=review')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('summary.super_admin_profit', $close(10.0)));
    }
}
