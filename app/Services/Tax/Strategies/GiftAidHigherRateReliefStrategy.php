<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Models\User;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;

/**
 * Strategy #13 — Gift Aid.
 *
 * Two cases, one action (CSJ 2026-09-28: a donor who does not use Gift Aid
 * should see what it would give them):
 *   - Donates under Gift Aid and pays above the basic rate: the extra relief
 *     they reclaim through Self Assessment, priced by the tax engine.
 *   - Donates without Gift Aid: what declaring it adds — 25p per £1 to the
 *     charity, and any higher-rate relief back to them. Offered only when
 *     they paid enough tax this year to cover what the charity claims
 *     (https://www.gov.uk/donating-to-charity/gift-aid).
 */
final class GiftAidHigherRateReliefStrategy implements TaxStrategy
{
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

        $band = $this->math->bandFromIncomeFor($user, $this->math->taxableIncomeFor($user));

        return $user->is_gift_aid
            ? $this->declared($user, $donations, $band)
            : $this->notDeclared($user, $donations, $band);
    }

    /** @return list<StrategyRecommendation> */
    private function declared(User $user, float $donations, string $band): array
    {
        $factor = $this->math->giftAidReclaimFactor($band);
        if ($factor <= 0) {
            return [];
        }

        // Priced by the tax engine rather than a flat factor (audit 2026-09-27):
        // the Personal Savings Allowance, dividends and the allowance won back
        // in the taper band all change what the donor reclaims.
        $saving = floor($this->math->giftAidDonorReclaim($user));
        if ($saving < 1) {
            return [];
        }

        return [new StrategyRecommendation(
            type: 'gift_aid_higher_rate_relief',
            category: StrategyCategory::Allowance,
            priority: StrategyPriority::Medium,
            title: sprintf(
                'Reclaim £%s on your Gift Aid donations via Self Assessment',
                number_format((int) floor($saving)),
            ),
            description: sprintf(
                'You give around £%s a year through Gift Aid. The charity already reclaims basic-rate tax — but as a %s-rate taxpayer you can claim back another £%s yourself when you file your Self Assessment.',
                number_format((int) $donations),
                $band === 'additional' ? 'additional' : 'higher',
                number_format((int) floor($saving)),
            ),
            estimatedAnnualTaxSaved: round($saving, 2),
            extra: [
                'annual_donations' => round($donations, 2),
                'uses_gift_aid' => true,
                'reclaim_factor' => $factor,
                'tax_band' => $band,
            ],
        )];
    }

    /** @return list<StrategyRecommendation> */
    private function notDeclared(User $user, float $donations, string $band): array
    {
        $basic = $this->math->bandRateForBand('basic');
        if ($basic <= 0 || $basic >= 1) {
            return [];
        }
        // What the charity claims: the basic-rate tax on the grossed-up gift.
        $charityGets = floor($donations / (1 - $basic) - $donations);
        // Only a donor who paid at least that much tax this year qualifies.
        if ($charityGets < 1 || $this->math->incomeTaxNow($user) < $charityGets) {
            return [];
        }
        $saving = floor($this->math->giftAidReclaimIfDeclared($user, $donations));

        return [new StrategyRecommendation(
            type: 'gift_aid_higher_rate_relief',
            category: StrategyCategory::Allowance,
            priority: StrategyPriority::Medium,
            title: $saving >= 1
                ? sprintf('Add Gift Aid to your donations: the charity gets £%s more and you get £%s back', number_format($charityGets), number_format($saving))
                : sprintf('Add Gift Aid to your donations: the charity gets £%s more', number_format($charityGets)),
            description: sprintf(
                'You give around £%s a year without Gift Aid. With a Gift Aid declaration the charity claims £%s from HM Revenue and Customs (HMRC) on top, at no cost to you.%s',
                number_format((int) $donations),
                number_format($charityGets),
                $saving >= 1 ? sprintf(' As a %s-rate taxpayer you can also claim back £%s yourself.', $band, number_format($saving)) : '',
            ),
            // A charity's gain is not the user's tax saved; only their own
            // reclaim counts in the headline total.
            estimatedAnnualTaxSaved: $saving >= 1 ? round($saving, 2) : null,
            extra: [
                'annual_donations' => round($donations, 2),
                'uses_gift_aid' => false,
                'charity_gift_aid' => $charityGets,
                'tax_band' => $band,
            ],
        )];
    }
}
