<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* MIXXTUNE_ANALYTICS_V4 (W51): names that differ only in spaces / case are one group */
class AnalyticsGroupingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function row(int $importId, array $x): void
    {
        DB::table('report_rows')->insert(array_merge([
            'report_import_id' => $importId, 'row_hash' => hash('sha256', uniqid('w51-', true)),
            'track_title' => 'Song', 'track_artist' => 'Singer', 'album_title' => 'Album', 'album_artist' => 'Singer',
            'isrc' => 'INAAA2600001', 'upc' => '123', 'label_name' => 'VR Bros HD',
            'platform' => 'Spotify', 'currency' => 'INR', 'country_code' => 'IN', 'sale_type' => 'Stream',
            'sale_month' => '2026-07', 'reporting_month' => '2026-07', 'streams' => 10, 'sale_units' => 10,
            'collected_revenue' => 100, 'label_rate' => 80, 'earnings' => 80,
            'mapping_status' => 'unmapped', 'created_at' => now(), 'updated_at' => now(),
        ], $x));
    }

    public function test_label_artist_store_variants_are_counted_together(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $import = (int) DB::table('report_imports')->insertGetId([
            'public_id' => (string) Str::ulid(), 'original_filename' => 'july.csv', 'stored_path' => 'x',
            'status' => 'completed', 'financial_status' => 'published', 'total_rows' => 3, 'imported_rows' => 3,
            'reporting_month' => '2026-07', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->row($import, []);
        $this->row($import, ['label_name' => 'VR Bros HD ', 'track_artist' => 'Singer ', 'platform' => 'spotify', 'album_title' => 'album']);
        $this->row($import, ['label_name' => 'vr bros hd', 'track_artist' => 'SINGER', 'platform' => 'Spotify ']);

        $this->actingAs($super)->get('/v2/analytics')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('topLabels', 1)
            ->where('topLabels.0.collected_revenue', 300)
            ->has('topArtists', 1)
            ->has('topPlatforms', 1)
            ->where('topPlatforms.0.collected_revenue', 300)
            ->has('topAlbums', 1)
            ->has('topTracks', 1)
            ->where('topTracks.0.streams', 30));
    }

    /* W52: one store per slice, growth only between REPORTING months */
    public function test_store_spellings_merge_and_growth_uses_reporting_months(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $import = (int) DB::table('report_imports')->insertGetId([
            'public_id' => (string) Str::ulid(), 'original_filename' => 'july.csv', 'stored_path' => 'x',
            'status' => 'completed', 'financial_status' => 'published', 'total_rows' => 3, 'imported_rows' => 3,
            'reporting_month' => '2026-07', 'created_at' => now(), 'updated_at' => now(),
        ]);

        /* one July report holding mostly June sales and a little July sales */
        $this->row($import, ['platform' => 'Jio Saavan', 'sale_month' => '2026-06', 'streams' => 1000]);
        $this->row($import, ['platform' => 'JioSaavn', 'sale_month' => '2026-06', 'streams' => 1000]);
        $this->row($import, ['platform' => 'Jio Saavn', 'sale_month' => '2026-07', 'streams' => 10]);
        $this->row($import, ['platform' => 'Spotify', 'sale_month' => '2026-07', 'streams' => 10]);

        $this->actingAs($super)->get('/v2/analytics')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('topPlatforms', 2)
            ->where('topPlatforms.0.name', 'JioSaavn')
            ->where('topPlatforms.0.collected_revenue', 300)
            ->where('summary.active_platforms', 2)
            ->where('growth.has_previous', false)
            ->has('importedRevenueAnalytics.monthly', 1)
            ->where('importedRevenueAnalytics.monthly.0.month', '2026-07'));
    }
}
