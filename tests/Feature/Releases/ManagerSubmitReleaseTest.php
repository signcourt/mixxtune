<?php

namespace Tests\Feature\Releases;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\DistributionStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/* W117 MIXXTUNE_MANAGER_SUBMIT (generated test data only) */
class ManagerSubmitReleaseTest extends TestCase
{
    use RefreshDatabase;

    private function draftBy(User $maker): Release
    {
        $owner = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create(['created_by' => $owner->id, 'user_id' => $owner->id]);
        $artist = Artist::factory()->create(['user_id' => $owner->id, 'label_id' => $label->id, 'created_by' => $owner->id]);
        $store = DistributionStore::query()->create(['name' => 'Store '.uniqid(), 'slug' => 'store-'.uniqid(), 'is_active' => true]);
        $release = Release::factory()->create([
            'artist_id' => $artist->id, 'label_id' => $label->id, 'catalog_number' => 'MXT-MGR-'.uniqid(),
            'release_type' => 'single', 'title' => 'Manager Made Release',
            'primary_artist_name' => $artist->stage_name ?: $artist->legal_name,
            'primary_artists' => [['name' => $artist->stage_name ?: $artist->legal_name]],
            'language' => 'Hindi', 'primary_genre' => 'Devotional',
            'digital_release_date' => now()->addDays(14)->toDateString(),
            'artwork_path' => 'releases/artwork/mgr-cover.jpg', 'stores' => [$store->id],
            'worldwide' => true, 'territories' => [], 'release_timezone' => 'Asia/Kolkata', 'pre_order' => false,
            'status' => 'draft', 'wizard_step' => 4, 'completion_percentage' => 90,
            'created_by' => $maker->id, 'updated_by' => $maker->id,
        ]);
        Track::factory()->withAudio()->create(['release_id' => $release->id, 'title' => 'Manager Track', 'primary_artist_name' => $artist->stage_name ?: $artist->legal_name, 'created_by' => $maker->id, 'updated_by' => $maker->id]);

        return $release;
    }

    public function test_manager_can_send_a_release_made_for_a_label_for_review(): void
    {
        $manager = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $release = $this->draftBy($manager);

        $this->actingAs($manager)->post(route('v2.releases.submit', $release))
            ->assertRedirect(route('v2.releases.show', $release))
            ->assertSessionHas('submitted', true);
        $this->assertSame('submitted', $release->fresh()->status);
        $this->actingAs($manager)->get(route('v2.releases.show', $release))->assertOk();
    }

    public function test_manager_with_create_release_blocked_still_cannot_send(): void
    {
        $manager = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $release = $this->draftBy($manager);
        DB::table('panel_access_overrides')->insert(['user_id' => $manager->id, 'tab' => 'create_release', 'allowed' => false, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($manager)->post(route('v2.releases.submit', $release))->assertForbidden();
        $this->assertSame('draft', $release->fresh()->status);
    }
}
