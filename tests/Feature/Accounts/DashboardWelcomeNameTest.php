<?php

namespace Tests\Feature\Accounts;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* W78: the owner dashboard greets the account by the name it was created with */
class DashboardWelcomeNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_label_and_artist_dashboards_get_the_account_name(): void
    {
        $this->withoutVite();

        foreach (['label' => 'Sanatan Records', 'artist' => 'Asha Rani'] as $role => $name) {
            $user = User::factory()->create(['role' => $role, 'name' => $name, 'account_status' => 'active', 'email_verified_at' => now()]);

            $this->actingAs($user)->followingRedirects()->get('/dashboard')->assertOk()->assertInertia(fn (Assert $p) => $p
                ->component('V2/Dashboard')
                ->where('accountName', $name));
        }
    }
}
