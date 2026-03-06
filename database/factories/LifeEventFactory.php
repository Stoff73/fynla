<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LifeEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LifeEvent>
 */
class LifeEventFactory extends Factory
{
    protected $model = LifeEvent::class;

    public function definition(): array
    {
        $isIncome = fake()->boolean(50);

        $eventType = $isIncome
            ? fake()->randomElement([
                'inheritance',
                'gift_received',
                'bonus',
                'redundancy_payment',
                'property_sale',
                'business_sale',
                'pension_lump_sum',
                'lottery_windfall',
                'custom_income',
            ])
            : fake()->randomElement([
                'large_purchase',
                'home_improvement',
                'wedding',
                'education_fees',
                'gift_given',
                'medical_expense',
                'custom_expense',
            ]);

        return [
            'user_id' => User::factory(),
            'event_name' => $this->generateEventName($eventType),
            'event_type' => $eventType,
            'description' => fake()->optional(0.5)->sentence(),
            'amount' => fake()->randomFloat(2, 1000, 200000),
            'impact_type' => $isIncome ? 'income' : 'expense',
            'expected_date' => fake()->dateTimeBetween('+1 month', '+10 years'),
            'certainty' => fake()->randomElement(['confirmed', 'likely', 'possible', 'speculative']),
            'icon' => null,
            'show_in_projection' => true,
            'show_in_household_view' => true,
            'ownership_type' => 'individual',
            'joint_owner_id' => null,
            'ownership_percentage' => 100.00,
            'status' => fake()->randomElement(['expected', 'confirmed']),
            'occurred_at' => null,
        ];
    }

    /**
     * Generate a realistic event name based on type.
     */
    private function generateEventName(string $eventType): string
    {
        return match ($eventType) {
            'inheritance' => 'Expected Inheritance',
            'gift_received' => 'Gift from Family',
            'bonus' => 'Annual Bonus',
            'redundancy_payment' => 'Redundancy Payment',
            'property_sale' => 'Property Sale',
            'business_sale' => 'Business Sale',
            'pension_lump_sum' => 'Pension Lump Sum',
            'lottery_windfall' => 'Windfall',
            'large_purchase' => fake()->randomElement(['New Car', 'Furniture', 'Home Appliances']),
            'home_improvement' => fake()->randomElement(['Kitchen Renovation', 'Extension', 'New Roof', 'Bathroom Renovation']),
            'wedding' => 'Wedding',
            'education_fees' => fake()->randomElement(['University Fees', 'School Fees', 'Professional Course']),
            'gift_given' => 'Gift to Family',
            'medical_expense' => 'Medical Treatment',
            'custom_income' => 'Additional Income',
            'custom_expense' => 'Planned Expense',
            default => fake()->words(3, true),
        };
    }

    /**
     * An income event.
     */
    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => fake()->randomElement([
                'inheritance', 'gift_received', 'bonus', 'property_sale', 'custom_income',
            ]),
            'impact_type' => 'income',
        ]);
    }

    /**
     * An expense event.
     */
    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => fake()->randomElement([
                'large_purchase', 'home_improvement', 'wedding', 'education_fees', 'custom_expense',
            ]),
            'impact_type' => 'expense',
        ]);
    }

    /**
     * An inheritance event.
     */
    public function inheritance(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_name' => 'Expected Inheritance',
            'event_type' => 'inheritance',
            'impact_type' => 'income',
            'amount' => fake()->randomFloat(2, 50000, 500000),
            'certainty' => fake()->randomElement(['likely', 'possible', 'speculative']),
        ]);
    }

    /**
     * A confirmed event.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'certainty' => 'confirmed',
        ]);
    }

    /**
     * A completed event.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'occurred_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ]);
    }

    /**
     * A jointly owned event.
     */
    public function joint(): static
    {
        return $this->state(fn (array $attributes) => [
            'ownership_type' => 'joint',
            'joint_owner_id' => User::factory(),
            'ownership_percentage' => 50.00,
        ]);
    }
}
