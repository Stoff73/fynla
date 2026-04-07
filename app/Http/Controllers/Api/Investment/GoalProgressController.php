<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Investment;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Investment\InvestmentGoal;
use App\Services\Investment\Goals\GoalProbabilityCalculator;
use App\Services\Investment\Goals\GoalProgressAnalyzer;
use App\Services\Investment\Goals\ShortfallAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Goal Progress Controller
 * Manages API endpoints for investment goal progress tracking and analysis
 */
class GoalProgressController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private GoalProgressAnalyzer $progressAnalyzer,
        private ShortfallAnalyzer $shortfallAnalyzer,
        private GoalProbabilityCalculator $probabilityCalculator
    ) {}

    /**
     * Analyze progress for a specific goal
     *
     * GET /api/investment/goals/{goalId}/progress
     */
    public function analyzeGoalProgress(Request $request, int $goalId): JsonResponse
    {
        $user = $request->user();

        try {
            // SECURITY: Fetch with ownership check to prevent information disclosure
            $goal = InvestmentGoal::where('id', $goalId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $cacheKey = "goal_progress_{$goalId}";

            $result = Cache::remember($cacheKey, 86400, function () use ($goal) {
                return $this->progressAnalyzer->analyzeGoalProgress($goal);
            });

            if (! $result['success']) {
                return response()->json($result, 422);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Goal progress analysis');
        }
    }

    /**
     * Analyze progress for all user goals
     *
     * GET /api/investment/goals/progress/all
     */
    public function analyzeAllGoals(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $cacheKey = "all_goals_progress_{$user->id}";

            $result = Cache::remember($cacheKey, 86400, function () use ($user) {
                return $this->progressAnalyzer->analyzeAllGoals($user->id);
            });

            if (! $result['success']) {
                return response()->json($result, 404);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'All goals progress analysis');
        }
    }

    /**
     * Analyze goal shortfall and get mitigation strategies
     *
     * GET /api/investment/goals/{goalId}/shortfall
     */
    public function analyzeShortfall(Request $request, int $goalId): JsonResponse
    {
        $user = $request->user();

        try {
            // SECURITY: Fetch with ownership check to prevent information disclosure
            $goal = InvestmentGoal::where('id', $goalId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            // Get current value
            $currentValue = $goal->current_value ?? 0;

            $result = $this->shortfallAnalyzer->analyzeShortfall($goal, $currentValue);

            if (! $result['success']) {
                return response()->json($result, 422);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Shortfall analysis');
        }
    }

    /**
     * Generate what-if scenarios for a goal
     *
     * POST /api/investment/goals/{goalId}/what-if
     */
    public function generateWhatIfScenarios(Request $request, int $goalId): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'scenarios' => 'nullable|array',
            'scenarios.*.name' => 'required_with:scenarios|string',
            'scenarios.*.contribution' => 'required_with:scenarios|numeric|min:0',
            'scenarios.*.return' => 'required_with:scenarios|numeric|min:0|max:0.5',
        ]);

        try {
            // SECURITY: Fetch with ownership check to prevent information disclosure
            $goal = InvestmentGoal::where('id', $goalId)
                ->where('user_id', $user->id)
                ->firstOrFail();

            $currentValue = $goal->current_value ?? 0;
            $scenarios = $validated['scenarios'] ?? [];

            $result = $this->shortfallAnalyzer->generateWhatIfScenarios($goal, $currentValue, $scenarios);

            if (! $result['success']) {
                return response()->json($result, 422);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'What-if scenario generation');
        }
    }

    /**
     * Calculate goal success probability
     *
     * POST /api/investment/goals/calculate-probability
     */
    public function calculateProbability(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_value' => 'required|numeric|min:0',
            'target_value' => 'required|numeric|min:0',
            'monthly_contribution' => 'required|numeric|min:0',
            'expected_return' => 'required|numeric|min:0|max:0.5',
            'volatility' => 'nullable|numeric|min:0|max:1',
            'years_to_goal' => 'required|integer|min:1|max:50',
            'iterations' => 'nullable|integer|min:100|max:5000',
        ]);

        try {
            $result = $this->probabilityCalculator->calculateGoalProbability(
                $validated['current_value'],
                $validated['target_value'],
                $validated['monthly_contribution'],
                $validated['expected_return'],
                $validated['volatility'] ?? 0.15,
                $validated['years_to_goal'],
                $validated['iterations'] ?? 1000
            );

            if (! $result['success']) {
                return response()->json($result, 422);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Goal probability calculation');
        }
    }

    /**
     * Calculate required contribution for target probability
     *
     * POST /api/investment/goals/required-contribution
     */
    public function calculateRequiredContribution(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_value' => 'required|numeric|min:0',
            'target_value' => 'required|numeric|min:0',
            'current_contribution' => 'required|numeric|min:0',
            'expected_return' => 'required|numeric|min:0|max:0.5',
            'volatility' => 'nullable|numeric|min:0|max:1',
            'years_to_goal' => 'required|integer|min:1|max:50',
            'target_probability' => 'nullable|numeric|min:0.5|max:0.99',
        ]);

        try {
            $result = $this->probabilityCalculator->calculateRequiredContribution(
                $validated['current_value'],
                $validated['target_value'],
                $validated['current_contribution'],
                $validated['expected_return'],
                $validated['volatility'] ?? 0.15,
                $validated['years_to_goal'],
                $validated['target_probability'] ?? 0.85
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Required contribution calculation');
        }
    }

    /**
     * Get glide path recommendation
     *
     * GET /api/investment/goals/glide-path
     */
    public function getGlidePath(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'years_to_goal' => 'required|integer|min:0|max:50',
            'current_equity_percent' => 'required|numeric|min:0|max:100',
        ]);

        try {
            $result = $this->probabilityCalculator->calculateGlidePath(
                $validated['years_to_goal'],
                $validated['current_equity_percent']
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Glide path calculation');
        }
    }

    /**
     * Clear goal progress caches
     *
     * DELETE /api/investment/goals/clear-cache
     */
    public function clearCache(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            // Clear all goal-related caches for this user
            $goals = InvestmentGoal::where('user_id', $user->id)->pluck('id');

            $cacheKeys = ["all_goals_progress_{$user->id}"];

            foreach ($goals as $goalId) {
                $cacheKeys[] = "goal_progress_{$goalId}";
            }

            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }

            return response()->json([
                'success' => true,
                'message' => 'Goal progress caches cleared',
                'cleared_count' => count($cacheKeys),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Goal cache clearing');
        }
    }

    /**
     * Clear user's goal progress cache (static method for use by other controllers)
     *
     * @param  int  $userId  User ID
     */
    public static function clearUserGoalProgressCache(int $userId): void
    {
        $goals = InvestmentGoal::where('user_id', $userId)->pluck('id');

        $cacheKeys = ["all_goals_progress_{$userId}"];

        foreach ($goals as $goalId) {
            $cacheKeys[] = "goal_progress_{$goalId}";
        }

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
}
