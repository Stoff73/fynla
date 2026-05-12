<?php

declare(strict_types=1);

use App\Models\BusinessInterest;
use App\Models\User;
use App\Services\Business\BusinessInterestService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * Wave 2.5 — REVIEW §4 High #34.
 *
 * `BusinessInterestService::calculateExitScenario()` previously fell
 * back to a hardcoded `0.10` (10%) for the BADR rate, and surfaced the
 * literal "10% rate" string in the user-facing warning, even though the
 * statutory rate is 14% in 2025/26 and 18% in 2026/27.
 *
 * After the fix, the rate is sourced from TaxConfigService with no
 * silent fallback, and the warning text quotes the actual current rate.
 */

/**
 * Build a BusinessInterest *without* hitting the factory — the test only
 * needs the property surface the service reads (current_valuation,
 * acquisition_cost, ownership_percentage). Keeping it in-memory avoids
 * schema-coupled factory churn.
 */
function makeBusinessInterest(int $userId): BusinessInterest
{
    $business = new BusinessInterest;
    $business->user_id = $userId;
    $business->current_valuation = 500_000;
    $business->acquisition_cost = 100_000;
    $business->ownership_percentage = 100;
    $business->business_type = 'limited_company';

    return $business;
}

it('uses the BADR rate from TaxConfigService (not the legacy 10% fallback)', function () {
    $user = User::factory()->create();
    $business = makeBusinessInterest($user->id);

    $result = app(BusinessInterestService::class)->calculateExitScenario($business);

    $warningsText = implode(' || ', $result['warnings'] ?? []);

    // Wave 2.5: no stale "10% rate" in user-facing copy when BADR-ineligible.
    expect($warningsText)->not->toContain('10% rate');
});

it('throws FinancialCalculationException when BADR rate is missing from config (fail-loud)', function () {
    $user = User::factory()->create();
    $business = makeBusinessInterest($user->id);

    $activeConfig = \App\Models\TaxConfiguration::where('is_active', true)->first();
    $config = $activeConfig->config_data;
    unset($config['capital_gains_tax']['business_asset_disposal_relief_rate']);
    $activeConfig->update(['config_data' => $config]);

    // Build a fresh service stack so neither TaxConfigService nor
    // BusinessInterestService is reused from the container singletons.
    $service = new BusinessInterestService(new \App\Services\TaxConfigService);

    expect(fn () => $service->calculateExitScenario($business))
        ->toThrow(\App\Exceptions\FinancialCalculationException::class);
});

/**
 * Sibling defect to Wave 2.5: prior `?? 0.20` / `?? 0.10` fallbacks for the
 * CGT higher/basic rates were the pre-30-October-2024 statutory rates.
 * Post-Autumn-2024 they are 0.24 / 0.18. Silent fall-back would surface
 * 4-percentage-point wrong tax advice on every non-BADR business sale.
 * Fail-loud matches the BADR pattern above.
 */
it('throws FinancialCalculationException when CGT higher_rate is missing from config (sibling fail-loud)', function () {
    $user = User::factory()->create();
    $business = makeBusinessInterest($user->id);

    $activeConfig = \App\Models\TaxConfiguration::where('is_active', true)->first();
    $config = $activeConfig->config_data;
    unset($config['capital_gains_tax']['higher_rate']);
    $activeConfig->update(['config_data' => $config]);

    $service = new BusinessInterestService(new \App\Services\TaxConfigService);

    expect(fn () => $service->calculateExitScenario($business))
        ->toThrow(\App\Exceptions\FinancialCalculationException::class);
});

it('throws FinancialCalculationException when CGT basic_rate is missing from config (sibling fail-loud)', function () {
    $user = User::factory()->create();
    $business = makeBusinessInterest($user->id);

    $activeConfig = \App\Models\TaxConfiguration::where('is_active', true)->first();
    $config = $activeConfig->config_data;
    unset($config['capital_gains_tax']['basic_rate']);
    $activeConfig->update(['config_data' => $config]);

    $service = new BusinessInterestService(new \App\Services\TaxConfigService);

    expect(fn () => $service->calculateExitScenario($business))
        ->toThrow(\App\Exceptions\FinancialCalculationException::class);
});
