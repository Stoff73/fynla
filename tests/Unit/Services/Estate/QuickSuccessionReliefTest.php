<?php

declare(strict_types=1);

use App\Services\Estate\QuickSuccessionReliefCalculator;
use App\Services\TaxConfigService;

/**
 * W-0527 — IHTA 1984 s141, quick succession relief.
 *
 * Where the same property is taxed twice within five years — a beneficiary
 * inherits, pays tax, and then dies — s141 reduces the tax on the second death.
 * The relief is:
 *
 *     tax paid on the first death × (net value received ÷ gross transfer) × taper
 *
 * and the taper is the five-band table in `inheritance_tax.quick_succession_relief`,
 * which was configured and read by nothing. `getQuickSuccessionRelief()` had zero
 * callers.
 *
 * The bands are read, never reproduced: a household under a different configured
 * taper must get that taper. Rule 2.
 */
function qsrCalculator(?array $rules = null): QuickSuccessionReliefCalculator
{
    $taxConfig = Mockery::mock(TaxConfigService::class)->makePartial();
    $taxConfig->shouldReceive('get')
        ->with('inheritance_tax.quick_succession_relief', [])
        ->andReturn($rules ?? [
            'max_years' => 5,
            'relief_rates' => [
                ['max_years' => 1, 'relief' => 1.0],
                ['max_years' => 2, 'relief' => 0.8],
                ['max_years' => 3, 'relief' => 0.6],
                ['max_years' => 4, 'relief' => 0.4],
                ['max_years' => 5, 'relief' => 0.2],
            ],
        ]);

    return new QuickSuccessionReliefCalculator($taxConfig);
}

describe('the s141 taper comes from configuration', function () {
    it('gives full relief where the second death follows within a year', function () {
        // £40,000 tax paid, £160,000 net of that tax received from a £200,000
        // gross transfer. 40,000 × 0.8 × 1.0.
        $relief = qsrCalculator()->reliefFor(
            taxPaidOnFirstDeath: 40000.0,
            netValueReceived: 160000.0,
            grossTransfer: 200000.0,
            yearsBetweenDeaths: 0.5,
        );

        expect($relief)->toBe(32000.0);
    });

    it('tapers by the band the years fall in', function () {
        // Same figures, three and a bit years on: 40,000 × 0.8 × 0.4.
        expect(qsrCalculator()->reliefFor(40000.0, 160000.0, 200000.0, 3.2))->toBe(12800.0);
    });

    it('gives nothing beyond the configured window', function () {
        expect(qsrCalculator()->reliefFor(40000.0, 160000.0, 200000.0, 5.1))->toBe(0.0);
    });

    it('uses a moved band rather than the statutory one', function () {
        // The point of the item. Under a two-band taper nothing may still return
        // 80% at three years — a hardcoded table fails here.
        $moved = qsrCalculator([
            'max_years' => 2,
            'relief_rates' => [
                ['max_years' => 1, 'relief' => 0.9],
                ['max_years' => 2, 'relief' => 0.3],
            ],
        ]);

        expect($moved->reliefFor(40000.0, 160000.0, 200000.0, 1.5))->toBe(9600.0)
            ->and($moved->reliefFor(40000.0, 160000.0, 200000.0, 2.5))->toBe(0.0);
    });
});

describe('it claims nothing it cannot compute', function () {
    it('returns no relief without the tax paid on the first death', function () {
        // The datum the app did not capture. Absent it, s141 has no multiplicand
        // — and inventing one would be a made-up tax relief.
        expect(qsrCalculator()->reliefFor(0.0, 160000.0, 200000.0, 1.0))->toBe(0.0);
    });

    it('does not divide by a gross transfer of nothing', function () {
        expect(qsrCalculator()->reliefFor(40000.0, 160000.0, 0.0, 1.0))->toBe(0.0);
    });

    it('never returns more relief than the tax that was paid', function () {
        // The fraction is capped at 1: a net value exceeding the gross transfer
        // is bad data, not a bonus.
        expect(qsrCalculator()->reliefFor(40000.0, 500000.0, 200000.0, 0.5))->toBe(40000.0);
    });
});
