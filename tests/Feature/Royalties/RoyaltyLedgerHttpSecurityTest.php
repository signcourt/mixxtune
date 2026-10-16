<?php

namespace Tests\Feature\Royalties;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoyaltyLedgerHttpSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }

    private function label(User $user): int
    {
        return (int) DB::table('labels')->insertGetId([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'user_id' => $user->id,
            'name' => 'FF431 K12 Label '.$user->id,
            'slug' => 'ff431-k12-label-'.$user->id.'-'.strtolower(
                \Illuminate\Support\Str::random(6)
            ),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ledger(User $owner): int
    {
        $labelId = $this->label($owner);

        return (int) DB::table('royalty_ledgers')->insertGetId([
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'ledger_number' => 'FF431-K12-'.strtoupper(
                \Illuminate\Support\Str::random(8)
            ),
            'user_id' => $owner->id,
            'label_id' => $labelId,
            'beneficiary_type' => 'label',
            'beneficiary_id' => $labelId,
            'statement_month' => '2026-09',
            'reporting_month' => '2026-09-01',
            'sale_month' => '2026-09-01',
            'store_name' => 'FF431 K12 Test Store',
            'territory' => 'IN',
            'currency' => 'INR',
            'gross_amount' => 100,
            'label_share' => 100,
            'artist_share' => 0,
            'split_percentage' => 100,
            'payable_amount' => 100,
            'streams' => 1,
            'status' => 'draft',
            'source_type' => 'ff431_k12_test',
            'source_key' => 'ff431-k12-'.strtolower(
                \Illuminate\Support\Str::random(12)
            ),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_manager_can_reach_read_only_ledger_routes(): void
    {
        $manager = $this->user('admin');

        $this->actingAs($manager)
            ->get('/admin/royalty-ledgers')
            ->assertOk();
    }

    public function test_manager_has_no_ledger_mutation_routes(): void
    {
        $manager = $this->user('admin');

        /*
         * These URLs intentionally must not exist for Manager.
         * Use an arbitrary ID so controller/business logic cannot affect
         * the security assertion.
         */
        foreach (['approve', 'cancel', 'credit-wallet'] as $action) {
            $this->actingAs($manager)
                ->post("/admin/royalty-ledgers/999999/{$action}")
                ->assertNotFound();
        }
    }

    public function test_label_and_artist_cannot_access_admin_ledger_routes(): void
    {
        foreach (['label', 'artist'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)
                ->get('/admin/royalty-ledgers')
                ->assertForbidden();

            $this->actingAs($user)
                ->get('/admin/royalty-ledgers/999999')
                ->assertForbidden();
        }
    }

    public function test_manager_cannot_access_super_admin_ledger_routes(): void
    {
        $manager = $this->user('admin');

        $this->actingAs($manager)
            ->get('/super-admin/royalty-ledgers')
            ->assertForbidden();

        $this->actingAs($manager)
            ->get('/super-admin/royalty-ledgers/999999')
            ->assertForbidden();

        foreach (['approve', 'cancel', 'credit-wallet'] as $action) {
            $this->actingAs($manager)
                ->post("/super-admin/royalty-ledgers/999999/{$action}")
                ->assertForbidden();
        }
    }

    public function test_super_admin_can_reach_super_admin_ledger_index(): void
    {
        $superAdmin = $this->user('super_admin');

        $this->actingAs($superAdmin)
            ->get('/super-admin/royalty-ledgers')
            ->assertOk();
    }

    public function test_super_admin_reaches_bound_ledger_mutation_controllers(): void
    {
        $superAdmin = $this->user('super_admin');
        $owner = $this->user('label');

        foreach (['approve', 'cancel', 'credit-wallet'] as $action) {
            $ledgerId = $this->ledger($owner);

            $response = $this->actingAs($superAdmin)
                ->post("/super-admin/royalty-ledgers/{$ledgerId}/{$action}");

            /*
             * Security objective:
             * request must pass auth + verified + super_admin middleware.
             *
             * Controller may redirect or return validation/business error
             * depending on ledger lifecycle, but must not be 403/404.
             */
            $this->assertNotSame(
                403,
                $response->getStatusCode(),
                "Super Admin unexpectedly forbidden for {$action}."
            );

            $this->assertNotSame(
                404,
                $response->getStatusCode(),
                "Super Admin did not reach {$action} controller."
            );
        }
    }

    public function test_super_admin_can_filter_exact_rights_import_batch(): void
    {
        /*
         * FF431_K16B_RIGHTS_IMPORT_FILTER_REGRESSION
         *
         * Permanent regression for:
         *
         * Rights Upload
         *   -> Publish to Ledger
         *   -> Review Published Ledgers
         *
         * Do NOT manually send X-Inertia here. A normal browser GET is the
         * correct route-level contract and avoids Inertia version 409s.
         */
        $superAdmin = $this->user('super_admin');
        $owner = $this->user('label');
        $labelId = $this->label($owner);

        $targetImport = (string) \Illuminate\Support\Str::ulid();
        $controlImport = (string) \Illuminate\Support\Str::ulid();

        $makeLedger = function (
            string $importPublicId,
            string $suffix,
            float $amount,
            string $sourceType = 'rights_royalty_allocation'
        ) use ($owner, $labelId): int {
            return (int) DB::table(
                'royalty_ledgers'
            )->insertGetId([
                'public_id' =>
                    (string) \Illuminate\Support\Str::ulid(),

                'ledger_number' =>
                    'FF431-K16B-'.$suffix.'-'.
                    strtoupper(
                        \Illuminate\Support\Str::random(8)
                    ),

                'user_id' => $owner->id,
                'label_id' => $labelId,
                'artist_id' => null,

                'beneficiary_type' => 'label',
                'beneficiary_id' => $labelId,

                'statement_month' => '2026-09',
                'reporting_month' => '2026-09-01',
                'sale_month' => '2026-09-01',

                'store_name' => $sourceType ===
                    'rights_royalty_allocation'
                        ? 'RIGHTS ROYALTY'
                        : 'FF431 K16B CONTROL',

                'territory' => 'IN',
                'currency' => 'INR',

                'gross_amount' => $amount,
                'label_share' => $amount,
                'artist_share' => 0,
                'split_percentage' => 100,
                'payable_amount' => $amount,
                'streams' => 0,

                'status' => 'draft',

                'source_type' => $sourceType,
                'source_key' =>
                    'ff431-k16b-'.
                    strtolower($suffix).'-'.
                    strtolower(
                        \Illuminate\Support\Str::random(16)
                    ),

                'metadata' => json_encode([
                    'source' => $sourceType,
                    'rights_royalty_import_public_id' =>
                        $importPublicId,
                    'fixture' => 'FF431-K16B-'.$suffix,
                ]),

                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        $targetLedgerId = $makeLedger(
            $targetImport,
            'TARGET',
            100
        );

        $controlLedgerId = $makeLedger(
            $controlImport,
            'CONTROL',
            50
        );

        /*
         * Same metadata ID but wrong source_type.
         * Must NOT leak into the Rights batch.
         */
        $nonRightsLedgerId = $makeLedger(
            $targetImport,
            'NONRIGHTS',
            999,
            'ff431_k16b_non_rights'
        );

        $response = $this
            ->actingAs($superAdmin)
            ->get(
                '/super-admin/royalty-ledgers?'.
                http_build_query([
                    'rights_import' => $targetImport,
                ])
            );

        $response->assertOk();

        /*
         * Laravel's Inertia testing assertions inspect the normal
         * HTML/Inertia response without forging protocol headers.
         */
        $response->assertInertia(
            fn ($page) => $page
                ->component('Admin/RoyaltyLedgers/Index')
                ->where(
                    'filters.rights_import',
                    $targetImport
                )
                ->has('ledgers.data', 1)
                ->where(
                    'ledgers.data.0.id',
                    $targetLedgerId
                )
                ->where(
                    'summary.total_ledgers',
                    1
                )
        );

        /*
         * Also verify exact database-side isolation independently
         * of presentation serialization.
         */
        $matchingIds = DB::table('royalty_ledgers')
            ->where(
                'source_type',
                'rights_royalty_allocation'
            )
            ->where(
                'metadata->rights_royalty_import_public_id',
                $targetImport
            )
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame(
            [$targetLedgerId],
            $matchingIds
        );

        $this->assertNotContains(
            $controlLedgerId,
            $matchingIds
        );

        $this->assertNotContains(
            $nonRightsLedgerId,
            $matchingIds
        );
    }


    public function test_super_admin_can_approve_and_credit_exact_rights_batch_idempotently(): void
    {
        /*
         * FF431_K17C3_RIGHTS_BATCH_E2E
         *
         * Exact Rights batch:
         * draft -> approved -> wallet_credited
         *
         * Also proves:
         * - another Rights import is untouched
         * - non-Rights ledger with same metadata is untouched
         * - approve retry is safe
         * - wallet credit retry is idempotent
         */
        $superAdmin = $this->user('super_admin');
        $owner = $this->user('label');
        $labelId = $this->label($owner);

        $targetImport = (string) \Illuminate\Support\Str::ulid();
        $controlImport = (string) \Illuminate\Support\Str::ulid();

        $makeLedger = function (
            string $importPublicId,
            string $suffix,
            float $amount,
            string $sourceType = 'rights_royalty_allocation'
        ) use ($owner, $labelId, $superAdmin): int {
            return (int) DB::table('royalty_ledgers')->insertGetId([
                'public_id' => (string) \Illuminate\Support\Str::ulid(),

                'ledger_number' =>
                    'FF431-K17C3-'.$suffix.'-'.
                    strtoupper(\Illuminate\Support\Str::random(8)),

                'user_id' => $owner->id,
                'label_id' => $labelId,
                'artist_id' => null,

                'beneficiary_type' => 'label',
                'beneficiary_id' => $labelId,

                'statement_month' => '2026-09',
                'reporting_month' => '2026-09-01',
                'sale_month' => '2026-09-01',

                'store_name' =>
                    $sourceType === 'rights_royalty_allocation'
                        ? 'RIGHTS ROYALTY'
                        : 'FF431 K17C3 CONTROL',

                'territory' => 'IN',
                'currency' => 'INR',

                'gross_amount' => $amount,
                'label_share' => $amount,
                'artist_share' => 0,
                'split_percentage' => 100,
                'payable_amount' => $amount,
                'streams' => 0,

                'status' => 'draft',

                'source_type' => $sourceType,

                'source_key' =>
                    'ff431-k17c3-'.
                    strtolower($suffix).'-'.
                    strtolower(\Illuminate\Support\Str::random(16)),

                'metadata' => json_encode([
                    'source' => $sourceType,
                    'rights_royalty_import_public_id' =>
                        $importPublicId,
                    'fixture' => 'FF431-K17C3-'.$suffix,
                ]),

                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        $targetOne = $makeLedger(
            $targetImport,
            'TARGET-A',
            100
        );

        $targetTwo = $makeLedger(
            $targetImport,
            'TARGET-B',
            50
        );

        $controlLedger = $makeLedger(
            $controlImport,
            'CONTROL',
            70
        );

        $nonRightsLedger = $makeLedger(
            $targetImport,
            'NONRIGHTS',
            999,
            'ff431_k17c3_non_rights'
        );

        /*
         * Approve exact target batch.
         */
        $this->actingAs($superAdmin)
            ->post(
                '/super-admin/royalty-ledgers/rights-import/approve',
                ['rights_import' => $targetImport]
            )
            ->assertRedirect();

        foreach ([$targetOne, $targetTwo] as $id) {
            $this->assertDatabaseHas('royalty_ledgers', [
                'id' => $id,
                'status' => 'approved',
            ]);
        }

        $this->assertDatabaseHas('royalty_ledgers', [
            'id' => $controlLedger,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('royalty_ledgers', [
            'id' => $nonRightsLedger,
            'status' => 'draft',
        ]);

        /*
         * Approve retry.
         */
        $this->actingAs($superAdmin)
            ->post(
                '/super-admin/royalty-ledgers/rights-import/approve',
                ['rights_import' => $targetImport]
            )
            ->assertRedirect();

        foreach ([$targetOne, $targetTwo] as $id) {
            $this->assertDatabaseHas('royalty_ledgers', [
                'id' => $id,
                'status' => 'approved',
            ]);
        }

        /*
         * Credit exact target batch.
         */
        $this->actingAs($superAdmin)
            ->post(
                '/super-admin/royalty-ledgers/rights-import/credit-wallet',
                ['rights_import' => $targetImport]
            )
            ->assertRedirect();

        foreach ([$targetOne, $targetTwo] as $id) {
            $this->assertDatabaseHas('royalty_ledgers', [
                'id' => $id,
                'status' => 'wallet_credited',
            ]);

            $this->assertDatabaseHas('wallet_transactions', [
                'reference_type' => 'royalty_ledger',
                'reference_id' => $id,
                'transaction_type' => 'royalty_credit',
            ]);
        }

        $wallet = DB::table('wallets')
            ->where('label_id', $labelId)
            ->whereNull('artist_id')
            ->where('currency', 'INR')
            ->first();

        $this->assertNotNull($wallet);

        $this->assertEqualsWithDelta(
            150,
            (float) $wallet->available_balance,
            0.00000001
        );

        $this->assertEqualsWithDelta(
            150,
            (float) $wallet->lifetime_credits,
            0.00000001
        );

        $this->assertSame(
            2,
            DB::table('wallet_transactions')
                ->where('reference_type', 'royalty_ledger')
                ->whereIn('reference_id', [$targetOne, $targetTwo])
                ->where('transaction_type', 'royalty_credit')
                ->count()
        );

        /*
         * Wallet credit retry.
         */
        $this->actingAs($superAdmin)
            ->post(
                '/super-admin/royalty-ledgers/rights-import/credit-wallet',
                ['rights_import' => $targetImport]
            )
            ->assertRedirect();

        $walletAfterRetry = DB::table('wallets')
            ->where('id', $wallet->id)
            ->first();

        $this->assertEqualsWithDelta(
            150,
            (float) $walletAfterRetry->available_balance,
            0.00000001
        );

        $this->assertEqualsWithDelta(
            150,
            (float) $walletAfterRetry->lifetime_credits,
            0.00000001
        );

        $this->assertSame(
            2,
            DB::table('wallet_transactions')
                ->where('reference_type', 'royalty_ledger')
                ->whereIn('reference_id', [$targetOne, $targetTwo])
                ->where('transaction_type', 'royalty_credit')
                ->count()
        );

        /*
         * Final isolation assertions.
         */
        $this->assertDatabaseHas('royalty_ledgers', [
            'id' => $controlLedger,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('royalty_ledgers', [
            'id' => $nonRightsLedger,
            'status' => 'draft',
        ]);

        $this->assertDatabaseMissing('wallet_transactions', [
            'reference_type' => 'royalty_ledger',
            'reference_id' => $controlLedger,
            'transaction_type' => 'royalty_credit',
        ]);

        $this->assertDatabaseMissing('wallet_transactions', [
            'reference_type' => 'royalty_ledger',
            'reference_id' => $nonRightsLedger,
            'transaction_type' => 'royalty_credit',
        ]);
    }

}
