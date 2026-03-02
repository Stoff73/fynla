# Coordination Module - Current State Documentation

**Last Updated:** 2026-02-18
**Module Version:** Part of Fynla v0.7.0
**Status:** Functional with cross-module analysis, conflict resolution, priority ranking, cashflow coordination, and holistic planning

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Database Schema](#2-database-schema)
3. [Models](#3-models)
4. [Controller](#4-controller)
5. [Agent](#5-agent)
6. [Services](#6-services)
7. [Validation Requests](#7-validation-requests)
8. [Vuex Store](#8-vuex-store)
9. [API Service](#9-api-service)
10. [Frontend Components](#10-frontend-components)
11. [Frontend Routing](#11-frontend-routing)
12. [Cross-Module Integration](#12-cross-module-integration)
13. [Profile Completeness Integration](#13-profile-completeness-integration)
14. [Seeder Data](#14-seeder-data)
15. [API Routing](#15-api-routing)
16. [Key Constants and Business Logic](#16-key-constants-and-business-logic)
17. [Known Issues and Limitations](#17-known-issues-and-limitations)
18. [Priority Scoring Formula](#18-priority-scoring-formula)

---

## 1. System Overview

The Coordination module is the holistic orchestration layer that sits above all other modules. It aggregates analysis from Protection, Savings, Investment, Retirement, and Estate Planning, then resolves conflicts, ranks recommendations by priority, optimises cashflow allocation, and generates a unified holistic financial plan.

### Architecture Flow

```
HolisticPlan.vue / PlansDashboard.vue
  -> holisticService.js (9 API methods)
  -> HolisticPlanningController.php (9 endpoints)
  -> CoordinatingAgent.php (orchestrates all module agents)
  -> Services: PriorityRanker, CashFlowCoordinator, ConflictResolver,
               HolisticPlanner, RecommendationsAggregatorService
  -> Module Agents: ProtectionAgent, SavingsAgent, InvestmentAgent,
                    RetirementAgent, (EstateAgent placeholder)
```

### File Count Summary

| Category | Count |
|---|---|
| Models | 1 (RecommendationTracking) |
| Services | 5 |
| Controllers | 1 |
| Vue Views | 3 |
| Vue Components | 0 (dedicated) |
| API Endpoints | 9 |
| Vuex Stores | 1 (holistic) |

---

## 2. Database Schema

### 2.1 `recommendation_tracking`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK, AUTO_INCREMENT |
| `user_id` | bigint unsigned | NOT NULL |
| `recommendation_id` | varchar(255) | NOT NULL |
| `module` | varchar(255) | NOT NULL |
| `recommendation_text` | text | NOT NULL |
| `priority_score` | decimal(5,2) | NOT NULL DEFAULT 50.00 |
| `timeline` | enum | `immediate`, `short_term`, `medium_term`, `long_term` DEFAULT 'medium_term' |
| `status` | enum | `pending`, `in_progress`, `completed`, `dismissed` DEFAULT 'pending' |
| `completed_at` | timestamp | NULL |
| `notes` | text | NULL |

**Indexes:**
- `recommendation_tracking_user_id_status_index` (`user_id`, `status`)
- `recommendation_tracking_user_id_module_index` (`user_id`, `module`)
- `recommendation_tracking_recommendation_id_index` (`recommendation_id`)
- `rec_tracking_user_completed_idx` (`user_id`, `completed_at`)
- `rec_tracking_timeline_idx` (`user_id`, `timeline`)

---

## 3. Models

### 3.1 RecommendationTracking

**File:** `app/Models/RecommendationTracking.php` (121 lines)
**Table:** `recommendation_tracking`

**Fillable:** `user_id`, `recommendation_id`, `module`, `recommendation_text`, `priority_score`, `timeline`, `status`, `completed_at`, `notes`

**Casts:**

| Attribute | Cast |
|---|---|
| `priority_score` | float |
| `completed_at` | datetime |

**Relationships:**

| Relationship | Type | Related Model |
|---|---|---|
| `user` | BelongsTo | User |

**Scopes (6):**

| Scope | Behaviour |
|---|---|
| `pending()` | WHERE status = 'pending' |
| `completed()` | WHERE status = 'completed' |
| `inProgress()` | WHERE status = 'in_progress' |
| `active()` | WHERE status IN ('pending', 'in_progress') |
| `byModule($module)` | WHERE module = $module |
| `byTimeline($timeline)` | WHERE timeline = $timeline |

**Instance Methods:**

| Method | Behaviour |
|---|---|
| `markAsCompleted()` | Sets status = 'completed', completed_at = now() |
| `dismiss()` | Sets status = 'dismissed' |
| `markAsInProgress()` | Sets status = 'in_progress' |

---

## 4. Controller

### HolisticPlanningController

**File:** `app/Http/Controllers/Api/HolisticPlanningController.php` (335 lines)
**Dependencies:** CoordinatingAgent, CashFlowCoordinator
**Cache TTLs:** Uses `TaxDefaults` constants (CACHE_TTL_STANDARD = 1 hour, CACHE_TTL_SIMULATION = 24 hours)

**Endpoints (9):**

| Method | Route | Action | Description |
|---|---|---|---|
| POST | `/holistic/analyze` | `analyze` | Orchestrates cross-module analysis. Cached for 1 hour. |
| POST | `/holistic/plan` | `plan` | Generates complete holistic plan. Cached for 24 hours. Stores ranked recommendations in `recommendation_tracking`. |
| GET | `/holistic/recommendations` | `recommendations` | Returns active recommendations ordered by `priority_score` desc. |
| GET | `/holistic/cash-flow-analysis` | `cashFlowAnalysis` | Calculates surplus, optimises allocation, identifies shortfalls, generates chart data, sustainable contribution analysis. |
| POST | `/holistic/recommendations/{id}/mark-done` | `markRecommendationDone` | Marks recommendation as completed. Invalidates holistic caches. |
| POST | `/holistic/recommendations/{id}/in-progress` | `markRecommendationInProgress` | Marks recommendation as in progress. |
| POST | `/holistic/recommendations/{id}/dismiss` | `dismissRecommendation` | Dismisses recommendation. Invalidates caches. |
| GET | `/holistic/recommendations/completed` | `completedRecommendations` | Returns completed recommendations. |
| PATCH | `/holistic/recommendations/{id}/notes` | `updateRecommendationNotes` | Updates recommendation notes. Validates `notes: required|string|max:1000`. |

**Private Methods:**

| Method | Behaviour |
|---|---|
| `storeRecommendations($userId, $recommendations)` | Clears existing pending recommendations, stores new ones with UUID `recommendation_id`. |
| `extractDemandsFromTracking($recommendations)` | Maps modules to placeholder monthly amounts: protection=150, savings=200, retirement=300, investment=250, estate=100. |

---

## 5. Agent

### CoordinatingAgent

**File:** `app/Agents/CoordinatingAgent.php` (357 lines)
**Extends:** BaseAgent
**Dependencies:** ConflictResolver, PriorityRanker, HolisticPlanner, CashFlowCoordinator, ProtectionAgent, InvestmentAgent, SavingsAgent, RetirementAgent, TaxConfigService

**Methods:**

| Method | Description |
|---|---|
| `analyze($userId)` | Delegates to `orchestrateAnalysis()`. |
| `generateRecommendations($analysisData)` | Extracts recommendations from analysis, ranks via PriorityRanker. |
| `buildScenarios($userId, $parameters)` | Placeholder - not yet implemented. |
| `orchestrateAnalysis($userId, $moduleAgents?)` | Main orchestration method (see flow below). |
| `generateHolisticPlan($userId, $moduleAgents?)` | Gets orchestrated analysis + HolisticPlanner plan + PriorityRanker action plan. Returns merged plan with recommendations, action plan, cashflow, conflicts. |
| `resolveConflicts($allRecommendations, $conflicts)` | Handles 3 conflict types: `protection_vs_savings_conflict`, `cashflow_conflict`, `isa_allowance_conflict`. |
| `collectModuleAnalysis($userId, $moduleAgents)` | Calls each module agent's `analyze()` with try/catch. Estate is placeholder. Gets user age from User model. |

**Orchestration Flow (`orchestrateAnalysis`):**

```
1. Collect analysis from all modules (protection, savings, investment, retirement, estate)
2. Calculate available surplus via CashFlowCoordinator
3. Extract recommendations from all modules
4. Identify conflicts via ConflictResolver
5. Resolve conflicts
6. Rank recommendations via PriorityRanker
7. Optimise cashflow allocation
8. Identify shortfalls
```

**Return structure:**
```php
[
    'user_id', 'analysis_date', 'module_analysis',
    'available_surplus', 'conflicts', 'ranked_recommendations',
    'cashflow_allocation', 'shortfall_analysis', 'summary'
]
```

**Module analysis collection order:** Protection -> Savings -> Investment -> Retirement -> Estate (placeholder) -> User age

---

## 6. Services

### 6.1 PriorityRanker

**File:** `app/Services/Coordination/PriorityRanker.php` (439 lines)

**Core formula:** `score = (urgency x 0.4) + (impact x 0.3) + (ease x 0.2) + (userPriority x 0.1)`

All scores are 0-100.

**Methods:**

| Method | Description |
|---|---|
| `rankRecommendations($allRecommendations, $userContext)` | Scores each recommendation, sorts descending. |
| `calculateRecommendationScore($recommendation, $module, $userContext)` | Returns `{total_score, urgency, impact, ease, user_priority}`. |
| `groupByCategory($recommendations)` | Groups into protection, savings, investment, retirement, estate. |
| `createActionPlan($rankedRecommendations)` | Groups by timeline (see timeline mapping in Section 18). |

**Default user priority weights:**

| Module | Default Priority |
|---|---|
| Protection | 70 |
| Savings | 75 |
| Retirement | 65 |
| Investment | 60 |
| Estate | 50 |

### 6.2 CashFlowCoordinator

**File:** `app/Services/Coordination/CashFlowCoordinator.php` (285 lines)

**Methods:**

| Method | Description |
|---|---|
| `calculateAvailableSurplus($userId)` | **PLACEHOLDER** - returns hardcoded £1,000. |
| `optimizeContributionAllocation($surplus, $demands)` | Allocates surplus by priority order: emergency_fund(1) -> protection(2) -> pension(3) -> investment(4) -> estate(5). Urgency >= 80 overrides ordering. |
| `identifyCashFlowShortfalls($allocation)` | Returns `{has_shortfall, total_shortfall, shortfalls[], recommendations[]}`. |
| `createCashFlowChartData($userId, $allocation)` | ApexCharts data: Living Expenses + allocated contributions + unallocated surplus. |
| `calculateSustainableContributions($monthlyIncome, $monthlyExpenses)` | 50/30/20 rule: 50% needs, 30% wants, 20% savings/investments. |

**Allocation return structure:**
```php
[
    'total_demand',
    'available_surplus',
    'allocation' => [/* per-category: allocated, requested, shortfall, percent_funded */],
    'total_shortfall',
    'surplus_remaining',
    'allocation_efficiency'
]
```

**Shortfall recommendations:** increase income, review expenses, prioritise critical areas, phased approach, use windfalls.

### 6.3 ConflictResolver

**File:** `app/Services/Coordination/ConflictResolver.php` (419 lines)
**Dependencies:** TaxConfigService

**Methods:**

| Method | Description |
|---|---|
| `identifyConflicts($recommendations)` | Detects 3 conflict types: cashflow, ISA allowance, protection vs savings. |
| `resolveProtectionVsSavings($recommendations)` | Adequacy-based split (see table below). |
| `resolveContributionConflicts($availableSurplus, $demands)` | Same priority order as CashFlowCoordinator. |
| `resolveISAAllocation($isaAllowance, $demands)` | Context-based ISA split (see table below). |

**Conflict severity thresholds:**

| Severity | Demand/Available Ratio |
|---|---|
| Critical | >= 2.0 |
| High | >= 1.5 |
| Medium | >= 1.2 |
| Low | < 1.2 |

**Protection vs Savings resolution:**

| Condition | Protection Share | Savings Share |
|---|---|---|
| Both adequacy < 50 | 60% | 40% |
| Protection lower | 80% | 20% |
| Savings lower / equal | 20% | 80% |

**ISA allocation resolution:**

| Context | Cash ISA | S&S ISA |
|---|---|---|
| Emergency fund critical | Cash ISA first | Remainder |
| Low risk tolerance | 70% | 30% |
| High growth + high risk | 10% | 90% |
| Balanced | Proportional split | Proportional split |

### 6.4 HolisticPlanner

**File:** `app/Services/Coordination/HolisticPlanner.php` (619 lines)

**Methods:**

| Method | Description |
|---|---|
| `createHolisticPlan($userId, $allAnalysis)` | Returns `{user_id, generated_at, executive_summary, financial_snapshot, net_worth_projection, risk_assessment, module_summaries}`. |
| `generateExecutiveSummary($plan)` | Returns `{overview, key_strengths (top 5), key_vulnerabilities (top 5), top_priorities (top 5), overall_score}`. |
| `projectNetWorthTrajectory($allData, $years)` | Baseline (4% growth) vs Optimised (6% growth). Returns improvement amount and percentage. |
| `assessOverallRisk($allAnalysis)` | Risk areas: protection, emergency fund, retirement, investment, IHT. Returns `{overall_risk_score, risk_level, risk_areas, total_risk_areas}`. |

**Overall health score weights:**

| Module | Weight |
|---|---|
| Protection | 0.25 |
| Savings | 0.20 |
| Investment | 0.20 |
| Retirement | 0.25 |
| Estate | 0.10 |

**Module status thresholds:**

| Status | Score Range |
|---|---|
| Excellent | >= 80 |
| Good | >= 60 |
| Needs Improvement | >= 40 |
| Critical | < 40 |

**Risk levels:**

| Level | Score Range |
|---|---|
| High | >= 70 |
| Moderate | >= 50 |
| Low | >= 30 |
| Minimal | < 30 |

### 6.5 RecommendationsAggregatorService

**File:** `app/Services/Coordination/RecommendationsAggregatorService.php` (277 lines)
**Dependencies:** ProtectionAgent, SavingsAgent, PortfolioAnalyzer, RetirementAgent, ComprehensiveEstatePlanService

**Methods:**

| Method | Description |
|---|---|
| `aggregateRecommendations($userId)` | Collects from all 5 modules with try/catch. Formats to consistent structure, sorts by `priority_score` desc. |
| `getRecommendationsByModule($userId, $module)` | Filter by module. |
| `getRecommendationsByPriority($userId, $priority)` | Filter by impact level. |
| `getRecommendationsByTimeline($userId, $timeline)` | Filter by timeline. |
| `getTopRecommendations($userId, $limit=5)` | Top N by priority. |
| `getSummary($userId)` | Returns `{total_count, by_priority, by_module, by_timeline, total_potential_benefit, total_estimated_cost}`. |

**Consistent recommendation structure:**
```php
[
    'recommendation_id',
    'module',
    'recommendation_text',
    'priority_score',
    'timeline',
    'category',
    'impact',
    'estimated_cost',
    'potential_benefit',
    'status'
]
```

**Category mapping:**

| Module | Category |
|---|---|
| Protection | risk_mitigation |
| Savings | liquidity_management |
| Investment | growth_optimisation |
| Retirement | retirement_planning |
| Estate | tax_optimisation |

---

## 7. Validation Requests

No dedicated FormRequest validation classes for the Coordination module. The `HolisticPlanningController` uses inline validation only:

| Endpoint | Validation |
|---|---|
| `updateRecommendationNotes` | `notes: required|string|max:1000` |

All other endpoints rely on authenticated user context from the request, with no additional input validation required.

---

## 8. Vuex Store

**File:** `resources/js/store/modules/holistic.js` (284 lines)
**Namespaced:** true

### State

| Property | Type | Default |
|---|---|---|
| `analysis` | Object | null |
| `plan` | Object | null |
| `recommendations` | Array | [] |
| `cashFlowAnalysis` | Object | null |
| `completedRecommendations` | Array | [] |
| `loading` | Boolean | false |
| `error` | String | null |

### Getters (12)

| Getter | Description |
|---|---|
| `hasAnalysis` | Boolean check for analysis data. |
| `hasPlan` | Boolean check for plan data. |
| `activeRecommendations` | Filters to pending or in_progress status. |
| `pendingRecommendations` | Filters to pending status only. |
| `inProgressRecommendations` | Filters to in_progress status only. |
| `recommendationsByTimeline` | Groups into `{immediate, short_term, medium_term, long_term}`. |
| `recommendationsByModule` | Groups by module name. |
| `topPriorities` | Top 5 recommendations by priority score. |
| `availableSurplus` | Extracts surplus from cashflow analysis. |
| `hasShortfall` | Boolean check for cashflow shortfall. |
| `executiveSummary` | Extracts executive summary from plan. |
| `netWorthProjection` | Extracts net worth projection from plan. |
| `riskAssessment` | Extracts risk assessment from plan. |
| `actionPlan` | Extracts action plan from plan. |

### Actions (10)

| Action | API Call | Notes |
|---|---|---|
| `fetchAnalysis` | POST /holistic/analyze | |
| `fetchPlan` | POST /holistic/plan | Also dispatches `fetchRecommendations`. |
| `fetchRecommendations` | GET /holistic/recommendations | |
| `fetchCashFlowAnalysis` | GET /holistic/cash-flow-analysis | |
| `markRecommendationDone` | POST /recommendations/{id}/mark-done | |
| `markRecommendationInProgress` | POST /recommendations/{id}/in-progress | |
| `dismissRecommendation` | POST /recommendations/{id}/dismiss | |
| `fetchCompletedRecommendations` | GET /recommendations/completed | |
| `updateRecommendationNotes` | PATCH /recommendations/{id}/notes | |
| `clearError` | n/a | Resets error state. |
| `clearAll` | n/a | Resets all state to defaults. |

---

## 9. API Service

**File:** `resources/js/services/holisticService.js` (82 lines)

9 API endpoint wrappers:

| Method | HTTP | Endpoint |
|---|---|---|
| `analyzeHolistic()` | POST | `/api/holistic/analyze` |
| `getPlan()` | POST | `/api/holistic/plan` |
| `getRecommendations()` | GET | `/api/holistic/recommendations` |
| `getCashFlowAnalysis()` | GET | `/api/holistic/cash-flow-analysis` |
| `markRecommendationDone(id)` | POST | `/api/holistic/recommendations/{id}/mark-done` |
| `markRecommendationInProgress(id)` | POST | `/api/holistic/recommendations/{id}/in-progress` |
| `dismissRecommendation(id)` | POST | `/api/holistic/recommendations/{id}/dismiss` |
| `getCompletedRecommendations()` | GET | `/api/holistic/recommendations/completed` |
| `updateRecommendationNotes(id, notes)` | PATCH | `/api/holistic/recommendations/{id}/notes` |

---

## 10. Frontend Components

No dedicated Coordination/Holistic components directory exists. The module is served by three view-level files:

| File | Purpose |
|---|---|
| `resources/js/views/HolisticPlan.vue` | Main holistic plan view. Displays executive summary, module summaries, recommendations, cashflow analysis, risk assessment, and net worth projections. |
| `resources/js/views/Plans/PlansDashboard.vue` | Plans dashboard providing navigation to available plan types. |
| `resources/js/views/Plans/InvestmentSavingsPlan.vue` | Investment and Savings combined plan view. |

---

## 11. Frontend Routing

| Path | Component | Auth Required |
|---|---|---|
| `/holistic-plan` | `HolisticPlan` | Yes |
| `/plans` | `PlansDashboard` | Yes |
| `/plans/investment-savings` | `InvestmentSavingsPlan` | Yes |

---

## 12. Cross-Module Integration

The Coordination module is the primary integration point for the entire application. It orchestrates all other modules rather than being consumed by them.

### Module Dependencies

| Module | Integration Point | Data Consumed |
|---|---|---|
| Protection | `CoordinatingAgent` calls `ProtectionAgent.analyze()` | Coverage gaps, adequacy scores, policy data |
| Savings | `CoordinatingAgent` calls `SavingsAgent.analyze()` | Emergency fund adequacy, savings balances, ISA usage |
| Investment | `CoordinatingAgent` calls `InvestmentAgent.analyze()` | Goal probabilities, portfolio performance, time horizons |
| Retirement | `CoordinatingAgent` calls `RetirementAgent.analyze()` | Income gaps, years to retirement, pension contributions |
| Estate | Placeholder analysis in `CoordinatingAgent`; `RecommendationsAggregatorService` calls `ComprehensiveEstatePlanService` | IHT liability, estate value, mitigation strategies |

### Cross-Module Conflict Points

| Conflict Type | Modules Involved | Resolution |
|---|---|---|
| Cashflow | All modules | Surplus allocated by priority order (emergency_fund > protection > pension > investment > estate) |
| ISA Allowance | Savings + Investment | Context-based split between Cash ISA and S&S ISA when combined demand exceeds £20,000 |
| Protection vs Savings | Protection + Savings | Adequacy-based proportional split (60/40 if both critical) |

---

## 13. Profile Completeness Integration

No direct profile completeness integration. The Coordination module depends on all other modules having sufficient data to generate meaningful analysis. If a module lacks data, its analysis returns default/empty values and the holistic plan reflects reduced confidence accordingly.

---

## 14. Seeder Data

No dedicated seeder for coordination data. `RecommendationTracking` records are generated dynamically when a holistic plan is created via `HolisticPlanningController::plan()`. The `storeRecommendations()` method clears existing pending recommendations and creates new records with UUID-based `recommendation_id` values.

---

## 15. API Routing

All routes are under the `/api/holistic` prefix and require authentication middleware.

```
POST   /api/holistic/analyze                              -> HolisticPlanningController@analyze
POST   /api/holistic/plan                                 -> HolisticPlanningController@plan
GET    /api/holistic/recommendations                      -> HolisticPlanningController@recommendations
GET    /api/holistic/cash-flow-analysis                    -> HolisticPlanningController@cashFlowAnalysis
POST   /api/holistic/recommendations/{id}/mark-done       -> HolisticPlanningController@markRecommendationDone
POST   /api/holistic/recommendations/{id}/in-progress     -> HolisticPlanningController@markRecommendationInProgress
POST   /api/holistic/recommendations/{id}/dismiss         -> HolisticPlanningController@dismissRecommendation
GET    /api/holistic/recommendations/completed             -> HolisticPlanningController@completedRecommendations
PATCH  /api/holistic/recommendations/{id}/notes           -> HolisticPlanningController@updateRecommendationNotes
```

---

## 16. Key Constants and Business Logic

### Priority Score Weights

| Factor | Weight |
|---|---|
| Urgency | 0.4 (40%) |
| Impact | 0.3 (30%) |
| Ease | 0.2 (20%) |
| User Priority | 0.1 (10%) |

### Cashflow Priority Order

| Priority | Category |
|---|---|
| 1 | Emergency Fund |
| 2 | Protection |
| 3 | Pension |
| 4 | Investment |
| 5 | Estate |

### Default Module Priorities

| Module | Priority Value |
|---|---|
| Protection | 80 |
| Savings | 75 |
| Retirement | 70 |
| Investment | 60 |
| Estate | 50 |

### Net Worth Projection Rates

| Scenario | Annual Growth |
|---|---|
| Baseline | 4% |
| Optimised | 6% |

### Sustainable Contribution Rule (50/30/20)

| Category | % of Income |
|---|---|
| Needs | 50% |
| Wants | 30% |
| Savings/Investments | 20% |

### Cache TTLs

| Data Type | Duration | Constant |
|---|---|---|
| Analysis | 1 hour | CACHE_TTL_STANDARD |
| Plan | 24 hours | CACHE_TTL_SIMULATION |

### Placeholder Demands (per month)

| Module | Amount |
|---|---|
| Protection | £150 |
| Savings | £200 |
| Retirement | £300 |
| Investment | £250 |
| Estate | £100 |

---

## 17. Known Issues and Limitations

| # | Issue | Severity | Details |
|---|---|---|---|
| 1 | Hardcoded available surplus | High | `CashFlowCoordinator.calculateAvailableSurplus()` returns a hardcoded £1,000 placeholder instead of calculating from actual income and expenses. |
| 2 | Estate analysis placeholder | High | `CoordinatingAgent.collectModuleAnalysis()` uses hardcoded values for estate (net_worth=350k, iht=10k, income=4500, expenses=3200) instead of calling the Estate agent. |
| 3 | Placeholder demand amounts | Medium | `extractDemandsFromTracking()` uses static per-module amounts (£150-£300/mo) instead of deriving actual amounts from recommendations. |
| 4 | Placeholder cashflow income/expenses | Medium | The `cashFlowAnalysis` endpoint uses hardcoded income (£4,500) and expenses (£3,200) instead of real user data. |
| 5 | Scenarios not implemented | Low | `buildScenarios()` on CoordinatingAgent returns a placeholder message. |
| 6 | Static user priorities | Medium | User context for priority ranking returns static default priorities instead of user-configurable preferences. |
| 7 | Empty investment recommendations | Low | `RecommendationsAggregatorService` returns an empty array for the investment module. |
| 8 | No dedicated frontend components | Low | The module relies on generic view-level files with no reusable component library. |
| 9 | Pending recommendations cleared on plan generation | Medium | `storeRecommendations()` clears all pending recommendations before storing new ones, potentially losing user progress on in-progress items (note: only pending items are cleared, not in-progress). |
| 10 | No real-time updates | Low | No WebSocket or push-based updates when recommendations change; requires manual refresh. |

---

## 18. Priority Scoring Formula

### Formula

```
total_score = (urgency x 0.4) + (impact x 0.3) + (ease x 0.2) + (user_priority x 0.1)
```

All component scores range from 0 to 100. The resulting `total_score` also ranges from 0 to 100.

### Urgency (40% weight)

Module-specific scoring. Higher scores indicate greater urgency.

| Module | Condition | Score |
|---|---|---|
| Protection | Coverage gap > £100k | 95 |
| Protection | Adequacy < 30% | 90 |
| Protection | Adequacy < 50% | 75 |
| Protection | Adequacy < 70% | 60 |
| Protection | Adequacy >= 70% | 40 |
| Savings | Emergency fund < 1 month | 95 |
| Savings | Emergency fund < 3 months | 85 |
| Savings | Emergency fund < 6 months | 65 |
| Savings | Emergency fund >= 6 months | 45 |
| Retirement | Income gap > £15k | 80 |
| Retirement | Income gap > £10k | 70 |
| Retirement | Income gap > £5k | 55 |
| Retirement | Income gap <= £5k | 35 |
| Retirement | < 10 years to retirement | +20 bonus |
| Investment | Goal probability < 30% | 75 |
| Investment | Goal probability < 50% | 60 |
| Investment | Goal probability >= 50% | 40 |
| Investment | < 3 years to goal | +25 bonus |
| Estate | IHT liability > £500k | 85 |
| Estate | IHT liability > £200k | 70 |
| Estate | IHT liability > £50k | 55 |
| Estate | IHT liability <= £50k | 30 |
| Estate | Age > 70 | +15 bonus |

### Impact (30% weight)

Financial benefit or risk reduction value, scored by gap/shortfall amounts.

| Module | Condition | Score |
|---|---|---|
| Protection | Coverage gap > £500k | 95 |
| Protection | Coverage gap > £250k | 85 |
| Protection | Coverage gap > £100k | 70 |
| Protection | Coverage gap <= £100k | 55 |
| Savings | Shortfall > £20k | 90 |
| Savings | Shortfall > £10k | 75 |
| Savings | Shortfall > £5k | 60 |
| Savings | Shortfall <= £5k | 45 |
| Retirement | Income gap > £30k | 95 |
| Retirement | Income gap > £15k | 80 |
| Retirement | Income gap > £5k | 65 |
| Retirement | Income gap <= £5k | 50 |
| Investment | Benefit > £50k | 90 |
| Investment | Benefit > £20k | 75 |
| Investment | Benefit > £10k | 60 |
| Investment | Benefit <= £10k | 45 |
| Estate | IHT saving > £200k | 95 |
| Estate | IHT saving > £100k | 85 |
| Estate | IHT saving > £50k | 70 |
| Estate | IHT saving <= £50k | 55 |

### Ease (20% weight)

Implementation difficulty based on monthly cost and module-specific caps/floors.

**Cost-based scoring:**

| Monthly Cost | Score |
|---|---|
| £0 (no cost) | 90 |
| < £50 | 80 |
| < £200 | 65 |
| < £500 | 45 |
| >= £500 | 30 |

**Module-specific adjustments:**

| Module | Adjustment |
|---|---|
| Protection | Max capped at 60 |
| Savings | Min floor of 70 |
| Investment | Max capped at 65 |
| Retirement (workplace) | Fixed at 75 |
| Retirement (personal) | Fixed at 55 |
| Estate (will) | Fixed at 50 |
| Estate (trust) | Fixed at 30 |

### User Priority (10% weight)

Default module weights (currently static, not user-configurable):

| Module | Default Weight |
|---|---|
| Protection | 70 |
| Savings | 75 |
| Retirement | 65 |
| Investment | 60 |
| Estate | 50 |

### Timeline Mapping

Recommendations are grouped into action plan timelines based on their urgency score:

| Urgency Score | Timeline | Timeframe |
|---|---|---|
| >= 80 | Immediate | Within 1 month |
| 60 - 79 | Short Term | Within 3 months |
| 40 - 59 | Medium Term | Within 12 months |
| < 40 | Long Term | 12+ months |

### Conflict Resolution Hierarchy

When multiple modules compete for the same financial resources, conflicts are resolved in this order:

1. **Cashflow conflicts:** Allocate available surplus in strict priority order: emergency_fund > protection > pension > investment > estate. Any recommendation with urgency >= 80 receives priority allocation regardless of module ordering.

2. **ISA allowance conflicts:** When combined Cash ISA and S&S ISA demands exceed the £20,000 annual allowance, allocation is context-based:
   - Emergency fund critical: Cash ISA receives priority allocation
   - Low risk tolerance: 70/30 split (Cash ISA / S&S ISA)
   - High growth + high risk tolerance: 90% to S&S ISA
   - Balanced: Proportional split based on demand ratios

3. **Protection vs Savings conflicts:** Resolution based on adequacy scores:
   - Both adequacy < 50%: 60/40 split favouring protection
   - Protection adequacy lower: 80/20 split favouring protection
   - Savings adequacy lower or equal: 20/80 split favouring savings