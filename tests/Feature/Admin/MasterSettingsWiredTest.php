<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnforceMaintenanceMode;
use App\Models\User;
use App\Services\V2\PlatformRules;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
 * MIXXTUNE_SETTINGS_WIRED_V1
 * All data is generated inside the SQLite :memory: test database.
 */
class MasterSettingsWiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function ($table) {
                $table->id();
                $table->string('public_id', 40)->unique();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 100);
                $table->string('module', 100)->nullable();
                $table->string('auditable_type')->nullable();
                $table->unsignedBigInteger('auditable_id')->nullable();
                $table->text('description')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('request_method', 10)->nullable();
                $table->text('request_url')->nullable();
                $table->timestamps();
            });
        }
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function payload(array $over = []): array
    {
        return array_replace_recursive([
            'branding' => ['name' => 'MIXX TUNE', 'logo_url' => '/x.svg', 'login_logo_url' => '/x.svg'],
            'company' => ['name' => 'Mixx Tune'],
            'release' => ['minimum_days_ahead' => 1, 'allow_explicit' => true, 'require_artwork' => true, 'require_wav' => true],
            'upload' => ['audio_max_mb' => 300, 'artwork_max_mb' => 20, 'report_max_mb' => 500],
            'maintenance' => ['enabled' => false],
            'finance' => ['default_currency' => 'INR', 'minimum_withdrawal' => 1000, 'default_commission_percent' => 0],
            'invoice' => ['prefix' => 'INV', 'gst_percent' => 0, 'tds_percent' => 0],
        ], $over);
    }

    public function test_defaults_match_the_old_hard_coded_rules(): void
    {
        $this->assertSame(300, PlatformRules::audioMaxMb());
        $this->assertSame(20, PlatformRules::artworkMaxMb());
        $this->assertSame(500, PlatformRules::reportMaxMb());
        $this->assertSame(1, PlatformRules::releaseMinDays());
        $this->assertSame(now()->addDay()->toDateString(), PlatformRules::earliestReleaseDate()->toDateString());
        $this->assertTrue(PlatformRules::requireArtwork());
        $this->assertTrue(PlatformRules::allowExplicit());
        $this->assertSame('INV', PlatformRules::invoicePrefix());
    }

    public function test_saved_settings_are_used_by_the_app(): void
    {
        $super = $this->user('super_admin');

        $this->actingAs($super)->patch('/admin/settings', $this->payload([
            'release' => ['minimum_days_ahead' => 5, 'allow_explicit' => false, 'require_artwork' => false],
            'upload' => ['audio_max_mb' => 200, 'artwork_max_mb' => 10, 'report_max_mb' => 100],
            'invoice' => ['prefix' => 'MXT'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(200, PlatformRules::audioMaxMb());
        $this->assertSame(10, PlatformRules::artworkMaxMb());
        $this->assertSame(100, PlatformRules::reportMaxMb());
        $this->assertSame(now()->addDays(5)->toDateString(), PlatformRules::earliestReleaseDate()->toDateString());
        $this->assertFalse(PlatformRules::requireArtwork());
        $this->assertFalse(PlatformRules::allowExplicit());
        $this->assertSame('MXT', PlatformRules::invoicePrefix());

        $this->actingAs($super)->get('/admin/settings')->assertOk();
    }

    public function test_page_explains_what_is_wrong_and_refuses_half_filled_maintenance(): void
    {
        $super = $this->user('super_admin');

        $this->actingAs($super)->patch('/admin/settings', $this->payload(['maintenance' => ['enabled' => true]]))
            ->assertSessionHasErrors(['maintenance.date', 'maintenance.start_time', 'maintenance.end_time']);

        $this->actingAs($super)->patch('/admin/settings', $this->payload([
            'maintenance' => ['enabled' => true, 'date' => '2026-12-01', 'start_time' => '10:00', 'end_time' => '10:00'],
        ]))->assertSessionHasErrors('maintenance.end_time');

        $this->actingAs($super)->patch('/admin/settings', $this->payload(['invoice' => ['prefix' => 'MX-']]))
            ->assertSessionHasErrors('invoice.prefix');

        $this->actingAs($this->user('admin'))->patch('/admin/settings', $this->payload())->assertForbidden();
    }

    public function test_overnight_maintenance_window_is_active_after_midnight(): void
    {
        $super = $this->user('super_admin');
        $owner = $this->user('label');

        Carbon::setTestNow(Carbon::parse('2026-12-01 23:30', 'Asia/Kolkata'));

        $this->actingAs($super)->patch('/admin/settings', $this->payload([
            'maintenance' => ['enabled' => true, 'date' => '2026-12-01', 'start_time' => '23:00', 'end_time' => '02:00'],
        ]))->assertSessionHasNoErrors();

        Carbon::setTestNow(Carbon::parse('2026-12-02 01:00', 'Asia/Kolkata'));

        $request = Request::create('/');
        $request->setUserResolver(fn () => $owner);
        $this->assertTrue(EnforceMaintenanceMode::state($request)['active']);

        Carbon::setTestNow(Carbon::parse('2026-12-02 02:30', 'Asia/Kolkata'));
        $this->assertFalse(EnforceMaintenanceMode::state($request)['active']);

        Carbon::setTestNow();
    }

    /* MIXXTUNE_SETTINGS_LAYOUT_V1 */
    public function test_logo_upload_stores_the_file_and_refuses_other_types(): void
    {
        Storage::fake('public');
        $super = $this->user('super_admin');

        $path = $this->actingAs($super)->post('/admin/settings/branding/upload', [
            'type' => 'panel_logo', 'file' => UploadedFile::fake()->image('logo.png', 1200, 400),
        ])->assertOk()->json('path');

        $this->assertStringStartsWith('/storage/branding/panel_logo/', $path);
        Storage::disk('public')->assertExists(substr($path, strlen('/storage/')));

        $this->actingAs($super)->postJson('/admin/settings/branding/upload', [
            'type' => 'panel_logo', 'file' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
        ])->assertStatus(422);

        $this->actingAs($this->user('admin'))->post('/admin/settings/branding/upload', [
            'type' => 'favicon', 'file' => UploadedFile::fake()->image('f.png', 64, 64),
        ])->assertForbidden();
    }
}
