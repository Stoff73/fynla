<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchResult;
use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\PlanSources\ModuleStrategySource;
use Illuminate\Support\Carbon;

/**
 * Generalised RecommendationHandler — exposes any module's composed plan as a
 * live pointer fetch. Each concrete subclass binds one ModuleStrategySource;
 * the value encoding mirrors RecommendationHandler (digest preimage) but keyed
 * by `composed_{module}_plan` so per-module digests never collide with the tax
 * `composed_tax_plan` parity namespace. Read-side only — composing a plan never
 * writes anything, so these pointers are safe on the read-only advice surface.
 */
abstract class ModulePlanHandler implements FetchHandler
{
    public function __construct(protected readonly ComposedModulePlanService $plans) {}

    /** Stable module key: 'retirement', 'savings', 'investment', 'protection', 'estate'. */
    abstract protected function moduleKey(): string;

    abstract protected function source(): ModuleStrategySource;

    public function id(): string
    {
        return $this->moduleKey().'-plan';
    }

    public function fetch(FetchContext $ctx): FetchResult
    {
        $plan = $this->plans->forSource($this->source(), $ctx->user);
        $ids = ComposedModulePlanService::extractStrategyIds($plan);

        return FetchResult::make(
            (string) json_encode(
                ['composed_'.$this->moduleKey().'_plan' => $plan],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
            $this->moduleKey().' plan engine',
            Carbon::now()->toDateString(),
            [
                'strategy_ids' => implode(',', $ids['surfaced']),
                'locked_strategy_ids' => implode(',', $ids['locked']),
            ],
        );
    }
}
