<?php

declare(strict_types=1);

namespace App\Services\Investment\Recommendation;

/**
 * Merges recommendations from waterfall + triggers + transfers + spouse.
 *
 * Responsibilities:
 *  1. Deduplicate (same action from different sources)
 *  2. Prioritise by: urgency, tax deadline, amount
 *  3. Remove contradictions (e.g., "invest in GIA" + "transfer out of GIA")
 *  4. Apply ISA and pension allowance competition rules
 */
class ConflictResolutionService
{
    /**
     * Priority ordering — lower number = higher priority.
     */
    private const PRIORITY_ORDER = [
        'critical' => 1,
        'high' => 2,
        'medium' => 3,
        'low' => 4,
    ];

    /**
     * Known contradiction pairs: if both keys appear, the resolution applies.
     * [keep_pattern, remove_pattern, reason]
     */
    private const CONTRADICTION_RULES = [
        // Don't recommend investing in GIA while also recommending Bed & ISA out of GIA
        ['bed_and_isa', 'gia', 'Bed and ISA transfer takes priority over new General Investment Account contributions.'],
        // Don't recommend Cash ISA → S&S ISA transfer while also recommending Cash ISA contributions
        ['cash_isa_to_ss_isa', 'cash_isa', 'Stocks and Shares ISA transfer takes priority over new Cash ISA contributions.'],
    ];

    /**
     * Resolve conflicts across all recommendation sources.
     *
     * @param  array  $waterfallRecs  From ContributionWaterfallService
     * @param  array  $triggerRecs  From InvestmentActionDefinitionService
     * @param  array  $transferRecs  From TransferRecommendationService
     * @param  array  $spouseRecs  From SpouseOptimisationService
     * @return array{
     *     recommendations: array,
     *     total_count: int,
     *     deduplicated_count: int,
     *     contradictions_resolved: int,
     *     sources_merged: array
     * }
     */
    public function resolve(
        array $waterfallRecs,
        array $triggerRecs,
        array $transferRecs,
        array $spouseRecs
    ): array {
        // Tag source on each recommendation
        $all = [];
        foreach ($waterfallRecs as $rec) {
            $rec['_source'] = $rec['source'] ?? 'waterfall';
            $all[] = $rec;
        }
        foreach ($triggerRecs as $rec) {
            $rec['_source'] = 'trigger';
            $all[] = $rec;
        }
        foreach ($transferRecs as $rec) {
            $rec['_source'] = $rec['source'] ?? 'transfer';
            $all[] = $rec;
        }
        foreach ($spouseRecs as $rec) {
            $rec['_source'] = $rec['source'] ?? 'spouse';
            $all[] = $rec;
        }

        $totalBefore = count($all);

        // Step 1: Deduplicate
        [$deduplicated, $dupsRemoved] = $this->deduplicate($all);

        // Step 2: Resolve contradictions
        [$resolved, $contradictionsResolved] = $this->resolveContradictions($deduplicated);

        // Step 3: Sort by priority
        $sorted = $this->sortByPriority($resolved);

        // Step 4: Clean up internal tags
        $final = array_map(function (array $rec) {
            unset($rec['_source'], $rec['_dedup_key']);

            return $rec;
        }, $sorted);

        return [
            'recommendations' => array_values($final),
            'total_count' => count($final),
            'deduplicated_count' => $dupsRemoved,
            'contradictions_resolved' => $contradictionsResolved,
            'sources_merged' => [
                'waterfall' => count($waterfallRecs),
                'trigger' => count($triggerRecs),
                'transfer' => count($transferRecs),
                'spouse' => count($spouseRecs),
            ],
        ];
    }

    // ──────────────────────────────────────────────
    // Internal pipeline
    // ──────────────────────────────────────────────

    /**
     * Deduplicate recommendations with the same action from different sources.
     *
     * Deduplication key = normalised headline + wrapper/scan_type.
     * When duplicates exist, keep the one with the higher priority.
     *
     * @return array{0: array, 1: int} [deduplicated list, count removed]
     */
    private function deduplicate(array $recommendations): array
    {
        $seen = [];
        $deduplicated = [];
        $removed = 0;

        foreach ($recommendations as $rec) {
            $key = $this->deduplicationKey($rec);
            $rec['_dedup_key'] = $key;

            if (! isset($seen[$key])) {
                $seen[$key] = count($deduplicated);
                $deduplicated[] = $rec;
            } else {
                // Keep the higher-priority version
                $existingIndex = $seen[$key];
                $existingPriority = $this->priorityRank($deduplicated[$existingIndex]['priority'] ?? 'low');
                $newPriority = $this->priorityRank($rec['priority'] ?? 'low');

                if ($newPriority < $existingPriority) {
                    $deduplicated[$existingIndex] = $rec;
                }
                $removed++;
            }
        }

        return [array_values($deduplicated), $removed];
    }

    /**
     * Generate a deduplication key for a recommendation.
     */
    private function deduplicationKey(array $rec): string
    {
        $headline = strtolower(trim($rec['headline'] ?? $rec['title'] ?? ''));
        $wrapper = $rec['wrapper'] ?? $rec['scan_type'] ?? $rec['strategy_type'] ?? $rec['category'] ?? '';

        // Normalise headline — strip numbers and currency for matching
        $normalised = preg_replace('/[£\d,.\s]+/', '', $headline);

        return md5($normalised.':'.$wrapper);
    }

    /**
     * Resolve contradictory recommendations.
     *
     * @return array{0: array, 1: int} [resolved list, contradictions resolved]
     */
    private function resolveContradictions(array $recommendations): array
    {
        $resolved = $recommendations;
        $contradictionsResolved = 0;

        foreach (self::CONTRADICTION_RULES as [$keepPattern, $removePattern, $reason]) {
            $hasKeep = false;
            $removeIndices = [];

            foreach ($resolved as $index => $rec) {
                $wrapper = $rec['wrapper'] ?? $rec['scan_type'] ?? $rec['step'] ?? '';
                $category = strtolower($rec['category'] ?? '');

                if ($wrapper === $keepPattern || str_contains($category, $keepPattern)) {
                    $hasKeep = true;
                }

                if ($wrapper === $removePattern || $wrapper === $removePattern.'_contribution') {
                    $removeIndices[] = $index;
                }
            }

            // Only remove if both patterns exist
            if ($hasKeep && ! empty($removeIndices)) {
                foreach ($removeIndices as $idx) {
                    unset($resolved[$idx]);
                    $contradictionsResolved++;
                }
            }
        }

        // ISA allowance competition: LISA contributions reduce available ISA allowance
        // Both can coexist — the waterfall handles this sequentially

        // Pension allowance competition: carry forward should not fire if current year has headroom
        // Already handled in ContributionWaterfallService (step 7 checks current year first)

        return [array_values($resolved), $contradictionsResolved];
    }

    /**
     * Sort recommendations by priority (highest first), then by amount (largest first).
     */
    private function sortByPriority(array $recommendations): array
    {
        usort($recommendations, function (array $a, array $b) {
            $aPriority = $this->priorityRank($a['priority'] ?? 'low');
            $bPriority = $this->priorityRank($b['priority'] ?? 'low');

            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority;
            }

            // Within same priority, sort by amount (larger first)
            $aAmount = (float) ($a['amount'] ?? $a['estimated_impact'] ?? 0);
            $bAmount = (float) ($b['amount'] ?? $b['estimated_impact'] ?? 0);

            return $bAmount <=> $aAmount;
        });

        return $recommendations;
    }

    /**
     * Convert priority string to numeric rank (lower = higher priority).
     */
    private function priorityRank(string|int $priority): int
    {
        if (is_int($priority)) {
            return $priority;
        }

        return self::PRIORITY_ORDER[$priority] ?? 5;
    }
}
