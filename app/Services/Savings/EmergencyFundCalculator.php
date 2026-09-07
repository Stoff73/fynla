<?php

declare(strict_types=1);

namespace App\Services\Savings;

class EmergencyFundCalculator
{
    /**
     * Calculate emergency fund runway in months, or null where it cannot be
     * worked out at all.
     *
     * **"No runway" and "no idea" are different answers and used to be the same
     * one** (W-0495). Returning 0.0 for an unrecorded expenditure told a
     * household sitting on £40,000 of cash that it had zero months of cover, and
     * the error ran in the alarming direction: every consumer that branches on a
     * low runway — `HolisticPlanner:333`/`:391` at under three months,
     * `PriorityRanker:179` at under one — then raised "build your emergency
     * fund" against a fund that may already be ample.
     *
     * Null is what the consumers already handle correctly: `?? 6` reads it as
     * "no reason to worry", and `isset()` skips the urgency escalation. A zero
     * could not be told apart from a real one.
     */
    public function calculateRunway(float $totalSavings, float $monthlyExpenditure): ?float
    {
        if ($monthlyExpenditure <= 0) {
            return null;
        }

        return round($totalSavings / $monthlyExpenditure, 2);
    }

    /**
     * Calculate emergency fund adequacy
     *
     * @return array{runway: float|null, target: int, adequacy_score: float|null, shortfall: float|null}
     */
    public function calculateAdequacy(?float $runway, int $targetMonths = 6): array
    {
        // An unknown runway has no shortfall to state and no adequacy to score.
        // Reporting a 100% score would claim the fund is ample; reporting 0
        // would claim it is empty. Both assert something nobody measured.
        if ($runway === null) {
            return [
                'runway' => null,
                'target' => $targetMonths,
                'adequacy_score' => null,
                'shortfall' => null,
            ];
        }

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
    public function categorizeAdequacy(?float $runway, int $targetMonths = 6): string
    {
        // Not a category on the scale — the scale runs Critical to Excellent and
        // every rung asserts a measurement. W-0495.
        if ($runway === null) {
            return 'Unknown';
        }

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
