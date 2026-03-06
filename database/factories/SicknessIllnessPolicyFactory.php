<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SicknessIllnessPolicy>
 */
class SicknessIllnessPolicyFactory extends Factory
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
            'Benenden Health',
        ];

        $conditions = [
            'Common Cold and Flu',
            'Gastroenteritis',
            'Back Pain',
            'Musculoskeletal Disorders',
            'Stress and Anxiety',
            'Depression',
            'Respiratory Infections',
            'Minor Injuries',
            'Headaches and Migraines',
            'Minor Surgical Procedures',
        ];

        $exclusions = [
            'Pre-existing conditions',
            'Self-inflicted injuries',
            'Injuries from hazardous activities',
            'Pandemic-related illnesses',
            'Mental health conditions (excluded)',
            'Pregnancy-related complications',
        ];

        $policyStartDate = fake()->dateTimeBetween('-5 years', 'now');
        $hasPolicyNumber = fake()->boolean(85);
        $hasDeferredPeriod = fake()->boolean(70);
        $hasBenefitPeriod = fake()->boolean(75);
        $hasPolicyTerm = fake()->boolean(65);
        $hasConditionsCovered = fake()->boolean(80);
        $hasExclusions = fake()->boolean(90);

        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement($providers),
            'policy_number' => $hasPolicyNumber ? ('SI'.fake()->numerify('######')) : null,
            'benefit_amount' => fake()->numberBetween(10000, 100000),
            'benefit_frequency' => fake()->randomElement(['monthly', 'weekly', 'lump_sum']),
            'deferred_period_weeks' => $hasDeferredPeriod ? fake()->numberBetween(0, 8) : null,
            'benefit_period_months' => $hasBenefitPeriod ? fake()->numberBetween(6, 24) : null,
            'premium_amount' => fake()->randomFloat(2, 15, 60),
            'premium_frequency' => 'monthly',
            'policy_start_date' => $policyStartDate,
            'policy_term_years' => $hasPolicyTerm ? fake()->numberBetween(10, 20) : null,
            'conditions_covered' => $hasConditionsCovered ? fake()->randomElements($conditions, fake()->numberBetween(3, 8)) : null,
            'exclusions' => $hasExclusions ? implode('; ', fake()->randomElements($exclusions, fake()->numberBetween(2, 5))) : null,
        ];
    }
}
