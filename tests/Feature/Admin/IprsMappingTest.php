<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\Iprs\IprsRoyaltyLine;
use App\Models\Iprs\IprsUpload;
use App\Models\User;
use App\Services\Iprs\IprsMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 * IPRS Phase 2b: mapping a work to a track stores the work number on the track,
 * matches the work's lines (resolving the owner), and makes future statements
 * auto-map by work number.
 */
class IprsMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function catalogueTrack(string $title, string $isrc): Track
    {
        $owner = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create(['user_id' => $owner->id, 'parent_label_id' => null, 'revenue_share_percentage' => 80]);
        $release = Release::factory()->create(['label_id' => $label->id, 'artist_id' => null, 'upc' => '890000000014', 'status' => 'live']);

        return Track::factory()->create(['release_id' => $release->id, 'title' => $title, 'isrc' => $isrc]);
    }

    private function seedUpload(string $workNo, string $title): IprsUpload
    {
        $upload = IprsUpload::create([
            'public_id' => (string) Str::ulid(),
            'file_name' => 'iprs.xlsx',
            'file_hash' => hash('sha256', Str::random()),
            'uploaded_by' => 1,
            'member_name' => 'MIXX TUNE',
            'file_total' => 100,
            'parsed_total' => 100,
            'total_lines' => 1,
            'total_works' => 1,
            'status' => 'imported',
            'metadata' => ['validation' => ['within_tolerance' => true]],
        ]);

        IprsRoyaltyLine::create([
            'public_id' => (string) Str::ulid(),
            'iprs_upload_id' => $upload->id,
            'work_int_no' => $workNo,
            'title' => $title,
            'source' => 'MUSERK JULY',
            'source_month' => 7,
            'amount' => 100,
            'mapping_status' => 'unmatched',
        ]);

        return $upload;
    }

    public function test_map_work_stores_work_no_on_track_and_matches_lines(): void
    {
        $track = $this->catalogueTrack('DHARA 302', 'INMXT2600001');
        $this->seedUpload('29099550', 'DHARA 302');
        $ownerUserId = $track->release->label->user_id;

        $result = app(IprsMappingService::class)->mapWork('29099550', $track->id);

        $this->assertSame('29099550', $track->fresh()->iprs_work_no);
        $this->assertSame(1, $result['matched_lines']);

        $line = IprsRoyaltyLine::where('work_int_no', '29099550')->first();
        $this->assertSame('matched', $line->mapping_status);
        $this->assertSame($track->id, $line->track_id);
        $this->assertSame('INMXT2600001', $line->isrc);
        $this->assertSame($ownerUserId, $line->client_id);
    }

    public function test_second_statement_auto_maps_by_work_number(): void
    {
        $track = $this->catalogueTrack('DHARA 302', 'INMXT2600001');
        $this->seedUpload('29099550', 'DHARA 302');
        app(IprsMappingService::class)->mapWork('29099550', $track->id);

        // a brand-new statement with the same work number
        $upload2 = $this->seedUpload('29099550', 'DHARA 302');
        $mappedWorks = app(IprsMappingService::class)->autoMapUpload($upload2);

        $this->assertSame(1, $mappedWorks);
        $line = IprsRoyaltyLine::where('iprs_upload_id', $upload2->id)->first();
        $this->assertSame('matched', $line->mapping_status);
        $this->assertSame($track->id, $line->track_id);
    }

    public function test_unmap_reverts_track_and_lines(): void
    {
        $track = $this->catalogueTrack('DHARA 302', 'INMXT2600001');
        $this->seedUpload('29099550', 'DHARA 302');
        $service = app(IprsMappingService::class);
        $service->mapWork('29099550', $track->id);

        $service->unmapWork('29099550');

        $this->assertNull($track->fresh()->iprs_work_no);
        $line = IprsRoyaltyLine::where('work_int_no', '29099550')->first();
        $this->assertSame('unmatched', $line->mapping_status);
        $this->assertNull($line->track_id);
        $this->assertNull($line->client_id);
    }

    public function test_search_endpoint_finds_track_by_title_and_isrc(): void
    {
        $super = $this->superAdmin();
        $this->catalogueTrack('DHARA 302', 'INMXT2600001');

        $this->actingAs($super)->getJson('/admin/iprs/tracks/search?q=DHARA')
            ->assertOk()
            ->assertJsonPath('tracks.0.title', 'DHARA 302')
            ->assertJsonPath('tracks.0.isrc', 'INMXT2600001')
            ->assertJsonStructure(['tracks' => [['primary_artist', 'composer', 'author', 'music_director']]]);

        $this->actingAs($super)->getJson('/admin/iprs/tracks/search?q=INMXT2600001')
            ->assertOk()
            ->assertJsonPath('tracks.0.title', 'DHARA 302');
    }

    public function test_map_endpoint_matches_the_work(): void
    {
        $super = $this->superAdmin();
        $track = $this->catalogueTrack('DHARA 302', 'INMXT2600001');
        $upload = $this->seedUpload('29099550', 'DHARA 302');

        $this->actingAs($super)
            ->post("/admin/iprs/uploads/{$upload->public_id}/map", ['work_int_no' => '29099550', 'track_id' => $track->id])
            ->assertSessionHasNoErrors();

        $this->assertSame('matched', IprsRoyaltyLine::where('work_int_no', '29099550')->value('mapping_status'));
        $this->assertSame('29099550', $track->fresh()->iprs_work_no);
    }

    public function test_non_admin_cannot_map(): void
    {
        $artist = User::factory()->create(['role' => 'artist', 'account_status' => 'active', 'email_verified_at' => now()]);
        $track = $this->catalogueTrack('DHARA 302', 'INMXT2600001');
        $upload = $this->seedUpload('29099550', 'DHARA 302');

        $this->actingAs($artist)
            ->post("/admin/iprs/uploads/{$upload->public_id}/map", ['work_int_no' => '29099550', 'track_id' => $track->id])
            ->assertForbidden();

        $this->assertSame('unmatched', IprsRoyaltyLine::where('work_int_no', '29099550')->value('mapping_status'));
    }
}
