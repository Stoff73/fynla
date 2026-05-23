<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BusinessInterest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessInterest>
 */
class BusinessInterestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $businessType = fake()->randomElement(['sole_trader', 'partnership', 'limited_company', 'llp']);

        return [
            'user_id' => User::factory(),
            'business_name' => fake()->company(),
            'company_number' => $businessType === 'limited_company' ? fake()->numerify('########') : null,
            'business_type' => $businessType,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100.00,
            'current_valuation' => fake()->randomFloat(2, 50000, 1000000),
            'valuation_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'valuation_method' => fake()->randomElement(['Market value', 'Book value', 'Expert valuation']),
            'annual_revenue' => fake()->optional()->randomFloat(2, 100000, 2000000),
            'annual_profit' => fake()->optional()->randomFloat(2, 20000, 500000),
            'annual_dividend_income' => fake()->optional()->randomFloat(2, 5000, 100000),
            'description' => fake()->optional()->sentence(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function joint(?User $partner = null): static
    {
        return $this->state(fn (array $attrs) => [
            'ownership_type' => 'joint',
            'joint_owner_id' => $partner?->id ?? User::factory(),
            'ownership_percentage' => 50.00,
        ]);
    }
}
