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
