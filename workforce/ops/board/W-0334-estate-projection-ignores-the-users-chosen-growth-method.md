---
id: W-0334
title: The estate projection silently ignores a user's chosen investment growth method, because the code that honours it is unreachable
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: done
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

---

## Closed 2026-09-01 — dispatch reachable (W-0520), and the agreement made structural

**Acceptance 1 — the dispatch is reachable and `custom` is honoured.** Done by W-0520,
verified in the code rather than taken on trust: the dead pair is gone from
`IHTCalculationService`, which now calls `EstateProjectionService::projectInvestments()`
(`:307` reads `investment_growth_method` and branches). The setting is no longer one
that does nothing.

**Acceptance 2 — and this is what was left.** The two consumers did "agree about what
the setting means", but only because
`EstateProjectionService::getFallbackGrowthRate()` and
`LifeCoverCalculator::getInvestmentReturnRate()` were **byte-identical copies** of the
rule, hardcoded 4.7% fallback included.

**Agreement by transcription is the arrangement that produced this defect.** Two copies
agree until someone edits one, and no behavioural test of either service can see the
disagreement — each would simply apply a different rate, plausibly. So the rule is now
in one place, `AssumptionsService::investmentGrowthRateFor()`, with the 4.7% as
`DEFAULT_INVESTMENT_GROWTH_RATE` beside the method default it belongs with.

Both consumers call it. Neither interprets the setting for itself.

### Tests

`tests/Unit/Services/Settings/InvestmentGrowthMethodTest.php` — 5: a custom rate
honoured, the shared default when nothing is chosen, a custom rate ignored when the
method is not `custom`, the dispatch still present, and a guard that **reads both
consumer files** and fails if either re-introduces the rate rule.

The guard deliberately allows the *dispatch* on `investment_growth_method` — choosing
Monte Carlo or custom is W-0520's fix and belongs in the projection — while forbidding a
second copy of the rate rule. The distinction is written at the line, because a broader
regex flags the correct code.

**Regression:** 446 tests across the estate services and settings.
