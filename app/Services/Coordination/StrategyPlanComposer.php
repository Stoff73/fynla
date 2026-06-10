<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\DataTransferObjects\StrategyRecommendation;

/**
 * Composes eligible strategy recommendations into ONE ordered, conflict-aware plan:
 *
 *   - Sequencing: do_before edges respected via bounded bubble-sort passes.
 *   - Conflicts: both strategies are kept in the plan; the lower-saving member of
 *     each mutually-exclusive pair carries a conflict_note naming its preferred
 *     alternative. When both sides have EQUAL savings, both carry notes — that is
 *     intentional (a genuine coin-flip; the user should choose).
 *   - Combined total: counts ONLY the preferred member of each conflict pair
 *     (the one whose savings >= the alternative). This gives a realistic
 *     upper-bound on realisable annual savings; counting the excluded alternative
 *     would overstate it.
 *   - Claim tier: attached from metadata for downstream voicing; defaults to
 *     'judgement' when metadata is absent.
 *
 * This is a PURE function of its inputs — callers are responsible for
 * fetching recommendations (TaxStrategyCalculator) and metadata (DB).
 */
final class StrategyPlanComposer
{
    /**
     * @param  list<StrategyRecommendation>  $recommendations
     * @param  array<string, array{claim_tier?: string, sequencing?: array{do_before?: list<string>, conflicts_with?: list<string>}}>  $metadata
     * @param  list<array{strategy_type: string, missing: list<string>}>  $lockedStrategies
     * @return array{items: list<array<string, mixed>>, combined_annual_saving: float, locked: list<array{strategy_type: string, missing: list<string>}>}
     */
    public function compose(array $recommendations, array $metadata, array $lockedStrategies): array
    {
        // 1. Base order: estimated saving descending (nulls last, stable).
        //    We split into valued and null groups to ensure null-savings always
        //    sort after any valued savings without relying on -INF comparison,
        //    which can behave unexpectedly across PHP/Laravel versions.
        $valued = collect($recommendations)
            ->filter(fn (StrategyRecommendation $r) => $r->estimatedAnnualTaxSaved !== null)
            ->sortByDesc(fn (StrategyRecommendation $r) => $r->estimatedAnnualTaxSaved)
            ->values();

        $nulls = collect($recommendations)
            ->filter(fn (StrategyRecommendation $r) => $r->estimatedAnnualTaxSaved === null)
            ->values();

        /** @var list<StrategyRecommendation> $items */
        $items = $valued->merge($nulls)->all();

        // 2. Sequencing: bubble any item that declares do_before ahead of its target.
        //    Bounded to n passes — the list is small (≤ ~20 strategies).
        $count = count($items);
        for ($pass = 0; $pass < $count; $pass++) {
            $moved = false;
            foreach ($items as $i => $rec) {
                foreach (($metadata[$rec->type]['sequencing']['do_before'] ?? []) as $target) {
                    $targetIdx = $this->indexOf($items, $target);
                    if ($targetIdx !== null && $targetIdx < $i) {
                        array_splice($items, $i, 1);
                        array_splice($items, $targetIdx, 0, [$rec]);
                        $moved = true;
                        break 2;
                    }
                }
            }
            if (! $moved) {
                break;
            }
        }

        // 3. Resolve conflicts: identify which member of each pair is excluded from the total.
        //    The lower-saving member is excluded. On a tie, both are excluded (both carry notes).
        //    We build a set of excluded types first, then assign conflict_note and total.
        $savingByType = [];
        foreach ($items as $rec) {
            $savingByType[$rec->type] = $rec->estimatedAnnualTaxSaved;
        }

        // Collect all conflict edges (undirected), resolve preferred/excluded per pair.
        $excludedTypes = [];
        $conflictNoteFor = []; // type => the preferred alternative type

        $visited = [];
        foreach ($items as $rec) {
            foreach (($metadata[$rec->type]['sequencing']['conflicts_with'] ?? []) as $other) {
                $normalised = collect([$rec->type, $other])->sort()->join('|');
                if (isset($visited[$normalised])) {
                    continue;
                }
                $visited[$normalised] = true;

                // Only process if the other type exists in our recommendation list.
                if (! isset($savingByType[$other])) {
                    continue;
                }

                $thisSaving = $savingByType[$rec->type] ?? 0.0;
                $otherSaving = $savingByType[$other] ?? 0.0;

                if ($thisSaving < $otherSaving) {
                    // rec->type is the excluded (lower) side.
                    $excludedTypes[$rec->type] = true;
                    $conflictNoteFor[$rec->type] = $other;
                } elseif ($otherSaving < $thisSaving) {
                    // other is the excluded (lower) side.
                    $excludedTypes[$other] = true;
                    $conflictNoteFor[$other] = $rec->type;
                } else {
                    // Equal savings — both carry notes; neither is clearly preferred.
                    $excludedTypes[$rec->type] = true;
                    $excludedTypes[$other] = true;
                    $conflictNoteFor[$rec->type] = $other;
                    $conflictNoteFor[$other] = $rec->type;
                }
            }
        }

        // 4. Build output items with annotations.
        $out = [];
        foreach ($items as $index => $rec) {
            $conflictNote = isset($conflictNoteFor[$rec->type])
                ? "Alternative to {$conflictNoteFor[$rec->type]} — compare before doing both."
                : null;

            $out[] = $rec->toArray() + [
                'claim_tier' => $metadata[$rec->type]['claim_tier'] ?? 'judgement',
                'sequence_position' => $index + 1,
                'conflict_note' => $conflictNote,
            ];
        }

        // 5. Combined annual saving: sum of all items EXCEPT excluded conflict alternatives.
        $total = 0.0;
        foreach ($items as $rec) {
            if (! isset($excludedTypes[$rec->type]) && $rec->estimatedAnnualTaxSaved !== null) {
                $total += $rec->estimatedAnnualTaxSaved;
            }
        }

        return [
            'items' => $out,
            'combined_annual_saving' => round($total, 2),
            'locked' => array_values($lockedStrategies),
        ];
    }

    /**
     * Return the index of the first StrategyRecommendation with the given type, or null if not found.
     *
     * @param  list<StrategyRecommendation>  $list
     */
    private function indexOf(array $list, string $type): ?int
    {
        foreach ($list as $i => $rec) {
            if ($rec->type === $type) {
                return $i;
            }
        }

        return null;
    }
}
