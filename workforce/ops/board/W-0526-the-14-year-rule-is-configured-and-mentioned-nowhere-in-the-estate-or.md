---
id: W-0526
title: The 14-year rule is configured and mentioned nowhere in the estate or tax services
mission: null
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
claimed_by: null
severity: medium
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

`getFourteenYearRule()` has zero callers and the key has zero hits across `app/Services/Estate/` and `app/Services/Tax/`. A chargeable lifetime transfer made up to fourteen years before death can still consume nil rate band against a later gift, through the seven-year cumulation that runs from the earlier transfer rather than from death.

## Notes

This interacts with `FailedGiftTaxCalculator` and `calculateNRBDeductionForGifts()`, which cumulate over seven years from the death date. Getting it wrong in either direction moves the band: too short understates the tax, too long overstates it.

## Acceptance

1. `getFourteenYearRule()` has a real caller on the estate path, and the `fourteen_year_rule` configuration
   decides the outcome — no literal reproduces any part of it (Rule 2).
2. A household that qualifies sees the relief in **both** the current and the projected
   Inheritance Tax column. W-0465 records what happens when only one column gets a
   relief: the two halves of a comparison table disagree by the whole of it.
3. A household that does not qualify is unaffected — before/after on a non-qualifying
   estate shows no movement.
4. Tests that fail with the relief removed, not just tests that pass with it present.
5. `tax-compliance-reviewer` — it moves Inheritance Tax for every qualifying household.
