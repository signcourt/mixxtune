<?php

namespace Tests\Feature\Tracks;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Contributor;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\Distribution\TrackSplit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrackSplitsWorkflowTest extends TestCase
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
            'title' => 'Split Test Track',
            'primary_artist_name' => $artist->stage_name,
            'status' => 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $firstContributor = $this->createContributor(
            $user,
            'First Recipient',
            'first@example.com'
        );

        $secondContributor = $this->createContributor(
            $user,
            'Second Recipient',
            'second@example.com'
        );

        return [
            $user,
            $label,
            $artist,
            $release,
            $track,
            $firstContributor,
            $secondContributor,
        ];
    }

    private function createContributor(
        User $user,
        string $name,
        string $email
    ): Contributor {
        return Contributor::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => $name,
            'legal_name' => $name,
            'email' => $email,
            'country' => 'India',
            'can_receive_splits' => true,
            'has_dashboard_access' => false,
            'status' => 'active',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    private function splitUrl(
        Track $track
    ): string {
        return route(
            'v2.track-credits.splits.replace',
            $track
        );
    }

    private function validSplits(
        Contributor $first,
        Contributor $second
    ): array {
        return [
            [
                'contributor_id' => $first->id,
                'recipient_name' => $first->name,
                'recipient_email' => $first->email,
                'percentage' => 60,
                'notes' => 'Primary share',
            ],
            [
                'contributor_id' => $second->id,
                'recipient_name' => $second->name,
                'recipient_email' => $second->email,
                'percentage' => 40,
                'notes' => 'Secondary share',
            ],
        ];
    }

    public function test_artist_can_save_master_splits_totalling_100(): void
    {
        [
            $user,
            ,
            ,
            ,
            $track,
            $first,
            $second,
        ] = $this->createContext();

        $response = $this
            ->actingAs($user)
            ->putJson(
                $this->splitUrl($track),
                [
                    'split_type' => 'master',
                    'splits' => $this->validSplits(
                        $first,
                        $second
                    ),
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Track splits updated.'
            );

        $this->assertDatabaseCount(
            'track_splits',
            2
        );

        $this->assertDatabaseHas(
            'track_splits',
            [
                'track_id' => $track->id,
                'contributor_id' => $first->id,
                'split_type' => 'master',
                'percentage' => 60,
                'status' => 'active',
            ]
        );

        $this->assertDatabaseHas(
            'track_splits',
            [
                'track_id' => $track->id,
                'contributor_id' => $second->id,
                'split_type' => 'master',
                'percentage' => 40,
                'status' => 'active',
            ]
        );
    }

    public function test_split_total_below_100_is_rejected(): void
    {
        [
            $user,
            ,
            ,
            ,
            $track,
            $first,
            $second,
        ] = $this->createContext();

        $response = $this
            ->actingAs($user)
            ->putJson(
                $this->splitUrl($track),
                [
                    'split_type' => 'master',
                    'splits' => [
                        [
                            'contributor_id' => $first->id,
                            'recipient_name' => $first->name,
                            'percentage' => 50,
                        ],
                        [
                            'contributor_id' => $second->id,
                            'recipient_name' => $second->name,
                            'percentage' => 40,
                        ],
                    ],
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'splits'
            );

        $this->assertDatabaseCount(
            'track_splits',
            0
        );
    }

    public function test_split_total_above_100_is_rejected(): void
    {
        [
            $user,
            ,
            ,
            ,
            $track,
            $first,
            $second,
        ] = $this->createContext();

        $response = $this
            ->actingAs($user)
            ->putJson(
                $this->splitUrl($track),
                [
                    'split_type' => 'publishing',
                    'splits' => [
                        [
                            'contributor_id' => $first->id,
                            'recipient_name' => $first->name,
                            'percentage' => 70,
                        ],
                        [
                            'contributor_id' => $second->id,
                            'recipient_name' => $second->name,
                            'percentage' => 40,
                        ],
                    ],
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'splits'
            );

        $this->assertDatabaseCount(
            'track_splits',
            0
        );
    }

    public function test_empty_splits_are_rejected(): void
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
            ->putJson(
                $this->splitUrl($track),
                [
                    'split_type' => 'master',
                    'splits' => [],
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'splits'
            );
    }

    public function test_invalid_split_type_is_rejected(): void
    {
        [
            $user,
            ,
            ,
            ,
            $track,
            $first,
            $second,
        ] = $this->createContext();

        $response = $this
            ->actingAs($user)
            ->putJson(
                $this->splitUrl($track),
                [
                    'split_type' => 'invalid_type',
                    'splits' => $this->validSplits(
                        $first,
                        $second
                    ),
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'split_type'
            );
    }

    public function test_existing_split_type_is_replaced(): void
    {
        [
            $user,
            ,
            ,
            ,
            $track,
            $first,
            $second,
        ] = $this->createContext();

        $this
            ->actingAs($user)
            ->putJson(
                $this->splitUrl($track),
                [
                    'split_type' => 'performance',
                    'splits' => $this->validSplits(
                        $first,
                        $second
                    ),
                ]
            )
            ->assertOk();

        $this
            ->actingAs($user)
            ->putJson(
                $this->splitUrl($track),
                [
                    'split_type' => 'performance',
                    'splits' => [
                        [
                            'contributor_id' => $first->id,
                            'recipient_name' => $first->name,
                            'percentage' => 100,
                        ],
                    ],
                ]
            )
            ->assertOk();

        $splits = TrackSplit::query()
            ->where('track_id', $track->id)
            ->where(
                'split_type',
                'performance'
            )
            ->get();

        $this->assertCount(1, $splits);

        $this->assertSame(
            $first->id,
            $splits->first()->contributor_id
        );

        $this->assertSame(
            '100.00',
            $splits->first()->percentage
        );
    }

    public function test_other_artist_cannot_manage_track_splits(): void
    {
        [
            ,
            ,
            ,
            ,
            $track,
            $first,
            $second,
        ] = $this->createContext();

        [
            $otherUser,
        ] = $this->createContext();

        $response = $this
            ->actingAs($otherUser)
            ->putJson(
                $this->splitUrl($track),
                [
                    'split_type' => 'master',
                    'splits' => $this->validSplits(
                        $first,
                        $second
                    ),
                ]
            );

        $response->assertForbidden();

        $this->assertDatabaseCount(
            'track_splits',
            0
        );
    }
}
