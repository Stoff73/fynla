<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Services\Stores\SavingsStore;
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

        // Shared-allowance allocation pass: a higher-saving ISA strategy may
        // already have claimed part of the one overall allowance — only the
        // remaining pool capacity is available to this evaluation.
        if ($context->isaPoolCap !== null) {
            $isaRemaining = min($isaRemaining, max(0.0, $context->isaPoolCap));
        }

        if ($isaRemaining <= 0) {
            return [];
        }

        // Keep the accounts so the recommendation can shelter the taxable
        // interest with the least cash, starting with the highest captured
        // rate. A blended-rate proposal is inaccurate when one account earns
        // nothing and another earns materially more.
        $nonIsaAccounts = app(SavingsStore::class)->forUser($user)
            ->where('user_id', $user->id)
            ->where('is_isa', false);
        $nonIsaBalance = (float) $nonIsaAccounts->sum('current_balance');

        // Use the centralised helper — it normalises the interest_rate
        // column convention (decimals 0.04 vs. percents 4.0) the same way
        // every other strategy does, so the resulting interest figure is
        // comparable across the dashboard.
        $annualInterest = $this->math->estimateAnnualInterest($user);
        $psa = $this->math->psaForBand($userBand);

        // Only fire when interest exceeds PSA — otherwise no tax to save.
        if ($annualInterest <= $psa) {
            return [];
        }

        $excessInterest = $annualInterest - $psa;
        $remainingInterest = $excessInterest;
        $remainingIsa = min($isaRemaining, $nonIsaBalance);
        $transferable = 0.0;
        $interestSheltered = 0.0;
        $targetAccounts = [];

        $rankedAccounts = $nonIsaAccounts->sortByDesc(function ($account) {
            $rate = (float) $account->interest_rate;

            return $rate > 1 ? $rate / 100 : $rate;
        });

        foreach ($rankedAccounts as $account) {
            $rate = (float) $account->interest_rate;
            if ($rate > 1) {
                $rate /= 100;
            }
            $balance = max(0.0, (float) $account->current_balance);
            if ($rate <= 0 || $balance <= 0 || $remainingInterest <= 0 || $remainingIsa <= 0) {
                continue;
            }

            $amount = min($balance, $remainingIsa, $remainingInterest / $rate);
            $sheltered = min($remainingInterest, $amount * $rate);
            $transferable += $amount;
            $interestSheltered += $sheltered;
            $remainingInterest -= $sheltered;
            $remainingIsa -= $amount;
            $provider = trim((string) ($account->institution ?? $account->account_name ?? ''));
            if ($provider !== '') {
                $targetAccounts[] = $provider;
            }
        }

        if ($transferable <= 1000) {
            return [];
        }

        $marginalRate = $this->math->bandRateFor($user);
        $saving = $interestSheltered * $marginalRate;
        $reportedTransfer = round($transferable, 2);
        $accountDirection = $targetAccounts === []
            ? ''
            : ' Start with '.implode(', ', array_unique($targetAccounts)).', where the captured rate is highest.';

        return [new StrategyRecommendation(
            type: 'isa_topup_vs_psa',
            category: StrategyCategory::Allowance,
            priority: StrategyPriority::High,
            title: sprintf(
                'Wrap £%s of cash savings inside an ISA before April 5',
                number_format((int) round($reportedTransfer)),
            ),
            description: sprintf(
                'You hold £%s of non-ISA cash producing £%s of annual interest, of which £%s is above your £%s Savings Allowance. Wrapping £%s in an ISA shelters that taxable interest — saving around £%s a year while the captured rates and tax position remain the same.%s',
                number_format((int) $nonIsaBalance),
                number_format($annualInterest, 2),
                number_format($interestSheltered, 2),
                number_format((int) $psa),
                number_format((int) round($reportedTransfer)),
                number_format((int) round($saving)),
                $accountDirection,
            ),
            estimatedAnnualTaxSaved: round($saving, 2),
            extra: [
                'suggested_transfer_amount' => $reportedTransfer,
                'isa_remaining' => round($isaRemaining, 2),
                'non_isa_balance' => round($nonIsaBalance, 2),
                'annual_interest' => round($annualInterest, 2),
                'taxable_interest_sheltered' => round($interestSheltered, 2),
                'target_accounts' => array_values(array_unique($targetAccounts)),
                'personal_savings_allowance' => $psa,
            ],
        )];
    }
}
