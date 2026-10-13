<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Services\AI\Pointers\FetchContext;
use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\PlanSources\ModuleStrategySource;
use App\Services\Coordination\PlanSources\RetirementStrategySource;
use App\Services\Retirement\RetirementHeadline;

/** Live composed retirement plan for the FynLoop. */
final class RetirementPlanHandler extends ModulePlanHandler
{
    public function __construct(
        ComposedModulePlanService $plans,
        private readonly RetirementStrategySource $retirementSource,
        private readonly RetirementHeadline $headline,
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

    /**
     * The Retirement page's own figures (RetirementHeadline, the one home), so
     * Fyn quotes the pot and income the page shows rather than working out its
     * own (walk fix plan T11, Q6). `dc_value_at_retirement` is the page's
     * "Projected Capital".
     */
    protected function alongside(FetchContext $ctx): array
    {
        return ['retirement_headline' => $this->headline->for($ctx->user)];
    }
}
