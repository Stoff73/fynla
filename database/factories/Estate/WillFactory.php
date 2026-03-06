<?php

declare(strict_types=1);

namespace Database\Factories\Estate;

use App\Models\Estate\Will;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Will>
 */
class WillFactory extends Factory
{
    protected $model = Will::class;

    public function definition(): array
    {
        $hasWill = fake()->boolean(70);

        return [
            'user_id' => User::factory(),
            'has_will' => $hasWill,
            'spouse_primary_beneficiary' => $hasWill ? fake()->boolean(80) : null,
            'spouse_bequest_percentage' => $hasWill ? fake()->randomElement([50.00, 75.00, 100.00]) : null,
            'executor_name' => $hasWill ? fake()->name() : null,
            'executor_notes' => $hasWill ? fake()->optional(0.3)->sentence() : null,
            'will_last_updated' => $hasWill ? fake()->dateTimeBetween('-5 years', 'now') : null,
        ];
    }

    /**
     * Indicate the user has a will.
     */
    public function withWill(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_will' => true,
            'spouse_primary_beneficiary' => true,
            'spouse_bequest_percentage' => 100.00,
            'executor_name' => fake()->name(),
            'will_last_updated' => fake()->dateTimeBetween('-2 years', 'now'),
        ]);
    }

    /**
     * Indicate the user has no will.
     */
    public function withoutWill(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_will' => false,
            'spouse_primary_beneficiary' => null,
            'spouse_bequest_percentage' => null,
            'executor_name' => null,
            'executor_notes' => null,
            'will_last_updated' => null,
        ]);
    }

    /**
     * Indicate the will is outdated (more than 3 years old).
     */
    public function outdated(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_will' => true,
            'will_last_updated' => fake()->dateTimeBetween('-10 years', '-3 years'),
        ]);
    }
}
