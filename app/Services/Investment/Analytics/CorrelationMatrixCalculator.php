<?php

declare(strict_types=1);

namespace App\Services\Investment\Analytics;

use App\Services\Investment\Utilities\StatisticalFunctions;
use Illuminate\Support\Collection;

/**
 * Calculate correlation matrix for portfolio holdings
 */
class CorrelationMatrixCalculator
{
    public function __construct(
        private StatisticalFunctions $stats
    ) {}

    /**
     * Calculate correlation matrix from returns data
     *
     * @param  Collection  $holdings  Holdings with historical returns
     * @return array ['matrix' => 2D array, 'labels' => holding names, 'statistics' => summary stats]
     */
    public function calculate(Collection $holdings): array
    {
        if ($holdings->isEmpty()) {
            return [
                'matrix' => [],
                'labels' => [],
                'statistics' => [],
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

        // Calculate correlation for each pair
        for ($i = 0; $i < $n; $i++) {
            $matrix[$i] = [];
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) {
                    $matrix[$i][$j] = 1.0; // Diagonal is always 1
                } else {
                    $matrix[$i][$j] = $this->stats->correlation(
                        $returnsData[$i],
                        $returnsData[$j]
                    );
                }
            }
        }

        // Calculate summary statistics
        $statistics = $this->calculateStatistics($matrix, $labels);

        return [
            'matrix' => $matrix,
            'labels' => $labels,
            'statistics' => $statistics,
        ];
    }

    /**
     * Calculate summary statistics from correlation matrix
     *
     * @param  array  $matrix  Correlation matrix
     * @param  array  $labels  Holding labels
     * @return array Summary statistics
     */
    private function calculateStatistics(array $matrix, array $labels): array
    {
        $n = count($matrix);
        $correlations = [];

        // Extract upper triangle (excluding diagonal)
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $correlations[] = [
                    'holding1' => $labels[$i],
                    'holding2' => $labels[$j],
                    'correlation' => round($matrix[$i][$j], 4),
                ];
            }
        }

        // Sort by correlation (highest first)
        usort($correlations, fn ($a, $b) => $b['correlation'] <=> $a['correlation']);

        // Calculate average correlation
        $avgCorrelation = $n > 1 ? $this->stats->mean(array_column($correlations, 'correlation')) : 0;

        // Find highly correlated pairs (> 0.90)
        $highlyCorrelated = array_filter($correlations, fn ($c) => $c['correlation'] > 0.90);

        // Find diversifiers (< 0.30 or negative)
        $diversifiers = array_filter($correlations, fn ($c) => $c['correlation'] < 0.30);

        return [
            'average_correlation' => round($avgCorrelation, 4),
            'highly_correlated_count' => count($highlyCorrelated),
            'highly_correlated_pairs' => array_slice($highlyCorrelated, 0, 5), // Top 5
            'diversifier_count' => count($diversifiers),
            'diversifier_pairs' => array_slice($diversifiers, 0, 5), // Top 5
            'max_correlation' => count($correlations) > 0 ? max(array_column($correlations, 'correlation')) : 0,
            'min_correlation' => count($correlations) > 0 ? min(array_column($correlations, 'correlation')) : 0,
        ];
    }

    /**
     * Identify redundant holdings (high correlation)
     *
     * @param  array  $correlationData  Output from calculate()
     * @param  float  $threshold  Correlation threshold (default 0.90)
     * @return array Redundant holdings to consider consolidating
     */
    public function identifyRedundantHoldings(array $correlationData, float $threshold = 0.90): array
    {
        $matrix = $correlationData['matrix'];
        $labels = $correlationData['labels'];
        $n = count($matrix);

        $redundantPairs = [];

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($matrix[$i][$j] > $threshold) {
                    $redundantPairs[] = [
                        'holding1' => $labels[$i],
                        'holding2' => $labels[$j],
                        'correlation' => round($matrix[$i][$j], 4),
                        'recommendation' => 'Consider consolidating these highly correlated holdings to reduce redundancy',
                    ];
                }
            }
        }

        return $redundantPairs;
    }

    /**
     * Find complementary holdings (low/negative correlation)
     *
     * @param  array  $correlationData  Output from calculate()
     * @param  float  $threshold  Correlation threshold (default 0.30)
     * @return array Complementary holdings for diversification
     */
    public function findComplementaryHoldings(array $correlationData, float $threshold = 0.30): array
    {
        $matrix = $correlationData['matrix'];
        $labels = $correlationData['labels'];
        $n = count($matrix);

        $complementaryPairs = [];

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($matrix[$i][$j] < $threshold) {
                    $complementaryPairs[] = [
                        'holding1' => $labels[$i],
                        'holding2' => $labels[$j],
                        'correlation' => round($matrix[$i][$j], 4),
                        'benefit' => $matrix[$i][$j] < 0 ? 'Negative correlation - excellent diversification' : 'Low correlation - good diversification',
                    ];
                }
            }
        }

        // Sort by correlation (lowest first)
        usort($complementaryPairs, fn ($a, $b) => $a['correlation'] <=> $b['correlation']);

        return $complementaryPairs;
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
