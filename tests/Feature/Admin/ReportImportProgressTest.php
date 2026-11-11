<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/* W143: the import records an expected row count so the UI can show real progress */
class ReportImportProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutVite();
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction('REGEXP_REPLACE', fn ($s, $p, $r) => $s === null ? null : preg_replace('~'.str_replace('~', '\\~', (string) $p).'~u', (string) $r, (string) $s), 3);
        }
    }

    public function test_import_records_expected_row_count(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);

        $path = tempnam(sys_get_temp_dir(), 'rev');
        $h = fopen($path, 'w');
        fputcsv($h, ['Track', 'ISRC', 'Collected Revenue']);
        foreach (['A', 'B', 'C', 'D'] as $i => $n) {
            fputcsv($h, [$n, 'INMXT260000'.$i, '10']);
        }
        fclose($h);
        $file = new UploadedFile($path, 'july.csv', 'text/csv', null, true);

        $this->actingAs($super)->post('/admin/reports/imports', [
            'reporting_month' => '2026-07',
            'report_file' => $file,
        ])->assertSessionHasNoErrors();

        $import = DB::table('report_imports')->latest('id')->first();
        $this->assertSame('completed', $import->status);
        // 4 data rows (header excluded)
        $this->assertSame(4, (int) $import->expected_rows);
        $this->assertSame(4, (int) $import->total_rows);
    }
}
