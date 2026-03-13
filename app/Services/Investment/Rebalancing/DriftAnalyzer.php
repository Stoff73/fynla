<?php

declare(strict_types=1);

namespace App\Services\Investment\Rebalancing;

use App\Constants\InvestmentDefaults;
use Illuminate\Support\Collection;

/**
 * Portfolio Drift Analyzer
 * Measures how far a portfolio has drifted from target allocation
 *
 * Drift Metrics:
 * - Absolute drift: Sum of absolute deviations
 * - Mean squared drift: Average of squared deviations (penalizes large drifts)
 * - Maximum drift: Largest single deviation
 * - Tracking error: Standard deviation of drifts
 */
class DriftAnalyzer
{
    /**
     * Analyze portfolio drift from target allocation
     *
     * @param  Collection  $holdings  Current holdings
     * @param  array  $targetAllocation  Target allocation by asset class
     * @return array Comprehensive drift analysis
     */
    public function analyzeDrift(Collection $holdings, array $targetAllocation): array
    {
        if ($holdings->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No holdings to analyze',
            ];
        }

        // Calculate current allocation
        $currentAllocation = $this->calculateCurrentAllocation($holdings);

        // Calculate drift metrics
        $driftMetrics = $this->calculateDriftMetrics($currentAllocation, $targetAllocation);

        // Identify assets needing adjustment
        $adjustments = $this->calculateAdjustments($currentAllocation, $targetAllocation, $holdings);

        // Calculate drift score (0-100, where 0 = perfect alignment)
        $driftScore = $this->calculateDriftScore($driftMetrics);

        // Determine urgency
        $urgency = $this->determineUrgency($driftScore, $driftMetrics['max_drift']);

        return [
            'success' => true,
            'current_allocation' => $currentAllocation,
            'target_allocation' => $targetAllocation,
            'drift_metrics' => $driftMetrics,
            'drift_score' => $driftScore,
            'urgency' => $urgency,
            'adjustments_needed' => $adjustments,
            'recommendation' => $this->generateDriftRecommendation($driftScore, $urgency),
        ];
    }

    /**
     * Calculate current allocation from holdings
     *
     * @return array Current allocation by asset class
     */
    private function calculateCurrentAllocation(Collection $holdings): array
    {
        $totalValue = $holdings->sum('current_value');

        if ($totalValue <= 0) {
            return [];
        }

        $allocation = [];

        foreach ($holdings as $holding) {
            $assetClass = $this->normalizeAssetClass($holding->asset_type);
            $percent = ($holding->current_value / $totalValue) * 100;

            if (! isset($allocation[$assetClass])) {
                $allocation[$assetClass] = 0.0;
            }

            $allocation[$assetClass] += $percent;
        }

        return $allocation;
    }

    /**
     * Calculate comprehensive drift metrics
     *
     * @param  array  $currentAllocation  Current allocation
     * @param  array  $targetAllocation  Target allocation
     * @return array Drift metrics
     */
    private function calculateDriftMetrics(array $currentAllocation, array $targetAllocation): array
    {
        $drifts = [];
        $absoluteDrift = 0.0;
        $squaredDrift = 0.0;
        $maxDrift = 0.0;
        $assetCount = 0;

        // Combine all asset classes
        $allAssets = array_unique(array_merge(
            array_keys($currentAllocation),
            array_keys($targetAllocation)
        ));

        foreach ($allAssets as $assetClass) {
            $current = $currentAllocation[$assetClass] ?? 0.0;
            $target = $targetAllocation[$assetClass] ?? 0.0;
            $drift = $current - $target;
            $absDrift = abs($drift);

            $drifts[$assetClass] = [
                'current' => round($current, 2),
                'target' => round($target, 2),
                'drift' => round($drift, 2),
                'abs_drift' => round($absDrift, 2),
                'relative_drift' => $target > 0 ? round(($absDrift / $target) * 100, 1) : 0.0,
            ];

            $absoluteDrift += $absDrift;
            $squaredDrift += ($drift * $drift);
            $maxDrift = max($maxDrift, $absDrift);
            $assetCount++;
        }

        $meanSquaredDrift = $assetCount > 0 ? $squaredDrift / $assetCount : 0.0;
        $trackingError = sqrt($meanSquaredDrift);

        return [
            'drifts_by_asset' => $drifts,
            'total_absolute_drift' => round($absoluteDrift, 2),
            'mean_squared_drift' => round($meanSquaredDrift, 2),
            'tracking_error' => round($trackingError, 2),
            'max_drift' => round($maxDrift, 2),
            'max_drift_asset' => $this->findMaxDriftAsset($drifts),
            'asset_count' => $assetCount,
        ];
    }

    /**
     * Calculate adjustments needed to reach target
     *
     * @param  array  $currentAllocation  Current allocation
     * @param  array  $targetAllocation  Target allocation
     * @param  Collection  $holdings  Holdings collection
     * @return array Adjustments by asset class
     */
    private function calculateAdjustments(
        array $currentAllocation,
        array $targetAllocation,
        Collection $holdings
    ): array {
        $totalValue = $holdings->sum('current_value');
        $adjustments = [];

        foreach ($targetAllocation as $assetClass => $targetPercent) {
            $currentPercent = $currentAllocation[$assetClass] ?? 0.0;
            $drift = $currentPercent - $targetPercent;

            // Calculate monetary value of adjustment needed
            $currentValue = ($currentPercent / 100) * $totalValue;
            $targetValue = ($targetPercent / 100) * $totalValue;
            $adjustmentAmount = $targetValue - $currentValue;

            $adjustments[$assetClass] = [
                'current_percent' => round($currentPercent, 2),
                'target_percent' => round($targetPercent, 2),
                'current_value' => round($currentValue, 2),
                'target_value' => round($targetValue, 2),
                'adjustment_needed' => round($adjustmentAmount, 2),
                'action' => $adjustmentAmount > 0 ? 'buy' : 'sell',
                'priority' => $this->calculatePriority(abs($drift), $targetPercent),
            ];
        }

        // Sort by priority (highest first)
        uasort($adjustments, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        return $adjustments;
    }

    /**
     * Calculate drift score (0-100)
     * 0 = perfect alignment, 100 = severe drift
     *
     * @return float Drift score
     */
    private function calculateDriftScore(array $driftMetrics): float
    {
        // Weighted combination of metrics
        $absoluteDriftScore = min(100, $driftMetrics['total_absolute_drift']);
        $maxDriftScore = min(100, $driftMetrics['max_drift'] * 2);
        $trackingErrorScore = min(100, $driftMetrics['tracking_error'] * 5);

        // Weighted average: 40% absolute drift, 40% max drift, 20% tracking error
        $score = (
            ($absoluteDriftScore * 0.40) +
            ($maxDriftScore * 0.40) +
            ($trackingErrorScore * 0.20)
        );

        return round($score, 1);
    }

    /**
     * Determine urgency level based on drift
     *
     * @param  float  $driftScore  Drift score (0-100)
     * @param  float  $maxDrift  Maximum single asset drift
     * @return array Urgency assessment
     */
    private function determineUrgency(float $driftScore, float $maxDrift): array
    {
        $urgencyLevel = match (true) {
            $driftScore >= 40 || $maxDrift >= 15 => 'high',
            $driftScore >= 20 || $maxDrift >= 10 => 'medium',
            $driftScore >= 10 || $maxDrift >= 5 => 'low',
            default => 'none',
        };

        $urgencyLabels = [
            'high' => 'High - Immediate rebalancing recommended',
            'medium' => 'Medium - Rebalancing should be considered',
            'low' => 'Low - Monitor and rebalance at next scheduled date',
            'none' => 'None - Portfolio is well-aligned with target',
        ];

        $colors = [
            'high' => '#EF4444',    // Red
            'medium' => '#F59E0B',  // Amber
            'low' => '#3B82F6',     // Blue
            'none' => '#10B981',    // Green
        ];

        return [
            'level' => $urgencyLevel,
            'label' => $urgencyLabels[$urgencyLevel],
            'color' => $colors[$urgencyLevel],
            'action_required' => in_array($urgencyLevel, ['high', 'medium']),
        ];
    }

    /**
     * Calculate priority for adjustment
     *
     * @param  float  $drift  Absolute drift amount
     * @param  float  $targetPercent  Target percentage
     * @return int Priority score (1-5)
     */
    private function calculatePriority(float $drift, float $targetPercent): int
    {
        // Higher drift = higher priority
        // Larger target allocation = higher priority (more impact)

        $driftScore = match (true) {
            $drift >= 15 => 5,
            $drift >= 10 => 4,
            $drift >= 7 => 3,
            $drift >= 5 => 2,
            default => 1,
        };

        $sizeScore = match (true) {
            $targetPercent >= 40 => 2,
            $targetPercent >= 20 => 1,
            default => 0,
        };

        return min(5, $driftScore + $sizeScore);
    }

    /**
     * Find asset class with maximum drift
     *
     * @param  array  $drifts  Drifts by asset class
     * @return string|null Asset class name
     */
    private function findMaxDriftAsset(array $drifts): ?string
    {
        $maxDrift = 0.0;
        $maxAsset = null;

        foreach ($drifts as $asset => $data) {
            if ($data['abs_drift'] > $maxDrift) {
                $maxDrift = $data['abs_drift'];
                $maxAsset = $asset;
            }
        }

        return $maxAsset;
    }

    /**
     * Generate drift recommendation
     *
     * @return string Recommendation text
     */
    private function generateDriftRecommendation(float $driftScore, array $urgency): string
    {
        if ($driftScore < 10) {
            return 'Your portfolio is well-aligned with your target allocation. No immediate action needed.';
        }

        if ($driftScore < 20) {
            return 'Your portfolio has minor drift from target. Monitor and rebalance at your next scheduled date.';
        }

        if ($driftScore < 40) {
            return 'Your portfolio has moderate drift from target. Consider rebalancing to reduce risk exposure mismatch.';
        }

        return 'Your portfolio has significant drift from target. Rebalancing is strongly recommended to restore intended risk profile.';
    }

    /**
     * Track drift over time
     *
     * @param  array  $historicalDrift  Historical drift data points
     * @return array Drift trend analysis
     */
    public function analyzeDriftTrend(array $historicalDrift): array
    {
        if (count($historicalDrift) < 2) {
            return [
                'success' => false,
                'message' => 'Insufficient data for trend analysis',
            ];
        }

        $driftValues = array_column($historicalDrift, 'drift_score');
        $dates = array_column($historicalDrift, 'date');

        // Calculate trend
        $count = count($driftValues);
        $avgDrift = array_sum($driftValues) / $count;
        $maxHistoricalDrift = max($driftValues);
        $minHistoricalDrift = min($driftValues);

        // Linear regression for trend direction
        $trend = $this->calculateTrend($driftValues);

        // Volatility of drift
        $variance = 0;
        foreach ($driftValues as $value) {
            $variance += pow($value - $avgDrift, 2);
        }
        $stdDev = sqrt($variance / $count);

        return [
            'success' => true,
            'data_points' => $count,
            'date_range' => [
                'start' => reset($dates),
                'end' => end($dates),
            ],
            'average_drift' => round($avgDrift, 2),
            'max_drift' => round($maxHistoricalDrift, 2),
            'min_drift' => round($minHistoricalDrift, 2),
            'current_drift' => round(end($driftValues), 2),
            'trend_direction' => $trend['direction'],
            'trend_slope' => round($trend['slope'], 3),
            'drift_volatility' => round($stdDev, 2),
            'interpretation' => $this->interpretTrend($trend, $avgDrift),
        ];
    }

    /**
     * Calculate linear trend
     *
     * @param  array  $values  Drift values
     * @return array Trend information
     */
    private function calculateTrend(array $values): array
    {
        $n = count($values);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        foreach ($values as $i => $y) {
            $x = $i + 1;
            $sumX += $x;
            $sumY += $y;
            $sumXY += ($x * $y);
            $sumX2 += ($x * $x);
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);

        $direction = match (true) {
            $slope > 1 => 'increasing',
            $slope < -1 => 'decreasing',
            default => 'stable',
        };

        return [
            'slope' => $slope,
            'direction' => $direction,
        ];
    }

    /**
     * Interpret drift trend
     *
     * @param  array  $trend  Trend data
     * @param  float  $avgDrift  Average drift
     * @return string Interpretation
     */
    private function interpretTrend(array $trend, float $avgDrift): string
    {
        if ($trend['direction'] === 'increasing' && $avgDrift > 15) {
            return 'Portfolio drift is increasing over time. Regular rebalancing is recommended.';
        }

        if ($trend['direction'] === 'decreasing') {
            return 'Portfolio drift is improving. Current rebalancing strategy is effective.';
        }

        if ($avgDrift < 10) {
            return 'Portfolio drift remains stable and within acceptable ranges.';
        }

        return 'Portfolio drift is stable but elevated. Consider more frequent rebalancing.';
    }

    /**
     * Normalize asset class names to standard categories
     *
     * @param  string  $assetType  Asset type from holding
     * @return string Normalized asset class
     */
    private function normalizeAssetClass(string $assetType): string
    {
        return InvestmentDefaults::resolveAssetClass($assetType);
    }
}
