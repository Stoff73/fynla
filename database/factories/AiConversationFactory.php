<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiConversation>
 */
class AiConversationFactory extends Factory
{
    protected $model = AiConversation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'status' => 'active',
            'model_used' => 'claude-opus-4-7',
            'total_input_tokens' => 0,
            'total_output_tokens' => 0,
            'message_count' => 0,
            'metadata' => [],
            'persona_state' => [
                'current' => 'advice',
                'pending_advice_question' => null,
                'capture_context' => null,
                'turns_in_capture' => 0,
            ],
        ];
    }

    public function capturing(): static
    {
        return $this->state(fn (array $attributes) => [
            'persona_state' => [
                'current' => 'capturing',
                'pending_advice_question' => 'What should I do about my pensions?',
                'capture_context' => [
                    'reason' => 'test capture context',
                    'entity_types' => ['dc_pension'],
                    'fields_needed' => [],
                    'pending_advice_question' => 'What should I do about my pensions?',
                    'originating_focus' => null,
                ],
                'turns_in_capture' => 1,
            ],
        ]);
    }
}
