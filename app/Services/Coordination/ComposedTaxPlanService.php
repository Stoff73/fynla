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

    /**
     * The shared home for strategy-id derivation (§6e) — surfaced item types
     * + locked strategy types, consumed by both provenance paths
     * (RecommendationHandler::fetch and CoordinatingAgent::handleRecommendations)
     * so they can never drift apart.
     *
     * @param  array{items: list<array<string,mixed>>, locked: list<array{strategy_type: string, missing: list<string>}>}  $plan
     * @return array{surfaced: list<string>, locked: list<string>}
     */
    public static function extractStrategyIds(array $plan): array
    {
        $surfaced = array_values(array_filter(array_map(
            static fn (array $item): string => (string) ($item['type'] ?? ''),
            $plan['items'],
        ), static fn (string $id): bool => $id !== ''));

        $locked = array_values(array_map(
            static fn (array $l): string => (string) $l['strategy_type'],
            $plan['locked'],
        ));

        return ['surfaced' => $surfaced, 'locked' => $locked];
    }

    /**
     * The harmonised plan digest (§6e): the same encoding
     * RecommendationHandler::fetch passes to FetchResult::make, so the same
     * plan yields the same digest on the skill and tool paths — pinned by
     * RecommendationHandlerParityTest against the FetchResult derivation.
     *
     * @param  array<string, mixed>  $plan
     */
    public static function planDigest(array $plan): string
    {
        return substr(hash('sha256', (string) json_encode(['composed_tax_plan' => $plan], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 0, 16);
    }
}
