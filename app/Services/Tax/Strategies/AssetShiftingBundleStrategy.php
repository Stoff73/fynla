<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
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

        // 1. Marriage Allowance transfer (basic-rate recipients only)
        if ($user->marriage_allowance_eligible && $this->math->bandFromIncome((float) ($user->annual_employment_income ?? 0)) === 'basic') {
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
        $spouseInterestCapacity = $personalAllowance + $startingRate + $this->math->psaForBand('basic');
        $existingSpouseSavings = (float) ($household?->spouse_existing_savings_balance ?? 0);
        $spouseUsedInterest = $existingSpouseSavings * $userAvgRate;
        $spouseRemainingInterestCapacity = max(0, $spouseInterestCapacity - $spouseUsedInterest);
        $maxTransferableByCapacity = $userAvgRate > 0 ? $spouseRemainingInterestCapacity / $userAvgRate : 0;
        $suggestedTransfer = min($userSavingsTotal, $maxTransferableByCapacity);

        if ($suggestedTransfer > 1000) {
            $userBandRate = $this->math->bandRateFor($user);
            $estimatedAnnualTaxSaved = $suggestedTransfer * $userAvgRate * $userBandRate;
            $psaBasic = $this->math->psaForBand('basic');
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
            $userDivRate = match ($this->math->bandFromIncome((float) ($user->annual_employment_income ?? 0))) {
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

        return array_map(
            fn (array $arr) => StrategyRecommendation::fromArray(StrategyCategory::Household, $arr),
            $suggestions,
        );
    }
}
