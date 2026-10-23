<?php

namespace Tests\Feature\Admin;

use App\Models\Finance\PayoutProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/*
 * MIXXTUNE_KYC_REVIEW_V1
 * All data is generated inside the SQLite :memory: test database.
 */
class KycReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function user(string $role, array $extra = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()], $extra));
    }

    private function profile(User $owner, array $extra = []): PayoutProfile
    {
        return PayoutProfile::query()->create(array_merge([
            'public_id' => (string) Str::ulid(), 'user_id' => $owner->id, 'account_holder_name' => $owner->name,
            'bank_name' => 'Gen Bank', 'bank_account_number' => '001112223334', 'ifsc_code' => 'GENB0001234',
            'pan_number' => 'ABCPK1234L', 'address_line_1' => 'Gen street 1', 'city' => 'Delhi', 'state' => 'DL',
            'postal_code' => '110001', 'country_code' => 'IN', 'kyc_status' => 'submitted',
        ], $extra));
    }

    private function notices(User $u, string $type): int
    {
        return DB::table('panel_notifications')->where('user_id', $u->id)->where('type', $type)->count();
    }

    public function test_review_tabs_checks_and_decisions(): void
    {
        $super = $this->user('super_admin');
        $good = $this->user('label', ['name' => 'Ravi Kumar']);
        $bad = $this->user('label', ['name' => 'Gen Other']);
        $copy = $this->user('artist', ['name' => 'Gen Copycat']);
        $pending = $this->user('artist', ['name' => 'Gen Pending']);
        $none = $this->user('label', ['name' => 'Gen No Profile']);

        $p = $this->profile($good);
        $b = $this->profile($bad, ['ifsc_code' => 'BAD', 'pan_number' => 'XYZ', 'account_holder_name' => 'Someone Else', 'bank_account_number' => '999988887777']);
        $this->profile($copy, ['bank_account_number' => '001112223334', 'kyc_status' => 'rejected']);
        $this->profile($pending, ['kyc_status' => 'pending']);

        $this->actingAs($super)->get('/admin/kyc')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.submitted', 2)->where('counts.pending', 1)->where('counts.missing', 1)->where('counts.rejected', 1)
                ->has('profiles.data', 2));

        $this->actingAs($super)->get('/admin/kyc?status=missing')
            ->assertInertia(fn (Assert $page) => $page->where('missing.data.0.name', 'Gen No Profile'));

        // checks: duplicate bank between good and copy, bad formats
        $checks = app(\App\Services\V2\KycChecks::class);
        $pc = collect($checks->run($p))->keyBy('key');
        $this->assertSame('fail', $pc['dup_bank']['state']);
        $this->assertSame('ok', $pc['holder']['state']);
        $bc = collect($checks->run($b))->keyBy('key');
        $this->assertSame(['fail', 'fail', 'warn'], [$bc['pan']['state'], $bc['ifsc']['state'], $bc['holder']['state']]);

        // verify with failed checks is refused unless override + reason
        $this->actingAs($super)->post('/admin/kyc/'.$p->id.'/verify', ['status' => 'verified'])->assertSessionHasErrors('kyc_notes');
        $this->assertSame('submitted', $p->fresh()->kyc_status);

        $this->actingAs($super)->post('/admin/kyc/'.$p->id.'/verify', ['status' => 'verified', 'override' => true, 'kyc_notes' => 'Gen same family account, checked'])
            ->assertSessionHasNoErrors();
        $this->assertSame('verified', $p->fresh()->kyc_status);
        $this->assertSame('verified', $good->fresh()->kyc_status);
        $this->assertSame(1, $this->notices($good, 'kyc.verified'));

        // reject needs a reason; owner sees it on the KYC page
        $this->actingAs($super)->post('/admin/kyc/'.$b->id.'/verify', ['status' => 'rejected'])->assertSessionHasErrors('kyc_notes');
        $this->actingAs($super)->post('/admin/kyc/'.$b->id.'/verify', ['status' => 'rejected', 'kyc_notes' => 'Gen IFSC wrong'])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->notices($bad, 'kyc.rejected'));
        $this->actingAs($bad)->get('/kyc-profile')->assertInertia(fn (Assert $page) => $page->where('profile.kyc_notes', 'Gen IFSC wrong'));

        // reveal: super admin only, audited
        $this->actingAs($super)->postJson('/admin/kyc/'.$p->id.'/reveal')->assertOk()->assertJsonPath('pan', 'ABCPK1234L');
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'kyc.revealed')->count());
        $manager = $this->user('admin');
        $this->actingAs($manager)->postJson('/admin/kyc/'.$p->id.'/reveal')->assertForbidden();

        // history
        $this->actingAs($super)->getJson('/admin/kyc/'.$p->id.'/history')->assertOk()->assertJsonCount(2, 'items');

        // reminders: not verified owners only
        $this->actingAs($super)->post('/admin/kyc/remind', ['user_ids' => [$pending->id, $none->id, $good->id]])->assertSessionHasNoErrors();
        $this->assertSame([1, 1, 0], [$this->notices($pending, 'kyc.reminder'), $this->notices($none, 'kyc.reminder'), $this->notices($good, 'kyc.reminder')]);

        // owners cannot use any of it
        $this->actingAs($good)->get('/admin/kyc')->assertForbidden();
    }
}
