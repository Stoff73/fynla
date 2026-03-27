# Dashboard Navigation Restructure & UI Updates

**Date:** 2026-03-23
**Branch:** dashboard

## 1. Navigation Restructure — Header Bar + Sub-Nav

### 1a. Header bar shows category name
Replace the per-page title logic in `Navbar.vue` (`pageTitle` computed) with a category-based mapping. Use prefix matching — sub-routes inherit their parent's category.

| Route prefix | Header title |
|---|---|
| `/dashboard` | Dashboard |
| `/net-worth/wealth-summary` | Net Worth |
| `/net-worth/cash`, `/savings`, `/valuable-info?section=income`, `/valuable-info?section=expenditure` | Cash Management |
| `/net-worth/investments`, `/net-worth/investment-detail`, `/net-worth/tax-efficiency`, `/net-worth/holdings-detail`, `/net-worth/fees-detail`, `/net-worth/strategy-detail`, `/net-worth/retirement`, `/pension`, `/net-worth/property`, `/net-worth/liabilities`, `/net-worth/chattels`, `/net-worth/business`, `/risk-profile` | Finances |
| `/protection`, `/estate`, `/trusts`, `/valuable-info?section=letter` | Family |
| `/holistic-plan`, `/plans`, `/planning`, `/goals`, `/actions` | Planning |
| `/profile`, `/settings` | Account |
| `/help`, `/admin`, `/checkout`, `/onboarding` | No category header (keep existing per-page title) |

Sub-routes (e.g., `/risk-profile/levels`, `/estate/will-builder`, `/plans/investment`, `/actions/:planType/:actionId`) inherit their parent prefix's category.

### 1b. Sub-nav shows sibling pages within category
Replace the per-route tab config in `subNavConfig.js` with category-based config. Each category shows its sibling pages as tabs, with the active page highlighted (raspberry-500 border-bottom).

| Category | Tab label | Route |
|---|---|---|
| **Cash Management** | Bank Accounts | `/net-worth/cash` |
| | Income | `/valuable-info?section=income` |
| | Expenditure | `/valuable-info?section=expenditure` |
| **Finances** | Investments | `/net-worth/investments` |
| | Retirement | `/net-worth/retirement` |
| | Property | `/net-worth/property` |
| | Liabilities | `/net-worth/liabilities` |
| | Personal Valuables | `/net-worth/chattels` |
| | Risk Profile | `/risk-profile` |
| | Business | `/net-worth/business` |
| **Family** | Protection | `/protection` |
| | Will | `/estate/will-builder` |
| | Letter to Spouse | `/valuable-info?section=letter` |
| | Trusts | `/trusts` |
| | Estate Planning | `/estate` |
| | Power of Attorney | `/estate/power-of-attorney` |
| **Planning** | Holistic Plan | `/holistic-plan` |
| | Plans | `/plans` |
| | Journeys | `/planning/journeys` |
| | What If | `/planning/what-if` |
| | Goals | `/goals` |
| | Life Events | `/goals?tab=events` |
| | Actions | `/actions` |
| **Dashboard, Net Worth, Account** | No sub-nav | |

**Active tab detection:** A tab is active when the current route path starts with the tab's route (or matches the query param for `/valuable-info` tabs). Sub-routes (e.g., `/net-worth/investment-detail/123`) activate their parent tab (Investments).

**CTAs:** Remain in the sub-nav, shown based on the active page within the category. For example, when on the Investments tab within Finances, show "Add Account" and "Upload Statement" CTAs.

**New config schema for `subNavConfig.js`:**
```js
{
  category: 'finances',
  headerTitle: 'Finances',
  matchPrefixes: ['/net-worth/investments', '/net-worth/retirement', ...],
  tabs: [
    { label: 'Investments', to: '/net-worth/investments', matchPrefixes: ['/net-worth/investments', '/net-worth/investment-detail', '/net-worth/tax-efficiency', '/net-worth/holdings-detail', '/net-worth/fees-detail', '/net-worth/strategy-detail'] },
    { label: 'Retirement', to: '/net-worth/retirement', matchPrefixes: ['/net-worth/retirement', '/pension'] },
    ...
  ],
  ctas: {
    '/net-worth/investments': [{ label: 'Add Account', action: 'addAccount' }, { label: 'Upload Statement', action: 'uploadStatement' }],
    '/net-worth/retirement': [{ label: 'Add Pension', action: 'addPension' }, { label: 'Upload Statement', action: 'uploadStatement' }],
    ...
  }
}
```

**SubNavBar.vue matching:** Replace the current per-route `find()` with a category lookup — find the category entry whose `matchPrefixes` includes the current route path (prefix match). Then render that category's tabs, highlighting the one whose `matchPrefixes` includes the current route.

**Pages with existing internal tabs (Investments, Estate):** The old sub-nav tabs for Investments (Portfolio, Tax Efficiency, Holdings, Fees) and Estate (Overview, Will Builder, Power of Attorney) become internal page-level segmented controls within their respective page components, using the same segmented control pattern as expenditure (section 2). These are separate routes so they already work as standalone pages — the segmented control provides in-page navigation convenience.

### 1c. Sidebar structure unchanged
The left sidebar (`SideMenu.vue`) already uses the correct category groupings. No structural changes needed — only the "Upgrade Now" and "Sign Out" text changes (see sections 7 and 8).

## 2. Expenditure Budget Tabs — Segmented Control

### 2a. Replace border-tab strip with segmented control
In `ExpenditureForm.vue`, replace the current budget period tab strip (Current Budget | Budget at Retirement | Budget if Widowed) with a segmented control pill:
- Container: `inline-flex bg-neutral-100 rounded-lg p-0.5`
- Inactive button: `text-sm px-4 py-1.5 rounded-md text-neutral-500 font-medium`
- Active button: `text-sm px-4 py-1.5 rounded-md bg-white text-horizon-500 font-semibold shadow-sm`
- This visually distinguishes it from the category sub-nav tabs above
- **Important:** The "Budget if Widowed" button must remain conditionally rendered (`v-if="isMarried"`) — only shown for married users

## 3. Dashboard Hero — Ring First + Consolidated Recommendations

### 3a. Move progress ring before greeting text
In `JourneyProgressHero.vue`, reorder the flex children so the layout reads left-to-right:
1. Progress ring (first/left)
2. Greeting text + stage + Continue Journey button (centre, `flex-1`)
3. Recommendations section (right, desktop only)

Verify the current template order and adjust if ring is not already first.

### 3b. Consolidate recommendations from module cards
The hero recommendations section already pulls from the `recommendations` Vuex store. The recommended actions shown on individual dashboard module cards (Protection, Cash, Investments, Estate, Retirement) should be removed from those cards — the hero is the single location for recommendations.

### 3c. Hide recommendations when empty
When `topRecommendations.length === 0`, hide the entire recommendations column (the `w-64 pl-5 ml-2 border-l` div). The hero shows only ring + greeting.

## 4. Dashboard Module Cards — Remove Recommended Actions

In `Dashboard.vue`, remove the "Recommended Actions" `<div>` block from each module card:
- Protection card
- Cash & Savings card
- Investments card
- Estate Planning card
- Retirement card(s)

Each card keeps its metrics, summary data, and any fallback content (e.g., policy/account lists shown when no actions exist). Only the actions-specific block (`v-if="xxxActions.length > 0"`) and its contents are removed. The `v-else` fallback blocks (which show account/policy summaries) should be kept and shown unconditionally since the actions condition is being removed.

## 5. Life Timeline Background

In `LifeTimelineCard.vue`, change the background:
- **When events exist:** `bg-white` → `bg-neutral-100` (light grey, `#F3F4F6`)
- **When empty:** `bg-light-pink-100/50` → `bg-neutral-100` (same light grey)
- Keep the CTA button raspberry (`bg-raspberry-500`)
- Keep the border: `border border-light-gray`

## 6. Income Page — Disposable Income + Definitions

### 6a. Move Disposable Income back into Income card
In `IncomeOccupation.vue`, remove the separate Disposable Income card and move its content back into the full-width Income card, below the income breakdown data. Show it as a sub-section with a dashed top border separator (`border-t border-dashed border-neutral-300 pt-3 mt-4`).

### 6b. Income Definitions next to Tax & NI
The bottom row keeps two side-by-side cards (`grid-cols-1 lg:grid-cols-2`):
- Left: Estimated Tax & National Insurance (existing data, existing card styling)
- Right: Your Income Definitions card — same card styling (`bg-white rounded-lg border border-light-gray p-6`), with a stacked list of definitions:
  - **Gross Income** — Total income before any deductions for tax or National Insurance.
  - **Net Income** — Your take-home pay after tax, National Insurance, and any student loan repayments.
  - **Disposable Income** — What remains after subtracting your regular expenditure from net income.
  - **Taxable Income** — Income above your Personal Allowance (£12,570) that is subject to tax.

## 7. Upgrade Now — Raspberry Pink Text

### 7a. Navbar
In `Navbar.vue`, change the Upgrade Now button classes:
- From: `text-horizon-500 hover:text-horizon-600 hover:bg-white/40`
- To: `text-raspberry-500 hover:text-raspberry-600 hover:bg-white/40`
- Keep all other classes unchanged (`inline-flex items-center text-sm font-semibold px-3 py-1.5 rounded-md transition-all`)

### 7b. SideMenu
In `SideMenu.vue`, change the Upgrade Now link classes:
- From: `text-horizon-500 hover:bg-savannah-100`
- To: `text-raspberry-500 hover:text-raspberry-600 hover:bg-savannah-100`

## 8. Sign Out Rename

Replace "Logout" / "Log out" with "Sign Out" in all user-facing locations:

| File | Occurrences | Change |
|---|---|---|
| `SideMenu.vue` | Tooltip `:title` attribute (collapsed mode) AND `<span>` label (expanded mode) | `Logout` → `Sign Out` (both occurrences) |
| `Navbar.vue` | Dropdown button text | `Logout` → `Sign Out` |
| `MoreMenu.vue` | Menu item text | `Log out` → `Sign Out` |

Note: `Settings.vue` already uses "Sign Out" — no change needed.

## 9. My Account Dropdown — Simplified

In `Navbar.vue`, replace the user dropdown contents:

**Remove:**
- Letter to Spouse (now in Family sub-nav)
- Income (now in Cash Management sub-nav)
- Expenditure (now in Cash Management sub-nav)

**Keep (in this order):**
1. User Profile → `/profile`
2. Risk Profile → `/risk-profile` (note: route changes from `/valuable-info?section=risk` to `/risk-profile`)
3. Settings → `/settings`
4. Divider
5. Sign Out

## 10. Letter to Spouse in Admin Section

In `SideMenu.vue`, Letter to Spouse is already present under the Family section (or Admin when no spouse). No change needed — it remains accessible via the Family category in both the sidebar and sub-nav.

## Files to modify

| File | Changes |
|---|---|
| `resources/js/components/Navbar.vue` | Category-based `pageTitle`, simplified dropdown, Upgrade Now colour, Sign Out rename |
| `resources/js/constants/subNavConfig.js` | Category-based tab config replacing per-route config |
| `resources/js/components/SubNavBar.vue` | Update to work with new category-based config (tab click navigates between pages) |
| `resources/js/components/Journey/JourneyProgressHero.vue` | Ring before greeting, hide recs when empty |
| `resources/js/views/Dashboard.vue` | Remove Recommended Actions from all module cards, keep fallback content |
| `resources/js/components/Dashboard/LifeTimelineCard.vue` | Background → light grey |
| `resources/js/components/UserProfile/IncomeOccupation.vue` | Disposable Income back in Income card, Income Definitions card |
| `resources/js/components/UserProfile/ExpenditureForm.vue` | Segmented control for budget tabs (preserve `isMarried` conditional) |
| `resources/js/components/SideMenu.vue` | Upgrade Now colour, Sign Out rename (tooltip + label) |
| `resources/js/mobile/views/MoreMenu.vue` | Sign Out rename |
