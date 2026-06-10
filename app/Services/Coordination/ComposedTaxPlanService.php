<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\DataTransferObjects\StrategyRecommendation;
use App\Models\TaxActionDefinition;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;

/**
 * The one entry point: user → composed tax plan. Joins the calculator's
 * eligible recommendations with catalogue metadata, and derives the locked
 * list — enabled strategy rows whose required_data are not all available
 * and which produced no recommendation. Locked strategies are surfaced as
 * unlock prompts, never silently skipped. A strategy whose required_data are
 * all present but which still produced no recommendation was assessed and
 * found inapplicable — it appears in neither items nor locked.
 */
final class ComposedTaxPlanService
{
    public function __construct(
        private readonly TaxStrategyCalculator $calculator,
        private readonly HouseholdFinancialContext $context,
        private readonly StrategyPlanComposer $composer,
    ) {}

    /**
     * @return array{items: list<array<string,mixed>>, combined_annual_saving: float, locked: list<array{strategy_type: string, missing: list<string>}>}
     */
    public function forUser(User $user): array
    {
        $output = $this->calculator->calculate($user);

        $recommendations = array_map(
            fn (array $r) => StrategyRecommendation::fromArray((string) $r['category'], $r),
            $output->recommendations
        );

        $rows = TaxActionDefinition::where('source', 'strategy')->where('is_enabled', true)->get();

        $metadata = [];
        foreach ($rows as $row) {
            $metadata[$row->strategy_type] = [
                'claim_tier' => (string) $row->claim_tier,
                'sequencing' => $row->sequencing ?? ['do_before' => [], 'conflicts_with' => []],
            ];
        }

        $availability = $this->context->availability($user);
        $firedTypes = array_map(fn (StrategyRecommendation $r): string => $r->type, $recommendations);

        $locked = [];
        foreach ($rows as $row) {
            $missing = array_values(array_filter(
                (array) ($row->required_data ?? []),
                fn (string $key): bool => ($availability[$key] ?? false) === false
            ));
            if ($missing !== [] && ! in_array($row->strategy_type, $firedTypes, true)) {
                $locked[] = ['strategy_type' => (string) $row->strategy_type, 'missing' => $missing];
            }
        }

        return $this->composer->compose($recommendations, $metadata, $locked);
    }
}
