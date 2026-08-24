---
id: W-0475
title: The projected gross estate is assembled from five categories, so any `assets` row that is not one of them vanishes from the projection and from the taper base
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
claimed_by: null
severity: high
surfaces: [web, m]
created: 2026-08-24T07:42:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
handoff_to: null
prior_art_checked: 2026-08-24
prior_art_found: [W-0465, W-0474]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: tax-compliance-reviewer round four, finding F7, 2026-08-24
---

## Intent

`$totalGrossAssets` is every non-exempt row `gatherUserAssets()` returns.
`$projectedGrossAssets` (`IHTCalculationService.php:717`) is assembled from **five
projected category totals**: cash, investments, properties, chattels, business.

`assets.asset_type` is `enum('property','pension','investment','business','other')`.
**A row of type `other` is in the current gross estate and absent from the projected
one entirely** — and therefore absent from the projected taper base too. Those rows
are user-creatable (`EstateController:288`, `CoordinatingAgent:4065`).

**Direction: UNDERSTATES the projected estate, and through the taper base
UNDERSTATES projected tax** — a smaller base means less taper, more residence band
surviving, less tax.

## Acceptance

1. The projection covers every asset type the current column counts, or states in
   code which types it deliberately excludes and why.
2. Before/after for a household holding an `other` asset, showing it present in both
   columns.
3. A guard that fails if a new `asset_type` is added to the enum and not to the
   projection — the enum and the projection are two lists that must agree.
4. `tax-compliance-reviewer` on the change.

## Working notes

- 2026-08-24 — Pre-existing, surfaced while confirming that W-0465's
  `$projectedEstateForTaper` mirrors the current column's `$estateForTaper`. The
  reviewer's phrasing is worth keeping: *"the mirror is exact, the surface it
  reflects is not."* The taper base is right about the arithmetic and wrong about
  the estate.
