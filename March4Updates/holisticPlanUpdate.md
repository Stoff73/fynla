# Holistic Plan Rewrite — Implementation Plan

## Context

The existing holistic plan (`HolisticPlan.vue`) uses its own `CoordinatingAgent`, `HolisticPlanner`, and dedicated Vuex store to generate a completely separate analysis. This duplicates work already done by individual module plans and produces data that doesn't match what users see in their Protection, Investment, Retirement, and Estate plans.

The goal is to replace this with a holistic view that **fetches the individual module plans** and displays them together in a unified page — no recalculation, no duplication. The only new feature is the **Priority Area**, which aggregates all actions across plans and shows the priority allocation against the user's monthly disposable income from a single shared pool.

---

## Architecture: Frontend-Orchestrated Aggregation

The holistic plan fetches all 4 individual plans from the existing `plans` Vuex store (`state.plans.protection`, `state.plans.investment`, `state.plans.retirement`, `state.plans.estate`). No new backend endpoint is needed.

**Why this works:**
- Each plan's data is already in Vuex after `fetchPlan(type)`
- Toggling actions dispatches to the same `plans/toggleAction` mutation, so cascading charts and what-if updates work identically
- When any individual plan is regenerated, the holistic view reactively updates (same Vuex state)

**Data flow:**
```
HolisticPlan.vue mounts
  → fetchPlan('protection'), fetchPlan('investment'), fetchPlan('retirement'), fetchPlan('estate') in parallel
  → plans.js store populates state.plans.{type}
  → HolisticPlanContent reads from each plan
  → Existing module components render directly (RetirementGroupedActions, etc.)
  → Toggle events bubble up → dispatch plans/toggleAction → Vuex mutation → reactive updates
```

---

## Page Structure

```
PlanPageLayout (title="Holistic Financial Plan", Print/Save button)
  HolisticPlanContent
    1. HolisticExecutiveSummary — Lists which plans are included
    2. Personal Information — Shown once (reuse InvestmentPersonalInformation or first available)
    3. Current Situation (per module):
       - Protection → ProtectionCurrentSituation (reuse)
       - Investment → Investment accounts + key indicators (new thin component)
       - Savings → Savings accounts + emergency fund + ISA (new thin component)
       - Retirement → RetirementCurrentSituation (reuse)
       - Estate → EstateCurrentSituation (reuse)
    4. Recommended Actions (per module):
       - Protection → PlanActionsList + PlanWhatIfComparison (reuse from ProtectionPlanContent)
       - Investment & Savings → InvestmentGroupedActions (reuse, handles both)
       - Retirement → RetirementGroupedActions (reuse)
       - Estate → EstateGroupedActions (reuse)
    5. Priority Area (NEW) — All actions ranked, allocated against shared disposable income
    6. Conclusion — Aggregated from all plans
```

---

## Files to Create (7 new)

### 1. `resources/js/components/Plans/Holistic/HolisticPlanContent.vue`
**The main orchestrator.** Receives all 4 plans as props. Renders each section conditionally (v-if on plan existence). Routes toggle events by plan type. Includes `computedProjectedScenario` for protection (same logic as ProtectionPlanContent).

Props: `protectionPlan`, `investmentPlan`, `retirementPlan`, `estatePlan`
Emits: `toggle-action`, `update-funding-source` (with planKey attached)

### 2. `resources/js/components/Plans/Holistic/HolisticExecutiveSummary.vue`
Lists which plans are included. Structured card with greeting, introduction listing plan names, and brief overview. Uses `PlanSectionHeader` with title "Executive Summary".

Props: `availablePlans` (array of plan type strings), `personalInfo` (from any plan)

### 3. `resources/js/components/Plans/Holistic/HolisticModuleSection.vue`
Reusable wrapper that labels a section with the module name. Uses `PlanSectionHeader` internally.

Props: `title`, `subtitle`, `color`
Slot: default content

### 4. `resources/js/components/Plans/Holistic/HolisticInvestmentSituation.vue`
Thin component showing only `situation.investment_accounts` + total investment value + asset allocation/fee/diversification indicators from the investment plan's current_situation.

Props: `situation` (investment plan's current_situation object)

### 5. `resources/js/components/Plans/Holistic/HolisticSavingsSituation.vue`
Thin component showing only `situation.savings_accounts` + total savings value + emergency fund + ISA allowance indicators.

Props: `situation` (investment plan's current_situation object)

### 6. `resources/js/components/Plans/Holistic/HolisticPriorityArea.vue`
**The only new feature.** Aggregates actions from all plans into a single prioritised list allocated against the user's monthly disposable income (single shared pool — unlike individual plans which each get the full amount).

Logic:
1. Collect all actions from all 4 plans, tag each with source module
2. Sort: goals first (source === 'goal'), then tax optimisation actions, then by priority (critical > high > medium > low)
3. For each action, show: title, source module badge, priority badge, estimated monthly cost (`cascade_params.additional_monthly` or `impact_parameters.monthly_premium_estimate` or `estimated_impact / 12`)
4. Running allocation bar: show cumulative cost vs monthly disposable income
5. When budget exceeded, remaining actions shown greyed out with "Exceeds available income" indicator

Props: `allActions` (merged + tagged), `monthlyDisposableIncome`

### 7. `resources/js/components/Plans/Holistic/HolisticConclusion.vue`
Aggregates conclusion data from all available plans into a single unified conclusion. Lists essential actions (priority 1-2) and optional actions (priority 3+) across all modules with module labels.

Props: `conclusions` (object keyed by plan type), `allActions` (merged)

---

## Files to Rewrite (2)

### 8. `resources/js/views/HolisticPlan.vue`
Complete rewrite. Uses `PlanPageLayout` wrapper. Fetches all 4 plans via `plans/fetchPlan` in `Promise.allSettled` (so one failure doesn't block others). Local `holisticLoading` flag (not the shared store loading). Passes plans to `HolisticPlanContent`. Routes toggle events to `plans/toggleAction` with correct planKey. Uses `planPrintMixin` for print.

### 9. `resources/js/components/Plans/Shared/planPrintMixin.js`
Add `printHolisticPlan(plans)` method and `buildHolisticPlanHtml(plans)` that:
- Builds a single HTML document with all sections
- Calls existing per-type builder methods (`buildProtectionCurrentSituationHtml`, `buildRetirementCurrentSituationHtml`, etc.) in sequence, each wrapped in a module header
- Adds holistic executive summary (plan list)
- Adds personal info once
- Adds Priority Area table
- Adds aggregated conclusion
- Reuses all existing chart/table rendering (cascading line charts, bar charts, IHT tables, etc.)

---

## Files to Modify (2)

### 10. `resources/js/store/index.js`
Remove `holistic` module import and registration.

### 11. `resources/js/router/index.js`
Update holistic plan route breadcrumb (path stays `/holistic-plan`).

---

## Files to Delete (11)

| File | Reason |
|------|--------|
| `resources/js/store/modules/holistic.js` | Replaced by plans store |
| `resources/js/services/holisticService.js` | No longer calls holistic API |
| `resources/js/components/Holistic/ExecutiveSummary.vue` | Replaced |
| `resources/js/components/Holistic/FinancialSnapshot.vue` | Replaced |
| `resources/js/components/Holistic/ModuleSummaries.vue` | Replaced |
| `resources/js/components/Holistic/PrioritizedRecommendations.vue` | Replaced |
| `resources/js/components/Holistic/CashFlowAllocationChart.vue` | Replaced |
| `resources/js/components/Holistic/NetWorthProjectionChart.vue` | Replaced |
| `resources/js/components/Holistic/RiskAssessment.vue` | Replaced |
| `resources/js/components/Holistic/EstateSummarySection.vue` | Replaced |
| `resources/js/components/Holistic/GoalsSummarySection.vue` | Replaced |

---

## Files Reused As-Is (no modification)

| Component | Reused For |
|-----------|-----------|
| `PlanPageLayout.vue` | Page wrapper with print button |
| `PlanSectionHeader.vue` | Module section headers |
| `PlanActionCard.vue` | Individual action cards with toggles |
| `PlanActionsList.vue` | Protection flat action list |
| `PlanWhatIfComparison.vue` | Protection what-if container |
| `PlanGoalSection.vue` | Goal cards (if needed per module) |
| `PlanConclusion.vue` | Conclusion formatting |
| `ProtectionCurrentSituation.vue` | Protection current situation |
| `ProtectionWhatIfControls.vue` | Protection what-if metrics |
| `RetirementCurrentSituation.vue` | Retirement current situation |
| `RetirementGroupedActions.vue` | Retirement actions + cascading charts |
| `InvestmentGroupedActions.vue` | Investment & Savings actions + cascading charts |
| `EstateCurrentSituation.vue` | Estate IHT calculation table |
| `EstateGroupedActions.vue` | Estate actions + gifting schedules |
| `InvestmentPersonalInformation.vue` | Personal info (shown once) |
| `CascadingActionChart.vue` | Per-action projection charts |
| `plans.js` (Vuex store) | All plan data, toggles, recalculation |
| `plansService.js` | API calls (unchanged) |

---

## Reactivity: How Toggles Work in Holistic View

The exact same mechanism as individual plans:

1. User clicks toggle on action card → PlanActionCard emits `toggle`
2. Bubbles through module actions component (e.g., RetirementGroupedActions) → emits `toggle`
3. HolisticPlanContent catches it, attaches planKey → emits `toggle-action` with `{ planKey, actionId }`
4. HolisticPlan.vue dispatches `plans/toggleAction({ planKey, actionId })`
5. Vuex mutation updates `state.plans[planKey].actions[i].enabled`
6. All components reading from that plan data reactively update (charts, what-if, etc.)

**Cascading charts work identically** — they read from `plan.actions` and `plan.what_if` which are in Vuex.

**Protection reactive what-if** — `computedProjectedScenario` computed property replicated in HolisticPlanContent (same logic as ProtectionPlanContent lines 83-137).

---

## Priority Area Detail

**Disposable income context:**
- Individual plans each create their own `DistributionAccount` with the FULL monthly disposable income (documented in `March1Update/updatePlans.md`)
- In the holistic view, the Priority Area uses a SINGLE shared pool — so actions that individually fit within budget may exceed it when combined across plans
- This is a **display/prioritisation feature**, not a recalculation — individual plan sections still show their own recommendations as-is

**Sort order:**
1. Goal-sourced actions (any plan) — ordered by goal priority
2. Tax optimisation actions (ISA maximisation, pension allowance, etc.)
3. All other actions by priority rank (critical > high > medium > low)

**Monthly cost per action (from existing data):**
- Retirement/Investment contribution actions: `cascade_params.additional_monthly`
- Protection premium actions: `impact_parameters.premium` or `impact_parameters.monthly_premium_estimate`
- Estate actions with affordability: `affordability.monthly_premium_estimate`
- Fallback: `estimated_impact / 12` if annual

---

## Print/Save Support

Add to `planPrintMixin.js`:

```
printHolisticPlan(plans)
  → buildHolisticPlanHtml(plans)
     → Cover page: "Holistic Financial Plan"
     → buildStructuredExecutiveSummaryHtml() — plan list version
     → buildPersonalInformationHtml() — once, from first available plan
     → For each available plan type:
        → Module header ("Protection", "Investment", etc.)
        → buildCurrentSituationByType() — existing per-type methods
     → For each available plan type:
        → Module header
        → buildActionsByType() — existing per-type methods
     → buildHolisticPriorityAreaHtml() — NEW: priority table with allocation bar
     → buildConclusionHtml() — aggregated
```

All existing chart rendering (SVG line charts, bar charts, IHT tables) is already in the mixin and will be called as-is.

---

## Verification

1. **Seed database**: `php artisan db:seed`
2. **Start dev server**: `./dev.sh`
3. **Login as preview persona** (peak_earners via landing page selector)
4. **Navigate to /holistic-plan**
5. **Verify sections**: Executive summary lists plan names, personal info shown once, all 5 current situation sections render, all 4 action sections render with working toggle switches
6. **Toggle test**: Toggle an action in Retirement section → cascading chart updates. Toggle a Protection action → what-if bar chart updates
7. **Priority Area**: All actions listed, sorted goals-first, running total shows disposable income allocation
8. **Print/Save**: Click Print/Save PDF → all sections render in print output with charts, tables, badges
9. **Cross-persona**: Test with widow (no investment plan), entrepreneur (single), young_saver (no estate)
10. **Run tests**: `./vendor/bin/pest` — no regressions
11. **Seed again**: `php artisan db:seed`
