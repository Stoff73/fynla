<?php

declare(strict_types=1);

namespace App\Services\Investment\Goals;

/**
 * Goal Probability Calculator
 * Calculates probability of reaching investment goals using Monte Carlo simulation
 *
 * Uses simplified Monte Carlo (without queue jobs) for goal analysis
 */
class GoalProbabilityCalculator
{
    /**
     * Calculate probability of reaching a goal
     *
     * @param  float  $currentValue  Current portfolio value
     * @param  float  $targetValue  Target goal value
     * @param  float  $monthlyContribution  Monthly contribution
     * @param  float  $expectedReturn  Expected annual return (e.g., 0.07)
     * @param  float  $volatility  Annual volatility (e.g., 0.15)
     * @param  int  $yearsToGoal  Years until goal date
     * @param  int  $iterations  Number of simulations (default 1000)
     * @return array Probability analysis
     */
    public function calculateGoalProbability(
        float $currentValue,
        float $targetValue,
        float $monthlyContribution,
        float $expectedReturn,
        float $volatility,
        int $yearsToGoal,
        int $iterations = 1000
    ): array {
        if ($yearsToGoal <= 0) {
            return [
                'success' => false,
                'message' => 'Years to goal must be positive',
            ];
        }

        $months = $yearsToGoal * 12;
        $monthlyReturn = $expectedReturn / 12;
        $monthlyVolatility = $volatility / sqrt(12);

        $successCount = 0;
        $finalValues = [];

        // Run Monte Carlo simulations
        for ($i = 0; $i < $iterations; $i++) {
            $finalValue = $this->runSingleSimulation(
                $currentValue,
                $monthlyContribution,
                $monthlyReturn,
                $monthlyVolatility,
                $months
            );

            $finalValues[] = $finalValue;

            if ($finalValue >= $targetValue) {
                $successCount++;
            }
        }

        $probability = ($successCount / $iterations) * 100;

        // Calculate percentiles
        sort($finalValues);
        $percentiles = $this->calculatePercentiles($finalValues);

        // Determine confidence level
        $confidence = $this->determineConfidence($probability);

        return [
            'success' => true,
            'probability_percent' => round($probability, 1),
            'confidence_level' => $confidence,
            'expected_value' => $percentiles['p50'],
            'best_case' => $percentiles['p90'],
            'worst_case' => $percentiles['p10'],
            'target_value' => $targetValue,
            'shortfall_risk' => 100 - $probability,
            'interpretation' => $this->interpretProbability($probability, $targetValue, $percentiles['p50']),
        ];
    }

    /**
     * Calculate required contribution to reach target probability
     *
     * @param  float  $currentValue  Current value
     * @param  float  $targetValue  Target value
     * @param  float  $currentContribution  Current monthly contribution
     * @param  float  $expectedReturn  Expected return
     * @param  float  $volatility  Volatility
     * @param  int  $yearsToGoal  Years to goal
     * @param  float  $targetProbability  Target probability (e.g., 0.85 for 85%)
     * @return array Required contribution analysis
     */
    public function calculateRequiredContribution(
        float $currentValue,
        float $targetValue,
        float $currentContribution,
        float $expectedReturn,
        float $volatility,
        int $yearsToGoal,
        float $targetProbability = 0.85
    ): array {
        // Use binary search to find required contribution
        $minContribution = 0;
        $maxContribution = $targetValue / ($yearsToGoal * 12); // Upper bound
        $tolerance = 10; // £10 tolerance

        $iterations = 0;
        $maxIterations = 20;

        while ($iterations < $maxIterations && ($maxContribution - $minContribution) > $tolerance) {
            $testContribution = ($minContribution + $maxContribution) / 2;

            $result = $this->calculateGoalProbability(
                $currentValue,
                $targetValue,
                $testContribution,
                $expectedReturn,
                $volatility,
                $yearsToGoal,
                500 // Fewer iterations for speed
            );

            $probability = $result['probability_percent'] / 100;

            if ($probability < $targetProbability) {
                $minContribution = $testContribution;
            } else {
                $maxContribution = $testContribution;
            }

            $iterations++;
        }

        $requiredContribution = ($minContribution + $maxContribution) / 2;
        $increase = $requiredContribution - $currentContribution;

        return [
            'current_contribution' => $currentContribution,
            'required_contribution' => round($requiredContribution, 2),
            'increase_needed' => round($increase, 2),
            'increase_percent' => $currentContribution > 0 ? round(($increase / $currentContribution) * 100, 1) : 0,
            'target_probability' => $targetProbability * 100,
            'action_needed' => $increase > 10, // More than £10/month increase
        ];
    }

    /**
     * Run a single Monte Carlo simulation
     *
     * @param  float  $startValue  Starting value
     * @param  float  $monthlyContribution  Monthly contribution
     * @param  float  $monthlyReturn  Expected monthly return
     * @param  float  $monthlyVolatility  Monthly volatility
     * @param  int  $months  Number of months
     * @return float Final value
     */
    private function runSingleSimulation(
        float $startValue,
        float $monthlyContribution,
        float $monthlyReturn,
        float $monthlyVolatility,
        int $months
    ): float {
        $value = $startValue;

        for ($month = 0; $month < $months; $month++) {
            // Generate random return using normal distribution
            $randomReturn = $this->generateNormalReturn($monthlyReturn, $monthlyVolatility);

            // Apply return to current value
            $value *= (1 + $randomReturn);

            // Add monthly contribution
            $value += $monthlyContribution;
        }

        return $value;
    }

    /**
     * Generate random return from normal distribution
     *
     * @param  float  $mean  Mean return
     * @param  float  $stdDev  Standard deviation
     * @return float Random return
     */
    private function generateNormalReturn(float $mean, float $stdDev): float
    {
        // Box-Muller transform
        $u1 = mt_rand() / mt_getrandmax();
        $u2 = mt_rand() / mt_getrandmax();

        if ($u1 < 1e-10) {
            $u1 = 1e-10;
        }

        $z = sqrt(-2 * log($u1)) * cos(2 * pi() * $u2);

        return $mean + ($z * $stdDev);
    }

    /**
     * Calculate percentiles from array of values
     *
     * @param  array  $values  Sorted array of values
     * @return array Percentiles
     */
    private function calculatePercentiles(array $values): array
    {
        $count = count($values);

        return [
            'p10' => $values[(int) ($count * 0.10)],
            'p20' => $values[(int) ($count * 0.20)],
            'p25' => $values[(int) ($count * 0.25)],
            'p50' => $values[(int) ($count * 0.50)], // Median
            'p75' => $values[(int) ($count * 0.75)],
            'p90' => $values[(int) ($count * 0.90)],
        ];
    }

    /**
     * Determine confidence level based on probability
     *
     * @param  float  $probability  Probability percentage
     * @return string Confidence level
     */
    private function determineConfidence(float $probability): string
    {
        return match (true) {
            $probability >= 90 => 'Very High',
            $probability >= 75 => 'High',
            $probability >= 60 => 'Moderate',
            $probability >= 40 => 'Low',
            default => 'Very Low',
        };
    }

    /**
     * Interpret probability result
     *
     * @param  float  $probability  Probability percentage
     * @param  float  $targetValue  Target value
     * @param  float  $expectedValue  Expected (median) value
     * @return string Interpretation
     */
    private function interpretProbability(float $probability, float $targetValue, float $expectedValue): string
    {
        if ($probability >= 85) {
            return sprintf(
                'Excellent - %.0f%% chance of reaching £%s target. You\'re on track.',
                $probability,
                number_format($targetValue, 0)
            );
        } elseif ($probability >= 70) {
            return sprintf(
                'Good - %.0f%% chance of reaching target. Consider small contribution increase for safety.',
                $probability
            );
        } elseif ($probability >= 50) {
            $shortfall = $targetValue - $expectedValue;

            return sprintf(
                'Moderate - %.0f%% chance. Expected shortfall: £%s. Increase contributions recommended.',
                $probability,
                number_format($shortfall, 0)
            );
        } else {
            return sprintf(
                'Low - Only %.0f%% chance of reaching target. Significant action needed: increase contributions, extend timeline, or reduce target.',
                $probability
            );
        }
    }

    /**
     * Calculate glide path recommendation
     * As goal approaches, reduce risk to protect accumulated gains
     *
     * @param  int  $yearsToGoal  Years remaining to goal
     * @param  float  $currentEquityPercent  Current equity allocation
     * @return array Glide path recommendation
     */
    public function calculateGlidePath(int $yearsToGoal, float $currentEquityPercent): array
    {
        // Standard glide path: reduce equity as goal approaches
        $recommendedEquityPercent = match (true) {
            $yearsToGoal >= 10 => 80, // More than 10 years: aggressive
            $yearsToGoal >= 5 => 60,  // 5-10 years: balanced
            $yearsToGoal >= 2 => 40,  // 2-5 years: conservative
            default => 20,            // Less than 2 years: very conservative
        };

        $rebalanceNeeded = abs($currentEquityPercent - $recommendedEquityPercent) > 10;

        return [
            'years_to_goal' => $yearsToGoal,
            'current_equity_percent' => $currentEquityPercent,
            'recommended_equity_percent' => $recommendedEquityPercent,
            'rebalance_needed' => $rebalanceNeeded,
            'rationale' => $this->getGlidePathRationale($yearsToGoal, $recommendedEquityPercent),
        ];
    }

    /**
     * Get glide path rationale
     *
     * @param  int  $yearsToGoal  Years to goal
     * @param  float  $recommendedEquity  Recommended equity percentage
     * @return string Rationale
     */
    private function getGlidePathRationale(int $yearsToGoal, float $recommendedEquity): string
    {
        if ($yearsToGoal >= 10) {
            return sprintf(
                'With %d years to go, maintain %d%% equities for growth potential. Time to recover from market volatility.',
                $yearsToGoal,
                (int) $recommendedEquity
            );
        } elseif ($yearsToGoal >= 5) {
            return sprintf(
                'With %d years remaining, reduce to %d%% equities for balanced growth and protection.',
                $yearsToGoal,
                (int) $recommendedEquity
            );
        } elseif ($yearsToGoal >= 2) {
            return sprintf(
                'Goal approaching in %d years. Reduce to %d%% equities to protect accumulated gains.',
                $yearsToGoal,
                (int) $recommendedEquity
            );
        } else {
            return sprintf(
                'Goal imminent (%d year%s). Reduce to %d%% equities to minimize volatility risk.',
                $yearsToGoal,
                $yearsToGoal === 1 ? '' : 's',
                (int) $recommendedEquity
            );
        }
    }
}
