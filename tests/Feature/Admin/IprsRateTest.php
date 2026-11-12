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
use App\Services\Iprs\IprsRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * IPRS royalty rates: per-owner, per-type owner-share %, resolved
 * specific -> global default -> digital share -> 100, and used at publish.
 */
class IprsRateTest extends TestCase
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

    private function label(float $digital = 80): Label
    {
        $owner = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);

        return Label::factory()->create(['user_id' => $owner->id, 'parent_label_id' => null, 'revenue_share_percentage' => $digital]);
    }

    public function test_rate_resolution_order(): void
    {
        $rates = app(IprsRateService::class);
        $label = $this->label(80);

        // 1. no rate -> digital share (80)
        $this->assertEqualsWithDelta(80.0, $rates->ownerShare('label', $label->id, 'publishing'), 0.001);

        // 2. global default -> 60
        $rates->setRate('default', 0, 'publishing', 60);
        $this->assertEqualsWithDelta(60.0, $rates->ownerShare('label', $label->id, 'publishing'), 0.001);

        // 3. owner-specific -> 70 (wins)
        $rates->setRate('label', $label->id, 'publishing', 70);
        $this->assertEqualsWithDelta(70.0, $rates->ownerShare('label', $label->id, 'publishing'), 0.001);

        // different type still falls back to digital
        $this->assertEqualsWithDelta(80.0, $rates->ownerShare('label', $label->id, 'ppl'), 0.001);
    }

    public function test_publish_uses_the_label_publishing_rate(): void
    {
        $label = $this->label(80);
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

        // owner keeps 70% for publishing
        app(IprsRateService::class)->setRate('label', $label->id, 'publishing', 70);

        $r = app(IprsPublishService::class)->publish($upload->fresh(), 1);

        $this->assertEqualsWithDelta(70.0, $r['credited'], 0.001);
        $this->assertEqualsWithDelta(70.0, (float) DB::table('wallets')->where('label_id', $label->id)->value('available_balance'), 0.001);
        $this->assertEqualsWithDelta(70.0, (float) DB::table('royalty_ledgers')->where('source_type', 'iprs_royalty')->value('payable_amount'), 0.001);
    }

    public function test_save_rate_endpoint_upserts_and_clears(): void
    {
        $super = $this->superAdmin();
        $label = $this->label(80);

        $this->actingAs($super)->post('/admin/iprs/rates', [
            'owner_type' => 'label', 'owner_id' => $label->id, 'royalty_type' => 'publishing', 'owner_share_percentage' => 65,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('iprs_royalty_rates', ['owner_type' => 'label', 'owner_id' => $label->id, 'royalty_type' => 'publishing', 'owner_share_percentage' => 65.00]);

        // clear
        $this->actingAs($super)->post('/admin/iprs/rates', [
            'owner_type' => 'label', 'owner_id' => $label->id, 'royalty_type' => 'publishing', 'owner_share_percentage' => null,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('iprs_royalty_rates', ['owner_type' => 'label', 'owner_id' => $label->id, 'royalty_type' => 'publishing']);
    }

    public function test_non_admin_cannot_save_rate(): void
    {
        $artist = User::factory()->create(['role' => 'artist', 'account_status' => 'active', 'email_verified_at' => now()]);

        $this->actingAs($artist)->post('/admin/iprs/rates', [
            'owner_type' => 'default', 'owner_id' => 0, 'royalty_type' => 'publishing', 'owner_share_percentage' => 50,
        ])->assertForbidden();
    }
}
