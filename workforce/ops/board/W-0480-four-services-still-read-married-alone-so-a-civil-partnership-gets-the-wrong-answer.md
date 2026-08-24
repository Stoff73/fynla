---
id: W-0480
title: Four Estate and Tax services still read ['married'] alone, so a civil partnership gets the wrong answer on adjacent screens
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
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
