<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LifeInsurancePolicy>
 */
class LifeInsurancePolicyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'policy_type' => fake()->randomElement(['term', 'whole_of_life', 'decreasing_term', 'family_income_benefit', 'level_term']),
            'provider' => fake()->company(),
            'policy_number' => fake()->unique()->numerify('LI######'),
            'sum_assured' => fake()->numberBetween(100000, 1000000),
            'premium_amount' => fake()->numberBetween(20, 200),
            'premium_frequency' => fake()->randomElement(['monthly', 'quarterly', 'annually']),
            'policy_start_date' => fake()->dateTimeBetween('-5 years', 'now'),
            'policy_term_years' => fake()->numberBetween(10, 30),
            'indexation_rate' => null,
            'in_trust' => fake()->boolean(30),
            'beneficiaries' => fake()->name(),
        ];
    }
}
