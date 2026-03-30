<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Models\StatePension;
use App\Models\User;
use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

/**
 * Contribution Optimizer Service
 *
 * Optimizes pension contributions to help users meet retirement goals while
 * maximizing tax relief and employer matches.
 */
class PensionContributionOptimizer
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

        // Required DC pot using safe withdrawal rate
        $safeWithdrawalRate = (float) $this->taxConfig->get('retirement.withdrawal_rates.safe', 0.04);
        $requiredPot = $dcTargetIncome / $safeWithdrawalRate;

        // Project future value of existing pots + existing contributions
        $projectedValue = 0;
        foreach ($pensions as $pension) {
            $currentValue = (float) $pension->current_fund_value;
            $annualContrib = $this->calculateAnnualContributionForPension($pension);
            $platformFeePercent = (float) ($pension->platform_fee_percent ?? 0);
            if (($pension->platform_fee_type ?? 'percentage') === 'fixed' && $currentValue > 0) {
                $fixedAmount = (float) ($pension->platform_fee_amount ?? 0);
                $annualFixed = match ($pension->platform_fee_frequency ?? 'annually') {
                    'monthly' => $fixedAmount * 12,
                    'quarterly' => $fixedAmount * 4,
                    default => $fixedAmount,
                };
                $platformFeePercent = ($annualFixed / $currentValue) * 100;
            }
            $advisorFeePercent = (float) ($pension->advisor_fee_percent ?? 0);
            $netGrowth = $growthRate - (($platformFeePercent + $advisorFeePercent) / 100);

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
        // Use configured employer match threshold
        $typicalMatchThreshold = (float) $this->taxConfig->get('retirement.employer_match_threshold', 0.05) * 100;
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
     * Check auto-enrolment compliance for the user.
     *
     * Verifies:
     * - Whether the user earns above the auto-enrolment earnings trigger
     * - Whether total contributions meet the minimum 8% of qualifying earnings
     * - Whether employer contributions meet the minimum 3%
     * - Whether employee contributions meet the minimum 5%
     *
     * Qualifying earnings are the portion of earnings between the lower (£6,240) and
     * upper (£50,270) qualifying earnings limits.
     *
     * @return array{
     *     eligible: bool,
     *     earnings_above_trigger: bool,
     *     qualifying_earnings: float,
     *     total_contribution_percent: float,
     *     employer_contribution_percent: float,
     *     employee_contribution_percent: float,
     *     meets_minimum_total: bool,
     *     meets_minimum_employer: bool,
     *     meets_minimum_employee: bool,
     *     shortfall_percent: float,
     *     shortfall_annual: float,
     *     warnings: array<int, array{type: string, message: string}>
     * }
     */
    public function checkAutoEnrolmentCompliance(User $user, Collection $pensions): array
    {
        $aeConfig = $this->taxConfig->get('pension.auto_enrolment', []);
        $earningsTrigger = (float) ($aeConfig['earnings_trigger'] ?? 10000);
        $lowerQE = (float) ($aeConfig['lower_qualifying_earnings'] ?? 6240);
        $upperQE = (float) ($aeConfig['upper_qualifying_earnings'] ?? 50270);
        $minTotal = (float) ($aeConfig['minimum_total_contribution'] ?? 0.08);
        $minEmployer = (float) ($aeConfig['minimum_employer_contribution'] ?? 0.03);
        $minEmployee = (float) ($aeConfig['minimum_employee_contribution'] ?? 0.05);

        $annualIncome = (float) ($user->annual_employment_income ?? 0);
        $earningsAboveTrigger = $annualIncome >= $earningsTrigger;

        // Calculate qualifying earnings: earnings between lower and upper limits
        $qualifyingEarnings = max(0, min($annualIncome, $upperQE) - $lowerQE);

        // Aggregate contribution percentages across workplace pensions
        $totalEmployeePercent = 0.0;
        $totalEmployerPercent = 0.0;
        $workplacePensionCount = 0;

        foreach ($pensions as $pension) {
            if ($pension->scheme_type !== 'workplace') {
                continue;
            }

            $workplacePensionCount++;
            $employeePercent = (float) ($pension->employee_contribution_percent ?? 0);
            $employerPercent = (float) ($pension->employer_contribution_percent ?? 0);

            // Use highest contribution across workplace pensions (most typical scenario)
            $totalEmployeePercent = max($totalEmployeePercent, $employeePercent);
            $totalEmployerPercent = max($totalEmployerPercent, $employerPercent);
        }

        // Convert to decimal for comparison with config thresholds
        $employeeDecimal = $totalEmployeePercent / 100;
        $employerDecimal = $totalEmployerPercent / 100;
        $totalDecimal = $employeeDecimal + $employerDecimal;

        $meetsMinTotal = $totalDecimal >= $minTotal;
        $meetsMinEmployer = $employerDecimal >= $minEmployer;
        $meetsMinEmployee = $employeeDecimal >= $minEmployee;

        // Calculate shortfall
        $shortfallPercent = max(0, $minTotal - $totalDecimal);
        $shortfallAnnual = $qualifyingEarnings * $shortfallPercent;

        $warnings = [];

        if (! $earningsAboveTrigger && $annualIncome > 0) {
            $warnings[] = [
                'type' => 'info',
                'message' => sprintf(
                    'Your employment income (%s) is below the auto-enrolment earnings trigger (%s). '
                    .'Your employer is not legally required to auto-enrol you, but you may still opt in.',
                    '£'.number_format($annualIncome, 0),
                    '£'.number_format($earningsTrigger, 0)
                ),
            ];
        }

        if ($earningsAboveTrigger && $workplacePensionCount > 0 && ! $meetsMinTotal) {
            $warnings[] = [
                'type' => 'warn',
                'message' => sprintf(
                    'Your total pension contribution rate (%.1f%%) is below the auto-enrolment minimum of %.0f%% of qualifying earnings. '
                    .'You may be missing out on %s per year in pension contributions.',
                    $totalDecimal * 100,
                    $minTotal * 100,
                    '£'.number_format($shortfallAnnual, 2)
                ),
            ];
        }

        if ($earningsAboveTrigger && $workplacePensionCount > 0 && ! $meetsMinEmployer) {
            $warnings[] = [
                'type' => 'warn',
                'message' => sprintf(
                    'Your employer contribution rate (%.1f%%) appears below the minimum %.0f%% required by auto-enrolment regulations. '
                    .'Check with your employer that they are meeting their legal obligation.',
                    $employerDecimal * 100,
                    $minEmployer * 100
                ),
            ];
        }

        if ($earningsAboveTrigger && $workplacePensionCount === 0) {
            $warnings[] = [
                'type' => 'warn',
                'message' => 'You earn above the auto-enrolment earnings trigger but have no workplace pension recorded. '
                    .'Your employer should have auto-enrolled you into a workplace pension scheme.',
            ];
        }

        return [
            'eligible' => $earningsAboveTrigger,
            'earnings_above_trigger' => $earningsAboveTrigger,
            'qualifying_earnings' => round($qualifyingEarnings, 2),
            'total_contribution_percent' => round($totalDecimal * 100, 2),
            'employer_contribution_percent' => round($employerDecimal * 100, 2),
            'employee_contribution_percent' => round($employeeDecimal * 100, 2),
            'meets_minimum_total' => $meetsMinTotal,
            'meets_minimum_employer' => $meetsMinEmployer,
            'meets_minimum_employee' => $meetsMinEmployee,
            'shortfall_percent' => round($shortfallPercent * 100, 2),
            'shortfall_annual' => round($shortfallAnnual, 2),
            'warnings' => $warnings,
        ];
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
