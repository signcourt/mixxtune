<?php

namespace Tests\Feature\Alerts;

use App\Console\Commands\V2\CheckBackups;
use App\Console\Commands\V2\RemindAgreements;
use App\Mail\StaffAlertMail;
use App\Models\Core\AccountAgreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesProductionOnlyTables;
use Tests\TestCase;

/* W96 MIXXTUNE_STAFF_ALERTS (generated test data only) */
class StaffAlertsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesProductionOnlyTables;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createProductionOnlyTables();
        Mail::fake();
        $this->dir = sys_get_temp_dir().'/w96-'.uniqid();
        mkdir($this->dir);
        CheckBackups::$dir = $this->dir;
        $this->travelTo(now('UTC')->setDate(2026, 10, 25)->setTime(3, 40));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function user(string $role, string $name = 'Test'): User
    {
        return User::factory()->create(['role' => $role, 'name' => $name, 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    public function test_backups_all_fine_sends_nothing(): void
    {
        $this->user('super_admin');
        file_put_contents($this->dir.'/offsite.last', "OK 20261025-031502 production db=1234 files={}\n");
        file_put_contents($this->dir.'/full.last', "OK 20261025-040011 full-x.tar.enc 99\n");
        $this->travelTo(now('UTC')->setDate(2026, 10, 25)->setTime(9, 0));
        $this->artisan('backups:check')->expectsOutputToContain('OK      Daily backup to Cloudflare R2: OK')->assertSuccessful();
        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('panel_notifications')->count());
    }

    public function test_failed_missing_or_old_backup_warns_every_super_admin_once_a_day(): void
    {
        $s1 = $this->user('super_admin', 'Boss');
        $s2 = $this->user('super_admin', 'Second');
        $this->user('admin', 'Manager');
        file_put_contents($this->dir.'/offsite.last', "FAILED 20261025-031502 production rclone upload failed\n");
        /* full.last missing */
        $this->artisan('backups:check --quiet-ok')->expectsOutputToContain('PROBLEM Daily backup to Cloudflare R2 FAILED on 25 Oct 2026, 08:45 AM (India): production rclone upload failed')->assertSuccessful();
        Mail::assertSent(StaffAlertMail::class, 2);
        Mail::assertSent(StaffAlertMail::class, fn ($m) => $m->hasTo($s1->email) && str_contains(implode(' ', $m->lines), 'Weekly full website backup: no status found'));
        $this->assertSame([$s1->id, $s2->id], DB::table('panel_notifications')->where('type', 'backup.problem')->orderBy('user_id')->pluck('user_id')->map(fn ($v) => (int) $v)->all());

        /* again the same day: not again */
        $this->artisan('backups:check')->expectsOutputToContain('already reported today')->assertSuccessful();
        Mail::assertSent(StaffAlertMail::class, 2);

        /* a run that is too old */
        $this->assertFalse(CheckBackups::read('x.last', 'X', 30)['ok']);
        file_put_contents($this->dir.'/offsite.last', "OK 20261023-031502 production\n");
        $r = CheckBackups::read('offsite.last', 'Daily backup', 30);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('48 hours ago - it has not run since', $r['text']);
        file_put_contents($this->dir.'/offsite.last', "garbage\n");
        $this->assertStringContainsString('unreadable', CheckBackups::read('offsite.last', 'Daily backup', 30)['text']);
    }

    public function test_agreement_reminder_mails_on_milestone_days_only(): void
    {
        $this->assertTrue(RemindAgreements::isMilestone(90));
        $this->assertTrue(RemindAgreements::isMilestone(0));
        $this->assertTrue(RemindAgreements::isMilestone(-14));
        $this->assertFalse(RemindAgreements::isMilestone(89));
        $this->assertFalse(RemindAgreements::isMilestone(-3));

        $super = $this->user('super_admin', 'Boss');
        $label = $this->user('label', 'Sanatan Records');
        $artist = $this->user('artist', 'Saras');
        /* India date is 25 Oct 2026: 30 days left -> mail; 45 days left -> only counted */
        AccountAgreement::query()->create(['user_id' => $label->id, 'signed_on' => '2023-11-25', 'term_months' => 36, 'ends_on' => '2026-11-24', 'notice_days' => 90, 'status' => 'active']);
        AccountAgreement::query()->create(['user_id' => $artist->id, 'signed_on' => '2023-12-10', 'term_months' => 36, 'ends_on' => '2026-12-09', 'notice_days' => 90, 'status' => 'active']);

        $this->artisan('agreements:remind')->expectsOutputToContain('to renew: 2, reminder today: 1')->assertSuccessful();
        Mail::assertSent(StaffAlertMail::class, fn ($m) => $m->hasTo($super->email) && $m->title === 'Agreement to renew'
            && $m->lines[0] === 'Sanatan Records - ends 24 Nov 2026 - 30 days left'
            && str_contains($m->lines[1], 'In total 2 agreements'));
        $this->assertSame(1, DB::table('panel_notifications')->where('type', 'agreement.reminder')->count());

        $this->artisan('agreements:remind')->expectsOutputToContain('already sent today')->assertSuccessful();
        Mail::assertSent(StaffAlertMail::class, 1);

        /* next day: 29 / 44 days left -> nothing */
        $this->travel(1)->days();
        $this->artisan('agreements:remind')->expectsOutputToContain('reminder today: 0')->assertSuccessful();
        Mail::assertSent(StaffAlertMail::class, 1);
    }

    public function test_the_morning_checks_are_scheduled(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('agreements:remind')->expectsOutputToContain('backups:check')->assertSuccessful();
    }
}
