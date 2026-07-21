<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\PlanSources\EstateStrategySource;
use App\Services\Coordination\PlanSources\ModuleStrategySource;

/** Live composed estate plan for the FynLoop. */
final class EstatePlanHandler extends ModulePlanHandler
{
    public function __construct(
        ComposedModulePlanService $plans,
        private readonly EstateStrategySource $estateSource,
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
}
