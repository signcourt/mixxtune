<?php

namespace Tests\Feature\Admin;

use App\Models\Finance\PayoutProfile;
use App\Models\Finance\WalletAccount;
use App\Models\Finance\WithdrawalRequest;
use App\Models\User;
use App\Services\V2\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ConfirmsActionOtp;
use Tests\TestCase;

/*
 * MIXXTUNE_WITHDRAWALS_V2
 * All data is generated inside the SQLite :memory: test database.
 */
class WithdrawalsReviewTest extends TestCase
{
    use RefreshDatabase;
    use ConfirmsActionOtp;

    protected function setUp(): void
    {
        parent::setUp();
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

        $this->setting('company', 'company.legal_name', 'Test Distribution Company', 'string');
        $this->setting('company', 'company.address', 'Test Company Address', 'string');
        $this->setting('invoice', 'invoice.gst_percent', '0', 'decimal');
        $this->setting('invoice', 'invoice.tds_percent', '10', 'decimal');
    }

    private function setting(string $group, string $key, string $value, string $type): void
    {
        DB::table('system_settings')->updateOrInsert(['key' => $key], [
            'group' => $group, 'value' => $value, 'type' => $type,
            'is_public' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Cache::forget("system_setting:{$key}");
    }

    private function makeUser(string $role, array $extra = []): User
    {
        $id = DB::table('users')->insertGetId(array_merge([
            'name' => ucfirst($role).' '.Str::random(4),
            'email' => Str::lower(Str::random(10)).'@example.test',
            'role' => $role,
            'account_status' => 'active',
            'kyc_status' => 'verified',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));

        return User::query()->findOrFail($id);
    }

    private function owner(?string $pan = 'ABCDE1234F', string $kyc = 'verified', float $wallet = 10000): User
    {
        $owner = $this->makeUser('artist');
        PayoutProfile::query()->create([
            'public_id' => (string) Str::ulid(), 'user_id' => $owner->id, 'account_holder_name' => 'Payee '.$owner->id,
            'bank_name' => 'Demo Bank', 'bank_account_number' => '0011122223333', 'ifsc_code' => 'DEMO0000001',
            'pan_number' => $pan, 'country_code' => 'IN', 'kyc_status' => $kyc, 'verified_at' => now(),
        ]);
        WalletAccount::query()->create([
            'public_id' => (string) Str::ulid(), 'user_id' => $owner->id, 'artist_id' => null, 'label_id' => null,
            'currency' => 'INR', 'available_balance' => $wallet, 'pending_balance' => 0,
            'lifetime_credits' => $wallet, 'lifetime_debits' => 0, 'status' => 'active',
        ]);

        return $owner;
    }

    private function notices(User $u, string $type): int
    {
        return DB::table('panel_notifications')->where('user_id', $u->id)->where('type', $type)->count();
    }

    public function test_list_search_totals_problems_and_owner_is_told(): void
    {
        $super = $this->makeUser('super_admin');
        $ok = $this->owner('ABCDE1234F', 'verified', 10000);
        $notVerified = $this->owner('ABCDE1234G', 'verified', 8000);

        $service = app(WithdrawalService::class);
        $w1 = $service->create($ok, 2000, 'bank');
        $w2 = $service->create($notVerified, 3000, 'bank');

        // KYC removed after the request was made
        PayoutProfile::query()->where('user_id', $notVerified->id)->update(['kyc_status' => 'rejected']);

        $this->actingAs($super)->get('/v2/admin/withdrawals')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('counts.pending', 2)->where('totals.pending', 5000)
                ->where('withdrawals.data.1.ready', true)
                ->where('withdrawals.data.0.problems.0', 'KYC rejected'));

        $this->actingAs($super)->get('/v2/admin/withdrawals?status=all&search='.urlencode($ok->email))
            ->assertInertia(fn (Assert $p) => $p->where('withdrawals.total', 1));

        // approve refused while KYC is not verified
        $this->actingAs($super)->post('/v2/admin/withdrawals/'.$w2->id.'/approve')->assertSessionHasErrors('withdrawal');
        $this->assertSame('pending', $w2->fresh()->status);

        // approve -> owner told
        $this->actingAs($super)->post('/v2/admin/withdrawals/'.$w1->id.'/approve')->assertSessionHasNoErrors();
        $this->assertSame('approved', $w1->fresh()->status);
        $this->assertSame(1, $this->notices($ok, 'withdrawal.approved'));

        // mark paid (e-mailed code) -> owner told with UTR
        $this->viaActionOtp($super, fn () => $this->actingAs($super)->post('/v2/admin/withdrawals/'.$w1->id.'/paid', ['payment_reference' => 'UTRGEN123']))
            ->assertSessionHasNoErrors();
        $this->assertSame('paid', $w1->fresh()->status);
        $this->assertSame(1, $this->notices($ok, 'withdrawal.paid'));

        // a paid request can never be rejected (money would be returned twice)
        $this->actingAs($super)->post('/v2/admin/withdrawals/'.$w1->id.'/reject', ['rejection_reason' => 'Gen too late'])->assertStatus(422);

        // reject -> money back + owner told
        $this->actingAs($super)->post('/v2/admin/withdrawals/'.$w2->id.'/reject', ['rejection_reason' => 'Gen KYC rejected'])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->notices($notVerified, 'withdrawal.rejected'));
        $this->assertEquals(8000.0, (float) DB::table('wallets')->where('user_id', $notVerified->id)->value('available_balance'));
    }

    public function test_number_never_repeats_after_a_delete_and_no_profile_cannot_be_paid(): void
    {
        $super = $this->makeUser('super_admin');
        $a = $this->owner('ABCDE1234F', 'verified', 10000);
        $b = $this->owner('ABCDE1234H', 'verified', 10000);
        $service = app(WithdrawalService::class);

        $first = $service->create($a, 1000, 'bank');
        $second = $service->create($b, 1000, 'bank');

        $this->actingAs($super)->delete('/v2/admin/withdrawals/'.$first->id)->assertSessionHasNoErrors();
        $this->assertSame(1, $this->notices($a, 'withdrawal.deleted'));

        $third = $service->create($a, 1500, 'bank');
        $this->assertNotSame($second->withdrawal_number, $third->withdrawal_number);

        // profile removed after approval -> Mark Paid refused
        $service->approve($second->fresh(), $super);
        PayoutProfile::query()->where('user_id', $b->id)->delete();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\V2\BankChangeGuard::class)->assertPayoutAllowed($b->id);
    }
}
