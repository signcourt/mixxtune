<?php

namespace Tests\Feature\Admin;

use App\Mail\ActionOtpMail;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* Every button of Super Admin > Revenue Imports, end to end (generated test data only) */
class RevenueImportPageFunctionsTest extends TestCase
{
    use RefreshDatabase;

    private const HEAD = ['Track Artist', 'Album Title', 'Label', 'Track Title', 'ISRC', 'UPC', 'Platform', 'Currency', 'Country/Region', 'Sale Type', 'Sale Date', 'Sale Month', 'Streams', 'Sale Units', 'Collected Revenue'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        $this->withoutVite();
        /* the live database (MariaDB) has REGEXP_REPLACE; the in-memory test database needs it added */
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction('REGEXP_REPLACE', fn ($s, $p, $r) => $s === null ? null : preg_replace('~'.str_replace('~', '\\~', (string) $p).'~u', (string) $r, (string) $s), 3);
        }
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function catalogue(): array
    {
        $owner = $this->user('label');
        $label = Label::factory()->create(['user_id' => $owner->id, 'created_by' => $owner->id, 'parent_label_id' => null, 'revenue_share_percentage' => 80]);
        $release = Release::factory()->create(['label_id' => $label->id, 'artist_id' => null, 'upc' => '890000000014', 'status' => 'live']);
        $track = Track::factory()->create(['release_id' => $release->id, 'isrc' => 'INMXT2600001']);

        return [$owner, $label, $track];
    }

    private function csv(array $lines, string $name = 'july.csv'): UploadedFile
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

    private function line(string $isrc, string $platform, string $country, string $revenue): array
    {
        return ['Asha', 'Song', 'Lbl', 'Song', $isrc, '890000000014', $platform, 'INR', $country, 'Stream', '2026-07-15', '2026-07', '100', '100', $revenue];
    }

    private function otp(User $actor): void
    {
        $code = null;
        Mail::assertSent(ActionOtpMail::class, function (ActionOtpMail $m) use (&$code) { $code = $m->otp; return true; });
        $this->actingAs($actor)->postJson('/admin/action-otp/unlock', ['otp' => (string) $code])->assertOk();
    }

    public function test_upload_review_edit_download_approve_publish_and_locks(): void
    {
        [$owner, $label] = $this->catalogue();
        $super = $this->user('super_admin');

        /* 1. page opens */
        $this->actingAs($super)->get('/admin/reports/imports')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('V2/Admin/Reports/Imports')->has('uploadLimitMb'));

        /* 2. validation: month and csv needed */
        $this->actingAs($super)->post('/admin/reports/imports', [])->assertSessionHasErrors(['reporting_month', 'report_file']);
        $this->actingAs($super)->post('/admin/reports/imports', ['reporting_month' => '2026-07', 'report_file' => UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')])
            ->assertSessionHasErrors('report_file');

        /* 3. upload: 3 lines, one an exact copy, one unknown ISRC */
        $file = $this->csv([
            $this->line('INMXT2600001', 'Spotify', 'IN', '100'),
            $this->line('INMXT2600001', 'Spotify', 'IN', '100'),
            $this->line('INMXT2600001', 'JioSaavn', 'IN', '50'),
            $this->line('XXUNKNOWN0001', 'Spotify', 'IN', '10'),
        ]);
        $this->actingAs($super)->post('/admin/reports/imports', ['reporting_month' => '2026-07', 'report_file' => $file])->assertSessionHasNoErrors();

        $import = DB::table('report_imports')->latest('id')->first();
        $this->assertSame('completed', $import->status);
        $this->assertSame([4, 3, 1, 0], [(int) $import->total_rows, (int) $import->imported_rows, (int) $import->duplicate_rows, (int) $import->failed_rows]);
        $this->assertSame('review_pending', $import->financial_status);
        $this->assertSame(80.0, (float) DB::table('report_rows')->where('platform', 'Spotify')->where('label_id', $label->id)->value('earnings'));

        /* 4. list shows it with 1 unmapped */
        $this->actingAs($super)->get('/admin/reports/imports')->assertInertia(fn (Assert $p) => $p
            ->where('imports.data.0.id', $import->id)->where('imports.data.0.unmapped_rows_count', 1));

        /* 5. Open & review */
        $this->actingAs($super)->get("/admin/reports/imports/{$import->id}/view")->assertOk();

        /* 6. download the original */
        $this->actingAs($super)->get("/admin/reports/imports/{$import->id}/download")->assertOk()->assertDownload('july.csv');

        /* 7. pencil: change month - rows move too */
        $this->actingAs($super)->patch("/admin/reports/imports/{$import->id}", ['reporting_month' => '2026-08'])->assertSessionHasNoErrors();
        $this->assertSame(3, DB::table('report_rows')->where('report_import_id', $import->id)->where('reporting_month', '2026-08')->count());
        $this->actingAs($super)->patch("/admin/reports/imports/{$import->id}", ['reporting_month' => '2026-07'])->assertSessionHasNoErrors();

        /* 8. approve is blocked while a row is unmapped */
        $this->actingAs($super)->post("/admin/reports/imports/{$import->id}/approve-and-schedule", ['publish_at' => now()->toDateString()])
            ->assertSessionHasErrors('approval');

        /* 9. the unknown ISRC is added to the catalogue, re-detect maps it */
        Track::factory()->create(['release_id' => Release::query()->first()->id, 'isrc' => 'XXUNKNOWN0001', 'track_number' => 2]);
        $this->actingAs($super)->post("/admin/reports/imports/{$import->id}/re-detect-unmapped")->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('report_rows')->where('report_import_id', $import->id)->whereNull('label_id')->count());

        /* 10. approve: needs the e-mailed code, then works */
        $this->actingAs($super)->post("/admin/reports/imports/{$import->id}/approve-and-schedule", ['publish_at' => now()->toDateString()])
            ->assertSessionHasErrors('action_otp');
        $this->otp($super);
        $this->actingAs($super)->post("/admin/reports/imports/{$import->id}/approve-and-schedule", ['publish_at' => now()->toDateString()])
            ->assertSessionHasNoErrors();
        $this->assertSame('approved_scheduled', DB::table('report_imports')->where('id', $import->id)->value('financial_status'));

        /* 11. approved: edit and delete are refused, approving twice is refused */
        $this->actingAs($super)->patch("/admin/reports/imports/{$import->id}", ['reporting_month' => '2026-09'])->assertSessionHasErrors('financial_status');
        $this->actingAs($super)->delete("/admin/reports/imports/{$import->id}")->assertSessionHasErrors('financial_status');
        $this->assertDatabaseHas('report_imports', ['id' => $import->id, 'reporting_month' => '2026-07']);

        /* 12. publish job puts it in the owner's wallet: (100 + 50 + 10) x 80% = 128 */
        $this->artisan('reports:publish-due-imports')->assertSuccessful();
        $this->assertSame('published', DB::table('report_imports')->where('id', $import->id)->value('financial_status'));
        $this->assertEqualsWithDelta(128.0, (float) DB::table('wallets')->where('user_id', $owner->id)->where('currency', 'INR')->sum('available_balance'), 0.001);
        /* owner sees it: statement for July */
        $this->assertTrue(DB::table('royalty_statements')->exists());

        /* 13. same file again: everything is a duplicate, nothing counted twice */
        $this->actingAs($super)->post('/admin/reports/imports', ['reporting_month' => '2026-07', 'report_file' => $this->csv([
            $this->line('INMXT2600001', 'Spotify', 'IN', '100'),
            $this->line('INMXT2600001', 'JioSaavn', 'IN', '50'),
        ], 'again.csv')]);
        $again = DB::table('report_imports')->latest('id')->first();
        $this->assertSame([0, 2], [(int) $again->imported_rows, (int) $again->duplicate_rows]);

        /* 14. delete (before approval) removes the report, its rows and the stored file */
        Storage::disk('local')->assertExists($again->stored_path);
        $this->actingAs($super)->delete("/admin/reports/imports/{$again->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('report_imports', ['id' => $again->id]);
        Storage::disk('local')->assertMissing($again->stored_path);
    }

    public function test_who_can_do_what(): void
    {
        $this->catalogue();
        $super = $this->user('super_admin');
        $manager = $this->user('admin');
        $label = $this->user('label');

        $this->actingAs($super)->post('/admin/reports/imports', ['reporting_month' => '2026-07', 'report_file' => $this->csv([$this->line('INMXT2600001', 'Spotify', 'IN', '5')])]);
        $id = (int) DB::table('report_imports')->latest('id')->value('id');

        /* manager: sees, uploads, downloads - cannot approve */
        $this->actingAs($manager)->get('/admin/reports/imports')->assertOk();
        $this->actingAs($manager)->get("/admin/reports/imports/{$id}/download")->assertOk();
        $this->actingAs($manager)->post("/admin/reports/imports/{$id}/approve-and-schedule", ['publish_at' => now()->toDateString()])->assertForbidden();

        /* label owner: nothing */
        $this->actingAs($label)->get('/admin/reports/imports')->assertForbidden();
        $this->actingAs($label)->get("/admin/reports/imports/{$id}/download")->assertForbidden();
        $this->actingAs($label)->delete("/admin/reports/imports/{$id}")->assertForbidden();

        /* not logged in: sent to login, never an error page */
        auth()->logout();
        $this->get('/admin/reports/imports')->assertRedirect('/login');
        $this->post("/admin/reports/imports/{$id}/missing-revenue-rates", [])->assertRedirect('/login');
    }
}
