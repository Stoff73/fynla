<?php

declare(strict_types=1);

namespace Database\Factories\Estate;

use App\Models\Estate\IHTProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IHTProfile>
 */
class IHTProfileFactory extends Factory
{
    protected $model = IHTProfile::class;

    public function definition(): array
    {
        $hasSpouse = fake()->boolean(60);
        $ownHome = fake()->boolean(70);

        return [
            'user_id' => User::factory(),
            'marital_status' => $hasSpouse
                ? fake()->randomElement(['married', 'civil_partnership'])
                : fake()->randomElement(['single', 'divorced', 'widowed']),
            'has_spouse' => $hasSpouse,
            'own_home' => $ownHome,
            'home_value' => $ownHome ? fake()->randomFloat(2, 150000, 800000) : 0,
            'nrb_transferred_from_spouse' => 0,
            'rnrb_transferred_from_spouse' => 0,
            'charitable_giving_percent' => fake()->optional(0.2)->randomFloat(2, 1, 15),
        ];
    }

    /**
     * Indicate the profile is for a married individual.
     */
    public function married(): static
    {
        return $this->state(fn (array $attributes) => [
            'marital_status' => 'married',
            'has_spouse' => true,
        ]);
    }

    /**
     * Indicate the profile is for a single individual.
     */
    public function single(): static
    {
        return $this->state(fn (array $attributes) => [
            'marital_status' => 'single',
            'has_spouse' => false,
            'nrb_transferred_from_spouse' => 0,
            'rnrb_transferred_from_spouse' => 0,
        ]);
    }

    /**
     * Indicate the profile is for a widowed individual with transferred allowances.
     */
    public function widowed(): static
    {
        return $this->state(fn (array $attributes) => [
            'marital_status' => 'widowed',
            'has_spouse' => false,
            'nrb_transferred_from_spouse' => 325000,
            'rnrb_transferred_from_spouse' => 175000,
        ]);
    }

    /**
     * Indicate the individual is a homeowner.
     */
    public function homeOwner(): static
    {
        return $this->state(fn (array $attributes) => [
            'own_home' => true,
            'home_value' => fake()->randomFloat(2, 200000, 800000),
        ]);
    }

    /**
     * Indicate the individual is not a homeowner.
     */
    public function noHome(): static
    {
        return $this->state(fn (array $attributes) => [
            'own_home' => false,
            'home_value' => 0,
        ]);
    }

    /**
     * Indicate the individual gives enough to charity for the reduced IHT rate.
     */
    public function charitableGiver(): static
    {
        return $this->state(fn (array $attributes) => [
            'charitable_giving_percent' => fake()->randomFloat(2, 10, 15),
        ]);
    }
}
