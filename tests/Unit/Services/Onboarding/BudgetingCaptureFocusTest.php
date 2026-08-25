<?php

declare(strict_types=1);

use App\Services\AI\Fyn\FynCaptureTurnInstructions;
use App\Services\Onboarding\OnboardingPromptBuilder;

/**
 * Live 2026-08-18, user 80 conversation 67: Fyn asked for the headline monthly
 * spending categories, the user answered "£5000 per month", and got the
 * prompt-injection refusal followed by "Sorry, I didn't catch that" — on every
 * retry, with no way out of the step.
 *
 * Cause: 'budgeting' was aliased to 'savings' in both focus maps, so the turn
 * ran as a Cash & Savings capture whose only write tool was
 * create_savings_account. A monthly spending figure is not a savings account,
 * the model had nothing that fit, and the refusal is its only scripted exit.
 * Identical to the failure the 'pensioncheck' arm was added to stop.
 */
it('budgeting focus exposes the expenditure tool, not the savings default', function (): void {
    $tools = OnboardingPromptBuilder::toolsForFocus('budgeting');

    expect($tools)->toContain('set_expenditure')
        ->and($tools)->not->toContain('create_savings_account');

    // Appended to every focus for the retraction block.
    expect($tools)->toContain('update_profile')
        ->and($tools)->toContain('update_record');
});

it('budgeting focus is labelled Budgeting on the capture turn, not Cash & Savings', function (): void {
    expect(OnboardingPromptBuilder::focusLabel('budgeting'))->toBe('Budgeting');

    // The label reaches the model through the rendered capture block — the
    // live prompt said "this Cash & Savings turn" while asking for a budget.
    $block = FynCaptureTurnInstructions::render(
        OnboardingPromptBuilder::focusLabel('budgeting'),
        implode(', ', OnboardingPromptBuilder::toolsForFocus('budgeting')),
    );

    expect($block)->toContain('Budgeting')
        ->and($block)->toContain('set_expenditure')
        ->and($block)->not->toContain('Cash & Savings');
});
