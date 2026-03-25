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
- Default state: use `border: 3px solid transparent` (or match existing border colour)
- Hover state: change border colour to `light-blue-200` — no width change, no layout shift
- Ensure `module-gradient` grey bottom fade and hover blue transition both work

**File:** `resources/css/app.css` (lines 373–383)

---

## 3. Progress Bar at 0%

**Problem:** Bars at 0% currently hidden entirely via `v-if`. User wants "0%" text visible.

**Design (Option A — approved):**
- Remove `v-if="percent > 0"` guards from all 6 progress bars
- When percentage is 0%: render the light blue track with "0%" text in Horizon blue (`text-horizon-500 font-bold`) inside it, left-aligned with `px-4`. No coloured inner bar.
- When percentage > 0%: render normally with gradient bar and white text inside

**Implementation:** Change the `v-if` to control only the gradient background, not the entire inner div. At 0%, the inner div renders with transparent background and horizon text.

**File:** `resources/js/views/Dashboard.vue` (lines ~568–786)

---

## 4. Cash & Savings Card Redesign

**Current:** Icon + total savings number + flat account list (always visible, all accounts shown).

**New design:**

### 4a. Sparkline Chart (below total savings)
- **Type:** ApexCharts line chart, not sparkline mode (need markers)
- **Style:** GA-style — thick Horizon blue line (`stroke-width: 3.5`), large circle markers (`size: 7`) with white centre, subtle gradient fill underneath (`opacityFrom: 0.12, opacityTo: 0.01`)
- **Colour:** Horizon 500 (`#1F2A44`) — line, markers, fill
- **Data:** Last 6 months of balance history. Use monthly snapshots if available from API; if no historical data exists, show current balance as a single flat line
- **Labels:** Month abbreviations on x-axis (e.g. Oct, Nov, Dec, Jan, Feb, Mar). No y-axis labels. No toolbar. No legend.
- **Height:** 80px (compact, fits within card)
- **Grid:** Subtle horizontal grid lines only (`borderColor: #f0f0f0`)

### 4b. Collapsible Accounts Section
- **Header:** "Accounts (N)" with chevron toggle icon
- **Default state:** Collapsed
- **Expanded state:** Show max 3 accounts, sorted by balance descending
- **Overflow:** If >3 accounts, show "View all N accounts →" link below the 3 visible accounts. Links to `/net-worth/cash`.
- **If ≤3 accounts:** Show all, no "View all" link
- **Chevron:** Rotates 180° when expanded (same pattern as AI chat Suggestions)

**Files:**
- `resources/js/views/Dashboard.vue` (lines 342–384)
- May extract `DashboardSparkline.vue` component for reuse

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

**Change:** Bar colour from periwinkle `#A8B8D8` to Horizon 500 `#1F2A44`.

**File:** `resources/js/components/Dashboard/GoalsProjectionChartDashboard.vue` (line ~161)
- `colors: ['#A8B8D8']` → `colors: ['#1F2A44']`

---

## 7. Income Page Donut Chart

**Problem:** `IncomeOccupation.vue` uses hardcoded hex colours instead of `designSystem.js` constants.

**Fix:**
- Import `CHART_COLORS`, `TEXT_COLORS` from `@/constants/designSystem`
- Replace hardcoded colour array with `CHART_COLORS`
- Replace hardcoded text colours (`#999`, `#1F2A44`) with `TEXT_COLORS.muted`, `TEXT_COLORS.primary`
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
