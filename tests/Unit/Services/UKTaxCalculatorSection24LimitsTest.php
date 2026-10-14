<?php

declare(strict_types=1);

use App\Services\UKTaxCalculator;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * T69: the finance-cost reduction for an individual's property business
 * (ITTOIA 2005 s274A-s274AA; HMRC PIM2058) is the basic rate of the LOWEST of:
 * the finance costs, the property profits (s274AA(2)), and the adjusted total
 * income, which is net income less savings income, dividends and the Personal
 * Allowance (s274AA(3), (6)). It was limited only by the total tax.
 *
 * Every case: £9,360 of finance costs (£180,000 at 5.2%), so a £1,872 credit
 * at 20% before the limits.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->calculator = app(UKTaxCalculator::class);
});

describe('UKTaxCalculator — Section 24 credit limits', function () {
    it('gives the whole credit when neither the profits nor the income limit it', function () {
        $result = $this->calculator->calculateDetailedNetIncome(
            employmentIncome: 60_000,
            rentalIncome: 15_000,
            section24Credit: 1_872,
        );

        expect($result['summary']['section_24_credit'])->toBe(1872.0);
    });

    it('limits the credit to the basic rate of the property profits', function () {
        // Profit £3,000 < finance costs £9,360: 20% × £3,000 = £600.
        $result = $this->calculator->calculateDetailedNetIncome(
            employmentIncome: 60_000,
            rentalIncome: 3_000,
            section24Credit: 1_872,
        );

        expect($result['summary']['section_24_credit'])->toBe(600.0);
    });

    it('limits the credit to the basic rate of adjusted total income, which leaves out dividends', function () {
        // Non-savings income £15,000 less the £12,570 Personal Allowance: ATI £2,430,
        // below the profits (£15,000) and the finance costs (£9,360). 20% × £2,430 = £486.
        // The £40,000 of dividends is tax the old cap let the credit reach.
        $result = $this->calculator->calculateDetailedNetIncome(
            rentalIncome: 15_000,
            dividendIncome: 40_000,
            section24Credit: 1_872,
        );

        expect($result['summary']['section_24_credit'])->toBe(486.0)
            ->and($result['summary']['total_income_tax_before_credits'])->toBeGreaterThan(1_872.0);
    });

    it('gives no credit when the Personal Allowance covers all non-savings income', function () {
        $result = $this->calculator->calculateDetailedNetIncome(
            rentalIncome: 10_000,
            interestIncome: 30_000,
            section24Credit: 1_872,
        );

        expect($result['summary']['section_24_credit'])->toBe(0.0);
    });
});
