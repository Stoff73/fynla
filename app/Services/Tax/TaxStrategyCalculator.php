<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\DataTransferObjects\TaxStrategyOutputDTO;
use App\DataTransferObjects\TaxStrategyOverridesDTO;
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
        $assetShifting = [];
        $crossSpouse = [];

        if ($mode === 'dual_earner' && $household instanceof TaxStrategyHouseholdInput) {
            $spouseAllowances = $this->buildSpouseAllowanceGridDualEarner($household);
            $crossSpouse = $this->buildCrossSpouseSuggestions($user, $household);
        } elseif ($mode === 'single_earner_couple') {
            $spouseAllowances = $this->buildSpouseAllowanceGridNonWorking($household);
            $assetShifting = $this->buildAssetShiftingSuggestions($user, $household, $overrides);
        }

        return new TaxStrategyOutputDTO(
            taxYear: $taxYear,
            calculationMode: $mode,
            userAllowances: $userAllowances,
            spouseAllowances: $spouseAllowances,
            assetShiftingSuggestions: $assetShifting,
            crossSpouseSuggestions: $crossSpouse,
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
            $suggestions[] = [
                'type' => 'savings_to_spouse',
                'priority' => 'high',
                'title' => 'Gift £'.number_format(round($suggestedTransfer / 1000) * 1000).' of savings to your spouse',
                'description' => 'Their unused Personal Allowance + Starting Rate for Savings + Personal Savings Allowance can absorb up to £'.number_format((int) $spouseRemainingInterestCapacity).'/year of interest income tax-free. Spousal transfers are exempt from CGT and IHT.',
                'suggested_transfer_amount' => round($suggestedTransfer / 1000) * 1000,
                'estimated_annual_tax_saved' => round($estimatedAnnualTaxSaved, 2),
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
            $suggestions[] = [
                'type' => 'gia_to_spouse',
                'priority' => 'medium',
                'title' => 'Hold non-ISA investments in your spouse\'s name',
                'description' => 'Their unused CGT allowance (£'.number_format((int) $cgtAllowance).'/yr) and Dividend Allowance (£500/yr) can absorb gains and dividends tax-free.',
                'available_cgt_allowance' => $cgtAllowance,
                'available_dividend_allowance' => 500.0,
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
            $suggestions[] = [
                'type' => 'gia_rebalance',
                'priority' => 'high',
                'title' => "Hold GIA in your spouse's name",
                'description' => "Your spouse's lower tax band means dividend and capital-gains income is taxed less in their name. Spousal transfers are exempt from CGT and IHT.",
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

    // ─── Helpers ────────────────────────────────────────────────────────

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
