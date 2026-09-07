<?php

declare(strict_types=1);

/**
 * W-0462 — the cost beside the saving.
 *
 * "Save £74,987" is true and incomplete. The estate really does pay that much
 * less tax, and on the peak_earners household the family really does receive
 * £37,891 LESS, because the gift that buys the reduced rate leaves the estate
 * too. Only one of those was ever on the page.
 *
 *     Δresidue = (r_s − r_r)·E − S·(1 − r_r)
 *
 * The break-even is S = E·(r_s − r_r)/(1 − r_r), which is 6.25% of the
 * chargeable estate at 40/36 AND ONLY at 40/36. The constant must never be
 * written down (Rule 2) — these tests move the rates to prove it is not.
 */
function residueEffect(float $standard, float $reduced, float $estate, float $shortfall): float
{
    return round((($standard - $reduced) * $estate) - ($shortfall * (1 - $reduced)), 2);
}

function breakEven(float $standard, float $reduced, float $estate): float
{
    return round($reduced >= 1.0 ? 0.0 : ($estate * ($standard - $reduced)) / (1 - $reduced), 2);
}

it('reproduces the peak_earners household the gate measured', function () {
    // £858,780 chargeable, £112,878 shortfall: the reviewer's £37,890.72.
    expect(residueEffect(0.40, 0.36, 858_780, 112_878))->toBe(-37_890.72);
});

it('puts the break-even at 6.25% of the chargeable estate at 40/36', function () {
    expect(breakEven(0.40, 0.36, 858_780))->toBe(53_673.75)
        ->and(round(breakEven(0.40, 0.36, 858_780) / 858_780 * 100, 2))->toBe(6.25);
});

it('leaves the beneficiaries better off below the break-even', function () {
    // Half the break-even shortfall: the tax saved exceeds the gift.
    expect(residueEffect(0.40, 0.36, 858_780, 26_836))->toBeGreaterThan(0.0);
});

it('breaks exactly even at the break-even, which is what makes it one', function () {
    $estate = 858_780.0;

    expect(residueEffect(0.40, 0.36, $estate, breakEven(0.40, 0.36, $estate)))
        ->toBeLessThan(0.01)
        ->toBeGreaterThan(-0.01);
});

it('moves the break-even when the rates move, so 6.25% is nowhere written down', function () {
    // At 31/29 the break-even is 2/71 of the estate, not 1/16. A hardcoded
    // 6.25% — or a hardcoded 0.0625 — fails here.
    $estate = 858_780.0;
    $moved = breakEven(0.31, 0.29, $estate);

    expect(round($moved / $estate * 100, 2))->toBe(2.82)
        ->and($moved)->not->toBe(breakEven(0.40, 0.36, $estate));
});
