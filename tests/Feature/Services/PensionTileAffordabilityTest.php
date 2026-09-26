<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Coordination\CompositePlanService;
use App\Services\Mobile\MilestoneDetectionService;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\TaxStrategyService;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * CSJ 2026-09-25: the pension tile must show what the user can use and afford,
 * not a statutory limit they can neither reach nor fund.
 * - Relief limit: FA 2004 s190, relief on the member's contributions is capped
 *   at relevant UK earnings, or the basic amount (pension.relevant_earnings_minimum)
 *   if higher. https://www.legislation.gov.uk/ukpga/2004/12/section/190
 * - Affordability: the app's own calculator,
 *   CompositePlanService::financials()['effective_surplus'] (monthly disposable
 *   income less committed contributions and goal commitments).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function pensionTile(array $allowances): array
{
    return collect($allowances)->firstWhere('key', 'pension_annual_allowance');
}

it('caps the pension tile at the relief limit an £8,000 earner can actually use (FA 2004 s190)', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single', 'employment_status' => 'employed',
        'annual_employment_income' => 8000, 'marital_status' => 'single',
    ]);
    $basic = (float) app(TaxConfigService::class)->getPensionAllowances()['relevant_earnings_minimum'];

    $tile = pensionTile(app(TaxStrategyCalculator::class)->calculate($user)->userAllowances);

    expect($tile['amount'])->toBe(max(8000.0, $basic))
        ->and($tile['label'])->not->toContain('Annual Allowance');
});

it('keeps the Annual Allowance tile when it, not earnings, is the binding limit', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single', 'employment_status' => 'employed',
        'annual_employment_income' => 150000, 'marital_status' => 'single',
    ]);
    $aa = (float) app(TaxConfigService::class)->getPensionAllowances()['annual_allowance'];

    $tile = pensionTile(app(TaxStrategyCalculator::class)->calculate($user)->userAllowances);

    expect($tile['amount'])->toBe($aa)
        ->and($tile['label'])->toBe('Pension Annual Allowance');
});

it('limits pension headroom to what the affordability calculator says the user can fund this year', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single', 'employment_status' => 'employed',
        'annual_employment_income' => 60000, 'marital_status' => 'single',
        'expenditure_entry_mode' => 'simple', 'monthly_expenditure' => 3000, 'annual_expenditure' => 36000,
    ]);
    $affordable = max(0.0, 12 * (float) app(CompositePlanService::class)->financials($user)['effective_surplus']);

    $tile = pensionTile(app(TaxStrategyService::class)->getDashboardPayload($user)['user_allowances']);

    expect($affordable)->toBeLessThan((float) $tile['amount'])
        ->and((float) $tile['remaining'])->toBe(round(min((float) $tile['amount'] - (float) $tile['used'], $affordable), 2))
        ->and((float) $tile['affordable_this_year'])->toBe(round($affordable, 2));
});

it('never tells a user at their earnings relief limit that they used their Annual Allowance', function () {
    $user = User::factory()->create();
    $position = fn (string $basis) => [[
        'key' => 'pension_annual_allowance', 'amount' => 8000, 'used' => 8000, 'remaining' => 0,
        'utilisation_pct' => 100.0, 'available' => true, 'known' => true, 'limit_basis' => $basis,
    ]];
    $service = app(MilestoneDetectionService::class);

    expect($service->detectTaxYearAllowances($user, '2026/27', $position('relief')))->toBe([])
        ->and($service->detectTaxYearAllowances($user, '2026/27', $position('annual_allowance')))->not->toBe([]);
});
