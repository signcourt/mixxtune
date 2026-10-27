<?php

namespace Tests\Feature\Releases;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* W119 MIXXTUNE_OWNER_RELEASE_STATUS (generated test data only) */
class OwnerReleaseStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_label_processing_filter_covers_approved_until_live(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create(['user_id' => $owner->id, 'created_by' => $owner->id, 'parent_label_id' => null]);
        $artist = Artist::factory()->create(['user_id' => $owner->id, 'label_id' => $label->id, 'created_by' => $owner->id]);
        foreach (['approved', 'processing', 'delivered', 'live', 'rejected'] as $s) {
            Release::factory()->create(['artist_id' => $artist->id, 'label_id' => $label->id, 'catalog_number' => 'MXT-OS-'.uniqid(), 'title' => 'Song '.$s, 'status' => $s, 'created_by' => $owner->id, 'updated_by' => $owner->id]);
        }

        $this->actingAs($owner)->get(route('v2.releases.index', ['status' => 'processing']))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('releases.total', 3));
        $this->actingAs($owner)->get(route('v2.releases.index', ['status' => 'live']))
            ->assertInertia(fn (Assert $p) => $p->where('releases.total', 1));

        /* the team still sees the real steps */
        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $this->actingAs($super)->get(route('v2.releases.index', ['status' => 'processing']))
            ->assertInertia(fn (Assert $p) => $p->where('releases.total', 1));
    }
}
