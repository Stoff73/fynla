<?php

declare(strict_types=1);

namespace App\Services\Investment\Analytics;

use App\Models\Investment\InvestmentAccount;
use App\Services\Investment\Utilities\MatrixOperations;
use App\Services\Investment\Utilities\StatisticalFunctions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Calculate Efficient Frontier for Modern Portfolio Theory
 * Generates the set of optimal portfolios offering maximum return for given risk
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

    /**
     * Extract expected returns and labels from holdings
     *
     * @param  Collection  $holdings  Holdings collection
     * @return array ['expected_returns' => array, 'labels' => array]
     */
    private function extractHoldingsData(Collection $holdings): array
    {
        $expectedReturns = [];
        $labels = [];

        foreach ($holdings as $holding) {
            // Use historical returns to estimate expected return (simple average)
            // TODO: Replace with more sophisticated estimation (CAPM, analyst estimates, etc.)
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
     *
     * @param  Collection  $holdings  Holdings collection
     * @return array Current weights
     */
    private function calculateCurrentWeights(Collection $holdings): array
    {
        $totalValue = $holdings->sum('current_value');

        if ($totalValue == 0) {
            $n = $holdings->count();

            return $n > 0 ? array_fill(0, $n, 1 / $n) : []; // Equal weight if no values
        }

        $weights = [];
        foreach ($holdings as $holding) {
            $weights[] = $holding->current_value / $totalValue;
        }

        return $weights;
    }

    /**
     * Generate points along the efficient frontier
     *
     * @param  array  $expectedReturns  Expected returns
     * @param  array  $covarianceMatrix  Covariance matrix
     * @param  array  $minVariancePortfolio  Minimum variance portfolio
     * @param  array  $tangencyPortfolio  Tangency portfolio
     * @param  int  $numPoints  Number of points to generate
     * @return array Frontier points
     */
    private function generateFrontierPoints(
        array $expectedReturns,
        array $covarianceMatrix,
        array $minVariancePortfolio,
        array $tangencyPortfolio,
        int $numPoints
    ): array {
        $minReturn = $minVariancePortfolio['expected_return'];
        $maxReturn = max($expectedReturns); // Maximum possible return

        $points = [];

        // Generate points from min variance to max return
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
     * Line from risk-free asset to tangency portfolio
     *
     * @param  array  $tangencyPortfolio  Tangency portfolio
     * @param  float  $riskFreeRate  Risk-free rate
     * @return array CAL data
     */
    private function calculateCapitalAllocationLine(
        array $tangencyPortfolio,
        float $riskFreeRate
    ): array {
        $sharpe = $tangencyPortfolio['sharpe_ratio'];
        $tangencyReturn = $tangencyPortfolio['expected_return'];
        $tangencyRisk = $tangencyPortfolio['expected_risk'];

        // CAL equation: R_p = R_f + Sharpe * σ_p
        // Generate points along CAL
        $calPoints = [];
        $maxRisk = $tangencyRisk * 2; // Extend CAL beyond tangency point

        for ($i = 0; $i <= 10; $i++) {
            $risk = ($maxRisk / 10) * $i;
            $return = $riskFreeRate + $sharpe * $risk;

            $calPoints[] = [
                'risk' => round($risk, 6),
                'return' => round($return, 6),
            ];
        }

        return [
            'slope' => $sharpe, // Sharpe ratio is the slope of CAL
            'intercept' => $riskFreeRate,
            'equation' => "R_p = {$riskFreeRate} + {$sharpe} × σ_p",
            'points' => $calPoints,
        ];
    }

    /**
     * Analyze improvement opportunities vs. current portfolio
     *
     * @param  array  $currentMetrics  Current portfolio metrics
     * @param  array  $tangencyPortfolio  Optimal portfolio
     * @param  array  $minVariancePortfolio  Min variance portfolio
     * @return array Improvement analysis
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
     * Generate recommendation text
     *
     * @param  float  $currentSharpe  Current Sharpe ratio
     * @param  float  $optimalSharpe  Optimal Sharpe ratio
     * @param  float  $currentRisk  Current risk
     * @param  float  $minRisk  Minimum possible risk
     * @return string Recommendation
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
     * Generate mock returns for testing (TODO: Remove when real data available)
     *
     * @param  int  $periods  Number of periods
     * @return array Mock return data
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
