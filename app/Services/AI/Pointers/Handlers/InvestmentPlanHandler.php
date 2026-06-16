<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\PlanSources\InvestmentStrategySource;
use App\Services\Coordination\PlanSources\ModuleStrategySource;

/** Live composed investment plan for the FynLoop. */
final class InvestmentPlanHandler extends ModulePlanHandler
{
    public function __construct(
        ComposedModulePlanService $plans,
        private readonly InvestmentStrategySource $investmentSource,
    ) {
        parent::__construct($plans);
    }

    protected function moduleKey(): string
    {
        return 'investment';
    }

    protected function source(): ModuleStrategySource
    {
        return $this->investmentSource;
    }
}
