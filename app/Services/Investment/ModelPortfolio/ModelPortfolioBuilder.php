<?php

declare(strict_types=1);

namespace App\Services\Investment\ModelPortfolio;

use App\Constants\InvestmentDefaults;

/**
 * Model Portfolio Builder
 * Generates pre-built model portfolios based on risk profiles
 *
 * 5 Model Portfolios:
 * 1. Very Conservative - Capital Preservation
 * 2. Conservative - Income Focus
 * 3. Moderate - Balanced Growth & Income
 * 4. Growth - Capital Appreciation
 * 5. Aggressive - Maximum Growth
 *
 * Each portfolio includes:
 * - Asset allocation
 * - Specific fund recommendations (UK low-cost index funds)
 * - Expected return and volatility
 * - Rebalancing guidance
 * - Geographic diversification
 */
class ModelPortfolioBuilder
{
    /**
     * Get model portfolio by risk level
     *
     * @param  int  $riskLevel  Risk level (1-5)
     * @return array Model portfolio
     */
    public function getModelPortfolio(int $riskLevel): array
    {
        return match ($riskLevel) {
            1 => $this->getVeryConservativePortfolio(),
            2 => $this->getConservativePortfolio(),
            3 => $this->getModeratePortfolio(),
            4 => $this->getGrowthPortfolio(),
            5 => $this->getAggressivePortfolio(),
            default => $this->getModeratePortfolio(),
        };
    }

    /**
     * Get all model portfolios
     *
     * @return array All model portfolios
     */
    public function getAllModelPortfolios(): array
    {
        return [
            1 => $this->getVeryConservativePortfolio(),
            2 => $this->getConservativePortfolio(),
            3 => $this->getModeratePortfolio(),
            4 => $this->getGrowthPortfolio(),
            5 => $this->getAggressivePortfolio(),
        ];
    }

    /**
     * Compare current allocation with model portfolio
     *
     * @param  array  $currentHoldings  Current holdings
     * @param  int  $riskLevel  Target risk level
     * @return array Comparison analysis
     */
    public function compareWithModel(array $currentHoldings, int $riskLevel): array
    {
        $modelPortfolio = $this->getModelPortfolio($riskLevel);
        $currentAllocation = $this->calculateCurrentAllocation($currentHoldings);

        $deviations = [];
        foreach ($modelPortfolio['asset_allocation'] as $assetClass => $targetPercent) {
            $currentPercent = $currentAllocation[$assetClass] ?? 0;
            $deviation = $currentPercent - $targetPercent;

            $deviations[$assetClass] = [
                'target' => $targetPercent,
                'current' => $currentPercent,
                'deviation' => round($deviation, 1),
                'deviation_abs' => abs($deviation),
            ];
        }

        // Sort by absolute deviation
        uasort($deviations, fn ($a, $b) => $b['deviation_abs'] <=> $a['deviation_abs']);

        return [
            'model_portfolio' => $modelPortfolio,
            'current_allocation' => $currentAllocation,
            'deviations' => $deviations,
            'alignment_score' => $this->calculateAlignmentScore($deviations),
            'rebalancing_needed' => $this->needsRebalancing($deviations),
        ];
    }

    /**
     * Very Conservative Portfolio
     *
     * @return array Portfolio details
     */
    private function getVeryConservativePortfolio(): array
    {
        return [
            'name' => 'Very Conservative',
            'risk_level' => 1,
            'description' => 'Capital preservation with minimal volatility. Suitable for short-term goals or those who cannot tolerate losses.',
            'asset_allocation' => InvestmentDefaults::TARGET_ALLOCATIONS[1],
            'geographic_allocation' => [
                'uk' => 60,
                'developed_ex_uk' => 30,
                'emerging' => 10,
            ],
            'funds' => [
                [
                    'asset_class' => 'bonds',
                    'allocation' => 50,
                    'name' => 'Vanguard UK Government Bond Index',
                    'ticker' => 'VGOV',
                    'ocf' => 0.0012,
                    'type' => 'UK Gilts',
                ],
                [
                    'asset_class' => 'bonds',
                    'allocation' => 20,
                    'name' => 'Vanguard UK Investment Grade Bond Index',
                    'ticker' => 'VRIG',
                    'ocf' => 0.0012,
                    'type' => 'Corporate Bonds',
                ],
                [
                    'asset_class' => 'equities',
                    'allocation' => 10,
                    'name' => 'Vanguard FTSE UK All Share Index',
                    'ticker' => 'VUKE',
                    'ocf' => 0.0006,
                    'type' => 'UK Equity',
                ],
                [
                    'asset_class' => 'cash',
                    'allocation' => 20,
                    'name' => 'Cash/Money Market',
                    'ticker' => 'CASH',
                    'ocf' => 0.0000,
                    'type' => 'Cash',
                ],
            ],
            'expected_return' => 2.5,
            'expected_volatility' => 4.0,
            'sharpe_ratio' => 0.35,
            'rebalancing' => [
                'frequency' => 'quarterly',
                'threshold' => 5,
            ],
            'total_ocf' => 0.0010,
        ];
    }

    /**
     * Conservative Portfolio
     *
     * @return array Portfolio details
     */
    private function getConservativePortfolio(): array
    {
        return [
            'name' => 'Conservative',
            'risk_level' => 2,
            'description' => 'Income generation with capital preservation. Suitable for those nearing retirement or with low risk tolerance.',
            'asset_allocation' => InvestmentDefaults::TARGET_ALLOCATIONS[2],
            'geographic_allocation' => [
                'uk' => 50,
                'developed_ex_uk' => 40,
                'emerging' => 10,
            ],
            'funds' => [
                [
                    'asset_class' => 'bonds',
                    'allocation' => 40,
                    'name' => 'Vanguard UK Government Bond Index',
                    'ticker' => 'VGOV',
                    'ocf' => 0.0012,
                    'type' => 'UK Gilts',
                ],
                [
                    'asset_class' => 'bonds',
                    'allocation' => 15,
                    'name' => 'Vanguard Global Bond Index',
                    'ticker' => 'VGBF',
                    'ocf' => 0.0015,
                    'type' => 'Global Bonds',
                ],
                [
                    'asset_class' => 'equities',
                    'allocation' => 15,
                    'name' => 'Vanguard FTSE UK All Share Index',
                    'ticker' => 'VUKE',
                    'ocf' => 0.0006,
                    'type' => 'UK Equity',
                ],
                [
                    'asset_class' => 'equities',
                    'allocation' => 15,
                    'name' => 'Vanguard FTSE Developed World ex-UK',
                    'ticker' => 'VDWE',
                    'ocf' => 0.0014,
                    'type' => 'Global Equity',
                ],
                [
                    'asset_class' => 'cash',
                    'allocation' => 10,
                    'name' => 'Cash/Money Market',
                    'ticker' => 'CASH',
                    'ocf' => 0.0000,
                    'type' => 'Cash',
                ],
                [
                    'asset_class' => 'alternatives',
                    'allocation' => 5,
                    'name' => 'Vanguard UK Property',
                    'ticker' => 'VUKP',
                    'ocf' => 0.0015,
                    'type' => 'Property',
                ],
            ],
            'expected_return' => 4.0,
            'expected_volatility' => 7.0,
            'sharpe_ratio' => 0.45,
            'rebalancing' => [
                'frequency' => 'semi_annual',
                'threshold' => 7,
            ],
            'total_ocf' => 0.0011,
        ];
    }

    /**
     * Moderate Portfolio
     *
     * @return array Portfolio details
     */
    private function getModeratePortfolio(): array
    {
        return [
            'name' => 'Moderate',
            'risk_level' => 3,
            'description' => 'Balanced growth and income. Suitable for medium-term goals with moderate risk tolerance.',
            'asset_allocation' => InvestmentDefaults::TARGET_ALLOCATIONS[3],
            'geographic_allocation' => [
                'uk' => 30,
                'developed_ex_uk' => 55,
                'emerging' => 15,
            ],
            'funds' => [
                [
                    'asset_class' => 'equities',
                    'allocation' => 20,
                    'name' => 'Vanguard FTSE UK All Share Index',
                    'ticker' => 'VUKE',
                    'ocf' => 0.0006,
                    'type' => 'UK Equity',
                ],
                [
                    'asset_class' => 'equities',
                    'allocation' => 25,
                    'name' => 'Vanguard FTSE Developed World ex-UK',
                    'ticker' => 'VDWE',
                    'ocf' => 0.0014,
                    'type' => 'Global Equity',
                ],
                [
                    'asset_class' => 'equities',
                    'allocation' => 5,
                    'name' => 'Vanguard FTSE Emerging Markets',
                    'ticker' => 'VFEM',
                    'ocf' => 0.0023,
                    'type' => 'Emerging Markets',
                ],
                [
                    'asset_class' => 'bonds',
                    'allocation' => 25,
                    'name' => 'Vanguard UK Government Bond Index',
                    'ticker' => 'VGOV',
                    'ocf' => 0.0012,
                    'type' => 'UK Gilts',
                ],
                [
                    'asset_class' => 'bonds',
                    'allocation' => 15,
                    'name' => 'Vanguard Global Bond Index',
                    'ticker' => 'VGBF',
                    'ocf' => 0.0015,
                    'type' => 'Global Bonds',
                ],
                [
                    'asset_class' => 'cash',
                    'allocation' => 5,
                    'name' => 'Cash/Money Market',
                    'ticker' => 'CASH',
                    'ocf' => 0.0000,
                    'type' => 'Cash',
                ],
                [
                    'asset_class' => 'alternatives',
                    'allocation' => 5,
                    'name' => 'Vanguard UK Property',
                    'ticker' => 'VUKP',
                    'ocf' => 0.0015,
                    'type' => 'Property',
                ],
            ],
            'expected_return' => 5.5,
            'expected_volatility' => 11.0,
            'sharpe_ratio' => 0.40,
            'rebalancing' => [
                'frequency' => 'annual',
                'threshold' => 10,
            ],
            'total_ocf' => 0.0012,
        ];
    }

    /**
     * Growth Portfolio
     *
     * @return array Portfolio details
     */
    private function getGrowthPortfolio(): array
    {
        return [
            'name' => 'Growth',
            'risk_level' => 4,
            'description' => 'Long-term capital appreciation. Suitable for those with 10+ year horizon and high risk tolerance.',
            'asset_allocation' => InvestmentDefaults::TARGET_ALLOCATIONS[4],
            'geographic_allocation' => [
                'uk' => 25,
                'developed_ex_uk' => 55,
                'emerging' => 20,
            ],
            'funds' => [
                [
                    'asset_class' => 'equities',
                    'allocation' => 20,
                    'name' => 'Vanguard FTSE UK All Share Index',
                    'ticker' => 'VUKE',
                    'ocf' => 0.0006,
                    'type' => 'UK Equity',
                ],
                [
                    'asset_class' => 'equities',
                    'allocation' => 40,
                    'name' => 'Vanguard FTSE Developed World ex-UK',
                    'ticker' => 'VDWE',
                    'ocf' => 0.0014,
                    'type' => 'Global Equity',
                ],
                [
                    'asset_class' => 'equities',
                    'allocation' => 10,
                    'name' => 'Vanguard FTSE Emerging Markets',
                    'ticker' => 'VFEM',
                    'ocf' => 0.0023,
                    'type' => 'Emerging Markets',
                ],
                [
                    'asset_class' => 'equities',
                    'allocation' => 5,
                    'name' => 'Vanguard Global Small-Cap Index',
                    'ticker' => 'VHYL',
                    'ocf' => 0.0029,
                    'type' => 'Small Cap',
                ],
                [
                    'asset_class' => 'bonds',
                    'allocation' => 20,
                    'name' => 'Vanguard Global Bond Index',
                    'ticker' => 'VGBF',
                    'ocf' => 0.0015,
                    'type' => 'Global Bonds',
                ],
                [
                    'asset_class' => 'alternatives',
                    'allocation' => 5,
                    'name' => 'Vanguard UK Property',
                    'ticker' => 'VUKP',
                    'ocf' => 0.0015,
                    'type' => 'Property',
                ],
            ],
            'expected_return' => 7.0,
            'expected_volatility' => 16.0,
            'sharpe_ratio' => 0.35,
            'rebalancing' => [
                'frequency' => 'annual',
                'threshold' => 15,
            ],
            'total_ocf' => 0.0015,
        ];
    }

    /**
     * Aggressive Portfolio
     *
     * @return array Portfolio details
     */
    private function getAggressivePortfolio(): array
    {
        return [
            'name' => 'Aggressive',
            'risk_level' => 5,
            'description' => 'Maximum growth potential. Suitable for those with 15+ year horizon and very high risk tolerance.',
            'asset_allocation' => InvestmentDefaults::TARGET_ALLOCATIONS[5],
            'geographic_allocation' => [
                'uk' => 20,
                'developed_ex_uk' => 55,
                'emerging' => 25,
            ],
            'funds' => [
                [
                    'asset_class' => 'equities',
                    'allocation' => 15,
                    'name' => 'Vanguard FTSE UK All Share Index',
                    'ticker' => 'VUKE',
                    'ocf' => 0.0006,
                    'type' => 'UK Equity',
                ],
                [
                    'asset_class' => 'equities',
                    'allocation' => 45,
                    'name' => 'Vanguard FTSE Developed World ex-UK',
                    'ticker' => 'VDWE',
                    'ocf' => 0.0014,
                    'type' => 'Global Equity',
                ],
                [
                    'asset_class' => 'equities',
                    'allocation' => 20,
                    'name' => 'Vanguard FTSE Emerging Markets',
                    'ticker' => 'VFEM',
                    'ocf' => 0.0023,
                    'type' => 'Emerging Markets',
                ],
                [
                    'asset_class' => 'equities',
                    'allocation' => 10,
                    'name' => 'Vanguard Global Small-Cap Index',
                    'ticker' => 'VHYL',
                    'ocf' => 0.0029,
                    'type' => 'Small Cap',
                ],
                [
                    'asset_class' => 'bonds',
                    'allocation' => 5,
                    'name' => 'Vanguard Global Bond Index',
                    'ticker' => 'VGBF',
                    'ocf' => 0.0015,
                    'type' => 'Global Bonds',
                ],
                [
                    'asset_class' => 'alternatives',
                    'allocation' => 5,
                    'name' => 'Vanguard UK Property',
                    'ticker' => 'VUKP',
                    'ocf' => 0.0015,
                    'type' => 'Property',
                ],
            ],
            'expected_return' => 8.5,
            'expected_volatility' => 20.0,
            'sharpe_ratio' => 0.35,
            'rebalancing' => [
                'frequency' => 'biennial',
                'threshold' => 20,
            ],
            'total_ocf' => 0.0017,
        ];
    }

    /**
     * Calculate current allocation from holdings
     *
     * @param  array  $holdings  Current holdings
     * @return array Current allocation by asset class
     */
    private function calculateCurrentAllocation(array $holdings): array
    {
        $totalValue = array_sum(array_column($holdings, 'current_value'));

        if ($totalValue == 0) {
            return [
                'equities' => 0,
                'bonds' => 0,
                'cash' => 0,
                'alternatives' => 0,
            ];
        }

        $allocation = [
            'equities' => 0,
            'bonds' => 0,
            'cash' => 0,
            'alternatives' => 0,
        ];

        foreach ($holdings as $holding) {
            $assetClass = InvestmentDefaults::resolveAssetClass($holding['asset_type'] ?? 'unknown', $holding['sub_type'] ?? null);
            $percentage = ($holding['current_value'] / $totalValue) * 100;
            $allocation[$assetClass] += $percentage;
        }

        return array_map(fn ($val) => round($val, 1), $allocation);
    }

    /**
     * Calculate alignment score
     *
     * @param  array  $deviations  Deviations from model
     * @return array Alignment score
     */
    private function calculateAlignmentScore(array $deviations): array
    {
        $totalDeviation = array_sum(array_column($deviations, 'deviation_abs'));
        $score = max(0, 100 - ($totalDeviation / 2));

        if ($score >= 90) {
            $rating = 'Excellent';
            $message = 'Portfolio closely matches model allocation';
        } elseif ($score >= 75) {
            $rating = 'Good';
            $message = 'Portfolio reasonably aligned with model';
        } elseif ($score >= 60) {
            $rating = 'Fair';
            $message = 'Some deviation from model - consider rebalancing';
        } else {
            $rating = 'Poor';
            $message = 'Significant deviation from model - rebalancing recommended';
        }

        return [
            'score' => round($score, 1),
            'rating' => $rating,
            'message' => $message,
        ];
    }

    /**
     * Check if rebalancing is needed
     *
     * @param  array  $deviations  Deviations
     * @return bool Needs rebalancing
     */
    private function needsRebalancing(array $deviations): bool
    {
        foreach ($deviations as $deviation) {
            if ($deviation['deviation_abs'] > 10) {
                return true;
            }
        }

        return false;
    }
}
