<?php

declare(strict_types=1);

namespace App\Services\Tiers;

use App\Constants\TaxDefaults;
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

        // Fix #1: IHT rate from TaxConfigService, fallback to TaxDefaults constant (Rule #3).
        $ihtRate = (float) ($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE);

        // Fix #2: NRB/RNRB fallback to TaxDefaults constants, not raw literals (Rule #3).
        $nrb = (float) ($ihtConfig['nil_rate_band'] ?? TaxDefaults::NRB);
        $rnrb = (float) ($ihtConfig['residence_nil_rate_band'] ?? TaxDefaults::RNRB);
        $threshold = $nrb + $rnrb;

        $netWorthData = $this->netWorthService->calculateNetWorth($user);
        $netWorth = (float) ($netWorthData['net_worth'] ?? 0.0);

        // Fix #5: exposure flag uses NRB+RNRB threshold (consistent with liability calc below).
        $exposed = $netWorth > $threshold;
        $estimatedLiabilityGbp = $exposed
            ? max(0.0, round(($netWorth - $threshold) * $ihtRate, 2))
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
