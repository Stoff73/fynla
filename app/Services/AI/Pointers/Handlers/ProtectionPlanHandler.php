<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\PlanSources\ModuleStrategySource;
use App\Services\Coordination\PlanSources\ProtectionStrategySource;

/** Live composed protection plan for the FynLoop. */
final class ProtectionPlanHandler extends ModulePlanHandler
{
    public function __construct(
        ComposedModulePlanService $plans,
        private readonly ProtectionStrategySource $protectionSource,
    ) {
        parent::__construct($plans);
    }

    protected function moduleKey(): string
    {
        return 'protection';
    }

    protected function source(): ModuleStrategySource
    {
        return $this->protectionSource;
    }
}
