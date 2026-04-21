<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMessage>
 */
class AiMessageFactory extends Factory
{
    protected $model = AiMessage::class;

    public function definition(): array
    {
        return [
            'conversation_id' => AiConversation::factory(),
            'role' => 'user',
            'content' => fake()->paragraph(),
            'persona' => null,
            'metadata' => [],
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'assistant',
            'content' => fake()->paragraph(),
        ]);
    }

    public function advicePersona(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'assistant',
            'persona' => 'advice',
        ]);
    }

    public function dataCapturePersona(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'assistant',
            'persona' => 'data_capture',
        ]);
    }
}
