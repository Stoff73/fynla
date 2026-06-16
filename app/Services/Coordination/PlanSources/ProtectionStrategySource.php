<?php

declare(strict_types=1);

namespace App\Services\Coordination\PlanSources;

use App\DataTransferObjects\StrategyRecommendation;
use App\Models\ProtectionActionDefinition;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Coordination\PlanSources\Adapters\ProtectionRecommendationAdapter;
use App\Services\Protection\CoverageGapAnalyzer;
use App\Services\Protection\RecommendationEngine;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Protection module's plan source. Mirrors ProtectionAgent::analyze() for the
 * gap + profile build, then calls RecommendationEngine::generateRecommendations()
 * directly so the strategy source produces the same recommendations as the agent
 * without going through the full agent cache layer.
 *
 * A bare user (no ProtectionProfile) returns an empty recommendation list rather
 * than throwing — the locked-strategy mechanism surfaces the gap to the user
 * through the required_data vocabulary instead.
 */
final class ProtectionStrategySource implements ModuleStrategySource
{
    public function __construct(
        private readonly CoverageGapAnalyzer $gapAnalyzer,
        private readonly RecommendationEngine $recommendationEngine,
        private readonly ProtectionRecommendationAdapter $adapter,
        private readonly ModuleAvailabilityProvider $availability,
    ) {}

    public function moduleKey(): string
    {
        return 'protection';
    }

    /**
     * Build gaps + profile exactly as ProtectionAgent::analyze() does, then
     * delegate to RecommendationEngine. Returns [] for any user lacking a
     * ProtectionProfile or whose profile cannot be used to produce gaps.
     *
     * @return list<StrategyRecommendation>
     */
    public function recommendations(User $user): array
    {
        try {
            $user->loadMissing([
                'protectionProfile',
                'lifeInsurancePolicies',
                'criticalIllnessPolicies',
                'incomeProtectionPolicies',
                'disabilityPolicies',
                'sicknessIllnessPolicies',
            ]);

            /** @var ProtectionProfile|null $profile */
            $profile = $user->protectionProfile;

            if ($profile === null) {
                return [];
            }

            $needs = $this->gapAnalyzer->calculateProtectionNeeds($profile);
            $coverage = $this->gapAnalyzer->calculateTotalCoverage(
                $user->lifeInsurancePolicies,
                $user->criticalIllnessPolicies,
                $user->incomeProtectionPolicies,
                $user->disabilityPolicies,
                $user->sicknessIllnessPolicies,
                $profile,
                $user
            );
            $gaps = $this->gapAnalyzer->calculateCoverageGap($needs, $coverage);

            $recs = $this->recommendationEngine->generateRecommendations($gaps, $profile);

            return array_map(
                fn (array $r) => $this->adapter->toStrategyRecommendation($r),
                $recs
            );
        } catch (Throwable) {
            return [];
        }
    }

    public function metadataRows(): Collection
    {
        return ProtectionActionDefinition::where('source', 'strategy')
            ->where('is_enabled', true)
            ->get();
    }

    public function availability(User $user): array
    {
        return $this->availability->forModule('protection', $user);
    }
}
