<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Plans;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Plans\EstatePlanService;
use App\Services\Plans\GoalPlanService;
use App\Services\Plans\InvestmentPlanService;
use App\Services\Plans\PlanConfigService;
use App\Services\Plans\ProtectionPlanService;
use App\Services\Plans\RetirementPlanService;
use App\Services\Plans\WhatIfCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PlanController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly InvestmentPlanService $investmentPlanService,
        private readonly ProtectionPlanService $protectionPlanService,
        private readonly RetirementPlanService $retirementPlanService,
        private readonly GoalPlanService $goalPlanService,
        private readonly EstatePlanService $estatePlanService,
        private readonly WhatIfCalculator $whatIfCalculator,
        private readonly PlanConfigService $planConfig
    ) {}

    /**
     * Generate plan for the given type.
     */
    public function generate(Request $request, string $type): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $cacheKey = "plan_{$type}_{$userId}";

            $plan = Cache::remember($cacheKey, $this->planConfig->getPlanCacheTTL(), function () use ($type, $userId) {
                return $this->getPlanService($type)->generatePlan($userId);
            });

            return response()->json([
                'success' => true,
                'data' => $plan,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationErrorResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->errorResponse($e, "Generating {$type} plan");
        }
    }

    /**
     * Generate a goal-specific plan.
     */
    public function generateGoalPlan(Request $request, int $goalId): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $cacheKey = "plan_goal_{$goalId}_{$userId}";

            $plan = Cache::remember($cacheKey, $this->planConfig->getPlanCacheTTL(), function () use ($userId, $goalId) {
                return $this->goalPlanService->generatePlan($userId, ['goal_id' => $goalId]);
            });

            return response()->json([
                'success' => true,
                'data' => $plan,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Generating goal plan');
        }
    }

    /**
     * Recalculate what-if scenario with specific enabled actions.
     */
    public function recalculate(Request $request, string $type): JsonResponse
    {
        $userId = $request->user()->id;
        $enabledActionIds = $request->input('enabled_action_ids', []);

        try {
            $plan = $this->whatIfCalculator->recalculate($type, $userId, $enabledActionIds);

            return response()->json([
                'success' => true,
                'data' => $plan,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationErrorResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->errorResponse($e, "Recalculating {$type} plan");
        }
    }

    /**
     * Recalculate what-if scenario for a goal plan.
     */
    public function recalculateGoalPlan(Request $request, int $goalId): JsonResponse
    {
        $userId = $request->user()->id;
        $enabledActionIds = $request->input('enabled_action_ids', []);

        try {
            $plan = $this->whatIfCalculator->recalculate('goal', $userId, $enabledActionIds, ['goal_id' => $goalId]);

            return response()->json([
                'success' => true,
                'data' => $plan,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Recalculating goal plan');
        }
    }

    /**
     * Clear plan cache.
     */
    public function clearCache(Request $request, string $type): JsonResponse
    {
        $userId = $request->user()->id;
        $cacheKey = "plan_{$type}_{$userId}";

        Cache::forget($cacheKey);

        return response()->json([
            'success' => true,
            'message' => 'Plan cache cleared successfully.',
        ]);
    }

    /**
     * Get plan readiness statuses for the dashboard.
     */
    public function statuses(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $statuses = [
                'investment' => $this->investmentPlanService->checkDataCompleteness($userId),
                'protection' => $this->protectionPlanService->checkDataCompleteness($userId),
                'retirement' => $this->retirementPlanService->checkDataCompleteness($userId),
                'estate' => $this->estatePlanService->checkDataCompleteness($userId),
            ];

            return response()->json([
                'success' => true,
                'data' => $statuses,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching plan statuses');
        }
    }

    /**
     * Resolve the plan service for a given type.
     */
    private function getPlanService(string $type): InvestmentPlanService|ProtectionPlanService|RetirementPlanService|EstatePlanService
    {
        return match ($type) {
            'investment' => $this->investmentPlanService,
            'protection' => $this->protectionPlanService,
            'retirement' => $this->retirementPlanService,
            'estate' => $this->estatePlanService,
            default => throw new \InvalidArgumentException("Unknown plan type: {$type}"),
        };
    }
}
