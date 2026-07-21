<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Investment;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Investment\InvestmentAccount;
use App\Services\Investment\Rebalancing\DriftAnalyzer;
use App\Services\Investment\Rebalancing\RebalancingCalculator;
use App\Services\Investment\Rebalancing\TaxAwareRebalancer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Portfolio-level rebalancing calculation controller
 *
 * Handles cross-portfolio rebalancing actions, CGT comparison/optimisation, and
 * drift analysis. Account-level routes (single-account rebalancing GET +
 * threshold PATCH) live in AccountRebalancingController.
 */
class RebalancingCalculationController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly RebalancingCalculator $rebalancingCalculator,
        private readonly TaxAwareRebalancer $taxAwareRebalancer,
        private readonly DriftAnalyzer $driftAnalyzer
    ) {}

    /**
     * Calculate rebalancing actions from target weights
     *
     * POST /api/investment/rebalancing/calculate
     */
    public function calculateRebalancing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_weights' => 'required|array|min:2',
            'target_weights.*' => 'required|numeric|min:0|max:1',
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'integer|exists:investment_accounts,id',
            'min_trade_size' => 'nullable|numeric|min:0',
            'optimize_for_cgt' => 'nullable|boolean',
            'cgt_allowance' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:1',
            'loss_carryforward' => 'nullable|numeric|min:0',
        ]);
        $user = $request->user();

        try {
            // Get user's holdings
            $query = InvestmentAccount::where('user_id', $user->id)->with('holdings');

            if (isset($validated['account_ids'])) {
                $query->whereIn('id', $validated['account_ids']);
            }

            $accounts = $query->get();

            if ($accounts->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No investment accounts found',
                ], 404);
            }

            $holdings = $accounts->flatMap->holdings;

            if ($holdings->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No holdings found',
                ], 404);
            }

            // Validate target weights count matches holdings count
            if (count($validated['target_weights']) !== $holdings->count()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Number of target weights must match number of holdings',
                ], 422);
            }

            // Validate weights sum to 1.0
            $weightSum = array_sum($validated['target_weights']);
            if (abs($weightSum - 1.0) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => 'Target weights must sum to 1.0 (100%)',
                ], 422);
            }

            // Calculate total cash
            $accountCash = $accounts->sum('cash_balance');

            // Calculate rebalancing
            $options = [
                'min_trade_size' => $validated['min_trade_size'] ?? 100,
                'account_cash' => $accountCash,
            ];

            $result = $this->rebalancingCalculator->calculateRebalancing(
                $holdings,
                $validated['target_weights'],
                $options
            );

            if (! $result['success']) {
                return response()->json($result, 400);
            }

            // Apply CGT optimization if requested
            if ($validated['optimize_for_cgt'] ?? false) {
                $cgtOptions = [
                    'cgt_allowance' => $validated['cgt_allowance'] ?? null,
                    'tax_rate' => $validated['tax_rate'] ?? null,
                    'loss_carryforward' => $validated['loss_carryforward'] ?? 0,
                ];

                $cgtResult = $this->taxAwareRebalancer->optimizeForCGT(
                    $result['actions'],
                    $holdings,
                    $cgtOptions
                );

                // Merge CGT analysis into result
                $result['actions'] = $cgtResult['optimized_actions'];
                $result['cgt_analysis'] = $cgtResult['cgt_analysis'];
                $result['tax_loss_opportunities'] = $cgtResult['tax_loss_opportunities'];
                $result['cgt_summary'] = $cgtResult['summary'];
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Rebalancing calculation');
        }
    }

    /**
     * Calculate rebalancing from optimization result
     *
     * POST /api/investment/rebalancing/from-optimization
     */
    public function calculateFromOptimization(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'weights' => 'required|array|min:2',
            'weights.*' => 'required|numeric|min:0|max:1',
            'labels' => 'nullable|array',
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'integer|exists:investment_accounts,id',
            'min_trade_size' => 'nullable|numeric|min:0',
            'optimize_for_cgt' => 'nullable|boolean',
            'cgt_allowance' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:1',
        ]);
        $user = $request->user();

        // Forward to calculateRebalancing with target_weights
        $request->merge(['target_weights' => $validated['weights']]);

        return $this->calculateRebalancing($request);
    }

    /**
     * Compare CGT between different rebalancing strategies
     *
     * POST /api/investment/rebalancing/compare-cgt
     */
    public function compareCGTStrategies(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'strategy_1_weights' => 'required|array|min:2',
            'strategy_1_weights.*' => 'required|numeric|min:0|max:1',
            'strategy_2_weights' => 'required|array|min:2',
            'strategy_2_weights.*' => 'required|numeric|min:0|max:1',
            'account_ids' => 'nullable|array',
            'cgt_allowance' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:1',
        ]);
        $user = $request->user();

        try {
            // Get holdings
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

            // Calculate actions for both strategies
            $options = ['min_trade_size' => 100];

            $strategy1Actions = $this->rebalancingCalculator->calculateRebalancing(
                $holdings,
                $validated['strategy_1_weights'],
                $options
            )['actions'];

            $strategy2Actions = $this->rebalancingCalculator->calculateRebalancing(
                $holdings,
                $validated['strategy_2_weights'],
                $options
            )['actions'];

            // Compare CGT
            $cgtOptions = [
                'cgt_allowance' => $validated['cgt_allowance'] ?? null,
                'tax_rate' => $validated['tax_rate'] ?? null,
            ];

            $comparison = $this->taxAwareRebalancer->compareStrategies(
                $holdings,
                $strategy1Actions,
                $strategy2Actions,
                $cgtOptions
            );

            return response()->json([
                'success' => true,
                'data' => $comparison,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'CGT strategy comparison');
        }
    }

    /**
     * Calculate rebalancing within CGT allowance
     *
     * POST /api/investment/rebalancing/within-cgt-allowance
     */
    public function rebalanceWithinCGTAllowance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_weights' => 'required|array|min:2',
            'target_weights.*' => 'required|numeric|min:0|max:1',
            'account_ids' => 'nullable|array',
            'cgt_allowance' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:1',
        ]);
        $user = $request->user();

        try {
            // Get holdings
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

            // Calculate initial actions
            $options = ['min_trade_size' => 100];

            $actions = $this->rebalancingCalculator->calculateRebalancing(
                $holdings,
                $validated['target_weights'],
                $options
            )['actions'];

            // Constrain to CGT allowance
            $cgtOptions = [
                'cgt_allowance' => $validated['cgt_allowance'] ?? null,
                'tax_rate' => $validated['tax_rate'] ?? null,
            ];

            $result = $this->taxAwareRebalancer->rebalanceWithinCGTAllowance(
                $actions,
                $holdings,
                $cgtOptions
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'CGT-constrained rebalancing');
        }
    }

    /**
     * Analyze portfolio drift from target allocation
     *
     * POST /api/investment/rebalancing/analyze-drift
     */
    public function analyzeDrift(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_allocation' => 'required|array|min:2',
            'target_allocation.equities' => 'required|numeric|min:0|max:100',
            'target_allocation.bonds' => 'required|numeric|min:0|max:100',
            'target_allocation.cash' => 'required|numeric|min:0|max:100',
            'target_allocation.alternatives' => 'required|numeric|min:0|max:100',
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'integer|exists:investment_accounts,id',
        ]);
        $user = $request->user();

        try {
            // Get holdings
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

            $result = $this->driftAnalyzer->analyzeDrift($holdings, $validated['target_allocation']);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Drift analysis');
        }
    }
}
