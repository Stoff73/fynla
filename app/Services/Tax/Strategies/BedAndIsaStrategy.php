<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Services\Tax\ChargeableGains;
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
        private readonly ChargeableGains $chargeableGains,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;
        $isa = $this->taxConfig->getISAAllowances();
        $cgt = $this->taxConfig->getCapitalGainsTax();
        $isaAllowance = (float) $isa['annual_allowance'];
        $aea = (float) $cgt['annual_exempt_amount'];

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

        // The rate on a gain above the allowance: basic only within the unused
        // basic rate band (TCGA 1992 s1H, TaxStrategyMath::capitalGainsTaxOn).
        $cgtRate = $this->math->capitalGainsTaxOn($user, 1.0)['marginal_rate'];

        // The one home for chargeable gains (item 8): chargeable accounts only,
        // each at the user's share (Rule 6). Shared with the investment ISA cards,
        // which step aside whenever this card can fire.
        $gains = $this->chargeableGains->unrealisedGainsFor($user);
        $totalUnrealisedGain = $gains['gain'];
        $totalCurrentValueWithGain = $gains['value_with_gain'];

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

        // The tax these gains would bear if realised above the allowance later:
        // split between the rates by the unused basic rate band (s1H).
        $saving = $this->math->capitalGainsTaxOn($user, $realisableGains)['tax'];
        if ($saving < 1) {
            return [];
        }

        return [new StrategyRecommendation(
            type: 'bed_and_isa',
            category: StrategyCategory::Allowance,
            priority: StrategyPriority::Medium,
            title: sprintf(
                'Bed & ISA — potentially shelter £%s of gains this year',
                number_format((int) round($realisableGains)),
            ),
            description: sprintf(
                'You hold £%s of unrealised gains outside your ISA. Selling around £%s of holdings and rebuying them inside an ISA could crystallise up to £%s within the annual exempt amount and could avoid roughly £%s of tax on a future sale, but only if you have not already used the allowance. Confirm gains and losses elsewhere this tax year, ISA subscriptions, dealing costs and market risk before acting; future growth inside the ISA is sheltered.',
                number_format((int) round($totalUnrealisedGain)),
                number_format((int) round($proceeds)),
                number_format((int) round($realisableGains)),
                number_format((int) floor($saving)),
            ),
            estimatedAnnualTaxSaved: round($saving, 2),
            requiresAdvice: true,
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
