<?php

namespace Tests\Feature\Identifiers;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* W98 MIXXTUNE_IDENTIFIER_DETAILS (generated test data only) */
class PendingIdentifierDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_lists_show_song_name_file_name_and_release_details(): void
    {
        $this->withoutVite();
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $owner = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create(['created_by' => $owner->id, 'name' => 'Saras Music']);
        $artist = Artist::factory()->create(['user_id' => $owner->id, 'label_id' => $label->id, 'created_by' => $owner->id]);
        $release = Release::factory()->create([
            'artist_id' => $artist->id, 'label_id' => $label->id, 'catalog_number' => 'MXT-2026-0000001', 'release_type' => 'single',
            'title' => 'Mere Shyam', 'primary_artist_name' => 'Ravi Kumar', 'status' => 'approved', 'upc' => null,
            'digital_release_date' => '2026-11-20', 'created_by' => $owner->id, 'updated_by' => $owner->id,
        ]);
        Track::factory()->withAudio()->create([
            'release_id' => $release->id, 'track_number' => 1, 'title' => 'Mere Shyam (Bhajan)', 'version' => 'Lofi',
            'audio_original_name' => 'final_master_v3.wav', 'primary_artist_name' => 'Ravi Kumar', 'author_name' => 'Lekh', 'composer_name' => 'Sur',
            'isrc' => null,
        ]);
        Track::factory()->withAudio()->create(['release_id' => $release->id, 'track_number' => 2, 'title' => 'Done Song', 'isrc' => 'INMXT2600001']);

        $this->actingAs($super)->get('/admin/identifiers/pending')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Admin/Identifiers/Index')
            ->where('pendingIsrc.total', 1)
            ->where('pendingIsrc.data.0.title', 'Mere Shyam (Bhajan)')
            ->where('pendingIsrc.data.0.version', 'Lofi')
            ->where('pendingIsrc.data.0.audio_original_name', 'final_master_v3.wav')
            ->where('pendingIsrc.data.0.author_name', 'Lekh')
            ->where('pendingIsrc.data.0.release.title', 'Mere Shyam')
            ->where('pendingIsrc.data.0.release.label', 'Saras Music')
            ->where('pendingIsrc.data.0.release.catalog_number', 'MXT-2026-0000001')
            ->where('pendingUpc.total', 1)
            ->where('pendingUpc.data.0.title', 'Mere Shyam')
            ->where('pendingUpc.data.0.primary_artist_name', 'Ravi Kumar')
            ->where('pendingUpc.data.0.label', 'Saras Music')
            ->where('pendingUpc.data.0.tracks_count', 2)
            ->where('pendingUpc.data.0.tracks_with_isrc_count', 1)
            ->where('pendingUpc.data.0.release_date', '2026-11-20'));
    }
}
