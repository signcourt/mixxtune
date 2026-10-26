<?php

namespace Tests\Feature\Admin;

use App\Mail\ActionOtpMail;
use App\Models\Finance\PayoutProfile;
use App\Models\Finance\WalletAccount;
use App\Models\User;
use App\Services\V2\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* W118 MIXXTUNE_MAKER_CHECKER (generated test data only) */
class MakerCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        Mail::fake();
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
        foreach ([['company', 'company.legal_name', 'Test Distribution Company'], ['company', 'company.address', 'Test Company Address']] as [$g, $k, $v]) {
            DB::table('system_settings')->insert(['group' => $g, 'key' => $k, 'value' => $v, 'type' => 'string', 'is_public' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function user(string $role, string $name): User
    {
        $id = DB::table('users')->insertGetId(['name' => $name, 'email' => Str::lower(Str::random(10)).'@example.test', 'role' => $role, 'account_status' => 'active', 'kyc_status' => 'verified', 'password' => Hash::make('password'), 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return User::query()->findOrFail($id);
    }

    private function unlock(User $actor): void
    {
        $code = null;
        Mail::assertSent(ActionOtpMail::class, function (ActionOtpMail $m) use (&$code) {
            $code = $m->otp;

            return true;
        });
        $this->actingAs($actor)->postJson('/admin/action-otp/unlock', ['otp' => (string) $code])->assertOk();
    }

    /** press, enter the e-mailed code, press again */
    private function withCode(User $u, callable $press)
    {
        $before = Mail::sent(ActionOtpMail::class)->count();
        $r = $press();
        if (Mail::sent(ActionOtpMail::class)->count() > $before) {
            $this->unlock($u);
            $r = $press();
        }

        return $r;
    }

    private function approvedWithdrawal(User $super)
    {
        $owner = $this->user('artist', 'Owner');
        PayoutProfile::query()->create(['public_id' => (string) Str::ulid(), 'user_id' => $owner->id, 'account_holder_name' => 'Owner One', 'bank_name' => 'Demo', 'bank_account_number' => '111122223333', 'ifsc_code' => 'DEMO0000001', 'country_code' => 'IN', 'kyc_status' => 'verified', 'verified_at' => now()]);
        WalletAccount::query()->create(['public_id' => (string) Str::ulid(), 'user_id' => $owner->id, 'artist_id' => null, 'label_id' => null, 'currency' => 'INR', 'available_balance' => 5000, 'pending_balance' => 0, 'lifetime_credits' => 5000, 'lifetime_debits' => 0, 'status' => 'active']);
        $s = app(WithdrawalService::class);

        return $s->approve($s->create($owner, 1500, 'bank', 'x'), $super);
    }

    public function test_off_by_default_and_needs_two_super_admins_to_switch_on(): void
    {
        $a = $this->user('super_admin', 'Admin A');
        $w = $this->approvedWithdrawal($a);
        /* OFF: Mark Paid works as before (only the code) */
        $this->withCode($a, fn () => $this->actingAs($a)->post("/admin/withdrawals/{$w->id}/paid", ['payment_reference' => 'UTR-A1']))->assertSessionHasNoErrors();
        $this->assertSame('paid', $w->fresh()->status);

        $this->actingAs($a)->post('/admin/approvals/switch', ['on' => 1])->assertSessionHasErrors('approval');
        $this->actingAs($a)->get('/admin/approvals')->assertInertia(fn (Assert $p) => $p->component('V2/Admin/Approvals/Index')->where('on', false)->where('superAdmins', 1));
        $this->actingAs($this->user('admin', 'Manager'))->get('/admin/approvals')->assertForbidden();
    }

    public function test_when_on_another_super_admin_must_say_yes_once(): void
    {
        $a = $this->user('super_admin', 'Admin A');
        $b = $this->user('super_admin', 'Admin B');
        $this->withCode($a, fn () => $this->actingAs($a)->post('/admin/approvals/switch', ['on' => 1]))->assertSessionHasNoErrors();
        $this->assertTrue(\App\Services\V2\MakerChecker::on());
        $w = $this->approvedWithdrawal($a);

        /* A presses Mark Paid: stopped, a request is made, B is told */
        $this->actingAs($a)->post("/admin/withdrawals/{$w->id}/paid", ['payment_reference' => 'UTR-1'])->assertSessionHasErrors('approval');
        $this->assertSame('approved', $w->fresh()->status);
        $req = DB::table('pending_approvals')->where('status', 'open')->first();
        $this->assertNotNull($req);
        $this->assertDatabaseHas('panel_notifications', ['user_id' => $b->id, 'type' => 'approval.requested']);
        $this->assertDatabaseMissing('panel_notifications', ['user_id' => $a->id, 'type' => 'approval.requested']);
        /* pressing again makes no second request */
        $this->actingAs($a)->post("/admin/withdrawals/{$w->id}/paid", ['payment_reference' => 'UTR-1'])->assertSessionHasErrors('approval');
        $this->assertSame(1, DB::table('pending_approvals')->count());

        /* A cannot approve own request; B approves (with B's code) */
        $this->actingAs($a)->post("/admin/approvals/{$req->id}", ['decision' => 'approve'])->assertSessionHasErrors('approval');
        $this->withCode($b, fn () => $this->actingAs($b)->post("/admin/approvals/{$req->id}", ['decision' => 'approve']))->assertSessionHasNoErrors();
        $this->assertSame('approved', DB::table('pending_approvals')->where('id', $req->id)->value('status'));
        $this->assertDatabaseHas('panel_notifications', ['user_id' => $a->id, 'type' => 'approval.decided']);

        /* B (the approver) cannot use it; A can, once */
        $this->actingAs($b)->post("/admin/withdrawals/{$w->id}/paid", ['payment_reference' => 'UTR-1'])->assertSessionHasErrors('approval');
        $this->withCode($a, fn () => $this->actingAs($a)->post("/admin/withdrawals/{$w->id}/paid", ['payment_reference' => 'UTR-1']))->assertSessionHasNoErrors();
        $this->assertSame('paid', $w->fresh()->status);
        $this->assertSame('used', DB::table('pending_approvals')->where('id', $req->id)->value('status'));

        /* switching OFF also needs B's yes */
        $this->actingAs($a)->post('/admin/approvals/switch', ['on' => 0])->assertSessionHasErrors('approval');
        $this->assertTrue(\App\Services\V2\MakerChecker::on());
    }
}
