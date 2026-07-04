<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\RetirementActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(RetirementActionDefinitionSeeder::class);
});

function pensioncheckSectionAdviceFor(User $user, string $section): ?string
{
    $director = app(OnboardingChatDirector::class);
    $ref = new ReflectionMethod($director, 'buildSectionAdvice');
    $ref->setAccessible(true);

    return $ref->invoke($director, $user, $section);
}

// ── Pensions section ──────────────────────────────────────────────────────────

it('voices retirement strategy advice for the pensions section on a pensioncheck user', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1980-05-15',
        'marital_status' => 'single',
        'annual_employment_income' => 50000,
        'employment_status' => 'full_time',
        'onboarding_fyn_selection' => 'pensioncheck',
    ]);
    RetirementProfile::factory()->create([
        'user_id' => $user->id,
        'current_age' => 45,
        'target_retirement_age' => 65,
        'target_retirement_income' => 30000,
    ]);
    // Zero-contribution DC pension fires Start_contributions → increase_pension_contribution.
    DCPension::factory()->create([
        'user_id' => $user->id,
        'scheme_type' => 'workplace',
        'current_fund_value' => 50000,
        'employee_contribution_percent' => 0,
        'employer_contribution_percent' => 0,
        'monthly_contribution_amount' => 0,
    ]);

    $text = pensioncheckSectionAdviceFor($user->fresh(), 'pensions');

    expect($text)->not->toBeNull()
        // The actions-list notice must appear (F6 — every voiced strategy is logged).
        ->and($text)->toContain("I've added this to your actions list to come back to later.");
});

it('adds the actions-list line for any voiced retirement pensions advice (F6)', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1975-01-01',
        'marital_status' => 'single',
        'annual_employment_income' => 60000,
        'employment_status' => 'full_time',
        'onboarding_fyn_selection' => 'pensioncheck',
    ]);
    RetirementProfile::factory()->create([
        'user_id' => $user->id,
        'current_age' => 50,
        'target_retirement_age' => 65,
        'target_retirement_income' => 35000,
    ]);
    DCPension::factory()->create([
        'user_id' => $user->id,
        'scheme_type' => 'workplace',
        'current_fund_value' => 80000,
        'employee_contribution_percent' => 0,
        'employer_contribution_percent' => 0,
        'monthly_contribution_amount' => 0,
    ]);

    $text = pensioncheckSectionAdviceFor($user->fresh(), 'pensions');

    expect($text)->not->toBeNull()
        ->and($text)->toContain("I've added this to your actions list to come back to later.");
});

// ── State pension section ─────────────────────────────────────────────────────

it('returns null for the state_pension section (no strategy-source types map there)', function () {
    // The composed retirement plan has no source='strategy' rows for state pension
    // (ni_gaps and state_pension_no_forecast are agent-sourced, not strategy-sourced).
    // The advice turn fires but emits nothing; the flow advances silently.
    $user = User::factory()->create(['onboarding_fyn_selection' => 'pensioncheck']);

    expect(pensioncheckSectionAdviceFor($user->fresh(), 'state_pension'))->toBeNull();
});

// ── Retirement goals section ──────────────────────────────────────────────────

it('returns null for the retirement_goals section when no relevant strategies fire', function () {
    // A bare pensioncheck user with no retirement data produces no plan_retirement_income item.
    $user = User::factory()->create([
        'onboarding_fyn_selection' => 'pensioncheck',
        'annual_employment_income' => 0,
        'marital_status' => 'single',
    ]);

    expect(pensioncheckSectionAdviceFor($user->fresh(), 'retirement_goals'))->toBeNull();
});

// ── Campaign advice states exist for pensioncheck sections ────────────────────

it('campaignSectionAdvice routes state_pension to its own advice state', function () {
    $states = OnboardingStateMachine::states();

    expect($states)->toHaveKey(OnboardingStateMachine::STATE_CAMPAIGN2_ADVICE_STATE_PENSION)
        ->and($states[OnboardingStateMachine::STATE_CAMPAIGN2_ADVICE_STATE_PENSION]['turn_type'])->toBe('advice')
        ->and($states[OnboardingStateMachine::STATE_CAMPAIGN2_ADVICE_STATE_PENSION]['advice_section'])->toBe('state_pension');
});

it('campaignSectionAdvice routes retirement_goals to its own advice state', function () {
    $states = OnboardingStateMachine::states();

    expect($states)->toHaveKey(OnboardingStateMachine::STATE_CAMPAIGN2_ADVICE_RETIREMENT_GOALS)
        ->and($states[OnboardingStateMachine::STATE_CAMPAIGN2_ADVICE_RETIREMENT_GOALS]['turn_type'])->toBe('advice')
        ->and($states[OnboardingStateMachine::STATE_CAMPAIGN2_ADVICE_RETIREMENT_GOALS]['advice_section'])->toBe('retirement_goals');
});

// ── Savetax pensions backward compat ─────────────────────────────────────────

it('savetax pensions section still uses the tax plan (backward compatibility)', function () {
    // A savetax user must NOT see retirement-plan advice for the pensions section.
    // Since the tax plan has no pensions-section strategies for a user with no ISAs
    // or savings, the advice is null — just as it was before.
    $user = User::factory()->create([
        'onboarding_fyn_selection' => 'savetax',
        'annual_employment_income' => 25000,
        'marital_status' => 'single',
    ]);

    // No salary_sacrifice_ni or pension_aa_carry_forward fires for a basic-rate earner.
    expect(pensioncheckSectionAdviceFor($user->fresh(), 'pensions'))->toBeNull();
});
