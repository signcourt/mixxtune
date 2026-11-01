<?php

namespace Tests\Feature\Releases;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\User;
use App\Services\V2\ReleaseValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/* W131 MIXXTUNE_MEDIA_OPTIONAL (generated test data only) */
class MediaOptionalTest extends TestCase
{
    use RefreshDatabase;

    private function storePayload(Label $label, array $extra = []): array
    {
        return array_merge([
            'catalog_number' => 'WP-'.uniqid(),
            'release_type' => 'single',
            'title' => 'Old Song',
            'label_id' => $label->id,
            'primary_artist_name' => 'Some Singer',
            'primary_artists' => [['name' => 'Some Singer']],
            'featuring_artists' => [],
            'language' => 'Hindi',
            'primary_genre' => 'Devotional',
            'sub_genre' => 'Bhajan',
            'generate_upc' => true,
            'digital_release_date' => now('Asia/Kolkata')->addMonths(2)->toDateString(),
            'copyright_owner' => 'Label',
            'copyright_year' => '2019',
            'phonographic_owner' => 'Label',
            'phonographic_year' => '2019',
        ], $extra);
    }

    public function test_only_admin_super_admin_can_turn_on_media_optional(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $owner = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create(['user_id' => $owner->id, 'created_by' => $owner->id, 'parent_label_id' => null]);

        /* a label sends media_optional = true -> ignored (stays false) */
        $this->actingAs($owner)->post(route('v2.releases.store'), $this->storePayload($label, ['title' => 'Label One', 'media_optional' => true]))->assertSessionHasNoErrors();
        $this->assertFalse((bool) Release::query()->where('title', 'Label One')->value('media_optional'));

        /* a Super Admin can set it */
        $this->actingAs($super)->post(route('v2.releases.store'), $this->storePayload($label, ['title' => 'Old One', 'media_optional' => true]))->assertSessionHasNoErrors();
        $this->assertTrue((bool) Release::query()->where('title', 'Old One')->value('media_optional'));
    }

    public function test_admin_can_add_a_track_without_wav_only_when_flag_on(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $owner = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create(['user_id' => $owner->id, 'created_by' => $owner->id, 'parent_label_id' => null]);
        $artist = Artist::factory()->create(['user_id' => $owner->id, 'label_id' => $label->id, 'created_by' => $owner->id]);

        $off = Release::factory()->create(['artist_id' => $artist->id, 'label_id' => $label->id, 'catalog_number' => 'WP-'.uniqid(), 'title' => 'NoFlag', 'status' => 'draft', 'media_optional' => false, 'created_by' => $admin->id]);
        $on = Release::factory()->create(['artist_id' => $artist->id, 'label_id' => $label->id, 'catalog_number' => 'WP-'.uniqid(), 'title' => 'Flag', 'status' => 'draft', 'media_optional' => true, 'created_by' => $admin->id]);

        $track = ['title' => 'T1', 'primary_artist_name' => 'Some Singer', 'language' => 'Hindi', 'parental_advisory' => 'no'];

        /* flag off -> WAV still required (validation error on audio) */
        $this->actingAs($admin)->post("/releases/{$off->id}/tracks", $track)->assertSessionHasErrors('audio');
        $this->assertSame(0, Track::query()->where('release_id', $off->id)->count());
        /* flag on -> track saved without a WAV */
        $this->actingAs($admin)->post("/releases/{$on->id}/tracks", $track)->assertSessionHasNoErrors();
        $t = Track::query()->where('release_id', $on->id)->first();
        $this->assertNotNull($t);
        $this->assertNull($t->audio_path);
        $this->assertSame('skipped', $t->audio_validation_status);
    }

    public function test_submission_skips_poster_and_wav_when_flag_on(): void
    {
        $owner = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create(['user_id' => $owner->id, 'created_by' => $owner->id, 'parent_label_id' => null]);
        $artist = Artist::factory()->create(['user_id' => $owner->id, 'label_id' => $label->id, 'created_by' => $owner->id]);
        $release = Release::factory()->create(['artist_id' => $artist->id, 'label_id' => $label->id, 'catalog_number' => 'WP-'.uniqid(), 'title' => 'Old', 'status' => 'draft', 'media_optional' => true, 'artwork_path' => null, 'digital_release_date' => '2019-05-01', 'created_by' => $owner->id]);
        Track::factory()->create(['release_id' => $release->id, 'title' => 'One', 'primary_artist_name' => 'Some Singer', 'audio_path' => null]);

        $errors = app(ReleaseValidationService::class)->validateForSubmission($release->fresh());
        $this->assertArrayNotHasKey('release.artwork', $errors);
        $this->assertArrayNotHasKey("tracks.{$release->tracks->first()->id}.audio", $errors);

        /* same release without the flag -> both are required */
        $release->forceFill(['media_optional' => false])->save();
        $errors = app(ReleaseValidationService::class)->validateForSubmission($release->fresh());
        $this->assertArrayHasKey('release.artwork', $errors);
    }
}
