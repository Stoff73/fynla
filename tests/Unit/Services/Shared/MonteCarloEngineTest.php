<?php

declare(strict_types=1);

use App\Services\Shared\MonteCarloEngine;

beforeEach(function () {
    $this->engine = new MonteCarloEngine;
});

it('returns required result keys from simulate', function () {
    $result = $this->engine->simulate(
        startValue: 10000,
        monthlyContribution: 500,
        expectedReturn: 0.07,
        volatility: 0.15,
        years: 5,
        iterations: 50
    );

    expect($result)->toHaveKeys(['final_values', 'year_by_year', 'percentiles', 'summary']);
    expect($result['final_values'])->toHaveCount(50);
    expect($result['year_by_year'])->toHaveCount(5);
    expect($result['summary'])->toHaveKeys([
        'start_value', 'monthly_contribution', 'years', 'iterations',
        'expected_return', 'volatility', 'total_contributions',
        'median_final_value', 'median_gain',
    ]);
});

it('produces positive final values', function () {
    $result = $this->engine->simulate(10000, 500, 0.07, 0.15, 3, 100);

    $allPositive = collect($result['final_values'])->every(fn ($v) => $v > 0);
    expect($allPositive)->toBeTrue();
});

it('returns sorted final values', function () {
    $result = $this->engine->simulate(10000, 500, 0.07, 0.15, 3, 100);

    $values = $result['final_values'];
    for ($i = 1; $i < count($values); $i++) {
        expect($values[$i])->toBeGreaterThanOrEqual($values[$i - 1]);
    }
});

it('calculates total contributions correctly', function () {
    $result = $this->engine->simulate(10000, 500, 0.07, 0.15, 5, 10);

    expect($result['summary']['total_contributions'])->toBe(40000.00);
});

it('handles zero volatility deterministically', function () {
    $result = $this->engine->simulate(10000, 0, 0.0, 0.0, 1, 10);

    foreach ($result['final_values'] as $value) {
        expect(round($value, 2))->toBe(10000.00);
    }
});

it('generates year by year percentiles', function () {
    $result = $this->engine->simulate(10000, 500, 0.07, 0.15, 3, 50);

    expect($result['year_by_year'])->toHaveCount(3);
    expect($result['year_by_year'][0]['year'])->toBe(1);
    expect($result['year_by_year'][0]['percentiles'])->toHaveCount(5);
});

it('returns 100 probability when all values exceed target', function () {
    $values = [100.0, 200.0, 300.0, 400.0, 500.0];
    expect($this->engine->calculateGoalProbability($values, 50))->toBe(100.00);
});

it('returns 0 probability when no values meet target', function () {
    $values = [10.0, 20.0, 30.0, 40.0, 50.0];
    expect($this->engine->calculateGoalProbability($values, 100))->toBe(0.00);
});

it('returns correct partial probability', function () {
    $values = [100.0, 200.0, 300.0, 400.0, 500.0];
    expect($this->engine->calculateGoalProbability($values, 300))->toBe(60.00);
});

it('handles empty array for probability', function () {
    expect($this->engine->calculateGoalProbability([], 100))->toBe(0.0);
});

it('returns all five standard percentiles', function () {
    $values = range(1, 100);
    $values = array_map('floatval', $values);
    $percentiles = $this->engine->calculatePercentiles($values);

    expect($percentiles)->toHaveCount(5);
    expect($percentiles[0]['percentile'])->toBe('10th');
    expect($percentiles[2]['percentile'])->toBe('50th');
    expect($percentiles[4]['percentile'])->toBe('90th');
});

it('returns zeros for empty percentile array', function () {
    $percentiles = $this->engine->calculatePercentiles([]);

    foreach ($percentiles as $p) {
        expect($p['value'])->toBe(0.0);
    }
});

it('returns median from sorted array', function () {
    $values = [10.0, 20.0, 30.0, 40.0, 50.0];
    expect($this->engine->getPercentileValue($values, 50))->toBe(30.0);
});

it('returns zero for empty percentile value', function () {
    expect($this->engine->getPercentileValue([], 50))->toBe(0.0);
});

it('generates normally distributed values centred around mean', function () {
    $values = [];
    for ($i = 0; $i < 1000; $i++) {
        $values[] = $this->engine->generateNormal(10.0, 1.0);
    }

    $mean = array_sum($values) / count($values);
    expect($mean)->toBeGreaterThan(9.0)->toBeLessThan(11.0);
});

it('percentiles do not include final_value key in base engine', function () {
    $values = range(1, 100);
    $values = array_map('floatval', $values);
    $percentiles = $this->engine->calculatePercentiles($values);

    foreach ($percentiles as $p) {
        expect($p)->toHaveKeys(['percentile', 'value']);
        expect($p)->not->toHaveKey('final_value');
    }
});
