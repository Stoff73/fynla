<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Plans;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Plans\InvestmentSavingsPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class InvestmentSavingsPlanController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private InvestmentSavingsPlanService $planService
    ) {}

    /**
     * Generate comprehensive Investment & Savings Plan
     */
    public function generate(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        try {
            // Cache the plan for 30 minutes (balances freshness with performance)
            $cacheKey = "investment_savings_plan_{$userId}";

            $plan = Cache::remember($cacheKey, 1800, function () use ($userId) {
                return $this->planService->generatePlan($userId);
            });

            return response()->json([
                'success' => true,
                'data' => $plan,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Generating investment savings plan');
        }
    }

    /**
     * Clear plan cache (useful after data updates)
     */
    public function clearCache(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $cacheKey = "investment_savings_plan_{$userId}";

        Cache::forget($cacheKey);

        return response()->json([
            'success' => true,
            'message' => 'Plan cache cleared successfully',
        ]);
    }
}
