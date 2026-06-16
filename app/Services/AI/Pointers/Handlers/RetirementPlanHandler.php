<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\PlanSources\ModuleStrategySource;
use App\Services\Coordination\PlanSources\RetirementStrategySource;

/** Live composed retirement plan for the FynLoop. */
final class RetirementPlanHandler extends ModulePlanHandler
{
    public function __construct(
        ComposedModulePlanService $plans,
        private readonly RetirementStrategySource $retirementSource,
    ) {
        parent::__construct($plans);
    }

    protected function moduleKey(): string
    {
        return 'retirement';
    }

    protected function source(): ModuleStrategySource
    {
        return $this->retirementSource;
    }
}
