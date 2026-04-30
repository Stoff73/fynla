<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\DataTransferObjects\StrategyRecommendation;
use App\DataTransferObjects\TaxStrategyOutputDTO;
use App\DataTransferObjects\TaxStrategyOverridesDTO;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Models\DCPension;
use App\Models\FamilyMember;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\TaxConfigService;

/**
 * Stateless tax-strategy calculator for the SaveTax campaign terminal page.
 *
 * Composes existing per-allowance logic without going through full agent
 * orchestration — designed for sub-50ms recalculation on every slider drag.
 * NEVER writes to the database; overrides are applied in-memory.
 *
 * Branches on users.household_calculation_mode:
 *   - 'single'               → user grid only.
 *   - 'dual_earner'          → twin grids + cross-spouse coordination suggestions.
 *   - 'single_earner_couple' → twin grids + asset-shifting suggestions sized to
 *                              the lesser of (user's at-risk holdings) and
 *                              (spouse's unused tax capacity).
 */
final class TaxStrategyCalculator
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
    ) {}

    /**
     * Personal Savings Allowance amount for a given band, sourced from
     * TaxConfigService['income_tax']['personal_savings_allowance'].
     */
    private function psaForBand(string $band): float
    {
        $psa = $this->taxConfig->getIncomeTax()['personal_savings_allowance'] ?? [];

        return (float) ($psa[$band] ?? 0);
    }

    /**
     * Tax-band thresholds sourced from TaxConfigService — basic/higher/additional
     * boundaries. Returns the lower bounds: basic = 0, higher band lower_limit,
     * additional band lower_limit.
     */
    private function bandThresholds(): array
    {
        $bands = $this->taxConfig->getIncomeTax()['bands'] ?? [];
        $higher = 0.0;
        $additional = 0.0;
        foreach ($bands as $band) {
            $name = strtolower((string) ($band['name'] ?? ''));
            if (str_contains($name, 'higher')) {
                $higher = (float) ($band['lower_limit'] ?? 0);
            }
            if (str_contains($name, 'additional')) {
                $additional = (float) ($band['lower_limit'] ?? 0);
            }
        }

        return ['higher' => $higher, 'additional' => $additional];
    }

    public function calculate(User $user, ?TaxStrategyOverridesDTO $overrides = null): TaxStrategyOutputDTO
    {
        $mode = (string) ($user->household_calculation_mode ?? 'single');
        $taxYear = $this->taxConfig->getTaxYear();
        $household = $user->taxStrategyHouseholdInput;

        $userAllowances = $this->buildUserAllowanceGrid($user, $overrides);

        $spouseAllowances = null;
        $householdLegacyRaw = [];

        // User-level strategies (income-band, allowance harvesting, lifecycle,
        // joint-savings) fire across all calculation modes — they don't
        // depend on a spouse grid or household input row.
        $userLevelRecs = array_merge(
            $this->buildIncomeBandRecommendations($user, $overrides),
            $this->buildAllowanceRecommendations($user),
            $this->buildSalarySacrificeRecommendation($user),
            $this->buildBedAndIsaRecommendation($user),
            $this->buildLifecycleRecommendations($user),
            $this->buildJointSavingsRecommendations($user, $household, $mode),
        );

        // Household-level strategies (Marriage Allowance, savings → spouse,
        // GIA coordination, ISA top-up in spouse name, ISA coordination) fire
        // only in the two coupled modes. They emit category=household.
        if ($mode === 'dual_earner' && $household instanceof TaxStrategyHouseholdInput) {
            $spouseAllowances = $this->buildSpouseAllowanceGridDualEarner($household);
            $householdLegacyRaw = $this->buildCrossSpouseSuggestions($user, $household);
        } elseif ($mode === 'single_earner_couple') {
            $spouseAllowances = $this->buildSpouseAllowanceGridNonWorking($household);
            $householdLegacyRaw = $this->buildAssetShiftingSuggestions($user, $household, $overrides);
        }

        $householdRecs = array_map(
            fn (array $arr) => StrategyRecommendation::fromArray(StrategyCategory::Household, $arr),
            $householdLegacyRaw,
        );

        $householdRecs = array_merge(
            $householdRecs,
            $this->buildNonEarnerSpousePensionRecommendation($user, $household, $mode),
        );

        $allRecs = array_merge($userLevelRecs, $householdRecs);

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

    // ─── Allowance grid builders ────────────────────────────────────────

    private function buildUserAllowanceGrid(User $user, ?TaxStrategyOverridesDTO $overrides): array
    {
        $income = $this->taxConfig->getIncomeTax();
        $isa = $this->taxConfig->getISAAllowances();
        $pension = $this->taxConfig->getPensionAllowances();
        $cgt = $this->taxConfig->getCapitalGainsTax();
        $div = $this->taxConfig->getDividendTax();

        $employmentIncome = (float) ($user->annual_employment_income ?? 0);
        $personalAllowanceAmount = (float) ($income['personal_allowance'] ?? 12570);
        $personalAllowanceUsed = min($employmentIncome, $personalAllowanceAmount);

        $personalSavingsAllowanceAmount = $this->personalSavingsAllowanceFor($employmentIncome);
        $estimatedAnnualInterest = $this->estimateAnnualInterest($user);

        $startingRateForSavingsAmount = (float) ($income['starting_rate_for_savings']['band'] ?? $income['starting_rate_for_savings']['amount'] ?? 5000);
        // Starting rate for savings tapers when non-savings income > £12,570 — full amount only when income ≤ PA
        $nonSavingsIncomeAbovePa = max(0, $employmentIncome - $personalAllowanceAmount);
        $startingRateForSavingsRemaining = max(0, $startingRateForSavingsAmount - $nonSavingsIncomeAbovePa);
        $startingRateForSavingsUsed = $startingRateForSavingsAmount - $startingRateForSavingsRemaining;

        $marriageAllowanceAmount = (float) ($income['marriage_allowance']['amount'] ?? 1260);
        // Recipient (the working spouse) "uses" the MA only when eligible
        $marriageAllowanceUsed = ($overrides?->marriageAllowanceClaimed === true || $user->marriage_allowance_eligible === true)
            ? $marriageAllowanceAmount
            : 0.0;

        $isaAmount = (float) ($isa['annual_allowance'] ?? 20000);
        $isaUsedThisYear = $this->estimateIsaSubscriptionsThisYear($user)
            + (float) ($overrides?->isaAdditionalDeposit ?? 0);
        $isaUsed = min($isaAmount, $isaUsedThisYear);

        $cgtAmount = (float) ($cgt['annual_exempt_amount'] ?? 3000);
        // Use 0 as default — V1 does not yet track realised gains per user;
        // the dashboard surfaces "headroom" against this for the user to act on.
        $cgtUsed = 0.0;

        $divAmount = (float) ($div['allowance']['amount'] ?? $div['allowance'] ?? 500);
        if (is_array($divAmount)) {
            $divAmount = (float) ($divAmount['amount'] ?? 500);
        }
        $divUsed = (float) ($user->annual_dividend_income ?? 0);

        $aaAmount = (float) ($pension['annual_allowance'] ?? 60000);
        $aaUsed = $this->estimatePensionContributionThisYear($user, $overrides);

        return [
            $this->position('personal_allowance', 'Personal Allowance', $personalAllowanceAmount, $personalAllowanceUsed, 'user'),
            $this->position('savings_allowance', 'Savings Allowance', $personalSavingsAllowanceAmount, min($personalSavingsAllowanceAmount, $estimatedAnnualInterest), 'user'),
            $this->position('starting_rate_for_savings', 'Starting Rate for Savings', $startingRateForSavingsAmount, $startingRateForSavingsUsed, 'user'),
            $this->position('marriage_allowance', 'Marriage Allowance', $marriageAllowanceAmount, $marriageAllowanceUsed, 'user'),
            $this->position('isa_allowance', 'ISA Allowance', $isaAmount, $isaUsed, 'user'),
            $this->position('cgt_allowance', 'Capital Gains Tax Allowance', $cgtAmount, $cgtUsed, 'user'),
            $this->position('dividend_allowance', 'Dividend Allowance', $divAmount, min($divAmount, $divUsed), 'user'),
            $this->position('pension_annual_allowance', 'Pension Annual Allowance', $aaAmount, $aaUsed, 'user'),
        ];
    }

    private function buildSpouseAllowanceGridDualEarner(TaxStrategyHouseholdInput $household): array
    {
        $income = $this->taxConfig->getIncomeTax();
        $isa = $this->taxConfig->getISAAllowances();
        $pension = $this->taxConfig->getPensionAllowances();
        $cgt = $this->taxConfig->getCapitalGainsTax();
        $div = $this->taxConfig->getDividendTax();

        $spouseIncome = (float) ($household->spouse_annual_income ?? 0);
        $personalAllowance = (float) ($income['personal_allowance'] ?? 12570);
        $startingRateAmount = (float) ($income['starting_rate_for_savings']['band'] ?? $income['starting_rate_for_savings']['amount'] ?? 5000);
        $marriageAmount = (float) ($income['marriage_allowance']['amount'] ?? 1260);
        $isaAmount = (float) ($isa['annual_allowance'] ?? 20000);
        $cgtAmount = (float) ($cgt['annual_exempt_amount'] ?? 3000);
        $divAmountRaw = $div['allowance'] ?? 500;
        $divAmount = is_array($divAmountRaw) ? (float) ($divAmountRaw['amount'] ?? 500) : (float) $divAmountRaw;
        $aaAmount = (float) ($pension['annual_allowance'] ?? 60000);

        $psa = $this->psaForBand((string) ($household->spouse_psa_band ?? 'basic'));

        $isaUsed = (float) ($household->spouse_isa_balance ?? 0);
        $unrealised = (float) ($household->spouse_unrealised_gains ?? 0);
        $divUsed = (float) ($household->spouse_annual_dividends ?? 0);
        $aaUsed = (float) ($household->spouse_pension_input_annual ?? 0);

        return [
            $this->position('personal_allowance', 'Personal Allowance', $personalAllowance, min($spouseIncome, $personalAllowance), 'spouse'),
            $this->position('savings_allowance', 'Savings Allowance', $psa, 0.0, 'spouse'),
            $this->position('starting_rate_for_savings', 'Starting Rate for Savings', $startingRateAmount, max(0, $spouseIncome - $personalAllowance), 'spouse'),
            $this->position('marriage_allowance', 'Marriage Allowance', $marriageAmount, 0.0, 'spouse'),
            $this->position('isa_allowance', 'ISA Allowance', $isaAmount, min($isaAmount, $isaUsed), 'spouse'),
            $this->position('cgt_allowance', 'Capital Gains Tax Allowance', $cgtAmount, min($cgtAmount, $unrealised), 'spouse'),
            $this->position('dividend_allowance', 'Dividend Allowance', $divAmount, min($divAmount, $divUsed), 'spouse'),
            $this->position('pension_annual_allowance', 'Pension Annual Allowance', $aaAmount, min($aaAmount, $aaUsed), 'spouse'),
        ];
    }

    private function buildSpouseAllowanceGridNonWorking(?TaxStrategyHouseholdInput $household): array
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

        $existingIsa = (float) ($household?->spouse_existing_isa_balance ?? 0);

        return [
            // Spouse has no income → PA fully unused
            $this->position('personal_allowance', 'Personal Allowance', $personalAllowance, 0.0, 'spouse'),
            // Basic-rate PSA from TaxConfigService
            $this->position('savings_allowance', 'Savings Allowance', $this->psaForBand('basic'), 0.0, 'spouse'),
            $this->position('starting_rate_for_savings', 'Starting Rate for Savings', $startingRateAmount, 0.0, 'spouse'),
            // Marriage Allowance N/A on spouse's grid (it transfers FROM them TO the working spouse)
            $this->position('marriage_allowance', 'Marriage Allowance', $marriageAmount, 0.0, 'spouse'),
            $this->position('isa_allowance', 'ISA Allowance', $isaAmount, min($isaAmount, $existingIsa), 'spouse'),
            $this->position('cgt_allowance', 'Capital Gains Tax Allowance', $cgtAmount, 0.0, 'spouse'),
            $this->position('dividend_allowance', 'Dividend Allowance', $divAmount, 0.0, 'spouse'),
            $this->position('pension_annual_allowance', 'Pension Annual Allowance', $aaAmount, 0.0, 'spouse'),
        ];
    }

    // ─── Suggestion builders ────────────────────────────────────────────

    private function buildAssetShiftingSuggestions(
        User $user,
        ?TaxStrategyHouseholdInput $household,
        ?TaxStrategyOverridesDTO $overrides
    ): array {
        $suggestions = [];
        $income = $this->taxConfig->getIncomeTax();
        $marriageAmount = (float) ($income['marriage_allowance']['amount'] ?? 1260);
        $isaAmount = (float) ($this->taxConfig->getISAAllowances()['annual_allowance'] ?? 20000);

        // 1. Marriage Allowance transfer (basic-rate recipients only)
        if ($user->marriage_allowance_eligible && $this->bandFromIncome((float) ($user->annual_employment_income ?? 0)) === 'basic') {
            $estimatedSaving = $marriageAmount * 0.20;
            $suggestions[] = [
                'type' => 'marriage_allowance_transfer',
                'priority' => 'medium',
                'title' => 'Claim Marriage Allowance',
                'description' => 'Your spouse can transfer £'.number_format((int) $marriageAmount).' of unused Personal Allowance to you, saving roughly £'.number_format((int) $estimatedSaving).' per year in income tax.',
                'estimated_annual_tax_saved' => round($estimatedSaving, 2),
                'amount_transferred' => $marriageAmount,
            ];
        }

        // 2. Savings → spouse: capacity = PA + Starting Rate + PSA basic
        $userSavings = SavingsAccount::query()
            ->where('user_id', $user->id)
            ->where('is_isa', false)
            ->get(['current_balance', 'interest_rate']);
        $userSavingsTotal = (float) $userSavings->sum('current_balance');
        $userAvgRate = $userSavings->count() > 0
            ? (float) $userSavings->avg('interest_rate')
            : 0.035;

        $personalAllowance = (float) ($income['personal_allowance'] ?? 12570);
        $startingRate = (float) ($income['starting_rate_for_savings']['band'] ?? 5000);
        $spouseInterestCapacity = $personalAllowance + $startingRate + $this->psaForBand('basic');
        $existingSpouseSavings = (float) ($household?->spouse_existing_savings_balance ?? 0);
        $spouseUsedInterest = $existingSpouseSavings * $userAvgRate;
        $spouseRemainingInterestCapacity = max(0, $spouseInterestCapacity - $spouseUsedInterest);
        $maxTransferableByCapacity = $userAvgRate > 0 ? $spouseRemainingInterestCapacity / $userAvgRate : 0;
        $suggestedTransfer = min($userSavingsTotal, $maxTransferableByCapacity);

        if ($suggestedTransfer > 1000) {
            $userBandRate = $this->bandRateFor($user);
            $estimatedAnnualTaxSaved = $suggestedTransfer * $userAvgRate * $userBandRate;
            $psaBasic = $this->psaForBand('basic');
            $stackedCapacity = $personalAllowance + $startingRate + $psaBasic;
            $suggestions[] = [
                'type' => 'savings_to_spouse',
                'priority' => 'high',
                'title' => sprintf(
                    'Gift £%s of savings to your spouse for up to £%s of interest tax-free every year',
                    number_format((int) round($suggestedTransfer / 1000) * 1000),
                    number_format((int) $stackedCapacity),
                ),
                'description' => sprintf(
                    'Their Personal Allowance (£%s), Starting Rate for Savings (£%s) and Personal Savings Allowance (£%s) stack — and spousal transfers are exempt from Capital Gains Tax and Inheritance Tax.',
                    number_format((int) $personalAllowance),
                    number_format((int) $startingRate),
                    number_format((int) $psaBasic),
                ),
                'suggested_transfer_amount' => round($suggestedTransfer / 1000) * 1000,
                'estimated_annual_tax_saved' => round($estimatedAnnualTaxSaved, 2),
                'spouse_personal_allowance' => $personalAllowance,
                'spouse_starting_rate_for_savings' => $startingRate,
                'spouse_personal_savings_allowance' => $psaBasic,
                'spouse_stacked_interest_capacity' => $stackedCapacity,
            ];
        }

        // 3. ISA top-up in spouse's name — uses fresh £20k allowance
        $spouseIsaRemaining = $isaAmount - (float) ($household?->spouse_existing_isa_balance ?? 0);
        if ($spouseIsaRemaining > 0) {
            $suggestions[] = [
                'type' => 'isa_topup_spouse',
                'priority' => 'medium',
                'title' => "Open or top up an ISA in your spouse's name",
                'description' => 'They have £'.number_format((int) $spouseIsaRemaining).' of unused ISA allowance for this tax year.',
                'available_allowance' => round($spouseIsaRemaining, 2),
            ];
        }

        // 4. GIA → spouse for CGT + Dividend allowances (only if user has investment accounts)
        $hasGia = InvestmentAccount::query()
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('account_type')->orWhere('account_type', '!=', 'isa');
            })
            ->exists();
        if ($hasGia) {
            $cgtAllowance = (float) ($this->taxConfig->getCapitalGainsTax()['annual_exempt_amount'] ?? 3000);
            $div = $this->taxConfig->getDividendTax();
            $divAllowanceRaw = $div['allowance'] ?? 500;
            $divAllowance = is_array($divAllowanceRaw)
                ? (float) ($divAllowanceRaw['amount'] ?? 500)
                : (float) $divAllowanceRaw;

            $userDividends = (float) ($user->annual_dividend_income ?? 0);
            $userDivRate = match ($this->bandFromIncome((float) ($user->annual_employment_income ?? 0))) {
                'basic' => (float) ($div['basic_rate'] ?? 0.0875),
                'higher' => (float) ($div['higher_rate'] ?? 0.3375),
                'additional' => (float) ($div['additional_rate'] ?? 0.3935),
                default => 0.0875,
            };
            $spouseDivRate = (float) ($div['basic_rate'] ?? 0.0875);
            $rateDelta = max(0.0, $userDivRate - $spouseDivRate);
            // Only the portion above the spouse's own dividend allowance carries any tax.
            $shiftableDividends = max(0, $userDividends - $divAllowance);
            $estimatedSaving = $shiftableDividends * $rateDelta;

            $suggestions[] = [
                'type' => 'gia_to_spouse',
                'priority' => $estimatedSaving > 0 ? 'high' : 'medium',
                'title' => 'Hold non-ISA investments in your spouse\'s name',
                'description' => sprintf(
                    'Their unused Capital Gains Tax allowance (£%s/yr) and Dividend Allowance (£%s/yr) absorb gains and dividends tax-free, then anything above is taxed at the basic rate rather than yours.',
                    number_format((int) $cgtAllowance),
                    number_format((int) $divAllowance),
                ),
                'available_cgt_allowance' => $cgtAllowance,
                'available_dividend_allowance' => $divAllowance,
                'estimated_annual_tax_saved' => $estimatedSaving > 0 ? round($estimatedSaving, 2) : null,
                'shiftable_dividends' => round($shiftableDividends, 2),
                'rate_delta' => round($rateDelta, 4),
            ];
        }

        return $suggestions;
    }

    private function buildCrossSpouseSuggestions(User $user, TaxStrategyHouseholdInput $household): array
    {
        $suggestions = [];
        $userBand = $this->bandFromIncome((float) ($user->annual_employment_income ?? 0));
        $spouseBand = (string) ($household->spouse_psa_band ?? 'basic');

        // Recommend rebalancing GIA / dividend-bearing holdings to the lower-earner spouse
        if ($userBand !== 'basic' && $spouseBand === 'basic') {
            $div = $this->taxConfig->getDividendTax();
            $userDivRate = match ($userBand) {
                'higher' => (float) ($div['higher_rate'] ?? 0.3375),
                'additional' => (float) ($div['additional_rate'] ?? 0.3935),
                default => (float) ($div['basic_rate'] ?? 0.0875),
            };
            $spouseDivRate = (float) ($div['basic_rate'] ?? 0.0875);
            $rateDelta = max(0.0, $userDivRate - $spouseDivRate);
            $userDividends = (float) ($user->annual_dividend_income ?? 0);
            $estimatedSaving = $userDividends * $rateDelta;

            $suggestions[] = [
                'type' => 'gia_rebalance',
                'priority' => 'high',
                'title' => $estimatedSaving > 0
                    ? sprintf(
                        'Move £%s of dividends into your spouse\'s name, saving around £%s a year',
                        number_format((int) $userDividends),
                        number_format((int) round($estimatedSaving)),
                    )
                    : "Hold GIA in your spouse's name",
                'description' => sprintf(
                    'Your dividends are currently taxed at %s%%. Moving them into your spouse\'s name drops the rate to %s%% — capital gains are taxed less too. Spousal transfers are exempt from Capital Gains Tax and Inheritance Tax.',
                    number_format($userDivRate * 100, 2),
                    number_format($spouseDivRate * 100, 2),
                ),
                'estimated_annual_tax_saved' => $estimatedSaving > 0 ? round($estimatedSaving, 2) : null,
                'user_dividend_rate' => $userDivRate,
                'spouse_dividend_rate' => $spouseDivRate,
                'rate_delta' => round($rateDelta, 4),
            ];
        }

        // ISA contribution coordination — both have £20k allowances
        $userIsaUsed = $this->estimateIsaSubscriptionsThisYear($user);
        $spouseIsaUsed = (float) ($household->spouse_isa_balance ?? 0);
        $isaAmount = (float) ($this->taxConfig->getISAAllowances()['annual_allowance'] ?? 20000);
        if ($userIsaUsed >= $isaAmount && $spouseIsaUsed < $isaAmount) {
            $suggestions[] = [
                'type' => 'isa_coordination',
                'priority' => 'medium',
                'title' => 'Use your spouse\'s ISA allowance',
                'description' => "You've maxed your ISA, but your spouse still has £".number_format((int) ($isaAmount - $spouseIsaUsed)).' of unused ISA capacity this tax year.',
            ];
        }

        return $suggestions;
    }

    // ─── Phase 2 strategy generators (April30Updates §A-E) ─────────────

    /**
     * Strategies #1 (Personal Allowance Taper Rescue) + #2 (Additional-Rate Avoidance).
     *
     * Both are pension-led income-band strategies — a relievable pension
     * contribution shifts taxable income out of a high-marginal-rate band.
     * #1 recovers the £12,570 Personal Allowance withdrawn at the 60%
     * effective rate between £100k-£125,140. #2 trims the slice above
     * £125,140 from 45% into the 40%/60% bands below.
     *
     * @return list<StrategyRecommendation>
     */
    private function buildIncomeBandRecommendations(User $user, ?TaxStrategyOverridesDTO $overrides): array
    {
        $income = $this->taxConfig->getIncomeTax();
        $taperThreshold = (float) ($income['personal_allowance_taper_threshold'] ?? 100000);
        $personalAllowance = (float) ($income['personal_allowance'] ?? 12570);
        $additionalRateThreshold = $this->bandThresholds()['additional'] ?: 125140;

        $taxableIncome = $this->taxableIncomeFor($user);
        $availableAA = $this->availableAnnualAllowance($user, $overrides);
        if ($availableAA <= 0) {
            return [];
        }

        $recommendations = [];

        // #1 — Personal Allowance Taper Rescue (60% effective rate band).
        // Effective only when contributing to drop income BELOW the taper
        // threshold, i.e. only the slice between £100k and the user's income
        // counts. Cap by both the in-band slice and the available AA.
        if ($taxableIncome > $taperThreshold && $taxableIncome <= $additionalRateThreshold) {
            $inBandSlice = $taxableIncome - $taperThreshold;
            $contribution = min($inBandSlice, $availableAA);
            if ($contribution > 0) {
                $saving = $contribution * 0.60;
                $recommendations[] = new StrategyRecommendation(
                    type: 'pa_taper_rescue',
                    category: StrategyCategory::IncomeBand,
                    priority: StrategyPriority::High,
                    title: 'Reclaim your Personal Allowance with a pension contribution',
                    description: sprintf(
                        'Income between £%s and £%s is taxed at 60%% because the Personal Allowance tapers in this band. A £%s pension contribution drops you below £%s and saves around £%s in tax this year.',
                        number_format((int) $taperThreshold),
                        number_format((int) $additionalRateThreshold),
                        number_format((int) round($contribution / 100) * 100),
                        number_format((int) $taperThreshold),
                        number_format((int) round($saving)),
                    ),
                    estimatedAnnualTaxSaved: round($saving, 2),
                    extra: [
                        'suggested_contribution' => round($contribution, 2),
                        'effective_marginal_rate' => 0.60,
                    ],
                );
            }
        }

        // #2 — Additional-Rate Avoidance (45% → 40%/60% bands).
        // Piecewise saving: top slice 45 → 40 (5pp), middle slice 40 → 60 swing
        // (gain), but the gain only materialises when contribution dips into
        // the £100k-£125,140 band. Approximate as: above-AR slice × 5pp +
        // continuation into taper band × 60pp differential vs nothing.
        if ($taxableIncome > $additionalRateThreshold) {
            $additionalSlice = min($taxableIncome - $additionalRateThreshold, $availableAA);
            $remaining = max(0, $availableAA - $additionalSlice);
            $taperSlice = min($remaining, $additionalRateThreshold - $taperThreshold);
            $remainingAfterTaper = max(0, $remaining - $taperSlice);
            $belowTaperSlice = min($remainingAfterTaper, max(0, $taperThreshold - max(0, $taxableIncome - $availableAA)));

            $saving = ($additionalSlice * 0.45) + ($taperSlice * 0.60) + ($belowTaperSlice * 0.40);
            $contribution = $additionalSlice + $taperSlice + $belowTaperSlice;

            if ($contribution > 0) {
                $recommendations[] = new StrategyRecommendation(
                    type: 'additional_rate_avoidance',
                    category: StrategyCategory::IncomeBand,
                    priority: StrategyPriority::High,
                    title: 'Shift income out of the 45% additional-rate band',
                    description: sprintf(
                        'Income above £%s is taxed at 45%%. A £%s pension contribution moves that slice into the 40%% band and reclaims part of your Personal Allowance, saving around £%s in tax this year.',
                        number_format((int) $additionalRateThreshold),
                        number_format((int) round($contribution / 100) * 100),
                        number_format((int) round($saving)),
                    ),
                    estimatedAnnualTaxSaved: round($saving, 2),
                    extra: [
                        'suggested_contribution' => round($contribution, 2),
                        'additional_rate_slice' => round($additionalSlice, 2),
                        'taper_band_slice' => round($taperSlice, 2),
                    ],
                );
            }
        }

        return $recommendations;
    }

    /**
     * Strategies #16 (Lifetime ISA, under-40s) + #17 (Junior ISA) + #18 (Junior Pension).
     *
     * @return list<StrategyRecommendation>
     */
    private function buildLifecycleRecommendations(User $user): array
    {
        $recommendations = [];
        $isa = $this->taxConfig->getISAAllowances();
        $pension = $this->taxConfig->getPensionAllowances();

        // #16 — Lifetime ISA (under-40s)
        $userAge = $this->ageOf($user->date_of_birth);
        $lisa = $isa['lifetime_isa'] ?? [];
        $lisaMaxAgeToOpen = (int) ($lisa['max_age_to_open'] ?? 39);
        $lisaAnnual = (float) ($lisa['annual_allowance'] ?? 4000);
        $lisaBonusRate = (float) ($lisa['government_bonus_rate'] ?? 0.25);

        if ($userAge !== null && $userAge >= 18 && $userAge <= $lisaMaxAgeToOpen) {
            $isaAllowance = (float) ($isa['annual_allowance'] ?? 20000);
            $isaUsed = $this->estimateIsaSubscriptionsThisYear($user);
            $isaRemaining = max(0, $isaAllowance - $isaUsed);
            $contribution = min($lisaAnnual, $isaRemaining);

            if ($contribution > 0) {
                $bonus = $contribution * $lisaBonusRate;
                $recommendations[] = new StrategyRecommendation(
                    type: 'lifetime_isa',
                    category: StrategyCategory::Lifecycle,
                    priority: StrategyPriority::Medium,
                    title: sprintf('Open a Lifetime ISA for a £%s government bonus every year', number_format((int) $bonus)),
                    description: sprintf(
                        'You\'re under %d. Contributing £%s a year to a Lifetime ISA unlocks a £%s government top-up — usable for a first home (up to £450,000) or from age 60. The contribution counts toward your £%s overall ISA allowance.',
                        $lisaMaxAgeToOpen + 1,
                        number_format((int) $contribution),
                        number_format((int) $bonus),
                        number_format((int) $isaAllowance),
                    ),
                    estimatedAnnualTaxSaved: round($bonus, 2),
                    extra: [
                        'suggested_contribution' => round($contribution, 2),
                        'government_bonus' => round($bonus, 2),
                        'user_age' => $userAge,
                    ],
                );
            }
        }

        // #17 + #18 — count dependant children under 18
        $juniorIsa = $isa['junior_isa'] ?? [];
        $juniorIsaMaxAge = (int) ($juniorIsa['max_age'] ?? 17);
        $juniorIsaAnnual = (float) ($juniorIsa['annual_allowance'] ?? 9000);

        $children = FamilyMember::query()
            ->where('user_id', $user->id)
            ->whereNotNull('date_of_birth')
            ->whereIn('relationship', ['child', 'son', 'daughter'])
            ->get(['date_of_birth']);

        $childrenUnder18 = $children->filter(function ($child) use ($juniorIsaMaxAge) {
            $age = $this->ageOf($child->date_of_birth);

            return $age !== null && $age <= $juniorIsaMaxAge;
        });
        $childCount = $childrenUnder18->count();

        if ($childCount > 0) {
            $totalJisaCapacity = $childCount * $juniorIsaAnnual;
            $recommendations[] = new StrategyRecommendation(
                type: 'junior_isa',
                category: StrategyCategory::Lifecycle,
                priority: StrategyPriority::Medium,
                title: sprintf(
                    '%s — up to £%s of Junior ISA capacity a year',
                    $childCount === 1
                        ? 'You have 1 child under 18 in your household'
                        : sprintf('You have %d children under 18 in your household', $childCount),
                    number_format((int) $totalJisaCapacity),
                ),
                description: sprintf(
                    'Each child under 18 has a £%s annual Junior ISA allowance — separate from your own £%s. All interest, dividends and capital gains inside the wrapper are tax-free until they turn 18.',
                    number_format((int) $juniorIsaAnnual),
                    number_format((int) ($isa['annual_allowance'] ?? 20000)),
                ),
                estimatedAnnualTaxSaved: null,
                extra: [
                    'children_under_18' => $childCount,
                    'total_jisa_capacity' => $totalJisaCapacity,
                ],
            );

            // #18 — Junior Pension. £2,880 net per child grossed up to £3,600
            // (25% tax relief). Use auto_enrolment / pension config; spec
            // gives £2,880 net + £720 uplift per child.
            $juniorPensionNet = 2880.0;
            $juniorPensionUplift = 720.0;
            $totalUplift = $childCount * $juniorPensionUplift;
            $recommendations[] = new StrategyRecommendation(
                type: 'junior_pension',
                category: StrategyCategory::Lifecycle,
                priority: StrategyPriority::Medium,
                title: sprintf(
                    'Open a pension for each child — instant £%s a year of free money',
                    number_format((int) $totalUplift),
                ),
                description: sprintf(
                    'Anyone, including a child with no income, can hold a personal pension. £%s contributed per child is topped up to £%s by the government — that\'s £%s of free money per child, every year. Decades of compounding sheltered from tax.',
                    number_format((int) $juniorPensionNet),
                    number_format((int) ($juniorPensionNet + $juniorPensionUplift)),
                    number_format((int) $juniorPensionUplift),
                ),
                estimatedAnnualTaxSaved: round($totalUplift, 2),
                extra: [
                    'children_under_18' => $childCount,
                    'net_contribution_per_child' => $juniorPensionNet,
                    'gross_contribution_per_child' => $juniorPensionNet + $juniorPensionUplift,
                    'total_government_uplift' => $totalUplift,
                ],
            );
        }

        return $recommendations;
    }

    /**
     * Strategy #15 — Joint Savings Split for Personal Savings Allowance
     * Doubling. Fires when a married user holds sole-name non-ISA savings
     * generating interest above their own Personal Savings Allowance, AND
     * their spouse has at least basic-rate band PSA capacity unused. Skipped
     * for additional-rate taxpayers because PSA = £0 at that band.
     *
     * @return list<StrategyRecommendation>
     */
    private function buildJointSavingsRecommendations(User $user, ?TaxStrategyHouseholdInput $household, string $mode): array
    {
        $isMarried = $user->marital_status === 'married'
            || in_array($mode, ['dual_earner', 'single_earner_couple'], true);
        if (! $isMarried) {
            return [];
        }

        $userBand = $this->bandFromIncome((float) ($user->annual_employment_income ?? 0));
        if ($userBand === 'additional') {
            return [];
        }

        // Sole-name non-ISA savings — joint accounts (joint_owner_id set) are
        // already split 50/50 by HMRC default and cannot benefit further.
        $soleSavings = SavingsAccount::query()
            ->where('user_id', $user->id)
            ->whereNull('joint_owner_id')
            ->where('is_isa', false)
            ->get(['current_balance', 'interest_rate']);

        if ($soleSavings->isEmpty()) {
            return [];
        }

        $balance = (float) $soleSavings->sum('current_balance');
        $avgRate = $soleSavings->count() > 0 ? (float) $soleSavings->avg('interest_rate') : 0.0;
        $interest = $balance * $avgRate;
        $userPsa = $this->psaForBand($userBand);

        if ($interest <= $userPsa || $avgRate <= 0) {
            return [];
        }

        $spouseBand = (string) ($household?->spouse_psa_band ?? 'basic');
        $spousePsa = $this->psaForBand($spouseBand);
        if ($spousePsa <= 0) {
            return [];
        }

        // Slice currently taxed in user's name that would slot into spouse's PSA on splitting.
        $taxableSlice = $interest - $userPsa;
        $shelterableSlice = min($taxableSlice, $spousePsa);
        $marginalRate = $this->bandRateFor($user);
        $saving = $shelterableSlice * $marginalRate;

        if ($saving < 1) {
            return [];
        }

        return [new StrategyRecommendation(
            type: 'joint_savings_psa_split',
            category: StrategyCategory::Household,
            priority: StrategyPriority::Low,
            title: 'Split your savings between you and your spouse to use both Savings Allowances',
            description: sprintf(
                'Holding £%s of cash in one name uses only your £%s Personal Savings Allowance. Putting half in your spouse\'s name claims their £%s allowance too — saving around £%s a year.',
                number_format((int) $balance),
                number_format((int) $userPsa),
                number_format((int) $spousePsa),
                number_format((int) round($saving)),
            ),
            estimatedAnnualTaxSaved: round($saving, 2),
            extra: [
                'sole_balance' => round($balance, 2),
                'user_psa' => $userPsa,
                'spouse_psa' => $spousePsa,
                'shelterable_interest' => round($shelterableSlice, 2),
            ],
        )];
    }

    /**
     * Strategy #5 (ISA Top-Up From Cash Earning Beyond the Savings Allowance)
     * + #7 (Dividend Allowance Harvest).
     *
     * @return list<StrategyRecommendation>
     */
    private function buildAllowanceRecommendations(User $user): array
    {
        $recommendations = [];
        $isa = $this->taxConfig->getISAAllowances();
        $div = $this->taxConfig->getDividendTax();
        $isaAllowance = (float) ($isa['annual_allowance'] ?? 20000);
        $userBand = $this->bandFromIncome($this->taxableIncomeFor($user));

        // #5 — ISA Top-Up From Cash Earning Beyond the Savings Allowance
        $isaUsed = $this->estimateIsaSubscriptionsThisYear($user);
        $isaRemaining = max(0, $isaAllowance - $isaUsed);

        if ($isaRemaining > 0) {
            $nonIsaSavings = SavingsAccount::query()
                ->where('user_id', $user->id)
                ->where('is_isa', false)
                ->get(['current_balance', 'interest_rate']);
            $nonIsaBalance = (float) $nonIsaSavings->sum('current_balance');
            $avgRate = $nonIsaSavings->count() > 0
                ? (float) $nonIsaSavings->avg('interest_rate')
                : 0.0;
            $annualInterest = $nonIsaBalance * $avgRate;
            $psa = $this->psaForBand($userBand);

            // Only fire when interest exceeds PSA — otherwise no tax to save.
            if ($annualInterest > $psa && $avgRate > 0) {
                $excessInterest = $annualInterest - $psa;
                $excessBalance = $excessInterest / $avgRate;
                $transferable = min($isaRemaining, $nonIsaBalance, $excessBalance);

                if ($transferable > 1000) {
                    $marginalRate = $this->bandRateFor($user);
                    $saving = $transferable * $avgRate * $marginalRate;

                    $recommendations[] = new StrategyRecommendation(
                        type: 'isa_topup_vs_psa',
                        category: StrategyCategory::Allowance,
                        priority: StrategyPriority::High,
                        title: sprintf(
                            'Wrap £%s of cash savings inside an ISA before April 5',
                            number_format((int) round($transferable / 1000) * 1000),
                        ),
                        description: sprintf(
                            'You hold £%s of non-ISA cash earning interest above your £%s Savings Allowance. Wrapping £%s in an ISA removes that interest from tax permanently — saving around £%s a year going forward.',
                            number_format((int) $nonIsaBalance),
                            number_format((int) $psa),
                            number_format((int) round($transferable / 1000) * 1000),
                            number_format((int) round($saving)),
                        ),
                        estimatedAnnualTaxSaved: round($saving, 2),
                        extra: [
                            'suggested_transfer_amount' => round($transferable / 1000) * 1000,
                            'isa_remaining' => round($isaRemaining, 2),
                            'non_isa_balance' => round($nonIsaBalance, 2),
                            'personal_savings_allowance' => $psa,
                        ],
                    );
                }
            }
        }

        // #7 — Dividend Allowance Harvest
        $dividendAllowanceRaw = $div['allowance'] ?? 500;
        $dividendAllowance = is_array($dividendAllowanceRaw)
            ? (float) ($dividendAllowanceRaw['amount'] ?? 500)
            : (float) $dividendAllowanceRaw;
        $userDividends = (float) ($user->annual_dividend_income ?? 0);
        $hasNonIsaInvestments = InvestmentAccount::query()
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('account_type')->orWhere('account_type', '!=', 'isa');
            })
            ->exists();

        if ($userDividends < $dividendAllowance && $hasNonIsaInvestments) {
            $headroom = $dividendAllowance - $userDividends;
            $divRate = match ($userBand) {
                'basic' => (float) ($div['basic_rate'] ?? 0.0875),
                'higher' => (float) ($div['higher_rate'] ?? 0.3375),
                'additional' => (float) ($div['additional_rate'] ?? 0.3935),
                default => 0.0875,
            };
            $saving = $headroom * $divRate;

            $recommendations[] = new StrategyRecommendation(
                type: 'dividend_allowance_harvest',
                category: StrategyCategory::Allowance,
                priority: StrategyPriority::Low,
                title: sprintf('You have £%s of unused Dividend Allowance', number_format((int) $headroom)),
                description: sprintf(
                    'The first £%s of dividend income is tax-free. Holding income-paying shares outside your ISA up to that amount returns the full payout — about £%s a year at your tax band.',
                    number_format((int) $dividendAllowance),
                    number_format((int) round($saving)),
                ),
                estimatedAnnualTaxSaved: round($saving, 2),
                extra: [
                    'unused_allowance' => round($headroom, 2),
                    'dividend_rate' => $divRate,
                ],
            );
        }

        return $recommendations;
    }

    /**
     * Strategy #4 — Salary Sacrifice for National Insurance Relief.
     *
     * Fires for an employed user who has a workplace DC pension where
     * salary_sacrifice is null or false. Saving = annual_contribution × the
     * employee's marginal NI rate, plus (if the employer rebates a share of
     * their NI saving) annual_contribution × employer_NI_rate × rebate_pct.
     * NI rates and the upper-earnings-limit are read from TaxConfigService.
     *
     * @return list<StrategyRecommendation>
     */
    private function buildSalarySacrificeRecommendation(User $user): array
    {
        if ((string) ($user->employment_status ?? '') !== 'employed') {
            return [];
        }

        $eligiblePensions = DCPension::query()
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('salary_sacrifice')->orWhere('salary_sacrifice', false);
            })
            ->where('monthly_contribution_amount', '>', 0)
            ->get(['id', 'monthly_contribution_amount', 'employer_ni_rebate_pct']);

        if ($eligiblePensions->isEmpty()) {
            return [];
        }

        $annualContribution = (float) $eligiblePensions->sum(fn ($p) => (float) $p->monthly_contribution_amount * 12);
        if ($annualContribution <= 0) {
            return [];
        }

        $ni = $this->taxConfig->getNationalInsurance();
        $employee = $ni['class_1']['employee'] ?? [];
        $employer = $ni['class_1']['employer'] ?? [];
        $uel = (float) ($employee['upper_earnings_limit'] ?? 50270);
        $mainRate = (float) ($employee['main_rate'] ?? 0.08);
        $additionalRate = (float) ($employee['additional_rate'] ?? 0.02);
        $employerRate = (float) ($employer['rate'] ?? 0.15);

        $income = (float) ($user->annual_employment_income ?? 0);
        $afterSacrifice = $income - $annualContribution;

        // NI saving applies on the slice between (income − contribution) and income.
        if ($income <= $uel) {
            $employeeSaving = $annualContribution * $mainRate;
        } elseif ($afterSacrifice >= $uel) {
            $employeeSaving = $annualContribution * $additionalRate;
        } else {
            $belowUelSlice = $uel - $afterSacrifice;
            $aboveUelSlice = $income - $uel;
            $employeeSaving = $belowUelSlice * $mainRate + $aboveUelSlice * $additionalRate;
        }

        $rebatePct = (float) $eligiblePensions->max('employer_ni_rebate_pct');
        $employerSaving = 0.0;
        if ($rebatePct > 0) {
            $employerSaving = $annualContribution * $employerRate * $rebatePct;
        }

        $totalSaving = $employeeSaving + $employerSaving;
        if ($totalSaving < 1) {
            return [];
        }

        $description = $rebatePct > 0
            ? sprintf(
                'Switching your £%s annual workplace pension contribution to salary sacrifice saves £%s in National Insurance every year. Your employer rebates %d%% of their NI saving back into the pot on top.',
                number_format((int) $annualContribution),
                number_format((int) round($totalSaving)),
                (int) round($rebatePct * 100),
            )
            : sprintf(
                'Switching your £%s annual workplace pension contribution to salary sacrifice saves £%s in National Insurance every year, with no change to your take-home pay.',
                number_format((int) $annualContribution),
                number_format((int) round($totalSaving)),
            );

        return [new StrategyRecommendation(
            type: 'salary_sacrifice_ni',
            category: StrategyCategory::Allowance,
            priority: StrategyPriority::Medium,
            title: sprintf(
                'Save around £%s a year by moving your pension contributions to salary sacrifice',
                number_format((int) round($totalSaving)),
            ),
            description: $description,
            estimatedAnnualTaxSaved: round($totalSaving, 2),
            extra: [
                'annual_contribution' => round($annualContribution, 2),
                'employee_ni_saving' => round($employeeSaving, 2),
                'employer_ni_rebate_pct' => $rebatePct,
                'employer_ni_rebate_saving' => round($employerSaving, 2),
            ],
        )];
    }

    /**
     * Strategy #6 — Bed & ISA Capital Gains Harvest within the Annual Exempt Amount.
     *
     * Fires when the user has non-ISA holdings with positive unrealised gains
     * AND remaining ISA allowance to absorb the proceeds. Saving =
     * min(total_unrealised_gain, AEA) × CGT rate for the user's band, sized
     * by the proceeds that fit inside the remaining ISA allowance.
     *
     * @return list<StrategyRecommendation>
     */
    private function buildBedAndIsaRecommendation(User $user): array
    {
        $isa = $this->taxConfig->getISAAllowances();
        $cgt = $this->taxConfig->getCapitalGainsTax();
        $isaAllowance = (float) ($isa['annual_allowance'] ?? 20000);
        $aea = (float) ($cgt['annual_exempt_amount'] ?? 3000);

        $isaUsed = $this->estimateIsaSubscriptionsThisYear($user);
        $isaRemaining = max(0, $isaAllowance - $isaUsed);

        if ($isaRemaining <= 0 || $aea <= 0) {
            return [];
        }

        $userBand = $this->bandFromIncome($this->taxableIncomeFor($user));
        $cgtRate = match ($userBand) {
            'basic' => (float) ($cgt['basic_rate'] ?? 0.18),
            'higher', 'additional' => (float) ($cgt['higher_rate'] ?? 0.24),
            default => 0.18,
        };

        $nonIsaAccountIds = InvestmentAccount::query()
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('account_type')->orWhere('account_type', '!=', 'isa');
            })
            ->pluck('id')
            ->all();

        if (empty($nonIsaAccountIds)) {
            return [];
        }

        $holdings = Holding::query()
            ->where('holdable_type', InvestmentAccount::class)
            ->whereIn('holdable_id', $nonIsaAccountIds)
            ->get(['quantity', 'purchase_price', 'current_price', 'current_value', 'cost_basis']);

        if ($holdings->isEmpty()) {
            return [];
        }

        $totalUnrealisedGain = 0.0;
        $totalCurrentValueWithGain = 0.0;
        foreach ($holdings as $h) {
            $current = (float) ($h->current_value ?? 0);
            if ($current <= 0 && $h->quantity && $h->current_price) {
                $current = (float) $h->quantity * (float) $h->current_price;
            }
            $costBasis = (float) ($h->cost_basis ?? 0);
            if ($costBasis <= 0 && $h->quantity && $h->purchase_price) {
                $costBasis = (float) $h->quantity * (float) $h->purchase_price;
            }
            if ($current <= 0 || $costBasis <= 0) {
                continue;
            }
            $gain = $current - $costBasis;
            if ($gain > 0) {
                $totalUnrealisedGain += $gain;
                $totalCurrentValueWithGain += $current;
            }
        }

        if ($totalUnrealisedGain <= 0) {
            return [];
        }

        $realisableGains = min($totalUnrealisedGain, $aea);
        $proceeds = min(
            $isaRemaining,
            $totalCurrentValueWithGain * ($realisableGains / $totalUnrealisedGain),
        );

        $saving = $realisableGains * $cgtRate;
        if ($saving < 1) {
            return [];
        }

        return [new StrategyRecommendation(
            type: 'bed_and_isa',
            category: StrategyCategory::Allowance,
            priority: StrategyPriority::Medium,
            title: sprintf(
                'Bed & ISA — shelter £%s of gains tax-free this year',
                number_format((int) round($realisableGains)),
            ),
            description: sprintf(
                'You hold £%s of unrealised gains outside your ISA. Selling around £%s of holdings and rebuying them inside the ISA crystallises gains within your £%s tax-free Capital Gains allowance — saving roughly £%s on a future sale and sheltering all future growth.',
                number_format((int) round($totalUnrealisedGain)),
                number_format((int) round($proceeds)),
                number_format((int) $aea),
                number_format((int) round($saving)),
            ),
            estimatedAnnualTaxSaved: round($saving, 2),
            extra: [
                'total_unrealised_gain' => round($totalUnrealisedGain, 2),
                'realisable_within_aea' => round($realisableGains, 2),
                'estimated_proceeds_to_transfer' => round($proceeds, 2),
                'cgt_rate' => $cgtRate,
                'isa_remaining' => round($isaRemaining, 2),
                'annual_exempt_amount' => $aea,
            ],
        )];
    }

    /**
     * Strategy #12 — Pension contribution for a non-earning spouse.
     *
     * Fires when household_calculation_mode = single_earner_couple AND the
     * spouse is under 75. £2,880 net contribution → £3,600 gross via 25%
     * basic-rate uplift = £720/yr direct saving. Spouse age is resolved from
     * a family_members row (relationship in spouse/partner/wife/husband/
     * civil_partner) or, failing that, a linked spouse user — when no DOB is
     * known we keep firing because single_earner_couple normally implies a
     * working-age partner.
     *
     * @return list<StrategyRecommendation>
     */
    private function buildNonEarnerSpousePensionRecommendation(
        User $user,
        ?TaxStrategyHouseholdInput $household,
        string $mode,
    ): array {
        if ($mode !== 'single_earner_couple') {
            return [];
        }

        $spouseAge = $this->resolveSpouseAge($user);
        if ($spouseAge !== null && $spouseAge >= 75) {
            return [];
        }

        $netContribution = 2880.0;
        $governmentUplift = 720.0;
        $existingBalance = (float) ($household?->spouse_existing_pension_balance ?? 0);

        $balanceLine = $existingBalance > 0
            ? sprintf(' On top of their existing £%s pot.', number_format((int) $existingBalance))
            : '';

        return [new StrategyRecommendation(
            type: 'non_earner_spouse_pension',
            category: StrategyCategory::Household,
            priority: StrategyPriority::Medium,
            title: sprintf(
                'Top up your spouse\'s pension by £%s — instant £%s of free money',
                number_format((int) $netContribution),
                number_format((int) $governmentUplift),
            ),
            description: sprintf(
                'A £%s contribution to your spouse\'s personal pension is grossed up to £%s by the government, even though they have no earnings. That\'s £%s a year of free uplift, plus a separate 25%% tax-free lump sum and another Personal Allowance in retirement.%s',
                number_format((int) $netContribution),
                number_format((int) ($netContribution + $governmentUplift)),
                number_format((int) $governmentUplift),
                $balanceLine,
            ),
            estimatedAnnualTaxSaved: round($governmentUplift, 2),
            extra: [
                'net_contribution' => $netContribution,
                'gross_contribution' => $netContribution + $governmentUplift,
                'government_uplift' => $governmentUplift,
                'spouse_existing_pension_balance' => round($existingBalance, 2),
                'spouse_age' => $spouseAge,
            ],
        )];
    }

    /**
     * Resolve the spouse's age in whole years, or null when unknown. Looks at
     * family_members (any spouse-class relationship) first, then falls back
     * to a linked spouse user when present on the User model.
     */
    private function resolveSpouseAge(User $user): ?int
    {
        $member = FamilyMember::query()
            ->where('user_id', $user->id)
            ->whereNotNull('date_of_birth')
            ->whereIn('relationship', ['spouse', 'partner', 'wife', 'husband', 'civil_partner'])
            ->first(['date_of_birth']);

        if ($member !== null) {
            return $this->ageOf($member->date_of_birth);
        }

        $spouseId = $user->spouse_id ?? null;
        if (! empty($spouseId)) {
            $spouseUser = User::find($spouseId);
            if ($spouseUser instanceof User) {
                return $this->ageOf($spouseUser->date_of_birth);
            }
        }

        return null;
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    /**
     * Age in whole years from a date_of_birth, or null when DOB is unknown.
     * Mirrors FamilyMember::getAgeAttribute. Used by lifecycle strategies.
     */
    private function ageOf(mixed $dateOfBirth): ?int
    {
        if ($dateOfBirth === null) {
            return null;
        }

        $dob = $dateOfBirth instanceof \DateTimeInterface
            ? \Carbon\Carbon::instance($dateOfBirth)
            : \Carbon\Carbon::parse((string) $dateOfBirth);

        return (int) $dob->diffInYears(now());
    }

    /**
     * Composed taxable-income view: employment income + dividend income +
     * estimated savings interest. Acts as a best-effort proxy for HMRC
     * taxable income above the Personal Allowance — refines as more income
     * sources are captured. Used by income-band strategies.
     */
    private function taxableIncomeFor(User $user): float
    {
        $employment = (float) ($user->annual_employment_income ?? 0);
        $dividends = (float) ($user->annual_dividend_income ?? 0);
        $interest = $this->estimateAnnualInterest($user);

        return $employment + $dividends + $interest;
    }

    /**
     * Remaining Pension Annual Allowance for the current tax year, after the
     * user's existing contributions and any in-flight slider override.
     * Floored at 0; does not currently account for tapered AA (Phase 5) or
     * carry-forward (Phase 4).
     */
    private function availableAnnualAllowance(User $user, ?TaxStrategyOverridesDTO $overrides): float
    {
        $pension = $this->taxConfig->getPensionAllowances();
        $aa = (float) ($pension['annual_allowance'] ?? 60000);
        $used = $this->estimatePensionContributionThisYear($user, $overrides);

        return max(0, $aa - $used);
    }

    private function position(string $key, string $label, float $amount, float $used, string $owner): array
    {
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
        ];
    }

    private function personalSavingsAllowanceFor(float $income): float
    {
        return $this->psaForBand($this->bandFromIncome($income));
    }

    private function bandFromIncome(float $income): string
    {
        $thresholds = $this->bandThresholds();

        return match (true) {
            $income >= $thresholds['additional'] && $thresholds['additional'] > 0 => 'additional',
            $income >= $thresholds['higher'] && $thresholds['higher'] > 0 => 'higher',
            default => 'basic',
        };
    }

    private function bandRateFor(User $user): float
    {
        return match ($this->bandFromIncome((float) ($user->annual_employment_income ?? 0))) {
            'basic' => 0.20,
            'higher' => 0.40,
            'additional' => 0.45,
        };
    }

    private function estimateAnnualInterest(User $user): float
    {
        return (float) SavingsAccount::query()
            ->where('user_id', $user->id)
            ->where('is_isa', false)
            ->get()
            ->sum(fn ($acc) => (float) $acc->current_balance * (float) $acc->interest_rate);
    }

    private function estimateIsaSubscriptionsThisYear(User $user): float
    {
        // V1: use ISA balances as a proxy for current-year subscriptions when
        // no per-subscription record exists. Conservative approximation.
        return (float) SavingsAccount::query()
            ->where('user_id', $user->id)
            ->where('is_isa', true)
            ->sum('current_balance');
    }

    private function estimatePensionContributionThisYear(User $user, ?TaxStrategyOverridesDTO $overrides): float
    {
        if ($overrides?->pensionContributionPercent !== null) {
            return (float) ($user->annual_employment_income ?? 0) * ($overrides->pensionContributionPercent / 100);
        }

        $monthlyTotal = (float) \App\Models\DCPension::where('user_id', $user->id)->sum('monthly_contribution_amount');

        return $monthlyTotal * 12;
    }
}
