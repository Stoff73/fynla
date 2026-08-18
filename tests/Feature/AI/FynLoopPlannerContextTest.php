<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\Loop\FynLoop;

// TestCase and RefreshDatabase are applied to all of tests/Feature by Pest.php.

/**
 * BUG-02 defect 4 (CSJ 2026-08-17) — Fyn discarded short answers to its own questions.
 *
 * Reproduced live against the real chat endpoint: the user said "I have an aviva
 * pension with a balance of 45000", Fyn asked whether it was a workplace pension or
 * a Self-Invested Personal Pension, the user answered "Sip" — and Fyn replied
 * "I need a little more time on this — let me come back to you." with NOTHING
 * persisted, not even the user's own message.
 *
 * Cause: FynLoop handed the planner `[['role' => 'user', 'content' => $message]]` —
 * the latest message and nothing else. A terse reply to Fyn's own question therefore
 * arrived context-free, the planner judged it unactionable and returned `no_action`,
 * and that handler emits the canned defer line and returns.
 *
 * Every clarification answer was at risk: "yes", "workplace", "45000", "the second
 * one". This is CSJ's "Fyn not storing the values given by the user into the app".
 *
 * `Planner` is final, so the planner input is asserted directly rather than spied on.
 */
function plannerMessagesFor(AiConversation $conversation, string $message): array
{
    $method = new ReflectionMethod(FynLoop::class, 'plannerMessages');
    $method->setAccessible(true);

    return $method->invoke(app(FynLoop::class), $conversation, $message);
}

it('gives the planner the conversation history, not just the latest message', function (): void {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    AiMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'I have an aviva pension with a balance of 45000',
    ]);
    AiMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Is this a workplace pension, or a Self-Invested Personal Pension?',
    ]);

    $messages = plannerMessagesFor($conversation, 'Sip');

    expect(count($messages))->toBeGreaterThan(1, 'The planner received only the latest message.');
    // The planner must be able to see the question it just asked.
    expect(array_column($messages, 'role'))->toContain('assistant');

    $last = end($messages);
    expect($last['role'])->toBe('user');
    expect($last['content'])->toBe('Sip', 'The turn being planned must be the final message.');
});

it('keeps the current message last even when it is already persisted', function (): void {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    AiMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Is this a workplace pension, or a Self-Invested Personal Pension?',
    ]);
    // run() may persist the user message before planning; it must not be duplicated.
    AiMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'Sip',
    ]);

    $messages = plannerMessagesFor($conversation, 'Sip');

    expect(array_column($messages, 'content'))->toBe([
        'Is this a workplace pension, or a Self-Invested Personal Pension?',
        'Sip',
    ]);
});

it('caps how much history the planner is charged for', function (): void {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    foreach (range(1, 20) as $i) {
        AiMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'role' => $i % 2 === 0 ? 'assistant' : 'user',
            'content' => 'turn '.$i.' '.str_repeat('x', 2000),
        ]);
    }

    $messages = plannerMessagesFor($conversation, 'Sip');

    expect(count($messages))->toBeLessThanOrEqual(7); // 6 history turns + the current one
    foreach ($messages as $entry) {
        expect(strlen($entry['content']))->toBeLessThanOrEqual(700);
    }
});

it('works on the first turn of a conversation with no history', function (): void {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $messages = plannerMessagesFor($conversation, 'I have an aviva pension with a balance of 45000');

    expect($messages)->toBe([
        ['role' => 'user', 'content' => 'I have an aviva pension with a balance of 45000'],
    ]);
});
