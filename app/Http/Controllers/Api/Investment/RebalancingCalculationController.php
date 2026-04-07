<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Investment;

use App\Constants\InvestmentDefaults;
use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Services\Investment\Rebalancing\DriftAnalyzer;
use App\Services\Investment\Rebalancing\RebalancingCalculator;
use App\Services\Investment\Rebalancing\TaxAwareRebalancer;
use App\Services\TaxConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Portfolio rebalancing calculation controller
 * Handles rebalancing calculations, CGT optimization, and drift analysis
 */
class RebalancingCalculationController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly RebalancingCalculator $rebalancingCalculator,
        private readonly TaxAwareRebalancer $taxAwareRebalancer,
        private readonly DriftAnalyzer $driftAnalyzer,
        private readonly TaxConfigService $taxConfig
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
                $cgtAllowance = $this->taxConfig->getCapitalGainsTax()['annual_exempt_amount'] ?? 3000;
                $cgtOptions = [
                    'cgt_allowance' => $validated['cgt_allowance'] ?? $cgtAllowance,
                    'tax_rate' => $validated['tax_rate'] ?? 0.20,
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
            $cgtAllowance = $this->taxConfig->getCapitalGainsTax()['annual_exempt_amount'] ?? 3000;
            $cgtOptions = [
                'cgt_allowance' => $validated['cgt_allowance'] ?? $cgtAllowance,
                'tax_rate' => $validated['tax_rate'] ?? 0.20,
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
            $cgtAllowance = $this->taxConfig->getCapitalGainsTax()['annual_exempt_amount'] ?? 3000;
            $cgtOptions = [
                'cgt_allowance' => $validated['cgt_allowance'] ?? $cgtAllowance,
                'tax_rate' => $validated['tax_rate'] ?? 0.20,
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
     * Get rebalancing analysis for a specific account
     *
     * GET /api/investment/accounts/{id}/rebalancing
     */
    public function getAccountRebalancing(Request $request, int $accountId): JsonResponse
    {
        $user = $request->user();

        try {
            $account = InvestmentAccount::where('id', $accountId)
                ->where('user_id', $user->id)
                ->with('holdings')
                ->first();

            if (! $account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Investment account not found',
                ], 404);
            }

            $holdings = $account->holdings;

            // Determine if account is tax-free
            $accountType = strtolower($account->account_type ?? '');
            $isTaxFree = in_array($accountType, ['isa', 'sipp', 'pension', 'lisa']);

            // Get user's main risk profile from risk_profiles table
            $userRiskProfile = RiskProfile::where('user_id', $user->id)
                ->first();

            $userRiskLevel = $userRiskProfile
                ? $this->mapRiskStringToLevel($userRiskProfile->risk_level)
                : 3; // Default to Moderate
            $userRiskLabel = $this->getRiskLabel($userRiskLevel);

            // Check if account has custom risk preference
            $hasCustomRisk = (bool) $account->has_custom_risk;
            $accountRiskPreference = $account->risk_preference;

            // Determine effective risk level for this account
            if ($hasCustomRisk && $accountRiskPreference) {
                $effectiveRiskLevel = $this->mapRiskStringToLevel($accountRiskPreference);
                $effectiveRiskLabel = $this->getRiskLabel($effectiveRiskLevel);
            } else {
                $effectiveRiskLevel = $userRiskLevel;
                $effectiveRiskLabel = $userRiskLabel;
            }

            // Get target allocation for the effective risk level
            $targetAllocation = $this->getTargetAllocationForRiskLevel($effectiveRiskLevel);

            // Get threshold (default 10%)
            $thresholdPercent = (float) ($account->rebalance_threshold_percent ?? 10.0);

            // Build risk profile info
            $riskProfileInfo = [
                'user_risk_level' => $userRiskLevel,
                'user_risk_label' => $userRiskLabel,
                'has_custom_risk' => $hasCustomRisk,
                'account_risk_preference' => $accountRiskPreference,
                'effective_risk_level' => $effectiveRiskLevel,
                'effective_risk_label' => $effectiveRiskLabel,
            ];

            // If no holdings, return basic info
            if ($holdings->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'account_id' => $account->id,
                        'account_type' => $accountType,
                        'is_tax_free' => $isTaxFree,
                        'risk_profile' => $riskProfileInfo,
                        'threshold_percent' => $thresholdPercent,
                        'current_allocation' => ['equities' => 0, 'bonds' => 0, 'cash' => 0, 'alternatives' => 0],
                        'target_allocation' => $targetAllocation,
                        'drift_analysis' => [
                            'drift_score' => 0,
                            'max_drift' => 0,
                            'needs_rebalancing' => false,
                        ],
                        'rebalancing_actions' => [],
                        'cgt_analysis' => null,
                    ],
                ]);
            }

            // Analyze drift using existing service
            $driftResult = $this->driftAnalyzer->analyzeDrift($holdings, $targetAllocation);

            $driftScore = $driftResult['drift_score'] ?? 0;
            $maxDrift = $driftResult['drift_metrics']['max_drift'] ?? 0;
            $needsRebalancing = $driftScore >= $thresholdPercent;

            $response = [
                'account_id' => $account->id,
                'account_type' => $accountType,
                'is_tax_free' => $isTaxFree,
                'risk_profile' => $riskProfileInfo,
                'threshold_percent' => $thresholdPercent,
                'current_allocation' => $driftResult['current_allocation'] ?? [],
                'target_allocation' => $targetAllocation,
                'drift_analysis' => [
                    'drift_score' => round($driftScore, 2),
                    'max_drift' => round($maxDrift, 2),
                    'needs_rebalancing' => $needsRebalancing,
                    'urgency' => $driftResult['urgency'] ?? 'low',
                    'recommendation' => $driftResult['recommendation'] ?? '',
                ],
                'rebalancing_actions' => [],
                'cgt_analysis' => null,
            ];

            // If needs rebalancing, calculate trade actions
            if ($needsRebalancing && $holdings->count() > 0) {
                // Convert target allocation to weights array matching holdings
                $targetWeights = $this->convertAllocationToHoldingWeights($holdings, $targetAllocation);

                $rebalanceResult = $this->rebalancingCalculator->calculateRebalancing(
                    $holdings,
                    $targetWeights,
                    ['min_trade_size' => 50]
                );

                if ($rebalanceResult['success'] ?? false) {
                    $response['rebalancing_actions'] = $rebalanceResult['actions'] ?? [];

                    // For taxable accounts, calculate CGT impact
                    if (! $isTaxFree && ! empty($rebalanceResult['actions'])) {
                        $cgtResult = $this->taxAwareRebalancer->optimizeForCGT(
                            $rebalanceResult['actions'],
                            $holdings,
                            [
                                'cgt_allowance' => $this->taxConfig->getCapitalGainsTax()['annual_exempt_amount'] ?? 3000,
                                'tax_rate' => 0.20,
                                'loss_carryforward' => 0,
                            ]
                        );

                        $response['rebalancing_actions'] = $cgtResult['optimized_actions'] ?? $rebalanceResult['actions'];
                        $response['cgt_analysis'] = [
                            'total_gains' => $cgtResult['cgt_analysis']['total_gains'] ?? 0,
                            'allowance_used' => min($cgtResult['cgt_analysis']['total_gains'] ?? 0, 3000),
                            'cgt_liability' => $cgtResult['cgt_analysis']['cgt_liability'] ?? 0,
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $response,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Account rebalancing analysis');
        }
    }

    /**
     * Update rebalancing threshold for an account
     *
     * PATCH /api/investment/accounts/{id}/rebalancing-threshold
     */
    public function updateRebalancingThreshold(Request $request, int $accountId): JsonResponse
    {
        $validated = $request->validate([
            'threshold_percent' => 'required|numeric|min:1|max:50',
        ]);

        $user = $request->user();

        try {
            $account = InvestmentAccount::where('id', $accountId)
                ->where('user_id', $user->id)
                ->first();

            if (! $account) {
                return response()->json([
                    'success' => false,
                    'message' => 'Investment account not found',
                ], 404);
            }

            $account->rebalance_threshold_percent = $validated['threshold_percent'];
            $account->save();

            return response()->json([
                'success' => true,
                'data' => [
                    'account_id' => $account->id,
                    'threshold_percent' => (float) $account->rebalance_threshold_percent,
                ],
                'message' => 'Rebalancing threshold updated successfully',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Rebalancing threshold update');
        }
    }

    /**
     * Get target asset allocation for a risk level
     */
    private function getTargetAllocationForRiskLevel(int $riskLevel): array
    {
        return InvestmentDefaults::getTargetAllocation($riskLevel);
    }

    /**
     * Get risk label for a risk level
     */
    private function getRiskLabel(int $riskLevel): string
    {
        return match ($riskLevel) {
            1 => 'Low',
            2 => 'Lower-Medium',
            3 => 'Medium',
            4 => 'Upper-Medium',
            5 => 'High',
            default => 'Medium',
        };
    }

    /**
     * Map risk string (from database) to numeric level (1-5)
     */
    private function mapRiskStringToLevel(?string $riskString): int
    {
        if (! $riskString) {
            return 3; // Default to Moderate
        }

        return match (strtolower($riskString)) {
            'low', 'cautious', 'very_conservative' => 1,
            'lower_medium', 'conservative' => 2,
            'medium', 'balanced', 'moderate' => 3,
            'upper_medium', 'growth' => 4,
            'high', 'adventurous', 'aggressive' => 5,
            default => 3,
        };
    }

    /**
     * Convert asset allocation percentages to holding-level weights
     */
    private function convertAllocationToHoldingWeights($holdings, array $targetAllocation): array
    {
        $weights = [];
        $totalValue = $holdings->sum('current_value');

        if ($totalValue <= 0) {
            // Equal weights if no value
            $count = $holdings->count();

            return array_fill(0, $count, $count > 0 ? 1 / $count : 0);
        }

        foreach ($holdings as $holding) {
            $assetClass = strtolower($holding->asset_class ?? 'equities');
            $targetPercent = $targetAllocation[$assetClass]
                ?? $targetAllocation['equities']
                ?? InvestmentDefaults::TARGET_ALLOCATIONS[3]['equities'];

            // Calculate this holding's share of its asset class
            $classTotal = $holdings->where('asset_class', $holding->asset_class)->sum('current_value');
            $holdingShareOfClass = $classTotal > 0 ? ($holding->current_value / $classTotal) : 1;

            // Weight = (target class allocation) * (holding's share of that class)
            $weights[] = ($targetPercent / 100) * $holdingShareOfClass;
        }

        // Normalize weights to sum to 1
        $sum = array_sum($weights);
        if ($sum > 0) {
            $weights = array_map(fn ($w) => $w / $sum, $weights);
        }

        return $weights;
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
