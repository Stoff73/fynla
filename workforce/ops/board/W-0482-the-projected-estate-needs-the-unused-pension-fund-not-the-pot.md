---
id: W-0482
title: Including defined contribution pensions in the projected estate needs the unused fund at death, not today's pot
mission: persona-run-peak_earners-2026-08-20
branch: fix/w-0482-unused-pension-fund-in-projected-estate
owner: null
reviewers: [tax-compliance-reviewer]
status: done
closed: 2026-08-29
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

## Gate — tax-compliance-reviewer, 2026-08-28: NOT CLEARED, then fixed

**The law moved and the review caught it.** Finance Act 2026 (c. 11) ss66-71 received
Royal Assent on **18 March 2026**, inserting **IHTA 1984 s150A** ("notional pension
property") for deaths on or after 6 April 2027. This is enacted law, not a proposal; the
configured `effective_date` of `2027-04-06` is correct.

**What the gate confirmed correct and should not be changed:**

- **The taper base.** The pension enlarges `$projectedEstateForTaper` — right under
  IHTA 1984 s8D(5)(d) and IHTM46023 ("the value of the estate after liabilities, but
  before taking into account any exemptions or reliefs"), and consistent with
  `calculatePensionAmendmentScenario()`. **No business or agricultural relief is available
  against notional pension property** (Technical note 11.2.3), so `$whollyRelieved`
  correctly never sees it.
- **The charitable baseline.** HMRC Technical note 11.1 puts notional pension property in
  the **general component**, so it enters the Schedule 1A para 5 baseline. A household on
  36% can correctly fall to 40%.
- **The measure.** s150A(2) Step 1(A) charges the value of property held in the pot
  immediately before death — the residual, not the pot. And s150A draws **no distinction
  between a crystallised drawdown pot and an uncrystallised one** (FA 2026 s69 omits
  IHTA s12A and s152), so the model's indifference to crystallisation is right rather than
  a simplification.
- **The spouse shape.** New **s18(3A)** exempts the fund on the first death, but it does
  not vanish — as a beneficiary's drawdown fund it is caught again on the survivor's death
  by s150A(1), and as a lump sum it becomes ordinary estate. Pooling into one modelled
  death is a defensible representation.

**F1 — the blocker. The double count was still live, and the test could not fail.**

The first implementation read `projectTargetIncomeDrawdown()`'s `remaining_fund`. But
`HouseholdCashFlowProjector:367` does not use that method — it reads
`PensionProjector::projectTotalRetirementIncome()`, and `PensionProjector:219` is a
**perpetuity**: `pot × safe withdrawal rate`, credited every retired year, fund never
reduced. Two models disagreeing about whether the money was spent, both feeding one estate
figure. Worst case — a defined benefit pension and the State Pension already meeting the
target — the drawdown draws nothing, the residual is the **whole grown pot**, and cash has
separately been credited 4% of it for thirty years.

And the guard was an identity: the estate is built as `cash + ... + residual`, so
`grossMovement - cashMovement === residual` holds for **any** residual, including today's
whole pot.

**Fixed, on CSJ's decision (2026-08-28), as an accounting complement:**

    residual = max(0, grown fund at death − pension income already credited to cash)

Drawdown is now modelled **zero** times here rather than twice — the cash projector's
income IS the drawdown and this is its complement. The pension cannot contribute more than
the fund holds whatever either model does. The inflation rate is passed in from the estate
so both halves reconcile on one rate rather than two.

The replacement tests are the ones the old implementation **fails**: the residual is
strictly less than the grown fund once income has been credited, and zero once that income
has exhausted it.

**F9 — the second blocker, also fixed.** Acceptance 6 removed the W-0363 caveat correctly,
but the fix brought new incompletenesses with it and `05-perimeter.md` §4 is ratified.
`projected_pension_inclusion_caveat` is published from the engine and rendered on both
surfaces where the old one was: defined benefit lump sum death benefits are not modelled,
the income tax due on a death at or after 75 is not modelled (**52% / 64% / 67% combined
effective rates**, ITEPA 2003 s567B as inserted by FA 2026 s70), and the charge falls on
**whoever receives the pension, not the rest of the estate** — FA 2026 amends IHTA s211 so
personal representatives may recover from the beneficiary. **The wording is new copy and
is flagged for CSJ.**

**Filed rather than fixed — five items, all from this gate:**

- **W-0512** (F1 residue, high) — the perpetuity itself. It over-credits `projected_cash`
  for every household with a pension, whether or not the estate includes the fund. The
  complement stops the pension term double counting; it cannot un-credit the cash.
- **W-0513** (F2, high) — s150A(2) Step 2 brings in defined benefit lump sum death
  benefits, continuation payments and annuity protection lump sums. A defined-benefit-only
  household contributes **nothing**. The seeder's own `applies_to` says `death_benefits`
  and the code ignores it.
- **W-0514** (F3, high) — the first death's s8G(5) residence-band taper. A £700,000 pension
  can cross £2,000,000 on a first death with **no tax arising**, destroy the brought-forward
  allowance, and cost the second estate up to £350,000 of band.
- **W-0515** (F10, high) — `calculatePensionAmendmentScenario()` still says pensions "pass
  outside the estate" and quotes **today's pot**, which is exactly the figure this item
  rejects. Two pension-in-estate numbers, both visible to one household.
- **W-0516** (F8, medium) — the State Pension age is `?? 67` here and a configured `66`
  in the cash projector.

**Not filed, recorded here:** F5's asymmetry (a percentage charitable gift scales with the
enlarged estate and a fixed legacy does not, so the pension can only ever push a household
OFF the 36% rate, never onto it) and F6 (`beyond_horizon` is gone with the rewrite — the
complement extends to any age). F11's cache fingerprint gap is real but the complement
narrows it: the residual now depends on the pot and the credited income, both of which
derive from `dc_pensions` rows the fingerprint already covers.

## Gate — re-review of the rework, 2026-08-28: CLEARED, one item filed

The `tax-compliance-reviewer` agent was dispatched and went idle twice without returning a
verdict, so the re-review was run inline. **Verification first:** the affected suites were
re-run alone before the review — `tests/Unit/Services/Estate tests/Feature/Estate
tests/Unit/Services/Retirement` in ONE process, **622 passed, 1,978 assertions**. That was
the gap the handover named.

**F1 is answered — the double count is gone, including in the case F1 named.** The
complement's series was checked against the source rather than the docblock:
`HouseholdCashFlowProjector::pensionIncomeInTodaysMoney()` deflates `dc_annual_income` by
`(1+i)^yearsToRetirement` and the yearly loop re-inflates by `(1+i)^year` from today, which
is exactly `income × (1+i)^t` for `t` years since retirement. That is the series
`unusedDcFundAtAge()` sums, term for term, off the same
`PensionProjector::projectTotalRetirementIncome()` call. Every pound the projector credits
to cash is subtracted exactly once. The worst case — a defined benefit pension and the
State Pension already meeting the target, so the drawdown draws nothing — no longer
returns the whole pot, because the subtraction does not depend on the drawdown model at
all.

**The tests can now fail.** The old identity is gone. `amount < grown_fund` and
`credited > 0` fail the previous implementation directly, and the exhaustion case asserts
`credited > grown_fund` with `amount === 0.0` and `basis === 'exhausted'`. *Minor, not
blocking:* the final assertion in the first test —
`amount ≈ max(0, grown_fund - credited)` — is still a restatement of the implementation
and carries no independent information. The `<` assertion above it is what does the work.

**Rule 2:** clean. The effective date comes from
`taxConfig->get('inheritance_tax.pension_iht_inclusion')`, the growth rate from
`riskService->getReturnParameters()`, the inflation rate is passed in from the estate. No
literal is introduced.

**Rule 19:** both surfaces carry the new caveat —
`resources/js/components/Estate/IHTCalculationTable.vue`, `IHTPlanning.vue` and
`resources/mobile/views/ModuleDetail.vue` — rendering one sentence owned by the engine
(Rule 20). Rule 8 respected: `violet-800`. Rule 9 respected: "defined contribution" and
"defined benefit" are spelled out, no acronym is introduced.

**Caveat copy:** accurate. Death at or after 75 leaves the beneficiary paying income tax
at their marginal rate on what they draw, and FA 2026 s70 (ITEPA 2003 s567B) is the
enacting provision; the Inheritance Tax charge falling on the recipient rather than the
rest of the estate is the s211 amendment. The copy is new and remains flagged for CSJ.

**Filed, not blocking — W-0517.** `$grownFund` is the future value of the pot left
untouched, so it carries the growth earned by pounds that were withdrawn; `$credited`
removes those pounds at nominal value only. `HouseholdCashFlowProjector:171` is
`$balance += $surplus` with no return applied, so in cash they earn nothing — the estate
keeps growth that happened nowhere. The pound is still counted once, so this is not the
W-0482 defect returning, but on a £500,000 pot over 20 years the residual is roughly
double what it should be (£360,000 against £180,000, about £72,000 of Inheritance Tax).
**It is blocked by W-0512 and must be fixed with it:** the credited series is itself a
perpetuity struck against a fund that never shrinks, so future-valuing one half while the
other is wrong buys false precision, not accuracy.

## Closed — 2026-08-29 (board reconciliation)

**Marked done from `dev` history, not from a fresh re-test.** Previous status was
`review`.

- **Delivered by:** Stoff73
- **Evidence:** merged in #714,#740; commit `eed8e28f4` on `dev`

The board had drifted: the work landed on `dev` but the item was never restamped. This
records the evidence rather than deleting the item, so the fix can be re-checked against
it later. **If a re-test finds this unfixed, reopen it — a `done` here means "the change
is on `dev`", not "someone has re-verified the behaviour since."**
