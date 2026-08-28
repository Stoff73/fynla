---
id: W-0480
title: Four Estate and Tax services still read ['married'] alone, so a civil partnership gets the wrong answer on adjacent screens
mission: persona-run-peak_earners-2026-08-20
branch: fix/w-0480-civil-partnership-parity
owner: null
reviewers: [tax-compliance-reviewer]
status: review
claimed_by: null
severity: high
surfaces: [web, m]
created: 2026-08-24T15:40:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-24
prior_art_found: [W-0474]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: tax-compliance-reviewer gate report on W-0474, finding F5, 2026-08-24
---

## Intent

W-0474 fixed `IHTCalculationService`, which read `['married']` alone while nine
siblings read `['married', 'civil_partnership']`. **The reviewer checked the siblings
and found the count was wrong in the other direction: four of them read `['married']`
alone too, and carry the same defect.**

- `app/Services/Estate/LifeCoverCalculator.php:56` and `:452`
- `app/Services/Estate/ComprehensiveEstatePlanService.php:71`
- `app/Services/Tax/TaxOptimisationService.php:384`
- `app/Services/Tax/TaxActionDefinitionService.php:170`

Two are Estate services a civil partnership reaches on the same screens as the figure
W-0474 corrected, so a household can now see a correct Inheritance Tax number beside
life-cover and planning output still computed as though they were single.

**W-0474's own commit message claimed a constant gave the list "one home". It did
not** — the constant was `private`, which cannot be read by anything else. That is
fixed as part of this item's prior art: `App\Support\HouseholdPooling` is public and
holds the list and the predicate, so these four have something to read.

## Acceptance

1. Each of the four either reads `HouseholdPooling::POOLING_MARITAL_STATUSES` /
   `hasSpousalStatus()`, or states at the line why its own list is deliberately
   narrower.
2. Before/after for a civil partnership on each figure that moves — these are
   different services and the direction is not assumed to be the same in each.
3. A guard that fails when a new consumer branches on `marital_status` with its own
   literal list. Grep-based is acceptable here; the failure mode is a hand-written
   copy, and only a sweep sees a copy.
4. `tax-compliance-reviewer` on the change — `TaxOptimisationService` moves tax.

## Working notes

- 2026-08-24 — Filed from the W-0474 gate report (F5), which the reviewer marked
  informational and explicitly out of scope for that commit. Recorded here rather than
  left in a handoff.
- 2026-08-24 — Check `LifeCoverCalculator` first: it has two sites, and life cover is
  the figure most likely to be read as a protection recommendation rather than a tax
  one, so a wrong answer there reaches a different kind of decision.

## Resolution — 2026-08-28

**Acceptance 1 — done.** All four read `HouseholdPooling::hasSpousalStatus()`. None
needed a narrower list; each was asking the pooling question and answering it with half
the statuses.

- `LifeCoverCalculator.php:59` (`$isJointPolicy`) and `:455` (`$isMarried`)
- `ComprehensiveEstatePlanService.php:72` (the spouse lookup)
- `TaxOptimisationService.php:385` (`buildSpousalStrategy`)
- `TaxActionDefinitionService.php:171` (`evaluateSpousalTransfer`)

**Acceptance 2 — done**, `tests/Unit/Services/CivilPartnershipHouseholdParityTest.php`.
Each household is built twice, `married` and `civil_partnership`, and required to give
the same answer, then `single` is required to still give the other one. **Verified by
mutation:** with the four service edits stashed, all five tests fail and produce exactly
the `single` answer; restored, all five pass. The figures that move, per service:

- Life cover — `is_joint_policy` flips, and the annual premium falls: a joint life second
  death policy carries a 25% discount (`LifeCoverCalculator.php:328`) and is priced on the
  average of two ages, so the test asserts the civil partnership's premium equals the
  marriage's and is strictly less than the single person's.
- Life cover, existing policies — the `single_life_married` warning now raised.
- Estate plan — `user_profile.spouse` is a block rather than `null`.
- `TaxOptimisationService` — `spousal_optimisation` strategy present, same
  `estimated_annual_saving` as the marriage.
- `TaxActionDefinitionService` — `spousal_transfer_beneficial` fires.

**Acceptance 3 — done**, `tests/Architecture/MaritalStatusLiteralsArchitectureTest.php`.
Grep-based, as the item allowed. It has both directions: a NEW literal list reddens it,
and so does a baselined site whose line has changed — including one that has been fixed,
so the entry gets pruned instead of rotting. **Both were mutation-tested** (a probe class
under `app/Support/`, and fixing `LifeStageService` and running without pruning). Its
blind spot is stated in the docblock: it reads one line at a time, so an `in_array` split
across lines slips past.

**The sweep found fourteen more sites — filed as W-0508**, not fixed here. The Estate API
(`WillController`, `LifePolicyController`, `GiftingController` x2, `TrustController` x2,
`EstateController`), three services (`LifeStageService`, `CoverageGapAnalyzer`,
`ProtectionDataReadinessService`) and four agents. **This item's premise — that four
siblings carried the defect — was itself an undercount**, which is the argument for the
sweep rather than another review.

**Acceptance 4 — `tax-compliance-reviewer` dispatched on the change.** See the working
note below for its verdict.

### Verification

- `tests/Unit/Services/Tax` + `tests/Unit/Services/Estate` + the new parity test —
  **587 passed, 1,812 assertions.**
- `tests/Feature/Tax` + `LifeCoverReachSpouseLinkStatesTest` +
  `DeletedSpouseVisibilityTest` + `RecommendationsAggregatorServiceTest` +
  `EstateAgentGoalsTest` + the new sweep guard — **59 passed, 193 assertions.**
- Pint clean.
- **NOT verified in a browser.** These are service-layer branches with no template change;
  the user-visible movement is asserted at the figures above rather than on screen.
