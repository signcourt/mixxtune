<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Label;
use App\Models\Finance\Invoice;
use App\Models\Finance\RoyaltyStatement;
use App\Models\Support\PanelNotification;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* MIXXTUNE_INVOICES_V2 (W42) */
class InvoicesReviewTest extends TestCase
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

    private function statement(?Label $label, array $extra = []): RoyaltyStatement
    {
        return RoyaltyStatement::query()->create(array_merge([
            'public_id' => (string) Str::ulid(),
            'label_id' => $label?->id,
            'artist_id' => null,
            'statement_month' => '2026-07-01',
            'currency' => 'INR',
            'gross_earnings' => 1000,
            'commission_amount' => 100,
            'tax_amount' => 0,
            'other_deductions' => 0,
            'net_payable' => 900,
            'status' => 'approved',
        ], $extra));
    }

    public function test_empty_page_explains_statements_waiting_for_approval(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');
        $label = Label::factory()->create(['user_id' => $owner->id, 'name' => 'Sigma Records']);
        $this->statement($label, ['status' => 'pending']);

        $this->actingAs($super)->get('/v2/admin/invoices')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('V2/Admin/Invoices/Index')
            ->where('counts.missing', 0)
            ->where('counts.waiting_approval', 1)
            ->has('statements.data', 0));
    }

    public function test_ready_list_shows_owner_name_and_month(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');
        $label = Label::factory()->create(['user_id' => $owner->id, 'name' => 'Sigma Records']);
        $this->statement($label);

        $this->actingAs($super)->get('/v2/admin/invoices')->assertInertia(fn (Assert $p) => $p
            ->where('counts.missing', 1)
            ->where('statements.data.0.owner', 'Sigma Records')
            ->where('statements.data.0.month_label', 'July 2026')
            ->where('statements.data.0.net', 900));
    }

    public function test_invoice_keeps_net_payable_does_not_take_tax_twice_and_tells_owner(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');
        $label = Label::factory()->create(['user_id' => $owner->id]);
        $s = $this->statement($label, ['tax_amount' => 100, 'net_payable' => 800]);

        $this->actingAs($super)->post('/v2/admin/invoices/statements/'.$s->id.'/generate')->assertSessionHasNoErrors();

        $inv = Invoice::query()->firstOrFail();
        $this->assertEquals(900, (float) $inv->subtotal);
        $this->assertEquals(100, (float) $inv->tds_amount);
        $this->assertEquals(900, (float) $inv->total_amount);
        $this->assertEquals(800, (float) $inv->getAttribute('net_payable'));
        $this->assertSame($label->id, (int) $inv->getAttribute('label_id'));
        $this->assertSame(1, PanelNotification::query()->where('user_id', $owner->id)->count());

        $this->actingAs($owner)->get('/v2/invoices/'.$inv->id.'/download')->assertOk();
    }

    public function test_number_continues_after_the_highest_used_number(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');
        $label = Label::factory()->create(['user_id' => $owner->id]);
        $year = now()->format('Y');
        $prefix = \App\Services\V2\PlatformRules::invoicePrefix();

        Invoice::query()->create([
            'public_id' => (string) Str::ulid(), 'invoice_number' => "{$prefix}-{$year}10-000041", 'user_id' => $owner->id,
            'invoice_type' => 'royalty', 'invoice_date' => now()->toDateString(), 'currency' => 'INR',
            'subtotal' => 1, 'tax_amount' => 0, 'tds_amount' => 0, 'total_amount' => 1, 'status' => 'issued',
        ]);

        $s = $this->statement($label);
        $this->actingAs($super)->post('/v2/admin/invoices/statements/'.$s->id.'/generate');

        $this->assertSame("{$prefix}-{$year}-000042", Invoice::query()->where('royalty_statement_id', $s->id)->value('invoice_number'));
    }

    public function test_statement_without_owner_or_not_approved_shows_a_message_not_an_error_page(): void
    {
        $super = $this->user('super_admin');
        $orphan = Label::factory()->create(['user_id' => null]);
        $s = $this->statement($orphan);

        $this->actingAs($super)->from('/v2/admin/invoices')->post('/v2/admin/invoices/statements/'.$s->id.'/generate')
            ->assertRedirect('/v2/admin/invoices')
            ->assertSessionHasErrors('invoice');

        $owner = $this->user('label');
        $pending = $this->statement(Label::factory()->create(['user_id' => $owner->id]), ['status' => 'pending']);
        $this->actingAs($super)->post('/v2/admin/invoices/statements/'.$pending->id.'/generate')->assertSessionHasErrors('invoice');

        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_make_all_missing_creates_each_once(): void
    {
        $super = $this->user('super_admin');
        foreach (range(1, 3) as $i) {
            $o = $this->user('label');
            $this->statement(Label::factory()->create(['user_id' => $o->id]));
        }

        $this->actingAs($super)->post('/v2/admin/invoices/generate-missing')->assertSessionHas('success');
        $this->assertSame(3, Invoice::query()->count());

        $this->actingAs($super)->post('/v2/admin/invoices/generate-missing');
        $this->assertSame(3, Invoice::query()->count());
        $this->assertSame(3, Invoice::query()->distinct()->count('invoice_number'));

        $this->actingAs($super)->get('/v2/admin/invoices?tab=invoices')->assertInertia(fn (Assert $p) => $p
            ->has('invoices.data', 3)
            ->where('invoices.data.0.kind', 'Monthly royalty')
            ->where('invoices.data.0.net', 900));
    }

    public function test_owner_sees_only_own_invoices_with_net_amount(): void
    {
        $super = $this->user('super_admin');
        $a = $this->user('label');
        $b = $this->user('label');
        $this->statement(Label::factory()->create(['user_id' => $a->id]));
        $this->statement(Label::factory()->create(['user_id' => $b->id]));
        $this->actingAs($super)->post('/v2/admin/invoices/generate-missing');

        $this->actingAs($a)->get('/v2/invoices')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('invoices.data', 1)
            ->where('invoices.data.0.net', 900)
            ->where('invoices.data.0.month_label', 'July 2026'));
    }
}
