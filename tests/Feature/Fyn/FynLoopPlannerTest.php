<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\TaxConfiguration;
use App\Models\TierConfiguration;
use App\Models\User;
use App\Services\AI\Loop\FynLoop;
use App\Services\AI\Loop\SessionMode;
use App\Services\TaxConfigService;
use Tests\Support\Fyn\FynStreamHarness;

/**
 * CoALA Phase 5 item 5 increment 2 — the planner driving FynLoop::run end-to-end
 * through the real chat loop (Option A: planner routes; reasoner keeps tool-use).
 *
 * The harness scripts the planner's forced `plan` tool call FIRST (one
 * createStream pop), then the reasoner's stream — proving the two-call shape on
 * the live dispatch path without a network call.
 */
beforeEach(function () {
    TierConfiguration::create(tierConfigFixture('free'));
    TaxConfiguration::factory()->create(['is_active' => true]);
    app()->forgetInstance(TaxConfigService::class);
});

function fynLoopUser(): array
{
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
        'title' => 'Planner loop',
        'message_count' => 0,
    ]);

    return [$user, $conversation];
}

it('plans reason then streams the reasoner answer through the loop', function () {
    [$user, $conversation] = fynLoopUser();

    FynStreamHarness::fake()
        ->toolTurn('plan', ['action_type' => 'reason', 'prompt_template_id' => 'advice_default'])
        ->textTurn('Your total net worth is £250,000.')
        ->bind();

    $events = iterator_to_array(
        app(FynLoop::class)->run(SessionMode::Advice, $user, $conversation, 'What is my net worth?', null, null),
        preserve_keys: false,
    );

    $content = collect($events)->where('type', 'content')->pluck('text')->implode('');

    expect($content)->toContain('Your total net worth is £250,000.')
        ->and(collect($events)->contains(fn ($e) => ($e['type'] ?? null) === 'done'))->toBeTrue();

    // The reasoner ran and persisted the assistant turn (proves reason routed to
    // the streamed reasoner, not the canonical defer path).
    $this->assertDatabaseHas('ai_messages', [
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
    ]);
});

it('records a reasoner cost-attribution row tagged to the action (FR-M11)', function () {
    [$user, $conversation] = fynLoopUser();

    FynStreamHarness::fake()
        ->toolTurn('plan', ['action_type' => 'reason', 'prompt_template_id' => 'advice_default'])
        ->textTurn('Your total net worth is £250,000.')
        ->bind();

    iterator_to_array(
        app(FynLoop::class)->run(SessionMode::Advice, $user, $conversation, 'net worth?', null, null),
        preserve_keys: false,
    );

    $this->assertDatabaseHas('ai_cost_attribution', [
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'stage' => 'reasoner',
        'action_type' => 'reason',
        'session_mode' => 'advice',
    ]);
});

it('emits the canonical defer response on a no_action plan', function () {
    [$user, $conversation] = fynLoopUser();

    FynStreamHarness::fake()
        ->toolTurn('plan', ['action_type' => 'no_action'])
        ->bind();

    $events = iterator_to_array(
        app(FynLoop::class)->run(SessionMode::Advice, $user, $conversation, 'hello', null, null),
        preserve_keys: false,
    );

    $content = collect($events)->where('type', 'content')->pluck('text')->implode('');

    expect($content)->toContain('I need a little more time')
        ->and(collect($events)->contains(fn ($e) => ($e['type'] ?? null) === 'done'))->toBeTrue();
});
