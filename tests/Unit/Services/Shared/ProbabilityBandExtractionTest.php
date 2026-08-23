<?php

declare(strict_types=1);

use App\Services\Shared\MonteCarloEngine;

/**
 * W-0251 — every band a user reads must be a percentile the simulation measured.
 *
 * Three fabrications previously sat between the simulation and the screen: the 20th
 * percentile was a linear interpolation between the 10th and the 25th, the 5th was
 * extrapolated below anything the simulation produced, and years 1 and 2 were pulled
 * toward the start value to flatten the early curve.
 *
 * The assertions below are written so that reinstating any of them turns a test red.
 */
beforeEach(function () {
    $this->engine = new MonteCarloEngine;
});

describe('probability band extraction', function () {
    it('reports the measured 20th percentile, not an interpolation of its neighbours', function () {
        // A distribution deliberately skewed between the 10th and 25th, so the measured
        // 20th sits nowhere near the straight line between them.
        $simulation = [
            'summary' => ['start_value' => 100000],
            'year_by_year' => [[
                'year' => 1,
                'percentiles' => [
                    ['percentile' => '5th', 'value' => 50000],
                    ['percentile' => '10th', 'value' => 60000],
                    ['percentile' => '15th', 'value' => 61000],
                    ['percentile' => '20th', 'value' => 62000],
                    ['percentile' => '25th', 'value' => 120000],
                    ['percentile' => '50th', 'value' => 140000],
                    ['percentile' => '75th', 'value' => 160000],
                    ['percentile' => '90th', 'value' => 180000],
                ],
            ]],
        ];

        $bands = $this->engine->extractProbabilityBands($simulation);
        $final = end($bands);

        $interpolated20 = 60000 + ((120000 - 60000) * 0.67);
        $interpolated15 = 60000 + ((120000 - 60000) * 0.33);

        expect($final['percentile_20'])->toBe(62000.0)
            ->and($final['percentile_20'])->not->toBe($interpolated20)
            ->and($final['percentile_15'])->toBe(61000.0)
            ->and($final['percentile_15'])->not->toBe($interpolated15);
    });

    it('never places the 5th percentile below what the simulation produced', function () {
        $simulation = [
            'summary' => ['start_value' => 100000],
            'year_by_year' => [[
                'year' => 1,
                'percentiles' => [
                    ['percentile' => '5th', 'value' => 95000],
                    ['percentile' => '10th', 'value' => 96000],
                    ['percentile' => '15th', 'value' => 97000],
                    ['percentile' => '20th', 'value' => 98000],
                    ['percentile' => '25th', 'value' => 99000],
                    ['percentile' => '50th', 'value' => 105000],
                    ['percentile' => '75th', 'value' => 112000],
                    ['percentile' => '90th', 'value' => 120000],
                ],
            ]],
        ];

        $bands = $this->engine->extractProbabilityBands($simulation);
        $final = end($bands);

        // The old extrapolation was p10 - (p25 - p10) * 0.33, i.e. below the whole sample.
        expect($final['percentile_5'])->toBe(95000.0)
            ->and($final['percentile_5'])->toBeGreaterThanOrEqual(95000.0);
    });

    it('reports the early years as simulated rather than blended toward the start value', function () {
        $simulation = [
            'summary' => ['start_value' => 100000],
            'year_by_year' => [
                ['year' => 1, 'percentiles' => [['percentile' => '50th', 'value' => 50000]]],
                ['year' => 2, 'percentiles' => [['percentile' => '50th', 'value' => 40000]]],
                ['year' => 3, 'percentiles' => [['percentile' => '50th', 'value' => 30000]]],
            ],
        ];

        $bands = $this->engine->extractProbabilityBands($simulation);

        // Old behaviour blended year 1 at 0.7 (=> 65,000) and year 2 at 0.9 (=> 46,000).
        expect($bands[1]['percentile_50'])->toBe(50000.0)
            ->and($bands[2]['percentile_50'])->toBe(40000.0)
            ->and($bands[3]['percentile_50'])->toBe(30000.0);
    });

    it('anchors year zero at the start value', function () {
        $bands = $this->engine->extractProbabilityBands([
            'summary' => ['start_value' => 172500],
            'year_by_year' => [],
        ]);

        expect($bands)->toHaveCount(1)
            ->and($bands[0]['year_number'])->toBe(0)
            ->and($bands[0]['percentile_20'])->toBe(172500.0);
    });

    it('omits a band the simulation did not measure rather than inventing one', function () {
        $bands = $this->engine->extractProbabilityBands([
            'summary' => ['start_value' => 100000],
            'year_by_year' => [[
                'year' => 1,
                'percentiles' => [
                    ['percentile' => '10th', 'value' => 90000],
                    ['percentile' => '50th', 'value' => 105000],
                ],
            ]],
        ]);

        expect($bands[1])->toHaveKey('percentile_10')
            ->and($bands[1])->not->toHaveKey('percentile_20')
            ->and($bands[1])->not->toHaveKey('percentile_5');
    });

    it('produces bands in ascending order from a real simulation', function () {
        $simulation = $this->engine->simulate(172500.0, 0.0, 0.0707, 0.1688, 10, 1000, null, [], MonteCarloEngine::BAND_PERCENTILES);
        $bands = $this->engine->extractProbabilityBands($simulation);
        $final = end($bands);

        $values = array_map(fn (int $p) => $final["percentile_{$p}"], MonteCarloEngine::BAND_PERCENTILES);
        $sorted = $values;
        sort($sorted);

        expect($values)->toBe($sorted)
            ->and($values[0])->toBeLessThan(end($values));
    });
});
