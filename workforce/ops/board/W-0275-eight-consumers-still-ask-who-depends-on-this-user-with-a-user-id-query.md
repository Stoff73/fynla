---
id: W-0275
title: Eight consumers still ask "who depends on this user" with a user_id-only query
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T22:10:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0272]
prior_art_outcome: route
constitution_refs: [07-quality-bar]
---

## Intent

**W-0272** built `app/Services/Shared/DependantsReach.php` as the one home for this
question and routed `AutoRiskCalculator` to it. Every other consumer still runs the
query that produced the defect, so the same household still gets two answers depending
on which page it is on.

| Site | What it drives |
|---|---|
| `Protection/ComprehensiveProtectionPlanService.php:243` | `number_of_dependents` in the protection plan — cover needs |
| `AI/MemoryRetrieverService.php:117-119` | `dependants_count` as a **fact given to Fyn**, so Fyn tells the spouse she has no children |
| `Savings/SavingsActionDefinitionService.php:2828`, `:3579` | Junior ISA and child-savings actions |
| `Coordination/PlanSources/ModuleAvailabilityProvider.php:57` | `dependants_known` — a module availability gate |
| `Estate/IntestacyCalculator.php:28` | who inherits under intestacy |
| `AI/AdvicePromptBuilder.php:989` | the family block in the advice prompt |
| `Agents/CoordinatingAgent.php:2191`, `:4556` | Fyn's family record listing |

`Estate/ComprehensiveEstatePlanService.php:262-289` and
`UserProfile/ProfileCompletenessChecker.php:163-171` already reach the spouse by hand
— **two more hand-rolled traversals** that should collapse into the same home rather
than being left as a third and fourth implementation (Rule 20).

## Acceptance

1. Every consumer of "who depends on this user" reads `DependantsReach`.
2. The hand-rolled spouse traversals are deleted, not left in parallel.
3. Note the semantics differ per site — intestacy wants **children**, not
   dependants — so route deliberately rather than mechanically.
