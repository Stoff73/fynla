<?php

declare(strict_types=1);

use App\Services\TaxConfigService;
use App\Services\UKTaxCalculator;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * The detailed path (calculateDetailedNetIncome), which the tax strategy
 * engine prices every saving with, must tax savings and dividends as ITA 2007
 * does: s12 starting rate for savings, s12B Personal Savings Allowance sized by
 * the band the whole income reaches, s13A dividend allowance, s25 unused
 * Personal Allowance, and 0% slices occupying band space. Audit 2026-09-27.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->calculator = app(UKTaxCalculator::class);
});

function detailedTax(float $salary, float $interest = 0, float $dividends = 0): float
{
    return (float) app(UKTaxCalculator::class)->calculateDetailedNetIncome(
        employmentIncome: $salary,
        interestIncome: $interest,
        dividendIncome: $dividends,
    )['summary']['total_income_tax_before_credits'];
}

it('applies the starting rate for savings', function () {
    // £1,430 of salary above the allowance at 20% = £286. Interest: £3,570
    // starting rate, £1,000 allowance, £1,430 at 20% = £286.
    expect(detailedTax(14000, 6000))->toBe(572.0);
});

it('sizes the Personal Savings Allowance by the whole income and lets it occupy band space', function () {
    // £50,500 total is higher rate, so £500 allowance. Salary tax £7,286; the
    // allowance fills £500 of the £1,270 basic band left, so £770 at 20% and
    // £230 at 40% = £246.
    expect(detailedTax(49000, 1500))->toBe(7532.0);
});

it('lets unused Personal Allowance cover interest before the starting rate', function () {
    // £20,000 interest, no other income: £12,570 allowance, £5,000 starting
    // rate, £1,000 savings allowance, £1,430 at 20% = £286, on both paths.
    expect(detailedTax(0, 20000))->toBe(286.0)
        ->and(round((float) app(UKTaxCalculator::class)->calculateNetIncome(interestIncome: 20000)['income_tax'], 2))->toBe(286.0);
});

it('lets unused Personal Allowance cover dividends', function () {
    $dividend = app(TaxConfigService::class)->getDividendTax();
    $pa = (float) app(TaxConfigService::class)->getIncomeTax()['personal_allowance'];
    $expected = round((20000 - $pa - (float) $dividend['allowance']) * (float) $dividend['basic_rate'], 2);

    expect(detailedTax(0, 0, 20000))->toBe($expected);
});

it('agrees with the simple path across salary, interest and dividend mixes', function (float $salary, float $interest, float $dividends) {
    $simple = (float) app(UKTaxCalculator::class)->calculateNetIncome(
        employmentIncome: $salary, interestIncome: $interest, dividendIncome: $dividends,
    )['income_tax'];

    expect(round(detailedTax($salary, $interest, $dividends), 0))->toBe(round($simple, 0));
})->with([
    [10000, 5000, 0], [14000, 6000, 0], [30000, 2000, 0], [49000, 1500, 0], [60000, 3000, 0],
    [110000, 2000, 0], [140000, 5000, 0], [30000, 0, 5000], [60000, 1000, 4000], [0, 0, 20000], [0, 20000, 0], [8000, 3000, 10000], [50270, 0, 0], [45000, 4000, 3000],
]);
