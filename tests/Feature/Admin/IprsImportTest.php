<?php

namespace Tests\Feature\Admin;

use App\Mail\ActionOtpMail;
use App\Models\Iprs\IprsRoyaltyLine;
use App\Models\Iprs\IprsUpload;
use App\Models\Iprs\IprsWork;
use App\Models\User;
use App\Services\Iprs\IprsImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/*
 * IPRS Phase 1: parse an "overseasMemberDetail" statement into clean royalty
 * lines, validate against the file's own TOTAL ROYALTIES, reject duplicates,
 * and gate upload behind the e-mail OTP. Acceptance numbers come from the
 * sample in the module spec.
 */
class IprsImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        $this->withoutVite();
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    /** Build the spec sample workbook ($grandTotal lets a test force a mismatch). */
    private function sampleFile(float $grandTotal = 647.3937, string $name = 'iprs-sample.xlsx'): UploadedFile
    {
        $ss = new Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->setTitle('overseasMemberDetail');
        $S = fn ($cell, $v) => $sh->setCellValueExplicit($cell, $v, DataType::TYPE_STRING);

        $S('A1', 'INTERNAL NO');
        $sh->setCellValue('B1', 8987105);
        $S('C1', 'IPI NAME NO');
        $S('D1', '01268667404');
        $S('A2', 'NAME');
        $S('B2', 'MIXX TUNE,');
        $S('C2', 'IPI BASE NO');
        $S('D2', 'I-007106032-5');

        $hdr = ['WORK INT NO', 'TITLE', 'AV', 'LANGUAGE', 'NAME', 'ROLE', 'SOCIETY', 'OWN', 'COLL', 'POOL', 'SOURCE', 'ROYALTY AMT'];
        $col = 'A';
        foreach ($hdr as $h) {
            $S($col.'4', $h);
            $col++;
        }

        $r = 5;
        // data index -> sheet column: work A, title B, lang D, name E, role F, soc G, own H, coll I, pool J, source K, amt L
        $put = function (array $a) use (&$r, $sh, $S) {
            $strMap = ['A' => 0, 'B' => 1, 'D' => 2, 'E' => 3, 'F' => 4, 'G' => 5, 'J' => 8, 'K' => 9];
            foreach ($strMap as $c => $i) {
                if (($a[$i] ?? '') !== '') {
                    $S($c.$r, (string) $a[$i]);
                }
            }
            if (($a[6] ?? '') !== '') {
                $sh->setCellValue('H'.$r, $a[6]);
            }
            if (($a[7] ?? '') !== '') {
                $sh->setCellValue('I'.$r, $a[7]);
            }
            if (($a[11] ?? null) !== null) {
                $sh->setCellValue('L'.$r, $a[11]);
            }
            $r++;
        };

        // work, title, lang, name, role, soc, own, coll, pool, source, (unused), amt
        $put(['29099550', 'DHARA 302', 'HINDI / HARYANVI', 'AMIT, SAINI', 'C', '036', '25', '33.33', '', '', '', null]);
        $put(['', '', '', 'SAABA, BALIANWALA', 'A', '099', '25', '0', '', '', '', null]);
        $put(['', '', '', 'MIXX TUNE,', 'E', '036', '50', '66.67', 'INTERNET WEBSITE', 'MUSERK APRIL', '', 130.4116]);
        $put(['', '', '', 'MIXX TUNE,', 'E', '036', '50', '66.67', 'INTERNET WEBSITE', 'MUSERK JULY', '', 513.0451]);
        $r++;
        $put(['28987264', 'VIDEO CALL PE BHOLENATH', 'HINDI / HARYANVI', 'PARVEEN, LATHER', 'C', '036', '25', '25', '', '', '', null]);
        $put(['', '', '', 'HARIOM, LATHER', 'A', '036', '25', '25', '', '', '', null]);
        $put(['', '', '', 'MIXX TUNE,', 'E', '036', '50', '50', 'INTERNET WEBSITE', 'MUSERK APRIL', '', 1.9961]);
        $put(['', '', '', 'MIXX TUNE,', 'E', '036', '50', '50', 'INTERNET WEBSITE', 'MUSERK JULY', '', 1.9409]);
        $r++;
        $S('H'.$r, 'TOTAL ROYALTIES');
        $sh->setCellValue('L'.$r, $grandTotal);

        $path = tempnam(sys_get_temp_dir(), 'iprs').'.xlsx';
        (new Xlsx($ss))->save($path);

        return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_parses_sample_into_four_lines_two_works_and_validates(): void
    {
        $super = $this->superAdmin();
        $result = app(IprsImportService::class)->import($this->sampleFile(), $super->id);

        $this->assertSame('imported', $result['status']);
        $upload = $result['upload'];

        $this->assertSame(4, $upload->total_lines);
        $this->assertSame(2, $upload->total_works);
        $this->assertEqualsWithDelta(647.3937, (float) $upload->parsed_total, 0.0001);
        $this->assertEqualsWithDelta(647.3937, (float) $upload->file_total, 0.0001);

        // member block
        $this->assertSame('MIXX TUNE', $upload->member_name);
        $this->assertSame('8987105', $upload->member_internal_no);
        $this->assertSame('01268667404', $upload->ipi_name_no);
        $this->assertSame('I-007106032-5', $upload->ipi_base_no);

        // per-source totals
        $src = $upload->metadata['source_totals'];
        $this->assertEqualsWithDelta(132.4077, $src['MUSERK APRIL'], 0.0001);
        $this->assertEqualsWithDelta(514.986, $src['MUSERK JULY'], 0.0001);

        // works stored
        $this->assertSame(2, IprsWork::count());
        $this->assertDatabaseHas('iprs_works', ['work_int_no' => '29099550', 'title' => 'DHARA 302']);

        // per-work totals from the lines
        $dhara = IprsRoyaltyLine::where('work_int_no', '29099550')->sum('amount');
        $video = IprsRoyaltyLine::where('work_int_no', '28987264')->sum('amount');
        $this->assertEqualsWithDelta(643.4567, (float) $dhara, 0.0001);
        $this->assertEqualsWithDelta(3.937, (float) $video, 0.0001);

        // every line starts unmatched
        $this->assertSame(4, IprsRoyaltyLine::where('mapping_status', 'unmatched')->count());
    }

    public function test_same_file_twice_is_rejected_as_duplicate(): void
    {
        $super = $this->superAdmin();
        $service = app(IprsImportService::class);

        $first = $service->import($this->sampleFile(), $super->id);
        $this->assertSame('imported', $first['status']);

        $second = $service->import($this->sampleFile(), $super->id);
        $this->assertSame('duplicate', $second['status']);

        $this->assertSame(1, IprsUpload::count());
        $this->assertSame(4, IprsRoyaltyLine::count());
    }

    public function test_total_mismatch_is_flagged_needs_review(): void
    {
        $super = $this->superAdmin();
        // file claims a different grand total than the lines sum to
        $result = app(IprsImportService::class)->import($this->sampleFile(999.0000), $super->id);

        $this->assertSame('needs_review', $result['status']);
        $this->assertFalse($result['upload']->metadata['validation']['within_tolerance']);
    }

    public function test_upload_requires_otp_then_succeeds(): void
    {
        $super = $this->superAdmin();

        // first attempt: blocked by the e-mail OTP gate
        $this->actingAs($super)
            ->post('/admin/iprs/uploads', ['report_files' => [$this->sampleFile()]])
            ->assertSessionHasErrors('action_otp');
        $this->assertSame(0, IprsUpload::count());

        // unlock with the e-mailed code
        $code = null;
        Mail::assertSent(ActionOtpMail::class, function (ActionOtpMail $m) use (&$code) {
            $code = $m->otp;
            return true;
        });
        $this->actingAs($super)->postJson('/admin/action-otp/unlock', ['otp' => (string) $code])->assertOk();

        // second attempt goes through
        $this->actingAs($super)
            ->post('/admin/iprs/uploads', ['report_files' => [$this->sampleFile()]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, IprsUpload::count());
        $this->assertSame('imported', DB::table('iprs_uploads')->value('status'));
    }

    public function test_non_admin_cannot_upload(): void
    {
        $artist = User::factory()->create(['role' => 'artist', 'account_status' => 'active', 'email_verified_at' => now()]);

        $this->actingAs($artist)
            ->post('/admin/iprs/uploads', ['report_files' => [$this->sampleFile()]])
            ->assertForbidden();

        $this->assertSame(0, IprsUpload::count());
    }
}
