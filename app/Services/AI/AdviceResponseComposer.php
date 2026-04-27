<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\User;

/**
 * S1.6 — Composes the `advice_response` SSE payload from
 * `CoordinatingAgent::orchestrateAnalysis` engine output. Pure-function
 * style — no DB writes, no LLM calls. Output shape is asserted by
 * `AdviceResponseSchema::validate` before emission.
 *
 * INV-2.3.2 — every interpretive line in `breakdowns` / `key_figures`
 * traces to an engine field; nothing is composed from prose. INV-2.3.5
 * — exactly one `advice_response` per recommendation-mode turn (caller
 * must enforce — composer just produces the payload).
 */
final class AdviceResponseComposer
{
    /**
     * @param  array<string, mixed>  $engineOutput  shape from `CoordinatingAgent::orchestrateAnalysis`
     * @param  array<string, mixed>|null  $classification
     * @return array<string, mixed>  validated `advice_response` payload
     */
    public function compose(User $user, array $engineOutput, ?array $classification = null): array
    {
        $payload = [
            'type' => AdviceResponseSchema::TYPE,
            'headline' => $this->composeHeadline($engineOutput, $classification),
            'key_figures' => $this->extractKeyFigures($engineOutput),
            'breakdowns' => $this->extractBreakdowns($engineOutput),
            'recommendations' => $this->mapRecommendations($engineOutput['ranked_recommendations'] ?? []),
            'next_steps' => $this->extractNextSteps($engineOutput),
            'signposting' => AdviceResponseSchema::FCA_SIGNPOSTING,
        ];

        AdviceResponseSchema::validate($payload);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $engineOutput
     * @param  array<string, mixed>|null  $classification
     */
    private function composeHeadline(array $engineOutput, ?array $classification): string
    {
        $summary = $engineOutput['summary'] ?? [];
        $totalRecs = (int) ($summary['total_recommendations'] ?? 0);
        $primary = $classification['primary'] ?? 'guidance';
        $primaryLabel = ucfirst(str_replace('_', ' ', (string) $primary));

        if ($totalRecs === 0) {
            return "{$primaryLabel} — no specific recommendations from the engine right now based on the data on file.";
        }

        if ($totalRecs === 1) {
            return "{$primaryLabel} — 1 recommendation prioritised based on your situation.";
        }

        return "{$primaryLabel} — {$totalRecs} recommendations prioritised based on your situation.";
    }

    /**
     * @param  array<string, mixed>  $engineOutput
     * @return list<array{label: string, value: string}>
     */
    private function extractKeyFigures(array $engineOutput): array
    {
        $figures = [];

        $availableSurplus = $engineOutput['available_surplus'] ?? null;
        if ($availableSurplus !== null) {
            $figures[] = [
                'label' => 'Monthly surplus',
                'value' => $this->formatCurrency((float) $availableSurplus),
            ];
        }

        $summary = $engineOutput['summary'] ?? [];
        if (isset($summary['total_monthly_demand']) && (float) $summary['total_monthly_demand'] > 0) {
            $figures[] = [
                'label' => 'Monthly demand',
                'value' => $this->formatCurrency((float) $summary['total_monthly_demand']),
            ];
        }

        if (isset($summary['conflicts_identified']) && (int) $summary['conflicts_identified'] > 0) {
            $figures[] = [
                'label' => 'Conflicts identified',
                'value' => (string) (int) $summary['conflicts_identified'],
            ];
        }

        return $figures;
    }

    /**
     * @param  array<string, mixed>  $engineOutput
     * @return list<array{label: string, value: string, detail?: string}>
     */
    private function extractBreakdowns(array $engineOutput): array
    {
        $breakdowns = [];

        $moduleAnalysis = $engineOutput['module_analysis'] ?? [];

        foreach ($moduleAnalysis as $moduleKey => $analysis) {
            if (! is_string($moduleKey) || ! is_array($analysis)) {
                continue;
            }

            $data = $analysis['data'] ?? [];
            $label = ucfirst(str_replace('_', ' ', $moduleKey));

            // Surface the most informative numeric metric per module.
            // Fall-through priority order matches the per-module agent's
            // primary headline metric.
            foreach (['net_worth', 'total_value', 'total_cover', 'total_savings', 'total_investments', 'iht_liability', 'pension_projection', 'retirement_income'] as $metric) {
                if (isset($data[$metric]) && is_numeric($data[$metric]) && (float) $data[$metric] > 0) {
                    $breakdowns[] = [
                        'label' => $label,
                        'value' => $this->formatCurrency((float) $data[$metric]),
                        'detail' => str_replace('_', ' ', $metric),
                    ];
                    break;
                }
            }
        }

        $shortfall = $engineOutput['shortfall_analysis']['has_shortfall'] ?? false;
        if ($shortfall === true) {
            $shortfallAmount = $engineOutput['shortfall_analysis']['shortfall_amount'] ?? null;
            $breakdowns[] = [
                'label' => 'Cashflow shortfall',
                'value' => $shortfallAmount !== null ? $this->formatCurrency((float) $shortfallAmount) : 'Yes',
                'detail' => 'Recommendation demand exceeds available surplus.',
            ];
        }

        return $breakdowns;
    }

    /**
     * @param  list<array<string, mixed>>  $rankedRecommendations
     * @return list<array{title: string, priority: string, reason: string, action?: string, route_path?: string}>
     */
    private function mapRecommendations(array $rankedRecommendations): array
    {
        $out = [];

        foreach (array_slice($rankedRecommendations, 0, 5) as $rec) {
            if (! is_array($rec)) {
                continue;
            }

            $title = (string) ($rec['title'] ?? $rec['name'] ?? $rec['action'] ?? 'Recommendation');
            $priority = $this->normalisePriority($rec['priority'] ?? null);
            $reason = (string) ($rec['reason'] ?? $rec['rationale'] ?? $rec['description'] ?? '');

            $entry = [
                'title' => $title,
                'priority' => $priority,
                'reason' => $reason,
            ];

            if (! empty($rec['action']) && is_string($rec['action']) && $rec['action'] !== $title) {
                $entry['action'] = $rec['action'];
            }
            if (! empty($rec['route_path']) && is_string($rec['route_path'])) {
                $entry['route_path'] = $rec['route_path'];
            }

            $out[] = $entry;
        }

        return $out;
    }

    private function normalisePriority(mixed $value): string
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $n = (int) $value;
            return match (true) {
                $n >= 8 => 'high',
                $n >= 5 => 'medium',
                $n >= 1 => 'low',
                default => 'info',
            };
        }

        if (is_string($value)) {
            $lower = strtolower(trim($value));
            if (in_array($lower, AdviceResponseSchema::VALID_PRIORITIES, true)) {
                return $lower;
            }
            if (in_array($lower, ['urgent', 'critical'], true)) {
                return 'high';
            }
        }

        return 'info';
    }

    /**
     * @param  array<string, mixed>  $engineOutput
     * @return list<array{label: string, route_path: string}>
     */
    private function extractNextSteps(array $engineOutput): array
    {
        $steps = [];
        $seen = [];

        foreach ((array) ($engineOutput['ranked_recommendations'] ?? []) as $rec) {
            if (! is_array($rec)) {
                continue;
            }
            $route = $rec['route_path'] ?? null;
            if (! is_string($route) || $route === '' || isset($seen[$route])) {
                continue;
            }
            $label = (string) ($rec['action'] ?? $rec['title'] ?? 'Review');
            $steps[] = ['label' => $label, 'route_path' => $route];
            $seen[$route] = true;
        }

        return array_slice($steps, 0, 5);
    }

    private function formatCurrency(float $value): string
    {
        if ($value >= 1000000) {
            return '£'.number_format($value / 1000000, 2).'m';
        }
        if ($value >= 1000) {
            return '£'.number_format($value, 0);
        }

        return '£'.number_format($value, 2);
    }
}
