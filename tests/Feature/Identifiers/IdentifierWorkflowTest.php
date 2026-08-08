<?php

namespace Tests\Feature\Identifiers;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IdentifierWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createContext(
        int $trackCount = 2
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

        $release = Release::factory()->create([
            'artist_id' => $artist->id,
            'label_id' => $label->id,
            'catalog_number' =>
                'MXT-ID-'.uniqid(),
            'title' =>
                'Identifier Workflow Release',
            'primary_artist_name' =>
                $artist->stage_name
                ?: $artist->legal_name,
            'status' => 'approved',
            'upc' => null,
            'upc_is_auto_generated' => false,
            'created_by' => $artistUser->id,
            'updated_by' => $artistUser->id,
        ]);

        $tracks = collect();

        for ($index = 1; $index <= $trackCount; $index++) {
            $tracks->push(
                Track::factory()
                    ->withAudio()
                    ->create([
                        'release_id' => $release->id,
                        'track_number' => $index,
                        'title' =>
                            "Identifier Track {$index}",
                        'primary_artist_name' =>
                            $release
                                ->primary_artist_name,
                        'isrc' => null,
                        'isrc_is_auto_generated' =>
                            false,
                        'created_by' =>
                            $artistUser->id,
                        'updated_by' =>
                            $artistUser->id,
                    ])
            );
        }

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
            $tracks,
            $admin,
        ];
    }

    private function assignIsrcUrl(
        Track $track
    ): string {
        return route(
            'v2.admin.identifiers.isrc.assign',
            $track
        );
    }

    private function generateIsrcUrl(
        Track $track
    ): string {
        return route(
            'v2.admin.identifiers.isrc.generate',
            $track
        );
    }

    private function assignUpcUrl(
        Release $release
    ): string {
        return route(
            'v2.admin.identifiers.upc.assign',
            $release
        );
    }

    private function generateUpcUrl(
        Release $release
    ): string {
        return route(
            'v2.admin.identifiers.upc.generate',
            $release
        );
    }

    private function bulkIsrcUrl(): string
    {
        return route(
            'v2.admin.identifiers.isrc.bulk'
        );
    }

    private function bulkUpcUrl(): string
    {
        return route(
            'v2.admin.identifiers.upc.bulk'
        );
    }

    private function validUpc(
        string $body = '89000000001'
    ): string {
        $sum = 0;

        foreach (
            str_split($body)
            as $index => $digit
        ) {
            $position = $index + 1;

            $sum +=
                (int) $digit
                * (
                    $position % 2 === 1
                        ? 3
                        : 1
                );
        }

        $checkDigit =
            (10 - ($sum % 10)) % 10;

        return $body.$checkDigit;
    }

    public function test_super_admin_can_assign_manual_isrc(): void
    {
        [
            ,
            ,
            ,
            ,
            $tracks,
            $admin,
        ] = $this->createContext();

        $track = $tracks->first();

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->assignIsrcUrl($track),
                [
                    'isrc' =>
                        'IN-MXT-26-00001',
                    'notes' =>
                        'Manual ISRC assignment.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'ISRC assigned successfully.'
            )
            ->assertJsonPath(
                'track.isrc',
                'IN-MXT-26-00001'
            );

        $track->refresh();

        $this->assertSame(
            'IN-MXT-26-00001',
            $track->isrc
        );

        $this->assertFalse(
            (bool) $track
                ->isrc_is_auto_generated
        );

        $this->assertNotNull(
            $track->isrc_assigned_at
        );

        $this->assertSame(
            $admin->id,
            $track->isrc_assigned_by
        );

        $this->assertDatabaseHas(
            'isrc_codes',
            [
                'code' =>
                    'IN-MXT-26-00001',
                'track_id' => $track->id,
                'status' => 'assigned',
            ]
        );
    }

    public function test_invalid_manual_isrc_is_rejected(): void
    {
        [
            ,
            ,
            ,
            ,
            $tracks,
            $admin,
        ] = $this->createContext();

        $track = $tracks->first();

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->assignIsrcUrl($track),
                [
                    'isrc' => 'INVALID-ISRC',
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'isrc'
            );

        $this->assertNull(
            $track->fresh()->isrc
        );
    }

    public function test_duplicate_isrc_is_rejected(): void
    {
        [
            ,
            ,
            ,
            ,
            $tracks,
            $admin,
        ] = $this->createContext();

        $first = $tracks->get(0);
        $second = $tracks->get(1);

        $this
            ->actingAs($admin)
            ->postJson(
                $this->assignIsrcUrl($first),
                [
                    'isrc' =>
                        'IN-MXT-26-00002',
                ]
            )
            ->assertOk();

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->assignIsrcUrl($second),
                [
                    'isrc' =>
                        'IN-MXT-26-00002',
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'isrc'
            );

        $this->assertNull(
            $second->fresh()->isrc
        );
    }

    public function test_super_admin_can_generate_isrc(): void
    {
        [
            ,
            ,
            ,
            ,
            $tracks,
            $admin,
        ] = $this->createContext();

        $track = $tracks->first();

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->generateIsrcUrl($track),
                [
                    'country_code' => 'IN',
                    'registrant_code' => 'MXT',
                    'reference_year' => 2026,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'ISRC generated successfully.'
            );

        $track->refresh();

        $this->assertMatchesRegularExpression(
            '/^IN-MXT-26-[0-9]{5}$/',
            (string) $track->isrc
        );

        $this->assertTrue(
            (bool) $track
                ->isrc_is_auto_generated
        );

        $this->assertSame(
            $admin->id,
            $track->isrc_assigned_by
        );
    }

    public function test_generated_isrc_sequence_increments(): void
    {
        [
            ,
            ,
            ,
            ,
            $tracks,
            $admin,
        ] = $this->createContext();

        foreach ($tracks as $track) {
            $this
                ->actingAs($admin)
                ->postJson(
                    $this->generateIsrcUrl(
                        $track
                    ),
                    [
                        'country_code' =>
                            'IN',
                        'registrant_code' =>
                            'MXT',
                        'reference_year' =>
                            2026,
                    ]
                )
                ->assertOk();
        }

        $this->assertSame(
            'IN-MXT-26-00001',
            $tracks
                ->get(0)
                ->fresh()
                ->isrc
        );

        $this->assertSame(
            'IN-MXT-26-00002',
            $tracks
                ->get(1)
                ->fresh()
                ->isrc
        );
    }

    public function test_invalid_isrc_country_code_is_rejected(): void
    {
        [
            ,
            ,
            ,
            ,
            $tracks,
            $admin,
        ] = $this->createContext();

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->generateIsrcUrl(
                    $tracks->first()
                ),
                [
                    'country_code' => '1N',
                    'registrant_code' => 'MXT',
                    'reference_year' => 2026,
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'country_code'
            );
    }

    public function test_invalid_isrc_registrant_code_is_rejected(): void
    {
        [
            ,
            ,
            ,
            ,
            $tracks,
            $admin,
        ] = $this->createContext();

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->generateIsrcUrl(
                    $tracks->first()
                ),
                [
                    'country_code' => 'IN',
                    'registrant_code' => '@@@',
                    'reference_year' => 2026,
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'registrant_code'
            );
    }

    public function test_track_with_existing_isrc_cannot_generate_another(): void
    {
        [
            ,
            ,
            ,
            ,
            $tracks,
            $admin,
        ] = $this->createContext();

        $track = $tracks->first();

        $this
            ->actingAs($admin)
            ->postJson(
                $this->generateIsrcUrl($track)
            )
            ->assertOk();

        $originalIsrc =
            $track->fresh()->isrc;

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->generateIsrcUrl($track)
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'isrc'
            );

        $this->assertSame(
            $originalIsrc,
            $track->fresh()->isrc
        );
    }

    public function test_bulk_isrc_generation_assigns_missing_tracks(): void
    {
        [
            ,
            ,
            ,
            ,
            $tracks,
            $admin,
        ] = $this->createContext(3);

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->bulkIsrcUrl(),
                [
                    'track_ids' =>
                        $tracks
                            ->pluck('id')
                            ->all(),

                    'country_code' => 'IN',
                    'registrant_code' => 'MXT',
                    'reference_year' => 2026,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                '3 ISRC codes generated.'
            );

        foreach ($tracks as $track) {
            $this->assertNotNull(
                $track->fresh()->isrc
            );
        }

        $this->assertSame(
            3,
            $response->json('tracks')
                ? count(
                    $response->json('tracks')
                )
                : 0
        );
    }

    public function test_super_admin_can_assign_manual_upc(): void
    {
        [
            ,
            ,
            ,
            $release,
            ,
            $admin,
        ] = $this->createContext();

        $upc = $this->validUpc();

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->assignUpcUrl($release),
                [
                    'upc' => $upc,
                    'notes' =>
                        'Manual UPC assignment.',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'UPC assigned successfully.'
            )
            ->assertJsonPath(
                'release.upc',
                $upc
            );

        $release->refresh();

        $this->assertSame(
            $upc,
            $release->upc
        );

        $this->assertNotNull(
            $release->upc_assigned_at
        );

        $this->assertSame(
            $admin->id,
            $release->upc_assigned_by
        );

        $this->assertDatabaseHas(
            'upc_codes',
            [
                'code' => $upc,
                'release_id' =>
                    $release->id,
                'status' => 'assigned',
            ]
        );
    }

    public function test_manual_upc_does_not_require_valid_checksum(): void
    {
        [
            ,
            ,
            ,
            $release,
            ,
            $admin,
        ] = $this->createContext();

        $upc = '890000000019';

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->assignUpcUrl($release),
                [
                    'upc' => $upc,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'UPC assigned successfully.'
            )
            ->assertJsonPath(
                'release.upc',
                $upc
            );

        $release->refresh();

        $this->assertSame(
            $upc,
            $release->upc
        );

        $this->assertDatabaseHas(
            'upc_codes',
            [
                'code' => $upc,
                'release_id' => $release->id,
                'status' => 'assigned',
            ]
        );
    }

    public function test_duplicate_upc_is_rejected(): void
    {
        [
            ,
            ,
            ,
            $firstRelease,
            ,
            $admin,
        ] = $this->createContext();

        [
            ,
            ,
            ,
            $secondRelease,
        ] = $this->createContext();

        $upc = $this->validUpc(
            '89000000002'
        );

        $this
            ->actingAs($admin)
            ->postJson(
                $this->assignUpcUrl(
                    $firstRelease
                ),
                [
                    'upc' => $upc,
                ]
            )
            ->assertOk();

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->assignUpcUrl(
                    $secondRelease
                ),
                [
                    'upc' => $upc,
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'upc'
            );

        $this->assertNull(
            $secondRelease->fresh()->upc
        );
    }

    public function test_super_admin_can_generate_upc(): void
    {
        [
            ,
            ,
            ,
            $release,
            ,
            $admin,
        ] = $this->createContext();

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->generateUpcUrl(
                    $release
                ),
                [
                    'prefix' => '890000',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                'UPC generated successfully.'
            );

        $release->refresh();

        $this->assertMatchesRegularExpression(
            '/^[0-9]{12}$/',
            (string) $release->upc
        );

        $this->assertSame(
            $admin->id,
            $release->upc_assigned_by
        );
    }

    public function test_invalid_upc_prefix_is_rejected(): void
    {
        [
            ,
            ,
            ,
            $release,
            ,
            $admin,
        ] = $this->createContext();

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->generateUpcUrl(
                    $release
                ),
                [
                    'prefix' => '12345',
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'prefix'
            );
    }

    public function test_release_with_existing_upc_cannot_generate_another(): void
    {
        [
            ,
            ,
            ,
            $release,
            ,
            $admin,
        ] = $this->createContext();

        $this
            ->actingAs($admin)
            ->postJson(
                $this->generateUpcUrl(
                    $release
                )
            )
            ->assertOk();

        $originalUpc =
            $release->fresh()->upc;

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->generateUpcUrl(
                    $release
                )
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'upc'
            );

        $this->assertSame(
            $originalUpc,
            $release->fresh()->upc
        );
    }

    public function test_bulk_upc_generation_assigns_missing_releases(): void
    {
        [
            ,
            ,
            ,
            $firstRelease,
            ,
            $admin,
        ] = $this->createContext();

        [
            ,
            ,
            ,
            $secondRelease,
        ] = $this->createContext();

        $response = $this
            ->actingAs($admin)
            ->postJson(
                $this->bulkUpcUrl(),
                [
                    'release_ids' => [
                        $firstRelease->id,
                        $secondRelease->id,
                    ],
                    'prefix' => '890000',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'message',
                '2 UPC codes generated.'
            );

        $this->assertNotNull(
            $firstRelease->fresh()->upc
        );

        $this->assertNotNull(
            $secondRelease->fresh()->upc
        );

        $this->assertNotSame(
            $firstRelease->fresh()->upc,
            $secondRelease->fresh()->upc
        );
    }

    public function test_artist_cannot_assign_isrc(): void
    {
        [
            $artistUser,
            ,
            ,
            ,
            $tracks,
        ] = $this->createContext();

        $response = $this
            ->actingAs($artistUser)
            ->postJson(
                $this->assignIsrcUrl(
                    $tracks->first()
                ),
                [
                    'isrc' =>
                        'IN-MXT-26-00009',
                ]
            );

        $response->assertForbidden();

        $this->assertNull(
            $tracks
                ->first()
                ->fresh()
                ->isrc
        );
    }

    public function test_artist_cannot_generate_upc(): void
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
                $this->generateUpcUrl(
                    $release
                )
            );

        $response->assertForbidden();

        $this->assertNull(
            $release->fresh()->upc
        );
    }

    public function test_identifier_assignment_logs_are_created_when_table_exists(): void
    {
        if (
            !Schema::hasTable(
                'identifier_assignment_logs'
            )
        ) {
            $this->markTestSkipped(
                'identifier_assignment_logs table is unavailable.'
            );
        }

        [
            ,
            ,
            ,
            $release,
            $tracks,
            $admin,
        ] = $this->createContext();

        $track = $tracks->first();

        $this
            ->actingAs($admin)
            ->postJson(
                $this->generateIsrcUrl($track)
            )
            ->assertOk();

        $this
            ->actingAs($admin)
            ->postJson(
                $this->generateUpcUrl(
                    $release
                )
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'identifier_assignment_logs',
            [
                'identifier_type' => 'isrc',
                'track_id' => $track->id,
                'action' => 'auto_generated',
                'performed_by' => $admin->id,
            ]
        );

        $this->assertDatabaseHas(
            'identifier_assignment_logs',
            [
                'identifier_type' => 'upc',
                'release_id' =>
                    $release->id,
                'action' => 'auto_generated',
                'performed_by' => $admin->id,
            ]
        );
    }
}
