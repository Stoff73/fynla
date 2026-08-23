<?php

declare(strict_types=1);

namespace App\Services\Tiers;

use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use App\Services\NetWorth\NetWorthService;
use App\Services\TaxConfigService;

/**
 * The Inheritance Tax exposure signal behind the Free Estate teaser.
 *
 * **This used to compute its own answer**, and its docblock said so as a virtue:
 * *"Intentionally avoids running the full Estate engine."* What it actually did was
 * `(netWorth − NRB − RNRB) × 40%` on the LOGGED-IN USER ALONE — single-person
 * allowances always, and no pooling, no gifts, no charitable exemption, no residence
 * cap, no £2m taper, no business relief. It was the only Inheritance Tax figure `/m`
 * ever displayed, so a married household could be quoted one number on the web and a
 * materially different one on their phone (W-0464).
 *
 * **CSJ, 2026-08-23: `/m` must never work anything out.** It shows what the engine
 * computed. So this asks `IHTCalculationService` — the one mechanism — and decides
 * only what to DISPLAY.
 *
 * The performance worry the old comment was answering is real and is handled by the
 * engine's own cache: `calculate()` returns a stored result unless the assets or
 * liabilities hash has moved, so the teaser costs a full run once per data change,
 * not once per page view.
 */
class EstateIhtExposureDetector
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly NetWorthService $netWorthService,
        private readonly IHTCalculationService $ihtCalculation,
    ) {}

    /**
     * Detect whether the user has a likely Inheritance Tax exposure.
     *
     * @return array{exposed: bool, headline: string, estimated_liability_gbp: float}
     */
    public function detect(User $user): array
    {
        // Resolved exactly as `IHTController::calculateIHT` resolves them, or this
        // reads a married couple as single and reintroduces the defect it is here
        // to remove.
        $calculation = $this->ihtCalculation->calculate(
            $user,
            $user->liveSpouse(),
            $user->hasAcceptedSpousePermission(),
        );

        $estimatedLiabilityGbp = round((float) ($calculation['iht_liability'] ?? 0.0), 2);
        $netEstate = (float) ($calculation['total_net_estate'] ?? 0.0);
        $threshold = (float) ($calculation['total_allowances'] ?? 0.0);

        // "Exposed" is now the engine's own answer — an estate over its allowances
        // with a bill to show — rather than a second threshold test that could
        // disagree with the figure printed beside it.
        $exposed = $estimatedLiabilityGbp > 0.0;

        return [
            'exposed' => $exposed,
            'headline' => $this->buildHeadline($exposed, $netEstate, $threshold, $estimatedLiabilityGbp),
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
            return 'Your estate exceeds the Inheritance Tax threshold — planning now can help protect your family.';
        }

        $formatted = '£'.number_format((int) $estimatedLiabilityGbp);

        return "Your estate could be subject to up to {$formatted} in Inheritance Tax — upgrading unlocks personalised planning to help reduce this.";
    }
}
