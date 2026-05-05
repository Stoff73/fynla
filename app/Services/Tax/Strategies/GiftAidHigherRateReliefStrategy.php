<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;

/**
 * Strategy #13 — Gift Aid Higher-Rate Relief.
 *
 * Fires when the user is in the higher or additional band AND has captured
 * a positive annual_charitable_donations figure. Personal saving is the
 * extra relief they can reclaim via Self Assessment on top of basic-rate
 * Gift Aid the charity already reclaims:
 *   - higher band:     donations × 0.25
 *   - additional band: donations × 0.3125
 */
final class GiftAidHigherRateReliefStrategy implements TaxStrategy
{
    private const HIGHER_RATE_FACTOR = 0.25;

    private const ADDITIONAL_RATE_FACTOR = 0.3125;

    public function __construct(
        private readonly TaxStrategyMath $math,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;

        $donations = (float) ($user->annual_charitable_donations ?? 0);
        if ($donations <= 0) {
            return [];
        }

        $band = $this->math->bandFromIncome($this->math->taxableIncomeFor($user));
        $factor = match ($band) {
            'higher' => self::HIGHER_RATE_FACTOR,
            'additional' => self::ADDITIONAL_RATE_FACTOR,
            default => 0.0,
        };

        if ($factor <= 0) {
            return [];
        }

        $saving = $donations * $factor;
        if ($saving < 1) {
            return [];
        }

        return [new StrategyRecommendation(
            type: 'gift_aid_higher_rate_relief',
            category: StrategyCategory::Allowance,
            priority: StrategyPriority::Medium,
            title: sprintf(
                'Reclaim £%s on your Gift Aid donations via Self Assessment',
                number_format((int) round($saving)),
            ),
            description: sprintf(
                'You give around £%s a year through Gift Aid. The charity already reclaims basic-rate tax — but as a %s-rate taxpayer you can claim back another £%s yourself when you file your Self Assessment.',
                number_format((int) $donations),
                $band === 'additional' ? 'additional' : 'higher',
                number_format((int) round($saving)),
            ),
            estimatedAnnualTaxSaved: round($saving, 2),
            extra: [
                'annual_donations' => round($donations, 2),
                'reclaim_factor' => $factor,
                'tax_band' => $band,
            ],
        )];
    }
}
