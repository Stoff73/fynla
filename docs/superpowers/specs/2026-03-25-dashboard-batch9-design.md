# Dashboard Batch 9 — Layout, Charts & Card Redesign

**Date:** 2026-03-25
**Status:** Approved
**Scope:** Dashboard home fixes, Cash & Savings/Investments card redesign, Goals chart colour, Income pie chart update

---

## 1. Dashboard Layout — Smaller Desktop Screens

**Problem:** 3-column grid becomes cramped on smaller desktop viewports (ref: DashboardLayout3).

**Fix:**
- Change grid breakpoint from `lg:grid-cols-3` to `xl:grid-cols-3` so smaller desktops (1024–1279px) use 2 columns
- Keep `md:grid-cols-2` and `gap-3`
- Ensure cards have `min-w-0` to prevent overflow

**File:** `resources/js/views/Dashboard.vue` (line ~118)

---

## 2. Card Gradient & Hover Fix

**Problem:** `hover-blue-gradient` sets `border-width: 3px` on hover, causing layout shift (1px → 3px jump).

**Fix:**
- Use `box-shadow: inset 0 0 0 2px theme('colors.light-blue.200')` on hover instead of changing `border-width`. This avoids all layout shift — no padding compensation needed.
- Remove `border-width: 3px` from `.hover-blue-gradient:hover`
- Keep border colour transition for the light-blue tint
- Ensure `module-gradient` grey bottom fade and hover blue transition both work

**File:** `resources/css/app.css` (lines 373–383)

---

## 3. Progress Bar at 0%

**Problem:** Bars at 0% currently hidden entirely via `v-if`. User wants "0%" text visible.

**Design (Option A — approved):**
- Remove `v-if="percent > 0"` guards from all 6 progress bars
- When percentage is 0%: render the light blue track with "0%" text in Horizon blue (`text-horizon-500 font-bold`) inside it, left-aligned with `px-4`. No coloured inner bar.
- When percentage > 0%: render normally with gradient bar and white text inside

**All 6 progress bars:**
1. Retirement Income Replacement (~line 568) — `retirementIncomePercent`
2. Retirement Capital Target (~line 594) — `retirementCapitalPercent`
3. Lifetime ISA Allowance (~line 651) — `lisaAllowanceData.percentUsed`
4. ISA Allowance (~line 683) — `isaAllowanceData.percentUsed`
5. Pension Annual Allowance (~line 731) — `pensionStandardPercent`
6. Carry Forward (~line 766) — `carryForwardData.percentUsed`

**Note:** Carry Forward is conditionally rendered (only when contributions exceed standard allowance). When the parent condition is true but value is 0%, still show the "0%" label.

**Implementation:** Change the `v-if` to control only the gradient background, not the entire inner div. At 0%, the inner div renders with transparent background and horizon text.

**File:** `resources/js/views/Dashboard.vue` (lines ~568–786)

---

## 4. Cash & Savings Card Redesign

**Current:** Icon + total savings number + flat account list (always visible, all accounts shown).

**New design:**

### 4a. Sparkline Chart (below total savings)
- **Type:** ApexCharts line chart, not sparkline mode (need markers)
- **Style:** GA-style — thick Horizon blue line (`stroke-width: 3.5`), large circle markers (`size: 7`) with white centre, subtle gradient fill underneath (`opacityFrom: 0.12, opacityTo: 0.01`)
- **Colour:** Use `SECONDARY_COLORS[500]` from `designSystem.js` (Horizon 500) — line, markers, fill. No hardcoded hex.
- **Data:** Last 6 months of balance history. Step 1 of implementation must investigate whether backend provides monthly snapshots. If no historical data exists, show current balance as a single flat line — known limitation until backend adds snapshot support.
- **Labels:** Month abbreviations on x-axis (e.g. Oct, Nov, Dec, Jan, Feb, Mar). No y-axis labels. No toolbar. No legend.
- **Height:** 80px (compact, fits within card). If too cramped during implementation, increase to 100px.
- **Grid:** Subtle horizontal grid lines only, using `BORDER_COLORS.default` from `designSystem.js` (not hardcoded hex)

### 4b. Collapsible Accounts Section
- **Header:** "Accounts (N)" with chevron toggle icon
- **Default state:** Collapsed
- **Expanded state:** Show max 3 accounts, sorted by balance descending
- **Overflow:** If >3 accounts, show "View all N accounts →" link below the 3 visible accounts. Links to `/net-worth/cash`.
- **If ≤3 accounts:** Show all, no "View all" link
- **Chevron:** Rotates 180° when expanded (same pattern as AI chat Suggestions)
- **Accessibility:** Toggle button must include `aria-expanded` bound to open state and `aria-controls` pointing to the collapsible content `id`
- **Sort tie-breaking:** Sort by balance descending, then alphabetically by account name for equal balances

**Files:**
- `resources/js/views/Dashboard.vue` (lines 342–384)
- New: `DashboardSparkline.vue` — reusable component

### DashboardSparkline Props Contract
| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `data` | `Array<{label: string, value: number}>` | required | Monthly data points |
| `color` | `String` | `SECONDARY_COLORS[500]` | Line/marker/fill colour |
| `height` | `Number` | `80` | Chart height in px |

---

## 5. Investments Card (Mirror Pattern)

Identical structure to Cash & Savings:
- Sparkline with portfolio value history (same GA-style, Horizon blue)
- "Accounts (N)" collapsible header, collapsed by default, max 3 accounts
- "View all N accounts →" links to `/net-worth/investments`
- Sort accounts by `current_value` or `total_value` descending

**File:** `resources/js/views/Dashboard.vue` (lines 386–428)

---

## 6. Goals Bar Chart — Horizon Blue

**Change:** Bar colour from periwinkle `#A8B8D8` to Horizon 500.

**File:** `resources/js/components/Dashboard/GoalsProjectionChartDashboard.vue` (line ~161)
- Import `SECONDARY_COLORS` from `@/constants/designSystem`
- `colors: ['#A8B8D8']` → `colors: [SECONDARY_COLORS[500]]`

---

## 7. Income Page Donut Chart

**Problem:** `IncomeOccupation.vue` uses hardcoded hex colours instead of `designSystem.js` constants.

**Fix:**
- Import `CHART_COLORS`, `TEXT_COLORS` from `@/constants/designSystem`
- Replace hardcoded colour array with `CHART_COLORS` (this also removes the banned `#F59E0B` amber colour at index 6)
- Replace hardcoded text colours (`#999`, `#1F2A44`) with `TEXT_COLORS.muted`, `TEXT_COLORS.primary`
- Also check template and `<style>` blocks for any remaining hardcoded hex values — replace with Tailwind equivalents (`text-neutral-500` etc.)
- Match styling patterns from `SpendingDonutChart.vue` (font family, stroke, legend)

**File:** `resources/js/components/UserProfile/IncomeOccupation.vue` (lines 505–532)

---

## Data Considerations

### Sparkline Historical Data
The sparkline needs 6 months of balance snapshots. Options:
1. **If API provides history:** Use `savingsService` or similar to fetch monthly balance snapshots
2. **If no history available:** Show a flat line at current balance with a single marker — still communicates the metric visually without fake data
3. **Future enhancement:** Backend could snapshot balances monthly for trend tracking

The implementation plan should investigate which data source is available before building the chart.

---

## Files Affected

| File | Changes |
|------|---------|
| `resources/js/views/Dashboard.vue` | Grid breakpoint, progress bars, Cash/Savings card, Investments card |
| `resources/css/app.css` | Fix hover border shift |
| `resources/js/components/Dashboard/GoalsProjectionChartDashboard.vue` | Bar colour |
| `resources/js/components/UserProfile/IncomeOccupation.vue` | Donut chart colours |
| `resources/js/components/Dashboard/DashboardSparkline.vue` | New — reusable sparkline component |
