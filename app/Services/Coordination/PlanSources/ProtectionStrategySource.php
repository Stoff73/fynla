<?php

declare(strict_types=1);

namespace App\Services\Coordination\PlanSources;

use App\DataTransferObjects\StrategyRecommendation;
use App\Models\ProtectionActionDefinition;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Coordination\PlanSources\Adapters\ProtectionRecommendationAdapter;
use App\Services\Protection\CoverageGapAnalyzer;
use App\Services\Protection\LifeCoverReach;
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
        private readonly LifeCoverReach $lifeCoverReach,
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

            // The policies covering this user's LIFE. This class "mirrors
            // ProtectionAgent::analyze()" by its own docblock — but the agent was routed
            // to the reach in W-0186 and the mirror was not, so the non-owning spouse was
            // recommended cover she already holds: `recommendations(Sarah)` returned
            // "Add decreasing term cover for debts" while the agent, feeding the SAME
            // RecommendationEngine, reported `debt_protection_gap = 0` (W-0401).
            //
            // A joint-life policy covers both spouses and is recorded once, on the account
            // that entered it, so the plain `user_id` hasMany stops at the owner.
            // `LifeCoverReach` is the one home for the question (Rule 20).
            //
            // **Critical illness stays the plain relation.** `critical_illness_policies`
            // has no `joint_life`, no `joint_owner_id` and no ownership columns at all
            // (verified with `SHOW COLUMNS`), so it covers only its owner.
            $coverage = $this->gapAnalyzer->calculateTotalCoverage(
                $this->lifeCoverReach->policiesCovering($user),
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
