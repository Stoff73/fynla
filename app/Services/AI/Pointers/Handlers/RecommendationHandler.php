<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchResult;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\Mobile\NextActionsService;
use Illuminate\Support\Carbon;

/** Engine archetype — the user's actions list and the live composed tax plan. */
final class RecommendationHandler implements FetchHandler
{
    public function __construct(
        private readonly ComposedTaxPlanService $plans,
        private readonly NextActionsService $actions,
    ) {}

    public function id(): string
    {
        return 'recommendations';
    }

    public function fetch(FetchContext $ctx): FetchResult
    {
        // §6b — the skill returns what get_recommendations returns: the user's
        // actions list (audit item 50; it returned only the tax plan, so a
        // household whose tax strategies were all locked heard "your list is
        // empty" beside ten open actions) and the composed tax plan.
        $plan = $this->plans->forUser($ctx->user);

        $ids = ComposedTaxPlanService::extractStrategyIds($plan);

        // The digest stays the plan's (ComposedTaxPlanService::planDigest), the
        // one the tool path records, so skill and tool provenance still match
        // (§6e, parity-pinned).
        return new FetchResult(
            (string) json_encode([
                'recommendations' => $this->actions->forModel($ctx->user->id),
                'composed_tax_plan' => $plan,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'recommendation engine',
            Carbon::now()->toDateString(),
            ComposedTaxPlanService::planDigest($plan),
            [
                'strategy_ids' => implode(',', $ids['surfaced']),
                'locked_strategy_ids' => implode(',', $ids['locked']),
            ],
        );
    }
}
