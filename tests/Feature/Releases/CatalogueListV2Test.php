<?php

namespace Tests\Feature\Releases;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/*
 * MIXXTUNE_CATALOGUE_LIST_V2
 * All data is generated inside the SQLite :memory: test database.
 */
class CatalogueListV2Test extends TestCase
{
    use RefreshDatabase;

    private function item(User $super, Label $label, array $values): int
    {
        $artist = Artist::factory()->create(['label_id' => $label->id, 'created_by' => $super->id, 'account_status' => 'active']);
        $release = Release::factory()->create(['artist_id' => $artist->id, 'label_id' => $label->id, 'status' => 'live', 'created_by' => $super->id]);

        return (int) DB::table('catalogue_items')->insertGetId(array_merge([
            'public_id' => (string) Str::ulid(), 'release_id' => $release->id, 'artist_id' => $artist->id, 'label_id' => $label->id,
            'label_name' => $label->name, 'primary_artist_name' => 'Gen Artist', 'release_status' => 'live',
            'track_count' => 1, 'is_visible' => true, 'created_at' => now(), 'updated_at' => now(),
        ], $values));
    }

    public function test_list_v2_props_and_filters(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $a = Label::factory()->create(['created_by' => $super->id, 'name' => 'Alpha Gen Records']);
        $b = Label::factory()->create(['created_by' => $super->id, 'name' => 'Beta Gen Music']);

        $this->item($super, $a, ['title' => 'Gen One', 'release_type' => 'single', 'digital_release_date' => '2026-07-10',
            'artwork_path' => 'artwork/gen-one.jpg', 'delivery_summary' => json_encode(['total' => 3, 'live' => 2])]);
        $this->item($super, $b, ['title' => 'Gen Two', 'release_type' => 'album', 'digital_release_date' => '2026-08-02', 'release_status' => 'approved']);

        $this->actingAs($super)->get('/v2/catalogue')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('V2/Catalogue/Index')
                ->has('catalogue.data', 2)
                ->where('labelOptions', ['Alpha Gen Records', 'Beta Gen Music']));

        $titles = fn (string $query) => collect(
            $this->actingAs($super)->get('/v2/catalogue?'.$query)->viewData('page')['props']['catalogue']['data']
        )->pluck('title')->sort()->values()->all();

        $this->assertSame(['Gen Two'], $titles('product_type=album'));
        $this->assertSame(['Gen One'], $titles('date_from=2026-07-01&date_to=2026-07-31'));
        $this->assertSame(['Gen Two'], $titles('label=Beta+Gen+Music'));
        $this->assertSame(['Gen Two'], $titles('status=approved'));
        $this->assertSame(['Gen One'], $titles('search=Alpha'));
    }
}
