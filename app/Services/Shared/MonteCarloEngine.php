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
     * The percentiles every simulation reports by default.
     */
    public const SUMMARY_PERCENTILES = [10, 25, 50, 75, 90];

    /**
     * The percentiles a probability-band chart needs.
     *
     * Every one of these is measured from the simulated distribution. Callers that
     * render bands MUST request this set — a band that is not in the simulation's
     * output cannot be recovered afterwards, and interpolating between neighbours
     * produces a number the simulation never generated.
     */
    public const BAND_PERCENTILES = [5, 10, 15, 20, 25, 50, 75, 90];

    /**
     * Run a single-asset Monte Carlo simulation.
     *
     * @param  float  $startValue  Initial value
     * @param  float  $monthlyContribution  Monthly contribution amount
     * @param  float  $expectedReturn  Expected annual return (decimal, e.g. 0.07)
     * @param  float  $volatility  Annual volatility (decimal, e.g. 0.15)
     * @param  int  $years  Simulation horizon in years
     * @param  int  $iterations  Number of simulation runs
     * @param  string|null  $cacheKey  Unused by the base engine; subclasses may cache on it
     * @param  array  $scheduledInjections  Year-keyed cash flows, e.g. [2 => -55000]
     * @param  int[]|null  $percentilePoints  Percentiles to report; null = SUMMARY_PERCENTILES
     * @return array{final_values: float[], year_by_year: array, percentiles: array, summary: array}
     */
    public function simulate(
        float $startValue,
        float $monthlyContribution,
        float $expectedReturn,
        float $volatility,
        int $years,
        int $iterations = 1000,
        ?string $cacheKey = null,
        array $scheduledInjections = [],
        ?array $percentilePoints = null
    ): array {
        return $this->runCoreSimulation(
            $startValue,
            $monthlyContribution,
            $expectedReturn,
            $volatility,
            $years,
            $iterations,
            $scheduledInjections,
            $percentilePoints
        );
    }

    /**
     * Core simulation logic shared by the base engine and subclasses.
     *
     * It once existed so subclasses could pass scheduled injections without changing
     * simulate()'s signature. That is no longer why: simulate() carries injections
     * itself and forwards them here. What this method is for now is that a subclass can
     * wrap the simulation — MonteCarloSimulator caches around it — while the maths
     * itself lives in exactly one place.
     *
     * `$cacheKey` is deliberately absent: caching is the subclass's concern, and the
     * base engine has no store to put anything in.
     *
     * @param  float  $startValue  Initial value
     * @param  float  $monthlyContribution  Monthly contribution amount
     * @param  float  $expectedReturn  Expected annual return (decimal, e.g. 0.07)
     * @param  float  $volatility  Annual volatility (decimal, e.g. 0.15)
     * @param  int  $years  Simulation horizon in years
     * @param  int  $iterations  Number of simulation runs
     * @param  array  $scheduledInjections  Optional year-indexed lump sum injections
     * @param  int[]|null  $percentilePoints  Percentiles to report; null = SUMMARY_PERCENTILES
     * @return array{final_values: float[], year_by_year: array, percentiles: array, summary: array}
     */
    protected function runCoreSimulation(
        float $startValue,
        float $monthlyContribution,
        float $expectedReturn,
        float $volatility,
        int $years,
        int $iterations = 1000,
        array $scheduledInjections = [],
        ?array $percentilePoints = null
    ): array {
        $monthlyReturn = $expectedReturn / 12;
        $monthlyVolatility = $volatility / sqrt(12);
        $totalMonths = $years * 12;

        // Seed from the inputs so one set of inputs has one answer.
        //
        // Without this, the same capital simulated twice — an account and the
        // single-account portfolio holding it, or the same projection either side of a
        // cache expiry — returns two different figures for the same money. The user
        // cannot tell that apart from something having changed. Sampling error is real
        // and is what the percentile bands express; it must not also move the answer
        // when nothing about the person has moved.
        $this->seedFromInputs([
            $startValue,
            $monthlyContribution,
            $expectedReturn,
            $volatility,
            $years,
            $iterations,
            $scheduledInjections,
        ]);

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

        // Hand the global generator back unpredictable, so nothing outside this
        // simulation inherits its seed.
        mt_srand();

        sort($finalValues);

        $yearByYear = [];
        for ($year = 1; $year <= $years; $year++) {
            $yearValues = array_map(fn ($r) => $r[$year - 1], $yearlyResults);
            sort($yearValues);
            $yearByYear[] = [
                'year' => $year,
                'percentiles' => $this->calculatePercentiles($yearValues, $percentilePoints),
            ];
        }

        $totalContributions = $startValue + ($monthlyContribution * $totalMonths);
        $median = $this->getPercentileValue($finalValues, 50);

        return [
            'final_values' => $finalValues,
            'year_by_year' => $yearByYear,
            'percentiles' => $this->calculatePercentiles($finalValues, $percentilePoints),
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
     * Seed the generator from the simulation's inputs.
     *
     * Same inputs, same sample, same answer — on every surface, in every module, and
     * whether or not a cached result happened to be available.
     */
    protected function seedFromInputs(array $inputs): void
    {
        // A year-keyed map built in a different order describes the same simulation.
        $inputs = array_map(static function ($value) {
            if (is_array($value)) {
                ksort($value);
            }

            return $value;
        }, $inputs);

        mt_srand((int) hexdec(substr(md5((string) json_encode($inputs)), 0, 7)));
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
     * @param  int[]|null  $points  Percentiles to report; null = SUMMARY_PERCENTILES
     * @return array Array of percentile objects, one per requested point
     */
    public function calculatePercentiles(array $sortedValues, ?array $points = null): array
    {
        $points = $points ?: self::SUMMARY_PERCENTILES;
        $count = count($sortedValues);

        if ($count === 0) {
            return array_map(fn ($p) => ['percentile' => "{$p}th", 'value' => 0.0], $points);
        }

        $percentiles = [];
        foreach ($points as $p) {
            $index = max(0, min((int) ceil(($p / 100) * $count) - 1, $count - 1));
            $percentiles[] = [
                'percentile' => "{$p}th",
                'value' => round($sortedValues[$index], 2),
            ];
        }

        return $percentiles;
    }

    /**
     * Reshape a simulation into the year-by-year probability bands a chart renders.
     *
     * This is the ONE home for that reshape. Every band it emits is a percentile the
     * simulation actually measured: nothing is interpolated between two neighbours,
     * nothing is extrapolated past the ends of the distribution, and no year is pulled
     * toward the start value to flatten the early curve. A figure a user reads off this
     * chart is therefore derivable from the simulation behind it.
     *
     * Requires the simulation to have been run with BAND_PERCENTILES. A band the
     * simulation did not measure is absent from the output rather than invented.
     *
     * @param  array  $simulation  Engine or investment-format simulation result
     * @return array Year 0 (the start value) followed by one row per simulated year
     */
    public function extractProbabilityBands(array $simulation): array
    {
        $startValue = (float) ($simulation['summary']['start_value'] ?? 0);
        $currentYear = (int) date('Y');

        $anchor = ['year' => $currentYear, 'year_number' => 0];
        foreach (self::BAND_PERCENTILES as $p) {
            $anchor["percentile_{$p}"] = round($startValue, 2);
        }

        $result = [$anchor];

        foreach ($simulation['year_by_year'] ?? [] as $yearData) {
            $yearIndex = (int) $yearData['year'];
            $measured = $this->indexPercentiles($yearData['percentiles'] ?? []);

            $row = ['year' => $currentYear + $yearIndex, 'year_number' => $yearIndex];
            foreach (self::BAND_PERCENTILES as $p) {
                if (array_key_exists($p, $measured)) {
                    $row["percentile_{$p}"] = round($measured[$p], 2);
                }
            }

            $result[] = $row;
        }

        return $result;
    }

    /**
     * Index a percentile list ("10th" => value) by its integer point.
     *
     * @param  array  $percentiles  List of ['percentile' => '10th', 'value' => float]
     * @return array<int, float>
     */
    private function indexPercentiles(array $percentiles): array
    {
        $indexed = [];

        foreach ($percentiles as $p) {
            if (! isset($p['percentile'])) {
                continue;
            }
            $indexed[(int) $p['percentile']] = (float) ($p['value'] ?? 0);
        }

        return $indexed;
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
