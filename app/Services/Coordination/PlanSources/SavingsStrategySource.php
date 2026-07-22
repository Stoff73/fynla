<?php

declare(strict_types=1);

namespace App\Services\Coordination\PlanSources;

use App\Agents\SavingsAgent;
use App\Models\SavingsActionDefinition;
use App\Models\User;
use App\Services\Coordination\PlanSources\Adapters\SavingsRecommendationAdapter;
use Illuminate\Support\Collection;

/**
 * Savings module's plan source. Mirrors the RecommendationsAggregatorService
 * call pattern (analyze → generateRecommendations) for the recommendation feed,
 * the source='strategy' catalogue rows for metadata, and ModuleAvailabilityProvider
 * for the required_data vocabulary.
 */
final class SavingsStrategySource implements ModuleStrategySource
{
    public function __construct(
        private readonly SavingsAgent $agent,
        private readonly SavingsRecommendationAdapter $adapter,
        private readonly ModuleAvailabilityProvider $availability,
    ) {}

    public function moduleKey(): string
    {
        return 'savings';
    }

    public function recommendations(User $user): array
    {
        $analysis = $this->agent->analyze($user->id);

        // SavingsAgent::generateRecommendations reads user_id from the analysis
        // array to load accounts and run the action definition service.
        $data = $analysis['data'] ?? $analysis;
        $data['user_id'] = $user->id;

        $generated = $this->agent->generateRecommendations($data);

        return array_map(
            fn (array $r) => $this->adapter->toStrategyRecommendation($r),
            $generated
        );
    }

    public function metadataRows(): Collection
    {
        return SavingsActionDefinition::where('source', 'strategy')->where('is_enabled', true)->get();
    }

    public function availability(User $user): array
    {
        return $this->availability->forModule('savings', $user);
    }
}
