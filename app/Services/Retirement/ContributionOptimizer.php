<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Models\StatePension;
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

        // Check employer match optimization and zero-contribution pensions
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

            // Flag pensions with no ongoing contributions
            $annualContrib = $this->calculateAnnualContributionForPension($pension);
            if ($annualContrib <= 0 && (float) $pension->current_fund_value > 0) {
                $recommendations[] = [
                    'type' => 'start_contributions',
                    'priority' => 'high',
                    'scheme_name' => $pension->scheme_name,
                    'message' => sprintf(
                        'Your %s has no ongoing contributions. Regular contributions would benefit from compound growth over your remaining years to retirement.',
                        $pension->scheme_name ?: 'pension'
                    ),
                ];
            }
        }

        // Calculate required contribution to meet target
        $yearsToRetirement = $profile->target_retirement_age - $profile->current_age;
        $targetIncome = (float) $profile->target_retirement_income;

        if ($targetIncome > 0 && $yearsToRetirement > 0) {
            $requiredAdditionalContribution = $this->calculateRequiredContribution(
                $profile,
                $pensions,
                $yearsToRetirement
            );

            if ($requiredAdditionalContribution > 0) {
                $recommendations[] = [
                    'type' => 'contribution_increase',
                    'priority' => 'medium',
                    'message' => sprintf(
                        'To meet your retirement income target, consider contributing an additional £%s per month across your pensions.',
                        number_format($requiredAdditionalContribution / 12, 2)
                    ),
                    'required_annual_contribution' => round($requiredAdditionalContribution, 2),
                    'required_monthly_contribution' => round($requiredAdditionalContribution / 12, 2),
                ];
            }
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
     * Calculate required ADDITIONAL annual contribution to meet retirement goal.
     *
     * Accounts for:
     * - State pension income (reduces the required DC pot)
     * - Future growth of existing DC pots
     * - Future value of existing contributions
     *
     * @return float Required additional annual contribution
     */
    public function calculateRequiredContribution(
        RetirementProfile $profile,
        Collection $pensions,
        int $yearsToRetirement
    ): float {
        $targetIncome = (float) $profile->target_retirement_income;
        $growthRate = 0.05;

        if ($yearsToRetirement <= 0 || $growthRate <= 0) {
            return 0.0;
        }

        // Only subtract state pension if user retires at or after state pension age
        $userId = $profile->user_id;
        $statePension = StatePension::where('user_id', $userId)->first();
        $statePensionAge = $statePension ? ($statePension->state_pension_age ?? 67) : 67;
        $retiresBeforeSPA = $profile->target_retirement_age < $statePensionAge;

        $statePensionIncome = 0;
        if (! $retiresBeforeSPA && $statePension) {
            $statePensionIncome = (float) ($statePension->state_pension_forecast_annual ?? 0);
        }
        $dcTargetIncome = max(0, $targetIncome - $statePensionIncome);

        // Required DC pot using 4% withdrawal rate
        $requiredPot = $dcTargetIncome / 0.04;

        // Project future value of existing pots + existing contributions
        $projectedValue = 0;
        foreach ($pensions as $pension) {
            $currentValue = (float) $pension->current_fund_value;
            $annualContrib = $this->calculateAnnualContributionForPension($pension);
            $netGrowth = $growthRate - ((float) ($pension->platform_fee_percent ?? 0) / 100);

            // FV of current pot
            $projectedValue += $currentValue * pow(1 + $netGrowth, $yearsToRetirement);

            // FV of existing contributions (annuity)
            if ($annualContrib > 0 && $netGrowth > 0) {
                $projectedValue += $annualContrib * ((pow(1 + $netGrowth, $yearsToRetirement) - 1) / $netGrowth);
            }
        }

        // Gap between required pot and projected value
        $gap = max(0, $requiredPot - $projectedValue);

        if ($gap <= 0) {
            return 0.0;
        }

        // PMT = (FV × r) / ((1 + r)^n - 1) — additional annual contribution needed
        return ($gap * $growthRate) / (pow(1 + $growthRate, $yearsToRetirement) - 1);
    }

    /**
     * Calculate annual contribution for a single pension.
     */
    private function calculateAnnualContributionForPension(DCPension $pension): float
    {
        $monthly = (float) ($pension->monthly_contribution_amount ?? 0);
        if ($monthly > 0) {
            return $monthly * 12;
        }

        $salary = (float) ($pension->annual_salary ?? 0);
        $employeePct = (float) ($pension->employee_contribution_percent ?? 0);
        $employerPct = (float) ($pension->employer_contribution_percent ?? 0);

        if ($salary > 0 && ($employeePct + $employerPct) > 0) {
            return $salary * ($employeePct + $employerPct) / 100;
        }

        return 0;
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

        $basicRateThreshold = (float) ($incomeTax['bands'][0]['upper_limit'] ?? 50270);
        $additionalRateThreshold = (float) ($incomeTax['bands'][1]['upper_limit'] ?? 125140);

        $basicRate = $pensionConfig['tax_relief']['basic_rate'] ?? 0.20;
        $higherRate = $pensionConfig['tax_relief']['higher_rate'] ?? 0.40;
        $additionalRate = $pensionConfig['tax_relief']['additional_rate'] ?? 0.45;

        $taxRelief = 0.0;

        if ($income <= $basicRateThreshold) {
            $taxRelief = $contribution * $basicRate;
        } elseif ($income <= $additionalRateThreshold) {
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
        $higherRateThreshold = (float) ($incomeTax['bands'][0]['upper_limit'] ?? 50270);
        $isHigherRateTaxpayer = $income > $higherRateThreshold;

        $optimizationAvailable = false;
        $message = '';
        $potentialSaving = 0.0;

        $pensionConfig = $this->taxConfig->getPensionAllowances();
        $annualAllowance = $pensionConfig['annual_allowance'] ?? 60000;

        if ($isHigherRateTaxpayer && $currentContributions < $annualAllowance) {
            $optimizationAvailable = true;
            $additionalContribution = $annualAllowance - $currentContributions;
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
