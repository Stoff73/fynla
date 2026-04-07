<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\User;
use App\Services\Goals\LifeEventCashFlowService;
use App\Services\Investment\MonteCarloSimulator;
use App\Services\Risk\RiskPreferenceService;
use App\Services\TaxConfigService;

/**
 * Retirement Projection Service
 *
 * Provides Monte Carlo projections for DC pensions and income drawdown analysis.
 * Results are cached for 24 hours via MonteCarloSimulator.
 */
class RetirementProjectionService
{
    private const DEFAULT_RETIREMENT_AGE = 67;

    public function __construct(
        private readonly MonteCarloSimulator $simulator,
        private readonly RiskPreferenceService $riskService,
        private readonly TaxConfigService $taxConfig,
        private readonly LifeEventCashFlowService $lifeEventCashFlowService,
        private readonly \App\Services\Cache\CacheInvalidationService $cacheInvalidation,
        private readonly RequiredCapitalCalculator $requiredCapitalCalculator
    ) {}

    /**
     * Get complete retirement projections including pot growth and income drawdown.
     * Monte Carlo results are cached for 24 hours via the simulator.
     */
    public function getProjections(int $userId): array
    {
        $user = User::with(['dcPensions', 'dbPensions', 'statePension'])
            ->findOrFail($userId);

        $potProjection = $this->projectPensionPot($user);
        $incomeDrawdown = $this->projectIncomeDrawdown($user, $potProjection);
        $targetIncomeDrawdown = $this->projectTargetIncomeDrawdown($user, $potProjection);

        // Get life events applied to projections (cover both accumulation and decumulation)
        $currentAge = $potProjection['current_age'];
        $endAge = (int) $this->taxConfig->get('retirement.projection_end_age', 100);
        $totalProjectionYears = $endAge - $currentAge;
        $appliedEvents = $this->lifeEventCashFlowService->getAppliedEvents(
            $userId,
            'retirement',
            $totalProjectionYears
        );

        return [
            'pension_pot_projection' => $potProjection,
            'income_drawdown' => $incomeDrawdown,
            'target_income_drawdown' => $targetIncomeDrawdown,
            'life_events_applied' => $appliedEvents,
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

        $expectedReturn = $riskParams['expected_return_typical'] / 100;
        $volatility = $riskParams['volatility'] / 100;

        // Build life event cash flow map for MC injection
        $scheduledInjections = $this->lifeEventCashFlowService->buildCashFlowMap(
            $user->id,
            'retirement',
            $yearsToRetirement
        );
        $eventHash = $this->lifeEventCashFlowService->getEventHash($user->id, 'retirement');

        // Cache key includes event hash so changes to life events invalidate cache
        $cacheKey = "user_{$user->id}_pension_pot_{$yearsToRetirement}y_e{$eventHash}";

        // Run Monte Carlo simulation (cached) with life event injections
        $simulation = $this->simulator->simulate(
            $totalCurrentValue,
            $totalMonthlyContribution,
            $expectedReturn,
            $volatility,
            $yearsToRetirement,
            (int) $this->taxConfig->get('retirement.monte_carlo_iterations', 1000),
            $cacheKey,
            $scheduledInjections
        );

        // Extract year-by-year data with custom percentiles for probability bands
        $yearByYear = $this->extractProbabilityBands($simulation, $yearsToRetirement);

        // Get values at retirement (last year's percentiles)
        // Using 80% probability (20th percentile) for conservative projections
        $lastYear = $yearByYear[count($yearByYear) - 1] ?? [];
        $percentile20AtRetirement = $lastYear['percentile_20'] ?? $totalCurrentValue;
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
            'percentile_20_at_retirement' => round($percentile20AtRetirement, 2),
            'median_at_retirement' => round($medianAtRetirement, 2),
            'year_by_year' => $yearByYear,
            'dc_pension_count' => $user->dcPensions->count(),
        ];
    }

    /**
     * Project individual DC pension pot growth using Monte Carlo simulation.
     * Results are cached for 24 hours via MonteCarloSimulator.
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

        // Cache key for individual pension projection
        $cacheKey = "user_{$userId}_pension_{$pensionId}_{$yearsToRetirement}y";

        // Run Monte Carlo simulation (cached)
        $simulation = $this->simulator->simulate(
            $currentValue,
            $monthlyContribution,
            $expectedReturn,
            $volatility,
            $yearsToRetirement,
            (int) $this->taxConfig->get('retirement.monte_carlo_iterations', 1000),
            $cacheKey
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
            'percentile_20_at_retirement' => round($lastYear['percentile_20'] ?? $currentValue, 2),
            'median_at_retirement' => round($lastYear['percentile_50'] ?? $currentValue, 2),
            'year_by_year' => $yearByYear,
        ];
    }

    /**
     * Invalidate cached retirement projections for a user.
     */
    public function invalidateRetirementProjections(int $userId): void
    {
        $this->simulator->clearUserCache($userId);
    }

    /**
     * Invalidate cached DC pension projection.
     */
    public function invalidateDCPensionProjection(int $pensionId): void
    {
        // Handled by clearUserCache when user updates pension
    }

    /**
     * Project income drawdown from retirement to age 100 using sustainable withdrawal rate.
     */
    public function projectIncomeDrawdown(User $user, array $potProjection): array
    {
        $retirementAge = $potProjection['retirement_age'];
        // Use 80% probability (20th percentile) for conservative drawdown projection
        $potAtRetirement = $potProjection['percentile_20_at_retirement'];

        // Get conservative growth rate during drawdown (use minimum expected return for risk level)
        $riskLevel = $potProjection['risk_level'];
        $riskParams = $this->riskService->getReturnParameters($riskLevel);
        $drawdownGrowthRate = $riskParams['expected_return_min'] / 100; // Conservative rate

        // Get guaranteed income sources
        $dbAnnualIncome = $this->getTotalDBIncome($user);
        $statePensionIncome = $this->getStatePensionIncome($user, $retirementAge);

        // Get target income from centralised RequiredCapitalCalculator (single source of truth)
        $requiredCapitalData = $this->requiredCapitalCalculator->calculate($user->id);
        $targetIncome = (float) $requiredCapitalData['required_income'];
        $currentNetIncome = $targetIncome; // For display purposes

        $endAge = (int) $this->taxConfig->get('retirement.projection_end_age', 100);
        $sustainableWithdrawalRate = (float) $this->taxConfig->get('retirement.withdrawal_rates.sustainable', 0.047);
        $inflationRate = (float) $this->taxConfig->get('assumptions.inflation', 0.025);

        // Get life event cash flows for the drawdown period (age-indexed)
        $drawdownCashFlows = $this->lifeEventCashFlowService->buildDrawdownCashFlowMap(
            $user->id,
            $retirementAge,
            $endAge
        );

        // Calculate year-by-year income from retirement to end age
        $yearlyIncome = [];
        $remainingFund = $potAtRetirement;
        $yearsAboveTarget = 0;
        $yearsBeforeDepletion = 0;
        $fundDepletionAge = null;
        $currentTargetIncome = $targetIncome;

        for ($age = $retirementAge; $age <= $endAge; $age++) {
            // Apply life event cash flows for this age
            $lifeEventImpact = $drawdownCashFlows[$age] ?? 0;
            if ($lifeEventImpact != 0) {
                $remainingFund += $lifeEventImpact;
                $remainingFund = max(0, $remainingFund);
            }

            // Calculate DC drawdown using sustainable withdrawal rate
            $dcDrawdown = $remainingFund > 0 ? $remainingFund * $sustainableWithdrawalRate : 0;

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
                'life_event_impact' => round($lifeEventImpact, 2),
            ];

            // Apply growth then reduce fund by drawdown
            $remainingFund = $remainingFund * (1 + $drawdownGrowthRate) - $dcDrawdown;
            $remainingFund = max(0, $remainingFund);

            // Inflate target for next year
            $currentTargetIncome *= (1 + $inflationRate);
        }

        // Calculate on-track status and probability
        $firstYearIncome = $yearlyIncome[0]['total_income'] ?? 0;
        $probability = $this->calculateRetirementProbability(
            $firstYearIncome,
            $targetIncome,
            $yearsBeforeDepletion,
            $endAge - $retirementAge + 1
        );
        $onTrackStatus = $this->determineOnTrackStatus($probability);

        return [
            'starting_pot' => round($potAtRetirement, 2),
            'target_income' => round($targetIncome, 2),
            'current_net_income' => round($currentNetIncome, 2),
            'retirement_age' => $retirementAge,
            'withdrawal_rate' => $sustainableWithdrawalRate * 100,
            'inflation_rate' => $inflationRate * 100,
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
        $potAtRetirement = $potProjection['percentile_20_at_retirement'];

        // Get conservative growth rate during drawdown
        $riskLevel = $potProjection['risk_level'];
        $riskParams = $this->riskService->getReturnParameters($riskLevel);
        $drawdownGrowthRate = $riskParams['expected_return_min'] / 100;

        // Get guaranteed income sources
        $dbAnnualIncome = $this->getTotalDBIncome($user);
        $statePensionIncome = $this->getStatePensionIncome($user, $retirementAge);

        // Get target income from centralised RequiredCapitalCalculator (single source of truth)
        $requiredCapitalData = $this->requiredCapitalCalculator->calculate($user->id);
        $targetIncome = (float) $requiredCapitalData['required_income'];

        $endAge = (int) $this->taxConfig->get('retirement.projection_end_age', 100);
        $inflationRate = (float) $this->taxConfig->get('assumptions.inflation', 0.025);

        // Get life event cash flows for the drawdown period (age-indexed)
        $drawdownCashFlows = $this->lifeEventCashFlowService->buildDrawdownCashFlowMap(
            $user->id,
            $retirementAge,
            $endAge
        );

        // Calculate year-by-year income drawing target amount
        $yearlyIncome = [];
        $remainingFund = $potAtRetirement;
        $fundDepletionAge = null;
        $currentTargetIncome = $targetIncome;

        for ($age = $retirementAge; $age <= $endAge; $age++) {
            // Apply life event cash flows for this age
            $lifeEventImpact = $drawdownCashFlows[$age] ?? 0;
            if ($lifeEventImpact != 0) {
                $remainingFund += $lifeEventImpact;
                $remainingFund = max(0, $remainingFund);
            }

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
                'life_event_impact' => round($lifeEventImpact, 2),
            ];

            // Apply growth then reduce fund by drawdown
            $remainingFund = $remainingFund * (1 + $drawdownGrowthRate) - $dcDrawdown;
            $remainingFund = max(0, $remainingFund);

            // Inflate target for next year
            $currentTargetIncome *= (1 + $inflationRate);
        }

        // Calculate years the fund lasts
        $yearsFunded = $fundDepletionAge ? $fundDepletionAge - $retirementAge : $endAge - $retirementAge + 1;

        return [
            'starting_pot' => round($potAtRetirement, 2),
            'target_income' => round($targetIncome, 2),
            'retirement_age' => $retirementAge,
            'inflation_rate' => $inflationRate * 100,
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
     */
    private function extractProbabilityBands(array $simulation, int $years): array
    {
        $result = [];
        $currentYear = (int) date('Y');
        $startValue = $simulation['summary']['start_value'] ?? 0;

        // Add year 0 (current year) with current value
        $result[] = [
            'year' => $currentYear,
            'year_number' => 0,
            'percentile_10' => round($startValue, 2),
            'percentile_15' => round($startValue, 2),
            'percentile_20' => round($startValue, 2),
            'percentile_25' => round($startValue, 2),
            'percentile_50' => round($startValue, 2),
            'percentile_75' => round($startValue, 2),
            'percentile_90' => round($startValue, 2),
        ];

        foreach ($simulation['year_by_year'] as $yearData) {
            $yearIndex = $yearData['year'];
            $percentiles = $yearData['percentiles'];

            $p10 = $this->getPercentileValue($percentiles, '10th');
            $p25 = $this->getPercentileValue($percentiles, '25th');
            $p50 = $this->getPercentileValue($percentiles, '50th');
            $p75 = $this->getPercentileValue($percentiles, '75th');
            $p90 = $this->getPercentileValue($percentiles, '90th');

            // Interpolate 15th and 20th percentiles between 10th and 25th
            $spread = $p25 - $p10;
            $p15 = $p10 + ($spread * 0.33);
            $p20 = $p10 + ($spread * 0.67);

            // Smooth transition for early years
            $blendFactor = 1.0;
            if ($yearIndex === 1) {
                $blendFactor = 0.7;
            } elseif ($yearIndex === 2) {
                $blendFactor = 0.9;
            }

            $p10 = $this->blendValue($p10, $startValue, $blendFactor);
            $p15 = $this->blendValue($p15, $startValue, $blendFactor);
            $p20 = $this->blendValue($p20, $startValue, $blendFactor);
            $p25 = $this->blendValue($p25, $startValue, $blendFactor);
            $p50 = $this->blendValue($p50, $startValue, $blendFactor);
            $p75 = $this->blendValue($p75, $startValue, $blendFactor);
            $p90 = $this->blendValue($p90, $startValue, $blendFactor);

            $result[] = [
                'year' => $currentYear + $yearIndex,
                'year_number' => $yearIndex,
                'percentile_10' => round($p10, 2),
                'percentile_15' => round($p15, 2),
                'percentile_20' => round($p20, 2),
                'percentile_25' => round($p25, 2),
                'percentile_50' => round($p50, 2),
                'percentile_75' => round($p75, 2),
                'percentile_90' => round($p90, 2),
            ];
        }

        return $result;
    }

    private function blendValue(float $monteCarloValue, float $startValue, float $blendFactor): float
    {
        return ($monteCarloValue * $blendFactor) + ($startValue * (1 - $blendFactor));
    }

    private function getPercentileValue(array $percentiles, string $key): float
    {
        foreach ($percentiles as $p) {
            if ($p['percentile'] === $key) {
                return (float) $p['value'];
            }
        }

        return 0.0;
    }

    private function getRetirementAge(User $user): int
    {
        if ($user->target_retirement_age) {
            return $user->target_retirement_age;
        }

        foreach ($user->dcPensions as $pension) {
            if ($pension->retirement_age) {
                return $pension->retirement_age;
            }
        }

        return self::DEFAULT_RETIREMENT_AGE;
    }

    private function getUserRiskLevel(User $user): string
    {
        return $this->getUserRiskLevelWithSource($user)['level'];
    }

    private function getUserRiskLevelWithSource(User $user): array
    {
        $riskProfile = $this->riskService->getRiskProfile($user->id);
        if ($riskProfile && isset($riskProfile['risk_level'])) {
            return [
                'level' => $riskProfile['risk_level'],
                'source' => 'profile',
            ];
        }

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

    private function calculateMonthlyContribution($pension): float
    {
        if ($pension->employee_contribution_percent && $pension->annual_salary) {
            $employeeMonthly = ($pension->annual_salary * $pension->employee_contribution_percent / 100) / 12;
            $employerMonthly = $pension->employer_contribution_percent
                ? ($pension->annual_salary * $pension->employer_contribution_percent / 100) / 12
                : 0;

            return $employeeMonthly + $employerMonthly;
        }

        return (float) ($pension->monthly_contribution_amount ?? 0);
    }

    private function getTotalDBIncome(User $user): float
    {
        $total = 0.0;
        foreach ($user->dbPensions as $pension) {
            $total += (float) ($pension->accrued_annual_pension ?? 0);
        }

        return $total;
    }

    private function getStatePensionIncome(User $user, int $retirementAge): float
    {
        if (! $user->statePension) {
            return 0.0;
        }

        return (float) ($user->statePension->state_pension_forecast_annual ?? 0);
    }

    private function calculateRetirementProbability(
        float $projectedIncome,
        float $targetIncome,
        int $yearsBeforeDepletion,
        int $totalYears
    ): float {
        $incomeRatio = $targetIncome > 0 ? $projectedIncome / $targetIncome : ($projectedIncome > 0 ? 1.0 : 0);

        if ($incomeRatio >= 1.0) {
            $baseProbability = 95;
        } elseif ($incomeRatio >= 0.90) {
            $baseProbability = 85;
        } elseif ($incomeRatio >= 0.75) {
            $baseProbability = 65;
        } elseif ($incomeRatio >= 0.50) {
            $baseProbability = 40;
        } elseif ($incomeRatio >= 0.25) {
            $baseProbability = 20;
        } else {
            $baseProbability = 10;
        }

        $longevityBonus = 0;
        if ($yearsBeforeDepletion >= 35) {
            $longevityBonus = 5;
        } elseif ($yearsBeforeDepletion >= 25) {
            $longevityBonus = 3;
        }

        return min(100, round($baseProbability + $longevityBonus, 0));
    }

    private function determineOnTrackStatus(float $probability): string
    {
        if ($probability >= 90) {
            return 'Excellent';
        }
        if ($probability >= 80) {
            return 'On Track';
        }
        if ($probability >= 60) {
            return 'Needs Attention';
        }
        if ($probability >= 35) {
            return 'Off Track';
        }
        if ($probability >= 15) {
            return 'Significantly Off Track';
        }

        return 'Critical';
    }

    /**
     * Invalidate retirement projection cache for a user.
     */
    public function invalidateCache(int $userId): void
    {
        $this->cacheInvalidation->invalidateForUser($userId);
    }
}
