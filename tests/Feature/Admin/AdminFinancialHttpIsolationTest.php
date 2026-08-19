<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Label;
use App\Models\Finance\PayoutProfile;
use App\Models\Finance\RoyaltyStatement;
use App\Models\Finance\WithdrawalRequest;
use App\Models\Finance\WalletAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminFinancialHttpIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function label(
        User $creator,
        ?User $owner = null
    ): Label {
        return Label::factory()->create([
            'created_by' => $creator->id,
            'user_id' => $owner?->id,
        ]);
    }

    private function assignLabel(
        User $admin,
        Label $label,
        User $super
    ): void {
        DB::table('admin_label_assignments')->insert([
            'user_id' => $admin->id,
            'label_id' => $label->id,
            'assignment_role' => 'manager',
            'can_view' => true,
            'can_edit' => true,
            'can_manage_releases' => true,
            'can_manage_team' => false,
            'can_manage_splits' => false,
            'assigned_by' => $super->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function statement(
        Label $label
    ): RoyaltyStatement {
        return RoyaltyStatement::query()->create([
            'public_id' => (string) Str::ulid(),
            'label_id' => $label->id,
            'statement_month' => '2026-06-01',
            'currency' => 'INR',
            'gross_earnings' => 1000,
            'commission_amount' => 100,
            'tax_amount' => 0,
            'other_deductions' => 0,
            'net_payable' => 900,
            'status' => 'available',
        ]);
    }

    private function wallet(
        User $owner,
        Label $label
    ): WalletAccount {
        return WalletAccount::query()->create([
            'public_id' => (string) Str::ulid(),
            'user_id' => $owner->id,
            'label_id' => $label->id,
            'currency' => 'INR',
            'pending_balance' => 0,
            'available_balance' => 5000,
            'lifetime_earnings' => 5000,
            'lifetime_withdrawn' => 0,
            'status' => 'active',
        ]);
    }

    private function withdrawal(
        User $owner,
        Label $label,
        WalletAccount $wallet
    ): WithdrawalRequest {
        return WithdrawalRequest::query()->create([
            'public_id' => (string) Str::ulid(),
            'withdrawal_number' =>
                'WD-' . Str::upper(
                    Str::random(12)
                ),
            'wallet_id' => $wallet->id,
            'user_id' => $owner->id,
            'label_id' => $label->id,
            'amount' => 1000,
            'currency' => 'INR',
            'status' => 'pending',
            'payment_method' => 'bank',
            'requested_at' => now(),
            'created_by' => $owner->id,
        ]);
    }

    private function payoutProfile(
        User $owner
    ): PayoutProfile {
        return PayoutProfile::query()->create([
            'public_id' => (string) Str::ulid(),
            'user_id' => $owner->id,
            'account_holder_name' => $owner->name,
            'bank_name' => 'Test Bank',
            'bank_account_number' => '1234567890',
            'ifsc_code' => 'TEST0000001',
            'country_code' => 'IN',
            'kyc_status' => 'submitted',
        ]);
    }

    public function test_admin_cannot_generate_invoice_for_foreign_statement(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $assigned = $this->label($super);
        $foreign = $this->label($super);

        $this->assignLabel(
            $admin,
            $assigned,
            $super
        );

        $statement =
            $this->statement($foreign);

        $this->actingAs($admin)
            ->post(
                route(
                    'v2.admin.invoices.generate',
                    $statement
                )
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'invoices',
            0
        );
    }

    public function test_admin_cannot_approve_foreign_withdrawal(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $assignedOwner = $this->user('label');
        $foreignOwner = $this->user('label');

        $assigned =
            $this->label(
                $super,
                $assignedOwner
            );

        $foreign =
            $this->label(
                $super,
                $foreignOwner
            );

        $this->assignLabel(
            $admin,
            $assigned,
            $super
        );

        $wallet =
            $this->wallet(
                $foreignOwner,
                $foreign
            );

        $withdrawal =
            $this->withdrawal(
                $foreignOwner,
                $foreign,
                $wallet
            );

        $this->actingAs($admin)
            ->post(
                route(
                    'v2.admin.withdrawals.approve',
                    $withdrawal
                ),
                [
                    'admin_note' => 'test',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'withdrawals',
            [
                'id' => $withdrawal->id,
                'status' => 'pending',
            ]
        );
    }

    public function test_admin_cannot_reject_foreign_withdrawal(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $assignedOwner = $this->user('label');
        $foreignOwner = $this->user('label');

        $assigned =
            $this->label(
                $super,
                $assignedOwner
            );

        $foreign =
            $this->label(
                $super,
                $foreignOwner
            );

        $this->assignLabel(
            $admin,
            $assigned,
            $super
        );

        $wallet =
            $this->wallet(
                $foreignOwner,
                $foreign
            );

        $withdrawal =
            $this->withdrawal(
                $foreignOwner,
                $foreign,
                $wallet
            );

        $this->actingAs($admin)
            ->post(
                route(
                    'v2.admin.withdrawals.reject',
                    $withdrawal
                ),
                [
                    'rejection_reason' =>
                        'Foreign account test',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'withdrawals',
            [
                'id' => $withdrawal->id,
                'status' => 'pending',
            ]
        );
    }

    public function test_admin_cannot_mark_foreign_withdrawal_paid(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $assignedOwner = $this->user('label');
        $foreignOwner = $this->user('label');

        $assigned =
            $this->label(
                $super,
                $assignedOwner
            );

        $foreign =
            $this->label(
                $super,
                $foreignOwner
            );

        $this->assignLabel(
            $admin,
            $assigned,
            $super
        );

        $wallet =
            $this->wallet(
                $foreignOwner,
                $foreign
            );

        $withdrawal =
            $this->withdrawal(
                $foreignOwner,
                $foreign,
                $wallet
            );

        $this->actingAs($admin)
            ->post(
                route(
                    'v2.admin.withdrawals.paid',
                    $withdrawal
                ),
                [
                    'payment_reference' =>
                        'TEST-FOREIGN-001',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'withdrawals',
            [
                'id' => $withdrawal->id,
                'status' => 'pending',
            ]
        );
    }

    public function test_admin_cannot_verify_foreign_kyc_profile(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $assignedOwner = $this->user('label');
        $foreignOwner = $this->user('label');

        $assigned =
            $this->label(
                $super,
                $assignedOwner
            );

        $this->label(
            $super,
            $foreignOwner
        );

        $this->assignLabel(
            $admin,
            $assigned,
            $super
        );

        $profile =
            $this->payoutProfile(
                $foreignOwner
            );

        $this->actingAs($admin)
            ->post(
                route(
                    'v2.admin.kyc.verify',
                    $profile
                ),
                [
                    'status' => 'verified',
                    'kyc_notes' => 'test',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas(
            'payout_profiles',
            [
                'id' => $profile->id,
                'kyc_status' => 'submitted',
            ]
        );
    }

    public function test_admin_cannot_use_super_admin_finance_mutations(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');

        $label = $this->label($super);

        $this->assignLabel(
            $admin,
            $label,
            $super
        );

        $statement =
            $this->statement($label);

        $this->actingAs($admin)
            ->post(
                route(
                    'v2.admin.finance.approve',
                    $statement
                )
            )
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(
                route(
                    'v2.admin.finance.available',
                    $statement
                )
            )
            ->assertForbidden();
    }
}
