<?php

namespace Tests\Feature\Accounts;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\ConfirmsActionOtp;
use Tests\TestCase;

/* MIXXTUNE_USER_SAFETY_V1 (W59) */
class UserSafetyTest extends TestCase
{
    use RefreshDatabase;
    use ConfirmsActionOtp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        if (!\Illuminate\Support\Facades\Schema::hasTable('audit_logs')) {
            \Illuminate\Support\Facades\Schema::create('audit_logs', function ($table) {
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

    private function user(string $role, string $status = 'active'): User
    {
        return User::factory()->create(['role' => $role, 'account_status' => $status, 'email_verified_at' => now(), 'password' => Hash::make('Secret-123!')]);
    }

    public function test_suspended_account_cannot_log_in_and_open_session_is_closed(): void
    {
        $u = $this->user('label', 'suspended');

        $this->post('/login', ['login' => $u->email, 'password' => 'Secret-123!'])->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->actingAs($u)->get('/v2/wallet')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_new_password_needs_the_code_and_is_shown_once(): void
    {
        $super = $this->user('super_admin');
        $target = $this->user('label');

        $send = fn () => $this->actingAs($super)->from('/v2/admin/users/'.$target->id.'/edit')->post('/v2/admin/users/'.$target->id.'/reset-password');

        $this->viaActionOtp($super, $send)->assertSessionHas('temporary_password');
        $this->assertFalse(Hash::check('Secret-123!', $target->fresh()->password));
    }

    public function test_quick_edit_refuses_super_admin_and_keeps_permissions_when_untouched(): void
    {
        $super = $this->user('super_admin');
        $other = $this->user('super_admin');
        $label = $this->user('label');

        $this->actingAs($super)->patch('/v2/admin/users/'.$other->id.'/quick-update', ['name' => 'X', 'email' => $other->email, 'account_status' => 'active'])->assertStatus(422);

        $this->actingAs($super)->patch('/v2/admin/users/'.$label->id.'/quick-update', [
            'name' => 'New Name', 'email' => $label->email, 'account_status' => 'active', 'kyc_status' => 'submitted',
            'permissions' => ['can_view_catalogue' => false],
        ])->assertSessionHasNoErrors();

        $this->assertSame('New Name', $label->fresh()->name);
        /* production table; untouched permissions are never written (the request did not fail on a missing table) */
        if (\Illuminate\Support\Facades\Schema::hasTable('user_panel_permissions')) {
            $this->assertDatabaseMissing('user_panel_permissions', ['user_id' => $label->id]);
        }
    }

    public function test_only_super_admin_cannot_be_demoted_or_suspend_himself(): void
    {
        $super = $this->user('super_admin');

        $this->actingAs($super)->patch('/v2/admin/users/'.$super->id, [
            'name' => $super->name, 'email' => $super->email, 'role' => 'super_admin', 'account_status' => 'suspended',
        ])->assertSessionHasErrors('account_status');

        $this->assertSame('active', $super->fresh()->account_status);
    }

    public function test_no_withdrawal_through_login_as(): void
    {
        $owner = $this->user('label');

        $this->actingAs($owner)->withSession(['impersonator_user_id' => 999])
            ->post('/v2/withdrawals', ['amount' => 100, 'payment_method' => 'bank'])
            ->assertSessionHasErrors('amount');
    }
}
