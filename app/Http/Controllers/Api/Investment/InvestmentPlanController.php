<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Investment;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Investment\InvestmentPlan;
use App\Services\Investment\InvestmentPlanGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Investment Plan Controller
 * Manages comprehensive investment plan generation and retrieval
 */
class InvestmentPlanController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private InvestmentPlanGenerator $planGenerator
    ) {}

    /**
     * Generate a comprehensive investment plan
     *
     * POST /api/investment/generate-plan
     */
    public function generatePlan(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            Log::info('Generating investment plan', ['user_id' => $user->id]);

            $result = $this->planGenerator->generatePlan($user->id);

            // Clear cached plan
            Cache::forget("investment_plan_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Investment plan generated successfully',
                'data' => $result,
            ], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Generating investment plan', 500, ['user_id' => $user->id]);
        }
    }

    /**
     * Get the latest investment plan
     *
     * GET /api/investment/plan
     */
    public function getLatestPlan(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $plan = Cache::remember(
                "investment_plan_{$user->id}",
                3600, // 1 hour
                function () use ($user) {
                    return InvestmentPlan::where('user_id', $user->id)
                        ->orderBy('generated_at', 'desc')
                        ->first();
                }
            );

            if (! $plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'No investment plan found. Generate one first.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $plan->id,
                    'plan_version' => $plan->plan_version,
                    'plan_data' => $plan->plan_data,
                    'portfolio_health_score' => $plan->portfolio_health_score,
                    'is_complete' => $plan->is_complete,
                    'generated_at' => $plan->generated_at,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Retrieving investment plan', 500, ['user_id' => $user->id]);
        }
    }

    /**
     * Get a specific investment plan by ID
     *
     * GET /api/investment/plan/{id}
     */
    public function getPlanById(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $plan = InvestmentPlan::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (! $plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Investment plan not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $plan->id,
                    'plan_version' => $plan->plan_version,
                    'plan_data' => $plan->plan_data,
                    'portfolio_health_score' => $plan->portfolio_health_score,
                    'is_complete' => $plan->is_complete,
                    'generated_at' => $plan->generated_at,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Retrieving investment plan', 500, ['user_id' => $user->id, 'plan_id' => $id]);
        }
    }

    /**
     * Get all investment plans for the user
     *
     * GET /api/investment/plans
     */
    public function getAllPlans(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $plans = InvestmentPlan::where('user_id', $user->id)
                ->orderBy('generated_at', 'desc')
                ->get()
                ->map(function ($plan) {
                    return [
                        'id' => $plan->id,
                        'plan_version' => $plan->plan_version,
                        'portfolio_health_score' => $plan->portfolio_health_score,
                        'is_complete' => $plan->is_complete,
                        'generated_at' => $plan->generated_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $plans,
                'count' => $plans->count(),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Retrieving investment plans', 500, ['user_id' => $user->id]);
        }
    }

    /**
     * Delete an investment plan
     *
     * DELETE /api/investment/plan/{id}
     */
    public function deletePlan(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $plan = InvestmentPlan::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (! $plan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Investment plan not found',
                ], 404);
            }

            $plan->delete();

            // Clear cache
            Cache::forget("investment_plan_{$user->id}");

            return response()->json([
                'success' => true,
                'message' => 'Investment plan deleted successfully',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Deleting investment plan', 500, ['user_id' => $user->id, 'plan_id' => $id]);
        }
    }

    /**
     * Clear cached plan
     *
     * DELETE /api/investment/plan/clear-cache
     */
    public function clearCache(Request $request): JsonResponse
    {
        $user = $request->user();

        Cache::forget("investment_plan_{$user->id}");

        return response()->json([
            'success' => true,
            'message' => 'Cache cleared successfully',
        ]);
    }
}
