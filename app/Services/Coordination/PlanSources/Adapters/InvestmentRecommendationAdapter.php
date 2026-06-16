<?php

declare(strict_types=1);

namespace App\Services\Coordination\PlanSources\Adapters;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use Illuminate\Support\Str;

/**
 * Maps InvestmentAgent::generateRecommendations() rec arrays into the common
 * StrategyRecommendation DTO. Investment recs carry a `definition_key` from
 * InvestmentActionDefinitionService::buildRecommendation() — that key becomes
 * the `type` directly. Where the key is absent (e.g. goal-sourced recs
 * that reach this path), a curated category→strategy_type map is tried, with
 * a slug fallback so every rec still surfaces.
 *
 * Investment strategies are lump-led (rebalancing, one-off transfers) rather
 * than contribution-led, so requiredMonthlyCost is always null and
 * requiredLumpSum is set from an `estimated_impact` field when present.
 */
final class InvestmentRecommendationAdapter
{
    /**
     * Curated category → strategy_type for unmapped or keyless recs.
     * Keep in sync with InvestmentActionDefinitionSeeder strategy rows.
     */
    private const CATEGORY_TO_STRATEGY_TYPE = [
        'Risk Profile' => 'set_risk_profile',
        'Rebalancing' => 'rebalance_to_target',
        'Platform Fees' => 'reduce_platform_fees',
        'Fund Fees' => 'reduce_platform_fees',
        'Fee Analysis' => 'reduce_platform_fees',
    ];

    public function toStrategyRecommendation(array $rec): StrategyRecommendation
    {
        $definitionKey = (string) ($rec['definition_key'] ?? '');
        $category = (string) ($rec['category'] ?? '');

        $type = $definitionKey !== ''
            ? $definitionKey
            : (self::CATEGORY_TO_STRATEGY_TYPE[$category] ?? $this->slugify($category));

        $impact = strtolower((string) ($rec['impact'] ?? 'medium'));
        $priority = in_array($impact, ['high', 'medium', 'low'], true) ? $impact : 'medium';

        // Risk / warning categories surface as Warning; all else as Lifecycle.
        $strategyCategory = in_array($category, ['Risk Profile', 'Tax Efficiency'], true)
            ? StrategyCategory::Warning
            : StrategyCategory::Lifecycle;

        $extra = array_filter([
            'definition_key' => $definitionKey !== '' ? $definitionKey : null,
            'scope' => $rec['scope'] ?? null,
            'account_id' => $rec['account_id'] ?? null,
            'account_name' => $rec['account_name'] ?? null,
            'source_category' => $category !== '' ? $category : null,
            'decision_trace' => $rec['decision_trace'] ?? null,
        ], static fn ($v) => $v !== null);

        // Investment actions are typically lump-led; use estimated_impact as the
        // indicative lump sum where present (fee savings, ISA transfer amounts, etc.).
        $requiredLumpSum = isset($rec['estimated_impact']) && is_numeric($rec['estimated_impact'])
            ? (float) $rec['estimated_impact']
            : null;

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
            requiredLumpSum: $requiredLumpSum,
        );
    }

    private function slugify(string $value): string
    {
        $slug = Str::slug($value, '_');

        return $slug !== '' ? $slug : 'investment_action';
    }
}
