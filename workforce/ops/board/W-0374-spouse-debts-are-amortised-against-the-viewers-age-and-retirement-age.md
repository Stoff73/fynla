---
id: W-0374
title: A spouse's undated debts are amortised against the signed-in user's age and retirement age, not their own
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: queued
severity: low
surfaces: [web, m, ios]
created: 2026-08-23T01:05:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [F-0026, W-0333, tax-compliance-review]
prior_art_outcome: none
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

From the tax-compliance review of W-0333.

`IHTCalculationService::projectMemberLiabilities()` is passed the **signed-in user's**
`$currentAge` and `$retirementAge` (`:410-411`) for **both** members. Where a spouse's
debt has no maturity date, `projectSingleLiability` falls back to *"assume cleared at
retirement age"* = `$retirementAge - $currentAge` — **computed in the wrong person's age
frame.** Sarah is two years younger than David, so her undated debts are assumed cleared
on David's timetable.

Pre-existing in substance. **W-0336 consolidated four loops into one shared method, and
the shared method now takes one member's ages for both** — so the defect is unchanged
but is now expressed in a single place, which is where it should be fixed.

Immaterial on this persona (every mortgage amortises to £0 well inside the 36-year
horizon) and it becomes visible the moment a horizon shortens.

Same family as W-0188, which fixed the equivalent age-frame error for the projection
**horizon** and did not reach the liability **fallback**.

## Acceptance

1. Each member's debts are amortised in that member's own age frame.
2. The household horizon (`$yearsToProject`) stays shared — W-0188 settled that and it
   must not regress.
