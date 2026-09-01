<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Constants\TaxDefaults;
use App\Models\User;
use App\Services\Goals\LifeEventService;
use App\Services\Retirement\PensionProjector;
use App\Services\Retirement\RetirementAgeResolver;
use App\Services\Retirement\RetirementProjectionService;
use App\Services\TaxConfigService;
use Carbon\Carbon;

/**
 * The household's cash balance, projected year by year to the end of the estate horizon.
 *
 * **One home (Rule 20).** This behaviour existed four times over, and each copy
 * answered the same question differently:
 *
 *   * `IHTCalculationService::projectCashWithInflation()` — the only live one, and
 *     the only one with no floor at all.
 *   * `IHTCalculationService::projectCashAccounts()` — floored the final answer.
 *   * `IHTCalculationService::projectCashAndInvestmentsIntegrated()` — floored every
 *     year and drew the deficit from investments. No caller.
 *   * `IHTFormattingService::generateCashProjectionBreakdown()` — the year-by-year
 *     table the user actually reads, with no inflation, no life events, no floor,
 *     its own hardcoded ages and its own income list.
 *
 * The headline figure and the table beneath it were therefore produced by two
 * different models, so the table could never explain the headline. Both now read
 * this, and there is nothing left to keep in step.
 *
 * Not to be confused with `CashFlowProjector` in this same namespace, which answers a
 * different question: one person's profit and loss for a single tax year and their
 * next five years, with no household pooling, no retirement phase and no horizon.
 * Named for the household because the household is precisely what the old code got
 * wrong.
 *
 * Four defects were fixed in the consolidation, because fixing any one alone still
 * lands on a wrong answer:
 *
 * **1. The floor (W-0137).** Cash draws down to £0 and stops. A deposit account
 * cannot hold a negative balance, and an overdrawn Cash ISA does not exist in UK
 * law. What used to accumulate as a minus-£1.8m asset line is returned separately
 * as `shortfall`: the projected expenditure the household's cash could not meet.
 * That is a planning output. A negative balance is not.
 *
 * **2. The axis is years from now, not the logged-in user's age (W-0188).** The
 * loop ran from the viewer's age to a death age expressed in whichever spouse dies
 * second's age frame. From that spouse's own login it is the true horizon; from the
 * other login it is short by the age gap between them. One household, two
 * projections, £103,206 apart — the whole divergence sat in this loop.
 *
 * **3. Income and expenses are resolved per member.** Each person retires at their
 * own age and reaches State Pension age at their own age, so a single household
 * switch driven by the viewer's age was both wrong and asymmetric. Summing
 * per-member flows is order-independent, which is what makes the two logins agree.
 *
 * **4. Pensions are income.** The estate's retirement income read
 * `retirement_profiles.target_retirement_income` and a State Pension column that
 * has never existed, so a £500,000 Defined Contribution pot and a £35,000 Defined
 * Benefit scheme both contributed nothing. Retirement income now comes from
 * `PensionProjector`, the module that owns the question, and a stated target
 * retirement income is treated as what the household intends to SPEND — which is
 * what it is — rather than as income it will receive.
 *
 * Everything is carried in today's money and inflated once, at the end of each
 * year's arithmetic, so income and expenses share one basis.
 */
class HouseholdCashFlowProjector
{
    /** Assumed current age when a member has no recorded date of birth. */
    private const ASSUMED_AGE_WITHOUT_DOB = 50;

    /**
     * Share of income assumed to be spent before retirement when no expenditure
     * profile exists. Not a tax value and not in tax configuration — a modelling
     * assumption, held here once rather than in each service that needs it.
     */
    private const PRE_RETIREMENT_EXPENDITURE_RATIO = 0.70;

    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly PensionProjector $pensionProjector,
        private readonly LifeEventService $lifeEventService,
        // W-0512 — the defined contribution income credited below comes from a drawdown
        // that REDUCES the fund it is paid from. `PensionProjector` still decides how much
        // the pension is meant to pay; this decides how long it can pay it.
        private readonly RetirementProjectionService $retirementProjection,
        // W-0196 — the one home for the retirement-age default and its priority chain.
        private readonly RetirementAgeResolver $retirementAge,
    ) {}

    /**
     * Project the household's cash to the end of the estate horizon.
     *
     * @param  User  $user  The signed-in member. Used only for the age labels on the
     *                      returned rows — never for the arithmetic, which is what
     *                      makes the answer the same from either login.
     * @param  int  $yearsToProject  The household horizon in years from now.
     * @param  float  $inflationRate  Annual rate as a fraction, e.g. 0.02.
     * @return array{
     *     starting_cash: float,
     *     final_cash: float,
     *     shortfall: float,
     *     years: list<array<string, mixed>>,
     *     assumptions: list<string>,
     *     pre_retirement_income: float,
     *     pre_retirement_expenses: float,
     *     retirement_income: float,
     *     retirement_expenses: float,
     *     state_pension_income: float,
     *     retirement_age: int,
     *     state_pension_age: int,
     *     death_age: int,
     * }
     */
    public function project(
        User $user,
        ?User $spouse,
        bool $poolsSpouse,
        int $yearsToProject,
        float $inflationRate,
    ): array {
        $members = $this->householdMembers($user, $spouse, $poolsSpouse);

        $startingCash = 0.0;
        foreach ($members as $member) {
            $startingCash += $this->currentCash($member);
        }

        $profiles = [];
        $assumptions = [];
        foreach ($members as $member) {
            $profile = $this->buildMemberProfile($member, $inflationRate, $yearsToProject);
            $profiles[] = $profile;
            foreach ($profile['assumptions'] as $assumption) {
                $assumptions[] = $assumption;
            }
        }

        $lifeEvents = $this->lifeEventImpactsByYearFromNow($members);

        $viewer = $profiles[0];
        $balance = $startingCash;
        $shortfall = 0.0;
        $years = [];

        for ($year = 0; $year < $yearsToProject; $year++) {
            $inflationMultiplier = pow(1 + $inflationRate, $year);

            $income = 0.0;
            $expenses = 0.0;
            $retiredCount = 0;

            foreach ($profiles as $profile) {
                $age = $profile['current_age'] + $year;
                $retired = $age >= $profile['retirement_age'];
                $retiredCount += $retired ? 1 : 0;

                // W-0512 — the defined contribution part is drawn from a fund that
                // shrinks, so it is a SERIES rather than the flat figure the rest of the
                // profile carries. It is already deflated per year (see
                // `pensionIncomeInTodaysMoney()`), so the household multiplier below
                // restores exactly the nominal withdrawal the drawdown made. Once the
                // fund is empty the term is zero and the household lives on its
                // guaranteed income, which is the behaviour that was missing: the old
                // scalar paid the same pension for thirty years out of a pot that was
                // never reduced.
                $income += $retired
                    ? $profile['guaranteed_retirement_income'] + ($profile['dc_income_by_year'][$year] ?? 0.0)
                    : $profile['pre_retirement_income'];
                $expenses += $retired ? $profile['retirement_expenses'] : $profile['pre_retirement_expenses'];

                if ($age >= $profile['state_pension_age']) {
                    $income += $profile['state_pension_income'];
                }
            }

            $income *= $inflationMultiplier;
            $expenses *= $inflationMultiplier;

            // Life events are recorded in today's money at a fixed date, so they are
            // injected as entered rather than inflated — unchanged from the behaviour
            // this replaced.
            $surplus = $income - $expenses + ($lifeEvents[$year] ?? 0.0);

            $balance += $surplus;

            if ($balance < 0) {
                $shortfall += -$balance;
                $balance = 0.0;
            }

            // Spouses rarely retire in the same year, so a two-state label cannot
            // explain the income step in between: the household loses one salary and
            // gains one pension while the other person is still working. Three states
            // can, and the same label is produced from either login.
            $years[] = [
                'year' => $year + 1,
                'age' => $viewer['current_age'] + $year,
                'phase' => match (true) {
                    $retiredCount === 0 => 'Pre-Retirement',
                    $retiredCount === count($profiles) => 'Retired',
                    default => 'Partly retired',
                },
                'income' => round($income, 0),
                'expenses' => round($expenses, 0),
                'surplus' => round($surplus, 0),
                'running_total' => round($balance, 0),
            ];
        }

        $householdRetirementIncome = 0.0;
        $householdRetirementExpenses = 0.0;
        $householdPreRetirementIncome = 0.0;
        $householdPreRetirementExpenses = 0.0;
        $householdStatePension = 0.0;
        foreach ($profiles as $profile) {
            $householdRetirementIncome += $profile['retirement_income'];
            $householdRetirementExpenses += $profile['retirement_expenses'];
            $householdPreRetirementIncome += $profile['pre_retirement_income'];
            $householdPreRetirementExpenses += $profile['pre_retirement_expenses'];
            $householdStatePension += $profile['state_pension_income'];
        }

        return [
            'starting_cash' => round($startingCash, 2),
            'final_cash' => round($balance, 2),
            'shortfall' => round($shortfall, 2),
            'years' => $years,
            'assumptions' => $assumptions,
            'pre_retirement_income' => round($householdPreRetirementIncome, 2),
            'pre_retirement_expenses' => round($householdPreRetirementExpenses, 2),
            'retirement_income' => round($householdRetirementIncome, 2),
            'retirement_expenses' => round($householdRetirementExpenses, 2),
            'state_pension_income' => round($householdStatePension, 2),
            'retirement_age' => $viewer['retirement_age'],
            'state_pension_age' => $viewer['state_pension_age'],
            'death_age' => $viewer['current_age'] + $yearsToProject,
        ];
    }

    /**
     * The age at which this person is modelled to retire.
     *
     * Public because the estate calculation publishes it and projects liabilities and
     * property to it. One resolution, one answer.
     *
     * The default is `PensionProjector::DEFAULT_RETIREMENT_AGE`. It used to be a
     * private 68 in the estate service, against the 67 the pension projector and
     * `DBPension` were deliberately aligned on, so a pension could count as income
     * from one age while being projected forward from another (W-0036).
     */
    public function retirementAgeFor(User $user): int
    {
        // W-0196. This copy had the order right — retirement profile before the user
        // record — and it is the order the one home now uses for everyone. Delegated
        // rather than kept, so there is no second implementation left to drift.
        return $this->retirementAge->forUser($user);
    }

    /**
     * The people this projection covers.
     *
     * W-0474 F1 — this used to decide for itself, on `$dataSharingEnabled && $spouse`,
     * which consults no marital status. It is now told: `App\Support\HouseholdPooling`
     * answers "one person's records or two?" once, and every caller passes the answer
     * in. A projector that re-derived it would be the eighth home for a rule that has
     * already drifted twice.
     *
     * @return list<User>
     */
    private function householdMembers(User $user, ?User $spouse, bool $poolsSpouse): array
    {
        $members = [$user];

        if ($poolsSpouse && $spouse) {
            $members[] = $spouse;
        }

        return $members;
    }

    /**
     * One member's annual cash flows in today's money, resolved once and then
     * evaluated for every year of the projection.
     *
     * Resolving per member rather than per household is what allows each person to
     * retire and to reach State Pension age at their own age, and it is why the
     * household total no longer depends on who is signed in.
     *
     * @return array{
     *     current_age: int, retirement_age: int, state_pension_age: int,
     *     pre_retirement_income: float, pre_retirement_expenses: float,
     *     retirement_income: float, state_pension_income: float,
     *     retirement_expenses: float, assumptions: list<string>
     * }
     */
    private function buildMemberProfile(User $member, float $inflationRate, int $yearsToProject): array
    {
        $assumptions = [];
        $name = $this->memberName($member);

        $currentAge = $member->date_of_birth
            ? Carbon::parse($member->date_of_birth)->age
            : self::ASSUMED_AGE_WITHOUT_DOB;

        if (! $member->date_of_birth) {
            $assumptions[] = "No date of birth recorded for {$name}, so the projection assumes they are "
                .self::ASSUMED_AGE_WITHOUT_DOB.' today.';
        }

        $retirementAge = $this->retirementAgeFor($member);
        $statePensionAge = $this->statePensionAgeFor($member);

        $preRetirementIncome = $this->grossAnnualIncome($member);
        $recordedExpenditure = $this->recordedAnnualExpenditure($member);
        $preRetirementExpenses = $this->preRetirementExpenses($recordedExpenditure, $preRetirementIncome, $assumptions, $name);

        $pensionIncome = $this->pensionIncomeInTodaysMoney($member, $currentAge, $retirementAge, $inflationRate, $assumptions, $name, $yearsToProject);

        $retirementExpenses = $this->retirementExpenses($member, $recordedExpenditure, $preRetirementIncome, $assumptions, $name);

        return [
            'current_age' => $currentAge,
            'retirement_age' => $retirementAge,
            'state_pension_age' => $statePensionAge,
            'pre_retirement_income' => $preRetirementIncome,
            'pre_retirement_expenses' => $preRetirementExpenses,
            // The private pension income at the START of retirement, which is what the
            // household summary publishes and what it has always meant.
            'retirement_income' => $pensionIncome['private'],
            // W-0512 — the two halves the year loop actually credits. The guaranteed part
            // is flat; the defined contribution part runs out.
            'guaranteed_retirement_income' => $pensionIncome['guaranteed'],
            'dc_income_by_year' => $pensionIncome['dc_by_year'],
            'state_pension_income' => $pensionIncome['state'],
            'retirement_expenses' => $retirementExpenses,
            'assumptions' => $assumptions,
        ];
    }

    /**
     * Retirement income from this person's pensions, expressed in today's money.
     *
     * `PensionProjector` owns this question — it projects Defined Contribution pots
     * and converts them at the configured safe withdrawal rate, revalues Defined
     * Benefit pensions to their scheme retirement age, and reads the State Pension
     * from `state_pension_forecast_annual`, which is the column that exists. The
     * estate service used to read `estimated_annual_amount`, which does not, and no
     * pension of any kind.
     *
     * The private-pension figures come back nominal at retirement, so they are
     * deflated back to today's money before entering a loop that inflates every
     * year's figures once. Without that, a pot grown at its own rate would then be
     * inflated a second time for the whole of retirement. The deflation uses the
     * member's own years to retirement; where individual schemes carry a different
     * retirement age of their own, that is an approximation and is stated as one.
     *
     * The State Pension forecast is already a today's-money figure and is not
     * deflated.
     *
     * @param  list<string>  $assumptions  appended to when a figure is absent rather
     *                                     than zero, so an unknown is never published
     *                                     as though it were a finding
     * @return array{private: float, guaranteed: float, dc_by_year: array<int, float>, state: float}
     */
    private function pensionIncomeInTodaysMoney(
        User $member,
        int $currentAge,
        int $retirementAge,
        float $inflationRate,
        array &$assumptions,
        string $name,
        int $yearsToProject,
    ): array {
        $projected = $this->pensionProjector->projectTotalRetirementIncome($member->id);

        $yearsToRetirement = max(0, $retirementAge - $currentAge);
        $deflator = pow(1 + $inflationRate, $yearsToRetirement);

        $private = ((float) $projected['dc_annual_income'] + (float) $projected['db_annual_income']) / ($deflator ?: 1.0);
        $guaranteed = (float) $projected['db_annual_income'] / ($deflator ?: 1.0);
        $state = (float) $projected['state_pension_income'];
        $dcByYear = $this->dcIncomeByYearInTodaysMoney($member, $currentAge, $inflationRate, $yearsToProject);

        if ($private <= 0.0) {
            $assumptions[] = "No Defined Contribution or Defined Benefit pension income could be projected for {$name}, "
                .'so the projection includes none. This is an absence of recorded pensions, not a forecast of nothing.';
        }

        if ($state <= 0.0) {
            $assumptions[] = "No State Pension forecast is recorded for {$name}, so the projection includes no State "
                .'Pension income for them. This is a gap in the record, not an entitlement of nothing.';
        }

        return [
            'private' => $private,
            // No drawdown series means no projectable defined contribution pot, so the
            // guaranteed figure IS the whole private income. Falling back to `$private`
            // rather than the defined-benefit-only figure is deliberate: a member whose
            // pot could not be projected must not silently lose income the old scalar
            // credited them.
            'guaranteed' => $dcByYear === [] ? $private : $guaranteed,
            'dc_by_year' => $dcByYear,
            'state' => $state,
        ];
    }

    /**
     * The defined contribution withdrawal for each year from now, in today's money.
     *
     * W-0512. This used to be a scalar — `dc_annual_income` deflated once — credited every
     * retired year from a fund the model never reduced. Thirty years of a perpetuity that
     * the pension could not have paid, accumulating into `final_cash` and from there into
     * the projected estate.
     *
     * `RetirementProjectionService::projectSafeWithdrawalDrawdown()` is the one place that
     * decides how long the fund lasts, and the estate reads the other end of the same
     * series for what is left of it at death (Rule 20). Nothing here re-derives a
     * withdrawal.
     *
     * **The money basis is the trap, so it is stated.** The drawdown is nominal — every
     * figure in the money of its own year — and the loop in `project()` multiplies each
     * year by `(1 + inflation)^year`. Deflating by that same power here means the
     * multiplication restores the drawdown's own nominal figure exactly. It is also why
     * this is behaviour-preserving until the fund runs dry: while the fund can pay in
     * full, `income_by_age` is `dc_annual_income × (1 + i)^(age − retirementAge)`, and
     * deflating by `(1 + i)^year` leaves `dc_annual_income ÷ (1 + i)^yearsToRetirement` —
     * the flat figure this replaced, term for term.
     *
     * @return array<int, float> keyed by years from now
     */
    private function dcIncomeByYearInTodaysMoney(
        User $member,
        int $currentAge,
        float $inflationRate,
        int $yearsToProject,
    ): array {
        $pot = $this->retirementProjection->projectPensionPot($member);

        if (($pot['dc_pension_count'] ?? 0) === 0) {
            return [];
        }

        $drawdown = $this->retirementProjection->projectSafeWithdrawalDrawdown(
            $member,
            $pot,
            $inflationRate,
            // Run it to the household horizon, not just the configured projection end age.
            // A horizon beyond that age would otherwise find no row and credit nothing,
            // which is a silent cliff rather than a modelled exhaustion.
            $currentAge + $yearsToProject
        );

        $byYear = [];
        foreach ($drawdown['income_by_age'] as $age => $nominal) {
            $year = $age - $currentAge;

            if ($year < 0) {
                continue;
            }

            $byYear[$year] = (float) $nominal / pow(1 + $inflationRate, $year);
        }

        return $byYear;
    }

    /**
     * Annual expenditure this person has actually recorded, or null if they have not.
     *
     * Kept distinct from the fallbacks so that "they told us what they spend" and "we
     * guessed from their income" can never be confused for one another downstream.
     */
    private function recordedAnnualExpenditure(User $member): ?float
    {
        $monthly = $member->expenditureProfile?->total_monthly_expenditure;

        return $monthly ? (float) $monthly * 12 : null;
    }

    /**
     * Expenditure before retirement: what they recorded, or a share of income.
     *
     * @param  list<string>  $assumptions
     */
    private function preRetirementExpenses(?float $recorded, float $income, array &$assumptions, string $name): float
    {
        if ($recorded !== null) {
            return $recorded;
        }

        $assumptions[] = "No expenditure recorded for {$name}, so the projection assumes they spend "
            .round(self::PRE_RETIREMENT_EXPENDITURE_RATIO * 100).'% of their income before retirement.';

        return $income * self::PRE_RETIREMENT_EXPENDITURE_RATIO;
    }

    /**
     * Expenditure in retirement: the recorded retirement budget, then a stated target
     * retirement income, then what they actually spend today, and only then a share of
     * income.
     *
     * **The final rule was a hardcoded 0.50 here while `RequiredCapitalCalculator`
     * read `retirement.target_income_percent` — 0.75 — for the same question.** Same
     * household, same question, two answers, and only one of them could be moved by
     * configuration. It reads the configured value now, and says so loudly if the key
     * is ever absent rather than quietly using a literal.
     *
     * **Recorded expenditure was inserted ahead of that ratio**, because keying
     * retirement spending to income while pre-retirement spending is keyed to recorded
     * expenditure lets one projection contradict the household's own data: David Jones
     * records spending £29,400 a year and was projected to spend £216,127 a year in
     * retirement, purely because his household income is large. A rule of thumb is
     * evidence about people in general; a recorded figure is evidence about this
     * person, and it wins.
     *
     * @param  list<string>  $assumptions
     */
    private function retirementExpenses(
        User $member,
        ?float $recordedExpenditure,
        float $preRetirementIncome,
        array &$assumptions,
        string $name,
    ): float {
        $profile = $member->retirementProfile;

        $budgeted = (float) ($profile?->essential_expenditure ?? 0)
            + (float) ($profile?->lifestyle_expenditure ?? 0);

        if ($budgeted > 0) {
            return $budgeted;
        }

        if ((float) ($profile?->target_retirement_income ?? 0) > 0) {
            return (float) $profile->target_retirement_income;
        }

        if ($recordedExpenditure !== null) {
            $assumptions[] = "No retirement spending recorded for {$name}, so the projection assumes they carry on "
                .'spending what they spend today.';

            return $recordedExpenditure;
        }

        $ratio = $this->retirementIncomeTargetRatio();

        $assumptions[] = "No retirement spending or current expenditure recorded for {$name}, so the projection "
            .'assumes they spend '.round($ratio * 100).'% of their current income once retired.';

        return $preRetirementIncome * $ratio;
    }

    /**
     * The configured share of pre-retirement income a household is assumed to need
     * in retirement.
     */
    private function retirementIncomeTargetRatio(): float
    {
        $ratio = $this->taxConfig->get('retirement.target_income_percent');

        if ($ratio === null) {
            report(new \RuntimeException(
                'retirement.target_income_percent is not configured; falling back to '
                .'TaxDefaults::RETIREMENT_TARGET_INCOME_PERCENT. Projected retirement expenditure is being '
                .'produced from a default, not from configuration.'
            ));

            return TaxDefaults::RETIREMENT_TARGET_INCOME_PERCENT;
        }

        return (float) $ratio;
    }

    /**
     * The age at which this person starts receiving the State Pension.
     *
     * Their own recorded State Pension age first — the real
     * `state_pensions.state_pension_age` column, which the estate service never read
     * because it looked for a `state_pension_age` on `users` that has never existed.
     * Otherwise the configured State Pension age, matching what
     * `RetirementIncomeService::getStatePensionStatus()` reads, so the estate and the
     * retirement module answer this the same way.
     */
    private function statePensionAgeFor(User $member): int
    {
        $recorded = $member->statePension?->state_pension_age;

        if ($recorded) {
            return (int) $recorded;
        }

        return (int) $this->taxConfig->get('pension.state_pension.current_spa', PensionProjector::DEFAULT_RETIREMENT_AGE);
    }

    /**
     * Gross annual income from every recorded income source.
     */
    private function grossAnnualIncome(User $member): float
    {
        return (float) ($member->annual_employment_income ?? 0)
            + (float) ($member->annual_self_employment_income ?? 0)
            + (float) ($member->annual_rental_income ?? 0)
            + (float) ($member->annual_dividend_income ?? 0)
            + (float) ($member->annual_interest_income ?? 0)
            + (float) ($member->annual_other_income ?? 0)
            + (float) ($member->annual_trust_income ?? 0);
    }

    /**
     * Cash held in savings accounts today.
     */
    private function currentCash(User $member): float
    {
        return (float) $member->savingsAccounts()->sum('current_balance');
    }

    /**
     * Life event impacts keyed by years from now.
     *
     * Keyed by the viewer's age before, which made the household's own cash flow
     * depend on who was signed in. Years from now is the same number for everybody.
     *
     * @param  list<User>  $members
     * @return array<int, float>
     */
    private function lifeEventImpactsByYearFromNow(array $members): array
    {
        $impacts = [];
        $now = Carbon::now()->startOfDay();

        foreach ($members as $member) {
            foreach ($this->lifeEventService->getActiveEventsForProjection($member->id, false) as $event) {
                $yearsFromNow = (int) $now->diffInYears(Carbon::parse($event->expected_date));
                $amount = (float) $event->amount;

                if ($event->impact_type === 'expense') {
                    $amount = -$amount;
                }

                $impacts[$yearsFromNow] = ($impacts[$yearsFromNow] ?? 0.0) + $amount;
            }
        }

        return $impacts;
    }

    /**
     * A name to use when telling the user what was assumed about this person.
     */
    private function memberName(User $member): string
    {
        $name = trim((string) ($member->first_name ?? ''));

        return $name !== '' ? $name : 'this person';
    }
}
