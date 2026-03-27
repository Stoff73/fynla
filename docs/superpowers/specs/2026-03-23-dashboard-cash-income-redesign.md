# Dashboard, Bank Accounts & Income Page Redesign

**Date:** 2026-03-23
**Branch:** dashboard

## 1. Dashboard Hero — Recommendations + Progress Ring

### 1a. Replace "Suggested for You" with "Recommendations"
- In `JourneyProgressHero.vue`, replace the "Suggested for You" section with top 3 recommendations from the `recommendations` Vuex store
- Fetch via `recommendations/fetchTopRecommendations` on mount
- Each item shows a trend icon + title text
- Hover: text turns raspberry-500
- Hidden on mobile (desktop only, `hidden lg:block`)

### 1b. Move Continue Journey button
- Move from right-side flex item to below stage text in the centre column
- Add `mt-4` spacing above button

### 1c. Progress ring centering
- Reduce max font size from `lg:text-4xl` to `md:text-3xl`
- Add `-mt-[2px]` to nudge number up for visual centering

**Status:** Already implemented.

## 2. Bank Accounts Page (CashOverview.vue)

### 2a. Header title change
- In `subNavConfig.js`, change the cash route label from "Cash" to "Bank Accounts"
- In `SideMenu.vue`, the label is already "Bank Accounts" — no change needed

### 2b. Account card title font size
- Increase `.card-title` from current size to `text-lg` (18px) and `font-bold` (700)
- Matches the Open Banking heading size

### 2c. Open Banking panel
- Move "Coming Soon" badge from top-right (`justify-between`) to inline next to title (`flex items-center gap-2`)
- Style badge: rounded grey pill (`bg-neutral-200 text-neutral-600 px-2.5 py-0.5 rounded-full text-xs font-semibold`)
- Change panel background from `bg-white` to `bg-light-blue-100` with `border-light-blue-200`

### 2d. Add account button behaviour
- **Cards WITH accounts:** Remove the "Add Account" button at the bottom. Add a circular plus icon (28px, `bg-light-pink-200`, `text-horizon-500`) top-right next to the card title
- **Cards WITHOUT accounts:** Keep the "Add Account" CTA button inside the card AND add the same circular plus icon top-right
- Plus icon uses the same `openAddAccountModal()` handler with the appropriate account type

## 3. Income Page (IncomeOccupation.vue)

### 3a. Layout change
- Change from `grid-cols-1 lg:grid-cols-2` (Income | Tax side-by-side) to:
  - Income card: full-width at top (`col-span-full`)
  - Tax card + Disposable Income section: side-by-side below (`grid-cols-1 lg:grid-cols-2`)

### 3b. Income donut chart
- Add a donut chart (ApexCharts) to the left side of the full-width Income card
- Chart shows income sources as proportional slices with total in centre
- Only shows income types that have values > 0
- Right side keeps the existing income data breakdown
- Uses design system colours from `designSystem.js`

### 3c. Disposable Income as separate card
- Extract the existing disposable income section (net income, expenditure, disposable) from the Income card into its own card
- Place side-by-side with the Tax card below
- No added visualisations — data only

## Files to modify

| File | Change |
|------|--------|
| `resources/js/components/Journey/JourneyProgressHero.vue` | Already done (section 1) |
| `resources/js/views/Dashboard.vue` | Already done (section 1) |
| `resources/js/constants/subNavConfig.js` | Cash label → "Bank Accounts" |
| `resources/js/views/NetWorth/CashOverview.vue` | Card titles, add button logic, Open Banking styling |
| `resources/js/components/UserProfile/IncomeOccupation.vue` | Full-width layout, donut chart, disposable income extraction |
