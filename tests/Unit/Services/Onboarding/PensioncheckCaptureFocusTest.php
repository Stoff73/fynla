<?php

declare(strict_types=1);

use App\Services\Onboarding\OnboardingPromptBuilder;

/**
 * D1 root cause — the pensioncheck campaign's delegated capture turns
 * (campaign_occupational_scheme, campaign2_pension_pots, campaign2_pension_db,
 * campaign2_flexible_access, campaign2_spouse_pensions, campaign_pension_contribs)
 * dispatch through handleAssetCaptureTurn with focus = onboarding_fyn_selection
 * = 'pensioncheck'. Before the fix, toolsForFocus('pensioncheck') fell to the
 * savings default (create_savings_account only), so the model had no pension
 * tool when the user described a workplace / Defined Benefit pension — it
 * security-refused ("I can only help with financial planning questions") or
 * narrated tool names instead of calling them. The catalogue must carry the
 * pension write tools, exactly as the savetax focus does for the same shared
 * delegated states.
 */
it('pensioncheck focus exposes the pension capture tools, not the savings default', function (): void {
    $tools = OnboardingPromptBuilder::toolsForFocus('pensioncheck');

    // The two writes the delegated pensioncheck states need directly.
    expect($tools)->toContain('create_pension')            // occupational / DB / SIPP / spouse
        ->and($tools)->toContain('capture_salary_sacrifice'); // occupational scheme

    // update_record (pot value + flexible-access flag) and update_profile are
    // appended to every focus.
    expect($tools)->toContain('update_record')
        ->and($tools)->toContain('update_profile');

    // Regression guard: must NOT be the savings-only default that caused the
    // live refusals.
    expect($tools)->not->toBe(['create_savings_account', 'update_profile', 'update_record']);
    expect($tools)->not->toContain('create_savings_account');
});

it('pensioncheck focus has a human label distinct from the raw selection key', function (): void {
    // focusLabel is private on both OnboardingPromptBuilder and FynContextAssembler;
    // assert the observable proxy — the assembled capture instruction block names
    // a proper label (not the literal 'pensioncheck' selection key). The label is
    // rendered by FynCaptureTurnInstructions::render($label, $toolList).
    $label = (new ReflectionMethod(OnboardingPromptBuilder::class, 'focusLabel'))
        ->invoke(app(OnboardingPromptBuilder::class), 'pensioncheck');

    expect($label)->toBe('Pension Check');
});
