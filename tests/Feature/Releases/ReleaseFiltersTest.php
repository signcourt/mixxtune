<?php

namespace Tests\Feature\Releases;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* W76 MIXXTUNE_RELEASE_FILTERS_V3 */
class ReleaseFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_every_new_filter_works_on_the_server_and_month_is_ignored(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);

        $label = Label::factory()->create();
        $mk = fn (array $v) => Release::factory()->create(array_merge([
            'label_id' => $label->id,
            'artist_id' => Artist::factory()->create(['label_id' => $label->id, 'created_by' => $super->id, 'account_status' => 'active'])->id,
            'created_by' => $super->id,
        ], $v));
        $a = $mk(['title' => 'Filter A', 'primary_artist_name' => 'Asha', 'language' => 'Hindi', 'primary_genre' => 'Devotional', 'upc' => '8900000000011', 'artwork_path' => 'covers/a.jpg', 'digital_release_date' => '2026-09-01']);
        $b = $mk(['title' => 'Filter B', 'primary_artist_name' => 'Bhanu', 'language' => 'Punjabi', 'primary_genre' => 'Pop', 'upc' => null, 'artwork_path' => null, 'digital_release_date' => '2026-01-01']);
        $storeId = DB::table('distribution_stores')->insertGetId(['name' => 'Spotify', 'slug' => 'spotify-w76', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('release_store_deliveries')->insert(['public_id' => (string) \Illuminate\Support\Str::ulid(), 'release_id' => $a->id, 'distribution_store_id' => $storeId, 'status' => 'live', 'created_at' => now(), 'updated_at' => now()]);

        $titles = fn (string $query) => collect(
            $this->actingAs($super)->get('/releases'.$query)->assertOk()->viewData('page')['props']['releases']['data']
        )->pluck('title')->sort()->values()->all();

        $this->assertSame(['Filter A', 'Filter B'], $titles('?month=2026-09'));
        $this->assertSame(['Filter B'], $titles('?artist=Bhanu'));
        $this->assertSame(['Filter A'], $titles('?language=Hindi'));
        $this->assertSame(['Filter B'], $titles('?genre=Pop'));
        /* W77: UPC / artwork / stores filters were removed - old links show everything */
        $this->assertSame(['Filter A', 'Filter B'], $titles('?upc=missing&stores=live&artwork=has'));

        $this->actingAs($super)->get('/releases?sort=release_newest')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('releases.data.0.title', 'Filter A')
            ->where('filterOptions.artists', ['Asha', 'Bhanu'])
            ->where('filterOptions.languages', ['Hindi', 'Punjabi'])
            ->missing('filters.month')
            ->missing('filters.upc'));
    }
}
