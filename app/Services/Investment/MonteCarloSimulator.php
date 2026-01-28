<?php

declare(strict_types=1);

namespace App\Services\Investment;

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
        ?string $cacheKey = null
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
            $iterations
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
            \Log::warning("Monte Carlo cache read failed: " . $e->getMessage());
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
            \Log::warning("Monte Carlo cache write failed: " . $e->getMessage());
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
            \Log::warning("Monte Carlo cache clear failed: " . $e->getMessage());
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
            \Log::warning("Monte Carlo user cache clear failed: " . $e->getMessage());
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
        int $iterations
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

                // Store value at end of each year
                if ($month % 12 == 0) {
                    $yearlyValues[] = $portfolioValue;
                }
            }

            $results[] = [
                'final_value' => $portfolioValue,
                'yearly_values' => $yearlyValues,
            ];
        }

        // Calculate statistics
        $finalValues = array_column($results, 'final_value');
        sort($finalValues);

        $percentiles = $this->calculatePercentiles($finalValues);

        // Calculate year-by-year percentiles
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

        $medianValue = $percentiles[2]['value'] ?? 0; // 50th percentile is index 2
        $totalContributions = $startValue + ($monthlyContribution * $totalMonths);

        return [
            'summary' => [
                'start_value' => round($startValue, 2),
                'monthly_contribution' => round($monthlyContribution, 2),
                'expected_return' => $expectedReturn,
                'volatility' => $volatility,
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
