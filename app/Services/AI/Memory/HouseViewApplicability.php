<?php

declare(strict_types=1);

namespace App\Services\AI\Memory;

use App\Models\TaxActionDefinition;
use App\Models\User;
use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\HouseholdFinancialContext;

/**
 * Keeps a strategy's house view out of Fyn's knowledge when that strategy
 * cannot apply to the user, by the plan's own rule
 * (ComposedModulePlanService::cannotApply). Retrieval is by keyword, so a
 * question about pensions loaded the carry forward guide for a £26,000
 * earner, and Fyn told her the plan needed her pension contribution history
 * (csjones, 2026-10-08; CSJ: never asked unless carry forward could apply).
 *
 * A house view's fact id is "hv-" plus its strategy type with dashes
 * (hv-pension-aa-carry-forward is pension_aa_carry_forward). Every other fact
 * passes through.
 */
final class HouseViewApplicability
{
    private const PREFIX = 'hv-';

    public function __construct(private readonly HouseholdFinancialContext $context) {}

    /**
     * @param  list<SemanticFact>  $facts
     * @return list<SemanticFact>
     */
    public function filter(array $facts, User $user): array
    {
        $strategyTypes = [];
        foreach ($facts as $fact) {
            if ($fact->category === 'house_view' && str_starts_with($fact->factId, self::PREFIX)) {
                $strategyTypes[$fact->factId] = str_replace('-', '_', substr($fact->factId, strlen(self::PREFIX)));
            }
        }
        if ($strategyTypes === []) {
            return $facts;
        }

        $required = TaxActionDefinition::where('source', 'strategy')
            ->whereIn('strategy_type', array_values($strategyTypes))
            ->pluck('required_data', 'strategy_type');
        $availability = $this->context->availability($user);

        return array_values(array_filter($facts, function (SemanticFact $fact) use ($strategyTypes, $required, $availability): bool {
            $type = $strategyTypes[$fact->factId] ?? null;

            return $type === null
                || ! ComposedModulePlanService::cannotApply((array) ($required[$type] ?? []), $availability);
        }));
    }
}
