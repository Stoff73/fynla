<?php

declare(strict_types=1);

use App\Models\StatePension;
use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;
use App\Traits\ResolvesIncome;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The one income figure (CSJ 2026-10-02: "why are we not using the income
 * figure provided?"). resolveGrossAnnualIncome is the Income page's total
 * (IncomeDefinitionsService `total_income`), not a sum of the users income
 * columns: those left out a pension being paid, so a retiree on £9,000 of
 * pension was told "gross annual income is required" by the module checks.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/** Anonymous harness exposing the protected trait method for assertion. */
function grossIncomeHarness(): object
{
    return new class
    {
        use ResolvesIncome;

        public function total(User $user): float
        {
            return $this->resolveGrossAnnualIncome($user);
        }
    };
}

it('is the Income page total for an earner', function () {
    $user = User::factory()->create([
        'annual_employment_income' => 50000,
        'annual_self_employment_income' => 10000,
        'annual_dividend_income' => 3000,
        'annual_interest_income' => 1500,
        'annual_other_income' => 2000,
        'annual_trust_income' => 500,
    ]);

    $total = app(IncomeDefinitionsService::class)->calculate($user->id)['total_income'];

    expect(grossIncomeHarness()->total($user))->toBe((float) $total)
        ->and((float) $total)->toBe(67000.0);
});

it('counts a State Pension being paid, as the Income page does', function () {
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
        'state_pension_forecast_annual' => 9000,
    ]);

    expect(grossIncomeHarness()->total($user->fresh()))->toBe(9000.0);
});

it('returns zero when the user has no income at all', function () {
    $user = User::factory()->create([
        'annual_employment_income' => 0,
        'annual_self_employment_income' => 0,
        'annual_rental_income' => 0,
        'annual_dividend_income' => 0,
        'annual_interest_income' => 0,
        'annual_other_income' => 0,
        'annual_trust_income' => 0,
    ]);

    expect(grossIncomeHarness()->total($user))->toBe(0.0);
});
