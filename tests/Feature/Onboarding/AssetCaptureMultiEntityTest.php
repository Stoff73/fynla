<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers PRD FR-M11 — multi-entity asset_capture.
 *
 * The asset_capture turn delegates to CoordinatingAgent::chatWithPromptOverride
 * so the LLM can call create_* tools multiple times in a single user turn
 * (e.g. "I have two ISAs at Vanguard and one at Fidelity" → three tool calls
 * yielding three tool_success events).
 *
 * This test mocks the delegated generator to yield multiple tool_success
 * events and asserts the director (a) forwards each of them downstream
 * verbatim, (b) advances state from asset_capture → add_more after the
 * delegation completes, and (c) emits the add_more turn's quick_replies.
 *
 * PRD: April/April20Updates/PRD-fyn-driven-onboarding.md §FR-M11
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

afterEach(function () {
    Mockery::close();
});

it('forwards multiple tool_success events from a single user turn and advances to add_more', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Test',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_ASSET_CAPTURE,
        'onboarding_fyn_selection' => 'savings',
        'onboarding_fyn_context' => ['visited_focuses' => ['savings']],
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    // Fake generator mimicking a multi-entity LLM turn — three savings
    // accounts created in a single delegated chat.
    $fakeDelegatedEvents = [
        ['type' => 'tool_use', 'tool' => 'create_savings_account'],
        ['type' => 'tool_success', 'tool' => 'create_savings_account', 'summary' => 'Vanguard ISA added'],
        ['type' => 'tool_use', 'tool' => 'create_savings_account'],
        ['type' => 'tool_success', 'tool' => 'create_savings_account', 'summary' => 'Vanguard GIA added'],
        ['type' => 'tool_use', 'tool' => 'create_savings_account'],
        ['type' => 'tool_success', 'tool' => 'create_savings_account', 'summary' => 'Fidelity ISA added'],
        ['type' => 'content', 'text' => 'All three accounts saved.'],
        ['type' => 'done', 'message_id' => 99],
    ];

    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () use ($fakeDelegatedEvents) {
            foreach ($fakeDelegatedEvents as $event) {
                yield $event;
            }
        });
    // Unified prompt mode arms + resets the onboarding focus on the agent
    // (OnboardingChatDirector::handleAssetCaptureTurn) — allow it.
    $mock->shouldReceive('setUnifiedOnboardingFocus')->zeroOrMoreTimes();

    $this->instance(CoordinatingAgent::class, $mock);

    $director = app(OnboardingChatDirector::class);

    $received = [];
    foreach ($director->handleUserMessage(
        $user,
        $conversation,
        'I have two ISAs at Vanguard and one at Fidelity'
    ) as $event) {
        $received[] = $event;
    }

    // All three tool_success events must be forwarded to the frontend —
    // the director's asset_capture turn MUST NOT collapse, swallow, or
    // deduplicate them. Multi-entity visibility depends on this.
    $toolSuccessEvents = array_values(array_filter(
        $received,
        fn (array $e) => ($e['type'] ?? null) === 'tool_success'
    ));
    expect($toolSuccessEvents)->toHaveCount(3)
        ->and(array_column($toolSuccessEvents, 'summary'))
        ->toBe(['Vanguard ISA added', 'Vanguard GIA added', 'Fidelity ISA added']);

    // State advances to add_more after delegation.
    $user->refresh();
    expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_ADD_MORE);

    // onboarding_advance + add_more quick_replies are emitted after the
    // delegated generator completes.
    $advanceEvent = collect($received)->firstWhere('type', 'onboarding_advance');
    expect($advanceEvent)->not->toBeNull()
        ->and($advanceEvent['from_step'])->toBe(OnboardingStateMachine::STATE_ASSET_CAPTURE)
        ->and($advanceEvent['to_step'])->toBe(OnboardingStateMachine::STATE_ADD_MORE);

    $quickReplies = collect($received)->firstWhere('type', 'quick_replies');
    expect($quickReplies)->not->toBeNull();

    // Already-visited 'savings' must be stripped from the add_more bubbles;
    // the "I'm done" bubble must be appended last (director invariant).
    $bubbleIds = array_column($quickReplies['bubbles'] ?? [], 'id');
    expect($bubbleIds)->not->toContain('savings')
        ->and(end($bubbleIds))->toBe('done');
});
