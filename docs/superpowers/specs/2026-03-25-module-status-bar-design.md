# Module Status Bar & Dashboard Polish — Design Spec

**Date:** 2026-03-25
**Status:** Approved
**Scope:** 6 changes to dashboard module pages, gradients, and side menu behaviour

---

## 1. Module Status Bar Component

### Overview

A new `ModuleStatusBar.vue` component placed at the top of every module page (below SubNavBar, above content). Shows page completeness and the info guide checklist data inline, replacing the need to open the floating "?" panel.

**Not shown on:** Actions page, Dashboard (which has its own JourneyProgressHero), Capacitor mobile app (uses MobileLayout, not AppLayout).

### Minimised State (default)

- White background, 1px light-grey border, 8px border-radius
- **Left:** 28px SVG progress ring showing `completionPercentage` from infoGuide store + text "X of Y items complete" (filled count / total count)
- **Right:** "What powers this view" label + chevron-down icon
- Click anywhere on the bar to expand
- Progress ring colour: spring-500 (green) at >= 80%, violet-500 at >= 50%, raspberry-500 at < 50% (matches InfoGuidePanel three-tier scheme)
- **Loading state:** While `infoGuide.loading` is true, show a skeleton pulse (same height as the bar) to prevent stale data flash

### Expanded State

- Same header row, chevron flips to up
- Below header: two-column grid (single column on mobile) of checklist items from `infoGuide.allRequirements`
- **Filled items:** Green check circle icon + label text (neutral colour)
- **Missing items:** Violet info circle icon + label as a `<router-link>` using the requirement's `link` field
- Smooth expand/collapse transition (CSS `max-height` or similar)
- Expand/collapse button uses `aria-expanded` attribute for accessibility
- Progress ring SVG includes `role="img"` and `aria-label="X% complete"`

### Data Source

- `infoGuide` Vuex store — `fetchRequirements(module)` on mount
- **Module name mapping:** The existing `moduleMap` in `InfoGuidePanel.vue` is incomplete. The implementation must extend the route-to-module mapping to cover all pages that receive the status bar. Missing mappings include: `/risk-profile`, `/planning/journeys`, `/planning/what-if`, `/goals`, `/actions`, `/plans`, `/settings`, `/help`, `/valuable-info`. The extended map should be extracted into a shared utility (e.g. `utils/moduleMap.js`) used by both `InfoGuidePanel` and `ModuleStatusBar`.
- Getters used: `allRequirements`, `filledItems`, `missingItems`, `completionPercentage`
- The `completeness` Vuex store is NOT used by this component — only `infoGuide`

### Placement

Two categories of module pages need different placement strategies:

**View-level pages** (~21 files: ProtectionDashboard, SavingsDashboard, GoalsDashboard, EstateDashboard, CashOverview, PlansDashboard, PlanningJourneys, WhatIf pages, Risk pages, WillBuilderView, LpaWizardView, PowerOfAttorneyView, Settings, UserProfile, Help, ValuableInfo):
- Add `<ModuleStatusBar />` inside the existing `module-gradient` wrapper div, as the first child before other content

**Component-level pages** (~7 files: InvestmentList, PensionList, PropertyList, LiabilitiesList, ChattelsList, BusinessInterestsList, NetWorthWealthSummary):
- These components have `module-gradient` on their own root div, not in a parent view
- Add `<ModuleStatusBar />` as the first child inside the component's `module-gradient` wrapper, before existing content

### Persistence

- Collapse/expand state in localStorage key `moduleStatusBarCollapsed`
- Shared across all modules (collapse on Protection = collapsed on Savings)
- Default: collapsed (minimised)

---

## 2. Gradient Colour Change: Blue → Light Grey

### Current State

`.module-gradient` in `app.css` uses `theme('colors.light-blue.100')` for the bottom fade.

### Change

Replace the gradient colour with `theme('colors.light-gray')` (the existing light-gray token). Same 120px height, same `::after` pseudo-element technique, same `pointer-events: none`.

This affects all pages using `module-gradient`, including CashOverview (intentional — the page gradient changes, while the Open Banking card within it is unaffected per Section 3).

### CSS Change

```css
/* Before */
background: linear-gradient(to top, theme('colors.light-blue.100'), transparent);

/* After */
background: linear-gradient(to top, theme('colors.light-gray'), transparent);
```

### Dashboard Cards Unchanged

`.module-gradient-pink` on `DashboardCard.vue` stays light-pink — no change.

---

## 3. Remove Gradient from Open Banking "Coming Soon" Section

### Current State

The Open Banking card in `CashOverview.vue` (lines 245-288) sits inside the page container that has `module-gradient`. It inherits the page gradient.

### Change

The Open Banking card itself is a distinct `div` with `bg-light-blue-100`. No gradient-specific change is needed on it — it doesn't have its own `module-gradient` class. The page gradient sits on the parent container and won't visually overlap with the Open Banking card because the card has its own solid background.

If the card's background becomes transparent in future, add a `no-gradient` utility class that sets `position: relative; z-index: 1` to sit above the `::after` pseudo-element, or wrap coming-soon sections in a container without the gradient class.

**Pattern for future "coming soon" modules:** Any section marked as coming soon should have its own solid background colour (not transparent), which naturally sits above the gradient pseudo-element.

---

## 4. Side Menu Auto-Expand on Collapsed Navigation

### Current Behaviour

When the side menu is collapsed and user clicks an icon:
- Menu stays collapsed
- Page navigates
- The correct section does NOT auto-expand
- User must manually expand menu, then expand section

### New Behaviour

When the side menu is **collapsed** and a navigation occurs to a route that belongs to a section:

1. **Expand the menu** — set `sideMenuCollapsed = false`, save to localStorage
2. **Expand the relevant section** — use the existing `activeSectionKey` computed to determine which section the new route belongs to, then add that key to `expandedSections` and save to localStorage

### Implementation Approach

**Challenge:** AppLayout (and therefore SideMenu) remounts on every route change. A `$route` watcher on the new SideMenu instance cannot detect what the collapsed state was *before* navigation, because the old instance is already destroyed.

**Solution:** Use a localStorage flag pattern:
1. In SideMenu's click handlers for navigation links: before navigating, if `collapsed` is true, write a `sideMenuPendingExpand` flag to localStorage with the target route path
2. In SideMenu's `mounted()` hook: check for `sideMenuPendingExpand` flag. If present, clear the flag, expand the menu (emit `toggle` to parent), and expand the section matching `activeSectionKey`
3. This survives the remount because localStorage persists across component lifecycles

### Persistence

Both changes are permanent (saved to localStorage):
- Menu expanded state: `sideMenuCollapsed` key
- Section expanded state: `sideMenuExpandedSections` key

User can still manually collapse the menu or sections at any time.

---

## 5. Files Changed Summary

| File | Change |
|------|--------|
| `resources/js/components/Shared/ModuleStatusBar.vue` | **New** — status bar component |
| `resources/js/utils/moduleMap.js` | **New** — shared route-to-module mapping (extracted from InfoGuidePanel) |
| `resources/css/app.css` | `.module-gradient` gradient colour blue → grey |
| `resources/js/components/SideMenu.vue` | Pending-expand flag on click + mount check for auto-expand |
| `resources/js/components/Shared/InfoGuidePanel.vue` | Refactor to use shared `moduleMap.js` instead of local map |
| ~21 view-level `.vue` files | Add `<ModuleStatusBar />` import and placement |
| ~7 component-level `.vue` files | Add `<ModuleStatusBar />` import and placement |

### No Changes Needed

| File | Reason |
|------|--------|
| `DashboardCard.vue` | Pink gradient unchanged |
| `AppLayout.vue` | Side menu expand already handled via `toggleSideMenu` method + event |
| `InfoGuidePanel.vue` | Remains available — not removed, coexists with status bar (refactored to share moduleMap only) |

---

## 6. Design Decisions

1. **Status bar coexists with InfoGuidePanel** — the floating "?" panel is not removed. Some users may prefer it. The status bar provides the same data inline.
2. **Shared collapse state across modules** — simpler than per-module persistence. If user wants it collapsed, they want it collapsed everywhere.
3. **Progress ring colour thresholds** — three-tier scheme matching InfoGuidePanel: spring >= 80%, violet >= 50%, raspberry < 50%. Consistent visual language across the app.
4. **Side menu expand is permanent** — respects the principle that if the user navigated while collapsed, they likely want to see the menu context. They can always re-collapse manually.
5. **Grey gradient** — neutral, doesn't compete with card content or status bar. Light-gray token already exists in the design system.
6. **Shared moduleMap utility** — extracted to avoid duplication between InfoGuidePanel and ModuleStatusBar. Single source of truth for route-to-module mapping.
7. **localStorage flag for auto-expand** — works around the AppLayout remount lifecycle. The flag is written before navigation and consumed on the next mount.
