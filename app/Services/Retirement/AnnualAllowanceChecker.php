<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\TaxConfigService;
use Carbon\Carbon;

/**
 * Annual Allowance Checker Service
 *
 * Checks pension annual allowance, tapering for high earners, carry forward, and MPAA.
 * Uses active tax year rates from TaxConfigService.
 */
class AnnualAllowanceChecker
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly IncomeDefinitionsService $incomeDefinitions
    ) {}

    /**
     * Get standard annual allowance from tax config
     */
    private function getStandardAnnualAllowance(): float
    {
        $pensionConfig = $this->taxConfig->getPensionAllowances();

        return $pensionConfig['annual_allowance'];
    }

    /**
     * Get minimum tapered allowance from tax config
     */
    private function getMinimumTaperedAllowance(): float
    {
        $pensionConfig = $this->taxConfig->getPensionAllowances();

        return $pensionConfig['tapered_annual_allowance']['minimum_allowance'];
    }

    /**
     * Get threshold income from tax config
     */
    private function getThresholdIncome(): float
    {
        $pensionConfig = $this->taxConfig->getPensionAllowances();

        return $pensionConfig['tapered_annual_allowance']['threshold_income'];
    }

    /**
     * Get adjusted income threshold from tax config
     */
    private function getAdjustedIncomeThreshold(): float
    {
        $pensionConfig = $this->taxConfig->getPensionAllowances();

        return $pensionConfig['tapered_annual_allowance']['adjusted_income_threshold'];
    }

    /**
     * Get Money Purchase Annual Allowance from tax config
     */
    private function getMPAA(): float
    {
        $pensionConfig = $this->taxConfig->getPensionAllowances();

        return $pensionConfig['mpaa'];
    }

    /**
     * Get the calendar-based tax year (April 6 - April 5).
     * Used to decide whether ongoing monthly contributions should be
     * attributed to the requested tax year.
     */
    private function getCalendarTaxYear(): string
    {
        $now = Carbon::now();
        $taxYearStart = Carbon::create($now->year, 4, 6);
        $startYear = $now->lt($taxYearStart) ? $now->year - 1 : $now->year;

        return $startYear.'/'.substr((string) ($startYear + 1), -2);
    }

    /**
     * Check annual allowance for a user in a given tax year.
     *
     * @param  string  $taxYear  Tax year (e.g., '2024/25')
     */
    public function checkAnnualAllowance(int $userId, string $taxYear): array
    {
        // DC pension contributions are stored as monthly recurring amounts
        // with no per-year history. The projected annual figure only applies
        // to the calendar year we're physically in — switching to a past or
        // future year should show zero used (tax year hasn't started yet, or
        // we have no record of what was contributed then).
        $isCalendarYear = $taxYear === $this->getCalendarTaxYear();

        if ($isCalendarYear) {
            $dcPensions = DCPension::where('user_id', $userId)->get();
            $totalContributions = $this->calculateTotalAnnualContributions($dcPensions);
        } else {
            $totalContributions = 0.0;
        }

        // Get user's income definitions (threshold and adjusted income)
        $definitions = $this->incomeDefinitions->calculate($userId);
        $thresholdIncome = $definitions['threshold_income'];
        $adjustedIncome = $definitions['adjusted_income'];

        // Check if tapering applies
        $standardAllowance = $this->getStandardAnnualAllowance();
        $availableAllowance = $standardAllowance;
        $isTapered = false;
        $taperingDetails = null;

        if ($thresholdIncome > $this->getThresholdIncome() && $adjustedIncome > $this->getAdjustedIncomeThreshold()) {
            $isTapered = true;
            $availableAllowance = $this->calculateTapering($thresholdIncome, $adjustedIncome);
            $taperingDetails = [
                'threshold_income' => $thresholdIncome,
                'adjusted_income' => $adjustedIncome,
                'reduction' => $standardAllowance - $availableAllowance,
            ];
        }

        // Calculate carry forward from previous 3 years
        $carryForward = $this->getCarryForward($userId, $taxYear);

        // Calculate remaining allowance
        $allowanceUsed = min($totalContributions, $availableAllowance + $carryForward);
        $remainingAllowance = max(0, $availableAllowance - $totalContributions);
        $excessContributions = max(0, $totalContributions - ($availableAllowance + $carryForward));

        return [
            'tax_year' => $taxYear,
            'standard_allowance' => $standardAllowance,
            'available_allowance' => $availableAllowance,
            'is_tapered' => $isTapered,
            'tapering_details' => $taperingDetails,
            'total_contributions' => round($totalContributions, 2),
            'carry_forward_available' => round($carryForward, 2),
            'allowance_used' => round($allowanceUsed, 2),
            'remaining_allowance' => round($remainingAllowance, 2),
            'excess_contributions' => round($excessContributions, 2),
            'has_excess' => $excessContributions > 0,
        ];
    }

    /**
     * Calculate tapered annual allowance for high earners.
     *
     * Reduction: £1 for every £2 over adjusted income threshold.
     * Minimum allowance: £10,000
     *
     * @return float Tapered allowance
     */
    public function calculateTapering(float $thresholdIncome, float $adjustedIncome): float
    {
        if ($thresholdIncome <= $this->getThresholdIncome() || $adjustedIncome <= $this->getAdjustedIncomeThreshold()) {
            return $this->getStandardAnnualAllowance();
        }

        // Calculate reduction
        $excessIncome = $adjustedIncome - $this->getAdjustedIncomeThreshold();
        $reduction = $excessIncome / 2;

        // Apply reduction but ensure minimum allowance
        $taperedAllowance = $this->getStandardAnnualAllowance() - $reduction;

        return max($this->getMinimumTaperedAllowance(), $taperedAllowance);
    }

    /**
     * Get carry forward allowance from previous 3 tax years.
     *
     * Uses user-entered prior year unused allowance data from RetirementProfile.
     * Returns 0 when no data is entered (conservative default to prevent
     * users unknowingly exceeding their allowance).
     *
     * @return float Total carry forward available
     */
    public function getCarryForward(int $userId, string $taxYear): float
    {
        $profile = RetirementProfile::where('user_id', $userId)->first();

        if (! $profile || ! $profile->prior_year_unused_allowance) {
            return 0.0;
        }

        $priorYears = $profile->prior_year_unused_allowance;
        $carryForward = 0.0;

        $previousYears = $this->getPrevious3TaxYears($taxYear);

        foreach ($previousYears as $year) {
            $carryForward += (float) ($priorYears[$year] ?? 0);
        }

        return $carryForward;
    }

    /**
     * Get the previous 3 tax year strings for carry forward lookback.
     *
     * @return array e.g. ['2022/23', '2023/24', '2024/25'] for current year '2025/26'
     */
    private function getPrevious3TaxYears(string $currentTaxYear): array
    {
        $startYear = (int) substr($currentTaxYear, 0, 4);

        return [
            ($startYear - 3).'/'.substr((string) ($startYear - 2), -2),
            ($startYear - 2).'/'.substr((string) ($startYear - 1), -2),
            ($startYear - 1).'/'.substr((string) $startYear, -2),
        ];
    }

    /**
     * Check if user has triggered Money Purchase Annual Allowance (MPAA).
     *
     * MPAA is triggered when user has flexibly accessed any DC pension
     * (e.g., flexi-access drawdown, UFPLS, or cashing in a small pot).
     */
    public function checkMPAA(int $userId): array
    {
        $isTriggered = DCPension::where('user_id', $userId)
            ->where('has_flexibly_accessed', true)
            ->exists();

        $mpaaAmount = $this->getMPAA();

        $triggerDate = null;
        if ($isTriggered) {
            $triggerDate = DCPension::where('user_id', $userId)
                ->where('has_flexibly_accessed', true)
                ->min('flexible_access_date');
        }

        return [
            'is_triggered' => $isTriggered,
            'mpaa_amount' => $mpaaAmount,
            'trigger_date' => $triggerDate,
            'message' => $isTriggered
                ? 'Money Purchase Annual Allowance triggered - your annual allowance for money purchase contributions is reduced to £'.number_format($mpaaAmount).' per year.'
                : 'Money Purchase Annual Allowance not triggered - standard annual allowance applies.',
        ];
    }

    /**
     * Calculate total annual pension contributions from all DC pensions.
     * Includes both employee and employer contributions as both count towards annual allowance.
     *
     * @param  \Illuminate\Support\Collection  $dcPensions
     */
    private function calculateTotalAnnualContributions($dcPensions): float
    {
        $total = 0.0;

        foreach ($dcPensions as $pension) {
            // First try monthly_contribution_amount if set
            if ($pension->monthly_contribution_amount > 0) {
                $total += (float) $pension->monthly_contribution_amount * 12;
            } else {
                // Otherwise calculate from percentages
                $annualSalary = (float) ($pension->annual_salary ?? 0);
                $employeePercent = (float) ($pension->employee_contribution_percent ?? 0);
                $employerPercent = (float) ($pension->employer_contribution_percent ?? 0);

                // Both employee and employer contributions count towards annual allowance
                $employeeContrib = $annualSalary * ($employeePercent / 100);
                $employerContrib = $annualSalary * ($employerPercent / 100);

                $total += $employeeContrib + $employerContrib;
            }
        }

        return $total;
    }
}
