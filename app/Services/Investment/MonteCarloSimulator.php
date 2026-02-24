<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Services\Investment\Utilities\MatrixOperations;
use Illuminate\Support\Facades\DB;

class MonteCarloSimulator
{
    /**
     * Cache TTL in hours (24 hours)
     */
    private const CACHE_TTL_HOURS = 24;

    /**
     * Run Monte Carlo simulation with optional caching
     *
     * @param  float  $startValue  Initial portfolio value
     * @param  float  $monthlyContribution  Monthly contribution amount
     * @param  float  $expectedReturn  Expected annual return (e.g., 0.07 for 7%)
     * @param  float  $volatility  Annual volatility/std deviation (e.g., 0.15 for 15%)
     * @param  int  $years  Number of years to simulate
     * @param  int  $iterations  Number of simulation runs (default 1000)
     * @param  string|null  $cacheKey  Optional cache key for 24-hour caching
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

        // Run the actual simulation
        $results = $this->runSimulation(
            $startValue,
            $monthlyContribution,
            $expectedReturn,
            $volatility,
            $years,
            $iterations,
            $scheduledInjections
        );

        // Store in cache if key provided
        if ($cacheKey !== null) {
            $this->cacheResult($cacheKey, $results);
        }

        return $results;
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
     * Run the actual Monte Carlo simulation (internal method)
     */
    private function runSimulation(
        float $startValue,
        float $monthlyContribution,
        float $expectedReturn,
        float $volatility,
        int $years,
        int $iterations,
        array $scheduledInjections = []
    ): array {
        $results = [];
        $monthlyReturn = $expectedReturn / 12;
        $monthlyVolatility = $volatility / sqrt(12);
        $totalMonths = $years * 12;

        // Run simulations
        for ($i = 0; $i < $iterations; $i++) {
            $portfolioValue = $startValue;
            $yearlyValues = [];

            for ($month = 1; $month <= $totalMonths; $month++) {
                // Generate random return using normal distribution
                $randomReturn = $this->generateNormalDistribution($monthlyReturn, $monthlyVolatility);

                // Apply return and add contribution
                $portfolioValue = $portfolioValue * (1 + $randomReturn) + $monthlyContribution;

                // Store value at end of each year and apply scheduled injections
                if ($month % 12 === 0) {
                    $portfolioValue = $this->applyScheduledInjection($portfolioValue, (int) ($month / 12), $scheduledInjections);
                    $yearlyValues[] = $portfolioValue;
                }
            }

            $results[] = [
                'final_value' => $portfolioValue,
                'yearly_values' => $yearlyValues,
            ];
        }

        $output = $this->aggregateResults($results, $startValue, $monthlyContribution, $years, $iterations);

        // Add single-asset-specific summary fields
        $output['summary']['expected_return'] = $expectedReturn;
        $output['summary']['volatility'] = $volatility;

        return $output;
    }

    /**
     * Run multi-asset Monte Carlo simulation with correlated returns.
     *
     * @param  array  $assetClasses  Array of ['type', 'weight', 'expectedReturn', 'volatility']
     * @param  array  $correlationMatrix  N x N correlation matrix between asset classes
     * @param  float  $startValue  Initial portfolio value
     * @param  float  $monthlyContribution  Monthly contribution
     * @param  int  $years  Simulation horizon
     * @param  int  $iterations  Number of simulation runs
     * @param  string|null  $cacheKey  Optional cache key
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

        $results = [];

        for ($i = 0; $i < $iterations; $i++) {
            $portfolioValue = $startValue;
            $yearlyValues = [];

            for ($month = 1; $month <= $totalMonths; $month++) {
                // Generate independent standard normal samples
                $independentSamples = [];
                for ($a = 0; $a < $n; $a++) {
                    $independentSamples[] = $this->generateNormalDistribution(0, 1);
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

            $results[] = [
                'final_value' => $portfolioValue,
                'yearly_values' => $yearlyValues,
            ];
        }

        $output = $this->aggregateResults($results, $startValue, $monthlyContribution, $years, $iterations);

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
     * Apply a scheduled injection at a given simulation year boundary.
     */
    private function applyScheduledInjection(float $portfolioValue, int $currentYear, array $scheduledInjections): float
    {
        if (isset($scheduledInjections[$currentYear])) {
            $portfolioValue += $scheduledInjections[$currentYear];
            $portfolioValue = max(0.0, $portfolioValue);
        }

        return $portfolioValue;
    }

    /**
     * Aggregate simulation results into percentile statistics.
     */
    private function aggregateResults(
        array $results,
        float $startValue,
        float $monthlyContribution,
        int $years,
        int $iterations
    ): array {
        $finalValues = array_column($results, 'final_value');
        sort($finalValues);

        $percentiles = $this->calculatePercentiles($finalValues);

        $yearByYearPercentiles = [];
        for ($year = 1; $year <= $years; $year++) {
            $yearIndex = $year - 1;
            $yearValues = array_map(fn ($r) => $r['yearly_values'][$yearIndex], $results);
            sort($yearValues);

            $yearByYearPercentiles[] = [
                'year' => $year,
                'percentiles' => $this->calculatePercentiles($yearValues),
            ];
        }

        $totalMonths = $years * 12;
        $medianValue = $percentiles[2]['value'] ?? 0;
        $totalContributions = $startValue + ($monthlyContribution * $totalMonths);

        return [
            'summary' => [
                'start_value' => round($startValue, 2),
                'monthly_contribution' => round($monthlyContribution, 2),
                'years' => $years,
                'iterations' => $iterations,
            ],
            'year_by_year' => $yearByYearPercentiles,
            'iterations' => $iterations,
            'final_percentiles' => $percentiles,
            'total_contributions' => round($totalContributions, 2),
            'median_gain' => round($medianValue - $totalContributions, 2),
        ];
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
     * Generate random number from normal distribution using Box-Muller transform
     */
    public function generateNormalDistribution(float $mean, float $stdDev): float
    {
        $u1 = mt_rand() / mt_getrandmax();
        $u2 = mt_rand() / mt_getrandmax();
        $u1 = max($u1, 1e-10);
        $z0 = sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);

        return $mean + ($z0 * $stdDev);
    }

    /**
     * Calculate percentiles from sorted array of values
     */
    public function calculatePercentiles(array $sortedValues): array
    {
        $count = count($sortedValues);

        if ($count == 0) {
            return [
                ['percentile' => '10th', 'value' => 0.0, 'final_value' => 0.0],
                ['percentile' => '25th', 'value' => 0.0, 'final_value' => 0.0],
                ['percentile' => '50th', 'value' => 0.0, 'final_value' => 0.0],
                ['percentile' => '75th', 'value' => 0.0, 'final_value' => 0.0],
                ['percentile' => '90th', 'value' => 0.0, 'final_value' => 0.0],
            ];
        }

        $percentiles = [];
        foreach ([10, 25, 50, 75, 90] as $p) {
            $index = (int) ceil(($p / 100) * $count) - 1;
            $index = max(0, min($index, $count - 1));
            $value = round($sortedValues[$index], 2);
            $percentiles[] = [
                'percentile' => "{$p}th",
                'value' => $value,
                'final_value' => $value,
            ];
        }

        return $percentiles;
    }

    /**
     * Calculate probability of reaching a goal
     */
    public function calculateGoalProbability(array $finalValues, float $goalAmount): float
    {
        if (empty($finalValues)) {
            return 0.0;
        }

        $successCount = count(array_filter($finalValues, fn ($v) => $v >= $goalAmount));

        return round(($successCount / count($finalValues)) * 100, 2);
    }
}
