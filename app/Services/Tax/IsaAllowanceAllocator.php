<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\DataTransferObjects\StrategyRecommendation;

/**
 * Greedy two-pass allocation of the ONE overall ISA allowance each person
 * has per tax year.
 *
 * Several strategies size themselves against the user's remaining ISA
 * allowance — the cash wrap (isa_topup_vs_psa), Bed & ISA (bed_and_isa) and
 * the Lifetime ISA (lifetime_isa). Evaluated independently (pass 1) they each
 * assume the FULL remaining allowance, so their combined sizings can count
 * the same allowance capacity up to three times. This pass re-allocates the
 * pool by value:
 *
 *   - Consumers are ranked by estimated annual saving, descending (stable on
 *     ties). The winner keeps its full pass-1 sizing.
 *   - Each subsequent consumer is re-evaluated by its OWN evaluator with the
 *     remaining pool capacity (TaxStrategyContext::isaPoolCap), so its own
 *     maths produces the honest saving on the smaller amount — savings are
 *     never pro-rata-scaled outside the evaluator. When the remaining
 *     capacity already covers the consumer's pass-1 draw, the original
 *     recommendation is kept byte-identical.
 *   - A consumer whose remaining capacity is zero, or below the floor its
 *     evaluator enforces, stays in the plan but is flagged
 *     isa_allowance_excluded so StrategyPlanComposer keeps it out of
 *     combined_annual_saving, and carries an isa_allowance_note naming the
 *     strategies that used the pool — mirroring the conflict_note mechanism.
 *
 * The spouse's allowance is the spouse's own separate pool (the spouse-side
 * isa_topup_spouse / isa_coordination strategies are mode-exclusive and emit
 * no savings figure, so no spouse-side allocation is needed), and the Junior
 * ISA is the child's own per-child pool — neither is touched here.
 */
final class IsaAllowanceAllocator
{
    public const EXCLUDED_FLAG = 'isa_allowance_excluded';

    public const NOTE_FIELD = 'isa_allowance_note';

    /**
     * Strategy type → the extra[] field carrying the £ amount the
     * recommendation draws from the user's overall ISA allowance.
     */
    private const POOL_DRAW_FIELD = [
        'isa_topup_vs_psa' => 'suggested_transfer_amount',
        'bed_and_isa' => 'estimated_proceeds_to_transfer',
        'lifetime_isa' => 'suggested_contribution',
    ];

    /**
     * @param  list<StrategyRecommendation>  $recommendations  Pass-1 output of every strategy.
     * @param  array<string, callable(float): list<StrategyRecommendation>>  $reEvaluators  type → re-run its evaluator with the given remaining pool capacity.
     * @param  float  $pool  The user's remaining overall ISA allowance for the year.
     * @return list<StrategyRecommendation>
     */
    public function allocate(array $recommendations, array $reEvaluators, float $pool): array
    {
        $consumers = [];
        foreach ($recommendations as $i => $rec) {
            if (isset(self::POOL_DRAW_FIELD[$rec->type])) {
                $consumers[$rec->type] = $i;
            }
        }

        // Zero or one consumer — nothing shares the pool; pass-1 output is
        // already honest and must stay byte-identical.
        if (count($consumers) < 2) {
            return $recommendations;
        }

        $ranked = array_keys($consumers);
        usort($ranked, function (string $a, string $b) use ($recommendations, $consumers): int {
            $bySaving = ($recommendations[$consumers[$b]]->estimatedAnnualTaxSaved ?? 0.0)
                <=> ($recommendations[$consumers[$a]]->estimatedAnnualTaxSaved ?? 0.0);

            return $bySaving !== 0 ? $bySaving : ($consumers[$a] <=> $consumers[$b]);
        });

        $remaining = max(0.0, $pool);
        $drawers = [];

        foreach ($ranked as $position => $type) {
            $idx = $consumers[$type];
            $rec = $recommendations[$idx];
            $wanted = $this->poolDraw($rec);

            // The highest-saving consumer keeps its full pass-1 sizing; every
            // later consumer whose draw still fits the remaining pool is also
            // left untouched.
            if ($position === 0 || ($remaining > 0.0 && $wanted <= $remaining)) {
                $remaining = max(0.0, $remaining - $wanted);
                if ($wanted > 0.0) {
                    $drawers[] = $type;
                }

                continue;
            }

            $usedBy = $drawers === [] ? $ranked[0] : implode(' and ', $drawers);

            if ($remaining <= 0.0) {
                $recommendations[$idx] = $this->withExtra($rec, [
                    self::EXCLUDED_FLAG => true,
                    self::NOTE_FIELD => "Draws on the same ISA allowance, already fully used by {$usedBy}.",
                ]);

                continue;
            }

            // Re-evaluate with the remaining capacity so the strategy's own
            // maths produces the honest saving on the smaller amount.
            $replacement = null;
            foreach ((isset($reEvaluators[$type]) ? ($reEvaluators[$type])($remaining) : []) as $regenerated) {
                if ($regenerated->type === $type) {
                    $replacement = $regenerated;
                    break;
                }
            }

            // The evaluator's own floor rejected the remaining capacity —
            // exclude rather than show a figure its maths can't support.
            if ($replacement === null) {
                $recommendations[$idx] = $this->withExtra($rec, [
                    self::EXCLUDED_FLAG => true,
                    self::NOTE_FIELD => "Draws on the same ISA allowance, already fully used by {$usedBy}.",
                ]);

                continue;
            }

            $recommendations[$idx] = $this->withExtra($replacement, [
                self::NOTE_FIELD => "Sized to the ISA allowance left after {$usedBy} — every ISA type shares one annual ISA allowance per tax year.",
            ]);
            $remaining = max(0.0, $remaining - $this->poolDraw($replacement));
            $drawers[] = $type;
        }

        return $recommendations;
    }

    private function poolDraw(StrategyRecommendation $rec): float
    {
        return (float) ($rec->extra[self::POOL_DRAW_FIELD[$rec->type]] ?? 0.0);
    }

    /**
     * @param  array<string, mixed>  $additions
     */
    private function withExtra(StrategyRecommendation $rec, array $additions): StrategyRecommendation
    {
        return new StrategyRecommendation(
            type: $rec->type,
            category: $rec->category,
            priority: $rec->priority,
            title: $rec->title,
            description: $rec->description,
            estimatedAnnualTaxSaved: $rec->estimatedAnnualTaxSaved,
            requiresAdvice: $rec->requiresAdvice,
            extra: array_merge($rec->extra, $additions),
        );
    }
}
