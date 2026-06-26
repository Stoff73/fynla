<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;

it('maps every navigable campaign section to a route and capture-entry state', function (): void {
    $config = OnboardingStateMachine::campaignVerifyConfig();

    // Every section navigates to its screen for the confirm.
    expect($config['income']['route'])->toBe('/income')
        ->and($config['income']['entry'])->toBe(OnboardingStateMachine::STATE_BASE_EMPLOYMENT)
        ->and($config['savings']['route'])->toBe('/savings')
        ->and($config['investments']['route'])->toBe('/investment')
        ->and($config['pensions']['route'])->toBe('/retirement')
        ->and($config['spouse']['route'])->toBe('/income')
        ->and($config['expenditure']['route'])->toBe('/expenditure');
});

it('stamps the verify section into context and goes straight to navigate/confirm', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_context' => [],
    ]);

    // No redundant "anything else?" gate — straight to the navigate/confirm.
    $next = OnboardingStateMachine::enterCampaignVerify($user, 'savings');

    expect($next)->toBe('campaign_verify_navigate')
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

it('routes verify_navigate no to edit and yes to the section advice', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_context' => ['verify_section' => 'pensions'],
        'marital_status' => 'single',
    ]);

    expect(OnboardingStateMachine::nextFromVerifyNavigate('no', $user))
        ->toBe('campaign_verify_edit');
    // Confirmed → the section's advice turn fires (before the next section).
    expect(OnboardingStateMachine::nextFromVerifyNavigate('yes', $user))
        ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_ADVICE_PENSIONS);
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

it('routes each section CAPTURE-end straight into navigate/confirm (no extra gate)', function (): void {
    $m = new ReflectionMethod(OnboardingStateMachine::class, 'inCodeStates');
    $m->setAccessible(true);
    $states = $m->invoke(null);
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_context' => [],
        'marital_status' => 'married',
    ]);

    // Capture-ends enter the navigate/confirm directly — never the (now removed)
    // redundant "anything else?" verify gate.
    foreach ([
        OnboardingStateMachine::STATE_CAMPAIGN_BANK_ACCOUNTS,
        OnboardingStateMachine::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS,
        OnboardingStateMachine::STATE_CAMPAIGN_PENSION_HISTORY,
        OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD,
    ] as $stateId) {
        $next = $states[$stateId]['next'];
        $resolved = is_callable($next) ? $next('', $user) : $next;
        expect($resolved)->toBe('campaign_verify_navigate', "state {$stateId} should enter navigate/confirm");
    }

    // Advice now fires AFTER the confirm and continues to the next section —
    // never back into the verify flow.
    foreach ([
        OnboardingStateMachine::STATE_CAMPAIGN_ADVICE_INCOME,
        OnboardingStateMachine::STATE_CAMPAIGN_ADVICE_SAVINGS,
    ] as $stateId) {
        $next = $states[$stateId]['next'];
        $resolved = is_callable($next) ? $next('', $user) : $next;
        expect($resolved)->not->toBe('campaign_verify_navigate')
            ->and($resolved)->not->toBe('campaign_verify_more');
    }
});

it('emits a navigation event for a navigable verify section and none for giving', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $director = app(OnboardingChatDirector::class);

    $emit = new ReflectionMethod($director, 'emitTurnForState');
    $emit->setAccessible(true);

    foreach ([['savings', true], ['giving', false]] as [$section, $expectNav]) {
        $user = User::factory()->create([
            'onboarding_fyn_path' => 'campaign', 'onboarding_completed' => false,
            'onboarding_fyn_step' => 'campaign_verify_navigate',
            'onboarding_fyn_context' => ['verify_section' => $section],
        ]);
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

        $state = OnboardingStateMachine::getState('campaign_verify_navigate');
        $events = iterator_to_array($emit->invoke($director, $user, $conversation, 'campaign_verify_navigate', $state), false);
        $types = array_column($events, 'type');

        expect(in_array('navigation', $types, true))->toBe($expectNav, "section {$section}");
        expect($types)->toContain('quick_replies');
    }
});
