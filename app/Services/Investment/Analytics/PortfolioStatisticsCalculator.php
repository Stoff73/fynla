<?php

declare(strict_types=1);

namespace App\Services\Investment\Analytics;

/**
 * Portfolio Statistics Calculator
 * Calculates comprehensive risk and return metrics for portfolios
 *
 * Moved from: App\Services\Investment\EfficientFrontier\PortfolioStatisticsCalculator
 *
 * Metrics:
 * - Expected Return: Weighted average of asset returns
 * - Volatility (Standard Deviation): Portfolio risk measure
 * - Sharpe Ratio: Risk-adjusted return
 * - Sortino Ratio: Downside risk-adjusted return
 * - Maximum Drawdown: Largest peak-to-trough decline
 * - Value at Risk (VaR): Potential loss at confidence level
 * - Diversification Ratio: Benefit from diversification
 */
class PortfolioStatisticsCalculator
{
    /**
     * Calculate comprehensive portfolio statistics
     *
     * @param  array  $allocation  Portfolio allocation weights
     * @param  array  $assetClasses  Asset class data (returns, volatility, correlations)
     * @param  float  $riskFreeRate  Risk-free rate
     * @return array Comprehensive statistics
     */
    public function calculateStatistics(
        array $allocation,
        array $assetClasses,
        float $riskFreeRate = 0.04
    ): array {
        // Expected return
        $expectedReturn = $this->calculateExpectedReturn($allocation, $assetClasses);

        // Volatility (standard deviation)
        $volatility = $this->calculateVolatility($allocation, $assetClasses);

        // Sharpe ratio
        $sharpeRatio = $this->calculateSharpeRatio($expectedReturn, $volatility, $riskFreeRate);

        // Sortino ratio (uses downside deviation)
        $downsideDeviation = $this->calculateDownsideDeviation($allocation, $assetClasses, $riskFreeRate);
        $sortinoRatio = $this->calculateSortinoRatio($expectedReturn, $downsideDeviation, $riskFreeRate);

        // Value at Risk (95% confidence)
        $var95 = $this->calculateVaR($expectedReturn, $volatility, 0.95);

        // Conditional Value at Risk (Expected Shortfall)
        $cvar95 = $this->calculateCVaR($expectedReturn, $volatility, 0.95);

        // Maximum drawdown estimate
        $maxDrawdown = $this->estimateMaxDrawdown($volatility);

        // Diversification ratio
        $diversificationRatio = $this->calculateDiversificationRatio($allocation, $assetClasses, $volatility);

        return [
            'expected_return' => round($expectedReturn * 100, 2), // Percentage
            'volatility' => round($volatility * 100, 2), // Percentage
            'sharpe_ratio' => round($sharpeRatio, 2),
            'sortino_ratio' => round($sortinoRatio, 2),
            'downside_deviation' => round($downsideDeviation * 100, 2),
            'value_at_risk_95' => round($var95 * 100, 2), // Percentage loss
            'cvar_95' => round($cvar95 * 100, 2),
            'max_drawdown_estimate' => round($maxDrawdown * 100, 2),
            'diversification_ratio' => round($diversificationRatio, 2),
            'risk_free_rate' => round($riskFreeRate * 100, 2),
        ];
    }

    /**
     * Calculate expected return (weighted average)
     */
    private function calculateExpectedReturn(array $allocation, array $assetClasses): float
    {
        $expectedReturn = 0.0;

        foreach ($allocation as $asset => $weight) {
            if (isset($assetClasses[$asset])) {
                $expectedReturn += $weight * $assetClasses[$asset]['expected_return'];
            }
        }

        return $expectedReturn;
    }

    /**
     * Calculate portfolio volatility (standard deviation)
     */
    private function calculateVolatility(array $allocation, array $assetClasses): float
    {
        $variance = 0.0;

        foreach ($allocation as $asset1 => $weight1) {
            foreach ($allocation as $asset2 => $weight2) {
                if (! isset($assetClasses[$asset1]) || ! isset($assetClasses[$asset2])) {
                    continue;
                }

                $volatility1 = $assetClasses[$asset1]['volatility'];
                $volatility2 = $assetClasses[$asset2]['volatility'];
                $correlation = $this->getCorrelation($asset1, $asset2, $assetClasses);

                $variance += $weight1 * $weight2 * $volatility1 * $volatility2 * $correlation;
            }
        }

        return sqrt(max(0, $variance));
    }

    /**
     * Calculate Sharpe ratio
     */
    private function calculateSharpeRatio(
        float $expectedReturn,
        float $volatility,
        float $riskFreeRate
    ): float {
        if ($volatility <= 0) {
            return 0.0;
        }

        return ($expectedReturn - $riskFreeRate) / $volatility;
    }

    /**
     * Calculate downside deviation (semi-deviation)
     */
    private function calculateDownsideDeviation(
        array $allocation,
        array $assetClasses,
        float $targetReturn
    ): float {
        $volatility = $this->calculateVolatility($allocation, $assetClasses);

        return $volatility * 0.7;
    }

    /**
     * Calculate Sortino ratio
     */
    private function calculateSortinoRatio(
        float $expectedReturn,
        float $downsideDeviation,
        float $riskFreeRate
    ): float {
        if ($downsideDeviation <= 0) {
            return 0.0;
        }

        return ($expectedReturn - $riskFreeRate) / $downsideDeviation;
    }

    /**
     * Calculate Value at Risk (VaR)
     */
    private function calculateVaR(
        float $expectedReturn,
        float $volatility,
        float $confidence
    ): float {
        $zScore = match ($confidence) {
            0.90 => 1.28,
            0.95 => 1.645,
            0.99 => 2.326,
            default => 1.645,
        };

        return abs($expectedReturn - ($zScore * $volatility));
    }

    /**
     * Calculate Conditional Value at Risk (CVaR / Expected Shortfall)
     */
    private function calculateCVaR(
        float $expectedReturn,
        float $volatility,
        float $confidence
    ): float {
        $var = $this->calculateVaR($expectedReturn, $volatility, $confidence);

        return $var * 1.2;
    }

    /**
     * Estimate maximum drawdown
     */
    private function estimateMaxDrawdown(float $volatility): float
    {
        return $volatility * 2.0;
    }

    /**
     * Calculate diversification ratio
     */
    private function calculateDiversificationRatio(
        array $allocation,
        array $assetClasses,
        float $portfolioVolatility
    ): float {
        if ($portfolioVolatility <= 0) {
            return 1.0;
        }

        $weightedVolatility = 0.0;

        foreach ($allocation as $asset => $weight) {
            if (isset($assetClasses[$asset])) {
                $weightedVolatility += $weight * $assetClasses[$asset]['volatility'];
            }
        }

        return $weightedVolatility / $portfolioVolatility;
    }

    /**
     * Get correlation between assets
     */
    private function getCorrelation(string $asset1, string $asset2, array $assetClasses): float
    {
        if ($asset1 === $asset2) {
            return 1.0;
        }

        if (isset($assetClasses[$asset1]['correlations'][$asset2])) {
            return $assetClasses[$asset1]['correlations'][$asset2];
        }

        return $this->getDefaultCorrelation($asset1, $asset2);
    }

    /**
     * Get default correlation between asset classes
     */
    private function getDefaultCorrelation(string $asset1, string $asset2): float
    {
        $defaultCorrelations = [
            'equities' => [
                'bonds' => 0.20,
                'cash' => 0.05,
                'alternatives' => 0.40,
            ],
            'bonds' => [
                'equities' => 0.20,
                'cash' => 0.30,
                'alternatives' => 0.15,
            ],
            'cash' => [
                'equities' => 0.05,
                'bonds' => 0.30,
                'alternatives' => 0.10,
            ],
            'alternatives' => [
                'equities' => 0.40,
                'bonds' => 0.15,
                'cash' => 0.10,
            ],
        ];

        return $defaultCorrelations[$asset1][$asset2] ?? 0.30;
    }

    /**
     * Get default asset class assumptions (UK market)
     */
    public function getDefaultAssetClassAssumptions(): array
    {
        return [
            'equities' => [
                'expected_return' => 0.08,
                'volatility' => 0.18,
                'correlations' => [
                    'bonds' => 0.20,
                    'cash' => 0.05,
                    'alternatives' => 0.40,
                ],
            ],
            'bonds' => [
                'expected_return' => 0.04,
                'volatility' => 0.06,
                'correlations' => [
                    'equities' => 0.20,
                    'cash' => 0.30,
                    'alternatives' => 0.15,
                ],
            ],
            'cash' => [
                'expected_return' => 0.025,
                'volatility' => 0.01,
                'correlations' => [
                    'equities' => 0.05,
                    'bonds' => 0.30,
                    'alternatives' => 0.10,
                ],
            ],
            'alternatives' => [
                'expected_return' => 0.06,
                'volatility' => 0.12,
                'correlations' => [
                    'equities' => 0.40,
                    'bonds' => 0.15,
                    'cash' => 0.10,
                ],
            ],
        ];
    }

    /**
     * Interpret portfolio statistics
     */
    public function interpretStatistics(array $statistics): array
    {
        $interpretations = [];

        // Expected return interpretation
        if ($statistics['expected_return'] >= 7) {
            $interpretations['return'] = 'High expected return - suitable for long-term growth';
        } elseif ($statistics['expected_return'] >= 4) {
            $interpretations['return'] = 'Moderate expected return - balanced growth potential';
        } else {
            $interpretations['return'] = 'Low expected return - focus on capital preservation';
        }

        // Volatility interpretation
        if ($statistics['volatility'] >= 15) {
            $interpretations['risk'] = 'High volatility - requires strong risk tolerance';
        } elseif ($statistics['volatility'] >= 8) {
            $interpretations['risk'] = 'Moderate volatility - suitable for balanced investors';
        } else {
            $interpretations['risk'] = 'Low volatility - conservative, stable portfolio';
        }

        // Sharpe ratio interpretation
        if ($statistics['sharpe_ratio'] >= 1.0) {
            $interpretations['sharpe'] = 'Excellent risk-adjusted returns';
        } elseif ($statistics['sharpe_ratio'] >= 0.5) {
            $interpretations['sharpe'] = 'Good risk-adjusted returns';
        } elseif ($statistics['sharpe_ratio'] >= 0) {
            $interpretations['sharpe'] = 'Adequate risk-adjusted returns';
        } else {
            $interpretations['sharpe'] = 'Poor risk-adjusted returns - below risk-free rate';
        }

        // Diversification ratio interpretation
        if ($statistics['diversification_ratio'] >= 1.5) {
            $interpretations['diversification'] = 'Excellent diversification benefit';
        } elseif ($statistics['diversification_ratio'] >= 1.2) {
            $interpretations['diversification'] = 'Good diversification benefit';
        } else {
            $interpretations['diversification'] = 'Limited diversification - consider broader allocation';
        }

        return [
            'interpretations' => $interpretations,
            'overall_assessment' => $this->generateOverallAssessment($statistics),
        ];
    }

    /**
     * Generate overall portfolio assessment
     */
    private function generateOverallAssessment(array $statistics): string
    {
        $score = 0;

        if ($statistics['sharpe_ratio'] >= 1.0) {
            $score += 40;
        } elseif ($statistics['sharpe_ratio'] >= 0.5) {
            $score += 30;
        } elseif ($statistics['sharpe_ratio'] >= 0) {
            $score += 15;
        }

        if ($statistics['diversification_ratio'] >= 1.3) {
            $score += 30;
        } elseif ($statistics['diversification_ratio'] >= 1.1) {
            $score += 20;
        }

        $returnToRiskRatio = $statistics['expected_return'] / max(0.1, $statistics['volatility']);
        if ($returnToRiskRatio >= 0.5) {
            $score += 30;
        } elseif ($returnToRiskRatio >= 0.3) {
            $score += 20;
        }

        if ($score >= 80) {
            return 'Excellent portfolio - well-optimized with strong risk-adjusted returns';
        }

        if ($score >= 60) {
            return 'Good portfolio - solid performance with room for minor optimization';
        }

        if ($score >= 40) {
            return 'Adequate portfolio - consider optimization to improve efficiency';
        }

        return 'Portfolio needs improvement - rebalancing recommended for better risk-adjusted returns';
    }
}
