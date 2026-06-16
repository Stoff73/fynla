<?php

declare(strict_types=1);

namespace App\Services\Coordination\PlanSources\Adapters;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use Illuminate\Support\Str;

/**
 * Maps RetirementAgent::generateRecommendations() rec arrays into the common
 * StrategyRecommendation DTO. Retirement recs are definition-driven but carry
 * no stable definition key through to the output, so `type` is derived from a
 * curated category→strategy_type map (the seeded source='strategy' rows key off
 * the same slugs); unmapped categories fall back to a slug so they still surface
 * (just without catalogue metadata).
 */
final class RetirementRecommendationAdapter
{
    /** Curated category → strategy_type. Keep in sync with RetirementActionDefinitionSeeder strategy rows. */
    private const CATEGORY_TO_STRATEGY_TYPE = [
        'Contribution_increase' => 'increase_pension_contribution',
        'Employer_match' => 'increase_pension_contribution',
        'Start_contributions' => 'increase_pension_contribution',
        'Salary Sacrifice' => 'salary_sacrifice_pension',
        'Tax Planning' => 'carry_forward_unused_allowance',
        'Decumulation' => 'plan_retirement_income',
        'Retirement Planning' => 'plan_retirement_income',
    ];

    public function toStrategyRecommendation(array $rec): StrategyRecommendation
    {
        $category = (string) ($rec['category'] ?? '');
        $type = self::CATEGORY_TO_STRATEGY_TYPE[$category] ?? $this->slugify($category);

        $impact = strtolower((string) ($rec['impact'] ?? 'medium'));
        $priority = in_array($impact, ['high', 'medium', 'low'], true) ? $impact : 'medium';

        $extra = array_filter([
            'scope' => $rec['scope'] ?? null,
            'account_id' => $rec['account_id'] ?? null,
            'account_name' => $rec['account_name'] ?? null,
            'source_category' => $category !== '' ? $category : null,
            'decision_trace' => $rec['decision_trace'] ?? null,
        ], static fn ($v) => $v !== null);

        return new StrategyRecommendation(
            type: $type,
            category: StrategyCategory::Lifecycle,
            priority: $priority,
            title: (string) ($rec['title'] ?? $rec['action'] ?? ''),
            description: (string) ($rec['description'] ?? ''),
            estimatedAnnualTaxSaved: null,
            requiresAdvice: false,
            extra: $extra,
            requiredMonthlyCost: isset($rec['available_monthly_headroom']) && is_numeric($rec['available_monthly_headroom'])
                ? (float) $rec['available_monthly_headroom']
                : null,
        );
    }

    private function slugify(string $value): string
    {
        $slug = Str::slug($value, '_');

        return $slug !== '' ? $slug : 'retirement_action';
    }
}
