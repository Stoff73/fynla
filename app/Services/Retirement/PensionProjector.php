<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Risk\RiskPreferenceService;
use App\Services\TaxConfigService;

/**
 * Pension Projector Service
 *
 * Handles projection of pension values at retirement for DC, DB, and State Pensions.
 */
class PensionProjector
{
    private const DEFAULT_GROWTH_RATE = 0.05; // 5% fallback if no risk profile (used when RiskPreferenceService unavailable)

    private const DEFAULT_RETIREMENT_AGE = 67;

    public function __construct(
        private readonly RiskPreferenceService $riskService,
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Project DC pension value at retirement.
     *
     * Uses future value formula: FV = PV × (1+r)^n + PMT × [((1+r)^n - 1) / r]
     *
     * @param  float  $growthRate  Annual growth rate (e.g., 0.05 for 5%)
     * @return float Projected value at retirement
     */
    public function projectDCPension(DCPension $pension, int $yearsToRetirement, float $growthRate): float
    {
        $currentValue = (float) $pension->current_fund_value;
        $annualContribution = $this->calculateAnnualContribution($pension);

        // Account for all fees: platform + advisor + weighted OCF
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
        $totalFeePercent = $platformFeePercent + $advisorFeePercent;
        $netGrowthRate = $growthRate - ($totalFeePercent / 100);

        // Future value of current fund
        $futureValueOfCurrentFund = $currentValue * pow(1 + $netGrowthRate, $yearsToRetirement);

        // Future value of contributions (annuity)
        $futureValueOfContributions = 0.0;
        if ($netGrowthRate > 0 && $annualContribution > 0) {
            $futureValueOfContributions = $annualContribution * ((pow(1 + $netGrowthRate, $yearsToRetirement) - 1) / $netGrowthRate);
        } elseif ($annualContribution > 0) {
            // If growth rate is 0
            $futureValueOfContributions = $annualContribution * $yearsToRetirement;
        }

        return $futureValueOfCurrentFund + $futureValueOfContributions;
    }

    /**
     * Project DB pension annual income at retirement.
     *
     * Applies compound revaluation based on inflation_protection type
     * over years to retirement.
     *
     * @return float Projected annual pension income
     */
    public function projectDBPension(DBPension $pension, ?int $currentAge = null): float
    {
        $accruedPension = (float) $pension->accrued_annual_pension;

        if (! $currentAge) {
            $user = $pension->user;
            $currentAge = $user?->age ?? $user?->date_of_birth?->age;
        }

        $retirementAge = $pension->normal_retirement_age ?? self::DEFAULT_RETIREMENT_AGE;
        $yearsToRetirement = max(0, $retirementAge - ($currentAge ?? 40));

        if ($yearsToRetirement <= 0) {
            return $accruedPension;
        }

        $revaluationRate = $this->getRevaluationRate($pension);

        if ($revaluationRate <= 0) {
            return $accruedPension;
        }

        return round($accruedPension * pow(1 + $revaluationRate, $yearsToRetirement), 2);
    }

    /**
     * Get the annual revaluation rate for a DB pension based on inflation protection type.
     */
    private function getRevaluationRate(DBPension $pension): float
    {
        return match ($pension->inflation_protection) {
            'cpi' => 0.025,
            'rpi' => 0.03,
            'fixed' => $this->parseFixedRate($pension->revaluation_method),
            'none' => 0.0,
            default => 0.02,
        };
    }

    /**
     * Parse a fixed revaluation rate from the revaluation_method string.
     */
    private function parseFixedRate(?string $revaluationMethod): float
    {
        if (! $revaluationMethod) {
            return 0.025;
        }

        if (preg_match('/(\d+(?:\.\d+)?)%/', $revaluationMethod, $matches)) {
            return (float) $matches[1] / 100;
        }

        return 0.025;
    }

    /**
     * Project State Pension annual income.
     *
     * @return float Projected annual state pension income
     */
    public function projectStatePension(StatePension $statePension): float
    {
        // Use forecast if available
        if ($statePension->state_pension_forecast_annual) {
            return (float) $statePension->state_pension_forecast_annual;
        }

        // Calculate based on NI years using active tax year state pension amount
        $pensionConfig = $this->taxConfig->getPensionAllowances();
        $fullStatePension = (float) ($pensionConfig['state_pension']['full_new_state_pension'] ?? 11973.00);
        $requiredYears = $statePension->ni_years_required
            ?? ($pensionConfig['state_pension']['qualifying_years'] ?? 35);
        $completedYears = min($statePension->ni_years_completed, $requiredYears);

        if ($requiredYears > 0) {
            return ($completedYears / $requiredYears) * $fullStatePension;
        }

        return 0.0;
    }

    /**
     * Project total retirement income from all pension sources.
     */
    public function projectTotalRetirementIncome(int $userId): array
    {
        $dcPensions = DCPension::where('user_id', $userId)->get();
        $dbPensions = DBPension::where('user_id', $userId)->get();
        $statePension = StatePension::where('user_id', $userId)->first();

        $totalDCValue = 0.0;
        $totalDBIncome = 0.0;
        $statePensionIncome = 0.0;
        $dcProjections = [];

        $currentAge = $this->getUserAge($userId);

        // Project DC pensions (each may have its own risk preference)
        foreach ($dcPensions as $dcPension) {
            $retirementAge = $dcPension->retirement_age ?? self::DEFAULT_RETIREMENT_AGE;
            $yearsToRetirement = max(0, $retirementAge - $currentAge);

            // Get growth rate for this specific pension (may have custom risk)
            $growthRate = $this->getGrowthRateForPension($dcPension, $userId);
            $projectedValue = $this->projectDCPension($dcPension, $yearsToRetirement, $growthRate);
            $totalDCValue += $projectedValue;

            $dcProjections[] = [
                'scheme_name' => $dcPension->scheme_name,
                'projected_value' => round($projectedValue, 2),
                'growth_rate_used' => round($growthRate * 100, 2),
            ];
        }

        // Project DB pensions (with revaluation)
        foreach ($dbPensions as $dbPension) {
            $annualIncome = $this->projectDBPension($dbPension, $currentAge);
            $totalDBIncome += $annualIncome;
        }

        // Project State Pension
        if ($statePension) {
            $statePensionIncome = $this->projectStatePension($statePension);
        }

        // Estimate DC pension income using safe withdrawal rate
        $safeWithdrawalRate = (float) $this->taxConfig->get('retirement.withdrawal_rates.safe', 0.04);
        $dcAnnualIncome = $totalDCValue * $safeWithdrawalRate;

        $totalProjectedIncome = $dcAnnualIncome + $totalDBIncome + $statePensionIncome;

        return [
            'dc_total_value' => round($totalDCValue, 2),
            'dc_annual_income' => round($dcAnnualIncome, 2),
            'dc_projections' => $dcProjections,
            'db_annual_income' => round($totalDBIncome, 2),
            'state_pension_income' => round($statePensionIncome, 2),
            'total_projected_income' => round($totalProjectedIncome, 2),
        ];
    }

    /**
     * Calculate income replacement ratio.
     *
     * @return float Ratio as percentage
     */
    public function calculateIncomeReplacementRatio(float $projectedIncome, float $currentIncome): float
    {
        if ($currentIncome <= 0) {
            return 0.0;
        }

        return round(($projectedIncome / $currentIncome) * 100, 2);
    }

    /**
     * Get user's current age from retirement profile or date of birth.
     */
    private function getUserAge(int $userId): int
    {
        $profile = \App\Models\RetirementProfile::where('user_id', $userId)->first();

        if ($profile && $profile->current_age) {
            return $profile->current_age;
        }

        $user = \App\Models\User::find($userId);
        if ($user && $user->date_of_birth) {
            return (int) $user->date_of_birth->diffInYears(now());
        }

        return 40; // Conservative fallback
    }

    /**
     * Get growth rate based on user's risk profile.
     *
     * Uses the expected return from the user's investment risk preference.
     * Falls back to default 5% if no risk profile is set.
     */
    private function getGrowthRateForUser(int $userId): float
    {
        $riskLevel = $this->getUserMainRiskLevel($userId);

        return $this->getGrowthRateForRiskLevel($riskLevel);
    }

    /**
     * Get growth rate for a specific DC pension.
     *
     * Priority:
     * 1. Pension's own risk_preference (if has_custom_risk is true)
     * 2. User's main risk level from Risk module
     * 3. Default 5%
     */
    private function getGrowthRateForPension(DCPension $pension, int $userId): float
    {
        // Check if pension has custom risk override
        if ($pension->has_custom_risk && $pension->risk_preference) {
            return $this->getGrowthRateForRiskLevel($pension->risk_preference);
        }

        // Fall back to user's main risk level
        return $this->getGrowthRateForUser($userId);
    }

    /**
     * Get the user's main risk level from the Risk module.
     */
    private function getUserMainRiskLevel(int $userId): string
    {
        $riskProfile = $this->riskService->getRiskProfile($userId);

        if ($riskProfile && $riskProfile['risk_level']) {
            return $riskProfile['risk_level'];
        }

        return 'medium'; // Default risk level
    }

    /**
     * Convert a risk level to a growth rate.
     */
    private function getGrowthRateForRiskLevel(string $riskLevel): float
    {
        $riskParams = $this->riskService->getReturnParameters($riskLevel);

        if ($riskParams && isset($riskParams['expected_return_typical'])) {
            return $riskParams['expected_return_typical'] / 100;
        }

        return self::DEFAULT_GROWTH_RATE;
    }

    /**
     * Calculate annual contribution for a DC pension.
     *
     * Handles two contribution methods:
     * 1. Fixed monthly amount (SIPP/personal pensions): monthly_contribution_amount × 12
     * 2. Percentage of salary (workplace pensions): salary × (employee% + employer%)
     *
     * @return float Annual contribution amount
     */
    private function calculateAnnualContribution(DCPension $pension): float
    {
        // Priority 1: Fixed monthly contribution (SIPP/personal pensions)
        $monthlyContribution = (float) ($pension->monthly_contribution_amount ?? 0.0);
        if ($monthlyContribution > 0) {
            return $monthlyContribution * 12;
        }

        // Priority 2: Percentage-based contributions (workplace pensions)
        $annualSalary = (float) ($pension->annual_salary ?? 0.0);
        if ($annualSalary > 0) {
            $employeePercent = (float) ($pension->employee_contribution_percent ?? 0.0);
            $employerPercent = (float) ($pension->employer_contribution_percent ?? 0.0);
            $totalPercent = $employeePercent + $employerPercent;

            if ($totalPercent > 0) {
                return $annualSalary * ($totalPercent / 100);
            }
        }

        // No contributions
        return 0.0;
    }
}
