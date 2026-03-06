<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DisabilityPolicy>
 */
class DisabilityPolicyFactory extends Factory
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
            'British Friendly',
        ];

        $occupationClasses = [
            'Class 1 (Low Risk)',
            'Class 2 (Medium Risk)',
            'Class 3 (High Risk)',
            'Class 4 (Very High Risk)',
        ];

        $policyStartDate = fake()->dateTimeBetween('-5 years', 'now');
        $hasOccupationClass = fake()->boolean(70);
        $hasPolicyNumber = fake()->boolean(90);
        $hasBenefitPeriod = fake()->boolean(80);
        $hasPolicyTerm = fake()->boolean(60);

        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement($providers),
            'policy_number' => $hasPolicyNumber ? ('DP'.fake()->numerify('######')) : null,
            'benefit_amount' => fake()->numberBetween(1000, 4000),
            'benefit_frequency' => fake()->randomElement(['monthly', 'weekly']),
            'deferred_period_weeks' => fake()->randomElement([4, 8, 13, 26]),
            'benefit_period_months' => $hasBenefitPeriod ? fake()->randomElement([12, 24, 36, 48, 60]) : null,
            'premium_amount' => fake()->randomFloat(2, 20, 80),
            'premium_frequency' => 'monthly',
            'occupation_class' => $hasOccupationClass ? fake()->randomElement($occupationClasses) : null,
            'policy_start_date' => $policyStartDate,
            'policy_term_years' => $hasPolicyTerm ? fake()->numberBetween(10, 25) : null,
            'coverage_type' => fake()->randomElement(['accident_only', 'accident_and_sickness']),
        ];
    }
}
