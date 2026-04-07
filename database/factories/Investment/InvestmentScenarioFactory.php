<?php

declare(strict_types=1);

namespace Database\Factories\Investment;

use App\Models\Investment\InvestmentScenario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentScenario>
 */
class InvestmentScenarioFactory extends Factory
{
    protected $model = InvestmentScenario::class;

    public function definition(): array
    {
        $scenarioType = fake()->randomElement(['monte_carlo', 'what_if', 'rebalance', 'withdrawal', 'contribution']);

        return [
            'user_id' => User::factory(),
            'scenario_name' => fake()->randomElement([
                'Retirement Portfolio Growth',
                'Market Downturn Impact',
                'Increased Contributions',
                'Early Retirement Scenario',
                'Conservative Allocation',
                'Aggressive Growth Strategy',
            ]),
            'description' => fake()->optional(0.7)->sentence(),
            'scenario_type' => $scenarioType,
            'template_name' => fake()->optional(0.3)->randomElement(['retirement_growth', 'market_crash', 'conservative_path']),
            'parameters' => [
                'time_horizon_years' => fake()->numberBetween(5, 30),
                'annual_return_percent' => fake()->randomFloat(2, 3, 10),
                'inflation_rate' => fake()->randomFloat(2, 1.5, 4.0),
                'monthly_contribution' => fake()->numberBetween(100, 2000),
            ],
            'results' => null,
            'comparison_data' => null,
            'is_saved' => fake()->boolean(60),
            'monte_carlo_job_id' => $scenarioType === 'monte_carlo' ? fake()->uuid() : null,
            'completed_at' => fake()->optional(0.5)->dateTimeBetween('-3 months', 'now'),
        ];
    }

    /**
     * A Monte Carlo simulation scenario.
     */
    public function monteCarlo(): static
    {
        return $this->state(fn (array $attributes) => [
            'scenario_type' => 'monte_carlo',
            'monte_carlo_job_id' => fake()->uuid(),
            'parameters' => [
                'simulations' => 1000,
                'time_horizon_years' => fake()->numberBetween(10, 30),
                'annual_return_percent' => fake()->randomFloat(2, 4, 8),
                'volatility_percent' => fake()->randomFloat(2, 10, 20),
                'inflation_rate' => 2.5,
            ],
        ]);
    }

    /**
     * A what-if scenario.
     */
    public function whatIf(): static
    {
        return $this->state(fn (array $attributes) => [
            'scenario_type' => 'what_if',
            'monte_carlo_job_id' => null,
        ]);
    }

    /**
     * A completed scenario with results.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'results' => [
                'final_value' => fake()->randomFloat(2, 50000, 1000000),
                'total_contributions' => fake()->randomFloat(2, 20000, 200000),
                'total_growth' => fake()->randomFloat(2, 10000, 500000),
                'annualised_return' => fake()->randomFloat(2, 3, 10),
            ],
        ]);
    }

    /**
     * A saved scenario.
     */
    public function saved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_saved' => true,
        ]);
    }

    /**
     * An unsaved (temporary) scenario.
     */
    public function unsaved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_saved' => false,
        ]);
    }
}
