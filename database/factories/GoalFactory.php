<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    protected $model = Goal::class;

    public function definition(): array
    {
        $goalType = fake()->randomElement([
            'emergency_fund',
            'property_purchase',
            'home_deposit',
            'education',
            'retirement',
            'wealth_accumulation',
            'wedding',
            'holiday',
            'car_purchase',
            'debt_repayment',
            'custom',
        ]);

        $targetAmount = fake()->randomFloat(2, 1000, 200000);
        $currentAmount = fake()->randomFloat(2, 0, $targetAmount * 0.8);
        $startDate = fake()->dateTimeBetween('-2 years', '-1 month');

        $assignedModule = match ($goalType) {
            'property_purchase', 'home_deposit' => 'property',
            'retirement' => 'retirement',
            'wealth_accumulation' => 'investment',
            default => fake()->randomElement(['savings', 'investment']),
        };

        return [
            'user_id' => User::factory(),
            'goal_name' => $this->generateGoalName($goalType),
            'goal_type' => $goalType,
            'custom_goal_type_name' => $goalType === 'custom' ? fake()->words(3, true) : null,
            'description' => fake()->optional(0.5)->sentence(),
            'target_amount' => $targetAmount,
            'current_amount' => $currentAmount,
            'target_date' => fake()->dateTimeBetween('+6 months', '+10 years'),
            'start_date' => $startDate,
            'assigned_module' => $assignedModule,
            'module_override' => false,
            'priority' => fake()->randomElement(['critical', 'high', 'medium', 'low']),
            'is_essential' => fake()->boolean(30),
            'status' => 'active',
            'monthly_contribution' => fake()->randomFloat(2, 50, 2000),
            'contribution_frequency' => 'monthly',
            'contribution_streak' => fake()->numberBetween(0, 24),
            'longest_streak' => fake()->numberBetween(0, 36),
            'last_contribution_date' => fake()->optional(0.7)->dateTimeBetween('-2 months', 'now'),
            'linked_account_ids' => null,
            'linked_savings_account_id' => null,
            'risk_preference' => null,
            'use_global_risk_profile' => true,
            'ownership_type' => 'individual',
            'joint_owner_id' => null,
            'ownership_percentage' => 100.00,
            'show_in_projection' => true,
            'show_in_household_view' => true,
            'property_location' => null,
            'property_type' => null,
            'is_first_time_buyer' => null,
            'estimated_property_price' => null,
            'deposit_percentage' => null,
            'stamp_duty_estimate' => null,
            'additional_costs_estimate' => null,
            'milestones' => null,
            'projection_data' => null,
            'completed_at' => null,
            'completion_notes' => null,
        ];
    }

    /**
     * Generate a realistic goal name based on type.
     */
    private function generateGoalName(string $goalType): string
    {
        return match ($goalType) {
            'emergency_fund' => 'Emergency Fund',
            'property_purchase' => 'Property Purchase Fund',
            'home_deposit' => 'Home Deposit',
            'education' => fake()->randomElement(['University Fund', 'School Fees', 'Professional Qualification']),
            'retirement' => 'Retirement Savings',
            'wealth_accumulation' => 'Wealth Building',
            'wedding' => 'Wedding Fund',
            'holiday' => fake()->randomElement(['Holiday Fund', 'Travel Savings']),
            'car_purchase' => 'New Car Fund',
            'debt_repayment' => 'Debt Clearance',
            'custom' => fake()->words(3, true),
            default => 'Savings Goal',
        };
    }

    /**
     * An emergency fund goal.
     */
    public function emergencyFund(): static
    {
        return $this->state(fn (array $attributes) => [
            'goal_name' => 'Emergency Fund',
            'goal_type' => 'emergency_fund',
            'assigned_module' => 'savings',
            'priority' => 'critical',
            'is_essential' => true,
            'target_amount' => fake()->randomFloat(2, 5000, 30000),
        ]);
    }

    /**
     * A property purchase goal with property-specific fields populated.
     */
    public function propertyPurchase(): static
    {
        $propertyPrice = fake()->randomFloat(2, 150000, 500000);
        $depositPercent = fake()->randomElement([5.00, 10.00, 15.00, 20.00]);
        $targetAmount = $propertyPrice * ($depositPercent / 100);

        return $this->state(fn (array $attributes) => [
            'goal_name' => 'Home Deposit',
            'goal_type' => 'home_deposit',
            'assigned_module' => 'property',
            'target_amount' => $targetAmount,
            'property_location' => fake()->city(),
            'property_type' => fake()->randomElement(['house', 'flat', 'terraced', 'semi_detached']),
            'is_first_time_buyer' => fake()->boolean(70),
            'estimated_property_price' => $propertyPrice,
            'deposit_percentage' => $depositPercent,
            'stamp_duty_estimate' => $propertyPrice * 0.02,
            'additional_costs_estimate' => fake()->randomFloat(2, 2000, 8000),
        ]);
    }

    /**
     * A completed goal.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $targetAmount = $attributes['target_amount'] ?? 10000;

            return [
                'status' => 'completed',
                'current_amount' => $targetAmount,
                'completed_at' => fake()->dateTimeBetween('-3 months', 'now'),
                'completion_notes' => fake()->optional(0.5)->sentence(),
            ];
        });
    }

    /**
     * A paused goal.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paused',
        ]);
    }

    /**
     * A jointly owned goal.
     */
    public function joint(): static
    {
        return $this->state(fn (array $attributes) => [
            'ownership_type' => 'joint',
            'joint_owner_id' => User::factory(),
            'ownership_percentage' => 50.00,
        ]);
    }

    /**
     * A high-priority goal.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
            'is_essential' => true,
        ]);
    }
}
