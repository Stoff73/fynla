<?php

declare(strict_types=1);

use App\Services\Investment\MonteCarloSimulator;
use App\Services\Shared\MonteCarloEngine;

beforeEach(function () {
    $this->simulator = new MonteCarloSimulator;
});

describe('inheritance', function () {
    it('extends MonteCarloEngine', function () {
        expect($this->simulator)->toBeInstanceOf(MonteCarloEngine::class);
    });

    it('inherits calculateGoalProbability from engine', function () {
        $values = [100.0, 200.0, 300.0];
        expect($this->simulator->calculateGoalProbability($values, 200))->toBe(66.67);
    });

    it('inherits calculatePercentiles from engine', function () {
        $values = array_map('floatval', range(1, 100));
        $percentiles = $this->simulator->calculatePercentiles($values);
        expect($percentiles)->toHaveCount(5);
        expect($percentiles[0]['percentile'])->toBe('10th');
    });

    it('inherits generateNormal from engine', function () {
        $values = [];
        for ($i = 0; $i < 500; $i++) {
            $values[] = $this->simulator->generateNormal(50.0, 5.0);
        }
        $mean = array_sum($values) / count($values);
        expect($mean)->toBeGreaterThan(45.0)->toBeLessThan(55.0);
    });
});

describe('simulate', function () {
    it('runs Monte Carlo simulation with correct structure', function () {
        $results = $this->simulator->simulate(
            startValue: 100000,
            monthlyContribution: 500,
            expectedReturn: 0.07,
            volatility: 0.12,
            years: 10,
            iterations: 100
        );

        expect($results)->toHaveKeys(['summary', 'year_by_year', 'iterations'])
            ->and($results['summary'])->toHaveKeys([
                'start_value',
                'monthly_contribution',
                'expected_return',
                'volatility',
                'years',
                'iterations',
            ])
            ->and($results['year_by_year'])->toHaveCount(10);
    });

    it('includes final_percentiles and total_contributions', function () {
        $results = $this->simulator->simulate(
            startValue: 100000,
            monthlyContribution: 500,
            expectedReturn: 0.07,
            volatility: 0.12,
            years: 5,
            iterations: 50
        );

        expect($results)->toHaveKeys(['final_percentiles', 'total_contributions', 'median_gain']);
        expect($results['final_percentiles'])->toHaveCount(5);
        expect($results['total_contributions'])->toBe(130000.00); // 100000 + 500*60
    });

    it('calculates percentiles correctly', function () {
        $results = $this->simulator->simulate(
            startValue: 100000,
            monthlyContribution: 1000,
            expectedReturn: 0.07,
            volatility: 0.12,
            years: 5,
            iterations: 1000
        );

        $finalYear = $results['year_by_year'][4]; // Year 5 (0-indexed)

        expect($finalYear['year'])->toBe(5)
            ->and($finalYear['percentiles'])->toHaveCount(5)
            ->and($finalYear['percentiles'][0])->toHaveKeys(['percentile', 'value', 'final_value'])
            ->and($finalYear['percentiles'][0]['percentile'])->toBe('10th')
            ->and($finalYear['percentiles'][4]['percentile'])->toBe('90th');
    });

    it('includes final_value key in percentiles', function () {
        $results = $this->simulator->simulate(
            startValue: 50000,
            monthlyContribution: 200,
            expectedReturn: 0.05,
            volatility: 0.10,
            years: 3,
            iterations: 50
        );

        foreach ($results['year_by_year'] as $yearData) {
            foreach ($yearData['percentiles'] as $p) {
                expect($p)->toHaveKeys(['percentile', 'value', 'final_value']);
                expect($p['final_value'])->toBe($p['value']);
            }
        }

        foreach ($results['final_percentiles'] as $p) {
            expect($p)->toHaveKeys(['percentile', 'value', 'final_value']);
            expect($p['final_value'])->toBe($p['value']);
        }
    });

    it('shows increasing value ranges over time with positive returns', function () {
        $results = $this->simulator->simulate(
            startValue: 100000,
            monthlyContribution: 500,
            expectedReturn: 0.07,
            volatility: 0.10,
            years: 20,
            iterations: 1000
        );

        $year5Median = $results['year_by_year'][4]['percentiles'][2]['value'];
        $year10Median = $results['year_by_year'][9]['percentiles'][2]['value'];
        $year20Median = $results['year_by_year'][19]['percentiles'][2]['value'];

        expect($year10Median)->toBeGreaterThan($year5Median)
            ->and($year20Median)->toBeGreaterThan($year10Median);
    });

    it('respects monthly contributions in projections', function () {
        $withoutContributions = $this->simulator->simulate(
            startValue: 100000,
            monthlyContribution: 0,
            expectedReturn: 0.05,
            volatility: 0.10,
            years: 10,
            iterations: 100
        );

        $withContributions = $this->simulator->simulate(
            startValue: 100000,
            monthlyContribution: 1000,
            expectedReturn: 0.05,
            volatility: 0.10,
            years: 10,
            iterations: 100
        );

        $medianWithout = $withoutContributions['year_by_year'][9]['percentiles'][2]['value'];
        $medianWith = $withContributions['year_by_year'][9]['percentiles'][2]['value'];

        expect($medianWith)->toBeGreaterThan($medianWithout);
    });

    it('handles zero starting value correctly', function () {
        $results = $this->simulator->simulate(
            startValue: 0,
            monthlyContribution: 500,
            expectedReturn: 0.07,
            volatility: 0.12,
            years: 10,
            iterations: 100
        );

        expect($results['summary']['start_value'])->toBe(0.0)
            ->and($results['year_by_year'][9]['percentiles'][2]['value'])->toBeGreaterThan(0);
    });

    it('applies scheduled injections at year boundaries', function () {
        // Simulate without injections
        $without = $this->simulator->simulate(
            startValue: 100000,
            monthlyContribution: 0,
            expectedReturn: 0.0,
            volatility: 0.0,
            years: 3,
            iterations: 10
        );

        // Simulate with a £50,000 injection at year 2
        $with = $this->simulator->simulate(
            startValue: 100000,
            monthlyContribution: 0,
            expectedReturn: 0.0,
            volatility: 0.0,
            years: 3,
            iterations: 10,
            scheduledInjections: [2 => 50000]
        );

        // With zero return and zero volatility, values are deterministic
        $withoutMedian = $without['year_by_year'][2]['percentiles'][2]['value'];
        $withMedian = $with['year_by_year'][2]['percentiles'][2]['value'];

        expect($withMedian)->toBeGreaterThan($withoutMedian);
        // The injection should add exactly 50000
        expect($withMedian - $withoutMedian)->toBe(50000.0);
    });
});

describe('generateNormalDistribution', function () {
    it('generates values around the mean', function () {
        $values = [];
        for ($i = 0; $i < 1000; $i++) {
            $values[] = $this->simulator->generateNormalDistribution(100, 10);
        }

        $mean = array_sum($values) / count($values);

        // Mean should be close to 100 (within 5% tolerance)
        expect($mean)->toBeGreaterThan(95)
            ->and($mean)->toBeLessThan(105);
    });

    it('respects standard deviation parameter', function () {
        $lowVolatility = [];
        $highVolatility = [];

        for ($i = 0; $i < 1000; $i++) {
            $lowVolatility[] = $this->simulator->generateNormalDistribution(100, 5);
            $highVolatility[] = $this->simulator->generateNormalDistribution(100, 20);
        }

        $lowStdDev = sqrt(array_sum(array_map(fn ($x) => pow($x - 100, 2), $lowVolatility)) / count($lowVolatility));
        $highStdDev = sqrt(array_sum(array_map(fn ($x) => pow($x - 100, 2), $highVolatility)) / count($highVolatility));

        expect($highStdDev)->toBeGreaterThan($lowStdDev);
    });

    it('is equivalent to inherited generateNormal', function () {
        // Both methods should use the same Box-Muller implementation
        // Test by checking they produce valid distributions
        $valuesA = [];
        $valuesB = [];

        for ($i = 0; $i < 500; $i++) {
            $valuesA[] = $this->simulator->generateNormalDistribution(0, 1);
            $valuesB[] = $this->simulator->generateNormal(0, 1);
        }

        $meanA = array_sum($valuesA) / count($valuesA);
        $meanB = array_sum($valuesB) / count($valuesB);

        // Both should be close to 0
        expect($meanA)->toBeGreaterThan(-0.5)->toBeLessThan(0.5);
        expect($meanB)->toBeGreaterThan(-0.5)->toBeLessThan(0.5);
    });
});

describe('calculateGoalProbability', function () {
    it('calculates probability of reaching goal', function () {
        $finalValues = [90000, 110000, 120000, 105000, 95000, 130000, 115000, 98000, 125000, 108000];
        $goalAmount = 100000;

        $probability = $this->simulator->calculateGoalProbability($finalValues, $goalAmount);

        // 7 out of 10 values are >= 100000, so probability should be 70%
        expect($probability)->toBe(70.0);
    });

    it('returns 100% when all simulations exceed goal', function () {
        $finalValues = [110000, 120000, 115000, 125000, 130000];
        $goalAmount = 100000;

        $probability = $this->simulator->calculateGoalProbability($finalValues, $goalAmount);

        expect($probability)->toBe(100.0);
    });

    it('returns 0% when no simulations reach goal', function () {
        $finalValues = [50000, 60000, 55000, 65000, 58000];
        $goalAmount = 100000;

        $probability = $this->simulator->calculateGoalProbability($finalValues, $goalAmount);

        expect($probability)->toBe(0.0);
    });

    it('returns 0% for empty array', function () {
        $finalValues = [];
        $goalAmount = 100000;

        $probability = $this->simulator->calculateGoalProbability($finalValues, $goalAmount);

        expect($probability)->toBe(0.0);
    });
});

describe('performance and edge cases', function () {
    it('completes simulation with maximum iterations in reasonable time', function () {
        $start = microtime(true);

        $this->simulator->simulate(
            startValue: 50000,
            monthlyContribution: 1000,
            expectedReturn: 0.07,
            volatility: 0.15,
            years: 30,
            iterations: 10000
        );

        $duration = microtime(true) - $start;

        // Should complete in under 10 seconds
        expect($duration)->toBeLessThan(10);
    });

    it('handles high volatility correctly', function () {
        $results = $this->simulator->simulate(
            startValue: 100000,
            monthlyContribution: 500,
            expectedReturn: 0.10,
            volatility: 0.30, // Very high volatility
            years: 10,
            iterations: 1000
        );

        $finalYear = $results['year_by_year'][9];
        $range = $finalYear['percentiles'][4]['value'] - $finalYear['percentiles'][0]['value'];

        // High volatility should create wide range between 10th and 90th percentiles
        expect($range)->toBeGreaterThan(50000);
    });

    it('handles negative expected return', function () {
        $results = $this->simulator->simulate(
            startValue: 100000,
            monthlyContribution: 0,
            expectedReturn: -0.02, // -2% annual return
            volatility: 0.10,
            years: 5,
            iterations: 100
        );

        $finalMedian = $results['year_by_year'][4]['percentiles'][2]['value'];

        // Median should be less than starting value
        expect($finalMedian)->toBeLessThan(100000);
    });
});
