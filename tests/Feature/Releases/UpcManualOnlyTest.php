<?php

namespace Tests\Feature\Releases;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* W82 MIXXTUNE_UPC_MANUAL_ONLY */
class UpcManualOnlyTest extends TestCase
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

    public function test_ask_mixx_tune_for_upc_leaves_it_empty_for_the_team(): void
    {
        [$user, $label, $artist] = $this->context();

        $this->actingAs($user)->post(route('v2.releases.store'), $this->payload($label, $artist))->assertSessionHasNoErrors();

        $release = Release::query()->where('title', 'Upc Rule Song')->firstOrFail();
        $this->assertNull($release->upc);
        $this->assertTrue((bool) $release->upc_is_auto_generated);
        $this->assertDatabaseMissing('upc_codes', ['release_id' => $release->id]);
    }

    public function test_typed_upc_is_verified_before_the_release_is_saved(): void
    {
        [$user, $label, $artist] = $this->context();

        /* wrong check digit */
        $this->actingAs($user)->post(route('v2.releases.store'), $this->payload($label, $artist, ['generate_upc' => false, 'upc' => '123456789013']))
            ->assertSessionHasErrors('upc');
        /* too short */
        $this->actingAs($user)->post(route('v2.releases.store'), $this->payload($label, $artist, ['generate_upc' => false, 'upc' => '12345']))
            ->assertSessionHasErrors('upc');
        $this->assertSame(0, Release::query()->where('title', 'Upc Rule Song')->count());

        /* correct UPC, typed with spaces */
        $this->actingAs($user)->post(route('v2.releases.store'), $this->payload($label, $artist, ['generate_upc' => false, 'upc' => '1234 5678 9012']))
            ->assertSessionHasNoErrors();
        $release = Release::query()->where('title', 'Upc Rule Song')->firstOrFail();
        $this->assertSame('123456789012', $release->upc);
        $this->assertFalse((bool) $release->upc_is_auto_generated);

        /* the same UPC again is refused and nothing is saved */
        $this->actingAs($user)->post(route('v2.releases.store'), $this->payload($label, $artist, ['generate_upc' => false, 'upc' => '123456789012', 'title' => 'Second Song']))
            ->assertSessionHasErrors('upc');
        $this->assertSame(0, Release::query()->where('title', 'Second Song')->count());
    }
}
