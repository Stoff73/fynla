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
                    'Your spouse or civil partner can transfer £%s of their unused Personal Allowance to you, saving your household around £%s a year in income tax.',
                    number_format((int) $amount),
                    number_format((int) round($saving)),
                )
                : sprintf(
                    'You can transfer £%s of your unused Personal Allowance to your spouse or civil partner, saving your household around £%s a year in income tax.',
                    number_format((int) $amount),
                    number_format((int) round($saving)),
                ),
            estimatedAnnualTaxSaved: $saving,
            extra: ['amount_transferred' => $amount, 'transfer_direction' => $position['direction']],
        )];
    }
}
