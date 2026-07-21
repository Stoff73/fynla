<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Models\Investment\InvestmentAccount;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

/**
 * Strategy #7 — Dividend Allowance Harvest.
 *
 * Fires when the user has unused Dividend Allowance AND holds non-ISA
 * investments. Saving = unused_allowance × dividend_rate_for_band.
 */
final class DividendAllowanceHarvestStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;
        $div = $this->taxConfig->getDividendTax();
        $userBand = $this->math->bandFromIncome($this->math->taxableIncomeFor($user));

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

        if ($userDividends >= $dividendAllowance || ! $hasNonIsaInvestments) {
            return [];
        }

        $headroom = $dividendAllowance - $userDividends;
        $divRate = $this->math->dividendRateForBand($userBand);
        $saving = $headroom * $divRate;

        return [new StrategyRecommendation(
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
        )];
    }
}
