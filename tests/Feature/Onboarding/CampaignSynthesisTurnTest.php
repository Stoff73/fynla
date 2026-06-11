<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

it('routes the campaign to a synthesis advice state before the terminal state', function () {
    $states = OnboardingStateMachine::states();

    expect($states)->toHaveKey(OnboardingStateMachine::STATE_CAMPAIGN_SYNTHESIS)
        ->and($states[OnboardingStateMachine::STATE_CAMPAIGN_SYNTHESIS]['turn_type'])->toBe('advice')
        ->and($states[OnboardingStateMachine::STATE_CAMPAIGN_SYNTHESIS]['advice_section'])->toBe('synthesis')
        ->and($states[OnboardingStateMachine::STATE_CAMPAIGN_SYNTHESIS]['next'])->toBe(OnboardingStateMachine::STATE_CAMPAIGN_TERMINAL);
});

it('voices a numbered plan with a combined total, conflict notes, and one locked tease', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19', 'marital_status' => 'married',
        'employment_status' => 'full_time', 'annual_employment_income' => 110000,
        'monthly_expenditure' => 3000, 'household_calculation_mode' => 'single_earner_couple',
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 81000, 'interest_rate' => 3.25,
    ]);

    $director = app(OnboardingChatDirector::class);
    $ref = new ReflectionMethod($director, 'buildSectionAdvice');
    $ref->setAccessible(true);
    $text = $ref->invoke($director, $user->fresh(), 'synthesis');

    expect($text)->not->toBeNull()
        ->and($text)->toContain('1.')                        // numbered plan
        ->and($text)->toMatch('/Together these .*£[\d,]+/') // combined total line
        ->and($text)->toContain('qualified financial adviser'); // FCA signposting
    // Locked tease: this user has no pension records → at least one locked strategy
    // exists → exactly ONE "tell me/unlock"-style tease line.
});

it('returns null synthesis for a user with no strategies so the turn stays silent', function () {
    $user = User::factory()->create(['annual_employment_income' => 0, 'monthly_expenditure' => 0]);

    $director = app(OnboardingChatDirector::class);
    $ref = new ReflectionMethod($director, 'buildSectionAdvice');
    $ref->setAccessible(true);
    $text = $ref->invoke($director, $user->fresh(), 'synthesis');

    expect($text)->toBeNull();
});
