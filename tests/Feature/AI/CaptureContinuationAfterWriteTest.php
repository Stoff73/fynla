<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\AdviceFyn;

// TestCase and RefreshDatabase are applied to all of tests/Feature by Pest.php.

/**
 * BUG-02/03 (CSJ 2026-08-17) — a capture turn that WROTE and ASKED dead-ends.
 *
 * WP-1 added `captureContinuationIntent()` for precisely this failure: a terse
 * answer to a capture question ("Sip", "workplace", "45000") does not re-match the
 * verb+entity classifier, so without the rule it lands in read-only advice and the
 * capture dead-ends. Its own comment records the 2026-07-03 walk where "the pension
 * details were never persisted".
 *
 * But it was scoped to a capture turn "that wrote nothing":
 *
 *     || ! empty($lastAssistant->tool_calls)   →   return null
 *
 * Live reproduction 2026-08-17: turn 1 ("I have an aviva pension with a balance of
 * 45000") both WROTE the pension and ASKED for the scheme type, leaving
 * persona=data_capture WITH tool_calls. Turn 2 ("Sip") therefore fell through to
 * advice, which narrated "recorded as a Self-Invested Personal Pension" while making
 * no tool call at all — the row stayed pension_type=occupational.
 *
 * Same dead-end WP-1 was built to close, reached by a turn that wrote as well as
 * asked. A capture turn is pending while it is still asking, whatever it wrote.
 */
function continuationIntentFor(AiConversation $conversation, string $message): ?array
{
    $method = new ReflectionMethod(AdviceFyn::class, 'captureContinuationIntent');
    $method->setAccessible(true);

    return $method->invoke(app(AdviceFyn::class), $conversation, $message);
}

function seedCaptureTurn(AiConversation $conversation, string $assistantText, bool $wrote): void
{
    AiMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'I have an aviva pension with a balance of 45000',
    ]);
    AiMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => $assistantText,
        'persona' => 'data_capture',
        'tool_calls' => $wrote ? [['name' => 'create_pension', 'id' => 'call_1']] : null,
    ]);
}

it('treats a terse answer as a continuation when the capture asked but wrote nothing', function (): void {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    seedCaptureTurn($conversation, 'Is this a workplace pension or a Self-Invested Personal Pension?', wrote: false);

    expect(continuationIntentFor($conversation, 'Sip'))->not->toBeNull();
});

it('treats a terse answer as a continuation when the capture wrote AND is still asking', function (): void {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    // The live shape: it wrote the pension and asked for the missing scheme type.
    seedCaptureTurn(
        $conversation,
        'Is this a workplace pension or a Self-Invested Personal Pension? Recorded — Aviva pension of £45,000.',
        wrote: true,
    );

    $intent = continuationIntentFor($conversation, 'Sip');

    expect($intent)->not->toBeNull(
        'A capture turn that wrote AND asked is still pending — the answer must reach capture, not advice.'
    );
    expect($intent['entity_type'])->toBe('pension');
});

it('does not treat a fresh message as a continuation once the capture concluded', function (): void {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    // Wrote and asked nothing further — that capture is finished.
    seedCaptureTurn($conversation, 'Recorded — Aviva pension of £45,000.', wrote: true);

    expect(continuationIntentFor($conversation, 'Sip'))->toBeNull();
});

it('never hijacks a question the user asks', function (): void {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    seedCaptureTurn($conversation, 'Is this a workplace pension or a Self-Invested Personal Pension?', wrote: true);

    expect(continuationIntentFor($conversation, 'What is my retirement income?'))->toBeNull();
});
