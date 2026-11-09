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
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/* W142 (Wave B): edit a report row's ISRC / names, then re-map + recalc earnings */
class ReportRowRemapTest extends TestCase
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

    private function csv(string $isrc, string $revenue): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'rev');
        $h = fopen($path, 'w');
        fputcsv($h, self::HEAD);
        fputcsv($h, ['Asha', 'Album', 'Lbl', 'Song', $isrc, '890000000014', 'Spotify', 'INR', 'IN', 'Stream', '2026-07-15', '2026-07', '100', '100', $revenue]);
        fclose($h);

        return new UploadedFile($path, 'july.csv', 'text/csv', null, true);
    }

    public function test_editing_the_isrc_remaps_the_row_and_recomputes_earnings(): void
    {
        $label = $this->catalogue();
        $super = $this->user('super_admin');

        // upload a row whose ISRC does NOT match the catalogue -> imported but unmapped, no earnings
        $this->actingAs($super)->post('/admin/reports/imports', [
            'reporting_month' => '2026-07',
            'report_file' => $this->csv('XXWRONG000001', '100'),
        ])->assertSessionHasNoErrors();

        $import = DB::table('report_imports')->latest('id')->first();
        $row = DB::table('report_rows')->where('report_import_id', $import->id)->first();

        $this->assertSame('unmapped', $row->mapping_status);
        $this->assertEqualsWithDelta(0.0, (float) $row->earnings, 0.001);
        $this->assertNull($row->label_id);

        // fix the ISRC to the catalogue track
        $this->actingAs($super)
            ->patch(route('v2.admin.reports.imports.rows.update', [$import->id, $row->id]), ['isrc' => 'INMXT2600001'])
            ->assertSessionHasNoErrors();

        $row = DB::table('report_rows')->where('id', $row->id)->first();

        $this->assertSame('mapped', $row->mapping_status);
        $this->assertSame((int) $label->id, (int) $row->label_id);
        // 100 collected x 80% = 80.00 earnings, exactly as a fresh import would compute
        $this->assertEqualsWithDelta(80.0, (float) $row->earnings, 0.001);
        $this->assertEqualsWithDelta(0.80, (float) $row->label_rate, 0.0001);
    }

    public function test_editing_a_name_persists_and_keeps_mapping(): void
    {
        $this->catalogue();
        $super = $this->user('super_admin');

        $this->actingAs($super)->post('/admin/reports/imports', [
            'reporting_month' => '2026-07',
            'report_file' => $this->csv('INMXT2600001', '100'),
        ])->assertSessionHasNoErrors();

        $import = DB::table('report_imports')->latest('id')->first();
        $row = DB::table('report_rows')->where('report_import_id', $import->id)->first();
        $this->assertSame('mapped', $row->mapping_status);

        $this->actingAs($super)
            ->patch(route('v2.admin.reports.imports.rows.update', [$import->id, $row->id]), ['track_title' => 'Corrected Title', 'track_artist' => 'Corrected Artist'])
            ->assertSessionHasNoErrors();

        $row = DB::table('report_rows')->where('id', $row->id)->first();
        $this->assertSame('Corrected Title', $row->track_title);
        $this->assertSame('Corrected Artist', $row->track_artist);
        $this->assertSame('mapped', $row->mapping_status); // still mapped, earnings intact
        $this->assertEqualsWithDelta(80.0, (float) $row->earnings, 0.001);
    }

    public function test_rows_cannot_be_edited_after_approval(): void
    {
        $this->catalogue();
        $super = $this->user('super_admin');

        $this->actingAs($super)->post('/admin/reports/imports', [
            'reporting_month' => '2026-07',
            'report_file' => $this->csv('INMXT2600001', '100'),
        ])->assertSessionHasNoErrors();

        $import = DB::table('report_imports')->latest('id')->first();
        $row = DB::table('report_rows')->where('report_import_id', $import->id)->first();

        if (Schema::hasColumn('report_imports', 'financial_status')) {
            DB::table('report_imports')->where('id', $import->id)->update(['financial_status' => 'approved_scheduled']);

            $this->actingAs($super)
                ->patch(route('v2.admin.reports.imports.rows.update', [$import->id, $row->id]), ['track_title' => 'Nope'])
                ->assertSessionHasErrors('row');
        } else {
            $this->markTestSkipped('financial_status column not present');
        }
    }
}
