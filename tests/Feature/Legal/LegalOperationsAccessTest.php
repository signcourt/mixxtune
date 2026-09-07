<?php

namespace Tests\Feature\Legal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalOperationsAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
        ]);
    }

    public function test_super_admin_can_open_legal_operations(): void
    {
        $user = $this->user('super_admin');

        $this->actingAs($user)
            ->get('/v2/legal-operations')
            ->assertOk();
    }

    public function test_admin_can_open_legal_operations(): void
    {
        $user = $this->user('admin');

        $this->actingAs($user)
            ->get('/v2/legal-operations')
            ->assertOk();
    }

    public function test_label_can_open_legal_operations(): void
    {
        $user = $this->user('label');

        $this->actingAs($user)
            ->get('/v2/legal-operations')
            ->assertOk();
    }

    public function test_artist_can_open_legal_operations(): void
    {
        $user = $this->user('artist');

        $this->actingAs($user)
            ->get('/v2/legal-operations')
            ->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/v2/legal-operations')
            ->assertRedirect('/login');
    }
}
