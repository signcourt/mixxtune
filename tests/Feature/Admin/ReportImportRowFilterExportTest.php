<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/*
 * W145: the Report Data view filters rows by any column (track / artist /
 * album / label / ISRC / UPC / platform / country / currency / month /
 * mapping) and can download exactly the filtered slice as a CSV, so an admin
 * can re-check e.g. one label's earnings outside the panel.
 */
class ReportImportRowFilterExportTest extends TestCase
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

    private function superAdmin(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function importTwoLabelRows(User $actor): int
    {
        $path = tempnam(sys_get_temp_dir(), 'rev');
        $handle = fopen($path, 'w');
        fputcsv($handle, ['Track', 'ISRC', 'Label', 'Platform', 'Country', 'Collected Revenue']);
        fputcsv($handle, ['AlphaTrack', 'INMXT2600001', 'AlphaLabel', 'Spotify', 'IN', '10']);
        fputcsv($handle, ['BetaTrack', 'INMXT2600002', 'BetaLabel', 'AppleMusic', 'US', '20']);
        fclose($handle);

        $file = new UploadedFile($path, 'july.csv', 'text/csv', null, true);

        $this->actingAs($actor)->post('/admin/reports/imports', [
            'reporting_month' => '2026-07',
            'report_file' => $file,
        ])->assertSessionHasNoErrors();

        return (int) DB::table('report_imports')->latest('id')->value('id');
    }

    public function test_show_filters_rows_by_column_and_exposes_facets(): void
    {
        $super = $this->superAdmin();
        $importId = $this->importTwoLabelRows($super);

        $this->assertSame(2, DB::table('report_rows')->where('report_import_id', $importId)->count());

        $this->actingAs($super)
            ->get('/admin/reports/imports/'.$importId.'/view?label_name=AlphaLabel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('V2/Admin/Reports/ImportShow')
                ->where('filters.label_name', 'AlphaLabel')
                ->has('facets.platform', 2)   // Spotify + AppleMusic
                ->has('rows.data', 1)          // only AlphaLabel row
                ->where('rows.data.0.label_name', 'AlphaLabel'));
    }

    public function test_export_streams_only_the_filtered_rows(): void
    {
        $super = $this->superAdmin();
        $importId = $this->importTwoLabelRows($super);

        $response = $this->actingAs($super)
            ->get('/admin/reports/imports/'.$importId.'/rows/export?label_name=AlphaLabel');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();

        // header row + the one matching label, and nothing from the other label
        $this->assertStringContainsString('Collected Revenue', $csv);
        $this->assertStringContainsString('AlphaLabel', $csv);
        $this->assertStringContainsString('INMXT2600001', $csv);
        $this->assertStringNotContainsString('BetaLabel', $csv);
        $this->assertStringNotContainsString('INMXT2600002', $csv);
    }

    public function test_export_without_filter_returns_every_row(): void
    {
        $super = $this->superAdmin();
        $importId = $this->importTwoLabelRows($super);

        $csv = $this->actingAs($super)
            ->get('/admin/reports/imports/'.$importId.'/rows/export')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('AlphaLabel', $csv);
        $this->assertStringContainsString('BetaLabel', $csv);
    }

    public function test_export_is_blocked_for_non_admin_roles(): void
    {
        $super = $this->superAdmin();
        $importId = $this->importTwoLabelRows($super);

        $label = User::factory()->create([
            'role' => 'label',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($label)
            ->get('/admin/reports/imports/'.$importId.'/rows/export')
            ->assertForbidden();
    }
}
