<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\LifeInsurancePolicy;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\OnboardingStateMachine;

/**
 * CSJ 2026-07-24 — the verify loop (announce → Okay → navigate →
 * Continue/Edit → edit until happy) applies to EVERY data entry, not just
 * campaign sections. The journey/focus path enters the SAME three verify
 * states; only the section stamping (from the current focus) and the
 * post-confirm continuation are journey-aware. One mechanism, no copies.
 */
it('maps the journey-only sections to routes with an asset-capture entry', function (): void {
    $config = OnboardingStateMachine::campaignVerifyConfig();

    expect($config['protection']['route'])->toBe('/protection')
        ->and($config['protection']['entry'])->toBe(OnboardingStateMachine::STATE_ASSET_CAPTURE)
        ->and($config['estate']['route'])->toBe('/estate')
        ->and($config['estate']['entry'])->toBe(OnboardingStateMachine::STATE_ASSET_CAPTURE)
        ->and($config['goals']['route'])->toBe('/goals')
        ->and($config['goals']['entry'])->toBe(OnboardingStateMachine::STATE_ASSET_CAPTURE);
});

it('verifies a journey module capture that landed data', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'journey',
        'onboarding_fyn_selection' => 'savings',
        'onboarding_fyn_context' => [],
    ]);
    SavingsAccount::factory()->for($user)->create(['is_isa' => false, 'current_balance' => 5000]);

    $next = OnboardingStateMachine::nextFromAssetCapture('done', $user);

    expect($next)->toBe('campaign_verify_announce')
        ->and($user->fresh()->onboarding_fyn_context['verify_section'])->toBe('savings')
        ->and($user->fresh()->onboarding_fyn_context['verify_origin'])->toBe('journey_module');
});

it('skips the verify when the module capture landed nothing', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'journey',
        'onboarding_fyn_selection' => 'protection',
        'onboarding_fyn_context' => [],
    ]);

    expect(OnboardingStateMachine::nextFromAssetCapture("I don't have any", $user))
        ->toBe(OnboardingStateMachine::STATE_ADD_MORE);
});

it('maps each journey focus to its verify section', function (): void {
    $cases = [
        ['retirement', 'pensions', fn (User $u) => DCPension::factory()->for($u)->create()],
        ['protection', 'protection', fn (User $u) => LifeInsurancePolicy::factory()->for($u)->create()],
    ];

    foreach ($cases as [$focus, $section, $seed]) {
        $user = User::factory()->create([
            'onboarding_fyn_path' => 'journey',
            'onboarding_fyn_selection' => $focus,
            'onboarding_fyn_context' => [],
        ]);
        $seed($user);

        expect(OnboardingStateMachine::nextFromAssetCapture('done', $user))
            ->toBe('campaign_verify_announce');
        expect($user->fresh()->onboarding_fyn_context['verify_section'])->toBe($section);
    }
});

it('returns a journey module confirm to the add-more picker, not campaign advice', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'journey',
        'onboarding_fyn_selection' => 'savings',
        'onboarding_fyn_context' => ['verify_section' => 'savings', 'verify_origin' => 'journey_module'],
    ]);

    expect(OnboardingStateMachine::nextFromVerifyNavigate('no', $user))
        ->toBe('campaign_verify_edit');
    expect(OnboardingStateMachine::nextFromVerifyNavigate('yes', $user))
        ->toBe(OnboardingStateMachine::STATE_ADD_MORE);
});

it('continues the base flow after journey income and expenditure confirms', function (): void {
    $income = User::factory()->create([
        'onboarding_fyn_path' => 'journey',
        'onboarding_fyn_context' => ['verify_section' => 'income', 'verify_origin' => 'journey_base'],
    ]);
    expect(OnboardingStateMachine::nextFromVerifyNavigate('yes', $income))
        ->toBe(OnboardingStateMachine::STATE_BASE_EXPENDITURE);

    $spend = User::factory()->create([
        'onboarding_fyn_path' => 'journey',
        'onboarding_fyn_context' => ['verify_section' => 'expenditure', 'verify_origin' => 'journey_base'],
    ]);
    expect(OnboardingStateMachine::nextFromVerifyNavigate('yes', $spend))
        ->toBe(OnboardingStateMachine::STATE_ASSET_CAPTURE);
});

it('verifies journey income when earnings were captured and skips when none were', function (): void {
    $earner = User::factory()->create([
        'onboarding_fyn_path' => 'journey',
        'annual_employment_income' => 125000,
        'onboarding_fyn_context' => [],
    ]);
    expect(OnboardingStateMachine::nextFromEmploymentMore('no', $earner))
        ->toBe('campaign_verify_announce');
    expect($earner->fresh()->onboarding_fyn_context['verify_section'])->toBe('income');

    $noIncome = User::factory()->create([
        'onboarding_fyn_path' => 'journey',
        'annual_employment_income' => 0,
        'annual_self_employment_income' => 0,
        'onboarding_fyn_context' => [],
    ]);
    expect(OnboardingStateMachine::nextFromEmploymentMore('no', $noIncome))
        ->toBe(OnboardingStateMachine::STATE_BASE_EXPENDITURE);
});

it('routes journey expenditure through the verify instead of the in-chat profile confirm', function (): void {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'journey',
        'monthly_expenditure' => 3400,
        'onboarding_fyn_context' => [],
    ]);

    $m = new ReflectionMethod(OnboardingStateMachine::class, 'inCodeStates');
    $m->setAccessible(true);
    $states = $m->invoke(null);
    $next = ($states[OnboardingStateMachine::STATE_BASE_EXPENDITURE]['next'])('3400', $user);

    expect($next)->toBe('campaign_verify_announce')
        ->and($user->fresh()->onboarding_fyn_context['verify_section'])->toBe('expenditure')
        ->and($user->fresh()->onboarding_fyn_context['verify_origin'])->toBe('journey_base');
});

it('resolves navigation routes for the journey-only sections', function (): void {
    foreach (['protection' => '/protection', 'estate' => '/estate', 'goals' => '/goals'] as $section => $route) {
        $user = User::factory()->create([
            'onboarding_fyn_path' => 'journey',
            'onboarding_fyn_context' => ['verify_section' => $section],
        ]);

        expect(OnboardingStateMachine::verifyNavigateRoute($user))->toBe($route);
    }
});

it('leaves the campaign confirm continuation on the section advice', function (): void {
    // Regression pin: journey wiring must not disturb the campaign
    // continuation (confirm → section advice → next section).
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_context' => ['verify_section' => 'pensions'],
        'marital_status' => 'single',
    ]);

    expect(OnboardingStateMachine::nextFromVerifyNavigate('yes', $user))
        ->toBe(OnboardingStateMachine::STATE_CAMPAIGN_ADVICE_PENSIONS);
});
