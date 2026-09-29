<?php

declare(strict_types=1);

namespace App\Services\Coordination\PlanSources\Adapters;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Services\Coordination\PriorityRanker;
use Illuminate\Support\Str;

/**
 * Maps ProtectionActionDefinitionService::evaluateActions() recs (CSJ
 * 2026-09-29: protection cards come from the definitions, as every other
 * module's do). `type` is the rec's `definition_key`, so each card has a
 * stable id and its own how-to; a rec without one falls back to the curated
 * category map, then a slug.
 *
 * Key field differences from Retirement recs:
 *   - `title` is usually absent; mapped FROM `$rec['action']`
 *   - `description` is usually absent; mapped FROM `$rec['rationale']`
 *   - `priority` is an int 1-5 (1=most urgent), also accepts `impact` High/Med/Low
 *   - `estimated_cost` is a monthly premium estimate, NOT a plan cost;
 *     carried in `extra` as `estimated_premium` rather than `requiredMonthlyCost`
 *   - `requiresAdvice` is always true (protection cover requires regulated advice)
 */
final class ProtectionRecommendationAdapter
{
    /**
     * Curated category-to-strategy_type map.
     * Keep in sync with ProtectionActionDefinitionSeeder strategy rows.
     */
    private const CATEGORY_TO_STRATEGY_TYPE = [
        'Life Insurance' => 'protection_life_cover_gap',
        'Critical Illness' => 'protection_critical_illness_gap',
        'Income Protection' => 'protection_income_protection_gap',
        'Trust Planning' => 'protection_policy_in_trust',
        'Policy Optimisation' => 'protection_policy_review',
    ];

    /**
     * Gap categories that map to StrategyCategory::Warning (cover deficits that
     * expose the user to immediate financial risk if the unexpected happens).
     * Optimisation and trust rows map to Lifecycle (improvements rather than gaps).
     */
    private const WARNING_CATEGORIES = [
        'Life Insurance',
        'Critical Illness',
        'Income Protection',
    ];

    public function toStrategyRecommendation(array $rec): StrategyRecommendation
    {
        $category = (string) ($rec['category'] ?? '');
        $definitionKey = (string) ($rec['definition_key'] ?? '');
        $type = $definitionKey !== ''
            ? $definitionKey
            : (self::CATEGORY_TO_STRATEGY_TYPE[$category] ?? $this->slugify($category));

        $strategyCategory = in_array($category, self::WARNING_CATEGORIES, true)
            ? StrategyCategory::Warning
            : StrategyCategory::Lifecycle;

        $seeded = PriorityRanker::priorityLabel($rec);
        $priority = PriorityRanker::impactLabel($seeded);

        $extra = array_filter([
            'seeded_priority' => $seeded,
            'estimated_premium' => isset($rec['estimated_cost']) && is_numeric($rec['estimated_cost'])
                ? (float) $rec['estimated_cost']
                : null,
            'source_category' => $category !== '' ? $category : null,
            'impact' => isset($rec['impact']) ? (string) $rec['impact'] : null,
            'decision_trace' => $rec['decision_trace'] ?? null,
            'definition_key' => $definitionKey !== '' ? $definitionKey : null,
            // The policy a per-policy rule is about — the aggregator scopes the id by it.
            'policy_id' => $rec['policy_id'] ?? null,
            'figures' => ! empty($rec['figures']) ? $rec['figures'] : null,
        ], static fn ($v) => $v !== null);

        return new StrategyRecommendation(
            type: $type,
            category: $strategyCategory,
            priority: $priority,
            title: (string) ($rec['title'] ?? $rec['action'] ?? ''),
            description: (string) ($rec['description'] ?? $rec['rationale'] ?? ''),
            estimatedAnnualTaxSaved: null,
            requiresAdvice: true,
            extra: $extra,
            requiredMonthlyCost: null,
        );
    }

    private function slugify(string $value): string
    {
        $slug = Str::slug($value, '_');

        return $slug !== '' ? $slug : 'protection_action';
    }
}
