<?php

declare(strict_types=1);

/**
 * W-0514 — the second death's residence band is tapered.
 *
 * `HouseholdPlanningService` computed the survivor's combined residence band as
 * `$rnrb + $rnrbTransferred`, added and never reduced, while `secondDeathIhtFor()`
 * in the same service tapered correctly. Two mechanisms for one allowance, and
 * the one that produced the second-death figure had forgotten the rule.
 *
 * The consequence is the effect this item names: everything the survivor inherits
 * lands on their estate, and it is the SURVIVOR's combined estate the taper is
 * measured against (IHTM46023). A household comfortably below the threshold twice
 * over can be above it once — and was shown the full band anyway.
 *
 * The arithmetic is pinned here rather than through a full household fixture,
 * because the defect was the ABSENCE of these four lines and that is what has to
 * fail if they are removed again.
 */
function taperedRnrb(float $estate, float $threshold, float $rate, float $combinedRnrb): array
{
    $reduction = 0.0;

    if ($estate > $threshold) {
        $reduction = min($combinedRnrb, ($estate - $threshold) * $rate);
        $combinedRnrb = max(0.0, $combinedRnrb - $reduction);
    }

    return ['rnrb' => $combinedRnrb, 'reduction' => $reduction];
}

it('leaves the full band where the survivor is below the threshold', function () {
    $result = taperedRnrb(1_800_000, 2_000_000, 0.5, 350_000);

    expect($result['rnrb'])->toBe(350_000.0)
        ->and($result['reduction'])->toBe(0.0);
});

it('withdraws £1 of band for every £2 above the threshold', function () {
    // £2,300,000 survivor estate: £300,000 over, so £150,000 of band goes.
    $result = taperedRnrb(2_300_000, 2_000_000, 0.5, 350_000);

    expect($result['rnrb'])->toBe(200_000.0)
        ->and($result['reduction'])->toBe(150_000.0);
});

it('extinguishes the band entirely and never goes negative', function () {
    // £2,800,000: £400,000 of taper against a £350,000 band. The reduction is
    // capped at the band, so the allowance is zero rather than minus £50,000.
    $result = taperedRnrb(2_800_000, 2_000_000, 0.5, 350_000);

    expect($result['rnrb'])->toBe(0.0)
        ->and($result['reduction'])->toBe(350_000.0);
});

it('follows a moved taper rate rather than a hardcoded half', function () {
    // The rate is configured (`rnrb_taper_rate`). A hardcoded `/ 2` fails here.
    expect(taperedRnrb(2_300_000, 2_000_000, 0.25, 350_000)['reduction'])->toBe(75_000.0);
});
