<?php

declare(strict_types=1);

use App\Models\StatePension;
use App\Models\User;
use App\Services\Estate\EstateDataReadinessService;
use App\Services\Investment\Recommendation\DataReadinessService as InvestmentDataReadinessService;
use App\Services\Protection\ProtectionDataReadinessService;
use App\Services\Retirement\RetirementDataReadinessService;
use App\Services\Savings\SavingsDataReadinessService;
use App\Services\Tax\IncomeDefinitionsService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * CSJ 2026-10-02: "why are we not using the income figure provided?" Every
 * module's income check reads the Income page's total (ResolvesIncome ->
 * IncomeDefinitionsService). Each summed the users income columns itself, so a
 * retiree living on a pension (csjones walk account 460, £9,000 a year) was told
 * "Gross annual income is required" by Investment, Protection and Savings.
 */

/** Every failed check keyed 'income' or 'income_data', wherever the shape nests it. */
function failedIncomeChecks(array $result): array
{
    $found = [];
    $walk = static function (array $node) use (&$walk, &$found): void {
        if (in_array($node['key'] ?? null, ['income', 'income_data'], true) && ($node['passed'] ?? true) === false) {
            $found[] = $node['key'];
        }
        foreach ($node as $child) {
            if (is_array($child)) {
                $walk($child);
            }
        }
    };
    $walk($result);

    return $found;
}

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);

    $this->retiree = User::factory()->create([
        'date_of_birth' => now()->subYears(70),
        'employment_status' => 'retired',
        'marital_status' => 'single',
        'annual_employment_income' => 0,
        'annual_self_employment_income' => 0,
        'annual_rental_income' => 0,
        'annual_dividend_income' => 0,
        'annual_interest_income' => 0,
        'annual_other_income' => 0,
        'annual_trust_income' => 0,
    ]);
    StatePension::factory()->create([
        'user_id' => $this->retiree->id,
        'already_receiving' => true,
        'state_pension_forecast_annual' => 9000,
    ]);
});

it('has the pension being paid as income on the Income page', function (): void {
    expect(app(IncomeDefinitionsService::class)->calculate($this->retiree->id)['total_income'])->toEqual(9000.0);
});

it('passes every module income check for a retiree living on a pension', function (string $service): void {
    $result = app($service)->assess($this->retiree->fresh());

    expect(failedIncomeChecks($result))->toBe([]);
})->with([
    'investment' => InvestmentDataReadinessService::class,
    'protection' => ProtectionDataReadinessService::class,
    'savings' => SavingsDataReadinessService::class,
    'retirement' => RetirementDataReadinessService::class,
    'estate' => EstateDataReadinessService::class,
]);
