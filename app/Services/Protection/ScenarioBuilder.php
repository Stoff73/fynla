<?php

declare(strict_types=1);

namespace App\Services\Protection;

use App\Models\ProtectionProfile;
use App\Services\TaxConfigService;

class ScenarioBuilder
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Model death scenario.
     */
    public function modelDeathScenario(ProtectionProfile $profile, array $coverage): array
    {
        $lifeCoverage = $coverage['life_coverage'];
        $debtAmount = $profile->mortgage_balance + $profile->other_debts;
        $remainingFunds = $lifeCoverage - $debtAmount;

        $scenarioWithdrawalRate = (float) $this->taxConfig->get('protection.withdrawal_rates.scenario', 0.03);
        $monthlyIncome = $remainingFunds > 0 ? ($remainingFunds * $scenarioWithdrawalRate) / 12 : 0;
        $monthsOfSupport = $profile->monthly_expenditure > 0 ?
                          $remainingFunds / $profile->monthly_expenditure : 0;

        return [
            'scenario_type' => 'Death',
            'payout' => $lifeCoverage,
            'debt_clearance' => $debtAmount,
            'remaining_funds' => max(0, $remainingFunds),
            'monthly_income_potential' => round($monthlyIncome, 2),
            'months_of_support' => round($monthsOfSupport, 1),
            'adequacy' => $this->assessScenarioAdequacy($remainingFunds, $profile),
            'insights' => $this->generateDeathScenarioInsights($remainingFunds, $monthsOfSupport, $profile),
        ];
    }

    /**
     * Model critical illness scenario.
     */
    public function modelCriticalIllnessScenario(ProtectionProfile $profile, array $coverage): array
    {
        $criticalIllnessCoverage = $coverage['critical_illness_coverage'];
        $immediateNeeds = $profile->monthly_expenditure * 6; // 6 months emergency fund
        $remainingFunds = $criticalIllnessCoverage - $immediateNeeds;

        $monthsOfSupport = $profile->monthly_expenditure > 0 ?
                          $criticalIllnessCoverage / $profile->monthly_expenditure : 0;

        return [
            'scenario_type' => 'Critical Illness',
            'payout' => $criticalIllnessCoverage,
            'immediate_needs' => $immediateNeeds,
            'remaining_funds' => max(0, $remainingFunds),
            'months_of_support' => round($monthsOfSupport, 1),
            'adequacy' => $this->assessScenarioAdequacy($criticalIllnessCoverage, $profile),
            'insights' => $this->generateCriticalIllnessInsights($criticalIllnessCoverage, $monthsOfSupport),
        ];
    }

    /**
     * Model disability scenario.
     */
    public function modelDisabilityScenario(ProtectionProfile $profile, array $coverage): array
    {
        $incomeProtectionCoverage = $coverage['income_protection_coverage'];
        $monthlyBenefit = $incomeProtectionCoverage / 12;
        $shortfall = max(0, $profile->monthly_expenditure - $monthlyBenefit);
        $replacementRatio = $profile->annual_income > 0 ?
                           ($incomeProtectionCoverage / $profile->annual_income) * 100 : 0;

        return [
            'scenario_type' => 'Disability',
            'annual_benefit' => $incomeProtectionCoverage,
            'monthly_benefit' => round($monthlyBenefit, 2),
            'monthly_expenditure' => $profile->monthly_expenditure,
            'monthly_shortfall' => round($shortfall, 2),
            'income_replacement_ratio' => round($replacementRatio, 1),
            'adequacy' => $this->assessIncomeProtectionAdequacy($replacementRatio),
            'insights' => $this->generateDisabilityInsights($replacementRatio, $shortfall),
        ];
    }

    /**
     * Model premium change scenario.
     */
    public function modelPremiumChangeScenario(array $coverage, float $newCoverage): array
    {
        $currentCoverage = $coverage['total_coverage'];
        $coverageIncrease = $newCoverage - $currentCoverage;
        $coverageIncreasePercent = $currentCoverage > 0 ?
                                  ($coverageIncrease / $currentCoverage) * 100 : 0;

        // Simplified premium estimation: base rate per £1,000 per year
        $baseRate = (float) $this->taxConfig->get('protection.premium_factors.base_rate', 0.50);
        $estimatedPremiumIncrease = ($coverageIncrease / 1000) * $baseRate / 12;

        return [
            'scenario_type' => 'Premium Change',
            'current_coverage' => $currentCoverage,
            'new_coverage' => $newCoverage,
            'coverage_increase' => $coverageIncrease,
            'coverage_increase_percent' => round($coverageIncreasePercent, 1),
            'estimated_monthly_premium_increase' => round($estimatedPremiumIncrease, 2),
            'estimated_annual_premium_increase' => round($estimatedPremiumIncrease * 12, 2),
        ];
    }

    /**
     * Assess scenario adequacy.
     */
    private function assessScenarioAdequacy(float $funds, ProtectionProfile $profile): string
    {
        $yearsOfSupport = $profile->monthly_expenditure > 0 ?
                         $funds / ($profile->monthly_expenditure * 12) : 0;

        return match (true) {
            $yearsOfSupport >= 10 => 'Excellent',
            $yearsOfSupport >= 5 => 'Good',
            $yearsOfSupport >= 2 => 'Fair',
            default => 'Poor',
        };
    }

    /**
     * Assess income protection adequacy.
     */
    private function assessIncomeProtectionAdequacy(float $replacementRatio): string
    {
        return match (true) {
            $replacementRatio >= 60 => 'Excellent',
            $replacementRatio >= 50 => 'Good',
            $replacementRatio >= 40 => 'Fair',
            default => 'Poor',
        };
    }

    /**
     * Generate death scenario insights.
     */
    private function generateDeathScenarioInsights(
        float $remainingFunds,
        float $monthsOfSupport,
        ProtectionProfile $profile
    ): array {
        $insights = [];

        $hasDependants = ($profile->number_of_dependents ?? 0) > 0;
        if ($remainingFunds <= 0) {
            $insights[] = 'Warning: Life insurance may not fully cover outstanding debts.';
            $insights[] = $hasDependants
                ? 'Consider increasing coverage to ensure debts are cleared and dependants are provided for.'
                : 'Consider increasing coverage to ensure debts are cleared.';
        } elseif ($monthsOfSupport < 24) {
            $insights[] = sprintf(
                'Coverage would provide approximately %.1f months of support at current expenditure levels.',
                $monthsOfSupport
            );
            $insights[] = 'This may not be sufficient for long-term financial security.';
        } elseif ($monthsOfSupport < 60) {
            $insights[] = sprintf(
                'Coverage would provide approximately %.1f years of support.',
                $monthsOfSupport / 12
            );
            $insights[] = 'This provides moderate protection but may need supplementing.';
        } else {
            $insights[] = sprintf(
                $hasDependants
                    ? 'Excellent: Coverage would provide %.1f years of support for dependants.'
                    : 'Excellent: Coverage would provide %.1f years of financial security.',
                $monthsOfSupport / 12
            );
        }

        if ($profile->number_of_dependents > 0) {
            $insights[] = sprintf(
                'Consider education costs for %d dependent(s) when planning coverage.',
                $profile->number_of_dependents
            );
        }

        return $insights;
    }

    /**
     * Generate critical illness insights.
     */
    private function generateCriticalIllnessInsights(float $coverage, float $monthsOfSupport): array
    {
        $insights = [];

        if ($coverage <= 0) {
            $insights[] = 'No critical illness coverage in place.';
            $insights[] = 'Critical illness cover provides a lump sum if you are diagnosed with a serious condition.';
        } elseif ($monthsOfSupport < 12) {
            $insights[] = 'Critical illness coverage would provide less than 1 year of support.';
            $insights[] = 'Consider increasing coverage to account for recovery time and lifestyle adjustments.';
        } elseif ($monthsOfSupport < 36) {
            $insights[] = sprintf(
                'Coverage would provide approximately %.1f years of support.',
                $monthsOfSupport / 12
            );
            $insights[] = 'This may cover initial recovery but consider long-term needs.';
        } else {
            $insights[] = sprintf(
                'Good coverage: Payout would cover %.1f years of expenses.',
                $monthsOfSupport / 12
            );
        }

        $insights[] = 'Critical illness cover can help pay for medical expenses, home modifications, or debt clearance.';

        return $insights;
    }

    /**
     * Generate disability scenario insights.
     */
    private function generateDisabilityInsights(float $replacementRatio, float $shortfall): array
    {
        $insights = [];

        if ($replacementRatio <= 0) {
            $insights[] = 'No income protection coverage in place.';
            $insights[] = 'Income protection replaces a portion of your income if you cannot work due to illness or injury.';
        } elseif ($replacementRatio < 40) {
            $insights[] = sprintf(
                'Income protection would replace only %.1f%% of your current income.',
                $replacementRatio
            );
            $insights[] = sprintf(
                'This creates a monthly shortfall of £%.2f.',
                $shortfall
            );
            $insights[] = 'Consider increasing coverage to at least 50-60% of income.';
        } elseif ($replacementRatio < 60) {
            $insights[] = sprintf(
                'Income protection would replace %.1f%% of your current income.',
                $replacementRatio
            );
            if ($shortfall > 0) {
                $insights[] = sprintf('There would be a monthly shortfall of £%.2f.', $shortfall);
            }
            $insights[] = 'This provides reasonable protection but may need supplementing.';
        } else {
            $insights[] = sprintf(
                'Excellent: Income protection would replace %.1f%% of your income.',
                $replacementRatio
            );
            if ($shortfall > 0) {
                $insights[] = sprintf(
                    'Small shortfall of £%.2f per month would need to be covered from savings or other sources.',
                    $shortfall
                );
            } else {
                $insights[] = 'This would fully cover your current monthly expenditure.';
            }
        }

        return $insights;
    }
}
