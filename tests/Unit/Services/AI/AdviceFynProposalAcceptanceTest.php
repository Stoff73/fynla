<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\AdviceFyn;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function proposalIntent(AiConversation $conversation, string $message): ?array
{
    $advice = app(AdviceFyn::class);
    $m = new ReflectionMethod($advice, 'proposalAcceptanceIntent');
    $m->setAccessible(true);

    return $m->invoke($advice, $conversation, $message);
}

function proposalConversation(string $lastAssistant, string $persona = 'advice'): AiConversation
{
    $user = User::factory()->create();
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test', 'title' => 'Test']);
    $conversation->messages()->create(['role' => 'user', 'content' => 'Should I create a goal for my emergency fund?']);
    $conversation->messages()->create(['role' => 'assistant', 'content' => $lastAssistant, 'persona' => $persona]);

    return $conversation;
}

/**
 * Live 2026-09-08: Fyn offered "Would you like me to help you set up that
 * emergency fund goal now?", the user said "Yes please, set it up.", and
 * grok-4.3 wrote delegate_to_capture out as prose. The acceptance now routes
 * deterministically off the offer the advice turn made.
 */
it('routes an acceptance of an advice-turn offer to a goal capture', function () {
    $conversation = proposalConversation('A goal of £12,000 would give you the buffer. Would you like me to help you set up that emergency fund goal now?');

    $intent = proposalIntent($conversation, 'Yes please, set it up.');

    expect($intent)->not->toBeNull()
        ->and($intent['entity_type'])->toBe('goal')
        ->and($intent['matched_verb'])->toBe('proposal_accepted');
});

it('leaves a capture turn\'s question to the continuation rule', function () {
    $conversation = proposalConversation('Would you like me to set up the goal with a December target?', 'data_capture');

    expect(proposalIntent($conversation, 'Yes'))->toBeNull();
});
