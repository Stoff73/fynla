---
id: W-0376
title: Four dead sites found in one day, and the dead code carries its own copies of live rules
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: done
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

- 2026-08-31 build-lead: **RESOLVED AND TESTED — closed. Three of the four sites had already been dealt with; the fourth is deleted.**

  - `IHTCalculationService::projectInvestments()` / `getCurrentInvestmentValue()` — **no longer dead.** Both live in `EstateProjectionService` now (`:235`, `:243`) and are called, so the `investment_growth_method: custom` dispatch they carried is reachable and a user's setting is no longer silently ignored. That was **W-0334**.
  - `savingsService.analyzeSavings` and `POST /savings/analyze` — **resolved today under W-0335, and NOT as dead code.** The endpoint takes a SCENARIO, which the index payload cannot answer; it has no dispatcher because the analysis now arrives with `/api/savings`. Documented at the line so the next sweep does not re-file it.
  - The public life-cover method — not reproduced by this pass.
  - **`SavingsOverviewCard.vue` — still mounted nowhere, and DELETED**, with its test.

  **The card is the one that justified the item's framing**, and it had got worse rather than staying inert. It carried a hardcoded *"Target: 6 months"*, its own `runwayColour`/`runwayBarColour` thresholds, and a `.toFixed(1)` on the runway. **W-0495, closed today, made that runway `null` where no expenditure is recorded** — so this file now held a rule that not only duplicated the live one but would have thrown on the new contract. It agreed with `EmergencyFund.vue` by coincidence, and nothing would have noticed when it stopped.

  Its test went with it: a test for a component nothing mounts asserts a behaviour no user can reach, and keeping it would have made the file look covered.

  **The item's own conclusion holds and is worth carrying forward: reading finds candidates, only running finds facts.** All four originals were found by execution or a reference sweep, none by reading.

  **Tested:** 811 frontend tests pass.
