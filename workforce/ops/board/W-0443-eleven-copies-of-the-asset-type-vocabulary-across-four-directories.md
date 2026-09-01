---
id: W-0443
title: The holding asset-type vocabulary exists as eleven independent copies across four directories, and nothing makes them agree
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
status: done
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

---

## Closed 2026-09-01 — fourteen copies, not eleven

**Acceptance 1 — one vocabulary, in one module.** `resources/js/constants/assetTypes.js`
holds the ten values and their labels. Every consumer reads it: the eleven the item
listed, plus **three it did not**:

- `HoldingForm.vue:128-137` — ten hardcoded `<option>` elements, the list a user picks
  FROM, so a value missing there could never be entered however the column was defined.
- `HoldingsTable.vue:26-33` — a filter dropdown **missing three of the ten values**
  (`equity`, `fund`, `etf`), so a user could never filter to those types however many
  holdings they held. Found by the guard, not by reading.
- `/m`'s `InvestmentAccountDetail.vue:148` — `capitalise(h.asset_type)`, which is not a
  label at all.

**Acceptance 2 — one label per value, spelled per Rule 9.** "UK Equity" and "US Equity",
never "Uk"/"Us". `etf` is **"Exchange-Traded Fund"** rather than a bare acronym — a
deliberate visible change, because acceptance 2 puts Rule 9 spelling in scope and a cold
acronym is what Rule 9 forbids.

**An unknown value returns an em dash, not a title-cased guess.** Title-casing is
exactly how "Uk Equity" reached a screen: it presents a stored string the vocabulary does
not contain as though it were a real label.

**Acceptance 3 — the server relationship, stated.** The authority is the
`holdings.asset_type` column enum, mirrored in
`StoreHoldingRequest::getAssetTypes():63-75` and `DCPensionHoldingsController:94`. The
module's docblock records that this is the client mirror — add a value to the column and
the rules, then here, never here alone — and **a test reads the PHP file and asserts the
two lists match**, so drift fails rather than waits to be noticed.

**Acceptance 4 — `/m` shares, it does not mirror.** `resources/mobile/` has its own
bundle, but already imports `ownership.js` and `holdingUnits.js` from `resources/js`, so
a third copy would have been a choice rather than a constraint. Stated in the module's
docblock, not left to be discovered.

### Tests

`resources/js/constants/__tests__/assetTypes.spec.js` — 18: the server mirror, Rule 9
spelling, the em-dash refusal, option ordering, and a per-file guard over **all fourteen**
consumers asserting each reads the shared module and holds no local map or inline option
list.

**Mutation-verified:** reintroducing a private map in `JointAccountHistory.vue` turns it
red. No behavioural test of a single screen could see this class of defect — each
renders a plausible label from its own map, which is how eleven of them disagreed for
months.

### A test that encoded the disagreement, corrected not deleted

`tests/frontend/components/Investment/HoldingsTable.test.js:151` asserted
`formatAssetType(null) === 'N/A'`. That was this component's private answer while the
other copies gave others. Corrected to the em dash the rest of the application renders,
with the reasoning at the line, and extended to assert `uk_equity` and the
unknown-value case.

**User-visible change worth naming:** the holdings table now shows "—" where it showed
"N/A" for a holding with no asset type.

**Regression:** 1,275 frontend tests, all passing.
