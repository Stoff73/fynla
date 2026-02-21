<?php

declare(strict_types=1);

namespace Database\Factories\Investment;

use App\Models\Investment\InvestmentPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentPlan>
 */
class InvestmentPlanFactory extends Factory
{
    protected $model = InvestmentPlan::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_version' => $this->faker->numberBetween(1, 5),
            'plan_data' => [
                'total_portfolio_value' => $this->faker->randomFloat(2, 10000, 500000),
                'target_allocation' => [
                    'equities' => $this->faker->numberBetween(40, 80),
                    'bonds' => $this->faker->numberBetween(10, 40),
                    'cash' => $this->faker->numberBetween(5, 20),
                    'alternatives' => $this->faker->numberBetween(0, 15),
                ],
                'rebalancing_frequency' => $this->faker->randomElement(['quarterly', 'semi_annually', 'annually']),
            ],
            'portfolio_health_score' => $this->faker->numberBetween(40, 100),
            'is_complete' => $this->faker->boolean(70),
            'completeness_score' => $this->faker->numberBetween(30, 100),
            'generated_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
        ];
    }

    /**
     * A complete plan with a high health score.
     */
    public function complete(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_complete' => true,
            'completeness_score' => 100,
            'portfolio_health_score' => $this->faker->numberBetween(75, 100),
        ]);
    }

    /**
     * An incomplete plan.
     */
    public function incomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_complete' => false,
            'completeness_score' => $this->faker->numberBetween(10, 60),
            'portfolio_health_score' => $this->faker->numberBetween(20, 50),
        ]);
    }

    /**
     * A plan with a poor health score.
     */
    public function poorHealth(): static
    {
        return $this->state(fn (array $attributes) => [
            'portfolio_health_score' => $this->faker->numberBetween(0, 30),
        ]);
    }
}
