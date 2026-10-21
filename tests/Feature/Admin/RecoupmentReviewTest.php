<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Label;
use App\Models\Finance\RecoupmentPlan;
use App\Models\Support\PanelNotification;
use App\Models\User;
use App\Services\V3\RecoupmentManagementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ConfirmsActionOtp;
use Tests\TestCase;

/* MIXXTUNE_RECOUPMENT_V2 (W41) */
class RecoupmentReviewTest extends TestCase
{
    use RefreshDatabase;
    use ConfirmsActionOtp;

    private string $url = '/super-admin/finance/recoupment';

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

    private function plan(User $super, User $owner, $amount = 1000, array $more = [])
    {
        return $this->viaActionOtp($super, fn () => $this->actingAs($super)->post($this->url, array_merge([
            'user_id' => $owner->id,
            'title' => 'Advance 2026',
            'base_percentage' => 80,
            'recovery_uplift_percentage' => 10,
            'initial_amount' => $amount,
        ], $more)));
    }

    private function notices(User $u): int
    {
        return PanelNotification::query()->where('user_id', $u->id)->count();
    }

    private function audits(string $action): int
    {
        return DB::table('audit_logs')->where('action', $action)->count();
    }

    public function test_first_advance_amount_is_kept_needs_code_and_owner_is_told(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');

        /* the old page sent initial_advance_amount - it was silently dropped */
        $this->viaActionOtp($super, fn () => $this->actingAs($super)->post($this->url, [
            'user_id' => $owner->id, 'title' => 'Old form', 'base_percentage' => 80,
            'recovery_uplift_percentage' => 10, 'initial_advance_amount' => 2500,
        ]))->assertSessionHasNoErrors();

        $plan = RecoupmentPlan::query()->firstOrFail();
        $this->assertEquals(2500, (float) $plan->total_recoverable_amount);
        $this->assertEquals(2500, (float) $plan->outstanding_amount);
        $this->assertSame(1, $this->notices($owner));
        $this->assertSame(1, $this->audits('finance.recoupment_plan_created'));
    }

    public function test_plan_without_amount_needs_no_code_and_second_active_plan_is_refused(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('artist');

        $this->actingAs($super)->post($this->url, [
            'user_id' => $owner->id, 'title' => 'Empty', 'base_percentage' => 80, 'recovery_uplift_percentage' => 10,
        ])->assertSessionHasNoErrors();

        $this->actingAs($super)->post($this->url, [
            'user_id' => $owner->id, 'title' => 'Again', 'base_percentage' => 80, 'recovery_uplift_percentage' => 10,
        ])->assertSessionHasErrors('user_id');

        $this->actingAs($super)->get($this->url)->assertInertia(fn (Assert $p) => $p->where('summary.empty_plans', 1));
    }

    public function test_scope_must_belong_to_the_owner(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');
        $other = $this->user('label');
        $foreign = Label::factory()->create(['user_id' => $other->id]);
        $mine = Label::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($super)->post($this->url, [
            'user_id' => $owner->id, 'title' => 'x', 'base_percentage' => 80, 'recovery_uplift_percentage' => 10, 'label_id' => $foreign->id,
        ])->assertSessionHasErrors('label_id');

        $this->actingAs($super)->post($this->url, [
            'user_id' => $owner->id, 'title' => 'x', 'base_percentage' => 80, 'recovery_uplift_percentage' => 10, 'label_id' => $mine->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($mine->id, (int) RecoupmentPlan::query()->firstOrFail()->label_id);
    }

    public function test_owner_search_shows_labels_and_open_plan(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label', ['name' => 'Kappa Records']);
        $label = Label::factory()->create(['user_id' => $owner->id, 'name' => 'Kappa Main']);
        $this->user('admin', ['name' => 'Kappa Admin']);
        $this->plan($super, $owner, 500);

        $r = $this->actingAs($super)->getJson($this->url.'/owners?q=Kappa')->assertOk();
        $this->assertCount(1, $r->json('owners'));
        $this->assertSame($label->id, $r->json('owners.0.labels.0.id'));
        $this->assertEquals(500, $r->json('owners.0.open_plan.outstanding'));

        $this->actingAs($this->user('admin'))->getJson($this->url.'/owners?q=Kappa')->assertForbidden();
    }

    public function test_recoverable_cost_needs_code_note_only_cost_does_not(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');
        $this->plan($super, $owner, 1000);
        $plan = RecoupmentPlan::query()->firstOrFail();

        $this->actingAs($super)->post($this->url.'/'.$plan->id.'/expenses', [
            'category' => 'meta_ads', 'title' => 'Ads', 'amount' => 300, 'is_recoverable' => false,
        ])->assertSessionHasNoErrors();
        $this->assertEquals(1000, (float) $plan->fresh()->outstanding_amount);

        $this->viaActionOtp($super, fn () => $this->actingAs($super)->post($this->url.'/'.$plan->id.'/expenses', [
            'category' => 'meta_ads', 'title' => 'Ads 2', 'amount' => 400, 'is_recoverable' => true,
        ]))->assertSessionHasNoErrors();

        $this->assertEquals(1400, (float) $plan->fresh()->outstanding_amount);
        $this->assertSame(2, $this->notices($owner));
    }

    public function test_cost_can_be_taken_out_but_not_below_what_is_already_recovered(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');
        $this->plan($super, $owner, 1000);
        $plan = RecoupmentPlan::query()->firstOrFail();
        $this->viaActionOtp($super, fn () => $this->actingAs($super)->post($this->url.'/'.$plan->id.'/expenses', [
            'category' => 'other', 'title' => 'Typo', 'amount' => 5000, 'is_recoverable' => true,
        ]));
        $typo = $plan->expenses()->where('title', 'Typo')->firstOrFail();

        /* 10% of 10,000 revenue = 1,000 recovered */
        app(RecoupmentManagementService::class)->applyRecovery($plan->fresh(), 10000);
        $this->assertEquals(1000, (float) $plan->fresh()->total_recovered_amount);

        $this->viaActionOtp($super, fn () => $this->actingAs($super)->post($this->url.'/'.$plan->id.'/expenses/'.$typo->id.'/remove', ['reason' => 'Wrong amount']))
            ->assertSessionHasNoErrors();

        $fresh = $plan->fresh();
        $this->assertEquals(1000, (float) $fresh->total_recoverable_amount);
        $this->assertEquals(0, (float) $fresh->outstanding_amount);
        $this->assertSame('completed', $fresh->status);
        $this->assertFalse((bool) $typo->fresh()->is_recoverable);
        $this->assertSame(1, $this->audits('finance.recoupment_expense_removed'));

        $first = $plan->expenses()->where('is_recoverable', true)->firstOrFail();
        $this->actingAs($super)->post($this->url.'/'.$plan->id.'/expenses/'.$first->id.'/remove', ['reason' => 'Try'])
            ->assertSessionHasErrors('expense');
    }

    public function test_rate_change_needs_code_title_change_does_not(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');
        $this->plan($super, $owner, 1000);
        $plan = RecoupmentPlan::query()->firstOrFail();
        $base = ['title' => 'Renamed', 'base_percentage' => 80, 'recovery_uplift_percentage' => 10, 'maximum_recovery_percentage' => ''];

        $this->actingAs($super)->post($this->url.'/'.$plan->id.'/update', $base)->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $plan->fresh()->title);

        $this->viaActionOtp($super, fn () => $this->actingAs($super)->post($this->url.'/'.$plan->id.'/update', array_merge($base, ['recovery_uplift_percentage' => 20])))
            ->assertSessionHasNoErrors();
        $this->assertEquals(20, (float) $plan->fresh()->recovery_uplift_percentage);

        $this->actingAs($super)->post($this->url.'/'.$plan->id.'/update', array_merge($base, ['recovery_uplift_percentage' => 90]))
            ->assertSessionHasErrors('recovery_uplift_percentage');
    }

    public function test_totals_leave_out_cancelled_plans_and_export_is_audited(): void
    {
        $super = $this->user('super_admin');
        $a = $this->user('label', ['name' => 'Alpha Owner']);
        $b = $this->user('label');
        $this->plan($super, $a, 1000);
        $this->plan($super, $b, 700);
        $planB = RecoupmentPlan::query()->where('user_id', $b->id)->firstOrFail();
        $this->viaActionOtp($super, fn () => $this->actingAs($super)->post($this->url.'/'.$planB->id.'/cancel', ['reason' => 'Settled outside']));

        $this->actingAs($super)->get($this->url)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V3/SuperAdmin/Recoupment/Index')
            ->where('summary.total_recoverable', 1000)
            ->where('summary.outstanding', 1000)
            ->where('summary.written_off', 700)
            ->where('summary.active_plans', 1)
            ->where('plans.data.0.rates.recovery', 10)
            ->missing('accounts'));

        $csv = $this->actingAs($super)->get($this->url.'/export');
        $csv->assertOk();
        $this->assertStringContainsString('Alpha Owner', $csv->streamedContent());
        $this->assertSame(1, $this->audits('finance.recoupment_exported'));
    }

    public function test_owner_sees_the_advance_on_the_wallet_page(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');
        $this->plan($super, $owner, 1500);

        $this->actingAs($owner)->get('/v2/wallet')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('advances', 1)
            ->where('advances.0.outstanding', 1500)
            ->where('advances.0.recovery', 10));
    }
}
