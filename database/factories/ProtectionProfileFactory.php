<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProtectionProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProtectionProfile>
 */
class ProtectionProfileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = ProtectionProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'annual_income' => fake()->numberBetween(25000, 150000),
            'monthly_expenditure' => fake()->numberBetween(2000, 8000),
            'mortgage_balance' => fake()->numberBetween(0, 500000),
            'other_debts' => fake()->numberBetween(0, 50000),
            'number_of_dependents' => fake()->numberBetween(0, 4),
            'dependents_ages' => fake()->randomElement([
                [],
                [5],
                [5, 10],
                [3, 8, 15],
            ]),
            'retirement_age' => fake()->numberBetween(65, 70),
            'occupation' => fake()->jobTitle(),
            'smoker_status' => fake()->boolean(20), // 20% chance of smoker
            'health_status' => fake()->randomElement(['excellent', 'good', 'fair', 'poor']),
        ];
    }
}
