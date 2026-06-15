<?php

declare(strict_types=1);

namespace App\Services\Coordination\PlanSources;

use App\Models\EstateActionDefinition;
use App\Models\User;
use App\Services\Coordination\PlanSources\Adapters\EstateRecommendationAdapter;
use App\Services\Estate\EstateActionDefinitionService;
use Illuminate\Support\Collection;

/**
 * Estate module's plan source. Calls EstateActionDefinitionService::evaluateActions()
 * for the live recommendation feed, the source='strategy' catalogue rows for metadata,
 * and ModuleAvailabilityProvider for the required_data vocabulary.
 *
 * The rec call is wrapped in try/catch: a bare user with no estate data returns []
 * rather than propagating an exception into the cross-module composer.
 */
final class EstateStrategySource implements ModuleStrategySource
{
    public function __construct(
        private readonly EstateActionDefinitionService $service,
        private readonly EstateRecommendationAdapter $adapter,
        private readonly ModuleAvailabilityProvider $availability,
    ) {}

    public function moduleKey(): string
    {
        return 'estate';
    }

    public function recommendations(User $user): array
    {
        try {
            $result = $this->service->evaluateActions($user);
            $recs = $result['recommendations'] ?? [];
        } catch (\Throwable) {
            return [];
        }

        return array_map(
            fn (array $r) => $this->adapter->toStrategyRecommendation($r),
            $recs
        );
    }

    public function metadataRows(): Collection
    {
        return EstateActionDefinition::where('source', 'strategy')
            ->where('is_enabled', true)
            ->get();
    }

    public function availability(User $user): array
    {
        return $this->availability->forModule('estate', $user);
    }
}
