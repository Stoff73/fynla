<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\User;
use App\Services\Investment\InvestmentProjectionService;
use App\Services\Retirement\RetirementProjectionService;
use App\Services\Settings\AssumptionsService;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Services\Stores\PropertyStore;
use App\Services\TaxConfigService;
use App\Support\HouseholdPooling;
use App\Traits\CalculatesOwnershipShare;
use Carbon\Carbon;

/**
 * What the household owns and owes at the modelled date of death.
 *
 * Extracted from `IHTCalculationService` unchanged, term for term. Every method
 * here answers "what is this worth at death?" and none of them answers "what tax
 * is due on it?" — that stays with the calculator, which composes these five
 * public terms into its projected column. The split is by question asked, not by
 * line count: the assessment reads a projected figure and never re-derives one,
 * and nothing here knows a nil rate band exists.
 *
 * **The pooling predicate is NOT re-derived here.** Every branch that asks whose
 * records are in the estate calls `HouseholdPooling::poolsSpouse()` — the same one
 * home `IHTCalculationService` reads through its own thin delegate (W-0474, Rule
 * 20). A new branch calls it rather than reproducing the condition.
 *
 * The W-numbered reasoning on each method is the record of why it computes what it
 * does and is carried over verbatim; none of it was re-decided by the move.
 */
class EstateProjectionService
{
    use CalculatesOwnershipShare;

    private const DEFAULT_PROPERTY_GROWTH_RATE = 3.0;

    public function __construct(
        private readonly EstateAssetAggregatorService $aggregator,
        private readonly TaxConfigService $taxConfig,
        private readonly AssumptionsService $assumptionsService,
        private readonly InvestmentProjectionService $investmentProjectionService,
        private readonly FutureValueCalculator $futureValueCalculator,
        private readonly PropertyStore $propertyStore,
        private readonly HouseholdCashFlowProjector $cashFlowProjector,
        private readonly CrossModuleAssetAggregator $crossModuleAggregator,
        private readonly UndividedShareDiscount $undividedShareDiscount,
        private readonly RetirementProjectionService $retirementProjection,
    ) {}

    /**
     * The household's unused defined contribution fund at the modelled date of death.
     *
     * W-0482. Three things decide it, and each is deliberate:
     *
     * **The date.** From `inheritance_tax.pension_iht_inclusion.effective_date` an unused
     * fund forms part of the estate — IHTA 1984 s150A, inserted by Finance Act 2026 ss66-71
     * for deaths on or after the configured date. The date is read from configuration,
     * never restated (Rule 2, W-0372). A household modelled to die before it adds nothing.
     *
     * **Whose fund.** The same pooling question the rest of the projection asks. A
     * partner's pot is in this estate exactly when their savings and property are.
     *
     * **Which age.** Each member is asked about THEIR OWN age at the household horizon,
     * not the viewer's. The horizon is a number of years from now (W-0188); two people of
     * different ages reach it at different ages, and a fund drawn to 95 is not the fund
     * drawn to 88.
     *
     * @return array{amount: float, basis: string}
     */
    public function projectedUnusedPensionFund(
        User $user,
        ?User $spouse,
        int $yearsUntilDeath,
        bool $poolsSpouse,
        float $inflationRate
    ): array {
        $inclusion = $this->taxConfig->get('inheritance_tax.pension_iht_inclusion');

        if (! isset($inclusion['effective_date'])) {
            return ['amount' => 0.0, 'basis' => 'not_configured', 'caveat' => null];
        }

        if (today()->addYears(max(0, $yearsUntilDeath))->lt(Carbon::parse($inclusion['effective_date']))) {
            return ['amount' => 0.0, 'basis' => 'before_effective_date', 'caveat' => null];
        }

        $members = [$user];
        if ($poolsSpouse && $spouse !== null) {
            $members[] = $spouse;
        }

        $total = 0.0;
        $bases = [];

        foreach ($members as $member) {
            if (! $member->date_of_birth) {
                $bases[] = 'no_date_of_birth';

                continue;
            }

            $residual = $this->retirementProjection->unusedDcFundAtAge(
                $member,
                Carbon::parse($member->date_of_birth)->age + max(0, $yearsUntilDeath),
                $inflationRate
            );

            $total += $residual['amount'];
            $bases[] = $residual['basis'];
        }

        return [
            'amount' => $total,
            'basis' => implode('+', array_unique($bases)),
            // What this figure still does not model, said where it is shown rather than
            // left silent (`05-perimeter.md` §4). Shown only to a household the figure
            // is actually about: they hold a defined contribution pot and their modelled
            // death falls on or after the configured date.
            'caveat' => $total > 0.0
                ? 'This includes what is left of your defined contribution pension at the '
                    .'projected date of death. It does not include lump sum death benefits '
                    .'from a defined benefit scheme, and it does not model the income tax '
                    .'that may also be due if you die at or after 75, so the total your '
                    .'family pays could be higher than shown. Inheritance Tax on a pension '
                    .'is paid by whoever receives the pension, not out of the rest of your '
                    .'estate. It is worth discussing with a regulated financial adviser.'
                : null,
        ];
    }

    /**
     * The net value of the main residence(s) AT DEATH.
     *
     * The projected counterpart of `sumMainResidenceNetShare()`, and it exists for
     * one reason: the residence cap in IHTA 1984 s8E(2) limits the residence band to
     * the net value of the home, so a projected band assessed against a current home
     * value caps a future allowance at a past price.
     *
     * Both halves reuse the mechanisms that produce the rest of the projection —
     * property growth via `FutureValueCalculator`, mortgage amortisation via
     * `projectSingleLiability()` — so the residence cannot be worth one thing here
     * and another in `projectProperties()` / `projectLiabilities()`.
     */
    public function projectMainResidenceNetValue(
        User $user,
        ?User $spouse,
        int $yearsToProject,
        array $assumptions
    ): float {
        $growthRate = ($assumptions['property_growth_rate'] ?? self::DEFAULT_PROPERTY_GROWTH_RATE) / 100;
        $currentYear = now()->year;

        $projectFor = function (User $member) use ($growthRate, $yearsToProject, $currentYear): float {
            // W-0374 — the member's OWN frame. This closure runs for the spouse too,
            // and it used to carry the viewer's ages in with it, so a spouse's
            // undated mortgage on the family home amortised on their partner's
            // timetable. `$yearsToProject` stays as passed: the horizon is shared.
            [$memberAge, $memberRetirementAge] = $this->ageFrameFor($member);

            return (float) $this->propertyStore
                ->forUserByType($member, 'main_residence')
                ->sum(function ($property) use ($member, $growthRate, $memberAge, $memberRetirementAge, $yearsToProject, $currentYear) {
                    // W-0368 — the PROJECTED residence band cap, the twin of
                    // `sumMainResidenceNetShare()`. Grow the value the estate is
                    // actually taxed on, not the undiscounted fraction, or the
                    // projected column repeats the current column's mismatch:
                    // estate taxed at the discounted share, allowance capped
                    // against the undiscounted one.
                    $valueShare = $this->futureValueCalculator->calculateFutureValue(
                        $this->undividedShareDiscount->shareValue($property, $member),
                        $growthRate,
                        $yearsToProject
                    );

                    $mortgageShare = (float) $property->mortgages->sum(function ($mortgage) use ($member, $memberAge, $memberRetirementAge, $yearsToProject, $currentYear) {
                        $endDate = $mortgage->maturity_date;

                        return $this->projectSingleLiability(
                            (float) $this->calculateUserMortgageShare($mortgage, $member->id),
                            $endDate instanceof \DateTimeInterface ? $endDate->format('Y-m-d') : $endDate,
                            $memberAge,
                            $memberRetirementAge,
                            $yearsToProject,
                            $currentYear
                        );
                    });

                    return max(0.0, $valueShare - $mortgageShare);
                });
        };

        $value = $projectFor($user);

        if ($spouse) {
            $value += $projectFor($spouse);
        }

        return max(0.0, $value);
    }

    /**
     * Get fallback investment growth rate from AssumptionsService.
     * Falls back to 4.7% if no user-specific assumption is configured.
     */
    private function getFallbackGrowthRate(User $user): float
    {
        $assumptions = $this->assumptionsService->getEstateAssumptions($user);

        if (($assumptions['investment_growth_method'] ?? 'monte_carlo') === 'custom'
            && isset($assumptions['custom_investment_rate'])) {
            return (float) $assumptions['custom_investment_rate'] / 100;
        }

        return 0.047;
    }

    /**
     * Project investments using Monte Carlo (80% confidence) or custom rate
     */
    private function projectInvestments(
        User $user,
        ?User $spouse,
        int $yearsToProject,
        array $assumptions,
        bool $dataSharingEnabled
    ): float {
        if ($yearsToProject <= 0) {
            return $this->getCurrentInvestmentValue($user, $spouse, $dataSharingEnabled);
        }

        $method = $assumptions['investment_growth_method'] ?? 'monte_carlo';

        if ($method === 'monte_carlo') {
            return $this->projectInvestmentsMonteCarlo($user, $spouse, $yearsToProject, $dataSharingEnabled);
        }

        // Custom rate: simple compound growth
        $customRate = ($assumptions['custom_investment_rate'] ?? 5.0) / 100;
        $currentValue = $this->getCurrentInvestmentValue($user, $spouse, $dataSharingEnabled);

        return $this->futureValueCalculator->calculateFutureValue($currentValue, $customRate, $yearsToProject);
    }

    /**
     * The household's current investment value — every record the pooled members
     * touch, counted once, at the share each of them owns.
     *
     * See the class docblock for the rule. This used to be
     * `where('user_id', $user->id)` plus `where('user_id', $spouse->id)`, each at
     * 100%. Those two queries are disjoint, so nothing was ever counted twice —
     * but a member's own account was taken whole regardless of who else owns it,
     * and their share of an account the OTHER member records was not taken at
     * all. Married with sharing on, the two errors cancel; with sharing off they
     * do not, and the household's joint General Investment Account landed
     * entirely in the recording spouse's estate.
     */
    private function getCurrentInvestmentValue(User $user, ?User $spouse, bool $dataSharingEnabled): float
    {
        $value = $this->memberInvestmentValue($user);

        if (HouseholdPooling::poolsSpouse($user, $spouse, $dataSharingEnabled)) {
            $value += $this->memberInvestmentValue($spouse);
        }

        return $value;
    }

    /**
     * One member's investment value: reach-complete, at their own share.
     *
     * Routed to `CrossModuleAssetAggregator` (Rule 20) rather than re-derived —
     * it is the same reader the headline estate uses through
     * `EstateAssetAggregatorService::gatherUserAssets()`, so the projection and
     * the headline cannot drift apart again.
     */
    private function memberInvestmentValue(User $member): float
    {
        return $this->crossModuleAggregator->calculateInvestmentTotal($member->id);
    }

    /**
     * Project investments using Monte Carlo simulation (80% confidence / p20)
     */
    public function projectInvestmentsMonteCarlo(
        User $user,
        ?User $spouse,
        int $yearsToProject,
        bool $dataSharingEnabled
    ): float {
        // The growth assumption is the household's, so the same rate applies to
        // both members — it is read from the signed-in user's assumptions record
        // exactly as before.
        $fallbackRate = $this->getFallbackGrowthRate($user);

        $projectedValue = $this->projectMemberInvestments($user, $yearsToProject, $fallbackRate);

        // Include the spouse's investments. Each member's figure is already at
        // that member's own share, so the two add to the household exactly once.
        if (HouseholdPooling::poolsSpouse($user, $spouse, $dataSharingEnabled)) {
            $projectedValue += $this->projectMemberInvestments($spouse, $yearsToProject, $fallbackRate);
        }

        return $projectedValue;
    }

    /**
     * One member's projected investment value, simulated where possible and
     * compounded where not.
     *
     * The simulation and the fallback have to measure the SAME thing, and they
     * did not. `getPortfolioProjections()` is reach-complete at the member's
     * share; the fallback took `where('user_id', $member->id)` at 100%. So a run
     * where one member's simulation succeeded and the other's did not counted a
     * joint account at one and a half times its value — the simulated member's
     * half plus the whole of it again from the member who fell back. Both sides
     * now read the same ownership rule, so which branch is taken changes the
     * growth applied and nothing about whose money it is.
     *
     * Four copies of this block existed, two per member. Editing them in step is
     * how they drifted; there is one now (Rule 20).
     */
    private function projectMemberInvestments(User $member, int $yearsToProject, float $fallbackRate): float
    {
        try {
            $projections = $this->investmentProjectionService->getPortfolioProjections(
                $member,
                [$yearsToProject]
            );

            $simulated = $projections['portfolio']['projections'][$yearsToProject]['percentiles']['p20'] ?? null;

            if ($simulated !== null) {
                return (float) $simulated;
            }
        } catch (\Exception $e) {
            // Fall through to the compounded figure below rather than contributing
            // nothing — a member whose simulation fails still owns their portfolio.
        }

        // Fallback: compound at the fallback rate instead of zero growth.
        return $this->futureValueCalculator->calculateFutureValue(
            $this->memberInvestmentValue($member),
            $fallbackRate,
            $yearsToProject
        );
    }

    /**
     * Project properties using configurable growth rate (default 3%)
     *
     * W-0333. This **completes** `5278a2457`, it does not reverse it.
     *
     * That commit found `PropertyStore::forUser` is joint-aware
     * (`user_id = ? OR joint_owner_id = ?`), so calling it for both members
     * matched a joint property TWICE, and pinned each side to its own primary
     * rows to stop it. The double count was real and this method must not
     * reintroduce it.
     *
     * But primary rows were then taken at **100%**, and that is where a THIRD
     * PARTY gets in. A property held `tenants_in_common` with someone who has no
     * account here carries their share into this household's estate — £177,000 of
     * a stranger's money inside an inheritance tax figure, on the persona
     * household alone. Three approaches, two failure modes:
     *
     * | approach | joint counted twice | third party's share included |
     * |---|---|---|
     * | `forUser` on both sides | **yes** | no |
     * | `user_id` at 100% | no | **yes** |
     * | reach + share, per member | **no** | **no** |
     *
     * `5278a2457` named the third option itself: it left
     * `EstateAssetAggregatorService` alone *"because that consumer applies
     * calculateUserShare on each row so joint properties correctly contribute the
     * user's share"*. The right answer was written in the commit that introduced
     * the defect; it simply was not applied here. It is now — and it is the same
     * reader the headline estate uses, so the projection and the figure above it
     * can no longer disagree about what this household owns.
     */
    public function projectProperties(
        User $user,
        ?User $spouse,
        int $yearsToProject,
        array $assumptions,
        bool $dataSharingEnabled
    ): float {
        $propertyGrowthRate = ($assumptions['property_growth_rate'] ?? self::DEFAULT_PROPERTY_GROWTH_RATE) / 100;

        // W-0368 — the projected column values undivided shares the same way the
        // current one does. It used to read `calculatePropertyTotal()`, which is
        // shared with net worth and the Letter to Spouse and is therefore
        // UNDISCOUNTED by design; reading it here would have left the two Inheritance
        // Tax columns valuing one property two ways. **F-0026 §1 records those columns
        // diverging once already**, which is why acceptance 3 of W-0368 asks for them
        // explicitly. `UndividedShareDiscount` is the one home for the rule and both
        // columns now read it.
        $currentPropertyValue = $this->undividedShareDiscount->propertyTotal(
            $user,
            $this->propertyStore->forUserWithJointOwner($user)
        );

        // Include spouse properties if data sharing enabled. Each member's figure
        // is already at that member's own share, so a property they hold together
        // contributes its whole value exactly once.
        if (HouseholdPooling::poolsSpouse($user, $spouse, $dataSharingEnabled)) {
            $currentPropertyValue += $this->undividedShareDiscount->propertyTotal(
                $spouse,
                $this->propertyStore->forUserWithJointOwner($spouse)
            );
        }

        if ($yearsToProject <= 0) {
            return $currentPropertyValue;
        }

        return $this->futureValueCalculator->calculateFutureValue($currentPropertyValue, $propertyGrowthRate, $yearsToProject);
    }

    /**
     * Project liabilities with amortisation to end date
     *
     * If no end date specified, assumes liability cleared at retirement age
     */
    public function projectLiabilities(
        User $user,
        ?User $spouse,
        int $currentAge,
        int $retirementAge,
        int $deathAge,
        bool $dataSharingEnabled
    ): float {
        // ONE horizon for the household, viewer-framed (W-0188), computed here so
        // that neither member's own ages can move it.
        $yearsToProject = $deathAge - $currentAge;

        $projectedLiabilities = $this->projectMemberLiabilities(
            $user, $yearsToProject, $currentAge, $retirementAge
        );

        // Include spouse liabilities if data sharing enabled. Each member's debts
        // are already at that member's own share, so a debt they hold together is
        // discharged once, not twice.
        //
        // W-0374 — in the SPOUSE's age frame. An undated debt is assumed cleared at
        // its owner's retirement, and the spouse retires on their own timetable.
        if (HouseholdPooling::poolsSpouse($user, $spouse, $dataSharingEnabled)) {
            [$spouseAge, $spouseRetirementAge] = $this->ageFrameFor($spouse);
            $projectedLiabilities += $this->projectMemberLiabilities(
                $spouse, $yearsToProject, $spouseAge, $spouseRetirementAge
            );
        }

        return $projectedLiabilities;
    }

    /**
     * One member's debts, amortised to the household horizon.
     *
     * W-0336, the third member of the W-0331 / W-0333 family, and the one that
     * fails in the OTHER direction: an over-counted debt reduces the estate, so
     * this understated tax rather than overstating it. Correcting property alone
     * would have left the estate right on the asset side and wrong on the debt
     * side.
     *
     * Two departures from the headline, both closed here:
     *
     *   * **Reach.** `$user->mortgages` and `$user->liabilities` are plain
     *     `user_id` relations, so a debt the OTHER member records was invisible
     *     to this one.
     *
     *     Mortgages need the **two-leg** reader, not the one-leg one, and the
     *     difference is not cosmetic. A mortgage is REACHED by the mortgage row's
     *     own `user_id`/`joint_owner_id`, but its share is resolved from the
     *     SECURING PROPERTY (W-0228). When those disagree, debt disappears: a home
     *     owned 50/50 with a mortgage row naming one spouse only gives that spouse
     *     50% and the other spouse nothing, so half the debt is deducted by
     *     nobody and the estate — and the tax — comes out too big. The old code
     *     took the row at 100% and happened to recover the whole debt for that
     *     shape, so switching to the share alone would have been a regression.
     *     `CrossModuleAssetAggregator::getMortgages()` exists for precisely this
     *     case: its second leg picks up mortgages on the user's properties that
     *     the mortgage row does not name. Found by the tax-compliance review of
     *     this change, filed as W-0338 against the headline, which still reads the
     *     one-leg version.
     *   * **Fraction.** Every balance was taken at 100%. `calculateUserShare` and
     *     `calculateUserMortgageShare` are the one home for the split, and the
     *     mortgage rule in particular is not guessable: **a debt is shared
     *     exactly as the asset securing it is shared** (CSJ's W-0228 ruling), not
     *     as the mortgage record's own percentage. Deriving it here would have
     *     re-created the bug that ruling settled.
     *
     * Four loops, two per member, became one. Editing them in step is how the
     * two members' branches were free to drift apart (Rule 20).
     */
    private function projectMemberLiabilities(
        User $member,
        int $yearsToProject,
        int $currentAge,
        int $retirementAge
    ): float {
        $currentYear = now()->year;
        $projected = 0.0;

        foreach ($this->crossModuleAggregator->getMortgages($member->id) as $mortgage) {
            $endDate = $mortgage->maturity_date;
            $projected += $this->projectSingleLiability(
                $this->calculateUserMortgageShare($mortgage, $member->id),
                $endDate instanceof \DateTimeInterface ? $endDate->format('Y-m-d') : $endDate,
                $currentAge,
                $retirementAge,
                $yearsToProject,
                $currentYear
            );
        }

        foreach ($this->aggregator->getUserLiabilities($member) as $liability) {
            $endDate = $liability->maturity_date ?? $this->estimatePayoffDate($liability);
            $projected += $this->projectSingleLiability(
                $this->calculateUserShare($liability, $member->id),
                $endDate instanceof \DateTimeInterface ? $endDate->format('Y-m-d') : $endDate,
                $currentAge,
                $retirementAge,
                $yearsToProject,
                $currentYear
            );
        }

        return $projected;
    }

    /**
     * A member's OWN age frame — their current age and their retirement age.
     *
     * W-0374. The undated-debt fallback in `projectSingleLiability()` is
     * `$retirementAge - $currentAge`, which is only meaningful in the frame of the
     * person who owes the debt. Both projection paths used to pass the signed-in
     * user's pair for BOTH members, so a spouse's undated debt was discharged on
     * their partner's timetable — and where the viewer's own retirement age was
     * already behind them the term came out as zero and the debt vanished entirely.
     *
     * One home, two callers (Rule 20): `projectLiabilities()` and
     * `projectMainResidenceNetValue()`.
     *
     * **The household HORIZON is deliberately not derived from this.** That stays
     * shared and viewer-framed — W-0188 settled it, and W-0374 acceptance 2 says it
     * must not regress. The two are now separate parameters precisely so the next
     * reader cannot conflate them again.
     *
     * @return array{0: int, 1: int}
     */
    private function ageFrameFor(User $member): array
    {
        return [
            $member->date_of_birth ? Carbon::parse($member->date_of_birth)->age : 50,
            $this->cashFlowProjector->retirementAgeFor($member),
        ];
    }

    /**
     * Project a single liability using linear amortisation
     */
    private function projectSingleLiability(
        float $currentBalance,
        ?string $endDate,
        int $currentAge,
        int $retirementAge,
        int $yearsToProject,
        int $currentYear
    ): float {
        if ($currentBalance <= 0) {
            return 0;
        }

        // Determine years until liability ends
        if ($endDate) {
            $endYear = Carbon::parse($endDate)->year;
            $yearsUntilEnd = max(0, $endYear - $currentYear);
        } else {
            // Default: assume liability cleared at retirement age
            $yearsUntilEnd = max(0, $retirementAge - $currentAge);
        }

        // If liability ends before death, it contributes £0 at death
        if ($yearsToProject >= $yearsUntilEnd) {
            return 0;
        }

        // Linear amortisation: remaining balance proportional to remaining term
        if ($yearsUntilEnd <= 0) {
            return $currentBalance; // Already past end date but still has balance
        }

        $remainingTerm = $yearsUntilEnd - $yearsToProject;
        $projectedBalance = $currentBalance * ($remainingTerm / $yearsUntilEnd);

        return max(0, $projectedBalance);
    }

    /**
     * Estimate payoff date from balance, monthly payment, and interest rate.
     */
    private function estimatePayoffDate($liability): ?string
    {
        $balance = (float) ($liability->current_balance ?? 0);
        $monthly = (float) ($liability->monthly_payment ?? 0);

        if ($balance <= 0 || $monthly <= 0) {
            return null;
        }

        $annualRate = (float) ($liability->interest_rate ?? 0);
        $monthlyRate = $annualRate / 100 / 12;

        if ($monthlyRate > 0 && $monthly <= $balance * $monthlyRate) {
            return null; // Payment doesn't cover interest
        }

        if ($monthlyRate > 0) {
            $months = (int) ceil(-log(1 - ($balance * $monthlyRate / $monthly)) / log(1 + $monthlyRate));
        } else {
            $months = (int) ceil($balance / $monthly);
        }

        return now()->addMonths($months)->format('Y-m-d');
    }
}
