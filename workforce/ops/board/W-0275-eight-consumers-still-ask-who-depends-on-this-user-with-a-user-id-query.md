---
id: W-0275
title: Eight consumers still ask "who depends on this user" with a user_id-only query
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: done
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

---

## Closed 2026-09-01 — eight consumers routed, two traversals deleted, one left on purpose

**Acceptance 1 — every consumer reads `DependantsReach`:**

| Site | Routed to | Why that one |
|---|---|---|
| `ComprehensiveProtectionPlanService` | `countFor()` | dependants — this figure sizes life cover |
| `MemoryRetrieverService` | `countFor()` | dependants — a **fact given to Fyn** |
| `SavingsActionDefinitionService` ×2 | `householdFamilyOf(['child'])` + `is_dependent` | Junior ISA needs dependent children with a date of birth |
| `ModuleAvailabilityProvider` | `householdFamilyOf()` | "known", not "dependent" — any family row answers it |
| `IntestacyCalculator` | `householdFamilyOf()` | **children, not dependants** — see below |
| `AdvicePromptBuilder` | `householdFamilyOf()` | the whole family block |
| `CoordinatingAgent:4604` | `householdFamilyOf(['child'])` | Fyn's family listing |

**Acceptance 3 — routed deliberately, and it changed two decisions.**

`householdFamilyOf()` was added for the sites whose question is not "who depends on
this user". **Intestacy is the clear case:** the Administration of Estates Act 1925
distributes to children, and a grown, self-supporting child inherits exactly as a
dependent one does — routing it through the dependants filter would have disinherited
the children of every household that recorded them honestly. Asserted directly.

**`CoordinatingAgent:2226` is deliberately NOT routed.** It lists family records for
**editing**, and the write path is scoped to `user_id`, so reaching the spouse's rows
there would offer edits that cannot succeed — the same reasoning `LifeCoverReach`
already applies to a joint-life policy. Recorded rather than left to look like an
oversight.

**Acceptance 2 — both hand-rolled traversals deleted, and each was worse than a
duplicate.**

`ComprehensiveEstatePlanService:269-311` had **three faults the one home does not**: it
never matched on `linked_user_id`, so the same child with a linked account and a
differently-typed name counted twice; it compared a Carbon against a string, so a
duplicate with a date of birth was rarely detected at all; and it gated on
`$dataSharingEnabled`, which governs disclosing a partner's *financial* data and is not
the question of whose children they are.

`ProfileCompletenessChecker:161-176` reached the spouse on raw `liveSpouseId()` rather
than reciprocally — the W-0350 axis — and applied **two different relationship rules** to
one question: everything-but-spouse on the user's own side, children and step-children
on the spouse's.

### Tests

`tests/Unit/Services/Shared/DependantsReachConsumersTest.php` — 5: both parents reach
the same two children, the family reach includes a grown child the dependants filter
excludes, the same child typed on both accounts counts once, and a guard that **reads
the eight consumer files** and fails if any re-introduces `FamilyMember::where('user_id'`
on a read path. No behavioural test on a single service can see this class of defect —
each would answer for half a household, plausibly.

**Regression:** 1,133 tests across shared, estate, protection, user-profile, savings and
AI suites.
