# Fynla Plans Section -- Complete Detailed Report

**Generated:** 1 March 2026
**Scope:** All financial plan types -- Investment & Savings, Protection, Retirement, Estate, Goal Plans, and Holistic Plan

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Shared Architecture](#2-shared-architecture)
3. [Investment & Savings Plan](#3-investment--savings-plan)
4. [Protection Plan](#4-protection-plan)
5. [Retirement Plan](#5-retirement-plan)
6. [Estate Plan](#6-estate-plan)
7. [Goal Plan](#7-goal-plan)
8. [Holistic Plan](#8-holistic-plan)
9. [Plans Dashboard](#9-plans-dashboard)
10. [Legacy Plans](#10-legacy-plans)
11. [Complete File Index](#11-complete-file-index)
12. [Complete Database Table Map](#12-complete-database-table-map)

---

## 1. System Overview

### Two Generations of Plans

The Fynla application contains two distinct plan system generations:

**Current Plans (v2):** A unified cross-module plan framework under the `/plans/` route with shared architecture: `BasePlanService`, `PlanController`, `WhatIfCalculator`, shared Vue components, a Vuex `plans` store, and a `plansService`. Supports Investment, Protection, Retirement, Estate, and Goal plans with toggleable actions and what-if scenarios.

**Legacy Plans (v1):** Standalone comprehensive plan views at module-level routes (`/protection-plan`, `/estate-plan`, `/plans/investment-savings`). These fetch data directly from module-specific services and use `html2pdf.js` for PDF generation.

### System Architecture Flow

```
Vue View -> Vuex Store -> plansService (API) -> PlanController -> PlanService -> Agent -> Domain Services -> Models -> Database
```

### All Plan Routes

#### Frontend Routes (`resources/js/router/index.js`)

| Path | Name | Component | Auth Required |
|------|------|-----------|---------------|
| `/plans` | `Plans` | `PlansDashboard.vue` | Yes |
| `/plans/investment` | `InvestmentPlan` | `InvestmentPlan.vue` | Yes |
| `/plans/investment-savings` | (redirect) | Redirects to `/plans/investment` | Yes |
| `/plans/protection` | `ProtectionPlan` | `ProtectionPlan.vue` | Yes |
| `/plans/retirement` | `RetirementPlan` | `RetirementPlan.vue` | Yes |
| `/plans/estate` | `EstatePlan` | `EstatePlan.vue` | Yes |
| `/plans/goal/:goalId` | `GoalPlan` | `GoalPlan.vue` | Yes |
| `/protection-plan` | `ComprehensiveProtectionPlan` | `ComprehensiveProtectionPlan.vue` | Yes |
| `/estate-plan` | `ComprehensiveEstatePlan` | `ComprehensiveEstatePlan.vue` | Yes |
| `/holistic-plan` | `HolisticPlan` | `HolisticPlan.vue` | Yes |

#### Backend API Routes (`routes/api.php`)

| Method | Endpoint | Controller Method | Purpose |
|--------|----------|-------------------|---------|
| GET | `/api/plans/statuses` | `PlanController::statuses` | Dashboard readiness check |
| GET | `/api/plans/{type}` | `PlanController::generate` | Generate plan (investment/protection/retirement/estate) |
| POST | `/api/plans/{type}/recalculate` | `PlanController::recalculate` | What-if recalculation with toggled actions |
| DELETE | `/api/plans/{type}/clear-cache` | `PlanController::clearCache` | Clear plan cache |
| GET | `/api/plans/goal/{goalId}` | `PlanController::generateGoalPlan` | Generate goal-specific plan |
| POST | `/api/plans/goal/{goalId}/recalculate` | `PlanController::recalculateGoalPlan` | Recalculate goal plan |
| GET | `/api/plans/investment-savings` | `InvestmentSavingsPlanController::generate` | Legacy combined plan |
| DELETE | `/api/plans/investment-savings/clear-cache` | `InvestmentSavingsPlanController::clearCache` | Legacy cache clear |
| POST | `/api/investment/plan/generate` | `InvestmentPlanController::generatePlan` | Legacy DB-persisted plan |
| GET | `/api/investment/plan` | `InvestmentPlanController::getLatestPlan` | Legacy get latest |
| GET | `/api/investment/plan/all` | `InvestmentPlanController::getAllPlans` | Legacy get all |
| GET | `/api/investment/plan/{id}` | `InvestmentPlanController::getPlanById` | Legacy get by ID |
| DELETE | `/api/investment/plan/{id}` | `InvestmentPlanController::deletePlan` | Legacy delete |
| GET | `/api/protection/comprehensive-plan` | `ProtectionPlanController::comprehensivePlan` | Legacy protection plan |
| GET | `/api/estate/comprehensive-plan` | `EstatePlanController::comprehensivePlan` | Legacy estate plan |
| POST | `/api/holistic/analyze` | `HolisticPlanningController::analyze` | Holistic analysis |
| POST | `/api/holistic/plan` | `HolisticPlanningController::plan` | Holistic plan |
| GET | `/api/holistic/recommendations` | `HolisticPlanningController::recommendations` | Holistic recommendations |

---

## 2. Shared Architecture

### 2.1 Standardised Plan Data Structure

Every current (v2) plan returns this structure from the API:

```json
{
  "metadata": {
    "plan_type": "string",
    "generated_at": "ISO 8601 timestamp",
    "user_name": "string",
    "user_id": "integer",
    "data_completeness": {
      "percentage": "integer (0-100)",
      "missing": ["array of missing items"],
      "complete": ["array of complete items"]
    },
    "has_warnings": "boolean"
  },
  "completeness_warning": {
    "level": "significant|minor",
    "message": "string",
    "missing_items": [
      {
        "field": "string",
        "label": "string",
        "description": "string",
        "link": "string (route path)"
      }
    ],
    "completeness_percentage": "integer"
  },
  "executive_summary": {
    "narrative": "string (personalised multi-paragraph text)",
    "key_metrics": []
  },
  "current_situation": {
    "...module-specific data..."
  },
  "actions": [
    {
      "id": "string (e.g. investment_action_1)",
      "title": "string",
      "description": "string",
      "category": "string",
      "priority": "critical|high|medium|low",
      "enabled": "boolean (default true)",
      "estimated_impact": "number|null",
      "impact_parameters": {},
      "action_detail": "string|null",
      "scope": "portfolio|account",
      "account_id": "integer|null",
      "account_name": "string|null"
    }
  ],
  "what_if": {
    "current_scenario": { "...module-specific metrics..." },
    "projected_scenario": { "...module-specific metrics..." },
    "is_approximate": "boolean",
    "frontend_calc_params": { "...module-specific..." }
  },
  "conclusion": {
    "summary_text": "string",
    "total_actions": "integer",
    "critical_actions": "integer",
    "high_priority_actions": "integer",
    "detailed_breakdown": [
      {
        "category": "string",
        "action_count": "integer",
        "actions": ["array of action title strings"]
      }
    ]
  }
}
```

Additional module-specific top-level keys:
- Investment: `account_projections` (array of per-account fee projection data)
- Retirement: `pension_projections` (array of per-pension growth projection data)
- Estate: `not_applicable` (boolean), `not_applicable_reason` (string)
- Goal: `goal` (object with `{ id, name, type, status, priority, assigned_module }`)

### 2.2 BasePlanService (`app/Services/Plans/BasePlanService.php`)

Abstract base class that all current plan services extend. Uses the `FormatsCurrency` trait.

**Abstract methods (contract for all child services):**

| Method | Signature | Purpose |
|--------|-----------|---------|
| `generatePlan` | `(int $userId, array $options = []): array` | Generate the complete plan |
| `getRecommendations` | `(int $userId): array` | Get raw recommendations |
| `checkDataCompleteness` | `(int $userId): array` | Returns `{ percentage, missing, complete }` |

**Concrete methods:**

| Method | Signature | Purpose |
|--------|-----------|---------|
| `structureActions` | `(array $recommendations, string $planType): array` | Converts raw agent recommendations into toggleable action cards. Assigns IDs as `{planType}_action_{index+1}` (1-based). Sets `enabled = true` for all actions initially. Maps fields from various recommendation formats (title/action/category, description/rationale/action, etc.) |
| `applyActionFilter` | `(array $actions, array $options): array` | If `options['enabled_action_ids']` is present, sets each action's `enabled` flag based on whether its `id` is in that array. If no filter key exists, returns actions unchanged |
| `buildPlanMetadata` | `(User $user, string $planType, array $completeness): array` | Returns standard metadata object |
| `buildCompletenessWarning` | `(array $completeness): ?array` | Returns null if no missing items. Returns warning with level `significant` (>2 missing) or `minor` (1-2 missing) |
| `generateDynamicConclusion` | `(array $currentSituation, array $enabledActions, string $planType): array` | Counts total/high/critical actions. Builds summary text with different templates based on action counts. Calls `buildDetailedBreakdown()` |
| `buildDetailedBreakdown` | `(array $enabledActions): array` | Groups enabled actions by `category`, returns array of `{ category, action_count, actions: [titles] }` |
| `normalisePriority` | `(mixed $priority): string` | Normalises integer or string priority to `critical/high/medium/low`. Integer mapping: <=1=critical, <=3=high, <=5=medium, else=low. String mapping: critical/urgent=critical, high=high, medium/moderate=medium, low=low, default=medium |
| `roundToPenny` | `(float $value): float` | `round($value, 2)` |

### 2.3 WhatIfCalculator (`app/Services/Plans/WhatIfCalculator.php`)

Orchestrates backend recalculation when the user toggles actions and clicks "Recalculate".

**Constructor dependencies:**
- `InvestmentPlanService`
- `ProtectionPlanService`
- `RetirementPlanService`
- `GoalPlanService`
- `EstatePlanService`

**Methods:**

| Method | Signature | Logic |
|--------|-----------|-------|
| `recalculate` | `(string $planType, int $userId, array $enabledActionIds, array $options = []): array` | Resolves the correct plan service, calls `generatePlan($userId, array_merge($options, ['enabled_action_ids' => $enabledActionIds]))`, then marks `what_if.is_approximate = false` |
| `resolveService` | `(string $planType): BasePlanService` | Match expression: `investment` -> InvestmentPlanService, `protection` -> ProtectionPlanService, `retirement` -> RetirementPlanService, `goal` -> GoalPlanService, `estate` -> EstatePlanService, default -> throws `InvalidArgumentException` |

### 2.4 PlanController (`app/Http/Controllers/Api/Plans/PlanController.php`)

Central controller for the current plan system. Uses `SanitizedErrorResponse` trait.

**Constructor dependencies:**
- `InvestmentPlanService`
- `ProtectionPlanService`
- `RetirementPlanService`
- `GoalPlanService`
- `EstatePlanService`
- `WhatIfCalculator`

**Methods:**

| Method | Route | Logic | Cache |
|--------|-------|-------|-------|
| `generate` | `GET /api/plans/{type}` | Gets `$userId`, resolves plan service via `getPlanService($type)`, calls `generatePlan($userId)`. Returns `{ success: true, data: plan }` | Key: `plan_{type}_{userId}`, TTL: 1800s (30 min) |
| `generateGoalPlan` | `GET /api/plans/goal/{goalId}` | Calls `goalPlanService->generatePlan($userId, ['goal_id' => $goalId])` | Key: `plan_goal_{goalId}_{userId}`, TTL: 1800s |
| `recalculate` | `POST /api/plans/{type}/recalculate` | Gets `enabled_action_ids` from request body. Calls `whatIfCalculator->recalculate($type, $userId, $enabledActionIds)` | No cache |
| `recalculateGoalPlan` | `POST /api/plans/goal/{goalId}/recalculate` | Gets `enabled_action_ids`. Calls `whatIfCalculator->recalculate('goal', $userId, $enabledActionIds, ['goal_id' => $goalId])` | No cache |
| `clearCache` | `DELETE /api/plans/{type}/clear-cache` | Calls `Cache::forget("plan_{$type}_{$userId}")` | N/A |
| `statuses` | `GET /api/plans/statuses` | Calls `checkDataCompleteness($userId)` on investment, protection, retirement, and estate services. Returns all 4 results. Note: does NOT include goal completeness (requires specific goal_id) | No cache |
| `getPlanService` | `(string $type): BasePlanService` | Match: `investment`, `protection`, `retirement`, `estate`. Throws `InvalidArgumentException` for unknown types. Note: `goal` is NOT in this match -- goals use dedicated endpoints | N/A |

### 2.5 BaseAgent (`app/Agents/BaseAgent.php`)

Abstract base class for all module agents. Uses `FormatsCurrency` trait. Defines cache version constant `CACHE_VERSION = 'v1'`.

**Abstract methods (contract for all child agents):**
- `analyze(int $userId): array` -- Module-specific analysis
- `generateRecommendations(array $analysisData): array` -- Generate recommendations from analysis
- `buildScenarios(int $userId, array $parameters): array` -- Build what-if scenarios

**Concrete methods:**

| Method | Purpose |
|--------|---------|
| `remember(string $key, callable $callback, ?int $ttl, array $tags)` | Cache wrapper with optional tag support (Redis/Memcached) |
| `rememberForUser(int $userId, string $suffix, callable $callback, ?int $ttl)` | User-scoped cache: key = `v1_{agentname}_{userId}_{suffix}` |
| `getUserCacheKey(int $userId, string $suffix): string` | Builds cache key: `v1_{agentname}_{userId}_{suffix}` |
| `invalidateUserCache(int $userId, array $additionalKeys)` | Clears all standard cache suffixes (analysis, recommendations, scenarios, summary, projection) plus any additional keys |
| `clearUserCache(int $userId, array $suffixes)` | Iterates suffixes, calls `Cache::forget()` for each |
| `response(bool $success, string $message, array $data): array` | Returns `{ success, message, data, timestamp }` |
| `roundToPenny(float $value): float` | `round($value, 2)` |

### 2.6 Vuex Store: `plans` (`resources/js/store/modules/plans.js`)

Namespaced as `plans`.

**State:**
- `plans` -- Object keyed by plan type (e.g., `{ investment: {...}, protection: {...} }`)
- `goalPlans` -- Object keyed by goalId (e.g., `{ 1: {...}, 2: {...} }`)
- `actionStates` -- Toggle overrides keyed by planKey (e.g., `{ investment: { action_id: true/false } }`)
- `planStatuses` -- Dashboard readiness data per type
- `loading` -- Boolean
- `recalculating` -- Boolean
- `error` -- String or null

**Getters:**

| Getter | Returns |
|--------|---------|
| `getPlan(type)` | Plan object by type |
| `getGoalPlan(goalId)` | Goal plan by ID |
| `enabledActions(type)` | Enabled actions considering toggle overrides |
| `disabledActions(type)` | Disabled actions considering toggle overrides |
| `enabledGoalActions(goalId)` | Enabled goal actions |
| `isLoading` | Loading state |
| `isRecalculating` | Recalculating state |
| `planStatuses` | Dashboard statuses |

**Mutations:**

| Mutation | Params | Purpose |
|----------|--------|---------|
| `setPlan` | `{ type, plan }` | Stores plan by type |
| `setGoalPlan` | `{ goalId, plan }` | Stores goal plan |
| `toggleAction` | `{ planKey, actionId }` | Toggles action enabled state with Vue reactivity update |
| `setPlanStatuses` | `statuses` | Sets dashboard statuses |
| `setLoading` | `loading` | Sets loading flag |
| `setRecalculating` | `recalculating` | Sets recalculating flag |
| `setError` | `error` | Sets error message |
| `clearPlan` | `type` | Clears plan data |

**Actions:**

| Action | Params | Logic |
|--------|--------|-------|
| `fetchPlan` | `type` | Calls `plansService.generatePlan(type)`, commits `setPlan` |
| `fetchGoalPlan` | `goalId` | Calls `plansService.generateGoalPlan(goalId)`, commits `setGoalPlan` |
| `toggleAction` | `{ planKey, actionId }` | Commits toggle mutation |
| `recalculateScenario` | `{ type }` | Collects enabled action IDs from store, posts to `plansService.recalculateScenario()`, updates plan |
| `recalculateGoalScenario` | `{ goalId }` | Same pattern for goal plans |
| `fetchDashboardStatuses` | none | Gets plan readiness from `plansService.getDashboardStatuses()` |

### 2.7 Frontend API Service: `plansService` (`resources/js/services/plansService.js`)

| Method | HTTP | Endpoint | Purpose |
|--------|------|----------|---------|
| `generateInvestmentSavingsPlan()` | GET | `/plans/investment-savings` | Legacy plan |
| `clearInvestmentSavingsPlanCache()` | DELETE | `/plans/investment-savings/clear-cache` | Legacy cache clear |
| `generatePlan(type)` | GET | `/plans/${type}` | Generate plan by type |
| `generateGoalPlan(goalId)` | GET | `/plans/goal/${goalId}` | Generate goal plan |
| `recalculateScenario(type, enabledActionIds)` | POST | `/plans/${type}/recalculate` | Recalculate with toggled actions |
| `recalculateGoalScenario(goalId, enabledActionIds)` | POST | `/plans/goal/${goalId}/recalculate` | Recalculate goal scenario |
| `getDashboardStatuses()` | GET | `/plans/statuses` | Dashboard readiness |
| `clearPlanCache(type)` | DELETE | `/plans/${type}/clear-cache` | Clear cache |

### 2.8 Shared Vue Components

#### PlanPageLayout (`resources/js/components/Plans/Shared/PlanPageLayout.vue`)
- **Props:** `title` (required), `subtitle`, `loading`, `error`, `loadingMessage`, `printTitle`, `planData`
- **Emits:** `retry`, `print`
- **Children:** `AppLayout`, `PrintHeader`, `PlanLoadingState`, `PlanErrorState`
- Wraps every current plan view. Shows back link to `/plans`, print button, loading/error states, and default slot for plan content.

#### PlanDashboardCard (`resources/js/components/Plans/Shared/PlanDashboardCard.vue`)
- **Props:** `title`, `description`, `iconPath`, `color` (blue/green/purple/teal/red), `completeness` (0-100), `clickable`, `status`
- **Emits:** `click`
- **Computed:** `statusLabel` -- Ready (>=75%), Partial Data (>=25%), Needs Data (<25%)
- Shows progress bar, status badge, "View Plan" link.

#### PlanExecutiveSummary (`resources/js/components/Plans/Shared/PlanExecutiveSummary.vue`)
- **Props:** `summary` (Object, required)
- Renders `summary.narrative` as preformatted text.

#### PlanSectionHeader (`resources/js/components/Plans/Shared/PlanSectionHeader.vue`)
- **Props:** `title` (required), `subtitle`, `color` (blue/green/purple/gray/teal)
- Renders coloured banner with title and optional subtitle.

#### PlanActionCard (`resources/js/components/Plans/Shared/PlanActionCard.vue`)
- **Props:** `action` (Object, required)
- **Emits:** `toggle` (with `action.id`)
- **Mixins:** `currencyMixin`
- Shows priority badge, category, title, description, estimated_impact, toggle switch.
- Priority colours: critical=red, high=blue, medium=gray, low=green.

#### PlanActionsList (`resources/js/components/Plans/Shared/PlanActionsList.vue`)
- **Props:** `actions` (Array)
- **Emits:** `toggle`
- **Children:** `PlanSectionHeader`, `PlanActionCard`
- **Computed:** `enabledCount`, `enabledCountLabel`, `sortedActions` (sorted by priority: critical > high > medium > low).

#### PlanConclusion (`resources/js/components/Plans/Shared/PlanConclusion.vue`)
- **Props:** `conclusion` (Object, required)
- **Data:** `expanded` (boolean for collapsible breakdown)
- Shows `summary_text`, action count badges (critical/high/total), collapsible `detailed_breakdown` groups with checkmark lists.

#### PlanMissingDataPrompt (`resources/js/components/Plans/Shared/PlanMissingDataPrompt.vue`)
- **Props:** `warning` (Object or null)
- Shows when data is incomplete: message, missing items with "Add" router-links to the relevant section, completeness percentage bar.

#### PlanWhatIfComparison (`resources/js/components/Plans/Shared/PlanWhatIfComparison.vue`)
- **Props:** `currentScenario`, `projectedScenario`, `chartMetrics`
- **Children:** `PlanSectionHeader`, `PlanWhatIfChart`
- **Slots:** `#current`, `#projected`, `#controls`
- Two-column layout: "Current Position" left, "With Actions" right (green tinted).

#### PlanWhatIfChart (`resources/js/components/Plans/Shared/PlanWhatIfChart.vue`)
- **Props:** `currentScenario`, `projectedScenario`, `metrics` (Array of `{key, label}`)
- **Mixins:** `currencyMixin`
- Uses ApexCharts horizontal bar chart with `CHART_COLORS` from `designSystem` constants.
- Reactive chart key based on data totals.

#### PlanWhatIfMetricRow (`resources/js/components/Plans/Shared/PlanWhatIfMetricRow.vue`)
- **Props:** `label`, `value`, `delta`, `format` (currency/percentage/number), `invertDelta`, `suffix`
- **Mixins:** `currencyMixin`
- Shows label, formatted value, and coloured delta badge (+/- with green/red).

#### PlanLoadingState (`resources/js/components/Plans/Shared/PlanLoadingState.vue`)
- **Props:** `message` (default: 'Generating your plan...')
- Spinner with message text.

#### PlanErrorState (`resources/js/components/Plans/Shared/PlanErrorState.vue`)
- **Props:** `message`
- **Emits:** `retry`
- Error icon, message, "Try Again" button.

#### planPrintMixin (`resources/js/components/Plans/Shared/planPrintMixin.js`)
- **Includes:** `currencyMixin`
- **Data:** `generatingPdf` (boolean)
- **Key methods:**
  - `printPlan(plan, title)` -- Opens a new browser window, writes a complete A4-printable HTML document, triggers print dialog
  - `buildPlanHtml(plan, title)` -- Generates HTML with sections: cover page with logo, executive summary, current situation (auto-detects data shape), recommended actions, projected outcomes (with HTML/CSS bar chart), conclusion
  - `buildCurrentSituationHtml(situation)` -- Dispatches to sub-builders based on data shape: coverage analysis, policies, debt, investment accounts, savings accounts, DC pensions, DB pensions, state pension, goal situation, situation indicators
  - `buildActionsHtml(enabled, disabled)` -- Renders numbered action items with priority badges
  - `buildWhatIfHtml(whatIf)` -- Renders bar chart comparison and metric table
  - `buildBarChartHtml(...)` -- Pure HTML/CSS horizontal bar chart (no JavaScript charting library)

### 2.9 Caching Strategy

| Cache Key Pattern | TTL | Used By | Cleared By |
|-------------------|-----|---------|------------|
| `plan_{type}_{userId}` | 1800s (30min) | `PlanController::generate()` | `PlanController::clearCache()` |
| `plan_goal_{goalId}_{userId}` | 1800s (30min) | `PlanController::generateGoalPlan()` | Manual |
| `v1_{agentname}_{userId}_analysis` | Agent-specific | All agents via `rememberForUser()` | `invalidateUserCache()` |
| `estate_analysis_{userId}` | Default | EstateAgent | `EstateAgent::invalidateCache()` |
| `protection_analysis_{userId}` | Default | ProtectionAgent | `ProtectionAgent::invalidateCache()` |
| `investment_analysis_{userId}` | Default | InvestmentAgent | `InvestmentAgent::clearCache()` |
| `retirement_analysis_{userId}` | 3600s (1hr) | RetirementAgent | N/A |
| `savings_analysis_{userId}` | 1800s (30min) | SavingsAgent | N/A |
| `v1_goalsagent_{userId}_analysis` | Default | GoalsAgent | `GoalsAgent::clearCache()` |

Note: Recalculate endpoints do NOT use cache -- they always regenerate fresh data.

### 2.10 Hardcoded Constants and Rates Used Across Plans

| Value | Used In | Purpose |
|-------|---------|---------|
| `0.05` (5%) | Investment, Retirement | Default annual growth rate |
| `0.04` (4%) | Retirement | Sustainable withdrawal / annuity rate |
| `0.25%` | Investment | Platform fee benchmark |
| `0.15%` | Investment | OCF (Ongoing Charges Figure) benchmark |
| `200` | Investment, Retirement | Default fee reduction per action (GBP); monthly contribution increase (GBP) |
| `500` | Investment, Goal | Monthly additional savings estimate (GBP); lump sum contribution (GBP) |
| `2400` | Retirement | Annual additional contribution per pension action (200/month * 12) |
| `2%` | Retirement | Consolidation efficiency gain on projected income |
| `3%` | Retirement | Tax optimisation gain on projected income |
| `1%` | Retirement | Default other action gain on projected income |
| `50` | Goal | Monthly contribution increase per action (GBP) |
| `25` | Goal | Default monthly contribution increase per action (GBP) |
| `1000` | Goal | Lump sum scenario constant (`SCENARIO_LUMP_SUM`) |
| `10` | Estate | Charitable giving threshold percentage for reduced IHT rate |
| `35` | Estate | Minimum age gate for estate planning |
| `1800` | PlanController | Cache TTL in seconds (30 minutes) |
| `3600` | RetirementAgent | Cache TTL for retirement analysis (1 hour) |

---

## 3. Investment & Savings Plan

### 3.1 Overview

The Investment & Savings Plan combines investment portfolio analysis with savings account assessment. It analyses investment accounts, holdings, fees, asset allocation, tax efficiency, emergency fund adequacy, ISA usage, and savings rates. It generates recommendations for fee reduction, portfolio rebalancing, tax optimisation, emergency fund building, and savings rate improvement.

### 3.2 Route

- **Frontend:** `/plans/investment` (name: `InvestmentPlan`)
- **API:** `GET /api/plans/investment`
- **Recalculate:** `POST /api/plans/investment/recalculate`

### 3.3 Frontend Files

#### View: `resources/js/views/Plans/InvestmentPlan.vue`
- **Components used:** `PlanPageLayout`, `InvestmentPlanContent`
- **Mixins:** `planPrintMixin`
- **Store interactions:** `mapGetters('plans', ['getPlan', 'isLoading'])`, `mapActions('plans', ['fetchPlan', 'toggleAction'])`
- **Computed:** `plan` from `getPlan('investment')`
- **Methods:** Passes `toggle-action` event from `InvestmentPlanContent` to `toggleAction({ planKey: 'investment', actionId })`. Passes `print` event to `planPrintMixin.printPlan()`.
- **On mount:** Dispatches `fetchPlan('investment')`

#### Content: `resources/js/components/Plans/Investment/InvestmentPlanContent.vue`
- **Props:** `plan` (Object)
- **Emits:** `toggle-action`
- **Children:** `PlanMissingDataPrompt`, `PlanExecutiveSummary`, `InvestmentCurrentSituation`, `InvestmentGroupedActions`, `PlanConclusion`
- Template renders sections in order: missing data prompt, executive summary, current situation, grouped actions with charts, conclusion.

#### Current Situation: `resources/js/components/Plans/Investment/InvestmentCurrentSituation.vue`
- **Props:** `situation` (Object)
- **Mixins:** `currencyMixin`
- Displays:
  - Investment accounts list: name, type, provider, formatted value, holdings count
  - Total investment value
  - Savings accounts list: institution, type, formatted balance, interest rate
  - Total savings value
  - Emergency fund indicator: colour-coded months of runway (green >=6, blue >=3, red <3)
  - ISA allowance: used vs remaining

#### Grouped Actions: `resources/js/components/Plans/Investment/InvestmentGroupedActions.vue`
- **Props:** `actions` (Array), `accountProjections` (Array), `whatIf` (Object)
- **Emits:** `toggle`
- **Children:** `PlanSectionHeader`, `PlanActionCard`, `AccountFeeProjectionChart`, `InvestmentWhatIfControls`
- Groups actions by `scope`:
  - Per-account groups (scope=`account`): Shows account name header, account-level action cards, per-account fee projection chart
  - Portfolio-level actions (scope=`portfolio`): Shows portfolio action cards
- Computes a portfolio projection line chart (5% growth, current vs reduced fees) using ApexCharts
- Shows side-by-side what-if metrics below the chart

#### Account Fee Projection Chart: `resources/js/components/Plans/Investment/AccountFeeProjectionChart.vue`
- **Props:** `projection` (Object), `enabledActionCount` (Number), `totalActionCount` (Number)
- Per-account line chart showing current fees vs reduced fees over projection horizon
- Uses linear interpolation for partial action toggles (if 2 of 4 fee actions enabled, shows 50% of fee reduction)
- Uses `CHART_COLORS` from designSystem constants

#### What-If Controls: `resources/js/components/Plans/Investment/InvestmentWhatIfControls.vue`
- **Props:** `scenario` (Object), `label` (String)
- Displays metrics: Total Wealth, Annual Fees, Emergency Fund (months), At Retirement value

### 3.4 Backend Service: `InvestmentPlanService` (`app/Services/Plans/InvestmentPlanService.php`)

**Extends:** `BasePlanService`

**Constructor dependencies:**
- `InvestmentAgent $investmentAgent`
- `SavingsAgent $savingsAgent`
- `FeeAnalyzer $feeAnalyzer`

#### `generatePlan(int $userId, array $options = []): array`

**Database queries:**
- `User::findOrFail($userId)` -- loads user from `users` table
- `InvestmentAccount::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->with('holdings')->get()` -- loads from `investment_accounts` table with `holdings` (polymorphic)
- `SavingsAccount::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get()` -- loads from `savings_accounts` table

**Agent calls:**
- `$this->investmentAgent->analyze($userId)` -- full portfolio analysis
- `$this->savingsAgent->analyze($userId)` -- full savings analysis

**Logic:**
1. Loads user, calculates age from `date_of_birth`
2. Calculates `$yearsToRetirement` from `retirement_date` or retirement profile's `target_retirement_age`
3. Calls both agents for analysis data
4. Builds completeness check, metadata, executive summary, current situation
5. Gets merged recommendations from investment + savings
6. Structures actions via `structureActions()`
7. Applies action filter if `enabled_action_ids` present
8. Builds per-account growth projections
9. Builds what-if data
10. Builds conclusion

**Returns:** `{ metadata, completeness_warning, executive_summary, current_situation, actions, what_if, conclusion, account_projections }`

#### `checkDataCompleteness(int $userId): array`

**Database queries:**
- `InvestmentAccount::where('user_id', $userId)->exists()` -- checks `investment_accounts` table
- `RiskProfile::where('user_id', $userId)->exists()` -- checks `risk_profiles` table
- `SavingsAccount::where('user_id', $userId)->exists()` -- checks `savings_accounts` table
- `User::where('id', $userId)->where(fn: annual_employment_income OR annual_self_employment_income NOT NULL)->exists()` -- checks `users` table
- `InvestmentAccount::where('user_id', $userId)->whereHas('holdings')->exists()` -- checks `investment_accounts` + `holdings` tables

**Checks (5 items):**

| Field | Label | Description | Link | Checks |
|-------|-------|-------------|------|--------|
| `investment_accounts` | Investment Accounts | Add your investment accounts | `/investments` | Has any investment account |
| `risk_profile` | Risk Profile | Complete your risk profile | `/investments` | Has risk profile record |
| `savings_accounts` | Savings Accounts | Add your savings accounts | `/savings` | Has any savings account |
| `income` | Income Details | Add your income | `/profile` | Has employment or self-employment income |
| `holdings` | Investment Holdings | Add holdings to your accounts | `/investments` | Has at least one account with holdings |

#### `getRecommendations(int $userId): array`

**Logic:**
1. Calls `$this->investmentAgent->analyze($userId)`
2. Calls `$this->savingsAgent->analyze($userId)`
3. Loads investment accounts with holdings
4. Runs `$this->feeAnalyzer->analyzeAccountFees($account)` for each account, filters successful results
5. Calls `$this->investmentAgent->generateRecommendations($investmentAnalysis, $accountFeeAnalyses)` -- note: InvestmentAgent's `generateRecommendations` takes an extra `$accountFeeAnalyses` parameter beyond the BaseAgent contract
6. Merges investment recs with `buildSavingsRecommendations($savingsAnalysis)`
7. Returns merged array

#### `buildExecutiveSummary(User $user, array $investmentAnalysis, array $savingsAnalysis, $investmentAccounts, $savingsAccounts): array`

**Additional query:** `RiskProfile::where('user_id', $user->id)->first()`

**Narrative construction:**
- Greeting with user's first name
- Total wealth paragraph (investment value + savings value)
- Risk profile mention (if exists)
- Account types overview (ISA, GIA, SIPP, etc.)
- Emergency fund assessment: >=6 months (strong), >=3 months (reasonable), >0 (below minimum), 0 (none)
- ISA allowance status

#### `buildCurrentSituation(...)`: array

**Returns:**
- `investment_accounts[]` -- mapped: `{ id, name, type, provider, value, holdings_count }`
- `savings_accounts[]` -- mapped: `{ id, institution, type, balance, interest_rate }`
- `asset_allocation` -- from investment analysis
- `fee_analysis` -- from investment analysis
- `diversification_score` -- from investment analysis
- `tax_wrappers` -- from investment analysis
- `emergency_fund` -- `{ runway_months, category, total_savings }`
- `isa_allowance` -- `{ used, remaining }`
- `total_investment_value`, `total_savings_value`

#### `buildSavingsRecommendations(array $savingsAnalysis): array`

**Logic:**
- Emergency fund: runway <3 = critical priority 1, <6 = high priority 2
- Rate comparison: filters savings accounts rated `'Poor'`, sums `potential_gain`, creates recommendation
- ISA allowance: if remaining >0 AND emergency runway >=6, suggests using ISA allowance

#### `buildWhatIfData(...)`: array

**Calculations:**
- Growth rate: `0.05` (5%)
- Projection years: `$yearsToRetirement ?? 10`
- Fee reduction per fee-related action: `$action['estimated_impact'] ?? 200`
- Additional savings per savings-related action: `500/month`
- Emergency fund improvement if savings actions: `+2 months`
- Current projected value: `totalWealth * (1 + 0.05)^years`
- Projected value: adds fee savings + additional savings

**Returns:**
- `current_scenario`: `{ total_wealth, annual_fees, emergency_fund_months, projected_value }`
- `projected_scenario`: `{ total_wealth, annual_fees, emergency_fund_months, projected_value }`
- `is_approximate: true`
- `frontend_calc_params`: `{ current_value, growth_rate: 0.05, years }`

#### `buildAccountGrowthProjections(...)`: array

**Additional query:** `Goal::where('user_id', $userId)->whereNotNull('linked_investment_account_id')->whereNotNull('target_date')->get()->groupBy('linked_investment_account_id')` -- from `goals` table

**Per-account logic:**
- Gets fee analysis via `$this->feeAnalyzer->analyzeAccountFees($account)`
- Determines projection years from linked goal target date, or retirement, or default 10 years
- Builds two series (current fees vs reduced fees) using compound growth: `currentValue * pow(1 + growthRate - feeRate, y)`
- Growth rate: `0.05` (5%)
- Estimates reduced fee percent via `estimateReducedFeePercent()` using benchmarks (platform 0.25%, OCF 0.15%)

**Returns per account:** `{ account_id, account_name, account_type, current_value, current_fee_percent, reduced_fee_percent, annual_fee_saving, years, projection_label, current_fees_series[], reduced_fees_series[], projection_difference }`

#### `projectValue(float $presentValue, float $rate, int $years, float $monthlyContribution): float`

**Formula:** `FV = PV*(1+r/12)^(n*12) + PMT*((1+r/12)^(n*12) - 1)/(r/12)`
- If rate <= 0: simple `PV + (PMT * 12 * years)`

### 3.5 InvestmentAgent (`app/Agents/InvestmentAgent.php`)

**Constructor dependencies (6 services):**
- `PortfolioAnalyzer`
- `MonteCarloSimulator` (injected but not directly called in this file)
- `AssetAllocationOptimizer`
- `FeeAnalyzer`
- `TaxEfficiencyCalculator`
- `TaxConfigService`

#### `analyze(int $userId): array`

**Database queries:**
- `InvestmentAccount::where('user_id', $userId)->with('holdings')->get()` -- `investment_accounts` + `holdings`
- `RiskProfile::where('user_id', $userId)->first()` -- `risk_profiles`
- `InvestmentGoal::where('user_id', $userId)->get()` -- `investment_goals`

**Service calls:**
- `$portfolioAnalyzer->calculateTotalValue($accounts)`
- `$portfolioAnalyzer->calculateReturns($holdings)`
- `$portfolioAnalyzer->calculateAssetAllocation($holdings)`
- `$portfolioAnalyzer->calculateDiversificationScore($allocation)`
- `$portfolioAnalyzer->calculatePortfolioRisk($holdings, $riskProfile)`
- `$feeAnalyzer->calculateTotalFees($accounts, $holdings)`
- `$feeAnalyzer->compareToLowCostAlternatives($holdings)`
- `$feeAnalyzer->identifyHighFeeHoldings($holdings)`
- `$taxCalculator->calculateUnrealizedGains($holdings)`
- `$taxCalculator->calculateTaxEfficiencyScore($accounts, $holdings)`
- `$taxCalculator->identifyHarvestingOpportunities($holdings)`
- If risk profile: `$allocationOptimizer->getTargetAllocation($riskProfile)`, `$allocationOptimizer->calculateDeviation($allocation, $targetAllocation)`
- `$this->taxConfig->getISAAllowances()` for ISA allowance data

**Returns:** `portfolio_summary`, `returns`, `asset_allocation`, `diversification_score`, `risk_metrics`, `fee_analysis`, `low_cost_comparison`, `tax_efficiency`, `tax_wrappers`, `allocation_deviation`, `goals`

#### `generateRecommendations(array $analysis, array $accountFeeAnalyses = []): array`

**Recommendation categories generated (in priority order):**
1. No risk profile -- "Complete Your Risk Profile" (priority 1)
2. No holdings -- "Add Your Holdings" (priority 1)
3. Low diversification score (<70) (priority 2)
4. Per-account fee issues (from `$accountFeeAnalyses`):
   - High total fees (>1.0%) -- scope: account
   - High weighted OCF (>0.5%) -- scope: account
   - High platform fees (>0.8%) -- scope: account
5. Portfolio rebalancing needed (priority 3, scope: portfolio)
6. Tax efficiency hierarchy:
   - Has GIA but no ISA -- suggest ISA (priority 2)
   - Has ISA with remaining allowance and GIA holdings -- transfer to ISA (priority 3)
   - Large GIA (>50k) with no bonds -- consider bond wrapper (priority 4)
7. Tax loss harvesting opportunities (priority 4)

### 3.6 SavingsAgent (`app/Agents/SavingsAgent.php`)

**Constructor dependencies (5 services):**
- `EmergencyFundCalculator`
- `ISATracker`
- `GoalProgressCalculator`
- `LiquidityAnalyzer`
- `RateComparator`

**Traits:** `ResolvesExpenditure` (in addition to `FormatsCurrency` from BaseAgent)

**Cache TTL:** 1800s (30 minutes)

#### `analyze(int $userId): array`

**Database queries:**
- `SavingsAccount::where('user_id', $userId)->get()` -- `savings_accounts`
- `SavingsGoal::where('user_id', $userId)->get()` -- `savings_goals`
- `User::find($userId)` -- `users`

**Service calls:**
- `$emergencyFundCalculator->calculateRunway($totalSavings, $monthlyExpenditure)`
- `$emergencyFundCalculator->calculateAdequacy($runway, 6)` (6 months target)
- `$emergencyFundCalculator->categorizeAdequacy($runway)`
- `$isaTracker->getCurrentTaxYear()`
- `$isaTracker->getISAAllowanceStatus($userId, $taxYear)`
- `$liquidityAnalyzer->categorizeLiquidity($accounts)`
- `$liquidityAnalyzer->getLiquiditySummary($accounts)`
- `$liquidityAnalyzer->buildLiquidityLadder($accounts)`
- Per account: `$rateComparator->compareToMarketRates($account)`, `$rateComparator->calculateInterestDifference($account, marketRate)`
- Per goal: `$goalProgressCalculator->calculateProgress($goal)`
- `$goalProgressCalculator->prioritizeGoals($goals)`

**Returns:** `summary`, `emergency_fund`, `isa_allowance`, `liquidity`, `rate_comparisons`, `goals`

### 3.7 Database Tables Involved

| Table | Used For |
|-------|----------|
| `users` | User profile, income data, date of birth, retirement date |
| `investment_accounts` | Investment account details, fees, account types |
| `holdings` | Individual security holdings, prices, OCF |
| `risk_profiles` | Risk tolerance, capacity for loss, time horizon |
| `savings_accounts` | Savings balances, interest rates, ISA status |
| `savings_goals` | Goal progress (deprecated, using `goals` instead) |
| `goals` | Linked goals for projection horizon |
| `investment_goals` | Investment-specific goals |
| `isa_allowance_tracking` | ISA subscription tracking by tax year |
| `savings_market_rates` | Market rate comparisons |
| `tax_configurations` | ISA allowance values |

---

## 4. Protection Plan

### 4.1 Overview

The Protection Plan analyses a user's protection needs based on their income, dependants, debts, and existing policies. It calculates coverage gaps for life insurance, critical illness, and income protection, then generates recommendations for closing those gaps with estimated costs.

### 4.2 Route

- **Frontend:** `/plans/protection` (name: `ProtectionPlan`)
- **API:** `GET /api/plans/protection`
- **Recalculate:** `POST /api/plans/protection/recalculate`

### 4.3 Frontend Files

#### View: `resources/js/views/Plans/ProtectionPlan.vue`
- **Components used:** `PlanPageLayout`, `ProtectionPlanContent`
- **Mixins:** `planPrintMixin`
- **Store interactions:** Same pattern as InvestmentPlan with type `'protection'`
- **On mount:** Dispatches `fetchPlan('protection')`

#### Content: `resources/js/components/Plans/Protection/ProtectionPlanContent.vue`
- **Props:** `plan` (Object)
- **Emits:** `toggle-action`
- **Data:** `chartMetrics` -- defines chart comparison metrics: `[{key: 'life_insurance_gap', label: 'Life Insurance'}, {key: 'critical_illness_gap', label: 'Critical Illness'}]`
- **Children:** `PlanMissingDataPrompt`, `PlanExecutiveSummary`, `ProtectionCurrentSituation`, `PlanActionsList`, `PlanWhatIfComparison`, `PlanConclusion`, `ProtectionWhatIfControls`
- Template renders: missing data prompt, executive summary, current situation, actions list, what-if comparison (with protection-specific controls in slots), conclusion.

#### Current Situation: `resources/js/components/Plans/Protection/ProtectionCurrentSituation.vue`
- **Props:** `situation` (Object)
- Displays:
  - Coverage analysis grid for life insurance, critical illness, income protection: need value, have value, gap value, progress bar
  - "How we calculated" expandable breakdowns showing formula components
  - Existing policies list with provider, type, sum assured
  - Debt breakdown: mortgage balance, other debts

#### What-If Controls: `resources/js/components/Plans/Protection/ProtectionWhatIfControls.vue`
- **Props:** `scenario` (Object)
- Displays: Total Coverage Gap, Life Insurance Gap, Critical Illness Gap, Income Protection Gap, Additional Monthly Premium

### 4.4 Backend Service: `ProtectionPlanService` (`app/Services/Plans/ProtectionPlanService.php`)

**Extends:** `BasePlanService`

**Constructor dependencies:**
- `ProtectionAgent $protectionAgent`
- `ComprehensiveProtectionPlanService $comprehensivePlanService`

#### `generatePlan(int $userId, array $options = []): array`

**Database queries:**
- `User::findOrFail($userId)` -- `users` table

**Delegated call:**
- `$this->comprehensivePlanService->generateComprehensiveProtectionPlan($user)` -- this is the heavy lifting, which internally queries all protection-related tables

**Logic:**
1. Checks data completeness
2. Calls comprehensive plan service (wrapped in try/catch -- returns error structure on failure)
3. Extracts recommendations via `extractRecommendations()`
4. Structures actions via `structureActions()`
5. Applies action filter if present
6. Builds what-if data
7. Builds custom protection conclusion (NOT using `generateDynamicConclusion()`)

**Returns:** `{ metadata, completeness_warning, executive_summary, current_situation, actions, what_if, conclusion }`

#### `checkDataCompleteness(int $userId): array`

**Database queries:**
- `User::with(['protectionProfile', 'lifeInsurancePolicies'])->find($userId)` -- `users`, `protection_profiles`, `life_insurance_policies`

**Checks (3 items):**

| Field | Label | Description | Link | Checks |
|-------|-------|-------------|------|--------|
| `protection_profile` | Protection Profile | Complete your protection profile | `/protection` | Has `protectionProfile` relationship |
| `income` | Income Details | Add your income details | `/profile` | Has `annual_employment_income` or `annual_self_employment_income` |
| `policies` | Protection Policies | Add your existing policies | `/protection` | Has at least one life insurance policy |

#### `extractRecommendations(array $comprehensivePlan): array`

**Source:** `$comprehensivePlan['optimized_strategy']['recommendations']`

**Per recommendation extracts:**
- `priority` from recommendation
- `category` from recommendation
- `action` from recommendation
- `rationale` from `details`
- `impact` from `importance`
- `estimated_cost` from `estimated_monthly_cost`
- `impact_parameters.coverage_amount` from recommendation
- `timeframe` from recommendation

**Gap filling:** Calls `ensureGapActions()` which checks if life, critical illness, or income protection recommendations already exist. If a gap is >0 and no recommendation exists for that type, it adds one:
- Life insurance gap: priority 1, impact 'Critical'
- Critical illness gap: priority 2, impact 'High'
- Income protection gap: priority 2, impact 'High'

**Sorts** by priority ascending.

#### `buildExecutiveSummary(User $user, array $comprehensivePlan): array`

**Additional queries:**
- `FamilyMember::where('user_id', $user->id)->orderByRaw("FIELD(relationship, 'spouse', 'partner', 'child', 'parent', 'sibling', 'other')")->get()` -- `family_members`
- `$user->lifeInsurancePolicies()->count()` -- `life_insurance_policies`
- `$user->criticalIllnessPolicies()->count()` -- `critical_illness_policies`
- `$user->incomeProtectionPolicies()->count()` -- `income_protection_policies`

**Narrative construction:**
- Personal context: age, occupation (with article prefix "a/an"), spouse name
- Dependant information: count, names, ages
- Income and employment details
- Health and lifestyle factors (smoker status, health status)
- Current policy overview: life, CI, IP policy counts and coverage analysis
- Debt context: mortgage and other liabilities
- Gap identification text
- Closing text

Uses `prefixWithArticle()` helper: returns "an {word}" if first letter is vowel, else "a {word}".

#### `buildCurrentSituation(array $comprehensivePlan): array`

**Simply restructures keys from comprehensive plan:**
- `profile` from `user_profile`
- `financial_summary` from `financial_summary`
- `needs` from `protection_needs`
- `current_coverage` from `current_coverage`
- `coverage_analysis` from `coverage_analysis`
- `scenario_analysis` from `scenario_analysis`
- `debt_breakdown` from `financial_summary.debt_breakdown`

#### `buildWhatIfData(array $comprehensivePlan, array $enabledActions): array`

**Data source:** `$comprehensivePlan['coverage_analysis']`

**Calculations:**
- Current gaps: life insurance, critical illness, income protection from coverage analysis
- `$totalGap = $lifeGap + $ciGap + ($ipGap * 12)` -- income protection annualised
- Per enabled action: reduces matching gap by `coverage_amount` (from `impact_parameters.coverage_amount` or `estimated_impact`)
- Also tracks `$additionalPremium` from `estimated_impact`
- Projected gaps: `max(0, gap - reduction)`
- Projected total: `projectedLife + projectedCi + (projectedIp * 12)`

**Returns:**
- `current_scenario`: 10 fields -- `total_coverage_gap`, `life_insurance_gap`, `life_insurance_need`, `life_insurance_coverage`, `critical_illness_gap`, `critical_illness_need`, `critical_illness_coverage`, `income_protection_gap`, `income_protection_need`, `income_protection_coverage`
- `projected_scenario`: same 10 fields + `estimated_additional_premium`
- `is_approximate: true`

#### `buildProtectionConclusion(array $enabledActions, array $disabledActions, array $whatIf): array`

Custom conclusion builder (does NOT use `generateDynamicConclusion`). Three narrative branches:
1. No actions at all: mentions total coverage gap
2. All disabled: mentions coverage gap remains unaddressed
3. Some/all enabled: describes enabled/disabled actions using `describeActions()` with specific amounts

### 4.5 ProtectionAgent (`app/Agents/ProtectionAgent.php`)

**Constructor dependencies (5 services):**
- `CoverageGapAnalyzer`
- `AdequacyScorer`
- `RecommendationEngine`
- `ScenarioBuilder`
- `ProfileCompletenessChecker`

#### `analyze(int $userId): array`

**Database queries:**
- `User` with 6 eager-loaded relations: `protectionProfile`, `lifeInsurancePolicies`, `criticalIllnessPolicies`, `incomeProtectionPolicies`, `disabilityPolicies`, `sicknessIllnessPolicies`
- `$user->mortgages()->sum('outstanding_balance')` -- `mortgages`
- `$user->liabilities()->sum('current_balance')` -- `liabilities`
- Calculates `totalAnnualIncome` from 5 income fields on User: `annual_employment_income`, `annual_self_employment_income`, `annual_rental_income`, `annual_dividend_income`, `annual_other_income`

**Service calls:**
- `$gapAnalyzer->calculateProtectionNeeds($profile)` -- what user needs
- `$gapAnalyzer->calculateTotalCoverage(...)` -- passes all 5 policy collections
- `$gapAnalyzer->calculateCoverageGap($needs, $coverage)`
- `$adequacyScorer->calculateAdequacyScore($gaps, $needs)`
- `$adequacyScorer->generateScoreInsights(...)` -- score, gaps, needs, hasDependants
- `$recommendationEngine->generateRecommendations($gaps, $profile)`
- `$scenarioBuilder->modelDeathScenario($profile, $coverage)`
- `$scenarioBuilder->modelCriticalIllnessScenario($profile, $coverage)`
- `$scenarioBuilder->modelDisabilityScenario($profile, $coverage)`
- `$completenessChecker->checkCompleteness($user)`

**Returns:** `profile`, `needs`, `coverage`, `gaps`, `adequacy_score`, `recommendations`, `scenarios`, `debt_breakdown`, `policies`, `profile_completeness`

### 4.6 ComprehensiveProtectionPlanService (`app/Services/Protection/ComprehensiveProtectionPlanService.php`)

**Constructor dependencies:**
- `ProtectionAgent`
- `CoverageGapAnalyzer`
- `AdequacyScorer`
- `RecommendationEngine`

**Method: `generateComprehensiveProtectionPlan(User $user): array`**

Returns full plan with sections: `plan_metadata`, `completeness_warning`, `executive_summary`, `user_profile`, `financial_summary`, `current_coverage`, `protection_needs`, `coverage_analysis`, `recommendations`, `scenario_analysis`, `optimized_strategy`, `implementation_timeline`, `next_steps`

**Method: `generateOptimizedStrategy(array $gaps, array $userProfile): array`**

Priority logic:
1. Life insurance if gap > 10,000 AND has dependants
2. Critical illness if gap > 10,000
3. Income protection if gap > 100/month

Estimates premiums by age and smoker status.

### 4.7 Database Tables Involved

| Table | Used For |
|-------|----------|
| `users` | User profile, income, age |
| `protection_profiles` | Occupation, smoker status, health, dependents |
| `life_insurance_policies` | Existing life cover, sums assured, trust status |
| `critical_illness_policies` | Existing CI cover |
| `income_protection_policies` | Existing IP cover |
| `disability_policies` | Existing disability cover |
| `sickness_illness_policies` | Existing sickness cover |
| `family_members` | Dependant details, spouse info |
| `mortgages` | Outstanding mortgage balance (for needs calculation) |
| `liabilities` | Other debts (for needs calculation) |

---

## 5. Retirement Plan

### 5.1 Overview

The Retirement Plan analyses a user's retirement preparedness by examining their defined contribution (DC) pensions, defined benefit (DB) pensions, state pension, and retirement profile. It projects retirement income, identifies income gaps (both before and after state pension age), and generates recommendations for contribution optimisation, employer match maximisation, NI gap filling, and tax relief.

### 5.2 Route

- **Frontend:** `/plans/retirement` (name: `RetirementPlan`)
- **API:** `GET /api/plans/retirement`
- **Recalculate:** `POST /api/plans/retirement/recalculate`

### 5.3 Frontend Files

#### View: `resources/js/views/Plans/RetirementPlan.vue`
- **Components used:** `PlanPageLayout`, `RetirementPlanContent`
- **Mixins:** `planPrintMixin`
- **Store interactions:** Same pattern with type `'retirement'`
- **On mount:** Dispatches `fetchPlan('retirement')`

#### Content: `resources/js/components/Plans/Retirement/RetirementPlanContent.vue`
- **Props:** `plan` (Object)
- **Emits:** `toggle-action`
- **Children:** `PlanMissingDataPrompt`, `PlanExecutiveSummary`, `RetirementCurrentSituation`, `RetirementGroupedActions`, `PlanConclusion`

#### Current Situation: `resources/js/components/Plans/Retirement/RetirementCurrentSituation.vue`
- **Props:** `situation` (Object)
- Displays:
  - DC pensions: scheme name, provider, current value, monthly employee + employer contributions
  - DB pensions: scheme name, projected annual pension, normal retirement age
  - State pension: weekly amount, annual amount, NI years completed
  - Years to retirement
  - Income gap: target vs projected (with separate after-SPA variant if applicable)
  - Total pension value

#### Grouped Actions: `resources/js/components/Plans/Retirement/RetirementGroupedActions.vue`
- **Props:** `actions` (Array), `pensionProjections` (Array), `whatIf` (Object)
- **Emits:** `toggle`
- Two modes:
  - **Single pension:** All actions shown inline with a single pension growth projection chart
  - **Multiple pensions:** Per-pension groups with individual charts + portfolio-level projection chart
- Portfolio projection factors in `additional_monthly_contribution` from projected scenario

#### Pension Growth Projection Chart: `resources/js/components/Plans/Retirement/PensionGrowthProjectionChart.vue`
- **Props:** `projection` (Object), `enabledActionCount` (Number), `totalActionCount` (Number)
- Line chart showing: current trajectory vs with-actions trajectory
- Linear interpolation for partial toggles (same pattern as investment fee chart)
- Uses `CHART_COLORS` from designSystem

#### What-If Controls: `resources/js/components/Plans/Retirement/RetirementWhatIfControls.vue`
- **Props:** `scenario` (Object)
- Displays: Projected Annual Income, Income Gap, Total Pension Value, At Retirement, Additional Monthly Contribution

### 5.4 Backend Service: `RetirementPlanService` (`app/Services/Plans/RetirementPlanService.php`)

**Extends:** `BasePlanService`

**Constructor dependencies:**
- `RetirementAgent $retirementAgent`
- `PensionProjector $projector` (injected but not directly used in this file)
- `TaxConfigService $taxConfig` (injected but not directly used in this file)

#### `generatePlan(int $userId, array $options = []): array`

**Database queries:**
- `User::findOrFail($userId)` -- `users`
- `RetirementProfile::where('user_id', $userId)->first()` -- `retirement_profiles`
- `DCPension::where('user_id', $userId)->get()` -- `dc_pensions`

**Agent call:**
- `$this->retirementAgent->analyze($userId)` -- if not successful, returns early error structure

**Logic:**
1. Loads user, profile, and DC pensions
2. Computes `$yearsToRetirement = max(0, target_retirement_age - current_age)`
3. Calls agent analysis
4. Builds completeness, metadata, executive summary, current situation
5. Gets recommendations from agent
6. Structures and filters actions
7. Builds pension growth projections
8. Builds what-if data
9. Builds conclusion

**Returns:** `{ metadata, completeness_warning, executive_summary, current_situation, actions, pension_projections, what_if, conclusion }`

#### `checkDataCompleteness(int $userId): array`

**Database queries:**
- `RetirementProfile::where('user_id', $userId)->exists()` -- `retirement_profiles`
- `DCPension::where('user_id', $userId)->exists()` OR `DBPension::where('user_id', $userId)->exists()` -- `dc_pensions`, `db_pensions`
- `User::find($userId)` -- `users` (income check)
- `RetirementProfile::where('user_id', $userId)->where('target_retirement_income', '>', 0)->exists()` -- `retirement_profiles`

**Checks (4 items):**

| Field | Label | Description | Link | Checks |
|-------|-------|-------------|------|--------|
| `retirement_profile` | Retirement Profile | Set up your retirement profile | `/retirement` | Has retirement profile |
| `pensions` | Pension Details | Add your pension details | `/retirement` | Has DC or DB pension |
| `income` | Income Details | Add your income | `/profile` | Has employment or self-employment income |
| `target_income` | Target Retirement Income | Set your target retirement income | `/retirement` | Has target > 0 (only checked if profile exists) |

#### `buildExecutiveSummary(User $user, array $data, int $userId): array`

**Additional queries:**
- `DCPension::where('user_id', $userId)->get()` -- `dc_pensions`
- `DBPension::where('user_id', $userId)->get()` -- `db_pensions`
- `StatePension::where('user_id', $userId)->first()` -- `state_pensions`

**Narrative construction:**
- Retirement timeline: years to retirement, target age
- DC pension arrangements: each pension with current value
- DB pension arrangements: each with projected annual pension
- State pension: weekly amount, NI years completed
- Total pension pot value
- Income target and gap analysis (special handling for early retirement before State Pension Age)
- Employer contributions summary

#### `buildPensionGrowthProjections(array $actions, Collection $dcPensions, int $yearsToRetirement): array`

Returns empty if `$yearsToRetirement <= 0` or no DC pensions.

**Per DC pension:**
- Net growth rate: `0.05 - (platform_fee_percent / 100)`
- Annual contribution: `monthly_contribution_amount * 12` or `annual_salary * (employee_pct + employer_pct) / 100`
- Additional annual contribution from enabled account-level actions: `count * 2400` (200/month * 12)
- Builds two series (current vs with-actions) using iterative compound: `(prev + annual_contribution) * (1 + netGrowthRate)`

**Returns per pension:** `{ pension_id, pension_name, pension_type, current_value, annual_contribution, growth_rate, years, projection_label, current_series[], with_actions_series[], projection_difference }`

#### `buildWhatIfData(array $data, array $currentSituation, array $enabledActions, int $yearsToRetirement): array`

**Calculations:**
- Growth rate: `0.05` (5%)
- Contribution increase per contribution-related action: `200/month`
- Consolidation efficiency gain: `2%` of projected income
- Tax optimisation gain: `3%` of projected income
- Default other action: `1%` of projected income
- Annuity/withdrawal rate: `0.04` (4%)
- Projects DC pension future value using `projectDcValue()`
- Estimates income from additional contributions using `estimateIncomeFromContribution()`

**Returns:**
- `current_scenario`: `{ projected_annual_income, income_gap, total_dc_value, dc_value_at_retirement }`
- `projected_scenario`: `{ projected_annual_income, income_gap, total_dc_value, dc_value_at_retirement, additional_monthly_contribution }`
- `is_approximate: true`
- `frontend_calc_params`: `{ current_dc_value, growth_rate: 0.05, years, annuity_rate: 0.04 }`

### 5.5 RetirementAgent (`app/Agents/RetirementAgent.php`)

**Constructor dependencies (11 services):**
- `PensionProjector`
- `AnnualAllowanceChecker`
- `ContributionOptimizer`
- `DecumulationPlanner` (injected but not directly called)
- `PensionPortfolioAnalyzer`
- `TaxConfigService`
- `PortfolioAnalyzer` (injected but not directly called)
- `MonteCarloSimulator` (injected but not directly called)
- `AssetAllocationOptimizer` (injected but not directly called)
- `FeeAnalyzer` (injected but not directly called)
- `TaxEfficiencyCalculator` (injected but not directly called)

**Cache TTL:** 3600s (1 hour)

#### `analyze(int $userId): array`

**Database queries:**
- `RetirementProfile::where('user_id', $userId)->first()` -- `retirement_profiles`
- `DCPension::where('user_id', $userId)->get()` -- `dc_pensions`
- `DBPension::where('user_id', $userId)->get()` -- `db_pensions`
- `StatePension::where('user_id', $userId)->first()` -- `state_pensions`

**Service calls:**
- `$this->projector->projectTotalRetirementIncome($userId)` -- income projection
- `$this->taxConfig->getTaxYear()` for current tax year
- `$this->allowanceChecker->checkAnnualAllowance($userId, $taxYear)`

**Computations:**
- `incomeAtRetirement = DC income + DB income (+ state pension only if retiring at/after SPA)`
- `incomeGap = max(0, target - projected)`
- `incomeAfterSPA` for pre-SPA retirees
- `yearsToRetirement`, `currentDcValue`

#### `generateRecommendations(array $analysisData): array`

**Additional queries:**
- `RetirementProfile::find(...)` -- `retirement_profiles`
- `DCPension::where('user_id', ...)->get()` -- `dc_pensions`
- `StatePension::where('user_id', ...)->first()` -- `state_pensions`

**Service call:** `$this->optimizer->optimizeContributions($profile, $dcPensions)`

**Recommendation types generated:**
- `employer_match` -> "Maximise Employer Pension Match" (scope: account, linked to specific DC pension)
- `start_contributions` -> "Start Pension Contributions" (scope: account)
- `contribution_increase` -> "Increase Pension Contributions" (scope: portfolio)
- `tax_relief` -> "Optimise Pension Tax Relief" (scope: portfolio)
- Annual allowance exceeded warning
- NI gaps (only if won't reach naturally): calculated via `yearsUntilSPA` vs `yearsShort`
- Income gap > 10% of target: suggests later retirement (min current+3, max 70)

### 5.6 Database Tables Involved

| Table | Used For |
|-------|----------|
| `users` | User profile, income, date of birth |
| `retirement_profiles` | Target retirement age, target income, salary |
| `dc_pensions` | Current values, contributions, employer match, fees |
| `db_pensions` | Accrued annual pension, retirement age |
| `state_pensions` | NI years, forecast, state pension age |
| `holdings` | Pension fund holdings (via polymorphic relationship on DC pensions) |
| `tax_configurations` | Annual allowance, tax year |

---

## 6. Estate Plan

### 6.1 Overview

The Estate Plan analyses a user's inheritance tax (IHT) position. It aggregates all estate assets, calculates IHT liability using current HMRC rates and allowances (NRB, RNRB, spouse exemption), and generates a 7-step IHT mitigation strategy covering charitable bequests, liquidity assessment, life cover, annual gifting, whole-of-life cover, PETs, and CLTs.

### 6.2 Route

- **Frontend:** `/plans/estate` (name: `EstatePlan`)
- **API:** `GET /api/plans/estate`
- **Recalculate:** `POST /api/plans/estate/recalculate`

### 6.3 Gate Checks

The estate plan has two gate checks that can return `not_applicable`:

1. **Age gate:** If the user is under 35, returns `{ not_applicable: true, not_applicable_reason: "Estate planning typically becomes relevant from age 35 onwards..." }`
2. **IHT liability gate:** If `IHTCalculationService->calculate()` returns liability <= 0, returns `{ not_applicable: true, not_applicable_reason: "Based on your current estate position, there is no projected Inheritance Tax liability..." }`

### 6.4 Frontend Files

#### View: `resources/js/views/Plans/EstatePlan.vue`
- **Components used:** `PlanPageLayout`, `EstatePlanContent`
- **Mixins:** `planPrintMixin`
- **Store interactions:** Same pattern with type `'estate'`
- **Additional logic:** Handles `plan.not_applicable` state -- shows info banner with `not_applicable_reason` text
- **On mount:** Dispatches `fetchPlan('estate')`

#### Content: `resources/js/components/Plans/Estate/EstatePlanContent.vue`
- **Props:** `plan` (Object)
- **Emits:** `toggle-action`
- **Children:** `PlanMissingDataPrompt`, `PlanExecutiveSummary`, `EstateCurrentSituation`, `EstateGroupedActions`, `PlanConclusion`

#### Current Situation: `resources/js/components/Plans/Estate/EstateCurrentSituation.vue`
- **Props:** `situation` (Object)
- Displays:
  - Estate value: gross, net, liabilities
  - IHT calculation: liability, NRB, RNRB, spouse exemption, effective rate
  - Asset breakdown: liquid, semi-liquid, illiquid
  - Life cover: in trust, not in trust, policy count
  - Charitable giving analysis

#### Grouped Actions: `resources/js/components/Plans/Estate/EstateGroupedActions.vue`
- **Props:** `actions` (Array), `whatIf` (Object)
- **Emits:** `toggle`
- **Key computed:** `projectedScenario` -- **Client-side** calculation of IHT liability reduction. Uses `frontend_calc_params.savings_map` to sum savings from enabled actions. Computes projected IHT liability, effective rate, estate to beneficiaries, and total mitigation savings entirely on the frontend without an API call. This enables instant feedback as the user toggles actions.

#### What-If Controls: `resources/js/components/Plans/Estate/EstateWhatIfControls.vue`
- **Props:** `scenario` (Object), `showSavings` (Boolean)
- Displays: IHT Liability, Effective Tax Rate, Estate to Beneficiaries, Total Mitigation Savings (when showSavings=true)

### 6.5 Backend Service: `EstatePlanService` (`app/Services/Plans/EstatePlanService.php`)

**Extends:** `BasePlanService`

**Constructor dependencies:**
- `EstateAgent $estateAgent`
- `IHTCalculationService $ihtCalculator`
- `TaxConfigService $taxConfig` (injected but not directly used)

#### `generatePlan(int $userId, array $options = []): array`

**Database queries:**
- `User::with(['spouse'])->findOrFail($userId)` -- `users`

**Gate checks:**
1. Age < 35: returns `not_applicable` immediately
2. `$this->ihtCalculator->calculate($user)` -- if liability <= 0: returns `not_applicable`

**Agent call:**
- `$this->estateAgent->analyze($userId)` -- if not successful, returns early error structure

**Returns:** `{ metadata, completeness_warning, executive_summary, current_situation, actions, what_if, conclusion }`

#### `checkDataCompleteness(int $userId): array`

**Database queries:**
- `Will::where('user_id', $userId)->exists()` -- `wills`
- `User::find($userId)` then: `$user->properties()->exists()`, `$user->investmentAccounts()->exists()`, `$user->savingsAccounts()->exists()` -- `properties`, `investment_accounts`, `savings_accounts`
- `LifeInsurancePolicy::where('user_id', $userId)->exists()` -- `life_insurance_policies`

**Checks (3 items):**

| Field | Label | Description | Link | Checks |
|-------|-------|-------------|------|--------|
| `will` | Will Details | Add your will information | `/estate` | Has will record |
| `estate_assets` | Estate Assets | Add your assets | `/estate` | Has properties, investments, or savings |
| `life_insurance` | Life Insurance | Add your life insurance | `/protection` | Has life insurance policy |

#### `buildExecutiveSummary(User $user, array $data): array`

**Additional calls within summary:**
- `$this->estateAgent->analyze($user->id)` -- re-analyses to get recommendation count
- `$this->estateAgent->generateRecommendations($analysis)`

**Narrative construction:**
- Estate value overview (gross and net)
- IHT position: liability amount
- Allowances: NRB, RNRB
- Spouse exemption (if married)
- Life cover in trust
- Mitigation strategy count

#### `buildCurrentSituation(array $data): array`

**Returns:**
- `estate_value`: `{ gross, net, liabilities }`
- `iht_calculation`: `{ liability, nil_rate_band, residence_nil_rate_band, spouse_exemption, effective_rate }`
- `asset_breakdown`: `{ liquid, semi_liquid, illiquid }`
- `life_cover`: `{ cover_in_trust, cover_not_in_trust, policy_count, policies_in_trust, policies_not_in_trust }`
- `charitable_giving`: `{ status, current_percentage, threshold: 10, shortfall, potential_saving }`

#### `buildWhatIfData(array $data, array $enabledActions): array`

**Calculations:**
- Current: `toBeneficiaries = netEstate - ihtLiability`, `effectiveRate = (ihtLiability / grossEstate) * 100`
- Per enabled action: sums `estimated_impact`
- `projectedLiability = max(0, ihtLiability - totalSavings)`
- `projectedToBeneficiaries = netEstate - projectedLiability`

**Returns:**
- `current_scenario`: `{ iht_liability, effective_tax_rate, estate_to_beneficiaries }`
- `projected_scenario`: `{ iht_liability, effective_tax_rate, estate_to_beneficiaries, total_mitigation_savings }`
- `is_approximate: true`
- `frontend_calc_params`: `{ current_iht_liability, net_estate, gross_estate, savings_map }` -- `savings_map` is `{ actionId => saving_amount }`

### 6.6 EstateAgent (`app/Agents/EstateAgent.php`)

**Constructor dependencies (7 services):**
- `IHTCalculationService`
- `EstateAssetAggregatorService`
- `ComprehensiveEstatePlanService` (injected but never actually called)
- `GiftingStrategyOptimizer`
- `PersonalizedTrustStrategyService`
- `WillAnalysisService`
- `TaxConfigService`

#### `analyze(int $userId): array`

**Database queries:**
- `User` with: `ihtProfile`, `assets`, `properties`, `liabilities`, `mortgages`, `spouse`, `familyMembers`, `trusts`, `gifts`
- `LifeInsurancePolicy` -- 3 queries:
  - User's policies where `in_trust = true`
  - User's policies where `in_trust = false` or null
  - Spouse's policies where `in_trust = true` (sum of `sum_assured`)
- `Will::where('user_id', $userId)->with('bequests')->first()` -- `wills` + `bequests`
- `$user->familyMembers()->where('relationship', 'child')->count()` -- `family_members`

**Service calls:**
- `$this->assetAggregator->gatherUserAssets($user)` then `calculateUserLiabilities($user)`
- `$this->ihtCalculator->calculate($user)` (wrapped in try/catch)
- `$this->trustStrategyService->generatePersonalizedTrustStrategy(...)` (wrapped in try/catch)
- `$this->giftingOptimizer->calculateOptimalGiftingStrategy(...)` (wrapped in try/catch)
- `$this->willAnalysisService->detectTrustTriggeringWishes($will)` (wrapped in try/catch)
- `$this->willAnalysisService->analyzeCharitableBequests($user, $netEstate)` (wrapped in try/catch)

**Returns:** `summary`, `asset_breakdown`, `iht_calculation`, `trust_recommendations`, `gifting_opportunities`, `trust_wish_triggers`, `charitable_analysis`, `life_cover`, `profile`

#### `generateRecommendations(array $analysisData): array`

**7-step IHT mitigation decision tree** (only if `iht_liability > 0`):

Each step tracks `$remainingLiability`, reducing it as strategies are applied:

| Step | Method | Strategy | Conditions | Tax Config Used |
|------|--------|----------|------------|-----------------|
| 1 | `step1CharitableBequestCheck()` | Increase charitable bequests to reduce IHT from 40% to 36% | Charitable giving below 10% threshold | N/A |
| 2 | `step2LiquidityAssessment()` | Liquidity warning | Liquid assets < 50% of IHT liability | N/A |
| 3 | `step3ExistingLifeCover()` | Place existing policies in trust | Policies exist not in trust | N/A |
| 4 | `step4AnnualGiftingStrategy()` | Annual exemption gifting (first resort) | Always recommended if liability exists | `TaxConfigService->getInheritanceTax()['annual_exemption']` |
| 5 | `step5LifeCoverStrategy()` | Whole of life cover in trust (second resort) | User age <= 50 | N/A |
| 6 | `step6PETGiftingStrategy()` | Potentially Exempt Transfers (third resort) | At least 1 seven-year cycle available | `TaxConfigService->getInheritanceTax()['nil_rate_band']` |
| 7 | `step7CLTIntoTrust()` | Chargeable Lifetime Transfer (last resort) | Remaining liability exists | `TaxConfigService->getInheritanceTax()['nil_rate_band']`, CLT rate |

Also adds recommendations from:
- Trust wish triggers from will analysis
- Low health scores (<50)

#### Estate Health Score Calculation

Starts at 100, deducts for:
- No IHT profile: -20
- High IHT ratio (>30%: -25, >20%: -15, >10%: -10)
- No trusts when estate > 650k: -10
- Married but no spouse linked: -5
- Liquidity risk (liquid < 50% of IHT): -15

Clamped to 0-100.

### 6.7 Database Tables Involved

| Table | Used For |
|-------|----------|
| `users` | User profile, date of birth, spouse link |
| `wills` | Will existence, spouse beneficiary status |
| `bequests` | Will beneficiary details |
| `properties` | Property values for estate |
| `mortgages` | Outstanding mortgage balances |
| `investment_accounts` | Investment values for estate |
| `savings_accounts` | Savings values for estate |
| `life_insurance_policies` | Life cover, trust status |
| `trusts` | Trust arrangements |
| `assets` | Estate assets |
| `gifts` | Previous gifts (PET/CLT tracking) |
| `liabilities` | Estate liabilities |
| `iht_profiles` | Marital status, home ownership, NRB/RNRB transfers |
| `iht_calculations` | Cached IHT calculation results |
| `family_members` | Dependant information |
| `business_interests` | Business values for estate |
| `chattels` | Chattel values for estate |
| `cash_accounts` | Cash account values for estate |
| `tax_configurations` | IHT rates, NRB, RNRB, annual exemption |

---

## 7. Goal Plan

### 7.1 Overview

The Goal Plan is unique among the plans because it operates on a per-goal basis rather than at the module level. Each goal (e.g., emergency fund, house deposit, education fund) gets its own plan with progress tracking, affordability analysis, and strategy recommendations.

### 7.2 Route

- **Frontend:** `/plans/goal/:goalId` (name: `GoalPlan`, props: true)
- **API:** `GET /api/plans/goal/{goalId}`
- **Recalculate:** `POST /api/plans/goal/{goalId}/recalculate`

### 7.3 Frontend Files

#### View: `resources/js/views/Plans/GoalPlan.vue`
- **Props route:** `goalId` from `$route.params.goalId`
- **Components used:** `PlanPageLayout`, `GoalPlanContent`
- **Mixins:** `planPrintMixin`
- **Store interactions:** `mapGetters('plans', ['getGoalPlan', 'isLoading'])`, `mapActions('plans', ['fetchGoalPlan', 'toggleAction'])`
- **Computed:** `plan` from `getGoalPlan(goalId)`, `goalName` from `plan?.goal?.name`
- **Toggle action:** Uses planKey `goal_${goalId}`
- **On mount:** Dispatches `fetchGoalPlan(goalId)`

#### Content: `resources/js/components/Plans/Goals/GoalPlanContent.vue`
- **Props:** `plan` (Object)
- **Emits:** `toggle-action`
- **Data:** `chartMetrics` -- `[{key: 'monthly_contribution', label: 'Monthly'}, {key: 'total_contributions', label: 'Total'}]`
- **Children:** `PlanMissingDataPrompt`, `PlanExecutiveSummary`, `GoalCurrentSituation`, `PlanActionsList`, `PlanWhatIfComparison`, `PlanConclusion`, `GoalWhatIfControls`

#### Current Situation: `resources/js/components/Plans/Goals/GoalCurrentSituation.vue`
- **Props:** `situation` (Object), `goal` (Object)
- Displays:
  - Goal details: name, type, priority, target amount, current amount, target date, monthly contribution
  - Progress bar with percentage
  - On-track indicator
  - Months remaining
  - Estimated completion date
  - Affordability category and surplus
  - Linked accounts (savings and investment)

#### What-If Controls: `resources/js/components/Plans/Goals/GoalWhatIfControls.vue`
- **Props:** `scenario` (Object)
- Displays: Monthly Contribution, Months to Goal, Estimated Completion (formatted date), Total Contributions, Lump Sum

### 7.4 Backend Service: `GoalPlanService` (`app/Services/Plans/GoalPlanService.php`)

**Extends:** `BasePlanService`

**Constructor dependencies:**
- `GoalsAgent $goalsAgent`
- `GoalProgressService $progressService`
- `GoalAffordabilityService $affordabilityService`
- `GoalStrategyService $strategyService`

#### `generatePlan(int $userId, array $options = []): array`

**Requires:** `$options['goal_id']` -- throws `InvalidArgumentException` if missing.

**Database queries:**
- `User::findOrFail($userId)` -- `users`
- `Goal::where('id', $goalId)->where(fn: user_id = userId OR joint_owner_id = userId)->firstOrFail()` -- `goals`

**Service calls:**
- `$this->progressService->calculateProgress($goal)` -- progress metrics
- `$this->affordabilityService->analyzeAffordability($goal, $user)` -- affordability assessment
- `$this->strategyService->getStrategyForGoal($goalId)` -- strategy (result not used directly)

**Returns:** `{ metadata, goal, completeness_warning, executive_summary, current_situation, actions, what_if, conclusion }`

#### `checkDataCompleteness(int $userId, ?int $goalId = null): array`

**Database query:** `Goal::find($goalId)`

**Checks (3 items):**

| Field | Label | Description | Link | Checks |
|-------|-------|-------------|------|--------|
| `target_amount` | Target Amount | Set a target amount for your goal | `/goals` | `target_amount > 0` |
| `target_date` | Target Date | Set a target date | `/goals` | `target_date` not null |
| `linked_accounts` | Linked Accounts | Link a savings or investment account | `/goals` | `linked_savings_account_id` or `linked_investment_account_id` not null |

If no goal found at all, returns single missing item for 'goal'.

#### `buildExecutiveSummary(User $user, Goal $goal, array $progress, array $affordability): array`

**Additional queries:**
- `SavingsAccount::find($goal->linked_savings_account_id)` -- `savings_accounts` (if linked)
- `InvestmentAccount::find($goal->linked_investment_account_id)` -- `investment_accounts` (if linked)

**Narrative construction:**
- Goal name and progress percentage
- Target and current amounts
- Linked account details
- On-track status
- Affordability assessment (comfortable/moderate, challenging, stretch)
- Monthly required contribution

#### `buildWhatIfData(Goal $goal, array $progress, array $affordability, array $enabledActions): array`

**Calculations per action:**
- Contribution/increase actions: `+50/month`
- Lump sum/transfer actions: `+500 one-off`
- Default other actions: `+25/month`

**Months to goal:** `ceil(remaining / monthlyContribution)` for current, `ceil(adjustedRemaining / newMonthly)` for projected
**Completion dates:** `now()->addMonths()` formatted as `Y-m-d`

**Returns:**
- `current_scenario`: `{ months_to_goal, completion_date, monthly_contribution, total_contributions }`
- `projected_scenario`: `{ months_to_goal, completion_date, monthly_contribution, lump_sum, total_contributions }`
- `is_approximate: true`

### 7.5 GoalsAgent (`app/Agents/GoalsAgent.php`)

**Constructor dependencies (4 services):**
- `GoalAssignmentService` (injected but not directly called)
- `GoalAffordabilityService`
- `GoalProgressService` (injected but not directly called)
- `GoalRiskService` (injected but not directly called)

#### `analyze(int $userId): array`

**Database queries:**
- `User::findOrFail($userId)` -- `users`
- `Goal::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get()` -- `goals` (includes joint-owned)

**Returns:** `has_goals`, `summary`, `by_module`, `top_goals`, `affordability`, `streaks`, `completed_count`, `goals_count`

#### `generateRecommendations(array $analysisData): array`

**Recommendations generated:**
1. No goals at all: "Set Your First Financial Goal"
2. Goals behind schedule: "Review Behind Schedule Goals"
3. Overcommitted affordability: "Rebalance Goal Contributions"
4. No emergency fund goal: "Establish an Emergency Fund Goal"
5. Good contribution streaks (>=3 months): positive reinforcement

### 7.6 Database Tables Involved

| Table | Used For |
|-------|----------|
| `users` | User profile, income |
| `goals` | Goal details, target amounts, dates, contributions, linked accounts |
| `goal_contributions` | Contribution tracking, streaks |
| `savings_accounts` | Linked savings account details |
| `investment_accounts` | Linked investment account details |

---

## 8. Holistic Plan

### 8.1 Overview

The Holistic Plan is a cross-module coordination plan that analyses all four primary modules (Protection, Investment, Savings, Retirement), identifies conflicts between recommendations, ranks all recommendations by priority, allocates available cash flow surplus across competing demands, and produces an integrated action plan.

Note: The Estate module and Goals module are NOT currently integrated into the CoordinatingAgent. Estate data uses hardcoded placeholder values.

### 8.2 Route

- **Frontend:** `/holistic-plan` (name: `HolisticPlan`)
- **API:** `POST /api/holistic/plan`

### 8.3 Frontend Files

#### View: `resources/js/views/HolisticPlan.vue`
- **Store:** `holistic` module (separate from `plans`)
- **State accessed:** `plan`, `recommendations`, `cashFlowAnalysis`, `loading`, `error`
- **Getters:** `executiveSummary`, `netWorthProjection`, `riskAssessment`, `actionPlan`, `activeRecommendations`
- **Actions:** `fetchPlan`, `fetchCashFlowAnalysis`, `markRecommendationDone`, `markRecommendationInProgress`, `dismissRecommendation`, `updateRecommendationNotes`, `clearError`
- **Components:** `ExecutiveSummary`, `PrioritizedRecommendations`, `CashFlowAllocationChart`, `NetWorthProjectionChart`, `RiskAssessment`, `ModuleSummaries` (all from `components/Holistic/`)
- **Data:** `activeTab` with 5 tabs: action-plan, cashflow, projection, risk, modules
- **Events from children:** `mark-done`, `mark-in-progress`, `dismiss`, `update-notes`

### 8.4 Backend: CoordinatingAgent (`app/Agents/CoordinatingAgent.php`)

**Constructor dependencies (9 parameters):**
- 4 coordination services: `ConflictResolver`, `PriorityRanker`, `HolisticPlanner`, `CashFlowCoordinator`
- 4 module agents: `ProtectionAgent`, `InvestmentAgent`, `SavingsAgent`, `RetirementAgent`
- 1 config service: `TaxConfigService`

Note: **EstateAgent and GoalsAgent are NOT injected.** Estate analysis uses hardcoded placeholder values.

#### `orchestrateAnalysis(int $userId): array`

1. Calls `collectModuleAnalysis($userId)` -- calls `analyze()` on all 4 agents (each wrapped in try/catch)
2. Estate analysis: hardcoded `{ net_worth: 350000, iht_liability: 10000, monthly_income: 4500, monthly_expenses: 3200, monthly_surplus: 1300 }`
3. `$this->cashFlowCoordinator->calculateAvailableSurplus($userId)`
4. Extracts all recommendations from all modules
5. `$this->conflictResolver->identifyConflicts($allRecommendations)` -- detects cashflow, ISA allowance, protection_vs_savings conflicts
6. `resolveConflicts($allRecommendations, $conflicts)`
7. `$this->priorityRanker->rankRecommendations(...)` -- scores: urgency 40%, impact 30%, ease 20%, user_priority 10%
8. `$this->cashFlowCoordinator->optimizeContributionAllocation($surplus, $demands)` -- priority: Emergency fund > Protection > Pension > Investment > Estate
9. `$this->cashFlowCoordinator->identifyCashFlowShortfalls($allocation)`

#### `generateHolisticPlan(int $userId): array`

1. Calls `orchestrateAnalysis()`
2. `$this->holisticPlanner->createHolisticPlan(...)` -- executive summary, financial snapshot, net worth projection (baseline 4% vs optimised 6% over 30 years), risk assessment (5 areas: protection, emergency_fund, retirement, investment, iht)
3. `$this->priorityRanker->createActionPlan(...)` -- groups: immediate (urgency >=80), short_term (60-79), medium_term (40-59), long_term (<40)

### 8.5 Backend Controller: `HolisticPlanningController` (`app/Http/Controllers/Api/HolisticPlanningController.php`)

**Dependencies:** `CoordinatingAgent`, `CashFlowCoordinator`

| Method | Route | Cache | Logic |
|--------|-------|-------|-------|
| `analyze` | `POST /api/holistic/analyze` | 1 hour | Calls `coordinatingAgent->orchestrateAnalysis()` |
| `plan` | `POST /api/holistic/plan` | 24 hours | Calls `coordinatingAgent->generateHolisticPlan()`, persists recommendations to `recommendation_tracking` table |
| `recommendations` | `GET /api/holistic/recommendations` | None | Queries `RecommendationTracking` (active) |
| `cashFlowAnalysis` | `GET /api/holistic/cash-flow` | None | Calls `cashFlowCoordinator` methods |
| `markRecommendationDone` | `POST /api/holistic/recommendations/{id}/done` | None | Updates status on `recommendation_tracking` |
| `markRecommendationInProgress` | `POST /api/holistic/recommendations/{id}/in-progress` | None | Updates status |
| `dismissRecommendation` | `POST /api/holistic/recommendations/{id}/dismiss` | None | Updates status |
| `updateRecommendationNotes` | `PUT /api/holistic/recommendations/{id}/notes` | None | Updates notes field |
| `completedRecommendations` | `GET /api/holistic/recommendations/completed` | None | Queries completed recs |

### 8.6 Coordination Services

#### CashFlowCoordinator (`app/Services/Coordination/CashFlowCoordinator.php`)

| Method | Purpose |
|--------|---------|
| `calculateAvailableSurplus(int $userId)` | Monthly income - expenditure - committed contributions |
| `optimizeContributionAllocation(float $surplus, array $demands)` | Priority: Emergency fund > Protection > Pension > Investment > Estate |
| `identifyCashFlowShortfalls(array $allocation)` | Flags under-funded demands |
| `calculateCommittedContributions(int $userId)` | Sums pension + protection premiums + regular savings |

#### ConflictResolver (`app/Services/Coordination/ConflictResolver.php`)

| Method | Purpose |
|--------|---------|
| `identifyConflicts(array $recommendations)` | Detects: cashflow, isa_allowance, protection_vs_savings conflicts |
| `resolveProtectionVsSavings(array $conflict)` | Splits allocation by adequacy scores |
| `resolveContributionConflicts(array $conflict)` | Priority-ordered with partial funding |
| `resolveISAAllocation(array $conflict)` | Cash vs S&S ISA split by emergency fund adequacy and risk tolerance |

#### PriorityRanker (`app/Services/Coordination/PriorityRanker.php`)

| Method | Purpose |
|--------|---------|
| `rankRecommendations(array $allRecommendations, array $userContext)` | Scores: urgency (40%), impact (30%), ease (20%), user_priority (10%) |
| `createActionPlan(array $rankedRecommendations)` | Groups: immediate (>=80), short_term (60-79), medium_term (40-59), long_term (<40) |

#### HolisticPlanner (`app/Services/Coordination/HolisticPlanner.php`)

| Method | Purpose |
|--------|---------|
| `createHolisticPlan(int $userId, array $allAnalysis)` | Executive summary, financial snapshot, net worth projection, risk assessment, module summaries |
| `generateExecutiveSummary(array $allAnalysis)` | Key strengths, vulnerabilities, top priorities |
| `projectNetWorthTrajectory(array $allAnalysis)` | Baseline (4% growth) vs optimised (6% growth) over 30 years |
| `assessOverallRisk(array $allAnalysis)` | 5 risk areas: protection, emergency_fund, retirement, investment, iht |

### 8.7 Database Tables Involved

All tables from the 4 integrated modules (Protection, Investment, Savings, Retirement) plus:

| Table | Used For |
|-------|----------|
| `recommendation_tracking` | Persisted recommendations with status tracking |
| `expenditure_profiles` | Monthly expenditure breakdown |

---

## 9. Plans Dashboard

### 9.1 Overview

The Plans Dashboard (`/plans`) is the entry point showing all available plans as clickable cards with data completeness indicators.

### 9.2 View: `resources/js/views/Plans/PlansDashboard.vue`

**Components used:** `AppLayout`, `PlanDashboardCard`

**Store interactions:**
- `mapGetters('plans', ['planStatuses'])` -- from plans store
- `mapGetters('goals', { goals: 'activeGoals' })` -- from goals store
- `mapActions('plans', ['fetchDashboardStatuses'])` -- fetches completeness
- `mapActions('goals', ['fetchGoals'])` -- fetches goals list

**On mount:** Fetches both dashboard statuses and goals.

**Template:** Renders 4 module plan cards in a grid (Investment, Protection, Retirement, Estate), plus dynamic Goal Plan cards for each active goal. Each card shows:
- Module name and description
- Completeness percentage (Ready >=75%, Partial Data >=25%, Needs Data <25%)
- Progress bar
- "View Plan" link

**API called:** `GET /api/plans/statuses` which invokes `checkDataCompleteness()` on all 4 plan services and returns combined results.

---

## 10. Legacy Plans

### 10.1 Legacy Investment & Savings Plan

**Route:** `/plans/investment-savings` (redirects to `/plans/investment`)

**Controller:** `InvestmentSavingsPlanController` (`app/Http/Controllers/Api/Plans/InvestmentSavingsPlanController.php`)
- Dependencies: `InvestmentSavingsPlanService`
- Cache: `investment_savings_plan_{userId}`, 1800s TTL

**Service:** `InvestmentSavingsPlanService` (`app/Services/Plans/InvestmentSavingsPlanService.php`)
- Does NOT extend `BasePlanService`
- Dependencies: `InvestmentAgent`, `SavingsAgent`
- Builds standalone report with: executive summary, investment portfolio, holdings overview, performance, savings summary, emergency fund analysis, rate comparisons, combined action plan
- Contains its own portfolio health score calculation and action prioritisation

**View:** `resources/js/views/Plans/InvestmentSavingsPlan.vue`
- Wraps `InvestmentSavingsPlanView` component
- Uses `html2pdf.js` for PDF generation

**Legacy Component:** `resources/js/components/Plans/InvestmentSavingsPlanView.vue`
- Self-contained with own loading/error/plan state
- Calls `plansService.generateInvestmentSavingsPlan()` directly
- Shows "Work in Progress" scaffold warning
- Note: This is the ONLY component that displays scores (health score, diversification score) which violates the CLAUDE.md rule about no scores in UI

### 10.2 Legacy Comprehensive Protection Plan

**Route:** `/protection-plan` (name: `ComprehensiveProtectionPlan`)

**Service:** `ComprehensiveProtectionPlanService` (`app/Services/Protection/ComprehensiveProtectionPlanService.php`)

**View:** `resources/js/views/Protection/ComprehensiveProtectionPlan.vue`
- Calls `protectionService.getComprehensiveProtectionPlan()` directly
- Uses `html2pdf.js` for PDF
- Contains inline coverage analysis, adequacy scores, strategy recommendations

### 10.3 Legacy Comprehensive Estate Plan

**Route:** `/estate-plan` (name: `ComprehensiveEstatePlan`)

**Service:** `ComprehensiveEstatePlanService` (`app/Services/Estate/ComprehensiveEstatePlanService.php`)

**View:** `resources/js/views/Estate/ComprehensiveEstatePlan.vue`
- Uses `estateService` directly
- `html2pdf.js` for PDF
- Contains IHT analysis, gifting strategy, trust recommendations, life cover

### 10.4 Legacy DB-Persisted Investment Plan

**Controller:** `InvestmentPlanController` (`app/Http/Controllers/Api/Investment/InvestmentPlanController.php`)
- Dependencies: `InvestmentPlanGenerator`
- Methods: generatePlan, getLatestPlan, getPlanById, getAllPlans, deletePlan

**Service:** `InvestmentPlanGenerator` (`app/Services/Investment/InvestmentPlanGenerator.php`)
- Dependencies: `PortfolioAnalyzer`, `TaxOptimizationAnalyzer`, `FeeAnalyzer`, `DriftAnalyzer`, `PerformanceAttributionAnalyzer`, `AssetLocationOptimizer`, `GoalProgressAnalyzer`
- Generates 8-section plan and **persists to `investment_plans` table** via `InvestmentPlan::create()`
- Calculates weighted portfolio health score: diversification (25%), risk alignment (20%), tax efficiency (20%), fee efficiency (15%), goal progress (20%)

**Model:** `InvestmentPlan` (`app/Models/Investment/InvestmentPlan.php`)
- Table: `investment_plans`
- Fields: `user_id`, `plan_version`, `plan_data` (JSON), `portfolio_health_score`, `is_complete`, `completeness_score`, `generated_at`
- Relationships: `user()`, `recommendations()`

**Model:** `InvestmentRecommendation` (`app/Models/Investment/InvestmentRecommendation.php`)
- Table: `investment_recommendations`
- Fields: `user_id`, `investment_plan_id`, `category`, `priority`, `title`, `description`, `action_required`, `impact_level`, `potential_saving`, `estimated_effort`, `status`, `due_date`, `completed_at`, `dismissed_at`, `dismissal_reason`
- Scopes: `pending`, `completed`, `highPriority`

---

## 11. Complete File Index

### Backend: Controllers (5 files)

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Api/Plans/PlanController.php` | Central controller for current plans |
| `app/Http/Controllers/Api/Plans/InvestmentSavingsPlanController.php` | Legacy combined plan |
| `app/Http/Controllers/Api/Investment/InvestmentPlanController.php` | Legacy DB-persisted plan |
| `app/Http/Controllers/Api/Investment/InvestmentRecommendationController.php` | CRUD for investment recommendations |
| `app/Http/Controllers/Api/HolisticPlanningController.php` | Holistic cross-module planning |

### Backend: Services (10 files)

| File | Extends | Purpose |
|------|---------|---------|
| `app/Services/Plans/BasePlanService.php` | -- | Abstract base for all plan services |
| `app/Services/Plans/InvestmentPlanService.php` | BasePlanService | Investment + savings plan generation |
| `app/Services/Plans/ProtectionPlanService.php` | BasePlanService | Protection plan generation |
| `app/Services/Plans/RetirementPlanService.php` | BasePlanService | Retirement plan generation |
| `app/Services/Plans/EstatePlanService.php` | BasePlanService | Estate plan generation |
| `app/Services/Plans/GoalPlanService.php` | BasePlanService | Goal plan generation |
| `app/Services/Plans/WhatIfCalculator.php` | -- | What-if recalculation orchestrator |
| `app/Services/Plans/InvestmentSavingsPlanService.php` | -- (standalone) | Legacy combined plan |
| `app/Services/Investment/InvestmentPlanGenerator.php` | -- | Legacy DB-persisted plan generator |
| `app/Services/Protection/ComprehensiveProtectionPlanService.php` | -- | Comprehensive protection plan data |
| `app/Services/Estate/ComprehensiveEstatePlanService.php` | -- | Comprehensive estate plan data |

### Backend: Agents (8 files)

| File | Purpose | Cache TTL |
|------|---------|-----------|
| `app/Agents/BaseAgent.php` | Abstract base for all agents | Configurable |
| `app/Agents/InvestmentAgent.php` | Investment portfolio analysis | Default |
| `app/Agents/SavingsAgent.php` | Savings and emergency fund analysis | 1800s (30min) |
| `app/Agents/ProtectionAgent.php` | Protection needs and gaps analysis | Default |
| `app/Agents/RetirementAgent.php` | Retirement income projections | 3600s (1hr) |
| `app/Agents/EstateAgent.php` | Estate and IHT analysis | Default |
| `app/Agents/GoalsAgent.php` | Goal progress and affordability | Default |
| `app/Agents/CoordinatingAgent.php` | Cross-module coordination | Via child agents |

### Backend: Coordination Services (4 files)

| File | Purpose |
|------|---------|
| `app/Services/Coordination/HolisticPlanner.php` | Holistic plan generation |
| `app/Services/Coordination/PriorityRanker.php` | Recommendation ranking |
| `app/Services/Coordination/CashFlowCoordinator.php` | Cash flow allocation |
| `app/Services/Coordination/ConflictResolver.php` | Conflict detection and resolution |

### Backend: Models (4 plan-specific files)

| File | Table | Purpose |
|------|-------|---------|
| `app/Models/Investment/InvestmentPlan.php` | `investment_plans` | Persisted investment plans |
| `app/Models/Investment/InvestmentRecommendation.php` | `investment_recommendations` | Persisted recommendations |
| `app/Models/RecommendationTracking.php` | `recommendation_tracking` | Holistic recommendation tracking |
| `app/Models/SubscriptionPlan.php` | `subscription_plans` | Subscription pricing (not financial plan) |

### Frontend: Views (10 files)

| File | Route | Type |
|------|-------|------|
| `resources/js/views/Plans/PlansDashboard.vue` | `/plans` | Current |
| `resources/js/views/Plans/InvestmentPlan.vue` | `/plans/investment` | Current |
| `resources/js/views/Plans/ProtectionPlan.vue` | `/plans/protection` | Current |
| `resources/js/views/Plans/RetirementPlan.vue` | `/plans/retirement` | Current |
| `resources/js/views/Plans/EstatePlan.vue` | `/plans/estate` | Current |
| `resources/js/views/Plans/GoalPlan.vue` | `/plans/goal/:goalId` | Current |
| `resources/js/views/Plans/InvestmentSavingsPlan.vue` | (legacy) | Legacy |
| `resources/js/views/HolisticPlan.vue` | `/holistic-plan` | Current |
| `resources/js/views/Protection/ComprehensiveProtectionPlan.vue` | `/protection-plan` | Legacy |
| `resources/js/views/Estate/ComprehensiveEstatePlan.vue` | `/estate-plan` | Legacy |

### Frontend: Shared Components (13 files)

| File | Purpose |
|------|---------|
| `resources/js/components/Plans/Shared/PlanPageLayout.vue` | Standard plan page wrapper |
| `resources/js/components/Plans/Shared/PlanDashboardCard.vue` | Dashboard card with completeness |
| `resources/js/components/Plans/Shared/PlanExecutiveSummary.vue` | Executive summary renderer |
| `resources/js/components/Plans/Shared/PlanSectionHeader.vue` | Section header with colour |
| `resources/js/components/Plans/Shared/PlanActionCard.vue` | Individual action with toggle |
| `resources/js/components/Plans/Shared/PlanActionsList.vue` | Sorted list of actions |
| `resources/js/components/Plans/Shared/PlanConclusion.vue` | Conclusion with breakdown |
| `resources/js/components/Plans/Shared/PlanLoadingState.vue` | Loading spinner |
| `resources/js/components/Plans/Shared/PlanErrorState.vue` | Error with retry |
| `resources/js/components/Plans/Shared/PlanMissingDataPrompt.vue` | Missing data warning |
| `resources/js/components/Plans/Shared/PlanWhatIfComparison.vue` | What-if side-by-side |
| `resources/js/components/Plans/Shared/PlanWhatIfChart.vue` | ApexCharts bar chart |
| `resources/js/components/Plans/Shared/PlanWhatIfMetricRow.vue` | Metric row with delta |

### Frontend: Module-Specific Components (20 files)

| File | Module |
|------|--------|
| `resources/js/components/Plans/Investment/InvestmentPlanContent.vue` | Investment |
| `resources/js/components/Plans/Investment/InvestmentCurrentSituation.vue` | Investment |
| `resources/js/components/Plans/Investment/InvestmentGroupedActions.vue` | Investment |
| `resources/js/components/Plans/Investment/AccountFeeProjectionChart.vue` | Investment |
| `resources/js/components/Plans/Investment/InvestmentWhatIfControls.vue` | Investment |
| `resources/js/components/Plans/Protection/ProtectionPlanContent.vue` | Protection |
| `resources/js/components/Plans/Protection/ProtectionCurrentSituation.vue` | Protection |
| `resources/js/components/Plans/Protection/ProtectionWhatIfControls.vue` | Protection |
| `resources/js/components/Plans/Retirement/RetirementPlanContent.vue` | Retirement |
| `resources/js/components/Plans/Retirement/RetirementCurrentSituation.vue` | Retirement |
| `resources/js/components/Plans/Retirement/RetirementGroupedActions.vue` | Retirement |
| `resources/js/components/Plans/Retirement/PensionGrowthProjectionChart.vue` | Retirement |
| `resources/js/components/Plans/Retirement/RetirementWhatIfControls.vue` | Retirement |
| `resources/js/components/Plans/Estate/EstatePlanContent.vue` | Estate |
| `resources/js/components/Plans/Estate/EstateCurrentSituation.vue` | Estate |
| `resources/js/components/Plans/Estate/EstateGroupedActions.vue` | Estate |
| `resources/js/components/Plans/Estate/EstateWhatIfControls.vue` | Estate |
| `resources/js/components/Plans/Goals/GoalPlanContent.vue` | Goals |
| `resources/js/components/Plans/Goals/GoalCurrentSituation.vue` | Goals |
| `resources/js/components/Plans/Goals/GoalWhatIfControls.vue` | Goals |

### Frontend: Legacy Components (1 file)

| File | Purpose |
|------|---------|
| `resources/js/components/Plans/InvestmentSavingsPlanView.vue` | Legacy investment-savings display |

### Frontend: Mixin (1 file)

| File | Purpose |
|------|---------|
| `resources/js/components/Plans/Shared/planPrintMixin.js` | Print/PDF export via popup window |

### Frontend: Store and Service (3 files)

| File | Purpose |
|------|---------|
| `resources/js/store/modules/plans.js` | Vuex store for all plans |
| `resources/js/store/modules/holistic.js` | Vuex store for holistic plan |
| `resources/js/services/plansService.js` | API service for plans |

---

## 12. Complete Database Table Map

### Tables Directly Queried During Plan Generation

| Table | Investment Plan | Protection Plan | Retirement Plan | Estate Plan | Goal Plan | Holistic Plan |
|-------|:-:|:-:|:-:|:-:|:-:|:-:|
| `users` | X | X | X | X | X | X |
| `investment_accounts` | X | | | | | X |
| `holdings` | X | | | | | X |
| `risk_profiles` | X | | | | | X |
| `savings_accounts` | X | | | | X | X |
| `savings_goals` | | | | | | X |
| `goals` | X | | | | X | |
| `investment_goals` | | | | | | X |
| `isa_allowance_tracking` | | | | | | X |
| `savings_market_rates` | | | | | | X |
| `protection_profiles` | | X | | | | X |
| `life_insurance_policies` | | X | | X | | X |
| `critical_illness_policies` | | X | | | | X |
| `income_protection_policies` | | X | | | | X |
| `disability_policies` | | X | | | | X |
| `sickness_illness_policies` | | X | | | | X |
| `family_members` | | X | | X | | |
| `mortgages` | | X | | | | |
| `liabilities` | | X | | X | | |
| `retirement_profiles` | | | X | | | X |
| `dc_pensions` | | | X | | | X |
| `db_pensions` | | | X | | | X |
| `state_pensions` | | | X | | | X |
| `wills` | | | | X | | |
| `bequests` | | | | X | | |
| `properties` | | | | X | | |
| `trusts` | | | | X | | |
| `assets` | | | | X | | |
| `gifts` | | | | X | | |
| `iht_profiles` | | | | X | | |
| `iht_calculations` | | | | X | | |
| `business_interests` | | | | X | | |
| `chattels` | | | | X | | |
| `cash_accounts` | | | | X | | |
| `tax_configurations` | X | | X | X | | X |
| `investment_plans` | (legacy) | | | | | |
| `investment_recommendations` | (legacy) | | | | | |
| `recommendation_tracking` | | | | | | X |
| `expenditure_profiles` | | | | | | X |
| `goal_contributions` | | | | | X | |

### Key Architectural Observations

1. **Estate Plan queries the most tables** -- it needs to aggregate the entire estate across properties, investments, savings, business interests, chattels, cash accounts, trusts, gifts, liabilities, life insurance, and family members.

2. **Investment Plan combines two agents** -- it calls both InvestmentAgent and SavingsAgent, making it the most complex in terms of service orchestration for a single plan type.

3. **Protection Plan delegates to ComprehensiveProtectionPlanService** -- the ProtectionPlanService is a thin wrapper that restructures the comprehensive plan output into the standardised plan format.

4. **Holistic Plan calls 4 agents** -- via CoordinatingAgent, it triggers analysis across Protection, Investment, Savings, and Retirement. Estate uses hardcoded placeholder values. Goals are not included.

5. **Goal Plan is per-goal** -- unlike the other plans which are per-user, each goal has its own independent plan.

6. **InvestmentAgent's `generateRecommendations` breaks the BaseAgent contract** -- it takes an extra `$accountFeeAnalyses` parameter, meaning it cannot be called polymorphically through the BaseAgent interface.

7. **Cache varies by agent** -- RetirementAgent caches for 1 hour, SavingsAgent for 30 minutes, others use the default from `TaxDefaults::CACHE_TTL_STANDARD`.

8. **Frontend instant feedback vs backend precision** -- Estate plan's `EstateGroupedActions.vue` computes IHT changes client-side using `frontend_calc_params.savings_map` for instant toggle feedback. All plans mark client-calculated results as `is_approximate: true` and the Recalculate button triggers a full backend regeneration that sets `is_approximate: false`.

9. **Error handling varies across agents** -- EstateAgent wraps every service call in try/catch (most resilient). CoordinatingAgent wraps each module analysis. Others have no explicit error handling.

10. **Joint ownership** -- InvestmentPlanService and SavingsAgent query with `orWhere('joint_owner_id', ...)`. GoalsAgent also queries joint-owned goals. Other agents only query by `user_id`.
