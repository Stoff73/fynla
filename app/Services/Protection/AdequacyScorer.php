<?php

declare(strict_types=1);

namespace App\Services\Protection;

use App\Services\TaxConfigService;

class AdequacyScorer
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

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
            $score >= 40 => 'blue',
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

        // Critical illness score: CI need = multiplier x annual gross income
        $ciMultiplier = (int) $this->taxConfig->get('protection.income_multipliers.critical_illness', 3);
        $ciNeed = ($needs['gross_income'] ?? 0) * $ciMultiplier;
        $ciCoverage = $needs['critical_illness_coverage'] ?? 0;
        $ciScore = $ciNeed > 0 ? (int) round(min($ciCoverage, $ciNeed) / $ciNeed * 100) : 100;

        // Income protection score: IP need = 60% of gross income
        $ipNeed = $needs['income_protection_need'] ?? 0;
        $ipCoverage = $gaps['income_replacement_coverage'] ?? 0;
        $ipScore = $ipNeed > 0 ? (int) round(min($ipCoverage, $ipNeed) / $ipNeed * 100) : 100;

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
