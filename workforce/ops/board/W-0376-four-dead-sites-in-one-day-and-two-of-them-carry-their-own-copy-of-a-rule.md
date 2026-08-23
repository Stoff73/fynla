---
id: W-0376
title: Four dead sites found in one day, and the dead code carries its own copies of live rules
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: queued
severity: medium
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

Raised on team-lead's instruction: **four instances in one day is a pattern, not a
curiosity**, and **dead code that carries its own copy of a rule is how the next person
copies the wrong one.**

| Site | What is dead | What it carries |
|---|---|---|
| `resources/js/components/Savings/SavingsOverviewCard.vue` | mounted **nowhere** | its own 6/3-month runway thresholds, status colours and captions |
| `IHTCalculationService::projectInvestments()` + `getCurrentInvestmentValue()` | unreachable (**W-0334**) | the `investment_growth_method: custom` dispatch — so a user's setting is **silently ignored** |
| a public life-cover method | zero production callers | (found by another agent this cycle) |
| `savingsService.analyzeSavings` + the `/savings/analyze` endpoint | nothing dispatched it (**W-0335**) | a second answer to the emergency-fund figure |

Two of the four **do not merely sit there** — they hold a copy of a rule that is live
elsewhere. `SavingsOverviewCard.vue` would today render runway thresholds that agree
with `EmergencyFund.vue` **by coincidence**, and nothing would notice if they diverged.

**Reading finds candidates; only running finds facts.** All four were found by
execution or by a reference sweep, none by reading.

## Acceptance

1. A census of unmounted Vue components and uncalled public methods across the app —
   count first, characterise second, as W-0280 §10 did.
2. Each classified **delete / wire up / keep with a reason**, never swept mechanically:
   W-0334's pair is a live product question, not tidy-up.
3. **Any dead site holding a copy of a live rule is prioritised over one that does
   not** — that is the half that bites.
