<?php

declare(strict_types=1);

namespace App\Services\Coordination\PlanSources;

use App\Agents\RetirementAgent;
use App\Models\RetirementActionDefinition;
use App\Models\User;
use App\Services\Coordination\PlanSources\Adapters\RetirementRecommendationAdapter;
use Illuminate\Support\Collection;

/**
 * Retirement module's plan source. Mirrors the RecommendationsAggregatorService
 * call pattern (analyze → generateRecommendations) for the recommendation feed,
 * the source='strategy' catalogue rows for metadata, and ModuleAvailabilityProvider
 * for the required_data vocabulary.
 */
final class RetirementStrategySource implements ModuleStrategySource
{
    public function __construct(
        private readonly RetirementAgent $agent,
        private readonly RetirementRecommendationAdapter $adapter,
        private readonly ModuleAvailabilityProvider $availability,
    ) {}

    public function moduleKey(): string
    {
        return 'retirement';
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
        return RetirementActionDefinition::where('source', 'strategy')->where('is_enabled', true)->get();
    }

    public function availability(User $user): array
    {
        return $this->availability->forModule('retirement', $user);
    }
}
