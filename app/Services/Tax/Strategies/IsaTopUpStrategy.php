<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Models\SavingsAccount;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

/**
 * Strategy #5 — ISA Top-Up From Cash Earning Beyond the Savings Allowance.
 *
 * Fires when the user has non-ISA cash savings generating interest above
 * the Personal Savings Allowance for their band AND remaining ISA allowance
 * to absorb the cash. Saving = transferable_balance × avg_rate × marginal_rate.
 */
final class IsaTopUpStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;
        $isa = $this->taxConfig->getISAAllowances();
        $isaAllowance = (float) ($isa['annual_allowance'] ?? 20000);
        $userBand = $this->math->bandFromIncome($this->math->taxableIncomeFor($user));

        $isaUsed = $this->math->estimateIsaSubscriptionsThisYear($user);
        $isaRemaining = max(0, $isaAllowance - $isaUsed);

        if ($isaRemaining <= 0) {
            return [];
        }

        $nonIsaBalance = (float) SavingsAccount::query()
            ->where('user_id', $user->id)
            ->where('is_isa', false)
            ->sum('current_balance');

        // Use the centralised helper — it normalises the interest_rate
        // column convention (decimals 0.04 vs. percents 4.0) the same way
        // every other strategy does, so the resulting interest figure is
        // comparable across the dashboard.
        $annualInterest = $this->math->estimateAnnualInterest($user);
        $effectiveAvgRate = $nonIsaBalance > 0 ? $annualInterest / $nonIsaBalance : 0.0;
        $psa = $this->math->psaForBand($userBand);

        // Only fire when interest exceeds PSA — otherwise no tax to save.
        if ($annualInterest <= $psa || $effectiveAvgRate <= 0) {
            return [];
        }

        $excessInterest = $annualInterest - $psa;
        $excessBalance = $excessInterest / $effectiveAvgRate;
        $transferable = min($isaRemaining, $nonIsaBalance, $excessBalance);

        if ($transferable <= 1000) {
            return [];
        }

        $marginalRate = $this->math->bandRateFor($user);
        $saving = $transferable * $effectiveAvgRate * $marginalRate;

        return [new StrategyRecommendation(
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
        )];
    }
}
