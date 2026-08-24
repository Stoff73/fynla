---
id: W-0475
title: The projected gross estate is assembled from five categories, so any `assets` row that is not one of them vanishes from the projection and from the taper base
mission: persona-run-peak_earners-2026-08-20
branch: estate-copy-and-m-handoff
owner: main-inference
reviewers: [tax-compliance-reviewer]
status: gated
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

- 2026-08-24 — **The scope in Intent is too narrow. Verified against the code, it is
  four of the five creatable types, not one.** The projected column is assembled from
  SOURCE TABLES while the current column is assembled from the aggregated collection:
  - `projectProperties()` → `PropertyStore` (the `properties` table)
  - `projectInvestmentsMonteCarlo()` → `calculateInvestmentTotal()` (`investment_accounts`)
  - `$projectedCash` → `HouseholdCashFlowProjector` (savings)
  - `$projectedChattels` / `$projectedBusiness` → filters over
    `gatherUserAssets()` itself

  Only the last two read the collection, so **only `business` survives**. An `assets`
  row of type `property`, `pension`, `investment` **or** `other` is in the current
  gross estate and absent from the projection. `CoordinatingAgent:4055` lets Fyn
  create **all five**: `Rule::in(['property','pension','investment','business','other'])`.
  So "I own a plot of land worth £200,000", captured as an asset of type `property`,
  is counted today and vanishes at death.
- 2026-08-24 — **Consequence for the fix: excluding by `asset_type` is the wrong
  axis.** A row of type `property` is "covered" by name and not by data source. The
  residual has to key on **provenance** — a raw `Estate\Asset` row that is not type
  `business` (business is already caught by the filter, which sees both provenances) —
  so a new enum member falls into the residual automatically instead of vanishing,
  which is what acceptance 3 is really asking for.
- 2026-08-24 — **The `assets` table is EMPTY on the development database** (0 rows, all
  types). So acceptance 2's before/after cannot be measured on live data and must be a
  Pest test with a seeded row per enum value. Worth knowing: this is also why the
  defect has survived — nothing on dev exercises the table.
- 2026-08-24 — Not started. Held while `tax-compliance-reviewer` has
  `IHTCalculationService.php` open for the W-0474 gate, to avoid editing a file under
  review.

## Resolution — 2026-08-24

Fixed by a residual keyed on **provenance**, not `asset_type`: an `Estate\Asset` row is
one the projection's sources (`properties`, `investment_accounts`, savings) never see.
`business` is excluded because that term already counts both provenances. **A new
member of the enum falls into the residual automatically rather than vanishing**, which
is what acceptance 3 actually needs. Carried at current value like chattels and
business — nothing models growth for an arbitrary asset, and unmodelled growth
understates by far less than a missing asset.

Guard: `IHTProjectedEstateCoversEveryAssetTypeTest` — every enum value proved present
in BOTH columns, plus a case that reads the enum out of `SHOW COLUMNS` and fails if a
type is added to the column and not to this file. **Mutation-checked: dropping the
residual reds 4 of 6.** Estate unit 351 green. Pint clean.

`W-0481` was widened on the way through: `AssetFactory` randomises **two** fields into
values their columns reject — `asset_type` (four of eight) and `ownership_type`
(`tenants_in_common`, which is property-only).
