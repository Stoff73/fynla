<?php

declare(strict_types=1);

namespace App\Services\Investment\Recommendation;

/**
 * Formats the final output from the investment recommendation pipeline.
 *
 * Produces a structured API response with sections:
 *  - readiness: data readiness gate results
 *  - safety_notes: safety check context and surplus adjustments
 *  - contribution_recommendations: waterfall allocations
 *  - transfer_recommendations: transfer scans
 *  - spouse_recommendations: spouse optimisation strategies
 *  - summary: overview metrics
 *  - decision_path: full audit trail for "What Drives This" UI
 */
class RecommendationOutputFormatter
{
    /**
     * Format the complete pipeline output.
     *
     * @param  array  $readiness  From DataReadinessService
     * @param  array  $safetyResult  From SafetyCheckService
     * @param  array  $mergedRecommendations  From ConflictResolutionService
     * @param  array  $waterfallResult  From ContributionWaterfallService (for decision path)
     * @param  array  $lifeEventModifiers  From LifeEventAssessmentService
     * @param  array  $goalModifiers  From GoalAssessmentService
     * @param  array  $context  From UserContextBuilder
     * @return array Structured API response
     */
    public function format(
        array $readiness,
        array $safetyResult,
        array $mergedRecommendations,
        array $waterfallResult,
        array $lifeEventModifiers,
        array $goalModifiers,
        array $context
    ): array {
        $recommendations = $mergedRecommendations['recommendations'] ?? [];

        // Partition recommendations by source
        $contributionRecs = array_values(array_filter($recommendations, fn ($r) => ($r['source'] ?? '') === 'waterfall'));
        $transferRecs = array_values(array_filter($recommendations, fn ($r) => ($r['source'] ?? '') === 'transfer'));
        $spouseRecs = array_values(array_filter($recommendations, fn ($r) => ($r['source'] ?? '') === 'spouse'));
        $triggerRecs = array_values(array_filter($recommendations, fn ($r) => ($r['source'] ?? '') === 'trigger'));

        // Format each recommendation with consistent shape
        $formattedContributions = array_map(fn ($r) => $this->formatRecommendation($r, 'contribution'), $contributionRecs);
        $formattedTransfers = array_map(fn ($r) => $this->formatRecommendation($r, 'transfer'), $transferRecs);
        $formattedSpouse = array_map(fn ($r) => $this->formatRecommendation($r, 'spouse'), $spouseRecs);
        $formattedTriggers = array_map(fn ($r) => $this->formatRecommendation($r, 'trigger'), $triggerRecs);

        // Build summary
        $totalAmount = collect($recommendations)->sum(fn ($r) => (float) ($r['amount'] ?? 0));
        $highPriorityCount = count(array_filter($recommendations, fn ($r) => in_array($r['priority'] ?? '', ['critical', 'high'], true)));

        return [
            'success' => true,
            'pipeline_version' => '1.0',
            'readiness' => [
                'can_proceed' => $readiness['can_proceed'] ?? true,
                'completion_percent' => $readiness['completion_percent'] ?? 100,
                'blocking' => $readiness['blocking'] ?? [],
                'warnings' => $readiness['warnings'] ?? [],
                'info' => $readiness['info'] ?? [],
            ],
            'safety_notes' => [
                'original_surplus' => $safetyResult['original_surplus'] ?? 0,
                'adjusted_surplus' => $safetyResult['adjusted_surplus'] ?? 0,
                'can_invest' => $safetyResult['can_invest'] ?? true,
                'checks' => $safetyResult['checks'] ?? [],
                'context_notes' => $safetyResult['context_notes'] ?? [],
                'employer_match' => $safetyResult['employer_match'] ?? null,
            ],
            'contribution_recommendations' => $formattedContributions,
            'transfer_recommendations' => $formattedTransfers,
            'spouse_recommendations' => $formattedSpouse,
            'trigger_recommendations' => $formattedTriggers,
            'life_events' => [
                'events_assessed' => count($lifeEventModifiers['events_assessed'] ?? []),
                'blocked_wrappers' => $lifeEventModifiers['blocked_wrappers'] ?? [],
                'prioritised_wrappers' => $lifeEventModifiers['prioritised_wrappers'] ?? [],
                'liquidity_priority' => $lifeEventModifiers['liquidity_priority'] ?? 'low',
                'sub_actions' => $lifeEventModifiers['sub_actions'] ?? [],
            ],
            'goals' => [
                'goals_assessed' => $goalModifiers['goals_assessed'] ?? 0,
                'has_short_term_goals' => $goalModifiers['has_short_term_goals'] ?? false,
                'has_house_purchase_goal' => $goalModifiers['has_house_purchase_goal'] ?? false,
                'goal_wrappers' => $goalModifiers['goal_wrappers'] ?? [],
            ],
            'summary' => [
                'total_recommendations' => count($recommendations),
                'high_priority_count' => $highPriorityCount,
                'total_allocation' => round($totalAmount, 2),
                'waterfall_steps_executed' => $waterfallResult['steps_executed'] ?? 0,
                'waterfall_steps_skipped' => $waterfallResult['steps_skipped'] ?? 0,
                'transfer_scans_triggered' => $mergedRecommendations['sources_merged']['transfer'] ?? 0,
                'spouse_strategies_triggered' => $mergedRecommendations['sources_merged']['spouse'] ?? 0,
                'sources_merged' => $mergedRecommendations['sources_merged'] ?? [],
                'deduplicated_count' => $mergedRecommendations['deduplicated_count'] ?? 0,
                'contradictions_resolved' => $mergedRecommendations['contradictions_resolved'] ?? 0,
            ],
            'decision_path' => $this->buildDecisionPath(
                $waterfallResult,
                $safetyResult,
                $lifeEventModifiers,
                $goalModifiers,
                $context
            ),
        ];
    }

    /**
     * Format a readiness-only response when the pipeline cannot proceed.
     */
    public function formatReadinessBlock(array $readiness): array
    {
        return [
            'success' => true,
            'pipeline_version' => '1.0',
            'readiness' => [
                'can_proceed' => false,
                'completion_percent' => $readiness['completion_percent'] ?? 0,
                'blocking' => $readiness['blocking'] ?? [],
                'warnings' => $readiness['warnings'] ?? [],
                'info' => $readiness['info'] ?? [],
            ],
            'safety_notes' => null,
            'contribution_recommendations' => [],
            'transfer_recommendations' => [],
            'spouse_recommendations' => [],
            'trigger_recommendations' => [],
            'life_events' => null,
            'goals' => null,
            'summary' => [
                'total_recommendations' => 0,
                'high_priority_count' => 0,
                'total_allocation' => 0,
            ],
            'decision_path' => [],
        ];
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    /**
     * Format a single recommendation with consistent shape.
     */
    private function formatRecommendation(array $rec, string $section): array
    {
        return [
            'id' => $rec['id'] ?? null,
            'section' => $section,
            'source' => $rec['source'] ?? $section,
            'headline' => $rec['headline'] ?? $rec['title'] ?? '',
            'explanation' => $rec['explanation'] ?? $rec['description'] ?? '',
            'personal_context' => $rec['personal_context'] ?? '',
            'amount' => round((float) ($rec['amount'] ?? $rec['estimated_impact'] ?? 0), 2),
            'priority' => $rec['priority'] ?? $rec['impact'] ?? 'medium',
            'wrapper' => $rec['wrapper'] ?? $rec['scan_type'] ?? $rec['strategy_type'] ?? $rec['category'] ?? null,
            'status' => 'pending',
            'notes' => array_filter([
                $rec['requires_verification'] ?? false ? 'Requires verification with provider.' : null,
                $rec['requires_specialist_advice'] ?? false ? 'Specialist advice recommended.' : null,
            ]),
        ];
    }

    /**
     * Build the decision path for transparency ("What Drives This" UI).
     */
    private function buildDecisionPath(
        array $waterfallResult,
        array $safetyResult,
        array $lifeEventModifiers,
        array $goalModifiers,
        array $context
    ): array {
        $path = [];

        // Context summary
        $path[] = [
            'phase' => 'context',
            'description' => 'User context assembled',
            'details' => [
                'tax_band' => $context['financial']['tax_band'] ?? 'unknown',
                'disposable_income' => $context['financial']['disposable_income'] ?? 0,
                'risk_level' => $context['risk']['risk_level'] ?? 'unknown',
                'has_spouse' => $context['spouse'] !== null,
                'age' => $context['personal']['age'] ?? 'unknown',
            ],
        ];

        // Life event modifiers
        $eventsCount = count($lifeEventModifiers['events_assessed'] ?? []);
        if ($eventsCount > 0) {
            $path[] = [
                'phase' => 'life_events',
                'description' => sprintf('%d life event%s assessed', $eventsCount, $eventsCount === 1 ? '' : 's'),
                'details' => [
                    'blocked_wrappers' => $lifeEventModifiers['blocked_wrappers'] ?? [],
                    'prioritised_wrappers' => $lifeEventModifiers['prioritised_wrappers'] ?? [],
                    'liquidity_priority' => $lifeEventModifiers['liquidity_priority'] ?? 'low',
                ],
            ];
        }

        // Goal modifiers
        $goalsCount = $goalModifiers['goals_assessed'] ?? 0;
        if ($goalsCount > 0) {
            $path[] = [
                'phase' => 'goals',
                'description' => sprintf('%d goal%s assessed', $goalsCount, $goalsCount === 1 ? '' : 's'),
                'details' => [
                    'has_short_term_goals' => $goalModifiers['has_short_term_goals'] ?? false,
                    'has_house_purchase_goal' => $goalModifiers['has_house_purchase_goal'] ?? false,
                ],
            ];
        }

        // Safety checks
        $path[] = [
            'phase' => 'safety',
            'description' => 'Safety checks completed',
            'details' => [
                'original_surplus' => $safetyResult['original_surplus'] ?? 0,
                'adjusted_surplus' => $safetyResult['adjusted_surplus'] ?? 0,
                'can_invest' => $safetyResult['can_invest'] ?? true,
                'checks_triggered' => count(array_filter($safetyResult['checks'] ?? [], fn ($c) => $c['triggered'])),
            ],
        ];

        // Waterfall decisions
        $waterfallPath = $waterfallResult['decision_path'] ?? [];
        foreach ($waterfallPath as $step) {
            $path[] = [
                'phase' => 'waterfall',
                'description' => sprintf('Step: %s — %s', $step['step'] ?? '', $step['action'] ?? ''),
                'details' => $step,
            ];
        }

        return $path;
    }
}
