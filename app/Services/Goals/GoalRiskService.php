<?php

declare(strict_types=1);

namespace App\Services\Goals;

use App\Models\Goal;
use App\Models\Investment\RiskProfile;
use App\Models\User;
use App\Services\Shared\MonteCarloEngine;

/**
 * Service for goal-based risk assessment and projections for investment goals.
 */
class GoalRiskService
{
    private const RISK_LEVELS = [
        1 => ['label' => 'Conservative', 'expected_return' => 0.03, 'volatility' => 0.05],
        2 => ['label' => 'Cautious', 'expected_return' => 0.045, 'volatility' => 0.08],
        3 => ['label' => 'Balanced', 'expected_return' => 0.06, 'volatility' => 0.12],
        4 => ['label' => 'Growth', 'expected_return' => 0.075, 'volatility' => 0.16],
        5 => ['label' => 'Aggressive', 'expected_return' => 0.09, 'volatility' => 0.20],
    ];

    /**
     * Mapping from the main 5-level string risk system to goal numeric 1-5 system.
     */
    private const RISK_LEVEL_STRING_MAP = [
        'low' => 1,
        'lower_medium' => 2,
        'medium' => 3,
        'upper_medium' => 4,
        'high' => 5,
    ];

    /**
     * Minimum years before using full Monte Carlo simulation (below this, use analytical).
     */
    private const MONTE_CARLO_MIN_YEARS = 1;

    /**
     * Default Monte Carlo iterations for goal projections.
     */
    private const MONTE_CARLO_ITERATIONS = 500;

    public function __construct(
        private readonly MonteCarloEngine $monteCarloEngine
    ) {}

    /**
     * Get risk parameters for a goal.
     */
    public function getRiskParameters(Goal $goal, ?RiskProfile $globalRiskProfile = null): array
    {
        // Use goal-specific risk preference if set and not using global
        $riskLevel = $goal->risk_preference;

        if ($goal->use_global_risk_profile && $globalRiskProfile) {
            $globalLevel = $globalRiskProfile->risk_level;
            $riskLevel = self::RISK_LEVEL_STRING_MAP[$globalLevel] ?? 3;
        }

        // Default to balanced if no preference set
        $riskLevel = $riskLevel ?? 3;
        $riskLevel = max(1, min(5, $riskLevel)); // Clamp to 1-5

        $params = self::RISK_LEVELS[$riskLevel];

        return [
            'risk_level' => $riskLevel,
            'risk_label' => $params['label'],
            'expected_return' => $params['expected_return'],
            'volatility' => $params['volatility'],
            'use_global_profile' => $goal->use_global_risk_profile,
        ];
    }

    /**
     * Get projections for an investment goal.
     */
    public function getProjections(Goal $goal, ?RiskProfile $riskProfile = null): array
    {
        $riskParams = $this->getRiskParameters($goal, $riskProfile);
        $currentAmount = (float) $goal->current_amount;
        $targetAmount = (float) $goal->target_amount;
        $monthlyContribution = (float) ($goal->monthly_contribution ?? 0);
        $yearsToGoal = $goal->months_remaining / 12;

        if ($yearsToGoal <= 0) {
            return [
                'risk_parameters' => $riskParams,
                'projections' => null,
                'message' => 'Goal target date has passed',
            ];
        }

        $expectedReturn = $riskParams['expected_return'];
        $volatility = $riskParams['volatility'];

        // Calculate deterministic projection
        $deterministicProjection = $this->calculateDeterministicProjection(
            $currentAmount,
            $monthlyContribution,
            $expectedReturn,
            $yearsToGoal
        );

        // Calculate probability using Monte Carlo simulation for realistic goals
        $probabilityOfSuccess = $this->calculateProbability(
            $currentAmount,
            $monthlyContribution,
            $targetAmount,
            $yearsToGoal,
            $expectedReturn,
            $volatility
        );

        // Calculate required contribution to reach goal with 75% probability
        $requiredContribution = $this->calculateRequiredContribution(
            $currentAmount,
            $targetAmount,
            $yearsToGoal,
            $expectedReturn
        );

        // Generate yearly projections with confidence bands
        $yearlyProjections = $this->generateYearlyProjections(
            $currentAmount,
            $monthlyContribution,
            $yearsToGoal,
            $expectedReturn,
            $volatility
        );

        return [
            'risk_parameters' => $riskParams,
            'projections' => [
                'expected_final_value' => round($deterministicProjection, 2),
                'target_amount' => round($targetAmount, 2),
                'shortfall' => round(max(0, $targetAmount - $deterministicProjection), 2),
                'surplus' => round(max(0, $deterministicProjection - $targetAmount), 2),
                'probability_of_success' => round($probabilityOfSuccess, 1),
                'required_monthly_contribution' => round($requiredContribution, 2),
                'current_monthly_contribution' => round($monthlyContribution, 2),
                'contribution_gap' => round(max(0, $requiredContribution - $monthlyContribution), 2),
            ],
            'yearly_projections' => $yearlyProjections,
            'recommendation' => $this->generateRecommendation(
                $probabilityOfSuccess,
                $monthlyContribution,
                $requiredContribution
            ),
        ];
    }

    /**
     * Calculate probability of reaching the goal.
     *
     * Uses Monte Carlo simulation via the shared engine for goals with
     * sufficient time horizon, falls back to analytical approximation
     * for very short-term goals.
     */
    private function calculateProbability(
        float $currentAmount,
        float $monthlyContribution,
        float $targetAmount,
        float $years,
        float $expectedReturn,
        float $volatility
    ): float {
        $yearsInt = max(1, (int) ceil($years));

        if ($years >= self::MONTE_CARLO_MIN_YEARS && $volatility > 0) {
            $result = $this->monteCarloEngine->simulate(
                $currentAmount,
                $monthlyContribution,
                $expectedReturn,
                $volatility,
                $yearsInt,
                self::MONTE_CARLO_ITERATIONS
            );

            return $this->monteCarloEngine->calculateGoalProbability(
                $result['final_values'],
                $targetAmount
            );
        }

        // Analytical fallback for very short-term or zero-volatility goals
        return $this->estimateProbabilityAnalytical(
            $currentAmount,
            $monthlyContribution,
            $targetAmount,
            $years,
            $expectedReturn,
            $volatility
        );
    }

    /**
     * Calculate deterministic projection using future value formula.
     */
    private function calculateDeterministicProjection(
        float $currentAmount,
        float $monthlyContribution,
        float $annualReturn,
        float $years
    ): float {
        $monthlyReturn = $annualReturn / 12;
        $months = (int) ($years * 12);

        // FV of lump sum
        $fvLumpSum = $currentAmount * pow(1 + $monthlyReturn, $months);

        // FV of regular contributions (annuity)
        $fvContributions = 0;
        if ($monthlyReturn > 0 && $monthlyContribution > 0) {
            $fvContributions = $monthlyContribution * ((pow(1 + $monthlyReturn, $months) - 1) / $monthlyReturn);
        }

        return $fvLumpSum + $fvContributions;
    }

    /**
     * Estimate probability of success using analytical log-normal approximation.
     */
    private function estimateProbabilityAnalytical(
        float $currentAmount,
        float $monthlyContribution,
        float $targetAmount,
        float $years,
        float $expectedReturn,
        float $volatility
    ): float {
        $expectedFinalValue = $this->calculateDeterministicProjection(
            $currentAmount,
            $monthlyContribution,
            $expectedReturn,
            $years
        );

        $portfolioVolatility = $volatility * sqrt($years);
        $mu = log($expectedFinalValue) - ($portfolioVolatility * $portfolioVolatility) / 2;

        if ($targetAmount <= 0 || $portfolioVolatility <= 0) {
            return $expectedFinalValue >= $targetAmount ? 95 : 50;
        }

        $z = (log($targetAmount) - $mu) / $portfolioVolatility;
        $probability = 1 - $this->standardNormalCDF($z);

        return max(0, min(100, $probability * 100));
    }

    /**
     * Standard normal cumulative distribution function approximation.
     */
    private function standardNormalCDF(float $z): float
    {
        // Abramowitz and Stegun approximation
        $a1 = 0.254829592;
        $a2 = -0.284496736;
        $a3 = 1.421413741;
        $a4 = -1.453152027;
        $a5 = 1.061405429;
        $p = 0.3275911;

        $sign = $z < 0 ? -1 : 1;
        $z = abs($z) / sqrt(2);

        $t = 1.0 / (1.0 + $p * $z);
        $y = 1.0 - ((((($a5 * $t + $a4) * $t) + $a3) * $t + $a2) * $t + $a1) * $t * exp(-$z * $z);

        return 0.5 * (1.0 + $sign * $y);
    }

    /**
     * Calculate required monthly contribution to reach target.
     */
    private function calculateRequiredContribution(
        float $currentAmount,
        float $targetAmount,
        float $years,
        float $expectedReturn
    ): float {
        $monthlyReturn = $expectedReturn / 12;
        $months = (int) ($years * 12);

        $fvCurrent = $currentAmount * pow(1 + $monthlyReturn, $months);
        $amountFromContributions = $targetAmount - $fvCurrent;

        if ($amountFromContributions <= 0) {
            return 0;
        }

        if ($monthlyReturn > 0) {
            return $amountFromContributions * $monthlyReturn / (pow(1 + $monthlyReturn, $months) - 1);
        }

        return $amountFromContributions / $months;
    }

    /**
     * Generate yearly projections with confidence bands.
     */
    private function generateYearlyProjections(
        float $currentAmount,
        float $monthlyContribution,
        float $totalYears,
        float $expectedReturn,
        float $volatility
    ): array {
        $projections = [];
        $years = max(1, (int) ceil($totalYears));

        for ($year = 0; $year <= $years; $year++) {
            $expectedValue = $this->calculateDeterministicProjection(
                $currentAmount,
                $monthlyContribution,
                $expectedReturn,
                $year
            );

            $yearVolatility = $volatility * sqrt(max(1, $year));
            $lowerBound = $expectedValue * exp(-1.96 * $yearVolatility);
            $upperBound = $expectedValue * exp(1.96 * $yearVolatility);

            $projections[] = [
                'year' => $year,
                'date' => now()->addYears($year)->format('Y-m-d'),
                'expected_value' => round($expectedValue, 2),
                'lower_bound_95' => round($lowerBound, 2),
                'upper_bound_95' => round($upperBound, 2),
            ];
        }

        return $projections;
    }

    /**
     * Generate recommendation based on projections.
     */
    private function generateRecommendation(
        float $probability,
        float $currentContribution,
        float $requiredContribution
    ): array {
        if ($probability >= 90) {
            return [
                'status' => 'excellent',
                'message' => 'You are well on track to meet this goal.',
                'action' => null,
            ];
        }

        if ($probability >= 75) {
            return [
                'status' => 'good',
                'message' => 'You have a good chance of reaching this goal.',
                'action' => 'Consider maintaining your current contribution level.',
            ];
        }

        if ($probability >= 50) {
            $gap = $requiredContribution - $currentContribution;

            return [
                'status' => 'moderate',
                'message' => 'There is a moderate chance of reaching this goal.',
                'action' => $gap > 0
                    ? sprintf('Consider increasing your contribution by £%.0f/month.', $gap)
                    : 'Consider extending your timeline or reducing the target.',
            ];
        }

        return [
            'status' => 'at_risk',
            'message' => 'This goal may be difficult to achieve with current settings.',
            'action' => 'Review your target amount, timeline, or contribution level.',
        ];
    }

    /**
     * Get user's global risk profile.
     */
    public function getUserRiskProfile(User $user): ?RiskProfile
    {
        return RiskProfile::where('user_id', $user->id)->first();
    }

    /**
     * Get available risk levels for selection.
     */
    public function getAvailableRiskLevels(): array
    {
        return collect(self::RISK_LEVELS)->map(fn ($params, $level) => [
            'level' => $level,
            'label' => $params['label'],
            'expected_return' => $params['expected_return'] * 100,
            'volatility' => $params['volatility'] * 100,
            'description' => $this->getRiskLevelDescription($level),
        ])->values()->toArray();
    }

    /**
     * Get description for a risk level.
     */
    private function getRiskLevelDescription(int $level): string
    {
        return match ($level) {
            1 => 'Prioritises capital preservation. Suitable for short-term goals or risk-averse investors.',
            2 => 'Seeks steady growth with limited downside. Suitable for medium-term goals.',
            3 => 'Balances growth potential with risk management. Suitable for 5-10 year goals.',
            4 => 'Focuses on growth with higher volatility tolerance. Suitable for 10+ year goals.',
            5 => 'Maximises growth potential. Suitable for long-term goals where volatility is acceptable.',
            default => 'Balanced approach to risk and return.',
        };
    }
}
