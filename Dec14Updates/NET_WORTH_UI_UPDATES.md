# Net Worth Module UI Updates

**Date:** December 15, 2025
**Branch:** upload

## Summary

Updated the Net Worth module UI with card-based Overview page and conditional sidebar navigation.

## Changes Made

### 1. NetWorthDashboard.vue - Sidebar with Icons

**File:** `resources/js/views/NetWorth/NetWorthDashboard.vue`

- Replaced horizontal tabs with vertical sidebar navigation
- Sidebar hidden on Overview page, visible on all other sections
- SVG icons rendered inline with dynamic path binding (`:d="item.iconPath"`)
- Conditional offset class for list views to align sidebar with first card

**Sidebar Menu Items:**
- Overview
- Wealth Summary
- Retirement
- Property
- Investments
- Cash
- Business Interests
- Chattels
- Joint History

**CSS Classes:**
- `.sidebar-offset` - 56px margin-top for list views (aligns with first card after header)
- No offset for Wealth Summary (sidebar at top)

### 2. Vuex Store - Detail View State

**File:** `resources/js/store/modules/netWorth.js`

Added state tracking for detail views:
```javascript
state: {
  isDetailView: false,
}

mutations: {
  SET_DETAIL_VIEW(state, isDetailView) {
    state.isDetailView = isDetailView;
  },
}

actions: {
  setDetailView({ commit }, isDetailView) {
    commit('SET_DETAIL_VIEW', isDetailView);
  },
}
```

### 3. List Components - setDetailView Integration

Updated list components to call `setDetailView` when entering/exiting detail views:

**Files Updated:**
- `resources/js/components/NetWorth/PensionList.vue`
- `resources/js/components/NetWorth/PropertyList.vue`
- `resources/js/components/NetWorth/InvestmentList.vue`
- `resources/js/views/Savings/SavingsDashboard.vue`

**Pattern:**
```javascript
methods: {
  ...mapActions('netWorth', ['setDetailView']),

  selectItem(item) {
    this.selectedItem = item;
    this.setDetailView(true);
  },

  clearSelection() {
    this.selectedItem = null;
    this.setDetailView(false);
  },
},

mounted() {
  this.setDetailView(false);
}
```

## Technical Notes

### SVG Icon Implementation

Icons are stored as path data strings in the `sidebarItems` array:

```javascript
sidebarItems: [
  {
    path: 'overview',
    label: 'Overview',
    iconPath: 'M3.75 6A2.25 2.25 0 016 3.75h2.25...'
  },
  {
    path: 'wealth-summary',
    label: 'Wealth Summary',
    iconPath: 'M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z',
    iconPath2: 'M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z'  // For dual-path icons
  },
]
```

Rendered in template:
```vue
<svg class="sidebar-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
  <path stroke-linecap="round" stroke-linejoin="round" :d="item.iconPath" />
  <path v-if="item.iconPath2" stroke-linecap="round" stroke-linejoin="round" :d="item.iconPath2" />
</svg>
```

**Important:** Do NOT use Vue component objects with `template` strings for icons - this requires Vue's runtime compiler which is not included in production builds.

### Sidebar Alignment

The sidebar offset is controlled by:
1. `sidebarNeedsOffset` computed property - returns true for list views
2. `.sidebar-offset` CSS class - applies 56px margin-top

List views have a header (title + button) that takes ~56px of vertical space before the first card.

## Files Modified

| File | Changes |
|------|---------|
| `resources/js/views/NetWorth/NetWorthDashboard.vue` | Sidebar with icons, conditional offset |
| `resources/js/store/modules/netWorth.js` | Added isDetailView state |
| `resources/js/components/NetWorth/PensionList.vue` | setDetailView calls |
| `resources/js/components/NetWorth/PropertyList.vue` | setDetailView calls |
| `resources/js/components/NetWorth/InvestmentList.vue` | setDetailView calls |
| `resources/js/views/Savings/SavingsDashboard.vue` | setDetailView calls |
