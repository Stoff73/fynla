<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SavingsGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsGoal>
 */
class SavingsGoalFactory extends Factory
{
    protected $model = SavingsGoal::class;

    public function definition(): array
    {
        $targetAmount = fake()->randomFloat(2, 1000, 50000);
        $currentSaved = fake()->randomFloat(2, 0, $targetAmount * 0.8);

        return [
            'user_id' => User::factory(),
            'goal_name' => fake()->randomElement([
                'Emergency Fund',
                'Holiday Fund',
                'House Deposit',
                'New Car',
                'Wedding Fund',
                'Home Improvements',
                'Christmas Fund',
            ]),
            'target_amount' => $targetAmount,
            'current_saved' => $currentSaved,
            'target_date' => fake()->dateTimeBetween('+1 month', '+3 years'),
            'priority' => fake()->randomElement(['high', 'medium', 'low']),
            'linked_account_id' => null,
            'auto_transfer_amount' => null,
        ];
    }

    /**
     * A high-priority goal.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }

    /**
     * A goal that is almost complete.
     */
    public function nearlyComplete(): static
    {
        return $this->state(function (array $attributes) {
            $target = $attributes['target_amount'] ?? 10000;

            return [
                'current_saved' => $target * fake()->randomFloat(2, 0.85, 0.99),
            ];
        });
    }

    /**
     * A goal with auto-transfer enabled.
     */
    public function withAutoTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_transfer_amount' => fake()->randomFloat(2, 50, 500),
        ]);
    }

    /**
     * An emergency fund goal.
     */
    public function emergencyFund(): static
    {
        return $this->state(fn (array $attributes) => [
            'goal_name' => 'Emergency Fund',
            'target_amount' => fake()->randomFloat(2, 5000, 15000),
            'priority' => 'high',
        ]);
    }

    /**
     * A house deposit goal.
     */
    public function houseDeposit(): static
    {
        return $this->state(fn (array $attributes) => [
            'goal_name' => 'House Deposit',
            'target_amount' => fake()->randomFloat(2, 15000, 50000),
            'priority' => 'high',
            'target_date' => fake()->dateTimeBetween('+1 year', '+5 years'),
        ]);
    }
}
