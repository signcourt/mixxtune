<?php

namespace Tests\Feature\Releases;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\DistributionStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseDistributionWorkflowTest extends TestCase
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
            'catalog_number' =>
                'MXT-DIST-'.uniqid(),
            'release_type' => 'single',
            'title' => 'Distribution Test Release',
            'primary_artist_name' =>
                $artist->stage_name
                ?: $artist->legal_name,
            'status' => 'draft',
            'wizard_step' => 4,
            'completion_percentage' => 80,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return [
            $user,
            $label,
            $artist,
            $release,
        ];
    }

    private function createStore(
        string $name,
        bool $active = true
    ): DistributionStore {
        return DistributionStore::query()->create([
            'name' => $name,
            'slug' => strtolower(
                str_replace(' ', '-', $name)
            ).'-'.uniqid(),
            'is_active' => $active,
        ]);
    }

    private function distributionUrl(
        Release $release
    ): string {
        return route(
            'v2.releases.distribution.update',
            $release
        );
    }

    public function test_artist_can_save_worldwide_distribution(): void
    {
        [
            $user,
            ,
            ,
            $release,
        ] = $this->createContext();

        $spotify = $this->createStore(
            'Spotify'
        );

        $apple = $this->createStore(
            'Apple Music'
        );

        $response = $this
            ->actingAs($user)
            ->patchJson(
                $this->distributionUrl($release),
                [
                    'stores' => [
                        $spotify->id,
                        $apple->id,
                    ],
                    'worldwide' => true,
                    'territories' => [],
                    'release_timezone' =>
                        'Asia/Kolkata',
                    'pre_order' => false,
                ]
            );

        $response->assertRedirect();

        $release->refresh();

        $this->assertSame(
            [
                $spotify->id,
                $apple->id,
            ],
            $release->stores
        );

        $this->assertTrue(
            $release->worldwide
        );

        $this->assertSame(
            [],
            $release->territories
        );

        $this->assertSame(
            'Asia/Kolkata',
            $release->release_timezone
        );

        $this->assertFalse(
            $release->pre_order
        );
    }

    public function test_artist_can_save_selected_territories(): void
    {
        [
            $user,
            ,
            ,
            $release,
        ] = $this->createContext();

        $youtube = $this->createStore(
            'YouTube Music'
        );

        $response = $this
            ->actingAs($user)
            ->patchJson(
                $this->distributionUrl($release),
                [
                    'stores' => [
                        $youtube->id,
                    ],
                    'worldwide' => false,
                    'territories' => [
                        'IN',
                        'US',
                        'GB',
                    ],
                    'release_timezone' =>
                        'Asia/Kolkata',
                    'pre_order' => true,
                ]
            );

        $response->assertRedirect();

        $release->refresh();

        $this->assertFalse(
            $release->worldwide
        );

        $this->assertSame(
            [
                'IN',
                'US',
                'GB',
            ],
            $release->territories
        );

        $this->assertTrue(
            $release->pre_order
        );
    }

    public function test_at_least_one_store_is_required(): void
    {
        [
            $user,
            ,
            ,
            $release,
        ] = $this->createContext();

        $response = $this
            ->actingAs($user)
            ->patchJson(
                $this->distributionUrl($release),
                [
                    'stores' => [],
                    'worldwide' => true,
                    'territories' => [],
                    'release_timezone' =>
                        'Asia/Kolkata',
                    'pre_order' => false,
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'stores'
            );
    }

    public function test_territory_is_required_when_worldwide_is_disabled(): void
    {
        [
            $user,
            ,
            ,
            $release,
        ] = $this->createContext();

        $store = $this->createStore(
            'Amazon Music'
        );

        $response = $this
            ->actingAs($user)
            ->patchJson(
                $this->distributionUrl($release),
                [
                    'stores' => [
                        $store->id,
                    ],
                    'worldwide' => false,
                    'territories' => [],
                    'release_timezone' =>
                        'Asia/Kolkata',
                    'pre_order' => false,
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'territories'
            );
    }

    public function test_invalid_territory_code_is_rejected(): void
    {
        [
            $user,
            ,
            ,
            $release,
        ] = $this->createContext();

        $store = $this->createStore(
            'Deezer'
        );

        $response = $this
            ->actingAs($user)
            ->patchJson(
                $this->distributionUrl($release),
                [
                    'stores' => [
                        $store->id,
                    ],
                    'worldwide' => false,
                    'territories' => [
                        'IND',
                    ],
                    'release_timezone' =>
                        'Asia/Kolkata',
                    'pre_order' => false,
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'territories.0'
            );
    }

    public function test_inactive_store_is_rejected(): void
    {
        [
            $user,
            ,
            ,
            $release,
        ] = $this->createContext();

        $inactiveStore = $this->createStore(
            'Inactive Store',
            false
        );

        $response = $this
            ->actingAs($user)
            ->patchJson(
                $this->distributionUrl($release),
                [
                    'stores' => [
                        $inactiveStore->id,
                    ],
                    'worldwide' => true,
                    'territories' => [],
                    'release_timezone' =>
                        'Asia/Kolkata',
                    'pre_order' => false,
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'stores'
            );
    }

    public function test_other_artist_cannot_manage_distribution(): void
    {
        [
            ,
            ,
            ,
            $release,
        ] = $this->createContext();

        [
            $otherUser,
        ] = $this->createContext();

        $store = $this->createStore(
            'Tidal'
        );

        $response = $this
            ->actingAs($otherUser)
            ->patchJson(
                $this->distributionUrl($release),
                [
                    'stores' => [
                        $store->id,
                    ],
                    'worldwide' => true,
                    'territories' => [],
                    'release_timezone' =>
                        'Asia/Kolkata',
                    'pre_order' => false,
                ]
            );

        $response->assertForbidden();
    }
}
