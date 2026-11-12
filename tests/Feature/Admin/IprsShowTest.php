<?php

namespace Tests\Feature\Admin;

use App\Models\Iprs\IprsRoyaltyLine;
use App\Models\Iprs\IprsUpload;
use App\Models\Iprs\IprsWork;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/*
 * IPRS Phase 2 (view): the statement detail screen groups the royalty lines by
 * work and returns them with their writers. Admin only.
 */
class IprsShowTest extends TestCase
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

    private function seedStatement(): IprsUpload
    {
        $upload = IprsUpload::create([
            'public_id' => (string) Str::ulid(),
            'file_name' => 'iprs.xlsx',
            'file_hash' => hash('sha256', 'x'),
            'uploaded_by' => 1,
            'member_name' => 'MIXX TUNE',
            'member_internal_no' => '8987105',
            'ipi_name_no' => '01268667404',
            'file_total' => 647.3937,
            'parsed_total' => 647.3937,
            'total_lines' => 4,
            'total_works' => 2,
            'status' => 'imported',
            'metadata' => ['validation' => ['within_tolerance' => true, 'file_total' => 647.3937, 'parsed_total' => 647.3937, 'difference' => 0]],
        ]);

        IprsWork::create(['work_int_no' => '29099550', 'title' => 'DHARA 302', 'language' => 'HINDI / HARYANVI', 'credits' => [['name' => 'AMIT, SAINI', 'role' => 'C'], ['name' => 'MIXX TUNE', 'role' => 'E']]]);
        IprsWork::create(['work_int_no' => '28987264', 'title' => 'VIDEO CALL PE BHOLENATH', 'language' => 'HINDI / HARYANVI', 'credits' => [['name' => 'PARVEEN, LATHER', 'role' => 'C']]]);

        foreach ([
            ['29099550', 'MUSERK APRIL', 4, 130.4116],
            ['29099550', 'MUSERK JULY', 7, 513.0451],
            ['28987264', 'MUSERK APRIL', 4, 1.9961],
            ['28987264', 'MUSERK JULY', 7, 1.9409],
        ] as [$work, $source, $month, $amount]) {
            IprsRoyaltyLine::create([
                'public_id' => (string) Str::ulid(),
                'iprs_upload_id' => $upload->id,
                'work_int_no' => $work,
                'title' => $work === '29099550' ? 'DHARA 302' : 'VIDEO CALL PE BHOLENATH',
                'source' => $source,
                'source_month' => $month,
                'amount' => $amount,
                'mapping_status' => 'unmatched',
            ]);
        }

        return $upload;
    }

    public function test_show_groups_lines_by_work_with_writers(): void
    {
        $super = $this->superAdmin();
        $upload = $this->seedStatement();

        $this->actingAs($super)
            ->get("/admin/iprs/uploads/{$upload->public_id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('V2/Admin/Iprs/Show')
                ->where('upload.file_name', 'iprs.xlsx')
                ->has('works', 2)
                ->where('works.0.work_int_no', '28987264') // ordered by work_int_no
                ->has('works.0.lines', 2)
                ->has('works.1.lines', 2)
                ->where('works.1.work_int_no', '29099550')
                ->where('works.1.total_amount', 643.4567)
            );
    }

    public function test_non_admin_cannot_view(): void
    {
        $artist = User::factory()->create(['role' => 'artist', 'account_status' => 'active', 'email_verified_at' => now()]);
        $upload = $this->seedStatement();

        $this->actingAs($artist)
            ->get("/admin/iprs/uploads/{$upload->public_id}")
            ->assertForbidden();
    }
}
