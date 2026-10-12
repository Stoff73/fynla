<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Agents\EstateAgent;
use App\Services\AI\Pointers\FetchContext;
use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\PlanSources\EstateStrategySource;
use App\Services\Coordination\PlanSources\ModuleStrategySource;

/** Live composed estate plan for the FynLoop. */
final class EstatePlanHandler extends ModulePlanHandler
{
    public function __construct(
        ComposedModulePlanService $plans,
        private readonly EstateStrategySource $estateSource,
        private readonly EstateAgent $estateAgent,
    ) {
        parent::__construct($plans);
    }

    protected function moduleKey(): string
    {
        return 'estate';
    }

    protected function source(): ModuleStrategySource
    {
        return $this->estateSource;
    }

    /**
     * The Inheritance Tax figure and the steps behind it, from the same
     * calculation `get_module_analysis(estate)` carries (EstateAgent, which
     * calls IHTCalculationService), so Fyn's two estate tools cannot quote
     * different figures (walk fix plan T12, M8 and Q8). Empty when the
     * analysis has no calculation (readiness or tier withheld it).
     */
    protected function alongside(FetchContext $ctx): array
    {
        $calculation = $this->estateAgent->analyze($ctx->user->id)['data']['iht_calculation'] ?? null;
        if (! is_array($calculation)) {
            return [];
        }

        return ['inheritance_tax' => array_intersect_key($calculation, array_flip([
            'total_net_estate', 'total_allowances', 'taxable_estate', 'iht_rate_percent', 'iht_liability',
            'estimated_age_at_death', 'years_to_death', 'projected_net_estate', 'projected_iht_liability',
            'working',
        ]))];
    }
}
