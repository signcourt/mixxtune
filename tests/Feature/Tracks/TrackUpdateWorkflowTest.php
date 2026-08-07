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

class TrackUpdateWorkflowTest extends TestCase
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

    private function createContext(
        string $releaseStatus = 'draft'
    ): array {
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
            'primary_artist_name' => $artist->stage_name,
            'status' => $releaseStatus,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $track = Track::factory()->create([
            'release_id' => $release->id,
            'disc_number' => 1,
            'track_number' => 1,
            'title' => 'Original Track Title',
            'version' => null,
            'primary_artist_name' => $artist->stage_name,
            'audio_path' => 'tracks/audio/original-track.wav',
            'audio_original_name' => 'original-track.wav',
            'audio_mime_type' => 'audio/wav',
            'audio_size_bytes' => 1024,
            'audio_validation_status' => 'passed',
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => null,
        ]);

        Storage::disk('public')->put(
            $track->audio_path,
            'original audio content'
        );

        return [
            $user,
            $label,
            $artist,
            $release,
            $track,
        ];
    }

    public function test_artist_can_update_track_metadata(): void
    {
        [
            $user,
            ,
            $artist,
            ,
            $track,
        ] = $this->createContext();

        $response = $this
            ->actingAs($user)
            ->patch(
                route(
                    'v2.release-tracks.update',
                    $track
                ),
                [
                    'disc_number' => 1,
                    'track_number' => 1,
                    'title' => 'Updated Track Title',
                    'version' => 'Acoustic Version',
                    'subtitle' => 'Special Edition',
                    'primary_artist_name' =>
                        $artist->stage_name,
                    'featuring_artist_name' =>
                        'Guest Artist',
                    'isrc' => 'INMIX2600001',
                    'language' => 'Hindi',
                    'genre' => 'Devotional',
                    'sub_genre' => 'Bhajan',
                    'is_explicit' => false,
                    'is_instrumental' => false,
                    'contains_ai_generated_content' =>
                        true,
                    'duration_seconds' => 245,
                    'preview_start_seconds' => 35,
                    'lyrics' => 'Updated lyrics content.',
                    'status' => 'draft',
                ]
            );

        $response->assertSessionHasNoErrors();

        $track->refresh();

        $this->assertSame(
            'Updated Track Title',
            $track->title
        );

        $this->assertSame(
            'Acoustic Version',
            $track->version
        );

        $this->assertSame(
            'Special Edition',
            $track->subtitle
        );

        $this->assertSame(
            'Guest Artist',
            $track->featuring_artist_name
        );

        $this->assertSame(
            'INMIX2600001',
            $track->isrc
        );

        $this->assertTrue(
            $track->contains_ai_generated_content
        );

        $this->assertSame(
            245,
            $track->duration_seconds
        );

        $this->assertSame(
            35,
            $track->preview_start_seconds
        );

        $this->assertSame(
            'Updated lyrics content.',
            $track->lyrics
        );

        $this->assertSame(
            $user->id,
            $track->updated_by
        );

        Storage::disk('public')->assertExists(
            'tracks/audio/original-track.wav'
        );
    }

    public function test_artist_can_replace_track_audio(): void
    {
        [
            $user,
            ,
            $artist,
            ,
            $track,
        ] = $this->createContext();

        $oldAudioPath = $track->audio_path;

        $response = $this
            ->actingAs($user)
            ->patch(
                route(
                    'v2.release-tracks.update',
                    $track
                ),
                [
                    'title' => $track->title,
                    'primary_artist_name' =>
                        $artist->stage_name,
                    'audio' =>
                        UploadedFile::fake()->create(
                            'replacement-track.wav',
                            2048,
                            'audio/wav'
                        ),
                ]
            );

        $response->assertSessionHasNoErrors();

        $track->refresh();

        Storage::disk('public')->assertMissing(
            $oldAudioPath
        );

        $this->assertNotSame(
            $oldAudioPath,
            $track->audio_path
        );

        Storage::disk('public')->assertExists(
            $track->audio_path
        );

        $this->assertSame(
            'replacement-track.wav',
            $track->audio_original_name
        );

        $this->assertSame(
            'pending',
            $track->audio_validation_status
        );

        $this->assertSame(
            $user->id,
            $track->updated_by
        );
    }

    public function test_artist_can_soft_delete_track_and_audio(): void
    {
        [
            $user,
            ,
            ,
            ,
            $track,
        ] = $this->createContext();

        $audioPath = $track->audio_path;

        $response = $this
            ->actingAs($user)
            ->delete(
                route(
                    'v2.release-tracks.destroy',
                    $track
                )
            );

        $response->assertSessionHasNoErrors();

        $this->assertSoftDeleted(
            'tracks',
            [
                'id' => $track->id,
            ]
        );

        Storage::disk('public')->assertMissing(
            $audioPath
        );

        $deletedTrack = Track::withTrashed()
            ->findOrFail($track->id);

        $this->assertSame(
            $user->id,
            $deletedTrack->updated_by
        );
    }

    public function test_submitted_release_track_cannot_be_updated(): void
    {
        [
            $user,
            ,
            $artist,
            ,
            $track,
        ] = $this->createContext('submitted');

        $response = $this
            ->actingAs($user)
            ->patch(
                route(
                    'v2.release-tracks.update',
                    $track
                ),
                [
                    'title' => 'Blocked Update',
                    'primary_artist_name' =>
                        $artist->stage_name,
                ]
            );

        $response->assertForbidden();

        $track->refresh();

        $this->assertSame(
            'Original Track Title',
            $track->title
        );
    }

    public function test_submitted_release_track_cannot_be_deleted(): void
    {
        [
            $user,
            ,
            ,
            ,
            $track,
        ] = $this->createContext('submitted');

        $response = $this
            ->actingAs($user)
            ->delete(
                route(
                    'v2.release-tracks.destroy',
                    $track
                )
            );

        $response->assertForbidden();

        $this->assertDatabaseHas(
            'tracks',
            [
                'id' => $track->id,
                'deleted_at' => null,
            ]
        );

        Storage::disk('public')->assertExists(
            $track->audio_path
        );
    }

    public function test_other_artist_cannot_update_track(): void
    {
        [
            ,
            ,
            ,
            ,
            $track,
        ] = $this->createContext();

        [
            $otherUser,
            ,
            $otherArtist,
        ] = $this->createContext();

        $response = $this
            ->actingAs($otherUser)
            ->patch(
                route(
                    'v2.release-tracks.update',
                    $track
                ),
                [
                    'title' => 'Unauthorized Update',
                    'primary_artist_name' =>
                        $otherArtist->stage_name,
                ]
            );

        $response->assertForbidden();

        $track->refresh();

        $this->assertSame(
            'Original Track Title',
            $track->title
        );
    }
}
