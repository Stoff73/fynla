<?php

declare(strict_types=1);

namespace App\Services\Plans;

/**
 * Handles precise backend recalculation of what-if scenarios
 * when the user clicks "Recalculate" (as opposed to approximate frontend calcs).
 */
class WhatIfCalculator
{
    public function __construct(
        private readonly InvestmentPlanService $investmentPlanService,
        private readonly ProtectionPlanService $protectionPlanService,
        private readonly RetirementPlanService $retirementPlanService,
        private readonly GoalPlanService $goalPlanService,
        private readonly EstatePlanService $estatePlanService
    ) {}

    /**
     * Recalculate a plan with only the specified actions enabled.
     *
     * Passes enabled_action_ids through options so generatePlan() builds
     * the what-if data with the correct set of enabled actions.
     */
    public function recalculate(string $planType, int $userId, array $enabledActionIds, array $options = []): array
    {
        $service = $this->resolveService($planType);

        // Generate a fresh plan with the user's action selections applied
        $plan = $service->generatePlan($userId, array_merge($options, [
            'enabled_action_ids' => $enabledActionIds,
        ]));

        // Mark as precise (backend-calculated, not approximate)
        if (isset($plan['what_if'])) {
            $plan['what_if']['is_approximate'] = false;
        }

        return $plan;
    }

    private function resolveService(string $planType): BasePlanService
    {
        return match ($planType) {
            'investment' => $this->investmentPlanService,
            'protection' => $this->protectionPlanService,
            'retirement' => $this->retirementPlanService,
            'goal' => $this->goalPlanService,
            'estate' => $this->estatePlanService,
            default => throw new \InvalidArgumentException("Unknown plan type: {$planType}"),
        };
    }
}
