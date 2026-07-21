<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Models\Investment\InvestmentAccount;
use App\Services\Stores\SavingsStore;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

/**
 * Single_earner_couple bundle of asset-shifting strategies — Marriage
 * Allowance transfer, savings-to-spouse, ISA top-up in spouse's name, GIA-
 * to-spouse for CGT/Dividend allowance shelter. Each emitted suggestion
 * surfaces as a separate recommendation card on the dashboard.
 */
final class AssetShiftingBundleStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        if ($context->mode !== 'single_earner_couple') {
            return [];
        }

        $user = $context->user;
        $household = $context->household;
        $suggestions = [];
        $income = $this->taxConfig->getIncomeTax();
        $marriageAmount = (float) ($income['marriage_allowance']['amount'] ?? 1260);
        $isaAmount = (float) ($this->taxConfig->getISAAllowances()['annual_allowance'] ?? 20000);

        // M11 — HMRC band uses TOTAL taxable income (employment + dividends +
        // savings interest), not employment alone. Computed once because
        // taxableIncomeFor() runs a SavingsAccount query.
        $userBand = $this->math->bandFromIncome($this->math->taxableIncomeFor($user));

        // 1. Marriage Allowance transfer (basic-rate recipients only). A £45k
        // employee with £10k of dividend income is a higher-rate taxpayer for
        // MA purposes and must not see this suggestion.
        if ($user->marriage_allowance_eligible && $userBand === 'basic') {
            // M8 — basic-rate from TaxConfigService, not hardcoded 0.20.
            $basicRate = $this->math->bandRateForBand('basic');
            $estimatedSaving = $marriageAmount * $basicRate;
            $suggestions[] = [
                'type' => 'marriage_allowance_transfer',
                'priority' => 'medium',
                'title' => 'Claim Marriage Allowance',
                'description' => 'Your spouse can transfer £'.number_format((int) $marriageAmount).' of unused Personal Allowance to you, saving roughly £'.number_format((int) $estimatedSaving).' per year in income tax.',
                'estimated_annual_tax_saved' => round($estimatedSaving, 2),
                'amount_transferred' => $marriageAmount,
            ];
        }

        // 2. Savings → spouse: only price this when the campaign has explicitly
        // confirmed that the non-earning spouse has no existing savings. A
        // balance without an account rate is not enough to infer their interest
        // income or unused tax-free capacity.
        $userSavings = app(SavingsStore::class)->forUser($user)
            ->where('user_id', $user->id)
            ->where('is_isa', false);
        $userSavingsTotal = (float) $userSavings->sum('current_balance');
        $annualInterest = (float) $userSavings->sum(function ($acc) {
            $r = (float) $acc->interest_rate;
            if ($r > 1) {
                $r /= 100;
            }

            return (float) $acc->current_balance * $r;
        });
        $userAvgRate = $userSavingsTotal > 0 ? $annualInterest / $userSavingsTotal : 0.0;

        $personalAllowance = (float) ($income['personal_allowance'] ?? 12570);
        $startingRate = (float) ($income['starting_rate_for_savings']['band'] ?? 5000);
        $spouseInterestCapacity = $personalAllowance + $startingRate + $this->math->psaForBand('basic');
        $spouseSavingsKnownZero = $household?->spouse_existing_savings_balance !== null
            && (float) $household->spouse_existing_savings_balance === 0.0;
        $maxTransferableByCapacity = $userAvgRate > 0 ? $spouseInterestCapacity / $userAvgRate : 0.0;
        $suggestedTransfer = $spouseSavingsKnownZero
            ? min($userSavingsTotal, $maxTransferableByCapacity)
            : 0.0;

        if ($suggestedTransfer > 1000) {
            // Marginal rate on savings interest follows the same total-income
            // band as MA above. bandRateFor() uses raw employment so we resolve
            // via the cached $userBand instead.
            $userBandRate = $this->math->bandRateForBand($userBand);
            $psaBasic = $this->math->psaForBand('basic');
            $stackedCapacity = $personalAllowance + $startingRate + $psaBasic;
            $userPersonalAllowance = $this->math->personalAllowanceFor($user);
            $userStartingRate = max(
                0.0,
                $startingRate - max(0.0, $this->math->nonSavingsIncomeFor($user) - $userPersonalAllowance),
            );
            $userTaxFreeInterest = $userStartingRate + $this->math->psaForBand($userBand);
            $taxableInterestBefore = max(0.0, $annualInterest - $userTaxFreeInterest);
            $annualInterestMoved = min($annualInterest, $suggestedTransfer * $userAvgRate);
            $taxableInterestSheltered = min($taxableInterestBefore, $annualInterestMoved);
            $estimatedAnnualTaxSaved = $taxableInterestSheltered * $userBandRate;
            $reportedTransfer = round($suggestedTransfer, 2);
            $suggestions[] = [
                'type' => 'savings_to_spouse',
                'priority' => 'high',
                'title' => sprintf(
                    'Gift £%s of savings to your spouse for up to £%s of interest tax-free every year',
                    number_format((int) round($reportedTransfer)),
                    number_format((int) $stackedCapacity),
                ),
                'description' => sprintf(
                    'Their Personal Allowance (£%s), Starting Rate for Savings (£%s) and Personal Savings Allowance (£%s) can stack because they are recorded as having no earnings or savings. The estimate only counts the £%s of your interest currently above your own tax-free savings amounts. A cash gift between eligible spouses or civil partners normally has no immediate Capital Gains Tax charge and may qualify for Inheritance Tax spouse exemption; ownership changes and conditions apply.',
                    number_format((int) $personalAllowance),
                    number_format((int) $startingRate),
                    number_format((int) $psaBasic),
                    number_format((int) round($taxableInterestSheltered)),
                ),
                'suggested_transfer_amount' => $reportedTransfer,
                'estimated_annual_tax_saved' => round($estimatedAnnualTaxSaved, 2),
                'annual_interest_moved' => round($annualInterestMoved, 2),
                'taxable_interest_sheltered' => round($taxableInterestSheltered, 2),
                'spouse_personal_allowance' => $personalAllowance,
                'spouse_starting_rate_for_savings' => $startingRate,
                'spouse_personal_savings_allowance' => $psaBasic,
                'spouse_stacked_interest_capacity' => $stackedCapacity,
                'requires_advice' => true,
            ];
        }

        // 3. ISA top-up in spouse's name — uses fresh £20k allowance
        $spouseIsaBalance = $household?->spouse_existing_isa_balance;
        if ($spouseIsaBalance !== null && (float) $spouseIsaBalance === 0.0) {
            $suggestions[] = [
                'type' => 'isa_topup_spouse',
                'priority' => 'medium',
                'title' => "Open or top up an ISA in your spouse's name",
                'description' => 'They have no ISA balance on file and may have up to £'.number_format((int) $isaAmount).' of ISA allowance available this tax year. Confirm any subscriptions made elsewhere before contributing.',
                'available_allowance' => round($isaAmount, 2),
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

            $suggestions[] = [
                'type' => 'gia_to_spouse',
                'priority' => 'medium',
                'title' => 'Hold non-ISA investments in your spouse\'s name',
                'description' => sprintf(
                    'Your spouse may have allowance available against up to £%s of net gains and £%s of dividends. Confirm their gains, losses and dividends for this tax year before relying on either amount. A transfer between eligible spouses or civil partners can usually be made without an immediate Capital Gains Tax charge, but they inherit the original acquisition cost and may pay tax on a later disposal.',
                    number_format((int) $cgtAllowance),
                    number_format((int) $divAllowance),
                ),
                'requires_advice' => true,
            ];
        }

        return array_map(
            fn (array $arr) => StrategyRecommendation::fromArray(StrategyCategory::Household, $arr),
            $suggestions,
        );
    }
}
