<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\RetirementActionDefinition;
use App\Models\RetirementProfile;
use App\Models\User;
use App\Services\Actions\ActionCardService;
use App\Services\Mobile\NextActionsService;
use Database\Seeders\ActionHowToSeeder;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\RetirementActionDefinitionSeeder;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

/*
 * Retirement cards are typed by their definition key (CSJ 2026-10-01), so a
 * card carries its key, its own figures and, for a per-pension rule, its
 * pension: evaluateAgentActions → adapter → composer → aggregator →
 * NextActionsService → ActionCardService → ActionHowToFacts. The batch stays
 * draft until CSJ approves it.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
    $this->seed(SavingsActionDefinitionSeeder::class);
    $this->seed(ProtectionActionDefinitionSeeder::class);
    $this->seed(RetirementActionDefinitionSeeder::class);
    $this->seed(ActionHowToSeeder::class);
});

function lowContributionSaver(): array
{
    $user = User::factory()->create([
        'employment_status' => 'employed', 'annual_employment_income' => 55000, 'annual_expenditure' => 30000,
        'annual_self_employment_income' => 0, 'annual_dividend_income' => 0, 'annual_interest_income' => 0,
        'annual_rental_income' => 0, 'annual_other_income' => 0, 'onboarding_completed' => true, 'marital_status' => 'single',
        'date_of_birth' => now()->subYears(40)->toDateString(),
    ]);
    RetirementProfile::create([
        'user_id' => $user->id, 'current_age' => 40, 'target_retirement_age' => 65,
        'target_retirement_income' => 40000, 'current_annual_salary' => 55000,
    ]);
    $pension = DCPension::create([
        'user_id' => $user->id, 'scheme_name' => 'Test Workplace', 'provider' => 'Acme', 'scheme_type' => 'workplace',
        'pension_type' => 'occupational', 'employee_contribution_percent' => 3.0, 'employer_contribution_percent' => 3.0,
        'current_fund_value' => 20000, 'annual_salary' => 55000, 'platform_fee_percent' => 1.2, 'retirement_age' => 65,
    ]);

    return [$user, $pension];
}

function retirementCard(User $user, string $key): ?array
{
    return collect(app(NextActionsService::class)->buildAll($user->id))
        ->first(fn (array $i): bool => ($i['card']['definition_key'] ?? null) === $key);
}

it('keeps a draft retirement entry off the card until CSJ approves it', function () {
    // The seeded batch is approved (CSJ 2026-10-01); put this entry back in draft
    // rather than depend on the seed's current state.
    RetirementActionDefinition::where('key', 'employer_match')->update(['how_to_status' => 'draft']);
    [$user] = lowContributionSaver();
    $item = retirementCard($user, 'employer_match');

    expect($item)->not->toBeNull()
        ->and(app(ActionCardService::class)->for($user, $item['id'])['how_to'])->toBe([]);
});

it('fills an approved employer match how-to with the card\'s own figures, one card per pension', function () {
    RetirementActionDefinition::where('key', 'employer_match')->update(['how_to_status' => 'approved']);
    [$user, $pension] = lowContributionSaver();
    $item = retirementCard($user, 'employer_match');

    $card = app(ActionCardService::class)->for($user, $item['id']);

    expect($item['id'])->toBe('retirement_employer_match_a'.$pension->id)
        ->and($item['card']['figures'])->toMatchArray(['employee_percent' => '3.0', 'scheme_name' => 'Test Workplace'])
        ->and(implode(' ', $card['why']))->toContain('You pay 3.0% of your salary into Test Workplace.')
        ->and(implode(' ', $card['how_to']))->toContain('Ask your employer, or check the scheme\'s rules, how their contribution to Test Workplace changes with yours');
});

it('gives the charges and income cards their own ids and figures', function () {
    RetirementActionDefinition::whereIn('key', ['pension_charges_review', 'retirement_income_position'])->update(['how_to_status' => 'approved']);
    [$user, $pension] = lowContributionSaver();

    $charges = retirementCard($user, 'pension_charges_review');
    $income = retirementCard($user, 'retirement_income_position');

    expect($charges['id'])->toBe('retirement_pension_charges_review_a'.$pension->id)
        ->and(implode(' ', app(ActionCardService::class)->for($user, $charges['id'])['why']))->toContain('a platform fee of 1.20%')
        ->and($income['id'])->toBe('retirement_retirement_income_position')
        ->and($income['title'])->toStartWith('Your retirement income is about £')
        ->and(implode(' ', app(ActionCardService::class)->for($user, $income['id'])['why']))->toContain('Your target is £40,000');
});

function untouchedPotRetiree(): array
{
    $user = User::factory()->create([
        'employment_status' => 'retired', 'annual_employment_income' => 0, 'annual_expenditure' => 24000,
        'annual_self_employment_income' => 0, 'annual_dividend_income' => 0, 'annual_interest_income' => 0,
        'annual_rental_income' => 0, 'annual_other_income' => 0, 'onboarding_completed' => true, 'marital_status' => 'single',
        'date_of_birth' => now()->subYears(66)->toDateString(),
    ]);
    RetirementProfile::create([
        'user_id' => $user->id, 'current_age' => 66, 'target_retirement_age' => 66,
        'target_retirement_income' => 20000, 'current_annual_salary' => 0,
    ]);
    $pension = DCPension::create([
        'user_id' => $user->id, 'scheme_name' => 'Old Workplace', 'provider' => 'Acme', 'scheme_type' => 'workplace',
        'pension_type' => 'occupational', 'current_fund_value' => 200000, 'retirement_age' => 66,
    ]);

    return [$user, $pension];
}

it('shows a retiree who has not yet taken their pot the ways of taking it, with their own tax-free figure', function () {
    RetirementActionDefinition::where('key', 'approaching_decumulation')->update(['how_to_status' => 'approved']);
    [$user] = untouchedPotRetiree();
    $item = retirementCard($user, 'approaching_decumulation');

    expect($item)->not->toBeNull();
    $card = app(ActionCardService::class)->for($user, $item['id']);
    $steps = implode(' ', $card['how_to']);

    // £200,000 x 25%, within the Lump Sum Allowance, from tax config.
    expect(implode(' ', $card['why']))->toContain('have not yet taken money from your defined contribution pensions, worth about £200,000')
        ->and($steps)->toContain('up to 25% of each pension tax-free, and no more than £268,275')
        ->and($steps)->toContain('On yours that is about £50,000')
        ->and($steps)->toContain('Pension Commencement Lump Sum (PCLS)')
        ->and($steps)->toContain('uncrystallised funds pension lump sums (UFPLS)')
        ->and($steps)->toContain('only £10,000 a year can go into defined contribution pensions')
        ->and($steps)->toContain('ask each provider which of them it offers')
        ->and($steps)->not->toContain('You can usually take a pension from age');
});

it('gives no decumulation card once the pot has been drawn from', function () {
    [$user, $pension] = untouchedPotRetiree();
    $pension->update(['annual_drawdown_income' => 8000, 'has_flexibly_accessed' => true]);

    expect(retirementCard($user->fresh(), 'approaching_decumulation'))->toBeNull();
});
