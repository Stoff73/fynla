<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExpenditureProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenditureProfile>
 */
class ExpenditureProfileFactory extends Factory
{
    protected $model = ExpenditureProfile::class;

    public function definition(): array
    {
        $housing = fake()->randomFloat(2, 500, 2500);
        $utilities = fake()->randomFloat(2, 100, 400);
        $food = fake()->randomFloat(2, 200, 800);
        $transport = fake()->randomFloat(2, 50, 500);
        $insurance = fake()->randomFloat(2, 50, 300);
        $loans = fake()->randomFloat(2, 0, 500);
        $discretionary = fake()->randomFloat(2, 100, 1000);

        $total = $housing + $utilities + $food + $transport + $insurance + $loans + $discretionary;

        return [
            'user_id' => User::factory(),
            'monthly_housing' => $housing,
            'monthly_utilities' => $utilities,
            'monthly_food' => $food,
            'monthly_transport' => $transport,
            'monthly_insurance' => $insurance,
            'monthly_loans' => $loans,
            'monthly_discretionary' => $discretionary,
            'total_monthly_expenditure' => round($total, 2),
        ];
    }

    /**
     * A low-cost lifestyle.
     */
    public function lowCost(): static
    {
        return $this->state(fn (array $attributes) => [
            'monthly_housing' => fake()->randomFloat(2, 400, 800),
            'monthly_utilities' => fake()->randomFloat(2, 80, 150),
            'monthly_food' => fake()->randomFloat(2, 150, 300),
            'monthly_transport' => fake()->randomFloat(2, 30, 100),
            'monthly_insurance' => fake()->randomFloat(2, 30, 80),
            'monthly_loans' => 0,
            'monthly_discretionary' => fake()->randomFloat(2, 50, 200),
            'total_monthly_expenditure' => fake()->randomFloat(2, 800, 1600),
        ]);
    }

    /**
     * A high-cost lifestyle.
     */
    public function highCost(): static
    {
        return $this->state(fn (array $attributes) => [
            'monthly_housing' => fake()->randomFloat(2, 2000, 4000),
            'monthly_utilities' => fake()->randomFloat(2, 300, 600),
            'monthly_food' => fake()->randomFloat(2, 600, 1200),
            'monthly_transport' => fake()->randomFloat(2, 300, 800),
            'monthly_insurance' => fake()->randomFloat(2, 200, 500),
            'monthly_loans' => fake()->randomFloat(2, 300, 1000),
            'monthly_discretionary' => fake()->randomFloat(2, 500, 2000),
            'total_monthly_expenditure' => fake()->randomFloat(2, 4500, 10000),
        ]);
    }
}
