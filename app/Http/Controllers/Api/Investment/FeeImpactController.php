<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Investment;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Investment\Holding;
use App\Services\Investment\FeeAnalyzer;
use App\Services\Investment\Fees\OCFImpactCalculator;
use App\Services\Investment\Fees\PlatformComparator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Fee Impact Controller
 * Manages API endpoints for investment fee analysis and optimization
 */
class FeeImpactController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private FeeAnalyzer $feeAnalyzer,
        private OCFImpactCalculator $ocfCalculator,
        private PlatformComparator $platformComparator
    ) {}

    /**
     * Analyze portfolio fees
     *
     * GET /api/investment/fees/analyze
     */
    public function analyzePortfolioFees(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $cacheKey = "fee_analysis_{$user->id}";

            $result = Cache::remember($cacheKey, 86400, function () use ($user) {
                return $this->feeAnalyzer->analyzePortfolioFees($user->id);
            });

            if (! $result['success']) {
                return response()->json($result, 404);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Portfolio fee analysis');
        }
    }

    /**
     * Analyze fees by holding
     *
     * GET /api/investment/fees/holdings
     */
    public function analyzeHoldingFees(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $result = $this->feeAnalyzer->analyzeHoldingFees($user->id);

            if (! $result['success']) {
                return response()->json($result, 404);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Holding fee analysis');
        }
    }

    /**
     * Calculate OCF impact over time
     *
     * POST /api/investment/fees/ocf-impact
     */
    public function calculateOCFImpact(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'years' => 'nullable|integer|min:1|max:50',
            'expected_return' => 'nullable|numeric|min:0|max:0.5',
        ]);

        try {
            // Get all user holdings
            $holdings = Holding::whereHas('investmentAccount', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->get();

            $result = $this->ocfCalculator->calculateOCFImpact(
                $holdings,
                $validated['years'] ?? 20,
                $validated['expected_return'] ?? 0.06
            );

            if (! $result['success']) {
                return response()->json($result, 404);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'OCF impact calculation');
        }
    }

    /**
     * Compare active vs passive funds
     *
     * GET /api/investment/fees/active-vs-passive
     */
    public function compareActiveVsPassive(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $holdings = Holding::whereHas('investmentAccount', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->get();

            $result = $this->ocfCalculator->compareActiveVsPassive($holdings);

            if (! $result['success']) {
                return response()->json($result, 404);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Active vs passive comparison');
        }
    }

    /**
     * Find low-cost alternatives for a holding
     *
     * GET /api/investment/fees/alternatives/{holdingId}
     */
    public function findAlternatives(Request $request, int $holdingId): JsonResponse
    {
        $user = $request->user();

        try {
            // SECURITY: Fetch with ownership check to prevent information disclosure
            $holding = Holding::whereHas('investmentAccount', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->where('id', $holdingId)->firstOrFail();

            $result = $this->ocfCalculator->findLowCostAlternatives($holding);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Alternative fund search');
        }
    }

    /**
     * Compare investment platforms
     *
     * GET /api/investment/fees/compare-platforms
     */
    public function comparePlatforms(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'portfolio_value' => 'required|numeric|min:0',
            'account_type' => 'nullable|in:isa,sipp,gia,jisa,lifetime_isa',
            'trades_per_year' => 'nullable|integer|min:0|max:1000',
        ]);

        try {
            $result = $this->platformComparator->comparePlatforms(
                $validated['portfolio_value'],
                $validated['account_type'] ?? 'isa',
                $validated['trades_per_year'] ?? 4
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Platform comparison');
        }
    }

    /**
     * Compare specific platforms
     *
     * POST /api/investment/fees/compare-specific
     */
    public function compareSpecificPlatforms(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'platforms' => 'required|array|min:2',
            'platforms.*' => 'required|string',
            'portfolio_value' => 'required|numeric|min:0',
            'account_type' => 'nullable|in:isa,sipp,gia,jisa,lifetime_isa',
            'trades_per_year' => 'nullable|integer|min:0|max:1000',
        ]);

        try {
            $result = $this->platformComparator->compareSpecificPlatforms(
                $validated['platforms'],
                $validated['portfolio_value'],
                $validated['account_type'] ?? 'isa',
                $validated['trades_per_year'] ?? 4
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Specific platform comparison');
        }
    }

    /**
     * Clear fee analysis cache
     *
     * DELETE /api/investment/fees/clear-cache
     */
    public function clearCache(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $cacheKeys = [
                "fee_analysis_{$user->id}",
            ];

            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }

            return response()->json([
                'success' => true,
                'message' => 'Fee analysis cache cleared',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fee cache clearing');
        }
    }

    /**
     * Clear user's fee cache (static method for use by other controllers)
     *
     * @param  int  $userId  User ID
     */
    public static function clearUserFeeCache(int $userId): void
    {
        Cache::forget("fee_analysis_{$userId}");
    }
}
