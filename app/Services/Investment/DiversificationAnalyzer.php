<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Constants\InvestmentDefaults;
use Illuminate\Support\Collection;

class DiversificationAnalyzer
{
    // Map string risk levels to numeric
    private const RISK_LEVEL_MAP = [
        'low' => 1,
        'lower_medium' => 2,
        'medium' => 3,
        'upper_medium' => 4,
        'high' => 5,
        'cautious' => 2,
        'balanced' => 3,
        'adventurous' => 4,
    ];

    /**
     * Calculate the Herfindahl-Hirschman Index (HHI)
     * Range: 0 (highly diversified) to 1 (single asset)
     */
    public function calculateHHI(Collection $holdings): float
    {
        if ($holdings->isEmpty()) {
            return 0.0;
        }

        $totalValue = $holdings->sum('current_value');
        if ($totalValue <= 0) {
            return 0.0;
        }

        $hhi = 0.0;
        foreach ($holdings as $holding) {
            $weight = ($holding->current_value ?? 0) / $totalValue;
            $hhi += $weight * $weight;
        }

        return round($hhi, 4);
    }

    /**
     * Get a human-readable label for the HHI value
     */
    public function getHHILabel(float $hhi): string
    {
        return match (true) {
            $hhi < 0.15 => 'Well Diversified',
            $hhi <= 0.25 => 'Moderate Concentration',
            default => 'High Concentration',
        };
    }

    /**
     * Calculate concentration metrics
     */
    public function calculateConcentration(Collection $holdings): array
    {
        if ($holdings->isEmpty()) {
            return [
                'top_holding_percent' => 0.0,
                'top_3_holdings_percent' => 0.0,
                'holdings_over_10_percent' => 0,
                'holdings_over_5_percent' => 0,
            ];
        }

        $totalValue = $holdings->sum('current_value');
        if ($totalValue <= 0) {
            return [
                'top_holding_percent' => 0.0,
                'top_3_holdings_percent' => 0.0,
                'holdings_over_10_percent' => 0,
                'holdings_over_5_percent' => 0,
            ];
        }

        // Calculate percentages and sort descending
        $percentages = $holdings->map(function ($holding) use ($totalValue) {
            return round((($holding->current_value ?? 0) / $totalValue) * 100, 2);
        })->sort()->reverse()->values();

        $topHolding = $percentages->first() ?? 0;
        $top3 = $percentages->take(3)->sum();
        $over10 = $percentages->filter(fn ($p) => $p > 10)->count();
        $over5 = $percentages->filter(fn ($p) => $p > 5)->count();

        return [
            'top_holding_percent' => round($topHolding, 2),
            'top_3_holdings_percent' => round($top3, 2),
            'holdings_over_10_percent' => $over10,
            'holdings_over_5_percent' => $over5,
        ];
    }

    /**
     * Get concentration warnings based on thresholds
     */
    public function getConcentrationWarnings(array $concentration): array
    {
        $warnings = [];

        if ($concentration['top_holding_percent'] > 25) {
            $warnings[] = [
                'type' => 'warning',
                'message' => 'Single holding exceeds 25% of portfolio - consider reducing concentration',
            ];
        }

        if ($concentration['top_3_holdings_percent'] > 60) {
            $warnings[] = [
                'type' => 'warning',
                'message' => 'Top 3 holdings account for over 60% of portfolio',
            ];
        }

        if ($concentration['holdings_over_10_percent'] > 3) {
            $warnings[] = [
                'type' => 'info',
                'message' => sprintf('%d holdings exceed 10%% each - monitor concentration', $concentration['holdings_over_10_percent']),
            ];
        }

        return $warnings;
    }

    /**
     * Get asset class breakdown from holdings
     */
    public function getAssetClassBreakdown(Collection $holdings): array
    {
        if ($holdings->isEmpty()) {
            return [
                'equities' => 0.0,
                'bonds' => 0.0,
                'cash' => 0.0,
                'alternatives' => 0.0,
            ];
        }

        $totalValue = $holdings->sum('current_value');
        if ($totalValue <= 0) {
            return [
                'equities' => 0.0,
                'bonds' => 0.0,
                'cash' => 0.0,
                'alternatives' => 0.0,
            ];
        }

        $breakdown = [
            'equities' => 0.0,
            'bonds' => 0.0,
            'cash' => 0.0,
            'alternatives' => 0.0,
            'mixed' => 0.0,
        ];

        foreach ($holdings as $holding) {
            $assetClass = InvestmentDefaults::resolveAssetClass($holding->asset_type ?? 'equity', $holding->sub_type ?? null);
            $percentage = (($holding->current_value ?? 0) / $totalValue) * 100;
            $breakdown[$assetClass] += $percentage;
        }

        // Round all values
        foreach ($breakdown as $class => $value) {
            $breakdown[$class] = round($value, 2);
        }

        return $breakdown;
    }

    /**
     * Compare current allocation to target based on risk level
     */
    public function compareToTarget(array $currentAllocation, int $riskLevel): array
    {
        $level = max(1, min(5, $riskLevel));
        $target = InvestmentDefaults::getTargetAllocation($level);

        $comparison = [];
        foreach (['equities', 'bonds', 'cash', 'alternatives'] as $class) {
            $current = $currentAllocation[$class] ?? 0;
            $targetVal = $target[$class] ?? 0;
            $deviation = $current - $targetVal;

            $comparison[$class] = [
                'current' => round($current, 2),
                'target' => round($targetVal, 2),
                'deviation' => round($deviation, 2),
                'severity' => $this->getDeviationSeverity(abs($deviation)),
            ];
        }

        return $comparison;
    }

    /**
     * Get deviation severity label
     */
    public function getDeviationSeverity(float $deviation): string
    {
        return match (true) {
            $deviation < 5 => 'aligned',
            $deviation <= 10 => 'minor',
            default => 'significant',
        };
    }

    /**
     * Convert string risk level to numeric (1-5)
     */
    public function normalizeRiskLevel(mixed $riskLevel): int
    {
        if (is_int($riskLevel)) {
            return max(1, min(5, $riskLevel));
        }

        if (is_string($riskLevel)) {
            return self::RISK_LEVEL_MAP[strtolower($riskLevel)] ?? 3;
        }

        return 3; // Default to medium
    }

    /**
     * Calculate diversification score from raw holdings.
     * Convenience method that computes HHI, concentration and asset class breakdown internally.
     */
    public function calculateScoreFromHoldings(Collection $holdings): int
    {
        if ($holdings->isEmpty()) {
            return 0;
        }

        $hhi = $this->calculateHHI($holdings);
        $concentration = $this->calculateConcentration($holdings);
        $assetClassBreakdown = $this->getAssetClassBreakdown($holdings);

        return $this->calculateDiversificationScore($hhi, $concentration, $assetClassBreakdown);
    }

    /**
     * Calculate diversification score (0-100)
     */
    public function calculateDiversificationScore(float $hhi, array $concentration, array $assetClassBreakdown): int
    {
        $score = 100;

        // HHI penalty (0-40 points)
        if ($hhi >= 0.5) {
            $score -= 40;
        } elseif ($hhi >= 0.25) {
            $score -= 25;
        } elseif ($hhi >= 0.15) {
            $score -= 10;
        }

        // Concentration penalties (0-30 points)
        if ($concentration['top_holding_percent'] > 40) {
            $score -= 20;
        } elseif ($concentration['top_holding_percent'] > 25) {
            $score -= 10;
        }

        if ($concentration['top_3_holdings_percent'] > 80) {
            $score -= 10;
        } elseif ($concentration['top_3_holdings_percent'] > 60) {
            $score -= 5;
        }

        // Asset class diversity bonus/penalty (0-30 points)
        $classesUsed = collect($assetClassBreakdown)->filter(fn ($v) => $v > 0)->count();
        if ($classesUsed >= 4) {
            $score += 10;
        } elseif ($classesUsed === 1) {
            $score -= 20;
        } elseif ($classesUsed === 2) {
            $score -= 10;
        }

        return max(0, min(100, $score));
    }

    /**
     * Get score label
     */
    public function getScoreLabel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Excellent',
            $score >= 60 => 'Good',
            $score >= 40 => 'Fair',
            default => 'Poor',
        };
    }

    /**
     * Generate recommendations based on analysis
     */
    public function generateRecommendations(float $hhi, array $concentration, array $comparison): array
    {
        $recommendations = [];

        // HHI-based recommendations
        if ($hhi >= 0.25) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'High concentration - consider spreading investments across more holdings',
            ];
        }

        // Concentration-based recommendations
        if ($concentration['top_holding_percent'] > 25) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => sprintf('Largest holding is %.1f%% - consider reducing to below 25%%', $concentration['top_holding_percent']),
            ];
        }

        // Allocation deviation recommendations
        foreach ($comparison as $class => $data) {
            if ($data['severity'] === 'significant') {
                $direction = $data['deviation'] > 0 ? 'overweight' : 'underweight';
                $action = $data['deviation'] > 0 ? 'reduce' : 'increase';
                $recommendations[] = [
                    'type' => 'info',
                    'message' => sprintf(
                        '%s allocation is %s by %.1f%% - consider %s exposure',
                        ucfirst($class),
                        $direction,
                        abs($data['deviation']),
                        $action === 'increase' ? 'increasing' : 'reducing'
                    ),
                ];
            }
        }

        // Add positive feedback if well diversified
        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'success',
                'message' => 'Portfolio is well diversified and aligned with your risk profile',
            ];
        }

        return $recommendations;
    }

    /**
     * Full diversification analysis
     */
    public function analyze(Collection $holdings, int $userRiskLevel, ?int $accountRiskLevel = null): array
    {
        $effectiveRiskLevel = $accountRiskLevel ?? $userRiskLevel;
        $effectiveRiskLevel = max(1, min(5, $effectiveRiskLevel));

        // Calculate all metrics
        $hhi = $this->calculateHHI($holdings);
        $concentration = $this->calculateConcentration($holdings);
        $assetClassBreakdown = $this->getAssetClassBreakdown($holdings);
        $comparison = $this->compareToTarget($assetClassBreakdown, $effectiveRiskLevel);
        $score = $this->calculateDiversificationScore($hhi, $concentration, $assetClassBreakdown);

        // Generate recommendations
        $recommendations = $this->generateRecommendations($hhi, $concentration, $comparison);

        return [
            'diversification_score' => $score,
            'diversification_label' => $this->getScoreLabel($score),
            'hhi' => $hhi,
            'hhi_label' => $this->getHHILabel($hhi),
            'concentration' => $concentration,
            'concentration_warnings' => $this->getConcentrationWarnings($concentration),
            'asset_class_breakdown' => $comparison,
            'risk_profile' => [
                'user_level' => $userRiskLevel,
                'account_level' => $accountRiskLevel,
                'effective_level' => $effectiveRiskLevel,
                'using_custom' => $accountRiskLevel !== null && $accountRiskLevel !== $userRiskLevel,
            ],
            'recommendations' => $recommendations,
            'holdings_count' => $holdings->count(),
        ];
    }
}
