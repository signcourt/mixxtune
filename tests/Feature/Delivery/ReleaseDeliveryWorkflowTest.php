<?php

namespace Tests\Feature\Delivery;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\DistributionStore;
use App\Models\ReleaseStoreDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseDeliveryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createContext(
        string $releaseStatus = 'approved',
        int $storeCount = 2
    ): array {
        $artistUser = User::factory()->create([
            'role' => 'artist',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $label = Label::factory()->create([
            'created_by' => $artistUser->id,
        ]);

        $artist = Artist::factory()->create([
            'user_id' => $artistUser->id,
            'label_id' => $label->id,
            'created_by' => $artistUser->id,
        ]);

        $stores = collect();

        for ($index = 1; $index <= $storeCount; $index++) {
            $stores->push(
                DistributionStore::query()->create([
                    'name' =>
                        "Delivery Store {$index} ".uniqid(),

                    'slug' =>
                        "delivery-store-{$index}-".uniqid(),

                    'is_active' => true,
                ])
            );
        }

        $release = Release::factory()->create([
            'artist_id' => $artist->id,
            'label_id' => $label->id,

            'catalog_number' =>
                'MXT-DEL-'.uniqid(),

            'title' =>
                'Delivery Workflow Release',

            'status' => $releaseStatus,

            'stores' =>
                $stores
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all(),

            'worldwide' => true,
            'territories' => [],

            'submitted_at' =>
                now()->subHours(2),

            'approved_at' =>
                $releaseStatus === 'approved'
                    ? now()->subHour()
                    : null,

            'wizard_step' => 5,
            'completion_percentage' => 100,

            'created_by' => $artistUser->id,
            'updated_by' => $artistUser->id,
        ]);

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        return [
            $artistUser,
            $label,
            $artist,
            $release,
            $stores,
            $admin,
        ];
    }

    private function initialiseUrl(
        Release $release
    ): string {
        return route(
            'v2.admin.delivery.initialise',
            $release
        );
    }

    private function updateUrl(
        ReleaseStoreDelivery $delivery
    ): string {
        return route(
            'v2.admin.delivery.update',
            $delivery
        );
    }

    private function bulkUrl(
        Release $release
    ): string {
        return route(
            'v2.admin.delivery.bulk-update',
            $release
        );
    }

    private function initialise(
        User $admin,
        Release $release
    ): void {
        $this
            ->actingAs($admin)
            ->postJson(
                $this->initialiseUrl($release)
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Delivery records initialised.'
            );
    }

    private function deliveries(
        Release $release
    ) {
        return ReleaseStoreDelivery::query()
            ->where('release_id', $release->id)
            ->orderBy('id')
            ->get();
    }

    private function transition(
        User $admin,
        ReleaseStoreDelivery $delivery,
        string $status,
        array $extra = []
    ): ReleaseStoreDelivery {
        $this
            ->actingAs($admin)
            ->patchJson(
                $this->updateUrl($delivery),
                [
                    'status' => $status,
                    ...$extra,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Store delivery status updated.'
            )
            ->assertJsonPath(
                'delivery.status',
                $status
            );

        return $delivery->fresh();
    }

    public function test_super_admin_can_initialise_delivery_records(): void
    {
        [
            ,
            ,
            ,
            $release,
            $stores,
            $admin,
        ] = $this->createContext();

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->initialiseUrl($release)
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Delivery records initialised.'
            )
            ->assertJsonPath(
                'summary.total',
                $stores->count()
            )
            ->assertJsonPath(
                'summary.pending',
                $stores->count()
            );

        $this->assertDatabaseCount(
            'release_store_deliveries',
            $stores->count()
        );

        foreach ($stores as $store) {
            $this->assertDatabaseHas(
                'release_store_deliveries',
                [
                    'release_id' => $release->id,
                    'distribution_store_id' =>
                        $store->id,
                    'status' => 'pending',
                ]
            );
        }
    }

    public function test_initialise_is_idempotent(): void
    {
        [
            ,
            ,
            ,
            $release,
            $stores,
            $admin,
        ] = $this->createContext();

        $this->initialise($admin, $release);
        $this->initialise($admin, $release);

        $this->assertDatabaseCount(
            'release_store_deliveries',
            $stores->count()
        );
    }

    public function test_draft_release_cannot_initialise_delivery(): void
    {
        [
            ,
            ,
            ,
            $release,
            ,
            $admin,
        ] = $this->createContext('draft');

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->initialiseUrl($release)
            );

        $response->assertStatus(422);

        $this->assertDatabaseCount(
            'release_store_deliveries',
            0
        );
    }

    public function test_pending_delivery_can_move_to_processing(): void
    {
        [
            ,
            ,
            ,
            $release,
            ,
            $admin,
        ] = $this->createContext();

        $this->initialise($admin, $release);

        $delivery = $this
            ->deliveries($release)
            ->first();

        $delivery = $this->transition(
            $admin,
            $delivery,
            'processing',
            [
                'delivery_note' =>
                    'DSP upload started.',
            ]
        );

        $this->assertSame(
            'processing',
            $delivery->status
        );

        $this->assertSame(
            'DSP upload started.',
            $delivery->delivery_note
        );

        $this->assertSame(
            'processing',
            $release->fresh()->status
        );
    }

    public function test_processing_delivery_can_move_to_delivered(): void
    {
        [
            ,
            ,
            ,
            $release,
            ,
            $admin,
        ] = $this->createContext();

        $this->initialise($admin, $release);

        $delivery = $this
            ->deliveries($release)
            ->first();

        $delivery = $this->transition(
            $admin,
            $delivery,
            'processing'
        );

        $delivery = $this->transition(
            $admin,
            $delivery,
            'delivered',
            [
                'external_reference' =>
                    'DSP-'.uniqid(),
            ]
        );

        $this->assertSame(
            'delivered',
            $delivery->status
        );

        $this->assertNotNull(
            $delivery->delivered_at
        );
    }

    public function test_delivered_delivery_can_move_to_live(): void
    {
        [
            ,
            ,
            ,
            $release,
            ,
            $admin,
        ] = $this->createContext(
            'approved',
            1
        );

        $this->initialise($admin, $release);

        $delivery = $this
            ->deliveries($release)
            ->first();

        $delivery = $this->transition(
            $admin,
            $delivery,
            'processing'
        );

        $delivery = $this->transition(
            $admin,
            $delivery,
            'delivered'
        );

        $delivery = $this->transition(
            $admin,
            $delivery,
            'live'
        );

        $this->assertNotNull(
            $delivery->live_at
        );

        $this->assertSame(
            'live',
            $release->fresh()->status
        );
    }

    public function test_delivery_can_fail_and_retry(): void
    {
        [
            ,
            ,
            ,
            $release,
            ,
            $admin,
        ] = $this->createContext();

        $this->initialise($admin, $release);

        $delivery = $this
            ->deliveries($release)
            ->first();

        $delivery = $this->transition(
            $admin,
            $delivery,
            'failed',
            [
                'error_message' =>
                    'DSP connection timeout.',
            ]
        );

        $this->assertSame(
            'failed',
            $delivery->status
        );

        $this->assertNotNull(
            $delivery->failed_at
        );

        $this->assertSame(
            'DSP connection timeout.',
            $delivery->error_message
        );

        $delivery = $this->transition(
            $admin,
            $delivery,
            'processing',
            [
                'delivery_note' =>
                    'Retry started.',
            ]
        );

        $this->assertNull(
            $delivery->failed_at
        );

        $this->assertSame(
            'processing',
            $delivery->status
        );
    }

    public function test_live_delivery_can_be_taken_down(): void
    {
        [
            ,
            ,
            ,
            $release,
            ,
            $admin,
        ] = $this->createContext(
            'approved',
            1
        );

        $this->initialise($admin, $release);

        $delivery = $this
            ->deliveries($release)
            ->first();

        foreach (
            [
                'processing',
                'delivered',
                'live',
            ] as $status
        ) {
            $delivery = $this->transition(
                $admin,
                $delivery,
                $status
            );
        }

        $delivery = $this->transition(
            $admin,
            $delivery,
            'takedown_requested',
            [
                'delivery_note' =>
                    'Rights owner requested takedown.',
            ]
        );

        $this->assertSame(
            'takedown_requested',
            $delivery->status
        );

        $delivery = $this->transition(
            $admin,
            $delivery,
            'taken_down'
        );

        $this->assertSame(
            'taken_down',
            $delivery->status
        );

        $this->assertNotNull(
            $delivery->taken_down_at
        );

        $this->assertSame(
            'taken_down',
            $release->fresh()->status
        );
    }

    public function test_invalid_delivery_transition_is_blocked(): void
    {
        [
            ,
            ,
            ,
            $release,
            ,
            $admin,
        ] = $this->createContext();

        $this->initialise($admin, $release);

        $delivery = $this
            ->deliveries($release)
            ->first();

        $response = $this
            ->actingAs($admin)
            ->patchJson(
                $this->updateUrl($delivery),
                [
                    'status' => 'live',
                ]
            );

        $response->assertStatus(422);

        $this->assertSame(
            'pending',
            $delivery->fresh()->status
        );
    }

    public function test_bulk_update_can_move_selected_deliveries(): void
    {
        [
            ,
            ,
            ,
            $release,
            $stores,
            $admin,
        ] = $this->createContext();

        $this->initialise($admin, $release);

        $deliveries = $this->deliveries(
            $release
        );

        $response = $this
            ->actingAs($admin)
            ->patchJson(
                $this->bulkUrl($release),
                [
                    'delivery_ids' =>
                        $deliveries
                            ->pluck('id')
                            ->all(),

                    'status' => 'processing',

                    'delivery_note' =>
                        'Bulk DSP processing started.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Selected store deliveries updated.'
            )
            ->assertJsonPath(
                'summary.processing',
                $stores->count()
            );

        foreach ($deliveries as $delivery) {
            $this->assertDatabaseHas(
                'release_store_deliveries',
                [
                    'id' => $delivery->id,
                    'status' => 'processing',
                ]
            );
        }
    }

    public function test_bulk_update_rejects_delivery_from_another_release(): void
    {
        [
            ,
            ,
            ,
            $release,
            ,
            $admin,
        ] = $this->createContext();

        [
            ,
            ,
            ,
            $otherRelease,
        ] = $this->createContext();

        $this->initialise(
            $admin,
            $release
        );

        $this->initialise(
            $admin,
            $otherRelease
        );

        $otherDelivery = $this
            ->deliveries($otherRelease)
            ->first();

        $response = $this
            ->actingAs($admin)
            ->patchJson(
                $this->bulkUrl($release),
                [
                    'delivery_ids' => [
                        $otherDelivery->id,
                    ],
                    'status' => 'processing',
                ]
            );

        $response->assertStatus(422);

        $this->assertSame(
            'pending',
            $otherDelivery->fresh()->status
        );
    }

    public function test_artist_cannot_initialise_delivery(): void
    {
        [
            $artistUser,
            ,
            ,
            $release,
        ] = $this->createContext();

        $response = $this
            ->actingAs($artistUser)
            ->postJson(
                $this->initialiseUrl($release)
            );

        $response->assertForbidden();

        $this->assertDatabaseCount(
            'release_store_deliveries',
            0
        );
    }

    public function test_artist_cannot_update_delivery_status(): void
    {
        [
            $artistUser,
            ,
            ,
            $release,
            ,
            $admin,
        ] = $this->createContext();

        $this->initialise($admin, $release);

        $delivery = $this
            ->deliveries($release)
            ->first();

        $response = $this
            ->actingAs($artistUser)
            ->patchJson(
                $this->updateUrl($delivery),
                [
                    'status' => 'processing',
                ]
            );

        $response->assertForbidden();

        $this->assertSame(
            'pending',
            $delivery->fresh()->status
        );
    }
}
