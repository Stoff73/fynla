---
id: W-0414
title: The goal plan reads a months_remaining key GoalProgressService has never returned, and silently runs on a default of 12
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
status: done
severity: medium
surfaces: [web, m]
created: 2026-08-23T02:30:00Z
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0411, GoalProgressService]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Found while consolidating `GoalProgressService` onto `GoalCalculationService` for W-0411.

`GoalPlanService.php:170` and `:279` read `$progress['months_remaining']`:

```php
$monthsRemaining = $progress['months_remaining'] ?? null;   // :170
$monthsRemaining = $progress['months_remaining'] ?? 12;     // :279
```

`GoalProgressService::calculateProgress()` returns `days_remaining`, `days_elapsed` and
`total_days`. **It has never returned `months_remaining`.** So `:170` is always `null` and
`:279` always falls back to **12 months**, for every goal, regardless of its date.

This is the silent-absence family named in `tests/CLAUDE.md` §4: an array read of a
missing key returns `null` quietly, and `?? 12` then supplies a plausible number that
never varies. **A goal nine years out and a goal three months out are planned on the same
horizon.**

`Goal::months_remaining` exists as a model accessor and is the obvious source.

## Acceptance

1. Establish what each of the two call sites is doing with the figure before supplying
   one — `:279`'s `?? 12` may be load-bearing in a way that changes the output materially.
2. Whatever is returned, assert the plan **moves** when the goal's target date moves. A
   test asserting a specific month count passes identically against the `?? 12` default
   for any goal that happens to be a year out.
3. Grep every other reader of `calculateProgress()` for keys it does not return — this one
   was found by eye, not by a sweep.

---

## Closed 2026-09-01

**Fixed at the producer, not at the two call sites.** `GoalProgressService::calculateProgress()`
returned days and never months, so `GoalPlanService:170` was always `null` and `:279`
always fell back to a plausible, unvarying **12 months** — a goal nine years out and a
goal three months out planned on the same horizon.

- `GoalProgressService.php:72-85` now emits `months_remaining`, derived from the same
  `GoalCalculationService` that backs the `Goal::months_remaining` accessor. **One
  derivation reachable two ways**, not a second implementation (Rule 20). Every consumer
  of the array gets it, not just the two the item named.
- `GoalPlanService.php:170` and `:279` — the `??` fallbacks now read `$goal->months_remaining`
  rather than `null` or `12`, so an absent key can never again become a plausible number.

### Tests

`tests/Unit/Services/Goals/GoalMonthsRemainingTest.php` — 3, built against the failure
mode rather than the symptom:

- **Two goals with deliberately different horizons** (3 months and 9 years) asserted to
  produce *different* numbers. A single-goal test cannot distinguish "computed correctly"
  from "always returns 12".
- The key must be **present**, not merely correct when present — absence was the defect,
  and a value assertion alone passes again the moment a `??` default returns downstream.
- The array value and the model accessor agree for the same goal, so the two access paths
  cannot diverge.

**Mutation-verified:** removing the key turns all three red.

### Fallout from W-0197, found and fixed here

Running this item's regression surfaced **13 failing Goals tests that were nothing to do
with W-0414**. My own W-0197 change made `StatePensionAgeResolver` throw when
`pension.state_pension.age_schedule` is absent — deliberately, so a scalar cannot
silently stand in for a cohort schedule — but `Pest.php`'s global safety-net
`TaxConfiguration` factory had no schedule, so any suite that did not seed the real
configuration exploded on setup rather than on the behaviour it was testing.

`database/factories/TaxConfigurationFactory.php:201-224` now carries the schedule,
mirroring the seeder's bands. **The production throw is unchanged** — weakening it would
have reintroduced the silent-scalar defect W-0197 exists to remove.

W-0197's own regression run covered retirement, estate, marketing and investment and did
not cover Goals, which is how this reached here. Recorded so the gap in that run is
visible rather than implied.

**Regression:** 124 tests across Goals and Plans; 298 across Coordination and Retirement.
