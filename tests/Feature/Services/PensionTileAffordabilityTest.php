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

it('limits pension headroom to the gross the affordability calculator\'s money buys this year', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single', 'employment_status' => 'employed',
        'annual_employment_income' => 60000, 'marital_status' => 'single',
        'expenditure_entry_mode' => 'simple', 'monthly_expenditure' => 3000, 'annual_expenditure' => 36000,
    ]);
    // The money a year of surplus pays, grossed up by the basic-rate relief
    // the provider adds (FA 2004 s192): the same figure the plan's own card is
    // capped by (PensionAffordability, CSJ 2026-09-30).
    $basicRelief = (float) app(TaxConfigService::class)->getPensionAllowances()['tax_relief']['basic_rate'];
    $affordable = round(max(0.0, 12 * (float) app(CompositePlanService::class)->financials($user)['effective_surplus']) / (1 - $basicRelief), 2);

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

// A non-earner funds a contribution from savings, not surplus income: the cap
// is what their recorded cash covers, grossed up by the relief added at source
// (CSJ 2026-09-29), and the limit is not "from your earnings".
function nonEarnerWithCash(string $status, float $cash): User
{
    $user = User::factory()->create([
        'household_calculation_mode' => 'single', 'employment_status' => $status,
        'annual_employment_income' => null, 'annual_self_employment_income' => null,
        'marital_status' => 'single', 'date_of_birth' => now()->subYears(40)->toDateString(),
    ]);
    if ($cash > 0) {
        \App\Models\SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => $cash, 'interest_rate' => 0, 'ownership_type' => 'individual', 'joint_owner_id' => null]);
    }

    return $user;
}

it('leaves a declared non-earner whose savings cover it their whole basic amount', function (string $status) {
    $user = nonEarnerWithCash($status, 10000);
    $basic = (float) app(TaxConfigService::class)->getPensionAllowances()['relevant_earnings_minimum'];

    $tile = pensionTile(app(TaxStrategyService::class)->getDashboardPayload($user)['user_allowances']);

    expect((float) $tile['amount'])->toBe($basic)
        ->and((float) $tile['remaining'])->toBe($basic)
        ->and($tile['label'])->toBe('Pension contribution limit without earnings');
})->with(['unemployed', 'retired']);

it('caps a declared non-earner at what their savings cover', function () {
    // £1,000 of cash pays £1,000 in; with 20% added at source that is £1,250 gross.
    $user = nonEarnerWithCash('retired', 1000);
    $relief = (float) app(TaxConfigService::class)->getPensionAllowances()['tax_relief']['basic_rate'];

    $tile = pensionTile(app(TaxStrategyService::class)->getDashboardPayload($user)['user_allowances']);

    expect((float) $tile['remaining'])->toBe(round(1000 / (1 - $relief), 2))
        ->and((float) $tile['affordable_this_year'])->toBe(round(1000 / (1 - $relief), 2));
});

it('still caps a low earner at what they can afford', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single', 'employment_status' => 'part_time',
        'annual_employment_income' => 2000, 'marital_status' => 'single',
    ]);

    $tile = pensionTile(app(TaxStrategyService::class)->getDashboardPayload($user)['user_allowances']);

    expect($tile)->toHaveKey('affordable_this_year')
        ->and($tile['label'])->toBe('Pension contribution limit from your earnings');
});
