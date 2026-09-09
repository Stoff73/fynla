<?php

declare(strict_types=1);

namespace App\Services\Coordination;

/**
 * PriorityRanker — the one ranking rule for recommendations.
 *
 * The seeded priority wins (CSJ, 2026-09-09; fyn-wiring Batch B, F2/F12).
 * Every engine carries the label its action definition was seeded with —
 * as a string `priority`, or as the `impact` label next to an engine int —
 * and that label sets the band. A numeric benefit and the module weight
 * only break ties inside a band; together they can never lift a rec across one.
 *
 * Both consumers read this class: CoordinatingAgent (Fyn's <financial_context>,
 * get_recommendations, the holistic plan) and RecommendationsAggregatorService
 * (the web API, the dashboard focus cards, /m next actions).
 */
class PriorityRanker
{
    /** Seeded label → urgency band. The dashboard's 85/60/45 with critical above high. */
    public const BANDS = ['critical' => 95.0, 'high' => 85.0, 'medium' => 60.0, 'low' => 45.0];

    /** Tiebreak only: weight/100 adds at most 0.8. */
    public const MODULE_WEIGHTS = [
        'protection' => 80,
        'savings' => 75,
        'retirement' => 70,
        'tax_optimisation' => 65,
        'tax' => 65,
        'investment' => 60,
        'goals' => 55,
        'estate' => 50,
    ];

    /** Keys CoordinatingAgent::extractRecommendations adds beside the module lists. */
    private const BOOKKEEPING_KEYS = ['module_scores', 'available_surplus'];

    /** Numeric benefit fields, first present wins. */
    private const BENEFIT_KEYS = ['estimated_impact', 'estimated_saving', 'estimated_annual_tax_saved', 'potential_benefit'];

    /**
     * Rank recommendations grouped by module.
     *
     * @param  array<string, mixed>  $allRecommendations  module => list of recs (plus bookkeeping keys)
     * @param  array<string, mixed>  $userContext  optional `module_priorities` overrides
     * @return list<array<string, mixed>> flat list, highest priority_score first
     */
    public function rankRecommendations(array $allRecommendations, array $userContext = []): array
    {
        $scored = [];

        foreach ($allRecommendations as $module => $recommendations) {
            if (in_array($module, self::BOOKKEEPING_KEYS, true) || ! is_array($recommendations)) {
                continue;
            }

            foreach ($recommendations as $recommendation) {
                if (! is_array($recommendation)) {
                    continue;
                }

                $label = self::priorityLabel($recommendation);
                $urgency = self::BANDS[$label];
                $impact = $this->impactScore($recommendation);
                $weight = (float) ($userContext['module_priorities'][$module] ?? self::MODULE_WEIGHTS[$module] ?? 50);

                $scored[] = array_merge($recommendation, [
                    'module' => $module,
                    'priority_score' => round($urgency + $impact / 20 + $weight / 100, 2),
                    'urgency_score' => $urgency,
                    'impact_score' => $impact,
                    'impact_label' => self::impactLabel($label),
                    'timeline' => self::timelineFor($urgency),
                ]);
            }
        }

        usort($scored, fn ($a, $b) => $b['priority_score'] <=> $a['priority_score']);

        return $scored;
    }

    /**
     * The seeded label on a recommendation: critical | high | medium | low.
     *
     * A string `priority` wins; then the `impact` label (the DB-driven engines
     * put the seeded label there and an int in `priority`); then the int rule
     * the protection adapter used (1–2 high, 3 medium, 4+ low); else medium.
     *
     * @param  array<string, mixed>  $recommendation
     */
    public static function priorityLabel(array $recommendation): string
    {
        foreach (['priority', 'impact'] as $key) {
            $value = $recommendation[$key] ?? null;
            if (is_string($value) && isset(self::BANDS[strtolower($value)])) {
                return strtolower($value);
            }
        }

        $int = $recommendation['priority'] ?? null;
        if (is_int($int)) {
            return match (true) {
                $int <= 2 => 'high',
                $int === 3 => 'medium',
                default => 'low',
            };
        }

        return 'medium';
    }

    /** The three-way label the UI filters on (critical folds into high). */
    public static function impactLabel(string $label): string
    {
        return $label === 'critical' ? 'high' : $label;
    }

    public static function timelineFor(float $urgency): string
    {
        return match (true) {
            $urgency >= 80 => 'immediate',
            $urgency >= 60 => 'short_term',
            $urgency >= 40 => 'medium_term',
            default => 'long_term',
        };
    }

    /**
     * Group ranked recommendations by module.
     *
     * @param  list<array<string, mixed>>  $recommendations
     * @return array<string, list<array<string, mixed>>>
     */
    public function groupByCategory(array $recommendations): array
    {
        $grouped = array_fill_keys(['protection', 'savings', 'investment', 'retirement', 'estate', 'goals'], []);

        foreach ($recommendations as $rec) {
            $module = $rec['module'] ?? 'other';
            if (isset($grouped[$module])) {
                $grouped[$module][] = $rec;
            }
        }

        return $grouped;
    }

    /**
     * Group ranked recommendations by timeline.
     *
     * @param  list<array<string, mixed>>  $rankedRecommendations
     * @return array{action_plan: array<string, list<array<string, mixed>>>, summary: array<string, int>}
     */
    public function createActionPlan(array $rankedRecommendations): array
    {
        $plan = array_fill_keys(['immediate', 'short_term', 'medium_term', 'long_term'], []);

        foreach ($rankedRecommendations as $rec) {
            $timeline = $rec['timeline'] ?? self::timelineFor((float) ($rec['urgency_score'] ?? 50));
            $plan[$timeline][] = $rec;
        }

        return [
            'action_plan' => $plan,
            'summary' => [
                'immediate_actions' => count($plan['immediate']),
                'short_term_actions' => count($plan['short_term']),
                'medium_term_actions' => count($plan['medium_term']),
                'long_term_actions' => count($plan['long_term']),
                'total_actions' => count($rankedRecommendations),
            ],
        ];
    }

    /**
     * Band the numeric benefit on the rec; 50 when there is none.
     *
     * @param  array<string, mixed>  $recommendation
     */
    private function impactScore(array $recommendation): float
    {
        foreach (self::BENEFIT_KEYS as $key) {
            $value = $recommendation[$key] ?? null;
            if (is_numeric($value) && (float) $value > 0) {
                $benefit = (float) $value;

                return match (true) {
                    $benefit > 50_000 => 95.0,
                    $benefit > 20_000 => 80.0,
                    $benefit > 10_000 => 65.0,
                    $benefit > 5_000 => 55.0,
                    default => 50.0,
                };
            }
        }

        return 50.0;
    }
}
