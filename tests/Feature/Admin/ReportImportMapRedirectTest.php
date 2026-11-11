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
 * W144: the import view ("Report Data") offers the Super Admin a "Map" shortcut
 * on every unmapped row and a "Map unmapped rows" button. Both link to the
 * mapping screen scoped to this import (report_import_id) and, per row, to the
 * clicked ISRC (search). This test guards that redirect target's contract:
 * the mapping screen is Super-Admin only, and it honours report_import_id +
 * search so the Super Admin lands exactly on the row they clicked.
 */
class ReportImportMapRedirectTest extends TestCase
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

    private function importOrphanRow(User $actor): int
    {
        $path = tempnam(sys_get_temp_dir(), 'rev');
        $handle = fopen($path, 'w');
        fputcsv($handle, ['Track', 'ISRC', 'Collected Revenue']);
        /* An ISRC that matches no catalogue track stays unmapped (label_id NULL). */
        fputcsv($handle, ['Orphan Track', 'INMXT2699999', '42']);
        fclose($handle);

        $file = new UploadedFile($path, 'july.csv', 'text/csv', null, true);

        $this->actingAs($actor)->post('/admin/reports/imports', [
            'reporting_month' => '2026-07',
            'report_file' => $file,
        ])->assertSessionHasNoErrors();

        return (int) DB::table('report_imports')->latest('id')->value('id');
    }

    public function test_super_admin_lands_on_the_scoped_unmapped_row(): void
    {
        $super = User::factory()->create([
            'role' => 'super_admin',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $importId = $this->importOrphanRow($super);
        $isrc = 'INMXT2699999';

        // the orphan row must actually be unmapped, so there is something to map
        $this->assertSame(1, DB::table('report_rows')
            ->where('report_import_id', $importId)
            ->whereNull('label_id')
            ->count());

        $this->actingAs($super)
            ->get('/super-admin/unmapped-revenue?report_import_id='.$importId.'&search='.$isrc)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('V2/Admin/UnmappedRevenue/Index')
                ->where('filters.report_import_id', $importId)
                ->where('filters.search', $isrc)
                ->has('rows.data', 1));
    }

    public function test_plain_admin_cannot_open_the_mapping_screen(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/super-admin/unmapped-revenue')
            ->assertForbidden();
    }
}
