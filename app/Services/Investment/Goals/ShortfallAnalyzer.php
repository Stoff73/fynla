<?php

declare(strict_types=1);

namespace App\Services\Investment\Goals;

use App\Models\Investment\InvestmentGoal;
use App\Services\Risk\RiskPreferenceService;

/**
 * Shortfall Analyzer
 * Analyzes goal shortfalls and provides mitigation strategies
 *
 * Features:
 * - Gap analysis (current trajectory vs target)
 * - Mitigation options (increase contributions, extend timeline, reduce target)
 * - What-if scenario modeling
 * - Sensitivity analysis (returns, contributions, timeline)
 */
class ShortfallAnalyzer
{
    public function __construct(
        private GoalProbabilityCalculator $probabilityCalculator,
        private readonly RiskPreferenceService $riskPreferenceService
    ) {}

    /**
     * Get default expected return from user's risk profile
     */
    private function getDefaultExpectedReturn(): float
    {
        return $this->riskPreferenceService->getReturnParameters('medium')['expected_return_typical'] / 100;
    }

    /**
     * Analyze goal shortfall and provide mitigation strategies
     *
     * @param  InvestmentGoal  $goal  Investment goal
     * @param  float  $currentValue  Current portfolio value
     * @return array Shortfall analysis
     */
    public function analyzeShortfall(InvestmentGoal $goal, float $currentValue): array
    {
        if (! $goal->target_value || ! $goal->target_date) {
            return [
                'success' => false,
                'message' => 'Goal requires target value and target date',
            ];
        }

        $yearsToGoal = $this->calculateYearsToGoal($goal->target_date);

        if ($yearsToGoal <= 0) {
            return [
                'success' => false,
                'message' => 'Goal target date has passed',
            ];
        }

        // Calculate base probability
        $baseProbability = $this->probabilityCalculator->calculateGoalProbability(
            $currentValue,
            $goal->target_value,
            $goal->monthly_contribution ?? 0,
            $goal->expected_return ?? $this->getDefaultExpectedReturn(),
            $goal->volatility ?? 0.15,
            $yearsToGoal
        );

        // If probability is high, no shortfall
        if ($baseProbability['probability_percent'] >= 85) {
            return [
                'success' => true,
                'has_shortfall' => false,
                'probability_percent' => $baseProbability['probability_percent'],
                'message' => 'Goal on track - no shortfall expected',
            ];
        }

        // Calculate projected shortfall
        $expectedShortfall = $goal->target_value - $baseProbability['expected_value'];

        // Generate mitigation strategies
        $strategies = $this->generateMitigationStrategies(
            $currentValue,
            $goal->target_value,
            $goal->monthly_contribution ?? 0,
            $goal->expected_return ?? $this->getDefaultExpectedReturn(),
            $goal->volatility ?? 0.15,
            $yearsToGoal,
            $expectedShortfall
        );

        // Perform sensitivity analysis
        $sensitivity = $this->performSensitivityAnalysis(
            $currentValue,
            $goal->target_value,
            $goal->monthly_contribution ?? 0,
            $goal->expected_return ?? $this->getDefaultExpectedReturn(),
            $goal->volatility ?? 0.15,
            $yearsToGoal
        );

        return [
            'success' => true,
            'has_shortfall' => true,
            'current_probability' => $baseProbability['probability_percent'],
            'expected_shortfall' => round($expectedShortfall, 2),
            'shortfall_percent' => round(($expectedShortfall / $goal->target_value) * 100, 1),
            'current_trajectory' => [
                'expected_value' => $baseProbability['expected_value'],
                'best_case' => $baseProbability['best_case'],
                'worst_case' => $baseProbability['worst_case'],
            ],
            'mitigation_strategies' => $strategies,
            'sensitivity_analysis' => $sensitivity,
            'recommendation' => $this->generateRecommendation($strategies, $expectedShortfall),
        ];
    }

    /**
     * Generate what-if scenarios
     *
     * @param  InvestmentGoal  $goal  Investment goal
     * @param  float  $currentValue  Current value
     * @param  array  $scenarios  Scenario parameters
     * @return array What-if analysis
     */
    public function generateWhatIfScenarios(InvestmentGoal $goal, float $currentValue, array $scenarios = []): array
    {
        $yearsToGoal = $this->calculateYearsToGoal($goal->target_date);

        if ($yearsToGoal <= 0) {
            return [
                'success' => false,
                'message' => 'Goal target date has passed',
            ];
        }

        $baseContribution = $goal->monthly_contribution ?? 0;
        $baseReturn = $goal->expected_return ?? $this->getDefaultExpectedReturn();
        $volatility = $goal->volatility ?? 0.15;

        // Default scenarios if none provided
        if (empty($scenarios)) {
            $scenarios = [
                ['name' => 'Base Case', 'contribution' => $baseContribution, 'return' => $baseReturn],
                ['name' => 'Increase Contributions 20%', 'contribution' => $baseContribution * 1.2, 'return' => $baseReturn],
                ['name' => 'Increase Contributions 50%', 'contribution' => $baseContribution * 1.5, 'return' => $baseReturn],
                ['name' => 'Higher Returns (+2%)', 'contribution' => $baseContribution, 'return' => $baseReturn + 0.02],
                ['name' => 'Lower Returns (-2%)', 'contribution' => $baseContribution, 'return' => $baseReturn - 0.02],
                ['name' => 'Best Case (+50% contrib, +2% return)', 'contribution' => $baseContribution * 1.5, 'return' => $baseReturn + 0.02],
            ];
        }

        $results = [];

        foreach ($scenarios as $scenario) {
            $probability = $this->probabilityCalculator->calculateGoalProbability(
                $currentValue,
                $goal->target_value,
                $scenario['contribution'],
                $scenario['return'],
                $volatility,
                $yearsToGoal
            );

            $results[] = [
                'scenario_name' => $scenario['name'],
                'monthly_contribution' => $scenario['contribution'],
                'expected_return' => $scenario['return'],
                'probability_percent' => $probability['probability_percent'],
                'expected_value' => $probability['expected_value'],
                'shortfall' => max(0, $goal->target_value - $probability['expected_value']),
                'status' => $this->getScenarioStatus($probability['probability_percent']),
            ];
        }

        return [
            'success' => true,
            'goal_name' => $goal->goal_name,
            'target_value' => $goal->target_value,
            'years_to_goal' => $yearsToGoal,
            'scenarios' => $results,
            'best_scenario' => $this->findBestScenario($results),
        ];
    }

    /**
     * Calculate years to goal
     *
     * @param  string  $targetDate  Target date
     * @return float Years to goal
     */
    private function calculateYearsToGoal(string $targetDate): float
    {
        $now = new \DateTime;
        $target = new \DateTime($targetDate);
        $interval = $now->diff($target);

        return $interval->y + ($interval->m / 12) + ($interval->d / 365);
    }

    /**
     * Generate mitigation strategies
     *
     * @param  float  $currentValue  Current value
     * @param  float  $targetValue  Target value
     * @param  float  $currentContribution  Current contribution
     * @param  float  $expectedReturn  Expected return
     * @param  float  $volatility  Volatility
     * @param  float  $yearsToGoal  Years to goal
     * @param  float  $expectedShortfall  Expected shortfall
     * @return array Mitigation strategies
     */
    private function generateMitigationStrategies(
        float $currentValue,
        float $targetValue,
        float $currentContribution,
        float $expectedReturn,
        float $volatility,
        float $yearsToGoal,
        float $expectedShortfall
    ): array {
        $strategies = [];

        // Strategy 1: Increase contributions
        $requiredContribution = $this->probabilityCalculator->calculateRequiredContribution(
            $currentValue,
            $targetValue,
            $currentContribution,
            $expectedReturn,
            $volatility,
            $yearsToGoal,
            0.85
        );

        if ($requiredContribution['required_contribution'] > $currentContribution) {
            $strategies[] = [
                'strategy' => 'increase_contributions',
                'name' => 'Increase Monthly Contributions',
                'current' => $currentContribution,
                'required' => $requiredContribution['required_contribution'],
                'increase' => $requiredContribution['increase_needed'],
                'increase_percent' => $requiredContribution['increase_percent'],
                'probability_after' => 85.0,
                'feasibility' => $this->assessFeasibility($requiredContribution['increase_percent']),
                'description' => sprintf(
                    'Increase monthly contribution by £%s (%.0f%%) to reach 85%% success probability',
                    number_format($requiredContribution['increase_needed'], 0),
                    $requiredContribution['increase_percent']
                ),
            ];
        }

        // Strategy 2: Extend timeline
        $extendedYears = $yearsToGoal + 2;
        $extendedProbability = $this->probabilityCalculator->calculateGoalProbability(
            $currentValue,
            $targetValue,
            $currentContribution,
            $expectedReturn,
            $volatility,
            $extendedYears
        );

        $strategies[] = [
            'strategy' => 'extend_timeline',
            'name' => 'Extend Timeline',
            'current_years' => $yearsToGoal,
            'extended_years' => $extendedYears,
            'extension' => 2,
            'probability_after' => $extendedProbability['probability_percent'],
            'feasibility' => 'medium',
            'description' => sprintf(
                'Extend goal timeline by 2 years to %.0f years. Success probability increases to %.0f%%',
                $extendedYears,
                $extendedProbability['probability_percent']
            ),
        ];

        // Strategy 3: Reduce target
        $reducedTarget = $targetValue * 0.90; // 10% reduction
        $reducedProbability = $this->probabilityCalculator->calculateGoalProbability(
            $currentValue,
            $reducedTarget,
            $currentContribution,
            $expectedReturn,
            $volatility,
            $yearsToGoal
        );

        $strategies[] = [
            'strategy' => 'reduce_target',
            'name' => 'Reduce Target Amount',
            'current_target' => $targetValue,
            'reduced_target' => $reducedTarget,
            'reduction' => $targetValue - $reducedTarget,
            'reduction_percent' => 10,
            'probability_after' => $reducedProbability['probability_percent'],
            'feasibility' => 'high',
            'description' => sprintf(
                'Reduce target by 10%% to £%s. Success probability increases to %.0f%%',
                number_format($reducedTarget, 0),
                $reducedProbability['probability_percent']
            ),
        ];

        // Strategy 4: Increase risk (higher return assumption)
        $higherReturn = $expectedReturn + 0.02; // +2% annual return
        $higherReturnProbability = $this->probabilityCalculator->calculateGoalProbability(
            $currentValue,
            $targetValue,
            $currentContribution,
            $higherReturn,
            $volatility * 1.2, // Higher volatility with higher return
            $yearsToGoal
        );

        $strategies[] = [
            'strategy' => 'increase_risk',
            'name' => 'Increase Portfolio Risk',
            'current_return' => $expectedReturn,
            'target_return' => $higherReturn,
            'probability_after' => $higherReturnProbability['probability_percent'],
            'feasibility' => 'medium',
            'risk_note' => 'Higher returns require higher risk tolerance and longer time horizon',
            'description' => sprintf(
                'Adjust asset allocation for %.0f%% annual return. Success probability: %.0f%%',
                $higherReturn * 100,
                $higherReturnProbability['probability_percent']
            ),
        ];

        // Sort by probability after
        usort($strategies, fn ($a, $b) => $b['probability_after'] <=> $a['probability_after']);

        return $strategies;
    }

    /**
     * Perform sensitivity analysis
     *
     * @param  float  $currentValue  Current value
     * @param  float  $targetValue  Target value
     * @param  float  $baseContribution  Base contribution
     * @param  float  $baseReturn  Base return
     * @param  float  $volatility  Volatility
     * @param  float  $yearsToGoal  Years to goal
     * @return array Sensitivity analysis
     */
    private function performSensitivityAnalysis(
        float $currentValue,
        float $targetValue,
        float $baseContribution,
        float $baseReturn,
        float $volatility,
        float $yearsToGoal
    ): array {
        // Contribution sensitivity
        $contributionSensitivity = [];
        $contributionChanges = [-50, -25, 0, 25, 50, 100];

        foreach ($contributionChanges as $pct) {
            $contribution = $baseContribution * (1 + ($pct / 100));
            $result = $this->probabilityCalculator->calculateGoalProbability(
                $currentValue,
                $targetValue,
                $contribution,
                $baseReturn,
                $volatility,
                $yearsToGoal
            );

            $contributionSensitivity[] = [
                'change_percent' => $pct,
                'contribution' => round($contribution, 2),
                'probability' => $result['probability_percent'],
            ];
        }

        // Return sensitivity
        $returnSensitivity = [];
        $returnChanges = [-0.02, -0.01, 0, 0.01, 0.02];

        foreach ($returnChanges as $change) {
            $return = $baseReturn + $change;
            $result = $this->probabilityCalculator->calculateGoalProbability(
                $currentValue,
                $targetValue,
                $baseContribution,
                $return,
                $volatility,
                $yearsToGoal
            );

            $returnSensitivity[] = [
                'change_percent' => $change * 100,
                'return' => $return,
                'probability' => $result['probability_percent'],
            ];
        }

        return [
            'contribution_sensitivity' => $contributionSensitivity,
            'return_sensitivity' => $returnSensitivity,
            'interpretation' => $this->interpretSensitivity($contributionSensitivity, $returnSensitivity),
        ];
    }

    /**
     * Assess feasibility of contribution increase
     *
     * @param  float  $increasePercent  Increase percentage
     * @return string Feasibility level
     */
    private function assessFeasibility(float $increasePercent): string
    {
        if ($increasePercent <= 10) {
            return 'high';
        } elseif ($increasePercent <= 30) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get scenario status
     *
     * @param  float  $probability  Probability percentage
     * @return string Status
     */
    private function getScenarioStatus(float $probability): string
    {
        if ($probability >= 85) {
            return 'excellent';
        } elseif ($probability >= 70) {
            return 'good';
        } elseif ($probability >= 50) {
            return 'moderate';
        } else {
            return 'poor';
        }
    }

    /**
     * Find best scenario from results
     *
     * @param  array  $scenarios  Scenario results
     * @return array Best scenario
     */
    private function findBestScenario(array $scenarios): array
    {
        $best = null;
        $highestProbability = 0;

        foreach ($scenarios as $scenario) {
            if ($scenario['probability_percent'] > $highestProbability) {
                $highestProbability = $scenario['probability_percent'];
                $best = $scenario;
            }
        }

        return $best ?? [];
    }

    /**
     * Generate recommendation based on strategies
     *
     * @param  array  $strategies  Mitigation strategies
     * @param  float  $shortfall  Expected shortfall
     * @return array Recommendation
     */
    private function generateRecommendation(array $strategies, float $shortfall): array
    {
        if (empty($strategies)) {
            return [
                'priority' => 'low',
                'message' => 'No immediate action required',
            ];
        }

        // Find most feasible strategy with good probability
        $recommended = null;
        foreach ($strategies as $strategy) {
            if ($strategy['probability_after'] >= 85 && $strategy['feasibility'] === 'high') {
                $recommended = $strategy;
                break;
            }
        }

        // If no high feasibility, take medium
        if (! $recommended) {
            foreach ($strategies as $strategy) {
                if ($strategy['probability_after'] >= 75 && $strategy['feasibility'] === 'medium') {
                    $recommended = $strategy;
                    break;
                }
            }
        }

        // Default to best probability strategy
        if (! $recommended) {
            $recommended = $strategies[0];
        }

        $priority = $shortfall > 50000 ? 'high' : ($shortfall > 20000 ? 'medium' : 'low');

        return [
            'priority' => $priority,
            'recommended_strategy' => $recommended['strategy'],
            'message' => $recommended['description'],
            'alternative_strategies' => array_slice($strategies, 1, 2),
        ];
    }

    /**
     * Interpret sensitivity analysis
     *
     * @param  array  $contributionSensitivity  Contribution sensitivity
     * @param  array  $returnSensitivity  Return sensitivity
     * @return string Interpretation
     */
    private function interpretSensitivity(array $contributionSensitivity, array $returnSensitivity): string
    {
        // Find impact of 50% contribution increase
        $contrib50 = collect($contributionSensitivity)->firstWhere('change_percent', 50);
        $contribBase = collect($contributionSensitivity)->firstWhere('change_percent', 0);

        // Find impact of 2% return increase
        $return2 = collect($returnSensitivity)->firstWhere('change_percent', 2);
        $returnBase = collect($returnSensitivity)->firstWhere('change_percent', 0);

        $contribImpact = $contrib50 && $contribBase ? $contrib50['probability'] - $contribBase['probability'] : 0;
        $returnImpact = $return2 && $returnBase ? $return2['probability'] - $returnBase['probability'] : 0;

        if ($contribImpact > $returnImpact * 1.5) {
            return 'Goal is highly sensitive to contribution changes. Increasing contributions will have significant impact.';
        } elseif ($returnImpact > $contribImpact * 1.5) {
            return 'Goal is highly sensitive to investment returns. Consider portfolio optimization.';
        } else {
            return 'Goal success depends on both contributions and returns. Balanced approach recommended.';
        }
    }
}
