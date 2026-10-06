<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Investment;

use App\Constants\InvestmentDefaults;
use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Investment\InvestmentAccount;
use App\Services\Investment\Rebalancing\AccountDriftService;
use App\Services\Investment\Rebalancing\RebalancingCalculator;
use App\Services\Investment\Rebalancing\TaxAwareRebalancer;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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
        private readonly AccountDriftService $accountDrift,
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
            // Either owner of a joint account sees its panel, as the card shows it to both.
            $account = InvestmentAccount::forUserOrJoint($user->id)
                ->where('id', $accountId)
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

            // The one rule for "is this account outside its threshold" (item 8).
            $drift = $this->accountDrift->forAccount($account, $user);
            $targetAllocation = $drift['target_allocation'];
            $needsRebalancing = $drift['needs_rebalancing'];

            $response = [
                'account_id' => $account->id,
                'account_type' => $accountType,
                'is_tax_free' => $isTaxFree,
                'risk_profile' => $drift['risk_profile'],
                'threshold_percent' => $drift['threshold_percent'],
                'current_allocation' => $drift['current_allocation'],
                'unrecorded_percent' => $drift['unrecorded_percent'],
                'target_allocation' => $targetAllocation,
                'drift_analysis' => [
                    'drift_score' => $drift['drift_score'],
                    'max_drift' => $drift['max_drift'],
                    'needs_rebalancing' => $needsRebalancing,
                    'urgency' => $drift['urgency'],
                    'recommendation' => $drift['recommendation'],
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
     * Convert asset allocation percentages to holding-level weights
     *
     * @param  EloquentCollection|Collection  $holdings
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
