---
id: W-0196
title: Seven retirement-age defaults and four copies of the priority chain — 68 in three services, 67 in four, and two different orderings
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T07:30:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0036, F-0001, F-0018]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: `cycle2-projection` while closing R6 in `F-0018`. Raised, not built.

### Expected

"At what age does this person retire, when they have not said?" has one answer, in one
place, and every module that asks gets it.

### Actual

**Seven private `DEFAULT_RETIREMENT_AGE` constants**, holding two different numbers:

| Value | Where |
|---|---|
| **68** | `AssumptionsService:26`, `GoalsProjectionService:33` |
| **67** | `PensionProjector:25`, `RetirementProjectionService:22`, `RetirementIncomeService:32`, `RequiredCapitalCalculator:27` |

plus `DBPension::DEFAULT_NORMAL_RETIREMENT_AGE = 67`, whose docblock already says it is
deliberately the same 67 as `PensionProjector`'s "so that a pension cannot count as
income from one age while being projected forward from another" (W-0036).

`IHTCalculationService` was an eighth, on 68. `F-0018` removed it by making
`PensionProjector::DEFAULT_RETIREMENT_AGE` public and reading it. The other six stand.

**And four independent copies of the priority chain**, which do not agree on order:

- `IHTCalculationService` (now `HouseholdCashFlowProjector::retirementAgeFor()`) —
  retirement profile, then user record, then Defined Contribution pension.
- `RetirementProjectionService::getRetirementAgeWithSource()` — **user record first**,
  then retirement profile, then pension.
- `RequiredCapitalCalculator:192` — user record first.
- `GoalsProjectionService:564` — user record first, and separately `max()`es against
  its own 68.

A household that has set a target retirement age on the retirement profile and a
different one on the user record gets different answers from different modules, and
nothing reveals it.

### Impact

Retirement age moves the point at which every projection switches from salary to
pension. A one-year disagreement is a whole year of income counted in the wrong phase;
a 67-against-68 disagreement means the estate and the goals module model different
retirements for the same person.

### Repro

`grep -rn "DEFAULT_RETIREMENT_AGE" app/` and `grep -rn "target_retirement_age" app/Services/`.

### Acceptance

1. One resolution of "when does this person retire", read by every module.
2. One default value, in one place, with the W-0036 alignment preserved.
3. The chain ordering is a single deliberate decision, not four accidental ones.
4. No module's answer changes except where it was demonstrably wrong.

## Working notes

- 2026-08-23 build-lead (`fix-cycle4-retirement`): **An eighth default — and then
  eleven more this item does not count. The inventory is backend-only.**

  Raised as D-17/D-18 by `peak-earners-c4` in cycle 4 and attached here on the
  team-lead's instruction rather than filed as a rival item.

  **The eighth, now fixed:** `PensionDetailInline.vue:483-485` read

  ```js
  userRetirementAge() { return this.user?.target_retirement_age || 67; }
  ```

  under a label reading "Retirement Age:" inside the pension's own details — so it
  rendered the **user's household target** where a reader takes it as **this
  pension's**, and fell back to a **hardcoded 67** when the store carried none.
  `dc_pensions.retirement_age` is captured by the pension's own form
  (`DCPensionForm.vue:291-314`), validated 55–75, and was never read. It now reads
  the pension's own value and shows an em dash when there is none — which is what
  `/m`'s `RetirementPensionDetail.vue` has always done for the same field. **Web was
  the outlier, not the pair.**

  **The eleven this item does not list.** `grep -rn "target_retirement_age" resources/js`
  returns eleven further hardcoded fallbacks, split 67/68 exactly as the backend
  constants are:

  | Value | Where |
  |---|---|
  | **67** | `Retirement/IncomeProjectionChart.vue:40` · `Retirement/AccumulationChart.vue:33` · `NetWorth/Property/PropertyDetailInline.vue:538` · `Onboarding/steps/AssetsStep.vue:66` |
  | **68** | `Retirement/StrategiesTab.vue:276` · `Retirement/RetirementIncomeTab.vue:482` · `Retirement/CapitalAdequacyTab.vue:313` · `NetWorth/InvestmentProjections.vue:720` · `views/Investment/AccountPerformancePanel.vue:361` |

  And **two hardcoded 65s** for defined benefit pensions in the same file this batch
  touched — `PensionDetailInline.vue:225` and `:229`
  (`pension.normal_retirement_age || 65`), a **third** value the item does not
  mention. `DBPension::DEFAULT_NORMAL_RETIREMENT_AGE` is 67, so the component and the
  model disagree by two years.

  **Only the one in scope was fixed.** The other eleven, and the two 65s, are left
  for this item — consolidating them is exactly the "one answer in one place" work
  W-0196 exists to do, and picking them off individually is the disease.

  **A trap for whoever takes this item.** On the live database David's
  `users.target_retirement_age` and his SIPP's `dc_pensions.retirement_age` are
  **both 60**, so the correct source and the wrong source produce the same number and
  no test built on real persona data can tell them apart (`tests/CLAUDE.md` §4,
  Collision). The cover written here uses three mutually distinct values — pension
  62, user 58, and the old literal 67 —
  `tests/frontend/components/NetWorth/PensionDetailInline.test.js`.

  Evidence and the mutation table: `workforce/branches/fixes/F-0032-cycle4-pension-holdings-entry-and-display.md`.
