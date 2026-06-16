<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Onboarding\OnboardingStateMachine;

it('maps every navigable campaign section to a route and capture-entry state', function (): void {
    $config = OnboardingStateMachine::campaignVerifyConfig();

    // Charity verifies inline (no route); the rest navigate.
    expect($config['income']['route'])->toBe('/income')
        ->and($config['income']['entry'])->toBe(OnboardingStateMachine::STATE_BASE_EMPLOYMENT)
        ->and($config['savings']['route'])->toBe('/savings')
        ->and($config['investments']['route'])->toBe('/investment')
        ->and($config['pensions']['route'])->toBe('/retirement')
        ->and($config['spouse']['route'])->toBe('/income')
        ->and($config['expenditure']['route'])->toBe('/expenditure')
        ->and($config['giving']['route'])->toBeNull();
});

it('stamps the verify section into context and enters campaign_verify_more', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_context' => [],
    ]);

    $next = OnboardingStateMachine::enterCampaignVerify($user, 'savings');

    expect($next)->toBe('campaign_verify_more')
        ->and($user->fresh()->onboarding_fyn_context['verify_section'])->toBe('savings');
});

it('routes verify_more yes back to the section entry and no to navigate', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_context' => ['verify_section' => 'savings'],
    ]);

    expect(OnboardingStateMachine::nextFromVerifyMore('yes', $user))
        ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_ISA_HOLDINGS)
        ->and(OnboardingStateMachine::nextFromVerifyMore('no', $user))
        ->toBe('campaign_verify_navigate');
});

it('routes verify_navigate no to edit and yes to the next section', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_context' => ['verify_section' => 'giving'],
        'marital_status' => 'single',
    ]);

    expect(OnboardingStateMachine::nextFromVerifyNavigate('no', $user))
        ->toBe('campaign_verify_edit');
    // 'giving' → next section is 'spouse' (skipped: single) → 'expenditure' entry.
    expect(OnboardingStateMachine::nextFromVerifyNavigate('yes', $user))
        ->toBe(OnboardingStateMachine::STATE_BASE_EXPENDITURE);
});

it('defines the three generic verify states with the right turn types', function (): void {
    $m = new ReflectionMethod(OnboardingStateMachine::class, 'inCodeStates');
    $m->setAccessible(true);
    $states = $m->invoke(null);

    expect($states)->toHaveKeys(['campaign_verify_more', 'campaign_verify_navigate', 'campaign_verify_edit'])
        ->and($states['campaign_verify_more']['turn_type'])->toBe('bubbles')
        ->and($states['campaign_verify_navigate']['turn_type'])->toBe('bubbles')
        ->and($states['campaign_verify_edit']['turn_type'])->toBe('delegated')
        ->and($states['campaign_verify_navigate']['navigate_to'])->toBeInstanceOf(Closure::class);
});
