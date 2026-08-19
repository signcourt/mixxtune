<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminRoyaltyLedgerIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::query()->create([
            'name' => 'Scoped Admin',
            'email' => 'scoped-admin@example.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    private function label(string $name): int
    {
        return DB::table('labels')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(8)),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ledger(int $labelId): int
    {
        return DB::table('royalty_ledgers')->insertGetId([
            'public_id' => (string) Str::ulid(),
            'ledger_number' => 'TEST-' . Str::upper(
                Str::random(8)
            ),
            'label_id' => $labelId,
            'reporting_month' => '2026-06-01',
            'sale_month' => '2026-05-01',
            'statement_month' => '2026-06-01',
            'store_name' => 'Test Store',
            'currency' => 'INR',
            'gross_amount' => 100,
            'label_share' => 100,
            'split_percentage' => 100,
            'payable_amount' => 100,
            'streams' => 10,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignLabel(
        User $admin,
        int $labelId
    ): void {
        DB::table(
            'admin_label_assignments'
        )->insert([
            'user_id' => $admin->id,
            'label_id' => $labelId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }


    public function test_admin_cannot_show_foreign_ledger(): void
    {
        $admin = $this->admin();

        $ownLabel = $this->label('Own Label');
        $foreignLabel = $this->label('Foreign Label');

        $this->assignLabel($admin, $ownLabel);

        $foreignLedger = $this->ledger($foreignLabel);

        $this->actingAs($admin)
            ->get(
                route(
                    'single.admin.royalty-ledgers.show',
                    $foreignLedger
                )
            )
            ->assertForbidden();
    }

    public function test_admin_cannot_approve_foreign_ledger(): void
    {
        $admin = $this->admin();

        $ownLabel = $this->label('Own Label');
        $foreignLabel = $this->label('Foreign Label');

        $this->assignLabel($admin, $ownLabel);

        $foreignLedger = $this->ledger($foreignLabel);

        $this->actingAs($admin)
            ->post(
                route(
                    'single.admin.royalty-ledgers.approve',
                    $foreignLedger
                )
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'royalty_ledgers',
            [
                'id' => $foreignLedger,
                'status' => 'draft',
            ]
        );
    }

    public function test_admin_cannot_cancel_foreign_ledger(): void
    {
        $admin = $this->admin();

        $ownLabel = $this->label('Own Label');
        $foreignLabel = $this->label('Foreign Label');

        $this->assignLabel($admin, $ownLabel);

        $foreignLedger = $this->ledger($foreignLabel);

        $this->actingAs($admin)
            ->post(
                route(
                    'single.admin.royalty-ledgers.cancel',
                    $foreignLedger
                )
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'royalty_ledgers',
            [
                'id' => $foreignLedger,
                'status' => 'draft',
            ]
        );
    }

    public function test_admin_cannot_credit_foreign_ledger(): void
    {
        $admin = $this->admin();

        $ownLabel = $this->label('Own Label');
        $foreignLabel = $this->label('Foreign Label');

        $this->assignLabel($admin, $ownLabel);

        $foreignLedger = $this->ledger($foreignLabel);

        $this->actingAs($admin)
            ->post(
                route(
                    'single.admin.royalty-ledgers.credit-wallet',
                    $foreignLedger
                )
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'royalty_ledgers',
            [
                'id' => $foreignLedger,
                'status' => 'draft',
            ]
        );
    }
}
