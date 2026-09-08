<?php

namespace Tests\Feature\V3;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecoupmentHttpSecurityTest extends TestCase
{
    use RefreshDatabase;

    private string $indexUrl =
        '/super-admin/finance/recoupment';

    private function userWithRole(
        string $role
    ): User {
        return User::factory()->create([
            'role' => $role,
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_guest_is_redirected_from_recoupment_index(): void
    {
        $this->get($this->indexUrl)
            ->assertRedirect('/login');
    }

    public function test_normal_admin_cannot_access_recoupment_index(): void
    {
        $this->actingAs(
            $this->userWithRole('admin')
        )
            ->get($this->indexUrl)
            ->assertForbidden();
    }

    public function test_artist_cannot_access_recoupment_index(): void
    {
        $this->actingAs(
            $this->userWithRole('artist')
        )
            ->get($this->indexUrl)
            ->assertForbidden();
    }

    public function test_label_cannot_access_recoupment_index(): void
    {
        $this->actingAs(
            $this->userWithRole('label')
        )
            ->get($this->indexUrl)
            ->assertForbidden();
    }

    public function test_super_admin_can_access_recoupment_index(): void
    {
        $this->actingAs(
            $this->userWithRole('super_admin')
        )
            ->get($this->indexUrl)
            ->assertOk();
    }

    public function test_normal_admin_cannot_create_recoupment_plan(): void
    {
        $admin = $this->userWithRole('admin');
        $target = $this->userWithRole('artist');

        $this->actingAs($admin)
            ->post(
                $this->indexUrl,
                [
                    'user_id' => $target->id,
                    'base_percentage' => 100,
                    'recovery_uplift_percentage' => 10,
                    'maximum_recovery_percentage' => 10,
                    'initial_amount' => 1000,
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'recoupment_plans',
            0
        );
    }

    public function test_artist_cannot_create_recoupment_plan(): void
    {
        $artist = $this->userWithRole('artist');

        $this->actingAs($artist)
            ->post(
                $this->indexUrl,
                [
                    'user_id' => $artist->id,
                    'base_percentage' => 100,
                    'recovery_uplift_percentage' => 10,
                    'maximum_recovery_percentage' => 10,
                    'initial_amount' => 1000,
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'recoupment_plans',
            0
        );
    }

    public function test_super_admin_can_create_plan_with_inertia_redirect_response(): void
    {
        $super = $this->userWithRole('super_admin');
        $target = $this->userWithRole('artist');

        $response = $this
            ->actingAs($super)
            ->from($this->indexUrl)
            ->post(
                $this->indexUrl,
                [
                    'user_id' => $target->id,
                    'base_percentage' => 100,
                    'recovery_uplift_percentage' => 10,
                    'maximum_recovery_percentage' => 10,
                    'initial_amount' => 1000,
                ]
            );

        $response->assertRedirect(
            $this->indexUrl
        );

        $response->assertSessionHas(
            'success',
            'Recoupment plan created.'
        );

        $this->assertDatabaseHas(
            'recoupment_plans',
            [
                'user_id' => $target->id,
                'status' => 'active',
            ]
        );
    }
}
