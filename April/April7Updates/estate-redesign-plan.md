# Estate Planning Dashboard Redesign Plan

**Date**: 7 April 2026
**Goal**: Restructure the estate planning page from a tab-based layout to a 3-column card grid with expandable IHT calculation table.

---

## Current Layout

- Tab-based: IHT Planning | Gifting Strategy | Life Policy | Trusts
- `EstateDashboard.vue` (199 lines) — manages tabs, renders one component at a time
- `IHTPlanning.vue` (1,725 lines) — contains ALL summary cards, IHT calculation tables, mitigation strategies, projections, charts, asset breakdowns
- Cards already exist inside `IHTPlanning.vue` at a `lg:grid-cols-5` grid (lines 95-301): IHT Summary, Will, Gifting, Life Policy, Charitable Bequest, Power of Attorney, Trust (conditional)
- IHT Calculation Table rendered directly below cards (lines 304-400)

## Desired Layout

**Row 1** (3 columns):
1. Inheritance Tax Summary (click to expand IHT calculation table below)
2. Will
3. Power of Attorney

**Row 2** (3 columns):
4. Charity Bequest
5. Life Policy
6. Gifting

- Will Builder banner stays as-is (above the grid)
- IHT Calculation Table moves behind the IHT Summary card — shown/hidden on click
- Remove the tab navigation entirely — all cards visible at once
- Clicking Will → navigates to will builder
- Clicking Power of Attorney → navigates to LPA page
- Clicking Life Policy → navigates to protection module
- Clicking Gifting → navigates to gifting strategy page

## Changes Required

### 1. EstateDashboard.vue

**Remove**: Tab navigation (`activeTab`, tab switching, conditional rendering of IHTPlanning/GiftingStrategy/LifePolicyStrategy/TrustPlanning)

**Keep**: Will Builder banner, loading/error states, data fetching

**New**: Render the card grid directly instead of tab components. The cards already exist in IHTPlanning.vue — they need to be extracted or the grid rendered at the dashboard level.

### 2. IHTPlanning.vue — Card Grid Reorder

**Current grid**: `lg:grid-cols-5` with order: IHT Summary, Will, Gifting, Life Policy, Charitable Bequest, Power of Attorney, Trust

**New grid**: `lg:grid-cols-3` with order:
- Row 1: IHT Summary, Will, Power of Attorney
- Row 2: Charitable Bequest, Life Policy, Gifting
- Trust card: remains conditional (estate > £2m), appears as 7th card if shown

### 3. IHT Calculation Table — Expandable

**Current**: Always visible below the cards

**New**: Hidden by default. Toggled by clicking the IHT Summary card. Add a chevron/expand icon to the IHT Summary card to indicate it's clickable. When expanded, the calculation table renders below the full card grid (spanning all 3 columns).

### 4. Tab Components

The existing tab components (`GiftingStrategy.vue`, `LifePolicyStrategy.vue`, `TrustPlanning.vue`) remain as standalone pages accessible via their sidebar nav links. The estate dashboard just shows the summary cards.

## File Changes

| File | Change |
|------|--------|
| `resources/js/views/Estate/EstateDashboard.vue` | Remove tab logic, render IHTPlanning directly (no tab switching) |
| `resources/js/components/Estate/IHTPlanning.vue` | Reorder cards to 3-col grid, add expandable IHT table toggle |

## Card Click Behaviour

| Card | Action |
|------|--------|
| IHT Summary | Toggle IHT Calculation Table (expand/collapse below grid) |
| Will | Navigate to `/estate/will-builder` |
| Power of Attorney | Navigate to `/estate/power-of-attorney` |
| Charitable Bequest | Toggle charitable bequest radio (stays in card, no navigation) |
| Life Policy | Navigate to `/protection` |
| Gifting | Navigate to gifting strategy (could be `/estate?tab=gifting` or dedicated route) |

## Implementation Steps

1. **EstateDashboard.vue**: Remove tab state and conditional rendering. Always render `<IHTPlanning />` directly.
2. **IHTPlanning.vue**: Change grid from `lg:grid-cols-5` to `lg:grid-cols-3`.
3. **IHTPlanning.vue**: Reorder cards — move Power of Attorney to position 3 (after Will), move Charitable Bequest to position 4, Life Policy to position 5, Gifting to position 6.
4. **IHTPlanning.vue**: Add `showIHTTable` data flag (default `false`). Toggle on IHT Summary card click. Add chevron icon to indicate expandability.
5. **IHTPlanning.vue**: Wrap both IHT Calculation Table sections (married and standard) in `v-if="showIHTTable"`.
6. Build and test.

## Notes

- The married user summary cards (3-col grid at lines 60-91) need the same treatment — reorder and make IHT table expandable.
- The non-married summary cards (5-col grid at lines 94-301) are the main target.
- Both grids should use the same 3-column layout and card order.
