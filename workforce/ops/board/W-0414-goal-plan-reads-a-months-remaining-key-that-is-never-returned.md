---
id: W-0414
title: The goal plan reads a months_remaining key GoalProgressService has never returned, and silently runs on a default of 12
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
status: queued
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
