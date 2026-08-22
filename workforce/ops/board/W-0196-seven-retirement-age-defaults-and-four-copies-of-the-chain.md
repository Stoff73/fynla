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
