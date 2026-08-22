---
id: W-0199
title: A projected cash shortfall never draws on investments, so a household runs out of money while holding an untouched portfolio
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: chief-of-staff
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T07:30:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0135, W-0136, W-0137, F-0018]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: `cycle2-projection` while closing W-0137. **A modelling decision for CSJ,
deliberately not taken.**

### What W-0137 fixed, and what it left

Projected cash now draws down to £0 and stops, and the expenditure it could not meet
is published as `projected_cash_shortfall`. That is W-0137's acceptance 2, and it
closes the impossible negative balances.

**But a household with a shortfall is now modelled as running out of cash while its
investments grow untouched to the end of the projection.** That is internally
contradictory in the other direction: nobody dies owing years of unfunded spending
while holding a portfolio they never sold.

### The mechanism that already existed

`IHTCalculationService::projectCashAndInvestmentsIntegrated()` did exactly the right
thing — floor cash at zero each year, distribute the deficit across investment
accounts, then apply growth to the reduced balance. **It had no caller** and was
deleted with the other duplicates in `F-0018`; it is recoverable from git history.

### Why it was not simply wired up

The live investment projection is `projectInvestmentsMonteCarlo()`, a p20 percentile
at the horizon rather than a year-by-year path, so drawing from it annually means
either deriving an implied annual rate (a second investment model — the Rule 20
hazard the deleted code represented) or restructuring the Monte Carlo call. Either
moves `projected_investments`, and therefore W-0135's and W-0136's figures.

### Impact

**Nil on screen for the `peak_earners` household today** — its shortfall is £0 once
retirement expenditure stopped contradicting recorded expenditure (`F-0018` §7). The
next household with a genuine shortfall sees an overstated estate: every pound of
unmet spending is a pound of investments that should have been sold and was not, and
the projected inheritance tax is charged on it.

### Acceptance

1. A stated model for what a household does when its cash runs out.
2. If investments are drawn on, one projection produces both figures — not a second
   investment model beside the Monte Carlo one.
3. The drawdown's effect on growth is modelled, not applied as a lump at the horizon.
4. `projected_investments` moves consistently on `/estate/inheritance-tax` and
   `/plans/estate`, and W-0135's two-screen agreement holds.
