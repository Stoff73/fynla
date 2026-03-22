# Code Review Report — 22 March 2026

**Branch:** `sidebarRevert` | **Commits:** 5 | **Reviewer:** Automated

## Changes Reviewed

1. Sidebar revert — removed journey-based filtering from SideMenu.vue
2. Info guide link fixes — corrected navigation links in ModuleDataRequirementsService.php
3. Removed suggested goals card from Dashboard.vue and GoalsCard.vue
4. Deploy guide updates

## Verdict

No critical issues. No regressions. No security vulnerabilities. The changes accomplish their stated goals correctly. The remaining work is dead code cleanup from the removals.

## Issues Found

### 1. Dead Code in SideMenu.vue (Important)

Template was cleaned up but the script retains ~90 lines of filtering infrastructure no longer used:

**Dead functions/computed:**
- `isPrimaryItem`, `isSectionVisible`, `isExploreItem`, `resolvedExploreItems`, `hasExploreItems`, `isItemActive`, `isStageItemVisible`

**Dead supporting computed:**
- `allStageItems`, `primaryItemSet`, `exploreItemSet`

**Dead refs:**
- `exploreExpanded`, `stageConfig`

**Dead constant:**
- `SIDEBAR_ITEMS` (27-entry mapping object, only used by dead `resolvedExploreItems`)

**Fix:** Remove all of the above from SideMenu.vue.

### 2. Unused GoalsCard Import in Dashboard.vue (Important)

`GoalsCard` is imported and registered in `components` but never used in the template.

**Fix:** Remove the import and component registration.

### 3. Dead Vuex Getters in lifeStage.js (Important)

6 getters now have no consumers:
- `sidebarPrimary`, `sidebarExplore`, `suggestedGoals`
- `effectiveSidebarPrimary`, `effectiveSidebarExplore`, `userDataFlags`

**Fix:** Remove the dead getters.

### 4. Dead Config Data in lifeStageConfig.js (Important)

`suggestedGoals` arrays and `sidebar.primary`/`sidebar.explore` arrays in all 5 stage configs are no longer consumed.

**Fix:** Remove `suggestedGoals` from all stages. Keep `sidebar` config for now if re-enabling filtering is a possibility.

### 5. Stale Comment in Dashboard.vue (Suggestion)

Line 823 references "Suggested goals card (1 column)" which was removed.

**Fix:** Update the comment.

### 6. Pre-existing Route Mismatch in InfoGuidePanel.vue (Suggestion)

Line 230 maps `/net-worth/business-interests` but the actual route is `/net-worth/business`. The panel won't auto-detect the module on the business interests page.

**Fix:** Change to `/net-worth/business`.

## Action Plan

| # | Issue | File | Status |
|---|-------|------|--------|
| 1 | Dead code cleanup | SideMenu.vue | To fix |
| 2 | Unused import | Dashboard.vue | To fix |
| 3 | Dead getters | lifeStage.js | To fix |
| 4 | Dead config | lifeStageConfig.js | To fix (suggestedGoals only) |
| 5 | Stale comment | Dashboard.vue | To fix |
| 6 | Route mismatch | InfoGuidePanel.vue | To fix |
