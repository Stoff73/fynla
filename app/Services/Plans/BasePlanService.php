<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Models\Goal;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Traits\FormatsCurrency;
use App\Traits\ResolvesExpenditure;

abstract class BasePlanService
{
    use FormatsCurrency;
    use ResolvesExpenditure;

    /**
     * Generate a complete plan for the given user.
     */
    abstract public function generatePlan(int $userId, array $options = []): array;

    /**
     * Get actionable recommendations for the plan.
     *
     * @param  int  $userId  User ID
     * @param  array|null  $preComputedData  Optional pre-computed analysis data to avoid redundant calls
     */
    abstract public function getRecommendations(int $userId, ?array $preComputedData = null): array;

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
            'savings' => (clone $baseQuery)->whereIn('assigned_module', ['savings', 'investment'])->get(),
            'retirement' => (clone $baseQuery)->where(function ($q) {
                $q->where('assigned_module', 'retirement')
                    ->orWhere('goal_type', 'retirement');
            })->get(),
            'protection' => (clone $baseQuery)->where('assigned_module', 'protection')->get(),
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
            'description' => $goal->description,
            'is_essential' => (bool) $goal->is_essential,
            'funding_source' => $this->resolveFundingSource($goal),
        ];
    }

    /** Liquid cash account types safe to recommend as a funding source. */
    private const CASH_ACCOUNT_TYPES = [
        'current_account',
        'instant_access',
        'business_current',
        'business_savings',
    ];

    /**
     * Resolve the best non-tax-event funding source for a goal top-up.
     *
     * Priority order:
     * 1. Liquid cash accounts (current / instant access, non-ISA) — only if
     *    withdrawal won't breach the 6-month emergency fund threshold.
     * 2. GIA — with a Capital Gains Tax warning explaining why cash wasn't used.
     * 3. null — no suitable source found.
     *
     * Never recommended: ISA, premium bonds, notice accounts, pensions, VCT/EIS.
     *
     * @return array{name: string|null, warning: string|null}
     */
    private function resolveFundingSource(Goal $goal): array
    {
        $userId = $goal->user_id;
        $user = User::find($userId);
        $lumpSumNeeded = max(0, (float) $goal->target_amount - (float) $goal->current_amount);

        // Calculate the 6-month emergency threshold
        $monthlyExpenditure = $user ? $this->resolveMonthlyExpenditure($user)['amount'] : 0.0;
        $emergencyThreshold = $monthlyExpenditure * 6;

        // 1. Try liquid cash accounts (non-ISA, non-premium-bonds, non-notice)
        $cashAccounts = SavingsAccount::where('user_id', $userId)
            ->where('is_isa', false)
            ->whereIn('account_type', self::CASH_ACCOUNT_TYPES)
            ->orderByDesc('current_balance')
            ->get();

        foreach ($cashAccounts as $account) {
            $balance = (float) $account->current_balance;
            $balanceAfterWithdrawal = $balance - $lumpSumNeeded;

            if ($balanceAfterWithdrawal >= $emergencyThreshold) {
                return [
                    'name' => $account->account_name ?? $account->institution ?? null,
                    'warning' => null,
                ];
            }
        }

        // 2. Fall back to GIA only (exclude ISA, pension, VCT, EIS, and employee schemes)
        $gia = InvestmentAccount::where('user_id', $userId)
            ->where('account_type', 'gia')
            ->orderByDesc('current_value')
            ->first();

        if ($gia) {
            return [
                'name' => $gia->account_name ?? $gia->provider ?? null,
                'warning' => 'Selling investments may trigger a Capital Gains Tax event. Cash accounts were not recommended as withdrawing would reduce your emergency fund below 6 months of expenditure.',
            ];
        }

        return ['name' => null, 'warning' => null];
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
     * Get the user's first name for personalised narratives.
     */
    protected function getUserFirstName(User $user): string
    {
        return $user->first_name ?? explode(' ', $user->name)[0] ?? 'there';
    }

    /**
     * Structure recommendations into actions and apply any filter from options.
     *
     * @return array{actions: array, enabledActions: array}
     */
    protected function prepareActions(array $recommendations, string $planType, array $options = []): array
    {
        $actions = $this->structureActions($recommendations, $planType);
        $actions = $this->applyActionFilter($actions, $options);
        $enabledActions = collect($actions)->where('enabled', true)->values()->toArray();

        return ['actions' => $actions, 'enabledActions' => $enabledActions];
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
                'title' => $rec['title'] ?? $rec['headline'] ?? $rec['action'] ?? $rec['category'] ?? 'Recommendation',
                'description' => $rec['description'] ?? $rec['explanation'] ?? $rec['rationale'] ?? $rec['action'] ?? '',
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
                'funding_source' => $rec['funding_source'] ?? null,
                'affordability' => $rec['affordability'] ?? null,
                'affordability_warning' => $rec['affordability_warning'] ?? null,
                'guidance' => $rec['guidance'] ?? null,
                'decision_trace' => $rec['decision_trace'] ?? [],
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
        $all = collect($enabledActions);
        $actionCount = $all->count();
        $highPriorityCount = $all->where('priority', 'high')->count();
        $criticalCount = $all->where('priority', 'critical')->count();

        // Split into essential (critical/high) and optional (medium/low)
        $essential = $all->whereIn('priority', ['critical', 'high'])->values();
        $optional = $all->whereIn('priority', ['medium', 'low'])->values();

        $summaryParts = [];

        $goalPhrase = match ($planType) {
            'protection' => 'closing your protection gaps',
            'investment' => 'reaching your investment goals',
            'savings' => 'optimising your savings position',
            'estate' => 'securing your estate plan',
            default => 'reaching your retirement goal',
        };

        if ($essential->isNotEmpty()) {
            $summaryParts[] = sprintf(
                'There %s %d action%s that %s essential to %s.',
                $essential->count() === 1 ? 'is' : 'are',
                $essential->count(),
                $essential->count() === 1 ? '' : 's',
                $essential->count() === 1 ? 'is' : 'are',
                $goalPhrase
            );
        }

        if ($optional->isNotEmpty()) {
            $summaryParts[] = sprintf(
                'A further %d action%s %s optional but would strengthen your position.',
                $optional->count(),
                $optional->count() === 1 ? '' : 's',
                $optional->count() === 1 ? 'is' : 'are'
            );
        }

        if ($actionCount === 0) {
            $summaryParts[] = 'No actions are currently selected. If recommendations were available above, consider enabling them to see their projected impact.';
        }

        return [
            'summary_text' => implode(' ', $summaryParts),
            'total_actions' => $actionCount,
            'critical_actions' => $criticalCount,
            'high_priority_actions' => $highPriorityCount,
            'essential_actions' => $essential->map(fn ($a) => [
                'title' => $a['title'],
                'priority' => $a['priority'],
            ])->toArray(),
            'optional_actions' => $optional->map(fn ($a) => [
                'title' => $a['title'],
                'priority' => $a['priority'],
            ])->toArray(),
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
     * Project future value: FV = PV*(1+r)^n + PMT*((1+r)^n - 1)/r  (monthly compounding)
     *
     * @param  float  $presentValue  Current lump sum
     * @param  float  $annualRate  Annual growth rate (e.g. 0.04)
     * @param  int  $years  Projection horizon
     * @param  float  $monthlyContribution  Regular monthly addition
     */
    protected function projectFutureValue(float $presentValue, float $annualRate, int $years, float $monthlyContribution = 0): float
    {
        if ($years <= 0) {
            return $presentValue;
        }

        if ($annualRate <= 0) {
            return $presentValue + ($monthlyContribution * 12 * $years);
        }

        $monthlyRate = $annualRate / 12;
        $months = $years * 12;
        $fv = $presentValue * pow(1 + $monthlyRate, $months);

        if ($monthlyContribution > 0 && $monthlyRate > 0) {
            $fv += $monthlyContribution * ((pow(1 + $monthlyRate, $months) - 1) / $monthlyRate);
        }

        return $fv;
    }

    /**
     * Round a monetary value to 2 decimal places.
     */
    protected function roundToPenny(float $value): float
    {
        return round($value, 2);
    }
}
