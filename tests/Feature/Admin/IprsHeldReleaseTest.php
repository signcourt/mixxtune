<?php

namespace Tests\Feature\Admin;

use App\Mail\ActionOtpMail;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\Iprs\IprsHeldRevenue;
use App\Models\Iprs\IprsRoyaltyLine;
use App\Models\Iprs\IprsUpload;
use App\Models\User;
use App\Services\Iprs\IprsMappingService;
use App\Services\Iprs\IprsPublishService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * IPRS hold + release: a payment is held (not credited) when the owner has no
 * user or an inactive one, and released to the wallet once an active user exists.
 */
class IprsHeldReleaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->withoutVite();
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    /** A mapped statement whose label has the given user (null = no user). */
    private function mappedStatement(?User $labelUser): Label
    {
        $label = Label::factory()->create(['user_id' => $labelUser?->id, 'parent_label_id' => null, 'revenue_share_percentage' => 80]);
        $release = Release::factory()->create(['label_id' => $label->id, 'artist_id' => null, 'upc' => '890000000014', 'status' => 'live']);
        $track = Track::factory()->create(['release_id' => $release->id, 'title' => 'DHARA 302', 'isrc' => 'INMXT2600001']);

        $upload = IprsUpload::create([
            'public_id' => (string) Str::ulid(), 'file_name' => 'iprs.xlsx', 'file_hash' => hash('sha256', Str::random()),
            'uploaded_by' => 1, 'file_total' => 100, 'parsed_total' => 100, 'total_lines' => 1, 'total_works' => 1,
            'status' => 'imported', 'royalty_type' => 'publishing', 'metadata' => ['validation' => ['within_tolerance' => true]],
        ]);
        IprsRoyaltyLine::create([
            'public_id' => (string) Str::ulid(), 'iprs_upload_id' => $upload->id, 'work_int_no' => '29099550',
            'title' => 'DHARA 302', 'source' => 'MUSERK JULY', 'source_month' => 7, 'amount' => 100, 'mapping_status' => 'unmatched',
        ]);
        app(IprsMappingService::class)->mapWork('29099550', $track->id);

        return $label;
    }

    public function test_no_user_owner_is_held_not_credited(): void
    {
        $label = $this->mappedStatement(null);
        $upload = IprsUpload::first();

        $r = app(IprsPublishService::class)->publish($upload, 1);

        $this->assertSame(0, $r['published']);
        $this->assertSame(1, $r['held']);
        $this->assertEqualsWithDelta(80.0, $r['held_amount'], 0.001);
        $this->assertSame(0, (int) DB::table('royalty_ledgers')->where('source_type', 'iprs_royalty')->count());
        $this->assertSame(0, (int) DB::table('wallets')->where('label_id', $label->id)->count());
        $this->assertDatabaseHas('iprs_held_revenues', ['owner_type' => 'label', 'owner_id' => $label->id, 'reason' => 'owner_user_missing', 'status' => 'held']);
    }

    public function test_inactive_user_owner_is_held(): void
    {
        $inactive = User::factory()->create(['role' => 'label', 'account_status' => 'inactive', 'email_verified_at' => now()]);
        $label = $this->mappedStatement($inactive);

        $r = app(IprsPublishService::class)->publish(IprsUpload::first(), 1);

        $this->assertSame(1, $r['held']);
        $this->assertDatabaseHas('iprs_held_revenues', ['owner_id' => $label->id, 'reason' => 'owner_user_inactive', 'status' => 'held']);
    }

    public function test_release_credits_wallet_once_user_active(): void
    {
        $label = $this->mappedStatement(null); // held, no user
        app(IprsPublishService::class)->publish(IprsUpload::first(), 1);

        // owner now gets an active user
        $user = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label->update(['user_id' => $user->id]);

        $r = app(IprsPublishService::class)->release(1);

        $this->assertSame(1, $r['released']);
        $this->assertEqualsWithDelta(80.0, $r['credited'], 0.001);
        $this->assertEqualsWithDelta(80.0, (float) DB::table('wallets')->where('label_id', $label->id)->value('available_balance'), 0.001);
        $this->assertDatabaseHas('iprs_held_revenues', ['owner_id' => $label->id, 'status' => 'released']);
    }

    public function test_release_keeps_holding_when_still_no_active_user(): void
    {
        $this->mappedStatement(null);
        app(IprsPublishService::class)->publish(IprsUpload::first(), 1);

        $r = app(IprsPublishService::class)->release(1);

        $this->assertSame(0, $r['released']);
        $this->assertSame(1, $r['still_held']);
    }

    public function test_release_endpoint_requires_otp(): void
    {
        $super = $this->superAdmin();
        $label = $this->mappedStatement(null);
        app(IprsPublishService::class)->publish(IprsUpload::first(), 1);
        $user = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label->update(['user_id' => $user->id]);

        $this->actingAs($super)->post('/admin/iprs/held/release', [])->assertSessionHasErrors('action_otp');
        $this->assertSame('held', IprsHeldRevenue::first()->status);

        $code = null;
        Mail::assertSent(ActionOtpMail::class, function (ActionOtpMail $m) use (&$code) {
            $code = $m->otp;
            return true;
        });
        $this->actingAs($super)->postJson('/admin/action-otp/unlock', ['otp' => (string) $code])->assertOk();

        $this->actingAs($super)->post('/admin/iprs/held/release', [])->assertSessionHasNoErrors();
        $this->assertSame('released', IprsHeldRevenue::first()->status);
    }
}
