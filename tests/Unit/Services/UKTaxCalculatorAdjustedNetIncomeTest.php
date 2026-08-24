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

    it('preserves PA and extends the bands when Gift Aid donations bring ANI back to £100k', function () {
        // £110k employment, £8k cash donations under Gift Aid (gross-up = £10k).
        // ANI = £100k → PA preserved at £12,570 (no taper).
        // Gift Aid does NOT reduce taxable income (basic-rate relief is given to the
        // charity at source), but it DOES extend the basic/higher rate limits by the
        // gross donation (ITA 2007 s414) — that is the higher-rate relief.
        // Bands: basic limit £50,270 + £10k = £60,270; higher limit £125,140 + £10k.
        // Taxable = £110k − £12,570 = £97,430.
        //   basic band space = £60,270 − £12,570 = £47,700 → £47,700 × 20% = £9,540
        //   higher: £97,430 − £47,700 = £49,730 × 40% = £19,892
        // Total = £29,432 (£2,000 less than the un-extended £31,432 — the 20-point
        // higher-rate relief on the £10k gross donation).
        $result = $this->calculator->calculateNetIncome(
            employmentIncome: 110_000,
            giftAidGross: 10_000,
        );

        expect((int) round($result['income_tax']))->toBe(29_432);
    });

    it('tapers PA against ANI, not gross income, and extends bands by Gift Aid', function () {
        // £130k employment, £10k pension, £5k Gift Aid gross.
        // ANI = £130k − £10k − £5k = £115k → excess £15k → PA reduced by £7,500
        // → adjusted PA = £5,070.
        // Pension uses the net-pay model (reduces taxable income); Gift Aid extends
        // the bands by the £5k gross donation (ITA 2007 s414).
        // Bands: the £37,700 basic-rate WIDTH is the constant, so the basic-rate
        // limit is £5,070 PA + £37,700 + £5k extension = £47,770 (W-0174 — it is
        // NOT £50,270 + £5k, which would leave a £50,200-wide 20% slice).
        // Taxable = £130k − £10k pension − £5,070 PA = £114,930.
        //   basic band space = £47,770 − £5,070 = £42,700; tax £42,700 × 20% = £8,540
        //   higher band remaining = £114,930 − £42,700 = £72,230 × 40% = £28,892
        // Total = £37,432 (£2,000 less than the £39,432 without the donation — the
        // £5k gross gift both extends the bands and restores £2,500 of allowance,
        // and every pound of it is relieved at the 40% marginal rate).
        $result = $this->calculator->calculateNetIncome(
            employmentIncome: 130_000,
            pensionContributions: 10_000,
            giftAidGross: 5_000,
        );

        expect((int) round($result['income_tax']))->toBe(37_432);
    });

    it('still tapers PA when deductions are zero (backwards compatibility)', function () {
        // £110k employment, no deductions.
        // ANI = £110k → PA tapered by £5,000 → adjusted PA = £7,570.
        // The basic-rate band narrows with the allowance: it runs from £7,570 to
        // £45,270, still £37,700 wide. Reading it as £50,270 − £7,570 = £42,700
        // taxed £5,000 of withdrawn allowance at 20% instead of 40% (W-0174).
        // Taxable = £110k − £7,570 = £102,430.
        //   basic band space = £37,700; tax £37,700 × 20% = £7,540
        //   higher band: £64,730 × 40% = £25,892
        // Total = £33,432 — £1,000 more than the £32,432 this case asserted while
        // the defect stood, being 20 points on the £5,000 of allowance withdrawn.
        $result = $this->calculator->calculateNetIncome(
            employmentIncome: 110_000,
        );

        expect((int) round($result['income_tax']))->toBe(33_432);
    });

    it('handles deep taper with large pension contribution', function () {
        // £150k employment, £25k pension. ANI = £125k → PA reduced by £12,500
        // → adjusted PA = £70.
        // Taxable = £150k − £25k pension − £70 PA = £124,930. Total income of
        // £125,000 stays below the £125,140 additional-rate threshold.
        //   basic band space = £37,700; tax £37,700 × 20% = £7,540
        //   higher band remaining = £87,230 × 40% = £34,892
        // Total = £42,432 — £2,500 more than the £39,932 asserted while the defect
        // stood, being 20 points on the £12,500 of allowance withdrawn.
        $result = $this->calculator->calculateNetIncome(
            employmentIncome: 150_000,
            pensionContributions: 25_000,
        );

        expect((int) round($result['income_tax']))->toBe(42_432);
    });
});
