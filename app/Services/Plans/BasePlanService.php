<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Models\Goal;
use App\Models\User;
use App\Traits\FormatsCurrency;

abstract class BasePlanService
{
    use FormatsCurrency;

    /**
     * Generate a complete plan for the given user.
     */
    abstract public function generatePlan(int $userId, array $options = []): array;

    /**
     * Get actionable recommendations for the plan.
     */
    abstract public function getRecommendations(int $userId): array;

    /**
     * Check what data is available/missing for this plan type.
     */
    abstract public function checkDataCompleteness(int $userId): array;

    /**
     * Fetch goals relevant to this plan type, split into linked and unlinked.
     *
     * @return array{linked: array, unlinked: array}
     */
    protected function getGoalsForPlan(int $userId, string $planType): array
    {
        $baseQuery = Goal::query()->active()->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId);
        });

        $goals = match ($planType) {
            'investment' => (clone $baseQuery)->whereIn('assigned_module', ['investment', 'savings'])->get(),
            'retirement' => (clone $baseQuery)->where(function ($q) {
                $q->where('assigned_module', 'retirement')
                    ->orWhere('goal_type', 'retirement');
            })->get(),
            'estate' => collect(),
            default => collect(),
        };

        $linked = [];
        $unlinked = [];

        foreach ($goals as $goal) {
            $formatted = $this->formatGoalForPlan($goal);
            if ($goal->linked_savings_account_id || $goal->linked_investment_account_id) {
                $linked[] = $formatted;
            } else {
                $unlinked[] = $formatted;
            }
        }

        return ['linked' => $linked, 'unlinked' => $unlinked];
    }

    /**
     * Map a Goal model to a plan-friendly array.
     */
    protected function formatGoalForPlan(Goal $goal): array
    {
        return [
            'id' => $goal->id,
            'name' => $goal->goal_name,
            'type' => $goal->goal_type,
            'display_type' => $goal->display_goal_type,
            'assigned_module' => $goal->assigned_module,
            'priority' => $goal->priority,
            'target_amount' => (float) $goal->target_amount,
            'current_amount' => (float) $goal->current_amount,
            'progress_percentage' => $goal->progress_percentage,
            'is_on_track' => $goal->is_on_track,
            'target_date' => $goal->target_date?->toDateString(),
            'months_remaining' => $goal->months_remaining,
            'monthly_contribution' => (float) ($goal->monthly_contribution ?? 0),
            'required_monthly_contribution' => $goal->required_monthly_contribution,
            'linked_savings_account_id' => $goal->linked_savings_account_id,
            'linked_investment_account_id' => $goal->linked_investment_account_id,
        ];
    }

    /**
     * Build recommendations for goals that are off-track or need attention.
     */
    protected function buildGoalRecommendations(array $linkedGoals): array
    {
        $recommendations = [];

        foreach ($linkedGoals as $goal) {
            $progress = $goal['progress_percentage'] ?? 0;
            $monthsRemaining = $goal['months_remaining'] ?? 0;
            $monthlyContribution = $goal['monthly_contribution'] ?? 0;
            $isComplete = $progress >= 100;

            if ($isComplete) {
                continue;
            }

            if ($monthlyContribution <= 0 && ($goal['required_monthly_contribution'] ?? 0) > 0) {
                $recommendations[] = [
                    'title' => "Start contributing to {$goal['name']}",
                    'description' => sprintf(
                        'You have not set a monthly contribution for %s. Contributing %s per month would help you reach your target of %s.',
                        $goal['name'],
                        $this->formatCurrency($goal['required_monthly_contribution']),
                        $this->formatCurrency($goal['target_amount'])
                    ),
                    'category' => 'Goal',
                    'priority' => 'high',
                    'source' => 'goal',
                    'goal_id' => $goal['id'],
                ];
            } elseif (! $goal['is_on_track']) {
                $shortfall = max(0, $goal['required_monthly_contribution'] - $monthlyContribution);
                $recommendations[] = [
                    'title' => "{$goal['name']} is behind schedule",
                    'description' => sprintf(
                        '%s is currently %.0f%% complete but behind schedule. Increasing your monthly contribution by %s would bring it back on track.',
                        $goal['name'],
                        $progress,
                        $this->formatCurrency($shortfall)
                    ),
                    'category' => 'Goal',
                    'priority' => 'high',
                    'source' => 'goal',
                    'goal_id' => $goal['id'],
                ];
            } elseif ($monthsRemaining <= 6 && $progress < 75) {
                $recommendations[] = [
                    'title' => "{$goal['name']} target date is approaching",
                    'description' => sprintf(
                        '%s is only %.0f%% complete with %d months remaining. Consider increasing your contributions to reach your target of %s on time.',
                        $goal['name'],
                        $progress,
                        $monthsRemaining,
                        $this->formatCurrency($goal['target_amount'])
                    ),
                    'category' => 'Goal',
                    'priority' => 'medium',
                    'source' => 'goal',
                    'goal_id' => $goal['id'],
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Transform agent recommendations into toggleable action cards.
     *
     * @param  array  $recommendations  Raw recommendations from an Agent
     * @param  string  $planType  The plan type for ID prefixing
     * @return array<int, array> Structured action cards
     */
    protected function structureActions(array $recommendations, string $planType): array
    {
        $actions = [];

        foreach ($recommendations as $rec) {
            $actions[] = [
                'title' => $rec['title'] ?? $rec['action'] ?? $rec['category'] ?? 'Recommendation',
                'description' => $rec['description'] ?? $rec['rationale'] ?? $rec['action'] ?? '',
                'category' => $rec['category'] ?? 'General',
                'priority' => $this->normalisePriority($rec['priority'] ?? $rec['impact'] ?? 'medium'),
                'enabled' => true,
                'estimated_impact' => $rec['estimated_impact'] ?? $rec['potential_saving'] ?? $rec['estimated_cost'] ?? null,
                'impact_parameters' => $rec['impact_parameters'] ?? [],
                'action_detail' => $rec['action'] ?? null,
                'scope' => $rec['scope'] ?? 'portfolio',
                'account_id' => $rec['account_id'] ?? null,
                'account_name' => $rec['account_name'] ?? null,
                'source' => $rec['source'] ?? 'module',
                'goal_id' => $rec['goal_id'] ?? null,
            ];
        }

        // Sort: goal-sourced actions first, then by priority within each group
        $priorityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        usort($actions, function ($a, $b) use ($priorityOrder) {
            $aIsGoal = ($a['source'] === 'goal') ? 0 : 1;
            $bIsGoal = ($b['source'] === 'goal') ? 0 : 1;
            if ($aIsGoal !== $bIsGoal) {
                return $aIsGoal - $bIsGoal;
            }

            return ($priorityOrder[$a['priority']] ?? 2) - ($priorityOrder[$b['priority']] ?? 2);
        });

        // Re-index IDs after sorting
        foreach ($actions as $index => &$action) {
            $action['id'] = $planType.'_action_'.($index + 1);
        }
        unset($action);

        return $actions;
    }

    /**
     * Build plan metadata envelope.
     */
    protected function buildPlanMetadata(User $user, string $planType, array $completeness): array
    {
        return [
            'plan_type' => $planType,
            'generated_at' => now()->toIso8601String(),
            'user_name' => $user->name,
            'user_id' => $user->id,
            'data_completeness' => $completeness,
            'has_warnings' => ! empty($completeness['missing']),
        ];
    }

    /**
     * Build a completeness warning structure for missing data.
     */
    protected function buildCompletenessWarning(array $completeness): ?array
    {
        if (empty($completeness['missing'])) {
            return null;
        }

        return [
            'level' => count($completeness['missing']) > 2 ? 'significant' : 'minor',
            'message' => 'Some data is missing which may affect the accuracy of this plan.',
            'missing_items' => $completeness['missing'],
            'completeness_percentage' => $completeness['percentage'] ?? 0,
        ];
    }

    /**
     * Generate a dynamic conclusion based on current situation and enabled actions.
     */
    public function generateDynamicConclusion(array $currentSituation, array $enabledActions, string $planType): array
    {
        $actionCount = count($enabledActions);
        $highPriorityCount = collect($enabledActions)->where('priority', 'high')->count();
        $criticalCount = collect($enabledActions)->where('priority', 'critical')->count();

        $summaryParts = [];

        if ($criticalCount > 0) {
            $summaryParts[] = "There are {$criticalCount} critical action(s) that should be addressed immediately.";
        }

        if ($highPriorityCount > 0) {
            $summaryParts[] = "{$highPriorityCount} high-priority recommendation(s) could significantly improve your position.";
        }

        if ($actionCount === 0) {
            $summaryParts[] = 'No actions are currently selected. If recommendations were available above, consider enabling them to see their projected impact.';
        } elseif ($actionCount > 0 && $criticalCount === 0) {
            $summaryParts[] = "Implementing the {$actionCount} recommended action(s) would strengthen your financial plan.";
        }

        return [
            'summary_text' => implode(' ', $summaryParts),
            'total_actions' => $actionCount,
            'critical_actions' => $criticalCount,
            'high_priority_actions' => $highPriorityCount,
            'detailed_breakdown' => $this->buildDetailedBreakdown($enabledActions),
        ];
    }

    /**
     * Build a detailed breakdown of enabled actions by category.
     */
    protected function buildDetailedBreakdown(array $enabledActions): array
    {
        return collect($enabledActions)
            ->groupBy('category')
            ->map(function ($group, $category) {
                return [
                    'category' => $category,
                    'action_count' => $group->count(),
                    'actions' => $group->pluck('title')->toArray(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Apply action filter from options (used by WhatIfCalculator recalculation).
     */
    protected function applyActionFilter(array $actions, array $options): array
    {
        if (! array_key_exists('enabled_action_ids', $options)) {
            return $actions;
        }

        $enabledIds = $options['enabled_action_ids'];

        return array_map(function ($action) use ($enabledIds) {
            $action['enabled'] = in_array($action['id'], $enabledIds, true);

            return $action;
        }, $actions);
    }

    /**
     * Normalise priority values to a consistent set.
     */
    protected function normalisePriority(mixed $priority): string
    {
        if (is_int($priority)) {
            return match (true) {
                $priority <= 1 => 'critical',
                $priority <= 3 => 'high',
                $priority <= 5 => 'medium',
                default => 'low',
            };
        }

        $priority = strtolower((string) $priority);

        return match ($priority) {
            'critical', 'urgent' => 'critical',
            'high' => 'high',
            'medium', 'moderate' => 'medium',
            'low' => 'low',
            default => 'medium',
        };
    }

    /**
     * Round a monetary value to 2 decimal places.
     */
    protected function roundToPenny(float $value): float
    {
        return round($value, 2);
    }
}
