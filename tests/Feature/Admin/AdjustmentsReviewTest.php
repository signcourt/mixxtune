<?php

namespace Tests\Feature\Admin;

use App\Models\Finance\FinancialAdjustment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ConfirmsActionOtp;
use Tests\TestCase;

class AdjustmentsReviewTest extends TestCase
{
    use RefreshDatabase;
    use ConfirmsActionOtp;

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
        return User::factory()->create(array_merge([
            'role' => $role,
            'account_status' => 'active',
            'email_verified_at' => now(),
        ], $extra));
    }

    private function postAdj(User $super, User $owner, string $dir, $amount, string $key, array $more = [])
    {
        return $this->viaActionOtp($super, fn () => $this->actingAs($super)->post('/super-admin/finance/adjustments', array_merge([
            'idempotency_key' => $key,
            'user_id' => $owner->id,
            'direction' => $dir,
            'amount' => $amount,
            'reason' => 'Royalty correction',
            'type' => 'manual',
            'currency' => 'INR',
        ], $more)));
    }

    public function test_reversal_is_not_counted_as_a_live_debit(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label', ['name' => 'Demo Owner One']);

        $this->postAdj($super, $owner, 'credit', 1, 'rev-sum-1')->assertSessionHasNoErrors();
        $a = FinancialAdjustment::query()->firstOrFail();
        $this->viaActionOtp($super, fn () => $this->actingAs($super)->post('/super-admin/finance/adjustments/'.$a->id.'/reverse', ['reason' => 'Wrong owner']))
            ->assertSessionHasNoErrors();

        $this->actingAs($super)->get('/super-admin/finance/adjustments')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('V3/SuperAdmin/Adjustments/Index')
                ->where('summary.credits', 0)
                ->where('summary.debits', 0)
                ->where('summary.posted', 0)
                ->where('summary.reversed', 1)
                ->where('summary.reversals', 1)
                ->has('adjustments.data', 2));

        $this->actingAs($super)->get('/super-admin/finance/adjustments?show=reversals')
            ->assertInertia(fn (Assert $p) => $p->has('adjustments.data', 1)->where('filters.show', 'reversals'));
    }

    public function test_search_and_direction_filters(): void
    {
        $super = $this->user('super_admin');
        $a = $this->user('label', ['name' => 'Alpha Records']);
        $b = $this->user('artist', ['name' => 'Beta Singer']);

        $this->postAdj($super, $a, 'credit', 500, 'f-1');
        $this->postAdj($super, $b, 'credit', 700, 'f-2', ['reference_number' => 'UTR-777']);

        $this->actingAs($super)->get('/super-admin/finance/adjustments?search=Alpha')
            ->assertInertia(fn (Assert $p) => $p->has('adjustments.data', 1)->where('adjustments.data.0.user.name', 'Alpha Records'));
        $this->actingAs($super)->get('/super-admin/finance/adjustments?search=UTR-777')
            ->assertInertia(fn (Assert $p) => $p->has('adjustments.data', 1));
        $this->actingAs($super)->get('/super-admin/finance/adjustments?direction=debit')
            ->assertInertia(fn (Assert $p) => $p->has('adjustments.data', 0));
    }

    public function test_owner_search_returns_balances_and_only_owners(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label', ['name' => 'Zeta Music']);
        $this->user('admin', ['name' => 'Zeta Admin']);
        $this->postAdj($super, $owner, 'credit', 250, 'o-1');

        $res = $this->actingAs($super)->getJson('/super-admin/finance/adjustments/owners?q=Zeta')->assertOk();
        $this->assertCount(1, $res->json('owners'));
        $this->assertSame($owner->id, $res->json('owners.0.id'));
        $this->assertEquals(250, $res->json('owners.0.available'));

        $this->actingAs($super)->getJson('/super-admin/finance/adjustments/owners?q=Z')->assertJson(['owners' => []]);
        $this->actingAs($this->user('admin'))->getJson('/super-admin/finance/adjustments/owners?q=Zeta')->assertForbidden();
    }

    public function test_export_csv_is_audited(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('artist', ['name' => 'Csv Owner']);
        $this->postAdj($super, $owner, 'credit', 100, 'x-1');

        $res = $this->actingAs($super)->get('/super-admin/finance/adjustments/export');
        $res->assertOk();
        $this->assertStringContainsString('Csv Owner', $res->streamedContent());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'finance.adjustments_exported')->count());
    }

    public function test_owner_is_told_unless_unticked(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');

        $this->postAdj($super, $owner, 'credit', 300, 'n-1')->assertSessionHasNoErrors();
        $this->postAdj($super, $owner, 'credit', 400, 'n-2', ['notify_owner' => false])->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table((new \App\Models\Support\PanelNotification)->getTable())->where('user_id', $owner->id)->count());
    }
}
