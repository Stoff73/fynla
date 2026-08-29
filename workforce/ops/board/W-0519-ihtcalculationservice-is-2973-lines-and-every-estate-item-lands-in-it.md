---
id: W-0519
title: IHTCalculationService is 2,973 lines and every estate item lands in it
mission: null
branch: fix/w-0519-split-iht-calculation-service
owner: null
reviewers: [tax-compliance-reviewer]
status: in_progress
claimed_by: null
severity: medium
surfaces: [web, m, ios]
created: 2026-08-29T08:40:00Z
claimed: 2026-08-29T08:40:00Z
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-29
prior_art_found: [W-0482, W-0465, W-0474, W-0331, W-0333, W-0336, W-0374]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: CSJ made it priority 1 on 2026-08-29 after W-0482 added ~174 more lines to it
---

## Intent

`app/Services/Estate/IHTCalculationService.php` was **2,973 lines** — six times the 500-line
split threshold — and it is the file every estate work item lands in. W-0482 alone added
~174 lines to it. The cost is compounding: each item after this one is cheaper once the
split is done and more expensive if it is not.

**This is a behaviour-preserving extraction, not a rewrite.** The gate question for a
refactor of this file is narrow: *did any published figure change?* The answer must be no.

## The seam

The file answers two different questions, and only one of them is about tax:

- **What is this household worth at death?** — growth, amortisation, drawdown, Monte Carlo.
- **What tax is due on an estate?** — nil rate band, residence band, taper, charitable rate.

The first is now `app/Services/Estate/EstateProjectionService.php`. It composes five public
terms the calculator asks for and never re-derives:

| term | what it projects |
|---|---|
| `projectedUnusedPensionFund()` | the unused defined contribution fund at the modelled death date (W-0482) |
| `projectMainResidenceNetValue()` | the s8E(2) residence cap base, grown and amortised (W-0368) |
| `projectInvestmentsMonteCarlo()` | p20 simulation, per member, at each member's own share (W-0331) |
| `projectProperties()` | growth on the undivided-share-discounted value (W-0368, W-0333) |
| `projectLiabilities()` | linear amortisation in each member's own age frame (W-0336, W-0374) |

Nothing in the new service knows a nil rate band exists, and nothing in the calculator
re-derives a projected figure.

**Pooling is not duplicated.** The moved branches call `HouseholdPooling::poolsSpouse()`
directly — the same one home `IHTCalculationService` reads through its own thin delegate
(W-0474, Rule 20). The predicate has one implementation, not two.

## Acceptance

1. Every method moves **verbatim**, W-numbered docblocks included. No arithmetic edited.
2. `tests/Unit/Services/Estate tests/Feature/Estate tests/Unit/Services/Retirement` holds
   the same count and the same figures as before the move.
3. `tax-compliance-reviewer` clears the diff on the narrow question: no published figure moved.
4. The calculator's constructor drops only the dependencies the move genuinely orphaned.

## Not in scope

`IHTCalculationService` is still ~2,391 lines. The cache/persistence block
(`getCachedCalculation`, `isCurrentResultShape`, `charitableBequestFingerprint`,
`generateHashes`, `saveCalculation`, `invalidateCache`) is the obvious next seam, ~230
lines and equally cohesive. Filed as follow-up rather than bundled, so this diff stays one
answerable question for the gate.

## Adjacent, reported not fixed

Found during the move, left alone deliberately (working style: report, do not silently fix):

- **`projectInvestments()` and `getCurrentInvestmentValue()` are dead** — zero callers
  anywhere in `app/` or `tests/`. `projectInvestmentsMonteCarlo()` is called directly. They
  moved with the block; deleting them is a separate decision.
- **`LifeEventService` is injected and never used** — `$this->lifeEventService` has zero
  references. It was already dead before this change, so the move did not orphan it and
  did not remove it.
- **`PensionStore` is imported but resolved from the container** at
  `IHTCalculationService:2300` (`app(PensionStore::class)`) rather than injected, in a
  class whose other ten collaborators are constructor-injected.
