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
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/* W138: report month picker (already-done months blocked + next suggested) and Excel upload */
class ReportImportMonthAndExcelTest extends TestCase
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

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function catalogue(): Label
    {
        $owner = $this->user('label');
        $label = Label::factory()->create(['user_id' => $owner->id, 'created_by' => $owner->id, 'parent_label_id' => null, 'revenue_share_percentage' => 80]);
        $release = Release::factory()->create(['label_id' => $label->id, 'artist_id' => null, 'upc' => '890000000014', 'status' => 'live']);
        Track::factory()->create(['release_id' => $release->id, 'isrc' => 'INMXT2600001']);

        return $label;
    }

    private function line(string $revenue): array
    {
        return ['Asha', 'Song', 'Lbl', 'Song', 'INMXT2600001', '890000000014', 'Spotify', 'INR', 'IN', 'Stream', '2026-07-15', '2026-07', '100', '100', $revenue];
    }

    private function csv(array $lines, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'rev');
        $h = fopen($path, 'w');
        fputcsv($h, self::HEAD);
        foreach ($lines as $l) {
            fputcsv($h, $l);
        }
        fclose($h);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    private function xlsx(array $lines, string $name): UploadedFile
    {
        $ss = new Spreadsheet();
        $ss->getActiveSheet()->fromArray(array_merge([self::HEAD], $lines), null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'rev').'.xlsx';
        (new Xlsx($ss))->save($path);

        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_month_meta_lists_used_months_and_suggests_the_next(): void
    {
        $this->catalogue();
        $super = $this->user('super_admin');

        $this->actingAs($super)->post('/admin/reports/imports', ['reporting_month' => '2026-06', 'report_file' => $this->csv([$this->line('100')], 'jun.csv')])->assertSessionHasNoErrors();
        $this->actingAs($super)->post('/admin/reports/imports', ['reporting_month' => '2026-07', 'report_file' => $this->csv([$this->line('100')], 'jul.csv')])->assertSessionHasNoErrors();

        $this->actingAs($super)->get('/admin/reports/imports')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('V2/Admin/Reports/Imports')
                ->where('monthMeta.suggested', '2026-08')
                ->where('monthMeta.used', fn ($used) => collect($used)->contains('2026-06') && collect($used)->contains('2026-07')));
    }

    public function test_excel_report_is_accepted_and_imported(): void
    {
        $this->catalogue();
        $super = $this->user('super_admin');

        $this->actingAs($super)->post('/admin/reports/imports', [
            'reporting_month' => '2026-08',
            'report_file' => $this->xlsx([$this->line('100'), $this->line('50')], 'august.xlsx'),
        ])->assertSessionHasNoErrors();

        $import = DB::table('report_imports')->latest('id')->first();
        $this->assertNotNull($import);
        $this->assertSame('completed', $import->status);
        $this->assertSame('august.xlsx', $import->original_filename);
        $this->assertSame(2, (int) $import->total_rows);
        $this->assertGreaterThanOrEqual(1, (int) $import->imported_rows);
    }

    public function test_pdf_is_still_rejected(): void
    {
        $super = $this->user('super_admin');

        $this->actingAs($super)->post('/admin/reports/imports', [
            'reporting_month' => '2026-08',
            'report_file' => UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'),
        ])->assertSessionHasErrors('report_file');
    }
}
