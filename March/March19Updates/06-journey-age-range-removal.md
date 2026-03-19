# Journey Age Range Removal

**Date:** 19 March 2026
**Branch:** `onboardFix`

## Summary

Removed age ranges ("Ages 18–25", "Ages 23–35", etc.) from all journey/life stage cards and UI elements. Cards now display only the stage name and tagline.

## Rationale

Age ranges were prescriptive and potentially off-putting — users shouldn't feel excluded from a journey based on their age. The stage names ("Starting Out", "Building Foundations", etc.) and taglines already communicate the intent without age constraints.

## Changes

### Config (source of truth)
- `resources/js/constants/lifeStageConfig.js` — removed `ageRange` property from all 5 stage definitions, updated section comments

### Landing Page
- `resources/js/views/Public/LandingPage.vue` — removed the `<p>Ages {{ stage.ageRange }}</p>` block from stage cards

### Onboarding
- `resources/js/components/Onboarding/FocusAreaSelection.vue` — removed age range line from focus area selection cards

### Dashboard Sidebar
- `resources/js/components/SideMenu.vue` — changed `{{ stageLabel }} · Ages {{ stageAgeRange }}` to just `{{ stageLabel }}`, removed unused `stageAgeRange` computed property and its return

### Persona Selectors
- `resources/js/components/Preview/PersonaSelector.vue` — removed age range from dropdown group headers, removed `ageRange` from group data objects
- `resources/js/components/Preview/PersonaSelectionModal.vue` — removed age range from modal group headers, removed `ageRange` from group data objects

## Not Changed

- `resources/js/components/Goals/EventIconsOverlay.vue` — uses `ageRange` for timeline icon positioning (user's actual age, unrelated to journey stages)
- `resources/js/components/Retirement/DecumulationStrategyCard.vue` — shows "Ages 65-75" for retirement phases (financial planning, not journey labels)

## Before/After

**Before:**
```
Starting Out
Ages 18–25
Build smart money habits from day one
```

**After:**
```
Starting Out
Build smart money habits from day one
```
