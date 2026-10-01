<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;

/**
 * Marriage Allowance transfer, in both directions and both couple modes.
 * The statutory tests live in TaxStrategyMath::marriageAllowance (ITA 2007
 * Part 3 Chapter 3A); this class only words the result.
 */
final class MarriageAllowanceStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $position = $this->math->marriageAllowance($context->user, $context->mode, $context->household);
        if ($position === null || $position['saving'] < 1) {
            return [];
        }

        $amount = $this->math->marriageAllowanceAmount();
        $saving = $position['saving'];

        return [new StrategyRecommendation(
            type: 'marriage_allowance_transfer',
            category: StrategyCategory::Household,
            priority: StrategyPriority::Medium,
            title: 'Claim Marriage Allowance',
            description: $position['direction'] === 'to_user'
                ? sprintf(
                    'Your spouse or civil partner can transfer £%s of their Personal Allowance to you, saving your household around £%s a year in income tax.',
                    number_format((int) $amount),
                    number_format((int) floor($saving)),
                )
                : sprintf(
                    'You can transfer £%s of your Personal Allowance to your spouse or civil partner, saving your household around £%s a year in income tax.',
                    number_format((int) $amount),
                    number_format((int) floor($saving)),
                ),
            estimatedAnnualTaxSaved: $saving,
            extra: [
                'amount_transferred' => $amount,
                'transfer_direction' => $position['direction'],
                // Each partner's income, so the steps can say why this way round.
                'user_income' => $position['user_income'],
                'spouse_income' => $position['spouse_income'],
                // What the giver pays once their allowance is smaller (s55B(6)),
                // already netted off the saving.
                'transferor_extra_tax' => $position['transferor_extra_tax'],
            ],
        )];
    }
}
