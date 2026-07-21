<?php

declare(strict_types=1);

use App\Services\UKTaxCalculator;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * Pins the Gift Aid band-extension relief in UKTaxCalculator::calculateIncomeTax.
 *
 * HMRC rule (ITA 2007 s414): grossed-up Gift Aid donations extend the basic-
 * and higher-rate limits by the gross donation. Basic-rate relief is given to
 * the charity at source (so Gift Aid does NOT reduce taxable income); the band
 * extension is what delivers higher/additional-rate relief to the donor.
 *
 * Bug fixed: Gift Aid previously only reduced Adjusted Net Income for the PA
 * taper — the bands were never extended, so a higher-rate donor received no
 * higher-rate relief.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->calculator = app(UKTaxCalculator::class);
});

describe('UKTaxCalculator — Gift Aid extends the tax bands (ITA 2007 s414)', function () {
    it('gives a higher-rate donor 20-point relief via the extended basic-rate band', function () {
        // £80k employment (higher-rate, but ANI £70k so no PA taper — isolates the band
        // extension). £8k cash donation, grossed up to £10k.
        // Bands: basic limit £50,270 + £10k = £60,270.
        // Taxable = £80k − £12,570 PA = £67,430.
        //   basic band space = £60,270 − £12,570 = £47,700 → £47,700 × 20% = £9,540
        //   higher: £67,430 − £47,700 = £19,730 × 40% = £7,892
        // Total = £17,432.
        $withGiftAid = $this->calculator->calculateNetIncome(
            employmentIncome: 80_000,
            giftAidGross: 10_000,
        );

        expect((int) round($withGiftAid['income_tax']))->toBe(17_432);
    });

    it('reduces the higher-rate donor\'s tax by exactly 20% of the gross donation', function () {
        // Same £80k earner with and without the £10k gross donation. The band
        // extension converts £10k of income from 40% to 20% → £2,000 relief.
        $baseline = $this->calculator->calculateNetIncome(employmentIncome: 80_000);
        $withGiftAid = $this->calculator->calculateNetIncome(
            employmentIncome: 80_000,
            giftAidGross: 10_000,
        );

        expect((int) round($baseline['income_tax']))->toBe(19_432)
            ->and((int) round($baseline['income_tax'] - $withGiftAid['income_tax']))->toBe(2_000);
    });

    it('gives a basic-rate donor no band-extension benefit (relief is at source)', function () {
        // £30k earner is entirely within the basic-rate band, so extending the band
        // changes nothing — Gift Aid relief for a basic-rate taxpayer is given wholly
        // to the charity at source. Tax is identical with and without the donation.
        $baseline = $this->calculator->calculateNetIncome(employmentIncome: 30_000);
        $withGiftAid = $this->calculator->calculateNetIncome(
            employmentIncome: 30_000,
            giftAidGross: 10_000,
        );

        expect((int) round($withGiftAid['income_tax']))->toBe((int) round($baseline['income_tax']));
    });
});
