<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Constants\InvestmentDefaults;
use App\Services\Investment\Rebalancing\DriftAnalyzer;
use Illuminate\Support\Collection;

class DiversificationAnalyzer
{
    /** Asset types that are one company's shares, not a fund of many. */
    private const SINGLE_COMPANY_TYPES = ['equity', 'stock'];

    /** A single company above this share of the account is named (product setting). */
    private const SINGLE_COMPANY_WARNING_PERCENT = 25.0;

    /** How each asset class reads in an insight. */
    private const CLASS_LABELS = ['equities' => 'shares', 'bonds' => 'bonds', 'cash' => 'cash', 'alternatives' => 'alternatives'];

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
            'unclassified' => 0.0,
        ];

        foreach ($holdings as $holding) {
            $assetClass = InvestmentDefaults::resolveAssetClass($holding->asset_type ?? 'unknown', $holding->sub_type ?? null);
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
     * The largest holding of one company's shares, as a share of the account.
     *
     * @return array{name: string, percent: float}|null
     */
    private function topSingleCompany(Collection $holdings): ?array
    {
        $total = (float) $holdings->sum('current_value');
        $top = $holdings
            ->filter(fn ($h) => in_array(strtolower((string) ($h->asset_type ?? '')), self::SINGLE_COMPANY_TYPES, true))
            ->sortByDesc('current_value')
            ->first();

        if ($total <= 0 || $top === null) {
            return null;
        }

        return [
            'name' => (string) ($top->security_name ?? $top->ticker ?? 'One holding'),
            'percent' => round((float) $top->current_value / $total * 100, 2),
        ];
    }

    /**
     * Generate recommendations based on analysis
     */
    public function generateRecommendations(float $hhi, array $concentration, array $comparison): array
    {
        $recommendations = [];

        // Concentration: only one company's shares count. A fund, ETF or trust
        // holds many companies, so three global funds are not "concentrated"
        // (item 8, 2026-10-06). The 25% is a product setting, stated as a fact
        // about the account, not as a target.
        $single = $concentration['top_single_company'] ?? null;
        if (is_array($single) && $single['percent'] > self::SINGLE_COMPANY_WARNING_PERCENT) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => sprintf(
                    '%s is %s%% of this account, all in one company\'s shares',
                    $single['name'],
                    number_format((float) $single['percent'], 0),
                ),
            ];
        }

        // Allocation gaps: only those the recorded holdings prove. Money in funds
        // with no recorded mix is placed where it closes the gaps first, the
        // rule the account's rebalancing panel and card use (DriftAnalyzer,
        // item 8), so a fund's unknown mix is never reported as a missing class.
        $unrecorded = (float) ($comparison['_unrecorded_percent'] ?? 0);
        foreach ($comparison['_assessed'] ?? [] as $class => $data) {
            if ($data['severity'] !== 'significant') {
                continue;
            }
            $qualifier = $unrecorded > 0 ? ($data['deviation'] > 0 ? 'at least ' : 'at most ') : '';
            $recommendations[] = [
                'type' => 'info',
                'message' => sprintf(
                    '%s are %s%s%% of this account against a %s%% target for its risk level',
                    ucfirst(self::CLASS_LABELS[$class] ?? $class),
                    $qualifier,
                    number_format((float) $data['current'], 0),
                    number_format((float) $data['target'], 0),
                ),
            ];
        }

        // Add positive feedback if well diversified
        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'success',
                'message' => $unrecorded > 0
                    ? sprintf('The recorded holdings show no gap from the target mix for this account\'s risk level. %s%% is in funds whose mix is not recorded.', number_format($unrecorded, 0))
                    : 'This account is within the target mix for its risk level',
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

        // Generate recommendations, judging gaps on the allocation with unrecorded
        // money placed (DriftAnalyzer::placeUnrecorded); the breakdown shown stays as recorded.
        $target = InvestmentDefaults::getTargetAllocation($effectiveRiskLevel);
        $assessed = $this->compareToTarget(DriftAnalyzer::placeUnrecorded($assetClassBreakdown, $target), $effectiveRiskLevel);
        $recommendations = $this->generateRecommendations($hhi, $concentration + [
            'top_single_company' => $this->topSingleCompany($holdings),
        ], $comparison + [
            '_assessed' => $assessed,
            '_unrecorded_percent' => DriftAnalyzer::unrecordedPercent($assetClassBreakdown),
        ]);

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
