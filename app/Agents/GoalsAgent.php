<?php

declare(strict_types=1);

namespace App\Agents;

use App\Models\Goal;
use App\Models\User;
use App\Services\Goals\GoalAffordabilityService;
use App\Services\Goals\GoalAssignmentService;
use App\Services\Goals\GoalProgressService;
use App\Services\Goals\GoalRiskService;

/**
 * Goals Agent - orchestrates goals analysis and recommendations.
 */
class GoalsAgent extends BaseAgent
{
    private const SCENARIO_LUMP_SUM = 1000;

    public function __construct(
        private readonly GoalAssignmentService $assignmentService,
        private readonly GoalAffordabilityService $affordabilityService,
        private readonly GoalProgressService $progressService,
        private readonly GoalRiskService $riskService
    ) {}

    /**
     * Comprehensive goals analysis for a user.
     */
    public function analyze(int $userId): array
    {
        return $this->rememberForUser($userId, 'analysis', function () use ($userId) {
            $user = User::findOrFail($userId);
            $goals = Goal::forUserOrJoint($userId)
                ->get();

            if ($goals->isEmpty()) {
                return [
                    'has_goals' => false,
                    'message' => 'No goals found. Set your first financial goal to start tracking progress.',
                    'summary' => $this->getEmptySummary(),
                ];
            }

            $activeGoals = $goals->where('status', 'active');
            $completedGoals = $goals->where('status', 'completed');

            // Analyze by module
            $byModule = [
                'savings' => $this->analyzeModuleGoals($activeGoals->where('assigned_module', 'savings')),
                'investment' => $this->analyzeModuleGoals($activeGoals->where('assigned_module', 'investment')),
                'property' => $this->analyzeModuleGoals($activeGoals->where('assigned_module', 'property')),
                'retirement' => $this->analyzeModuleGoals($activeGoals->where('assigned_module', 'retirement')),
            ];

            // Overall summary
            $summary = $this->calculateSummary($activeGoals);

            // Top goals (by priority and progress)
            $topGoals = $this->getTopGoals($activeGoals);

            // Affordability analysis
            $affordability = $this->affordabilityService->analyzeAllGoals($user);

            // Best streak across all goals
            $bestStreak = $goals->max('contribution_streak') ?? 0;
            $longestEverStreak = $goals->max('longest_streak') ?? 0;

            return [
                'has_goals' => true,
                'summary' => $summary,
                'by_module' => $byModule,
                'top_goals' => $topGoals,
                'affordability' => $affordability,
                'streaks' => [
                    'best_current_streak' => $bestStreak,
                    'longest_ever_streak' => $longestEverStreak,
                ],
                'completed_count' => $completedGoals->count(),
                'goals_count' => $goals->count(),
            ];
        });
    }

    /**
     * Generate recommendations based on analysis.
     */
    public function generateRecommendations(array $analysisData): array
    {
        $recommendations = [];
        $priority = 1;

        if (! ($analysisData['has_goals'] ?? false)) {
            return [
                'recommendation_count' => 1,
                'recommendations' => [[
                    'category' => 'Getting Started',
                    'priority' => 1,
                    'title' => 'Set Your First Financial Goal',
                    'description' => 'People with clear financial goals are more likely to feel financially secure. Start with an emergency fund goal.',
                    'action' => 'Create an emergency fund goal for 3-6 months of expenses.',
                ]],
            ];
        }

        $summary = $analysisData['summary'] ?? [];
        $affordability = $analysisData['affordability'] ?? [];

        // Check for goals behind schedule
        $behindCount = $summary['behind_count'] ?? 0;
        if ($behindCount > 0) {
            $recommendations[] = [
                'category' => 'Progress',
                'priority' => $priority++,
                'title' => "{$behindCount} goal(s) falling behind schedule",
                'description' => 'Some goals are not on track to be achieved by their target date.',
                'action' => 'Review these goals and consider increasing contributions or extending timelines.',
            ];
        }

        // Check affordability
        if (($affordability['status'] ?? '') === 'overcommitted') {
            $recommendations[] = [
                'category' => 'Affordability',
                'priority' => $priority++,
                'title' => 'Goal commitments exceed available surplus',
                'description' => 'Your planned monthly contributions exceed your available savings capacity.',
                'action' => 'Prioritise essential goals and consider pausing or reducing others.',
            ];
        }

        // Check for no emergency fund
        $byModule = $analysisData['by_module'] ?? [];
        $savingsGoals = $byModule['savings']['goals'] ?? [];
        $hasEmergencyFund = collect($savingsGoals)->contains(fn ($g) => ($g['goal_type'] ?? '') === 'emergency_fund');

        if (! $hasEmergencyFund) {
            $recommendations[] = [
                'category' => 'Safety Net',
                'priority' => $priority++,
                'title' => 'No Emergency Fund Goal',
                'description' => 'An emergency fund provides financial security against unexpected expenses.',
                'action' => 'Create an emergency fund goal for 3-6 months of living expenses.',
            ];
        }

        // Check contribution streaks
        $bestStreak = $analysisData['streaks']['best_current_streak'] ?? 0;
        if ($bestStreak >= 3) {
            $recommendations[] = [
                'category' => 'Momentum',
                'priority' => $priority++,
                'title' => "Excellent! {$bestStreak}-month contribution streak",
                'description' => 'Consistency is key to achieving your financial goals.',
                'action' => 'Keep up the great work and maintain your contribution schedule.',
            ];
        }

        // Sort by priority
        usort($recommendations, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return [
            'recommendation_count' => count($recommendations),
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Build what-if scenarios for goal planning.
     */
    public function buildScenarios(int $userId, array $parameters): array
    {
        $goalId = $parameters['goal_id'] ?? null;
        $scenarios = [];

        if (! $goalId) {
            return [
                'scenario_count' => 0,
                'scenarios' => [],
                'message' => 'Please specify a goal_id to generate scenarios.',
            ];
        }

        $goal = Goal::where('user_id', $userId)->where('id', $goalId)->first();
        if (! $goal) {
            return [
                'scenario_count' => 0,
                'scenarios' => [],
                'message' => 'Goal not found.',
            ];
        }

        $currentContribution = (float) ($goal->monthly_contribution ?? 0);
        $currentAmount = (float) $goal->current_amount;
        $targetAmount = (float) $goal->target_amount;
        $remaining = $targetAmount - $currentAmount;

        // Scenario 1: Increase contribution by 20%
        if ($currentContribution > 0) {
            $increasedContribution = $currentContribution * 1.2;
            $monthsToGoal = $remaining / $increasedContribution;
            $scenarios[] = [
                'name' => 'Increase Contribution by 20%',
                'description' => sprintf('Increase monthly contribution to £%.0f', $increasedContribution),
                'parameters' => [
                    'monthly_contribution' => round($increasedContribution, 2),
                    'months_to_goal' => ceil($monthsToGoal),
                    'time_saved_months' => max(0, $goal->months_remaining - ceil($monthsToGoal)),
                ],
            ];
        }

        // Scenario 2: Reach goal 6 months earlier
        $monthsEarlier = max(1, $goal->months_remaining - 6);
        if ($monthsEarlier > 0) {
            $requiredContribution = $remaining / $monthsEarlier;
            $scenarios[] = [
                'name' => 'Reach Goal 6 Months Earlier',
                'description' => sprintf('Achieve goal by %s', now()->addMonths($monthsEarlier)->format('M Y')),
                'parameters' => [
                    'monthly_contribution' => round($requiredContribution, 2),
                    'months_to_goal' => $monthsEarlier,
                    'additional_per_month' => round(max(0, $requiredContribution - $currentContribution), 2),
                ],
            ];
        }

        // Scenario 3: Reduce target by 20%
        $reducedTarget = $targetAmount * 0.8;
        $reducedRemaining = max(0, $reducedTarget - $currentAmount);
        $scenarios[] = [
            'name' => 'Reduce Target by 20%',
            'description' => sprintf('Lower target amount to £%.0f', $reducedTarget),
            'parameters' => [
                'target_amount' => round($reducedTarget, 2),
                'amount_remaining' => round($reducedRemaining, 2),
                'months_to_goal' => $currentContribution > 0 ? ceil($reducedRemaining / $currentContribution) : null,
            ],
        ];

        // Scenario 4: Add lump sum
        $lumpSum = self::SCENARIO_LUMP_SUM;
        $monthsWithLumpSum = $currentContribution > 0 ? ($remaining - $lumpSum) / $currentContribution : null;
        $scenarios[] = [
            'name' => 'Add £'.number_format($lumpSum).' Lump Sum',
            'description' => 'One-time contribution to accelerate progress',
            'parameters' => [
                'lump_sum' => $lumpSum,
                'new_current_amount' => round($currentAmount + $lumpSum, 2),
                'months_saved' => $monthsWithLumpSum ? max(0, $goal->months_remaining - ceil($monthsWithLumpSum)) : null,
            ],
        ];

        return [
            'goal' => [
                'id' => $goal->id,
                'name' => $goal->goal_name,
                'target_amount' => $targetAmount,
                'current_amount' => $currentAmount,
                'monthly_contribution' => $currentContribution,
                'months_remaining' => $goal->months_remaining,
            ],
            'scenario_count' => count($scenarios),
            'scenarios' => $scenarios,
        ];
    }

    /**
     * Get dashboard overview data for the goals card.
     */
    public function getDashboardOverview(int $userId): array
    {
        $goals = Goal::forUserOrJoint($userId)
            ->get();

        if ($goals->isEmpty()) {
            return [
                'has_goals' => false,
                'total_goals' => 0,
                'on_track_count' => 0,
                'total_target' => 0,
                'total_current' => 0,
                'overall_progress' => 0,
                'top_goals' => [],
                'best_streak' => 0,
            ];
        }

        $activeGoals = $goals->where('status', 'active');
        $onTrackCount = $activeGoals->filter(fn ($g) => $g->is_on_track)->count();

        $totalTarget = $activeGoals->sum('target_amount');
        $totalCurrent = $activeGoals->sum('current_amount');
        $overallProgress = $totalTarget > 0 ? ($totalCurrent / $totalTarget) * 100 : 0;

        // Top 5 goals by priority then by progress
        $topGoals = $activeGoals
            ->sortBy([
                ['priority', 'asc'],
                ['progress_percentage', 'desc'],
            ])
            ->take(5)
            ->map(fn ($goal) => [
                'id' => $goal->id,
                'name' => $goal->goal_name,
                'goal_type' => $goal->goal_type,
                'display_goal_type' => $goal->display_goal_type,
                'assigned_module' => $goal->assigned_module,
                'progress_percentage' => $goal->progress_percentage,
                'is_on_track' => $goal->is_on_track,
                'days_remaining' => $goal->days_remaining,
                'target_amount' => round((float) $goal->target_amount, 2),
                'current_amount' => round((float) $goal->current_amount, 2),
            ])
            ->values()
            ->toArray();

        return [
            'has_goals' => true,
            'total_goals' => $activeGoals->count(),
            'on_track_count' => $onTrackCount,
            'total_target' => round($totalTarget, 2),
            'total_current' => round($totalCurrent, 2),
            'overall_progress' => round($overallProgress, 1),
            'top_goals' => $topGoals,
            'best_streak' => $goals->max('contribution_streak') ?? 0,
            'completed_this_year' => $goals
                ->where('status', 'completed')
                ->where('completed_at', '>=', now()->startOfYear())
                ->count(),
        ];
    }

    /**
     * Analyze goals for a specific module.
     */
    private function analyzeModuleGoals($goals): array
    {
        if ($goals->isEmpty()) {
            return [
                'count' => 0,
                'total_target' => 0,
                'total_current' => 0,
                'average_progress' => 0,
                'on_track_count' => 0,
                'goals' => [],
            ];
        }

        $totalTarget = $goals->sum('target_amount');
        $totalCurrent = $goals->sum('current_amount');
        $averageProgress = $goals->avg('progress_percentage');
        $onTrackCount = $goals->filter(fn ($g) => $g->is_on_track)->count();

        return [
            'count' => $goals->count(),
            'total_target' => round($totalTarget, 2),
            'total_current' => round($totalCurrent, 2),
            'average_progress' => round($averageProgress, 1),
            'on_track_count' => $onTrackCount,
            'goals' => $goals->map(fn ($goal) => [
                'id' => $goal->id,
                'goal_name' => $goal->goal_name,
                'goal_type' => $goal->goal_type,
                'target_amount' => round((float) $goal->target_amount, 2),
                'current_amount' => round((float) $goal->current_amount, 2),
                'progress_percentage' => $goal->progress_percentage,
                'is_on_track' => $goal->is_on_track,
                'priority' => $goal->priority,
            ])->values()->toArray(),
        ];
    }

    /**
     * Calculate overall summary.
     */
    private function calculateSummary($goals): array
    {
        if ($goals->isEmpty()) {
            return $this->getEmptySummary();
        }

        $totalTarget = $goals->sum('target_amount');
        $totalCurrent = $goals->sum('current_amount');
        $onTrackGoals = $goals->filter(fn ($g) => $g->is_on_track);
        $behindGoals = $goals->filter(fn ($g) => ! $g->is_on_track);

        return [
            'total_goals' => $goals->count(),
            'total_target' => round($totalTarget, 2),
            'total_current' => round($totalCurrent, 2),
            'overall_progress' => $totalTarget > 0 ? round(($totalCurrent / $totalTarget) * 100, 1) : 0,
            'on_track_count' => $onTrackGoals->count(),
            'behind_count' => $behindGoals->count(),
            'on_track_percentage' => $goals->count() > 0
                ? round(($onTrackGoals->count() / $goals->count()) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get empty summary structure.
     */
    private function getEmptySummary(): array
    {
        return [
            'total_goals' => 0,
            'total_target' => 0,
            'total_current' => 0,
            'overall_progress' => 0,
            'on_track_count' => 0,
            'behind_count' => 0,
            'on_track_percentage' => 0,
        ];
    }

    /**
     * Get top priority goals.
     */
    private function getTopGoals($goals, int $limit = 5): array
    {
        return $goals
            ->sortBy([
                fn ($a, $b) => $this->priorityOrder($a->priority) <=> $this->priorityOrder($b->priority),
                fn ($a, $b) => $b->progress_percentage <=> $a->progress_percentage,
            ])
            ->take($limit)
            ->map(fn ($goal) => [
                'id' => $goal->id,
                'goal_name' => $goal->goal_name,
                'goal_type' => $goal->goal_type,
                'display_goal_type' => $goal->display_goal_type,
                'assigned_module' => $goal->assigned_module,
                'target_amount' => round((float) $goal->target_amount, 2),
                'current_amount' => round((float) $goal->current_amount, 2),
                'progress_percentage' => $goal->progress_percentage,
                'is_on_track' => $goal->is_on_track,
                'days_remaining' => $goal->days_remaining,
                'priority' => $goal->priority,
                'contribution_streak' => $goal->contribution_streak,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get priority order for sorting.
     */
    private function priorityOrder(string $priority): int
    {
        return match ($priority) {
            'critical' => 1,
            'high' => 2,
            'medium' => 3,
            'low' => 4,
            default => 5,
        };
    }

    /**
     * Clear cache for a user.
     */
    public function clearCache(int $userId): void
    {
        $this->clearUserCache($userId);
    }
}
