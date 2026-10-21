<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* MIXXTUNE_REPORTS_V2 (W49) */
class ReportsPageTest extends TestCase
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

    private function import(string $fin, string $file, ?string $publishedAt = null): int
    {
        return (int) DB::table('report_imports')->insertGetId([
            'public_id' => (string) Str::ulid(), 'original_filename' => $file, 'stored_path' => 'x',
            'status' => 'completed', 'financial_status' => $fin, 'total_rows' => 1, 'imported_rows' => 1,
            'reporting_month' => '2026-06-01', 'published_at' => $publishedAt, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function row(int $importId, string $month): void
    {
        DB::table('report_rows')->insert([
            'report_import_id' => $importId, 'row_hash' => hash('sha256', uniqid('w49-', true)),
            'track_title' => 'Song', 'track_artist' => 'Singer', 'isrc' => 'INAAA26'.random_int(10000, 99999),
            'platform' => 'Spotify', 'currency' => 'INR', 'country_code' => 'IN', 'sale_type' => 'Stream',
            'sale_month' => $month, 'reporting_month' => $month, 'streams' => 10, 'sale_units' => 10,
            'collected_revenue' => 100, 'label_rate' => 80, 'earnings' => 80,
            'mapping_status' => 'unmapped', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_page_sends_only_what_it_uses_with_real_published_date(): void
    {
        $super = $this->user('super_admin');
        $this->row($this->import('published', 'june.csv', '2026-07-05 06:30:00'), '2026-06');
        $this->row($this->import('review_pending', 'july.csv'), '2026-07');

        $this->actingAs($super)->get('/v2/admin/reports')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Reports/Index')
            ->has('automaticReports', 1)
            ->where('automaticReports.0.month', '2026-06')
            ->where('automaticReports.0.generatedAt', '05 Jul 2026')
            ->has('months', 1)
            ->has('unpublished', 1)
            ->where('unpublished.0.file', 'july.csv')
            ->missing('rows')
            ->missing('topTracks')
            ->missing('summary')
            ->missing('monthlyTrend'));
    }

    public function test_owner_gets_no_unpublished_list(): void
    {
        $this->row($this->import('review_pending', 'july.csv'), '2026-07');

        $this->actingAs($this->user('artist'))->get('/v2/reports')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('unpublished', [])
            ->has('automaticReports', 0));
    }
}
