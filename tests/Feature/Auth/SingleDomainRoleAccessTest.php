<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SingleDomainRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_artist_login_redirects_to_artist_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'artist',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);

        $response->assertRedirect(
            '/artist/dashboard'
        );
    }

    public function test_label_login_redirects_to_label_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'label',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);

        $response->assertRedirect(
            '/label/dashboard'
        );
    }

    public function test_admin_login_redirects_to_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);

        $response->assertRedirect(
            '/admin/dashboard'
        );
    }

    public function test_super_admin_login_redirects_to_super_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);

        $response->assertRedirect(
            '/super-admin/dashboard'
        );
    }

    public function test_guests_are_redirected_to_login_from_all_panel_dashboards(): void
    {
        foreach (
            [
                '/artist/dashboard',
                '/label/dashboard',
                '/admin/dashboard',
                '/super-admin/dashboard',
            ] as $path
        ) {
            $this->get($path)
                ->assertRedirect('/login');
        }
    }

    public function test_artist_cannot_access_other_panel_dashboards(): void
    {
        $user = User::factory()->create([
            'role' => 'artist',
        ]);

        $this->actingAs($user)
            ->get('/label/dashboard')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/super-admin/dashboard')
            ->assertForbidden();
    }

    public function test_label_cannot_access_other_panel_dashboards(): void
    {
        $user = User::factory()->create([
            'role' => 'label',
        ]);

        $this->actingAs($user)
            ->get('/artist/dashboard')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/super-admin/dashboard')
            ->assertForbidden();
    }

    public function test_admin_cannot_access_artist_label_or_super_admin_dashboards(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($user)
            ->get('/artist/dashboard')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/label/dashboard')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/super-admin/dashboard')
            ->assertForbidden();
    }

    public function test_super_admin_cannot_accidentally_use_lower_role_panel_aliases(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $this->actingAs($user)
            ->get('/artist/dashboard')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/label/dashboard')
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_invalid_password_does_not_authenticate_user(): void
    {
        $user = User::factory()->create([
            'role' => 'artist',
        ]);

        $response = $this
            ->from('/login')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

        $this->assertGuest();

        $response
            ->assertRedirect('/login')
            ->assertSessionHasErrors();
    }

    public function test_logout_redirects_to_public_home(): void
    {
        $user = User::factory()->create([
            'role' => 'artist',
        ]);

        $response = $this
            ->actingAs($user)
            ->post('/logout');

        $this->assertGuest();

        $response->assertRedirect('/');
    }
}
