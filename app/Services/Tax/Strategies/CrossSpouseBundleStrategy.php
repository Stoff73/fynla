<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
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
        $spouseBand = (string) ($household->spouse_psa_band ?? 'basic');

        // Recommend rebalancing GIA / dividend-bearing holdings to the lower-earner spouse
        if ($userBand !== 'basic' && $spouseBand === 'basic') {
            $userDivRate = $this->math->dividendRateForBand($userBand);
            $spouseDivRate = $this->math->dividendRateForBand('basic');
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
        $userIsaUsed = $this->math->estimateIsaSubscriptionsThisYear($user);
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

        return array_map(
            fn (array $arr) => StrategyRecommendation::fromArray(StrategyCategory::Household, $arr),
            $suggestions,
        );
    }
}
