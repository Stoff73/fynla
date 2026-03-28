# Dashboard UI Polish Batch 2 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Polish the dashboard layout, sub-navigation, progress ring, module card consistency, and add a "Suggested for You" card to the greeting hero.

**Architecture:** Seven independent UI changes across SubNavBar, AppLayout, SideMenu, DashboardCard, and Dashboard.vue. No backend changes required.

**Tech Stack:** Vue 3, Tailwind CSS, Vuex

---

## File Structure

| File | Action | Purpose |
|------|--------|---------|
| `resources/js/components/SubNavBar.vue` | Modify | Move CTAs below tabs, left-align |
| `resources/js/layouts/AppLayout.vue` | Modify | Remove max-w-7xl constraint, expand content area |
| `resources/js/components/SideMenu.vue` | Modify | Move progress ring around percentage text when collapsed |
| `resources/js/components/Dashboard/DashboardCard.vue` | Modify | Add `clickable` prop, light-blue bg for non-clickable |
| `resources/js/views/Dashboard.vue` | Modify | Pass clickable prop to Allowances card, add Suggested card to hero |
| `resources/js/components/Journey/JourneyProgressHero.vue` | Modify | Add Suggested for You card into right side |

---

### Task 1: SubNavBar — CTAs below tabs, left-aligned

**Files:**
- Modify: `resources/js/components/SubNavBar.vue`

Currently the CTAs sit to the right of the tabs in a single row (`flex items-center justify-between`). Change layout so:
- Tabs row stays on top
- CTAs appear on a second row below tabs, aligned left
- Sub-navigation is left-aligned (remove `max-w-7xl mx-auto` centering)

- [ ] **Step 1: Update template layout**

Change from single-row `flex justify-between` to stacked layout:

```vue
<template>
  <div v-if="config" class="bg-white border-b border-light-gray">
    <div class="px-4 sm:px-6 lg:px-8">
      <!-- Tabs row: horizontal scroll on mobile -->
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

      <!-- CTAs row: below tabs, left-aligned -->
      <div v-if="config.ctas.length" class="flex items-center gap-2 py-2">
        <button
          v-for="cta in config.ctas"
          :key="cta.action"
          @click="handleCta(cta.action)"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-semibold transition-colors whitespace-nowrap"
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
</template>
```

Key changes:
- Outer wrapper: removed `max-w-7xl mx-auto` → left-aligned
- Removed `flex items-center justify-between` wrapper that put tabs and CTAs on same row
- Tabs in their own `div`
- CTAs in a new `div` with `py-2`, left-aligned by default

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/SubNavBar.vue
git commit -m "refactor: move SubNavBar CTAs below tabs, left-align layout"
```

---

### Task 2: Expand content area to span screen on larger sizes

**Files:**
- Modify: `resources/js/layouts/AppLayout.vue`
- Modify: `resources/js/components/SubNavBar.vue` (already done in Task 1 — verify)

The main content area is currently constrained to `max-w-7xl` (1280px) and centred. Remove the max-width constraint so content expands to fill the available width on larger screens.

- [ ] **Step 1: Remove max-w-7xl from main content wrapper**

In `AppLayout.vue`, line 37-39, change:
```html
<div class="max-w-7xl mx-auto py-2 sm:py-3 px-4 sm:px-6 lg:px-8">
```
To:
```html
<div class="py-2 sm:py-3 px-4 sm:px-6 lg:px-8">
```

This removes the 1280px cap and the centring. Content will now flow to fill the available width (minus the side menu margin and any docked chat panel).

**Note:** Individual page components that have their own `max-w-7xl mx-auto` wrappers (like ProtectionDashboard, EstateDashboard, etc.) will still constrain themselves — this is fine; they can be updated individually later if needed.

- [ ] **Step 2: Also remove max-w-7xl from SubNavBar**

Verify that Task 1 already removed `max-w-7xl mx-auto` from SubNavBar. If not, do it now.

- [ ] **Step 3: Commit**

```bash
git add resources/js/layouts/AppLayout.vue
git commit -m "refactor: remove max-w-7xl cap from AppLayout content area"
```

---

### Task 3: Update SideMenu progress ring — ring around percentage when collapsed

**Files:**
- Modify: `resources/js/components/SideMenu.vue`

Currently when collapsed, the progress ring wraps around the favicon logo image (lines 32-42). Change it so the ring wraps around the percentage number instead — no favicon inside the ring.

- [ ] **Step 1: Update the collapsed progress ring block**

Replace lines 32-42 (the collapsed ring with favicon) and lines 67-69 (the separate percentage below):

Current (lines 32-42):
```html
<div v-if="effectiveCollapsed && currentStage" class="relative" :title="`Journey: ${progressPercentage}% complete`">
  <svg viewBox="0 0 40 40" class="w-10 h-10 -rotate-90">
    <circle cx="20" cy="20" r="17" fill="none" stroke-width="3" class="stroke-light-gray" />
    <circle cx="20" cy="20" r="17" fill="none" stroke-width="3"
      :class="progressRingColourClass"
      :stroke-dasharray="106.8"
      :stroke-dashoffset="106.8 - (106.8 * progressPercentage / 100)"
      stroke-linecap="round" />
  </svg>
  <img :src="faviconUrl" alt="Fynla" class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-5 w-5" />
</div>
```

Replace with:
```html
<div v-if="effectiveCollapsed && currentStage" class="relative" :title="`Journey: ${progressPercentage}% complete`">
  <svg viewBox="0 0 40 40" class="w-10 h-10 -rotate-90">
    <circle cx="20" cy="20" r="17" fill="none" stroke-width="3" class="stroke-light-gray" />
    <circle cx="20" cy="20" r="17" fill="none" stroke-width="3"
      :class="progressRingColourClass"
      :stroke-dasharray="106.8"
      :stroke-dashoffset="106.8 - (106.8 * progressPercentage / 100)"
      stroke-linecap="round" />
  </svg>
  <div class="absolute inset-0 flex items-center justify-center">
    <span class="text-[9px] font-bold text-raspberry-500">{{ progressPercentage }}%</span>
  </div>
</div>
```

Key change: replaced the favicon `<img>` with the percentage text centred inside the ring.

- [ ] **Step 2: Remove the separate percentage display below the ring**

Remove lines 66-69:
```html
<!-- Collapsed: tiny progress percentage below ring -->
<div v-if="effectiveCollapsed && currentStage" class="text-center flex-shrink-0 -mt-1 mb-1">
  <span class="text-[9px] font-bold text-raspberry-500">{{ progressPercentage }}%</span>
</div>
```

The percentage is now inside the ring, so this separate display is redundant.

- [ ] **Step 3: Show favicon when collapsed without a stage**

The existing line 44 already handles this:
```html
<img v-else-if="effectiveCollapsed" :src="faviconUrl" alt="Fynla" class="h-8 w-8" />
```

No change needed here.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/SideMenu.vue
git commit -m "refactor: show progress ring around percentage in collapsed SideMenu"
```

---

### Task 4: Non-clickable module cards get light-blue background

**Files:**
- Modify: `resources/js/components/Dashboard/DashboardCard.vue`
- Modify: `resources/js/views/Dashboard.vue`

Add a `clickable` prop to DashboardCard. When `false`, the card gets `bg-light-blue-100` background, no cursor-pointer, no hover effects. When `true` (default), it behaves as now.

- [ ] **Step 1: Add clickable prop to DashboardCard**

In `DashboardCard.vue`, add to props:
```javascript
clickable: {
  type: Boolean,
  default: true,
},
```

- [ ] **Step 2: Update template classes**

Change the root div from:
```html
<div
  class="dashboard-card bg-white rounded-lg border border-light-gray p-6 transition-all duration-200 cursor-pointer hover:bg-[#EEEEEE] hover:shadow-md hover:-translate-y-0.5"
  @click="$emit('click')"
  role="button"
  tabindex="0"
  @keypress.enter="$emit('click')"
>
```

To:
```html
<div
  class="dashboard-card rounded-lg border border-light-gray p-6 transition-all duration-200"
  :class="clickable
    ? 'bg-white cursor-pointer hover:bg-[#EEEEEE] hover:shadow-md hover:-translate-y-0.5'
    : 'bg-light-blue-100'"
  @click="clickable ? $emit('click') : null"
  :role="clickable ? 'button' : undefined"
  :tabindex="clickable ? 0 : undefined"
  @keypress.enter="clickable ? $emit('click') : null"
>
```

- [ ] **Step 3: Pass `:clickable="false"` to the Allowances card in Dashboard.vue**

Find the Allowances DashboardCard (line ~689):
```html
<DashboardCard
  v-if="isCardVisible('tax-allowances')"
  title="Allowances"
  :loading="loading.taxAllowances"
>
```

Add `:clickable="false"`:
```html
<DashboardCard
  v-if="isCardVisible('tax-allowances')"
  title="Allowances"
  :loading="loading.taxAllowances"
  :clickable="false"
>
```

- [ ] **Step 4: Remove the arrow icon for non-clickable cards**

In the card header, conditionally show the arrow:
```html
<svg v-if="clickable" class="w-4 h-4 text-neutral-400 flex-shrink-0 mt-1" ...>
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/Dashboard/DashboardCard.vue resources/js/views/Dashboard.vue
git commit -m "feat: differentiate non-clickable dashboard cards with light-blue background"
```

---

### Task 5: Make all module dashboard pages match dashboard home card styling

**Files:**
- Modify: Various module dashboard pages

The main Dashboard.vue uses DashboardCard components with consistent styling (white bg, rounded-lg, border border-light-gray, p-6, hover effects). Module-specific dashboards (Protection, Estate, Goals, etc.) use their own card-like containers that may not match.

This task audits and aligns the card styling in module dashboards. The main areas to check:

- [ ] **Step 1: Audit module dashboard card styles**

Read the following files and check their card container classes:
- `resources/js/views/Protection/ProtectionDashboard.vue` — uses `bg-white rounded-lg shadow` containers
- `resources/js/views/Estate/EstateDashboard.vue` — uses `bg-white border border-light-gray rounded-lg` containers
- `resources/js/views/Goals/GoalsDashboard.vue` — uses various card patterns
- `resources/js/views/NetWorth/CashOverview.vue` — uses `.account-card` scoped classes
- `resources/js/views/NetWorth/RetirementDashboard.vue` — check card patterns

The target styling for all card containers is:
```
bg-white rounded-lg border border-light-gray p-6
```

No shadow (matches DashboardCard pattern). Hover effects only on clickable cards.

- [ ] **Step 2: Update ProtectionDashboard card containers**

Replace `shadow` with `border border-light-gray` to match dashboard cards. Remove `shadow-sm` or `shadow` — the dashboard home cards use border only, no shadow.

- [ ] **Step 3: Update EstateDashboard card containers**

Ensure cards use `bg-white rounded-lg border border-light-gray p-6` consistently.

- [ ] **Step 4: Update remaining dashboards as needed**

Check Goals, Cash, Retirement dashboards and align any card-like containers.

- [ ] **Step 5: Commit**

```bash
git add resources/js/views/
git commit -m "refactor: align module dashboard card styling with dashboard home cards"
```

---

### Task 6: Add "Suggested for You" card into the JourneyProgressHero

**Files:**
- Modify: `resources/js/components/Journey/JourneyProgressHero.vue`
- Modify: `resources/js/views/Dashboard.vue` (pass suggestions data as prop)

Move the "Suggested for You" content into the right side of the "Good afternoon" hero box. Currently the hero has: progress ring (left) + greeting text (centre) + Continue Journey button (right). The Suggested for You card currently sits as a separate card in the dashboard grid (lines 876-902 of Dashboard.vue).

- [ ] **Step 1: Add suggestedGoals prop to JourneyProgressHero**

```javascript
props: {
  suggestedGoals: {
    type: Array,
    default: () => [],
  },
},
```

- [ ] **Step 2: Add Suggested card into the expanded hero layout**

After the Continue Journey button (line 68), add a right-side panel inside the hero:

```html
<!-- Right: Suggested for You (desktop only) -->
<div v-if="suggestedGoals.length && !heroCollapsed" class="hidden lg:block flex-shrink-0 w-64 border-l border-white/30 pl-5 ml-2">
  <h4 class="text-sm font-semibold text-horizon-500 mb-2">Suggested for You</h4>
  <div class="space-y-1.5">
    <div
      v-for="suggestion in suggestedGoals.slice(0, 3)"
      :key="suggestion.id"
      class="flex items-center gap-2 p-2 rounded-lg cursor-pointer hover:bg-white/40 transition-colors"
      @click="$emit('suggested-goal', suggestion)"
    >
      <div class="w-5 h-5 rounded-full bg-violet-100 flex items-center justify-center flex-shrink-0">
        <svg class="w-3 h-3 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
        </svg>
      </div>
      <span class="text-xs font-medium text-horizon-500 truncate">{{ suggestion.label }}</span>
    </div>
  </div>
</div>
```

- [ ] **Step 3: Add `suggested-goal` emit**

Add to emits: `emits: ['suggested-goal']`

- [ ] **Step 4: Pass suggestedGoals from Dashboard.vue**

In Dashboard.vue, change the JourneyProgressHero usage (line 105):
```html
<JourneyProgressHero
  v-if="currentStage"
  class="mb-3"
  :suggested-goals="stageSuggestedGoals"
  @suggested-goal="handleSuggestedGoal"
/>
```

- [ ] **Step 5: Optionally hide the standalone Suggested card from the grid**

The standalone "Suggested for You" card (Dashboard.vue lines 876-902) can remain as a fallback for mobile (where the hero's suggested panel is hidden with `hidden lg:block`). Or it can be kept as-is if the user wants both.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/Journey/JourneyProgressHero.vue resources/js/views/Dashboard.vue
git commit -m "feat: add Suggested for You panel into JourneyProgressHero"
```

---

### Task 7: Final build verification

- [ ] **Step 1: Run Vite build**

```bash
npx vite build
```

Verify no compilation errors.

- [ ] **Step 2: Visual check on localhost:8000**

Navigate through dashboard and module pages. Verify:
- SubNavBar: tabs on first row, CTAs on second row below, left-aligned
- Content expands to full width on wide screens
- SideMenu collapsed: progress ring wraps percentage number, not favicon
- Allowances card: light-blue-100 background, no hover effect, no arrow icon
- Module dashboards: card styling consistent with dashboard home
- JourneyProgressHero: Suggested for You panel on right side (desktop)

- [ ] **Step 3: Final commit if needed**

```bash
git add -A
git commit -m "fix: final polish for dashboard UI batch 2"
```
