<?php

namespace Tests\Feature\Royalties;

use App\Models\User;
use App\Models\Finance\RightsRoyaltyImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RightsRoyaltyHttpSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }

    public function test_non_super_admin_roles_cannot_access_rights_inspect(): void
    {
        foreach (['admin', 'label', 'artist'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)
                ->postJson(
                    route('v2.admin.royalties.rights.inspect'),
                    []
                )
                ->assertForbidden();
        }
    }

    public function test_non_super_admin_roles_cannot_access_rights_store(): void
    {
        foreach (['admin', 'label', 'artist'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)
                ->postJson(
                    route('v2.admin.royalties.rights.store'),
                    []
                )
                ->assertForbidden();
        }
    }

    public function test_super_admin_reaches_inspect_controller(): void
    {
        $super = $this->user('super_admin');

        /*
         * Empty payload should fail controller validation with 422.
         * That proves super_admin passed auth/verified/role middleware.
         */
        $this->actingAs($super)
            ->postJson(
                route('v2.admin.royalties.rights.inspect'),
                []
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors('report_file');
    }

    public function test_super_admin_reaches_store_controller(): void
    {
        $super = $this->user('super_admin');

        /*
         * Empty payload should fail controller validation with 422,
         * proving the request reached the Rights controller.
         */
        $this->actingAs($super)
            ->postJson(
                route('v2.admin.royalties.rights.store'),
                []
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'source',
                'statement_month',
                'currency',
                'report_file',
                'mapping',
            ]);
    }

    public function test_non_super_admin_roles_cannot_access_rights_show_approve_publish(): void
    {
        $owner = $this->user('super_admin');

        $import = RightsRoyaltyImport::create([
            'public_id' => (string) Str::ulid(),
            'source' => 'OTHER',
            'statement_month' => '2026-09',
            'currency' => 'INR',
            'original_filename' => 'security-test.csv',
            'uploaded_by' => $owner->id,
            'total_rows' => 0,
            'mapped_rows' => 0,
            'unmapped_rows' => 0,
            'gross_amount' => 0,
            'mapped_amount' => 0,
            'unmapped_amount' => 0,
            'status' => 'review_required',
        ]);

        foreach (['admin', 'label', 'artist'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)
                ->getJson(
                    route(
                        'v2.admin.royalties.rights.show',
                        ['import' => $import->public_id]
                    )
                )
                ->assertForbidden();

            $this->actingAs($user)
                ->postJson(
                    route(
                        'v2.admin.royalties.rights.approve',
                        ['import' => $import->public_id]
                    )
                )
                ->assertForbidden();

            $this->actingAs($user)
                ->postJson(
                    route(
                        'v2.admin.royalties.rights.publish',
                        ['import' => $import->public_id]
                    )
                )
                ->assertForbidden();
        }
    }

    public function test_super_admin_passes_role_middleware_for_show_approve_publish(): void
    {
        $super = $this->user('super_admin');

        $import = RightsRoyaltyImport::create([
            'public_id' => (string) Str::ulid(),
            'source' => 'OTHER',
            'statement_month' => '2026-09',
            'currency' => 'INR',
            'original_filename' => 'security-test-super.csv',
            'uploaded_by' => $super->id,
            'total_rows' => 0,
            'mapped_rows' => 0,
            'unmapped_rows' => 0,
            'gross_amount' => 0,
            'mapped_amount' => 0,
            'unmapped_amount' => 0,
            'status' => 'review_required',
        ]);

        /*
         * SHOW should reach the controller successfully.
         */
        $this->actingAs($super)
            ->getJson(
                route(
                    'v2.admin.royalties.rights.show',
                    ['import' => $import->public_id]
                )
            )
            ->assertOk();

        /*
         * APPROVE reaches the publishing service.
         * Empty import is rejected by business validation with 422,
         * proving authorization middleware was passed.
         */
        $this->actingAs($super)
            ->postJson(
                route(
                    'v2.admin.royalties.rights.approve',
                    ['import' => $import->public_id]
                )
            )
            ->assertStatus(422);

        /*
         * PUBLISH also reaches the publishing service.
         * Import is not approved, therefore business validation = 422.
         */
        $this->actingAs($super)
            ->postJson(
                route(
                    'v2.admin.royalties.rights.publish',
                    ['import' => $import->public_id]
                )
            )
            ->assertStatus(422);
    }


    public function test_super_admin_can_approve_publish_rights_import(): void
    {
        $super = $this->user('super_admin');

        /*
         * FF431_J5B_REAL_LABEL_FIXTURE
         *
         * Ledger label_id has a real FK.
         * Never use a synthetic non-existent ID here.
         */
        $labelId = \DB::table('labels')->insertGetId([
            'public_id' =>
                'LBL-' . strtoupper(substr((string) Str::ulid(), -12)),
            'user_id' => $super->id,
            'name' => 'FF431 J5 Lifecycle Label',
            'slug' =>
                'ff431-j5-lifecycle-' .
                strtolower(substr((string) Str::ulid(), -8)),
            'country' => 'India',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'payout_cycle' => 'monthly',
            'minimum_withdrawal_amount' => 5000,
            'royalty_share_percentage' => 100,
            'parent_commission_percentage' => 0,
            'status' => 'active',
            'created_by' => $super->id,
            'updated_by' => $super->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $import = RightsRoyaltyImport::create([
            'public_id' => (string) Str::ulid(),
            'source' => 'OTHER',
            'statement_month' => '2026-09',
            'currency' => 'INR',
            'original_filename' => 'lifecycle-test.csv',
            'uploaded_by' => $super->id,
            'total_rows' => 2,
            'mapped_rows' => 1,
            'unmapped_rows' => 1,
            'gross_amount' => 125,
            'mapped_amount' => 100,
            'unmapped_amount' => 25,
            'status' => 'review_required',
            'processed_at' => now(),
        ]);

        $mappedRowId = \DB::table(
            'rights_royalty_rows'
        )->insertGetId([
            'public_id' => (string) Str::ulid(),
            'rights_royalty_import_id' => $import->id,
            'source_row_number' => 1,
            'isrc' => 'FF431J5MAPPED',
            'track_title' => 'Mapped Rights Test',
            'rights_type' => 'TEST',
            'territory' => 'IN',
            'gross_amount' => 100,
            'track_id' => null,
            'release_id' => null,
            'label_id' => $labelId,
            'artist_id' => null,
            'user_id' => $super->id,
            'royalty_percentage' => 100,
            'beneficiary_amount' => 100,
            'company_amount' => 0,
            'mapping_status' => 'mapped',
            'mapping_reason' => null,
            'raw_data' => json_encode([
                'test' => 'FF431-J5-MAPPED',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $unmappedRowId = \DB::table(
            'rights_royalty_rows'
        )->insertGetId([
            'public_id' => (string) Str::ulid(),
            'rights_royalty_import_id' => $import->id,
            'source_row_number' => 2,
            'isrc' => 'FF431J5UNMAPPED',
            'track_title' => 'Unmapped Rights Test',
            'rights_type' => 'TEST',
            'territory' => 'IN',
            'gross_amount' => 25,
            'track_id' => null,
            'release_id' => null,
            'label_id' => null,
            'artist_id' => null,
            'user_id' => null,
            'royalty_percentage' => 0,
            'beneficiary_amount' => 0,
            'company_amount' => 0,
            'mapping_status' => 'unmapped',
            'mapping_reason' => 'ISRC not found in catalogue',
            'raw_data' => json_encode([
                'test' => 'FF431-J5-UNMAPPED',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sourceKey =
            'ff431-j5:' .
            $import->id . ':' .
            $mappedRowId .
            ':label:' . $labelId . ':direct';

        \DB::table(
            'rights_royalty_allocations'
        )->insert([
            'public_id' => (string) Str::ulid(),
            'rights_royalty_row_id' => $mappedRowId,
            'release_id' => null,
            'track_id' => null,
            'beneficiary_type' => 'label',
            'beneficiary_id' => $labelId,
            'user_id' => $super->id,
            'label_id' => $labelId,
            'artist_id' => null,
            'master_label_id' => null,
            'allocation_type' => 'direct',
            'share_percentage' => 100,
            'gross_amount' => 100,
            'allocated_amount' => 100,
            'currency' => 'INR',
            'revenue_share_id' => null,
            'share_effective_from' => null,
            'share_effective_to' => null,
            'source_key' => $sourceKey,
            'status' => 'mapped',
            'metadata' => json_encode([
                'test' => 'FF431-J5',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            0,
            \DB::table('rights_royalty_allocations')
                ->where(
                    'rights_royalty_row_id',
                    $unmappedRowId
                )
                ->count()
        );

        $walletBefore = \DB::table(
            'wallet_transactions'
        )->count();

        /*
         * APPROVE
         */
        $approve = $this
            ->actingAs($super)
            ->postJson(
                route(
                    'v2.admin.royalties.rights.approve',
                    ['import' => $import->public_id]
                )
            );

        $approve
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'message' =>
                    'Rights royalty import approved.',
            ])
            ->assertJsonPath(
                'import.status',
                'approved'
            );

        $this->assertDatabaseHas(
            'rights_royalty_imports',
            [
                'id' => $import->id,
                'status' => 'approved',
                'approved_by' => $super->id,
            ]
        );

        /*
         * PUBLISH
         */
        $publish = $this
            ->actingAs($super)
            ->postJson(
                route(
                    'v2.admin.royalties.rights.publish',
                    ['import' => $import->public_id]
                )
            );

        $publish
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'message' =>
                    'Rights royalty import published.',
                'created_ledgers' => 1,
                'skipped_ledgers' => 0,
            ])
            ->assertJsonPath(
                'import.status',
                'published'
            );

        $this->assertDatabaseHas(
            'rights_royalty_imports',
            [
                'id' => $import->id,
                'status' => 'published',
                'published_by' => $super->id,
            ]
        );

        $this->assertDatabaseHas(
            'royalty_ledgers',
            [
                'source_type' =>
                    'rights_royalty_allocation',
                'source_key' => $sourceKey,
                'beneficiary_type' => 'label',
                'beneficiary_id' => $labelId,
                'label_id' => $labelId,
                'statement_month' => '2026-09',
                'currency' => 'INR',
                'status' => 'draft',
            ]
        );

        $ledger = \DB::table('royalty_ledgers')
            ->where(
                'source_type',
                'rights_royalty_allocation'
            )
            ->where('source_key', $sourceKey)
            ->first();

        $this->assertNotNull($ledger);

        $this->assertEqualsWithDelta(
            100.0,
            (float) $ledger->gross_amount,
            0.00000001
        );

        $this->assertEqualsWithDelta(
            100.0,
            (float) $ledger->payable_amount,
            0.00000001
        );

        /*
         * Unmapped row must never publish.
         */
        $unmappedLedgerCount = \DB::table(
            'royalty_ledgers'
        )
            ->where(
                'source_type',
                'rights_royalty_allocation'
            )
            ->where(
                'metadata',
                'like',
                '%"rights_royalty_row_id":' .
                $unmappedRowId . '%'
            )
            ->count();

        $this->assertSame(
            0,
            $unmappedLedgerCount
        );

        /*
         * Second publish must be idempotent.
         */
        $secondPublish = $this
            ->actingAs($super)
            ->postJson(
                route(
                    'v2.admin.royalties.rights.publish',
                    ['import' => $import->public_id]
                )
            );

        $secondPublish
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'created_ledgers' => 0,
                'skipped_ledgers' => 1,
            ]);

        $this->assertSame(
            1,
            \DB::table('royalty_ledgers')
                ->where(
                    'source_type',
                    'rights_royalty_allocation'
                )
                ->where(
                    'source_key',
                    $sourceKey
                )
                ->count()
        );

        /*
         * Publish must never mutate wallet balance.
         */
        $this->assertSame(
            $walletBefore,
            \DB::table(
                'wallet_transactions'
            )->count()
        );
    }

}
