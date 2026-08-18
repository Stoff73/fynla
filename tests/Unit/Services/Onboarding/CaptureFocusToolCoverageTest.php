<?php

declare(strict_types=1);

use App\Services\Onboarding\OnboardingPromptBuilder;
use App\Services\Onboarding\OnboardingStateMachine;

/**
 * The class of bug behind the live budgeting loop: a capture turn asks the user
 * for something its own tool catalogue cannot record. The model has nothing that
 * fits, and its only scripted exits are the prompt-injection refusal (which the
 * director turns into "Sorry, I didn't catch that", forever) or silently
 * dropping the answer, because the capture block tells it to ignore anything
 * outside the tool list.
 *
 * Every focus a user can select must therefore carry the write tool for the
 * question it asks. Two focuses reached production without one:
 * 'pensioncheck' (fixed earlier) and 'budgeting' (fixed 2026-08-18, live user
 * 80). This enumerates the selectable set so the next one cannot ship.
 */

/** Focus id → a tool it cannot do its job without. */
const FOCUS_REQUIRED_TOOL = [
    'savings' => 'create_savings_account',
    'investment' => 'create_investment_account',
    'retirement' => 'create_pension',
    'protection' => 'create_protection_policy',
    'estate' => 'create_property',
    'goals' => 'create_goal',
    'budgeting' => 'set_expenditure',
    'business' => 'create_business_interest',
    'savetax' => 'create_pension',
    'pensioncheck' => 'create_pension',
];

it('offers every selectable focus a tool that can record what it asks for', function (): void {
    $cannotRecord = [];
    foreach (FOCUS_REQUIRED_TOOL as $focus => $requiredTool) {
        if (! in_array($requiredTool, OnboardingPromptBuilder::toolsForFocus($focus), true)) {
            $cannotRecord[$focus] = $requiredTool;
        }
    }

    // Names the focus and the tool it is missing, so the failure reads as the
    // bug it is rather than "expected true".
    expect($cannotRecord)->toBe([]);
});

it('never silently falls back to the savings catalogue for a non-savings focus', function (): void {
    $savingsDefault = ['create_savings_account', 'update_profile', 'update_record'];
    $fellThrough = [];

    foreach (array_keys(FOCUS_REQUIRED_TOOL) as $focus) {
        if ($focus === 'savings') {
            continue;
        }

        $fellThrough[$focus] = OnboardingPromptBuilder::toolsForFocus($focus) === $savingsDefault;
    }

    expect(array_keys(array_filter($fellThrough)))->toBe([]);
});

it('covers every focus bubble the state machine can hand to a capture turn', function (): void {
    $table = (new ReflectionMethod(OnboardingStateMachine::class, 'transitionTable'))
        ->invoke(null);

    $selectable = [];
    foreach ($table as $state) {
        if (($state['capture_field'] ?? null) !== 'onboarding_fyn_selection') {
            continue;
        }
        foreach ($state['bubbles'] ?? [] as $bubble) {
            $selectable[] = $bubble['id'];
        }
    }

    expect($selectable)->not->toBeEmpty();

    // Every bubble the user can press must appear in the table above, so a new
    // journey or focus cannot be added without deciding which tool it needs.
    foreach (array_unique($selectable) as $focus) {
        expect(FOCUS_REQUIRED_TOOL)->toHaveKey($focus);
    }
});
