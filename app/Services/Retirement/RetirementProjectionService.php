<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\User;
use App\Services\Investment\MonteCarloSimulator;
use App\Services\Risk\RiskPreferenceService;
use App\Services\UserProfile\UserProfileService;

/**
 * Retirement Projection Service
 *
 * Provides Monte Carlo projections for DC pensions and income drawdown analysis.
 */
class RetirementProjectionService
{
    private const DEFAULT_RETIREMENT_AGE = 65;
    private const SUSTAINABLE_WITHDRAWAL_RATE = 0.047; // 4.7%
    private const INFLATION_RATE = 0.02; // 2%
    private const TARGET_INCOME_PERCENT = 0.75; // 75% of current net income
    private const END_AGE = 100;
    private const MONTE_CARLO_ITERATIONS = 1000;

    public function __construct(
        private MonteCarloSimulator $simulator,
        private RiskPreferenceService $riskService,
        private UserProfileService $userProfileService
    ) {}

    /**
     * Get complete retirement projections including pot growth and income drawdown.
     */
    public function getProjections(int $userId): array
    {
        $user = User::with(['dcPensions', 'dbPensions', 'statePension'])
            ->findOrFail($userId);

        $potProjection = $this->projectPensionPot($user);
        $incomeDrawdown = $this->projectIncomeDrawdown($user, $potProjection);
        $targetIncomeDrawdown = $this->projectTargetIncomeDrawdown($user, $potProjection);

        return [
            'pension_pot_projection' => $potProjection,
            'income_drawdown' => $incomeDrawdown,
            'target_income_drawdown' => $targetIncomeDrawdown,
        ];
    }

    /**
     * Project pension pot growth using Monte Carlo simulation for DC pensions.
     */
    public function projectPensionPot(User $user): array
    {
        // Get user's current age
        $currentAge = $user->date_of_birth?->age ?? 40;

        // Get retirement age from user profile or DC pensions or default
        $retirementAge = $this->getRetirementAge($user);

        // Calculate years to retirement
        $yearsToRetirement = max(1, $retirementAge - $currentAge);

        // Aggregate DC pensions
        $totalCurrentValue = 0.0;
        $totalMonthlyContribution = 0.0;

        foreach ($user->dcPensions as $pension) {
            $totalCurrentValue += (float) ($pension->current_fund_value ?? 0);
            $totalMonthlyContribution += $this->calculateMonthlyContribution($pension);
        }

        // Get risk parameters
        $riskLevel = $this->getUserRiskLevel($user);
        $riskParams = $this->riskService->getReturnParameters($riskLevel);

        $expectedReturn = $riskParams['expected_return_typical'] / 100; // Convert percentage to decimal
        $volatility = $riskParams['volatility'] / 100;

        // Run Monte Carlo simulation
        $simulation = $this->simulator->simulate(
            $totalCurrentValue,
            $totalMonthlyContribution,
            $expectedReturn,
            $volatility,
            $yearsToRetirement,
            self::MONTE_CARLO_ITERATIONS
        );

        // Extract year-by-year data with custom percentiles for probability bands
        $yearByYear = $this->extractProbabilityBands($simulation, $yearsToRetirement);

        // Get values at retirement (last year's percentiles)
        $lastYear = $yearByYear[count($yearByYear) - 1] ?? [];
        $percentile5AtRetirement = $lastYear['percentile_5'] ?? $totalCurrentValue;
        $medianAtRetirement = $lastYear['percentile_50'] ?? $totalCurrentValue;

        return [
            'current_value' => round($totalCurrentValue, 2),
            'monthly_contribution' => round($totalMonthlyContribution, 2),
            'risk_level' => $riskLevel,
            'expected_return' => $riskParams['expected_return_typical'],
            'volatility' => $riskParams['volatility'],
            'years_to_retirement' => $yearsToRetirement,
            'retirement_age' => $retirementAge,
            'current_age' => $currentAge,
            'percentile_5_at_retirement' => round($percentile5AtRetirement, 2),
            'median_at_retirement' => round($medianAtRetirement, 2),
            'year_by_year' => $yearByYear,
            'dc_pension_count' => $user->dcPensions->count(),
        ];
    }

    /**
     * Project income drawdown from retirement to age 100 using sustainable withdrawal rate.
     */
    public function projectIncomeDrawdown(User $user, array $potProjection): array
    {
        $retirementAge = $potProjection['retirement_age'];
        // Use 95% probability (5th percentile) for conservative drawdown projection
        $potAtRetirement = $potProjection['percentile_5_at_retirement'];

        // Get conservative growth rate during drawdown (use minimum expected return for risk level)
        $riskLevel = $potProjection['risk_level'];
        $riskParams = $this->riskService->getReturnParameters($riskLevel);
        $drawdownGrowthRate = $riskParams['expected_return_min'] / 100; // Conservative rate

        // Get guaranteed income sources
        $dbAnnualIncome = $this->getTotalDBIncome($user);
        $statePensionIncome = $this->getStatePensionIncome($user, $retirementAge);

        // Get target income (75% of current after-tax income)
        $currentNetIncome = $this->getCurrentNetIncome($user);
        $targetIncome = $currentNetIncome * self::TARGET_INCOME_PERCENT;

        // Calculate year-by-year income from retirement to age 100
        $yearlyIncome = [];
        $remainingFund = $potAtRetirement;
        $yearsAboveTarget = 0;
        $yearsBeforeDepletion = 0;
        $fundDepletionAge = null;
        $currentTargetIncome = $targetIncome;

        for ($age = $retirementAge; $age <= self::END_AGE; $age++) {
            // Calculate DC drawdown (4.7% of remaining fund)
            $dcDrawdown = $remainingFund > 0 ? $remainingFund * self::SUSTAINABLE_WITHDRAWAL_RATE : 0;

            // State pension may start at a different age
            $statePensionThisYear = $age >= ($user->statePension?->state_pension_age ?? 67)
                ? $statePensionIncome
                : 0;

            // Total income for this year
            $totalIncome = $dcDrawdown + $dbAnnualIncome + $statePensionThisYear;

            // Check if above target
            $aboveTarget = $totalIncome >= $currentTargetIncome;
            if ($aboveTarget) {
                $yearsAboveTarget++;
            }

            // Track fund depletion
            if ($remainingFund > 0) {
                $yearsBeforeDepletion++;
            } elseif ($fundDepletionAge === null) {
                $fundDepletionAge = $age;
            }

            $yearlyIncome[] = [
                'age' => $age,
                'year' => date('Y') + ($age - ($user->date_of_birth?->age ?? 40)),
                'dc_drawdown' => round($dcDrawdown, 2),
                'db_income' => round($dbAnnualIncome, 2),
                'state_pension' => round($statePensionThisYear, 2),
                'total_income' => round($totalIncome, 2),
                'target_income' => round($currentTargetIncome, 2),
                'remaining_fund' => round(max(0, $remainingFund), 2),
                'above_target' => $aboveTarget,
            ];

            // Apply growth then reduce fund by drawdown
            $remainingFund = $remainingFund * (1 + $drawdownGrowthRate) - $dcDrawdown;
            $remainingFund = max(0, $remainingFund);

            // Inflate target for next year
            $currentTargetIncome *= (1 + self::INFLATION_RATE);
        }

        // Calculate on-track status and probability
        $totalYears = self::END_AGE - $retirementAge + 1;
        $probability = round(($yearsAboveTarget / $totalYears) * 100, 0);
        $onTrackStatus = $this->determineOnTrackStatus($probability);

        return [
            'starting_pot' => round($potAtRetirement, 2),
            'target_income' => round($targetIncome, 2),
            'current_net_income' => round($currentNetIncome, 2),
            'retirement_age' => $retirementAge,
            'withdrawal_rate' => self::SUSTAINABLE_WITHDRAWAL_RATE * 100,
            'inflation_rate' => self::INFLATION_RATE * 100,
            'growth_rate' => round($drawdownGrowthRate * 100, 1),
            'on_track_status' => $onTrackStatus,
            'probability' => $probability,
            'fund_depletion_age' => $fundDepletionAge,
            'years_funded' => $yearsBeforeDepletion,
            'guaranteed_income' => [
                'db_pensions' => round($dbAnnualIncome, 2),
                'state_pension' => round($statePensionIncome, 2),
                'total' => round($dbAnnualIncome + $statePensionIncome, 2),
            ],
            'yearly_income' => $yearlyIncome,
        ];
    }

    /**
     * Project target income drawdown - draws full target income until fund depletes.
     */
    public function projectTargetIncomeDrawdown(User $user, array $potProjection): array
    {
        $retirementAge = $potProjection['retirement_age'];
        $potAtRetirement = $potProjection['percentile_5_at_retirement'];

        // Get conservative growth rate during drawdown
        $riskLevel = $potProjection['risk_level'];
        $riskParams = $this->riskService->getReturnParameters($riskLevel);
        $drawdownGrowthRate = $riskParams['expected_return_min'] / 100;

        // Get guaranteed income sources
        $dbAnnualIncome = $this->getTotalDBIncome($user);
        $statePensionIncome = $this->getStatePensionIncome($user, $retirementAge);

        // Get target income (75% of current after-tax income)
        $currentNetIncome = $this->getCurrentNetIncome($user);
        $targetIncome = $currentNetIncome * self::TARGET_INCOME_PERCENT;

        // Calculate year-by-year income drawing target amount
        $yearlyIncome = [];
        $remainingFund = $potAtRetirement;
        $fundDepletionAge = null;
        $currentTargetIncome = $targetIncome;

        for ($age = $retirementAge; $age <= self::END_AGE; $age++) {
            // State pension may start at a different age
            $statePensionThisYear = $age >= ($user->statePension?->state_pension_age ?? 67)
                ? $statePensionIncome
                : 0;

            // Calculate how much DC drawdown is needed to reach target
            $incomeFromGuaranteed = $dbAnnualIncome + $statePensionThisYear;
            $dcNeeded = max(0, $currentTargetIncome - $incomeFromGuaranteed);

            // Draw what we can from the fund
            $dcDrawdown = min($dcNeeded, $remainingFund);
            $fundDepleted = $remainingFund <= 0;

            // Total income for this year
            $totalIncome = $dcDrawdown + $incomeFromGuaranteed;

            // Track fund depletion
            if ($remainingFund <= 0 && $fundDepletionAge === null) {
                $fundDepletionAge = $age;
            }

            $yearlyIncome[] = [
                'age' => $age,
                'year' => date('Y') + ($age - ($user->date_of_birth?->age ?? 40)),
                'dc_drawdown' => round($dcDrawdown, 2),
                'db_income' => round($dbAnnualIncome, 2),
                'state_pension' => round($statePensionThisYear, 2),
                'total_income' => round($totalIncome, 2),
                'target_income' => round($currentTargetIncome, 2),
                'remaining_fund' => round(max(0, $remainingFund), 2),
                'fund_depleted' => $fundDepleted,
            ];

            // Apply growth then reduce fund by drawdown
            $remainingFund = $remainingFund * (1 + $drawdownGrowthRate) - $dcDrawdown;
            $remainingFund = max(0, $remainingFund);

            // Inflate target for next year
            $currentTargetIncome *= (1 + self::INFLATION_RATE);
        }

        // Calculate years the fund lasts
        $yearsFunded = $fundDepletionAge ? $fundDepletionAge - $retirementAge : self::END_AGE - $retirementAge + 1;

        return [
            'starting_pot' => round($potAtRetirement, 2),
            'target_income' => round($targetIncome, 2),
            'retirement_age' => $retirementAge,
            'inflation_rate' => self::INFLATION_RATE * 100,
            'growth_rate' => round($drawdownGrowthRate * 100, 1),
            'fund_depletion_age' => $fundDepletionAge,
            'years_funded' => $yearsFunded,
            'guaranteed_income' => [
                'db_pensions' => round($dbAnnualIncome, 2),
                'state_pension' => round($statePensionIncome, 2),
                'total' => round($dbAnnualIncome + $statePensionIncome, 2),
            ],
            'yearly_income' => $yearlyIncome,
        ];
    }

    /**
     * Extract probability bands from Monte Carlo results.
     * Returns 5th, 10th, 15th, 20th, 50th percentiles for each year.
     */
    private function extractProbabilityBands(array $simulation, int $years): array
    {
        $result = [];
        $currentYear = (int) date('Y');

        foreach ($simulation['year_by_year'] as $yearData) {
            $yearIndex = $yearData['year'];
            $percentiles = $yearData['percentiles'];

            // Map the standard percentiles and interpolate for 5th/15th
            $p10 = $this->getPercentileValue($percentiles, '10th');
            $p25 = $this->getPercentileValue($percentiles, '25th');
            $p50 = $this->getPercentileValue($percentiles, '50th');
            $p75 = $this->getPercentileValue($percentiles, '75th');
            $p90 = $this->getPercentileValue($percentiles, '90th');

            // Estimate 5th and 15th/20th percentiles by interpolation
            // For p5, extrapolate below p10 using the spread between p10 and p25
            $spread = $p25 - $p10;
            $p5 = $p10 - ($spread * 0.33);
            $p15 = $p10 + ($spread * 0.33);
            $p20 = $p10 + ($spread * 0.67);

            $result[] = [
                'year' => $currentYear + $yearIndex,
                'year_number' => $yearIndex,
                'percentile_5' => round($p5, 2),
                'percentile_10' => round($p10, 2),
                'percentile_15' => round($p15, 2),
                'percentile_20' => round($p20, 2),
                'percentile_50' => round($p50, 2),
                'percentile_75' => round($p75, 2),
                'percentile_90' => round($p90, 2),
            ];
        }

        return $result;
    }

    /**
     * Get percentile value from array by percentile key.
     */
    private function getPercentileValue(array $percentiles, string $key): float
    {
        foreach ($percentiles as $p) {
            if ($p['percentile'] === $key) {
                return (float) $p['value'];
            }
        }

        return 0.0;
    }

    /**
     * Get user's retirement age from profile or DC pensions.
     */
    private function getRetirementAge(User $user): int
    {
        // Check user's target retirement age field
        if ($user->target_retirement_age) {
            return $user->target_retirement_age;
        }

        // Check DC pensions for retirement age
        foreach ($user->dcPensions as $pension) {
            if ($pension->retirement_age) {
                return $pension->retirement_age;
            }
        }

        return self::DEFAULT_RETIREMENT_AGE;
    }

    /**
     * Get user's risk level, defaulting to medium if not set.
     */
    private function getUserRiskLevel(User $user): string
    {
        // Check risk profile via service
        $riskProfile = $this->riskService->getRiskProfile($user->id);
        if ($riskProfile && isset($riskProfile['risk_level'])) {
            return $riskProfile['risk_level'];
        }

        // Check DC pensions for custom risk
        foreach ($user->dcPensions as $pension) {
            if ($pension->risk_preference) {
                return $pension->risk_preference;
            }
        }

        return 'medium';
    }

    /**
     * Calculate monthly contribution for a DC pension.
     */
    private function calculateMonthlyContribution($pension): float
    {
        // For occupational pensions with percentage-based contributions
        if ($pension->employee_contribution_percent && $pension->annual_salary) {
            $employeeMonthly = ($pension->annual_salary * $pension->employee_contribution_percent / 100) / 12;
            $employerMonthly = $pension->employer_contribution_percent
                ? ($pension->annual_salary * $pension->employer_contribution_percent / 100) / 12
                : 0;

            return $employeeMonthly + $employerMonthly;
        }

        // For SIPPs and personal pensions with fixed monthly amounts
        return (float) ($pension->monthly_contribution_amount ?? 0);
    }

    /**
     * Get total annual income from DB pensions.
     */
    private function getTotalDBIncome(User $user): float
    {
        $total = 0.0;
        foreach ($user->dbPensions as $pension) {
            $total += (float) ($pension->accrued_annual_pension ?? 0);
        }

        return $total;
    }

    /**
     * Get state pension annual income.
     */
    private function getStatePensionIncome(User $user, int $retirementAge): float
    {
        if (! $user->statePension) {
            return 0.0;
        }

        return (float) ($user->statePension->state_pension_forecast_annual ?? 0);
    }

    /**
     * Get user's current after-tax income.
     */
    private function getCurrentNetIncome(User $user): float
    {
        $profile = $this->userProfileService->getCompleteProfile($user);

        return (float) ($profile['income_occupation']['net_income'] ?? 0);
    }

    /**
     * Determine on-track status based on probability.
     */
    private function determineOnTrackStatus(float $probability): string
    {
        if ($probability >= 90) {
            return 'Excellent';
        }
        if ($probability >= 75) {
            return 'On Track';
        }
        if ($probability >= 50) {
            return 'Needs Attention';
        }
        if ($probability >= 25) {
            return 'Off Track';
        }

        return 'Significantly Off Track';
    }
}
