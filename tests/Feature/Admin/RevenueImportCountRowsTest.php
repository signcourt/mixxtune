<?php

namespace Tests\Feature\Admin;

use App\Models\Finance\RevenueImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/* W139: the /revenue-imports upload counts rows without loading the whole file into memory */
class RevenueImportCountRowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function csv(array $dataRows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'rev');
        $h = fopen($path, 'w');
        fputcsv($h, ['Track', 'ISRC', 'Collected Revenue']);
        foreach ($dataRows as $r) {
            fputcsv($h, $r);
        }
        fclose($h);

        return new UploadedFile($path, 'july.csv', 'text/csv', null, true);
    }

    public function test_upload_counts_data_rows_excluding_the_header(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'email_verified_at' => now()]);

        $file = $this->csv([
            ['Song A', 'INMXT2600001', '100'],
            ['Song B', 'INMXT2600002', '50'],
            ['Song C', 'INMXT2600003', '25'],
        ]);

        $this->actingAs($admin)
            ->post(route('single.admin.revenue-imports.store'), [
                'dsp_name' => 'Spotify',
                'statement_month' => '2026-07',
                'currency' => 'INR',
                'report_file' => $file,
            ])
            ->assertSessionHasNoErrors();

        $import = RevenueImport::query()->latest('id')->first();
        $this->assertNotNull($import);
        $this->assertSame(3, (int) $import->total_rows);
    }
}
