<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/*
 * MIXXTUNE_CATALOGUE_HEALTH_V1
 * All data is generated inside the SQLite :memory: test database.
 */
class CatalogueHealthTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    public function test_missing_details_counts_lists_and_exports_without_changing_anything(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');
        $withPct = Label::factory()->create(['created_by' => $super->id, 'user_id' => $owner->id, 'name' => 'Gen Full Label', 'revenue_share_percentage' => 80]);
        $noPct = Label::factory()->create(['created_by' => $super->id, 'user_id' => null, 'name' => 'Gen Empty Label', 'revenue_share_percentage' => 0]);
        $artist = Artist::factory()->create(['label_id' => $withPct->id, 'created_by' => $super->id]);

        $make = fn (array $x) => Release::factory()->create(array_merge([
            'artist_id' => $artist->id, 'label_id' => $withPct->id, 'status' => 'live', 'created_by' => $super->id,
            'artwork_path' => 'releases/artwork/gen.jpg', 'upc' => null, 'primary_genre' => 'Pop', 'digital_release_date' => '2026-01-01',
        ], $x));

        $make(['title' => 'Gen No Poster', 'artwork_path' => null, 'upc' => '123456789012']);
        $make(['title' => 'Gen Draft', 'artwork_path' => null, 'status' => 'draft']);

        $before = DB::table('releases')->count();

        $this->actingAs($super)->get('/super-admin/catalogue-health?check=no_poster')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('V2/Admin/CatalogueHealth/Index')
                ->where('checks.0.key', 'no_poster')
                ->where('checks.0.count', 1)
                ->where('list.rows.total', 1)
                ->where('list.rows.data.0.title', 'Gen No Poster'));

        $this->actingAs($super)->get('/super-admin/catalogue-health?check=label_no_percent')
            ->assertInertia(fn (Assert $page) => $page->where('list.rows.total', 1)->where('list.rows.data.0.name', 'Gen Empty Label'));

        $csv = $this->actingAs($super)->get('/super-admin/catalogue-health/export?check=label_no_owner')->assertOk()->streamedContent();
        $this->assertStringContainsString('Gen Empty Label', $csv);
        $this->assertStringNotContainsString('Gen Full Label', $csv);

        $this->actingAs($super)->get('/super-admin/catalogue-health/export?check=nope')->assertNotFound();
        $this->assertSame($before, DB::table('releases')->count());

        $this->actingAs($this->user('admin'))->get('/super-admin/catalogue-health')->assertForbidden();
    }
}
