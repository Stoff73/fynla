<?php

declare(strict_types=1);

namespace App\Services\Investment\Analytics;

use App\Models\Investment\InvestmentAccount;
use App\Services\Investment\Utilities\MatrixOperations;
use App\Services\Investment\Utilities\StatisticalFunctions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Efficient Frontier Calculator
 * Implements Modern Portfolio Theory (MPT) calculations
 *
 * Consolidated from:
 * - App\Services\Investment\Analytics\EfficientFrontierCalculator (user holdings-based)
 * - App\Services\Investment\EfficientFrontier\EfficientFrontierCalculator (asset class-based)
 *
 * Supports two modes:
 * 1. User Portfolio Mode: Analyzes actual user holdings with DI services
 * 2. Asset Class Mode: Generic efficient frontier using asset class assumptions
 */
class EfficientFrontierCalculator
{
    public function __construct(
        private MarkowitzOptimizer $optimizer,
        private CovarianceMatrixCalculator $covCalculator,
        private CorrelationMatrixCalculator $corrCalculator,
        private MatrixOperations $matrix,
        private StatisticalFunctions $stats
    ) {}

    // =========================================================================
    // USER PORTFOLIO MODE (for actual user holdings analysis)
    // =========================================================================

    /**
     * Calculate efficient frontier for user's portfolio
     *
     * @param  int  $userId  User ID
     * @param  float  $riskFreeRate  Current risk-free rate (UK Gilts)
     * @param  int  $numPoints  Number of points to calculate on frontier
     * @return array Complete efficient frontier analysis
     */
    public function calculate(
        int $userId,
        float $riskFreeRate = 0.045,
        int $numPoints = 50
    ): array {
        // Get user's holdings
        $accounts = InvestmentAccount::where('user_id', $userId)
            ->with('holdings')
            ->get();

        if ($accounts->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No investment accounts found',
            ];
        }

        $holdings = $accounts->flatMap->holdings;

        if ($holdings->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No holdings found in investment accounts',
            ];
        }

        // Extract data from holdings
        $holdingsData = $this->extractHoldingsData($holdings);
        $expectedReturns = $holdingsData['expected_returns'];
        $labels = $holdingsData['labels'];

        // Calculate covariance matrix
        $covData = $this->covCalculator->calculate($holdings);
        $covarianceMatrix = $covData['matrix'];
        $volatilities = $covData['volatilities'];

        // Calculate current portfolio position
        $currentWeights = $this->calculateCurrentWeights($holdings);
        $currentMetrics = $this->optimizer->calculateMetrics(
            $currentWeights,
            $expectedReturns,
            $covarianceMatrix,
            $riskFreeRate
        );

        // Find key portfolios
        $minVariancePortfolio = $this->optimizer->minimumVariance(
            $expectedReturns,
            $covarianceMatrix
        );

        $tangencyPortfolio = $this->optimizer->maximumSharpe(
            $expectedReturns,
            $covarianceMatrix,
            $riskFreeRate
        );

        // Generate frontier points
        $frontierPoints = $this->generateFrontierPoints(
            $expectedReturns,
            $covarianceMatrix,
            $minVariancePortfolio,
            $tangencyPortfolio,
            $numPoints
        );

        // Calculate Capital Allocation Line (CAL)
        $cal = $this->calculateCapitalAllocationLine(
            $tangencyPortfolio,
            $riskFreeRate
        );

        // Correlation and diversification analysis
        $corrData = $this->corrCalculator->calculate($holdings);
        $diversification = $this->covCalculator->calculateDiversificationBenefit(
            $currentWeights,
            $volatilities,
            $currentMetrics['expected_risk']
        );

        return [
            'success' => true,
            'calculation_date' => now()->toDateString(),
            'risk_free_rate' => $riskFreeRate,
            'holdings_count' => count($expectedReturns),
            'holdings_labels' => $labels,

            // Current portfolio
            'current_portfolio' => [
                'weights' => array_map(fn ($w) => round($w, 6), $currentWeights),
                'expected_return' => $currentMetrics['expected_return'],
                'expected_risk' => $currentMetrics['expected_risk'],
                'sharpe_ratio' => $currentMetrics['sharpe_ratio'],
            ],

            // Optimal portfolios
            'minimum_variance_portfolio' => $minVariancePortfolio,
            'tangency_portfolio' => $tangencyPortfolio,

            // Efficient frontier
            'frontier_points' => $frontierPoints,

            // Capital Allocation Line
            'capital_allocation_line' => $cal,

            // Diversification
            'diversification' => $diversification,

            // Correlation summary
            'correlation_summary' => $corrData['statistics'],

            // Improvement opportunities
            'improvement_opportunities' => $this->analyzeImprovementOpportunities(
                $currentMetrics,
                $tangencyPortfolio,
                $minVariancePortfolio
            ),
        ];
    }

    // =========================================================================
    // ASSET CLASS MODE (for generic efficient frontier calculations)
    // =========================================================================

    /**
     * Calculate efficient frontier for given asset classes
     *
     * @param  array  $assetClasses  Asset class data with returns, volatility, correlations
     * @param  int  $numPortfolios  Number of portfolios to generate (default 100)
     * @param  float  $riskFreeRate  Risk-free rate (default 0.04 = 4%)
     * @return array Efficient frontier data
     */
    public function calculateEfficientFrontier(
        array $assetClasses,
        int $numPortfolios = 100,
        float $riskFreeRate = 0.04
    ): array {
        // Validate inputs
        if (count($assetClasses) < 2) {
            return [
                'success' => false,
                'message' => 'At least 2 asset classes required',
            ];
        }

        // Generate random portfolio allocations
        $portfolios = $this->generateRandomPortfolios($assetClasses, $numPortfolios);

        // Calculate risk/return for each portfolio
        $portfolioStats = [];
        foreach ($portfolios as $portfolio) {
            $stats = $this->calculatePortfolioStatistics($portfolio, $assetClasses);
            $stats['sharpe_ratio'] = $this->calculateSharpeRatio(
                $stats['expected_return'],
                $stats['volatility'],
                $riskFreeRate
            );
            $stats['allocation'] = $portfolio['weights'];
            $portfolioStats[] = $stats;
        }

        // Find key portfolios
        $maxSharpePortfolio = $this->findMaxSharpePortfolio($portfolioStats);
        $minVariancePortfolio = $this->findMinVariancePortfolio($portfolioStats);

        // Calculate efficient frontier curve (pareto optimal portfolios)
        $efficientPortfolios = $this->extractEfficientPortfolios($portfolioStats);

        return [
            'success' => true,
            'efficient_frontier' => $efficientPortfolios,
            'all_portfolios' => $portfolioStats,
            'max_sharpe_portfolio' => $maxSharpePortfolio,
            'min_variance_portfolio' => $minVariancePortfolio,
            'risk_free_rate' => $riskFreeRate,
            'num_portfolios' => count($portfolioStats),
            'asset_classes' => array_keys($assetClasses),
        ];
    }

    /**
     * Calculate optimal portfolio for target return
     *
     * @param  array  $assetClasses  Asset class data
     * @param  float  $targetReturn  Target annual return
     * @param  float  $riskFreeRate  Risk-free rate
     * @return array Optimal portfolio allocation
     */
    public function calculateOptimalPortfolio(
        array $assetClasses,
        float $targetReturn,
        float $riskFreeRate = 0.04
    ): array {
        // Generate efficient frontier
        $frontier = $this->calculateEfficientFrontier($assetClasses, 500, $riskFreeRate);

        if (! $frontier['success']) {
            return $frontier;
        }

        // Find portfolio closest to target return on efficient frontier
        $optimalPortfolio = null;
        $minDifference = PHP_FLOAT_MAX;

        foreach ($frontier['efficient_frontier'] as $portfolio) {
            $difference = abs($portfolio['expected_return'] - $targetReturn);
            if ($difference < $minDifference) {
                $minDifference = $difference;
                $optimalPortfolio = $portfolio;
            }
        }

        if (! $optimalPortfolio) {
            return [
                'success' => false,
                'message' => 'Could not find optimal portfolio for target return',
            ];
        }

        return [
            'success' => true,
            'optimal_portfolio' => $optimalPortfolio,
            'target_return' => $targetReturn,
            'achieved_return' => $optimalPortfolio['expected_return'],
            'volatility' => $optimalPortfolio['volatility'],
            'sharpe_ratio' => $optimalPortfolio['sharpe_ratio'],
            'allocation' => $optimalPortfolio['allocation'],
        ];
    }

    /**
     * Calculate optimal portfolio for target risk level
     *
     * @param  array  $assetClasses  Asset class data
     * @param  float  $targetVolatility  Target volatility (standard deviation)
     * @param  float  $riskFreeRate  Risk-free rate
     * @return array Optimal portfolio allocation
     */
    public function calculateOptimalPortfolioByRisk(
        array $assetClasses,
        float $targetVolatility,
        float $riskFreeRate = 0.04
    ): array {
        // Generate efficient frontier
        $frontier = $this->calculateEfficientFrontier($assetClasses, 500, $riskFreeRate);

        if (! $frontier['success']) {
            return $frontier;
        }

        // Find portfolio closest to target volatility on efficient frontier
        $optimalPortfolio = null;
        $minDifference = PHP_FLOAT_MAX;

        foreach ($frontier['efficient_frontier'] as $portfolio) {
            $difference = abs($portfolio['volatility'] - $targetVolatility);
            if ($difference < $minDifference) {
                $minDifference = $difference;
                $optimalPortfolio = $portfolio;
            }
        }

        if (! $optimalPortfolio) {
            return [
                'success' => false,
                'message' => 'Could not find optimal portfolio for target risk',
            ];
        }

        return [
            'success' => true,
            'optimal_portfolio' => $optimalPortfolio,
            'target_volatility' => $targetVolatility,
            'achieved_volatility' => $optimalPortfolio['volatility'],
            'expected_return' => $optimalPortfolio['expected_return'],
            'sharpe_ratio' => $optimalPortfolio['sharpe_ratio'],
            'allocation' => $optimalPortfolio['allocation'],
        ];
    }

    /**
     * Compare current portfolio with efficient frontier
     *
     * @param  array  $currentAllocation  Current portfolio allocation
     * @param  array  $assetClasses  Asset class data
     * @param  float  $riskFreeRate  Risk-free rate
     * @return array Comparison analysis
     */
    public function compareWithEfficientFrontier(
        array $currentAllocation,
        array $assetClasses,
        float $riskFreeRate = 0.04
    ): array {
        // Calculate current portfolio statistics
        $currentStats = $this->calculatePortfolioStatistics(
            ['weights' => $currentAllocation],
            $assetClasses
        );
        $currentStats['sharpe_ratio'] = $this->calculateSharpeRatio(
            $currentStats['expected_return'],
            $currentStats['volatility'],
            $riskFreeRate
        );

        // Generate efficient frontier
        $frontier = $this->calculateEfficientFrontier($assetClasses, 500, $riskFreeRate);

        if (! $frontier['success']) {
            return $frontier;
        }

        // Find nearest efficient portfolio with same risk
        $nearestEfficientPortfolio = null;
        $minDifference = PHP_FLOAT_MAX;

        foreach ($frontier['efficient_frontier'] as $portfolio) {
            $difference = abs($portfolio['volatility'] - $currentStats['volatility']);
            if ($difference < $minDifference) {
                $minDifference = $difference;
                $nearestEfficientPortfolio = $portfolio;
            }
        }

        // Calculate efficiency score (0-100)
        $efficiencyScore = $this->calculateEfficiencyScore(
            $currentStats,
            $nearestEfficientPortfolio,
            $frontier['max_sharpe_portfolio']
        );

        return [
            'success' => true,
            'current_portfolio' => [
                'expected_return' => $currentStats['expected_return'],
                'volatility' => $currentStats['volatility'],
                'sharpe_ratio' => $currentStats['sharpe_ratio'],
                'allocation' => $currentAllocation,
            ],
            'nearest_efficient_portfolio' => $nearestEfficientPortfolio,
            'max_sharpe_portfolio' => $frontier['max_sharpe_portfolio'],
            'efficiency_score' => $efficiencyScore,
            'improvement_potential' => [
                'return_increase' => $nearestEfficientPortfolio['expected_return'] - $currentStats['expected_return'],
                'risk_reduction' => $currentStats['volatility'] - $nearestEfficientPortfolio['volatility'],
                'sharpe_improvement' => $nearestEfficientPortfolio['sharpe_ratio'] - $currentStats['sharpe_ratio'],
            ],
            'recommendation' => $this->generateEfficiencyRecommendation($efficiencyScore),
        ];
    }

    // =========================================================================
    // USER PORTFOLIO HELPER METHODS
    // =========================================================================

    /**
     * Extract expected returns and labels from holdings
     */
    private function extractHoldingsData(Collection $holdings): array
    {
        $expectedReturns = [];
        $labels = [];

        foreach ($holdings as $holding) {
            $historicalReturns = $holding->historical_returns ?? $this->generateMockReturns();
            $expectedReturns[] = $this->stats->mean($historicalReturns);
            $labels[] = $holding->asset_name ?? $holding->ticker_symbol ?? 'Unknown';
        }

        return [
            'expected_returns' => $expectedReturns,
            'labels' => $labels,
        ];
    }

    /**
     * Calculate current portfolio weights
     */
    private function calculateCurrentWeights(Collection $holdings): array
    {
        $totalValue = $holdings->sum('current_value');

        if ($totalValue == 0) {
            $n = $holdings->count();

            return $n > 0 ? array_fill(0, $n, 1 / $n) : [];
        }

        $weights = [];
        foreach ($holdings as $holding) {
            $weights[] = $holding->current_value / $totalValue;
        }

        return $weights;
    }

    /**
     * Generate points along the efficient frontier
     */
    private function generateFrontierPoints(
        array $expectedReturns,
        array $covarianceMatrix,
        array $minVariancePortfolio,
        array $tangencyPortfolio,
        int $numPoints
    ): array {
        $minReturn = $minVariancePortfolio['expected_return'];
        $maxReturn = max($expectedReturns);

        $points = [];

        for ($i = 0; $i < $numPoints; $i++) {
            $targetReturn = $minReturn + ($maxReturn - $minReturn) * ($i / ($numPoints - 1));

            try {
                $portfolio = $this->optimizer->targetReturn(
                    $expectedReturns,
                    $covarianceMatrix,
                    $targetReturn
                );

                $points[] = [
                    'return' => $portfolio['expected_return'],
                    'risk' => $portfolio['expected_risk'],
                    'sharpe' => isset($portfolio['sharpe_ratio']) ? $portfolio['sharpe_ratio'] : null,
                ];
            } catch (\Exception $e) {
                Log::warning('Failed to calculate frontier point', [
                    'target_return' => $targetReturn,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        return $points;
    }

    /**
     * Calculate Capital Allocation Line
     */
    private function calculateCapitalAllocationLine(
        array $tangencyPortfolio,
        float $riskFreeRate
    ): array {
        $sharpe = $tangencyPortfolio['sharpe_ratio'];
        $tangencyRisk = $tangencyPortfolio['expected_risk'];

        $calPoints = [];
        $maxRisk = $tangencyRisk * 2;

        for ($i = 0; $i <= 10; $i++) {
            $risk = ($maxRisk / 10) * $i;
            $return = $riskFreeRate + $sharpe * $risk;

            $calPoints[] = [
                'risk' => round($risk, 6),
                'return' => round($return, 6),
            ];
        }

        return [
            'slope' => $sharpe,
            'intercept' => $riskFreeRate,
            'equation' => "R_p = {$riskFreeRate} + {$sharpe} * sigma_p",
            'points' => $calPoints,
        ];
    }

    /**
     * Analyze improvement opportunities vs. current portfolio
     */
    private function analyzeImprovementOpportunities(
        array $currentMetrics,
        array $tangencyPortfolio,
        array $minVariancePortfolio
    ): array {
        $currentSharpe = $currentMetrics['sharpe_ratio'];
        $currentRisk = $currentMetrics['expected_risk'];
        $currentReturn = $currentMetrics['expected_return'];

        $optimalSharpe = $tangencyPortfolio['sharpe_ratio'];
        $optimalRisk = $tangencyPortfolio['expected_risk'];
        $optimalReturn = $tangencyPortfolio['expected_return'];

        $minRisk = $minVariancePortfolio['expected_risk'];

        $sharpeImprovement = $optimalSharpe - $currentSharpe;
        $sharpeImprovementPercent = $currentSharpe != 0 ? ($sharpeImprovement / abs($currentSharpe)) * 100 : 0;

        $returnImprovement = $optimalReturn - $currentReturn;
        $riskReduction = $currentRisk - $optimalRisk;
        $maxRiskReduction = $currentRisk - $minRisk;

        return [
            'sharpe_improvement' => round($sharpeImprovement, 4),
            'sharpe_improvement_percent' => round($sharpeImprovementPercent, 2),
            'potential_return_increase' => round($returnImprovement, 6),
            'potential_risk_reduction' => round($riskReduction, 6),
            'max_risk_reduction_possible' => round($maxRiskReduction, 6),
            'recommendation' => $this->generateRecommendation(
                $currentSharpe,
                $optimalSharpe,
                $currentRisk,
                $minRisk
            ),
        ];
    }

    /**
     * Generate recommendation text for user portfolio
     */
    private function generateRecommendation(
        float $currentSharpe,
        float $optimalSharpe,
        float $currentRisk,
        float $minRisk
    ): string {
        $sharpeGap = $optimalSharpe - $currentSharpe;
        $riskGap = $currentRisk - $minRisk;

        if ($sharpeGap > 0.3) {
            return 'Significant opportunity to improve risk-adjusted returns. Consider rebalancing towards the optimal portfolio.';
        } elseif ($sharpeGap > 0.1) {
            return 'Moderate opportunity for improvement. Rebalancing could enhance risk-adjusted returns.';
        } elseif ($riskGap > 0.05) {
            return 'Your portfolio has higher risk than necessary. Consider reducing risk through better diversification.';
        } else {
            return 'Your portfolio is reasonably well-optimized. Minor adjustments could provide marginal improvements.';
        }
    }

    /**
     * Generate simulated returns as fallback
     */
    private function generateMockReturns(int $periods = 36): array
    {
        $returns = [];
        for ($i = 0; $i < $periods; $i++) {
            $returns[] = (rand(-100, 200) / 1000);
        }

        return $returns;
    }

    // =========================================================================
    // ASSET CLASS MODE HELPER METHODS
    // =========================================================================

    /**
     * Generate random portfolio allocations
     */
    private function generateRandomPortfolios(array $assetClasses, int $numPortfolios): array
    {
        $portfolios = [];
        $assetNames = array_keys($assetClasses);
        $numAssets = count($assetNames);

        for ($i = 0; $i < $numPortfolios; $i++) {
            $weights = [];
            $sum = 0;

            for ($j = 0; $j < $numAssets; $j++) {
                $weight = mt_rand(0, 100) / 100;
                $weights[$assetNames[$j]] = $weight;
                $sum += $weight;
            }

            foreach ($weights as $asset => $weight) {
                $weights[$asset] = $weight / $sum;
            }

            $portfolios[] = ['weights' => $weights];
        }

        return $portfolios;
    }

    /**
     * Calculate portfolio statistics (return, volatility)
     */
    private function calculatePortfolioStatistics(array $portfolio, array $assetClasses): array
    {
        $weights = $portfolio['weights'];

        $expectedReturn = 0.0;
        foreach ($weights as $asset => $weight) {
            $expectedReturn += $weight * $assetClasses[$asset]['expected_return'];
        }

        $variance = 0.0;

        foreach ($weights as $asset1 => $weight1) {
            foreach ($weights as $asset2 => $weight2) {
                $volatility1 = $assetClasses[$asset1]['volatility'];
                $volatility2 = $assetClasses[$asset2]['volatility'];
                $correlation = $this->getCorrelation($asset1, $asset2, $assetClasses);
                $variance += $weight1 * $weight2 * $volatility1 * $volatility2 * $correlation;
            }
        }

        $volatility = sqrt($variance);

        return [
            'expected_return' => $expectedReturn * 100,
            'volatility' => $volatility * 100,
        ];
    }

    /**
     * Get correlation between two assets
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
     * Calculate Sharpe ratio
     */
    private function calculateSharpeRatio(float $return, float $volatility, float $riskFreeRate): float
    {
        if ($volatility <= 0) {
            return 0.0;
        }

        return ($return - ($riskFreeRate * 100)) / $volatility;
    }

    /**
     * Find portfolio with maximum Sharpe ratio
     */
    private function findMaxSharpePortfolio(array $portfolios): array
    {
        $maxSharpe = -PHP_FLOAT_MAX;
        $maxSharpePortfolio = null;

        foreach ($portfolios as $portfolio) {
            if ($portfolio['sharpe_ratio'] > $maxSharpe) {
                $maxSharpe = $portfolio['sharpe_ratio'];
                $maxSharpePortfolio = $portfolio;
            }
        }

        return $maxSharpePortfolio;
    }

    /**
     * Find portfolio with minimum variance
     */
    private function findMinVariancePortfolio(array $portfolios): array
    {
        $minVolatility = PHP_FLOAT_MAX;
        $minVariancePortfolio = null;

        foreach ($portfolios as $portfolio) {
            if ($portfolio['volatility'] < $minVolatility) {
                $minVolatility = $portfolio['volatility'];
                $minVariancePortfolio = $portfolio;
            }
        }

        return $minVariancePortfolio;
    }

    /**
     * Extract efficient frontier (pareto optimal portfolios)
     */
    private function extractEfficientPortfolios(array $portfolios): array
    {
        usort($portfolios, fn ($a, $b) => $a['volatility'] <=> $b['volatility']);

        $efficientPortfolios = [];
        $maxReturnSoFar = -PHP_FLOAT_MAX;

        foreach ($portfolios as $portfolio) {
            if ($portfolio['expected_return'] > $maxReturnSoFar) {
                $efficientPortfolios[] = $portfolio;
                $maxReturnSoFar = $portfolio['expected_return'];
            }
        }

        return $efficientPortfolios;
    }

    /**
     * Calculate portfolio efficiency score
     */
    private function calculateEfficiencyScore(
        array $currentStats,
        array $efficientPortfolio,
        array $maxSharpePortfolio
    ): float {
        if ($maxSharpePortfolio['sharpe_ratio'] <= 0) {
            return 50.0;
        }

        $sharpeScore = ($currentStats['sharpe_ratio'] / $maxSharpePortfolio['sharpe_ratio']) * 100;

        return min(100.0, max(0.0, $sharpeScore));
    }

    /**
     * Generate efficiency recommendation
     */
    private function generateEfficiencyRecommendation(float $efficiencyScore): string
    {
        if ($efficiencyScore >= 90) {
            return 'Your portfolio is highly efficient and near-optimal on the efficient frontier.';
        }

        if ($efficiencyScore >= 75) {
            return 'Your portfolio is reasonably efficient but has room for improvement.';
        }

        if ($efficiencyScore >= 60) {
            return 'Your portfolio could be significantly improved by moving towards the efficient frontier.';
        }

        return 'Your portfolio is sub-optimal. Consider rebalancing to improve risk-adjusted returns.';
    }
}
