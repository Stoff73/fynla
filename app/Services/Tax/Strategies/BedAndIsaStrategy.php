<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

/**
 * Strategy #6 — Bed & ISA Capital Gains Harvest within the Annual Exempt Amount.
 *
 * Fires when the user has non-ISA holdings with positive unrealised gains
 * AND remaining ISA allowance to absorb the proceeds. Saving = the gains
 * embedded in the proceeds that fit inside the remaining ISA allowance
 * (capped at min(total_unrealised_gain, AEA)) × CGT rate for the user's band.
 */
final class BedAndIsaStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;
        $isa = $this->taxConfig->getISAAllowances();
        $cgt = $this->taxConfig->getCapitalGainsTax();
        $isaAllowance = (float) ($isa['annual_allowance'] ?? 20000);
        $aea = (float) ($cgt['annual_exempt_amount'] ?? 3000);

        $isaUsed = $this->math->estimateIsaSubscriptionsThisYear($user);
        $isaRemaining = max(0, $isaAllowance - $isaUsed);

        // Shared-allowance allocation pass: a higher-saving ISA strategy may
        // already have claimed part of the one overall allowance — only the
        // remaining pool capacity is available to this evaluation.
        if ($context->isaPoolCap !== null) {
            $isaRemaining = min($isaRemaining, max(0.0, $context->isaPoolCap));
        }

        if ($isaRemaining <= 0 || $aea <= 0) {
            return [];
        }

        $userBand = $this->math->bandFromIncome($this->math->taxableIncomeFor($user));
        $cgtRate = match ($userBand) {
            'basic' => (float) ($cgt['basic_rate'] ?? 0.18),
            'higher', 'additional' => (float) ($cgt['higher_rate'] ?? 0.24),
            default => 0.18,
        };

        $nonIsaAccountIds = InvestmentAccount::query()
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('account_type')->orWhere('account_type', '!=', 'isa');
            })
            ->pluck('id')
            ->all();

        if (empty($nonIsaAccountIds)) {
            return [];
        }

        $holdings = Holding::query()
            ->where('holdable_type', InvestmentAccount::class)
            ->whereIn('holdable_id', $nonIsaAccountIds)
            ->get(['quantity', 'purchase_price', 'current_price', 'current_value', 'cost_basis']);

        if ($holdings->isEmpty()) {
            return [];
        }

        $totalUnrealisedGain = 0.0;
        $totalCurrentValueWithGain = 0.0;
        foreach ($holdings as $h) {
            $current = (float) ($h->current_value ?? 0);
            if ($current <= 0 && $h->quantity && $h->current_price) {
                $current = (float) $h->quantity * (float) $h->current_price;
            }
            $costBasis = (float) ($h->cost_basis ?? 0);
            if ($costBasis <= 0 && $h->quantity && $h->purchase_price) {
                $costBasis = (float) $h->quantity * (float) $h->purchase_price;
            }
            if ($current <= 0 || $costBasis <= 0) {
                continue;
            }
            $gain = $current - $costBasis;
            if ($gain > 0) {
                $totalUnrealisedGain += $gain;
                $totalCurrentValueWithGain += $current;
            }
        }

        if ($totalUnrealisedGain <= 0) {
            return [];
        }

        $realisableGains = min($totalUnrealisedGain, $aea);
        $proceeds = min(
            $isaRemaining,
            $totalCurrentValueWithGain * ($realisableGains / $totalUnrealisedGain),
        );

        // The gains actually crystallised are only those embedded in the
        // proceeds that fit inside the remaining ISA allowance (whether the
        // clip came from the user's own subscriptions or a shared-pool cap) —
        // the honest saving on the smaller amount, not the full AEA figure.
        if ($totalCurrentValueWithGain > 0.0) {
            $realisableGains = min(
                $realisableGains,
                $proceeds * ($totalUnrealisedGain / $totalCurrentValueWithGain),
            );
        }

        $saving = $realisableGains * $cgtRate;
        if ($saving < 1) {
            return [];
        }

        return [new StrategyRecommendation(
            type: 'bed_and_isa',
            category: StrategyCategory::Allowance,
            priority: StrategyPriority::Medium,
            title: sprintf(
                'Bed & ISA — shelter £%s of gains tax-free this year',
                number_format((int) round($realisableGains)),
            ),
            description: sprintf(
                'You hold £%s of unrealised gains outside your ISA. Selling around £%s of holdings and rebuying them inside the ISA crystallises gains within your £%s tax-free Capital Gains allowance — saving roughly £%s on a future sale and sheltering all future growth.',
                number_format((int) round($totalUnrealisedGain)),
                number_format((int) round($proceeds)),
                number_format((int) $aea),
                number_format((int) round($saving)),
            ),
            estimatedAnnualTaxSaved: round($saving, 2),
            extra: [
                'total_unrealised_gain' => round($totalUnrealisedGain, 2),
                'realisable_within_aea' => round($realisableGains, 2),
                'estimated_proceeds_to_transfer' => round($proceeds, 2),
                'cgt_rate' => $cgtRate,
                'isa_remaining' => round($isaRemaining, 2),
                'annual_exempt_amount' => $aea,
            ],
        )];
    }
}
