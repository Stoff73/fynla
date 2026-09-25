<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;

/**
 * Marriage Allowance transfer, for both couple modes. The one home for the
 * rule: it used to live in the single-earner bundle only, so a dual-earner
 * couple with a low-earning spouse never saw it (B7), and a user with no
 * taxable income was promised the full saving (B6).
 */
final class MarriageAllowanceStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $reducible = $this->math->marriageAllowanceTransfer($context->user, $context->mode, $context->household);
        $saving = round($reducible * $this->math->bandRateForBand('basic'), 2);
        if ($saving < 1) {
            return [];
        }

        $amount = $this->math->marriageAllowanceAmount();

        return [new StrategyRecommendation(
            type: 'marriage_allowance_transfer',
            category: StrategyCategory::Household,
            priority: StrategyPriority::Medium,
            title: 'Claim Marriage Allowance',
            description: sprintf(
                'Your spouse or civil partner can transfer £%s of their unused Personal Allowance to you, saving you around £%s a year in income tax.',
                number_format((int) $amount),
                number_format((int) round($saving)),
            ),
            estimatedAnnualTaxSaved: $saving,
            extra: ['amount_transferred' => $amount],
        )];
    }
}
