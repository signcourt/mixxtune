<?php

namespace Tests\Feature\Admin;

use App\Models\Finance\WalletAccount;
use App\Models\User;
use App\Services\V2\WalletService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* MIXXTUNE_WALLET_V2 (W44) */
class WalletReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $t) {
                $t->id();
                $t->string('public_id', 40)->unique();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('action', 100);
                $t->string('module', 100)->nullable();
                $t->string('auditable_type')->nullable();
                $t->unsignedBigInteger('auditable_id')->nullable();
                $t->text('description')->nullable();
                $t->json('old_values')->nullable();
                $t->json('new_values')->nullable();
                $t->string('ip_address', 64)->nullable();
                $t->text('user_agent')->nullable();
                $t->string('request_method', 10)->nullable();
                $t->text('request_url')->nullable();
                $t->timestamps();
            });
        }
    }

    private function user(string $role, array $extra = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()], $extra));
    }

    private function wallet(User $u, float $available, float $pending): WalletAccount
    {
        $w = app(WalletService::class)->account($u);
        $w->forceFill(['available_balance' => $available, 'pending_balance' => $pending, 'lifetime_credits' => $available + $pending])->save();

        return $w;
    }

    private function withdrawal(WalletAccount $w, float $amount, string $status): void
    {
        DB::table('withdrawals')->insert([
            'public_id' => (string) Str::ulid(), 'withdrawal_number' => 'WD-'.Str::upper(Str::random(8)),
            'wallet_id' => $w->id, 'user_id' => $w->user_id, 'amount' => $amount, 'currency' => 'INR',
            'status' => $status, 'requested_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function entry(WalletAccount $w, string $type, string $dir, float $amount): void
    {
        DB::table('wallet_transactions')->insert([
            'public_id' => (string) Str::ulid(), 'wallet_id' => $w->id, 'user_id' => $w->user_id,
            'transaction_type' => $type, 'direction' => $dir, 'amount' => $amount, 'currency' => 'INR',
            'balance_before' => 0, 'balance_after' => $amount, 'description' => 'test entry',
            'status' => 'posted', 'created_at' => '2026-10-21 06:30:00', 'updated_at' => now(),
        ]);
    }

    public function test_staff_sees_money_in_withdrawals_separately_and_named_history(): void
    {
        $super = $this->user('super_admin');
        $a = $this->user('label', ['name' => 'Alpha Owner']);
        $b = $this->user('artist', ['name' => 'Beta Owner']);
        $wa = $this->wallet($a, 1000, 700);
        $wb = $this->wallet($b, 50, 0);
        $this->withdrawal($wa, 500, 'pending');
        $this->withdrawal($wa, 200, 'paid');
        $this->entry($wa, 'royalty_import', 'credit', 1000);
        $this->entry($wb, 'withdrawal_hold', 'debit', 50);

        $this->actingAs($super)->get('/admin/wallet')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Wallet/Index')
            ->where('wallet.in_withdrawal', 500)
            ->where('wallet.royalty_pending', 200)
            ->where('wallet.owners_with_money', 2)
            ->where('owners.data.0.owner_name', 'Alpha Owner')
            ->where('owners.data.0.in_withdrawal', 500)
            ->where('owners.data.0.royalty_pending', 200)
            ->has('transactions.data', 2)
            ->where('transactions.data.0.type', 'Withdrawal requested')
            ->where('transactions.data.0.owner', 'Beta Owner')
            ->where('transactions.data.1.date', '21 Oct 2026, 12:00 PM'));

        $this->actingAs($super)->get('/admin/wallet?wallet='.$wa->id)->assertInertia(fn (Assert $p) => $p
            ->has('transactions.data', 1)
            ->where('historyWallet', $wa->id)
            ->where('historyOwner', 'Alpha Owner'));
    }

    public function test_history_filter_cannot_reach_a_wallet_outside_scope(): void
    {
        $admin = $this->user('admin');
        $other = $this->user('label');
        $w = $this->wallet($other, 10, 0);
        $this->entry($w, 'royalty_import', 'credit', 10);

        $r = $this->actingAs($admin)->get('/admin/wallet?wallet='.$w->id);

        /* an admin without the wallet permission is stopped before; otherwise nothing leaks */
        if ($r->status() === 403) {
            $this->assertTrue(true);

            return;
        }

        $r->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('historyWallet', null)
            ->has('transactions.data', 0));
    }

    public function test_owner_sees_in_withdrawal_and_readable_history(): void
    {
        $owner = $this->user('label');
        $w = $this->wallet($owner, 300, 400);
        $this->withdrawal($w, 400, 'approved');
        $this->entry($w, 'manual_adjustment', 'credit', 300);

        $this->actingAs($owner)->get('/wallet')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('inWithdrawal', 400)
            ->where('transactions.data.0.type', 'Manual adjustment'));
    }

    /**
     * MIXXTUNE_HIDE_ADVANCE_CHARGE_V1: an owner (and a manager/admin) sees the
     * advance charge folded into one "Advance recovery" row; only a super admin
     * sees the charge broken out.
     */
    private function recoveryPair(WalletAccount $w, int $planId, float $recovery, float $charge): void
    {
        DB::table('wallet_transactions')->insert([
            'public_id' => (string) Str::ulid(), 'wallet_id' => $w->id, 'user_id' => $w->user_id,
            'transaction_type' => 'recoupment_recovery', 'direction' => 'debit', 'amount' => $recovery, 'currency' => 'INR',
            'balance_before' => 0, 'balance_after' => 0, 'reference_type' => 'recoupment_plan', 'reference_id' => $planId,
            'description' => 'rec', 'status' => 'posted', 'created_at' => '2026-10-21 07:00:00', 'updated_at' => now(),
        ]);
        DB::table('wallet_transactions')->insert([
            'public_id' => (string) Str::ulid(), 'wallet_id' => $w->id, 'user_id' => $w->user_id,
            'transaction_type' => 'advance_charge_commission', 'direction' => 'debit', 'amount' => $charge, 'currency' => 'INR',
            'balance_before' => 0, 'balance_after' => 0, 'reference_type' => 'recoupment_plan', 'reference_id' => $planId,
            'description' => 'chg', 'status' => 'posted', 'created_at' => '2026-10-21 07:00:01', 'updated_at' => now(),
        ]);
    }

    public function test_owner_sees_advance_charge_folded_into_recovery(): void
    {
        $owner = $this->user('label');
        $w = $this->wallet($owner, 0, 0);
        $this->recoveryPair($w, 4242, 455.77, 50.64);

        // Owner: one row only, "Advance recovery", amount = 455.77 + 50.64 = 506.41.
        $this->actingAs($owner)->get('/wallet')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('transactions.data', fn ($rows) => collect($rows)->count() === 1
                && collect($rows)->first()['type'] === 'Advance recovery'
                && abs((float) collect($rows)->first()['amount'] - 506.41) < 0.001)
            ->etc());
    }

    public function test_super_admin_sees_advance_charge_broken_out(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');
        $w = $this->wallet($owner, 0, 0);
        $this->recoveryPair($w, 4343, 455.77, 50.64);

        // Super admin: both rows present, charge shown separately.
        $this->actingAs($super)->get('/admin/wallet?wallet='.$w->id)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('transactions.data', fn ($rows) => collect($rows)->count() === 2
                && collect($rows)->contains(fn ($r) => $r['type'] === 'Advance charge' && abs((float) $r['amount'] - 50.64) < 0.001)
                && collect($rows)->contains(fn ($r) => $r['type'] === 'Advance recovery' && abs((float) $r['amount'] - 455.77) < 0.001))
            ->etc());
    }

    public function test_owner_balances_csv_is_staff_only_and_audited(): void
    {
        $super = $this->user('super_admin');
        $this->wallet($this->user('label', ['name' => 'Csv Wallet Owner']), 99, 0);

        $r = $this->actingAs($super)->get('/admin/wallet/export');
        $r->assertOk();
        $this->assertStringContainsString('Csv Wallet Owner', $r->streamedContent());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'finance.wallets_exported')->count());

        $this->actingAs($this->user('label'))->get('/admin/wallet/export')->assertForbidden();
    }
}
