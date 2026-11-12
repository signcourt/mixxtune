<?php

namespace Tests\Feature\Admin;

use App\Mail\ActionOtpMail;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
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
 * IPRS Phase 3: publishing a mapped statement credits each owner's wallet at
 * their royalty share (same rate as digital), through the shared ledger/wallet
 * primitive, and never double-credits.
 */
class IprsPublishTest extends TestCase
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

    /** Track owned by a label whose royalty share is 80%. */
    private function mappedStatement(): array
    {
        $owner = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create(['user_id' => $owner->id, 'parent_label_id' => null, 'revenue_share_percentage' => 80]);
        $release = Release::factory()->create(['label_id' => $label->id, 'artist_id' => null, 'upc' => '890000000014', 'status' => 'live']);
        $track = Track::factory()->create(['release_id' => $release->id, 'title' => 'DHARA 302', 'isrc' => 'INMXT2600001']);

        $upload = IprsUpload::create([
            'public_id' => (string) Str::ulid(),
            'file_name' => 'iprs.xlsx',
            'file_hash' => hash('sha256', Str::random()),
            'uploaded_by' => 1,
            'member_name' => 'MIXX TUNE',
            'file_total' => 100, 'parsed_total' => 100, 'total_lines' => 1, 'total_works' => 1,
            'status' => 'imported',
            'metadata' => ['validation' => ['within_tolerance' => true]],
        ]);
        IprsRoyaltyLine::create([
            'public_id' => (string) Str::ulid(),
            'iprs_upload_id' => $upload->id,
            'work_int_no' => '29099550', 'title' => 'DHARA 302',
            'source' => 'MUSERK JULY', 'source_month' => 7, 'amount' => 100,
            'mapping_status' => 'unmatched',
        ]);

        app(IprsMappingService::class)->mapWork('29099550', $track->id);

        return ['upload' => $upload->fresh(), 'label' => $label, 'owner' => $owner, 'track' => $track];
    }

    public function test_publish_credits_owner_wallet_at_their_share(): void
    {
        ['upload' => $upload, 'label' => $label] = $this->mappedStatement();

        $r = app(IprsPublishService::class)->publish($upload, 1);

        $this->assertSame(1, $r['published']);
        $this->assertEqualsWithDelta(80.0, $r['credited'], 0.0001); // 100 * 80%

        $ledger = DB::table('royalty_ledgers')->where('source_type', 'iprs_royalty')->first();
        $this->assertNotNull($ledger);
        $this->assertEqualsWithDelta(100.0, (float) $ledger->gross_amount, 0.0001);
        $this->assertEqualsWithDelta(80.0, (float) $ledger->payable_amount, 0.0001);
        $this->assertSame('wallet_credited', $ledger->status);

        $balance = (float) DB::table('wallets')->where('label_id', $label->id)->value('available_balance');
        $this->assertEqualsWithDelta(80.0, $balance, 0.0001);

        $this->assertSame('distributed', $upload->fresh()->status);
    }

    public function test_republish_does_not_double_credit(): void
    {
        ['upload' => $upload, 'label' => $label] = $this->mappedStatement();
        $service = app(IprsPublishService::class);

        $service->publish($upload, 1);
        $r2 = $service->publish($upload->fresh(), 1);

        $this->assertSame(0, $r2['published']);
        $this->assertSame(1, $r2['already']);
        $this->assertSame(1, DB::table('royalty_ledgers')->where('source_type', 'iprs_royalty')->count());
        $this->assertEqualsWithDelta(80.0, (float) DB::table('wallets')->where('label_id', $label->id)->value('available_balance'), 0.0001);
    }

    public function test_publish_endpoint_requires_otp(): void
    {
        $super = $this->superAdmin();
        ['upload' => $upload] = $this->mappedStatement();

        $this->actingAs($super)
            ->post("/admin/iprs/uploads/{$upload->public_id}/publish")
            ->assertSessionHasErrors('action_otp');
        $this->assertSame(0, DB::table('royalty_ledgers')->where('source_type', 'iprs_royalty')->count());

        $code = null;
        Mail::assertSent(ActionOtpMail::class, function (ActionOtpMail $m) use (&$code) {
            $code = $m->otp;
            return true;
        });
        $this->actingAs($super)->postJson('/admin/action-otp/unlock', ['otp' => (string) $code])->assertOk();

        $this->actingAs($super)
            ->post("/admin/iprs/uploads/{$upload->public_id}/publish")
            ->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('royalty_ledgers')->where('source_type', 'iprs_royalty')->count());
    }

    public function test_unmatched_lines_are_skipped(): void
    {
        $upload = IprsUpload::create([
            'public_id' => (string) Str::ulid(), 'file_name' => 'x.xlsx', 'file_hash' => hash('sha256', Str::random()),
            'uploaded_by' => 1, 'file_total' => 50, 'parsed_total' => 50, 'total_lines' => 1, 'total_works' => 1,
            'status' => 'imported', 'metadata' => ['validation' => ['within_tolerance' => true]],
        ]);
        IprsRoyaltyLine::create([
            'public_id' => (string) Str::ulid(), 'iprs_upload_id' => $upload->id, 'work_int_no' => '99999999',
            'title' => 'UNMAPPED', 'source' => 'MUSERK JULY', 'source_month' => 7, 'amount' => 50, 'mapping_status' => 'unmatched',
        ]);

        $r = app(IprsPublishService::class)->publish($upload, 1);

        $this->assertSame(0, $r['published']);
        $this->assertSame(1, $r['skipped_unmatched']);
        $this->assertSame(0, DB::table('royalty_ledgers')->where('source_type', 'iprs_royalty')->count());
    }
}
