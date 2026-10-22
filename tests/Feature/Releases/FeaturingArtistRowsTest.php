<?php

namespace Tests\Feature\Releases;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* W84: featuring artist works like primary artist; blank boxes are ignored */
class FeaturingArtistRowsTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $user = User::factory()->create(['role' => 'artist', 'account_status' => 'active', 'email_verified_at' => now()]);
        $label = Label::factory()->create(['created_by' => $user->id]);
        $artist = Artist::factory()->create(['user_id' => $user->id, 'label_id' => $label->id, 'created_by' => $user->id]);

        return [$user, $label, $artist];
    }

    private function payload(Label $label, Artist $artist, array $extra = []): array
    {
        return array_merge([
            'catalog_number' => 'MT-UPC-'.uniqid(),
            'release_type' => 'single',
            'title' => 'Upc Rule Song',
            'artist_id' => $artist->id,
            'label_id' => $label->id,
            'primary_artist_name' => $artist->stage_name,
            'primary_artists' => [['name' => $artist->stage_name, 'artist_id' => $artist->id]],
            'featuring_artists' => [],
            'language' => 'Hindi',
            'primary_genre' => 'Devotional',
            'sub_genre' => 'Bhajan',
            'generate_upc' => true,
            'digital_release_date' => now()->addDays(14)->toDateString(),
            'copyright_owner' => 'Mixx Tune Entertainment',
            'copyright_year' => now()->format('Y'),
            'phonographic_owner' => 'Mixx Tune Entertainment',
            'phonographic_year' => now()->format('Y'),
        ], $extra);
    }

    public function test_blank_featuring_boxes_are_ignored_and_names_are_kept(): void
    {
        [$user, $label, $artist] = $this->context();

        $this->actingAs($user)->post(route('v2.releases.store'), $this->payload($label, $artist, [
            'featuring_artists' => [['name' => 'Guest One'], ['name' => '  '], ['name' => 'Guest Two'], ['name' => '']],
        ]))->assertSessionHasNoErrors();

        $release = Release::query()->where('title', 'Upc Rule Song')->firstOrFail();
        $this->assertSame('Guest One, Guest Two', $release->featuring_artist_name);
        $this->assertCount(2, $release->featuring_artists);

        /* only blank boxes = no featuring artist */
        $this->actingAs($user)->post(route('v2.releases.store'), $this->payload($label, $artist, [
            'title' => 'Solo Song', 'featuring_artists' => [['name' => '']],
        ]))->assertSessionHasNoErrors();
        $this->assertEmpty(Release::query()->where('title', 'Solo Song')->value('featuring_artist_name'));
    }
}
