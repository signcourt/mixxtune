<?php

namespace Tests\Feature\Routing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/* W92 MIXXTUNE_NO_V2_IN_URL: panel pages live without /v2; old /v2 links still open */
class NoV2InUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_route_starts_with_v2_except_the_old_link_redirect(): void
    {
        $left = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->uri())
            ->filter(fn ($uri) => str_starts_with($uri, 'v2/') || $uri === 'v2')
            ->values()->all();

        $this->assertSame(['v2/{path?}'], $left);
    }

    public function test_old_v2_links_go_to_the_same_page_without_v2(): void
    {
        $this->get('/v2/releases/create')->assertStatus(301)->assertRedirect('/releases/create');
        $this->get('/v2/admin/profit?stage=all&x=1')->assertStatus(301)->assertRedirect('/admin/profit?stage=all&x=1');
        $this->get('/v2')->assertStatus(301)->assertRedirect('/');
        $this->post('/v2/support')->assertStatus(308)->assertRedirect('/support');
    }

    public function test_panel_page_opens_without_v2(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);

        $this->actingAs($user)->get('/releases/create')->assertOk();
        $this->actingAs($user)->followingRedirects()->get('/v2/releases/create')->assertOk();
        $this->assertStringNotContainsString('/v2/', route('v2.releases.create'));
    }
}
