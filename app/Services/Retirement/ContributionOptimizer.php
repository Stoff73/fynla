<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

/**
 * Contribution Optimizer Service
 *
 * Optimizes pension contributions to help users meet retirement goals while
 * maximizing tax relief and employer matches.
 */
class ContributionOptimizer
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Optimize pension contributions based on retirement profile and goals.
     */
    public function optimizeContributions(RetirementProfile $profile, Collection $pensions): array
    {
        $recommendations = [];

        // Check employer match optimization
        foreach ($pensions as $pension) {
            if ($pension->scheme_type === 'workplace') {
                $matchAnalysis = $this->checkEmployerMatch($pension);
                if (! $matchAnalysis['is_maximized']) {
                    $recommendations[] = [
                        'type' => 'employer_match',
                        'priority' => 'high',
                        'scheme_name' => $pension->scheme_name,
                        'message' => $matchAnalysis['message'],
                        'potential_gain' => $matchAnalysis['potential_gain'],
                    ];
                }
            }
        }

        // Calculate required contribution to meet target
        $yearsToRetirement = $profile->target_retirement_age - $profile->current_age;
        $targetIncome = (float) $profile->target_retirement_income;

        if ($targetIncome > 0 && $yearsToRetirement > 0) {
            $requiredContribution = $this->calculateRequiredContribution(
                $profile,
                $pensions,
                $yearsToRetirement
            );

            $recommendations[] = [
                'type' => 'contribution_increase',
                'priority' => 'medium',
                'message' => sprintf(
                    'To meet your retirement income target, consider increasing total contributions to £%s per month.',
                    number_format($requiredContribution / 12, 2)
                ),
                'required_annual_contribution' => round($requiredContribution, 2),
                'required_monthly_contribution' => round($requiredContribution / 12, 2),
            ];
        }

        // Tax relief optimization
        $taxReliefAnalysis = $this->analyzeTaxRelief($profile, $pensions);
        if ($taxReliefAnalysis['optimization_available']) {
            $recommendations[] = [
                'type' => 'tax_relief',
                'priority' => 'medium',
                'message' => $taxReliefAnalysis['message'],
                'potential_saving' => $taxReliefAnalysis['potential_saving'],
            ];
        }

        return [
            'recommendations' => $recommendations,
            'total_current_contributions' => $this->calculateTotalCurrentContributions($pensions),
            'estimated_tax_relief' => $this->calculateTaxRelief(
                $this->calculateTotalCurrentContributions($pensions),
                (float) $profile->current_annual_salary
            ),
        ];
    }

    /**
     * Calculate required annual contribution to meet retirement goal.
     *
     * @return float Required annual contribution
     */
    public function calculateRequiredContribution(
        RetirementProfile $profile,
        Collection $pensions,
        int $yearsToRetirement
    ): float {
        $targetIncome = (float) $profile->target_retirement_income;

        // Using 4% withdrawal rate, calculate required pot
        $requiredPot = $targetIncome / 0.04;

        // Get current DC pension values
        $currentValue = $pensions->sum('current_fund_value');

        // Calculate gap
        $gap = max(0, $requiredPot - $currentValue);

        // Calculate required annual contribution using FV formula
        // Assuming 5% growth rate
        $growthRate = 0.05;

        if ($yearsToRetirement <= 0 || $growthRate <= 0) {
            return 0.0;
        }

        // PMT = (FV × r) / ((1 + r)^n - 1)
        $requiredAnnualContribution = ($gap * $growthRate) / (pow(1 + $growthRate, $yearsToRetirement) - 1);

        return max(0, $requiredAnnualContribution);
    }

    /**
     * Check if user is maximizing employer pension match.
     */
    public function checkEmployerMatch(DCPension $pension): array
    {
        $employeeContribution = (float) $pension->employee_contribution_percent ?? 0.0;
        $employerContribution = (float) $pension->employer_contribution_percent ?? 0.0;

        // Common employer match scenarios
        // Assume employer matches up to 5% if employee contributes 5%
        $typicalMatchThreshold = 5.0;
        $isMaximized = $employeeContribution >= $typicalMatchThreshold;

        $message = '';
        $potentialGain = 0.0;

        if (! $isMaximized) {
            $additionalContribution = $typicalMatchThreshold - $employeeContribution;
            $message = sprintf(
                'Increase your contribution by %s%% to maximize employer match. This is free money!',
                number_format($additionalContribution, 1)
            );

            // Estimate potential gain (simplified)
            $potentialGain = $additionalContribution * 12; // Monthly gain estimate
        } else {
            $message = 'You are maximizing your employer pension match.';
        }

        return [
            'is_maximized' => $isMaximized,
            'message' => $message,
            'potential_gain' => $potentialGain,
            'current_employee_contribution' => $employeeContribution,
            'recommended_contribution' => max($employeeContribution, $typicalMatchThreshold),
        ];
    }

    /**
     * Calculate tax relief on pension contributions.
     *
     * @param  float  $contribution  Annual contribution
     * @param  float  $income  Annual income
     * @return float Tax relief amount
     */
    public function calculateTaxRelief(float $contribution, float $income): float
    {
        $incomeTax = $this->taxConfig->getIncomeTax();
        $pensionConfig = $this->taxConfig->getPensionAllowances();

        $basicRateThreshold = $incomeTax['higher_rate_threshold'] ?? 50270;
        $higherRateThreshold = $incomeTax['additional_rate_threshold'] ?? 125140;

        $basicRate = $pensionConfig['tax_relief']['basic_rate'] ?? 0.20;
        $higherRate = $pensionConfig['tax_relief']['higher_rate'] ?? 0.40;
        $additionalRate = $pensionConfig['tax_relief']['additional_rate'] ?? 0.45;

        $taxRelief = 0.0;

        if ($income <= $basicRateThreshold) {
            $taxRelief = $contribution * $basicRate;
        } elseif ($income <= $higherRateThreshold) {
            $taxRelief = $contribution * $higherRate;
        } else {
            $taxRelief = $contribution * $additionalRate;
        }

        return round($taxRelief, 2);
    }

    /**
     * Analyze tax relief optimization opportunities.
     */
    private function analyzeTaxRelief(RetirementProfile $profile, Collection $pensions): array
    {
        $income = (float) $profile->current_annual_salary;
        $currentContributions = $this->calculateTotalCurrentContributions($pensions);

        // Check if user is a higher rate taxpayer
        $incomeTax = $this->taxConfig->getIncomeTax();
        $higherRateThreshold = $incomeTax['higher_rate_threshold'] ?? 50270;
        $isHigherRateTaxpayer = $income > $higherRateThreshold;

        $optimizationAvailable = false;
        $message = '';
        $potentialSaving = 0.0;

        if ($isHigherRateTaxpayer && $currentContributions < 40000) {
            $optimizationAvailable = true;
            $pensionConfig = $this->taxConfig->getPensionAllowances();
            $additionalContribution = min(20000, $pensionConfig['annual_allowance'] - $currentContributions);
            $potentialSaving = $this->calculateTaxRelief($additionalContribution, $income);

            $message = sprintf(
                'As a higher-rate taxpayer, you can save £%s in tax by contributing an additional £%s to your pension.',
                number_format($potentialSaving, 2),
                number_format($additionalContribution, 2)
            );
        }

        return [
            'optimization_available' => $optimizationAvailable,
            'message' => $message,
            'potential_saving' => $potentialSaving,
        ];
    }

    /**
     * Calculate total current annual contributions across all pensions.
     */
    private function calculateTotalCurrentContributions(Collection $pensions): float
    {
        $total = 0.0;

        foreach ($pensions as $pension) {
            $monthlyContribution = (float) $pension->monthly_contribution_amount ?? 0.0;
            $total += $monthlyContribution * 12;
        }

        return $total;
    }
}
