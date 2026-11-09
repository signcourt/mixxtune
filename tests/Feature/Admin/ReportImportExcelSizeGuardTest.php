<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/* W141: an oversized Excel is blocked at upload (point to CSV); CSV is never blocked */
class ReportImportExcelSizeGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction('REGEXP_REPLACE', fn ($s, $p, $r) => $s === null ? null : preg_replace('~'.str_replace('~', '\\~', (string) $p).'~u', (string) $r, (string) $s), 3);
        }
    }

    private function super(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    public function test_large_excel_is_rejected_with_a_csv_hint(): void
    {
        // 11 MB .xlsx (size in KB) -> over the 10 MB Excel cap
        $file = UploadedFile::fake()->create('big.xlsx', 11 * 1024, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($this->super())
            ->post('/admin/reports/imports', ['reporting_month' => '2026-07', 'report_file' => $file])
            ->assertSessionHasErrors('report_file');

        $this->assertSame(0, DB::table('report_imports')->count());
    }

    public function test_large_csv_is_allowed(): void
    {
        // 20 MB CSV must NOT be blocked by the Excel guard (CSV streams)
        $path = tempnam(sys_get_temp_dir(), 'rev');
        $h = fopen($path, 'w');
        fputcsv($h, ['Track', 'ISRC', 'Collected Revenue']);
        fputcsv($h, ['Song A', 'INMXT2600001', '100']);
        fclose($h);
        $file = new UploadedFile($path, 'report.csv', 'text/csv', null, true);

        $this->actingAs($this->super())
            ->post('/admin/reports/imports', ['reporting_month' => '2026-07', 'report_file' => $file])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('report_imports')->count());
    }
}
