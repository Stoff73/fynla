<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RetirementProfile>
 */
class RetirementProfileFactory extends Factory
{
    protected $model = \App\Models\RetirementProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currentAge = $this->faker->numberBetween(25, 55);
        $targetRetirementAge = $this->faker->numberBetween(
            max(60, $currentAge + 5),
            68
        );

        $currentAnnualSalary = $this->faker->randomFloat(2, 25000, 100000);
        $targetRetirementIncome = $currentAnnualSalary * $this->faker->randomFloat(2, 0.5, 0.8); // 50-80% of current salary

        return [
            'user_id' => \App\Models\User::factory(),
            'current_age' => $currentAge,
            'target_retirement_age' => $targetRetirementAge,
            'current_annual_salary' => $currentAnnualSalary,
            'target_retirement_income' => $targetRetirementIncome,
            'essential_expenditure' => $this->faker->randomFloat(2, 15000, 40000),
            'lifestyle_expenditure' => $this->faker->randomFloat(2, 5000, 30000),
            'life_expectancy' => $this->faker->numberBetween(80, 95),
            'spouse_life_expectancy' => $this->faker->optional(0.6)->numberBetween(80, 95),
            'prior_year_unused_allowance' => null,
        ];
    }
}
