<?php

declare(strict_types=1);

namespace App\Services\Investment\Goals;

use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\InvestmentGoal;
use App\Services\Risk\RiskPreferenceService;

/**
 * Goal Progress Analyzer
 * Tracks investment goal progress and determines on-track status
 *
 * Analyzes:
 * - Current value vs target value
 * - Progress percentage and trajectory
 * - On-track status (Green/Orange/Red)
 * - Success probability using Monte Carlo
 * - Time remaining and milestones
 * - Monthly contribution adequacy
 */
class GoalProgressAnalyzer
{
    public function __construct(
        private GoalProbabilityCalculator $probabilityCalculator,
        private readonly RiskPreferenceService $riskPreferenceService
    ) {}

    /**
     * Get default expected return from risk preference service
     */
    private function getDefaultExpectedReturn(): float
    {
        return $this->riskPreferenceService->getReturnParameters('medium')['expected_return_typical'] / 100;
    }

    /**
     * Analyze progress for a single goal
     *
     * @param  InvestmentGoal  $goal  Investment goal
     * @return array Progress analysis
     */
    public function analyzeGoalProgress(InvestmentGoal $goal): array
    {
        if (! $goal->target_value || ! $goal->target_date) {
            return [
                'success' => false,
                'message' => 'Goal requires target value and target date',
            ];
        }

        // Get current portfolio value for this goal
        $currentValue = $this->calculateCurrentValue($goal);

        // Calculate time remaining
        $timeRemaining = $this->calculateTimeRemaining($goal->target_date);

        if ($timeRemaining['years'] <= 0) {
            return $this->analyzeCompletedGoal($goal, $currentValue);
        }

        // Calculate progress metrics
        $progressPercent = ($currentValue / $goal->target_value) * 100;
        $requiredGrowth = $goal->target_value - $currentValue;

        // Calculate success probability using Monte Carlo
        $probability = $this->probabilityCalculator->calculateGoalProbability(
            $currentValue,
            $goal->target_value,
            $goal->monthly_contribution ?? 0,
            $goal->expected_return ?? $this->getDefaultExpectedReturn(),
            $goal->volatility ?? 0.15,
            $timeRemaining['years'],
            1000
        );

        // Determine on-track status
        $status = $this->determineTrackStatus($progressPercent, $probability['probability_percent'], $timeRemaining['years']);

        // Calculate milestones
        $milestones = $this->calculateMilestones($currentValue, $goal->target_value, $goal->target_date);

        // Calculate required monthly contribution to improve success rate
        $contributionAnalysis = null;
        if ($probability['probability_percent'] < 85) {
            $contributionAnalysis = $this->probabilityCalculator->calculateRequiredContribution(
                $currentValue,
                $goal->target_value,
                $goal->monthly_contribution ?? 0,
                $goal->expected_return ?? $this->getDefaultExpectedReturn(),
                $goal->volatility ?? 0.15,
                $timeRemaining['years'],
                0.85
            );
        }

        // Calculate trajectory
        $trajectory = $this->calculateTrajectory($currentValue, $goal->monthly_contribution ?? 0, $goal->expected_return ?? $this->getDefaultExpectedReturn(), $timeRemaining['years']);

        return [
            'success' => true,
            'goal_name' => $goal->goal_name,
            'goal_type' => $goal->goal_type,
            'current_value' => $currentValue,
            'target_value' => $goal->target_value,
            'progress_percent' => round($progressPercent, 1),
            'required_growth' => $requiredGrowth,
            'time_remaining' => $timeRemaining,
            'status' => $status,
            'probability_analysis' => $probability,
            'monthly_contribution' => $goal->monthly_contribution ?? 0,
            'contribution_analysis' => $contributionAnalysis,
            'trajectory' => $trajectory,
            'milestones' => $milestones,
            'recommendations' => $this->generateRecommendations($status, $probability, $contributionAnalysis, $timeRemaining),
        ];
    }

    /**
     * Analyze progress for all user goals
     *
     * @param  int  $userId  User ID
     * @return array Progress summary for all goals
     */
    public function analyzeAllGoals(int $userId): array
    {
        // Note: investment_goals table does not have a status column
        // Get all goals for the user
        $goals = InvestmentGoal::where('user_id', $userId)
            ->get();

        if ($goals->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No active investment goals found',
            ];
        }

        $goalAnalyses = [];
        $summary = [
            'total_goals' => 0,
            'on_track' => 0,
            'needs_attention' => 0,
            'critical' => 0,
            'total_current_value' => 0,
            'total_target_value' => 0,
            'average_probability' => 0,
        ];

        foreach ($goals as $goal) {
            $analysis = $this->analyzeGoalProgress($goal);

            if ($analysis['success']) {
                $goalAnalyses[] = $analysis;

                $summary['total_goals']++;
                $summary['total_current_value'] += $analysis['current_value'];
                $summary['total_target_value'] += $analysis['target_value'];

                if (isset($analysis['probability_analysis']['probability_percent'])) {
                    $summary['average_probability'] += $analysis['probability_analysis']['probability_percent'];
                }

                match ($analysis['status']['level']) {
                    'green' => $summary['on_track']++,
                    'orange' => $summary['needs_attention']++,
                    'red' => $summary['critical']++,
                    default => null,
                };
            }
        }

        if ($summary['total_goals'] > 0) {
            $summary['average_probability'] = round($summary['average_probability'] / $summary['total_goals'], 1);
            $summary['overall_progress_percent'] = round(($summary['total_current_value'] / $summary['total_target_value']) * 100, 1);
        }

        return [
            'success' => true,
            'summary' => $summary,
            'goals' => $goalAnalyses,
            'overall_status' => $this->determineOverallStatus($summary),
        ];
    }

    /**
     * Calculate current value for a goal
     *
     * @param  InvestmentGoal  $goal  Goal
     * @return float Current value
     */
    private function calculateCurrentValue(InvestmentGoal $goal): float
    {
        // If goal has specific accounts linked
        if ($goal->account_id) {
            $account = InvestmentAccount::find($goal->account_id);

            return $account ? $account->total_value : 0;
        }

        // Otherwise use goal's current_value field
        return $goal->current_value ?? 0;
    }

    /**
     * Calculate time remaining to goal
     *
     * @param  string  $targetDate  Target date (YYYY-MM-DD)
     * @return array Time remaining breakdown
     */
    private function calculateTimeRemaining(string $targetDate): array
    {
        $now = new \DateTime;
        $target = new \DateTime($targetDate);
        $interval = $now->diff($target);

        $years = $interval->y + ($interval->m / 12) + ($interval->d / 365);
        $months = ($interval->y * 12) + $interval->m;

        return [
            'years' => round($years, 1),
            'months' => $months,
            'days' => $interval->days,
            'formatted' => $this->formatTimeRemaining($interval),
            'percentage_elapsed' => 0, // Would need start date to calculate
        ];
    }

    /**
     * Format time remaining as human-readable string
     *
     * @param  \DateInterval  $interval  Date interval
     * @return string Formatted string
     */
    private function formatTimeRemaining(\DateInterval $interval): string
    {
        $parts = [];

        if ($interval->y > 0) {
            $parts[] = $interval->y.' '.($interval->y === 1 ? 'year' : 'years');
        }

        if ($interval->m > 0) {
            $parts[] = $interval->m.' '.($interval->m === 1 ? 'month' : 'months');
        }

        if (empty($parts) && $interval->d > 0) {
            $parts[] = $interval->d.' '.($interval->d === 1 ? 'day' : 'days');
        }

        return implode(' and ', $parts);
    }

    /**
     * Determine on-track status
     *
     * @param  float  $progressPercent  Progress percentage
     * @param  float  $probability  Success probability
     * @param  float  $yearsRemaining  Years remaining
     * @return array Status with level and message
     */
    private function determineTrackStatus(float $progressPercent, float $probability, float $yearsRemaining): array
    {
        // Green: High probability and good progress
        if ($probability >= 85 && $progressPercent >= 50) {
            return [
                'level' => 'green',
                'label' => 'On Track',
                'color' => '#10B981',
                'message' => sprintf('Excellent progress - %.0f%% complete with %.0f%% success probability', $progressPercent, $probability),
            ];
        }

        // Green: High probability even with lower progress (early days)
        if ($probability >= 85 && $yearsRemaining > 10) {
            return [
                'level' => 'green',
                'label' => 'On Track',
                'color' => '#10B981',
                'message' => sprintf('On track - %.0f%% success probability with plenty of time', $probability),
            ];
        }

        // Orange: Moderate probability or needs attention
        if ($probability >= 60 || ($progressPercent >= 40 && $probability >= 50)) {
            return [
                'level' => 'orange',
                'label' => 'Needs Attention',
                'color' => '#F97316',
                'message' => sprintf('Moderate progress - %.0f%% probability. Consider increasing contributions.', $probability),
            ];
        }

        // Red: Low probability or critical
        return [
            'level' => 'red',
            'label' => 'Critical',
            'color' => '#EF4444',
            'message' => sprintf('Action needed - only %.0f%% chance of success. Urgent review required.', $probability),
        ];
    }

    /**
     * Analyze completed goal (past target date)
     *
     * @param  InvestmentGoal  $goal  Goal
     * @param  float  $currentValue  Current value
     * @return array Completion analysis
     */
    private function analyzeCompletedGoal(InvestmentGoal $goal, float $currentValue): array
    {
        $shortfall = $goal->target_value - $currentValue;
        $achieved = $currentValue >= $goal->target_value;

        return [
            'success' => true,
            'goal_name' => $goal->goal_name,
            'status' => 'completed',
            'achieved' => $achieved,
            'current_value' => $currentValue,
            'target_value' => $goal->target_value,
            'shortfall' => $achieved ? 0 : $shortfall,
            'surplus' => $achieved ? ($currentValue - $goal->target_value) : 0,
            'message' => $achieved
                ? sprintf('Goal achieved! Final value: £%s (target: £%s)', number_format($currentValue, 0), number_format($goal->target_value, 0))
                : sprintf('Goal not achieved. Shortfall: £%s', number_format(abs($shortfall), 0)),
        ];
    }

    /**
     * Calculate milestones
     *
     * @param  float  $currentValue  Current value
     * @param  float  $targetValue  Target value
     * @param  string  $targetDate  Target date
     * @return array Milestones
     */
    private function calculateMilestones(float $currentValue, float $targetValue, string $targetDate): array
    {
        $milestones = [];
        $percentages = [25, 50, 75, 90, 100];

        foreach ($percentages as $pct) {
            $milestoneValue = ($pct / 100) * $targetValue;
            $achieved = $currentValue >= $milestoneValue;

            $milestones[] = [
                'percentage' => $pct,
                'value' => $milestoneValue,
                'achieved' => $achieved,
                'label' => $pct === 100 ? 'Goal Complete' : "{$pct}% of target",
            ];
        }

        return $milestones;
    }

    /**
     * Calculate trajectory (projected future value)
     *
     * @param  float  $currentValue  Current value
     * @param  float  $monthlyContribution  Monthly contribution
     * @param  float  $expectedReturn  Expected annual return
     * @param  float  $years  Years to project
     * @return array Trajectory projection
     */
    private function calculateTrajectory(float $currentValue, float $monthlyContribution, float $expectedReturn, float $years): array
    {
        $months = (int) ($years * 12);
        $monthlyReturn = $expectedReturn / 12;

        $projectedValue = $currentValue;

        for ($i = 0; $i < $months; $i++) {
            $projectedValue *= (1 + $monthlyReturn);
            $projectedValue += $monthlyContribution;
        }

        // Calculate breakdown
        $totalContributions = $monthlyContribution * $months;
        $growth = $projectedValue - $currentValue - $totalContributions;

        return [
            'projected_value' => round($projectedValue, 2),
            'current_value' => $currentValue,
            'total_contributions' => $totalContributions,
            'projected_growth' => round($growth, 2),
            'growth_percent' => $currentValue > 0 ? round(($growth / $currentValue) * 100, 1) : 0,
        ];
    }

    /**
     * Generate recommendations based on status
     *
     * @param  array  $status  Status
     * @param  array  $probability  Probability analysis
     * @param  array|null  $contributionAnalysis  Contribution analysis
     * @param  array  $timeRemaining  Time remaining
     * @return array Recommendations
     */
    private function generateRecommendations(array $status, array $probability, ?array $contributionAnalysis, array $timeRemaining): array
    {
        $recommendations = [];

        // Based on status level
        if ($status['level'] === 'green') {
            $recommendations[] = [
                'priority' => 'low',
                'action' => 'maintain',
                'message' => 'Continue current contribution plan. On track to achieve goal.',
            ];

            // Consider glide path
            if ($timeRemaining['years'] <= 5) {
                $recommendations[] = [
                    'priority' => 'medium',
                    'action' => 'reduce_risk',
                    'message' => sprintf('Consider reducing equity allocation. With %.1f years remaining, protect accumulated gains.', $timeRemaining['years']),
                ];
            }
        } elseif ($status['level'] === 'amber') {
            if ($contributionAnalysis && $contributionAnalysis['increase_needed'] > 0) {
                $recommendations[] = [
                    'priority' => 'high',
                    'action' => 'increase_contributions',
                    'message' => sprintf('Increase monthly contribution by £%s to reach 85%% success probability.', number_format($contributionAnalysis['increase_needed'], 0)),
                ];
            }

            $recommendations[] = [
                'priority' => 'medium',
                'action' => 'review_allocation',
                'message' => 'Review asset allocation. Consider higher-growth investments if risk tolerance permits.',
            ];
        } else {
            // Red - critical
            $recommendations[] = [
                'priority' => 'critical',
                'action' => 'urgent_review',
                'message' => 'Urgent action required. Consider: 1) Significantly increase contributions, 2) Extend timeline, or 3) Reduce target.',
            ];

            if ($contributionAnalysis) {
                $recommendations[] = [
                    'priority' => 'critical',
                    'action' => 'increase_contributions',
                    'message' => sprintf('Minimum increase needed: £%s/month for 85%% success rate.', number_format($contributionAnalysis['increase_needed'], 0)),
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Determine overall status across all goals
     *
     * @param  array  $summary  Summary statistics
     * @return array Overall status
     */
    private function determineOverallStatus(array $summary): array
    {
        if ($summary['total_goals'] === 0) {
            return [
                'level' => 'none',
                'message' => 'No active goals',
            ];
        }

        $criticalPercent = ($summary['critical'] / $summary['total_goals']) * 100;
        $onTrackPercent = ($summary['on_track'] / $summary['total_goals']) * 100;

        if ($criticalPercent > 50) {
            return [
                'level' => 'red',
                'label' => 'Multiple Goals At Risk',
                'message' => sprintf('%d of %d goals need urgent attention', $summary['critical'], $summary['total_goals']),
            ];
        }

        if ($onTrackPercent >= 75) {
            return [
                'level' => 'green',
                'label' => 'Goals On Track',
                'message' => sprintf('%d of %d goals on track', $summary['on_track'], $summary['total_goals']),
            ];
        }

        return [
            'level' => 'orange',
            'label' => 'Some Goals Need Attention',
            'message' => sprintf('%d goals on track, %d need attention', $summary['on_track'], $summary['needs_attention'] + $summary['critical']),
        ];
    }
}
