<?php

declare(strict_types=1);

namespace App\Services\Goals;

use App\Models\Goal;
use App\Models\User;

/**
 * Goal Strategy Service
 *
 * Generates strategy summaries for goals within a specific module,
 * suitable for display within that module's section.
 */
class GoalStrategyService
{
    public function __construct(
        private readonly GoalProgressService $progressService,
        private readonly GoalAffordabilityService $affordabilityService,
        private readonly GoalRiskService $riskService,
        private readonly GoalAssignmentService $assignmentService
    ) {}

    /**
     * Get strategies for all active goals assigned to a specific module.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getStrategiesForModule(int $userId, string $module): array
    {
        $user = User::findOrFail($userId);

        $goals = Goal::with(['linkedSavingsAccount', 'linkedInvestmentAccount'])
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhere('joint_owner_id', $userId);
            })
            ->where('status', 'active')
            ->where('assigned_module', $module)
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
            ->orderBy('target_date')
            ->get();

        return $goals->map(fn (Goal $goal) => $this->buildStrategy($goal, $user))->toArray();
    }

    /**
     * Get the full strategy for a single goal.
     *
     * @return array<string, mixed>
     */
    public function getStrategyForGoal(int $goalId): array
    {
        $goal = Goal::findOrFail($goalId);
        $user = User::findOrFail($goal->user_id);

        return $this->buildStrategy($goal, $user);
    }

    /**
     * Get aggregate summary of goals for a module.
     *
     * @return array<string, mixed>
     */
    public function getModuleGoalsSummary(int $userId, string $module): array
    {
        $user = User::findOrFail($userId);

        $goals = Goal::where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
                ->orWhere('joint_owner_id', $userId);
        })
            ->where('status', 'active')
            ->where('assigned_module', $module)
            ->get();

        if ($goals->isEmpty()) {
            return [
                'total_goals' => 0,
                'total_target' => 0,
                'total_current' => 0,
                'overall_progress' => 0,
                'total_monthly_commitment' => 0,
                'on_track_count' => 0,
                'behind_count' => 0,
                'affordability_status' => null,
            ];
        }

        $totalTarget = $goals->sum('target_amount');
        $totalCurrent = $goals->sum('current_amount');
        $totalMonthly = $goals->sum('monthly_contribution');
        $onTrackCount = $goals->filter(fn (Goal $g) => $g->is_on_track)->count();

        // Calculate affordability for total commitment
        $monthlySurplus = $this->affordabilityService->calculateMonthlySurplus($user);
        $commitmentRatio = $monthlySurplus > 0 ? $totalMonthly / $monthlySurplus : 0;

        return [
            'total_goals' => $goals->count(),
            'total_target' => round((float) $totalTarget, 2),
            'total_current' => round((float) $totalCurrent, 2),
            'overall_progress' => $totalTarget > 0
                ? round(($totalCurrent / $totalTarget) * 100, 1)
                : 0,
            'total_monthly_commitment' => round((float) $totalMonthly, 2),
            'on_track_count' => $onTrackCount,
            'behind_count' => $goals->count() - $onTrackCount,
            'affordability_status' => $this->getAffordabilityStatus($commitmentRatio, $monthlySurplus),
        ];
    }

    /**
     * Build the full strategy for a goal.
     *
     * @return array<string, mixed>
     */
    private function buildStrategy(Goal $goal, User $user): array
    {
        $progress = $this->progressService->calculateProgress($goal);
        $affordability = $this->affordabilityService->analyzeAffordability($goal, $user);
        $streak = $this->progressService->getStreakDisplay($goal);

        $strategy = [
            'goal' => [
                'id' => $goal->id,
                'name' => $goal->goal_name,
                'type' => $goal->goal_type,
                'display_type' => $goal->display_goal_type,
                'target_amount' => round((float) $goal->target_amount, 2),
                'current_amount' => round((float) $goal->current_amount, 2),
                'progress_percentage' => $progress['progress_percentage'],
                'target_date' => $goal->target_date?->toDateString(),
                'priority' => $goal->priority,
                'status' => $goal->status,
                'assigned_module' => $goal->assigned_module,
                'is_on_track' => $progress['is_on_track'],
                'progress_status' => $progress['status'],
            ],
            'contribution_plan' => [
                'monthly_amount' => round((float) ($goal->monthly_contribution ?? 0), 2),
                'frequency' => $goal->contribution_frequency ?? 'monthly',
                'next_due' => $this->calculateNextDueDate($goal),
                'streak' => $streak,
                'is_on_track' => $progress['is_on_track'],
                'required_monthly_to_stay_on_track' => $affordability['required_monthly'],
            ],
            'affordability' => [
                'category' => $affordability['category'],
                'category_label' => $affordability['category_label'],
                'ratio' => $affordability['affordability_ratio'],
                'monthly_surplus_after_goal' => $affordability['available_surplus'],
                'is_achievable' => $affordability['is_achievable'],
            ],
            'linked_accounts' => $this->getLinkedAccounts($goal),
            'recommendations' => $this->generateGoalRecommendations($goal, $progress, $affordability),
        ];

        // Add projections for investment and retirement goals
        if (in_array($goal->assigned_module, ['investment', 'retirement'])) {
            $riskProfile = $this->riskService->getUserRiskProfile($user);
            $projections = $this->riskService->getProjections($goal, $riskProfile);

            $strategy['projections'] = [
                'expected_completion_date' => $this->estimateCompletionDate($goal, $projections),
                'probability_of_success' => $projections['projections']['probability_of_success'] ?? null,
                'recommended_allocation' => $this->assignmentService->getRecommendedAllocation([
                    'target_date' => $goal->target_date?->toDateString(),
                ]),
            ];
        }

        return $strategy;
    }

    /**
     * Calculate when the next contribution is due.
     */
    private function calculateNextDueDate(Goal $goal): ?string
    {
        $lastDate = $goal->last_contribution_date;
        $frequency = $goal->contribution_frequency ?? 'monthly';

        if ($lastDate === null) {
            return now()->toDateString();
        }

        $nextDue = match ($frequency) {
            'weekly' => $lastDate->addWeek(),
            'quarterly' => $lastDate->addMonths(3),
            'annually' => $lastDate->addYear(),
            default => $lastDate->addMonth(),
        };

        return $nextDue->toDateString();
    }

    /**
     * Get linked account details for a goal.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getLinkedAccounts(Goal $goal): array
    {
        $accounts = [];

        if ($goal->linkedSavingsAccount) {
            $account = $goal->linkedSavingsAccount;
            $accounts[] = [
                'id' => $account->id,
                'name' => $account->account_name,
                'type' => 'savings',
                'current_balance' => round((float) $account->current_balance, 2),
            ];
        }

        if ($goal->linkedInvestmentAccount) {
            $account = $goal->linkedInvestmentAccount;
            $accounts[] = [
                'id' => $account->id,
                'name' => $account->account_name,
                'type' => 'investment',
                'current_value' => round((float) $account->current_value, 2),
            ];
        }

        return $accounts;
    }

    /**
     * Generate actionable recommendations for a goal.
     *
     * @return string[]
     */
    private function generateGoalRecommendations(Goal $goal, array $progress, array $affordability): array
    {
        $recommendations = [];

        // Behind schedule
        if ($progress['status'] === 'behind' || $progress['status'] === 'slightly_behind') {
            $gap = $affordability['required_monthly'] - ($goal->monthly_contribution ?? 0);
            if ($gap > 0) {
                $recommendations[] = sprintf(
                    'Increase your monthly contribution by %s to get back on track.',
                    '£'.number_format($gap, 0)
                );
            }
        }

        // No contributions yet
        if ((float) $goal->current_amount === 0.0 && $goal->status === 'active') {
            $recommendations[] = 'Make your first contribution to start building towards this goal.';
        }

        // Overcommitted
        if ($affordability['category'] === 'overcommitted') {
            $recommendations[] = 'Consider extending your target date or reducing the target amount to make this goal more achievable.';
        }

        // Streak broken
        if (($goal->contribution_streak ?? 0) === 0 && ($goal->longest_streak ?? 0) > 0) {
            $recommendations[] = 'Your contribution streak has ended. Make a contribution to start a new one.';
        }

        // No linked account
        if ($goal->assigned_module === 'savings' && $goal->linked_savings_account_id === null) {
            $recommendations[] = 'Link a savings account to this goal to track progress automatically.';
        }

        return array_slice($recommendations, 0, 3);
    }

    /**
     * Estimate when the goal will be completed based on projections.
     */
    private function estimateCompletionDate(Goal $goal, array $projections): ?string
    {
        $projectionData = $projections['projections'] ?? null;
        if ($projectionData === null) {
            return null;
        }

        $expectedFinal = $projectionData['expected_final_value'] ?? 0;
        $target = $projectionData['target_amount'] ?? 0;

        if ($expectedFinal >= $target) {
            return $goal->target_date?->toDateString();
        }

        // If behind, estimate when we'll reach target at current rate
        $monthlyContribution = (float) ($goal->monthly_contribution ?? 0);
        if ($monthlyContribution <= 0) {
            return null;
        }

        $remaining = (float) $goal->target_amount - (float) $goal->current_amount;
        $monthsNeeded = (int) ceil($remaining / $monthlyContribution);

        return now()->addMonths($monthsNeeded)->toDateString();
    }

    /**
     * Get a simple affordability status string for the module summary.
     */
    private function getAffordabilityStatus(float $commitmentRatio, float $surplus): string
    {
        if ($surplus <= 0) {
            return 'no_surplus';
        }
        if ($commitmentRatio <= 0.3) {
            return 'comfortable';
        }
        if ($commitmentRatio <= 0.5) {
            return 'moderate';
        }
        if ($commitmentRatio <= 0.75) {
            return 'challenging';
        }

        return 'stretched';
    }
}
