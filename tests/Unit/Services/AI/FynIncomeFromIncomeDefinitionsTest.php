<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;
use App\Models\DCPension;
use App\Models\StatePension;
use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;
use App\Services\AI\KycGateChecker;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\UserProfile\UserProfileService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Audit item 44 (CSJ 2026-10-01, one figure, every surface): Fyn's income is
 * the Income page's income, IncomeDefinitionsService, not a raw sum of the
 * users columns that leaves out a pension being paid.
 */

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function fynIncomeLine(User $user): ?string
{
    $profile = app(AdvicePromptBuilder::class)->buildUserProfile($user->fresh());
    preg_match('/- Total annual income: (£[0-9,.]+)/', $profile, $m);

    return $m[1] ?? null;
}

it('gives Fyn the State Pension being paid as income, as the Income page does', function () {
    $user = User::factory()->create([
        'date_of_birth' => now()->subYears(70),
        'annual_employment_income' => 0,
        'annual_self_employment_income' => 0,
        'annual_rental_income' => 0,
        'annual_dividend_income' => 0,
        'annual_interest_income' => 0,
        'annual_other_income' => 0,
        'annual_trust_income' => 0,
    ]);
    StatePension::factory()->create([
        'user_id' => $user->id,
        'already_receiving' => true,
        'state_pension_forecast_annual' => 12547.60,
    ]);

    $total = app(IncomeDefinitionsService::class)->calculate($user->id)['total_income'];

    expect($total)->toBeGreaterThan(0.0)
        ->and(fynIncomeLine($user))->toBe('£'.number_format($total, 2));

    $kyc = app(KycGateChecker::class)->check($user->fresh(), [
        'primary' => QuerySchemas::INCOME,
        'related' => [],
        'modules' => [],
    ]);
    expect(collect($kyc['missing'])->pluck('label'))->not->toContain('Annual income');
});

it('gives Fyn the same total as the Income page for an earner with rent', function () {
    $user = User::factory()->create([
        'annual_employment_income' => 60000,
        'annual_rental_income' => 12000,
        'annual_dividend_income' => 2000,
    ]);

    $total = app(IncomeDefinitionsService::class)->calculate($user->id)['total_income'];

    expect(fynIncomeLine($user))->toBe('£'.number_format($total, 2));
});

it('names the band the Tax plan uses, with the rate from tax config', function () {
    $user = User::factory()->create([
        'annual_employment_income' => 60000,
        'annual_self_employment_income' => 0,
        'annual_rental_income' => 0,
        'annual_dividend_income' => 0,
        'annual_interest_income' => 0,
        'annual_other_income' => 0,
        'annual_trust_income' => 0,
    ]);

    $profile = app(AdvicePromptBuilder::class)->buildUserProfile($user->fresh());

    expect($profile)->toContain('- Estimated income tax band: Higher rate (40%)');
});

// Walked 2026-10-03: asked "how much tax do I pay?", Fyn added the parts up to
// a total of its own (no salary sacrifice line) and priced the tax at 20% and
// 40% by hand. The context now carries the deduction and the Income page's own
// Income Tax, National Insurance and take-home.
it('gives Fyn the salary sacrifice and the Income page\'s tax, National Insurance and take-home', function () {
    $user = User::factory()->create([
        'date_of_birth' => now()->subYears(40),
        'annual_employment_income' => 50000,
        'annual_self_employment_income' => 0,
        'annual_rental_income' => 0,
        'annual_dividend_income' => 2000,
        'annual_interest_income' => 0,
        'annual_other_income' => 0,
        'annual_trust_income' => 0,
    ]);
    $tab = app(UserProfileService::class)->incomeAndTaxFor($user->fresh());

    $profile = app(AdvicePromptBuilder::class)->buildUserProfile($user->fresh());

    expect($profile)->toContain('- Income Tax this year (the Income page\'s figure; quote it, never work it out again): £'.number_format((float) $tab['income_tax'], 2))
        ->and($profile)->toContain('- National Insurance this year (the Income page\'s figure): £'.number_format((float) $tab['national_insurance'], 2))
        ->and($profile)->toContain('- Take-home after Income Tax and National Insurance (the Income page\'s figure): £'.number_format((float) $tab['net_income'], 2))
        ->and((float) $tab['income_tax'])->toBeGreaterThan(0.0);
});

it('lists employment after salary sacrifice, so the parts add up to the total', function () {
    $user = User::factory()->create(['date_of_birth' => now()->subYears(40), 'annual_employment_income' => 60000, 'employment_income_basis' => 'gross', 'annual_dividend_income' => 0, 'annual_trust_income' => 0, 'annual_self_employment_income' => 0, 'annual_rental_income' => 0, 'annual_interest_income' => 0, 'annual_other_income' => 0]);
    DCPension::factory()->create([
        'user_id' => $user->id, 'scheme_type' => 'workplace', 'pension_type' => 'occupational',
        'salary_sacrifice' => true, 'monthly_contribution_amount' => 250,
        'employee_contribution_percent' => 0, 'employer_contribution_percent' => 0,
    ]);

    $profile = app(AdvicePromptBuilder::class)->buildUserProfile($user->fresh());

    expect($profile)->toContain('- Total annual income: £57,000.00')
        ->and($profile)->toContain('Employment (PAYE) after salary sacrifice (£60,000.00 before; £3,000.00 is paid into the pension before tax and National Insurance) [relevant UK earnings]: £57,000.00')
        ->and($profile)->not->toContain('-£3,000.00');
});
