---
id: W-0486
title: Monte Carlo projections are not monotonic in their own inputs — adding contributions lowers the projection half the time, and the fee signal is smaller than the sampling noise
mission: M-0002-persona-fidelity
owner: null
status: queued
severity: high
surfaces: [web, m, ios]
created: 2026-08-25T16:05:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-25
prior_art_found: [W-0008 (makes it product-facing), W-0217 (gated, high — likely a SYMPTOM of this), W-0252, W-0253, W-0258, W-0259]
prior_art_outcome: none
source: found by tax-compliance-reviewer discharging the W-0008 gate, 2026-08-25; raise-today instruction from that reviewer
---

## Intent

**`MonteCarloEngine::seedFromInputs()` seeds the simulation from its economic inputs.**
Change a fee, a contribution or a return and the seed changes, so the sample is
**re-rolled** — and the difference a user sees between two scenarios is partly a
different random draw rather than a different outcome.

**Measured, not argued** (tax-compliance-reviewer, 2026-08-25):

| Probe | Result |
|---|---|
| £10 increases in monthly contribution | **10 of 20 LOWERED** the projected 20th percentile |
| Fee **increases** | **11 of 20 RAISED** the projected 20th percentile |
| Cutting an adviser fee 0.75% → 0.70% | shows **£3,640 LESS** |
| Sampling range on p20 | **7.49%** |
| A full 1.00pp fee is worth | **6.88%** — **signal ≈ noise** |

**What is NOT wrong:** the level is sound — p50 sits within ±2.6% of the closed form —
and there is **no run-to-run flicker**: identical inputs give identical output, ten
times over. A user refreshing the page sees a stable figure.

**What is wrong is every comparison drawn across two projections** — which is what the
product asks the engine to do. "What if I contribute more", "what does this fee cost
me", "does a higher risk profile project higher" are all differences of two draws.

## Why it is raised now

Pre-existing in the engine, but **W-0008 made it product-facing**: before that change no
fee reached the simulation at all, so no fee comparison could be drawn from it. W-0008
exists precisely to make the adviser fee move the projection — and the measurements
above show that comparison is at the noise floor.

It also explains a retraction on W-0008: a "the adviser fee is worth £8,329" figure did
not reproduce (£3,847 on re-measurement) and was withdrawn. That was not a slip in the
arithmetic — **the quantity is not well defined in this engine.**

**W-0217 is gated and high, and is very likely a symptom of this** — "a lower-risk
portfolio projects higher than a higher-risk one at the conservative percentile" is
precisely what a comparison drawn across two independently-seeded samples produces.
It should be re-examined against the fixed engine before being closed on any other
explanation.

It is also very likely the unexplained residue behind **W-0258 / W-0259** (the p20 inversion
across risk levels) and the D-21 family (W-0252, W-0253), which were closed as
"hump-shaped p20 is a property of the model". Some of that is real; some of it is this.

## The fix

**Common random numbers.** Seed from **identity, not economics** — the user, the
account and the horizon — so two scenarios for the same account are drawn against the
*same* sample path and their difference is the effect rather than the draw. This is the
standard variance-reduction technique for exactly this comparison, and it removes the
noise without adding iterations.

## Acceptance

1. `MonteCarloEngine` seeds from identity, not from economic inputs.
2. **Monotonicity holds where the model says it should.** Adding capital, adding a
   contribution, or removing a fee never lowers the projection at a fixed percentile.
   Pin it with the probes above — 20 increments, not one.
3. **A fee change moves the projection by more than the sampling range.** The W-0008
   comparison becomes meaningful rather than indicative.
4. Caching is re-checked: `MonteCarloSimulator::fingerprintKey()` hashes the economic
   inputs, so a seed change must not reintroduce the D-21 stale-projection defect
   (W-0252 / W-0253).
5. **Re-examine W-0258 / W-0259 against the fixed engine** before accepting "p20 is
   hump-shaped in risk" as the whole explanation.
6. No user's projection level moves materially — this corrects comparisons, not
   central estimates.

## Working notes

- 2026-08-25 tax-compliance-reviewer: *"it doesn't undermine the projections' level,
  only every comparison drawn across them — including the exact comparison W-0008
  exists to make."* Raise-today instruction, given W-0008 ships in PR #716.
