---
id: W-0482
title: Including defined contribution pensions in the projected estate needs the unused fund at death, not today's pot
mission: persona-run-peak_earners-2026-08-20
branch: fix/w-0482-unused-pension-fund-in-projected-estate
owner: null
reviewers: [tax-compliance-reviewer]
status: review
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

## Resolution — 2026-08-28

**Acceptance 1 — done.** `IHTCalculationService::calculateProjectedValues()` adds
`$projectedUnusedPension['amount']` to `$projectedGrossAssets`, which carries into
`$projectedEstateForTaper` as well — the pension enlarges the taper base exactly as it
enlarges the estate (IHTA 1984 s8D(5)(d)), the same treatment
`calculatePensionAmendmentScenario()` already gives it. Gated on the modelled death date
against `inheritance_tax.pension_iht_inclusion.effective_date`; a household modelled to
die before it adds nothing.

**Acceptance 2 — done, and it required a choice worth recording.**
`RetirementProjectionService::unusedDcFundAtAge()` is the one place asked. It reads
**`projectTargetIncomeDrawdown()`, not `projectIncomeDrawdown()`** — the item cited both
loops (`:318` and `:436`) and they are not interchangeable:

- `projectIncomeDrawdown()` withdraws a sustainable PERCENTAGE of whatever remains, so by
  construction the fund is never exhausted. Every household would leave a residual in
  their estate however modest their pot, and acceptance 3's "fully drawn adds nothing"
  could not be satisfied at all.
- `projectTargetIncomeDrawdown()` draws what the household actually needs and stops when
  there is nothing left. That is the same question `HouseholdCashFlowProjector` asks of
  expenditure, and the two figures sit in one estate, so they must agree about whether
  the money was spent.

**Acceptance 3 — done**, `tests/Unit/Services/Estate/ProjectedEstateCountsThePensionOnceTest.php`,
7 tests. The double count is measured rather than asserted away: a larger pension raises
BOTH the projected cash (it becomes income) and the unused fund (what income did not
spend), so the test takes the cash movement out and requires what remains to equal the
residual exactly. Adding the pot at today's value fails that. Both ends of the range are
covered — a spent fund adds under £1 of a £20,000 pot, an undrawn one adds the whole
grown fund at the same 20th percentile the drawdown starts from.

**Acceptance 4 — done.** Three regimes are named in the returned `basis` rather than
collapsed into a number: `pre_retirement_growth`, `drawdown_residual`, `beyond_horizon`,
plus `no_pension`, `today`, `before_effective_date` and `not_configured`. A death modelled
beyond `retirement.projection_end_age` takes the fund at the last modelled age and says
so in `modelled_to_age`. **That can only overstate the residual, never understate it** —
further drawdown would reduce it — and overstating an Inheritance Tax liability is the
safer direction: nobody is told they owe less than they do. Stated in the docblock, not
left to `?? 0`.

**Acceptance 5 — `tax-compliance-reviewer` still to run.**

**Acceptance 6 — done.** The W-0363 caveat is gone from the engine, `IHTController`,
`IHTCalculationTable.vue` (markup and prop), `IHTPlanning.vue` (both bindings and the
mapping) and `resources/mobile/views/ModuleDetail.vue` — Rule 19, both surfaces in the
same change. `ProjectedPensionExclusionIsStatedTest` is replaced rather than deleted, and
the new suite asserts the key is no longer published at all.

### Found on the way — filed, not fixed here

**W-0510** — the drawdown's fund never reports as depleted. `$dcDrawdown = min($dcNeeded,
$remainingFund)` means that once need exceeds fund the balance becomes
`$remainingFund * $growthRate` — it approaches zero and never arrives, so
`fund_depletion_age` is always `null`, `fund_depleted` always `false`, and `years_funded`
always the full horizon. A household is told its money lasts to 100 when the engine's own
figures say it is spent at 64. **It does not affect this change** — the estate reads
`remaining_fund` directly and £2.31 is correctly nothing — but it is why the test here
asserts "under £1" rather than exactly zero.

### For CSJ — a design decision, not taken here

The projected estate now includes the unused fund, and **no surface shows it as its own
row.** `projected_unused_pension` and `projected_unused_pension_basis` are published so
one can, but adding a row to the Inheritance Tax table is a design decision and Rule 16
says not to invent one. Worth deciding: a household's projected estate grows with this
change and the sentence that used to explain the omission is gone.

### Verification

- `tests/Unit/Services/Estate` + `tests/Unit/Services/Retirement` + `tests/Feature/Estate`
  — **620 passed, 1,971 assertions.**
- Pint clean.
- **NOT verified in a browser.** The frontend change is a removal on both surfaces; the
  figure change is asserted at the engine.
