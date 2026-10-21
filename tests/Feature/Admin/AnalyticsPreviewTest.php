<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* MIXXTUNE_ANALYTICS_V2 (W48) */
class AnalyticsPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function import(string $fin, string $file): int
    {
        return (int) DB::table('report_imports')->insertGetId([
            'public_id' => (string) Str::ulid(), 'original_filename' => $file, 'stored_path' => 'x',
            'status' => 'completed', 'financial_status' => $fin, 'total_rows' => 1, 'imported_rows' => 1,
            'reporting_month' => '2026-07-01', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function row(int $importId, ?int $labelId, float $revenue): void
    {
        DB::table('report_rows')->insert([
            'report_import_id' => $importId, 'row_hash' => hash('sha256', uniqid('w48-', true)),
            'track_title' => 'Song', 'track_artist' => 'Singer', 'isrc' => 'INAAA26'.random_int(10000, 99999),
            'platform' => 'Spotify', 'currency' => 'INR', 'country_code' => 'IN', 'sale_type' => 'Stream',
            'sale_month' => '2026-07', 'reporting_month' => '2026-07', 'streams' => 100, 'sale_units' => 100,
            'collected_revenue' => $revenue, 'label_rate' => 80, 'earnings' => $revenue * 0.8, 'label_id' => $labelId,
            'mapping_status' => $labelId ? 'mapped' : 'unmapped', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_staff_see_why_it_is_empty_and_can_preview_unpublished_reports(): void
    {
        $super = $this->user('super_admin');
        $this->row($this->import('review_pending', 'july.csv'), null, 500);

        $this->actingAs($super)->get('/v2/analytics')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Analytics/Index')
            ->where('preview', false)
            ->where('summary.rows', 0)
            ->has('unpublished', 1)
            ->where('unpublished.0.file', 'july.csv')
            ->where('unpublished.0.step', 'Waiting for review'));

        $this->actingAs($super)->get('/v2/analytics?preview=1')->assertInertia(fn (Assert $p) => $p
            ->where('preview', true)
            ->where('summary.rows', 1)
            ->has('filterOptions.months', 1));

        $csv = $this->actingAs($super)->get('/v2/analytics/export?preview=1');
        $csv->assertOk();
        $this->assertStringContainsString('Spotify', $csv->streamedContent());
    }

    public function test_owners_never_get_the_preview(): void
    {
        $owner = $this->user('label');
        $label = Label::factory()->create(['user_id' => $owner->id]);
        $this->row($this->import('review_pending', 'july.csv'), $label->id, 500);

        $this->actingAs($owner)->get('/v2/analytics?preview=1')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('preview', false)
            ->where('unpublished', [])
            ->has('filterOptions.months', 0));
    }
}
