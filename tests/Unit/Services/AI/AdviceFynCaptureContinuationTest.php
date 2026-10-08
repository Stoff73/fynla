<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\AdviceFyn;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * WP-1 — capture-continuation. A capture turn that asks for details
 * ("Happy to — what's the scheme, provider, and current value?") persists a
 * data_capture assistant row with no tool calls; the user's next non-question
 * message is the answer and must route back to capture, not to read-only
 * advice (where the 2026-07-03 walk's pension details dead-ended).
 */
function continuationIntent(AiConversation $conversation, string $message): ?array
{
    $advice = app(AdviceFyn::class);
    $m = new ReflectionMethod($advice, 'captureContinuationIntent');
    $m->setAccessible(true);

    return $m->invoke($advice, $conversation, $message);
}

function conversationWith(array $rows): AiConversation
{
    $user = User::factory()->create();
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Test',
    ]);
    foreach ($rows as $row) {
        $conversation->messages()->create($row);
    }

    return $conversation;
}

it('reuses the opening write intent for the answer to a pending capture question', function () {
    $conversation = conversationWith([
        ['role' => 'user', 'content' => 'Help me add my pension details'],
        ['role' => 'assistant', 'content' => "Happy to — what's the scheme name, provider, and current value?", 'persona' => 'data_capture'],
    ]);

    $intent = continuationIntent($conversation, "It's a workplace pension with Aviva, worth about £40,000");

    expect($intent)->not->toBeNull()
        ->and($intent['entity_type'])->toBe('pension')
        ->and($intent['reason'])->toContain('capture continuation');
});

it('routes a mid-capture question to advice, not capture', function () {
    $conversation = conversationWith([
        ['role' => 'user', 'content' => 'Help me add my pension details'],
        ['role' => 'assistant', 'content' => "Happy to — what's the scheme name, provider, and current value?", 'persona' => 'data_capture'],
    ]);

    expect(continuationIntent($conversation, 'What is a SIPP?'))->toBeNull();
});

it('does not continue after an advice turn', function () {
    $conversation = conversationWith([
        ['role' => 'user', 'content' => 'Help me add my pension details'],
        ['role' => 'assistant', 'content' => 'Pensions are a tax-efficient way to save.', 'persona' => 'advice'],
    ]);

    expect(continuationIntent($conversation, "It's a workplace pension with Aviva"))->toBeNull();
});

it('does not continue after a capture turn that already wrote', function () {
    $conversation = conversationWith([
        ['role' => 'user', 'content' => 'Add my Aviva workplace pension worth £40,000'],
        ['role' => 'assistant', 'content' => 'Recorded — Aviva workplace pension £40,000.', 'persona' => 'data_capture', 'tool_calls' => [['tool' => 'create_pension']]],
    ]);

    expect(continuationIntent($conversation, 'Thanks, looks right'))->toBeNull();
});

it('takes the answer to the duplicate guard\'s form question as about that record', function () {
    // csjones, Alex, 2026-10-08: a savings form saved outside the setup walk
    // matched his joint account; the guard asked "a separate savings account …,
    // or the same one?" and "It's the same one" reopened a blank form. The
    // question row (OnboardingChatDirector::emitFormProblem) is a capture still
    // asking about record 893, and the answer continues it, naming that record.
    $conversation = conversationWith([
        ['role' => 'user', 'content' => 'Add my Santander easy access savings account, £24,000 at 4%, joint with Jamie 50/50'],
        ['role' => 'assistant', 'content' => 'Fill this in and save, and I\'ll add it to your records.', 'metadata' => ['turn_intent' => 'verify_prompt']],
        ['role' => 'user', 'content' => 'Santander easy access savings, balance £24,000, 4% interest, joint.'],
        ['role' => 'assistant', 'persona' => 'data_capture',
            'content' => 'You already have a savings account recorded as "Santander easy access savings", with exactly these details. Is this a separate savings account you also hold, or the same one?',
            'metadata' => ['turn_intent' => 'capture_clarification', 'capture_write_failed' => true, 'capture_write_landed' => false, 'capture_record_id' => 893]],
    ]);

    $intent = continuationIntent($conversation, "It's the same one");

    expect($intent)->not->toBeNull()
        ->and($intent['entity_type'])->toBe('savings_account')
        ->and($intent['pending_record_id'])->toBe(893);
});
