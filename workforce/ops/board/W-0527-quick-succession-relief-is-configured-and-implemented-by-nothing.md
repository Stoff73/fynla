---
id: W-0527
title: Quick succession relief is configured and implemented by nothing
mission: null
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
claimed_by: null
severity: low
surfaces: [web, m, ios]
created: 2026-08-29T13:30:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-29
prior_art_found: [W-0463, W-0465, W-0091]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: W-0463 independent verification, 2026-08-29 — CSJ ruled these four are work and need doing
---

## Intent

**CSJ, 2026-08-29, on the four reliefs W-0463 left open: "the four reliefs are work, and
need to be done."** This is one of the four, broken out so each can be claimed, gated and
verified on its own rather than sitting inside a structural item.

`getQuickSuccessionRelief()` has zero callers. IHTA 1984 s141 reduces the tax where the same property is taxed twice within five years — a beneficiary who inherits and then dies shortly after. The relief tapers by the years between the two deaths.

## Notes

Lowest of the four: it needs a second death within five years of an inherited estate, which the app does not currently model at all. Filed so it is a decision rather than an omission.

## Acceptance

1. `getQuickSuccessionRelief()` has a real caller on the estate path, and the `quick_succession_relief` configuration
   decides the outcome — no literal reproduces any part of it (Rule 2).
2. A household that qualifies sees the relief in **both** the current and the projected
   Inheritance Tax column. W-0465 records what happens when only one column gets a
   relief: the two halves of a comparison table disagree by the whole of it.
3. A household that does not qualify is unaffected — before/after on a non-qualifying
   estate shows no movement.
4. Tests that fail with the relief removed, not just tests that pass with it present.
5. `tax-compliance-reviewer` — it moves Inheritance Tax for every qualifying household.
