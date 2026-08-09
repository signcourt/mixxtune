<?php

namespace Tests\Feature\Labels;

use App\Models\Core\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelReleaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeLabelUser(): array
    {
        $user = User::factory()->create([
            'role' => 'label',
            'email_verified_at' => now(),
        ]);

        $label = Label::factory()->create([
            'user_id' => $user->id,
        ]);

        return [$user, $label];
    }

    public function test_label_release_alias_redirects_to_v2_release_index(): void
    {
        [$user] = $this->makeLabelUser();

        $response = $this
            ->actingAs($user)
            ->get('/label/releases');

        $response->assertRedirect(
            route('v2.releases.index')
        );
    }

    public function test_label_can_open_v2_release_index(): void
    {
        [$user] = $this->makeLabelUser();

        $response = $this
            ->actingAs($user)
            ->get('/v2/releases');

        $response->assertSuccessful();
    }

    public function test_label_can_open_v2_release_create_workflow(): void
    {
        [$user] = $this->makeLabelUser();

        $response = $this
            ->actingAs($user)
            ->get('/v2/releases/create');

        $response->assertSuccessful();
    }

    public function test_label_release_routes_are_registered(): void
    {
        $router = app('router');

        $this->assertNotNull(
            $router->getRoutes()->getByName(
                'single.label.releases.index'
            )
        );

        $this->assertNotNull(
            $router->getRoutes()->getByName(
                'v2.releases.index'
            )
        );

        $this->assertNotNull(
            $router->getRoutes()->getByName(
                'v2.releases.create'
            )
        );
    }
}
