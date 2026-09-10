<?php

declare(strict_types=1);

namespace App\Services\Coordination\PlanSources\Adapters;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Services\Coordination\PriorityRanker;
use Illuminate\Support\Str;

/**
 * Maps EstateActionDefinitionService::evaluateActions() rec arrays into the
 * common StrategyRecommendation DTO.
 *
 * type: prefers $rec['definition_key'] (stable catalogue key); falls back to a
 * curated category-to-slug map, then to Str::slug of the category.
 *
 * IHT exposure and no-will warnings use StrategyCategory::Warning; all other
 * estate planning actions use StrategyCategory::Lifecycle.
 *
 * estimatedAnnualTaxSaved is always null — IHT saving is a one-off lump, not an
 * annual income-tax saving. The estimated_impact value (if present) is carried
 * through in extra as estimated_iht_saving.
 *
 * requiresAdvice is true for trust and will actions (legal advice required);
 * false for administrative actions (LPA registration, beneficiary review).
 */
final class EstateRecommendationAdapter
{
    /**
     * Categories whose recs should surface as warnings (IHT / no-will exposure).
     *
     * @var list<string>
     */
    private const WARNING_CATEGORIES = [
        'Inheritance Tax',
        'Will',
        'charitable_bequest',
        'planning',
    ];

    /**
     * Categories requiring formal legal or financial advice.
     *
     * @var list<string>
     */
    private const REQUIRES_ADVICE_CATEGORIES = [
        'Trust',
        'Will',
        'will_trust_setup',
        'new_life_cover',
        'annual_gifting',
        'charitable_bequest',
    ];

    /**
     * Curated category -> strategy_type. Keep in sync with
     * EstateActionDefinitionSeeder strategy rows.
     *
     * @var array<string, string>
     */
    private const CATEGORY_TO_STRATEGY_TYPE = [
        'Will' => 'make_a_will',
        'Lasting Power of Attorney' => 'register_lpa',
        'Inheritance Tax' => 'reduce_iht_exposure',
        'Trust' => 'reduce_iht_exposure',
        'liquidity' => 'reduce_iht_exposure',
        'life_cover' => 'reduce_iht_exposure',
        'new_life_cover' => 'reduce_iht_exposure',
        'annual_gifting' => 'gift_to_reduce_estate',
        'Gifts' => 'gift_to_reduce_estate',
        'charitable_bequest' => 'reduce_iht_exposure',
        'Beneficiaries' => 'register_lpa',
        'will_review' => 'make_a_will',
        'will_trust_setup' => 'make_a_will',
        'planning' => 'reduce_iht_exposure',
    ];

    public function toStrategyRecommendation(array $rec): StrategyRecommendation
    {
        $definitionKey = (string) ($rec['definition_key'] ?? '');
        $category = (string) ($rec['category'] ?? '');

        $type = $definitionKey !== ''
            ? $definitionKey
            : (self::CATEGORY_TO_STRATEGY_TYPE[$category] ?? $this->slugify($category));

        $strategyCategory = in_array($category, self::WARNING_CATEGORIES, true)
            ? StrategyCategory::Warning
            : StrategyCategory::Lifecycle;

        $seeded = PriorityRanker::priorityLabel($rec);
        $priority = PriorityRanker::impactLabel($seeded);

        $requiresAdvice = in_array($category, self::REQUIRES_ADVICE_CATEGORIES, true);

        $estimatedIhtSaving = isset($rec['estimated_impact']) && is_numeric($rec['estimated_impact'])
            ? (float) $rec['estimated_impact']
            : null;

        $extra = array_filter([
            'seeded_priority' => $seeded,
            'definition_key' => $definitionKey !== '' ? $definitionKey : null,
            'scope' => $rec['scope'] ?? null,
            // The policy a per-policy rule is about — the aggregator scopes the id by it.
            'policy_id' => $rec['policy_id'] ?? null,
            'estimated_iht_saving' => $estimatedIhtSaving,
            'source_category' => $category !== '' ? $category : null,
            'decision_trace' => $rec['decision_trace'] ?? null,
        ], static fn (mixed $v): bool => $v !== null);

        return new StrategyRecommendation(
            type: $type,
            category: $strategyCategory,
            priority: $priority,
            title: (string) ($rec['title'] ?? $rec['action'] ?? ''),
            description: (string) ($rec['description'] ?? ''),
            estimatedAnnualTaxSaved: null,
            requiresAdvice: $requiresAdvice,
            extra: $extra,
            requiredMonthlyCost: null,
            requiredLumpSum: null,
        );
    }

    private function slugify(string $value): string
    {
        $slug = Str::slug($value, '_');

        return $slug !== '' ? $slug : 'estate_action';
    }
}
