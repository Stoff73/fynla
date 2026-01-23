# Plan: IHT Table Concertina/Accordion Sections

## Overview

Add collapsible (concertina) groupings to the IHT Calculation breakdown tables in `IHTPlanning.vue` so that multi-item asset/liability types collapse under a summary heading, reducing table clutter. Sections start **collapsed by default**.

## File to Modify

```
resources/js/components/Estate/IHTPlanning.vue
```

## Implementation

### 1. Add Accordion State (data properties ~line 1397)

```javascript
// Accordion state for first table (second death married)
expandedAssets: {},      // keyed by 'user-property', 'spouse-investment', etc.
expandedLiabilities: {}, // keyed by 'user-mortgages', 'spouse-other', etc.
expandedAllowances: false,
// Same keys used for second table (shared state)
```

### 2. Add Toggle Method (methods section)

```javascript
toggleAssetGroup(key) {
  this.expandedAssets = { ...this.expandedAssets, [key]: !this.expandedAssets[key] };
},
toggleLiabilityGroup(key) {
  this.expandedLiabilities = { ...this.expandedLiabilities, [key]: !this.expandedLiabilities[key] };
},
toggleAllowances() {
  this.expandedAllowances = !this.expandedAllowances;
},
```

### 3. Add Computed Helpers for Group Totals

Helper to sum asset group values (used for the collapsed heading display):
```javascript
assetGroupTotal(assets) {
  return (assets || []).reduce((sum, a) => sum + (a.value || 0), 0);
},
assetGroupProjectedTotal(assets) {
  return (assets || []).reduce((sum, a) => sum + (a.projected_value || 0), 0);
},
```

### 4. Template Pattern for Each Asset Type

For each of the 5 asset types (property, investment, cash, business, chattel) in both user and spouse sections across both tables:

**Condition:** Only apply concertina when the group has > 1 item.

**When count > 1:**
- Show a **group heading row** with:
  - Chevron icon (rotates on expand)
  - Type label + count: e.g. "Property (3)"
  - Sum of current values in the "Now" column
  - Sum of projected values in the "Projected" column
  - Click handler to toggle expansion
- Detail rows wrapped in `v-if="expandedAssets['user-property']"`

**When count === 1:**
- Show the single item directly (no heading, no toggle) - same as current behaviour

**When count === 0:**
- Show nothing (already the case with v-for)

### 5. Liability Groups

Same pattern for:
- User mortgages (group when > 1 mortgage)
- User other liabilities (group when > 1)
- Spouse mortgages
- Spouse other liabilities

### 6. Assets/Liabilities Subtotals

The "User's Assets" and "User's Liabilities" section header rows (the coloured border-left headers) become clickable toggles that collapse ALL asset/liability detail for that person:
- Add keys: `expandedAssets['user-all']`, `expandedAssets['spouse-all']`, `expandedLiabilities['user-all']`, `expandedLiabilities['spouse-all']`
- These default to `true` (section visible) since individual groups already start collapsed
- Chevron on the section header row

### 7. Allowances Section

When the user has a linked spouse (NRB from spouse, RNRB from spouse), the allowances section (NRB + RNRB rows) gets a collapsible heading:
- Show "Allowances (4)" as a clickable heading with chevron
- Individual NRB/RNRB rows hidden until expanded
- Show the total allowances deduction in the collapsed heading row
- Only apply concertina when there are spouse-linked allowances (> 2 allowance rows)

### 8. Chevron Icon

Reuse the existing SVG chevron pattern already in the component (lines 708-710):
```html
<svg class="w-3 h-3 transition-transform" :class="{ 'rotate-90': expanded }">
  <path d="M8.25 4.5l7.5 7.5-7.5 7.5" />
</svg>
```

## Scope

Both tables in the component:
- **First table** (lines 315-663): Second death scenario for married users
- **Second table** (lines 691-1060): Standard breakdown table

## Verification

1. Load Estate Planning → IHT Calculation with a persona that has multiple properties/investments (peak_earners)
2. Verify asset type groups with > 1 item show as collapsed headings with type name, count, and total
3. Click a group heading → verify it expands to show individual items
4. Verify single-item groups show directly without a heading
5. Click "User's Assets" section header → verify it collapses all asset detail
6. Verify allowances section collapses when spouse allowances exist
7. Toggle -5/+5 year columns → verify concertina rows correctly show/hide the extra columns
8. Verify totals in collapsed headings match the sum of individual items
