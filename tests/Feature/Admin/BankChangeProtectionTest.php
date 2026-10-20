<?php

namespace Tests\Feature\Admin;

use App\Mail\BankChangeOtpMail;
use App\Mail\BankDetailsChangedMail;
use App\Models\Finance\PayoutProfile;
use App\Models\Finance\WalletAccount;
use App\Models\User;
use App\Services\V2\BankChangeGuard;
use App\Services\V2\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/*
 * MIXXTUNE_BANK_CHANGE_PROTECTION_V1
 * All data here is generated inside the SQLite :memory: test database.
 */
class BankChangeProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Cache::store('file')->flush();

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
            ['invoice', 'invoice.gst_percent', '18', 'decimal'],
            ['invoice', 'invoice.tds_percent', '10', 'decimal'],
        ] as [$group, $key, $value, $type]) {
            DB::table('system_settings')->insert([
                'group' => $group, 'key' => $key, 'value' => $value, 'type' => $type,
                'is_public' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function account(string $role, string $email): User
    {
        return User::factory()->create([
            'role' => $role,
            'email' => $email,
            'account_status' => 'active',
            'kyc_status' => 'verified',
            'email_verified_at' => now(),
        ]);
    }

    private function profile(User $owner, bool $withBank = true): PayoutProfile
    {
        return PayoutProfile::query()->create([
            'public_id' => (string) Str::ulid(),
            'user_id' => $owner->id,
            'account_holder_name' => $withBank ? 'Demo Owner' : null,
            'bank_name' => $withBank ? 'Demo Bank' : null,
            'bank_account_number' => $withBank ? '111122223333' : null,
            'ifsc_code' => $withBank ? 'DEMO0000001' : null,
            'city' => 'Delhi',
            'country_code' => 'IN',
            'kyc_status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    private function quickUpdate(User $admin, User $owner, array $changes)
    {
        return $this->actingAs($admin)
            ->from('/v2/admin/users')
            ->patch('/v2/admin/users/'.$owner->id.'/quick-update', array_merge([
                'name' => $owner->name,
                'email' => $owner->email,
                'account_status' => 'active',
            ], $changes));
    }

    private function sentCode(User $admin): string
    {
        $code = null;
        Mail::assertSent(BankChangeOtpMail::class, function (BankChangeOtpMail $mail) use ($admin, &$code) {
            if ($mail->hasTo($admin->email)) {
                $code = $mail->otp;

                return true;
            }

            return false;
        });

        return (string) $code;
    }

    public function test_bank_change_needs_code_then_saves_with_kyc_reset_notice_and_audit(): void
    {
        Mail::fake();
        $admin = $this->account('super_admin', 'boss@example.test');
        $owner = $this->account('label', 'owner@example.test');
        $this->profile($owner);

        $this->quickUpdate($admin, $owner, ['bank_account_number' => '999988887777'])
            ->assertSessionHasErrors('bank_change_otp');

        $profile = PayoutProfile::query()->where('user_id', $owner->id)->first();
        $this->assertSame('111122223333', $profile->bank_account_number, 'nothing saved without the code');
        $this->assertSame('verified', $profile->kyc_status);
        Mail::assertNotSent(BankDetailsChangedMail::class);

        $code = $this->sentCode($admin);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $wrong = $code === '000000' ? '111111' : '000000';
        $this->actingAs($admin)->postJson('/v2/admin/bank-change/unlock', ['otp' => $wrong])
            ->assertStatus(422)->assertJsonValidationErrors('otp');

        $this->actingAs($admin)->postJson('/v2/admin/bank-change/unlock', ['otp' => $code])
            ->assertOk()->assertJson(['ok' => true]);

        $this->quickUpdate($admin, $owner, ['bank_account_number' => '999988887777'])
            ->assertSessionHasNoErrors();

        $profile->refresh();
        $this->assertSame('999988887777', $profile->bank_account_number);
        $this->assertSame('submitted', $profile->kyc_status, 'KYC must be verified again');
        $this->assertNull($profile->verified_at);
        $this->assertSame('submitted', $owner->fresh()->kyc_status);

        $audit = DB::table('audit_logs')->where('action', BankChangeGuard::AUDIT_ACTION)->first();
        $this->assertNotNull($audit);
        $this->assertSame((int) $profile->id, (int) $audit->auditable_id);
        $this->assertStringNotContainsString('111122223333', $audit->old_values.$audit->new_values, 'full numbers never stored');
        $this->assertStringContainsString('3333', $audit->old_values);
        $this->assertStringContainsString('7777', $audit->new_values);

        Mail::assertSent(BankDetailsChangedMail::class, fn ($mail) => $mail->hasTo($owner->email));

        // the unlock was used up: the next bank change needs a new code
        $this->quickUpdate($admin, $owner, ['ifsc_code' => 'DEMO0000002'])
            ->assertSessionHasErrors('bank_change_otp');
        $this->assertSame('DEMO0000001', $profile->fresh()->ifsc_code);
    }

    public function test_first_bank_details_and_other_fields_need_no_code(): void
    {
        Mail::fake();
        $admin = $this->account('super_admin', 'boss@example.test');
        $owner = $this->account('label', 'owner@example.test');
        $this->profile($owner, false);

        $this->quickUpdate($admin, $owner, [
            'account_holder_name' => 'Demo Owner',
            'bank_account_number' => '555566667777',
            'ifsc_code' => 'DEMO0000009',
        ])->assertSessionHasNoErrors();

        $profile = PayoutProfile::query()->where('user_id', $owner->id)->first();
        $this->assertSame('555566667777', $profile->bank_account_number);
        $this->assertSame('verified', $profile->kyc_status);

        // same bank values (different spacing / case) and a city change: not a bank change
        $this->quickUpdate($admin, $owner, ['ifsc_code' => 'demo0000009', 'city' => 'Mumbai'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Mumbai', $profile->fresh()->city);

        Mail::assertNotSent(BankChangeOtpMail::class);
        Mail::assertNotSent(BankDetailsChangedMail::class);
        $this->assertSame(0, DB::table('audit_logs')->where('action', BankChangeGuard::AUDIT_ACTION)->count());
    }

    public function test_code_is_for_one_owner_and_one_person_only(): void
    {
        Mail::fake();
        $admin = $this->account('super_admin', 'boss@example.test');
        $other = $this->account('super_admin', 'other@example.test');
        $owner = $this->account('label', 'owner@example.test');
        $second = $this->account('label', 'second@example.test');
        $this->profile($owner);
        $this->profile($second);

        $this->quickUpdate($admin, $owner, ['upi_id' => 'new@upi'])->assertSessionHasErrors('bank_change_otp');
        $code = $this->sentCode($admin);

        // another admin cannot use it
        $this->actingAs($other)->postJson('/v2/admin/bank-change/unlock', ['otp' => $code])->assertStatus(422);

        $this->actingAs($admin)->postJson('/v2/admin/bank-change/unlock', ['otp' => $code])->assertOk();

        // unlocked for $owner only, not for $second
        $this->quickUpdate($admin, $second, ['upi_id' => 'second@upi'])->assertSessionHasErrors('bank_change_otp');
        $this->assertNull(PayoutProfile::query()->where('user_id', $second->id)->value('upi_id'));
    }

    public function test_mark_paid_waits_48_hours_and_for_kyc_after_a_bank_change(): void
    {
        Mail::fake();
        $admin = $this->account('super_admin', 'boss@example.test');
        $owner = $this->account('artist', 'artist@example.test');
        $profile = $this->profile($owner);

        WalletAccount::query()->create([
            'public_id' => (string) Str::ulid(), 'user_id' => $owner->id, 'artist_id' => null, 'label_id' => null,
            'currency' => 'INR', 'available_balance' => 5000, 'pending_balance' => 0,
            'lifetime_credits' => 5000, 'lifetime_debits' => 0, 'status' => 'active',
        ]);

        $service = app(WithdrawalService::class);
        $withdrawal = $service->create($owner, 1500, 'bank', 'Demo withdrawal.');
        $withdrawal = $service->approve($withdrawal, $admin);

        // bank change (unlocked) after the withdrawal was approved
        $this->quickUpdate($admin, $owner, ['bank_account_number' => '444455556666'])->assertSessionHasErrors('bank_change_otp');
        $this->actingAs($admin)->postJson('/v2/admin/bank-change/unlock', ['otp' => $this->sentCode($admin)])->assertOk();
        $this->quickUpdate($admin, $owner, ['bank_account_number' => '444455556666'])->assertSessionHasNoErrors();

        try {
            $service->markPaid($withdrawal->fresh(), $admin, 'UTR-HOLD-1');
            $this->fail('paid must be blocked during the 48h hold');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('on hold', implode(' ', $e->errors()['payment_reference'] ?? []));
        }

        $this->travel(49)->hours();

        try {
            $service->markPaid($withdrawal->fresh(), $admin, 'UTR-HOLD-2');
            $this->fail('paid must wait for KYC verification');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('KYC', implode(' ', $e->errors()['payment_reference'] ?? []));
        }

        $profile->refresh()->forceFill(['kyc_status' => 'verified', 'verified_at' => now()])->save();

        $paid = $service->markPaid($withdrawal->fresh(), $admin, 'UTR-OK-3');
        $this->assertSame('paid', $paid->status);
    }
}
