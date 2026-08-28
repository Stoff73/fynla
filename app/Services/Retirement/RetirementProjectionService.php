<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\User;
use App\Services\Cache\CacheInvalidationService;
use App\Services\Goals\LifeEventCashFlowService;
use App\Services\Investment\MonteCarloSimulator;
use App\Services\Risk\RiskPreferenceService;
use App\Services\Shared\MonteCarloEngine;
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
        private readonly CacheInvalidationService $cacheInvalidation,
        private readonly RequiredCapitalCalculator $requiredCapitalCalculator,
        // W-0482 — `unusedDcFundAtAge()` subtracts exactly what
        // `HouseholdCashFlowProjector` credits, and this is where that figure comes
        // from. Reading the same source is what makes it a complement rather than a
        // third opinion about the same pension.
        private readonly PensionProjector $pensionProjector
    ) {}

    /**
     * Get complete retirement projections including pot growth and income drawdown.
     * Monte Carlo results are cached for 24 hours via the simulator.
     */
    public function getProjections(int $userId): array
    {
        $user = User::with(['dcPensions', 'dbPensions', 'statePension', 'retirementProfile'])
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
        $currentAgeSource = $user->date_of_birth ? 'date_of_birth' : 'assumed';

        // Get retirement age from user profile or DC pensions or default
        $retirementAgeResult = $this->getRetirementAgeWithSource($user);
        $retirementAge = $retirementAgeResult['age'];

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

        // Name whose projection this is; MonteCarloSimulator::simulate() appends the
        // fingerprint of the simulation's inputs, so the key is self-invalidating.
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
            $scheduledInjections,
            MonteCarloEngine::BAND_PERCENTILES
        );

        // Extract year-by-year data with custom percentiles for probability bands
        $yearByYear = $this->extractProbabilityBands($simulation);

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
            'retirement_age_source' => $retirementAgeResult['source'],
            'current_age' => $currentAge,
            'current_age_source' => $currentAgeSource,
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
        $retirementAge = $this->getRetirementAge($user);
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

        // Input fingerprinting is applied by MonteCarloSimulator::simulate().
        $cacheKey = "user_{$userId}_pension_{$pensionId}_{$yearsToRetirement}y";

        // Run Monte Carlo simulation (cached)
        $simulation = $this->simulator->simulate(
            $currentValue,
            $monthlyContribution,
            $expectedReturn,
            $volatility,
            $yearsToRetirement,
            (int) $this->taxConfig->get('retirement.monte_carlo_iterations', 1000),
            $cacheKey,
            [],
            MonteCarloEngine::BAND_PERCENTILES
        );

        $yearByYear = $this->extractProbabilityBands($simulation);
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
     * What is LEFT of the defined contribution fund at a given age — W-0482.
     *
     * The estate needs this and cannot get it from the pot value. From the configured
     * effective date an **unused** pension fund forms part of the estate for Inheritance
     * Tax, and "unused" is the whole of the question.
     *
     * **This is an accounting complement, not a second drawdown model, and that is the
     * point.** `HouseholdCashFlowProjector` already turns this pension into income and
     * carries it in `projected_cash`, so the estate has ALREADY counted part of the fund.
     * What is left to count is the rest of it:
     *
     *     residual = max(0, grown fund at death − pension income already credited)
     *
     * The first version of this read `projectTargetIncomeDrawdown()`'s `remaining_fund`
     * and the `tax-compliance-reviewer` gate rejected it, correctly. That model depletes;
     * `HouseholdCashFlowProjector` reads `PensionProjector::projectTotalRetirementIncome()`,
     * which is a **perpetuity** — `pot × safe withdrawal rate`, credited every retired
     * year, with the fund never reduced. Two models that disagree about whether the money
     * was spent, both feeding one estate figure. Worst case, where a defined benefit
     * pension and the State Pension already meet the target, the drawdown model draws
     * nothing, the residual is the WHOLE grown pot, and cash has separately been credited
     * 4% of it for thirty years. The estate carried the pension roughly twice.
     *
     * Subtracting what the estate has already counted cannot do that, whatever either
     * model does: the pension contributes the grown fund and no more. **Drawdown is now
     * modelled zero times here rather than twice** — the cash projector's income IS the
     * drawdown, and this is its complement.
     *
     * What it does NOT fix: the perpetuity itself over-credits `projected_cash` for a
     * fund that never shrinks. That is W-0512, and it inflates cash rather than the
     * pension term — this method floors at zero and adds nothing once the credited income
     * has exhausted the fund.
     *
     * Three regimes, named in `basis` rather than collapsed into a number the caller has
     * to interpret:
     *
     *  - **before retirement** — nothing has been credited, so the whole projected pot is
     *    unused. Read from the accumulation path at the 20th percentile.
     *  - **after retirement** — the grown fund less the credited income.
     *  - **exhausted** — the credited income has already met or exceeded the fund, so the
     *    estate adds nothing.
     *
     * @param  float  $inflationRate  the rate `HouseholdCashFlowProjector` inflates its
     *                                credited income by. Passed in rather than read here,
     *                                because the two figures must use ONE rate or the
     *                                complement does not reconcile.
     * @return array{amount: float, basis: string, credited: float, grown_fund: float}
     */
    public function unusedDcFundAtAge(User $user, int $ageAtDeath, float $inflationRate): array
    {
        $user->loadMissing(['dcPensions', 'dbPensions', 'statePension', 'retirementProfile']);

        $pot = $this->projectPensionPot($user);

        if (($pot['dc_pension_count'] ?? 0) === 0) {
            return ['amount' => 0.0, 'basis' => 'no_pension', 'credited' => 0.0, 'grown_fund' => 0.0];
        }

        $currentAge = (int) $pot['current_age'];
        $retirementAge = (int) $pot['retirement_age'];

        if ($ageAtDeath <= $currentAge) {
            return [
                'amount' => (float) $pot['current_value'],
                'basis' => 'today',
                'credited' => 0.0,
                'grown_fund' => (float) $pot['current_value'],
            ];
        }

        if ($ageAtDeath < $retirementAge) {
            // `year_by_year[0]` is today, so the row for an age is its distance from now.
            // Nothing has been credited to cash yet: the projector pays retirement income
            // only from the retirement age.
            $row = $pot['year_by_year'][$ageAtDeath - $currentAge] ?? null;
            $fund = (float) ($row['percentile_20'] ?? $pot['percentile_20_at_retirement']);

            return [
                'amount' => $fund,
                'basis' => 'pre_retirement_growth',
                'credited' => 0.0,
                'grown_fund' => $fund,
            ];
        }

        // The fund keeps growing through retirement at the conservative rate the drawdown
        // uses, so the two halves of this subtraction are struck on the same basis.
        $riskParams = $this->riskService->getReturnParameters($pot['risk_level']);
        $growthRate = (float) $riskParams['expected_return_min'] / 100;
        $yearsRetired = $ageAtDeath - $retirementAge;

        $grownFund = (float) $pot['percentile_20_at_retirement'] * pow(1 + $growthRate, $yearsRetired);

        // Exactly what `HouseholdCashFlowProjector` credits, and no more: it deflates the
        // projected annual income to today's money and re-inflates it year by year, so at
        // the retirement age the credited amount is the nominal figure and it grows with
        // inflation from there. Summing the same series is what makes this a complement
        // rather than a third opinion.
        $annualIncome = (float) $this->pensionProjector->projectTotalRetirementIncome($user->id)['dc_annual_income'];

        $credited = 0.0;
        for ($year = 0; $year <= $yearsRetired; $year++) {
            $credited += $annualIncome * pow(1 + $inflationRate, $year);
        }

        $residual = max(0.0, $grownFund - $credited);

        return [
            'amount' => $residual,
            'basis' => $residual > 0.0 ? 'grown_fund_less_income_credited' : 'exhausted',
            'credited' => $credited,
            'grown_fund' => $grownFund,
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
     *
     * Delegates to MonteCarloEngine::extractProbabilityBands(), the one home for this
     * reshape, shared with the investment projection. Every band it returns is a
     * percentile the simulation measured.
     */
    private function extractProbabilityBands(array $simulation): array
    {
        return $this->simulator->extractProbabilityBands($simulation);
    }

    private function getRetirementAge(User $user): int
    {
        return $this->getRetirementAgeWithSource($user)['age'];
    }

    /** @return array{age:int,source:string} */
    private function getRetirementAgeWithSource(User $user): array
    {
        if ($user->target_retirement_age) {
            return ['age' => (int) $user->target_retirement_age, 'source' => 'user_profile'];
        }

        if ($user->retirementProfile?->target_retirement_age) {
            return ['age' => (int) $user->retirementProfile->target_retirement_age, 'source' => 'retirement_profile'];
        }

        foreach ($user->dcPensions as $pension) {
            if ($pension->retirement_age) {
                return ['age' => (int) $pension->retirement_age, 'source' => 'pension'];
            }
        }

        return ['age' => self::DEFAULT_RETIREMENT_AGE, 'source' => 'assumed'];
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
        if ((float) $pension->monthly_contribution_amount > 0) {
            return (float) $pension->monthly_contribution_amount;
        }

        if ($pension->employee_contribution_percent && $pension->annual_salary) {
            $employeeMonthly = ($pension->annual_salary * $pension->employee_contribution_percent / 100) / 12;
            $employerMonthly = $pension->employer_contribution_percent
                ? ($pension->annual_salary * $pension->employer_contribution_percent / 100) / 12
                : 0;

            return $employeeMonthly + $employerMonthly;
        }

        return 0.0;
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
