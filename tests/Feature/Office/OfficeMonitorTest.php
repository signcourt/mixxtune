<?php

namespace Tests\Feature\Office;

use App\Models\Office\OfficeDevice;
use App\Models\User;
use App\Services\V2\Office\OfficeService;
use App\Services\V2\Office\OfficeSettings;
use App\Services\V2\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Mail\LoginEmailOtpMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/* W128 MIXXTUNE_OFFICE_MONITOR (generated test data only) */
class OfficeMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function ($t) {
                $t->id();
                $t->string('public_id', 40)->unique();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('action', 100);
                $t->string('module', 100)->nullable();
                $t->string('auditable_type')->nullable();
                $t->unsignedBigInteger('auditable_id')->nullable();
                $t->text('description')->nullable();
                $t->json('old_values')->nullable();
                $t->json('new_values')->nullable();
                $t->string('ip_address', 64)->nullable();
                $t->text('user_agent')->nullable();
                $t->string('request_method', 10)->nullable();
                $t->text('request_url')->nullable();
                $t->timestamps();
            });
        }
    }

    private function super(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function staff(string $email = 's@o.test'): User
    {
        return User::query()->create(['name' => 'Staff', 'email' => $email, 'role' => 'staff', 'account_status' => 'active', 'password' => 'x', 'email_verified_at' => now()]);
    }

    public function test_settings_are_saved_and_only_super_admin_can_change_them(): void
    {
        $boss = $this->super();
        $staff = $this->staff();

        $this->actingAs($staff)->get('/office/admin/settings')->assertForbidden();
        $this->actingAs($boss)->post('/office/admin/settings', [
            'monitoring_enabled' => true, 'screenshot_minutes' => 7, 'idle_minutes' => 12,
            'work_start' => '09:30', 'work_end' => '19:00', 'rustdesk_host' => 'rd.mixxtune.com',
        ])->assertRedirect();
        $this->assertTrue(OfficeSettings::monitoringEnabled());
        $this->assertSame(7, OfficeSettings::screenshotMinutes());
        $this->assertSame(12, OfficeSettings::idleMinutes());
    }

    public function test_agent_pairs_sends_heartbeats_and_screenshots(): void
    {
        $boss = $this->super();
        app(SystemSettingService::class)->set('office.monitoring_enabled', true, 'office', 'boolean', $boss->id);
        $staff = $this->staff();
        $office = app(OfficeService::class);

        Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00', OfficeService::TZ));
        $office->clockIn($staff);

        /* staff makes a pair code (web) */
        $code = $this->actingAs($staff)->postJson('/office/device/pair-code', ['platform' => 'windows', 'device_name' => 'Studio PC'])->assertOk()->json('code');
        $this->assertNotEmpty($code);

        /* agent pairs with the code -> gets a token */
        $token = $this->postJson('/api/office/agent/pair', ['code' => $code, 'platform' => 'windows', 'device_name' => 'Studio PC', 'rustdesk_id' => '123456789'])->assertOk()->json('token');
        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('office_devices', ['user_id' => $staff->id, 'approved' => true, 'rustdesk_id' => '123456789']);

        /* a wrong token is refused */
        $this->postJson('/api/office/agent/heartbeat', ['idle_seconds' => 0], ['Authorization' => 'Bearer nope'])->assertStatus(401);

        $auth = ['Authorization' => 'Bearer '.$token];
        /* active heartbeat, 60s later another active one -> computer active time grows */
        $this->postJson('/api/office/agent/heartbeat', ['idle_seconds' => 5, 'foreground_app' => 'Photoshop'], $auth)->assertOk()->assertJsonPath('clocked_in', true);
        Carbon::setTestNow(Carbon::parse('2026-10-31 10:01:00', OfficeService::TZ));
        $this->postJson('/api/office/agent/heartbeat', ['idle_seconds' => 200, 'foreground_app' => 'Premiere'], $auth)->assertOk();
        $att = DB::table('office_attendance')->where('user_id', $staff->id)->first();
        $this->assertSame(90, (int) $att->comp_active_seconds); /* first ping active */
        $this->assertSame(90, (int) $att->comp_idle_seconds);   /* second ping idle (200s) */

        /* screenshot stored */
        $this->postJson('/api/office/agent/screenshot', ['shot' => UploadedFile::fake()->image('s.jpg', 400, 300)], $auth)->assertOk()->assertJsonPath('stored', true);
        $this->assertSame(1, DB::table('office_screenshots')->where('user_id', $staff->id)->count());

        /* Super Admin activity page shows the device */
        $this->actingAs($boss)->get('/office/admin/activity')->assertOk();
        Carbon::setTestNow();
    }

    public function test_login_clocks_in_and_idle_check_alerts(): void
    {
        Mail::fake();
        $boss = $this->super();
        app(SystemSettingService::class)->set('office.monitoring_enabled', true, 'office', 'boolean', $boss->id);
        app(SystemSettingService::class)->set('office.idle_minutes', 15, 'office', 'integer', $boss->id);
        $staff = User::query()->create(['name' => 'Idle', 'email' => 'idle@o.test', 'role' => 'staff', 'account_status' => 'active', 'password' => 'secret-pass', 'email_verified_at' => now()]);

        Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00', OfficeService::TZ));
        /* real login: password step sends the e-mailed code */
        $this->post('/login', ['email' => 'idle@o.test', 'password' => 'secret-pass'])->assertRedirect(route('login.otp.show'));
        $code = null;
        Mail::assertSent(LoginEmailOtpMail::class, function (LoginEmailOtpMail $m) use (&$code) { $code = $m->otp; return true; });
        /* entering the code logs in -> staff is clocked in and sent to My Office */
        $this->post(route('login.otp.verify'), ['otp' => $code])->assertRedirect('/office');
        $att = DB::table('office_attendance')->where('user_id', $staff->id)->first();
        $this->assertNotNull($att->clock_in_at);

        /* a device that was last active 20 min ago */
        OfficeDevice::query()->create([
            'public_id' => (string) \Illuminate\Support\Str::ulid(), 'user_id' => $staff->id, 'approved' => true,
            'token_hash' => hash('sha256', 'tk'), 'last_seen_at' => now(), 'last_active_at' => now()->subMinutes(20), 'foreground_app' => 'Chrome',
        ]);
        $this->artisan('office:idle-check')->assertSuccessful();
        $this->assertDatabaseHas('panel_notifications', ['user_id' => $boss->id, 'type' => 'office.idle']);
        Carbon::setTestNow();
    }
}
