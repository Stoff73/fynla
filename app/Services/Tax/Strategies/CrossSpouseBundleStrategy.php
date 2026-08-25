<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Models\Investment\InvestmentAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

/**
 * Dual_earner bundle of cross-spouse coordination strategies — GIA rebalance
 * to the lower-band spouse, ISA coordination when one has maxed and the
 * other hasn't.
 */
final class CrossSpouseBundleStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        if ($context->mode !== 'dual_earner' || ! $context->household instanceof TaxStrategyHouseholdInput) {
            return [];
        }

        $user = $context->user;
        $household = $context->household;
        $suggestions = [];
        // M11 — gate user on HMRC band over total taxable income (employment
        // + dividends + interest). A user with basic-rate employment but
        // significant dividends/interest IS a higher-rate payer for the
        // dividend rate-delta calculation below.
        $userBand = $this->math->bandFromIncome($this->math->taxableIncomeFor($user));
        $spouseIncome = (float) ($household->spouse_annual_income ?? 0)
            + (float) ($household->spouse_annual_dividends ?? 0);
        $spouseBand = $this->math->bandFromIncome($spouseIncome);
        $hasGia = InvestmentAccount::query()
            ->where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('account_type')->orWhere('account_type', '!=', 'isa');
            })
            ->exists();

        // Recommend rebalancing GIA / dividend-bearing holdings to the lower-earner spouse
        if ($hasGia && $userBand !== 'basic' && $spouseBand === 'basic') {
            $userDivRate = $this->math->dividendRateForBand($userBand);
            $spouseDivRate = $this->math->dividendRateForBand('basic');
            $rateDelta = max(0.0, $userDivRate - $spouseDivRate);

            $suggestions[] = [
                'type' => 'gia_rebalance',
                'priority' => 'high',
                'title' => "Consider holding eligible non-ISA investments in your spouse's name",
                'description' => sprintf(
                    'Based on the income recorded, your dividend rate is %s%% and your spouse\'s is %s%%. A saving may be possible, but it depends on which holdings and dividends are transferred and on both partners\' full current-year income. Transfers between eligible spouses or civil partners can usually be made without an immediate Capital Gains Tax charge, but the recipient normally inherits the original acquisition cost and may pay tax on a later disposal. Inheritance Tax spouse-exemption conditions also apply.',
                    number_format($userDivRate * 100, 2),
                    number_format($spouseDivRate * 100, 2),
                ),
                'requires_advice' => true,
                'user_dividend_rate' => $userDivRate,
                'spouse_dividend_rate' => $spouseDivRate,
                'rate_delta' => round($rateDelta, 4),
            ];
        }

        // ISA contribution coordination — both have £20k allowances
        $userIsaUsed = $this->math->estimateIsaSubscriptionsThisYear($user);
        $spouseIsaBalance = $household->spouse_isa_balance;
        $spouseIsaUseKnown = $spouseIsaBalance !== null && (float) $spouseIsaBalance === 0.0;
        $isaAmount = (float) ($this->taxConfig->getISAAllowances()['annual_allowance'] ?? 20000);
        if ($userIsaUsed >= $isaAmount && $spouseIsaUseKnown) {
            $suggestions[] = [
                'type' => 'isa_coordination',
                'priority' => 'medium',
                'title' => 'Use your spouse\'s ISA allowance',
                'description' => "You've maxed your ISA, and your spouse has no ISA balance on file, so they may still have up to £".number_format((int) $isaAmount).' of ISA capacity this tax year. Confirm any subscriptions made elsewhere before contributing.',
            ];
        }

        return array_map(
            fn (array $arr) => StrategyRecommendation::fromArray(StrategyCategory::Household, $arr),
            $suggestions,
        );
    }
}
