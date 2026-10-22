<?php

namespace Tests\Feature\Tracks;

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

/* W87 MIXXTUNE_BACKGROUND_WAV_UPLOAD */
class BackgroundWavUploadTest extends TestCase
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

    private function owner(): array
    {
        $user = User::factory()->create(['role' => 'artist', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create(['created_by' => $user->id]);
        $artist = Artist::factory()->create(['user_id' => $user->id, 'label_id' => $label->id, 'created_by' => $user->id]);
        $release = Release::factory()->create(['artist_id' => $artist->id, 'label_id' => $label->id, 'created_by' => $user->id, 'updated_by' => $user->id, 'status' => 'draft']);

        return [$user, $release, $artist];
    }

    private function details(Artist $artist, array $extra = []): array
    {
        return array_merge([
            'title' => 'Background Song',
            'primary_artist_name' => $artist->stage_name,
            'author_name' => 'Lekh',
            'composer_name' => 'Ravi',
            'language' => 'Hindi',
            'genre' => 'Devotional',
            'sub_genre' => 'Bhajan',
        ], $extra);
    }

    public function test_wav_uploaded_first_is_used_when_the_track_is_saved(): void
    {
        [$user, $release, $artist] = $this->owner();

        $token = $this->actingAs($user)
            ->postJson(route('v2.release-tracks.audio-upload', $release), ['audio' => UploadedFile::fake()->create('song.wav', 2048, 'audio/wav')])
            ->assertOk()
            ->json('token');
        $this->assertSame(40, strlen($token));

        $this->actingAs($user)->post(route('v2.release-tracks.store', $release), $this->details($artist, ['audio_upload_token' => $token]))
            ->assertSessionHasNoErrors();

        $track = Track::query()->firstOrFail();
        $this->assertSame('song.wav', $track->audio_original_name);
        Storage::disk('public')->assertExists($track->audio_path);

        /* a token works once */
        $this->actingAs($user)->post(route('v2.release-tracks.store', $release), $this->details($artist, ['title' => 'Again', 'audio_upload_token' => $token]))
            ->assertSessionHasErrors('audio');
        $this->assertSame(1, Track::query()->count());
    }

    public function test_upload_checks_file_and_token_belongs_to_user_and_release(): void
    {
        [$user, $release, $artist] = $this->owner();
        [$other, $otherRelease] = $this->owner();

        $this->actingAs($user)->postJson(route('v2.release-tracks.audio-upload', $release), ['audio' => UploadedFile::fake()->create('song.mp3', 100, 'audio/mpeg')])
            ->assertStatus(422)->assertJsonValidationErrors('audio');

        /* someone else's release */
        $this->actingAs($user)->postJson(route('v2.release-tracks.audio-upload', $otherRelease), ['audio' => UploadedFile::fake()->create('x.wav', 100, 'audio/wav')])
            ->assertForbidden();

        $token = $this->actingAs($user)->postJson(route('v2.release-tracks.audio-upload', $release), ['audio' => UploadedFile::fake()->create('mine.wav', 100, 'audio/wav')])->json('token');

        /* another user cannot use my token */
        $this->actingAs($other)->post(route('v2.release-tracks.store', $otherRelease), $this->details($artist, ['audio_upload_token' => $token]))
            ->assertSessionHasErrors('audio');
        $this->assertSame(0, Track::query()->count());

        /* ... and it still works for me afterwards */
        $this->actingAs($user)->post(route('v2.release-tracks.store', $release), $this->details($artist, ['audio_upload_token' => $token]))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Track::query()->count());

        /* no file and no token */
        $this->actingAs($user)->post(route('v2.release-tracks.store', $release), $this->details($artist))
            ->assertSessionHasErrors('audio');
    }

    /* W91: unused uploads wait in tracks/audio-pending and are cleaned; used ones move to tracks/audio */
    public function test_used_upload_moves_and_unused_ones_are_cleaned(): void
    {
        [$user, $release, $artist] = $this->owner();

        $token = $this->actingAs($user)->postJson(route('v2.release-tracks.audio-upload', $release), ['audio' => UploadedFile::fake()->create('used.wav', 100, 'audio/wav')])->json('token');
        $this->actingAs($user)->postJson(route('v2.release-tracks.audio-upload', $release), ['audio' => UploadedFile::fake()->create('left.wav', 100, 'audio/wav')]);

        $this->actingAs($user)->post(route('v2.release-tracks.store', $release), $this->details($artist, ['audio_upload_token' => $token]))->assertSessionHasNoErrors();
        $track = Track::query()->firstOrFail();
        $this->assertStringStartsWith('tracks/audio/', $track->audio_path);
        Storage::disk('public')->assertExists($track->audio_path);

        $pending = Storage::disk('public')->files('tracks/audio-pending');
        $this->assertCount(1, $pending);

        /* still fresh: kept */
        $this->artisan('tracks:clean-pending-audio')->assertSuccessful();
        $this->assertCount(1, Storage::disk('public')->files('tracks/audio-pending'));

        /* two days later: removed; the track's own file stays */
        touch(Storage::disk('public')->path($pending[0]), now()->subDays(2)->getTimestamp());
        $this->artisan('tracks:clean-pending-audio')->assertSuccessful();
        $this->assertCount(0, Storage::disk('public')->files('tracks/audio-pending'));
        Storage::disk('public')->assertExists($track->audio_path);
    }
}
