<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Constants\InvestmentDefaults;
use App\Models\Investment\RiskProfile;

class SimpleAssetAllocationOptimizer
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
     * Get allocation percentages for a risk level.
     *
     * Delegates to InvestmentDefaults::getTargetAllocation() which accepts
     * both string labels and integer levels, and returns plural keys
     * (equities, bonds, cash, alternatives).
     */
    private function getAllocationForLevel(string $level): array
    {
        return InvestmentDefaults::getTargetAllocation($level);
    }

    /**
     * Map legacy 3-level tolerance - keeps original values since getAllocationForLevel
     * handles them directly with their own allocations.
     */
    private function mapLegacyTolerance(?string $tolerance): string
    {
        // Return the original value if it's a valid legacy value,
        // since getAllocationForLevel handles 'cautious', 'balanced', 'adventurous' directly
        return match ($tolerance) {
            'cautious', 'balanced', 'adventurous' => $tolerance,
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
