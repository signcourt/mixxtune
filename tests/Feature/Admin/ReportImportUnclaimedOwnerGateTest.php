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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
 * W151: a mapped label that has NO owner user yet must not block approval -
 * its revenue is held as unclaimed at publish (owner_user_missing). A label
 * whose linked user is inactive still blocks (the publisher cannot hold or
 * credit it safely).
 */
class ReportImportUnclaimedOwnerGateTest extends TestCase
{
    use RefreshDatabase;

    private const HEAD = ['Track Artist', 'Album Title', 'Label', 'Track Title', 'ISRC', 'UPC', 'Platform', 'Currency', 'Country/Region', 'Sale Type', 'Sale Date', 'Sale Month', 'Streams', 'Sale Units', 'Collected Revenue'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        $this->withoutVite();
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction('REGEXP_REPLACE', fn ($s, $p, $r) => $s === null ? null : preg_replace('~'.str_replace('~', '\\~', (string) $p).'~u', (string) $r, (string) $s), 3);
        }
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function labelOwningTrack(?int $userId, string $isrc): void
    {
        $label = Label::factory()->create([
            'user_id' => $userId,
            'parent_label_id' => null,
            'revenue_share_percentage' => 80,
        ]);
        $release = Release::factory()->create([
            'label_id' => $label->id,
            'artist_id' => null,
            'upc' => '890000000014',
            'status' => 'live',
        ]);
        Track::factory()->create(['release_id' => $release->id, 'isrc' => $isrc]);
    }

    private function uploadOneRow(User $super, string $isrc): int
    {
        $path = tempnam(sys_get_temp_dir(), 'rev');
        $h = fopen($path, 'w');
        fputcsv($h, self::HEAD);
        fputcsv($h, ['Asha', 'Song', 'Lbl', 'Song', $isrc, '890000000014', 'Spotify', 'INR', 'IN', 'Stream', '2026-07-15', '2026-07', '100', '100', '100']);
        fclose($h);

        $this->actingAs($super)->post('/admin/reports/imports', [
            'reporting_month' => '2026-07',
            'report_file' => new UploadedFile($path, 'july.csv', 'text/csv', null, true),
        ])->assertSessionHasNoErrors();

        return (int) DB::table('report_imports')->latest('id')->value('id');
    }

    private function unlockOtp(User $super): void
    {
        $code = null;
        Mail::assertSent(ActionOtpMail::class, function (ActionOtpMail $m) use (&$code) {
            $code = $m->otp;
            return true;
        });
        $this->actingAs($super)->postJson('/admin/action-otp/unlock', ['otp' => (string) $code])->assertOk();
    }

    public function test_label_without_owner_user_no_longer_blocks_approval(): void
    {
        $super = $this->superAdmin();
        $this->labelOwningTrack(null, 'INMXT2600001'); // unowned label
        $importId = $this->uploadOneRow($super, 'INMXT2600001');

        // row must be mapped (ISRC matched) with nothing unmapped
        $this->assertSame(0, DB::table('report_rows')->where('report_import_id', $importId)->whereNull('label_id')->count());

        // approve: the owner gate no longer returns an "approval" error; it now
        // reaches the two-step OTP gate instead (proving the gate was relaxed).
        $this->actingAs($super)
            ->post("/admin/reports/imports/{$importId}/approve-and-schedule", ['publish_at' => now()->toDateString()])
            ->assertSessionHasErrors('action_otp')
            ->assertSessionDoesntHaveErrors('approval');

        // with the e-mailed code it goes through
        $this->unlockOtp($super);
        $this->actingAs($super)
            ->post("/admin/reports/imports/{$importId}/approve-and-schedule", ['publish_at' => now()->toDateString()])
            ->assertSessionHasNoErrors();

        $this->assertSame('approved_scheduled', DB::table('report_imports')->where('id', $importId)->value('financial_status'));
    }

    public function test_label_with_inactive_owner_user_still_blocks_approval(): void
    {
        $super = $this->superAdmin();
        $inactive = User::factory()->create(['role' => 'label', 'account_status' => 'inactive', 'email_verified_at' => now()]);
        $this->labelOwningTrack($inactive->id, 'INMXT2600002');
        $importId = $this->uploadOneRow($super, 'INMXT2600002');

        $this->actingAs($super)
            ->post("/admin/reports/imports/{$importId}/approve-and-schedule", ['publish_at' => now()->toDateString()])
            ->assertSessionHasErrors('approval');
    }
}
