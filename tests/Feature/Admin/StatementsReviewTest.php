<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Finance\RoyaltyStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* MIXXTUNE_STATEMENTS_V2 (W43) */
class StatementsReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function statement(array $extra): RoyaltyStatement
    {
        return RoyaltyStatement::query()->create(array_merge([
            'public_id' => (string) Str::ulid(),
            'statement_month' => '2026-07-01',
            'currency' => 'INR',
            'gross_earnings' => 1000,
            'commission_amount' => 100,
            'tax_amount' => 0,
            'other_deductions' => 50,
            'net_payable' => 850,
            'status' => 'pending',
        ], $extra));
    }

    public function test_totals_cover_every_matching_statement_not_only_the_page(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');
        $label = Label::factory()->create(['user_id' => $owner->id, 'name' => 'Omega Records']);

        foreach (range(1, 30) as $i) {
            $this->statement(['label_id' => $label->id, 'statement_month' => sprintf('2024-%02d-01', ($i % 12) + 1), 'status' => $i <= 4 ? 'pending' : 'paid']);
        }

        $this->actingAs($super)->get('/v2/admin/statements')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Statements/Index')
            ->has('statements.data', 25)
            ->where('summary.count', 30)
            ->where('summary.gross', 30000)
            ->where('summary.deductions', 4500)
            ->where('summary.net', 25500)
            ->where('summary.pending_count', 4)
            ->where('summary.pending_net', 3400)
            ->where('statements.data.0.owner_name', 'Omega Records')
            ->where('statements.data.0.owner_type', 'Label'));
    }

    public function test_month_filter_keeps_month_list_and_owner_sees_real_wallet(): void
    {
        $owner = $this->user('artist');
        $artist = Artist::factory()->create(['user_id' => $owner->id, 'stage_name' => 'Nova']);
        $this->statement(['artist_id' => $artist->id, 'statement_month' => '2026-07-01']);
        $this->statement(['artist_id' => $artist->id, 'statement_month' => '2026-08-01']);

        app(\App\Services\V2\WalletService::class)->account($owner)->forceFill(['available_balance' => 1234.5])->save();

        $this->actingAs($owner)->get('/v2/statements?month=2026-08')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('statements.data', 1)
            ->where('summary.count', 1)
            ->where('summary.wallet', 1234.5)
            ->where('filters.month', '2026-08')
            ->has('months', 2));
    }

    public function test_owner_does_not_see_other_owners_statements_or_totals(): void
    {
        $a = $this->user('artist');
        $b = $this->user('artist');
        $artistA = Artist::factory()->create(['user_id' => $a->id]);
        $artistB = Artist::factory()->create(['user_id' => $b->id]);
        $this->statement(['artist_id' => $artistA->id]);
        $this->statement(['artist_id' => $artistB->id, 'net_payable' => 999999]);

        $this->actingAs($a)->get('/v2/statements')->assertInertia(fn (Assert $p) => $p
            ->has('statements.data', 1)
            ->where('summary.net', 850));
    }
}
