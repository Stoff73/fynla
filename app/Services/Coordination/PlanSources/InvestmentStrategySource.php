<?php

declare(strict_types=1);

namespace App\Services\Coordination\PlanSources;

use App\Agents\InvestmentAgent;
use App\Models\InvestmentActionDefinition;
use App\Models\User;
use App\Services\Coordination\PlanSources\Adapters\InvestmentRecommendationAdapter;
use Illuminate\Support\Collection;

/**
 * Investment module's plan source. Mirrors the RecommendationsAggregatorService
 * call pattern (analyze → generateRecommendations) for the recommendation feed,
 * the source='strategy' catalogue rows for metadata, and ModuleAvailabilityProvider
 * for the required_data vocabulary.
 *
 * Note: InvestmentAgent::generateRecommendations() accepts the flat analysis array
 * directly (it does not wrap output in a 'data' key), so the `$analysis['data'] ??
 * $analysis` fallback resolves to $analysis in all cases.
 */
final class InvestmentStrategySource implements ModuleStrategySource
{
    public function __construct(
        private readonly InvestmentAgent $agent,
        private readonly InvestmentRecommendationAdapter $adapter,
        private readonly ModuleAvailabilityProvider $availability,
    ) {}

    public function moduleKey(): string
    {
        return 'investment';
    }

    public function recommendations(User $user): array
    {
        $analysis = $this->agent->analyze($user->id);
        $data = $analysis['data'] ?? $analysis;
        $generated = $this->agent->generateRecommendations($data);

        return array_map(
            fn (array $r) => $this->adapter->toStrategyRecommendation($r),
            $generated['recommendations'] ?? []
        );
    }

    public function metadataRows(): Collection
    {
        return InvestmentActionDefinition::where('source', 'strategy')->where('is_enabled', true)->get();
    }

    public function availability(User $user): array
    {
        return $this->availability->forModule('investment', $user);
    }
}
