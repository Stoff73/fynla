<?php

declare(strict_types=1);

namespace App\Services\Tiers;

use App\Models\User;
use App\Services\NetWorth\NetWorthService;
use App\Services\TaxConfigService;

/**
 * Cheap Inheritance Tax exposure signal for the Free/Tier1 Estate teaser.
 *
 * Intentionally avoids running the full Estate engine — it uses the canonical
 * net-worth figure (spec §10.2) and NRB/RNRB from TaxConfigService (Rule #3).
 * Returns currency and a plain headline only — no scores (Rule #13).
 */
class EstateIhtExposureDetector
{
    /** UK Inheritance Tax rate above thresholds. */
    private const IHT_RATE = 0.40;

    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly NetWorthService $netWorthService,
    ) {}

    /**
     * Detect whether the user has a likely Inheritance Tax exposure.
     *
     * @return array{exposed: bool, headline: string, estimated_liability_gbp: float}
     */
    public function detect(User $user): array
    {
        $ihtConfig = $this->taxConfig->getInheritanceTax();

        $nrb = (float) ($ihtConfig['nil_rate_band'] ?? 325000);
        $rnrb = (float) ($ihtConfig['residence_nil_rate_band'] ?? 175000);
        $threshold = $nrb + $rnrb;

        $netWorthData = $this->netWorthService->calculateNetWorth($user);
        $netWorth = (float) ($netWorthData['net_worth'] ?? 0.0);

        $exposed = $netWorth > $nrb;
        $estimatedLiabilityGbp = $exposed
            ? max(0.0, round(($netWorth - $threshold) * self::IHT_RATE, 2))
            : 0.0;

        $headline = $this->buildHeadline($exposed, $netWorth, $threshold, $estimatedLiabilityGbp);

        return [
            'exposed' => $exposed,
            'headline' => $headline,
            'estimated_liability_gbp' => $estimatedLiabilityGbp,
        ];
    }

    private function buildHeadline(
        bool $exposed,
        float $netWorth,
        float $threshold,
        float $estimatedLiabilityGbp,
    ): string {
        if (! $exposed) {
            return 'Your estate is currently below the Inheritance Tax threshold.';
        }

        if ($estimatedLiabilityGbp <= 0.0) {
            return 'Your estate may be approaching the Inheritance Tax threshold — planning now can help protect your family.';
        }

        $formatted = '£'.number_format((int) $estimatedLiabilityGbp);

        return "Your estate could be subject to up to {$formatted} in Inheritance Tax — upgrading unlocks personalised planning to help reduce this.";
    }
}
