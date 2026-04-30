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
                ? $this->buildSpouseAllowanceGridDualEarner($household)
                : null,
            'single_earner_couple' => $this->buildSpouseAllowanceGridNonWorking($household),
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

    // ─── Allowance grid builders (output-DTO-bound, kept here) ──────────

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

        $personalSavingsAllowanceAmount = $this->math->personalSavingsAllowanceFor($employmentIncome);
        $estimatedAnnualInterest = $this->math->estimateAnnualInterest($user);

        $startingRateForSavingsAmount = (float) ($income['starting_rate_for_savings']['band'] ?? $income['starting_rate_for_savings']['amount'] ?? 5000);
        // Starting rate for savings tapers £-for-£ once non-savings income exceeds the
        // Personal Allowance and disappears entirely once it exceeds PA + £5,000. We only
        // surface the position when the user could actually use some of it.
        $nonSavingsIncomeAbovePa = max(0, $employmentIncome - $personalAllowanceAmount);
        $startingRateForSavingsAvailable = max(0, $startingRateForSavingsAmount - $nonSavingsIncomeAbovePa);
        $startingRateForSavingsUsed = min($startingRateForSavingsAvailable, $this->math->estimateAnnualInterest($user));

        $marriageAllowanceAmount = (float) ($income['marriage_allowance']['amount'] ?? 1260);
        $maritalStatus = (string) ($user->marital_status ?? '');
        $isPartnered = in_array($maritalStatus, ['married', 'civil_partnership'], true);
        // Recipient (the working spouse) "uses" the MA only when eligible
        $marriageAllowanceUsed = ($overrides?->marriageAllowanceClaimed === true || $user->marriage_allowance_eligible === true)
            ? $marriageAllowanceAmount
            : 0.0;

        $isaAmount = (float) ($isa['annual_allowance'] ?? 20000);
        $isaUsedThisYear = $this->math->estimateIsaSubscriptionsThisYear($user)
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
        $aaUsed = $this->math->estimatePensionContributionThisYear($user, $overrides);

        $positions = [
            $this->position('personal_allowance', 'Personal Allowance', $personalAllowanceAmount, $personalAllowanceUsed, 'user'),
            $this->position('savings_allowance', 'Savings Allowance', $personalSavingsAllowanceAmount, min($personalSavingsAllowanceAmount, $estimatedAnnualInterest), 'user'),
            $this->position('isa_allowance', 'ISA Allowance', $isaAmount, $isaUsed, 'user'),
            $this->position('cgt_allowance', 'Capital Gains Tax Allowance', $cgtAmount, $cgtUsed, 'user'),
            $this->position('dividend_allowance', 'Dividend Allowance', $divAmount, min($divAmount, $divUsed), 'user'),
            $this->position('pension_annual_allowance', 'Pension Annual Allowance', $aaAmount, $aaUsed, 'user'),
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
            $positions[] = $this->position('marriage_allowance', 'Marriage Allowance', $marriageAllowanceAmount, $marriageAllowanceUsed, 'user');
        }

        return $positions;
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

        $psa = $this->math->psaForBand((string) ($household->spouse_psa_band ?? 'basic'));

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
            $this->position('savings_allowance', 'Savings Allowance', $this->math->psaForBand('basic'), 0.0, 'spouse'),
            $this->position('starting_rate_for_savings', 'Starting Rate for Savings', $startingRateAmount, 0.0, 'spouse'),
            // Marriage Allowance N/A on spouse's grid (it transfers FROM them TO the working spouse)
            $this->position('marriage_allowance', 'Marriage Allowance', $marriageAmount, 0.0, 'spouse'),
            $this->position('isa_allowance', 'ISA Allowance', $isaAmount, min($isaAmount, $existingIsa), 'spouse'),
            $this->position('cgt_allowance', 'Capital Gains Tax Allowance', $cgtAmount, 0.0, 'spouse'),
            $this->position('dividend_allowance', 'Dividend Allowance', $divAmount, 0.0, 'spouse'),
            $this->position('pension_annual_allowance', 'Pension Annual Allowance', $aaAmount, 0.0, 'spouse'),
        ];
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
}
