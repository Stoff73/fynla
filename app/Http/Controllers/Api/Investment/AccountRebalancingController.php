<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Investment;

use App\Constants\InvestmentDefaults;
use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Models\User;
use App\Services\Investment\Rebalancing\DriftAnalyzer;
use App\Services\Investment\Rebalancing\RebalancingCalculator;
use App\Services\Investment\Rebalancing\TaxAwareRebalancer;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Account-level rebalancing controller
 *
 * Split out from RebalancingCalculationController (which handles portfolio-level
 * actions). This controller owns the account-scoped routes — drift + rebalancing
 * analysis for a single investment account, and the per-account threshold.
 *
 * Routes:
 *   GET   /api/investment/accounts/{id}/rebalancing
 *   PATCH /api/investment/accounts/{id}/rebalancing-threshold
 */
class AccountRebalancingController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly RebalancingCalculator $rebalancingCalculator,
        private readonly TaxAwareRebalancer $taxAwareRebalancer,
        private readonly DriftAnalyzer $driftAnalyzer
    ) {}

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

            $accountType = strtolower($account->account_type ?? '');
            $isTaxFree = in_array($accountType, ['isa', 'sipp', 'pension', 'lisa']);

            $riskProfileInfo = $this->resolveAccountRiskProfile($account, $user);
            $targetAllocation = $this->getTargetAllocationForRiskLevel($riskProfileInfo['effective_risk_level']);
            $thresholdPercent = (float) ($account->rebalance_threshold_percent ?? 10.0);

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

            if ($needsRebalancing && $holdings->count() > 0) {
                $targetWeights = $this->convertAllocationToHoldingWeights($holdings, $targetAllocation);

                $rebalanceResult = $this->rebalancingCalculator->calculateRebalancing(
                    $holdings,
                    $targetWeights,
                    ['min_trade_size' => 50]
                );

                if ($rebalanceResult['success'] ?? false) {
                    $response['rebalancing_actions'] = $rebalanceResult['actions'] ?? [];

                    if (! $isTaxFree && ! empty($rebalanceResult['actions'])) {
                        $cgtResult = $this->taxAwareRebalancer->optimizeForCGT(
                            $rebalanceResult['actions'],
                            $holdings,
                            [
                                'cgt_allowance' => null,
                                'tax_rate' => null,
                                'loss_carryforward' => 0,
                            ]
                        );

                        $response['rebalancing_actions'] = $cgtResult['optimized_actions'] ?? $rebalanceResult['actions'];
                        $response['cgt_analysis'] = [
                            'total_gains' => $cgtResult['cgt_analysis']['total_gains'] ?? 0,
                            'allowance_used' => $cgtResult['cgt_analysis']['allowance_used'] ?? 0,
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
     * Resolve the effective risk profile for a single investment account.
     *
     * Combines the user's main risk profile (from risk_profiles) with the
     * account's optional custom override (`has_custom_risk` + `risk_preference`).
     * Extracted from getAccountRebalancing during the controller split
     * (tech-debt audit Warning #1, 2026-05-13 session 3).
     */
    private function resolveAccountRiskProfile(InvestmentAccount $account, User $user): array
    {
        $userRiskProfile = RiskProfile::where('user_id', $user->id)->first();

        $userRiskLevel = $userRiskProfile
            ? $this->mapRiskStringToLevel($userRiskProfile->risk_level)
            : 3;
        $userRiskLabel = $this->getRiskLabel($userRiskLevel);

        $hasCustomRisk = (bool) $account->has_custom_risk;
        $accountRiskPreference = $account->risk_preference;

        if ($hasCustomRisk && $accountRiskPreference) {
            $effectiveRiskLevel = $this->mapRiskStringToLevel($accountRiskPreference);
            $effectiveRiskLabel = $this->getRiskLabel($effectiveRiskLevel);
        } else {
            $effectiveRiskLevel = $userRiskLevel;
            $effectiveRiskLabel = $userRiskLabel;
        }

        return [
            'user_risk_level' => $userRiskLevel,
            'user_risk_label' => $userRiskLabel,
            'has_custom_risk' => $hasCustomRisk,
            'account_risk_preference' => $accountRiskPreference,
            'effective_risk_level' => $effectiveRiskLevel,
            'effective_risk_label' => $effectiveRiskLabel,
        ];
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
            return 3;
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
     *
     * @param  EloquentCollection|\Illuminate\Support\Collection  $holdings
     */
    private function convertAllocationToHoldingWeights($holdings, array $targetAllocation): array
    {
        $weights = [];
        $totalValue = $holdings->sum('current_value');

        if ($totalValue <= 0) {
            $count = $holdings->count();

            return array_fill(0, $count, $count > 0 ? 1 / $count : 0);
        }

        foreach ($holdings as $holding) {
            $assetClass = strtolower($holding->asset_class ?? 'equities');
            $targetPercent = $targetAllocation[$assetClass]
                ?? $targetAllocation['equities']
                ?? InvestmentDefaults::TARGET_ALLOCATIONS[3]['equities'];

            $classTotal = $holdings->where('asset_class', $holding->asset_class)->sum('current_value');
            $holdingShareOfClass = $classTotal > 0 ? ($holding->current_value / $classTotal) : 1;

            $weights[] = ($targetPercent / 100) * $holdingShareOfClass;
        }

        $sum = array_sum($weights);
        if ($sum > 0) {
            $weights = array_map(fn ($w) => $w / $sum, $weights);
        }

        return $weights;
    }
}
