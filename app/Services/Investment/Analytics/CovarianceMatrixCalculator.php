<?php

declare(strict_types=1);

namespace App\Services\Investment\Analytics;

use App\Services\Investment\Utilities\StatisticalFunctions;
use Illuminate\Support\Collection;

/**
 * Calculate covariance matrix for portfolio optimization
 * Used as input to Markowitz mean-variance optimization
 */
class CovarianceMatrixCalculator
{
    public function __construct(
        private StatisticalFunctions $stats
    ) {}

    /**
     * Calculate covariance matrix from returns data
     *
     * @param  Collection  $holdings  Holdings with historical returns
     * @return array ['matrix' => 2D array, 'labels' => holding names, 'volatilities' => array of std devs]
     */
    public function calculate(Collection $holdings): array
    {
        if ($holdings->isEmpty()) {
            return [
                'matrix' => [],
                'labels' => [],
                'volatilities' => [],
            ];
        }

        // Extract returns arrays for each holding
        $returnsData = [];
        $labels = [];

        foreach ($holdings as $holding) {
            $labels[] = $holding->asset_name ?? $holding->ticker_symbol ?? 'Unknown';
            // Note: Using mock returns until historical data API integration implemented
            $returnsData[] = $holding->historical_returns ?? $this->generateMockReturns();
        }

        $n = count($returnsData);
        $matrix = [];
        $volatilities = [];

        // Calculate covariance for each pair
        for ($i = 0; $i < $n; $i++) {
            $matrix[$i] = [];
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    // Diagonal is variance
                    $variance = $this->stats->variance($returnsData[$i]);
                    $matrix[$i][$j] = $variance;
                    $volatilities[$i] = sqrt($variance); // Standard deviation
                } else {
                    $matrix[$i][$j] = $this->stats->covariance(
                        $returnsData[$i],
                        $returnsData[$j]
                    );
                }
            }
        }

        return [
            'matrix' => $matrix,
            'labels' => $labels,
            'volatilities' => $volatilities,
        ];
    }

    /**
     * Calculate portfolio variance given weights and covariance matrix
     * Portfolio Variance = w^T * Σ * w
     *
     * @param  array  $weights  Portfolio weights (must sum to 1)
     * @param  array  $covarianceMatrix  Covariance matrix
     * @return float Portfolio variance
     */
    public function calculatePortfolioVariance(array $weights, array $covarianceMatrix): float
    {
        $n = count($weights);
        $variance = 0;

        // Calculate w^T * Σ * w
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $variance += $weights[$i] * $weights[$j] * $covarianceMatrix[$i][$j];
            }
        }

        return max(0, $variance); // Ensure non-negative
    }

    /**
     * Calculate portfolio standard deviation (volatility)
     *
     * @param  array  $weights  Portfolio weights
     * @param  array  $covarianceMatrix  Covariance matrix
     * @return float Portfolio standard deviation
     */
    public function calculatePortfolioVolatility(array $weights, array $covarianceMatrix): float
    {
        $variance = $this->calculatePortfolioVariance($weights, $covarianceMatrix);

        return sqrt($variance);
    }

    /**
     * Calculate diversification benefit
     * Compares weighted sum of individual volatilities vs. portfolio volatility
     *
     * @param  array  $weights  Portfolio weights
     * @param  array  $volatilities  Individual asset volatilities
     * @param  float  $portfolioVolatility  Portfolio volatility
     * @return array ['diversification_ratio' => float, 'benefit' => float]
     */
    public function calculateDiversificationBenefit(
        array $weights,
        array $volatilities,
        float $portfolioVolatility
    ): array {
        // Weighted sum of individual volatilities
        $weightedVolatility = 0;
        $n = count($weights);

        for ($i = 0; $i < $n; $i++) {
            $weightedVolatility += $weights[$i] * $volatilities[$i];
        }

        // Diversification ratio: weighted vol / portfolio vol
        // Ratio > 1 indicates diversification benefit
        $ratio = $portfolioVolatility > 0 ? $weightedVolatility / $portfolioVolatility : 1.0;

        // Reduction in risk due to diversification
        $benefit = $weightedVolatility - $portfolioVolatility;
        $benefitPercent = $weightedVolatility > 0 ? ($benefit / $weightedVolatility) * 100 : 0;

        return [
            'diversification_ratio' => round($ratio, 4),
            'risk_reduction' => round($benefit, 6),
            'risk_reduction_percent' => round($benefitPercent, 2),
            'interpretation' => $ratio > 1.5 ? 'Excellent diversification' :
                ($ratio > 1.2 ? 'Good diversification' :
                    ($ratio > 1.0 ? 'Moderate diversification' : 'Limited diversification')),
        ];
    }

    /**
     * Annualize covariance matrix (from monthly to annual)
     *
     * @param  array  $monthlyCovariance  Monthly covariance matrix
     * @return array Annualized covariance matrix
     */
    public function annualize(array $monthlyCovariance): array
    {
        $n = count($monthlyCovariance);
        $annualized = [];

        for ($i = 0; $i < $n; $i++) {
            $annualized[$i] = [];
            for ($j = 0; $j < $n; $j++) {
                $annualized[$i][$j] = $monthlyCovariance[$i][$j] * 12; // Multiply by 12 for annual
            }
        }

        return $annualized;
    }

    /**
     * Calculate marginal contribution to risk for each asset
     * Shows how much each asset contributes to overall portfolio risk
     *
     * @param  array  $weights  Portfolio weights
     * @param  array  $covarianceMatrix  Covariance matrix
     * @param  float  $portfolioVolatility  Portfolio volatility
     * @return array Marginal risk contribution per asset
     */
    public function calculateMarginalRisk(
        array $weights,
        array $covarianceMatrix,
        float $portfolioVolatility
    ): array {
        $n = count($weights);
        $marginalRisk = [];

        if ($portfolioVolatility == 0) {
            return array_fill(0, $n, 0);
        }

        // MCR_i = (Σ * w)_i / σ_p
        for ($i = 0; $i < $n; $i++) {
            $sum = 0;
            for ($j = 0; $j < $n; $j++) {
                $sum += $covarianceMatrix[$i][$j] * $weights[$j];
            }
            $marginalRisk[$i] = $sum / $portfolioVolatility;
        }

        return $marginalRisk;
    }

    /**
     * Generate simulated returns as fallback when historical data is unavailable.
     *
     * Used for demo/preview users and when market data integration is not configured.
     * Returns are randomly distributed between -10% and +20% to approximate
     * typical equity market volatility.
     *
     * @param  int  $periods  Number of periods to simulate
     * @return array Simulated return data
     */
    private function generateMockReturns(int $periods = 36): array
    {
        $returns = [];
        for ($i = 0; $i < $periods; $i++) {
            $returns[] = (rand(-100, 200) / 1000); // Random returns between -10% and +20%
        }

        return $returns;
    }
}
