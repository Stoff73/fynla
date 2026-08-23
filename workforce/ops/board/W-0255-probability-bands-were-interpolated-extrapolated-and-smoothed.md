---
id: W-0255
title: The "80% Probability" band was a straight line between two neighbours, the 5th percentile sat below anything simulated, and the first two years were pulled toward the start value
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0024-cycle4-investment-projection.md
owner: build-lead
status: handoff
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T20:00:00Z
claimed: 2026-08-22T20:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: [MonteCarloEngine, RetirementProjectionService-extractProbabilityBands, MonteCarloResults.vue]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Raised by `peak-earners-c4` (R-23) as three method faults to fix in the same visit as D-20,
because they are why the figures were unauditable.

- **The 20th percentile was not a percentile.** `p20 = p10 + (p25 − p10) × 0.67`, a linear
  interpolation between two neighbours, presented to the user as "80% Probability".
- **The 5th percentile was extrapolated below the simulated range** —
  `p5 = p10 − (p25 − p10) × 0.33`, a number outside anything the simulation produced.
- **Years 1 and 2 were blended 30% and 10% toward the start value** for presentational
  smoothing, so the displayed early years were deliberately not the model's output.

Three mechanisms implemented this: the investment service, a near-identical private copy in
`RetirementProjectionService`, and a third fallback inside `MonteCarloResults.vue`.

## Acceptance

1. Every band shown is a percentile the simulation measured. ✓
2. Nothing extrapolated past the ends of the distribution. ✓
3. No presentational smoothing of any year. ✓
4. If an interpolated band were kept, the label would have to stop calling it a percentile —
   not applicable; the interpolation is gone and the label is now true. ✓

## Working notes

- One home: `MonteCarloEngine::extractProbabilityBands()`, with
  `BAND_PERCENTILES = [5,10,15,20,25,50,75,90]` requested by the two projection services.
- `calculatePercentiles()`'s default point set is unchanged, so `RunMonteCarloSimulation`'s
  goal-probability path is byte-identical. `runMultiAssetSimulation`'s positional
  `$finalPercentiles[2]` median read is fixed to a keyed lookup — it would have silently
  become the 20th percentile.
- A band the simulation did not measure is now **absent** from the payload rather than
  invented.
- `MonteCarloResults.vue`'s client-side interpolation fallback deleted.
- Guarded by `tests/Unit/Services/Shared/ProbabilityBandExtractionTest.php`, using a skewed
  distribution where the measured 20th is nowhere near the interpolant.
