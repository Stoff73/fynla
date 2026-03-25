# Dashboard Navigation Restructure & UI Updates — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restructure navigation so the header bar shows category names, sub-nav shows sibling pages within categories, and apply several dashboard/income/expenditure UI updates.

**Architecture:** Rewrite `subNavConfig.js` to a category-based schema, update `SubNavBar.vue` and `Navbar.vue` to use category lookup instead of per-route matching. Remaining tasks are isolated component-level changes (hero layout, card cleanup, styling tweaks, text renames).

**Tech Stack:** Vue.js 3, Vuex, Vue Router, Tailwind CSS, ApexCharts

**Spec:** `docs/superpowers/specs/2026-03-23-dashboard-nav-restructure.md`

---

### Task 1: Rewrite subNavConfig.js to category-based schema

**Files:**
- Modify: `resources/js/constants/subNavConfig.js`

- [ ] **Step 1: Replace the entire `SUB_NAV_CONFIG` array with category-based entries**

Replace the contents of `subNavConfig.js` with:

```javascript
export const SUB_NAV_CONFIG = [
  {
    category: 'cashManagement',
    headerTitle: 'Cash Management',
    tabs: [
      { label: 'Bank Accounts', to: '/net-worth/cash', matchPrefixes: ['/net-worth/cash', '/savings'] },
      { label: 'Income', to: { path: '/valuable-info', query: { section: 'income' } }, matchQuery: { section: 'income' }, matchPrefixes: ['/valuable-info'] },
      { label: 'Expenditure', to: { path: '/valuable-info', query: { section: 'expenditure' } }, matchQuery: { section: 'expenditure' }, matchPrefixes: ['/valuable-info'] },
    ],
    ctas: {
      '/net-worth/cash': [
        { label: 'Add Account', icon: 'plus', action: 'addAccount', style: 'primary' },
      ],
    },
  },
  {
    category: 'finances',
    headerTitle: 'Finances',
    tabs: [
      { label: 'Investments', to: '/net-worth/investments', matchPrefixes: ['/net-worth/investments', '/net-worth/investment-detail', '/net-worth/tax-efficiency', '/net-worth/holdings-detail', '/net-worth/fees-detail', '/net-worth/strategy-detail'] },
      { label: 'Retirement', to: '/net-worth/retirement', matchPrefixes: ['/net-worth/retirement', '/pension'] },
      { label: 'Property', to: '/net-worth/property', matchPrefixes: ['/net-worth/property'] },
      { label: 'Liabilities', to: '/net-worth/liabilities', matchPrefixes: ['/net-worth/liabilities'] },
      { label: 'Personal Valuables', to: '/net-worth/chattels', matchPrefixes: ['/net-worth/chattels'] },
      { label: 'Risk Profile', to: '/risk-profile', matchPrefixes: ['/risk-profile'] },
      { label: 'Business', to: '/net-worth/business', matchPrefixes: ['/net-worth/business'] },
    ],
    ctas: {
      '/net-worth/investments': [
        { label: 'Add Account', icon: 'plus', action: 'addAccount', style: 'primary' },
        { label: 'Upload Statement', icon: 'upload', action: 'uploadStatement', style: 'secondary' },
      ],
      '/net-worth/retirement': [
        { label: 'Add Pension', icon: 'plus', action: 'addPension', style: 'primary' },
        { label: 'Upload Statement', icon: 'upload', action: 'uploadStatement', style: 'secondary' },
      ],
      '/net-worth/property': [
        { label: 'Add Property', icon: 'plus', action: 'addProperty', style: 'primary' },
      ],
      '/net-worth/liabilities': [],
      '/net-worth/chattels': [],
      '/risk-profile': [],
      '/net-worth/business': [],
    },
  },
  {
    category: 'family',
    headerTitle: 'Family',
    tabs: [
      { label: 'Protection', to: '/protection', matchPrefixes: ['/protection'] },
      { label: 'Will', to: '/estate/will-builder', matchPrefixes: ['/estate/will-builder'] },
      { label: 'Letter to Spouse', to: { path: '/valuable-info', query: { section: 'letter' } }, matchQuery: { section: 'letter' }, matchPrefixes: ['/valuable-info'] },
      { label: 'Trusts', to: '/trusts', matchPrefixes: ['/trusts'] },
      { label: 'Estate Planning', to: '/estate', matchPrefixes: ['/estate'] },
      { label: 'Power of Attorney', to: '/estate/power-of-attorney', matchPrefixes: ['/estate/power-of-attorney'] },
    ],
    ctas: {
      '/protection': [
        { label: 'Add Policy', icon: 'plus', action: 'addPolicy', style: 'primary' },
      ],
      '/trusts': [
        { label: 'Add Trust', icon: 'plus', action: 'addTrust', style: 'primary' },
        { label: 'Upload Document', icon: 'upload', action: 'uploadDocument', style: 'secondary' },
      ],
    },
  },
  {
    category: 'planning',
    headerTitle: 'Planning',
    tabs: [
      { label: 'Holistic Plan', to: '/holistic-plan', matchPrefixes: ['/holistic-plan'] },
      { label: 'Plans', to: '/plans', matchPrefixes: ['/plans'] },
      { label: 'Journeys', to: '/planning/journeys', matchPrefixes: ['/planning/journeys'] },
      { label: 'What If', to: '/planning/what-if', matchPrefixes: ['/planning/what-if'] },
      { label: 'Goals', to: '/goals', matchPrefixes: ['/goals'] },
      { label: 'Life Events', to: { path: '/goals', query: { tab: 'events' } }, matchQuery: { tab: 'events' }, matchPrefixes: ['/goals'] },
      { label: 'Actions', to: '/actions', matchPrefixes: ['/actions'] },
    ],
    ctas: {
      '/goals': [
        { label: 'Add Goal', icon: 'plus', action: 'addGoal', style: 'primary' },
      ],
    },
  },
];

// Helper: find category config for a given route
export function findCategoryConfig(routePath, routeQuery = {}) {
  for (const category of SUB_NAV_CONFIG) {
    for (const tab of category.tabs) {
      // Check query-param tabs first (more specific)
      if (tab.matchQuery) {
        const queryMatch = Object.entries(tab.matchQuery).every(
          ([key, value]) => routeQuery[key] === value
        );
        if (queryMatch && tab.matchPrefixes.some(p => routePath.startsWith(p))) {
          return category;
        }
      }
    }
    // Then check prefix-only tabs
    for (const tab of category.tabs) {
      if (!tab.matchQuery && tab.matchPrefixes.some(p => routePath.startsWith(p))) {
        return category;
      }
    }
  }
  return null;
}

// Helper: find the active tab within a category
export function findActiveTab(category, routePath, routeQuery = {}) {
  if (!category) return null;

  // Priority 1: query-param match (most specific)
  for (const tab of category.tabs) {
    if (tab.matchQuery) {
      const queryMatch = Object.entries(tab.matchQuery).every(
        ([key, value]) => routeQuery[key] === value
      );
      if (queryMatch && tab.matchPrefixes.some(p => routePath.startsWith(p))) {
        return tab;
      }
    }
  }

  // Priority 2: longest prefix match (e.g., /estate/will-builder before /estate)
  let bestMatch = null;
  let bestLength = 0;
  for (const tab of category.tabs) {
    if (tab.matchQuery) continue;
    for (const prefix of tab.matchPrefixes) {
      if (routePath.startsWith(prefix) && prefix.length > bestLength) {
        bestMatch = tab;
        bestLength = prefix.length;
      }
    }
  }
  return bestMatch;
}

// Helper: get CTAs for the active tab's route
export function getActiveCtas(category, activeTab) {
  if (!category || !activeTab || !category.ctas) return [];
  const tabPath = typeof activeTab.to === 'string' ? activeTab.to : activeTab.to.path;
  return category.ctas[tabPath] || [];
}
```

- [ ] **Step 2: Verify the file has no syntax errors**

Run: `node -e "require('./resources/js/constants/subNavConfig.js')"` — this will fail because it's ESM. Instead check Vite compiles by watching the dev server terminal for errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/constants/subNavConfig.js
git commit -m "refactor: rewrite subNavConfig to category-based schema"
```

---

### Task 2: Update SubNavBar.vue for category-based config

**Files:**
- Modify: `resources/js/components/SubNavBar.vue`

- [ ] **Step 1: Update imports and computed properties**

Replace the current config lookup logic. The component currently imports `SUB_NAV_CONFIG` and iterates with `path.startsWith(m)`. Replace with:

```javascript
import { findCategoryConfig, findActiveTab, getActiveCtas } from '@/constants/subNavConfig';
```

Replace the `config` computed property (lines 54-63) with:

```javascript
categoryConfig() {
  return findCategoryConfig(this.$route.path, this.$route.query);
},
activeTab() {
  return findActiveTab(this.categoryConfig, this.$route.path, this.$route.query);
},
activeCtas() {
  return getActiveCtas(this.categoryConfig, this.activeTab);
},
```

- [ ] **Step 2: Update template to use category tabs**

Replace the tab `v-for` (lines 6-14). Change `config.tabs` to `categoryConfig.tabs` and update the active check:

```html
<template>
  <div v-if="categoryConfig" class="bg-white border-b border-light-gray">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center -mb-px">
        <nav class="flex space-x-6 overflow-x-auto" aria-label="Sub navigation">
          <router-link
            v-for="tab in categoryConfig.tabs"
            :key="tabKey(tab)"
            :to="tab.to"
            class="whitespace-nowrap py-3 px-1 border-b-2 text-body-sm font-medium transition-colors"
            :class="isTabActive(tab)
              ? 'border-raspberry-500 text-raspberry-600'
              : 'border-transparent text-neutral-500 hover:text-horizon-500 hover:border-horizon-300'"
          >
            {{ tab.label }}
          </router-link>
        </nav>

        <!-- CTAs -->
        <div v-if="activeCtas.length" class="ml-auto flex items-center gap-2 py-2">
          <button
            v-for="cta in activeCtas"
            :key="cta.action"
            @click="handleCta(cta.action)"
            :class="cta.style === 'primary'
              ? 'bg-raspberry-500 hover:bg-raspberry-600 text-white'
              : 'bg-white hover:bg-savannah-100 text-horizon-500 border border-light-gray'"
            class="inline-flex items-center px-3 py-1.5 rounded-button text-body-sm font-medium transition-colors"
          >
            {{ cta.label }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
```

- [ ] **Step 3: Update `isTabActive` method**

Replace the existing `isTabActive` method (lines 65-79) with:

```javascript
isTabActive(tab) {
  return this.activeTab && this.tabKey(tab) === this.tabKey(this.activeTab);
},
```

- [ ] **Step 4: Update `handleCta` method**

Keep the existing CTA dispatch pattern — `this.$store.dispatch('subNav/triggerCta', action)`.

- [ ] **Step 5: Verify in browser**

Navigate to `/net-worth/investments` — sub-nav should show: Investments (active) | Retirement | Property | Liabilities | Personal Valuables | Risk Profile | Business. Navigate to `/net-worth/retirement` — Retirement tab should be active. Navigate to `/valuable-info?section=income` — should show Cash Management tabs with Income active.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/SubNavBar.vue
git commit -m "refactor: SubNavBar uses category-based config for sibling page tabs"
```

---

### Task 3: Update Navbar.vue — category header title + simplified dropdown

**Files:**
- Modify: `resources/js/components/Navbar.vue`

- [ ] **Step 1: Replace `pageTitle` computed with category-based logic**

Replace the `pageTitle` computed property (lines 282-317) with:

```javascript
pageTitle() {
  const path = this.$route.path;
  const query = this.$route.query;

  // Standalone pages (no category)
  if (path.startsWith('/dashboard')) return 'Dashboard';
  if (path.startsWith('/net-worth/wealth-summary')) return 'Net Worth';
  if (path.startsWith('/help')) return 'Help';
  if (path.startsWith('/admin')) return 'Admin Panel';
  if (path.startsWith('/onboarding')) return 'Setup';
  if (path.startsWith('/profile')) return 'Account';
  if (path.startsWith('/settings')) return 'Account';

  // Category-based titles
  const categoryConfig = findCategoryConfig(path, query);
  if (categoryConfig) return categoryConfig.headerTitle;

  return '';
},
```

Add the import at the top of the `<script>` section:

```javascript
import { findCategoryConfig } from '@/constants/subNavConfig';
```

- [ ] **Step 2: Simplify the user dropdown**

Replace the dropdown items (lines 152-218) with:

```html
<!-- User Profile -->
<router-link to="/profile" class="flex items-center px-4 py-2 text-body-sm text-horizon-500 hover:bg-savannah-100" @click="showDropdown = false">
  User Profile
</router-link>
<!-- Risk Profile -->
<router-link to="/risk-profile" class="flex items-center px-4 py-2 text-body-sm text-horizon-500 hover:bg-savannah-100" @click="showDropdown = false">
  Risk Profile
</router-link>
<!-- Settings -->
<router-link to="/settings" class="flex items-center px-4 py-2 text-body-sm text-horizon-500 hover:bg-savannah-100" @click="showDropdown = false">
  Settings
</router-link>
<!-- Divider -->
<div class="border-t border-light-gray my-1"></div>
<!-- Sign Out -->
<button @click="handleLogout" class="flex items-center w-full text-left px-4 py-2 text-body-sm text-horizon-500 hover:bg-savannah-100">
  Sign Out
</button>
```

- [ ] **Step 3: Change Upgrade Now colour**

Find the Upgrade Now button (lines 31-39). Change:
- `text-horizon-500` → `text-raspberry-500`
- `hover:text-horizon-600` → `hover:text-raspberry-600`

Keep `hover:bg-white/40` and all other classes.

- [ ] **Step 4: Verify in browser**

Navigate to `/net-worth/investments` — header should say "Finances". Click user dropdown — should show User Profile, Risk Profile, Settings, Sign Out. Upgrade Now text should be raspberry pink.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/Navbar.vue
git commit -m "refactor: Navbar uses category titles, simplified dropdown, raspberry Upgrade Now"
```

---

### Task 4: Sign Out rename + Upgrade Now colour in SideMenu and MoreMenu

**Files:**
- Modify: `resources/js/components/SideMenu.vue`
- Modify: `resources/js/mobile/views/MoreMenu.vue`

- [ ] **Step 1: SideMenu — rename Logout to Sign Out**

In `SideMenu.vue`, find the logout button (lines 181-194). Change two occurrences:
- `:title="effectiveCollapsed ? 'Logout' : ''"` → `:title="effectiveCollapsed ? 'Sign Out' : ''"`
- `<span ...>Logout</span>` → `<span ...>Sign Out</span>`

- [ ] **Step 2: SideMenu — change Upgrade Now colour**

Find the Upgrade Now link (lines 165-179). Change:
- `text-horizon-500` → `text-raspberry-500`

Add `hover:text-raspberry-600` to the class list. Keep `hover:bg-savannah-100`.

- [ ] **Step 3: MoreMenu — rename Log out to Sign Out**

In `MoreMenu.vue`, find line 14. Change:
- `Log out` → `Sign Out`

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/SideMenu.vue resources/js/mobile/views/MoreMenu.vue
git commit -m "chore: rename Logout to Sign Out, Upgrade Now to raspberry pink"
```

---

### Task 5: Dashboard Hero — ring before greeting, hide empty recommendations

**Files:**
- Modify: `resources/js/components/Journey/JourneyProgressHero.vue`

- [ ] **Step 1: Verify flex order of ring vs greeting**

Read `JourneyProgressHero.vue` lines 24-102. The current order should already be: ring (lines 26-38), then greeting (lines 40-81), then recommendations (lines 84-101). If the ring is already first, no reorder needed — just verify.

If the ring is NOT first, move the ring `<div>` (the `flex-shrink-0 relative w-[160px] h-[160px]` block) to be the first child inside the flex row.

- [ ] **Step 2: Add v-if to hide recommendations when empty**

The recommendations section (lines 84-101) already has `v-if="topRecommendations.length && !heroCollapsed"`. This is correct — it already hides when empty. Verify this works.

- [ ] **Step 3: Commit (if changes were made)**

```bash
git add resources/js/components/Journey/JourneyProgressHero.vue
git commit -m "fix: verify hero ring order and empty recommendations hiding"
```

---

### Task 6: Remove Recommended Actions from dashboard module cards

**Files:**
- Modify: `resources/js/views/Dashboard.vue`

- [ ] **Step 1: Identify all Recommended Actions blocks**

Search for `Recommended Actions` text in `Dashboard.vue`. There are ~6 blocks, one per module card: Protection, Cash & Savings, Investments, Estate Planning, and Retirement (2 blocks — one for each retirement sub-section).

Each block follows this pattern:
```html
<div v-if="xxxActions.length > 0" class="pt-3 border-t border-light-gray space-y-2">
  <div class="text-xs font-semibold text-neutral-500 uppercase tracking-wider">Recommended Actions</div>
  ...action items...
</div>
<div v-else class="pt-3 border-t border-light-gray space-y-2">
  ...fallback content (policy/account list)...
</div>
```

- [ ] **Step 2: Remove the actions blocks, keep fallback content**

For each module card, remove the `v-if="xxxActions.length > 0"` block and its contents. Keep the `v-else` block but remove the `v-else` attribute (make it unconditional) so the fallback content always shows.

Do this for all module cards: Protection, Cash & Savings, Investments, Estate Planning, Retirement.

- [ ] **Step 3: Verify in browser**

Navigate to `/dashboard` — module cards should show their metrics and fallback content but no "Recommended Actions" sections.

- [ ] **Step 4: Commit**

```bash
git add resources/js/views/Dashboard.vue
git commit -m "refactor: remove Recommended Actions from dashboard cards (moved to hero)"
```

---

### Task 7: Life Timeline background change

**Files:**
- Modify: `resources/js/components/Dashboard/LifeTimelineCard.vue`

- [ ] **Step 1: Change background class**

Find line 2, the `:class` binding:
```
:class="timelineEvents.length > 0 ? 'bg-white' : 'bg-light-pink-100/50'"
```

Replace with a static class — remove the `:class` binding entirely and add `bg-neutral-100` to the static `class` attribute:
```
class="rounded-lg border border-light-gray shadow-sm p-6 bg-neutral-100"
```

- [ ] **Step 2: Verify the CTA button remains raspberry**

Check that any button in the component still uses `bg-raspberry-500`. No changes to buttons.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/Dashboard/LifeTimelineCard.vue
git commit -m "style: Life Timeline background to light grey"
```

---

### Task 8: Income page — Disposable Income back in card + Definitions card

**Files:**
- Modify: `resources/js/components/UserProfile/IncomeOccupation.vue`

- [ ] **Step 1: Read the current IncomeOccupation.vue structure**

Read the file to find:
1. The separate Disposable Income card (added in the previous session)
2. The Income card's data breakdown section
3. The bottom row layout (Tax & NI card)

- [ ] **Step 2: Move Disposable Income back into the Income card**

Find the separate Disposable Income `<div>` card. Move its contents (net income, expenditure, disposable income rows) into the full-width Income card, below the income breakdown. Add a separator:

```html
<!-- Disposable Income (inside Income card, below income rows) -->
<div class="border-t border-dashed border-neutral-300 pt-3 mt-4">
  <h4 class="text-sm font-semibold text-horizon-500 mb-2">Disposable Income</h4>
  <!-- net income row -->
  <!-- expenditure row -->
  <!-- disposable income total row -->
</div>
```

Remove the standalone Disposable Income card from the bottom grid.

- [ ] **Step 3: Add Income Definitions card**

In the bottom row (`grid-cols-1 lg:grid-cols-2`), where Disposable Income used to be (next to Tax & NI), add:

```html
<div class="bg-white rounded-lg border border-light-gray p-6 h-full">
  <h3 class="text-h4 font-semibold text-horizon-500 mb-4">Your Income Definitions</h3>
  <div class="space-y-3 text-sm text-neutral-600">
    <p><span class="font-semibold text-horizon-500">Gross Income</span> — Total income before any deductions for tax or National Insurance.</p>
    <p><span class="font-semibold text-horizon-500">Net Income</span> — Your take-home pay after tax, National Insurance, and any student loan repayments.</p>
    <p><span class="font-semibold text-horizon-500">Disposable Income</span> — What remains after subtracting your regular expenditure from net income.</p>
    <p><span class="font-semibold text-horizon-500">Taxable Income</span> — Income above your Personal Allowance (£12,570) that is subject to tax.</p>
  </div>
</div>
```

- [ ] **Step 4: Verify in browser**

Navigate to `/valuable-info?section=income` — Income card should be full-width with donut chart + data + disposable income sub-section. Below: Tax & NI card (left) and Income Definitions card (right).

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/UserProfile/IncomeOccupation.vue
git commit -m "refactor: move Disposable Income into Income card, add Income Definitions card"
```

---

### Task 9: Expenditure budget tabs — segmented control

**Files:**
- Modify: `resources/js/components/UserProfile/ExpenditureForm.vue`

- [ ] **Step 1: Replace budget tab strip with segmented control**

Find the budget tabs section (lines 3-44). Replace:

```html
<!-- Budget Type Tabs (only shown when showBudgetTabs is true) -->
<div v-if="showBudgetTabs" class="mb-4">
  <div class="inline-flex bg-neutral-100 rounded-lg p-0.5">
    <button
      type="button"
      @click="activeBudgetTab = 'current'"
      :class="[
        'text-sm px-4 py-1.5 rounded-md font-medium transition-all duration-200',
        activeBudgetTab === 'current'
          ? 'bg-white text-horizon-500 font-semibold shadow-sm'
          : 'text-neutral-500 hover:text-horizon-500'
      ]"
    >
      Current Budget
    </button>
    <button
      type="button"
      @click="activeBudgetTab = 'retired'"
      :class="[
        'text-sm px-4 py-1.5 rounded-md font-medium transition-all duration-200',
        activeBudgetTab === 'retired'
          ? 'bg-white text-horizon-500 font-semibold shadow-sm'
          : 'text-neutral-500 hover:text-horizon-500'
      ]"
    >
      Budget at Retirement
    </button>
    <button
      v-if="isMarried"
      type="button"
      @click="activeBudgetTab = 'widowed'"
      :class="[
        'text-sm px-4 py-1.5 rounded-md font-medium transition-all duration-200',
        activeBudgetTab === 'widowed'
          ? 'bg-white text-horizon-500 font-semibold shadow-sm'
          : 'text-neutral-500 hover:text-horizon-500'
      ]"
    >
      Budget if Widowed
    </button>
  </div>
</div>
```

Note: `v-if="isMarried"` preserved on the third button.

- [ ] **Step 2: Verify in browser**

Navigate to `/valuable-info?section=expenditure` — budget tabs should show as a pill-style segmented control. Click between tabs — content should switch. If married persona: three buttons. If single: two buttons.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/UserProfile/ExpenditureForm.vue
git commit -m "style: expenditure budget tabs as segmented control"
```

---

### Task 10: Browser verification of all changes

- [ ] **Step 1: Dashboard page**

Navigate to `http://localhost:8000/dashboard`:
- Header should say "Dashboard" (no sub-nav)
- Hero: progress ring on left, greeting centre, recommendations right (or hidden if none)
- Module cards: no "Recommended Actions" sections
- Life Timeline: light grey background, raspberry CTA button

- [ ] **Step 2: Cash Management pages**

Navigate to `/net-worth/cash`:
- Header: "Cash Management"
- Sub-nav: Bank Accounts (active) | Income | Expenditure

Navigate to `/valuable-info?section=income`:
- Header: "Cash Management"
- Sub-nav: Income (active)
- Income card: full-width with donut, disposable income inside, definitions card below

Navigate to `/valuable-info?section=expenditure`:
- Header: "Cash Management"
- Sub-nav: Expenditure (active)
- Budget tabs: segmented control pill style

- [ ] **Step 3: Finances pages**

Navigate to `/net-worth/investments`:
- Header: "Finances"
- Sub-nav: Investments (active) | Retirement | Property | ... | Business

Navigate to `/net-worth/retirement`:
- Header: "Finances"
- Sub-nav: Retirement (active)

- [ ] **Step 4: Family pages**

Navigate to `/protection`:
- Header: "Family"
- Sub-nav: Protection (active) | Will | Letter to Spouse | Trusts | Estate Planning | Power of Attorney

- [ ] **Step 5: Planning pages**

Navigate to `/goals`:
- Header: "Planning"
- Sub-nav: Goals (active)

- [ ] **Step 6: User dropdown + sidebar**

Click user dropdown: User Profile, Risk Profile, Settings, Sign Out
Sidebar bottom: Upgrade Now (raspberry text), Sign Out (not Logout)

- [ ] **Step 7: Commit final verification**

If any fixes were needed during verification, commit them.
