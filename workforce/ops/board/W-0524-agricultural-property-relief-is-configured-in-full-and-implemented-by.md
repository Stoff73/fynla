---
id: W-0524
title: Agricultural Property Relief is configured in full and implemented by nothing
mission: null
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
claimed_by: null
severity: high
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

An estate holding farmland gets NO relief at all. `getAgriculturalRelief()` has zero callers anywhere in `app/`, while the configuration carries the same capped, shared structure Business Property Relief uses — `allowance_cap`, `cap_in_effect`, `relief_above_cap`, and `cap_shared_with_bpr: true` meaning the two reliefs draw on ONE £2,500,000 allowance.

## Notes

W-0465 built exactly this shape for Business Property Relief in `EstateAssetAggregatorService::applyBusinessPropertyRelief()` — capped, pro-rata, dated by `allowance_cap_effective_date`. **That is the model to follow, and `cap_shared_with_bpr` means it cannot be a parallel copy:** the two reliefs must allocate from the same allowance or a household holding both gets £5,000,000 of relief where the law gives £2,500,000.

## Acceptance

1. `getAgriculturalRelief()` has a real caller on the estate path, and the `agricultural_property_relief` configuration
   decides the outcome — no literal reproduces any part of it (Rule 2).
2. A household that qualifies sees the relief in **both** the current and the projected
   Inheritance Tax column. W-0465 records what happens when only one column gets a
   relief: the two halves of a comparison table disagree by the whole of it.
3. A household that does not qualify is unaffected — before/after on a non-qualifying
   estate shows no movement.
4. Tests that fail with the relief removed, not just tests that pass with it present.
5. `tax-compliance-reviewer` — it moves Inheritance Tax for every qualifying household.
