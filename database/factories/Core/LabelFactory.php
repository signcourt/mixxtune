<?php

namespace Database\Factories\Core;

use App\Models\Core\Label;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
{
    protected $model = Label::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' Records';

        return [
            'parent_label_id' => null,
            'label_type' => 'label',
            'public_id' => (string) Str::ulid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(
                1000,
                999999
            ),
            'legal_name' => $name.' Private Limited',
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->numerify('+91##########'),
            'website' => fake()->url(),
            'country' => 'India',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'payout_cycle' => 'monthly',
            'minimum_withdrawal_amount' => 5000,
            'royalty_share_percentage' => 100,
            'parent_commission_percentage' => 0,
            'status' => 'active',
            'can_access_catalogue' => true,
            'can_access_royalties' => true,
            'can_access_reports' => true,
            'can_access_wallet' => true,
            'can_withdraw' => true,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => 'inactive',
        ]);
    }
}
