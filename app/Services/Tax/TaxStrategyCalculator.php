<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\DataTransferObjects\StrategyRecommendation;
use App\DataTransferObjects\TaxStrategyOutputDTO;
use App\DataTransferObjects\TaxStrategyOverridesDTO;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\TaxConfigService;

/**
 * Stateless tax-strategy calculator for the SaveTax campaign terminal page.
 *
 * Composes a registry of TaxStrategy classes against a per-user context —
 * the strategies own their preconditions and produce StrategyRecommendation
 * DTOs; this class only builds the allowance grids and stitches the output
 * DTO together. Designed for sub-50ms recalculation on every slider drag.
 * NEVER writes to the database; overrides are applied in-memory.
 *
 * Branches on users.household_calculation_mode:
 *   - 'single'               → user grid only.
 *   - 'dual_earner'          → twin grids + cross-spouse coordination strategy.
 *   - 'single_earner_couple' → twin grids + asset-shifting bundle strategy.
 */
final class TaxStrategyCalculator
{
    /** The user's own pension items; only one applies for a given income. */
    private const OWN_PENSION_TYPES = ['pa_taper_rescue', 'additional_rate_avoidance', 'pension_tax_relief'];

    /** Items that each remove the same sole-name interest from the user's income. */
    private const SHARED_INTEREST_TYPES = ['isa_topup_vs_psa', 'savings_to_spouse', 'joint_savings_psa_split'];

    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly TaxStrategyMath $math,
        private readonly IsaAllowanceAllocator $isaAllocator,
        private readonly Strategies\IncomeBandStrategy $incomeBand,
        private readonly Strategies\PensionTaxReliefStrategy $pensionTaxRelief,
        private readonly Strategies\LifecycleStrategy $lifecycle,
        private readonly Strategies\JointSavingsStrategy $jointSavings,
        private readonly Strategies\IsaTopUpStrategy $isaTopUp,
        private readonly Strategies\DividendAllowanceHarvestStrategy $dividendAllowance,
        private readonly Strategies\SalarySacrificeNiStrategy $salarySacrifice,
        private readonly Strategies\BedAndIsaStrategy $bedAndIsa,
        private readonly Strategies\PensionAACarryForwardStrategy $pensionAACarryForward,
        private readonly Strategies\GiftAidHigherRateReliefStrategy $giftAidHigherRate,
        private readonly Strategies\TaperedAnnualAllowanceStrategy $taperedAnnualAllowance,
        private readonly Strategies\NonEarnerSpousePensionStrategy $nonEarnerSpousePension,
        private readonly Strategies\MarriageAllowanceStrategy $marriageAllowance,
        private readonly Strategies\AssetShiftingBundleStrategy $assetShifting,
        private readonly Strategies\CrossSpouseBundleStrategy $crossSpouse,
    ) {}

    public function calculate(User $user, ?TaxStrategyOverridesDTO $overrides = null): TaxStrategyOutputDTO
    {
        $mode = (string) ($user->household_calculation_mode ?? 'single');
        $taxYear = $this->taxConfig->getTaxYear();
        $household = $user->taxStrategyHouseholdInput;

        $context = new Strategies\TaxStrategyContext($user, $overrides, $household, $mode);

        $userAllowances = $this->buildUserAllowanceGrid($user, $overrides, $mode, $household);

        $linkedSpouse = in_array($mode, ['dual_earner', 'single_earner_couple'], true)
            ? $user->financiallySharedSpouse()
            : null;

        $spouseAllowances = match (true) {
            $linkedSpouse !== null => $this->buildSpouseAllowanceGridFromLinkedAccount($linkedSpouse, $this->marriageAllowanceAvailableFor($user, $mode, $household)),
            $mode === 'dual_earner' => $household instanceof TaxStrategyHouseholdInput
                ? $this->buildSpouseAllowanceGridDualEarner($user, $household, $this->marriageAllowanceAvailableFor($user, $mode, $household))
                : null,
            $mode === 'single_earner_couple' => $this->buildSpouseAllowanceGridNonWorking($user, $household, $this->marriageAllowanceAvailableFor($user, $mode, $household)),
            default => null,
        };

        // Strategy registry. Each strategy returns [] when its preconditions
        // (mode, band, captured-data presence, etc.) aren't met for this user.
        $strategies = [
            $this->incomeBand,
            $this->pensionTaxRelief,
            $this->lifecycle,
            $this->jointSavings,
            $this->isaTopUp,
            $this->dividendAllowance,
            $this->salarySacrifice,
            $this->bedAndIsa,
            $this->pensionAACarryForward,
            $this->giftAidHigherRate,
            $this->taperedAnnualAllowance,
            $this->nonEarnerSpousePension,
            $this->marriageAllowance,
            $this->crossSpouse,
            $this->assetShifting,
        ];

        $allRecs = [];
        foreach ($strategies as $strategy) {
            $allRecs = array_merge($allRecs, $strategy->generate($context));
        }

        // The pension is priced first; the savings items that share the same
        // interest are priced after it (before the ISA pool is allocated, so
        // the allocator works on the figures the plan shows).
        $afterPension = $this->contextAfterPension($allRecs, $context);
        $allRecs = $this->repriceSavingsAfterPension($allRecs, $afterPension);

        // All adult ISA types share ONE overall annual allowance per person —
        // cash wraps, Bed & ISA proceeds and Lifetime ISA contributions all
        // draw from the same pool, yet each strategy above sized itself
        // against the full remaining allowance independently. Re-allocate the
        // pool greedily by annual saving so the same allowance capacity is
        // never counted twice (the Lifetime ISA's own sub-limit is enforced
        // inside its evaluator). Lives here, not in the composer, so the
        // dashboard payload (TaxStrategyService) and the composed plan
        // (ComposedTaxPlanService) see identical honest figures.
        $allRecs = $this->isaAllocator->allocate($allRecs, [
            'isa_topup_vs_psa' => fn (float $cap): array => $this->isaTopUp->generate($afterPension->withIsaPoolCap($cap)),
            'bed_and_isa' => fn (float $cap): array => $this->bedAndIsa->generate($context->withIsaPoolCap($cap)),
            'lifetime_isa' => fn (float $cap): array => $this->lifecycle->generate($context->withIsaPoolCap($cap)),
        ], $this->userIsaPoolRemaining($user));

        usort($allRecs, function (StrategyRecommendation $a, StrategyRecommendation $b): int {
            $cat = $a->categoryEnum()->sortWeight() <=> $b->categoryEnum()->sortWeight();

            return $cat !== 0 ? $cat : ($a->priorityEnum()->sortWeight() <=> $b->priorityEnum()->sortWeight());
        });

        $recommendations = array_map(fn (StrategyRecommendation $r) => $r->toArray(), $allRecs);

        return new TaxStrategyOutputDTO(
            taxYear: $taxYear,
            calculationMode: $mode,
            userAllowances: $userAllowances,
            spouseAllowances: $spouseAllowances,
            recommendations: $recommendations,
            deltaVsBaseline: [],
        );
    }

    /**
     * The ISA wrap, the spouse gift and the joint split all shelter the same
     * sole-name interest; the composer counts only the largest of them (the
     * conflict rule seeded in TaxActionDefinitionSeeder). Whichever the user
     * picks, it and the pension item must not both claim the tax on the same
     * slice of income (a £110k plan counted it twice, £441 too much: SaveTax
     * matrix E1, 29 Sep 2026).
     *
     * The plan prices the pension first (agreed 29 Sep 2026): the pension item
     * keeps its price on today's income, which is right whether or not any
     * savings move, and each savings item is re-priced on the income left once
     * the pension contribution is paid. The pair then adds up to what doing
     * both saves, whichever savings item is chosen.
     *
     * @param  list<StrategyRecommendation>  $recs
     * @return list<StrategyRecommendation>
     */
    private function repriceSavingsAfterPension(array $recs, Strategies\TaxStrategyContext $afterPension): array
    {
        $hasSharedInterestItem = (bool) array_filter($recs, fn (StrategyRecommendation $r): bool => in_array($r->type, self::SHARED_INTEREST_TYPES, true));
        if ($afterPension->pensionPaidElsewhere <= 0 || ! $hasSharedInterestItem) {
            return $recs;
        }

        $repriced = [];
        foreach ($recs as $rec) {
            if (! in_array($rec->type, self::SHARED_INTEREST_TYPES, true)) {
                $repriced[] = $rec;

                continue;
            }
            // Runs before the ISA pool allocation, so each is sized as in the
            // first pass; the allocator then caps the ISA wrap as usual.
            $fresh = match ($rec->type) {
                'isa_topup_vs_psa' => $this->isaTopUp->generate($afterPension),
                'savings_to_spouse' => $this->assetShifting->generate($afterPension),
                'joint_savings_psa_split' => $this->jointSavings->generate($afterPension),
            };
            foreach ($fresh as $candidate) {
                if ($candidate->type === $rec->type) {
                    $repriced[] = $candidate;
                }
            }
        }

        return $repriced;
    }

    /**
     * The context the savings items are priced in: the user's own pension
     * item's contribution treated as paid. The largest, not the sum: the
     * taper-rescue and additional-rate items can both fire when adjusted net
     * income sits below net income, and the additional-rate contribution
     * already covers the taper slice.
     *
     * @param  list<StrategyRecommendation>  $recs
     */
    private function contextAfterPension(array $recs, Strategies\TaxStrategyContext $context): Strategies\TaxStrategyContext
    {
        $pensionPaid = 0.0;
        foreach ($recs as $rec) {
            if (in_array($rec->type, self::OWN_PENSION_TYPES, true)) {
                $pensionPaid = max($pensionPaid, (float) ($rec->extra['suggested_contribution'] ?? 0));
            }
        }

        return $pensionPaid > 0 ? $context->withPensionPaidElsewhere($pensionPaid) : $context;
    }

    /**
     * The user's remaining overall ISA allowance — the same basis every
     * ISA-consuming evaluator uses internally (no override deposit applied,
     * matching pass-1 sizing).
     */
    private function userIsaPoolRemaining(User $user): float
    {
        $isa = $this->taxConfig->getISAAllowances();
        $allowance = $this->configAmount($isa, 'annual_allowance', 'isa.annual_allowance');

        return max(0.0, $allowance - $this->math->estimateIsaSubscriptionsThisYear($user));
    }

    // ─── Allowance grid builders (output-DTO-bound, kept here) ──────────

    private function buildUserAllowanceGrid(User $user, ?TaxStrategyOverridesDTO $overrides, string $mode, ?TaxStrategyHouseholdInput $household): array
    {
        $income = $this->taxConfig->getIncomeTax();
        $isa = $this->taxConfig->getISAAllowances();
        $cgt = $this->taxConfig->getCapitalGainsTax();
        $div = $this->taxConfig->getDividendTax();

        $totalIncome = $this->math->taxableIncomeFor($user);
        $nonSavingsIncome = $this->math->nonSavingsIncomeFor($user);
        $personalAllowanceAmount = $this->math->personalAllowanceFor($user);
        $personalAllowanceUsed = min($totalIncome, $personalAllowanceAmount);

        $personalSavingsAllowanceAmount = $this->math->personalSavingsAllowanceForUser($user);
        $estimatedAnnualInterest = $this->math->estimateAnnualInterest($user);

        $startingRateForSavingsAmount = $this->configAmount($income, 'starting_rate_for_savings.band', 'income_tax.starting_rate_for_savings.band');
        // Starting rate for savings tapers £-for-£ once non-savings income exceeds the
        // Personal Allowance and disappears entirely once it exceeds PA + £5,000. We only
        // surface the position when the user could actually use some of it.
        $nonSavingsIncomeAbovePa = max(0, $nonSavingsIncome - $personalAllowanceAmount);
        $startingRateForSavingsAvailable = max(0, $startingRateForSavingsAmount - $nonSavingsIncomeAbovePa);
        $startingRateForSavingsUsed = min($startingRateForSavingsAvailable, $this->math->estimateAnnualInterest($user));

        $marriageAllowanceAmount = $this->configAmount($income, 'marriage_allowance.amount', 'income_tax.marriage_allowance.amount');
        $maritalStatus = (string) ($user->marital_status ?? '');
        $isPartnered = in_array($maritalStatus, ['married', 'civil_partnership'], true);
        // HMRC: the recipient of a Marriage Allowance transfer must be a
        // basic-rate taxpayer. A higher/additional-rate user can't claim it
        // at all — surface "not available" rather than the misleading
        // "fully used" / "headroom" framings.
        $marriageAllowanceAvailable = $this->marriageAllowanceAvailableFor($user, $mode, $household);
        // Eligibility is not a completed claim. Only an explicit in-memory
        // claimed override consumes the allowance in this grid.
        $marriageAllowanceUsed = $overrides?->marriageAllowanceClaimed === true
            ? $marriageAllowanceAmount
            : 0.0;

        $isaAmount = $this->configAmount($isa, 'annual_allowance', 'isa.annual_allowance');
        $isaUsedThisYear = $this->math->estimateIsaSubscriptionsThisYear($user)
            + (float) ($overrides?->isaAdditionalDeposit ?? 0);
        $isaUsed = min($isaAmount, $isaUsedThisYear);

        $cgtAmount = $this->configAmount($cgt, 'annual_exempt_amount', 'capital_gains_tax.annual_exempt_amount');

        $divAmount = $this->dividendAllowance($div);
        $divUsed = (float) ($user->annual_dividend_income ?? 0);

        $mpaaApplies = $this->math->moneyPurchaseAnnualAllowanceApplies($user);
        $aaAmount = $this->math->effectiveAnnualAllowanceFor($user);
        $aaUsed = $this->math->estimatePensionContributionThisYear($user, $overrides);

        $positions = [
            $this->position('personal_allowance', 'Personal Allowance', $personalAllowanceAmount, $personalAllowanceUsed, 'user', $personalAllowanceAmount > 0),
            $this->position('savings_allowance', 'Savings Allowance', $personalSavingsAllowanceAmount, min($personalSavingsAllowanceAmount, $estimatedAnnualInterest), 'user'),
            $this->position('isa_allowance', 'ISA Allowance', $isaAmount, $isaUsed, 'user'),
            // Unrealised gains do not consume the annual exempt amount. Until
            // current-year disposals, allowable losses and reliefs are captured,
            // showing the whole amount as available would be false precision.
            $this->position('cgt_allowance', 'Capital Gains Tax Allowance', $cgtAmount, 0.0, 'user', true, false),
            $this->position('dividend_allowance', 'Dividend Allowance', $divAmount, min($divAmount, $divUsed), 'user'),
            $this->pensionPosition($user, $overrides, $aaAmount, $aaUsed, $mpaaApplies),
        ];

        // Only surface Starting Rate for Savings when the user actually has
        // some of it (non-savings income < £17,570 in 2026/27). Tapered to
        // zero for higher earners — hiding it avoids the misleading
        // "£5,000 fully used" framing.
        if ($startingRateForSavingsAvailable > 0) {
            $positions[] = $this->position('starting_rate_for_savings', 'Starting Rate for Savings', $startingRateForSavingsAvailable, $startingRateForSavingsUsed, 'user');
        }

        // Marriage Allowance is only available when the user is married or
        // in a civil partnership. Don't show it to single / divorced / widowed
        // users — there's no spouse to transfer the allowance from.
        if ($isPartnered) {
            $positions[] = $this->position('marriage_allowance', 'Marriage Allowance', $marriageAllowanceAmount, $marriageAllowanceUsed, 'user', $marriageAllowanceAvailable);
        }

        return $positions;
    }

    /**
     * The pension tile shows the limit the user can actually use (CSJ
     * 2026-09-25): relief on the member's own contributions is capped at their
     * relevant UK earnings, or the basic amount if higher (FA 2004 s190,
     * https://www.legislation.gov.uk/ukpga/2004/12/section/190). Where that cap
     * is below the Annual Allowance it is the tile, measured against the user's
     * own gross contributions; otherwise the Annual Allowance tile is unchanged.
     * `limit_basis` tells consumers (the allowance milestone) which one it is.
     */
    private function pensionPosition(User $user, ?TaxStrategyOverridesDTO $overrides, float $aaAmount, float $aaUsed, bool $mpaaApplies): array
    {
        $pension = $this->taxConfig->getPensionAllowances();
        $earnings = $this->math->relevantEarningsFor($user);
        $reliefLimit = max((float) ($pension['relevant_earnings_minimum'] ?? 0), $earnings);

        if ($reliefLimit < $aaAmount) {
            return $this->position(
                'pension_annual_allowance',
                // With no earnings the limit is the basic amount (FA 2004 s190),
                // not a figure "from your earnings" (Brett 2026-09-29).
                $this->math->isDeclaredNonEarner($user)
                    ? 'Pension contribution limit without earnings'
                    : 'Pension contribution limit from your earnings',
                $reliefLimit,
                // The what-if slider replaces the captured contributions.
                $overrides?->pensionContributionPercent !== null
                    ? $this->math->estimatePensionContributionThisYear($user, $overrides)
                    : $this->math->grossEmployeePensionContributions($user),
                'user',
            ) + ['limit_basis' => 'relief'];
        }

        return $this->position(
            'pension_annual_allowance',
            $mpaaApplies ? 'Money Purchase Annual Allowance' : 'Pension Annual Allowance',
            $aaAmount,
            $aaUsed,
            'user',
        ) + ['limit_basis' => 'annual_allowance'];
    }

    /**
     * Marriage Allowance is available when the statutory tests in
     * TaxStrategyMath::marriageAllowance pass in either direction (ITA 2007
     * Part 3 Chapter 3A). It is the same test the plan item uses, so the grid
     * and the plan can never disagree.
     */
    private function marriageAllowanceAvailableFor(User $user, string $mode, ?TaxStrategyHouseholdInput $household): bool
    {
        return $this->math->marriageAllowance($user, $mode, $household) !== null;
    }

    /**
     * A linked spouse who shares financial data has their own records on file,
     * so their allowances are read from those records — the same grid their
     * own Tax Strategy page shows — rather than marked "not confirmed" from the
     * few household answers the campaign captured (SaveTax couple run M2,
     * 2026-09-29). Marriage Allowance keeps the couple-level test so the grid
     * and the plan item cannot disagree.
     */
    private function buildSpouseAllowanceGridFromLinkedAccount(User $spouse, bool $marriageAllowanceAvailable): array
    {
        $spouseMode = (string) ($spouse->household_calculation_mode ?? 'single');
        $grid = $this->buildUserAllowanceGrid($spouse, null, $spouseMode, $spouse->taxStrategyHouseholdInput);

        $grid = array_values(array_filter($grid, fn (array $row): bool => $row['key'] !== 'marriage_allowance'));
        $grid[] = $this->position(
            'marriage_allowance',
            'Marriage Allowance',
            $this->math->marriageAllowanceAmount(),
            0.0,
            'spouse',
            $marriageAllowanceAvailable,
        );

        return array_map(fn (array $row): array => array_merge($row, [
            'owner' => 'spouse',
            // Rendered under "Your spouse's allowances".
            'label' => str_replace('from your earnings', 'from their earnings', $row['label']),
        ]), $grid);
    }

    private function buildSpouseAllowanceGridDualEarner(User $user, TaxStrategyHouseholdInput $household, bool $marriageAllowanceAvailable = true): array
    {
        $income = $this->taxConfig->getIncomeTax();
        $isa = $this->taxConfig->getISAAllowances();
        $pension = $this->taxConfig->getPensionAllowances();
        $cgt = $this->taxConfig->getCapitalGainsTax();
        $div = $this->taxConfig->getDividendTax();

        $spouseNonSavingsIncome = (float) ($household->spouse_annual_income ?? 0);
        $spouseTotalIncome = $spouseNonSavingsIncome + (float) ($household->spouse_annual_dividends ?? 0);
        $personalAllowance = $this->math->personalAllowanceForIncome($spouseTotalIncome);
        $startingRateAmount = $this->configAmount($income, 'starting_rate_for_savings.band', 'income_tax.starting_rate_for_savings.band');
        $marriageAmount = $this->configAmount($income, 'marriage_allowance.amount', 'income_tax.marriage_allowance.amount');
        $isaAmount = $this->configAmount($isa, 'annual_allowance', 'isa.annual_allowance');
        $cgtAmount = $this->configAmount($cgt, 'annual_exempt_amount', 'capital_gains_tax.annual_exempt_amount');
        $divAmount = $this->dividendAllowance($div);
        $aaAmount = $this->configAmount($pension, 'annual_allowance', 'pension.annual_allowance');

        $psa = $this->math->personalSavingsAllowanceFor($spouseTotalIncome);

        $spouseIsaBalance = $household->spouse_isa_balance;
        $spouseIsaUseKnown = $spouseIsaBalance !== null && (float) $spouseIsaBalance === 0.0;
        $dividendUseKnown = $household->spouse_annual_dividends !== null;
        $divUsed = (float) ($household->spouse_annual_dividends ?? 0);
        $startingRateAvailable = max(0.0, $startingRateAmount - max(0.0, $spouseNonSavingsIncome - $personalAllowance));

        // The spouse's HMRC share of the user's joint accounts is confirmed
        // data (issue #21) — stack it through the allowances in HMRC order:
        // remaining Personal Allowance → Starting Rate → Savings Allowance.
        $spouseJointInterest = $this->math->estimateSpouseJointInterest($user);
        $spouseSavingsKnown = $spouseJointInterest > 0;
        $paRemainingForSavings = max(0.0, $personalAllowance - $spouseNonSavingsIncome);
        $interestAboveAllowances = max(0.0, $spouseJointInterest - $paRemainingForSavings);
        $spouseStartingRateUsed = min($startingRateAvailable, $interestAboveAllowances);
        $spousePsaUsed = min($psa, $interestAboveAllowances - $spouseStartingRateUsed);

        return [
            $this->position('personal_allowance', 'Personal Allowance', $personalAllowance, min($spouseTotalIncome + $spouseJointInterest, $personalAllowance), 'spouse', $personalAllowance > 0),
            $this->position('savings_allowance', 'Savings Allowance', $psa, $spousePsaUsed, 'spouse', true, $spouseSavingsKnown),
            $this->position('starting_rate_for_savings', 'Starting Rate for Savings', $startingRateAvailable, $spouseStartingRateUsed, 'spouse', $startingRateAvailable > 0, $spouseSavingsKnown),
            $this->position('marriage_allowance', 'Marriage Allowance', $marriageAmount, 0.0, 'spouse', $marriageAllowanceAvailable),
            $this->position('isa_allowance', 'ISA Allowance', $isaAmount, 0.0, 'spouse', true, $spouseIsaUseKnown),
            $this->position('cgt_allowance', 'Capital Gains Tax Allowance', $cgtAmount, 0.0, 'spouse', true, false),
            $this->position('dividend_allowance', 'Dividend Allowance', $divAmount, min($divAmount, $divUsed), 'spouse', true, $dividendUseKnown),
            // The campaign captures a spouse's own gross contribution, not
            // employer input, flexible-access status or prior scheme inputs.
            // Those missing facts can change both use and the applicable limit.
            $this->position('pension_annual_allowance', 'Pension Annual Allowance', $aaAmount, 0.0, 'spouse', true, false),
        ];
    }

    private function buildSpouseAllowanceGridNonWorking(User $user, ?TaxStrategyHouseholdInput $household, bool $marriageAllowanceAvailable = true): array
    {
        $income = $this->taxConfig->getIncomeTax();
        $isa = $this->taxConfig->getISAAllowances();
        $pension = $this->taxConfig->getPensionAllowances();
        $cgt = $this->taxConfig->getCapitalGainsTax();
        $div = $this->taxConfig->getDividendTax();

        // Non-working spouse — assume basic-rate band by default.
        $personalAllowance = $this->configAmount($income, 'personal_allowance', 'income_tax.personal_allowance');
        $startingRateAmount = $this->configAmount($income, 'starting_rate_for_savings.band', 'income_tax.starting_rate_for_savings.band');
        $marriageAmount = $this->configAmount($income, 'marriage_allowance.amount', 'income_tax.marriage_allowance.amount');
        $isaAmount = $this->configAmount($isa, 'annual_allowance', 'isa.annual_allowance');
        $cgtAmount = $this->configAmount($cgt, 'annual_exempt_amount', 'capital_gains_tax.annual_exempt_amount');
        $divAmount = $this->dividendAllowance($div);
        $aaAmount = $this->configAmount($pension, 'annual_allowance', 'pension.annual_allowance');

        $existingIsa = $household?->spouse_existing_isa_balance;
        $spouseIsaUseKnown = $existingIsa !== null && (float) $existingIsa === 0.0;
        $savingsUseKnown = $household?->spouse_existing_savings_balance !== null
            && (float) $household->spouse_existing_savings_balance === 0.0;
        $noInvestmentsKnown = $household?->spouse_existing_investment_balance !== null
            && (float) $household->spouse_existing_investment_balance === 0.0
            && $household->spouse_existing_dividend_holdings_value !== null
            && (float) $household->spouse_existing_dividend_holdings_value === 0.0;
        $nonEarnerPensionLimit = (float) ($pension['relevant_earnings_minimum'] ?? 3600);

        // The spouse's HMRC share of the user's joint accounts is confirmed
        // data (issue #21). With no other income the Personal Allowance
        // absorbs it first; only the (unlikely) excess reaches the Starting
        // Rate and then the Savings Allowance.
        $spouseJointInterest = $this->math->estimateSpouseJointInterest($user);
        $interestAbovePa = max(0.0, $spouseJointInterest - $personalAllowance);
        $spouseStartingRateUsed = min($startingRateAmount, $interestAbovePa);
        $spousePsaUsed = min($this->math->psaForBand('basic'), $interestAbovePa - $spouseStartingRateUsed);
        $savingsUseKnown = $savingsUseKnown || $spouseJointInterest > 0;

        return [
            // No earnings — only the joint-interest share consumes any PA.
            $this->position('personal_allowance', 'Personal Allowance', $personalAllowance, min($personalAllowance, $spouseJointInterest), 'spouse'),
            // Basic-rate PSA from TaxConfigService
            $this->position('savings_allowance', 'Savings Allowance', $this->math->psaForBand('basic'), $spousePsaUsed, 'spouse', true, $savingsUseKnown),
            $this->position('starting_rate_for_savings', 'Starting Rate for Savings', $startingRateAmount, $spouseStartingRateUsed, 'spouse', true, $savingsUseKnown),
            // Marriage Allowance on the spouse's grid = their PA slice available
            // to transfer TO the working spouse — gated on the recipient's band.
            $this->position('marriage_allowance', 'Marriage Allowance', $marriageAmount, 0.0, 'spouse', $marriageAllowanceAvailable),
            $this->position('isa_allowance', 'ISA Allowance', $isaAmount, 0.0, 'spouse', true, $spouseIsaUseKnown),
            $this->position('cgt_allowance', 'Capital Gains Tax Allowance', $cgtAmount, 0.0, 'spouse', true, $noInvestmentsKnown),
            $this->position('dividend_allowance', 'Dividend Allowance', $divAmount, 0.0, 'spouse', true, $noInvestmentsKnown),
            $this->position('pension_annual_allowance', 'Pension contribution limit without earnings', min($aaAmount, $nonEarnerPensionLimit), 0.0, 'spouse', true, false),
        ];
    }

    private function position(string $key, string $label, float $amount, float $used, string $owner, bool $available = true, bool $known = true): array
    {
        // An unavailable allowance (e.g. Marriage Allowance when the recipient
        // pays higher-rate tax) has no usage and no headroom — it can't be
        // claimed at all. remaining=0 keeps it out of headroom totals even on
        // a consumer that ignores the `available` flag.
        if (! $available) {
            return [
                'key' => $key,
                'label' => $label,
                'amount' => round($amount, 2),
                'used' => 0.0,
                'remaining' => 0.0,
                'utilisation_pct' => 0.0,
                'status' => 'muted',
                'owner' => $owner,
                'available' => false,
                'known' => $known,
            ];
        }

        if (! $known) {
            return [
                'key' => $key,
                'label' => $label,
                'amount' => round($amount, 2),
                'used' => 0.0,
                'remaining' => 0.0,
                'utilisation_pct' => 0.0,
                'status' => 'muted',
                'owner' => $owner,
                'available' => true,
                'known' => false,
            ];
        }

        $used = max(0.0, min($amount, $used));
        $remaining = max(0.0, $amount - $used);
        $pct = $amount > 0 ? round(($used / $amount) * 100, 1) : 0.0;
        $status = $pct >= 90 ? 'spring' : ($pct >= 50 ? 'violet' : 'raspberry');

        return [
            'key' => $key,
            'label' => $label,
            'amount' => round($amount, 2),
            'used' => round($used, 2),
            'remaining' => round($remaining, 2),
            'utilisation_pct' => $pct,
            'status' => $status,
            'owner' => $owner,
            'available' => true,
            'known' => true,
        ];
    }

    /**
     * A tax figure read from config with no typed-in fallback (Rule 2): a
     * missing key fails loudly, as SaveTaxEstimateService does, rather than
     * showing an out-of-date allowance.
     *
     * @param  array<string, mixed>  $values
     */
    private function configAmount(array $values, string $key, string $path): float
    {
        $value = \Illuminate\Support\Arr::get($values, $key);
        if (! is_numeric($value)) {
            throw new \LogicException("Tax config {$path} is missing");
        }

        return (float) $value;
    }

    /**
     * The dividend allowance, held either as a figure or as ['amount' => …].
     *
     * @param  array<string, mixed>  $div
     */
    private function dividendAllowance(array $div): float
    {
        $allowance = $div['allowance'] ?? null;

        return is_array($allowance)
            ? $this->configAmount($allowance, 'amount', 'dividend_tax.allowance.amount')
            : $this->configAmount($div, 'allowance', 'dividend_tax.allowance');
    }
}
