<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\PlanSources\ModuleStrategySource;
use App\Services\Coordination\PlanSources\SavingsStrategySource;

/** Live composed savings plan for the FynLoop. */
final class SavingsPlanHandler extends ModulePlanHandler
{
    public function __construct(
        ComposedModulePlanService $plans,
        private readonly SavingsStrategySource $savingsSource,
    ) {
        parent::__construct($plans);
    }

    protected function moduleKey(): string
    {
        return 'savings';
    }

    protected function source(): ModuleStrategySource
    {
        return $this->savingsSource;
    }
}
