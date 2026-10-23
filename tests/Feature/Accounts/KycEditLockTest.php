<?php

namespace Tests\Feature\Accounts;

use App\Models\Finance\KycEditUnlock;
use App\Models\Finance\PayoutProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesProductionOnlyTables;
use Tests\TestCase;

/* W80 MIXXTUNE_KYC_EDIT_LOCK */
class KycEditLockTest extends TestCase
{
    use RefreshDatabase;
    use CreatesProductionOnlyTables;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');

        $this->createProductionOnlyTables();
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function details(array $extra = []): array
    {
        return array_merge([
            'account_holder_name' => 'Ramesh Kumar',
            'bank_account_number' => '123456789012',
            'ifsc_code' => 'sbin0001234',
            'bank_name' => 'State Bank',
            'pan_number' => 'abcde1234f',
            'address_line_1' => 'Street 1',
            'city' => 'Rajkot',
            'state' => 'Gujarat',
            'postal_code' => '360001',
        ], $extra);
    }

    public function test_owner_fills_once_sends_and_is_then_locked(): void
    {
        $owner = $this->user('label');

        $this->actingAs($owner)->get('/kyc-profile')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('editState.can_edit', true)->where('editState.state', 'first_time'));

        $this->actingAs($owner)->post('/kyc-profile', $this->details(['ifsc_code' => 'BAD']))->assertSessionHasErrors('ifsc_code');

        $this->actingAs($owner)->post('/kyc-profile', $this->details(['submit' => true]))->assertSessionHasNoErrors();
        $profile = PayoutProfile::query()->where('user_id', $owner->id)->firstOrFail();
        $this->assertSame('submitted', $profile->kyc_status);
        $this->assertSame('SBIN0001234', $profile->ifsc_code);
        $this->assertSame('ABCDE1234F', $profile->pan_number);

        $this->actingAs($owner)->get('/kyc-profile')->assertInertia(fn (Assert $p) => $p
            ->where('editState.can_edit', false)->where('editState.state', 'waiting'));

        $this->actingAs($owner)->post('/kyc-profile', $this->details(['bank_name' => 'Other Bank']))->assertSessionHasErrors('kyc');
        $this->assertSame('State Bank', $profile->fresh()->bank_name);
    }

    public function test_super_admin_opens_with_signed_form_for_one_change(): void
    {
        $owner = $this->user('artist');
        $super = $this->user('super_admin');
        $profile = PayoutProfile::query()->create([
            'public_id' => (string) Str::ulid(), 'user_id' => $owner->id, 'account_holder_name' => 'Asha', 'bank_name' => 'Old Bank',
            'bank_account_number' => '999988887777', 'pan_number' => 'ABCDE1234F',
            'ifsc_code' => 'HDFC0000001', 'address_line_1' => 'A', 'city' => 'B', 'state' => 'C', 'postal_code' => '110001', 'country_code' => 'IN',
            'kyc_status' => 'verified', 'verified_at' => now(),
        ]);

        /* only a super admin, and only with the signed form */
        $this->actingAs($this->user('admin'))->post('/admin/users/'.$owner->id.'/kyc-unlock', ['reason' => 'TKT-1 new bank', 'document' => UploadedFile::fake()->create('form.pdf', 50, 'application/pdf')])->assertForbidden();
        $this->actingAs($super)->post('/admin/users/'.$owner->id.'/kyc-unlock', ['reason' => 'TKT-1 new bank'])->assertSessionHasErrors('document');

        $this->actingAs($super)->post('/admin/users/'.$owner->id.'/kyc-unlock', [
            'reason' => 'TKT-1 new bank',
            'document' => UploadedFile::fake()->create('signed-form.pdf', 50, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $unlock = KycEditUnlock::query()->firstOrFail();
        Storage::disk('local')->assertExists($unlock->document_path);
        $this->actingAs($super)->get('/admin/kyc-unlocks/'.$unlock->id.'/document')->assertOk();
        $this->actingAs($owner)->get('/admin/kyc-unlocks/'.$unlock->id.'/document')->assertForbidden();

        $this->actingAs($super)->get('/admin/users/'.$owner->id.'/edit')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('kycLock.state', 'unlocked')->where('kycLock.history.0.active', true));

        $this->actingAs($owner)->get('/kyc-profile')->assertInertia(fn (Assert $p) => $p
            ->where('editState.can_edit', true)->where('editState.state', 'unlocked'));

        /* a non-bank change goes straight through and uses up the opening */
        $this->actingAs($owner)->post('/kyc-profile', $this->details(['bank_account_number' => '', 'pan_number' => '', 'account_holder_name' => 'Asha', 'ifsc_code' => 'HDFC0000001', 'bank_name' => 'Old Bank Ltd', 'submit' => true]))
            ->assertSessionHasNoErrors();

        $this->assertSame('submitted', $profile->fresh()->kyc_status);
        $this->assertSame('Old Bank Ltd', $profile->fresh()->bank_name);
        $this->assertNotNull($unlock->fresh()->used_at);
        $this->assertSame('999988887777', $profile->fresh()->bank_account_number);

        $this->actingAs($owner)->get('/kyc-profile')->assertInertia(fn (Assert $p) => $p->where('editState.can_edit', false));
    }

    public function test_lock_again_and_create_with_owner_filling_kyc(): void
    {
        $owner = $this->user('label');
        $super = $this->user('super_admin');
        PayoutProfile::query()->create(['public_id' => (string) Str::ulid(), 'user_id' => $owner->id, 'kyc_status' => 'verified', 'country_code' => 'IN']);

        $this->actingAs($super)->post('/admin/users/'.$owner->id.'/kyc-unlock', ['reason' => 'TKT-2', 'document' => UploadedFile::fake()->image('form.jpg')])->assertSessionHasNoErrors();
        $this->actingAs($super)->post('/admin/users/'.$owner->id.'/kyc-unlock/revoke')->assertSessionHasNoErrors();
        $this->assertNotNull(KycEditUnlock::query()->first()->revoked_at);
        $this->actingAs($owner)->get('/kyc-profile')->assertInertia(fn (Assert $p) => $p->where('editState.can_edit', false));
    }
}
