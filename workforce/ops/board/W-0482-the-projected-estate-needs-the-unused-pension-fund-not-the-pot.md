---
id: W-0482
title: Including defined contribution pensions in the projected estate needs the unused fund at death, not today's pot
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
claimed_by: null
severity: high
surfaces: [web, m]
created: 2026-08-24T18:20:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-24
prior_art_found: [W-0363, W-0364]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: split out of W-0363 while implementing its acceptance 3, 2026-08-24
---

## Intent

W-0363 asked for defined contribution pensions to appear in the projected estate from
the configured effective date. **Adding them is not a one-line change, and the obvious
version is wrong in a way that would not show up as an error.**

`HouseholdCashFlowProjector` already turns the pension into income and carries it in
`projected_cash`. Adding the pot at today's value on top would **double count** the same
money — once as the income it becomes, once as the fund it came from.

What belongs in the estate is the **unused fund at death**: the pot grown to
retirement, less drawdown to the modelled death date. That figure is already computed —
`RetirementProjectionService::projectIncomeDrawdown()` produces `remaining_fund` per
year (`:318`, `:436`) — but it is not reachable from `IHTCalculationService`, and its
horizon is not guaranteed to reach the estate projection's death age.

## Acceptance

1. The projected estate includes the **unused** fund at the modelled death date, from
   the configured effective date, with no double count against `projected_cash`.
2. One mechanism: the estate projection reads the retirement engine's residual rather
   than modelling drawdown a second time (Rule 20).
3. A guard proving the pension money is counted exactly ONCE — a household whose pot is
   fully drawn adds nothing, and one who draws nothing adds the grown fund.
4. Behaviour when the drawdown horizon ends before the estate's death age is stated in
   code, not left to `?? 0`.
5. `tax-compliance-reviewer` on the change — it moves tax for every household with a
   pension.
6. The interim caveat from W-0363 is REMOVED in the same change; a caveat that outlives
   its cause becomes noise.

## Working notes

- 2026-08-24 — Split out while implementing W-0363 acceptance 3. W-0363 published the
  exclusion as a stated assumption, which is honest but does not correct the figure.
  **The understatement is live** — roughly 40% of whatever the unused fund turns out to
  be, for every household holding a defined contribution pension.
