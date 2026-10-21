<?php

namespace Tests\Feature\Tracks;

use App\Http\Controllers\V2\ReleaseTrackController;
use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\User;
use App\Services\V2\AudioValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

/* MIXXTUNE_TRACK_CREDITS_SAVED (W65): credits were dropped by the V2 track save */
class TrackCreditsSavedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->mock(AudioValidationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('validate')->zeroOrMoreTimes()->andReturnNull();
        });
    }

    public function test_credits_languages_and_flags_are_saved_on_create_and_update(): void
    {
        $user = User::factory()->create(['role' => 'artist', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create(['created_by' => $user->id]);
        $artist = Artist::factory()->create(['user_id' => $user->id, 'label_id' => $label->id, 'created_by' => $user->id]);
        $release = Release::factory()->create(['artist_id' => $artist->id, 'label_id' => $label->id, 'created_by' => $user->id, 'updated_by' => $user->id, 'status' => 'draft']);

        $r = $this->actingAs($user)->post(action([ReleaseTrackController::class, 'store'], ['release' => $release]), [
            'title' => 'Credit Song',
            'primary_artist_name' => $artist->stage_name,
            'track_type' => 'cover',
            'author_name' => 'Lyric Writer',
            'composer_name' => 'Tune Maker',
            'producer_name' => 'Beat Producer',
            'music_director_name' => 'Music Head',
            'publisher_name' => 'Song Publisher',
            'p_line' => '2026 Credit Label',
            'release_year' => 2025,
            'language' => 'Hindi',
            'title_language' => 'English',
            'lyrics_language' => 'Hindi',
            'genre' => 'Devotional',
            'sub_genre' => 'Bhajan',
            'parental_advisory' => 'cleaned',
            'audio' => UploadedFile::fake()->create('credit-song.wav', 1024, 'audio/wav'),
        ]);
        $r->assertSessionHasNoErrors();

        $track = Track::query()->firstOrFail();
        $this->assertSame('Lyric Writer', $track->author_name);
        $this->assertSame('Tune Maker', $track->composer_name);
        $this->assertSame('Beat Producer', $track->producer_name);
        $this->assertSame('Music Head', $track->music_director_name);
        $this->assertSame('Song Publisher', $track->publisher_name);
        $this->assertSame('2026 Credit Label', $track->p_line);
        $this->assertSame(2025, (int) $track->release_year);
        $this->assertSame('English', $track->title_language);
        $this->assertSame('cover', $track->track_type);
        $this->assertSame('cleaned', $track->parental_advisory);

        $this->actingAs($user)->post(action([ReleaseTrackController::class, 'update'], ['track' => $track]), [
            '_method' => 'patch',
            'title' => 'Credit Song',
            'primary_artist_name' => $artist->stage_name,
            'composer_name' => 'New Composer',
            'author_name' => 'Lyric Writer',
        ])->assertSessionHasNoErrors();

        $this->assertSame('New Composer', $track->fresh()->composer_name);

        $this->actingAs($user)->post(action([ReleaseTrackController::class, 'update'], ['track' => $track]), [
            '_method' => 'patch',
            'title' => 'Credit Song',
            'primary_artist_name' => $artist->stage_name,
            'parental_advisory' => 'maybe',
        ])->assertSessionHasErrors('parental_advisory');
    }
}
