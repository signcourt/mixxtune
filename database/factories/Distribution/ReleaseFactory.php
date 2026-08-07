<?php

namespace Database\Factories\Distribution;

use App\Models\Core\Artist;
use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Release>
 */
class ReleaseFactory extends Factory
{
    protected $model = Release::class;

    public function definition(): array
    {
        $artistName = fake()->name();

        return [
            'public_id' => (string) Str::ulid(),
            'catalog_number' => 'MT-'.fake()->unique()->numerify(
                '########'
            ),
            'artist_id' => Artist::factory(),
            'label_id' => null,
            'release_type' => fake()->randomElement([
                'single',
                'ep',
                'album',
            ]),
            'title' => fake()->unique()->sentence(3),
            'version' => null,
            'primary_artist_name' => $artistName,
            'primary_artists' => null,
            'featuring_artist_name' => null,
            'featuring_artists' => null,
            'language' => 'Hindi',
            'primary_genre' => 'Devotional',
            'sub_genre' => null,
            'upc' => null,
            'upc_is_auto_generated' => false,
            'original_release_date' => now()->toDateString(),
            'digital_release_date' => now()
                ->addDays(14)
                ->toDateString(),
            'copyright_owner' => 'Mixx Tune Entertainment',
            'copyright_year' => now()->format('Y'),
            'phonographic_owner' => 'Mixx Tune Entertainment',
            'phonographic_year' => now()->format('Y'),
            'artwork_path' => null,
            'status' => 'draft',
            'review_notes' => null,
            'rejection_reason' => null,
            'stores' => null,
            'excluded_store_ids' => null,
            'territories' => null,
            'worldwide' => true,
            'release_timezone' => 'Asia/Kolkata',
            'pre_order' => false,
            'wizard_step' => 1,
            'completion_percentage' => 0,
            'created_by' => User::factory()->state([
                'role' => 'artist',
                'account_status' => 'active',
            ]),
            'updated_by' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => 'submitted',
            'submitted_at' => now(),
            'wizard_step' => 5,
            'completion_percentage' => 100,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'submitted_at' => now()->subHour(),
            'approved_at' => now(),
            'wizard_step' => 5,
            'completion_percentage' => 100,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => 'rejected',
            'submitted_at' => now()->subHour(),
            'rejected_at' => now(),
            'rejection_reason' => 'Metadata correction required.',
        ]);
    }
}
