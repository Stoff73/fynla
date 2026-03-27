# Sub-Navigation Bar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a shared sub-navigation bar below the header bar that shows section-specific tabs and CTAs, replacing duplicate in-page titles and inline CTAs.

**Architecture:** A single `SubNavBar.vue` component renders inside `AppLayout.vue` between the Navbar and the content area. It reads route-based config from a `subNavConfig.js` constant file to determine which tabs and CTAs to show for the current route. Page components emit events upward (via a Vuex store module or provide/inject) to wire CTA clicks back to their modal handlers. On mobile, tabs scroll horizontally.

**Tech Stack:** Vue 3, Vuex, Tailwind CSS, Vue Router

---

## File Structure

| File | Action | Purpose |
|------|--------|---------|
| `resources/js/components/SubNavBar.vue` | Create | Shared sub-navigation component |
| `resources/js/constants/subNavConfig.js` | Create | Route-to-tabs/CTAs mapping config |
| `resources/js/store/modules/subNav.js` | Create | Vuex module for CTA event communication |
| `resources/js/store/index.js` | Modify | Register subNav module |
| `resources/js/layouts/AppLayout.vue` | Modify | Insert SubNavBar between Navbar and content |
| `resources/js/components/NetWorth/InvestmentList.vue` | Modify | Remove duplicate title + inline CTAs, listen for subNav events |
| `resources/js/views/NetWorth/CashOverview.vue` | Modify | Remove duplicate title if present, wire CTAs |
| `resources/js/views/Protection/ProtectionDashboard.vue` | Modify | Remove duplicate title |
| `resources/js/views/Estate/EstateDashboard.vue` | Modify | Remove duplicate title |
| `resources/js/views/Goals/GoalsDashboard.vue` | Modify | Remove duplicate title |

---

### Task 1: Create subNavConfig.js

**Files:**
- Create: `resources/js/constants/subNavConfig.js`

This config maps route prefixes to their sub-nav tabs and CTAs. Each entry defines:
- `tabs`: Array of `{ label, to }` objects (router-link targets)
- `ctas`: Array of `{ label, icon, action, style }` objects
- `action` is a string key dispatched via the subNav Vuex store

- [ ] **Step 1: Create the config file**

```javascript
// resources/js/constants/subNavConfig.js
//
// Maps route prefixes to sub-navigation tabs and CTAs.
// Tabs use router-link `to` values. CTAs dispatch actions via the subNav store.
// Order matters — first match wins (most specific prefixes first).

export const SUB_NAV_CONFIG = [
  // ── Cash Management ──
  {
    match: '/net-worth/cash',
    tabs: [
      { label: 'All Accounts', to: '/net-worth/cash' },
    ],
    ctas: [
      { label: 'Add Account', icon: 'plus', action: 'addAccount', style: 'primary' },
    ],
  },

  // ── Investments (sub-routes first) ──
  {
    match: ['/net-worth/investments', '/net-worth/investment-detail', '/net-worth/tax-efficiency', '/net-worth/holdings-detail', '/net-worth/fees-detail', '/net-worth/strategy-detail'],
    tabs: [
      { label: 'Portfolio', to: '/net-worth/investments' },
      { label: 'Tax Efficiency', to: '/net-worth/tax-efficiency' },
      { label: 'Holdings', to: '/net-worth/holdings-detail' },
      { label: 'Fees', to: '/net-worth/fees-detail' },
    ],
    ctas: [
      { label: 'Add Account', icon: 'plus', action: 'addAccount', style: 'primary' },
      { label: 'Upload Statement', icon: 'upload', action: 'uploadStatement', style: 'secondary' },
    ],
  },

  // ── Retirement ──
  {
    match: '/net-worth/retirement',
    tabs: [
      { label: 'Pensions', to: '/net-worth/retirement' },
    ],
    ctas: [
      { label: 'Add Pension', icon: 'plus', action: 'addPension', style: 'primary' },
    ],
  },

  // ── Property ──
  {
    match: '/net-worth/property',
    tabs: [
      { label: 'Properties', to: '/net-worth/property' },
    ],
    ctas: [
      { label: 'Add Property', icon: 'plus', action: 'addProperty', style: 'primary' },
    ],
  },

  // ── Protection ──
  {
    match: '/protection',
    tabs: [
      { label: 'Policies', to: '/protection' },
    ],
    ctas: [
      { label: 'Add Policy', icon: 'plus', action: 'addPolicy', style: 'primary' },
    ],
  },

  // ── Estate Planning ──
  {
    match: '/estate',
    tabs: [
      { label: 'Overview', to: '/estate' },
      { label: 'Will Builder', to: '/estate/will-builder' },
      { label: 'Power of Attorney', to: '/estate/power-of-attorney' },
    ],
    ctas: [],
  },

  // ── Trusts ──
  {
    match: '/trusts',
    tabs: [
      { label: 'Trusts', to: '/trusts' },
    ],
    ctas: [
      { label: 'Add Trust', icon: 'plus', action: 'addTrust', style: 'primary' },
    ],
  },

  // ── Goals ──
  {
    match: '/goals',
    tabs: [
      { label: 'Overview', to: '/goals' },
      { label: 'Life Events', to: { path: '/goals', query: { tab: 'events' } } },
    ],
    ctas: [
      { label: 'Add Goal', icon: 'plus', action: 'addGoal', style: 'primary' },
    ],
  },

  // ── Actions ──
  {
    match: '/actions',
    tabs: [
      { label: 'All Actions', to: '/actions' },
    ],
    ctas: [],
  },

  // ── Savings ──
  {
    match: '/savings',
    tabs: [
      { label: 'Savings', to: '/savings' },
    ],
    ctas: [
      { label: 'Add Account', icon: 'plus', action: 'addAccount', style: 'primary' },
    ],
  },

  // ── Valuable Info ──
  {
    match: '/valuable-info',
    tabs: [
      { label: 'Letter to Spouse', to: { path: '/valuable-info', query: { section: 'letter' } } },
      { label: 'Income', to: { path: '/valuable-info', query: { section: 'income' } } },
      { label: 'Expenditure', to: { path: '/valuable-info', query: { section: 'expenditure' } } },
      { label: 'Risk Profile', to: { path: '/valuable-info', query: { section: 'risk' } } },
    ],
    ctas: [],
  },

  // ── Settings ──
  {
    match: '/settings',
    tabs: [
      { label: 'General', to: '/settings' },
      { label: 'Security', to: '/settings/security' },
      { label: 'Privacy', to: '/settings/privacy' },
      { label: 'Assumptions', to: '/settings/assumptions' },
    ],
    ctas: [],
  },
];
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/constants/subNavConfig.js
git commit -m "feat: add sub-navigation route config"
```

---

### Task 2: Create subNav Vuex store module

**Files:**
- Create: `resources/js/store/modules/subNav.js`
- Modify: `resources/js/store/index.js`

This module provides a simple event bus pattern: the SubNavBar dispatches a CTA action string, and the page component watches for it and handles the modal/form opening.

- [ ] **Step 1: Create the store module**

```javascript
// resources/js/store/modules/subNav.js
const state = {
  // The last CTA action dispatched (e.g. 'addAccount', 'uploadStatement')
  pendingAction: null,
  // Counter to ensure watchers fire even for repeated same-action clicks
  actionCounter: 0,
};

const mutations = {
  triggerAction(state, action) {
    state.pendingAction = action;
    state.actionCounter++;
  },
  clearAction(state) {
    state.pendingAction = null;
  },
};

const actions = {
  triggerCta({ commit }, action) {
    commit('triggerAction', action);
  },
  consumeCta({ commit }) {
    commit('clearAction');
  },
};

const getters = {
  pendingAction: (state) => state.pendingAction,
  actionCounter: (state) => state.actionCounter,
};

export default {
  namespaced: true,
  state,
  mutations,
  actions,
  getters,
};
```

- [ ] **Step 2: Register in store/index.js**

Add `import subNav from './modules/subNav';` and include `subNav` in the modules object.

- [ ] **Step 3: Commit**

```bash
git add resources/js/store/modules/subNav.js resources/js/store/index.js
git commit -m "feat: add subNav Vuex module for CTA event communication"
```

---

### Task 3: Create SubNavBar.vue component

**Files:**
- Create: `resources/js/components/SubNavBar.vue`

The component reads the current route, matches it against `SUB_NAV_CONFIG`, and renders tabs + CTAs. On mobile, the tab row scrolls horizontally.

- [ ] **Step 1: Create the component**

```vue
<template>
  <div v-if="config" class="bg-white border-b border-light-gray">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between">
        <!-- Tabs: horizontal scroll on mobile -->
        <div class="flex overflow-x-auto scrollbar-hide -mb-px">
          <router-link
            v-for="tab in config.tabs"
            :key="tabKey(tab)"
            :to="tab.to"
            class="whitespace-nowrap py-3 px-4 border-b-2 text-sm font-medium transition-colors flex-shrink-0"
            :class="isTabActive(tab) ? 'border-raspberry-500 text-raspberry-600' : 'border-transparent text-neutral-500 hover:text-horizon-500 hover:border-horizon-300'"
          >
            {{ tab.label }}
          </router-link>
        </div>

        <!-- CTAs -->
        <div v-if="config.ctas.length" class="flex items-center gap-2 flex-shrink-0 ml-4">
          <button
            v-for="cta in config.ctas"
            :key="cta.action"
            @click="handleCta(cta.action)"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-button text-sm font-semibold transition-colors whitespace-nowrap"
            :class="cta.style === 'primary'
              ? 'bg-raspberry-500 text-white hover:bg-raspberry-600'
              : 'bg-white text-horizon-500 border border-light-gray hover:bg-savannah-100'"
          >
            <svg v-if="cta.icon === 'plus'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <svg v-else-if="cta.icon === 'upload'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
            {{ cta.label }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useStore } from 'vuex';
import { SUB_NAV_CONFIG } from '@/constants/subNavConfig';

export default {
  name: 'SubNavBar',

  setup() {
    const route = useRoute();
    const store = useStore();

    const config = computed(() => {
      const path = route.path;
      for (const entry of SUB_NAV_CONFIG) {
        const matches = Array.isArray(entry.match) ? entry.match : [entry.match];
        if (matches.some(m => path.startsWith(m))) {
          return entry;
        }
      }
      return null;
    });

    const isTabActive = (tab) => {
      const tabPath = typeof tab.to === 'string' ? tab.to : tab.to.path;
      const tabQuery = typeof tab.to === 'object' ? tab.to.query : null;

      // Exact path match required
      if (route.path !== tabPath) return false;

      // If tab has query params, they must match too
      if (tabQuery) {
        return Object.entries(tabQuery).every(([key, val]) => route.query[key] === val);
      }

      // If no query on tab, it's active when path matches and no distinguishing query
      return true;
    };

    const tabKey = (tab) => {
      if (typeof tab.to === 'string') return tab.to;
      return tab.to.path + JSON.stringify(tab.to.query || {});
    };

    const handleCta = (action) => {
      store.dispatch('subNav/triggerCta', action);
    };

    return { config, isTabActive, tabKey, handleCta };
  },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/SubNavBar.vue
git commit -m "feat: create SubNavBar component with route-based tabs and CTAs"
```

---

### Task 4: Integrate SubNavBar into AppLayout

**Files:**
- Modify: `resources/js/layouts/AppLayout.vue`

Insert the SubNavBar immediately after the Navbar, inside the `ref="appHeader"` div so it's part of the header area (and the docked chat positions correctly below it).

- [ ] **Step 1: Add import**

Add to imports section:
```javascript
import SubNavBar from '@/components/SubNavBar.vue';
```

Add to components registration.

- [ ] **Step 2: Insert in template**

After `<Navbar />` (line 23), before `<OfflineBanner />` (line 26), add:
```vue
<SubNavBar />
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/layouts/AppLayout.vue
git commit -m "feat: integrate SubNavBar into AppLayout below header"
```

---

### Task 5: Wire InvestmentList.vue to subNav store

**Files:**
- Modify: `resources/js/components/NetWorth/InvestmentList.vue`

Remove the duplicate `<h2>Investments</h2>` title and the inline "Add Account" / "Upload Statement" buttons from the header. Instead, watch the subNav store for CTA actions.

- [ ] **Step 1: Remove duplicate title and inline CTAs**

Remove the `<div class="list-header">` block (lines 15-42) which contains:
- `<h2 class="list-title">Investments</h2>`
- `<button class="add-account-button">Add Account</button>`
- `<button class="upload-button">Upload Statement</button>`

Keep the Risk Profile link — move it to a suitable location within the page content if still needed.

- [ ] **Step 2: Add subNav watcher**

In the component's `setup()` or `watch` section, watch for subNav actions:

```javascript
import { watch, computed } from 'vue';
import { useStore } from 'vuex';

// In setup or created:
const store = useStore();
const subNavAction = computed(() => store.getters['subNav/pendingAction']);
const actionCounter = computed(() => store.getters['subNav/actionCounter']);

watch(actionCounter, () => {
  const action = subNavAction.value;
  if (action === 'addAccount') {
    showAccountForm.value = true; // or this.showAccountForm = true
    store.dispatch('subNav/consumeCta');
  } else if (action === 'uploadStatement') {
    showUploadModal.value = true;
    store.dispatch('subNav/consumeCta');
  }
});
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/NetWorth/InvestmentList.vue
git commit -m "refactor: remove duplicate title and inline CTAs from InvestmentList, wire to subNav store"
```

---

### Task 6: Wire CashOverview.vue to subNav store

**Files:**
- Modify: `resources/js/views/NetWorth/CashOverview.vue`

Remove inline "Add Account" buttons from each account type card header. Wire the subNav `addAccount` action to open the add account modal (default to generic type or the first available).

- [ ] **Step 1: Add subNav watcher**

Watch for `addAccount` action and open the add modal.

- [ ] **Step 2: Commit**

```bash
git add resources/js/views/NetWorth/CashOverview.vue
git commit -m "refactor: wire CashOverview CTAs to subNav store"
```

---

### Task 7: Remove duplicate titles from remaining pages

**Files:**
- Modify: `resources/js/views/Protection/ProtectionDashboard.vue` — Remove `<h1>Protection Planning</h1>` (line 7)
- Modify: `resources/js/views/Estate/EstateDashboard.vue` — Remove `<h1>Estate Planning</h1>` (line 7)
- Modify: `resources/js/views/Goals/GoalsDashboard.vue` — Remove `<h1>Goals & Life Events</h1>` (line 7)

For each file:

- [ ] **Step 1: Remove the duplicate h1/h2 title element**

The header bar already shows the page title via Navbar's `pageTitle` computed property. Remove the in-page title and any surrounding wrapper div that only existed to hold the title.

- [ ] **Step 2: Wire any inline CTAs to subNav store** (if applicable)

For GoalsDashboard: the "Add Goal" CTA is delegated to child component GoalsOverview — add a watcher for `addGoal` action.

For ProtectionDashboard: the "Add Policy" CTA is in child component CurrentSituation — add a watcher for `addPolicy` action.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/Protection/ProtectionDashboard.vue resources/js/views/Estate/EstateDashboard.vue resources/js/views/Goals/GoalsDashboard.vue
git commit -m "refactor: remove duplicate page titles, wire CTAs to subNav store"
```

---

### Task 8: Remove ValuableInfo.vue in-page tabs (moved to SubNavBar)

**Files:**
- Modify: `resources/js/views/ValuableInfo.vue`

The ValuableInfo page currently has its own tab navigation (lines 7-22). Since the sub-nav bar now provides these tabs via the config, remove the in-page tab bar. The `activeTab` reactive ref should now sync with the route query param (which the SubNavBar router-links will set).

- [ ] **Step 1: Remove the in-page tab bar template**

Remove the `<div class="flex overflow-x-auto...">` tab buttons block.

- [ ] **Step 2: Keep the route-based tab switching logic**

The existing `watch(() => route.query.section, ...)` watcher already handles tab content switching based on URL. This continues to work since SubNavBar uses `router-link` which changes the URL.

- [ ] **Step 3: Commit**

```bash
git add resources/js/views/ValuableInfo.vue
git commit -m "refactor: remove in-page tabs from ValuableInfo, now in SubNavBar"
```

---

### Task 9: Visual polish and mobile scroll

**Files:**
- Modify: `resources/js/components/SubNavBar.vue`

Ensure the tab row scrolls horizontally on mobile with no visible scrollbar.

- [ ] **Step 1: Verify horizontal scroll classes**

The template already has `overflow-x-auto scrollbar-hide` on the tabs container. The `scrollbar-hide` class is defined globally in `app.css`. Verify it works on small screens.

- [ ] **Step 2: Add touch momentum scrolling for iOS**

Add `-webkit-overflow-scrolling: touch` if not already in the global `scrollbar-hide` class (check `app.css`).

- [ ] **Step 3: Test responsive behaviour**

Resize browser to mobile width. Tabs should scroll horizontally. CTAs should remain visible (they have `flex-shrink-0`).

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/SubNavBar.vue
git commit -m "fix: ensure SubNavBar tabs scroll horizontally on mobile"
```

---

### Task 10: Final integration test

- [ ] **Step 1: Test each module section**

Navigate to each section via the side menu and verify:
- Sub-nav bar appears with correct tabs
- Active tab is highlighted (raspberry underline)
- CTAs trigger the correct modals
- No duplicate titles appear in the page content
- Dashboard page shows NO sub-nav (no config match for `/dashboard`)

- [ ] **Step 2: Test mobile**

Resize browser to mobile width:
- Tabs scroll horizontally
- CTAs remain accessible
- No layout overflow

- [ ] **Step 3: Final commit**

```bash
git add -A
git commit -m "feat: sub-navigation bar — complete integration with all module sections"
```
