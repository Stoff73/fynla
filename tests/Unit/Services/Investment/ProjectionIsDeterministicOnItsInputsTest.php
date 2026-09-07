<?php

declare(strict_types=1);

use App\Services\Investment\MonteCarloSimulator;

/**
 * W-0222 — the headline projected tax figure moved by £305,727 depending on whether a
 * cache was warm.
 *
 * The same household, unchanged, produced £2,791,757 read through the service and
 * £3,097,484 in a browser one cycle later. Neither reading was a regression and both
 * were internally consistent; the variance was the Monte Carlo simulation being re-run.
 * A projection is allowed to be uncertain, but the number on the page is presented as a
 * single figure with no stated uncertainty, and it moved by more than £300,000 for
 * reasons that had nothing to do with the household — nobody edited anything.
 *
 * The fix is `MonteCarloEngine::seedFromInputs()`: `mt_srand()` seeded from a hash of
 * the ksorted inputs, so the run is a pure function of what was asked. **This file is
 * the acceptance criterion that had no test** — "pinned by a test that would fail if
 * the figure became cache-dependent again".
 *
 * Every assertion here is an EQUALITY BETWEEN TWO RUNS, never a comparison against a
 * literal. A literal would pin whatever the seed happens to produce today and would
 * break on any legitimate change to the model; what must never change is that two
 * identical questions get one answer.
 *
 * Cache is bypassed throughout (`$cacheKey = null`), deliberately: a cached second run
 * would pass by returning the first run's stored array and would prove nothing about
 * the simulation. The point is that the SIMULATION repeats, not that the cache works.
 */
beforeEach(function () {
    $this->simulator = new MonteCarloSimulator;
});

it('returns the same figure when the same question is asked twice', function () {
    $run = fn (): array => $this->simulator->simulate(
        startValue: 220_000,
        monthlyContribution: 0,
        expectedReturn: 0.06,
        volatility: 0.14,
        years: 36,
        iterations: 200,
        cacheKey: null,
    );

    $first = $run();
    $second = $run();

    expect($second)->toEqual($first);
});

it('still moves when the question changes, so determinism is not a frozen answer', function () {
    $run = fn (float $return): array => $this->simulator->simulate(
        startValue: 220_000,
        monthlyContribution: 0,
        expectedReturn: $return,
        volatility: 0.14,
        years: 36,
        iterations: 200,
        cacheKey: null,
    );

    // Guards the obvious wrong fix: seeding on a constant would satisfy the test above
    // and destroy the model. A higher expected return must produce a higher median.
    $lower = $run(0.04);
    $higher = $run(0.08);

    $median = fn (array $r): float => (float) collect($r['final_percentiles'])
        ->firstWhere('percentile', '50th')['value'];

    expect($median($higher))->toBeGreaterThan($median($lower));
});

it('repeats a multi-asset run too, which is the path the estate projection uses', function () {
    $assets = [
        ['name' => 'equities', 'expectedReturn' => 0.07, 'volatility' => 0.16, 'weight' => 0.7],
        ['name' => 'bonds', 'expectedReturn' => 0.03, 'volatility' => 0.06, 'weight' => 0.3],
    ];
    $correlations = [[1.0, 0.2], [0.2, 1.0]];

    $run = fn (): array => $this->simulator->runMultiAssetSimulation(
        assetClasses: $assets,
        correlationMatrix: $correlations,
        startValue: 220_000,
        monthlyContribution: 0,
        years: 20,
        iterations: 150,
        cacheKey: null,
    );

    expect($run())->toEqual($run());
});
