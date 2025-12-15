# Net Worth Module UI Redesign Plan

**Date**: December 15, 2025
**Branch**: UI-improvements
**Status**: Planning

---

## Summary
Redesign the Net Worth module to replace horizontal tabs with a card-based Overview page and conditional sidebar navigation.

## Requirements
1. **Rename current Overview** → "Wealth Summary"
2. **Create new Overview** with 4 clickable cards: Retirement, Property, Investments, Cash
3. **Cards display**: Total value + list of individual accounts
4. **Sidebar**: Hidden on Overview, visible on all other sections
5. **Remove horizontal tabs** - sidebar becomes the only navigation

---

## Implementation Steps

### Phase 1: Backend API Enhancement

**File: `app/Services/NetWorth/NetWorthService.php`**
- Add `getAssetsSummaryWithDetails()` method returning account lists:
```php
[
  'pensions' => ['count' => 2, 'total_value' => 165000, 'items' => [
    ['id' => 1, 'name' => 'Workplace Pension', 'value' => 45000],
    ['id' => 2, 'name' => 'SIPP', 'value' => 120000],
  ]],
  'property' => [...],
  'investments' => [...],
  'cash' => [...],
]
```

**File: `app/Http/Controllers/Api/NetWorthController.php`**
- Add `getAssetsSummaryWithDetails()` controller method

**File: `routes/api.php`**
- Add route: `GET /net-worth/assets-summary-detailed`

---

### Phase 2: Frontend Store Updates

**File: `resources/js/store/modules/netWorth.js`**
- Add `assetsSummaryDetailed` state with items arrays
- Add `SET_ASSETS_SUMMARY_DETAILED` mutation
- Add `fetchAssetsSummaryDetailed` action

**File: `resources/js/services/netWorthService.js`**
- Add `getAssetsSummaryDetailed()` method

---

### Phase 3: Component Changes

**Step 3.1: Rename existing Overview**
- Rename `resources/js/components/NetWorth/NetWorthOverview.vue` → `NetWorthWealthSummary.vue`
- Update component name inside file

**Step 3.2: Create new card-based Overview**
- Create `resources/js/components/NetWorth/NetWorthOverview.vue` (new)
- 4 clickable cards showing totals + account lists
- Click navigates to respective section

**Step 3.3: Modify Dashboard for conditional sidebar**
- Update `resources/js/views/NetWorth/NetWorthDashboard.vue`
- Remove horizontal tabs
- Add sidebar with menu items (hidden when route is 'overview')
- Grid layout: sidebar (240px) + content area

---

### Phase 4: Router Updates

**File: `resources/js/router/index.js`**
- Update `/net-worth` children:
  - `overview` → new card-based component
  - `wealth-summary` → renamed existing overview
- Update preview routes similarly

---

### Phase 5: Styling

**Sidebar styles:**
- Sticky sidebar with white background
- Active state with blue highlight
- Hover effects

**Card styles:**
- Follow existing card patterns
- Grid layout for 4 cards
- Show max 3-4 accounts with "view all" link

**Mobile responsive:**
- Hide sidebar on mobile (<1024px)
- Full-width content

---

## Files to Modify

| File | Change |
|------|--------|
| `app/Services/NetWorth/NetWorthService.php` | Add `getAssetsSummaryWithDetails()` |
| `app/Http/Controllers/Api/NetWorthController.php` | Add controller method |
| `routes/api.php` | Add new route |
| `resources/js/store/modules/netWorth.js` | Add detailed state/actions |
| `resources/js/services/netWorthService.js` | Add API method |
| `resources/js/components/NetWorth/NetWorthOverview.vue` | Rename to WealthSummary |
| `resources/js/components/NetWorth/NetWorthOverview.vue` | Create new (cards) |
| `resources/js/views/NetWorth/NetWorthDashboard.vue` | Replace tabs with sidebar |
| `resources/js/router/index.js` | Update route config |

---

## Sidebar Menu Items
1. Overview (card-based)
2. Wealth Summary (current overview with charts)
3. Retirement
4. Property
5. Investments
6. Cash
7. Business Interests
8. Chattels
9. Joint History

---

## Implementation Order
1. Backend API (ensure data available)
2. Store updates (frontend can consume data)
3. Create new Overview component (cards)
4. Rename existing Overview → WealthSummary
5. Update router
6. Modify Dashboard (add sidebar)
7. Styling and polish
8. Test navigation and mobile

---

## Progress Tracking

- [x] Phase 1: Backend API Enhancement
  - [x] Add `getAssetsSummaryWithDetails()` to NetWorthService
  - [x] Add controller method
  - [x] Add API route
- [x] Phase 2: Frontend Store Updates
  - [x] Add store state and actions
  - [x] Add service method
- [x] Phase 3: Component Changes
  - [x] Rename NetWorthOverview → NetWorthWealthSummary
  - [x] Create new card-based NetWorthOverview
  - [x] Modify NetWorthDashboard with sidebar
- [x] Phase 4: Router Updates
  - [x] Update main routes
  - [x] Update preview routes
- [x] Phase 5: Styling & Testing
  - [x] Sidebar styling
  - [x] Card styling
  - [x] Mobile responsive
  - [x] API tested successfully

**Completed**: December 15, 2025
