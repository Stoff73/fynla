# Mobile Responsive Detail Views Fix Plan

**Date**: January 5, 2026
**Target**: 375px minimum (iPhone SE)
**Scope**: All detail views (Investment, Protection, Pension)

## Problem Summary

Detail views across Investment, Protection, and Retirement modules break on mobile devices due to:
1. Header layouts not stacking on small screens
2. Grid layouts missing intermediate breakpoints
3. Definition lists overflowing
4. Typography too large for mobile
5. Buttons not adapting to narrow widths

## Files to Modify (18 files)

### Investment/Account Details (6 files)
| File | Issues |
|------|--------|
| `resources/js/views/Investment/AccountDetailView.vue` | Header layout |
| `resources/js/components/NetWorth/InvestmentDetailInline.vue` | Tab navigation, key metrics |
| `resources/js/views/Investment/AccountSummaryPanel.vue` | Details grid |
| `resources/js/views/Investment/AccountHoldingsPanel.vue` | Card details grid |
| `resources/js/components/NetWorth/PropertyDetailInline.vue` | Header, definition lists |
| `resources/js/components/NetWorth/BusinessInterestDetailInline.vue` | Key metrics grid |

### Protection Details (5 files)
| File | Issues |
|------|--------|
| `resources/js/components/Protection/PolicyDetail.vue` | Header, tabs, metrics grid |
| `resources/js/components/Protection/PolicyFormModal.vue` | Modal sizing, button layout |
| `resources/js/components/Protection/PolicyCard.vue` | Card grid, broken CSS selector |
| `resources/js/components/Protection/CurrentSituation.vue` | Coverage summary grid |
| `resources/js/views/Protection/ProtectionDashboard.vue` | Tab navigation |

### Retirement/Pension Details (7 files)
| File | Issues |
|------|--------|
| `resources/js/views/Retirement/PensionDetail.vue` | Header, metrics grids, definition lists |
| `resources/js/views/Retirement/RetirementReadiness.vue` | Pension cards grid, detail rows |
| `resources/js/components/Retirement/IncomeSourceSlider.vue` | Source header layout |
| `resources/js/components/Retirement/StrategyCard.vue` | Card header, income comparison |
| `resources/js/components/Retirement/AnnualAllowanceTracker.vue` | Progress section, carry forward |
| `resources/js/components/Retirement/RetirementIncomeTab.vue` | Tab layout |
| `resources/js/components/Retirement/TaxBreakdownCard.vue` | Card metrics |

## Common Fix Patterns

### 1. Header Layouts
```html
<!-- Before -->
<div class="flex justify-between items-start">

<!-- After -->
<div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
```

### 2. Grid Layouts
```html
<!-- Before -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4">

<!-- After -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
```

### 3. Definition Lists
```html
<!-- Before -->
<div class="flex justify-between">

<!-- After -->
<div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
```

### 4. Typography
```html
<!-- Before -->
<h1 class="text-3xl font-bold">

<!-- After -->
<h1 class="text-xl sm:text-2xl lg:text-3xl font-bold">
```

### 5. Action Buttons
```html
<!-- Before -->
<div class="flex space-x-2">
  <button class="px-6 py-3">Edit</button>

<!-- After -->
<div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
  <button class="w-full sm:w-auto px-4 sm:px-6 py-2 sm:py-3">Edit</button>
```

## Implementation Phases

| Phase | Focus | Files |
|-------|-------|-------|
| 1 | Header Layouts | 5 files |
| 2 | Grid Breakpoints | 6 files |
| 3 | Definition Lists | 4 files |
| 4 | Modals & Buttons | 3 files |
| 5 | Typography & Polish | All files |

## Testing Checklist

- [ ] iPhone SE (375px width) - Primary target
- [ ] iPhone 14 (390px width)
- [ ] iPad Mini (768px width)
- [ ] Desktop (1280px+ width)

Test each flow:
1. Investment → Click account → View details
2. Protection → Click policy → View details
3. Retirement → Click pension → View details

## Estimated Changes

- ~18 Vue component files modified
- Primarily Tailwind class additions (responsive prefixes)
- No structural/logic changes
- No backend changes required
