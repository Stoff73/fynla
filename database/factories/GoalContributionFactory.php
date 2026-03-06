<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Goal;
use App\Models\GoalContribution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoalContribution>
 */
class GoalContributionFactory extends Factory
{
    protected $model = GoalContribution::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 25, 2000);

        return [
            'goal_id' => Goal::factory(),
            'user_id' => User::factory(),
            'amount' => $amount,
            'contribution_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'contribution_type' => fake()->randomElement(['manual', 'automatic', 'lump_sum', 'interest', 'adjustment']),
            'notes' => fake()->optional(0.2)->sentence(),
            'goal_balance_after' => fake()->randomFloat(2, $amount, 50000),
            'streak_qualifying' => true,
        ];
    }

    /**
     * A manual contribution.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'contribution_type' => 'manual',
            'streak_qualifying' => true,
        ]);
    }

    /**
     * An automatic (standing order) contribution.
     */
    public function automatic(): static
    {
        return $this->state(fn (array $attributes) => [
            'contribution_type' => 'automatic',
            'streak_qualifying' => true,
        ]);
    }

    /**
     * A lump sum contribution.
     */
    public function lumpSum(): static
    {
        return $this->state(fn (array $attributes) => [
            'contribution_type' => 'lump_sum',
            'amount' => fake()->randomFloat(2, 1000, 20000),
            'streak_qualifying' => false,
        ]);
    }

    /**
     * An interest accrual entry.
     */
    public function interest(): static
    {
        return $this->state(fn (array $attributes) => [
            'contribution_type' => 'interest',
            'amount' => fake()->randomFloat(2, 1, 100),
            'streak_qualifying' => false,
        ]);
    }

    /**
     * A balance adjustment (e.g. correction or revaluation).
     */
    public function adjustment(): static
    {
        return $this->state(fn (array $attributes) => [
            'contribution_type' => 'adjustment',
            'amount' => fake()->randomFloat(2, -500, 500),
            'streak_qualifying' => false,
            'notes' => 'Balance adjustment',
        ]);
    }
}
