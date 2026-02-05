<?php

declare(strict_types=1);

namespace App\Services\Protection;

class AdequacyScorer
{
    /**
     * Calculate adequacy score based on coverage gaps.
     */
    public function calculateAdequacyScore(array $gaps, array $needs): int
    {
        $totalNeed = $needs['total_need'];
        $totalGap = $gaps['total_gap'];

        if ($totalNeed <= 0) {
            return 100;
        }

        $coverageRatio = ($totalNeed - $totalGap) / $totalNeed;
        $score = (int) round($coverageRatio * 100);

        return max(0, min(100, $score));
    }

    /**
     * Categorize adequacy score.
     */
    public function categorizeScore(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Excellent',
            $score >= 60 => 'Good',
            $score >= 40 => 'Fair',
            default => 'Critical',
        };
    }

    /**
     * Get score color for UI display.
     */
    public function getScoreColor(int $score): string
    {
        return match (true) {
            $score >= 80 => 'green',
            $score >= 60 => 'blue',
            $score >= 40 => 'orange',
            default => 'red',
        };
    }

    /**
     * Calculate individual policy type adequacy scores.
     */
    private function calculateIndividualScores(array $gaps, array $needs): array
    {
        $gapsByCategory = $gaps['gaps_by_category'] ?? [];

        // Life insurance score (based on human capital + debt coverage)
        $lifeNeed = ($needs['human_capital'] ?? 0) + ($needs['debt_protection'] ?? 0) + ($needs['final_expenses'] ?? 0);
        $lifeGap = ($gapsByCategory['human_capital_gap'] ?? 0) + ($gapsByCategory['debt_protection_gap'] ?? 0) + ($gapsByCategory['final_expenses_gap'] ?? 0);
        $lifeScore = $lifeNeed > 0 ? (int) round((($lifeNeed - $lifeGap) / $lifeNeed) * 100) : 100;

        // Critical illness score (placeholder - based on CI coverage percentage)
        // This would need CI-specific needs calculation in future
        $ciScore = 0; // Placeholder for now

        // Income protection score (placeholder - based on IP coverage percentage)
        // This would need IP-specific needs calculation in future
        $ipScore = 0; // Placeholder for now

        return [
            'life_insurance_score' => max(0, min(100, $lifeScore)),
            'critical_illness_score' => $ciScore,
            'income_protection_score' => $ipScore,
        ];
    }

    /**
     * Generate score insights.
     */
    public function generateScoreInsights(int $score, array $gaps, array $needs = [], bool $hasDependants = false): array
    {
        $category = $this->categorizeScore($score);
        $color = $this->getScoreColor($score);

        $insights = [];

        if ($score >= 80) {
            $insights[] = 'Your protection coverage is excellent. You have comprehensive protection in place.';
        } elseif ($score >= 60) {
            $insights[] = 'Your protection coverage is good, but there are some areas for improvement.';
        } elseif ($score >= 40) {
            $insights[] = $hasDependants
                ? 'Your protection coverage is fair. Consider increasing coverage to better protect your family.'
                : 'Your protection coverage is fair. Consider increasing coverage to improve your financial security.';
        } else {
            $insights[] = $hasDependants
                ? 'Your protection coverage is critical. Immediate action is recommended to protect your family.'
                : 'Your protection coverage is critical. Immediate action is recommended to address these gaps.';
        }

        // Add specific gap insights
        if ($gaps['gaps_by_category']['human_capital_gap'] > 0) {
            $insights[] = 'There is a significant gap in life insurance coverage.';
        }

        if ($gaps['gaps_by_category']['income_protection_gap'] > 0) {
            $insights[] = 'Consider adding income protection to cover loss of earnings.';
        }

        // Calculate individual scores if needs are provided
        $individualScores = ! empty($needs) ? $this->calculateIndividualScores($gaps, $needs) : [
            'life_insurance_score' => 0,
            'critical_illness_score' => 0,
            'income_protection_score' => 0,
        ];

        return [
            'overall_score' => $score,
            'rating' => $category,
            'color' => $color,
            'insights' => $insights,
            'life_insurance_score' => $individualScores['life_insurance_score'],
            'critical_illness_score' => $individualScores['critical_illness_score'],
            'income_protection_score' => $individualScores['income_protection_score'],
            // Keep legacy keys for backward compatibility
            'score' => $score,
            'category' => $category,
        ];
    }
}
