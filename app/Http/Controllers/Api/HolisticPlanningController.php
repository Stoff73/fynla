<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Agents\CoordinatingAgent;
use App\Constants\TaxDefaults;
use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\RecommendationTracking;
use App\Services\Cache\CacheInvalidationService;
use App\Services\Coordination\CashFlowCoordinator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class HolisticPlanningController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly CoordinatingAgent $coordinatingAgent,
        private readonly CashFlowCoordinator $cashFlowCoordinator,
        private readonly CacheInvalidationService $cacheInvalidation
    ) {}

    /**
     * Perform holistic analysis across all modules
     *
     * POST /api/holistic/analyze
     */
    public function analyze(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Cache key based on user ID
        $cacheKey = "holistic_analysis_{$userId}";

        // Cache for 1 hour (standard TTL)
        $analysis = Cache::remember($cacheKey, TaxDefaults::CACHE_TTL_STANDARD, function () use ($userId) {
            return $this->coordinatingAgent->orchestrateAnalysis($userId);
        });

        return response()->json([
            'success' => true,
            'data' => $analysis,
        ]);
    }

    /**
     * Generate complete holistic plan
     *
     * POST /api/holistic/plan
     */
    public function plan(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Cache key
        $cacheKey = "holistic_plan_{$userId}";

        // Cache for 24 hours (simulation TTL)
        // Only store recommendations when plan is freshly generated (not from cache)
        $freshlyGenerated = false;
        $plan = Cache::remember($cacheKey, TaxDefaults::CACHE_TTL_SIMULATION, function () use ($userId, &$freshlyGenerated) {
            $freshlyGenerated = true;

            return $this->coordinatingAgent->generateHolisticPlan($userId);
        });

        if ($freshlyGenerated) {
            $this->storeRecommendations($userId, $plan['ranked_recommendations'] ?? []);
        }

        return response()->json([
            'success' => true,
            'data' => $plan,
        ]);
    }

    /**
     * Get all ranked recommendations
     *
     * GET /api/holistic/recommendations
     */
    public function recommendations(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Get from tracking table
        $recommendations = RecommendationTracking::where('user_id', $userId)
            ->active()
            ->orderBy('priority_score', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $recommendations,
        ]);
    }

    /**
     * Get cashflow analysis
     *
     * GET /api/holistic/cash-flow-analysis
     */
    public function cashFlowAnalysis(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Get available surplus
        $availableSurplus = $this->cashFlowCoordinator->calculateAvailableSurplus($userId);

        // Get demands from recommendations
        $recommendations = RecommendationTracking::where('user_id', $userId)
            ->active()
            ->get();

        $demands = $this->extractDemandsFromTracking($recommendations);

        // Optimize allocation
        $allocation = $this->cashFlowCoordinator->optimizeContributionAllocation($availableSurplus, $demands);

        // Identify shortfalls
        $shortfallAnalysis = $this->cashFlowCoordinator->identifyCashFlowShortfalls($allocation);

        // Get chart data
        $chartData = $this->cashFlowCoordinator->createCashFlowChartData($userId, $allocation);

        // Get sustainable contribution analysis from real user data
        $financials = $this->cashFlowCoordinator->getMonthlyFinancials($userId);
        $sustainableAnalysis = $this->cashFlowCoordinator->calculateSustainableContributions(
            $financials['monthly_income'],
            $financials['monthly_expenses']
        );

        return response()->json([
            'success' => true,
            'data' => [
                'available_surplus' => $availableSurplus,
                'allocation' => $allocation,
                'shortfall_analysis' => $shortfallAnalysis,
                'chart_data' => $chartData,
                'sustainable_analysis' => $sustainableAnalysis,
            ],
        ]);
    }

    /**
     * Mark recommendation as done
     *
     * POST /api/holistic/recommendations/{id}/mark-done
     */
    public function markRecommendationDone(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        $recommendation = RecommendationTracking::where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $recommendation->markAsCompleted();

        // Invalidate holistic plan cache
        $this->cacheInvalidation->invalidateForUser($userId);

        return response()->json([
            'success' => true,
            'message' => 'Recommendation marked as completed',
            'data' => $recommendation->fresh(),
        ]);
    }

    /**
     * Mark recommendation as in progress
     *
     * POST /api/holistic/recommendations/{id}/in-progress
     */
    public function markRecommendationInProgress(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        $recommendation = RecommendationTracking::where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $recommendation->markAsInProgress();

        return response()->json([
            'success' => true,
            'message' => 'Recommendation marked as in progress',
            'data' => $recommendation->fresh(),
        ]);
    }

    /**
     * Dismiss recommendation
     *
     * POST /api/holistic/recommendations/{id}/dismiss
     */
    public function dismissRecommendation(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        $recommendation = RecommendationTracking::where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $recommendation->dismiss();

        // Invalidate caches
        $this->cacheInvalidation->invalidateForUser($userId);

        return response()->json([
            'success' => true,
            'message' => 'Recommendation dismissed',
            'data' => $recommendation->fresh(),
        ]);
    }

    /**
     * Get completed recommendations
     *
     * GET /api/holistic/recommendations/completed
     */
    public function completedRecommendations(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $recommendations = RecommendationTracking::where('user_id', $userId)
            ->completed()
            ->orderBy('completed_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $recommendations,
        ]);
    }

    /**
     * Update recommendation notes
     *
     * PATCH /api/holistic/recommendations/{id}/notes
     */
    public function updateRecommendationNotes(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'notes' => 'required|string|max:1000',
        ]);

        $userId = $request->user()->id;

        $recommendation = RecommendationTracking::where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $recommendation->update([
            'notes' => $validated['notes'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notes updated successfully',
            'data' => $recommendation->fresh(),
        ]);
    }

    /**
     * Store recommendations in tracking table
     */
    private function storeRecommendations(int $userId, array $recommendations): void
    {
        // Clear existing pending recommendations for this user
        RecommendationTracking::where('user_id', $userId)
            ->where('status', 'pending')
            ->delete();

        // Store new recommendations
        foreach ($recommendations as $rec) {
            RecommendationTracking::create([
                'user_id' => $userId,
                'recommendation_id' => Str::uuid()->toString(),
                'module' => $rec['module'] ?? 'unknown',
                'recommendation_text' => $rec['title'] ?? $rec['description'] ?? $rec['text'] ?? $rec['recommendation_text'] ?? 'No description',
                'priority_score' => $rec['priority_score'] ?? 50,
                'recommended_amount' => $rec['recommended_monthly_contribution'] ?? $rec['recommended_monthly_premium'] ?? 0,
                'timeline' => $rec['timeline'] ?? 'medium_term',
                'status' => 'pending',
            ]);
        }
    }

    /**
     * Extract demands from recommendation tracking records
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $recommendations
     */
    private function extractDemandsFromTracking($recommendations): array
    {
        $demands = [];

        foreach ($recommendations as $rec) {
            $module = $rec->module;
            $category = $this->mapModuleToCategory($module);
            $amount = (float) ($rec->recommended_amount ?? 0);

            if ($amount <= 0) {
                continue;
            }

            if (! isset($demands[$category])) {
                $demands[$category] = [
                    'amount' => 0,
                    'urgency' => 50,
                ];
            }

            $demands[$category]['amount'] += $amount;
            $demands[$category]['urgency'] = max($demands[$category]['urgency'], $rec->priority_score ?? 50);
        }

        return $demands;
    }

    /**
     * Map module to cashflow category
     */
    private function mapModuleToCategory(string $module): string
    {
        return match ($module) {
            'protection' => 'protection',
            'savings' => 'emergency_fund',
            'investment' => 'investment',
            'retirement' => 'pension',
            'estate' => 'estate',
            'goals' => 'goals',
            default => $module,
        };
    }
}
