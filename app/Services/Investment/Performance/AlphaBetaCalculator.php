<?php

declare(strict_types=1);

namespace App\Services\Investment\Performance;

/**
 * Alpha Beta Calculator
 * Calculates risk-adjusted performance metrics (alpha, beta, Sharpe ratio, etc.)
 *
 * Key Metrics:
 * - Alpha: Excess return above what beta predicts (skill-based return)
 * - Beta: Sensitivity to market movements (systematic risk)
 * - Sharpe Ratio: Risk-adjusted return (return per unit of volatility)
 * - Treynor Ratio: Return per unit of systematic risk
 * - Information Ratio: Excess return per unit of tracking error
 */
class AlphaBetaCalculator
{
    /**
     * Calculate alpha and beta for a portfolio
     *
     * @param  array  $portfolioReturns  Array of portfolio returns (monthly or daily)
     * @param  array  $benchmarkReturns  Array of benchmark returns (same period)
     * @param  float  $riskFreeRate  Annual risk-free rate (e.g., 0.045 for 4.5%)
     * @return array Alpha, beta, and related metrics
     */
    public function calculateAlphaBeta(
        array $portfolioReturns,
        array $benchmarkReturns,
        float $riskFreeRate = 0.045
    ): array {
        if (count($portfolioReturns) !== count($benchmarkReturns)) {
            throw new \InvalidArgumentException('Portfolio and benchmark returns must have same length');
        }

        if (count($portfolioReturns) < 12) {
            throw new \InvalidArgumentException('Need at least 12 periods for meaningful alpha/beta calculation');
        }

        // Calculate means
        $portfolioMean = $this->calculateMean($portfolioReturns);
        $benchmarkMean = $this->calculateMean($benchmarkReturns);

        // Calculate beta (covariance / variance)
        $covariance = $this->calculateCovariance($portfolioReturns, $benchmarkReturns);
        $benchmarkVariance = $this->calculateVariance($benchmarkReturns);

        $beta = $benchmarkVariance > 0 ? $covariance / $benchmarkVariance : 1.0;

        // Calculate alpha (Jensen's alpha)
        // Alpha = Portfolio Return - [Risk-free rate + Beta * (Benchmark Return - Risk-free rate)]
        $periodsPerYear = $this->estimatePeriodsPerYear($portfolioReturns);
        $monthlyRiskFreeRate = $riskFreeRate / $periodsPerYear;

        $excessPortfolioReturn = $portfolioMean - $monthlyRiskFreeRate;
        $excessBenchmarkReturn = $benchmarkMean - $monthlyRiskFreeRate;

        $alpha = $excessPortfolioReturn - ($beta * $excessBenchmarkReturn);

        // Annualize alpha
        $annualizedAlpha = $alpha * $periodsPerYear;

        // Calculate R-squared (how well benchmark explains portfolio)
        $rSquared = $this->calculateRSquared($portfolioReturns, $benchmarkReturns, $beta, $portfolioMean);

        // Calculate tracking error (standard deviation of excess returns)
        $excessReturns = [];
        for ($i = 0; $i < count($portfolioReturns); $i++) {
            $excessReturns[] = $portfolioReturns[$i] - $benchmarkReturns[$i];
        }
        $trackingError = $this->calculateStandardDeviation($excessReturns);
        $annualizedTrackingError = $trackingError * sqrt($periodsPerYear);

        // Calculate Information Ratio
        $informationRatio = $annualizedTrackingError > 0
            ? ($annualizedAlpha / $annualizedTrackingError)
            : 0;

        return [
            'alpha' => $alpha,
            'annualized_alpha' => $annualizedAlpha,
            'alpha_percent' => $annualizedAlpha * 100,
            'beta' => $beta,
            'r_squared' => $rSquared,
            'tracking_error' => $trackingError,
            'annualized_tracking_error' => $annualizedTrackingError,
            'information_ratio' => $informationRatio,
            'interpretation' => $this->interpretAlphaBeta($annualizedAlpha, $beta, $rSquared),
        ];
    }

    /**
     * Calculate Sharpe ratio
     *
     * @param  array  $returns  Array of returns
     * @param  float  $riskFreeRate  Annual risk-free rate
     * @return array Sharpe ratio and components
     */
    public function calculateSharpeRatio(array $returns, float $riskFreeRate = 0.045): array
    {
        if (count($returns) < 12) {
            throw new \InvalidArgumentException('Need at least 12 periods for Sharpe ratio calculation');
        }

        $mean = $this->calculateMean($returns);
        $stdDev = $this->calculateStandardDeviation($returns);

        $periodsPerYear = $this->estimatePeriodsPerYear($returns);
        $monthlyRiskFreeRate = $riskFreeRate / $periodsPerYear;

        // Annualize return and volatility
        $annualizedReturn = $mean * $periodsPerYear;
        $annualizedVolatility = $stdDev * sqrt($periodsPerYear);

        // Sharpe ratio = (Return - Risk-free rate) / Volatility
        $sharpeRatio = $annualizedVolatility > 0
            ? (($annualizedReturn - $riskFreeRate) / $annualizedVolatility)
            : 0;

        return [
            'sharpe_ratio' => $sharpeRatio,
            'annualized_return' => $annualizedReturn,
            'annualized_return_percent' => $annualizedReturn * 100,
            'annualized_volatility' => $annualizedVolatility,
            'annualized_volatility_percent' => $annualizedVolatility * 100,
            'risk_free_rate' => $riskFreeRate,
            'excess_return' => $annualizedReturn - $riskFreeRate,
            'interpretation' => $this->interpretSharpeRatio($sharpeRatio),
        ];
    }

    /**
     * Calculate Treynor ratio
     *
     * @param  array  $portfolioReturns  Portfolio returns
     * @param  array  $benchmarkReturns  Benchmark returns
     * @param  float  $riskFreeRate  Annual risk-free rate
     * @return array Treynor ratio and components
     */
    public function calculateTreynorRatio(
        array $portfolioReturns,
        array $benchmarkReturns,
        float $riskFreeRate = 0.045
    ): array {
        // Calculate beta first
        $alphaBeta = $this->calculateAlphaBeta($portfolioReturns, $benchmarkReturns, $riskFreeRate);
        $beta = $alphaBeta['beta'];

        // Calculate annualized portfolio return
        $mean = $this->calculateMean($portfolioReturns);
        $periodsPerYear = $this->estimatePeriodsPerYear($portfolioReturns);
        $annualizedReturn = $mean * $periodsPerYear;

        // Treynor ratio = (Return - Risk-free rate) / Beta
        $treynorRatio = $beta > 0
            ? (($annualizedReturn - $riskFreeRate) / $beta)
            : 0;

        return [
            'treynor_ratio' => $treynorRatio,
            'annualized_return' => $annualizedReturn,
            'beta' => $beta,
            'risk_free_rate' => $riskFreeRate,
            'excess_return' => $annualizedReturn - $riskFreeRate,
            'interpretation' => $this->interpretTreynorRatio($treynorRatio),
        ];
    }

    /**
     * Calculate covariance between two return series
     *
     * @param  array  $returns1  First return series
     * @param  array  $returns2  Second return series
     * @return float Covariance
     */
    private function calculateCovariance(array $returns1, array $returns2): float
    {
        $mean1 = $this->calculateMean($returns1);
        $mean2 = $this->calculateMean($returns2);

        $sum = 0;
        $n = count($returns1);

        for ($i = 0; $i < $n; $i++) {
            $sum += ($returns1[$i] - $mean1) * ($returns2[$i] - $mean2);
        }

        return $sum / ($n - 1);
    }

    /**
     * Calculate variance
     *
     * @param  array  $returns  Return series
     * @return float Variance
     */
    private function calculateVariance(array $returns): float
    {
        $mean = $this->calculateMean($returns);
        $sum = 0;
        $n = count($returns);

        foreach ($returns as $return) {
            $sum += pow($return - $mean, 2);
        }

        return $sum / ($n - 1);
    }

    /**
     * Calculate standard deviation
     *
     * @param  array  $returns  Return series
     * @return float Standard deviation
     */
    private function calculateStandardDeviation(array $returns): float
    {
        return sqrt($this->calculateVariance($returns));
    }

    /**
     * Calculate mean
     *
     * @param  array  $values  Values
     * @return float Mean
     */
    private function calculateMean(array $values): float
    {
        return count($values) > 0 ? array_sum($values) / count($values) : 0.0;
    }

    /**
     * Calculate R-squared
     *
     * @param  array  $portfolioReturns  Portfolio returns
     * @param  array  $benchmarkReturns  Benchmark returns
     * @param  float  $beta  Beta
     * @param  float  $portfolioMean  Portfolio mean return
     * @return float R-squared
     */
    private function calculateRSquared(
        array $portfolioReturns,
        array $benchmarkReturns,
        float $beta,
        float $portfolioMean
    ): float {
        $benchmarkMean = $this->calculateMean($benchmarkReturns);

        $totalSS = 0;
        $residualSS = 0;

        for ($i = 0; $i < count($portfolioReturns); $i++) {
            $predicted = $beta * ($benchmarkReturns[$i] - $benchmarkMean) + $portfolioMean;
            $totalSS += pow($portfolioReturns[$i] - $portfolioMean, 2);
            $residualSS += pow($portfolioReturns[$i] - $predicted, 2);
        }

        return $totalSS > 0 ? 1 - ($residualSS / $totalSS) : 0;
    }

    /**
     * Estimate periods per year from data
     *
     * @param  array  $returns  Return series
     * @return int Periods per year
     */
    private function estimatePeriodsPerYear(array $returns): int
    {
        // Assume monthly data if we have 12-60 data points
        // Daily data if we have >250 points
        $count = count($returns);

        if ($count > 250) {
            return 252; // Daily (trading days)
        } elseif ($count > 40) {
            return 12; // Monthly
        } else {
            return 12; // Default to monthly
        }
    }

    /**
     * Interpret alpha and beta
     *
     * @param  float  $alpha  Annualized alpha
     * @param  float  $beta  Beta
     * @param  float  $rSquared  R-squared
     * @return string Interpretation
     */
    private function interpretAlphaBeta(float $alpha, float $beta, float $rSquared): string
    {
        $alphaPercent = $alpha * 100;
        $parts = [];

        // Interpret alpha
        if ($alphaPercent > 2) {
            $parts[] = sprintf('Strong positive alpha (%.1f%%) indicates excellent manager skill', $alphaPercent);
        } elseif ($alphaPercent > 0) {
            $parts[] = sprintf('Positive alpha (%.1f%%) suggests some outperformance', $alphaPercent);
        } elseif ($alphaPercent < -2) {
            $parts[] = sprintf('Negative alpha (%.1f%%) indicates underperformance', $alphaPercent);
        } else {
            $parts[] = 'Alpha near zero - performance in line with expectations';
        }

        // Interpret beta
        if ($beta > 1.2) {
            $parts[] = sprintf('High beta (%.2f) means higher volatility than market', $beta);
        } elseif ($beta < 0.8) {
            $parts[] = sprintf('Low beta (%.2f) means lower volatility than market', $beta);
        } else {
            $parts[] = sprintf('Beta (%.2f) indicates market-like volatility', $beta);
        }

        // Interpret R-squared
        if ($rSquared > 0.85) {
            $parts[] = sprintf('High R² (%.1f%%) - well-explained by benchmark', $rSquared * 100);
        } elseif ($rSquared < 0.5) {
            $parts[] = sprintf('Low R² (%.1f%%) - diversified beyond benchmark', $rSquared * 100);
        }

        return implode('. ', $parts);
    }

    /**
     * Interpret Sharpe ratio
     *
     * @param  float  $sharpe  Sharpe ratio
     * @return string Interpretation
     */
    private function interpretSharpeRatio(float $sharpe): string
    {
        if ($sharpe > 2.0) {
            return 'Excellent risk-adjusted returns';
        } elseif ($sharpe > 1.0) {
            return 'Good risk-adjusted returns';
        } elseif ($sharpe > 0.5) {
            return 'Acceptable risk-adjusted returns';
        } elseif ($sharpe > 0) {
            return 'Modest risk-adjusted returns';
        } else {
            return 'Poor risk-adjusted returns (below risk-free rate)';
        }
    }

    /**
     * Interpret Treynor ratio
     *
     * @param  float  $treynor  Treynor ratio
     * @return string Interpretation
     */
    private function interpretTreynorRatio(float $treynor): string
    {
        if ($treynor > 0.10) {
            return 'Excellent return per unit of systematic risk';
        } elseif ($treynor > 0.05) {
            return 'Good return per unit of systematic risk';
        } elseif ($treynor > 0) {
            return 'Acceptable return per unit of systematic risk';
        } else {
            return 'Poor return per unit of systematic risk';
        }
    }
}
