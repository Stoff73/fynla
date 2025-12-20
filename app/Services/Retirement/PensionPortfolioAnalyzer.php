<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\DCPension;
use App\Models\Investment\RiskProfile;
use App\Services\Investment\AssetAllocationOptimizer;
use App\Services\Investment\PortfolioAnalyzer;
use Illuminate\Support\Collection;

/**
 * Pension Portfolio Analyzer Service
 *
 * Provides portfolio analysis for DC pension holdings including:
 * - Risk metrics analysis
 * - Fee analysis and comparison
 * - Asset allocation analysis
 * - Pension holdings breakdown
 */
class PensionPortfolioAnalyzer
{
    public function __construct(
        private PortfolioAnalyzer $portfolioAnalyzer,
        private AssetAllocationOptimizer $allocationOptimizer
    ) {}

    /**
     * Analyze DC pension portfolio for a user
     */
    public function analyze(int $userId, ?int $dcPensionId = null): array
    {
        // Get DC pensions with holdings
        $query = DCPension::where('user_id', $userId);
        if ($dcPensionId) {
            $query->where('id', $dcPensionId);
        }

        $dcPensions = $query->with('holdings')->get();

        // Filter pensions that have holdings
        $pensionsWithHoldings = $dcPensions->filter(function ($pension) {
            return $pension->holdings->isNotEmpty();
        });

        if ($pensionsWithHoldings->isEmpty()) {
            return [
                'message' => 'No DC pension holdings found for portfolio analysis',
                'pensions_with_holdings' => 0,
                'has_portfolio_data' => false,
            ];
        }

        // Aggregate all holdings from all DC pensions
        $allHoldings = $pensionsWithHoldings->flatMap->holdings;

        // Get user's risk profile
        $riskProfile = RiskProfile::where('user_id', $userId)->first();

        // Portfolio analysis using Investment services
        $totalValue = $allHoldings->sum('current_value');
        $returns = $this->portfolioAnalyzer->calculateReturns($allHoldings);
        $allocation = $this->portfolioAnalyzer->calculateAssetAllocation($allHoldings);
        $diversificationScore = $this->portfolioAnalyzer->calculateDiversificationScore($allocation);
        $riskMetrics = $this->portfolioAnalyzer->calculatePortfolioRisk($allHoldings, $riskProfile);

        // Fee analysis for pension holdings
        $feeAnalysis = $this->analyzeFees($dcPensions, $allHoldings);

        // Asset allocation vs target
        $allocationDeviation = null;
        $targetAllocation = null;
        if ($riskProfile) {
            $targetAllocation = $this->allocationOptimizer->getTargetAllocation($riskProfile);
            $allocationDeviation = $this->allocationOptimizer->calculateDeviation($allocation, $targetAllocation);
        }

        // Build portfolio summary
        return [
            'has_portfolio_data' => true,
            'pensions_with_holdings' => $pensionsWithHoldings->count(),
            'portfolio_summary' => [
                'total_value' => round($totalValue, 2),
                'pensions_count' => $pensionsWithHoldings->count(),
                'holdings_count' => $allHoldings->count(),
            ],
            'returns' => $returns,
            'asset_allocation' => $allocation,
            'target_allocation' => $targetAllocation,
            'diversification_score' => $diversificationScore,
            'risk_metrics' => $riskMetrics,
            'fee_analysis' => $feeAnalysis,
            'allocation_deviation' => $allocationDeviation,
            'pensions_breakdown' => $this->buildBreakdown($pensionsWithHoldings),
        ];
    }

    /**
     * Analyze fees for DC pension holdings
     */
    public function analyzeFees(Collection $dcPensions, Collection $allHoldings): array
    {
        $totalValue = $allHoldings->sum('current_value');

        // Platform fees (from pension level)
        $platformFees = $dcPensions->sum(function ($pension) {
            return $pension->current_fund_value * ($pension->platform_fee_percent / 100);
        });

        // Fund OCF fees (from holdings level)
        $fundFees = $allHoldings->sum(function ($holding) {
            return $holding->current_value * ($holding->ocf_percent / 100);
        });

        $totalAnnualFees = $platformFees + $fundFees;
        $feePercentage = $totalValue > 0 ? ($totalAnnualFees / $totalValue) * 100 : 0;

        // Low-cost comparison (assume 0.20% for low-cost index funds)
        $lowCostEquivalent = $totalValue * 0.002; // 0.20%
        $potentialSaving = max(0, $totalAnnualFees - $lowCostEquivalent);

        return [
            'total_annual_fees' => round($totalAnnualFees, 2),
            'fee_percentage' => round($feePercentage, 4),
            'platform_fees' => round($platformFees, 2),
            'fund_ocf_fees' => round($fundFees, 2),
            'low_cost_comparison' => [
                'low_cost_equivalent' => round($lowCostEquivalent, 2),
                'potential_annual_saving' => round($potentialSaving, 2),
            ],
        ];
    }

    /**
     * Build breakdown of holdings by pension
     */
    public function buildBreakdown(Collection $pensions): array
    {
        return $pensions->map(function ($pension) {
            $holdings = $pension->holdings;
            $totalValue = $holdings->sum('current_value');

            return [
                'id' => $pension->id,
                'scheme_name' => $pension->scheme_name,
                'scheme_type' => $pension->scheme_type,
                'provider' => $pension->provider,
                'total_value' => $totalValue,
                'holdings_count' => $holdings->count(),
                'platform_fee_percent' => $pension->platform_fee_percent,
                'holdings' => $holdings->map(function ($holding) {
                    return [
                        'id' => $holding->id,
                        'security_name' => $holding->security_name,
                        'ticker' => $holding->ticker,
                        'asset_type' => $holding->asset_type,
                        'current_value' => $holding->current_value,
                        'allocation_percent' => $holding->allocation_percent,
                        'ocf_percent' => $holding->ocf_percent,
                    ];
                })->toArray(),
            ];
        })->values()->toArray();
    }
}
