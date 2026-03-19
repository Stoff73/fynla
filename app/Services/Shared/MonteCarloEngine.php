<?php

declare(strict_types=1);

namespace App\Services\Shared;

/**
 * Shared Monte Carlo simulation engine.
 *
 * Provides core simulation primitives for any module needing
 * stochastic projections (investment, goals, retirement).
 *
 * This is the canonical Monte Carlo implementation. The Investment
 * module's MonteCarloSimulator extends this class to add caching,
 * scheduled injections, and multi-asset correlation features.
 */
class MonteCarloEngine
{
    /**
     * Run a single-asset Monte Carlo simulation.
     *
     * @param  float  $startValue  Initial value
     * @param  float  $monthlyContribution  Monthly contribution amount
     * @param  float  $expectedReturn  Expected annual return (decimal, e.g. 0.07)
     * @param  float  $volatility  Annual volatility (decimal, e.g. 0.15)
     * @param  int  $years  Simulation horizon in years
     * @param  int  $iterations  Number of simulation runs
     * @return array{final_values: float[], year_by_year: array, percentiles: array, summary: array}
     */
    public function simulate(
        float $startValue,
        float $monthlyContribution,
        float $expectedReturn,
        float $volatility,
        int $years,
        int $iterations = 1000
    ): array {
        return $this->runCoreSimulation(
            $startValue,
            $monthlyContribution,
            $expectedReturn,
            $volatility,
            $years,
            $iterations
        );
    }

    /**
     * Core simulation logic shared by the base engine and subclasses.
     *
     * Extracted to allow subclasses to call this with scheduled injections
     * without changing the public simulate() signature.
     *
     * @param  float  $startValue  Initial value
     * @param  float  $monthlyContribution  Monthly contribution amount
     * @param  float  $expectedReturn  Expected annual return (decimal, e.g. 0.07)
     * @param  float  $volatility  Annual volatility (decimal, e.g. 0.15)
     * @param  int  $years  Simulation horizon in years
     * @param  int  $iterations  Number of simulation runs
     * @param  array  $scheduledInjections  Optional year-indexed lump sum injections
     * @return array{final_values: float[], year_by_year: array, percentiles: array, summary: array}
     */
    protected function runCoreSimulation(
        float $startValue,
        float $monthlyContribution,
        float $expectedReturn,
        float $volatility,
        int $years,
        int $iterations = 1000,
        array $scheduledInjections = []
    ): array {
        $monthlyReturn = $expectedReturn / 12;
        $monthlyVolatility = $volatility / sqrt(12);
        $totalMonths = $years * 12;

        $finalValues = [];
        $yearlyResults = [];

        for ($i = 0; $i < $iterations; $i++) {
            $value = $startValue;
            $yearlyValues = [];

            for ($month = 1; $month <= $totalMonths; $month++) {
                $randomReturn = $this->generateNormal($monthlyReturn, $monthlyVolatility);
                $value = $value * (1 + $randomReturn) + $monthlyContribution;

                if ($month % 12 === 0) {
                    $value = $this->applyScheduledInjection($value, (int) ($month / 12), $scheduledInjections);
                    $yearlyValues[] = $value;
                }
            }

            $finalValues[] = $value;
            $yearlyResults[] = $yearlyValues;
        }

        sort($finalValues);

        $yearByYear = [];
        for ($year = 1; $year <= $years; $year++) {
            $yearValues = array_map(fn ($r) => $r[$year - 1], $yearlyResults);
            sort($yearValues);
            $yearByYear[] = [
                'year' => $year,
                'percentiles' => $this->calculatePercentiles($yearValues),
            ];
        }

        $totalContributions = $startValue + ($monthlyContribution * $totalMonths);
        $median = $this->getPercentileValue($finalValues, 50);

        return [
            'final_values' => $finalValues,
            'year_by_year' => $yearByYear,
            'percentiles' => $this->calculatePercentiles($finalValues),
            'summary' => [
                'start_value' => round($startValue, 2),
                'monthly_contribution' => round($monthlyContribution, 2),
                'years' => $years,
                'iterations' => $iterations,
                'expected_return' => $expectedReturn,
                'volatility' => $volatility,
                'total_contributions' => round($totalContributions, 2),
                'median_final_value' => round($median, 2),
                'median_gain' => round($median - $totalContributions, 2),
            ],
        ];
    }

    /**
     * Apply a scheduled injection at a given simulation year boundary.
     */
    protected function applyScheduledInjection(float $portfolioValue, int $currentYear, array $scheduledInjections): float
    {
        if (isset($scheduledInjections[$currentYear])) {
            $portfolioValue += $scheduledInjections[$currentYear];
            $portfolioValue = max(0.0, $portfolioValue);
        }

        return $portfolioValue;
    }

    /**
     * Calculate the probability of reaching a target amount.
     *
     * @param  float[]  $finalValues  Array of simulation final values (need not be sorted)
     * @param  float  $targetAmount  Target to reach
     * @return float Probability as percentage (0-100)
     */
    public function calculateGoalProbability(array $finalValues, float $targetAmount): float
    {
        if (empty($finalValues)) {
            return 0.0;
        }

        $successCount = count(array_filter($finalValues, fn ($v) => $v >= $targetAmount));

        return round(($successCount / count($finalValues)) * 100, 2);
    }

    /**
     * Calculate percentiles from a sorted array of values.
     *
     * @param  float[]  $sortedValues  Pre-sorted values
     * @return array Array of percentile objects with 10th, 25th, 50th, 75th, 90th
     */
    public function calculatePercentiles(array $sortedValues): array
    {
        $count = count($sortedValues);

        if ($count === 0) {
            return array_map(fn ($p) => ['percentile' => "{$p}th", 'value' => 0.0], [10, 25, 50, 75, 90]);
        }

        $percentiles = [];
        foreach ([10, 25, 50, 75, 90] as $p) {
            $index = max(0, min((int) ceil(($p / 100) * $count) - 1, $count - 1));
            $percentiles[] = [
                'percentile' => "{$p}th",
                'value' => round($sortedValues[$index], 2),
            ];
        }

        return $percentiles;
    }

    /**
     * Get a single percentile value from a sorted array.
     *
     * @param  float[]  $sortedValues  Pre-sorted values
     * @param  int  $percentile  Percentile (0-100)
     */
    public function getPercentileValue(array $sortedValues, int $percentile): float
    {
        $count = count($sortedValues);
        if ($count === 0) {
            return 0.0;
        }

        $index = max(0, min((int) ceil(($percentile / 100) * $count) - 1, $count - 1));

        return $sortedValues[$index];
    }

    /**
     * Generate a random number from a normal distribution (Box-Muller transform).
     */
    public function generateNormal(float $mean, float $stdDev): float
    {
        $u1 = max(mt_rand() / mt_getrandmax(), 1e-10);
        $u2 = mt_rand() / mt_getrandmax();
        $z0 = sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);

        return $mean + ($z0 * $stdDev);
    }
}
