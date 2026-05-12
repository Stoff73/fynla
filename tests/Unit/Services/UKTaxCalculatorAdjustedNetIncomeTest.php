<?php

declare(strict_types=1);

use App\Services\UKTaxCalculator;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * Pins UKTaxCalculator behaviour for the Personal Allowance taper using
 * Adjusted Net Income (ANI), not gross income.
 *
 * HMRC rule: PA reduces £1 for every £2 of ANI over £100,000. ANI =
 * total taxable income − gross pension contributions (relief-at-source)
 * − grossed-up Gift Aid donations − Blind Person's Allowance.
 *
 * Bug fixed: previously the taper used `total income pre-relief` instead
 * of ANI. A £110k earner contributing £10k gross to a pension had their
 * PA reduced by £5,000 (× 40% marginal = £2,000 over-tax) when their
 * true ANI was £100k → PA preserved at £12,570.
 *
 * Audit finding: review-tax-compliance §2.2 / REVIEW §4 High #35.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->calculator = app(UKTaxCalculator::class);
});

describe('UKTaxCalculator — PA taper uses Adjusted Net Income', function () {
    it('preserves PA when pension contributions bring ANI back to £100k', function () {
        // £110k employment, £10k gross pension contributions.
        // ANI = £100k → PA preserved at £12,570 (no taper, ANI not strictly above £100k).
        // Taxable = £110k − £10k pension − £12,570 PA = £87,430.
        //   basic band space = £50,270 − £12,570 = £37,700; tax £37,700 × 20% = £7,540
        //   higher band: £49,730 × 40% = £19,892
        // Total = £27,432.
        $result = $this->calculator->calculateNetIncome(
            employmentIncome: 110_000,
            pensionContributions: 10_000,
        );

        expect((int) round($result['income_tax']))->toBe(27_432);
    });

    it('preserves PA when Gift Aid donations bring ANI back to £100k', function () {
        // £110k employment, £8k cash donations under Gift Aid (gross-up = £10k).
        // ANI = £100k → PA preserved at £12,570 (no taper).
        // Gift Aid only adjusts ANI; it does NOT reduce taxable income (relief is given
        // at source — charity gets the 20% back; higher-rate relief is via extended BRT
        // band, treated as out-of-scope follow-up).
        // Taxable = £110k − £12,570 = £97,430.
        //   basic: £37,700 × 20% = £7,540
        //   higher: £59,730 × 40% = £23,892
        // Total = £31,432.
        $result = $this->calculator->calculateNetIncome(
            employmentIncome: 110_000,
            giftAidGross: 10_000,
        );

        expect((int) round($result['income_tax']))->toBe(31_432);
    });

    it('tapers PA against ANI, not gross income', function () {
        // £130k employment, £10k pension, £5k Gift Aid gross.
        // ANI = £130k − £10k − £5k = £115k → excess £15k → PA reduced by £7,500
        // → adjusted PA = £5,070.
        // Taxable = £130k − £10k pension − £5,070 PA = £114,930.
        //   basic band space = £50,270 − £5,070 = £45,200; tax £45,200 × 20% = £9,040
        //   higher band remaining = £69,730 × 40% = £27,892
        // Total = £36,932.
        $result = $this->calculator->calculateNetIncome(
            employmentIncome: 130_000,
            pensionContributions: 10_000,
            giftAidGross: 5_000,
        );

        expect((int) round($result['income_tax']))->toBe(36_932);
    });

    it('still tapers PA when deductions are zero (backwards compatibility)', function () {
        // £110k employment, no deductions.
        // ANI = £110k → PA tapered by £5,000 → adjusted PA = £7,570.
        // Taxable = £110k − £7,570 = £102,430.
        //   basic band space = £50,270 − £7,570 = £42,700; tax £42,700 × 20% = £8,540
        //   higher band: £59,730 × 40% = £23,892
        // Total = £32,432. (Matches pre-fix behaviour — backwards-compatible default.)
        $result = $this->calculator->calculateNetIncome(
            employmentIncome: 110_000,
        );

        expect((int) round($result['income_tax']))->toBe(32_432);
    });

    it('handles deep taper with large pension contribution', function () {
        // £150k employment, £25k pension. ANI = £125k → PA reduced by £12,500
        // → adjusted PA = £70.
        // Taxable = £150k − £25k pension − £70 PA = £124,930.
        //   basic band space = £50,270 − £70 = £50,200; tax £50,200 × 20% = £10,040
        //   higher band remaining = £74,730 × 40% = £29,892
        // Total = £39,932.
        $result = $this->calculator->calculateNetIncome(
            employmentIncome: 150_000,
            pensionContributions: 25_000,
        );

        expect((int) round($result['income_tax']))->toBe(39_932);
    });
});
