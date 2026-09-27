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
        $userBand = $this->math->bandFromIncomeFor($user, $this->math->taxableIncomeFor($user));

        $dividendAllowanceRaw = $div['allowance'];
        $dividendAllowance = is_array($dividendAllowanceRaw)
            ? (float) $dividendAllowanceRaw['amount']
            : (float) $dividendAllowanceRaw;
        // Every captured dividend, from the income definitions (audit 2026-09-27).
        $userDividends = (float) $this->math->incomePartsFor($user)['dividends'];
        $hasNonIsaInvestments = InvestmentAccount::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('joint_owner_id', $user->id))
            ->where(function ($q) {
                $q->whereNull('account_type')->orWhere('account_type', '!=', 'isa');
            })
            ->exists();

        if ($userDividends >= $dividendAllowance || ! $hasNonIsaInvestments) {
            return [];
        }

        $headroom = $dividendAllowance - $userDividends;
        $divRate = $this->math->dividendRateForBand($userBand);

        return [new StrategyRecommendation(
            type: 'dividend_allowance_harvest',
            category: StrategyCategory::Allowance,
            priority: StrategyPriority::Low,
            title: sprintf('You have £%s of unused Dividend Allowance', number_format((int) $headroom)),
            description: sprintf(
                'The first £%s of dividend income each year is tax-free, and you have £%s of it unused. Dividends from shares held outside an ISA use it first, so up to that amount they pay no tax; above it they are taxed at %s%% at your tax band.',
                number_format((int) $dividendAllowance),
                number_format((int) $headroom),
                rtrim(rtrim(number_format($divRate * 100, 2), '0'), '.'),
            ),
            // Unused allowance saves nothing until dividends exist to use it (ruling 2026-09-25).
            estimatedAnnualTaxSaved: null,
            extra: [
                'unused_allowance' => round($headroom, 2),
                'dividend_rate' => $divRate,
            ],
        )];
    }
}
