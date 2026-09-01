<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Retirement\PensionProjector;

/**
 * W-0264 — the per-product risk override never worked for any real user.
 *
 * Three services gated it on the PAIR `has_custom_risk && risk_preference`, and
 * nothing wrote `has_custom_risk`: the column defaults to `false`, no client sent it,
 * and no form exposed it. So every override a user set was discarded.
 *
 * Two of the three readers were moved onto `RiskPreferenceService::getProductRiskOverride()`
 * — which reads the preference itself and does not consult the flag — and
 * `PensionProjector` was left on the raw pair. That is the reader the item singles out
 * as **"the one that changes the projection"**.
 *
 * These assert on `growth_rate_used`, which `projectTotalRetirementIncome()` publishes
 * per scheme — the user-visible consequence, not an internal. The first test is the
 * whole item: a pension carrying a preference with the flag unset (the shape of every
 * row written before `PensionNormaliser` began deriving it) must still have its
 * override honoured. It is the case a backfill would otherwise have been needed for,
 * and the reason none was written.
 */
beforeEach(function () {
    $this->projector = app(PensionProjector::class);
    $this->user = User::factory()->create(['date_of_birth' => now()->subYears(40)]);
});

function w0264Pension(User $user, string $scheme, ?string $risk, bool $flag): DCPension
{
    return DCPension::factory()->create([
        'user_id' => $user->id,
        'scheme_name' => $scheme,
        'risk_preference' => $risk,
        'has_custom_risk' => $flag,
        'current_fund_value' => 100_000,
        'retirement_age' => 65,
    ]);
}

function w0264Rate(array $result, string $scheme): float
{
    return (float) collect($result['dc_projections'] ?? $result['dc_pensions'] ?? [])
        ->firstWhere('scheme_name', $scheme)['growth_rate_used'];
}

it('honours an override on a row written before anything wrote the flag', function () {
    // has_custom_risk FALSE with a preference set — the legacy row shape, and the
    // exact case the old `has_custom_risk && risk_preference` gate discarded.
    w0264Pension($this->user, 'Legacy High', 'high', false);
    w0264Pension($this->user, 'No Preference', null, false);

    $result = $this->projector->projectTotalRetirementIncome($this->user->id);

    expect(w0264Rate($result, 'Legacy High'))
        ->toBeGreaterThan(w0264Rate($result, 'No Preference'));
});

it('honours an override on a row written since the normaliser derives the flag', function () {
    w0264Pension($this->user, 'Modern High', 'high', true);
    w0264Pension($this->user, 'Modern None', null, true);

    $result = $this->projector->projectTotalRetirementIncome($this->user->id);

    expect(w0264Rate($result, 'Modern High'))
        ->toBeGreaterThan(w0264Rate($result, 'Modern None'));
});

it('treats a set flag with no preference as no override, not as an error', function () {
    // The inverse legacy shape. There is nothing to honour, so it must fall through
    // to the user's main level rather than throwing or inventing a default risk.
    w0264Pension($this->user, 'Flag Only', null, true);
    w0264Pension($this->user, 'Neither', null, false);

    $result = $this->projector->projectTotalRetirementIncome($this->user->id);

    expect(w0264Rate($result, 'Flag Only'))->toBe(w0264Rate($result, 'Neither'));
});
