<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\V2\ReportImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

/* W93 MIXXTUNE_DUPLICATE_KEY_COUNTRY_SOURCE (generated test data, same columns as the July 2026 file) */
class ReportCountryNameDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private function import(array $lines): object
    {
        Storage::fake('local');
        $path = tempnam(sys_get_temp_dir(), 'jul');
        $h = fopen($path, 'w');
        fputcsv($h, ['Reporting Month', 'Sale Month', 'Track Title', 'Track Artist', 'Album Title', 'Track Artist', 'Label', 'ISRC', 'UPC', 'Platform', 'Country Name', 'Currency', 'Sale Type', 'Sale Source', 'Stream', 'Collected Revenue', 'Lable Rate', 'Eraning']);
        foreach ($lines as $l) {
            fputcsv($h, $l);
        }
        fclose($h);
        $user = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active']);
        $import = app(ReportImportService::class)->import(new UploadedFile($path, 'july.csv', 'text/csv', null, true), $user, '2026-07');

        return DB::table('report_imports')->where('id', $import->id)->first();
    }

    private function line(string $country, string $source, string $revenue = '0.0123'): array
    {
        return ['2026-07', '2026-07', 'Song', 'Asha', 'Album', 'Asha', 'Lbl', 'INMXT2600001', '890000000014', 'YouTube', $country, 'INR', 'Stream', $source, '3', $revenue, '0.8', '0.01'];
    }

    public function test_lines_from_different_countries_or_sources_are_all_counted(): void
    {
        $imp = $this->import([
            $this->line('South Africa', 'Ads'),
            $this->line('South Africa', 'Ads'),          /* exact copy: skipped */
            $this->line('Z1 - WMISL', 'Ads'),            /* other country: kept (lost before W93) */
            $this->line('Jamaica', 'Ads'),
            $this->line('Jamaica', 'Premium'),           /* other sale source: kept */
        ]);

        $this->assertSame([5, 4, 1], [(int) $imp->total_rows, (int) $imp->imported_rows, (int) $imp->duplicate_rows]);
        $this->assertSame(['ZA', 'JM', 'JM', null], DB::table('report_rows')->orderBy('country_code', 'desc')->pluck('country_code')->all());
        $this->assertEqualsWithDelta(0.0492, (float) DB::table('report_rows')->sum('collected_revenue'), 0.000001);
    }

    public function test_key_of_reports_without_these_columns_is_unchanged(): void
    {
        $svc = app(ReportImportService::class);
        $m = new ReflectionMethod($svc, 'rowHash');
        $m->setAccessible(true);
        $row = ['isrc' => 'IN-MXT-26-00001', 'upc' => '890000000014', 'platform' => 'Spotify', 'country_code' => 'IN', 'sale_type' => 'Stream',
            'sale_date' => '2026-07-15', 'sale_month' => '2026-07', 'streams' => '10', 'sale_units' => '10', 'collected_revenue' => '5', 'territory_name' => null, 'sale_source' => null];

        /* the formula used before W93 */
        $old = hash('sha256', implode('|', ['INMXT2600001', '890000000014', 'SPOTIFY', 'IN', 'STREAM', '2026-07-15', '2026-07', '10', '10', '5']));
        $this->assertSame($old, $m->invoke($svc, $row));
        $this->assertNotSame($old, $m->invoke($svc, array_merge($row, ['sale_source' => 'Ads'])));
    }
}
