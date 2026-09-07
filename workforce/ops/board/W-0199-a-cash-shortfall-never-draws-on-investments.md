---
id: W-0199
title: A projected cash shortfall never draws on investments, so a household runs out of money while holding an untouched portfolio
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: chief-of-staff
status: done
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

---

## Closed 2026-09-01 — the model stated, and taken from the projection that already exists

**Acceptance 1 — the stated model.** When cash reaches zero the household sells
investments to cover that year's shortfall, and stops when the portfolio is exhausted.
What it still cannot meet stays a shortfall — a planning output, where a negative asset
would be a broken model. Written at
`EstateProjectionService::projectInvestmentsAfterCashShortfall()`.

**Acceptance 2 — one investment model, not two.** The horizon value still comes from
the user's configured growth method (Monte Carlo p20, or their own rate — W-0520). The
annual path is **that same projection's implied rate**, `(Vn / V0) ^ (1/n) - 1`, so the
drawdown is unwound from the one model's own answer. That is the distinction the item
was right to insist on: the deleted `projectCashAndInvestmentsIntegrated()` carried its
own growth assumptions and would have been a second model; deriving the rate from the
first one is not.

`projectInvestments()` is now a thin call into the same method with no deficits, so the
two cannot drift — asserted directly.

**Acceptance 3 — growth on the reduced balance.** The deficit is taken in the year it
falls, so money sold in year 3 stops compounding in year 3. The test that proves it:
the same £10,000 sold early costs the household **more than £10,000** at the horizon,
and more than the same £10,000 sold late. A lump subtraction at the horizon can produce
neither result.

### How the deficits reach it

`HouseholdCashFlowProjector` already computed each year's unmet amount and threw it
into a running total — which is exactly why the estate could only ever have subtracted
a lump, and so subtracted nothing. It now publishes `annual_deficits`, keyed by year
offset. Three lines, no new mechanism.

`IHTCalculationService` runs the cash flow **before** the investment projection now, so
the second knows what the first could not fund, and publishes two figures where it
published one:

- `projected_cash_shortfall` is now what remains **after** the portfolio was drawn on.
  Before, it was the raw cash shortfall while the investments that should have paid it
  grew untouched — the household modelled as both unable to fund its spending and still
  holding the money to fund it, with the estate taxed on the second half.
- `projected_investments_drawn_for_shortfall` is published so the reduction can be shown
  as a row rather than appearing as unexplained shrinkage.

### Tests

`tests/Unit/Services/Estate/CashShortfallDrawsOnInvestmentsTest.php` — 6 tests: no
shortfall leaves the projection untouched, a household with nothing invested reports the
whole shortfall unmet, early selling costs more than late selling and more than the sum
itself, the portfolio empties before anything is reported unmet, the plain projection
equals the drawdown path at zero deficit, and the configured growth method is honoured.

A custom rate is used rather than Monte Carlo throughout, and the reason is written at
the helper: the simulation's p20 moves between runs, and a test that cannot state its
expected number cannot tell a correct drawdown from a wrong one.

**Regression:** 643 tests across the estate services and features.

**Impact:** still nil on screen for `peak_earners`, whose shortfall is £0. The next
household with a genuine shortfall no longer has an overstated estate, or projected
Inheritance Tax charged on money it should have spent.
