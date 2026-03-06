<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IncomeProtectionPolicy>
 */
class IncomeProtectionPolicyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $providers = [
            'Aviva',
            'Legal & General',
            'Royal London',
            'Vitality',
            'AIG Life',
            'LV=',
            'Scottish Widows',
            'Zurich',
            'The Exeter',
            'British Friendly',
        ];

        $occupationClasses = [
            'Class 1 (Low Risk)',
            'Class 2 (Medium Risk)',
            'Class 3 (High Risk)',
            'Class 4 (Very High Risk)',
        ];

        $policyStartDate = fake()->dateTimeBetween('-5 years', 'now');

        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement($providers),
            'policy_number' => 'IP'.fake()->unique()->numerify('######'),
            'benefit_amount' => fake()->numberBetween(1000, 5000),
            'benefit_frequency' => fake()->randomElement(['monthly', 'weekly']),
            'deferred_period_weeks' => fake()->randomElement([4, 8, 13, 26, 52]),
            'benefit_period_months' => fake()->randomElement([12, 24, 36, 48, 60]),
            'premium_amount' => fake()->randomFloat(2, 20, 100),
            'occupation_class' => fake()->randomElement($occupationClasses),
            'policy_start_date' => $policyStartDate,
        ];
    }
}
