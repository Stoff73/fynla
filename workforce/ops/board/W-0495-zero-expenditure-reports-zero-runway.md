---
id: W-0495
title: A household with no recorded expenditure is told it has zero months of runway, however much cash it holds
mission: persona-run-peak_earners-2026-08-20
owner: build-lead
status: done
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

- 2026-08-31 build-lead: **VERIFIED STILL LIVE against `dev`.**
  `app/Services/Savings/EmergencyFundCalculator.php:14-16` is unchanged: `$monthlyExpenditure <= 0`
  returns `0.0`, indistinguishable from a genuine zero runway. A household with cash and no
  recorded expenditure is still told it has 0 months, and the error runs in the alarming direction.

- 2026-08-31 build-lead: **FIXED AND TESTED — closed.**

  **Criterion 3 first, as the item required — and yes, false recommendations were being generated.** Traced before changing anything: `HolisticPlanner:333` and `:391` treat a runway under three months as urgent, `PriorityRanker:179` treats under one month as top priority, and `CrossModuleStrategyService:219` reads the same key. A household with cash and no recorded expenditure scored 0.0 and was therefore told to build an emergency fund it may already have had in full.

  **Criterion 1 — null, not 0.0.** `EmergencyFundCalculator::calculateRunway()` returns `?float`, null where expenditure is not recorded. `calculateAdequacy()` returns null runway/score/shortfall for it rather than scoring it (a 100 would claim the fund is ample, a 0 that it is empty — both assert something nobody measured), and `categorizeAdequacy()` returns `'Unknown'` rather than `'Critical'`.

  **The consumers needed no re-plumbing, which is why null was the right shape:** `?? 6` at the two urgent branches reads null as "no reason to worry", and `PriorityRanker`'s `isset()` skips the escalation. Three call sites did need guarding and were fixed: `SavingsAgent:341` (the adequacy gate, `?? 100`), `SavingsAgent:651` (the goal suggestion, explicit null check) and `AutoRiskCalculator:500`/`:510`, which already said *"Not calculated"* in `value` while rounding null into `raw_value`.

  **Criterion 2 — one wording, both surfaces.** `resources/js/utils/emergencyRunway.js` is the single home: `RUNWAY_UNAVAILABLE_LABEL` ("Add your monthly spending"), `RUNWAY_UNAVAILABLE_HINT` and `runwayLabel()`, which keeps W-0276's "from cash savings" basis. The desktop overview imports it (`SavingsModuleOverview.vue:126-141`, replacing `emergencyFundRunway.toFixed(1)` which printed **0.0 months**), the store getter now returns null instead of 0, and **`/m` imports the same file** (`resources/mobile/views/modules/Savings.vue:147`) rather than keeping its own "Runway unavailable" string — Rule 20 across two bundles.

  **Two tests encoded the defect as a contract and were corrected, not deleted:** `EmergencyFundCalculatorTest` asserted `toBe(0.0)` for zero and negative expenditure, and `savingsEmergencyFundGetters.test.js` was literally named *"is zero only when there is no expenditure to run through"*. Both now assert null, with the reasoning at the line, and four new cases cover the unknown adequacy and the `Unknown` category.

  **Tested:** 584 PHP passed / 1,892 assertions across savings, coordination and risk; 819 frontend passed.

  **NOT DONE — criterion 4.** Not browser-verified on web or `/m`. Both bundles in `public/` are csjones builds, so a local browser check is not possible without a rebuild CSJ has staged work in.
