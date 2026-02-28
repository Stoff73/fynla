<?php

declare(strict_types=1);

namespace App\Services\Plans;

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
     * Transform agent recommendations into toggleable action cards.
     *
     * @param  array  $recommendations  Raw recommendations from an Agent
     * @param  string  $planType  The plan type for ID prefixing
     * @return array<int, array>  Structured action cards
     */
    protected function structureActions(array $recommendations, string $planType): array
    {
        $actions = [];

        foreach ($recommendations as $index => $rec) {
            $actions[] = [
                'id' => $planType . '_action_' . ($index + 1),
                'title' => $rec['title'] ?? $rec['action'] ?? $rec['category'] ?? 'Recommendation',
                'description' => $rec['description'] ?? $rec['rationale'] ?? $rec['action'] ?? '',
                'category' => $rec['category'] ?? 'General',
                'priority' => $this->normalisePriority($rec['priority'] ?? $rec['impact'] ?? 'medium'),
                'enabled' => true,
                'estimated_impact' => $rec['estimated_impact'] ?? $rec['potential_saving'] ?? $rec['estimated_cost'] ?? null,
                'impact_parameters' => $rec['impact_parameters'] ?? [],
                'action_detail' => $rec['action'] ?? null,
            ];
        }

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
