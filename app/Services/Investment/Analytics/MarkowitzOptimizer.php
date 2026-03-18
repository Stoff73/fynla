<?php

declare(strict_types=1);

namespace App\Services\Investment\Analytics;

use App\Services\Investment\Utilities\MatrixOperations;
use App\Services\Investment\Utilities\StatisticalFunctions;

/**
 * Markowitz Mean-Variance Portfolio Optimization
 * Implements quadratic programming for optimal portfolio weights
 */
class MarkowitzOptimizer
{
    public function __construct(
        private readonly MatrixOperations $matrix,
        private readonly StatisticalFunctions $stats,
        private readonly CovarianceMatrixCalculator $covCalculator
    ) {}

    /**
     * Find minimum variance portfolio
     * Minimize: w^T * Σ * w
     * Subject to: Σw = 1, w >= 0 (no short selling)
     *
     * @param  array  $expectedReturns  Expected returns for each asset
     * @param  array  $covarianceMatrix  Covariance matrix
     * @param  array  $constraints  ['min_weight' => 0, 'max_weight' => 1, 'sector_limits' => []]
     * @return array ['weights' => array, 'return' => float, 'risk' => float, 'sharpe' => float]
     */
    public function minimumVariance(
        array $expectedReturns,
        array $covarianceMatrix,
        array $constraints = []
    ): array {
        $n = count($expectedReturns);

        // Start with equal weights
        $weights = array_fill(0, $n, 1 / $n);

        // Apply constraints
        $minWeight = $constraints['min_weight'] ?? 0.0;
        $maxWeight = $constraints['max_weight'] ?? 1.0;

        // Use gradient descent to minimize variance
        $learningRate = 0.01;
        $iterations = 1000;
        $tolerance = 1e-6;

        for ($iter = 0; $iter < $iterations; $iter++) {
            $oldWeights = $weights;

            // Calculate gradient: 2 * Σ * w
            $gradient = [];
            for ($i = 0; $i < $n; $i++) {
                $sum = 0;
                for ($j = 0; $j < $n; $j++) {
                    $sum += $covarianceMatrix[$i][$j] * $weights[$j];
                }
                $gradient[$i] = 2 * $sum;
            }

            // Update weights
            for ($i = 0; $i < $n; $i++) {
                $weights[$i] -= $learningRate * $gradient[$i];
                // Apply box constraints
                $weights[$i] = max($minWeight, min($maxWeight, $weights[$i]));
            }

            // Normalize to sum to 1
            $sum = array_sum($weights);
            if ($sum > 0) {
                for ($i = 0; $i < $n; $i++) {
                    $weights[$i] /= $sum;
                }
            }

            // Check convergence
            $change = 0;
            for ($i = 0; $i < $n; $i++) {
                $change += abs($weights[$i] - $oldWeights[$i]);
            }
            if ($change < $tolerance) {
                break;
            }
        }

        // Calculate portfolio metrics
        $portfolioReturn = $this->matrix->dotProduct($weights, $expectedReturns);
        $portfolioVariance = $this->covCalculator->calculatePortfolioVariance($weights, $covarianceMatrix);
        $portfolioRisk = sqrt($portfolioVariance);

        return [
            'weights' => array_map(fn ($w) => round($w, 6), $weights),
            'expected_return' => round($portfolioReturn, 6),
            'expected_risk' => round($portfolioRisk, 6),
            'sharpe_ratio' => null, // Not applicable for min variance
            'optimization_type' => 'minimum_variance',
        ];
    }

    /**
     * Find maximum Sharpe ratio portfolio (tangency portfolio)
     * Maximize: (R_p - R_f) / σ_p
     *
     * @param  array  $expectedReturns  Expected returns for each asset
     * @param  array  $covarianceMatrix  Covariance matrix
     * @param  float  $riskFreeRate  Risk-free rate (e.g., UK Gilts)
     * @param  array  $constraints  Constraints on weights
     * @return array Portfolio with maximum Sharpe ratio
     */
    public function maximumSharpe(
        array $expectedReturns,
        array $covarianceMatrix,
        float $riskFreeRate = 0.045,
        array $constraints = []
    ): array {
        $n = count($expectedReturns);

        // Calculate excess returns
        $excessReturns = array_map(fn ($r) => $r - $riskFreeRate, $expectedReturns);

        // Start with equal weights
        $weights = array_fill(0, $n, 1 / $n);

        $minWeight = $constraints['min_weight'] ?? 0.0;
        $maxWeight = $constraints['max_weight'] ?? 1.0;

        // Gradient ascent to maximize Sharpe ratio
        $learningRate = 0.01;
        $iterations = 2000;
        $tolerance = 1e-6;
        $bestSharpe = -INF;
        $bestWeights = $weights;

        for ($iter = 0; $iter < $iterations; $iter++) {
            // Calculate current Sharpe ratio
            $portfolioReturn = $this->matrix->dotProduct($weights, $expectedReturns);
            $portfolioVariance = $this->covCalculator->calculatePortfolioVariance($weights, $covarianceMatrix);
            $portfolioRisk = sqrt($portfolioVariance);

            $currentSharpe = $portfolioRisk > 0 ? ($portfolioReturn - $riskFreeRate) / $portfolioRisk : 0;

            // Track best solution
            if ($currentSharpe > $bestSharpe) {
                $bestSharpe = $currentSharpe;
                $bestWeights = $weights;
            }

            // Calculate gradient (simplified)
            $gradient = [];
            $excessReturn = $portfolioReturn - $riskFreeRate;

            for ($i = 0; $i < $n; $i++) {
                // Marginal contribution to return
                $marginalReturn = $excessReturns[$i];

                // Marginal contribution to risk
                $marginalRisk = 0;
                for ($j = 0; $j < $n; $j++) {
                    $marginalRisk += $covarianceMatrix[$i][$j] * $weights[$j];
                }
                $marginalRisk = $portfolioRisk > 0 ? $marginalRisk / $portfolioRisk : 0;

                // Gradient of Sharpe ratio
                $gradient[$i] = ($marginalReturn * $portfolioRisk - $excessReturn * $marginalRisk) /
                    ($portfolioRisk * $portfolioRisk + 1e-8);
            }

            // Update weights
            $oldWeights = $weights;
            for ($i = 0; $i < $n; $i++) {
                $weights[$i] += $learningRate * $gradient[$i];
                $weights[$i] = max($minWeight, min($maxWeight, $weights[$i]));
            }

            // Normalize
            $sum = array_sum($weights);
            if ($sum > 0) {
                for ($i = 0; $i < $n; $i++) {
                    $weights[$i] /= $sum;
                }
            }

            // Check convergence
            $change = 0;
            for ($i = 0; $i < $n; $i++) {
                $change += abs($weights[$i] - $oldWeights[$i]);
            }
            if ($change < $tolerance) {
                break;
            }
        }

        // Use best weights found
        $weights = $bestWeights;

        // Calculate final metrics
        $portfolioReturn = $this->matrix->dotProduct($weights, $expectedReturns);
        $portfolioVariance = $this->covCalculator->calculatePortfolioVariance($weights, $covarianceMatrix);
        $portfolioRisk = sqrt($portfolioVariance);
        $sharpe = $portfolioRisk > 0 ? ($portfolioReturn - $riskFreeRate) / $portfolioRisk : 0;

        return [
            'weights' => array_map(fn ($w) => round($w, 6), $weights),
            'expected_return' => round($portfolioReturn, 6),
            'expected_risk' => round($portfolioRisk, 6),
            'sharpe_ratio' => round($sharpe, 4),
            'optimization_type' => 'maximum_sharpe',
            'risk_free_rate' => $riskFreeRate,
        ];
    }

    /**
     * Find portfolio for target return
     * Minimize: w^T * Σ * w
     * Subject to: w^T * R = R_target, Σw = 1, w >= 0
     *
     * @param  array  $expectedReturns  Expected returns
     * @param  array  $covarianceMatrix  Covariance matrix
     * @param  float  $targetReturn  Desired return level
     * @param  array  $constraints  Constraints
     * @return array Portfolio achieving target return with minimum risk
     */
    public function targetReturn(
        array $expectedReturns,
        array $covarianceMatrix,
        float $targetReturn,
        array $constraints = []
    ): array {
        $n = count($expectedReturns);

        // Start with equal weights
        $weights = array_fill(0, $n, 1 / $n);

        $minWeight = $constraints['min_weight'] ?? 0.0;
        $maxWeight = $constraints['max_weight'] ?? 1.0;

        // Dual gradient descent: minimize risk while achieving target return
        $learningRate = 0.01;
        $iterations = 2000;
        $penalty = 1000; // Penalty for deviating from target return

        for ($iter = 0; $iter < $iterations; $iter++) {
            // Calculate current portfolio return
            $portfolioReturn = $this->matrix->dotProduct($weights, $expectedReturns);

            // Gradient for variance
            $gradientRisk = [];
            for ($i = 0; $i < $n; $i++) {
                $sum = 0;
                for ($j = 0; $j < $n; $j++) {
                    $sum += $covarianceMatrix[$i][$j] * $weights[$j];
                }
                $gradientRisk[$i] = 2 * $sum;
            }

            // Gradient for return constraint (penalty method)
            $returnError = $portfolioReturn - $targetReturn;
            $gradientReturn = array_map(fn ($r) => 2 * $penalty * $returnError * $r, $expectedReturns);

            // Combined gradient
            $oldWeights = $weights;
            for ($i = 0; $i < $n; $i++) {
                $weights[$i] -= $learningRate * ($gradientRisk[$i] + $gradientReturn[$i]);
                $weights[$i] = max($minWeight, min($maxWeight, $weights[$i]));
            }

            // Normalize
            $sum = array_sum($weights);
            if ($sum > 0) {
                for ($i = 0; $i < $n; $i++) {
                    $weights[$i] /= $sum;
                }
            }

            // Check convergence
            $change = 0;
            for ($i = 0; $i < $n; $i++) {
                $change += abs($weights[$i] - $oldWeights[$i]);
            }
            if ($change < 1e-6 && abs($returnError) < 0.001) {
                break;
            }
        }

        // Calculate final metrics
        $portfolioReturn = $this->matrix->dotProduct($weights, $expectedReturns);
        $portfolioVariance = $this->covCalculator->calculatePortfolioVariance($weights, $covarianceMatrix);
        $portfolioRisk = sqrt($portfolioVariance);

        return [
            'weights' => array_map(fn ($w) => round($w, 6), $weights),
            'expected_return' => round($portfolioReturn, 6),
            'expected_risk' => round($portfolioRisk, 6),
            'sharpe_ratio' => null,
            'optimization_type' => 'target_return',
            'target_return' => $targetReturn,
        ];
    }

    /**
     * Risk parity portfolio
     * Each asset contributes equally to portfolio risk
     * Weight inversely proportional to volatility
     *
     * @param  array  $volatilities  Individual asset volatilities (standard deviations)
     * @param  array  $expectedReturns  Expected returns (for metrics calculation)
     * @param  array  $covarianceMatrix  Covariance matrix (for metrics calculation)
     * @return array Risk parity portfolio
     */
    public function riskParity(
        array $volatilities,
        array $expectedReturns,
        array $covarianceMatrix
    ): array {
        $n = count($volatilities);

        // Weight inversely proportional to volatility
        $inverseVols = array_map(fn ($v) => $v > 0 ? 1 / $v : 0, $volatilities);
        $sumInverseVols = array_sum($inverseVols);

        $weights = [];
        for ($i = 0; $i < $n; $i++) {
            $weights[$i] = $sumInverseVols > 0 ? $inverseVols[$i] / $sumInverseVols : 0;
        }

        // Calculate portfolio metrics
        $portfolioReturn = $this->matrix->dotProduct($weights, $expectedReturns);
        $portfolioVariance = $this->covCalculator->calculatePortfolioVariance($weights, $covarianceMatrix);
        $portfolioRisk = sqrt($portfolioVariance);

        return [
            'weights' => array_map(fn ($w) => round($w, 6), $weights),
            'expected_return' => round($portfolioReturn, 6),
            'expected_risk' => round($portfolioRisk, 6),
            'sharpe_ratio' => null,
            'optimization_type' => 'risk_parity',
        ];
    }

    /**
     * Equal weight portfolio (1/N rule)
     * Simple diversification strategy
     *
     * @param  int  $n  Number of assets
     * @param  array  $expectedReturns  Expected returns
     * @param  array  $covarianceMatrix  Covariance matrix
     * @return array Equal weight portfolio
     */
    public function equalWeight(
        int $n,
        array $expectedReturns,
        array $covarianceMatrix
    ): array {
        $weights = array_fill(0, $n, 1 / $n);

        // Calculate portfolio metrics
        $portfolioReturn = $this->matrix->dotProduct($weights, $expectedReturns);
        $portfolioVariance = $this->covCalculator->calculatePortfolioVariance($weights, $covarianceMatrix);
        $portfolioRisk = sqrt($portfolioVariance);

        return [
            'weights' => array_map(fn ($w) => round($w, 6), $weights),
            'expected_return' => round($portfolioReturn, 6),
            'expected_risk' => round($portfolioRisk, 6),
            'sharpe_ratio' => null,
            'optimization_type' => 'equal_weight',
        ];
    }

    /**
     * Calculate portfolio metrics for given weights
     *
     * @param  array  $weights  Portfolio weights
     * @param  array  $expectedReturns  Expected returns
     * @param  array  $covarianceMatrix  Covariance matrix
     * @param  float  $riskFreeRate  Risk-free rate
     * @return array Portfolio metrics
     */
    public function calculateMetrics(
        array $weights,
        array $expectedReturns,
        array $covarianceMatrix,
        float $riskFreeRate = 0.045
    ): array {
        $portfolioReturn = $this->matrix->dotProduct($weights, $expectedReturns);
        $portfolioVariance = $this->covCalculator->calculatePortfolioVariance($weights, $covarianceMatrix);
        $portfolioRisk = sqrt($portfolioVariance);
        $sharpe = $portfolioRisk > 0 ? ($portfolioReturn - $riskFreeRate) / $portfolioRisk : 0;

        return [
            'expected_return' => round($portfolioReturn, 6),
            'expected_risk' => round($portfolioRisk, 6),
            'expected_variance' => round($portfolioVariance, 8),
            'sharpe_ratio' => round($sharpe, 4),
        ];
    }
}
