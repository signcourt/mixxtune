<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\V2\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* W135 MIXXTUNE_IDLE_LOGOUT */
class IdleTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_idle_user_past_the_limit_is_logged_out(): void
    {
        $u = $this->user();

        $this->withSession(['mt_last_activity' => now()->subMinutes(31)->getTimestamp()])
            ->actingAs($u)
            ->get('/profile')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_active_user_within_the_limit_stays_signed_in(): void
    {
        $u = $this->user();

        $this->withSession(['mt_last_activity' => now()->subMinutes(5)->getTimestamp()])
            ->actingAs($u)
            ->get('/profile')
            ->assertOk();

        $this->assertAuthenticated();
    }

    public function test_the_idle_limit_is_configurable_from_the_panel(): void
    {
        /* raise the limit to 60 minutes; 31 minutes idle must now be fine */
        app(SystemSettingService::class)->set('security.idle_minutes', 60, 'security', 'integer');

        $u = $this->user();

        $this->withSession(['mt_last_activity' => now()->subMinutes(31)->getTimestamp()])
            ->actingAs($u)
            ->get('/profile')
            ->assertOk();

        $this->assertAuthenticated();
    }

    public function test_the_office_heartbeat_does_not_keep_an_idle_session_alive(): void
    {
        /* a background ping must not refresh the idle clock */
        $u = User::factory()->create([
            'role' => 'staff',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        /* 10 minutes idle, then a background ping */
        $this->withSession(['mt_last_activity' => now()->subMinutes(10)->getTimestamp()])
            ->actingAs($u)
            ->post('/office/ping');

        /* the clock should still read ~10 min ago, so 31 min total idle logs out */
        $this->withSession(['mt_last_activity' => now()->subMinutes(31)->getTimestamp()])
            ->actingAs($u)
            ->get('/profile')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
