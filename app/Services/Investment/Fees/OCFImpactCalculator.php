<?php

declare(strict_types=1);

namespace App\Services\Investment\Fees;

use App\Constants\InvestmentDefaults;
use App\Models\Investment\Holding;
use App\Services\Risk\RiskPreferenceService;
use App\Traits\CalculatesOCF;
use Illuminate\Support\Collection;

/**
 * OCF Impact Calculator
 * Projects the impact of Ongoing Charges Figure (OCF) on portfolio returns over time
 *
 * Calculations:
 * - Long-term OCF drag on portfolio value
 * - Comparison between active and passive funds
 * - OCF efficiency scoring
 * - Alternative fund suggestions with lower OCF
 */
class OCFImpactCalculator
{
    use CalculatesOCF;

    public function __construct(
        private readonly RiskPreferenceService $riskPreferenceService
    ) {}

    /**
     * Calculate OCF impact over time
     *
     * @param  Collection  $holdings  Portfolio holdings
     * @param  int  $years  Projection years
     * @param  float  $expectedReturn  Expected gross return
     * @return array OCF impact analysis
     */
    public function calculateOCFImpact(Collection $holdings, int $years = 20, ?float $expectedReturn = null, string $riskLevel = 'medium'): array
    {
        if ($expectedReturn === null) {
            $expectedReturn = $this->riskPreferenceService->getReturnParameters($riskLevel)['expected_return_typical'] / 100;
        }

        if ($holdings->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No holdings provided',
            ];
        }

        $totalValue = $holdings->sum('current_value');

        if ($totalValue == 0) {
            return [
                'success' => false,
                'message' => 'Portfolio has zero value',
            ];
        }

        // Calculate weighted average OCF
        $weightedOCF = $this->calculateWeightedOCF($holdings, $totalValue);

        // Project portfolio value with and without OCF
        $projection = $this->projectPortfolioValue($totalValue, $weightedOCF, $years, $expectedReturn);

        // Calculate by holding
        $holdingImpacts = $this->calculateHoldingImpacts($holdings, $years, $expectedReturn);

        // Find high-cost holdings
        $highCostHoldings = $this->identifyHighCostHoldings($holdings);

        return [
            'success' => true,
            'current_portfolio_value' => $totalValue,
            'weighted_ocf_percent' => round($weightedOCF * 100, 3),
            'annual_ocf_cost' => round($totalValue * $weightedOCF, 2),
            'projection' => $projection,
            'holding_impacts' => $holdingImpacts,
            'high_cost_holdings' => $highCostHoldings,
            'summary' => $this->generateSummary($projection, $weightedOCF),
        ];
    }

    /**
     * Compare active vs passive fund costs
     *
     * @param  Collection  $holdings  Holdings
     * @return array Active vs passive comparison
     */
    public function compareActiveVsPassive(Collection $holdings): array
    {
        $activeHoldings = $holdings->filter(fn ($h) => $this->isActiveFund($h));
        $passiveHoldings = $holdings->filter(fn ($h) => $this->isPassiveFund($h));

        $activeValue = $activeHoldings->sum('current_value');
        $passiveValue = $passiveHoldings->sum('current_value');
        $totalValue = $activeValue + $passiveValue;

        if ($totalValue == 0) {
            return [
                'success' => false,
                'message' => 'No fund holdings to compare',
            ];
        }

        $activeOCF = $activeValue > 0 ? $this->calculateWeightedOCF($activeHoldings, $activeValue) : 0;
        $passiveOCF = $passiveValue > 0 ? $this->calculateWeightedOCF($passiveHoldings, $passiveValue) : 0;

        // Calculate savings if all active moved to passive
        $potentialPassiveOCF = 0.0015; // 0.15% typical passive fund
        $savingsIfAllPassive = $activeValue * ($activeOCF - $potentialPassiveOCF);

        return [
            'success' => true,
            'active' => [
                'value' => $activeValue,
                'percent' => round(($activeValue / $totalValue) * 100, 1),
                'count' => $activeHoldings->count(),
                'average_ocf' => round($activeOCF * 100, 3),
                'annual_cost' => round($activeValue * $activeOCF, 2),
            ],
            'passive' => [
                'value' => $passiveValue,
                'percent' => round(($passiveValue / $totalValue) * 100, 1),
                'count' => $passiveHoldings->count(),
                'average_ocf' => round($passiveOCF * 100, 3),
                'annual_cost' => round($passiveValue * $passiveOCF, 2),
            ],
            'potential_savings' => [
                'annual' => round(max(0, $savingsIfAllPassive), 2),
                'ten_year' => round(max(0, $this->calculateCompoundSavings($activeValue, $savingsIfAllPassive, 10, $this->getDefaultExpectedReturn())), 2),
            ],
            'recommendation' => $this->generateActiveVsPassiveRecommendation($activeValue, $passiveValue, $activeOCF, $passiveOCF),
        ];
    }

    /**
     * Find low-cost alternatives for high-OCF holdings
     *
     * @param  Holding  $holding  Holding
     * @return array Alternative suggestions
     */
    public function findLowCostAlternatives(Holding $holding): array
    {
        $currentOCF = $holding->ocf ?? $this->estimateOCF($holding->asset_type);
        $assetType = $holding->asset_type;

        // Get typical alternatives based on asset type
        $alternatives = $this->getSuggestedAlternatives($assetType, $holding->ticker ?? '');

        $projectedSavings = [];
        foreach ($alternatives as $alternative) {
            $ocfDiff = $currentOCF - $alternative['ocf'];
            $annualSaving = $holding->current_value * $ocfDiff;

            $projectedSavings[] = [
                'fund_name' => $alternative['name'],
                'ticker' => $alternative['ticker'],
                'ocf' => round($alternative['ocf'] * 100, 3),
                'annual_saving' => round($annualSaving, 2),
                'ten_year_saving' => round($this->calculateCompoundSavings($holding->current_value, $annualSaving, 10, $this->getDefaultExpectedReturn()), 2),
                'provider' => $alternative['provider'],
            ];
        }

        return [
            'current_holding' => [
                'name' => $holding->security_name ?? $holding->ticker,
                'ticker' => $holding->ticker,
                'ocf' => round($currentOCF * 100, 3),
                'value' => $holding->current_value,
            ],
            'alternatives' => $projectedSavings,
            'has_better_options' => count($projectedSavings) > 0 && $projectedSavings[0]['annual_saving'] > 50,
        ];
    }

    /**
     * Project portfolio value with OCF impact
     *
     * @param  float  $initialValue  Initial value
     * @param  float  $ocf  OCF rate
     * @param  int  $years  Years
     * @param  float  $grossReturn  Gross return
     * @return array Projection
     */
    private function projectPortfolioValue(float $initialValue, float $ocf, int $years, float $grossReturn): array
    {
        $netReturn = $grossReturn - $ocf;

        $valueWithoutOCF = $initialValue * pow(1 + $grossReturn, $years);
        $valueWithOCF = $initialValue * pow(1 + $netReturn, $years);
        $ocfDrag = $valueWithoutOCF - $valueWithOCF;

        $yearByYear = [];
        for ($year = 1; $year <= min($years, 30); $year++) {
            $yearByYear[] = [
                'year' => $year,
                'value_without_ocf' => round($initialValue * pow(1 + $grossReturn, $year), 2),
                'value_with_ocf' => round($initialValue * pow(1 + $netReturn, $year), 2),
                'cumulative_drag' => round(($initialValue * pow(1 + $grossReturn, $year)) - ($initialValue * pow(1 + $netReturn, $year)), 2),
            ];
        }

        return [
            'years' => $years,
            'initial_value' => $initialValue,
            'gross_return_percent' => $grossReturn * 100,
            'net_return_percent' => $netReturn * 100,
            'final_value_without_ocf' => round($valueWithoutOCF, 2),
            'final_value_with_ocf' => round($valueWithOCF, 2),
            'total_ocf_drag' => round($ocfDrag, 2),
            'drag_percent_of_final' => round(($ocfDrag / $valueWithoutOCF) * 100, 1),
            'year_by_year' => $yearByYear,
        ];
    }

    /**
     * Calculate individual holding impacts
     *
     * @param  Collection  $holdings  Holdings
     * @param  int  $years  Years
     * @param  float  $expectedReturn  Expected return
     * @return array Holding impacts
     */
    private function calculateHoldingImpacts(Collection $holdings, int $years, float $expectedReturn): array
    {
        $impacts = [];

        foreach ($holdings as $holding) {
            $ocf = $holding->ocf ?? $this->estimateOCF($holding->asset_type);
            $drag = $holding->current_value * (pow(1 + $expectedReturn, $years) - pow(1 + $expectedReturn - $ocf, $years));

            $impacts[] = [
                'holding_id' => $holding->id,
                'name' => $holding->security_name ?? $holding->ticker,
                'ticker' => $holding->ticker,
                'current_value' => $holding->current_value,
                'ocf' => round($ocf * 100, 3),
                'annual_cost' => round($holding->current_value * $ocf, 2),
                'total_drag_over_period' => round($drag, 2),
            ];
        }

        usort($impacts, fn ($a, $b) => $b['total_drag_over_period'] <=> $a['total_drag_over_period']);

        return array_slice($impacts, 0, 20); // Top 20
    }

    /**
     * Identify high-cost holdings (OCF > 0.75%)
     *
     * @param  Collection  $holdings  Holdings
     * @return array High-cost holdings
     */
    private function identifyHighCostHoldings(Collection $holdings): array
    {
        $highCost = [];

        foreach ($holdings as $holding) {
            $ocf = $holding->ocf ?? $this->estimateOCF($holding->asset_type);

            if ($ocf > InvestmentDefaults::HIGH_OCF_THRESHOLD_DECIMAL) {
                $highCost[] = [
                    'holding_id' => $holding->id,
                    'name' => $holding->security_name ?? $holding->ticker,
                    'ticker' => $holding->ticker,
                    'value' => $holding->current_value,
                    'ocf' => round($ocf * 100, 3),
                    'annual_cost' => round($holding->current_value * $ocf, 2),
                    'severity' => $ocf > 0.015 ? 'high' : 'medium',
                ];
            }
        }

        usort($highCost, fn ($a, $b) => $b['ocf'] <=> $a['ocf']);

        return $highCost;
    }

    /**
     * Check if holding is active fund
     *
     * @param  Holding  $holding  Holding
     * @return bool Is active fund
     */
    private function isActiveFund(Holding $holding): bool
    {
        return $holding->asset_type === 'active_fund' || (($holding->ocf ?? 0) > 0.0050);
    }

    /**
     * Check if holding is passive fund
     *
     * @param  Holding  $holding  Holding
     * @return bool Is passive fund
     */
    private function isPassiveFund(Holding $holding): bool
    {
        return in_array($holding->asset_type, ['index_fund', 'etf']) || (($holding->ocf ?? 0) <= 0.0025);
    }

    /**
     * Generate summary
     *
     * @param  array  $projection  Projection
     * @param  float  $ocf  OCF
     * @return array Summary
     */
    private function generateSummary(array $projection, float $ocf): array
    {
        $dragPercent = $projection['drag_percent_of_final'];

        if ($dragPercent > 30) {
            $assessment = 'very_high';
            $message = sprintf(
                'Very high OCF impact - %.1f%% drag over %d years. Significant savings possible with lower-cost funds.',
                $dragPercent,
                $projection['years']
            );
        } elseif ($dragPercent > 20) {
            $assessment = 'high';
            $message = sprintf(
                'High OCF impact - %.1f%% drag over %d years. Consider reviewing fund choices.',
                $dragPercent,
                $projection['years']
            );
        } elseif ($dragPercent > 10) {
            $assessment = 'medium';
            $message = sprintf(
                'Moderate OCF impact - %.1f%% drag over %d years. Some optimization opportunities exist.',
                $dragPercent,
                $projection['years']
            );
        } else {
            $assessment = 'low';
            $message = sprintf(
                'Low OCF impact - %.1f%% drag over %d years. Well-optimized portfolio.',
                $dragPercent,
                $projection['years']
            );
        }

        return [
            'assessment' => $assessment,
            'message' => $message,
            'drag_value' => $projection['total_ocf_drag'],
            'drag_percent' => $dragPercent,
        ];
    }

    /**
     * Generate active vs passive recommendation
     *
     * @param  float  $activeValue  Active value
     * @param  float  $passiveValue  Passive value
     * @param  float  $activeOCF  Active OCF
     * @param  float  $passiveOCF  Passive OCF
     * @return string Recommendation
     */
    private function generateActiveVsPassiveRecommendation(float $activeValue, float $passiveValue, float $activeOCF, float $passiveOCF): string
    {
        if ($activeValue == 0) {
            return 'Excellent - 100% passive funds. Optimal cost structure.';
        }

        $activePercent = ($activeValue / ($activeValue + $passiveValue)) * 100;

        if ($activePercent > 75) {
            return sprintf(
                'High active fund allocation (%.0f%%). Consider moving to passive funds to reduce costs. Typical active OCF: %.2f%%, passive: 0.15%%.',
                $activePercent,
                $activeOCF * 100
            );
        } elseif ($activePercent > 50) {
            return sprintf(
                'Balanced active/passive split (%.0f%% active). Review if active funds justify their higher costs (avg %.2f%% OCF).',
                $activePercent,
                $activeOCF * 100
            );
        } else {
            return sprintf(
                'Good passive allocation (%.0f%%). Active funds should outperform to justify %.2f%% average OCF.',
                100 - $activePercent,
                $activeOCF * 100
            );
        }
    }

    /**
     * Get default expected return from risk preference service (medium risk)
     */
    private function getDefaultExpectedReturn(): float
    {
        return $this->riskPreferenceService->getReturnParameters('medium')['expected_return_typical'] / 100;
    }

    /**
     * Get suggested alternatives
     *
     * @param  string  $assetType  Asset type
     * @param  string  $ticker  Current ticker
     * @return array Alternatives
     */
    private function getSuggestedAlternatives(string $assetType, string $ticker): array
    {
        // Common low-cost UK alternatives
        $alternatives = [];

        if (str_contains(strtolower($assetType), 'equity') || str_contains(strtolower($ticker), 'UK')) {
            $alternatives[] = [
                'name' => 'Vanguard FTSE UK All Share Index',
                'ticker' => 'VUKE',
                'ocf' => 0.0006,
                'provider' => 'Vanguard',
            ];
        }

        if (str_contains(strtolower($assetType), 'global') || str_contains(strtolower($ticker), 'World')) {
            $alternatives[] = [
                'name' => 'Vanguard FTSE Global All Cap Index',
                'ticker' => 'VGAG',
                'ocf' => 0.0023,
                'provider' => 'Vanguard',
            ];
        }

        return $alternatives;
    }
}
