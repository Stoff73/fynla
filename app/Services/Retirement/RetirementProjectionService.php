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
    /** W-0196 — one home for the default; see {@see RetirementAgeResolver}. */
    private const DEFAULT_RETIREMENT_AGE = RetirementAgeResolver::DEFAULT_RETIREMENT_AGE;

    public function __construct(
        private readonly MonteCarloSimulator $simulator,
        private readonly RiskPreferenceService $riskService,
        private readonly TaxConfigService $taxConfig,
        private readonly LifeEventCashFlowService $lifeEventCashFlowService,
        private readonly CacheInvalidationService $cacheInvalidation,
        private readonly RequiredCapitalCalculator $requiredCapitalCalculator,
        // W-0482, W-0512 — the intended withdrawal comes from here, and
        // `projectSafeWithdrawalDrawdown()` decides only when the fund can no longer pay
        // it. Reading the one source is what stops the income the household is shown
        // spending and the fund the estate is taxed on becoming two opinions.
        private readonly PensionProjector $pensionProjector,
        // W-0196 — the one home for the retirement-age default and its priority chain.
        private readonly RetirementAgeResolver $retirementAge
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
     * **It is a lookup into the one drawdown, not arithmetic of its own.**
     * `projectSafeWithdrawalDrawdown()` carries the fund forward a year at a time, paying
     * the income `HouseholdCashFlowProjector` credits and reducing the fund by it. What is
     * unused at death is simply the balance that loop is holding at that age.
     *
     * **W-0482** — the history, because the shape here has been wrong twice and in
     * opposite directions. The first version read `projectTargetIncomeDrawdown()`'s
     * `remaining_fund` while the cash flow was being credited a PERPETUITY from
     * `PensionProjector`, so two models disagreed about whether the money had been spent
     * and the estate carried the pension roughly twice. That was replaced by a
     * subtraction — grown fund less credited income — which removed the double count.
     *
     * **W-0517** — the subtraction was itself wrong, and this is what replaced it. It took
     * each withdrawal out at its NOMINAL value from a fund grown as though nothing had
     * been withdrawn, so the growth those pounds had supposedly earned stayed in the
     * estate. `HouseholdCashFlowProjector:171` is `$balance += $surplus` with no return
     * applied, so once they reached cash they earned nothing anywhere. On a £500,000 pot
     * at 3%, 2.5% inflation, a 4% withdrawal rate and twenty years of retirement the
     * residual came out at roughly £360,000 against a true £180,000 — about £72,000 of
     * overstated Inheritance Tax for that household, growing with the length of retirement.
     *
     * **W-0512** — and the credited series was a perpetuity, so the subtraction's other
     * half was struck against a fund that never shrank. Both are fixed by the one loop
     * rather than separately, because refining either against the other uncorrected buys
     * false precision. See `projectSafeWithdrawalDrawdown()` for why carrying the fund
     * forward IS the future-valued complement W-0517 asks for.
     *
     * Three regimes, named in `basis` rather than collapsed into a number the caller has
     * to interpret:
     *
     *  - **before retirement** — nothing has been drawn, so the whole projected pot is
     *    unused. Read from the accumulation path at the 20th percentile.
     *  - **after retirement** — the balance the drawdown still holds at that age.
     *  - **exhausted** — the drawdown emptied the fund before this age, so the estate adds
     *    nothing.
     *
     * `credited` and `grown_fund` are still published, but **`amount` is no longer
     * `grown_fund - credited`** and is deliberately smaller than it: the difference is the
     * growth the withdrawn pounds no longer earn.
     *
     * @param  float  $inflationRate  the rate the withdrawal is uprated by each year, and
     *                                the rate `HouseholdCashFlowProjector` inflates by.
     *                                Passed in rather than read here, because the two must
     *                                use ONE rate or the income shown and the fund taxed
     *                                do not reconcile.
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

        // W-0517 — read the fund the drawdown actually left, rather than subtracting the
        // withdrawals from a fund that was never touched.
        //
        // The old subtraction removed each withdrawal at its NOMINAL value from a fund
        // grown as though nothing had been taken out of it, so the growth those withdrawn
        // pounds had supposedly earned stayed in the estate — while
        // `HouseholdCashFlowProjector:171` is `$balance += $surplus` with no return
        // applied, so once they reached cash they earned nothing at all. The estate was
        // credited with growth that happened nowhere. On a £500,000 pot at 3% with a 4%
        // withdrawal rate and twenty years of retirement the residual came out at roughly
        // double what the fund holds.
        //
        // The drawdown carries the fund forward one year at a time, so growth only ever
        // accrues on what is still in it. That is the future-valued complement, and it
        // needs no complement arithmetic here at all.
        $drawdown = $this->projectSafeWithdrawalDrawdown($user, $pot, $inflationRate, $ageAtDeath);

        $residual = (float) ($drawdown['fund_by_age'][$ageAtDeath] ?? 0.0);
        $yearsRetired = $ageAtDeath - $retirementAge;

        // What the cash flow was credited on the way here, at nominal value. Published for
        // the same reason it always was — so a caller can see the two halves — but it is
        // no longer what the residual is derived FROM, and `amount` is deliberately
        // smaller than `grown_fund - credited` by the growth those withdrawals no longer
        // earn (W-0517).
        $credited = array_sum(array_intersect_key(
            $drawdown['income_by_age'],
            array_flip(range($retirementAge, $ageAtDeath))
        ));

        // The fund had it never been drawn on. Kept as the published comparator it always
        // was; it is context now rather than a term in the answer.
        $grownFund = (float) $pot['percentile_20_at_retirement']
            * pow(1 + $drawdown['growth_rate'], $yearsRetired);

        return [
            'amount' => $residual,
            'basis' => $residual > 0.0 ? 'fund_remaining_after_drawdown' : 'exhausted',
            'credited' => $credited,
            'grown_fund' => $grownFund,
        ];
    }

    /**
     * The safe-withdrawal drawdown: what the pension can actually pay, year by year,
     * and what is left of it at each age.
     *
     * W-0512 and W-0517, which are one defect seen from two ends and are therefore fixed
     * by one loop.
     *
     * **What was wrong.** `PensionProjector::projectTotalRetirementIncome()` converts the
     * pot at the configured safe withdrawal rate and returns a scalar. Nothing reduced the
     * fund, so `HouseholdCashFlowProjector` credited that income every retired year for
     * thirty years or more out of a pot that never shrank (W-0512), and
     * `unusedDcFundAtAge()` subtracted those withdrawals from an untouched fund at their
     * NOMINAL value, leaving the growth they had supposedly earned sitting in the estate
     * (W-0517). One overstated cash, the other overstated the pension term.
     *
     * **Why one loop fixes both.** Carrying the fund forward as
     * `fund × (1 + g) − drawn` is algebraically the same thing as
     * `grownFund − Σ drawn_t × (1 + g)^(yearsRetired − t)` — the future-valued complement
     * W-0517 asks for — and it additionally stops paying when the fund reaches zero, which
     * is what W-0512 asks for. There is no version of this where the two are fixed
     * separately without one of them being struck against a figure the other has not
     * corrected yet.
     *
     * **One mechanism (Rule 20).** The cash flow credits `income_by_age` and the estate
     * reads `fund_by_age`. They are two ends of the same series, so the income the
     * household is shown spending and the fund the estate is taxed on can no longer
     * disagree about whether the money was withdrawn.
     *
     * **It is not `projectTargetIncomeDrawdown()`, and the difference is stated.** That
     * one answers "how long does the fund last if I draw what I NEED"; this one answers
     * "what can the fund actually pay of what it was MEANT to pay". Same fund, different
     * questions, so they stay separate methods rather than one being bent into the other.
     * The withdrawal order also differs — see the comment in the loop.
     *
     * **Entirely nominal, deliberately.** `percentile_20_at_retirement` and
     * `dc_annual_income` are both nominal at the retirement age and the fund grows
     * nominally, so every figure here is in the money of its own year. A caller working in
     * today's money deflates on the way out — `HouseholdCashFlowProjector` does, and says
     * so at the call site. Mixing the bases inside the loop is the silent factor error
     * W-0512's working notes warn about.
     *
     * @param  float  $inflationRate  the rate the intended withdrawal is uprated by each
     *                                year, passed in so the caller's basis and this series
     *                                cannot drift apart
     * @param  int|null  $throughAge  run the drawdown at least this far, for a caller whose
     *                                horizon is beyond the configured projection end age
     * @return array{
     *     retirement_age: int,
     *     growth_rate: float,
     *     annual_income_at_retirement: float,
     *     income_by_age: array<int, float>,
     *     fund_by_age: array<int, float>,
     *     depletion_age: int|null
     * }
     */
    public function projectSafeWithdrawalDrawdown(
        User $user,
        array $potProjection,
        float $inflationRate,
        ?int $throughAge = null
    ): array {
        $retirementAge = (int) $potProjection['retirement_age'];
        $endAge = max(
            (int) $this->taxConfig->get('retirement.projection_end_age', 100),
            $throughAge ?? 0
        );

        $riskParams = $this->riskService->getReturnParameters($potProjection['risk_level']);
        $growthRate = (float) $riskParams['expected_return_min'] / 100;

        // The income the household intends to take, from the one place that decides it.
        // This method changes when the fund runs out, never how much the pension was
        // meant to pay.
        $annualIncome = (float) $this->pensionProjector
            ->projectTotalRetirementIncome($user->id)['dc_annual_income'];

        $fund = (float) $potProjection['percentile_20_at_retirement'];
        $incomeByAge = [];
        $fundByAge = [];
        $depletionAge = null;

        for ($age = $retirementAge; $age <= $endAge; $age++) {
            // The fund as it stands at the START of this year of retirement, before this
            // year's growth and this year's withdrawal. That is the balance an estate
            // taxed on a death during this year is taxed on.
            $fundByAge[$age] = $fund;

            if ($fund <= 0.0 && $depletionAge === null) {
                $depletionAge = $age;
            }

            $intended = $annualIncome * pow(1 + $inflationRate, $age - $retirementAge);

            // The fund earns its return over the year and the withdrawal comes out of what
            // it is then worth, so a fund that cannot meet the intended draw is emptied by
            // it. Capping the draw at the PRE-growth balance instead — which is what
            // `projectTargetIncomeDrawdown()` does — strands `fund × growth` in an account
            // the model has just said is exhausted, and the estate is then taxed on a
            // residue nobody could have withdrawn. Only the depletion boundary differs:
            // while the fund can pay in full, `intended` is below both balances and the
            // two orders give the same withdrawal.
            $grown = max(0.0, $fund) * (1 + $growthRate);
            $drawn = min($intended, $grown);
            $incomeByAge[$age] = $drawn;

            $fund = max(0.0, $grown - $drawn);
        }

        return [
            'retirement_age' => $retirementAge,
            'growth_rate' => $growthRate,
            'annual_income_at_retirement' => $annualIncome,
            'income_by_age' => $incomeByAge,
            'fund_by_age' => $fundByAge,
            'depletion_age' => $depletionAge,
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

    /**
     * W-0196. This copy read `users.target_retirement_age` before the retirement
     * profile, so a household that had set a target in the retirement module and left
     * a stale one on the user record got a different answer here than from the estate
     * projection. The order and the source labels now live in one place.
     *
     * @return array{age:int,source:string}
     */
    private function getRetirementAgeWithSource(User $user): array
    {
        return $this->retirementAge->withSource($user);
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
