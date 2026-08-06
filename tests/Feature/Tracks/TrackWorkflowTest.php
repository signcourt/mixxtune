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

class TrackWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->mock(
            AudioValidationService::class,
            function (MockInterface $mock): void {
                $mock
                    ->shouldReceive('validate')
                    ->zeroOrMoreTimes()
                    ->andReturnNull();
            }
        );
    }

    private function createArtistContext(): array
    {
        $user = User::factory()->create([
            'role' => 'artist',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $label = Label::factory()->create([
            'created_by' => $user->id,
        ]);

        $artist = Artist::factory()->create([
            'user_id' => $user->id,
            'label_id' => $label->id,
            'created_by' => $user->id,
        ]);

        $release = Release::factory()->create([
            'artist_id' => $artist->id,
            'label_id' => $label->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'primary_artist_name' => $artist->stage_name,
            'status' => 'draft',
            'wizard_step' => 1,
            'completion_percentage' => 20,
        ]);

        return [
            $user,
            $label,
            $artist,
            $release,
        ];
    }

    private function trackStoreUrl(
        Release $release
    ): string {
        return action(
            [
                ReleaseTrackController::class,
                'store',
            ],
            [
                'release' => $release,
            ]
        );
    }

    private function validPayload(
        Artist $artist,
        string $filename = 'test-track.wav'
    ): array {
        return [
            'disc_number' => 1,
            'title' => 'Test Devotional Track',
            'version' => 'Original Version',
            'subtitle' => 'Test Subtitle',
            'primary_artist_name' =>
                $artist->stage_name,
            'featuring_artist_name' => null,
            'isrc' => null,
            'language' => 'Hindi',
            'genre' => 'Devotional',
            'sub_genre' => 'Bhajan',
            'is_explicit' => false,
            'is_instrumental' => false,
            'contains_ai_generated_content' => false,
            'duration_seconds' => 240,
            'preview_start_seconds' => 30,
            'lyrics' => 'Test devotional lyrics.',
            'audio' => UploadedFile::fake()->create(
                $filename,
                1024,
                'audio/wav'
            ),
        ];
    }

    public function test_artist_can_upload_wav_track(): void
    {
        [
            $user,
            ,
            $artist,
            $release,
        ] = $this->createArtistContext();

        $response = $this
            ->actingAs($user)
            ->post(
                $this->trackStoreUrl($release),
                $this->validPayload($artist)
            );

        $response->assertSessionHasNoErrors();

        $track = Track::query()->first();

        $this->assertNotNull($track);

        $this->assertSame(
            $release->id,
            $track->release_id
        );

        $this->assertSame(
            'Test Devotional Track',
            $track->title
        );

        $this->assertSame(
            'Original Version',
            $track->version
        );

        $this->assertSame(
            $artist->stage_name,
            $track->primary_artist_name
        );

        $this->assertSame(
            1,
            $track->disc_number
        );

        $this->assertSame(
            1,
            $track->track_number
        );

        $this->assertSame(
            'pending',
            $track->audio_validation_status
        );

        $this->assertSame(
            $user->id,
            $track->created_by
        );

        $this->assertNotNull(
            $track->audio_path
        );

        Storage::disk('public')->assertExists(
            $track->audio_path
        );

        $release->refresh();

        $this->assertGreaterThanOrEqual(
            2,
            $release->wizard_step
        );

        $this->assertGreaterThanOrEqual(
            50,
            $release->completion_percentage
        );
    }

    public function test_track_number_is_generated_automatically(): void
    {
        [
            $user,
            ,
            $artist,
            $release,
        ] = $this->createArtistContext();

        $firstPayload = $this->validPayload(
            $artist,
            'first-track.wav'
        );

        unset($firstPayload['track_number']);

        $this
            ->actingAs($user)
            ->post(
                $this->trackStoreUrl($release),
                $firstPayload
            )
            ->assertSessionHasNoErrors();

        $secondPayload = $this->validPayload(
            $artist,
            'second-track.wav'
        );

        $secondPayload['title'] =
            'Second Devotional Track';

        unset($secondPayload['track_number']);

        $this
            ->actingAs($user)
            ->post(
                $this->trackStoreUrl($release),
                $secondPayload
            )
            ->assertSessionHasNoErrors();

        $tracks = Track::query()
            ->where('release_id', $release->id)
            ->orderBy('track_number')
            ->get();

        $this->assertCount(2, $tracks);

        $this->assertSame(
            1,
            $tracks[0]->track_number
        );

        $this->assertSame(
            2,
            $tracks[1]->track_number
        );
    }

    public function test_non_wav_audio_is_rejected(): void
    {
        [
            $user,
            ,
            $artist,
            $release,
        ] = $this->createArtistContext();

        $payload = $this->validPayload($artist);

        $payload['audio'] =
            UploadedFile::fake()->create(
                'invalid-track.mp3',
                1024,
                'audio/mpeg'
            );

        $response = $this
            ->actingAs($user)
            ->postJson(
                $this->trackStoreUrl($release),
                $payload
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'audio'
            );

        $this->assertDatabaseCount(
            'tracks',
            0
        );
    }

    public function test_artist_cannot_add_track_to_another_artist_release(): void
    {
        [
            ,
            ,
            ,
            $release,
        ] = $this->createArtistContext();

        [
            $otherUser,
            ,
            $otherArtist,
        ] = $this->createArtistContext();

        $response = $this
            ->actingAs($otherUser)
            ->post(
                $this->trackStoreUrl($release),
                $this->validPayload(
                    $otherArtist,
                    'unauthorized-track.wav'
                )
            );

        $response->assertForbidden();

        $this->assertDatabaseCount(
            'tracks',
            0
        );
    }
}
