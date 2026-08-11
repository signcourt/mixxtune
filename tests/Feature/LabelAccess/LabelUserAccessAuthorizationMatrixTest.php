<?php

namespace Tests\Feature\LabelAccess;

use App\Models\Core\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelUserAccessAuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_label_owner_can_open_user_access(): void
    {
        $owner = User::factory()->create([
            'role' => 'label',
        ]);

        Label::factory()->create([
            'user_id' => $owner->id,
            'parent_label_id' => null,
        ]);

        $this->actingAs($owner)
            ->get('/v2/label/user-access')
            ->assertOk();
    }

    public function test_child_label_owner_cannot_open_user_access(): void
    {
        $masterOwner = User::factory()->create([
            'role' => 'label',
        ]);

        $master = Label::factory()->create([
            'user_id' => $masterOwner->id,
            'parent_label_id' => null,
        ]);

        $childOwner = User::factory()->create([
            'role' => 'label',
        ]);

        Label::factory()->create([
            'user_id' => $childOwner->id,
            'parent_label_id' => $master->id,
        ]);

        $response = $this->actingAs($childOwner)
            ->get('/v2/label/user-access');

        $this->assertNotSame(
            200,
            $response->getStatusCode()
        );
    }

    public function test_artist_cannot_open_label_user_access(): void
    {
        $artist = User::factory()->create([
            'role' => 'artist',
        ]);

        $response = $this->actingAs($artist)
            ->get('/v2/label/user-access');

        $this->assertNotSame(
            200,
            $response->getStatusCode()
        );
    }

    public function test_admin_cannot_open_label_user_access(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)
            ->get('/v2/label/user-access');

        $this->assertNotSame(
            200,
            $response->getStatusCode()
        );
    }

    public function test_super_admin_cannot_open_label_user_access(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
        ]);

        $response = $this->actingAs($superAdmin)
            ->get('/v2/label/user-access');

        $this->assertNotSame(
            200,
            $response->getStatusCode()
        );
    }
}
