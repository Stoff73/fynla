<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\WhatIfScenario;
use Illuminate\Database\Eloquent\Factories\Factory;

class WhatIfScenarioFactory extends Factory
{
    protected $model = WhatIfScenario::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->sentence(3),
            'scenario_type' => fake()->randomElement(['retirement', 'property', 'family', 'income', 'custom']),
            'parameters' => ['retirement_age' => 55],
            'affected_modules' => ['retirement', 'investment'],
            'created_via' => 'manual',
            'ai_narrative' => null,
        ];
    }

    public function viaAiChat(): static
    {
        return $this->state(fn () => [
            'created_via' => 'ai_chat',
            'ai_narrative' => fake()->paragraph(),
        ]);
    }

    public function retirement(): static
    {
        return $this->state(fn () => [
            'name' => 'Retire at 55',
            'scenario_type' => 'retirement',
            'parameters' => ['retirement_age' => 55],
            'affected_modules' => ['retirement', 'investment', 'estate'],
        ]);
    }

    public function property(): static
    {
        return $this->state(fn () => [
            'name' => 'Sell Main Residence',
            'scenario_type' => 'property',
            'parameters' => ['sell_property' => true],
            'affected_modules' => ['property', 'estate', 'savings'],
        ]);
    }
}
