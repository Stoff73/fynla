<?php

declare(strict_types=1);

use App\Services\Investment\MonteCarloSimulator;
use App\Services\Shared\MonteCarloEngine;
use Illuminate\Support\Facades\DB;

/**
 * W-0251 — a cached simulation must never be served against inputs that did not produce it.
 *
 * The defect this guards: the cache key named only the user, the horizon and a
 * life-event hash. Every input that determines the answer — capital, contributions,
 * expected return, volatility, iterations — was absent from it, so a £47,500 portfolio's
 * result was served for 22 hours against a £172,500 portfolio, and a risk change could
 * not dislodge it.
 *
 * Every assertion below is a movement assertion: the same key prefix with a changed
 * input must produce a changed answer. None of them can pass by matching a literal.
 */
beforeEach(function () {
    $this->simulator = app(MonteCarloSimulator::class);
    $this->prefix = 'user_999999_projection_test';

    DB::table('monte_carlo_cache')->where('cache_key', 'like', $this->prefix.'%')->delete();
});

afterEach(function () {
    DB::table('monte_carlo_cache')->where('cache_key', 'like', $this->prefix.'%')->delete();
});

/** Run through the cache under one shared key prefix and return the final median. */
function medianUnderSharedKey(MonteCarloSimulator $simulator, string $prefix, array $overrides = []): float
{
    $inputs = array_merge([
        'start' => 100000.0,
        'monthly' => 0.0,
        'return' => 0.05,
        'volatility' => 0.10,
        'years' => 10,
        'injections' => [],
    ], $overrides);

    $simulation = $simulator->simulate(
        $inputs['start'],
        $inputs['monthly'],
        $inputs['return'],
        $inputs['volatility'],
        $inputs['years'],
        1000,
        $prefix,
        $inputs['injections'],
        MonteCarloEngine::BAND_PERCENTILES
    );

    foreach ($simulation['final_percentiles'] as $percentile) {
        if ($percentile['percentile'] === '50th') {
            return (float) $percentile['value'];
        }
    }

    throw new RuntimeException('The simulation reported no median.');
}

describe('Monte Carlo cache identity', function () {
    it('serves a cached answer only while every input still matches', function () {
        $first = medianUnderSharedKey($this->simulator, $this->prefix);
        $repeat = medianUnderSharedKey($this->simulator, $this->prefix);

        expect($repeat)->toBe($first)
            ->and(DB::table('monte_carlo_cache')->where('cache_key', 'like', $this->prefix.'%')->count())
            ->toBe(1);
    });

    it('does not serve the previous answer when the capital changes', function () {
        $small = medianUnderSharedKey($this->simulator, $this->prefix, ['start' => 47500.0]);
        $large = medianUnderSharedKey($this->simulator, $this->prefix, ['start' => 172500.0]);

        expect($large)->toBeGreaterThan($small * 3);
    });

    // D-21: the risk preference reaches the simulation as return + volatility. If the
    // key ignores them, changing risk changes the caption and nothing else.
    it('does not serve the previous answer when the expected return changes', function () {
        $cautious = medianUnderSharedKey($this->simulator, $this->prefix, ['return' => 0.05]);
        $adventurous = medianUnderSharedKey($this->simulator, $this->prefix, ['return' => 0.08]);

        expect($adventurous)->toBeGreaterThan($cautious);
    });

    it('does not serve the previous answer when the volatility changes', function () {
        $steady = medianUnderSharedKey($this->simulator, $this->prefix, ['volatility' => 0.05]);
        $wild = medianUnderSharedKey($this->simulator, $this->prefix, ['volatility' => 0.30]);

        expect($wild)->not->toBe($steady);
    });

    it('does not serve the previous answer when contributions change', function () {
        $none = medianUnderSharedKey($this->simulator, $this->prefix, ['monthly' => 0.0]);
        $saving = medianUnderSharedKey($this->simulator, $this->prefix, ['monthly' => 500.0]);

        expect($saving)->toBeGreaterThan($none + 50000);
    });

    it('does not serve the previous answer when the life events change', function () {
        $undisturbed = medianUnderSharedKey($this->simulator, $this->prefix);
        $withdrawn = medianUnderSharedKey($this->simulator, $this->prefix, ['injections' => [2 => -55000]]);

        expect($withdrawn)->toBeLessThan($undisturbed);
    });

    it('treats a life-event map written in a different order as the same simulation', function () {
        $ascending = medianUnderSharedKey($this->simulator, $this->prefix, ['injections' => [2 => -55000, 4 => -25000]]);
        $descending = medianUnderSharedKey($this->simulator, $this->prefix, ['injections' => [4 => -25000, 2 => -55000]]);

        expect($descending)->toBe($ascending);
    });

    it('keeps the caller prefix so per-user cache clearing still reaches the entry', function () {
        medianUnderSharedKey($this->simulator, $this->prefix);

        $key = DB::table('monte_carlo_cache')->where('cache_key', 'like', $this->prefix.'%')->value('cache_key');

        expect($key)->toStartWith($this->prefix)
            ->and($key)->not->toBe($this->prefix);
    });
});

describe('Monte Carlo reproducibility', function () {
    // The same money must not have two answers. Sampling error is what the bands
    // express; it must not also move the headline when nothing about the person moved.
    it('returns the same answer for the same inputs without any cache', function () {
        $first = $this->simulator->simulate(172500.0, 0.0, 0.0707, 0.1688, 10, 1000, null, [], MonteCarloEngine::BAND_PERCENTILES);
        $second = $this->simulator->simulate(172500.0, 0.0, 0.0707, 0.1688, 10, 1000, null, [], MonteCarloEngine::BAND_PERCENTILES);

        expect($second['final_percentiles'])->toBe($first['final_percentiles']);
    });

    it('still returns a different answer for different inputs', function () {
        $first = $this->simulator->simulate(172500.0, 0.0, 0.0707, 0.1688, 10, 1000, null, [], MonteCarloEngine::BAND_PERCENTILES);
        $second = $this->simulator->simulate(172501.0, 0.0, 0.0707, 0.1688, 10, 1000, null, [], MonteCarloEngine::BAND_PERCENTILES);

        expect($second['final_percentiles'])->not->toBe($first['final_percentiles']);
    });
});
