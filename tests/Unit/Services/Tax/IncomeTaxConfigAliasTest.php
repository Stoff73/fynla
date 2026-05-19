<?php

declare(strict_types=1);

use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * Pins the top-level income-tax threshold aliases against drift.
 *
 * Many services read `income_tax.higher_rate_threshold` and
 * `income_tax.additional_rate_threshold` with a literal `?? 50270` / `?? 125140`
 * fallback. The aliases are derived values — bands[0].upper_limit and
 * bands[1].upper_limit respectively — and must stay in sync with the bands
 * when historical years override them.
 *
 * If this test fails, do NOT just update the assertion. Check that whoever
 * overrode the band threshold for a historical year also updated the alias.
 */
describe('income_tax top-level threshold aliases', function () {
    it('exposes higher_rate_threshold matching bands[0].upper_limit', function () {
        $cfg = app(TaxConfigService::class)->getIncomeTax();

        expect($cfg)->toHaveKey('higher_rate_threshold')
            ->and((int) $cfg['higher_rate_threshold'])->toBe((int) $cfg['bands'][0]['upper_limit'])
            ->and((int) $cfg['higher_rate_threshold'])->toBe(50270);
    });

    it('exposes additional_rate_threshold matching bands[1].upper_limit', function () {
        $cfg = app(TaxConfigService::class)->getIncomeTax();

        expect($cfg)->toHaveKey('additional_rate_threshold')
            ->and((int) $cfg['additional_rate_threshold'])->toBe((int) $cfg['bands'][1]['upper_limit'])
            ->and((int) $cfg['additional_rate_threshold'])->toBe(125140);
    });

    it('keeps personal allowance + basic-rate band width consistent with upper limit', function () {
        // PA (£12,570) + bands[0].max (£37,700 band width) = bands[0].upper_limit (£50,270).
        // This invariant is what audit finding #5 (latent band-ceiling bug) relies on
        // — if a future year breaks it, several services compute the wrong upper bound.
        $cfg = app(TaxConfigService::class)->getIncomeTax();
        $pa = (int) $cfg['personal_allowance'];
        $bandWidth = (int) $cfg['bands'][0]['max'];
        $upperLimit = (int) $cfg['bands'][0]['upper_limit'];

        expect($pa + $bandWidth)->toBe($upperLimit);
    });
});
