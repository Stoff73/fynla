<?php

declare(strict_types=1);

namespace App\Services\Protection;

use App\Models\ProtectionProfile;
use App\Services\TaxConfigService;
use App\Traits\ResolvesExpenditure;
use App\Traits\ResolvesIncome;

class RecommendationEngine
{
    use ResolvesExpenditure;
    use ResolvesIncome;

    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Generate recommendations based on coverage gaps.
     */
    public function generateRecommendations(array $gaps, ProtectionProfile $profile): array
    {
        $recommendations = [];

        // Life insurance gap - only recommend if user has dependants
        $hasDependants = ($profile->number_of_dependents ?? 0) > 0;
        if ($gaps['gaps_by_category']['human_capital_gap'] > 10000 && $hasDependants) {
            $recommendations[] = $this->createRecommendation(
                priority: $this->calculatePriority($gaps['gaps_by_category']['human_capital_gap'], $profile),
                category: 'Life Insurance',
                action: 'Increase life insurance coverage',
                rationale: sprintf(
                    'Current coverage falls short by £%s. This gap could leave your dependants financially vulnerable.',
                    number_format($gaps['gaps_by_category']['human_capital_gap'], 2)
                ),
                impact: 'High'
            );
        }

        // Debt protection gap
        if ($gaps['gaps_by_category']['debt_protection_gap'] > 0) {
            $recommendations[] = $this->createRecommendation(
                priority: $this->calculatePriority($gaps['gaps_by_category']['debt_protection_gap'], $profile),
                category: 'Life Insurance',
                action: 'Add decreasing term cover for debts',
                rationale: sprintf(
                    'Outstanding debts of £%s should be covered separately to protect your estate.',
                    number_format($profile->mortgage_balance + $profile->other_debts, 2)
                ),
                impact: 'High'
            );
        }

        // Critical illness gap
        if ($gaps['gaps_by_category']['human_capital_gap'] > 0 && $profile->user->criticalIllnessPolicies->isEmpty()) {
            $recommendations[] = $this->createRecommendation(
                priority: 2,
                category: 'Critical Illness',
                action: 'Consider critical illness cover',
                rationale: 'Critical illness cover would provide a lump sum if you are diagnosed with a serious condition.',
                impact: 'Medium'
            );
        }

        // Income protection gap
        if ($gaps['gaps_by_category']['income_protection_gap'] > 0) {
            $recommendations[] = $this->createRecommendation(
                priority: $this->calculatePriority($gaps['gaps_by_category']['income_protection_gap'], $profile),
                category: 'Income Protection',
                action: 'Add income protection insurance',
                rationale: sprintf(
                    'Income protection would replace £%s per year if you cannot work due to illness or injury.',
                    number_format($gaps['gaps_by_category']['income_protection_gap'], 2)
                ),
                impact: 'High'
            );
        }

        // Trust recommendation
        if ($profile->user->lifeInsurancePolicies()->where('in_trust', false)->exists()) {
            $recommendations[] = $this->createRecommendation(
                priority: 4,
                category: 'Trust Planning',
                action: 'Place policies in trust',
                rationale: 'Policies not in trust may be subject to inheritance tax and probate delays.',
                impact: 'Medium'
            );
        }

        // Policy optimisation (only if income is known)
        $totalPremiums = $this->calculateTotalPremiums($profile);
        if ($profile->annual_income > 0 && $totalPremiums > $profile->annual_income * 0.05) {
            $recommendations[] = $this->createRecommendation(
                priority: 5,
                category: 'Policy Optimisation',
                action: 'Review and optimise existing policies',
                rationale: sprintf(
                    'Total premiums of £%s per year exceed 5%% of income. Consider reviewing for better value.',
                    number_format($totalPremiums, 2)
                ),
                impact: 'Low'
            );
        }

        // Sort by priority
        usort($recommendations, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return $recommendations;
    }

    /**
     * Create a standardized recommendation array.
     */
    private function createRecommendation(
        int $priority,
        string $category,
        string $action,
        string $rationale,
        string $impact
    ): array {
        return [
            'priority' => $priority,
            'category' => $category,
            'action' => $action,
            'rationale' => $rationale,
            'impact' => $impact,
            // No premium figure: nothing sources what an insurer would charge
            // this person (Rule 23; CSJ 2026-10-06).
        ];
    }

    /**
     * Calculate recommendation priority.
     */
    private function calculatePriority(float $gap, ProtectionProfile $profile): int
    {
        $gapRatio = $profile->annual_income > 0 ? $gap / $profile->annual_income : 0;

        return match (true) {
            $gapRatio > 5 => 1, // Critical
            $gapRatio > 2 => 2, // High
            $gapRatio > 1 => 3, // Medium
            default => 4,       // Low
        };
    }

    /**
     * Calculate total current premiums.
     */
    private function calculateTotalPremiums(ProtectionProfile $profile): float
    {
        $profile->user->loadMissing(['lifeInsurancePolicies', 'criticalIllnessPolicies', 'incomeProtectionPolicies']);

        $totalPremiums = 0;

        foreach ($profile->user->lifeInsurancePolicies as $policy) {
            $premium = $policy->premium_amount;
            if ($policy->premium_frequency === 'monthly') {
                $premium *= 12;
            } elseif ($policy->premium_frequency === 'quarterly') {
                $premium *= 4;
            }
            $totalPremiums += $premium;
        }

        foreach ($profile->user->criticalIllnessPolicies as $policy) {
            $premium = $policy->premium_amount;
            if ($policy->premium_frequency === 'monthly') {
                $premium *= 12;
            } elseif ($policy->premium_frequency === 'quarterly') {
                $premium *= 4;
            }
            $totalPremiums += $premium;
        }

        foreach ($profile->user->incomeProtectionPolicies as $policy) {
            $premium = $policy->premium_amount;
            $totalPremiums += $premium * 12; // Stored as monthly
        }

        return $totalPremiums;
    }
}
