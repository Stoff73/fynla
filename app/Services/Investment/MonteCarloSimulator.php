<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Services\Investment\Utilities\MatrixOperations;
use App\Services\Shared\MonteCarloEngine;
use Illuminate\Support\Facades\DB;

/**
 * Investment-specific Monte Carlo simulator.
 *
 * Extends the shared MonteCarloEngine with:
 * - Database-backed result caching (24-hour TTL)
 * - Scheduled lump-sum injections at year boundaries
 * - Multi-asset correlated simulation (Cholesky decomposition)
 *
 * All core simulation math (random number generation, percentile
 * calculation, goal probability) is inherited from MonteCarloEngine.
 */
class MonteCarloSimulator extends MonteCarloEngine
{
    /**
     * Cache TTL in hours (24 hours)
     */
    private const CACHE_TTL_HOURS = 24;

    /**
     * Run Monte Carlo simulation with optional caching and scheduled injections.
     *
     * Delegates core simulation math to MonteCarloEngine::runCoreSimulation(),
     * then reshapes the output to the investment module format and adds caching.
     *
     * @param  float  $startValue  Initial portfolio value
     * @param  float  $monthlyContribution  Monthly contribution amount
     * @param  float  $expectedReturn  Expected annual return (e.g., 0.07 for 7%)
     * @param  float  $volatility  Annual volatility/std deviation (e.g., 0.15 for 15%)
     * @param  int  $years  Number of years to simulate
     * @param  int  $iterations  Number of simulation runs (default 1000)
     * @param  string|null  $cacheKey  Optional cache key for 24-hour caching
     * @param  array  $scheduledInjections  Optional year-indexed lump sum injections
     * @return array Simulation results with percentiles
     */
    public function simulate(
        float $startValue,
        float $monthlyContribution,
        float $expectedReturn,
        float $volatility,
        int $years,
        int $iterations = 1000,
        ?string $cacheKey = null,
        array $scheduledInjections = []
    ): array {
        // Check cache if key provided
        if ($cacheKey !== null) {
            $cached = $this->getCachedResult($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Run simulation via parent engine core (handles scheduled injections)
        $engineResult = $this->runCoreSimulation(
            $startValue,
            $monthlyContribution,
            $expectedReturn,
            $volatility,
            $years,
            $iterations,
            $scheduledInjections
        );

        // Reshape to the investment module format that all consumers expect
        $results = $this->reshapeToInvestmentFormat($engineResult, $expectedReturn, $volatility);

        // Store in cache if key provided
        if ($cacheKey !== null) {
            $this->cacheResult($cacheKey, $results);
        }

        return $results;
    }

    /**
     * Reshape engine results to the investment module format.
     *
     * Engine format:  {final_values, year_by_year, percentiles, summary}
     * Investment format: {summary, year_by_year, iterations, final_percentiles, total_contributions, median_gain}
     */
    private function reshapeToInvestmentFormat(array $engineResult, float $expectedReturn, float $volatility): array
    {
        // Rebuild percentiles with the extra 'final_value' key that investment consumers expect
        $finalPercentiles = [];
        foreach ($engineResult['percentiles'] as $p) {
            $finalPercentiles[] = [
                'percentile' => $p['percentile'],
                'value' => $p['value'],
                'final_value' => $p['value'],
            ];
        }

        // Rebuild year_by_year percentiles with 'final_value' key
        $yearByYear = [];
        foreach ($engineResult['year_by_year'] as $yearData) {
            $percentiles = [];
            foreach ($yearData['percentiles'] as $p) {
                $percentiles[] = [
                    'percentile' => $p['percentile'],
                    'value' => $p['value'],
                    'final_value' => $p['value'],
                ];
            }
            $yearByYear[] = [
                'year' => $yearData['year'],
                'percentiles' => $percentiles,
            ];
        }

        return [
            'summary' => [
                'start_value' => $engineResult['summary']['start_value'],
                'monthly_contribution' => $engineResult['summary']['monthly_contribution'],
                'years' => $engineResult['summary']['years'],
                'iterations' => $engineResult['summary']['iterations'],
                'expected_return' => $expectedReturn,
                'volatility' => $volatility,
            ],
            'year_by_year' => $yearByYear,
            'iterations' => $engineResult['summary']['iterations'],
            'final_percentiles' => $finalPercentiles,
            'total_contributions' => $engineResult['summary']['total_contributions'],
            'median_gain' => $engineResult['summary']['median_gain'],
        ];
    }

    /**
     * Get cached result if valid (not expired)
     */
    private function getCachedResult(string $cacheKey): ?array
    {
        try {
            $cached = DB::table('monte_carlo_cache')
                ->where('cache_key', $cacheKey)
                ->where('expires_at', '>', now())
                ->first();

            if ($cached) {
                return json_decode($cached->results, true);
            }
        } catch (\Throwable $e) {
            // Log but don't fail if cache table doesn't exist yet
            \Log::warning('Monte Carlo cache read failed: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Store result in cache
     */
    private function cacheResult(string $cacheKey, array $results): void
    {
        try {
            DB::table('monte_carlo_cache')->updateOrInsert(
                ['cache_key' => $cacheKey],
                [
                    'results' => json_encode($results),
                    'calculated_at' => now(),
                    'expires_at' => now()->addHours(self::CACHE_TTL_HOURS),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            // Log but don't fail if cache table doesn't exist yet
            \Log::warning('Monte Carlo cache write failed: '.$e->getMessage());
        }
    }

    /**
     * Clear cache for a specific key or all expired entries
     */
    public function clearCache(?string $cacheKey = null): void
    {
        try {
            if ($cacheKey !== null) {
                DB::table('monte_carlo_cache')->where('cache_key', $cacheKey)->delete();
            } else {
                // Clear all expired entries
                DB::table('monte_carlo_cache')->where('expires_at', '<', now())->delete();
            }
        } catch (\Throwable $e) {
            \Log::warning('Monte Carlo cache clear failed: '.$e->getMessage());
        }
    }

    /**
     * Clear all cache entries for a user (e.g., when data changes)
     */
    public function clearUserCache(int $userId): void
    {
        try {
            DB::table('monte_carlo_cache')
                ->where('cache_key', 'like', "user_{$userId}_%")
                ->delete();
        } catch (\Throwable $e) {
            \Log::warning('Monte Carlo user cache clear failed: '.$e->getMessage());
        }
    }

    /**
     * Run multi-asset Monte Carlo simulation with correlated returns.
     *
     * Uses Cholesky decomposition of the covariance matrix to generate
     * correlated random returns across multiple asset classes.
     *
     * @param  array  $assetClasses  Array of ['type', 'weight', 'expectedReturn', 'volatility']
     * @param  array  $correlationMatrix  N x N correlation matrix between asset classes
     * @param  float  $startValue  Initial portfolio value
     * @param  float  $monthlyContribution  Monthly contribution
     * @param  int  $years  Simulation horizon
     * @param  int  $iterations  Number of simulation runs
     * @param  string|null  $cacheKey  Optional cache key
     * @param  array  $scheduledInjections  Optional year-indexed lump sum injections
     * @return array Simulation results with percentiles
     */
    public function runMultiAssetSimulation(
        array $assetClasses,
        array $correlationMatrix,
        float $startValue,
        float $monthlyContribution,
        int $years,
        int $iterations = 1000,
        ?string $cacheKey = null,
        array $scheduledInjections = []
    ): array {
        // Check cache
        if ($cacheKey !== null) {
            $cached = $this->getCachedResult($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $matrixOps = new MatrixOperations;
        $n = count($assetClasses);
        $totalMonths = $years * 12;

        // Build covariance matrix from correlation matrix and volatilities
        $covarianceMatrix = $this->buildCovarianceMatrix($assetClasses, $correlationMatrix);

        // Cholesky decomposition for generating correlated samples
        $choleskyL = $matrixOps->choleskyDecomposition($covarianceMatrix);

        // Convert annual parameters to monthly
        $monthlyReturns = array_map(fn ($ac) => $ac['expectedReturn'] / 12, $assetClasses);
        $weights = array_map(fn ($ac) => $ac['weight'], $assetClasses);

        $finalValues = [];
        $yearlyResults = [];

        for ($i = 0; $i < $iterations; $i++) {
            $portfolioValue = $startValue;
            $yearlyValues = [];

            for ($month = 1; $month <= $totalMonths; $month++) {
                // Generate independent standard normal samples using inherited method
                $independentSamples = [];
                for ($a = 0; $a < $n; $a++) {
                    $independentSamples[] = $this->generateNormal(0, 1);
                }

                // Transform to correlated samples using Cholesky: correlated = L * independent
                $correlatedSamples = $matrixOps->multiplyVector($choleskyL, $independentSamples);

                // Calculate weighted portfolio return for this month
                $portfolioReturn = 0.0;
                for ($a = 0; $a < $n; $a++) {
                    $monthlyVol = $assetClasses[$a]['volatility'] / sqrt(12);
                    $assetReturn = $monthlyReturns[$a] + ($correlatedSamples[$a] * $monthlyVol);
                    $portfolioReturn += $weights[$a] * $assetReturn;
                }

                $portfolioValue = $portfolioValue * (1 + $portfolioReturn) + $monthlyContribution;

                if ($month % 12 === 0) {
                    $portfolioValue = $this->applyScheduledInjection($portfolioValue, (int) ($month / 12), $scheduledInjections);
                    $yearlyValues[] = $portfolioValue;
                }
            }

            $finalValues[] = $portfolioValue;
            $yearlyResults[] = $yearlyValues;
        }

        sort($finalValues);

        // Build year-by-year percentiles with 'final_value' key
        $yearByYear = [];
        for ($year = 1; $year <= $years; $year++) {
            $yearIndex = $year - 1;
            $yearValues = array_map(fn ($r) => $r[$yearIndex], $yearlyResults);
            sort($yearValues);

            $percentiles = [];
            foreach ($this->calculatePercentiles($yearValues) as $p) {
                $percentiles[] = [
                    'percentile' => $p['percentile'],
                    'value' => $p['value'],
                    'final_value' => $p['value'],
                ];
            }

            $yearByYear[] = [
                'year' => $year,
                'percentiles' => $percentiles,
            ];
        }

        // Build final percentiles with 'final_value' key
        $finalPercentiles = [];
        foreach ($this->calculatePercentiles($finalValues) as $p) {
            $finalPercentiles[] = [
                'percentile' => $p['percentile'],
                'value' => $p['value'],
                'final_value' => $p['value'],
            ];
        }

        $totalContributions = $startValue + ($monthlyContribution * $totalMonths);
        $medianValue = $finalPercentiles[2]['value'] ?? 0;

        $output = [
            'summary' => [
                'start_value' => round($startValue, 2),
                'monthly_contribution' => round($monthlyContribution, 2),
                'years' => $years,
                'iterations' => $iterations,
            ],
            'year_by_year' => $yearByYear,
            'iterations' => $iterations,
            'final_percentiles' => $finalPercentiles,
            'total_contributions' => round($totalContributions, 2),
            'median_gain' => round($medianValue - $totalContributions, 2),
        ];

        if ($cacheKey !== null) {
            $this->cacheResult($cacheKey, $output);
        }

        return $output;
    }

    /**
     * Build covariance matrix from asset class volatilities and correlation matrix.
     *
     * Cov(i,j) = correlation(i,j) * vol(i) * vol(j)
     */
    private function buildCovarianceMatrix(array $assetClasses, array $correlationMatrix): array
    {
        $n = count($assetClasses);
        $cov = [];

        for ($i = 0; $i < $n; $i++) {
            $cov[$i] = [];
            for ($j = 0; $j < $n; $j++) {
                $cov[$i][$j] = $correlationMatrix[$i][$j]
                    * $assetClasses[$i]['volatility']
                    * $assetClasses[$j]['volatility'];
            }
        }

        return $cov;
    }

    /**
     * Get default correlation matrix for common asset classes.
     *
     * @return array Correlation matrix for [equity, bond, cash]
     */
    public static function getDefaultCorrelationMatrix(): array
    {
        return [
            // equity, bond,  cash
            [1.00, -0.20, 0.05],  // equity
            [-0.20, 1.00, 0.15],  // bond
            [0.05, 0.15, 1.00],   // cash
        ];
    }

    /**
     * Generate random number from normal distribution.
     *
     * Alias for parent's generateNormal() to maintain backward compatibility
     * with any code calling generateNormalDistribution() directly.
     */
    public function generateNormalDistribution(float $mean, float $stdDev): float
    {
        return $this->generateNormal($mean, $stdDev);
    }
}
