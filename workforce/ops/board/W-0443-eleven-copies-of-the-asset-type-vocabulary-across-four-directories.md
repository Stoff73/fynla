---
id: W-0443
title: The holding asset-type vocabulary exists as eleven independent copies across four directories, and nothing makes them agree
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
status: queued
severity: medium
surfaces: [web, m]
created: 2026-08-23T04:35:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [W-0441, W-0442, HoldingSubTypes, W-0109, W-0376]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Found by `fix-cycle4-retirement` during W-0441's prior-art sweep. **Raised, not
built** — the team-lead issued this id rather than let the batch widen into it.

`grep -rln "formatAssetType" resources/js` returns **eleven files**, each carrying
its own private map of `asset_type` value to human label:

| Directory | Files |
|---|---|
| `components/Investment/` | `HoldingsTable.vue` · `AccountStrategyCard.vue` · `InvestmentPerformance.vue` · `InlineHoldingsEditor.vue` (as an `ASSET_TYPES` const) |
| `components/NetWorth/` | `PensionDetailInline.vue` · `InvestmentProjections.vue` · `JointAccountHistory.vue` |
| `components/Estate/` | `TrustPlanningStrategy.vue` · `GiftingStrategy.vue` |
| `views/Investment/` | `AccountSummaryPanel.vue` · `AccountHoldingsPanel.vue` · `AccountPerformancePanel.vue` |

`HoldingForm.vue` is a further consumer, holding the same list inline as `<option>`
elements rather than as a method.

**They do not agree.** `HoldingsTable.formatAssetType` renders an unknown type by
title-casing it, so `uk_equity` reads **"Uk Equity"**; `PensionDetailInline` maps it
explicitly to **"UK Equity"** and falls back to an em dash. The same stored value
therefore reads differently depending on which screen the user is looking at, and
one of the two spellings breaks Rule 9 besides.

## Why this is Rule 20 and not tidiness

Rule 20 does not say the copies must agree, it says there must not be copies.
Eleven maps of one vocabulary is the same shape as the ownership-phrasing and
LPA-fee duplications (W-0109, W-0376): every future correction lands in one of
eleven places and the other ten drift further.

**A precedent already exists in the same area.** W-0441 met the identical problem
one level down — `holdings.sub_type` was a private `getSubTypes()` on two request
classes and absent from a third consumer — and resolved it by moving the list to
`app/Constants/HoldingSubTypes.php` with all three reading it, precisely so the fix
would not become a third copy. This item is that pattern applied to the asset-type
list, on the client side.

## Acceptance

1. One asset-type vocabulary, in one module, read by every consumer — including
   `HoldingForm`'s `<option>` list and `InlineHoldingsEditor`'s `ASSET_TYPES`.
2. One label per stored value, spelled per Rule 9. "Uk Equity" is not a label.
3. The server-side vocabulary and the client's mirror are related deliberately and
   the relationship is recorded, in the way `ownership.js` mirrors
   `CalculatesOwnershipShare`. The `in:` rules on the holding requests and
   `DCPensionHoldingsController` are the authority.
4. `/m` is named explicitly. `resources/mobile/` has its own rendering of holdings
   (`CanonicalPortfolio.vue`) and does not import from `resources/js/` — whether it
   shares this vocabulary or mirrors it is part of the decision, not an oversight
   to discover later.

## Working notes

- 2026-08-23 build-lead (`fix-cycle4-retirement`): **No twelfth copy was added by
  W-0441 or W-0442.** Both batches reused the map each file already had, including
  in `PensionDetailInline`'s new holdings columns.

  **Two of the eleven are Estate files**, so whoever takes this should check the
  board for a live Estate holder first — `app/Services/Estate/`,
  `IHTCalculationService.php`, `IHTController.php` and `IHTPlanning.vue` were held
  by a sibling agent on the night this was raised.

  **Sizing note:** the count is of files defining the vocabulary, not of call sites.
  A consolidation touching eleven components across four directories is a batch in
  its own right and should not be folded into a functional fix — which is why it is
  here rather than inside F-0032.
