---
id: W-0525
title: Normal Expenditure Out of Income is a label on a strategy and is never computed
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

The IHTA 1984 s21 exemption appears in the app **only** as `'strategy_name' => 'Normal Expenditure Out of Income'` in two gifting services. The rule set is configured; `getNormalExpenditureFromIncome()` has zero callers. So the app names the exemption to the user and computes nothing from it.

## Notes

s21 needs three tests met together — the gift is part of a regular pattern, it is made out of INCOME rather than capital, and it leaves the donor able to maintain their usual standard of living. The app already holds income and expenditure per member (`HouseholdCashFlowProjector`), which is the surplus the third test turns on.

## Acceptance

1. `getNormalExpenditureFromIncome()` has a real caller on the estate path, and the `normal_expenditure_out_of_income` configuration
   decides the outcome — no literal reproduces any part of it (Rule 2).
2. A household that qualifies sees the relief in **both** the current and the projected
   Inheritance Tax column. W-0465 records what happens when only one column gets a
   relief: the two halves of a comparison table disagree by the whole of it.
3. A household that does not qualify is unaffected — before/after on a non-qualifying
   estate shows no movement.
4. Tests that fail with the relief removed, not just tests that pass with it present.
5. `tax-compliance-reviewer` — it moves Inheritance Tax for every qualifying household.
