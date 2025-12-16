<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Models\Investment\RiskProfile;

class AssetAllocationOptimizer
{
    /**
     * Get target allocation based on risk profile
     *
     * Supports both the new 5-level risk_level system and the legacy 3-level risk_tolerance.
     */
    public function getTargetAllocation(RiskProfile $profile): array
    {
        // Prefer new risk_level, fall back to legacy risk_tolerance
        $level = $profile->risk_level ?? $this->mapLegacyTolerance($profile->risk_tolerance);

        $allocation = $this->getAllocationForLevel($level);

        // Convert to array of objects
        return collect($allocation)->map(fn ($percentage, $assetType) => [
            'asset_type' => $assetType,
            'percentage' => (float) $percentage,
        ])->values()->toArray();
    }

    /**
     * Get target allocation for a specific risk level
     */
    public function getTargetAllocationForLevel(string $riskLevel): array
    {
        $allocation = $this->getAllocationForLevel($riskLevel);

        return collect($allocation)->map(fn ($percentage, $assetType) => [
            'asset_type' => $assetType,
            'percentage' => (float) $percentage,
        ])->values()->toArray();
    }

    /**
     * Get allocation percentages for a risk level
     */
    private function getAllocationForLevel(string $level): array
    {
        return match ($level) {
            'low' => [
                'equity' => 10,
                'bond' => 70,
                'cash' => 20,
            ],
            'lower_medium' => [
                'equity' => 30,
                'bond' => 55,
                'cash' => 15,
            ],
            'medium' => [
                'equity' => 50,
                'bond' => 40,
                'cash' => 10,
            ],
            'upper_medium' => [
                'equity' => 75,
                'bond' => 20,
                'cash' => 5,
            ],
            'high' => [
                'equity' => 90,
                'bond' => 5,
                'cash' => 5,
            ],
            // Legacy values for backwards compatibility
            'cautious' => [
                'equity' => 20,
                'bond' => 60,
                'cash' => 20,
            ],
            'balanced' => [
                'equity' => 60,
                'bond' => 30,
                'cash' => 10,
            ],
            'adventurous' => [
                'equity' => 80,
                'bond' => 15,
                'cash' => 5,
            ],
            default => [
                'equity' => 50,
                'bond' => 40,
                'cash' => 10,
            ],
        };
    }

    /**
     * Map legacy 3-level tolerance to new 5-level system
     */
    private function mapLegacyTolerance(?string $tolerance): string
    {
        return match ($tolerance) {
            'cautious' => 'lower_medium',
            'balanced' => 'medium',
            'adventurous' => 'upper_medium',
            default => 'medium',
        };
    }

    /**
     * Calculate deviation between current and target allocation
     */
    public function calculateDeviation(array $current, array $target): array
    {
        $deviations = [];
        $totalDeviation = 0;

        foreach ($target as $targetAsset) {
            $assetType = $targetAsset['asset_type'];
            $targetPercent = $targetAsset['percentage'];
            $currentPercent = 0;

            // Find current percentage for this asset type
            foreach ($current as $asset) {
                if ($asset['asset_type'] === $assetType) {
                    $currentPercent = $asset['percentage'];
                    break;
                }
            }

            $deviation = $currentPercent - $targetPercent;
            $absoluteDeviation = abs($deviation);
            $totalDeviation += $absoluteDeviation;

            $deviations[] = [
                'asset_type' => $assetType,
                'target' => (float) $targetPercent,
                'current' => (float) $currentPercent,
                'difference' => (float) round($deviation, 2),
                'status' => match (true) {
                    $deviation > 5 => 'overweight',
                    $deviation < -5 => 'underweight',
                    default => 'balanced',
                },
            ];
        }

        return [
            'deviations' => $deviations,
            'total_deviation' => round($totalDeviation, 2),
            'needs_rebalancing' => $totalDeviation > 15,
        ];
    }

    /**
     * Generate rebalancing trades
     */
    public function generateRebalancingTrades(array $current, array $target, float $portfolioValue): array
    {
        if ($portfolioValue == 0) {
            return [];
        }

        $trades = [];

        foreach ($target as $targetAsset) {
            $assetType = $targetAsset['asset_type'];
            $targetPercent = $targetAsset['percentage'];
            $targetValue = $portfolioValue * ($targetPercent / 100);

            // Find current value for this asset type
            $currentValue = 0;
            foreach ($current as $asset) {
                if ($asset['asset_type'] === $assetType) {
                    $currentValue = $asset['value'];
                    break;
                }
            }

            $difference = $targetValue - $currentValue;

            if (abs($difference) > $portfolioValue * 0.05) { // Only trade if difference > 5% of portfolio
                $trades[] = [
                    'asset_type' => $assetType,
                    'action' => $difference > 0 ? 'buy' : 'sell',
                    'amount' => round(abs($difference), 2),
                    'current_value' => round($currentValue, 2),
                    'target_value' => round($targetValue, 2),
                ];
            }
        }

        return $trades;
    }

    /**
     * Suggest optimal allocation for a new investor
     */
    public function suggestNewInvestorAllocation(int $age, int $retirementAge = 67): array
    {
        $yearsToRetirement = max(1, $retirementAge - $age);

        // Age-based rule of thumb: 100 - age = equity allocation
        $equityPercent = max(20, min(80, 100 - $age));
        $bondPercent = 100 - $equityPercent - 10; // Leave 10% for cash
        $cashPercent = 10;

        // Adjust based on time horizon
        if ($yearsToRetirement < 10) {
            $equityPercent -= 10;
            $bondPercent += 10;
        }

        return [
            ['asset_type' => 'equity', 'percentage' => (float) max(20, $equityPercent)],
            ['asset_type' => 'bond', 'percentage' => (float) $bondPercent],
            ['asset_type' => 'cash', 'percentage' => (float) $cashPercent],
        ];
    }
}
