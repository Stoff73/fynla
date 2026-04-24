<?php

declare(strict_types=1);

namespace Database\Factories\Investment;

use App\Models\Investment\RiskProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiskProfile>
 */
class RiskProfileFactory extends Factory
{
    protected $model = RiskProfile::class;

    public function definition(): array
    {
        $riskTolerance = fake()->randomElement(['cautious', 'balanced', 'adventurous']);

        return [
            'user_id' => User::factory(),
            'risk_tolerance' => $riskTolerance,
            'risk_level' => fake()->randomElement(['low', 'lower_medium', 'medium', 'upper_medium', 'high']),
            'capacity_for_loss_percent' => fake()->randomFloat(2, 10, 50),
            'time_horizon_years' => fake()->numberBetween(5, 40),
            'knowledge_level' => fake()->randomElement(['novice', 'intermediate', 'experienced']),
            'attitude_to_volatility' => fake()->randomElement([
                'Very uncomfortable with any fluctuations',
                'Comfortable with small fluctuations',
                'Comfortable with moderate fluctuations',
                'Comfortable with significant fluctuations',
            ]),
            'esg_preference' => fake()->boolean(40),
            'risk_assessed_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'is_self_assessed' => fake()->boolean(80),
            'factor_breakdown' => null,
        ];
    }

    public function cautious(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_tolerance' => 'cautious',
            'capacity_for_loss_percent' => fake()->randomFloat(2, 10, 20),
            'attitude_to_volatility' => 'Comfortable with small fluctuations',
        ]);
    }

    public function balanced(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_tolerance' => 'balanced',
            'capacity_for_loss_percent' => fake()->randomFloat(2, 20, 35),
            'attitude_to_volatility' => 'Comfortable with moderate fluctuations',
        ]);
    }

    public function adventurous(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_tolerance' => 'adventurous',
            'capacity_for_loss_percent' => fake()->randomFloat(2, 35, 50),
            'attitude_to_volatility' => 'Comfortable with significant fluctuations',
        ]);
    }
}
