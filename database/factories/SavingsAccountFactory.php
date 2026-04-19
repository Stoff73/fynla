<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SavingsAccount>
 */
class SavingsAccountFactory extends Factory
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
            'account_type' => fake()->randomElement(['easy_access', 'notice', 'fixed_rate']),
            'institution' => fake()->company(),
            'account_number' => fake()->numerify('########'),
            'current_balance' => fake()->randomFloat(2, 100, 50000),
            'interest_rate' => fake()->randomFloat(4, 0.01, 0.05),
            'access_type' => fake()->randomElement(['immediate', 'notice', 'fixed']),
            'notice_period_days' => null,
            'maturity_date' => null,
            'is_isa' => false,
            'isa_type' => null,
            'isa_subscription_year' => null,
            'isa_subscription_amount' => null,
        ];
    }
}
