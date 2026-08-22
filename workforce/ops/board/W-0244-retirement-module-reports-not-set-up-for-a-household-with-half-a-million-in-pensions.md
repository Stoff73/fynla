---
id: W-0244
title: The retirement module reports "not yet set up" for a household with £500,000 of pensions, because there is no retirement_profiles row
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: queued
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T20:45:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0238, F-0001]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Found while fixing **W-0238** and **worked around at the card level, not fixed** —
the fix belongs inside `RetirementAgent` and its blast radius is every consumer of
retirement analysis, which is more than this cycle's items.

### The defect

`app/Agents/RetirementAgent.php:110-114`:

```php
$hasProfile = $profile !== null;
…
if (! $hasProfile) {
    return $this->response(false, 'No retirement profile found', []);
}
```

`success: false` with an **empty data array**. Not "we cannot project without a
target" — nothing at all: no pot, no schemes, no state pension, no count.

Both peak_earners accounts are in this state. Verified against the live local
database: `retirement_profiles` holds **zero rows for users 16 and 17**, while they
hold two defined contribution pensions worth £500,000, an NHS final salary scheme
paying £35,000 a year, and a State Pension forecast.

The readiness gate is **not** what blocks them — `RetirementDataReadinessService::
assess()` returns `can_proceed: true` for both, with the missing target age and
target income correctly classified as warnings. The profile check below it is
absolute.

### Why the distinction matters

**What a user HAS is a fact about their pension records. What they are AIMING AT is
a fact about their profile.** Conflating them means a household that has entered
every pension it owns is told it has not started, and any consumer asking "does
this user have retirement provision" gets "no".

### The workaround now in place, and its cost

W-0238 made `MobileDashboardAggregator::extractRetirementSummary()` read the pension
records directly when the agent declines to answer, so the dashboard card is right.
**Every other consumer of `RetirementAgent::analyze()` still gets nothing** — the
retirement module page, the plans, and Fyn's retirement context. That asymmetry is
the cost of not fixing this here, and it is why this is filed as high.

### The likely correct shape

Return `success: true` with the pension facts and a null projection, so
"I have no target" and "I have no pensions" stop being the same answer. **Every
consumer that branches on `success === false` for retirement must be enumerated
before that lands** — several render "not yet set up" from it.

## Acceptance

1. A user with pensions and no `retirement_profiles` row gets their pot, schemes and
   guaranteed income from `RetirementAgent::analyze()`.
2. Every `success === false` retirement consumer enumerated and checked.
3. The dashboard's direct pension read in `extractRetirementSummary()` is removed
   once the agent answers, so the figure has one home again.
4. Whether onboarding should be writing a `retirement_profiles` row for these users
   at all — a separate question this item raises but does not answer.
