<?php

namespace Tests\Feature;

use App\Models\Core\Artist;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArtistReleaseActionsTest extends TestCase
{
    use RefreshDatabase;

    private function artistUser(): array
    {
        $user = User::factory()->create([
            'role' => 'artist',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $artist = Artist::factory()->create([
            'user_id' => $user->id,
        ]);

        return [$user, $artist];
    }

    public function test_artist_can_delete_own_draft_release(): void
    {
        [$user, $artist] = $this->artistUser();

        $release = Release::factory()->create([
            'artist_id' => $artist->id,
            'created_by' => $user->id,
            'status' => 'draft',
        ]);

        $response = $this
            ->actingAs($user)
            ->delete("/artist/releases/{$release->id}");

        $response->assertRedirect();

        $this->assertSoftDeleted(
            $release->getTable(),
            ['id' => $release->id]
        );
    }

    public function test_artist_cannot_delete_submitted_release(): void
    {
        [$user, $artist] = $this->artistUser();

        $release = Release::factory()->create([
            'artist_id' => $artist->id,
            'created_by' => $user->id,
            'status' => 'submitted',
        ]);

        $this
            ->actingAs($user)
            ->delete("/artist/releases/{$release->id}")
            ->assertForbidden();

        $this->assertDatabaseHas(
            $release->getTable(),
            ['id' => $release->id]
        );
    }

    public function test_artist_cannot_delete_another_artist_release(): void
    {
        [$user] = $this->artistUser();

        [$otherUser, $otherArtist] = $this->artistUser();

        $release = Release::factory()->create([
            'artist_id' => $otherArtist->id,
            'created_by' => $otherUser->id,
            'status' => 'draft',
        ]);

        $this
            ->actingAs($user)
            ->delete("/artist/releases/{$release->id}")
            ->assertForbidden();

        $this->assertDatabaseHas(
            $release->getTable(),
            ['id' => $release->id]
        );
    }

    public function test_artist_can_stream_own_track(): void
    {
        Storage::fake('public');

        [$user, $artist] = $this->artistUser();

        $release = Release::factory()->create([
            'artist_id' => $artist->id,
            'created_by' => $user->id,
            'status' => 'draft',
        ]);

        $track = Track::factory()->create([
            'release_id' => $release->id,
            'audio_path' => 'tracks/test.wav',
            'audio_original_name' => 'test.wav',
            'audio_mime_type' => 'audio/wav',
        ]);

        Storage::disk('public')->put(
            'tracks/test.wav',
            'RIFF-test-audio'
        );

        $response = $this
            ->actingAs($user)
            ->get("/artist/tracks/{$track->id}/stream");

        $response->assertOk();

        $this->assertStringContainsString(
            'audio/wav',
            (string) $response->headers->get('content-type')
        );
    }
}
