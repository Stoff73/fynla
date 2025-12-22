<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Investment;

use App\Http\Controllers\Controller;
use App\Models\Investment\InvestmentAccount;
use App\Services\Investment\Analytics\EfficientFrontierCalculator;
use App\Services\Investment\Analytics\PortfolioStatisticsCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Efficient Frontier Controller
 * Manages API endpoints for Modern Portfolio Theory (MPT) analysis
 */
class EfficientFrontierController extends Controller
{
    public function __construct(
        private EfficientFrontierCalculator $frontierCalculator,
        private PortfolioStatisticsCalculator $statsCalculator
    ) {}

    /**
     * Calculate efficient frontier
     *
     * POST /api/investment/efficient-frontier/calculate
     */
    public function calculateEfficientFrontier(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'asset_classes' => 'required|array|min:2',
            'asset_classes.*.expected_return' => 'required|numeric|min:-1|max:2',
            'asset_classes.*.volatility' => 'required|numeric|min:0|max:1',
            'asset_classes.*.correlations' => 'nullable|array',
            'num_portfolios' => 'nullable|integer|min:50|max:1000',
            'risk_free_rate' => 'nullable|numeric|min:0|max:0.2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $result = $this->frontierCalculator->calculateEfficientFrontier(
                $validated['asset_classes'],
                $validated['num_portfolios'] ?? 200,
                $validated['risk_free_rate'] ?? 0.04
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Efficient frontier calculation failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate efficient frontier',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate efficient frontier with default UK assumptions
     *
     * GET /api/investment/efficient-frontier/default
     */
    public function calculateWithDefaults(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'num_portfolios' => 'nullable|integer|min:50|max:1000',
            'risk_free_rate' => 'nullable|numeric|min:0|max:0.2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $assetClasses = $this->statsCalculator->getDefaultAssetClassAssumptions();

            $result = $this->frontierCalculator->calculateEfficientFrontier(
                $assetClasses,
                $validated['num_portfolios'] ?? 200,
                $validated['risk_free_rate'] ?? 0.04
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'note' => 'Using default UK market assumptions',
            ]);
        } catch (\Exception $e) {
            Log::error('Default efficient frontier calculation failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate efficient frontier',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Find optimal portfolio for target return
     *
     * POST /api/investment/efficient-frontier/optimal-by-return
     */
    public function findOptimalByReturn(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'target_return' => 'required|numeric|min:0|max:0.5',
            'asset_classes' => 'nullable|array|min:2',
            'risk_free_rate' => 'nullable|numeric|min:0|max:0.2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $assetClasses = $validated['asset_classes'] ?? $this->statsCalculator->getDefaultAssetClassAssumptions();

            $result = $this->frontierCalculator->calculateOptimalPortfolio(
                $assetClasses,
                $validated['target_return'],
                $validated['risk_free_rate'] ?? 0.04
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Optimal portfolio by return calculation failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to find optimal portfolio',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Find optimal portfolio for target risk level
     *
     * POST /api/investment/efficient-frontier/optimal-by-risk
     */
    public function findOptimalByRisk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'target_volatility' => 'required|numeric|min:0|max:1',
            'asset_classes' => 'nullable|array|min:2',
            'risk_free_rate' => 'nullable|numeric|min:0|max:0.2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $assetClasses = $validated['asset_classes'] ?? $this->statsCalculator->getDefaultAssetClassAssumptions();

            $result = $this->frontierCalculator->calculateOptimalPortfolioByRisk(
                $assetClasses,
                $validated['target_volatility'],
                $validated['risk_free_rate'] ?? 0.04
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Optimal portfolio by risk calculation failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to find optimal portfolio',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Compare current portfolio with efficient frontier
     *
     * POST /api/investment/efficient-frontier/compare
     */
    public function compareWithFrontier(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'allocation' => 'required|array|min:2',
            'allocation.equities' => 'required|numeric|min:0|max:1',
            'allocation.bonds' => 'required|numeric|min:0|max:1',
            'allocation.cash' => 'required|numeric|min:0|max:1',
            'allocation.alternatives' => 'required|numeric|min:0|max:1',
            'asset_classes' => 'nullable|array|min:2',
            'risk_free_rate' => 'nullable|numeric|min:0|max:0.2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Validate allocation sums to 1.0
        $allocationSum = array_sum($validated['allocation']);
        if (abs($allocationSum - 1.0) > 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Allocation must sum to 1.0 (100%)',
            ], 422);
        }

        try {
            $assetClasses = $validated['asset_classes'] ?? $this->statsCalculator->getDefaultAssetClassAssumptions();

            $result = $this->frontierCalculator->compareWithEfficientFrontier(
                $validated['allocation'],
                $assetClasses,
                $validated['risk_free_rate'] ?? 0.04
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Efficient frontier comparison failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to compare with efficient frontier',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate comprehensive portfolio statistics
     *
     * POST /api/investment/efficient-frontier/statistics
     */
    public function calculateStatistics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'allocation' => 'required|array|min:2',
            'allocation.equities' => 'required|numeric|min:0|max:1',
            'allocation.bonds' => 'required|numeric|min:0|max:1',
            'allocation.cash' => 'required|numeric|min:0|max:1',
            'allocation.alternatives' => 'required|numeric|min:0|max:1',
            'asset_classes' => 'nullable|array|min:2',
            'risk_free_rate' => 'nullable|numeric|min:0|max:0.2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Validate allocation sums to 1.0
        $allocationSum = array_sum($validated['allocation']);
        if (abs($allocationSum - 1.0) > 0.01) {
            return response()->json([
                'success' => false,
                'message' => 'Allocation must sum to 1.0 (100%)',
            ], 422);
        }

        try {
            $assetClasses = $validated['asset_classes'] ?? $this->statsCalculator->getDefaultAssetClassAssumptions();

            $statistics = $this->statsCalculator->calculateStatistics(
                $validated['allocation'],
                $assetClasses,
                $validated['risk_free_rate'] ?? 0.04
            );

            $interpretation = $this->statsCalculator->interpretStatistics($statistics);

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $statistics,
                    'interpretation' => $interpretation,
                    'allocation' => $validated['allocation'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Portfolio statistics calculation failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate portfolio statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get default asset class assumptions
     *
     * GET /api/investment/efficient-frontier/default-assumptions
     */
    public function getDefaultAssumptions(Request $request): JsonResponse
    {
        try {
            $assumptions = $this->statsCalculator->getDefaultAssetClassAssumptions();

            return response()->json([
                'success' => true,
                'data' => $assumptions,
                'note' => 'Default UK market assumptions based on historical data',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get default assumptions', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get default assumptions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Analyze user's current portfolio efficiency
     *
     * GET /api/investment/efficient-frontier/analyze-current
     */
    public function analyzeCurrentPortfolio(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'integer|exists:investment_accounts,id',
            'risk_free_rate' => 'nullable|numeric|min:0|max:0.2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            // Get user's holdings
            $query = InvestmentAccount::where('user_id', $user->id)->with('holdings');

            if (isset($validated['account_ids'])) {
                $query->whereIn('id', $validated['account_ids']);
            }

            $accounts = $query->get();
            $holdings = $accounts->flatMap->holdings;

            if ($holdings->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No holdings found',
                ], 404);
            }

            // Calculate current allocation
            $totalValue = $holdings->sum('current_value');
            $allocation = [
                'equities' => 0.0,
                'bonds' => 0.0,
                'cash' => 0.0,
                'alternatives' => 0.0,
            ];

            foreach ($holdings as $holding) {
                $assetClass = $this->normalizeAssetClass($holding->asset_type);
                $weight = $holding->current_value / $totalValue;
                $allocation[$assetClass] = ($allocation[$assetClass] ?? 0.0) + $weight;
            }

            // Get default asset class assumptions
            $assetClasses = $this->statsCalculator->getDefaultAssetClassAssumptions();

            // Compare with efficient frontier
            $comparison = $this->frontierCalculator->compareWithEfficientFrontier(
                $allocation,
                $assetClasses,
                $validated['risk_free_rate'] ?? 0.04
            );

            // Calculate statistics
            $statistics = $this->statsCalculator->calculateStatistics(
                $allocation,
                $assetClasses,
                $validated['risk_free_rate'] ?? 0.04
            );

            $interpretation = $this->statsCalculator->interpretStatistics($statistics);

            return response()->json([
                'success' => true,
                'data' => [
                    'current_allocation' => $allocation,
                    'portfolio_value' => $totalValue,
                    'statistics' => $statistics,
                    'interpretation' => $interpretation,
                    'efficient_frontier_comparison' => $comparison['data'] ?? null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Current portfolio analysis failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze current portfolio',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Normalize asset class names
     */
    private function normalizeAssetClass(string $assetType): string
    {
        return match (strtolower($assetType)) {
            'uk_equity', 'global_equity', 'emerging_markets', 'equity', 'stock' => 'equities',
            'uk_bonds', 'global_bonds', 'government_bonds', 'corporate_bonds', 'bond' => 'bonds',
            'cash', 'money_market' => 'cash',
            'property', 'real_estate', 'commodities', 'alternative' => 'alternatives',
            default => $assetType,
        };
    }
}
