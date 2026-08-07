<?php

namespace Database\Factories\Core;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Artist>
 */
class ArtistFactory extends Factory
{
    protected $model = Artist::class;

    public function definition(): array
    {
        $stageName = fake()->unique()->name();

        return [
            'public_id' => (string) Str::ulid(),
            'user_id' => User::factory()->state([
                'role' => 'artist',
                'account_status' => 'active',
            ]),
            'label_id' => Label::factory(),
            'stage_name' => $stageName,
            'legal_name' => $stageName,
            'slug' => Str::slug($stageName).'-'.fake()->unique()->numberBetween(
                1000,
                999999
            ),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+91##########'),
            'country' => 'India',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'bio' => fake()->sentence(),
            'account_status' => 'active',
            'kyc_status' => 'pending',
            'can_receive_splits' => true,
            'can_create_releases' => true,
            'created_by' => User::factory()->state([
                'role' => 'admin',
                'account_status' => 'active',
            ]),
            'updated_by' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'kyc_status' => 'verified',
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'account_status' => 'suspended',
            'can_create_releases' => false,
        ]);
    }
}
