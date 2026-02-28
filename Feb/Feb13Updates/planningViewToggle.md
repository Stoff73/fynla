# Estate Planning View Toggle

## Context

The dashboard currently only shows an "information" view -- the user's current financial state. Users need a way to see what their estate position would look like if they followed the system's recommendations (gifting strategies, trust creation, life cover, etc.). This helps users understand the value of planning and motivates action.

Starting with the Estate module, we'll add a "Current / Planning" toggle to:
1. The main dashboard (affects the Estate card content)
2. The Estate detail view (replaces IHT tab content with a comparison view)

The backend already has everything we need -- `ComprehensiveEstatePlanService::generateComprehensiveEstatePlan()` returns an `optimized_recommendation` with a `summary` containing `current_iht_liability`, `total_iht_saving`, `remaining_liability`, `effectiveness_percentage`, and prioritised recommendation actions.

---

## Files to Modify

| File | Change |
|------|--------|
| `resources/js/store/modules/estate.js` | Add `planningData` state, getter, action, mutations |
| `resources/js/views/Dashboard.vue` | Add toggle + conditional estate card content |
| `resources/js/views/Estate/EstateDashboard.vue` | Add toggle, pass `viewMode` to IHTPlanning |
| `resources/js/components/Estate/IHTPlanning.vue` | Accept `viewMode` prop, show comparison when planning |
| `resources/js/components/Estate/EstatePlanningComparison.vue` | **New** -- side-by-side comparison component |

No backend changes required.

---

## Implementation Steps

### Step 1: Vuex Store -- Add Planning Data

**File:** `resources/js/store/modules/estate.js`

Add to state:
- `planningData: null` -- stores the comprehensive plan response
- `planningLoading: false`

Add getter `optimisedIHTLiability` that reads from `state.planningData.optimized_recommendation.summary`:
```js
{
  currentLiability: summary.current_iht_liability,
  totalSaving: summary.total_iht_saving,
  remainingLiability: summary.remaining_liability,
  effectivenessPercentage: summary.effectiveness_percentage,
  annualCosts: summary.annual_costs,
  netBenefit: summary.net_benefit,
}
```

Also expose `planningRecommendations` getter returning `state.planningData.optimized_recommendation.recommendations` (the prioritised action list).

Add action `fetchPlanningData` that calls existing `estateService.getComprehensiveEstatePlan()` and commits result.

Add mutations `setPlanningData` and `setPlanningLoading`.

### Step 2: Dashboard Toggle

**File:** `resources/js/views/Dashboard.vue`

Add `viewMode: 'current'` to `data()`.

Insert a segmented toggle control between the MFA banner and the card grid:
- Two buttons: "Current" (default active) and "Planning"
- Active: `bg-primary-600 text-white`, Inactive: `text-gray-600 hover:text-gray-900`
- Wrapped in `inline-flex rounded-lg border border-gray-200 bg-white p-1`

Add computed `isPlanningMode` and `optimisedEstateData` (from store getter).

Lazy-load planning data: in `setViewMode('planning')`, dispatch `estate/fetchPlanningData` if `planningData` is null.

### Step 3: Estate Card -- Planning Mode Content

**File:** `resources/js/views/Dashboard.vue` (Estate Planning card section, lines 108-152)

Wrap existing estate card content in `v-if="!isPlanningMode"`.

Add `v-else` block for planning mode showing:
- **Two-column comparison** (`grid grid-cols-2 gap-4`):
  - Left ("Current"): IHT Liability + Taxable Estate values
  - Right ("Planning"): Remaining IHT Liability + Strategies Applied count
- **Savings summary bar** (green-50 bg): estimated IHT savings amount + effectiveness percentage
- Fallback "No plan data available" text if `optimisedEstateData` is null

On mobile, the two-column grid stacks naturally (`grid-cols-1 sm:grid-cols-2`).

### Step 4: Estate Detail View Toggle

**File:** `resources/js/views/Estate/EstateDashboard.vue`

Add `viewMode: 'current'` to `data()`.

Insert the same segmented toggle in the header area (after the description text, before the loading/error states).

Pass `viewMode` as a prop to `IHTPlanning`:
```html
<IHTPlanning v-if="activeTab === 'iht'" :view-mode="viewMode" ... />
```

### Step 5: IHTPlanning -- Accept viewMode Prop

**File:** `resources/js/components/Estate/IHTPlanning.vue`

Add prop: `viewMode: { type: String, default: 'current' }`

Add computed: `isPlanningMode() { return this.viewMode === 'planning'; }`

At the top of the main content area, add:
```html
<EstatePlanningComparison v-if="isPlanningMode" />
<template v-else>
  <!-- existing IHT planning content unchanged -->
</template>
```

### Step 6: New Component -- EstatePlanningComparison

**New file:** `resources/js/components/Estate/EstatePlanningComparison.vue`

Fetches planning data from the Vuex store (dispatches `fetchPlanningData` on mount if not loaded).

**Layout:**
1. **Comparison header cards** (3-column grid, same pattern as existing IHT summary cards):
   - Card 1 (blue border): "Current IHT Position" -- current IHT liability, taxable estate
   - Card 2 (green border): "Planned IHT Position" -- remaining liability after strategies, total savings
   - Card 3 (primary border): "Planning Effectiveness" -- effectiveness %, annual costs, net benefit

2. **Strategy breakdown** -- list of recommended actions grouped by priority:
   - Each group has a priority label (e.g., "Immediate Actions (Year 1)")
   - Each action shows: action name, details, IHT saving amount, timeframe
   - Uses accordion pattern consistent with `IHTMitigationStrategies.vue`

3. **Implementation timeline** (from `planningData.implementation_timeline`)

Uses `currencyMixin` for all formatting. British spelling for labels.

---

## Key Reuse Points

| What | Where | How |
|------|-------|-----|
| Currency formatting | `resources/js/mixins/currencyMixin.js` | `mixins: [currencyMixin]` |
| Estate service API | `resources/js/services/estateService.js:250` | `getComprehensiveEstatePlan()` already exists |
| Comprehensive plan backend | `app/Services/Estate/ComprehensiveEstatePlanService.php` | No changes needed |
| Card styling | `resources/js/components/Dashboard/DashboardCard.vue` | Slot-based, no changes needed |
| Summary card pattern | `resources/js/components/Estate/IHTPlanning.vue:60-91` | Reuse the 3-column bordered card layout |
| Strategy accordion pattern | `resources/js/components/Estate/IHTMitigationStrategies.vue` | Reuse expand/collapse pattern |

---

## Verification

1. **Start dev server:** `./dev.sh`
2. **Login** as `chris@fynla.org` / `Password1!` (ask user for verification code)
3. **Main dashboard:**
   - Toggle should appear above the card grid
   - Default view is "Current" showing existing card content
   - Switch to "Planning" -- Estate card shows comparison with savings
   - Other module cards remain unchanged in both modes
4. **Estate detail view** (`/estate`):
   - Toggle appears in header
   - "Current" shows normal IHT Planning tab content
   - "Planning" replaces IHT tab with comparison component
   - Other tabs (Gifting, Life Policy, Trust) still work normally
5. **Test with different personas** via preview mode:
   - `peak_earners` (David & Sarah Mitchell) -- should show significant IHT liability and meaningful planning savings
   - `young_saver` (John Morgan) -- likely no IHT liability, planning view should handle zero liability gracefully
   - `widow` (Margaret Thompson) -- estate planning focus, should show relevant strategies
6. **Mobile responsiveness:** comparison columns should stack on small screens
