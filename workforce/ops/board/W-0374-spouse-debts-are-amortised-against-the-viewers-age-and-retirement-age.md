---
id: W-0374
title: A spouse's undated debts are amortised against the signed-in user's age and retirement age, not their own
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: review
severity: low
surfaces: [web, m, ios]
created: 2026-08-23T01:05:00Z
claimed: 2026-08-26
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

---

## Fixed 2026-08-26 — commit `073d904e8`, merged in PR #722

`projectSingleLiability()` falls back to "cleared at retirement age" for an undated
debt, computed as `$retirementAge - $currentAge` — both the **viewer's**, for both
members. Where the viewer's retirement age is already behind them that is
`max(0, 60 - 70) = 0`, and the spouse's debt vanished from the projection entirely.

**TWO sites, where this item names one.** Said plainly because this file has a
history of "I found every site" turning out wrong:

1. `projectLiabilities()` passed the viewer's pair for the spouse — the one described
   above.
2. `projectMainResidenceNetValue()` builds ONE closure and applies it to both members,
   capturing the viewer's ages on the way in. It feeds the projected residence-band
   cap rather than the estate total, so it is invisible in `projected_liabilities`
   and needed its own test.

Both now read `ageFrameFor($member)` — one home, two callers (Rule 20).

**The trap in the fix, and acceptance 2.** `projectMemberLiabilities()` DERIVED the
horizon from the same `$currentAge` it used for the fallback, so handing it the
spouse's age would have moved the horizon too and regressed **W-0188**.
`$yearsToProject` is now computed once by the caller and passed in, so the shared
household horizon and the per-member fallback are separate parameters that cannot be
conflated again. That has its own test.

`projectMainResidenceNetValue()`'s now-unused age parameters were removed rather than
left — passing them looks like it means something, which is how this bug worked.

The residence-path test is **differential** and was verified to FAIL with the defect
reinstated. A differential test that passes for the wrong reason is worth nothing,
and this one initially passed for the wrong reason twice.

*Closed late: the code merged in PR #722 while this item stayed `queued`. Recorded
2026-08-26 on noticing.*
