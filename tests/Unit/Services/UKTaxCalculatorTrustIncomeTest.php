<?php

declare(strict_types=1);

use App\Services\TaxConfigService;
use App\Services\UKTaxCalculator;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * Trust income in the Income tab's engine (walked 2026-10-03, TODO item 7a).
 * A beneficiary is taxed on trust income at their own rates, inside their own
 * bands, and the tax the trust paid is a credit against it: discretionary
 * income is "treated as though it has already been taxed at 45%", with any
 * excess reclaimable (ITA 2007 s494; gov.uk/trusts-taxes/beneficiaries-paying-
 * and-reclaiming-tax-on-trusts); an interest in possession gives basic rate
 * taxpayers a credit and higher rate taxpayers more to pay; bare trust income
 * is the beneficiary's own. It is non-savings income, so it comes before
 * interest and dividends (ITA 2007 s16).
 *
 * Before: the trust's 45% was counted as the person's own tax, the income took
 * no band space (so dividends after it were taxed at the basic rate), and the
 * reclaim was priced at one typed-in marginal rate.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function trustCase(float $salary, float $trust, ?string $type, float $interest = 0, float $dividends = 0): array
{
    $result = app(UKTaxCalculator::class)->calculateDetailedNetIncome(
        employmentIncome: $salary,
        trustIncome: $trust,
        interestIncome: $interest,
        dividendIncome: $dividends,
        trustType: $type,
    );
    $card = collect($result['income_breakdowns'])->firstWhere('income_type', 'trust');

    return [$result, $card];
}

it('taxes discretionary trust income in the bands, before interest and dividends, with the 45% as a credit', function () {
    [$result, $card] = trustCase(47500, 2000, 'discretionary', 300, 1500);

    // Salary: £34,930 at 20% = £6,986. Trust: £2,000 inside the £2,770 of
    // basic band left = £400, against £900 the trust paid: reclaim £500.
    // Interest: higher rate taxpayer overall (£38,730 taxable), so a £500
    // allowance covers the £300 and takes £300 of band. Dividends: the £500
    // allowance takes the last £470 of basic band and £30 of higher; the other
    // £1,000 is at the higher dividend rate.
    $higherDividendRate = (float) app(TaxConfigService::class)->getDividendTax()['higher_rate'];

    expect((float) $card['tax_breakdown']['total_income_tax'])->toBe(400.0)
        ->and((float) $card['tax_breakdown']['tax_paid_by_trust'])->toBe(900.0)
        ->and($card['tax_breakdown']['reclaim_info']['type'])->toBe('reclaim')
        ->and((float) $card['tax_breakdown']['reclaim_info']['amount'])->toBe(500.0)
        ->and((float) $card['net_income'])->toBe(1600.0)
        ->and((float) $result['summary']['total_income_tax_before_credits'])->toBe(round(6986 + 400 + 1000 * $higherDividendRate, 2));
});

it('prices the part above the basic rate band at the higher rate', function () {
    [, $card] = trustCase(50000, 2000, 'discretionary');

    // £270 of basic band left: £54, then £1,730 at 40% = £692. £900 paid: reclaim £154.
    expect((float) $card['tax_breakdown']['total_income_tax'])->toBe(746.0)
        ->and((float) $card['tax_breakdown']['higher_rate']['taxable'])->toBe(1730.0)
        ->and((float) $card['tax_breakdown']['reclaim_info']['amount'])->toBe(154.0);
});

it('asks a higher rate beneficiary of an interest in possession trust for the difference', function () {
    [, $card] = trustCase(60000, 1000, 'interest_in_possession');

    // £400 at 40% against a £200 credit at 20%: £200 more to pay.
    expect((float) $card['tax_breakdown']['total_income_tax'])->toBe(400.0)
        ->and((float) $card['tax_breakdown']['tax_paid_by_trust'])->toBe(200.0)
        ->and($card['tax_breakdown']['reclaim_info']['type'])->toBe('owe')
        ->and((float) $card['tax_breakdown']['reclaim_info']['amount'])->toBe(200.0);
});

it('taxes bare trust income as the beneficiary\'s own, with nothing paid by the trust', function () {
    [, $card] = trustCase(20000, 1000, 'bare');

    expect((float) $card['tax_breakdown']['total_income_tax'])->toBe(200.0)
        ->and((float) $card['tax_breakdown']['tax_paid_by_trust'])->toBe(0.0)
        ->and($card['tax_breakdown']['reclaim_info'])->toBeNull();
});
