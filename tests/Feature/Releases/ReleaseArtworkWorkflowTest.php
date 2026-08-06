<?php

namespace Tests\Feature\Releases;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReleaseArtworkWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createContext(): array
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

        $release = Release::factory()->create([
            'artist_id' => $artist->id,
            'label_id' => $label->id,
            'catalog_number' => 'MXT-ART-'.uniqid(),
            'release_type' => 'single',
            'title' => 'Artwork Test Release',
            'primary_artist_name' =>
                $artist->stage_name
                ?: $artist->legal_name,
            'status' => 'draft',
            'wizard_step' => 3,
            'completion_percentage' => 60,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return [
            $user,
            $label,
            $artist,
            $release,
        ];
    }

    private function updatePayload(
        Artist $artist,
        Release $release
    ): array {
        return [

            'catalog_number' =>
                $release->catalog_number,

            'artist_id' =>
                $artist->id,

            'label_id' =>
                $release->label_id,

            'release_type' =>
                $release->release_type
                ?: 'single',

            'title' =>
                $release->title,

            'version' =>
                $release->version,

            'primary_artists' => [
                [
                    'name' =>
                        $artist->stage_name
                        ?: $artist->legal_name,
                ],
            ],

            'featuring_artists' => [],

            'language' =>
                $release->language,

            'primary_genre' =>
                $release->primary_genre,

            'sub_genre' =>
                $release->sub_genre,

            'upc' =>
                $release->upc,

            'original_release_date' =>
                optional(
                    $release->original_release_date
                )->format('Y-m-d'),

            'digital_release_date' =>
                optional(
                    $release->digital_release_date
                )->format('Y-m-d'),

            'copyright_owner' =>
                $release->copyright_owner,

            'copyright_year' =>
                $release->copyright_year,

            'phonographic_owner' =>
                $release->phonographic_owner,

            'phonographic_year' =>
                $release->phonographic_year,

            'wizard_step' => 3,

            'completion_percentage' => 60,
        ];
    }

    private function updateUrl(
        Release $release
    ): string {
        return route(
            'v2.releases.update',
            $release
        );
    }

    public function test_artist_can_upload_release_artwork(): void
    {
        Storage::fake('public');

        [
            $user,
            ,
            $artist,
            $release,
        ] = $this->createContext();

        $artwork = UploadedFile::fake()->image(
            'cover.jpg',
            3000,
            3000
        );

        $response = $this
            ->actingAs($user)
            ->patch(
                $this->updateUrl($release),
                [
                    ...$this->updatePayload(
                        $artist,
                        $release
                    ),
                    'artwork' => $artwork,
                ]
            );

        $response->assertRedirect();

        $release->refresh();

        $this->assertNotNull(
            $release->artwork_path
        );

        $this->assertStringStartsWith(
            'releases/artwork/',
            $release->artwork_path
        );

        Storage::disk('public')->assertExists(
            $release->artwork_path
        );
    }

    public function test_artist_can_replace_existing_artwork(): void
    {
        Storage::fake('public');

        [
            $user,
            ,
            $artist,
            $release,
        ] = $this->createContext();

        $oldPath = UploadedFile::fake()
            ->image(
                'old-cover.jpg',
                3000,
                3000
            )
            ->store(
                'releases/artwork',
                'public'
            );

        $release->update([
            'artwork_path' => $oldPath,
        ]);

        Storage::disk('public')->assertExists(
            $oldPath
        );

        $newArtwork = UploadedFile::fake()
            ->image(
                'new-cover.png',
                3000,
                3000
            );

        $response = $this
            ->actingAs($user)
            ->patch(
                $this->updateUrl($release),
                [
                    ...$this->updatePayload(
                        $artist,
                        $release
                    ),
                    'artwork' => $newArtwork,
                ]
            );

        $response->assertRedirect();

        $release->refresh();

        $this->assertNotSame(
            $oldPath,
            $release->artwork_path
        );

        Storage::disk('public')->assertMissing(
            $oldPath
        );

        Storage::disk('public')->assertExists(
            $release->artwork_path
        );
    }

    public function test_non_image_artwork_is_rejected(): void
    {
        Storage::fake('public');

        [
            $user,
            ,
            $artist,
            $release,
        ] = $this->createContext();

        $invalidArtwork = UploadedFile::fake()
            ->create(
                'cover.pdf',
                200,
                'application/pdf'
            );

        $response = $this
            ->actingAs($user)
            ->patchJson(
                $this->updateUrl($release),
                [
                    ...$this->updatePayload(
                        $artist,
                        $release
                    ),
                    'artwork' => $invalidArtwork,
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'artwork'
            );

        $this->assertNull(
            $release->fresh()->artwork_path
        );
    }

    public function test_oversized_artwork_is_rejected(): void
    {
        Storage::fake('public');

        [
            $user,
            ,
            $artist,
            $release,
        ] = $this->createContext();

        $oversizedArtwork = UploadedFile::fake()
            ->image(
                'large-cover.jpg',
                3000,
                3000
            )
            ->size(20481);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                $this->updateUrl($release),
                [
                    ...$this->updatePayload(
                        $artist,
                        $release
                    ),
                    'artwork' => $oversizedArtwork,
                ]
            );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(
                'artwork'
            );

        $this->assertNull(
            $release->fresh()->artwork_path
        );
    }

    public function test_existing_artwork_remains_when_no_new_file_is_sent(): void
    {
        Storage::fake('public');

        [
            $user,
            ,
            $artist,
            $release,
        ] = $this->createContext();

        $existingPath = UploadedFile::fake()
            ->image(
                'existing-cover.jpg',
                3000,
                3000
            )
            ->store(
                'releases/artwork',
                'public'
            );

        $release->update([
            'artwork_path' => $existingPath,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(
                $this->updateUrl($release),
                $this->updatePayload(
                    $artist,
                    $release
                )
            );

        $response->assertRedirect();

        $this->assertSame(
            $existingPath,
            $release->fresh()->artwork_path
        );

        Storage::disk('public')->assertExists(
            $existingPath
        );
    }

    public function test_other_artist_cannot_replace_release_artwork(): void
    {
        Storage::fake('public');

        [
            ,
            ,
            $artist,
            $release,
        ] = $this->createContext();

        [
            $otherUser,
        ] = $this->createContext();

        $artwork = UploadedFile::fake()->image(
            'unauthorized-cover.jpg',
            3000,
            3000
        );

        $response = $this
            ->actingAs($otherUser)
            ->patchJson(
                $this->updateUrl($release),
                [
                    ...$this->updatePayload(
                        $artist,
                        $release
                    ),
                    'artwork' => $artwork,
                ]
            );

        $response->assertForbidden();

        $this->assertNull(
            $release->fresh()->artwork_path
        );

        Storage::disk('public')->assertMissing(
            'releases/artwork/'
            .$artwork->hashName()
        );
    }
}
