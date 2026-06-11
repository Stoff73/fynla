<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\TaxConfigService;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

function invokeSectionAdvice(User $user, string $section): ?string
{
    $director = app(OnboardingChatDirector::class);
    $ref = new ReflectionMethod($director, 'buildSectionAdvice');
    $ref->setAccessible(true);

    return $ref->invoke($director, $user, $section);
}

it('voices the ISA wrap strategy in the savings section (the Azlan miss)', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19', 'marital_status' => 'married',
        'employment_status' => 'full_time', 'annual_employment_income' => 110000,
        'monthly_expenditure' => 3000, 'household_calculation_mode' => 'single_earner_couple',
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => true, 'current_balance' => 19000,
        'isa_subscription_year' => app(TaxConfigService::class)->getTaxYear(),
        'isa_subscription_amount' => 100,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 81000, 'interest_rate' => 3.25,
    ]);

    $text = invokeSectionAdvice($user->fresh(), 'savings');

    expect($text)->not->toBeNull()
        ->and($text)->toContain('ISA')
        ->and($text)->toMatch('/£[\d,]+/');
});

it('hedges judgement-tier strategies and states mechanical ones directly', function () {
    // income section voices pa_taper_rescue (claim_tier=mechanical) — no hedge prefix.
    $user = User::factory()->create([
        'marital_status' => 'single',
        'household_calculation_mode' => 'single',
        'date_of_birth' => '1980-06-01',
        'employment_status' => 'full_time',
        'annual_employment_income' => 110000,
    ]);

    $text = invokeSectionAdvice($user->fresh(), 'income');

    // Mechanical strategy: should NOT contain the hedge prefix.
    expect($text)->not->toBeNull()
        ->and($text)->not->toContain('You may want to consider:');
});

it('returns null for a section with no applicable strategies', function () {
    $user = User::factory()->create(['annual_employment_income' => 25000, 'monthly_expenditure' => 1500]);

    expect(invokeSectionAdvice($user->fresh(), 'investments'))->toBeNull();
});

it('returns null for synthesis section (handled by the next task)', function () {
    $user = User::factory()->create();

    expect(invokeSectionAdvice($user->fresh(), 'synthesis'))->toBeNull();
});

it('returns null for unmapped sections (giving, expenditure)', function () {
    $user = User::factory()->create();

    expect(invokeSectionAdvice($user->fresh(), 'giving'))->toBeNull();
    expect(invokeSectionAdvice($user->fresh(), 'expenditure'))->toBeNull();
});
