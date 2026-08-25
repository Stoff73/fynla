---
id: W-0198
title: Two columns hold one life expectancy — the override now agrees everywhere, the fallbacks still do not
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
prior_art_found: [F-0004, F-0018]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: `cycle2-projection` while closing R7 in `F-0018`. Raised, not built —
merging two columns is a data-model decision, not a defect fix.

### Expected

"How long does this person expect to live" is one fact with one home.

### Actual

Two columns hold it, and three modules combine them differently:

| Where | Rule |
|---|---|
| `RetirementAgent:178` | `life_expectancy_override ?? retirement_profiles.life_expectancy ?? 85` |
| `DecumulationController:62` | `life_expectancy_override ?? retirement_profiles.life_expectancy ?? 85` |
| `FutureValueCalculator::getLifeExpectancy():39` | `life_expectancy_override`, else the Office for National Statistics actuarial tables |

`F-0018` routed the estate's inheritance tax projection through
`FutureValueCalculator::getLifeExpectancy(User)`, so **the override is now honoured
everywhere**. It previously called `getLifeExpectancyYears(int $age, string $gender)`,
the one method that never receives the user and therefore could not see the override
however it was written.

**The fallbacks still disagree.** A household that has filled in
`retirement_profiles.life_expectancy` but not `users.life_expectancy_override` is
answered by that profile figure in retirement and decumulation, and by the actuarial
tables in the estate. `retirement_profiles.spouse_life_expectancy` is a third field
nothing outside `RetirementAgent` reads.

### Impact

The horizon scales every projected figure: the estate, the tax on it, life-cover
sizing and any decumulation plan. Two modules answering "when do I die" differently is
not a rounding difference.

### Acceptance

1. One column, or one resolver both columns feed, with a stated precedence.
2. Retirement, decumulation and the estate return the same number for the same person
   in every combination of the two columns being set.
3. Whatever the user typed still wins over anything derived.
4. `spouse_life_expectancy` is either wired up or removed — not left unread.
