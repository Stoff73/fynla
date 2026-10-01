<?php

declare(strict_types=1);

namespace App\Services\Coordination\PlanSources\Adapters;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Services\Coordination\PriorityRanker;
use Illuminate\Support\Str;

/**
 * Maps RetirementAgent::generateRecommendations() rec arrays into the common
 * StrategyRecommendation DTO. `type` is the rec's `definition_key` (CSJ
 * 2026-10-01, as protection's since #972), so each card has a stable id and
 * its own how-to; a rec without one falls back to the curated category map,
 * then a slug.
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
        $definitionKey = (string) ($rec['definition_key'] ?? '');
        $type = $definitionKey !== ''
            ? $definitionKey
            : (self::CATEGORY_TO_STRATEGY_TYPE[$category] ?? $this->slugify($category));

        // One label rule (PriorityRanker); the enum has no critical case, so the
        // seeded label travels in extra for the ranker to read off the composed item.
        $seeded = PriorityRanker::priorityLabel($rec);
        $priority = PriorityRanker::impactLabel($seeded);

        $extra = array_filter([
            'seeded_priority' => $seeded,
            'scope' => $rec['scope'] ?? null,
            'account_id' => $rec['account_id'] ?? null,
            'account_name' => $rec['account_name'] ?? null,
            'source_category' => $category !== '' ? $category : null,
            'decision_trace' => $rec['decision_trace'] ?? null,
            'definition_key' => $definitionKey !== '' ? $definitionKey : null,
            'figures' => ! empty($rec['figures']) ? $rec['figures'] : null,
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
