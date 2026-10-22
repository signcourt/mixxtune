<?php

namespace Tests\Feature\Releases;

use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/* W86 MIXXTUNE_ARTIST_NAME_SUGGESTIONS */
class ArtistNameSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function owner(): array
    {
        $user = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create(['user_id' => $user->id, 'created_by' => $user->id, 'parent_label_id' => null]);

        return [$user, $label];
    }

    public function test_names_used_before_are_offered_only_inside_the_account(): void
    {
        [$mine, $myLabel] = $this->owner();
        [, $otherLabel] = $this->owner();

        $release = Release::factory()->create(['label_id' => $myLabel->id, 'artist_id' => null, 'primary_artist_name' => 'Arijit Singh', 'featuring_artist_name' => 'Arun,  Neha Kakkar']);
        Track::factory()->create(['release_id' => $release->id, 'primary_artist_name' => 'arijit singh', 'featuring_artist_name' => 'Asha Bhosle']);
        Release::factory()->create(['label_id' => $otherLabel->id, 'artist_id' => null, 'primary_artist_name' => 'Arun Secret']);

        $this->actingAs($mine)->get(route('v2.releases.create'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('artistNameSuggestions', fn ($names) => collect($names)->values()->all() === ['Arijit Singh', 'Arun', 'Asha Bhosle', 'Neha Kakkar']));

        $super = User::factory()->create(['role' => 'super_admin', 'account_status' => 'active', 'email_verified_at' => now()]);
        $this->actingAs($super)->get(route('v2.releases.create'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('artistNameSuggestions', fn ($names) => collect($names)->contains('Arun Secret') && collect($names)->contains('Arijit Singh')));
    }
}
