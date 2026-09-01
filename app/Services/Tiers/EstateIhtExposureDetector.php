<?php

declare(strict_types=1);

namespace App\Services\Tiers;

use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use App\Services\NetWorth\NetWorthService;

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
        private readonly NetWorthService $netWorthService,
        private readonly IHTCalculationService $ihtCalculation,
    ) {}

    /**
     * Detect whether the user has a likely Inheritance Tax exposure.
     *
     * @return array{exposed: bool, headline: string, estimated_liability_gbp: float, unmodelled_relief_caveat: string|null, projected_pension_inclusion_caveat: string|null}
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
        // "Exposed" is now the engine's own answer — an estate over its allowances
        // with a bill to show — rather than a second threshold test that could
        // disagree with the figure printed beside it.
        $exposed = $estimatedLiabilityGbp > 0.0;

        return [
            'exposed' => $exposed,
            'headline' => $this->buildHeadline(
                $exposed,
                $estimatedLiabilityGbp,
                // W-0467 — whether the figure is one estate or two is the ENGINE's
                // answer, read back off the result it just returned. Re-deriving
                // `married && sharing` here would be a second predicate that can
                // drift from the one the figure was actually computed under.
                ($calculation['is_married'] ?? false) && ($calculation['data_sharing_enabled'] ?? false),
                // Married with a linked account but NOT pooling — sharing off or
                // revoked. Also read off the calculation, for the same reason.
                ($calculation['is_married'] ?? false) && ! ($calculation['data_sharing_enabled'] ?? false),
                // Married, and the partner has NO linked account at all. `is_married`
                // is FALSE here — it requires `$spouse !== null` — which is why this
                // needs the marital status the engine published rather than that flag
                // (compliance-lead, second pass, §11).
                in_array($calculation['marital_status'] ?? null, ['married', 'civil_partnership'], true)
                    && ! ($calculation['is_married'] ?? false),
            ),
            'estimated_liability_gbp' => $estimatedLiabilityGbp,
            // W-0466 — the teaser is the ONLY Inheritance Tax figure `/m` shows, so
            // if the caveat belongs anywhere on that surface it belongs here. Passed
            // straight through from the engine, words and all: `/m` computes nothing
            // and there is one home for the sentence (Rule 20).
            'unmodelled_relief_caveat' => $calculation['unmodelled_relief_caveat'] ?? null,
            // W-0507 — the teaser's figure is the SECOND-DEATH projected one, so the
            // caveat about what the projection leaves out belongs to it at least as
            // much as to the full table. It was published by the engine and passed to
            // the full table only; the teaser is a different component behind the
            // upgrade gate, so a free user — which is every user who has not upgraded,
            // and every demo persona a prospective customer sees — read the figure
            // with neither caveat attached.
            'projected_pension_inclusion_caveat' => $calculation['projected_pension_inclusion_caveat'] ?? null,
        ];
    }

    /**
     * W-0467 — the teaser said "your estate" of a figure that is frequently neither
     * that person's estate nor payable on their death.
     *
     * For a married household with sharing on, `iht_liability` is a POOLED,
     * SECOND-DEATH figure covering both estates against doubled allowances. The
     * same user's own first-death liability, with spouse exemption, is typically
     * £0. "could be subject to up to" hedged the MAGNITUDE and nothing else — the
     * two things wrong with it were WHOSE and WHEN.
     *
     * This is the only Inheritance Tax figure `/m` ever shows, on a Free-tier
     * conversion surface, to users who cannot open the calculation behind it.
     *
     * Wording chosen by CSJ, 2026-08-23. The single/unmarried branch keeps "your
     * estate" because for them the figure genuinely is their own.
     */
    private function buildHeadline(
        bool $exposed,
        float $estimatedLiabilityGbp,
        bool $pooledHousehold,
        bool $marriedButNotPooled = false,
        bool $marriedButUnlinked = false,
    ): string {
        if (! $exposed) {
            return 'Your estate is currently below the Inheritance Tax threshold.';
        }

        if ($estimatedLiabilityGbp <= 0.0) {
            return 'Your estate exceeds the Inheritance Tax threshold — planning now can help protect your family.';
        }

        $formatted = '£'.number_format((int) $estimatedLiabilityGbp);

        if ($pooledHousehold) {
            return "Your household could face up to {$formatted} in Inheritance Tax on the second death. Upgrading unlocks estate planning tools you could use to explore ways of reducing it.";
        }

        // `compliance-lead` finding F, 2026-08-24 — **the "single" branch was never
        // the single branch.** The predicate is "not pooled", and `$isMarried`
        // itself requires a LINKED spouse account, so this caught three groups:
        // genuinely single or unmarried users, married users whose partner has no
        // Fynla account, and married users with sharing off or revoked. To the last
        // two it said "Your estate could be subject to up to £X" — a married person
        // whose own first-death liability, with the spouse exemption, is typically
        // £0. **The exact defect W-0467 exists to fix, alive in the branch nobody
        // changed.**
        //
        // W-0347 makes it grow: sharing is now genuinely opt-in and revocable, so
        // the married-but-not-pooled group gets larger as a direct consequence.
        //
        // The FIGURE was already right — W-0154 F3 moved the allowance doubling onto
        // `$poolsSpouse`, so these users get single allowances against their own
        // assets. It was only ever the sentence.
        // Linked, but not sharing. **"Linking your accounts" is the wrong
        // instruction here** — reaching this branch REQUIRES a linked account, so
        // the first version told the user to do a thing they had already done. What
        // is switched off is the sharing permission (compliance-lead, second pass).
        if ($marriedButNotPooled) {
            return "Based on your own records alone, your estate could be subject to up to {$formatted} in Inheritance Tax. This figure does not allow for anything passing to your partner. Sharing your finances with them gives a fuller picture.";
        }

        // Married, and the partner has no Fynla account. **This group was falling to
        // the final branch and being told "Your estate could be subject to…" — the
        // original W-0467 defect, still live after the first fix**, because the
        // predicate keyed on `is_married`, which is false without a linked account.
        //
        // W-0347 makes this population grow: linking is now an invitation that can
        // be ignored, so "married in profile, no linked partner" is an ordinary
        // steady state rather than a transient one.
        if ($marriedButUnlinked) {
            return "Based on your own records alone, your estate could be subject to up to {$formatted} in Inheritance Tax. This figure does not allow for anything passing to your partner. Linking your accounts gives a fuller picture.";
        }

        return "Your estate could be subject to up to {$formatted} in Inheritance Tax. Upgrading unlocks estate planning tools you could use to explore ways of reducing it.";
    }
}
