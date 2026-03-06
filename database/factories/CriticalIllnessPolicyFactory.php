<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CriticalIllnessPolicy>
 */
class CriticalIllnessPolicyFactory extends Factory
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
        ];

        $conditions = [
            'Cancer',
            'Heart Attack',
            'Stroke',
            'Coronary Artery Bypass',
            'Kidney Failure',
            'Major Organ Transplant',
            'Multiple Sclerosis',
            'Parkinson\'s Disease',
            'Motor Neurone Disease',
            'Alzheimer\'s Disease',
            'Blindness',
            'Deafness',
            'Loss of Limbs',
            'Paralysis',
            'Third Degree Burns',
        ];

        $policyStartDate = fake()->dateTimeBetween('-5 years', 'now');

        return [
            'user_id' => User::factory(),
            'policy_type' => fake()->randomElement(['standalone', 'accelerated']),
            'provider' => fake()->randomElement($providers),
            'policy_number' => 'CI'.fake()->unique()->numerify('######'),
            'sum_assured' => fake()->numberBetween(50000, 500000),
            'premium_amount' => fake()->randomFloat(2, 30, 150),
            'premium_frequency' => fake()->randomElement(['monthly', 'quarterly', 'annually']),
            'policy_start_date' => $policyStartDate,
            'policy_term_years' => fake()->numberBetween(10, 25),
            'conditions_covered' => fake()->randomElements($conditions, fake()->numberBetween(5, 12)),
        ];
    }
}
