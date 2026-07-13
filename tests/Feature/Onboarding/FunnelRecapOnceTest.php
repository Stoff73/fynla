<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Onboarding\FunnelIncomeBand;
use App\Services\Onboarding\OnboardingStateMachine;
use App\Services\TaxConfigService;
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

it('recaps the income band using the active tax configuration', function () {
    $configuration = TaxConfiguration::where('is_active', true)->firstOrFail();
    $data = $configuration->config_data;
    $data['income_tax']['higher_rate_threshold'] = 60000;
    $data['income_tax']['personal_allowance_taper_threshold'] = 110000;
    $data['income_tax']['additional_rate_threshold'] = 140000;
    $configuration->update(['config_data' => $data]);
    app()->forgetInstance(TaxConfigService::class);
    $user = campaignWorkUser();
    $user->update(['funnel_answers' => [...$user->funnel_answers, 'income' => 'upto_50270']]);

    expect(OnboardingStateMachine::buildWorkPrompt('', $user))
        ->toContain('Earning up to £60,000')
        ->not->toContain('Earning up to £50,270');
});

it('keeps the recap wording from the tax year in which the funnel was completed', function () {
    $context = FunnelIncomeBand::context('upto_50270');
    $configuration = TaxConfiguration::where('is_active', true)->firstOrFail();
    $data = $configuration->config_data;
    $data['income_tax']['higher_rate_threshold'] = 60000;
    $configuration->update(['config_data' => $data]);
    app()->forgetInstance(TaxConfigService::class);

    $user = campaignWorkUser();
    $user->update(['funnel_answers' => [
        ...$user->funnel_answers,
        'income' => 'upto_50270',
        'income_context' => $context,
    ]]);

    expect(OnboardingStateMachine::buildWorkPrompt('', $user))
        ->toContain('Earning up to £50,270')
        ->not->toContain('Earning up to £60,000');
});
