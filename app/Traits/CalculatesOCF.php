<?php

declare(strict_types=1);

namespace App\Traits;

use App\Constants\InvestmentDefaults;
use Illuminate\Support\Collection;

/**
 * Trait for OCF (Ongoing Charges Figure) calculations.
 *
 * Provides weighted OCF calculation across holdings, OCF estimation
 * by asset type, and compound fee-savings projection.
 *
 * Extracted from:
 * - App\Services\Investment\FeeAnalyzer
 * - App\Services\Investment\Fees\OCFImpactCalculator
 */
trait CalculatesOCF
{
    /**
     * Calculate weighted average OCF across holdings.
     *
     * Each holding's OCF is weighted by its proportion of the total portfolio value.
     * Holdings without an explicit OCF are estimated via estimateOCF().
     *
     * @param  Collection  $holdings  Portfolio holdings (must have current_value, ocf, asset_type)
     * @param  float  $totalValue  Total portfolio value
     * @return float Weighted OCF as a decimal (e.g. 0.005 = 0.5%)
     */
    protected function calculateWeightedOCF(Collection $holdings, float $totalValue): float
    {
        if ($totalValue == 0) {
            return 0.0;
        }

        $weightedOCF = 0.0;

        foreach ($holdings as $holding) {
            $weight = $holding->current_value / $totalValue;
            $ocf = $holding->ocf ?? $this->estimateOCF($holding->asset_type);
            $weightedOCF += $weight * $ocf;
        }

        return $weightedOCF;
    }

    /**
     * Estimate OCF for an asset type when no explicit value is provided.
     *
     * Looks up the asset type in InvestmentDefaults::DEFAULT_OCF_ESTIMATES,
     * falling back to the 'default' entry for unrecognised types.
     *
     * @param  string  $assetType  Asset type (e.g. 'index_fund', 'etf', 'active_fund')
     * @return float Estimated OCF as a decimal (e.g. 0.001 = 0.1%)
     */
    protected function estimateOCF(string $assetType): float
    {
        $estimates = InvestmentDefaults::DEFAULT_OCF_ESTIMATES;

        return $estimates[strtolower($assetType)] ?? $estimates['default'];
    }

    /**
     * Calculate compound fee savings over a projection period.
     *
     * Projects the difference in portfolio value between the current fee rate
     * and a reduced fee rate, compounded over the given number of years.
     *
     * @param  float  $portfolioValue  Current portfolio value
     * @param  float  $annualSavings  Annual fee savings in currency
     * @param  int  $years  Projection period in years
     * @param  float  $returnRate  Expected gross annual return as a decimal (e.g. 0.06 = 6%)
     * @return float Projected compound savings over the period
     */
    protected function calculateCompoundSavings(float $portfolioValue, float $annualSavings, int $years, float $returnRate): float
    {
        if ($annualSavings <= 0 || $portfolioValue == 0) {
            return 0.0;
        }

        $feePercent = $annualSavings / $portfolioValue;

        return $portfolioValue * (pow(1 + $returnRate, $years) - pow(1 + $returnRate - $feePercent, $years));
    }
}
