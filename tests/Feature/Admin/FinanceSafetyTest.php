<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Finance\FinancialAdjustment;
use App\Models\Finance\RecoupmentPlan;
use App\Models\Finance\RoyaltyStatement;
use App\Models\Finance\WalletAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ConfirmsActionOtp;
use Tests\TestCase;

/*
 * MIXXTUNE_FINANCE_SAFETY_V1
 * All data is generated inside the SQLite :memory: test database.
 */
class FinanceSafetyTest extends TestCase
{
    use RefreshDatabase;
    use ConfirmsActionOtp;

    protected function setUp(): void
    {
        parent::setUp();

        /* audit_logs lives in the MySQL schema dump only; create it for SQLite. */
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function ($table) {
                $table->id();
                $table->string('public_id', 40)->unique();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 100);
                $table->string('module', 100)->nullable();
                $table->string('auditable_type')->nullable();
                $table->unsignedBigInteger('auditable_id')->nullable();
                $table->text('description')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('request_method', 10)->nullable();
                $table->text('request_url')->nullable();
                $table->timestamps();
            });
        }
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function audits(string $action): int
    {
        return DB::table('audit_logs')->where('action', $action)->count();
    }

    public function test_wallet_adjustment_needs_the_emailed_code_and_is_audited(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('artist');
        $payload = [
            'idempotency_key' => 'gen-safety-'.$owner->id, 'user_id' => $owner->id, 'direction' => 'credit',
            'amount' => 250, 'reason' => 'Gen correction', 'type' => 'manual', 'currency' => 'INR',
        ];

        $response = $this->viaActionOtp($super, fn () => $this->actingAs($super)->post('/super-admin/finance/adjustments', $payload));

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, FinancialAdjustment::query()->count());
        $this->assertSame(1, $this->audits('finance.adjustment_posted'));

        /* the code was used up: the same request needs a new code */
        $this->actingAs($super)->post('/super-admin/finance/adjustments', array_merge($payload, ['idempotency_key' => 'gen-safety-2']))
            ->assertSessionHasErrors('action_otp');
        $this->assertSame(1, FinancialAdjustment::query()->count());

        $adjustment = FinancialAdjustment::query()->firstOrFail();
        $this->viaActionOtp($super, fn () => $this->actingAs($super)->post('/super-admin/finance/adjustments/'.$adjustment->id.'/reverse', ['reason' => 'Gen undo']))
            ->assertSessionHasNoErrors();
        $this->assertSame('reversed', $adjustment->fresh()->status);
        $this->assertSame(1, $this->audits('finance.adjustment_reversed'));
    }

    public function test_statement_approve_and_make_available_need_code_and_are_audited(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('artist');
        $label = Label::factory()->create(['created_by' => $owner->id]);
        $artist = Artist::factory()->create(['user_id' => $owner->id, 'label_id' => $label->id, 'created_by' => $owner->id]);
        $statement = RoyaltyStatement::query()->create([
            'public_id' => (string) Str::ulid(), 'artist_id' => $artist->id, 'label_id' => null, 'statement_month' => '2026-07',
            'currency' => 'INR', 'gross_earnings' => 1000, 'commission_amount' => 0, 'tax_amount' => 0,
            'other_deductions' => 0, 'net_payable' => 1000, 'status' => 'pending',
        ]);

        /* viaActionOtp also checks that the first press is stopped without the code */
        $approve = function () use ($super, $statement) {
            $response = $this->actingAs($super)->post('/admin/finance/statements/'.$statement->id.'/approve');

            return $response;
        };

        $presses = 0;
        $this->viaActionOtp($super, function () use ($approve, $statement, &$presses) {
            if (++$presses === 2) {
                /* first press was stopped: nothing changed yet */
                $this->assertSame('pending', $statement->fresh()->status);
            }

            return $approve();
        })->assertSessionHasNoErrors();
        $this->assertSame('approved', $statement->fresh()->status);
        $this->assertSame(1, $this->audits('finance.statement_approved'));

        $this->viaActionOtp($super, fn () => $this->actingAs($super)->post('/admin/finance/statements/'.$statement->id.'/available'))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $this->audits('finance.statement_made_available'));
    }

    /* MIXXTUNE_FINANCE_CLEANUP_V1 */
    public function test_recoupment_plan_cancel_needs_code_reason_and_is_audited(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('artist');
        $plan = RecoupmentPlan::create([
            'user_id' => $owner->id, 'plan_number' => 'REC-GEN-001', 'title' => 'Gen advance',
            'base_percentage' => 20, 'recovery_uplift_percentage' => 10, 'total_recoverable_amount' => 5000,
            'total_recovered_amount' => 0, 'outstanding_amount' => 5000, 'recovery_method' => 'percentage', 'status' => 'active',
        ]);

        $this->actingAs($super)->post('/super-admin/finance/recoupment/'.$plan->id.'/cancel', [])
            ->assertSessionHasErrors('reason');

        $this->viaActionOtp($super, fn () => $this->actingAs($super)->post('/super-admin/finance/recoupment/'.$plan->id.'/cancel', ['reason' => 'Gen settled outside']))
            ->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $plan->fresh()->status);
        $this->assertSame(1, $this->audits('finance.recoupment_plan_cancelled'));

        $this->actingAs($this->user('admin'))->post('/super-admin/finance/recoupment/'.$plan->id.'/cancel', ['reason' => 'x x x'])
            ->assertForbidden();
    }

    public function test_statements_search_runs_on_the_server_over_all_pages(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('artist');
        $label = Label::factory()->create(['created_by' => $owner->id, 'name' => 'Zeta Gen Music']);
        $artist = Artist::factory()->create(['user_id' => $owner->id, 'label_id' => $label->id, 'created_by' => $owner->id]);

        foreach (range(1, 30) as $i) {
            RoyaltyStatement::query()->create([
                'public_id' => (string) Str::ulid(), 'artist_id' => $artist->id, 'label_id' => $i === 30 ? $label->id : null,
                'statement_month' => '2026-07', 'currency' => 'INR', 'gross_earnings' => $i, 'commission_amount' => 0,
                'tax_amount' => 0, 'other_deductions' => 0, 'net_payable' => $i, 'status' => $i % 2 ? 'pending' : 'paid',
            ]);
        }

        $this->actingAs($super)->get('/admin/statements?search=Zeta')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('statements.data', 1)->where('filters.search', 'Zeta'));

        $this->actingAs($super)->get('/admin/statements?status=paid')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('statements.total', 15));

        $other = User::factory()->create(['role' => 'guest_viewer', 'account_status' => 'active', 'email_verified_at' => now()]);
        $this->actingAs($other)->get('/statements')->assertForbidden();
    }

    public function test_admin_wallet_lists_each_owner_and_searches(): void
    {
        $super = $this->user('super_admin');
        $a = User::factory()->create(['role' => 'label', 'name' => 'Gen Alpha Owner', 'account_status' => 'active', 'email_verified_at' => now()]);
        $b = User::factory()->create(['role' => 'artist', 'name' => 'Gen Beta Owner', 'account_status' => 'active', 'email_verified_at' => now()]);

        foreach ([[$a, 700], [$b, 300]] as [$user, $amount]) {
            WalletAccount::query()->create([
                'public_id' => (string) Str::ulid(), 'user_id' => $user->id, 'currency' => 'INR',
                'available_balance' => $amount, 'pending_balance' => 0, 'lifetime_credits' => $amount, 'lifetime_debits' => 0,
            ]);
        }

        $this->actingAs($super)->get('/admin/wallet')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('owners.data', 2)
                ->where('owners.data.0.owner_name', 'Gen Alpha Owner'));

        $this->actingAs($super)->get('/admin/wallet?owner_search=Beta')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('owners.data', 1)->where('owners.data.0.owner_name', 'Gen Beta Owner'));
    }

    public function test_generate_for_a_blocked_month_shows_a_message_not_an_error_page(): void
    {
        $super = $this->user('super_admin');

        $this->actingAs($super)->from('/admin/finance')
            ->post('/admin/finance/statements/generate', ['month' => '2026-05'])
            ->assertRedirect('/admin/finance')
            ->assertSessionHasErrors('generate');
    }
}
