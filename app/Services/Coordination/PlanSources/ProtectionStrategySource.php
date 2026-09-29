<?php

declare(strict_types=1);

namespace App\Services\Coordination\PlanSources;

use App\DataTransferObjects\StrategyRecommendation;
use App\Exceptions\FinancialCalculationException;
use App\Models\ProtectionActionDefinition;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Coordination\PlanSources\Adapters\ProtectionRecommendationAdapter;
use App\Services\Protection\ComprehensiveProtectionPlanService;
use App\Services\Protection\ProtectionActionDefinitionService;
use Illuminate\Support\Collection;

/**
 * Protection module's plan source: the protection action definitions
 * (ProtectionActionDefinitionService::evaluateActions over the comprehensive
 * plan), the same recommendations the Protection Plan page shows — so a card,
 * the plan page and a card's how-to all rest on one catalogue (CSJ 2026-09-29;
 * the fixed rules in RecommendationEngine disagreed with the plan page).
 *
 * The comprehensive plan builds on ProtectionAgent::analyze(), which reads life
 * cover through LifeCoverReach (W-0186, W-0401), so a joint-life policy reaches
 * the non-owning spouse. A user with no ProtectionProfile, or one the readiness
 * gate stops, gets no recommendations; the locked-strategy mechanism surfaces
 * the gap through the required_data vocabulary instead.
 */
final class ProtectionStrategySource implements ModuleStrategySource
{
    public function __construct(
        private readonly ComprehensiveProtectionPlanService $planService,
        private readonly ProtectionActionDefinitionService $definitions,
        private readonly ProtectionRecommendationAdapter $adapter,
        private readonly ModuleAvailabilityProvider $availability,
    ) {}

    public function moduleKey(): string
    {
        return 'protection';
    }

    /**
     * @return list<StrategyRecommendation>
     */
    public function recommendations(User $user): array
    {
        if (! ProtectionProfile::where('user_id', $user->id)->exists()) {
            return [];
        }

        try {
            $plan = $this->planService->generateComprehensiveProtectionPlan($user);
        } catch (FinancialCalculationException) {
            // No profile or readiness incomplete: nothing to recommend yet.
            return [];
        }

        return array_map(
            fn (array $r) => $this->adapter->toStrategyRecommendation($r),
            $this->definitions->evaluateActions($plan)
        );
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
