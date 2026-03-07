<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TaxActionDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxActionDefinitionFactory extends Factory
{
    protected $model = TaxActionDefinition::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'source' => 'agent',
            'title_template' => fake()->sentence(4),
            'description_template' => fake()->sentence(10),
            'action_template' => fake()->sentence(6),
            'category' => fake()->randomElement(['ISA Allowance', 'Pension Allowance', 'Capital Gains', 'Spousal Optimisation', 'Dividend Tax']),
            'priority' => fake()->randomElement(['critical', 'high', 'medium', 'low']),
            'scope' => 'portfolio',
            'what_if_impact_type' => fake()->randomElement(['tax_optimisation', 'contribution', 'default']),
            'trigger_config' => ['condition' => 'test_condition'],
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
}
