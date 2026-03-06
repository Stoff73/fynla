<?php

namespace Database\Factories;

use App\Models\DCPension;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DCPension>
 */
class DCPensionFactory extends Factory
{
    protected $model = DCPension::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'scheme_name' => fake()->randomElement([
                'Workplace Pension',
                'SIPP',
                'Personal Pension',
                'Stakeholder Pension',
            ]),
            'scheme_type' => fake()->randomElement(['workplace', 'sipp', 'personal']),
            'provider' => fake()->randomElement([
                'Aviva',
                'Legal & General',
                'Scottish Widows',
                'Nest',
                'Standard Life',
                'Fidelity',
            ]),
            'member_number' => fake()->regexify('[A-Z]{2}[0-9]{6}'),
            'current_fund_value' => fake()->randomFloat(2, 10000, 500000),
            'employee_contribution_percent' => fake()->randomFloat(2, 3, 10),
            'employer_contribution_percent' => fake()->randomFloat(2, 3, 8),
            'monthly_contribution_amount' => fake()->randomFloat(2, 200, 1500),
            'investment_strategy' => fake()->randomElement([
                'Balanced Growth',
                'Cautious Growth',
                'Adventurous Growth',
                'Target Date Fund',
                'Ethical Investment',
            ]),
            'platform_fee_percent' => fake()->randomFloat(4, 0.25, 1.5),
            'retirement_age' => fake()->numberBetween(60, 68),
            'projected_value_at_retirement' => null,
        ];
    }
}
