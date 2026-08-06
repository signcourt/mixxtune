<?php

namespace Tests\Feature\Tracks;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Contributor;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\Distribution\TrackContributor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackCreditsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createContext(): array
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
            'primary_artist_name' => $artist->stage_name,
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $track = Track::factory()->create([
            'release_id' => $release->id,
            'title' => 'Credits Test Track',
            'primary_artist_name' => $artist->stage_name,
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return [
            $user,
            $label,
            $artist,
            $release,
            $track,
        ];
    }

    private function createContributor(
        User $user,
        array $overrides = []
    ): Contributor {
        return Contributor::query()->create([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'name' => 'Test Composer',
            'legal_name' => 'Test Composer Legal',
            'email' => 'composer@example.com',
            'phone' => '+919999999999',
            'ipi_number' => null,
            'isni' => null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            ...$overrides,
        ]);
    }

    public function test_artist_can_create_contributor(): void
    {
        [
            $user,
            ,
            ,
            ,
            $track,
        ] = $this->createContext();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route(
                    'v2.track-credits.contributors.create',
                    $track
                ),
                [
                    'name' => 'New Lyricist',
                    'legal_name' => 'New Lyricist Legal Name',
                    'email' => 'lyricist@example.com',
                    'phone' => '+919876543210',
                    'ipi_number' => 'IPI123456789',
                    'isni' => null,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Contributor created.'
            );

        $this->assertDatabaseHas(
            'contributors',
            [
                'name' => 'New Lyricist',
                'email' => 'lyricist@example.com',
                'created_by' => $user->id,
            ]
        );
    }

    public function test_artist_can_attach_contributor_to_track(): void
    {
        [
            $user,
            ,
            ,
            ,
            $track,
        ] = $this->createContext();

        $contributor = $this->createContributor(
            $user
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                route(
                    'v2.track-credits.contributors.attach',
                    $track
                ),
                [
                    'contributor_id' => $contributor->id,
                    'role' => 'composer',
                    'is_primary' => true,
                    'display_order' => 1,
                    'metadata' => ['notes' => 'Primary composer'],
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Contributor attached to track.'
            );

        $this->assertDatabaseHas(
            'track_contributors',
            [
                'track_id' => $track->id,
                'contributor_id' => $contributor->id,
                'role' => 'composer',
                'is_primary' => 1,
            ]
        );
    }

    public function test_duplicate_contributor_role_is_rejected(): void
    {
        [
            $user,
            ,
            ,
            ,
            $track,
        ] = $this->createContext();

        $contributor = $this->createContributor(
            $user
        );

        $payload = [
            'contributor_id' => $contributor->id,
            'role' => 'composer',
            'is_primary' => true,
            'display_order' => 1,
        ];

        $this
            ->actingAs($user)
            ->postJson(
                route(
                    'v2.track-credits.contributors.attach',
                    $track
                ),
                $payload
            )
            ->assertOk();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route(
                    'v2.track-credits.contributors.attach',
                    $track
                ),
                $payload
            );

        $response->assertStatus(422);

        $this->assertSame(
            'This contributor already has this role on the track.',
            $response->json(
                'errors.contributor_id.0'
            )
        );

        $this->assertSame(
            1,
            TrackContributor::query()
                ->where('track_id', $track->id)
                ->where(
                    'contributor_id',
                    $contributor->id
                )
                ->where('role', 'composer')
                ->count()
        );
    }

    public function test_invalid_contributor_role_is_rejected(): void
    {
        [
            $user,
            ,
            ,
            ,
            $track,
        ] = $this->createContext();

        $contributor = $this->createContributor(
            $user
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                route(
                    'v2.track-credits.contributors.attach',
                    $track
                ),
                [
                    'contributor_id' => $contributor->id,
                    'role' => 'invalid_role',
                    'is_primary' => false,
                    'display_order' => 1,
                ]
            );

        $response->assertStatus(422);

        $this->assertSame(
            'Invalid contributor role.',
            $response->json(
                'errors.role.0'
            )
        );

        $this->assertDatabaseCount(
            'track_contributors',
            0
        );
    }

    public function test_artist_can_update_contributor_credit(): void
    {
        [
            $user,
            ,
            ,
            ,
            $track,
        ] = $this->createContext();

        $contributor = $this->createContributor(
            $user
        );

        $credit = TrackContributor::query()->create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'track_id' => $track->id,
            'contributor_id' => $contributor->id,
            'role' => 'composer',
            'is_primary' => false,
            'credited_name' => $contributor->name,
            'is_featured' => false,
            'display_order' => 1,
            'metadata' => null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route(
                    'v2.track-credits.credit.update',
                    $credit
                ),
                [
                    'role' => 'lyricist',
                    'is_primary' => true,
                    'display_order' => 2,
                    'metadata' => ['notes' => 'Updated credit'],
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Contributor credit updated.'
            );

        $credit->refresh();

        $this->assertSame(
            'lyricist',
            $credit->role
        );

        $this->assertTrue(
            $credit->is_primary
        );

        $this->assertSame(
            2,
            $credit->display_order
        );

        $this->assertSame(
            'Updated credit',
            $credit->metadata['notes'] ?? null
        );
    }

    public function test_artist_can_delete_contributor_credit(): void
    {
        [
            $user,
            ,
            ,
            ,
            $track,
        ] = $this->createContext();

        $contributor = $this->createContributor(
            $user
        );

        $credit = TrackContributor::query()->create([
            'public_id' => (string) \Illuminate\Support\Str::uuid(),
            'track_id' => $track->id,
            'contributor_id' => $contributor->id,
            'role' => 'producer',
            'is_primary' => false,
            'credited_name' => $contributor->name,
            'is_featured' => false,
            'display_order' => 1,
            'metadata' => null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->deleteJson(
                route(
                    'v2.track-credits.credit.delete',
                    $credit
                )
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Contributor credit deleted.'
            );

        $this->assertDatabaseMissing(
            'track_contributors',
            [
                'id' => $credit->id,
            ]
        );
    }

    public function test_other_artist_cannot_manage_track_credits(): void
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
        ] = $this->createContext();

        $response = $this
            ->actingAs($otherUser)
            ->postJson(
                route(
                    'v2.track-credits.contributors.create',
                    $track
                ),
                [
                    'name' => 'Unauthorized Contributor',
                    'email' => 'unauthorized@example.com',
                ]
            );

        $response->assertForbidden();

        $this->assertDatabaseMissing(
            'contributors',
            [
                'email' => 'unauthorized@example.com',
            ]
        );
    }
}
