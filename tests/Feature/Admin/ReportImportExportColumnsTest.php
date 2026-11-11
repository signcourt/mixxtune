<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
 * W146: the CSV download lets the admin choose which columns are included
 * (?columns=track_title,isrc,...). Unknown keys are ignored, the canonical
 * order is kept, and an empty / broken selection falls back to every column
 * so the file is never empty.
 */
class ReportImportExportColumnsTest extends TestCase
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

    private function seedImport(): array
    {
        $super = User::factory()->create([
            'role' => 'super_admin',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'rev');
        $handle = fopen($path, 'w');
        fputcsv($handle, ['Track', 'ISRC', 'Label', 'Platform', 'Country', 'Collected Revenue']);
        fputcsv($handle, ['AlphaTrack', 'INMXT2600001', 'AlphaLabel', 'Spotify', 'IN', '10']);
        fclose($handle);

        $this->actingAs($super)->post('/admin/reports/imports', [
            'reporting_month' => '2026-07',
            'report_file' => new UploadedFile($path, 'july.csv', 'text/csv', null, true),
        ])->assertSessionHasNoErrors();

        return [$super, (int) DB::table('report_imports')->latest('id')->value('id')];
    }

    public function test_export_includes_only_selected_columns(): void
    {
        [$super, $importId] = $this->seedImport();

        $csv = $this->actingAs($super)
            ->get('/admin/reports/imports/'.$importId.'/rows/export?columns=label_name,isrc')
            ->assertOk()
            ->streamedContent();

        // chosen columns present
        $this->assertStringContainsString('Label', $csv);
        $this->assertStringContainsString('ISRC', $csv);
        $this->assertStringContainsString('AlphaLabel', $csv);
        $this->assertStringContainsString('INMXT2600001', $csv);

        // unchosen columns (and their data) absent
        $this->assertStringNotContainsString('Platform', $csv);
        $this->assertStringNotContainsString('Spotify', $csv);
        $this->assertStringNotContainsString('Collected Revenue', $csv);
    }

    public function test_export_respects_requested_column_order_and_drops_unknown(): void
    {
        [$super, $importId] = $this->seedImport();

        $csv = $this->actingAs($super)
            ->get('/admin/reports/imports/'.$importId.'/rows/export?columns=upc,made_up,isrc')
            ->assertOk()
            ->streamedContent();

        $header = strtok($csv, "\n");

        // W147: the admin's order is honoured (UPC before ISRC), unknown dropped
        $this->assertStringContainsString('ISRC', $header);
        $this->assertStringContainsString('UPC', $header);
        $this->assertLessThan(strpos($header, 'ISRC'), strpos($header, 'UPC'));
        $this->assertStringNotContainsString('Label', $header);
        $this->assertStringNotContainsString('made_up', $header);
    }

    public function test_empty_selection_falls_back_to_all_columns(): void
    {
        [$super, $importId] = $this->seedImport();

        $csv = $this->actingAs($super)
            ->get('/admin/reports/imports/'.$importId.'/rows/export?columns=')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Collected Revenue', $csv);
        $this->assertStringContainsString('Platform', $csv);
        $this->assertStringContainsString('Earnings', $csv);
    }
}
