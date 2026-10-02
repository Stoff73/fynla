<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;
use App\Models\StatePension;
use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;
use App\Services\AI\KycGateChecker;
use App\Services\Tax\IncomeDefinitionsService;
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
