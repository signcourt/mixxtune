<?php

namespace Tests\Feature\Admin;

use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/* W140: a big Excel (more than one chunk) is converted row-by-row and every row is imported */
class ReportImportExcelChunkTest extends TestCase
{
    use RefreshDatabase;

    private const HEAD = ['Track Artist', 'Album Title', 'Label', 'Track Title', 'ISRC', 'UPC', 'Platform', 'Currency', 'Country/Region', 'Sale Type', 'Sale Date', 'Sale Month', 'Streams', 'Sale Units', 'Collected Revenue'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutVite();
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction('REGEXP_REPLACE', fn ($s, $p, $r) => $s === null ? null : preg_replace('~'.str_replace('~', '\\~', (string) $p).'~u', (string) $r, (string) $s), 3);
        }
    }

    public function test_excel_larger_than_one_chunk_imports_every_row(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);

        // 2500 distinct rows => more than one 2000-row conversion chunk; distinct ISRCs so none are duplicates.
        // Catalogue mapping is irrelevant here: every parsed row is imported (mapped or unmapped).
        $data = [self::HEAD];
        for ($i = 1; $i <= 2500; $i++) {
            $isrc = 'INMXT26'.str_pad((string) $i, 5, '0', STR_PAD_LEFT);
            $data[] = ['Asha', 'Song', 'Lbl', 'Song', $isrc, '890000000014', 'Spotify', 'INR', 'IN', 'Stream', '2026-07-15', '2026-07', '100', '100', '100'];
        }

        $ss = new Spreadsheet();
        $ss->getActiveSheet()->fromArray($data, null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'big').'.xlsx';
        (new Xlsx($ss))->save($path);
        $ss->disconnectWorksheets();

        $file = new UploadedFile($path, 'big.xlsx', null, null, true);

        $this->actingAs($super)
            ->post('/admin/reports/imports', ['reporting_month' => '2026-07', 'report_file' => $file])
            ->assertSessionHasNoErrors();

        $import = DB::table('report_imports')->latest('id')->first();
        $this->assertNotNull($import);
        $this->assertSame('completed', $import->status);
        $this->assertSame(2500, (int) $import->total_rows);
        $this->assertSame(2500, (int) $import->imported_rows);
    }
}
