<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * C2 RED-FIRST — handleGroupedExtractTurn must honour advance_on_answered_question.
 *
 * campaign2_state_pension is grouped_extract with advance_on_answered_question: true.
 * When the user says "not sure", the LLM returns no capture (capture_state_pension
 * is never called). Before the fix the director called emitRetry forever; after the
 * fix it advances to the next state (campaign_verify_announce, via enterCampaignVerify).
 *
 * campaign2_flexible_access is converted from grouped_extract to delegated with
 * advance_on_answered_question: true, so "No" advances without a tool call.
 */
afterEach(function () {
    Mockery::close();
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function c2User(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_selection' => 'pensioncheck',
        'marital_status' => 'single',
        'employment_status' => 'full_time',
        'date_of_birth' => '1980-06-15',
    ], $attrs));
}

// ── campaign2_state_pension "not sure" advances ───────────────────────────────

it('C2: grouped_extract advance_on_answered_question — "not sure" on state_pension advances rather than retrying', function (): void {
    $user = c2User([
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN2_STATE_PENSION,
    ]);

    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'director',
        'title' => 'Onboarding',
    ]);

    // The delegated LLM returns content only — no capture_state_pension call.
    // This is what happens when the user says "not sure" and the model cannot
    // extract a value.
    $fakeEvents = [
        ['type' => 'content', 'text' => "No problem — I'll note the gap."],
        ['type' => 'done', 'message_id' => 1],
    ];

    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('chatWithPromptOverride')
        ->once()
        ->andReturnUsing(function () use ($fakeEvents) {
            foreach ($fakeEvents as $event) {
                yield $event;
            }
        });
    $this->instance(CoordinatingAgent::class, $mock);

    $director = app(OnboardingChatDirector::class);

    $received = [];
    foreach ($director->handleUserMessage($user, $conversation, 'not sure') as $event) {
        $received[] = $event;
    }

    // Must advance — an onboarding_advance event must be in the output
    $advanceEvents = array_values(array_filter(
        $received,
        fn ($e) => ($e['type'] ?? null) === 'onboarding_advance'
    ));
    expect($advanceEvents)->not->toBeEmpty('expected onboarding_advance but got retry loop');

    // State must have moved off campaign2_state_pension
    $user->refresh();
    expect($user->onboarding_fyn_step)->not->toBe(
        OnboardingStateMachine::STATE_CAMPAIGN2_STATE_PENSION,
        'user is still on state_pension after a "not sure" answer — advance_on_answered_question not honoured'
    );
});

// ── campaign2_flexible_access turn_type is now delegated (C2b) ───────────────

it('C2: campaign2_flexible_access has turn_type delegated after conversion', function (): void {
    $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_CAMPAIGN2_FLEXIBLE_ACCESS);
    expect($state['turn_type'] ?? null)->toBe('delegated',
        'campaign2_flexible_access must be delegated so advance_on_answered_question fires; was grouped_extract');
});

it('C2: campaign2_flexible_access has advance_on_answered_question set', function (): void {
    $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_CAMPAIGN2_FLEXIBLE_ACCESS);
    expect($state['advance_on_answered_question'] ?? false)->toBeTrue();
});

it('C2: campaign2_flexible_access has no extraction_tool after conversion to delegated', function (): void {
    $state = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_CAMPAIGN2_FLEXIBLE_ACCESS);
    expect(array_key_exists('extraction_tool', $state))->toBeFalse(
        'delegated states do not use extraction_tool; it belongs to grouped_extract'
    );
});
