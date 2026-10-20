<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Finance\FinancialAdjustment;
use App\Models\Finance\RoyaltyStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
            $response = $this->actingAs($super)->post('/v2/admin/finance/statements/'.$statement->id.'/approve');

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

        $this->viaActionOtp($super, fn () => $this->actingAs($super)->post('/v2/admin/finance/statements/'.$statement->id.'/available'))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $this->audits('finance.statement_made_available'));
    }

    public function test_generate_for_a_blocked_month_shows_a_message_not_an_error_page(): void
    {
        $super = $this->user('super_admin');

        $this->actingAs($super)->from('/v2/admin/finance')
            ->post('/v2/admin/finance/statements/generate', ['month' => '2026-05'])
            ->assertRedirect('/v2/admin/finance')
            ->assertSessionHasErrors('generate');
    }
}
