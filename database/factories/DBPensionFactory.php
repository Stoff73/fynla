<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DBPension>
 */
class DBPensionFactory extends Factory
{
    protected $model = \App\Models\DBPension::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'scheme_name' => fake()->randomElement([
                'NHS Pension Scheme',
                'Teachers\' Pension',
                'Civil Service Pension',
                'Local Government Pension Scheme',
                'Police Pension Scheme',
            ]),
            'scheme_type' => fake()->randomElement(['final_salary', 'career_average', 'public_sector']),
            'accrued_annual_pension' => fake()->randomFloat(2, 5000, 40000),
            'pensionable_service_years' => fake()->randomFloat(2, 5, 35),
            'pensionable_salary' => fake()->randomFloat(2, 25000, 80000),
            'normal_retirement_age' => fake()->randomElement([60, 65, 66, 67, 68]),
            'revaluation_method' => fake()->randomElement([
                'CPI indexation',
                'RPI indexation',
                'Treasury Order',
                'Fixed rate',
            ]),
            'spouse_pension_percent' => fake()->randomElement([50.0, 66.67, 100.0]),
            'lump_sum_entitlement' => fake()->randomFloat(2, 0, 100000),
            'inflation_protection' => fake()->randomElement(['cpi', 'rpi', 'fixed', 'none']),
        ];
    }
}
