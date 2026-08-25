---
id: W-0334
title: The estate projection silently ignores a user's chosen investment growth method, because the code that honours it is unreachable
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T23:25:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [AssumptionsService, AssumptionsController, LifeCoverCalculator]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

`IHTCalculationService::projectInvestments()` dispatches on
`$assumptions['investment_growth_method']` and honours `custom` with
`custom_investment_rate`. **Nothing calls it.** `calculateProjectedValues():425`
calls `projectInvestmentsMonteCarlo()` directly, and `getCurrentInvestmentValue()`
is reachable only from the dead method.

`investment_growth_method` is a real user-settable assumption —
`AssumptionsController:65-66` validates it, `AssumptionsService:68-75` stores and
serves it, and `LifeCoverCalculator:426` reads it. So a user who sets it to `custom`
gets their rate applied to their life-cover sizing and **silently ignored** by their
estate projection.

Found while fixing W-0331; the ownership defect was fixed in place in both dead
methods rather than deleting code whose intent is a live product question.

## Acceptance

1. Either the dispatch is reachable and `custom` is honoured by the estate
   projection, or the option is removed from the projection's contract and the
   dead pair deleted — **not left as a setting that does nothing**.
2. Whichever is chosen, `LifeCoverCalculator` and the estate projection agree about
   what the setting means.
