---
id: W-0495
title: A household with no recorded expenditure is told it has zero months of runway, however much cash it holds
mission: persona-run-peak_earners-2026-08-20
owner: build-lead
status: queued
severity: high
surfaces: [web, m, ios]
source: found while quantifying W-0276, 2026-08-25
prior_art_checked: 2026-08-25
prior_art_found: [W-0276]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

`EmergencyFundCalculator::calculateRunway()` guards its divisor and returns zero:

    if ($monthlyExpenditure <= 0) {
        return 0.0;
    }

    return round($totalSavings / $monthlyExpenditure, 2);

The guard is right — dividing by zero is not an option. **What is wrong is that the
caller cannot tell "no runway" from "cannot be calculated", because both are `0.0`.**

The `retired_couple` persona holds **£74,250** in cash and has no recorded monthly
expenditure. Every surface therefore tells that household it has **0 months** of
runway. That is not a cautious figure or a rounding artefact — it is a false
statement about someone's finances, and the direction is the alarming one: a
household with three quarters of its emergency target already banked is told it has
nothing.

Measured 2026-08-25 through the same store and ownership rule the aggregator uses:

| Persona | Cash | Monthly expenditure | Runway shown |
|---|---|---|---|
| retired_couple | £74,250 | £0 | **0** |

## The web component already knows better

`resources/js/components/Savings/EmergencyFund.vue:212` has a `hasExpenditure`
guard and says *"Please add your monthly expenditure to calculate emergency fund
runway."* rather than printing a number.

`/m` and iOS do not have that guard on the same footing — `Savings.vue:225` returns
"Runway unavailable" only when `runwayMonths` is **null**, and the API sends `0.0`,
not null. `SavingsView.swift:389` guards `monthly > 0` and returns "Runway
unavailable", so native may already be correct; that needs checking rather than
assuming.

So the fix is a Rule 20 problem as much as a calculation one: three surfaces, three
different answers to the same missing input.

## Acceptance

1. "Cannot be calculated" is distinguishable from "zero months" in the payload —
   `runway_months: null` rather than `0.0` when expenditure is not recorded, or an
   explicit flag beside it.
2. Every surface renders the prompt to add expenditure, not a number. One wording,
   all surfaces.
3. Anything that branches on runway is checked for the same conflation — at least
   `HolisticPlanner`, `PriorityRanker` and `CrossModuleStrategyService` read
   `emergency_fund_months` and treat low values as urgent, so a household with no
   expenditure recorded may be generating false "build your emergency fund"
   recommendations today. **Establish whether it is before fixing anything.**
4. Verified in a browser on web and `/m` with a persona that has cash and no
   expenditure.
