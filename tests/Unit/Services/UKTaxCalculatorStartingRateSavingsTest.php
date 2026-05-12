<?php

declare(strict_types=1);

use App\Services\UKTaxCalculator;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * Pins UKTaxCalculator behaviour around the Starting Rate for Savings (HMRC ITA 2007 s12).
 *
 * SRS is £5,000 of savings income at 0%, reduced £1-for-£1 by non-savings income
 * exceeding the personal allowance. Without it, non-earner / low-earner savers
 * are over-taxed by up to £1,000 (£5,000 × 20%) on interest income.
 *
 * Order applied (HMRC): non-savings income consumes PA, then SRS 0% band on savings,
 * then PSA 0% band on savings, then standard bands.
 *
 * Audit finding: review-tax-compliance §1 (Starting Rate for Savings not applied).
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->calculator = app(UKTaxCalculator::class);
});

describe('UKTaxCalculator — Starting Rate for Savings', function () {
    it('applies full £5,000 SRS plus PSA when non-savings income is below the personal allowance', function () {
        // Retiree-style profile: £10,000 employment (below £12,570 PA), £5,000 interest.
        // Pre-fix: £5,000 - £1,000 PSA = £4,000 × 20% = £800 owed.
        // Post-fix: full £5,000 absorbed by SRS (PSA unused). Tax owed = £0.
        $result = $this->calculator->calculateNetIncome(
            employmentIncome: 10_000,
            interestIncome: 5_000,
        );

        expect((int) round($result['income_tax']))->toBe(0);
    });

    it('tapers SRS £1-for-£1 against non-savings income above the personal allowance', function () {
        // £15,000 non-savings (£2,430 above PA), £5,000 interest.
        // SRS available: £5,000 - £2,430 = £2,570.
        // Interest taxed: £5,000 - £2,570 SRS - £1,000 PSA = £1,430 × 20% = £286.
        // Non-savings tax: (£15,000 - £12,570) × 20% = £486.
        // Total: £772.
        $result = $this->calculator->calculateNetIncome(
            employmentIncome: 15_000,
            interestIncome: 5_000,
        );

        expect((int) round($result['income_tax']))->toBe(772);
    });

    it('fully eliminates SRS when non-savings income exceeds PA by £5,000 or more', function () {
        // £18,000 non-savings (£5,430 above PA — exceeds the £5,000 SRS band entirely).
        // SRS available: £0. Interest: £5,000 - £1,000 PSA = £4,000 × 20% = £800.
        // Non-savings tax: (£18,000 - £12,570) × 20% = £1,086.
        // Total: £1,886.
        $result = $this->calculator->calculateNetIncome(
            employmentIncome: 18_000,
            interestIncome: 5_000,
        );

        expect((int) round($result['income_tax']))->toBe(1_886);
    });

    it('does not apply SRS for higher-rate taxpayers with non-savings well above PA', function () {
        // £60,000 non-savings, £2,000 interest. No SRS available.
        // PSA at higher rate: £500. Taxable interest: £1,500 × 40% = £600.
        // Non-savings tax: £37,700 × 20% + (£60,000 - £50,270) × 40% = £7,540 + £3,892 = £11,432.
        // Total: £12,032.
        $result = $this->calculator->calculateNetIncome(
            employmentIncome: 60_000,
            interestIncome: 2_000,
        );

        expect((int) round($result['income_tax']))->toBe(12_032);
    });

    it('does not over-credit SRS when interest income is below £5,000', function () {
        // £8,000 non-savings (below PA), £2,000 interest. SRS available £5,000 but interest is £2k.
        // All £2,000 absorbed at 0%. Tax = £0.
        $result = $this->calculator->calculateNetIncome(
            employmentIncome: 8_000,
            interestIncome: 2_000,
        );

        expect((int) round($result['income_tax']))->toBe(0);
    });
});
