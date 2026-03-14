<?php

declare(strict_types=1);

namespace App\Services\Savings;

class EmergencyFundCalculator
{
    /**
     * Calculate emergency fund runway in months
     */
    public function calculateRunway(float $totalSavings, float $monthlyExpenditure): float
    {
        if ($monthlyExpenditure <= 0) {
            return 0.0;
        }

        return round($totalSavings / $monthlyExpenditure, 2);
    }

    /**
     * Calculate emergency fund adequacy
     *
     * @return array{runway: float, target: int, adequacy_score: float, shortfall: float}
     */
    public function calculateAdequacy(float $runway, int $targetMonths = 6): array
    {
        $adequacyScore = $targetMonths > 0 ? min(100, ($runway / $targetMonths) * 100) : 0;
        $shortfall = max(0, $targetMonths - $runway);

        return [
            'runway' => $runway,
            'target' => $targetMonths,
            'adequacy_score' => round($adequacyScore, 2),
            'shortfall' => round($shortfall, 2),
        ];
    }

    /**
     * Calculate monthly top-up amount required to meet target
     */
    public function calculateMonthlyTopUp(float $shortfall, int $months): float
    {
        if ($months <= 0) {
            return 0.0;
        }

        return round($shortfall / $months, 2);
    }

    /**
     * Categorize adequacy level based on runway
     *
     * target+ months: Excellent
     * target/2 to target: Good
     * 1 to target/2: Fair
     * <1 month: Critical
     */
    public function categorizeAdequacy(float $runway, int $targetMonths = 6): string
    {
        if ($runway >= $targetMonths) {
            return 'Excellent';
        }

        if ($runway >= ($targetMonths / 2)) {
            return 'Good';
        }

        if ($runway >= 1) {
            return 'Fair';
        }

        return 'Critical';
    }

    /**
     * Get target emergency fund months based on employment status
     *
     * Employment-based targets:
     * - employed: 6 months
     * - self_employed: 9 months (irregular income)
     * - contractor: 9 months (contract gaps)
     * - retired: 3 months (stable pension income)
     * - unemployed: 6 months
     */
    public function getTargetMonths(?string $employmentStatus): int
    {
        return match ($employmentStatus) {
            'employed', 'part_time' => 6,
            'self_employed', 'freelance' => 9,
            'contractor' => 9,
            'retired' => 3,
            default => 6,
        };
    }
}
