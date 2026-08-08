<?php

namespace Tests\Feature\Releases;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseDraftWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createArtistContext(): array
    {
        $user = User::factory()->create([
            'role' => 'artist',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $label = Label::factory()->create([
            'created_by' => $user->id,
        ]);

        $artist = Artist::factory()->create([
            'user_id' => $user->id,
            'label_id' => $label->id,
            'created_by' => $user->id,
        ]);

        return [$user, $label, $artist];
    }

    public function test_verified_artist_can_open_release_create_page(): void
    {
        [$user] = $this->createArtistContext();

        $response = $this
            ->actingAs($user)
            ->get(route('v2.releases.create'));

        $response->assertOk();
    }

    public function test_verified_artist_can_create_release_draft(): void
    {
        [$user, $label, $artist] = $this->createArtistContext();

        $payload = [
            'catalog_number' => 'MT-TEST-0001',
            'release_type' => 'single',
            'title' => 'Test Devotional Release',
            'version' => null,
            'artist_id' => $artist->id,
            'label_id' => $label->id,
            'primary_artist_name' => $artist->stage_name,
            'primary_artists' => [
                [
                    'name' => $artist->stage_name,
                    'artist_id' => $artist->id,
                ],
            ],
            'featuring_artists' => [],
            'language' => 'Hindi',
            'primary_genre' => 'Devotional',
            'sub_genre' => 'Bhajan',
            'generate_upc' => true,
            'digital_release_date' => now()
                ->addDays(14)
                ->toDateString(),
            'copyright_owner' => 'Mixx Tune Entertainment',
            'copyright_year' => now()->format('Y'),
            'phonographic_owner' => 'Mixx Tune Entertainment',
            'phonographic_year' => now()->format('Y'),
        ];

        $response = $this
            ->actingAs($user)
            ->post(route('v2.releases.store'), $payload);

        $response->assertSessionHasNoErrors();

        $release = Release::query()
            ->where('title', 'Test Devotional Release')
            ->first();

        $this->assertNotNull($release);

        $this->assertSame(
            'draft',
            $release->status
        );

        $this->assertSame(
            $artist->id,
            $release->artist_id
        );

        $this->assertSame(
            $label->id,
            $release->label_id
        );

        $this->assertSame(
            $user->id,
            $release->created_by
        );

        $this->assertNotEmpty(
            $release->catalog_number
        );

        $response->assertRedirect(
            route(
                'v2.releases.edit',
                $release,
                absolute: false
            )
        );
    }

    public function test_artist_can_update_existing_draft(): void
    {
        [$user, $label, $artist] = $this->createArtistContext();

        $release = Release::factory()->create([
            'artist_id' => $artist->id,
            'label_id' => $label->id,
            'created_by' => $user->id,
            'updated_by' => null,
            'status' => 'draft',
            'title' => 'Old Draft Title',
            'primary_artist_name' => $artist->stage_name,
        ]);

        $payload = [
            'catalog_number' => 'MT-TEST-0002',
            'release_type' => 'single',
            'title' => 'Updated Draft Title',
            'version' => 'Acoustic Version',
            'artist_id' => $artist->id,
            'label_id' => $label->id,
            'primary_artist_name' => $artist->stage_name,
            'primary_artists' => [
                [
                    'name' => $artist->stage_name,
                    'artist_id' => $artist->id,
                ],
            ],
            'featuring_artists' => [],
            'language' => 'Hindi',
            'primary_genre' => 'Devotional',
            'sub_genre' => 'Bhajan',
            'generate_upc' => true,
            'digital_release_date' => now()
                ->addDays(21)
                ->toDateString(),
            'copyright_owner' => 'Mixx Tune Entertainment',
            'copyright_year' => now()->format('Y'),
            'phonographic_owner' => 'Mixx Tune Entertainment',
            'phonographic_year' => now()->format('Y'),
        ];

        $response = $this
            ->actingAs($user)
            ->patch(
                route('v2.releases.update', $release),
                $payload
            );

        $response->assertSessionHasNoErrors();

        $release->refresh();

        $this->assertSame(
            'Updated Draft Title',
            $release->title
        );

        $this->assertSame(
            'Acoustic Version',
            $release->version
        );

        $this->assertSame(
            'draft',
            $release->status
        );

        $this->assertSame(
            $user->id,
            $release->updated_by
        );

        $response->assertRedirect(
            route(
                'v2.releases.edit',
                $release,
                absolute: false
            )
        );
    }

    public function test_guest_cannot_create_release(): void
    {
        $response = $this->post(
            route('v2.releases.store'),
            []
        );

        $response->assertRedirect(
            route('login')
        );

        $this->assertDatabaseCount(
            'releases',
            0
        );
    }
}
