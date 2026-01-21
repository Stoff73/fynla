<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\RetirementProfile;
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

        // Get risk parameters and track source
        $riskResult = $this->getUserRiskLevelWithSource($user);
        $riskLevel = $riskResult['level'];
        $riskSource = $riskResult['source'];
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
            'risk_source' => $riskSource,
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
     * Project individual DC pension pot growth using Monte Carlo simulation.
     */
    public function projectIndividualDCPension(int $pensionId, int $userId): array
    {
        $user = User::findOrFail($userId);
        $pension = $user->dcPensions()->findOrFail($pensionId);

        $currentAge = $user->date_of_birth?->age ?? 40;
        $retirementAge = $pension->retirement_age ?? $this->getRetirementAge($user);
        $yearsToRetirement = max(1, $retirementAge - $currentAge);

        $currentValue = (float) ($pension->current_fund_value ?? 0);
        $monthlyContribution = $this->calculateMonthlyContribution($pension);

        // Get risk parameters - use pension's risk preference if set, otherwise user's
        $riskSource = 'default';
        if ($pension->risk_preference !== null) {
            $riskLevel = $pension->risk_preference;
            $riskSource = 'profile';
        } else {
            $riskResult = $this->getUserRiskLevelWithSource($user);
            $riskLevel = $riskResult['level'];
            $riskSource = $riskResult['source'];
        }
        $riskParams = $this->riskService->getReturnParameters($riskLevel);

        $expectedReturn = $riskParams['expected_return_typical'] / 100;
        $volatility = $riskParams['volatility'] / 100;

        // Run Monte Carlo simulation
        $simulation = $this->simulator->simulate(
            $currentValue,
            $monthlyContribution,
            $expectedReturn,
            $volatility,
            $yearsToRetirement,
            self::MONTE_CARLO_ITERATIONS
        );

        $yearByYear = $this->extractProbabilityBands($simulation, $yearsToRetirement);
        $lastYear = $yearByYear[count($yearByYear) - 1] ?? [];

        return [
            'pension_id' => $pensionId,
            'scheme_name' => $pension->scheme_name,
            'current_value' => round($currentValue, 2),
            'monthly_contribution' => round($monthlyContribution, 2),
            'risk_level' => $riskLevel,
            'risk_source' => $riskSource,
            'expected_return' => $riskParams['expected_return_typical'],
            'volatility' => $riskParams['volatility'],
            'years_to_retirement' => $yearsToRetirement,
            'retirement_age' => $retirementAge,
            'current_age' => $currentAge,
            'percentile_5_at_retirement' => round($lastYear['percentile_5'] ?? $currentValue, 2),
            'median_at_retirement' => round($lastYear['percentile_50'] ?? $currentValue, 2),
            'year_by_year' => $yearByYear,
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

        // Get target income from profile, or 75% of current after-tax income as fallback
        $currentNetIncome = $this->getCurrentNetIncome($user);
        $targetIncome = $this->getTargetRetirementIncome($user, $currentNetIncome);

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
        // Probability is based primarily on income coverage (can you meet your target?)
        $firstYearIncome = $yearlyIncome[0]['total_income'] ?? 0;
        $probability = $this->calculateRetirementProbability(
            $firstYearIncome,
            $targetIncome,
            $yearsBeforeDepletion,
            self::END_AGE - $retirementAge + 1
        );
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

        // Get target income from profile, or 75% of current after-tax income as fallback
        $currentNetIncome = $this->getCurrentNetIncome($user);
        $targetIncome = $this->getTargetRetirementIncome($user, $currentNetIncome);

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
        $startValue = $simulation['summary']['start_value'] ?? 0;

        // Add year 0 (current year) with current value - all bands start at the same point
        $result[] = [
            'year' => $currentYear,
            'year_number' => 0,
            'percentile_5' => round($startValue, 2),
            'percentile_10' => round($startValue, 2),
            'percentile_15' => round($startValue, 2),
            'percentile_20' => round($startValue, 2),
            'percentile_50' => round($startValue, 2),
            'percentile_75' => round($startValue, 2),
            'percentile_90' => round($startValue, 2),
        ];

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

            // Smooth transition for early years - blend with start value
            // Year 1: 70% Monte Carlo, 30% start value
            // Year 2: 90% Monte Carlo, 10% start value
            // Year 3+: 100% Monte Carlo
            $blendFactor = 1.0;
            if ($yearIndex === 1) {
                $blendFactor = 0.7;
            } elseif ($yearIndex === 2) {
                $blendFactor = 0.9;
            }

            $p5 = $this->blendValue($p5, $startValue, $blendFactor);
            $p10 = $this->blendValue($p10, $startValue, $blendFactor);
            $p15 = $this->blendValue($p15, $startValue, $blendFactor);
            $p20 = $this->blendValue($p20, $startValue, $blendFactor);
            $p50 = $this->blendValue($p50, $startValue, $blendFactor);
            $p75 = $this->blendValue($p75, $startValue, $blendFactor);
            $p90 = $this->blendValue($p90, $startValue, $blendFactor);

            $result[] = [
                'year' => $currentYear + $yearIndex,
                'year_number' => $yearIndex,
                'percentile_5' => round(max(0, $p5), 2),
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
     * Blend a Monte Carlo value with the start value for smooth transitions.
     */
    private function blendValue(float $monteCarloValue, float $startValue, float $blendFactor): float
    {
        return ($monteCarloValue * $blendFactor) + ($startValue * (1 - $blendFactor));
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
     * Get target retirement income from profile, or calculate from current income.
     */
    private function getTargetRetirementIncome(User $user, float $currentNetIncome): float
    {
        // Check for target_retirement_income in RetirementProfile
        $profile = RetirementProfile::where('user_id', $user->id)->first();
        if ($profile && $profile->target_retirement_income) {
            return (float) $profile->target_retirement_income;
        }

        // Fallback to 75% of current after-tax income
        return $currentNetIncome * self::TARGET_INCOME_PERCENT;
    }

    /**
     * Get user's risk level, defaulting to medium if not set.
     */
    private function getUserRiskLevel(User $user): string
    {
        return $this->getUserRiskLevelWithSource($user)['level'];
    }

    /**
     * Get user's risk level with source tracking.
     *
     * @return array{level: string, source: string}
     */
    private function getUserRiskLevelWithSource(User $user): array
    {
        // Check risk profile via service
        $riskProfile = $this->riskService->getRiskProfile($user->id);
        if ($riskProfile && isset($riskProfile['risk_level'])) {
            return [
                'level' => $riskProfile['risk_level'],
                'source' => 'profile',
            ];
        }

        // Check DC pensions for custom risk
        foreach ($user->dcPensions as $pension) {
            if ($pension->risk_preference) {
                return [
                    'level' => $pension->risk_preference,
                    'source' => 'profile',
                ];
            }
        }

        return [
            'level' => 'medium',
            'source' => 'default',
        ];
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
     * Calculate retirement probability based on income coverage.
     *
     * The probability is primarily based on whether projected income meets
     * the target income. A user who can only draw 30% of their target income
     * is NOT on track, regardless of how long the fund lasts.
     *
     * Income Coverage Scoring:
     * - 100%+ of target: 95% probability (Excellent)
     * - 90-99% of target: 85% probability (On Track)
     * - 75-89% of target: 65% probability (Needs Attention)
     * - 50-74% of target: 40% probability (Off Track)
     * - 25-49% of target: 20% probability (Significantly Off Track)
     * - Below 25%: 10% probability (Critical)
     *
     * Fund longevity adjustment: +5% if fund lasts to age 90+
     */
    private function calculateRetirementProbability(
        float $projectedIncome,
        float $targetIncome,
        int $yearsBeforeDepletion,
        int $totalYears
    ): float {
        // Primary metric: Income coverage ratio
        // If no target income set but projected income exists, consider fully covered
        $incomeRatio = $targetIncome > 0 ? $projectedIncome / $targetIncome : ($projectedIncome > 0 ? 1.0 : 0);

        // Base probability from income coverage
        if ($incomeRatio >= 1.0) {
            $baseProbability = 95;  // Meeting or exceeding target
        } elseif ($incomeRatio >= 0.90) {
            $baseProbability = 85;  // Within 10% of target
        } elseif ($incomeRatio >= 0.75) {
            $baseProbability = 65;  // 75-90% of target
        } elseif ($incomeRatio >= 0.50) {
            $baseProbability = 40;  // 50-75% of target
        } elseif ($incomeRatio >= 0.25) {
            $baseProbability = 20;  // 25-50% of target
        } else {
            $baseProbability = 10;  // Less than 25% of target
        }

        // Small bonus for fund longevity (max 5%)
        // Fund lasting 25+ years adds confidence
        $longevityBonus = 0;
        if ($yearsBeforeDepletion >= 35) {
            $longevityBonus = 5;
        } elseif ($yearsBeforeDepletion >= 25) {
            $longevityBonus = 3;
        }

        return min(100, round($baseProbability + $longevityBonus, 0));
    }

    /**
     * Determine on-track status based on probability.
     * Thresholds aligned with income coverage ratios.
     */
    private function determineOnTrackStatus(float $probability): string
    {
        if ($probability >= 90) {
            return 'Excellent';        // 100%+ of target income
        }
        if ($probability >= 80) {
            return 'On Track';         // 90%+ of target income
        }
        if ($probability >= 60) {
            return 'Needs Attention';  // 75-90% of target income
        }
        if ($probability >= 35) {
            return 'Off Track';        // 50-75% of target income
        }
        if ($probability >= 15) {
            return 'Significantly Off Track';  // 25-50% of target income
        }

        return 'Critical';  // Less than 25% of target income
    }
}
