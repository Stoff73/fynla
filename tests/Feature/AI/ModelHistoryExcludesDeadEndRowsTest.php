<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\Fyn\FynSystemPrompt;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Live prod conversation 843 (2026-09-11): after a refusal and two "Sorry, I
 * didn't catch that" retries, grok refused even a full entity sentence — our
 * own dead-end rows had become the pattern it continued. The model-facing
 * history is built in ONE place; it must never carry them.
 */
it('drops retry rows and the canned refusal from the model-facing history but keeps everything else', function (): void {
    $user = User::factory()->create();
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test'])->fresh();
    $conversation->messages()->create(['role' => 'user', 'content' => 'Lloyds current account, 250 balance, 0.5% interest']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => 'Is this in your name only, or do you share it with someone else?', 'metadata' => ['turn_intent' => 'capture_clarification']]);
    $conversation->messages()->create(['role' => 'user', 'content' => 'My name']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => FynSystemPrompt::CANNED_REFUSAL.'. How can I assist with your finances?', 'metadata' => ['turn_intent' => 'capture_ack']]);
    $conversation->messages()->create(['role' => 'assistant', 'content' => "Sorry, I didn't catch that. Could you try again?", 'metadata' => ['is_retry' => true, 'turn_intent' => 'capture_clarification']]);
    $conversation->messages()->create(['role' => 'user', 'content' => 'Individual']);

    $method = new ReflectionMethod(CoordinatingAgent::class, 'buildMessageHistory');
    $history = $method->invoke(app(CoordinatingAgent::class), $conversation->fresh());

    expect(array_column($history, 'content'))->toBe([
        'Lloyds current account, 250 balance, 0.5% interest',
        'Is this in your name only, or do you share it with someone else?',
        'My name',
        'Individual',
    ]);
});
