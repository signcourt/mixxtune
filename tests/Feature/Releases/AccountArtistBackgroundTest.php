<?php

namespace Tests\Feature\Releases;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* W83 MIXXTUNE_NO_ACCOUNT_ARTIST_FIELD: the label form has no "Account Artist"; it is linked in the background */
class AccountArtistBackgroundTest extends TestCase
{
    use RefreshDatabase;

    private function labelOwner(): array
    {
        $user = User::factory()->create(['role' => 'label', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create(['user_id' => $user->id, 'created_by' => $user->id, 'parent_label_id' => null]);

        return [$user, $label];
    }

    private function artist(Label $label, string $name): Artist
    {
        return Artist::factory()->create(['label_id' => $label->id, 'stage_name' => $name, 'account_status' => 'active', 'can_create_releases' => true]);
    }

    private function send(User $user, Label $label, string $primary, string $title)
    {
        return $this->actingAs($user)->post(route('v2.releases.store'), [
            'catalog_number' => 'MT-AA-'.uniqid(),
            'release_type' => 'single',
            'title' => $title,
            'label_id' => $label->id,
            'primary_artist_name' => $primary,
            'primary_artists' => [['name' => $primary]],
            'featuring_artists' => [],
            'language' => 'Hindi',
            'primary_genre' => 'Devotional',
            'sub_genre' => 'Bhajan',
            'generate_upc' => true,
            'digital_release_date' => now()->addDays(14)->toDateString(),
            'copyright_owner' => 'Label',
            'copyright_year' => now()->format('Y'),
            'phonographic_owner' => 'Label',
            'phonographic_year' => now()->format('Y'),
        ]);
    }

    public function test_release_is_made_without_choosing_an_artist_account(): void
    {
        [$user, $label] = $this->labelOwner();

        /* no artist account on the level at all - used to stop with "Select an artist" */
        $this->send($user, $label, 'Some Singer', 'No Account Song')->assertSessionHasNoErrors();
        $release = Release::query()->where('title', 'No Account Song')->firstOrFail();
        $this->assertNull($release->artist_id);
        $this->assertSame($label->id, (int) $release->label_id);

        /* several artist accounts: the one with the primary artist's name is linked */
        $this->artist($label, 'Other Voice');
        $asha = $this->artist($label, 'Asha Rani');
        $this->send($user, $label, ' asha rani ', 'Matched Song')->assertSessionHasNoErrors();
        $this->assertSame($asha->id, (int) Release::query()->where('title', 'Matched Song')->value('artist_id'));

        /* no matching name: release stays with the label only */
        $this->send($user, $label, 'Unknown Person', 'Unmatched Song')->assertSessionHasNoErrors();
        $this->assertNull(Release::query()->where('title', 'Unmatched Song')->value('artist_id'));

        /* the owner still sees and opens them */
        $this->actingAs($user)->get(route('v2.releases.edit', Release::query()->where('title', 'Unmatched Song')->firstOrFail()))->assertOk();
    }
}
