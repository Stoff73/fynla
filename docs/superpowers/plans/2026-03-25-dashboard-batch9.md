# Dashboard Batch 9 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix dashboard layout/hover/progress bar issues, redesign Cash & Savings and Investments cards with sparklines and collapsible accounts, update Goals chart colour, and fix Income donut chart colours.

**Architecture:** All changes are frontend-only (Vue components + CSS). The sparkline uses mock data via an existing `generateMockBalanceTrend()` utility adapted for 6 monthly points. A new reusable `DashboardSparkline.vue` component wraps ApexCharts line chart with GA-style markers.

**Tech Stack:** Vue 3 (Options API), ApexCharts (vue3-apexcharts), Tailwind CSS, designSystem.js constants

**Spec:** `docs/superpowers/specs/2026-03-25-dashboard-batch9-design.md`

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `resources/css/app.css` | Modify | Fix hover border shift |
| `resources/js/views/Dashboard.vue` | Modify | Grid breakpoint, progress bars, Cash/Savings card, Investments card |
| `resources/js/components/Dashboard/DashboardSparkline.vue` | Create | Reusable GA-style sparkline component |
| `resources/js/components/Dashboard/GoalsProjectionChartDashboard.vue` | Modify | Bar colour to Horizon |
| `resources/js/components/UserProfile/IncomeOccupation.vue` | Modify | Donut chart colours from designSystem |

---

### Task 1: Fix Hover Border Layout Shift

**Files:**
- Modify: `resources/css/app.css:373-383`

- [ ] **Step 1: Replace border-width with box-shadow on hover**

In `resources/css/app.css`, find the `.hover-blue-gradient:hover` rule (line ~377-380). Replace the `border-width: 3px` with an inset box-shadow:

```css
  .hover-blue-gradient:hover {
    border-color: theme('colors.light-blue.200');
    box-shadow: inset 0 0 0 2px theme('colors.light-blue.200');
  }
```

Remove the `border-width: 3px;` line entirely. The `box-shadow` creates a visual border effect without changing layout.

- [ ] **Step 2: Verify in browser**

Run `./dev.sh` if not already running. Open http://localhost:8000/dashboard. Hover over dashboard cards and confirm:
- No layout shift/jump on hover
- Light blue border highlight appears smoothly
- Grey bottom gradient transitions to light blue on hover

- [ ] **Step 3: Commit**

```bash
git add resources/css/app.css
git commit -m "fix: use box-shadow for hover border to prevent layout shift"
```

---

### Task 2: Fix Grid Breakpoint for Smaller Desktops

**Files:**
- Modify: `resources/js/views/Dashboard.vue:118`

- [ ] **Step 1: Change lg to xl for 3-column breakpoint**

Find line ~118:
```html
<div v-else class="dashboard-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
```

Change to:
```html
<div v-else class="dashboard-grid grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
```

This makes screens 1024–1279px use 2 columns instead of 3.

- [ ] **Step 2: Add min-w-0 to prevent card overflow**

Add `min-w-0` to the grid container's children. The simplest approach — add a CSS rule in the `<style scoped>` section of Dashboard.vue:

```css
.dashboard-grid > * {
  min-width: 0;
}
```

- [ ] **Step 3: Update Goals card span class**

The Goals card uses `lg:col-span-2` (two places, lines ~810 and ~837). Update both to `xl:col-span-2`:

```html
class="xl:col-span-2"
```

Also update LifeTimelineCard span (line ~853) from `lg:col-span-3` to `xl:col-span-3`.

- [ ] **Step 4: Verify in browser**

Resize browser to ~1100px width. Confirm cards display in 2-column layout without cramping. At 1280px+, confirm 3-column layout.

- [ ] **Step 5: Commit**

```bash
git add resources/js/views/Dashboard.vue
git commit -m "fix: use xl breakpoint for 3-column dashboard grid on smaller desktops"
```

---

### Task 3: Progress Bar 0% — Show Text Inside Track

**Files:**
- Modify: `resources/js/views/Dashboard.vue:568-786`

- [ ] **Step 1: Fix Retirement Income bar (~line 568)**

Find the retirement income progress bar inner div. It currently has `v-if="retirementIncomePercent > 0"`. Change the approach: always render the inner div, but conditionally apply the gradient background.

Replace:
```html
<div
  v-if="retirementIncomePercent > 0"
  class="h-12 rounded-full transition-all duration-500 flex items-center px-4"
  :class="[
    retirementIncomePercent >= 100 ? 'bg-gradient-to-r from-spring-500 to-spring-400' : 'bg-gradient-to-r from-horizon-400 to-horizon-500',
```

With:
```html
<div
  class="h-12 rounded-full transition-all duration-500 flex items-center px-4"
  :class="[
    retirementIncomePercent === 0 ? '' : (retirementIncomePercent >= 100 ? 'bg-gradient-to-r from-spring-500 to-spring-400' : 'bg-gradient-to-r from-horizon-400 to-horizon-500'),
```

And update the width style to handle 0%:
```html
:style="{ width: retirementIncomePercent > 0 ? Math.min(retirementIncomePercent, 100) + '%' : '100%' }"
```

Update the text inside to show horizon blue at 0%:
```html
<span
  class="text-sm font-bold whitespace-nowrap"
  :class="retirementIncomePercent === 0 ? 'text-horizon-500' : 'text-white'"
>
```

- [ ] **Step 2: Apply same pattern to Retirement Capital bar (~line 594)**

Same changes as Step 1 but for `retirementCapitalPercent`.

- [ ] **Step 3: Apply same pattern to LISA Allowance bar (~line 651)**

Same changes but for `lisaAllowanceData.percentUsed`.

- [ ] **Step 4: Apply same pattern to ISA Allowance bar (~line 683)**

Same changes but for `isaAllowanceData.percentUsed`.

- [ ] **Step 5: Apply same pattern to Pension Annual Allowance bar (~line 731)**

Same changes but for `pensionStandardPercent`.

- [ ] **Step 6: Apply same pattern to Carry Forward bar (~line 766)**

Same changes but for `carryForwardData.percentUsed`.

- [ ] **Step 7: Verify in browser**

Log in as a preview persona with 0% bars (e.g. young_saver John Morgan). Confirm:
- 0% bars show "0%" text in horizon blue inside the light blue track
- Non-zero bars still render normally with gradient and white text
- No coloured bar visible at 0%

- [ ] **Step 8: Commit**

```bash
git add resources/js/views/Dashboard.vue
git commit -m "fix: show 0% text in horizon blue inside progress bar track"
```

---

### Task 4: Create DashboardSparkline Component

**Files:**
- Create: `resources/js/components/Dashboard/DashboardSparkline.vue`

- [ ] **Step 1: Create the component file**

Create `resources/js/components/Dashboard/DashboardSparkline.vue`:

```vue
<template>
  <div class="dashboard-sparkline">
    <div class="text-xs text-neutral-500 mb-1">Last 6 months</div>
    <apexchart
      v-if="chartReady"
      type="line"
      :options="chartOptions"
      :series="chartSeries"
      :height="height"
    />
  </div>
</template>

<script>
import { SECONDARY_COLORS, BORDER_COLORS, TEXT_COLORS, CHART_DEFAULTS } from '@/constants/designSystem';

export default {
  name: 'DashboardSparkline',

  props: {
    data: {
      type: Array,
      required: true,
      // Array of { label: string, value: number }
    },
    color: {
      type: String,
      default: SECONDARY_COLORS[500],
    },
    height: {
      type: Number,
      default: 80,
    },
  },

  data() {
    return {
      chartReady: false,
    };
  },

  mounted() {
    // Delay render to avoid ApexCharts flash
    setTimeout(() => {
      this.chartReady = true;
    }, 100);
  },

  computed: {
    chartSeries() {
      return [{
        name: 'Balance',
        data: this.data.map(d => d.value),
      }];
    },

    chartOptions() {
      return {
        chart: {
          ...CHART_DEFAULTS.chart,
          type: 'line',
          toolbar: { show: false },
          zoom: { enabled: false },
          sparkline: { enabled: false },
        },
        colors: [this.color],
        stroke: {
          curve: 'straight',
          width: 3.5,
          lineCap: 'round',
        },
        markers: {
          size: 7,
          colors: [this.color],
          strokeColors: '#ffffff',
          strokeWidth: 3.5,
          hover: { sizeOffset: 2 },
          // Note: white is intentional for marker centres, not a design system colour
        },
        fill: {
          type: 'gradient',
          gradient: {
            shade: 'light',
            type: 'vertical',
            opacityFrom: 0.12,
            opacityTo: 0.01,
          },
        },
        xaxis: {
          categories: this.data.map(d => d.label),
          labels: {
            style: { fontSize: '10px', colors: TEXT_COLORS.muted },
          },
          axisBorder: { show: false },
          axisTicks: { show: false },
        },
        yaxis: { show: false },
        grid: {
          borderColor: BORDER_COLORS.default,
          strokeDashArray: 0,
          xaxis: { lines: { show: false } },
          yaxis: { lines: { show: true } },
          padding: { left: 0, right: 0, top: -10, bottom: 0 },
        },
        tooltip: { enabled: false },
        legend: { show: false },
        dataLabels: { enabled: false },
      };
    },
  },
};
</script>
```

- [ ] **Step 2: Verify component renders**

Temporarily add to Dashboard.vue to test (will be properly integrated in Task 5). Import and render with dummy data:

```javascript
import DashboardSparkline from '@/components/Dashboard/DashboardSparkline.vue';
```

```html
<DashboardSparkline :data="[
  { label: 'Oct', value: 38000 },
  { label: 'Nov', value: 39500 },
  { label: 'Dec', value: 40200 },
  { label: 'Jan', value: 41000 },
  { label: 'Feb', value: 43500 },
  { label: 'Mar', value: 45230 },
]" />
```

Confirm GA-style chart renders with thick horizon blue lines, large circle markers with white centres, and subtle gradient fill.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/Dashboard/DashboardSparkline.vue
git commit -m "feat: add DashboardSparkline component with GA-style markers"
```

---

### Task 5: Redesign Cash & Savings Card

**Files:**
- Modify: `resources/js/views/Dashboard.vue:342-384`

- [ ] **Step 1: Import DashboardSparkline in Dashboard.vue**

Add to imports (near line ~866):
```javascript
import DashboardSparkline from '@/components/Dashboard/DashboardSparkline.vue';
```

Add to components (near line ~885):
```javascript
DashboardSparkline,
```

- [ ] **Step 2: Add mock sparkline data computed property**

Add to the `computed` section of Dashboard.vue:

```javascript
savingsSparklineData() {
  // Generate 6 monthly data points based on current total
  // Mock: slight upward trend ending at current balance
  const total = this.savingsTotalBalance || 0;
  const months = ['Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'];
  const now = new Date();
  const labels = [];
  for (let i = 5; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    labels.push(d.toLocaleString('en-GB', { month: 'short' }));
  }
  // Generate gentle upward trend
  const variation = total * 0.08;
  return labels.map((label, i) => ({
    label,
    value: Math.round(total - variation + (variation * i / 5)),
  }));
},
```

- [ ] **Step 3: Add collapsed state data property**

Add to `data()` return object:
```javascript
savingsAccountsExpanded: false,
investmentAccountsExpanded: false,
```

- [ ] **Step 4: Add computed for visible savings accounts**

Add to computed:
```javascript
visibleSavingsAccounts() {
  const sorted = [...(this.savingsAccountList || [])].sort((a, b) =>
    (b.current_balance || 0) - (a.current_balance || 0) || (a.account_name || a.provider || '').localeCompare(b.account_name || b.provider || '')
  );
  if (!this.savingsAccountsExpanded) return [];
  return sorted.slice(0, 3);
},
```

- [ ] **Step 5: Replace Cash & Savings card template**

Replace the Cash & Savings card content (lines ~350-376) — the `<div v-if="hasSavingsData" class="space-y-4">` block — with:

```html
<div v-if="hasSavingsData" class="space-y-3">
  <!-- Hero metric -->
  <div class="border-b border-light-gray pb-3">
    <div class="flex items-center gap-4">
      <div class="w-14 h-14 rounded-xl bg-spring-100 flex items-center justify-center flex-shrink-0">
        <svg class="w-8 h-8 text-spring-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
        </svg>
      </div>
      <div>
        <span class="text-sm text-neutral-500">Total Savings</span>
        <div class="mt-0.5">
          <span class="text-2xl sm:text-3xl lg:text-4xl font-black text-spring-600">{{ formatCurrency(savingsTotalBalance) }}</span>
        </div>
      </div>
    </div>
  </div>

  <!-- 6-month sparkline -->
  <DashboardSparkline :data="savingsSparklineData" />

  <!-- Collapsible accounts -->
  <div class="border-t border-light-gray pt-2">
    <button
      class="w-full flex justify-between items-center py-1.5 text-sm"
      :aria-expanded="savingsAccountsExpanded"
      aria-controls="savings-account-list"
      @click.stop="savingsAccountsExpanded = !savingsAccountsExpanded"
    >
      <span class="font-semibold text-horizon-500">Accounts ({{ savingsAccountCount }})</span>
      <svg
        class="w-4 h-4 text-neutral-400 transition-transform duration-200"
        :class="{ 'rotate-180': savingsAccountsExpanded }"
        fill="none" stroke="currentColor" viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>
    <div
      v-if="savingsAccountsExpanded"
      id="savings-account-list"
      class="space-y-2 pt-2"
    >
      <div v-for="acc in visibleSavingsAccounts" :key="acc.id" class="flex justify-between text-sm">
        <span class="text-neutral-500 truncate mr-2">{{ acc.account_name || acc.provider }}</span>
        <span class="font-medium text-horizon-500 whitespace-nowrap">{{ formatCurrency(acc.current_balance) }}</span>
      </div>
      <div v-if="savingsAccountCount > 3" class="text-center pt-1">
        <router-link
          to="/net-worth/cash"
          class="text-xs font-semibold text-horizon-500 hover:text-horizon-600"
          @click.stop
        >
          View all {{ savingsAccountCount }} accounts &rarr;
        </router-link>
      </div>
    </div>
  </div>
</div>
```

- [ ] **Step 6: Verify in browser**

Open http://localhost:8000/dashboard logged in as peak_earners (David & Sarah Mitchell — have savings accounts). Confirm:
- Total Savings with icon renders at top
- Sparkline shows below with 6 months, horizon blue line, circle markers with white centres, subtle fill
- "Accounts (N)" is collapsed by default
- Clicking expands to show max 3 accounts
- "View all" link appears if >3 accounts

- [ ] **Step 7: Commit**

```bash
git add resources/js/views/Dashboard.vue resources/js/components/Dashboard/DashboardSparkline.vue
git commit -m "feat: redesign Cash & Savings card with sparkline and collapsible accounts"
```

---

### Task 6: Redesign Investments Card (Mirror Pattern)

**Files:**
- Modify: `resources/js/views/Dashboard.vue:386-428`

- [ ] **Step 1: Add investment sparkline data computed**

Add to computed section:

```javascript
investmentSparklineData() {
  const total = this.investmentPortfolioValue || 0;
  const labels = [];
  const now = new Date();
  for (let i = 5; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    labels.push(d.toLocaleString('en-GB', { month: 'short' }));
  }
  const variation = total * 0.10;
  return labels.map((label, i) => ({
    label,
    value: Math.round(total - variation + (variation * i / 5)),
  }));
},

visibleInvestmentAccounts() {
  const sorted = [...(this.investmentAccountList || [])].sort((a, b) =>
    ((b.current_value || b.total_value || 0) - (a.current_value || a.total_value || 0)) || (a.account_name || a.provider || '').localeCompare(b.account_name || b.provider || '')
  );
  if (!this.investmentAccountsExpanded) return [];
  return sorted.slice(0, 3);
},
```

- [ ] **Step 2: Replace Investments card template**

Replace the Investments card content (lines ~394-420) — the `<div v-if="hasInvestmentData" class="space-y-4">` block — with:

```html
<div v-if="hasInvestmentData" class="space-y-3">
  <!-- Hero metric -->
  <div class="border-b border-light-gray pb-3">
    <div class="flex items-center gap-4">
      <div class="w-14 h-14 rounded-xl bg-violet-100 flex items-center justify-center flex-shrink-0">
        <svg class="w-8 h-8 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
      </div>
      <div>
        <span class="text-sm text-neutral-500">Portfolio Value</span>
        <div class="mt-0.5">
          <span class="text-2xl sm:text-3xl lg:text-4xl font-black text-horizon-500">{{ formatCurrency(investmentPortfolioValue) }}</span>
        </div>
      </div>
    </div>
  </div>

  <!-- 6-month sparkline -->
  <DashboardSparkline :data="investmentSparklineData" />

  <!-- Collapsible accounts -->
  <div class="border-t border-light-gray pt-2">
    <button
      class="w-full flex justify-between items-center py-1.5 text-sm"
      :aria-expanded="investmentAccountsExpanded"
      aria-controls="investment-account-list"
      @click.stop="investmentAccountsExpanded = !investmentAccountsExpanded"
    >
      <span class="font-semibold text-horizon-500">Accounts ({{ investmentAccountCount }})</span>
      <svg
        class="w-4 h-4 text-neutral-400 transition-transform duration-200"
        :class="{ 'rotate-180': investmentAccountsExpanded }"
        fill="none" stroke="currentColor" viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>
    <div
      v-if="investmentAccountsExpanded"
      id="investment-account-list"
      class="space-y-2 pt-2"
    >
      <div v-for="acc in visibleInvestmentAccounts" :key="acc.id" class="flex justify-between text-sm">
        <span class="text-neutral-500 truncate mr-2">{{ acc.account_name || acc.provider }}</span>
        <span class="font-medium text-horizon-500 whitespace-nowrap">{{ formatCurrency(acc.current_value || acc.total_value || 0) }}</span>
      </div>
      <div v-if="investmentAccountCount > 3" class="text-center pt-1">
        <router-link
          to="/net-worth/investments"
          class="text-xs font-semibold text-horizon-500 hover:text-horizon-600"
          @click.stop
        >
          View all {{ investmentAccountCount }} accounts &rarr;
        </router-link>
      </div>
    </div>
  </div>
</div>
```

- [ ] **Step 3: Verify in browser**

Same persona (peak_earners). Confirm Investments card mirrors Cash & Savings layout exactly.

- [ ] **Step 4: Commit**

```bash
git add resources/js/views/Dashboard.vue
git commit -m "feat: redesign Investments card with sparkline and collapsible accounts"
```

---

### Task 7: Goals Bar Chart — Horizon Blue

**Files:**
- Modify: `resources/js/components/Dashboard/GoalsProjectionChartDashboard.vue:161`

- [ ] **Step 1: Import SECONDARY_COLORS**

Add to the imports at the top of the `<script>` section:
```javascript
import { SECONDARY_COLORS } from '@/constants/designSystem';
```

Check if the file already imports from `designSystem.js` — if so, add `SECONDARY_COLORS` to the existing import.

- [ ] **Step 2: Change bar colour**

Find line ~161:
```javascript
colors: ['#A8B8D8'], // Muted periwinkle blue
```

Replace with:
```javascript
colors: [SECONDARY_COLORS[500]], // Horizon blue
```

- [ ] **Step 3: Verify in browser**

Navigate to dashboard with a persona that has goals data. Confirm bar chart bars are now Horizon blue (#1F2A44) instead of periwinkle.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/Dashboard/GoalsProjectionChartDashboard.vue
git commit -m "style: change Goals bar chart to Horizon blue via designSystem constant"
```

---

### Task 8: Income Page Donut Chart — Design System Colours

**Files:**
- Modify: `resources/js/components/UserProfile/IncomeOccupation.vue:505-532`

- [ ] **Step 1: Add designSystem imports**

Find the `<script>` section (Options API with Composition API imports, line ~422). Add import alongside existing imports:
```javascript
import { CHART_COLORS, TEXT_COLORS } from '@/constants/designSystem';
```

- [ ] **Step 2: Replace hardcoded colours in chart options**

Find `incomeChartOptions` computed (line ~505). Replace:
```javascript
colors: ['#E8365D', '#F5B3C5', '#1F2A44', '#93C5FD', '#6EE7B7', '#5854E6', '#F59E0B', '#6B7280'],
```

With:
```javascript
colors: CHART_COLORS,
```

Also update stroke colour (line ~530):
```javascript
stroke: { width: 2, colors: ['#ffffff'] }, // White separator between segments
```

Replace hardcoded text colours in donut labels:
```javascript
name: { show: true, fontSize: '12px', color: TEXT_COLORS.muted },
value: { show: true, fontSize: '18px', fontWeight: 700, color: TEXT_COLORS.primary, formatter: (val) => formatCurrency(Number(val)) },
total: {
  show: true,
  label: 'Total Annual',
  fontSize: '12px',
  color: TEXT_COLORS.muted,
  formatter: (w) => formatCurrency(w.globals.seriesTotals.reduce((a, b) => a + b, 0)),
},
```

- [ ] **Step 3: Verify in browser**

Navigate to User Profile > Income section. Confirm donut chart renders with design system palette colours (no amber/orange).

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/UserProfile/IncomeOccupation.vue
git commit -m "style: use designSystem constants for Income donut chart colours"
```

---

### Task 9: Final Visual Verification

- [ ] **Step 1: Full dashboard walkthrough**

Using Playwright or manual browser, test as peak_earners (David & Sarah Mitchell):
1. Dashboard loads without errors
2. Cards in 3-column layout at 1280px+, 2-column at 1024-1279px
3. Hover on cards shows light blue border via box-shadow (no layout shift)
4. Grey gradient fades at card bottoms, transitions to light blue on hover
5. Cash & Savings: sparkline renders, accounts collapsed, expand/collapse works, "View all" shows if >3
6. Investments: same pattern as Cash & Savings
7. Goals bar chart: Horizon blue bars
8. Progress bars at 0% show "0%" text in horizon blue

Also test as young_saver (John Morgan) for 0% progress bar and empty module states.

- [ ] **Step 2: Test Income donut**

Navigate to /profile (User Profile). Confirm donut chart colours use design system palette.

- [ ] **Step 3: Final commit if any tweaks needed**

Stage only the specific files that were modified, then commit:
```bash
git add resources/css/app.css resources/js/views/Dashboard.vue resources/js/components/Dashboard/DashboardSparkline.vue resources/js/components/Dashboard/GoalsProjectionChartDashboard.vue resources/js/components/UserProfile/IncomeOccupation.vue
git commit -m "style: dashboard batch 9 visual polish"
```
