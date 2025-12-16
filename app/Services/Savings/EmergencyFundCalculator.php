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
     * 6+ months: Excellent
     * 3-6 months: Good
     * 1-3 months: Fair
     * <1 month: Critical
     */
    public function categorizeAdequacy(float $runway): string
    {
        if ($runway >= 6) {
            return 'Excellent';
        }

        if ($runway >= 3) {
            return 'Good';
        }

        if ($runway >= 1) {
            return 'Fair';
        }

        return 'Critical';
    }
}
