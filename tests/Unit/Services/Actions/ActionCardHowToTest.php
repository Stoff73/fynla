<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\SavingsAccount;
use App\Models\TaxActionDefinition;
use App\Models\User;
use App\Services\Actions\ActionCardService;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\Tax\TaxStrategyMath;
use Database\Seeders\ActionHowToSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

/*
 * How-to steps are drafted and then reviewed by CSJ (ruling 2026-09-25); a card
 * shows them only once approved. The user's own records pick the branch and
 * their own figures fill it (CSJ 2026-09-28).
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

it('shows steps only once they are approved', function () {
    $user = User::factory()->create([
        'employment_status' => 'employed', 'annual_employment_income' => 60000,
        'annual_self_employment_income' => 0, 'annual_dividend_income' => 0, 'annual_interest_income' => 0,
        'annual_rental_income' => 0, 'annual_other_income' => 0, 'onboarding_completed' => true, 'marital_status' => 'single',
    ]);
    SavingsAccount::factory()->create(['user_id' => $user->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 40000, 'interest_rate' => 4.5, 'ownership_type' => 'individual', 'joint_owner_id' => null]);

    TaxActionDefinition::where('strategy_type', 'pension_tax_relief')->update([
        'how_to_steps' => json_encode(['Decide how much to pay in.']),
        'how_to_status' => 'draft',
    ]);
    expect(app(ActionCardService::class)->for($user, 'tax_pension_tax_relief')['how_to'])->toBe([]);

    TaxActionDefinition::where('strategy_type', 'pension_tax_relief')->update(['how_to_status' => 'approved']);
    expect(app(ActionCardService::class)->for($user, 'tax_pension_tax_relief')['how_to'])->toBe(['Decide how much to pay in.']);
});

/** A higher-rate earner, with the repository's pension_tax_relief entry approved. */
function higherRateEarnerWithApprovedPensionSteps(): User
{
    $user = User::factory()->create([
        'employment_status' => 'employed', 'annual_employment_income' => 60000,
        'annual_self_employment_income' => 0, 'annual_dividend_income' => 0, 'annual_interest_income' => 0,
        'annual_rental_income' => 0, 'annual_other_income' => 0, 'onboarding_completed' => true, 'marital_status' => 'single',
    ]);
    $entry = ActionHowToSeeder::parse((string) file_get_contents(ActionHowToSeeder::sourcePath('tax')))['pension_tax_relief'];
    TaxActionDefinition::where('strategy_type', 'pension_tax_relief')->update([
        'how_to_steps' => json_encode($entry['steps']),
        'how_to_status' => 'approved',
    ]);

    return $user;
}

/** @return array<string, mixed> the composed plan item the card reads */
function pensionReliefItem(User $user): array
{
    return collect(app(ComposedTaxPlanService::class)->forUser($user)['items'])->firstWhere('type', 'pension_tax_relief');
}

it('walks a SIPP holder through paying net into their own SIPP and claiming the rest', function () {
    $user = higherRateEarnerWithApprovedPensionSteps();
    DCPension::create(['user_id' => $user->id, 'scheme_name' => 'Vanguard SIPP', 'scheme_type' => 'sipp', 'pension_type' => 'sipp',
        'current_fund_value' => 20000, 'monthly_contribution_amount' => 0]);

    $item = pensionReliefItem($user);
    $gross = (float) $item['suggested_contribution'];
    $relief = round($gross * 0.20);
    $extra = floor((float) $item['estimated_annual_tax_saved']) - $relief;
    $pounds = fn (float $v) => '£'.number_format(floor($v + 0.001));

    $steps = app(ActionCardService::class)->for($user, 'tax_pension_tax_relief')['how_to'];

    expect($steps)->toContain(sprintf('Pay %s into Vanguard SIPP. The provider claims %s of basic-rate relief from HM Revenue and Customs (HMRC) and adds it, so %s goes into your pension.', $pounds($gross - $relief), $pounds($relief), $pounds($gross)))
        ->and(collect($steps)->first(fn ($s) => str_contains($s, 'claim the other')))->toContain($pounds($extra))
        ->and(collect($steps)->filter(fn ($s) => str_contains($s, 'increase your salary sacrifice') || str_contains($s, 'no pension recorded')))->toBeEmpty()
        ->and(implode(' ', $steps))->not->toContain('{');
});

it('shows a SIPP holder what the payment does to their tax and what it really costs', function () {
    $user = higherRateEarnerWithApprovedPensionSteps();
    DCPension::create(['user_id' => $user->id, 'scheme_name' => 'Vanguard SIPP', 'scheme_type' => 'sipp', 'pension_type' => 'sipp',
        'current_fund_value' => 20000, 'monthly_contribution_amount' => 0]);

    $item = pensionReliefItem($user);
    $saved = floor((float) $item['estimated_annual_tax_saved']);
    $now = app(TaxStrategyMath::class)->incomeTaxNow($user);
    $gross = (float) $item['suggested_contribution'];
    $pounds = fn (float $v) => '£'.number_format(floor($v + 0.001));

    $card = app(ActionCardService::class)->for($user, 'tax_pension_tax_relief');

    expect($card['what_this_changes'])->toBe([
        sprintf('Doing this alone, your Income Tax for the year falls from %s to %s: %s less.', $pounds($now), $pounds($now - $saved), $pounds($saved)),
        sprintf('%s goes into your pension, and after the tax relief it costs you %s.', $pounds($gross), $pounds($gross - $saved)),
    ])->and(implode(' ', $card['how_to']))->not->toContain('Doing this alone');
});

it('sends a salary sacrifice member to payroll, with nothing to claim', function () {
    $user = higherRateEarnerWithApprovedPensionSteps();
    DCPension::create(['user_id' => $user->id, 'scheme_name' => 'Acme Pension', 'scheme_type' => 'workplace', 'pension_type' => 'occupational',
        'current_fund_value' => 20000, 'employee_contribution_percent' => 3, 'salary_sacrifice' => true]);

    $steps = app(ActionCardService::class)->for($user, 'tax_pension_tax_relief')['how_to'];

    expect($steps[0])->toStartWith('Ask your employer to increase your salary sacrifice into Acme Pension by ')
        ->and(implode(' ', $steps))->not->toContain('Self Assessment')
        ->and(implode(' ', $steps))->not->toContain('Vanguard');
});

it('tells someone with no pension recorded to open one first', function () {
    $user = higherRateEarnerWithApprovedPensionSteps();

    $steps = app(ActionCardService::class)->for($user, 'tax_pension_tax_relief')['how_to'];

    // Employed with no workplace pension: automatic enrolment comes first.
    expect($steps[0])->toStartWith('As an employee, your employer must usually enrol you in a workplace pension')
        ->and($steps[1])->toStartWith('You have no pension recorded. Open a personal pension or self-invested personal pension (SIPP)');
});
