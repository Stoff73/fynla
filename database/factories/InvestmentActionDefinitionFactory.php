<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InvestmentActionDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvestmentActionDefinitionFactory extends Factory
{
    protected $model = InvestmentActionDefinition::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'source' => 'agent',
            'title_template' => fake()->sentence(4),
            'description_template' => fake()->sentence(10),
            'action_template' => fake()->sentence(6),
            'category' => fake()->randomElement(['Fees', 'Diversification', 'Tax Efficiency', 'Emergency Fund', 'Goal']),
            'priority' => fake()->randomElement(['critical', 'high', 'medium', 'low']),
            'scope' => fake()->randomElement(['account', 'portfolio']),
            'what_if_impact_type' => fake()->randomElement(['fee_reduction', 'savings_increase', 'contribution', 'tax_optimisation', 'default']),
            'trigger_config' => ['condition' => 'test_condition', 'threshold' => 5.0],
            'is_enabled' => true,
            'sort_order' => fake()->numberBetween(1, 200),
            'notes' => null,
        ];
    }

    /**
     * Mark the definition as disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }

    /**
     * Set source to goal.
     */
    public function goalSourced(): static
    {
        return $this->state(fn () => [
            'source' => 'goal',
            'category' => 'Goal',
        ]);
    }
}
