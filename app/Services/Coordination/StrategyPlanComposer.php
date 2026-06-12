<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\DataTransferObjects\StrategyRecommendation;
use App\Services\Tax\IsaAllowanceAllocator;

/**
 * Composes eligible strategy recommendations into ONE ordered, conflict-aware plan:
 *
 *   - Sequencing: do_before edges respected via bounded bubble-sort passes.
 *   - Conflicts (graph-aware): both strategies are kept in the plan. Conflict
 *     pairs are resolved in descending order of each pair's higher saving: the
 *     lower-saving member is excluded from the combined total and carries a
 *     conflict_note naming its preferred alternative — UNLESS that alternative
 *     is itself already excluded by a stronger pair, in which case the lower
 *     member stays realisable and carries no note from that pair. (Chain
 *     A=300 ↔ B=200 ↔ C=100 excludes only B: C's sole conflict is already out,
 *     so C still counts.) When both sides have EQUAL savings, both carry notes
 *     (a genuine coin-flip; the user should choose) and only one — the earlier
 *     in plan order — counts toward the total.
 *   - Combined total: sums every non-excluded item with a non-null saving, so
 *     mutually-exclusive alternatives are never double-counted and chains are
 *     never over-excluded.
 *   - Shared ISA allowance: items flagged isa_allowance_excluded by the
 *     calculator's IsaAllowanceAllocator pass (pool fully used by a
 *     higher-saving ISA strategy) are kept in the plan but excluded from the
 *     combined total; their isa_allowance_note surfaces via conflict_note.
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

        // 3. Resolve conflicts graph-aware. Collect unique undirected pairs
        //    where BOTH members are in the plan, then process them in
        //    descending order of each pair's higher saving: the lower-saving
        //    member is excluded and noted — unless the winner is already
        //    excluded by a stronger pair, in which case the loser stays
        //    realisable with no note from that pair.
        $savingByType = [];
        $orderIndex = [];
        foreach ($items as $i => $rec) {
            $savingByType[$rec->type] = $rec->estimatedAnnualTaxSaved;
            $orderIndex[$rec->type] = $i;
        }

        $pairs = [];
        foreach ($items as $rec) {
            foreach (($metadata[$rec->type]['sequencing']['conflicts_with'] ?? []) as $other) {
                // array_key_exists, NOT isset: a present-but-null saving still
                // means the strategy is in the plan and its pair must resolve.
                if ($other === $rec->type || ! array_key_exists($other, $savingByType)) {
                    continue;
                }
                $key = collect([$rec->type, $other])->sort()->join('|');
                $pairs[$key] = [$rec->type, $other];
            }
        }

        $pairList = array_values($pairs);
        usort($pairList, function (array $x, array $y) use ($savingByType, $orderIndex): int {
            $maxX = max($savingByType[$x[0]] ?? 0.0, $savingByType[$x[1]] ?? 0.0);
            $maxY = max($savingByType[$y[0]] ?? 0.0, $savingByType[$y[1]] ?? 0.0);

            return $maxX === $maxY
                ? min($orderIndex[$x[0]], $orderIndex[$x[1]]) <=> min($orderIndex[$y[0]], $orderIndex[$y[1]])
                : $maxY <=> $maxX;
        });

        $excluded = [];
        $noteFor = []; // type => the preferred alternative it should name

        // Shared-ISA-allowance exclusions arrive pre-resolved from the
        // calculator's allocation pass (IsaAllowanceAllocator). Seed them
        // before conflict-pair processing so a pool-exhausted strategy cannot
        // knock its conflict alternative out of the combined total.
        foreach ($items as $rec) {
            if (($rec->extra[IsaAllowanceAllocator::EXCLUDED_FLAG] ?? false) === true) {
                $excluded[$rec->type] = true;
            }
        }

        foreach ($pairList as [$a, $b]) {
            $savingA = $savingByType[$a] ?? 0.0;
            $savingB = $savingByType[$b] ?? 0.0;

            $tie = $savingA === $savingB;
            if ($tie) {
                // Coin-flip pair: the earlier item in plan order counts toward
                // the total; both carry notes so the user compares.
                [$winner, $loser] = $orderIndex[$a] <= $orderIndex[$b] ? [$a, $b] : [$b, $a];
            } else {
                [$winner, $loser] = $savingA > $savingB ? [$a, $b] : [$b, $a];
            }

            if (isset($excluded[$winner])) {
                // The preferred alternative is itself excluded by a stronger
                // pair — the loser stays realisable, no note from this pair.
                continue;
            }

            if (! isset($excluded[$loser])) {
                $excluded[$loser] = true;
                // First note wins: if a stronger pair already named an
                // alternative, keep that one.
                $noteFor[$loser] = $winner;
            }

            if ($tie && ! isset($noteFor[$winner])) {
                $noteFor[$winner] = $loser;
            }
        }

        // 4. Build output items with annotations. array_merge (NOT the + union
        //    operator): composer-owned fields must win over any same-named keys
        //    leaking in from the DTO's extra[] via toArray().
        $out = [];
        foreach ($items as $index => $rec) {
            // Conflict-pair notes take precedence; otherwise surface the
            // shared-ISA-allowance note through the same channel so frontends
            // render both kinds of annotation identically.
            $isaNote = $rec->extra[IsaAllowanceAllocator::NOTE_FIELD] ?? null;
            $conflictNote = isset($noteFor[$rec->type])
                ? "Alternative to {$noteFor[$rec->type]} — compare before doing both."
                : (is_string($isaNote) ? $isaNote : null);

            $out[] = array_merge($rec->toArray(), [
                'claim_tier' => $metadata[$rec->type]['claim_tier'] ?? 'judgement',
                'sequence_position' => $index + 1,
                'conflict_note' => $conflictNote,
            ]);
        }

        // 5. Combined annual saving: sum of all items EXCEPT excluded conflict alternatives.
        $total = 0.0;
        foreach ($items as $rec) {
            if (! isset($excluded[$rec->type]) && $rec->estimatedAnnualTaxSaved !== null) {
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
