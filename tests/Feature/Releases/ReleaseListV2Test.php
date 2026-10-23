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
 * MIXXTUNE_RELEASES_LIST_V2
 * All data is generated inside the SQLite :memory: test database.
 */
class ReleaseListV2Test extends TestCase
{
    use RefreshDatabase;

    private function release(User $super, Label $label, array $values): Release
    {
        $artist = Artist::factory()->create(['label_id' => $label->id, 'created_by' => $super->id, 'account_status' => 'active']);

        return Release::factory()->create(array_merge([
            'artist_id' => $artist->id,
            'label_id' => $label->id,
            'status' => 'approved',
            'created_by' => $super->id,
        ], $values));
    }

    private function store(string $name): int
    {
        return (int) DB::table('distribution_stores')->insertGetId([
            'name' => $name, 'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'is_active' => true, 'default_selected' => true, 'sort_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_filters_search_store_progress_and_label_list(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $labelA = Label::factory()->create(['created_by' => $super->id, 'name' => 'Alpha Gen Records']);
        $labelB = Label::factory()->create(['created_by' => $super->id, 'name' => 'Beta Gen Music']);

        $single = $this->release($super, $labelA, ['title' => 'Gen Single One', 'release_type' => 'single', 'digital_release_date' => '2026-07-10', 'status' => 'live']);
        $album = $this->release($super, $labelB, ['title' => 'Gen Album Two', 'release_type' => 'album', 'digital_release_date' => '2026-08-02']);

        DB::table('tracks')->insert(array_filter([
            'public_id' => (string) Str::ulid(), 'release_id' => $album->id, 'title' => 'Gen Track', 'isrc' => 'INGEN2600001',
            'created_at' => now(), 'updated_at' => now(),
        ] + $this->trackDefaults($album), fn ($v) => $v !== null));

        foreach ([['Store A', 'live'], ['Store B', 'live'], ['Store C', 'pending']] as [$name, $status]) {
            DB::table('release_store_deliveries')->insert([
                'public_id' => (string) Str::ulid(), 'release_id' => $single->id, 'distribution_store_id' => $this->store($name),
                'status' => $status, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($super)->get('/releases')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('V2/Releases/Index')
                ->has('releases.data', 2)
                ->has('labelOptions', 2)
                ->where('releases.data', fn ($rows) => collect($rows)->firstWhere('id', $single->id)['stores_total'] == 3
                    && collect($rows)->firstWhere('id', $single->id)['stores_live'] == 2));

        $titles = fn (string $query) => collect(
            $this->actingAs($super)->get('/releases?'.$query)->viewData('page')['props']['releases']['data']
        )->pluck('title')->all();

        $this->assertSame(['Gen Album Two'], $titles('release_type=album'));
        /* W76: the release-date month filter was removed - an old ?month= link shows everything */
        $this->assertCount(2, $titles('month=2026-07'));
        $this->assertSame(['Gen Album Two'], $titles('search=INGEN2600001'));
        $this->assertSame(['Gen Single One'], $titles('search=Alpha+Gen'));
        $this->assertSame(['Gen Album Two'], $titles('label_id='.$labelB->id));
        $this->assertSame([], $titles('release_type=ep'));

        // a bad value is ignored, not an error
        $this->actingAs($super)->get('/releases?release_type=zzz&month=bad')->assertOk();
    }

    private function trackDefaults(Release $release): array
    {
        $row = [];
        $columns = DB::getSchemaBuilder()->getColumnListing('tracks');
        $defaults = [
            'artist_id' => $release->artist_id, 'label_id' => $release->label_id, 'track_number' => 1, 'disc_number' => 1,
            'primary_artist_name' => 'Gen Artist', 'status' => 'draft', 'language' => 'Hindi', 'primary_genre' => 'Pop',
            'created_by' => $release->created_by,
        ];
        foreach ($defaults as $column => $value) {
            if (in_array($column, $columns, true)) {
                $row[$column] = $value;
            }
        }

        return $row;
    }
}
