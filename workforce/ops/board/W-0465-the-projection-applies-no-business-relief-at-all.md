---
id: W-0465
title: The Inheritance Tax projection applies no Business Property Relief at all, so the current and projected columns disagree by the whole relief
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
claimed_by: null
severity: medium
surfaces: [web, m]
created: 2026-08-23T19:20:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0091, W-0463]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: tax-compliance-reviewer finding R6, re-review of a1d36b90b, 2026-08-23 — surfaced while verifying the F2 taper-base fix, not caused by it
---

## Intent

`$projectedBusiness` sums `current_value` over non-exempt business assets and
`$projectedNetEstate` subtracts no relief. So a £6m trading business shows **£4.25m of
relief today and none at death** — the two columns of the same table disagree by the
whole relief, on a screen whose purpose is to compare them.

**This makes a comment in `assessTaxPosition()` accidentally true.** It says the
projection "does not model business relief separately, so its net estate is already
relief-free", which is why passing `$projectedNetEstate` as its own taper base is
correct. That reasoning holds **only because the projection is wrong about relief.**
Fixing this item invalidates that comment and the taper base must be revisited in the
same change.

## Acceptance

1. The projection applies the same capped, pro-rata relief as the current calculation —
   one mechanism (`EstateAssetAggregatorService::applyBusinessPropertyRelief()`), not a
   second implementation (Rule 20).
2. `projected_business_relief_deduction` is published and rendered beside the current
   figure, web and `/m` (Rule 19). `IHTPlanning.vue` already reads the key with a
   fallback to the current value; that fallback becomes wrong once this lands.
3. The projected taper base is re-derived, since it can no longer be assumed relief-free.
4. Business values are projected forward before relief is applied — relieving a
   present-day value against a future cap understates the charge.
5. `tax-compliance-reviewer` on the change.

## Working notes

- 2026-08-23 — Raised from the re-review. **No persona can exercise it**: the largest
  business interest on the dev database is £750,000, below the cap, so the relief is
  100% in both columns and the disagreement is invisible on every fixture. Needs the
  purpose-built shape used in `BusinessPropertyReliefCapTest`.
