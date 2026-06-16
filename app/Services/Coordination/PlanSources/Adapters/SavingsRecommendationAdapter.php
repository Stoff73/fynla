<?php

declare(strict_types=1);

namespace App\Services\Coordination\PlanSources\Adapters;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use Illuminate\Support\Str;

/**
 * Maps SavingsActionDefinitionService::buildRecommendation() output arrays into
 * the common StrategyRecommendation DTO. Savings recs are definition-driven and
 * carry a stable `definition_key`; that key is used directly as the `type` slug.
 * When definition_key is absent (inline/legacy fallback recs), a curated
 * category-to-strategy_type map is consulted first; unmapped categories fall
 * back to a slug so they still surface without catalogue metadata.
 *
 * Emergency-fund categories resolve to StrategyCategory::Warning (they surface
 * a data gap). All other savings categories resolve to StrategyCategory::Lifecycle.
 */
final class SavingsRecommendationAdapter
{
    /**
     * Categories that indicate a safety gap rather than a lifecycle optimisation.
     * These map to StrategyCategory::Warning so the composer can surface them first.
     */
    private const WARNING_CATEGORIES = [
        'Emergency Fund',
        'emergency_fund',
    ];

    /** Curated category-to-strategy_type for inline/legacy recs without a definition_key. */
    private const CATEGORY_TO_STRATEGY_TYPE = [
        'Emergency Fund' => 'build_emergency_fund',
        'emergency_fund' => 'build_emergency_fund',
        'Rate Optimisation' => 'move_to_high_interest',
        'rate_improvement' => 'move_to_high_interest',
        'Tax Efficiency' => 'move_to_high_interest',
        'isa_allowance' => 'regular_savings_habit',
        'Goal' => 'regular_savings_habit',
        'goal_behind_schedule' => 'regular_savings_habit',
        'create_emergency_fund_goal' => 'build_emergency_fund',
        'life_event_cash_buffer' => 'regular_savings_habit',
    ];

    public function toStrategyRecommendation(array $rec): StrategyRecommendation
    {
        // Prefer stable definition_key as the type slug (all DB-driven recs carry this).
        $definitionKey = (string) ($rec['definition_key'] ?? '');
        $category = (string) ($rec['category'] ?? '');

        $type = $definitionKey !== ''
            ? $definitionKey
            : (self::CATEGORY_TO_STRATEGY_TYPE[$category] ?? $this->slugify($category));

        $strategyCategory = in_array($category, self::WARNING_CATEGORIES, true)
            ? StrategyCategory::Warning
            : StrategyCategory::Lifecycle;

        $impact = strtolower((string) ($rec['impact'] ?? 'medium'));
        $priority = in_array($impact, ['high', 'medium', 'low'], true) ? $impact : 'medium';

        $extra = array_filter([
            'definition_key' => $definitionKey !== '' ? $definitionKey : null,
            'scope' => $rec['scope'] ?? null,
            'account_id' => $rec['account_id'] ?? null,
            'account_name' => $rec['account_name'] ?? null,
            'source_category' => $category !== '' ? $category : null,
            'decision_trace' => $rec['decision_trace'] ?? null,
        ], static fn ($v) => $v !== null);

        return new StrategyRecommendation(
            type: $type,
            category: $strategyCategory,
            priority: $priority,
            title: (string) ($rec['title'] ?? $rec['action'] ?? ''),
            description: (string) ($rec['description'] ?? ''),
            estimatedAnnualTaxSaved: null,
            requiresAdvice: false,
            extra: $extra,
            requiredMonthlyCost: null,
        );
    }

    private function slugify(string $value): string
    {
        $slug = Str::slug($value, '_');

        return $slug !== '' ? $slug : 'savings_action';
    }
}
