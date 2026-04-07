<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Investment;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Investment\InvestmentAccount;
use App\Services\Investment\Rebalancing\RebalancingStrategyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rebalancing strategies controller
 * Handles evaluation of different rebalancing strategies and frequency recommendations
 */
class RebalancingStrategiesController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private RebalancingStrategyService $strategyService
    ) {}

    /**
     * Evaluate rebalancing strategies
     *
     * POST /api/investment/rebalancing/evaluate-strategies
     */
    public function evaluateStrategies(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_allocation' => 'required|array|min:2',
            'target_allocation.equities' => 'required|numeric|min:0|max:100',
            'target_allocation.bonds' => 'required|numeric|min:0|max:100',
            'target_allocation.cash' => 'required|numeric|min:0|max:100',
            'target_allocation.alternatives' => 'required|numeric|min:0|max:100',
            'threshold_percent' => 'nullable|numeric|min:1|max:50',
            'tolerance_band_percent' => 'nullable|numeric|min:1|max:50',
            'last_rebalance_date' => 'nullable|date',
            'frequency' => 'nullable|in:quarterly,semi_annual,annual,biennial',
            'account_ids' => 'nullable|array',
        ]);
        $user = $request->user();

        try {
            // Get current allocation
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

            if ($totalValue <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Portfolio has no value',
                ], 400);
            }

            $currentAllocation = [];

            foreach ($holdings as $holding) {
                $assetClass = $this->normalizeAssetClass($holding->asset_type);
                if (! isset($currentAllocation[$assetClass])) {
                    $currentAllocation[$assetClass] = 0.0;
                }
                $currentAllocation[$assetClass] += ($holding->current_value / $totalValue) * 100;
            }

            // Evaluate strategies
            $options = [
                'threshold_percent' => $validated['threshold_percent'] ?? 5.0,
                'tolerance_band_percent' => $validated['tolerance_band_percent'] ?? 5.0,
                'last_rebalance_date' => $validated['last_rebalance_date'] ?? date('Y-m-d', strtotime('-6 months')),
                'frequency' => $validated['frequency'] ?? 'annual',
            ];

            $result = $this->strategyService->compareStrategies(
                $currentAllocation,
                $validated['target_allocation'],
                $options
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Evaluating rebalancing strategies', 500, ['user_id' => $user->id]);
        }
    }

    /**
     * Recommend optimal rebalancing frequency
     *
     * POST /api/investment/rebalancing/recommend-frequency
     */
    public function recommendFrequency(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'portfolio_value' => 'required|numeric|min:0',
            'risk_level' => 'required|integer|min:1|max:5',
            'expected_volatility' => 'required|numeric|min:0|max:100',
            'is_taxable' => 'nullable|boolean',
        ]);

        try {
            $result = $this->strategyService->recommendRebalancingFrequency(
                $validated['portfolio_value'],
                $validated['risk_level'],
                $validated['expected_volatility'],
                $validated['is_taxable'] ?? true
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Recommending rebalancing frequency');
        }
    }

    /**
     * Evaluate threshold-based rebalancing strategy
     *
     * POST /api/investment/rebalancing/threshold-strategy
     */
    public function evaluateThresholdStrategy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_allocation' => 'required|array|min:2',
            'threshold_percent' => 'required|numeric|min:1|max:50',
            'account_ids' => 'nullable|array',
        ]);
        $user = $request->user();

        try {
            // Get current allocation
            $query = InvestmentAccount::where('user_id', $user->id)->with('holdings');

            if (isset($validated['account_ids'])) {
                $query->whereIn('id', $validated['account_ids']);
            }

            $holdings = $query->get()->flatMap->holdings;

            if ($holdings->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No holdings found',
                ], 404);
            }

            $totalValue = $holdings->sum('current_value');

            if ($totalValue <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Portfolio has no value',
                ], 400);
            }

            $currentAllocation = [];

            foreach ($holdings as $holding) {
                $assetClass = $this->normalizeAssetClass($holding->asset_type);
                if (! isset($currentAllocation[$assetClass])) {
                    $currentAllocation[$assetClass] = 0.0;
                }
                $currentAllocation[$assetClass] += ($holding->current_value / $totalValue) * 100;
            }

            $result = $this->strategyService->evaluateThresholdStrategy(
                $currentAllocation,
                $validated['target_allocation'],
                $validated['threshold_percent']
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Evaluating threshold strategy', 500, ['user_id' => $user->id]);
        }
    }

    /**
     * Evaluate calendar-based rebalancing strategy
     *
     * POST /api/investment/rebalancing/calendar-strategy
     */
    public function evaluateCalendarStrategy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'last_rebalance_date' => 'required|date',
            'frequency' => 'required|in:quarterly,semi_annual,annual,biennial',
        ]);

        try {
            $result = $this->strategyService->evaluateCalendarStrategy(
                $validated['last_rebalance_date'],
                $validated['frequency']
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Evaluating calendar strategy');
        }
    }

    /**
     * Evaluate opportunistic rebalancing with cash flow
     *
     * POST /api/investment/rebalancing/opportunistic-strategy
     */
    public function evaluateOpportunisticStrategy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_allocation' => 'required|array|min:2',
            'new_cash_flow' => 'required|numeric',
            'account_ids' => 'nullable|array',
        ]);
        $user = $request->user();

        try {
            // Get current allocation
            $query = InvestmentAccount::where('user_id', $user->id)->with('holdings');

            if (isset($validated['account_ids'])) {
                $query->whereIn('id', $validated['account_ids']);
            }

            $holdings = $query->get()->flatMap->holdings;

            if ($holdings->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No holdings found',
                ], 404);
            }

            $totalValue = $holdings->sum('current_value');

            if ($totalValue <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Portfolio has no value',
                ], 400);
            }

            $currentAllocation = [];

            foreach ($holdings as $holding) {
                $assetClass = $this->normalizeAssetClass($holding->asset_type);
                if (! isset($currentAllocation[$assetClass])) {
                    $currentAllocation[$assetClass] = 0.0;
                }
                $currentAllocation[$assetClass] += ($holding->current_value / $totalValue) * 100;
            }

            $result = $this->strategyService->evaluateOpportunisticStrategy(
                $currentAllocation,
                $validated['target_allocation'],
                $validated['new_cash_flow'],
                $totalValue
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Evaluating opportunistic strategy', 500, ['user_id' => $user->id]);
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
