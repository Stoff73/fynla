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
    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly TaxStrategyMath $math,
        private readonly IsaAllowanceAllocator $isaAllocator,
        private readonly Strategies\IncomeBandStrategy $incomeBand,
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
        private readonly Strategies\AssetShiftingBundleStrategy $assetShifting,
        private readonly Strategies\CrossSpouseBundleStrategy $crossSpouse,
    ) {}

    public function calculate(User $user, ?TaxStrategyOverridesDTO $overrides = null): TaxStrategyOutputDTO
    {
        $mode = (string) ($user->household_calculation_mode ?? 'single');
        $taxYear = $this->taxConfig->getTaxYear();
        $household = $user->taxStrategyHouseholdInput;

        $context = new Strategies\TaxStrategyContext($user, $overrides, $household, $mode);

        $userAllowances = $this->buildUserAllowanceGrid($user, $overrides);

        $spouseAllowances = match ($mode) {
            'dual_earner' => $household instanceof TaxStrategyHouseholdInput
                ? $this->buildSpouseAllowanceGridDualEarner($household, $this->marriageAllowanceAvailableFor($user))
                : null,
            'single_earner_couple' => $this->buildSpouseAllowanceGridNonWorking($household, $this->marriageAllowanceAvailableFor($user)),
            default => null,
        };

        // Strategy registry. Each strategy returns [] when its preconditions
        // (mode, band, captured-data presence, etc.) aren't met for this user.
        $strategies = [
            $this->incomeBand,
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
            $this->crossSpouse,
            $this->assetShifting,
        ];

        $allRecs = [];
        foreach ($strategies as $strategy) {
            $allRecs = array_merge($allRecs, $strategy->generate($context));
        }

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
            'isa_topup_vs_psa' => fn (float $cap): array => $this->isaTopUp->generate($context->withIsaPoolCap($cap)),
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
     * The user's remaining overall ISA allowance — the same basis every
     * ISA-consuming evaluator uses internally (no override deposit applied,
     * matching pass-1 sizing).
     */
    private function userIsaPoolRemaining(User $user): float
    {
        $isa = $this->taxConfig->getISAAllowances();
        $allowance = (float) ($isa['annual_allowance'] ?? 20000);

        return max(0.0, $allowance - $this->math->estimateIsaSubscriptionsThisYear($user));
    }

    // ─── Allowance grid builders (output-DTO-bound, kept here) ──────────

    private function buildUserAllowanceGrid(User $user, ?TaxStrategyOverridesDTO $overrides): array
    {
        $income = $this->taxConfig->getIncomeTax();
        $isa = $this->taxConfig->getISAAllowances();
        $cgt = $this->taxConfig->getCapitalGainsTax();
        $div = $this->taxConfig->getDividendTax();

        $totalIncome = $this->math->taxableIncomeFor($user);
        $nonSavingsIncome = $this->math->nonSavingsIncomeFor($user);
        $personalAllowanceAmount = $this->math->personalAllowanceFor($user);
        $personalAllowanceUsed = min($totalIncome, $personalAllowanceAmount);

        $personalSavingsAllowanceAmount = $this->math->personalSavingsAllowanceFor($totalIncome);
        $estimatedAnnualInterest = $this->math->estimateAnnualInterest($user);

        $startingRateForSavingsAmount = (float) ($income['starting_rate_for_savings']['band'] ?? $income['starting_rate_for_savings']['amount'] ?? 5000);
        // Starting rate for savings tapers £-for-£ once non-savings income exceeds the
        // Personal Allowance and disappears entirely once it exceeds PA + £5,000. We only
        // surface the position when the user could actually use some of it.
        $nonSavingsIncomeAbovePa = max(0, $nonSavingsIncome - $personalAllowanceAmount);
        $startingRateForSavingsAvailable = max(0, $startingRateForSavingsAmount - $nonSavingsIncomeAbovePa);
        $startingRateForSavingsUsed = min($startingRateForSavingsAvailable, $this->math->estimateAnnualInterest($user));

        $marriageAllowanceAmount = (float) ($income['marriage_allowance']['amount'] ?? 1260);
        $maritalStatus = (string) ($user->marital_status ?? '');
        $isPartnered = in_array($maritalStatus, ['married', 'civil_partnership'], true);
        // HMRC: the recipient of a Marriage Allowance transfer must be a
        // basic-rate taxpayer. A higher/additional-rate user can't claim it
        // at all — surface "not available" rather than the misleading
        // "fully used" / "headroom" framings.
        $marriageAllowanceAvailable = $this->marriageAllowanceAvailableFor($user);
        // Eligibility is not a completed claim. Only an explicit in-memory
        // claimed override consumes the allowance in this grid.
        $marriageAllowanceUsed = $overrides?->marriageAllowanceClaimed === true
            ? $marriageAllowanceAmount
            : 0.0;

        $isaAmount = (float) ($isa['annual_allowance'] ?? 20000);
        $isaUsedThisYear = $this->math->estimateIsaSubscriptionsThisYear($user)
            + (float) ($overrides?->isaAdditionalDeposit ?? 0);
        $isaUsed = min($isaAmount, $isaUsedThisYear);

        $cgtAmount = (float) ($cgt['annual_exempt_amount'] ?? 3000);

        $divAmount = (float) ($div['allowance']['amount'] ?? $div['allowance'] ?? 500);
        if (is_array($divAmount)) {
            $divAmount = (float) ($divAmount['amount'] ?? 500);
        }
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
            $this->position(
                'pension_annual_allowance',
                $mpaaApplies ? 'Money Purchase Annual Allowance' : 'Pension Annual Allowance',
                $aaAmount,
                $aaUsed,
                'user',
            ),
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
     * Marriage Allowance availability is decided by the RECIPIENT's band:
     * the transfer can only be claimed when the working spouse pays no more
     * than basic-rate tax. The spouse grids mirror the primary's verdict —
     * a non-earner spouse "with £1,260 of headroom" is misleading when the
     * primary can't receive the transfer.
     */
    private function marriageAllowanceAvailableFor(User $user): bool
    {
        return $this->math->taxableIncomeFor($user)
            <= (float) $this->taxConfig->get('income_tax.higher_rate_threshold', 50270);
    }

    private function buildSpouseAllowanceGridDualEarner(TaxStrategyHouseholdInput $household, bool $marriageAllowanceAvailable = true): array
    {
        $income = $this->taxConfig->getIncomeTax();
        $isa = $this->taxConfig->getISAAllowances();
        $pension = $this->taxConfig->getPensionAllowances();
        $cgt = $this->taxConfig->getCapitalGainsTax();
        $div = $this->taxConfig->getDividendTax();

        $spouseNonSavingsIncome = (float) ($household->spouse_annual_income ?? 0);
        $spouseTotalIncome = $spouseNonSavingsIncome + (float) ($household->spouse_annual_dividends ?? 0);
        $personalAllowance = $this->math->personalAllowanceForIncome($spouseTotalIncome);
        $startingRateAmount = (float) ($income['starting_rate_for_savings']['band'] ?? $income['starting_rate_for_savings']['amount'] ?? 5000);
        $marriageAmount = (float) ($income['marriage_allowance']['amount'] ?? 1260);
        $isaAmount = (float) ($isa['annual_allowance'] ?? 20000);
        $cgtAmount = (float) ($cgt['annual_exempt_amount'] ?? 3000);
        $divAmountRaw = $div['allowance'] ?? 500;
        $divAmount = is_array($divAmountRaw) ? (float) ($divAmountRaw['amount'] ?? 500) : (float) $divAmountRaw;
        $aaAmount = (float) ($pension['annual_allowance'] ?? 60000);

        $psa = $this->math->personalSavingsAllowanceFor($spouseTotalIncome);

        $spouseIsaBalance = $household->spouse_isa_balance;
        $spouseIsaUseKnown = $spouseIsaBalance !== null && (float) $spouseIsaBalance === 0.0;
        $dividendUseKnown = $household->spouse_annual_dividends !== null;
        $divUsed = (float) ($household->spouse_annual_dividends ?? 0);
        $startingRateAvailable = max(0.0, $startingRateAmount - max(0.0, $spouseNonSavingsIncome - $personalAllowance));

        return [
            $this->position('personal_allowance', 'Personal Allowance', $personalAllowance, min($spouseTotalIncome, $personalAllowance), 'spouse', $personalAllowance > 0),
            $this->position('savings_allowance', 'Savings Allowance', $psa, 0.0, 'spouse', true, false),
            $this->position('starting_rate_for_savings', 'Starting Rate for Savings', $startingRateAvailable, 0.0, 'spouse', $startingRateAvailable > 0, false),
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

    private function buildSpouseAllowanceGridNonWorking(?TaxStrategyHouseholdInput $household, bool $marriageAllowanceAvailable = true): array
    {
        $income = $this->taxConfig->getIncomeTax();
        $isa = $this->taxConfig->getISAAllowances();
        $pension = $this->taxConfig->getPensionAllowances();
        $cgt = $this->taxConfig->getCapitalGainsTax();
        $div = $this->taxConfig->getDividendTax();

        // Non-working spouse — assume basic-rate band by default.
        $personalAllowance = (float) ($income['personal_allowance'] ?? 12570);
        $startingRateAmount = (float) ($income['starting_rate_for_savings']['band'] ?? $income['starting_rate_for_savings']['amount'] ?? 5000);
        $marriageAmount = (float) ($income['marriage_allowance']['amount'] ?? 1260);
        $isaAmount = (float) ($isa['annual_allowance'] ?? 20000);
        $cgtAmount = (float) ($cgt['annual_exempt_amount'] ?? 3000);
        $divAmountRaw = $div['allowance'] ?? 500;
        $divAmount = is_array($divAmountRaw) ? (float) ($divAmountRaw['amount'] ?? 500) : (float) $divAmountRaw;
        $aaAmount = (float) ($pension['annual_allowance'] ?? 60000);

        $existingIsa = $household?->spouse_existing_isa_balance;
        $spouseIsaUseKnown = $existingIsa !== null && (float) $existingIsa === 0.0;
        $savingsUseKnown = $household?->spouse_existing_savings_balance !== null
            && (float) $household->spouse_existing_savings_balance === 0.0;
        $noInvestmentsKnown = $household?->spouse_existing_investment_balance !== null
            && (float) $household->spouse_existing_investment_balance === 0.0
            && $household->spouse_existing_dividend_holdings_value !== null
            && (float) $household->spouse_existing_dividend_holdings_value === 0.0;
        $nonEarnerPensionLimit = (float) ($pension['relevant_earnings_minimum'] ?? 3600);

        return [
            // Spouse has no income → PA fully unused
            $this->position('personal_allowance', 'Personal Allowance', $personalAllowance, 0.0, 'spouse'),
            // Basic-rate PSA from TaxConfigService
            $this->position('savings_allowance', 'Savings Allowance', $this->math->psaForBand('basic'), 0.0, 'spouse', true, $savingsUseKnown),
            $this->position('starting_rate_for_savings', 'Starting Rate for Savings', $startingRateAmount, 0.0, 'spouse', true, $savingsUseKnown),
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
}
