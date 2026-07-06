<?php

declare(strict_types=1);

use App\Models\TaxConfiguration;
use App\Services\Estate\FutureValueCalculator;
use App\Services\Estate\LifeCoverCalculator;
use App\Services\Estate\LifePolicyStrategyService;

/**
 * Consolidation guard (jul6 audit, Fix 1): calculateFutureValueOfAnnuity was
 * duplicated byte-for-byte as a private method in LifeCoverCalculator and
 * LifePolicyStrategyService. It now lives once on FutureValueCalculator,
 * injected into both. These tests pin that the extracted public method
 * reproduces the former inline formula exactly, and that both consumers still
 * construct via the container with the new dependency.
 */
beforeEach(function () {
    if (! TaxConfiguration::where('is_active', true)->exists()) {
        TaxConfiguration::factory()->create(['is_active' => true]);
    }

    $this->calculator = app(FutureValueCalculator::class);
});

/** Reference = the exact formula both deleted private copies used. */
function annuityReference(float $payment, float $rate, int $periods): float
{
    if ($rate == 0) {
        return $payment * $periods;
    }

    return $payment * ((pow(1 + $rate, $periods) - 1) / $rate);
}

it('reproduces the former inline annuity formula across a range of inputs', function () {
    $cases = [
        [1000.0, 0.047, 20],
        [5000.0, 0.05, 30],
        [2400.0, 0.03, 10],
        [1200.0, 0.0, 25],   // zero-rate branch
        [0.0, 0.06, 15],
        [7500.0, 0.047, 1],
    ];

    foreach ($cases as [$payment, $rate, $periods]) {
        expect($this->calculator->calculateFutureValueOfAnnuity($payment, $rate, $periods))
            ->toBe(annuityReference($payment, $rate, $periods));
    }
});

it('zero rate degrades to payment times periods', function () {
    expect($this->calculator->calculateFutureValueOfAnnuity(3000.0, 0.0, 12))->toBe(36000.0);
});

it('both life-cover consumers resolve with the injected FutureValueCalculator', function () {
    expect(app(LifeCoverCalculator::class))->toBeInstanceOf(LifeCoverCalculator::class);
    expect(app(LifePolicyStrategyService::class))->toBeInstanceOf(LifePolicyStrategyService::class);
});
