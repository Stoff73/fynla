<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The SaveTax funnel recap ("Hi X — thanks for those answers…") is delivered
 * exactly once per conversation. The welcome-back resume ('continue') re-emits
 * the base_work turn; before the delivered-check the full recap + question
 * repeated as duplicate transcript rows (observed live 2026-07-03).
 */
function campaignWorkUser(): User
{
    return User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_WORK,
        'employment_status' => 'employed',
        'annual_employment_income' => null,
        'funnel_answers' => [
            'employment' => 'full-time',
            'income' => '50271_100000',
            'spouse' => 'no',
            'assets' => ['bank', 'isa'],
        ],
    ]);
}

it('greets with the funnel recap on the first work turn', function () {
    $user = campaignWorkUser();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $prompt = OnboardingStateMachine::buildWorkPrompt('', $user, $conversation);

    expect($prompt)->toContain('thanks for those answers');
});

it('asks only the income question when the recap was already delivered', function () {
    $user = campaignWorkUser();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    // The first emission persisted its turn with the state stamped in metadata
    // (exactly what emitTurnForState's saveMessage does).
    $conversation->messages()->create([
        'role' => 'assistant',
        'content' => 'recap already delivered',
        'metadata' => ['onboarding_step' => OnboardingStateMachine::STATE_BASE_WORK],
    ]);

    $prompt = OnboardingStateMachine::buildWorkPrompt('', $user, $conversation);

    expect($prompt)->not->toContain('thanks for those answers')
        ->and($prompt)->toContain('gross annual income');
});

it('still greets with the recap when no conversation is supplied', function () {
    $user = campaignWorkUser();

    expect(OnboardingStateMachine::buildWorkPrompt('', $user))
        ->toContain('thanks for those answers');
});
