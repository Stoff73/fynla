<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\Models\User;
use App\Services\Coordination\PlanSources\TaxStrategySource;

/**
 * The tax facade. Now a thin adapter over ComposedModulePlanService +
 * TaxStrategySource — preserved as a named entry point because Fyn's tax paths
 * (CoordinatingAgent::handleRecommendations, RecommendationHandler::fetch) and
 * the parity tests reference it by name. forUser + the two statics are
 * byte-stable; the locked-strategy / claim-tier behaviour is unchanged.
 */
final class ComposedTaxPlanService
{
    /**
     * WP-5c-iii — per-request memo (the service is container-scoped). The
     * dashboard read composes the plan for strategy unlocks; memoising lets
     * milestone detection reuse it in the same request instead of paying for
     * a second composition — and forUserIfComputed() lets it decline when the
     * plan was never needed (e.g. the tax gate is closed).
     *
     * @var array<int, array{items: list<array<string,mixed>>, combined_annual_saving: float, locked: list<array{strategy_type: string, missing: list<string>}>}>
     */
    private array $memo = [];

    public function __construct(
        private readonly ComposedModulePlanService $composedModulePlanService,
        private readonly TaxStrategySource $taxSource,
    ) {}

    /**
     * @return array{items: list<array<string,mixed>>, combined_annual_saving: float, locked: list<array{strategy_type: string, missing: list<string>}>}
     */
    public function forUser(User $user): array
    {
        return $this->memo[$user->id] ??= $this->composedModulePlanService->forSource($this->taxSource, $user);
    }

    /**
     * WP-5c-iii — the memoised plan if some earlier caller this request
     * already composed it; null otherwise (never triggers a composition).
     *
     * @return array{items: list<array<string,mixed>>, combined_annual_saving: float, locked: list<array{strategy_type: string, missing: list<string>}>}|null
     */
    public function forUserIfComputed(User $user): ?array
    {
        return $this->memo[$user->id] ?? null;
    }

    /**
     * The shared home for strategy-id derivation — delegated to the
     * module-agnostic ComposedModulePlanService so the two provenance paths
     * (RecommendationHandler::fetch and CoordinatingAgent::handleRecommendations)
     * can never drift apart.
     *
     * @param  array{items: list<array<string,mixed>>, locked: list<array{strategy_type: string, missing: list<string>}>}  $plan
     * @return array{surfaced: list<string>, locked: list<string>}
     */
    public static function extractStrategyIds(array $plan): array
    {
        return ComposedModulePlanService::extractStrategyIds($plan);
    }

    /**
     * The harmonised plan digest — delegated to ComposedModulePlanService.
     * Byte-stable; pinned by RecommendationHandlerParityTest.
     *
     * @param  array<string, mixed>  $plan
     */
    public static function planDigest(array $plan): string
    {
        return ComposedModulePlanService::planDigest($plan);
    }
}
