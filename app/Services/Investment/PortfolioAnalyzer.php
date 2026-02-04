<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Models\Investment\Holding;
use App\Models\Investment\RiskProfile;
use Illuminate\Support\Collection;

class PortfolioAnalyzer
{
    /**
     * Calculate total portfolio value across all accounts
     */
    public function calculateTotalValue(Collection $accounts): float
    {
        return $accounts->sum('current_value');
    }

    /**
     * Calculate portfolio returns (YTD, 1Y, 3Y, 5Y)
     */
    public function calculateReturns(Collection $holdings): array
    {
        $totalCostBasis = $holdings->sum('cost_basis');
        $totalCurrentValue = $holdings->sum('current_value');

        if ($totalCostBasis == 0) {
            return [
                'total_cost_basis' => 0.0,
                'total_current_value' => 0.0,
                'total_gain' => 0.0,
                'total_return_percent' => 0.0,
                'ytd_return' => 0.0,
                'one_year_return' => 0.0,
            ];
        }

        $totalGain = $totalCurrentValue - $totalCostBasis;
        $totalReturnPercent = ($totalGain / $totalCostBasis) * 100;

        return [
            'total_cost_basis' => round($totalCostBasis, 2),
            'total_current_value' => round($totalCurrentValue, 2),
            'total_gain' => round($totalGain, 2),
            'total_return_percent' => round($totalReturnPercent, 2),
            'ytd_return' => round($totalReturnPercent, 2), // Simplified - in production would filter by date
            'one_year_return' => round($totalReturnPercent, 2), // Simplified
        ];
    }

    /**
     * Calculate asset allocation by type
     */
    public function calculateAssetAllocation(Collection $holdings): array
    {
        $totalValue = $holdings->sum('current_value');

        if ($totalValue == 0) {
            return [];
        }

        $byType = $holdings->groupBy('asset_type')->map(function ($group, $type) use ($totalValue) {
            $typeValue = $group->sum('current_value');

            return [
                'asset_type' => $type,
                'value' => round($typeValue, 2),
                'percentage' => round(($typeValue / $totalValue) * 100, 2),
                'count' => $group->count(),
            ];
        })->values()->toArray();

        // Sort by value descending
        usort($byType, fn ($a, $b) => $b['value'] <=> $a['value']);

        return $byType;
    }

    /**
     * Calculate geographic allocation based on holdings data.
     *
     * Analyses holdings to determine regional exposure. Falls back to asset-type
     * based estimation when specific geographic data is not available.
     *
     * @param  Collection  $holdings  Collection of Holding models
     * @return array Array of regions with percentage allocations
     */
    public function calculateGeographicAllocation(Collection $holdings): array
    {
        if ($holdings->isEmpty()) {
            return [];
        }

        $totalValue = $holdings->sum('current_value');
        if ($totalValue == 0) {
            return [];
        }

        // Attempt to use geographic data from holdings if available
        $hasGeographicData = $holdings->contains(fn ($h) => ! empty($h->region) || ! empty($h->country));

        if ($hasGeographicData) {
            return $this->calculateFromHoldingRegions($holdings, $totalValue);
        }

        // Fall back to asset-type based estimation
        return $this->estimateGeographicFromAssetTypes($holdings, $totalValue);
    }

    /**
     * Calculate geographic allocation from holding region data.
     */
    private function calculateFromHoldingRegions(Collection $holdings, float $totalValue): array
    {
        $byRegion = $holdings->groupBy(function ($holding) {
            // Use region field if available, otherwise map country to region
            if (! empty($holding->region)) {
                return $holding->region;
            }

            if (! empty($holding->country)) {
                return $this->mapCountryToRegion($holding->country);
            }

            return 'Unknown';
        });

        return $byRegion->map(function ($group, $region) use ($totalValue) {
            $regionValue = $group->sum('current_value');

            return [
                'region' => $region,
                'percentage' => round(($regionValue / $totalValue) * 100, 1),
                'value' => round($regionValue, 2),
            ];
        })->sortByDesc('percentage')->values()->toArray();
    }

    /**
     * Estimate geographic allocation based on asset types.
     *
     * Uses typical fund compositions when specific geographic data is unavailable.
     */
    private function estimateGeographicFromAssetTypes(Collection $holdings, float $totalValue): array
    {
        $regionTotals = [
            'UK' => 0.0,
            'US' => 0.0,
            'Europe' => 0.0,
            'Emerging Markets' => 0.0,
            'Other' => 0.0,
        ];

        foreach ($holdings as $holding) {
            $value = (float) $holding->current_value;
            $assetType = $holding->asset_type ?? 'unknown';

            // Estimate regional exposure based on asset type
            $allocation = match ($assetType) {
                'uk_equity' => ['UK' => 1.0],
                'us_equity' => ['US' => 1.0],
                'international_equity' => ['US' => 0.4, 'Europe' => 0.3, 'Emerging Markets' => 0.2, 'Other' => 0.1],
                'equity', 'fund', 'etf' => ['UK' => 0.25, 'US' => 0.45, 'Europe' => 0.15, 'Emerging Markets' => 0.15],
                'bond' => ['UK' => 0.5, 'US' => 0.3, 'Europe' => 0.2],
                default => ['Other' => 1.0],
            };

            foreach ($allocation as $region => $weight) {
                $regionTotals[$region] += $value * $weight;
            }
        }

        // Convert to percentages
        $result = [];
        foreach ($regionTotals as $region => $value) {
            if ($value > 0) {
                $result[] = [
                    'region' => $region,
                    'percentage' => round(($value / $totalValue) * 100, 1),
                    'estimated' => true,
                ];
            }
        }

        // Sort by percentage descending
        usort($result, fn ($a, $b) => $b['percentage'] <=> $a['percentage']);

        return $result;
    }

    /**
     * Map country codes/names to regions.
     */
    private function mapCountryToRegion(string $country): string
    {
        $country = strtoupper(trim($country));

        $regionMap = [
            // UK
            'UK' => 'UK', 'GB' => 'UK', 'UNITED KINGDOM' => 'UK',
            // US
            'US' => 'US', 'USA' => 'US', 'UNITED STATES' => 'US',
            // Europe
            'DE' => 'Europe', 'GERMANY' => 'Europe',
            'FR' => 'Europe', 'FRANCE' => 'Europe',
            'IT' => 'Europe', 'ITALY' => 'Europe',
            'ES' => 'Europe', 'SPAIN' => 'Europe',
            'NL' => 'Europe', 'NETHERLANDS' => 'Europe',
            'CH' => 'Europe', 'SWITZERLAND' => 'Europe',
            // Emerging Markets
            'CN' => 'Emerging Markets', 'CHINA' => 'Emerging Markets',
            'IN' => 'Emerging Markets', 'INDIA' => 'Emerging Markets',
            'BR' => 'Emerging Markets', 'BRAZIL' => 'Emerging Markets',
        ];

        return $regionMap[$country] ?? 'Other';
    }

    /**
     * Calculate diversification score (0-100)
     */
    public function calculateDiversificationScore(array $allocation): int
    {
        if (empty($allocation)) {
            return 0;
        }

        $score = 100;

        // Penalty for concentration in single asset type
        foreach ($allocation as $asset) {
            if ($asset['percentage'] >= 90) {
                $score -= 70; // Extreme concentration
            } elseif ($asset['percentage'] >= 80) {
                $score -= 60; // Heavy concentration
            } elseif ($asset['percentage'] >= 60) {
                $score -= 40;
            } elseif ($asset['percentage'] >= 50) {
                $score -= 30;
            } elseif ($asset['percentage'] >= 40) {
                $score -= 20;
            } elseif ($asset['percentage'] >= 30) {
                $score -= 10;
            }
        }

        // Bonus for having multiple asset types
        $assetTypeCount = count($allocation);
        if ($assetTypeCount >= 4) {
            $score += 10;
        } elseif ($assetTypeCount >= 3) {
            $score += 5;
        } elseif ($assetTypeCount <= 1) {
            $score -= 20; // Penalty for being in only one asset type
        }

        return max(0, min(100, $score));
    }

    /**
     * Calculate portfolio risk metrics
     */
    public function calculatePortfolioRisk(Collection $holdings, ?RiskProfile $profile): array
    {
        $allocation = $this->calculateAssetAllocation($holdings);

        // For empty holdings, return default medium risk
        if (empty($allocation)) {
            return [
                'risk_level' => 'medium',
                'equity_percentage' => 0.0,
                'estimated_volatility' => 0.0,
            ];
        }

        // Simplified risk calculation based on asset allocation
        $equityPercent = collect($allocation)->firstWhere('asset_type', 'equity')['percentage'] ?? 0;

        $riskLevel = match (true) {
            $equityPercent >= 70 => 'high',
            $equityPercent >= 30 => 'medium',
            default => 'low',
        };

        $volatilityEstimate = $equityPercent * 0.15; // Simplified: ~15% volatility for equities

        $result = [
            'risk_level' => $riskLevel,
            'equity_percentage' => round($equityPercent, 2),
            'estimated_volatility' => round($volatilityEstimate, 2),
        ];

        if ($profile) {
            $result['matches_risk_profile'] = $this->matchesRiskProfile($equityPercent, $profile);
        }

        return $result;
    }

    /**
     * Check if current allocation matches risk profile
     */
    private function matchesRiskProfile(float $equityPercent, RiskProfile $profile): bool
    {
        $targetRange = match ($profile->risk_tolerance) {
            'cautious' => ['min' => 10, 'max' => 30],
            'balanced' => ['min' => 50, 'max' => 70],
            'adventurous' => ['min' => 75, 'max' => 90],
            default => ['min' => 0, 'max' => 100],
        };

        return $equityPercent >= $targetRange['min'] && $equityPercent <= $targetRange['max'];
    }
}
