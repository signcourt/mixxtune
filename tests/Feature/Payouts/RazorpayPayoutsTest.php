<?php

namespace Tests\Feature\Payouts;

use App\Mail\ActionOtpMail;
use App\Models\Finance\BankPayout;
use App\Models\Finance\PaymentGatewaySetting;
use App\Models\Finance\PayoutProfile;
use App\Models\Finance\WalletAccount;
use App\Models\Payroll\SalaryRun;
use App\Models\Payroll\StaffMember;
use App\Models\User;
use App\Services\V2\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* W115 MIXXTUNE_RAZORPAY_PAYOUTS + MIXXTUNE_STAFF_SALARY (generated test data only; Razorpay is faked) */
class RazorpayPayoutsTest extends TestCase
{
    use RefreshDatabase;

    /** answers the fake Razorpay gives to POST /payouts, one after another */
    private array $payoutAnswers = [];

    private array $created = [];

    private bool $contactsDown = false;

    /** what GET /payouts (list) gives */
    private array $listItems = [];

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
        foreach ([
            ['company', 'company.legal_name', 'Test Distribution Company', 'string'],
            ['company', 'company.address', 'Test Company Address', 'string'],
            ['invoice', 'invoice.tds_percent', '10', 'decimal'],
        ] as [$group, $key, $value, $type]) {
            DB::table('system_settings')->insert(['group' => $group, 'key' => $key, 'value' => $value, 'type' => $type, 'is_public' => false, 'created_at' => now(), 'updated_at' => now()]);
        }

        Http::fake(function (HttpRequest $r) {
            $url = $r->url();
            if (str_contains($url, '/v1/contacts') && $this->contactsDown) {
                return Http::response(['error' => ['description' => 'down']], 503);
            }
            if (str_contains($url, '/v1/contacts')) {
                return Http::response(['id' => 'cont_test'.count($this->created), 'entity' => 'contact'], 200);
            }
            if (str_contains($url, '/v1/fund_accounts')) {
                return Http::response(['id' => 'fa_test'.count($this->created), 'entity' => 'fund_account', 'active' => true], 200);
            }
            if ($r->method() === 'POST' && str_ends_with($url, '/v1/payouts')) {
                $this->created[] = ['key' => $r->header('X-Payout-Idempotency')[0] ?? null, 'body' => $r->data()];
                $a = array_shift($this->payoutAnswers) ?? ['status' => 'processing'];
                if (isset($a['http'])) {
                    return Http::response(['error' => ['description' => $a['desc'] ?? 'error']], $a['http']);
                }

                return Http::response(['id' => $a['id'] ?? 'pout_'.count($this->created), 'entity' => 'payout', 'status' => $a['status'], 'utr' => $a['utr'] ?? null, 'amount' => $r->data()['amount'], 'mode' => 'NEFT'], 200);
            }
            if ($r->method() === 'GET' && preg_match('#/v1/payouts/(pout_\w+)#', $url, $m)) {
                return Http::response(['id' => $m[1], 'entity' => 'payout', 'status' => 'processed', 'utr' => 'NEFTUTR'.$m[1]], 200);
            }
            if ($r->method() === 'GET' && str_contains($url, '/v1/payouts')) {
                return Http::response(['entity' => 'collection', 'count' => count($this->listItems), 'items' => $this->listItems], 200);
            }

            return Http::response(['error' => ['description' => 'unexpected '.$url]], 500);
        });
    }

    private function user(string $role): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => ucfirst($role).' Person', 'email' => Str::lower(Str::random(10)).'@example.test', 'role' => $role,
            'account_status' => 'active', 'kyc_status' => 'verified', 'password' => Hash::make('password'),
            'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

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

    private function connect(bool $on = true): void
    {
        $s = PaymentGatewaySetting::razorpay();
        $s->forceFill(['enabled' => $on, 'key_id' => 'rzp_test_ABC123', 'key_secret' => 'sec_test', 'webhook_secret' => 'whsec_test', 'account_number' => '7878780080316316', 'narration' => 'Mixx Tune'])->save();
    }

    private function withdrawal(float $amount = 2000)
    {
        $owner = $this->user('artist');
        PayoutProfile::query()->create([
            'public_id' => (string) Str::ulid(), 'user_id' => $owner->id, 'account_holder_name' => 'Test Owner', 'bank_name' => 'Demo Bank',
            'bank_account_number' => '111122223333', 'ifsc_code' => 'DEMO0000001', 'country_code' => 'IN', 'kyc_status' => 'verified', 'verified_at' => now(),
        ]);
        WalletAccount::query()->create([
            'public_id' => (string) Str::ulid(), 'user_id' => $owner->id, 'artist_id' => null, 'label_id' => null, 'currency' => 'INR',
            'available_balance' => 5000, 'pending_balance' => 0, 'lifetime_credits' => 5000, 'lifetime_debits' => 0, 'status' => 'active',
        ]);

        return app(WithdrawalService::class)->create($owner, $amount, 'bank', 'Test request.');
    }

    public function test_settings_are_super_admin_only_need_a_code_and_keep_secrets_hidden(): void
    {
        $super = $this->user('super_admin');
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/admin/razorpay')->assertForbidden();
        $this->actingAs($admin)->post('/admin/razorpay/settings', ['enabled' => 0])->assertForbidden();

        $form = ['enabled' => 1, 'key_id' => 'rzp_test_ABC123', 'key_secret' => 'sec_test_SECRET', 'webhook_secret' => 'wh_SECRET', 'account_number' => '7878780080316316', 'narration' => 'Mixx Tune'];
        $this->actingAs($super)->post('/admin/razorpay/settings', $form)->assertSessionHasErrors('action_otp');
        $this->assertFalse(PaymentGatewaySetting::razorpay()->enabled);
        $this->unlock($super);
        $this->actingAs($super)->post('/admin/razorpay/settings', $form)->assertSessionHasNoErrors();

        $s = PaymentGatewaySetting::razorpay();
        $this->assertTrue($s->enabled);
        $this->assertSame('sec_test_SECRET', $s->key_secret);
        $this->assertStringNotContainsString('sec_test_SECRET', (string) DB::table('payment_gateway_settings')->value('key_secret'));
        $this->assertStringNotContainsString('SECRET', json_encode(DB::table('audit_logs')->get()));
        $page = $this->actingAs($super)->get('/admin/razorpay')->assertOk();
        $page->assertInertia(fn (Assert $p) => $p->component('V2/Admin/Razorpay/Index')->where('settings.has_secret', true)->where('settings.account_hint', '••••6316')->where('settings.live', false)->missing('settings.key_secret'));
        $this->assertStringNotContainsString('SECRET', $page->getContent());
        $this->assertStringNotContainsString('7878780080316316', $page->getContent());

        /* empty secret boxes keep the saved ones */
        $keep = ['enabled' => 1, 'key_id' => 'rzp_test_ABC123', 'key_secret' => '', 'account_number' => ''];
        $this->actingAs($super)->post('/admin/razorpay/settings', $keep)->assertSessionHasErrors('action_otp');
        $this->unlock($super);
        $this->actingAs($super)->post('/admin/razorpay/settings', $keep)->assertSessionHasNoErrors();
        $this->assertSame('sec_test_SECRET', PaymentGatewaySetting::razorpay()->key_secret);

        $this->actingAs($super)->post('/admin/razorpay/test')->assertSessionHas('success', 'Connection works (TEST mode - no real money).');
    }

    public function test_live_keys_are_refused_on_the_staging_site(): void
    {
        config(['app.url' => 'https://staging.mixxtune.com']);
        $super = $this->user('super_admin');
        $this->actingAs($super)->post('/admin/razorpay/settings', ['enabled' => 1, 'key_id' => 'rzp_live_XYZ', 'key_secret' => 's', 'account_number' => '123456'])->assertSessionHasErrors('key_id');
        $this->assertNull(PaymentGatewaySetting::razorpay()->key_id);
    }

    public function test_approve_and_pay_sends_the_net_once_and_the_webhook_marks_it_paid(): void
    {
        $this->connect();
        $super = $this->user('super_admin');
        $admin = $this->user('admin');
        $w = $this->withdrawal(2000);

        $this->actingAs($admin)->post("/admin/withdrawals/{$w->id}/razorpay-pay")->assertForbidden();
        $this->actingAs($super)->post("/admin/withdrawals/{$w->id}/razorpay-pay")->assertSessionHasErrors('action_otp');
        $this->assertSame('pending', $w->fresh()->status);
        $this->unlock($super);
        $this->actingAs($super)->post("/admin/withdrawals/{$w->id}/razorpay-pay")->assertSessionHasNoErrors();

        $w->refresh();
        $this->assertSame('processing', $w->status);
        $this->assertCount(1, $this->created);
        $body = $this->created[0]['body'];
        $this->assertSame(180000, $body['amount']); /* ₹2000 - 10% TDS = ₹1800 */
        $this->assertSame('NEFT', $body['mode']);
        $this->assertSame('payout', $body['purpose']);
        $this->assertSame('7878780080316316', $body['account_number']);
        $this->assertSame($w->withdrawal_number, $body['reference_id']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9 ]{1,30}$/', $body['narration']);
        $p = BankPayout::query()->firstOrFail();
        $this->assertSame($p->idempotency_key, $this->created[0]['key']);
        $this->assertSame('processing', $p->status);

        /* while Razorpay is paying: no second payment, no Mark Paid / Reject by hand */
        $this->actingAs($super)->post("/admin/withdrawals/{$w->id}/razorpay-pay")->assertStatus(422);
        $this->actingAs($super)->post("/admin/withdrawals/{$w->id}/reject", ['rejection_reason' => 'Changed my mind'])->assertSessionHasErrors('razorpay');
        $this->assertCount(1, $this->created);

        /* webhook: bad signature refused; good one books the payment (wallet + payout document), only once */
        $payload = json_encode(['event' => 'payout.processed', 'payload' => ['payout' => ['entity' => ['id' => $p->razorpay_payout_id, 'status' => 'processed', 'utr' => 'AXISN26300012345']]]]);
        $this->call('POST', '/api/webhooks/razorpayx', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => 'bad', 'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_1'], $payload)->assertStatus(401);
        $sig = hash_hmac('sha256', $payload, 'whsec_test');
        $this->call('POST', '/api/webhooks/razorpayx', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $sig, 'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_1'], $payload)->assertOk();
        $this->call('POST', '/api/webhooks/razorpayx', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $sig, 'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_1'], $payload)->assertJson(['duplicate' => true]);

        $w->refresh();
        $this->assertSame('paid', $w->status);
        $this->assertSame('AXISN26300012345', $w->payment_reference);
        $this->assertTrue($p->fresh()->settled);
        $this->assertSame(1, DB::table('invoices')->where('withdrawal_id', $w->id)->count());
        $wallet = WalletAccount::query()->where('user_id', $w->user_id)->first();
        $this->assertEquals(0, (float) $wallet->pending_balance);
        $this->assertEquals(3000, (float) $wallet->available_balance);
        $this->assertDatabaseHas('panel_notifications', ['user_id' => $w->user_id, 'type' => 'withdrawal.paid']);

        /* a late "reversed" after paid: stays paid, Super Admins are told */
        $rev = json_encode(['event' => 'payout.reversed', 'payload' => ['payout' => ['entity' => ['id' => $p->razorpay_payout_id, 'status' => 'reversed']]]]);
        $this->call('POST', '/api/webhooks/razorpayx', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $rev, 'whsec_test'), 'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_2'], $rev)->assertOk();
        $this->assertTrue($p->fresh()->reversed_after_paid);
        $this->assertSame('paid', $w->fresh()->status);
        $this->assertDatabaseHas('panel_notifications', ['user_id' => $super->id, 'type' => 'payout.razorpay']);
    }

    public function test_a_refused_payout_goes_back_to_approved_and_no_answer_is_retried_with_the_same_key(): void
    {
        $this->connect();
        $super = $this->user('super_admin');

        /* Razorpay refuses (e.g. bad IFSC): back to Approved, can be sent again */
        $w = $this->withdrawal();
        $this->payoutAnswers = [['http' => 400, 'desc' => 'Invalid IFSC Code in fund account']];
        app(\App\Services\V2\Payouts\BankPayoutService::class)->payWithdrawal($w, $super);
        $this->assertSame('approved', $w->fresh()->status);
        $this->assertSame('failed', BankPayout::query()->latest('id')->value('status'));
        $this->assertStringContainsString('Invalid IFSC', (string) BankPayout::query()->latest('id')->value('failure_reason'));

        /* time-out: kept open; payouts:sync sends again with the SAME key; then the status is fetched */
        $this->payoutAnswers = [['http' => 503], ['status' => 'processing', 'id' => 'pout_retry1']];
        $p = app(\App\Services\V2\Payouts\BankPayoutService::class)->payWithdrawal($w->fresh(), $super);
        $this->assertSame('error', $p->status);
        $this->assertSame('processing', $w->fresh()->status);
        $this->travel(4)->minutes();
        $this->artisan('payouts:sync')->assertExitCode(0);
        $keys = array_column(array_slice($this->created, -2), 'key');
        $this->assertSame($keys[0], $keys[1]);
        $this->assertSame($p->idempotency_key, $keys[0]);
        $this->assertSame('processing', $p->fresh()->status);
        $this->artisan('payouts:sync')->assertExitCode(0);
        $this->assertSame('processed', $p->fresh()->status);
        $this->assertSame('paid', $w->fresh()->status);
        $this->assertSame('NEFTUTRpout_retry1', $w->fresh()->payment_reference);
    }

    public function test_review_fixes_no_double_pay_paths(): void
    {
        $this->connect();
        $super = $this->user('super_admin');

        /* 1. paid at the bank while KYC was re-opened: still booked (money is gone), never refundable */
        $w = $this->withdrawal();
        $p = app(\App\Services\V2\Payouts\BankPayoutService::class)->payWithdrawal($w, $super);
        PayoutProfile::query()->where('user_id', $w->user_id)->update(['kyc_status' => 'submitted']);
        $this->actingAs($super)->post("/admin/withdrawals/{$w->id}/reject", ['rejection_reason' => 'x x x'])->assertSessionHasErrors('razorpay');
        $this->travel(2)->minutes();
        $this->artisan('payouts:sync')->assertExitCode(0);
        /* the payout document needs verified KYC - so it waits, the request can NOT be rejected / refunded */
        $this->assertSame('processing', $w->fresh()->status);
        $this->assertStringContainsString('not booked', (string) $p->fresh()->failure_reason);
        $this->actingAs($super)->post("/admin/withdrawals/{$w->id}/reject", ['rejection_reason' => 'x x x'])->assertSessionHasErrors('razorpay');
        $this->artisan('payouts:sync')->assertExitCode(0);
        $this->assertSame(1, DB::table('panel_notifications')->where('type', 'payout.razorpay')->where('title', 'Payout paid but not booked')->count()); /* told once */
        PayoutProfile::query()->where('user_id', $w->user_id)->update(['kyc_status' => 'verified']);
        $this->actingAs($super)->post("/admin/razorpay/payouts/{$p->id}/check")->assertSessionHasNoErrors();
        $this->assertSame('paid', $w->fresh()->status);
        $this->assertTrue($p->fresh()->settled);
        $this->expectRejectRefused($w);
        $this->travelBack();

        /* 2. no answer: never closed, retried with the same key, retries not close together */
        $w2 = $this->withdrawal();
        $this->payoutAnswers = array_fill(0, 3, ['http' => 503]);
        $p2 = app(\App\Services\V2\Payouts\BankPayoutService::class)->payWithdrawal($w2, $super);
        $this->assertSame('error', $p2->status);
        $n = count($this->created);
        $this->artisan('payouts:sync')->assertExitCode(0); /* too soon */
        $this->assertCount($n, $this->created);
        $this->travel(4)->minutes();
        $this->artisan('payouts:sync')->assertExitCode(0);
        $this->assertCount($n + 1, $this->created);
        $this->assertSame($p2->idempotency_key, end($this->created)['key']);
        $this->actingAs($super)->post("/admin/withdrawals/{$w2->id}/razorpay-pay")->assertStatus(422);

        /* 3. a refusal of a RETRY is not taken as "not paid" */
        $this->payoutAnswers = [['http' => 400, 'desc' => 'idempotency key in use']];
        $this->travel(4)->minutes();
        $this->artisan('payouts:sync')->assertExitCode(0);
        $this->assertSame('error', $p2->fresh()->status);
        $this->assertSame('processing', $w2->fresh()->status);

        /* 4. bank changed while there was no answer and no fund account yet: not sent to the new account */
        $w3 = $this->withdrawal();
        $this->payoutAnswers = [];
        $this->contactsDown = true;
        $p3 = app(\App\Services\V2\Payouts\BankPayoutService::class)->payWithdrawal($w3, $super);
        $this->assertSame('error', $p3->status);
        $this->assertNull($p3->fund_account_id);
        $prof = PayoutProfile::query()->where('user_id', $w3->user_id)->first();
        $prof->bank_account_number = '999900001111';
        $prof->saveQuietly();
        $this->travel(5)->minutes();
        app(\App\Services\V2\Payouts\BankPayoutService::class)->sync($p3->fresh(), true);
        $this->assertStringContainsString('bank details changed', (string) $p3->fresh()->failure_reason);
        $this->assertSame('failed', $p3->fresh()->status); /* no fund account = nothing could have been paid */
        $this->assertSame('approved', $w3->fresh()->status);
        $this->contactsDown = false;

        /* 5. Mark Paid by hand is refused inside the lock while Razorpay pays it */
        try {
            app(WithdrawalService::class)->markPaid($w2->fresh(), $super, 'MANUALUTR');
            $this->fail('hand booking while Razorpay pays');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertStringContainsString('being paid through Razorpay', $e->getMessage());
        }
        $this->assertSame('processing', $w2->fresh()->status);
    }

    public function test_clear_refusal_after_an_unanswered_contact_call_is_final_and_close_as_not_paid(): void
    {
        $this->connect();
        $super = $this->user('super_admin');
        $svc = app(\App\Services\V2\Payouts\BankPayoutService::class);

        /* contact call timed out (no payout asked); next try gets a clear 400 -> failed, back to approved */
        $w = $this->withdrawal();
        $this->contactsDown = true;
        $p = $svc->payWithdrawal($w, $super);
        $this->assertSame('error', $p->status);
        $this->assertNull($p->payout_requested_at);
        $this->contactsDown = false;
        $this->payoutAnswers = [['http' => 400, 'desc' => 'Invalid IFSC Code']];
        $svc->sync($p->fresh(), true);
        $this->assertSame('failed', $p->fresh()->status);
        $this->assertSame('approved', $w->fresh()->status);

        /* payout asked, no answer: close needs 15 min + Razorpay saying it has none */
        $this->payoutAnswers = [['http' => 503]];
        $p2 = $svc->payWithdrawal($w->fresh(), $super);
        $this->assertNotNull($p2->payout_requested_at);
        $this->actingAs($super)->post("/admin/razorpay/payouts/{$p2->id}/close")->assertSessionHasErrors('action_otp');
        $this->unlock($super);
        $this->actingAs($super)->post("/admin/razorpay/payouts/{$p2->id}/close")->assertSessionHasErrors('razorpay');
        $this->assertSame('error', $p2->fresh()->status);

        /* Razorpay HAS it -> not closed, followed */
        $this->travel(20)->minutes();
        $this->listItems = [['id' => 'pout_found1', 'status' => 'processing', 'notes' => ['panel_payout' => $p2->public_id]]];
        $this->assertSame('found:processing', $svc->closeNotPaid($p2->fresh(), $super));
        $this->assertSame('processing', $p2->fresh()->status);
        $this->assertSame('pout_found1', $p2->fresh()->razorpay_payout_id);

        /* a resend just now (no answer) restarts the 15 minutes */
        $wx = $this->withdrawal();
        $this->payoutAnswers = [['http' => 503], ['http' => 503]];
        $px = $svc->payWithdrawal($wx, $super);
        $this->listItems = [];
        $this->travel(40)->minutes();
        $svc->sync($px->fresh(), true);
        $this->assertSame('too-soon', $svc->closeNotPaid($px->fresh(), $super));
        $this->assertSame('processing', $wx->fresh()->status);

        /* none at Razorpay -> closed as not paid, request can be paid again */
        $w2 = $this->withdrawal();
        $this->payoutAnswers = [['http' => 503]];
        $p3 = $svc->payWithdrawal($w2, $super);
        $this->listItems = [];
        $this->travel(20)->minutes();
        $this->assertSame('closed', $svc->closeNotPaid($p3->fresh(), $super));
        $this->assertSame('failed', $p3->fresh()->status);
        $this->assertSame('approved', $w2->fresh()->status);

        /* a retry first looks at Razorpay: found there -> not sent again */
        $w3 = $this->withdrawal();
        $this->payoutAnswers = [['http' => 503]];
        $p4 = $svc->payWithdrawal($w3, $super);
        $n = count($this->created);
        $this->listItems = [['id' => 'pout_found2', 'status' => 'processed', 'utr' => 'UTRFOUND2', 'notes' => ['panel_payout' => $p4->public_id]]];
        $svc->sync($p4->fresh(), true);
        $this->assertCount($n, $this->created);
        $this->assertSame('paid', $w3->fresh()->status);
        $this->assertSame('UTRFOUND2', $w3->fresh()->payment_reference);
    }

    public function test_a_webhook_for_a_busy_payout_is_asked_again(): void
    {
        $this->connect();
        $super = $this->user('super_admin');
        $w = $this->withdrawal();
        $p = app(\App\Services\V2\Payouts\BankPayoutService::class)->payWithdrawal($w, $super);
        $lock = \Illuminate\Support\Facades\Cache::lock('bank_payout:'.$p->id, 60);
        $this->assertTrue($lock->get());
        $payload = json_encode(['event' => 'payout.processed', 'payload' => ['payout' => ['entity' => ['id' => $p->razorpay_payout_id, 'status' => 'processed', 'utr' => 'U1']]]]);
        $h = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $payload, 'whsec_test'), 'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_busy'];
        $this->call('POST', '/api/webhooks/razorpayx', [], [], [], $h, $payload)->assertStatus(503);
        $lock->release();
        $this->call('POST', '/api/webhooks/razorpayx', [], [], [], $h, $payload)->assertOk()->assertJson(['ok' => true]);
        $this->assertSame('paid', $w->fresh()->status);
    }

    private function expectRejectRefused($w): void
    {
        $this->actingAs($this->user('super_admin'))->post("/admin/withdrawals/{$w->id}/reject", ['rejection_reason' => 'x x x'])->assertSessionHasErrors();
        $this->assertSame('paid', $w->fresh()->status);
    }

    public function test_test_keys_cannot_pay_on_the_live_site_and_axis_file_rows_are_kept_apart(): void
    {
        $this->connect();
        $super = $this->user('super_admin');
        $w = $this->withdrawal();
        $this->app->detectEnvironment(fn () => 'production');
        config(['app.url' => 'https://www.mixxtune.com']);
        try {
            app(\App\Services\V2\Payouts\BankPayoutService::class)->payWithdrawal($w, $super);
            $this->fail('test keys paid on the live site');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('TEST keys on the live site', $e->getMessage().json_encode($e->errors()));
        }
        $this->assertSame([], $this->created);
        $this->assertSame('pending', $w->fresh()->status);
        $this->app->detectEnvironment(fn () => 'testing');

        /* in an Axis bank file: Razorpay only after saying it was not paid there */
        app(WithdrawalService::class)->approve($w->fresh(), $super);
        $this->actingAs($super)->get('/admin/payouts/axis-file?format=csv')->assertOk();
        $this->assertNotEmpty($w->fresh()->metadata['bank_file_at'] ?? null);
        $this->unlock_after(fn () => $this->actingAs($super)->post("/admin/withdrawals/{$w->id}/razorpay-pay"), $super)->assertSessionHasErrors('razorpay');
        $this->assertSame([], $this->created);
        $this->unlock_after(fn () => $this->actingAs($super)->post("/admin/withdrawals/{$w->id}/razorpay-pay", ['not_in_bank_file' => 1]), $super)->assertSessionHasNoErrors();
        $this->assertCount(1, $this->created);
        /* and a request being paid by Razorpay is not in the next Axis file */
        $csv = $this->actingAs($super)->get('/admin/payouts/axis-file?format=csv')->streamedContent();
        $this->assertStringNotContainsString((string) $w->withdrawal_number.',', $csv);
    }

    /** press, get the e-mailed code, press again */
    private function unlock_after(callable $press, User $super)
    {
        $before = Mail::sent(ActionOtpMail::class)->count();
        $r = $press();
        if (Mail::sent(ActionOtpMail::class)->count() > $before) {
            $this->unlock($super);
            $r = $press();
        }

        return $r;
    }

    public function test_switched_off_razorpay_sends_nothing(): void
    {
        $this->connect(false);
        $super = $this->user('super_admin');
        $w = $this->withdrawal();
        $this->actingAs($super)->post("/admin/withdrawals/{$w->id}/razorpay-pay")->assertSessionHasErrors('razorpay');
        $this->assertSame('pending', $w->fresh()->status);
        $this->assertSame([], $this->created);
        $this->actingAs($super)->get('/admin/withdrawals?status=pending')->assertInertia(fn (Assert $p) => $p->where('razorpay.ready', false)->where('razorpay.can', true));
    }

    public function test_staff_and_a_monthly_salary_sheet_paid_through_razorpay(): void
    {
        $this->connect();
        $super = $this->user('super_admin');
        $this->actingAs($this->user('admin'))->get('/admin/salaries')->assertForbidden();

        $this->actingAs($super)->post('/admin/staff', ['name' => 'Test Staff One', 'designation' => 'COO', 'monthly_salary' => 50000, 'status' => 'active',
            'account_holder_name' => 'Test Staff One', 'bank_account_number' => '222233334444', 'ifsc_code' => 'demo0000002'])->assertSessionHasNoErrors();
        $this->actingAs($super)->post('/admin/staff', ['name' => 'Test Staff Two', 'designation' => 'Head of Finance', 'monthly_salary' => 40000, 'status' => 'active'])->assertSessionHasNoErrors();
        $this->actingAs($super)->post('/admin/staff', ['name' => 'Test Staff Old', 'monthly_salary' => 10000, 'status' => 'inactive'])->assertSessionHasNoErrors();
        $this->actingAs($super)->post('/admin/staff', ['name' => 'Bad', 'monthly_salary' => 1, 'status' => 'active', 'ifsc_code' => 'XX'])->assertSessionHasErrors('ifsc_code');
        $one = StaffMember::query()->where('name', 'Test Staff One')->firstOrFail();
        $two = StaffMember::query()->where('name', 'Test Staff Two')->firstOrFail();
        $this->assertSame('DEMO0000002', $one->ifsc_code);
        $this->assertStringNotContainsString('222233334444', (string) DB::table('staff_members')->where('id', $one->id)->value('bank_account_number'));
        $this->actingAs($super)->get('/admin/salaries')->assertInertia(fn (Assert $p) => $p->component('V2/Admin/Payroll/Index')->where('monthlyTotal', 90000)->where('staff.0.masked_bank_account', '••••4444')->missing('staff.0.bank_account_number'));

        /* sheet: active staff only; extra + cut while draft */
        $this->actingAs($super)->post('/admin/salaries/runs', ['month' => '2026-10'])->assertRedirect();
        $run = SalaryRun::query()->where('month', '2026-10')->firstOrFail();
        $this->assertSame(2, $run->items()->count());
        $this->actingAs($super)->post('/admin/salaries/runs', ['month' => '2026-10'])->assertSessionHasErrors('month');
        $itemOne = $run->items()->where('staff_member_id', $one->id)->firstOrFail();
        $this->actingAs($super)->post("/admin/salaries/items/{$itemOne->id}", ['salary' => 50000, 'additions' => 5000, 'deductions' => 2000, 'note' => 'Bonus, advance cut'])->assertSessionHasNoErrors();
        $this->assertEquals(53000, (float) $itemOne->fresh()->net);

        /* approve & pay (code first): one with a bank goes, one without is reported */
        $this->actingAs($super)->post("/admin/salaries/{$run->id}/pay")->assertSessionHasErrors('action_otp');
        $this->unlock($super);
        $this->actingAs($super)->post("/admin/salaries/{$run->id}/pay")->assertSessionHas('success', fn ($m) => str_contains($m, '1 salary payment(s) sent') && str_contains($m, 'Test Staff Two: No bank account / IFSC'));
        $this->assertSame(5300000, $this->created[0]['body']['amount']);
        $this->assertSame('salary', $this->created[0]['body']['purpose']);
        $this->assertSame('paying', $itemOne->fresh()->status);
        $this->actingAs($super)->post("/admin/salaries/items/{$itemOne->id}", ['salary' => 1])->assertSessionHasErrors('item');

        /* the second one is paid by hand (with a code) */
        $itemTwo = $run->items()->where('staff_member_id', $two->id)->firstOrFail();
        $this->assertSame('failed', $itemTwo->status);
        $this->actingAs($super)->post("/admin/salaries/items/{$itemTwo->id}/paid", ['utr' => 'HANDUTR1'])->assertSessionHasErrors('action_otp');
        $this->unlock($super);
        $this->actingAs($super)->post("/admin/salaries/items/{$itemTwo->id}/paid", ['utr' => 'HANDUTR1'])->assertSessionHasNoErrors();

        /* Razorpay finishes the first -> the sheet is done */
        $this->travel(2)->minutes();
        $this->artisan('payouts:sync')->assertExitCode(0);
        $this->assertSame('paid', $itemOne->fresh()->status);
        $this->assertSame('razorpay', $itemOne->fresh()->paid_how);
        $this->assertSame('done', $run->fresh()->status);
        $this->actingAs($super)->get("/admin/salaries/{$run->id}")->assertInertia(fn (Assert $p) => $p->component('V2/Admin/Payroll/Run')->where('run.paid', 93000)->where('run.status', 'done'));

        /* changing a bank account of staff needs a code */
        $this->actingAs($super)->post("/admin/staff/{$one->id}", ['name' => 'Test Staff One', 'monthly_salary' => 50000, 'status' => 'active', 'account_holder_name' => 'Test Staff One', 'bank_account_number' => '999988887777', 'ifsc_code' => 'DEMO0000002'])->assertSessionHasErrors('action_otp');
        $this->assertSame('222233334444', $one->fresh()->bank_account_number);
    }
}
