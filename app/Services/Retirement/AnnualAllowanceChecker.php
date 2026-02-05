<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\DCPension;
use App\Services\TaxConfigService;

/**
 * Annual Allowance Checker Service
 *
 * Checks pension annual allowance, tapering for high earners, carry forward, and MPAA.
 * Uses active tax year rates from TaxConfigService.
 */
class AnnualAllowanceChecker
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
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
     * Check annual allowance for a user in a given tax year.
     *
     * @param  string  $taxYear  Tax year (e.g., '2024/25')
     */
    public function checkAnnualAllowance(int $userId, string $taxYear): array
    {
        $dcPensions = DCPension::where('user_id', $userId)->get();

        // Calculate total annual contributions
        $totalContributions = $this->calculateTotalAnnualContributions($dcPensions);

        // Get user's income (from retirement profile or assumed)
        $income = $this->getUserIncome($userId);
        $thresholdIncome = $income; // Simplified - should include other sources
        $adjustedIncome = $income + $totalContributions; // Simplified calculation

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
     * Note: This is a simplified implementation. In reality, we would need to track
     * actual contributions and unused allowance for each of the previous 3 years.
     *
     * @return float Total carry forward available
     */
    public function getCarryForward(int $userId, string $taxYear): float
    {
        // Simplified: Assume unused allowance from previous years
        // In a full implementation, we would track this in a separate table
        // For now, return a conservative estimate (1 year's unused allowance)
        return $this->getStandardAnnualAllowance();
    }

    /**
     * Check if user has triggered Money Purchase Annual Allowance (MPAA).
     *
     * MPAA is triggered when user has flexibly accessed a pension.
     */
    public function checkMPAA(int $userId): array
    {
        // In a full implementation, we would track flexible access events
        // For now, return false (not triggered)
        $isTriggered = false;
        $mpaaAmount = $this->getMPAA();

        return [
            'is_triggered' => $isTriggered,
            'mpaa_amount' => $mpaaAmount,
            'message' => $isTriggered
                ? 'MPAA triggered - your annual allowance is reduced to £'.number_format($mpaaAmount).' per year.'
                : 'MPAA not triggered - standard annual allowance applies.',
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

    /**
     * Get user's annual income.
     */
    private function getUserIncome(int $userId): float
    {
        $profile = \App\Models\RetirementProfile::where('user_id', $userId)->first();

        return $profile ? (float) $profile->current_annual_salary : 0.0;
    }
}
