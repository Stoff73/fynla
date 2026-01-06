# Mobile Responsive Fixes - Implementation Task List

**Target**: 375px minimum (iPhone SE)
**Status**: COMPLETED

---

## Phase 1: Header Layouts (5 files) - COMPLETED

### Task 1.1: AccountDetailView.vue
- [x] File: `resources/js/views/Investment/AccountDetailView.vue`
- [x] Fix header flex layout: Add `flex-col sm:flex-row`
- [x] Make title responsive: `text-xl sm:text-2xl lg:text-3xl`
- [x] Stack value below title on mobile

### Task 1.2: PropertyDetailInline.vue
- [x] File: `resources/js/components/NetWorth/Property/PropertyDetailInline.vue`
- [x] Fix header: Add `flex-col sm:flex-row gap-4`
- [x] Make Edit/Delete buttons full-width on mobile
- [x] Responsive title sizing

### Task 1.3: PolicyDetail.vue
- [x] File: `resources/js/components/Protection/PolicyDetail.vue`
- [x] Fix header: Stack badges and buttons on mobile
- [x] Title: `text-xl sm:text-2xl lg:text-3xl`
- [x] Buttons: `w-full sm:w-auto` pattern

### Task 1.4: PensionDetail.vue
- [x] File: `resources/js/views/Retirement/PensionDetail.vue`
- [x] Fix header: `flex-col sm:flex-row`
- [x] Responsive title sizing
- [x] Stack Edit/Delete buttons on mobile

### Task 1.5: BusinessInterestDetailInline.vue
- [x] File: `resources/js/components/NetWorth/BusinessInterestDetailInline.vue`
- [x] Fix header layout for mobile
- [x] Responsive button layout

---

## Phase 2: Grid Breakpoints (6 files) - COMPLETED

### Task 2.1: InvestmentDetailInline.vue
- [x] File: `resources/js/components/NetWorth/InvestmentDetailInline.vue`
- [x] Key metrics grid: Add `sm:grid-cols-2` before `md:grid-cols-3`
- [x] Tab navigation: overflow-x-auto works on mobile

### Task 2.2: AccountSummaryPanel.vue
- [x] File: `resources/js/views/Investment/AccountSummaryPanel.vue`
- [x] Details grid: Verified responsive breakpoints
- [x] Added `sm:` breakpoints

### Task 2.3: PolicyDetail.vue (continued)
- [x] Key metrics: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`
- [x] Overview grid: Added `sm:` breakpoint
- [x] Tab navigation: Overflow handling in place

### Task 2.4: CurrentSituation.vue
- [x] File: `resources/js/components/Protection/CurrentSituation.vue`
- [x] 5-column coverage grid: `grid-cols-2 sm:grid-cols-3 lg:grid-cols-5`
- [x] Policy cards grid: Verified breakpoints
- [x] Responsive text sizing for metrics

### Task 2.5: PensionDetail.vue (continued)
- [x] DC metrics grid: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`
- [x] DB metrics grid: Same pattern
- [x] State pension grid: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`
- [x] Details panel: Added `sm:` breakpoint

### Task 2.6: RetirementReadiness.vue
- [x] File: `resources/js/views/Retirement/RetirementReadiness.vue`
- [x] Pension cards grid: Changed `minmax(320px, 1fr)` to `minmax(280px, 1fr)`
- [x] Added responsive breakpoints to grids

---

## Phase 3: Definition Lists (4 files) - COMPLETED

### Task 3.1: PropertyDetailInline.vue (continued)
- [x] All definition lists: Changed `flex justify-between` to `flex flex-col sm:flex-row sm:justify-between`
- [x] Added `gap-1 sm:gap-0` for mobile spacing
- [x] Fixed: Property Details, Ownership, Valuation, Rental Income, Mortgage Details sections

### Task 3.2: PensionDetail.vue (continued)
- [x] Inline definition lists: Stack on mobile
- [x] Pattern: `flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0`
- [x] Fixed: DC, DB, and State pension detail sections

### Task 3.3: AnnualAllowanceTracker.vue
- [x] File: `resources/js/components/Retirement/AnnualAllowanceTracker.vue`
- [x] Progress section: Stack label and value on mobile
- [x] Status text section: Responsive layout
- [x] Remaining Allowance section: Responsive layout
- [x] Historical View section: Responsive layout
- [x] Total Available section: Responsive layout

### Task 3.4: RetirementReadiness.vue (continued)
- [x] Detail rows: Added CSS media query for `.detail-row` class
- [x] Pattern: `flex-direction: column` on mobile, `row` on desktop

---

## Phase 4: Modals & Buttons (3 files) - COMPLETED

### Task 4.1: PolicyFormModal.vue
- [x] File: `resources/js/components/Protection/PolicyFormModal.vue`
- [x] Modal container: Added `mx-4 sm:mx-0` for edge padding
- [x] Button layout: Uses `flex gap-3` with `flex-1` on submit

### Task 4.2: PolicyCard.vue
- [x] File: `resources/js/components/Protection/PolicyCard.vue`
- [x] Card grid: Changed to `grid-cols-1 sm:grid-cols-2`
- [x] Reduced gap on mobile: `gap-2 sm:gap-4`

### Task 4.3: StrategyCard.vue
- [x] File: `resources/js/components/Retirement/StrategyCard.vue`
- [x] Already has comprehensive mobile responsive styles (lines 765-792)
- [x] No changes needed

---

## Phase 5: Typography & Final Polish - COMPLETED

### Task 5.1: Global typography audit
- [x] Fixed `text-3xl` without responsive prefix in detail view titles
- [x] Pattern applied: `text-xl sm:text-2xl lg:text-3xl`
- [x] Fixed files:
  - SavingsAccountDetailInline.vue
  - SavingsAccountDetail.vue
  - ChattelDetailInline.vue
  - PropertyDetail.vue
  - PensionDetailInline.vue

### Task 5.2: Additional header layouts fixed
- [x] SavingsAccountDetailInline.vue: Added responsive header layout
- [x] SavingsAccountDetail.vue: Added responsive header layout
- [x] ChattelDetailInline.vue: Added responsive header layout
- [x] PropertyDetail.vue: Added responsive header layout
- [x] PensionDetailInline.vue: Added responsive header layout

---

## All Modified Files Summary

| File | Changes Made |
|------|-------------|
| `views/Investment/AccountDetailView.vue` | Header layout, title typography |
| `components/NetWorth/Property/PropertyDetailInline.vue` | Header layout, title typography, all definition lists |
| `components/Protection/PolicyDetail.vue` | Header layout, title typography, metrics grids |
| `views/Retirement/PensionDetail.vue` | Header layout, title typography, metrics grids, definition lists |
| `components/NetWorth/BusinessInterestDetailInline.vue` | Header layout, title typography |
| `components/NetWorth/InvestmentDetailInline.vue` | Metrics grid breakpoints |
| `views/Investment/AccountSummaryPanel.vue` | Grid breakpoints |
| `components/Protection/CurrentSituation.vue` | Coverage grid (5-col to responsive) |
| `views/Retirement/RetirementReadiness.vue` | Pension cards grid, CSS for detail-row |
| `components/Retirement/AnnualAllowanceTracker.vue` | Progress sections, summary rows |
| `components/Protection/PolicyFormModal.vue` | Modal edge padding |
| `components/Protection/PolicyCard.vue` | Card grid responsive |
| `views/Savings/SavingsAccountDetailInline.vue` | Header layout, title typography |
| `views/Savings/SavingsAccountDetail.vue` | Header layout, title typography |
| `components/NetWorth/ChattelDetailInline.vue` | Header layout, title typography |
| `components/NetWorth/Property/PropertyDetail.vue` | Header layout, title typography |
| `components/NetWorth/PensionDetailInline.vue` | Header layout, title typography |

---

## Testing Checklist

- [ ] Test on iPhone SE simulator (375px)
- [ ] Test on iPhone 14 size (390px)
- [ ] Test on iPad Mini (768px)
- [ ] Test on desktop (1280px+)
- [ ] Test each detail view type
- [ ] Verify no horizontal scroll at 375px

---

## Quick Reference: Tailwind Breakpoints

| Prefix | Min Width | Use Case |
|--------|-----------|----------|
| (none) | 0px | Mobile-first base styles |
| `sm:` | 640px | Large phones, small tablets |
| `md:` | 768px | Tablets |
| `lg:` | 1024px | Laptops, desktops |
| `xl:` | 1280px | Large desktops |

## Quick Reference: Common Patterns

### Responsive Header
```html
<div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
```

### Responsive Grid
```html
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
```

### Responsive Buttons
```html
<div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
  <button class="w-full sm:w-auto">Button</button>
</div>
```

### Responsive Typography
```html
<h1 class="text-xl sm:text-2xl lg:text-3xl font-bold">Title</h1>
```

### Definition List (Stack on Mobile)
```html
<div class="flex flex-col sm:flex-row sm:justify-between gap-1 sm:gap-0">
  <dt class="text-sm text-gray-600">Label:</dt>
  <dd class="text-sm font-medium text-gray-900">Value</dd>
</div>
```
