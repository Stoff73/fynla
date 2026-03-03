<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProtectionActionDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProtectionActionDefinitionFactory extends Factory
{
    protected $model = ProtectionActionDefinition::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'source' => 'agent',
            'title_template' => fake()->sentence(4),
            'description_template' => fake()->sentence(10),
            'action_template' => fake()->sentence(6),
            'category' => fake()->randomElement(['Life Insurance', 'Critical Illness', 'Income Protection', 'General']),
            'priority' => fake()->randomElement(['critical', 'high', 'medium', 'low']),
            'scope' => 'portfolio',
            'what_if_impact_type' => fake()->randomElement(['coverage_increase', 'gap_reduction', 'default']),
            'trigger_config' => ['condition' => 'test_condition', 'threshold' => 0],
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
     * Set source to gap.
     */
    public function gapSourced(): static
    {
        return $this->state(fn () => [
            'source' => 'gap',
            'category' => 'Coverage Gap',
        ]);
    }
}
