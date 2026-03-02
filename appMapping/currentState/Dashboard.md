# Dashboard Module - Current State Documentation

**Last Updated:** 2026-02-19
**Module Version:** Part of Fynla v0.7.0
**Status:** Frontend fully functional with live module data; backend DashboardAggregator is a stub with hardcoded values (not wired to frontend)

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
13. [Profile Completeness](#13-profile-completeness)
14. [Seeder Data](#14-seeder-data)
15. [API Routing](#15-api-routing)
16. [Key Constants](#16-key-constants)
17. [Known Issues](#17-known-issues)
18. [Deep Dive: Cross-Module Data Loading Architecture](#18-deep-dive-cross-module-data-loading-architecture)

---

## 1. System Overview

The Dashboard is the primary landing page for authenticated users. It aggregates data from all seven Fynla modules (Protection, Savings, Investment, Retirement, Estate Planning, Goals & Life Events, and Coordination) into a single-screen overview consisting of card-based widgets arranged in a responsive three-column grid.

### Key Architecture Distinction

**The dashboard has a split architecture that is important to understand:**

- **Backend (DashboardAggregator):** A PHP service that exists but is a **stub** returning hardcoded data. The DashboardController exposes three cached endpoints (`/api/dashboard`, `/api/dashboard/financial-health-score`, `/api/dashboard/alerts`) that call the aggregator, but these endpoints are **not used by the current frontend**.
- **Frontend (Dashboard.vue):** The actual dashboard view bypasses the DashboardAggregator entirely. It dispatches 15+ Vuex actions in parallel via `Promise.allSettled`, each hitting the individual module APIs directly. The dashboard reads computed data from module-specific Vuex stores (netWorth, protection, estate, retirement, investment, savings, goals, trusts, userProfile).

```
Current Reality:
  Dashboard.vue
    -> dispatches 15+ store actions in parallel
    -> netWorth/fetchOverview -> NetWorthController
    -> protection/fetchProtectionData -> ProtectionController
    -> estate/fetchEstateData -> EstateController
    -> retirement/fetchRetirementData -> RetirementController
    -> investment/fetchInvestmentData -> InvestmentController
    -> savings/fetchSavingsData -> SavingsController
    -> goals/fetchDashboardOverview -> GoalsController
    -> ... (more actions)
    -> reads from module Vuex store getters/state
    -> renders cards with live data

NOT Used (stub):
  DashboardController -> DashboardAggregator -> hardcoded arrays
```

### File Count Summary

| Category | Count |
|---|---|
| Controllers | 1 (DashboardController) |
| Services | 1 (DashboardAggregator - stub) |
| Vue Components | 16 (in `components/Dashboard/`) |
| Vue Views | 1 (Dashboard.vue) |
| Vuex Stores | 1 (dashboard.js - partially used) |
| API Services | 1 (dashboardService.js - partially used) |
| Test Files | 3 (DashboardApiTest, DashboardIntegrationTest, Dashboard.test.js) |
| API Endpoints | 5 |

---

## 2. Database Schema

The Dashboard module has **no dedicated database tables**. It is a pure aggregation layer that reads from other modules' tables.

### Caching Strategy

Instead of database storage, the backend DashboardController uses Laravel's `Cache` facade with three per-user cache keys:

| Cache Key Pattern | TTL | Purpose |
|---|---|---|
| `dashboard_{userId}` | 300 seconds (5 minutes) | Aggregated overview data from all modules |
| `financial_health_score_{userId}` | 3600 seconds (1 hour) | Composite financial health score with breakdown |
| `alerts_{userId}` | 900 seconds (15 minutes) | Priority-sorted alerts from all modules |

Cache invalidation is available via `POST /api/dashboard/invalidate-cache` which clears all three keys for the authenticated user. Dismissing an alert (`POST /api/dashboard/alerts/{id}/dismiss`) clears only the alerts cache.

**Note:** Since the frontend does not currently use these backend endpoints, this caching is effectively dormant.

---

## 3. Models

The Dashboard module has **no dedicated Eloquent models**. All data is sourced from models belonging to other modules:

- `User` (auth)
- `Property`, `Mortgage` (property/net worth)
- `InvestmentAccount` (investment)
- `SavingsAccount` (savings)
- `DCPension`, `DBPension`, `StatePension` (retirement)
- `ProtectionPolicy` (protection)
- `Trust` (trusts/estate)
- `Goal`, `LifeEvent` (goals)

---

## 4. Controller

### `DashboardController`

**File:** `app/Http/Controllers/Api/DashboardController.php`

Uses the `SanitizedErrorResponse` trait for safe error handling. Injects `DashboardAggregator` via constructor.

#### Methods

| Method | Route | HTTP | Cache TTL | Description |
|---|---|---|---|---|
| `index` | `/api/dashboard` | GET | 300s (5 min) | Returns aggregated module summaries (protection, savings, investment, retirement, estate) |
| `financialHealthScore` | `/api/dashboard/financial-health-score` | GET | 3600s (1 hour) | Returns composite 0-100 score with per-module breakdown |
| `alerts` | `/api/dashboard/alerts` | GET | 900s (15 min) | Returns severity-sorted alerts from all modules |
| `dismissAlert` | `/api/dashboard/alerts/{id}/dismiss` | POST | Clears alerts cache | Dismisses an alert (stub: only invalidates cache, no DB update) |
| `invalidateCache` | `/api/dashboard/invalidate-cache` | POST | Clears all 3 | Clears all dashboard cache keys for the user |

#### Response Format

All endpoints return:
```json
{
  "success": true,
  "data": { ... }
}
```

On error:
```json
{
  "success": false,
  "message": "Sanitized error message"
}
```

---

## 5. Agent

The Dashboard module has **no dedicated Agent**. The `DashboardAggregator` service fills the role that an agent would normally play, but it is a stub.

The backend aggregator injects four agents via constructor:
- `ProtectionAgent`
- `SavingsAgent`
- `InvestmentAgent`
- `RetirementAgent`

However, none of these injected agents are actually called in the current implementation. All private helper methods return hardcoded arrays.

---

## 6. Services

### `DashboardAggregator`

**File:** `app/Services/Dashboard/DashboardAggregator.php`

**Status: STUB - All private methods return hardcoded data.**

#### Constructor Dependencies

```php
public function __construct(
    private readonly ProtectionAgent $protectionAgent,
    private readonly SavingsAgent $savingsAgent,
    private readonly InvestmentAgent $investmentAgent,
    private readonly RetirementAgent $retirementAgent
) {}
```

#### Public Methods

##### `aggregateOverviewData(int $userId): array`

Returns module summaries. Calls five private methods:

```php
return [
    'protection' => $this->getProtectionSummary($userId),
    'savings' => $this->getSavingsSummary($userId),
    'investment' => $this->getInvestmentSummary($userId),
    'retirement' => $this->getRetirementSummary($userId),
    'estate' => $this->getEstateSummary($userId),
];
```

Hardcoded stub values for each module (e.g., protection returns `adequacy_score: 75`, `total_coverage: 500000`, etc.).

##### `calculateFinancialHealthScore(int $userId): array`

Computes a weighted composite score (0-100) from five dimensions:

| Dimension | Weight | Hardcoded Score | Source (intended) |
|---|---|---|---|
| Protection Coverage | 20% | 75.0 | ProtectionAgent adequacy score |
| Emergency Fund | 15% | 66.7 | `min(100, (runway / 6) * 100)` |
| Retirement Readiness | 25% | 68.0 | RetirementAgent readiness |
| Investment Diversification | 20% | 80.0 | InvestmentAgent diversification |
| Estate Planning | 20% | 85.0 | EstateAgent probate readiness |

**Formula:**
```
compositeScore = (protection * 0.20) + (emergencyFund * 0.15) + (retirement * 0.25) + (investment * 0.20) + (estate * 0.20)
```

**Emergency Fund Score Formula (defined but using hardcoded runway of 4):**
```
score = min(100, (runway_months / 6) * 100)
```

**Labels:**
| Score Range | Label |
|---|---|
| >= 80 | Excellent |
| >= 60 | Good |
| >= 40 | Fair |
| < 40 | Needs Improvement |

**Recommendations:**
| Score Range | Recommendation |
|---|---|
| >= 80 | "Your finances are in great shape. Keep up the good work!" |
| >= 60 | "Your finances are on track with some room for improvement." |
| < 60 | "Consider addressing key areas to improve your financial health." |

Return structure:
```php
[
    'composite_score' => round($compositeScore, 2),
    'breakdown' => [
        'protection' => ['score' => ..., 'weight' => 0.20, 'contribution' => ...],
        'emergency_fund' => ['score' => ..., 'weight' => 0.15, 'contribution' => ...],
        'retirement' => ['score' => ..., 'weight' => 0.25, 'contribution' => ...],
        'investment' => ['score' => ..., 'weight' => 0.20, 'contribution' => ...],
        'estate' => ['score' => ..., 'weight' => 0.20, 'contribution' => ...],
    ],
    'label' => '...',
    'recommendation' => '...',
]
```

##### `aggregateAlerts(int $userId): array`

Collects alerts from five private methods, merges, and sorts by severity:

```php
$severityOrder = ['critical' => 0, 'important' => 1, 'info' => 2];
```

**Alert Structure:**
```php
[
    'id' => int,
    'module' => string,        // 'Protection', 'Savings', 'Investment', 'Retirement', 'Estate'
    'severity' => string,      // 'critical', 'important', 'info'
    'title' => string,
    'message' => string,
    'action_link' => string,   // Vue route path
    'action_text' => string,
    'created_at' => string,    // ISO 8601
]
```

Hardcoded alerts:
- Savings: "Emergency Fund Below Target" (critical)
- Protection: "Income Protection Gap" (important)
- Retirement: "Pension Contribution Opportunity" (important)
- Investment: "Portfolio Performing Well" (info)
- Estate: none (returns empty array)

#### Error Handling

All public methods wrap logic in try/catch. On failure:
- `aggregateOverviewData` returns `[]`
- `calculateFinancialHealthScore` returns `['composite_score' => 0, 'breakdown' => [], 'label' => 'Unknown', ...]`
- `aggregateAlerts` returns `[]`

Errors are logged via `\Log::error()`.

---

## 7. Validation Requests

The Dashboard module has **no dedicated Form Request classes**. The only validated parameter is the `{id}` route parameter on `dismissAlert`, which is type-hinted as `int` in the controller method signature.

---

## 8. Vuex Store

### `dashboard.js`

**File:** `resources/js/store/modules/dashboard.js`

**Note:** This store is registered but largely unused by the current Dashboard.vue, which reads directly from module-specific stores. The dashboard store is wired to the backend DashboardAggregator endpoints.

#### State

```javascript
{
    overviewData: null,          // Aggregated module summaries
    financialHealthScore: null,  // Composite score object
    alerts: [],                  // Array of alert objects
    loading: false,              // Global loading flag
    error: null,                 // Error message string
    isPreviewMode: false,        // Whether in preview persona mode
    previewData: null,           // Pre-loaded data for preview mode
}
```

#### Getters

| Getter | Returns |
|---|---|
| `overviewData` | `state.overviewData` |
| `financialHealthScore` | `state.financialHealthScore` |
| `alerts` | `state.alerts` |
| `criticalAlerts` | Alerts filtered by `severity === 'critical'` |
| `importantAlerts` | Alerts filtered by `severity === 'important'` |
| `infoAlerts` | Alerts filtered by `severity === 'info'` |
| `totalAlerts` | `state.alerts.length` |
| `loading` | `state.loading` |
| `error` | `state.error` |

#### Mutations

| Mutation | Parameters | Purpose |
|---|---|---|
| `setOverviewData` | `data` | Sets `state.overviewData` |
| `setFinancialHealthScore` | `score` | Sets `state.financialHealthScore` |
| `setAlerts` | `alerts` | Sets `state.alerts` |
| `removeAlert` | `alertId` | Filters out alert by ID |
| `setLoading` | `loading` | Sets global loading flag |
| `setError` | `error` | Sets error message |
| `SET_PREVIEW_MODE` | `{ isPreview, data }` | Enables/disables preview mode and pre-loads data |

#### Actions

| Action | API Call | Preview Behaviour |
|---|---|---|
| `fetchDashboardData` | `dashboardService.getDashboardData()` | Skips if `isPreviewMode` |
| `fetchFinancialHealthScore` | `dashboardService.getFinancialHealthScore()` | Skips if `isPreviewMode` |
| `fetchAlerts` | `dashboardService.getAlerts()` | Skips if `isPreviewMode` |
| `dismissAlert` | `dashboardService.dismissAlert(alertId)` | No preview check |
| `fetchAllDashboardData` | `dashboardService.fetchAllDashboardData()` | Skips if `isPreviewMode` |

#### Preview Mode

The `SET_PREVIEW_MODE` mutation enables preview mode for seeded test personas:

```javascript
SET_PREVIEW_MODE(state, { isPreview, data }) {
    state.isPreviewMode = isPreview;
    state.previewData = data;
    if (isPreview && data) {
        if (data.dashboard_overview) state.overviewData = data.dashboard_overview;
        if (data.financial_health_score) state.financialHealthScore = data.financial_health_score;
    } else if (!isPreview) {
        state.isPreviewMode = false;
        state.previewData = null;
    }
}
```

When preview mode is active, all fetch actions return immediately without API calls, using the pre-loaded data.

---

## 9. API Service

### `dashboardService.js`

**File:** `resources/js/services/dashboardService.js`

Base URL: `/api/dashboard`

| Method | HTTP | Endpoint | Description |
|---|---|---|---|
| `getDashboardData()` | GET | `/api/dashboard` | Fetches aggregated overview |
| `getFinancialHealthScore()` | GET | `/api/dashboard/financial-health-score` | Fetches FHS score |
| `getAlerts()` | GET | `/api/dashboard/alerts` | Fetches sorted alerts |
| `dismissAlert(alertId)` | POST | `/api/dashboard/alerts/{alertId}/dismiss` | Dismisses an alert |
| `fetchAllDashboardData()` | GET | All 3 endpoints | Parallel fetch via `Promise.allSettled` |

#### `fetchAllDashboardData()` - Promise.allSettled Pattern

This method fires all three GET requests in parallel and handles partial failures gracefully:

```javascript
const results = await Promise.allSettled(promises);
return {
    overview: results[0].status === 'fulfilled' ? results[0].value : null,
    financialHealthScore: results[1].status === 'fulfilled' ? results[1].value : null,
    alerts: results[2].status === 'fulfilled' ? results[2].value : null,
    errors: {
        overview: results[0].status === 'rejected' ? results[0].reason : null,
        financialHealthScore: results[1].status === 'rejected' ? results[1].reason : null,
        alerts: results[2].status === 'rejected' ? results[2].reason : null,
    },
};
```

**Important:** While this service exists and works, the main Dashboard.vue does not use it. Dashboard.vue dispatches actions to individual module stores instead.

---

## 10. Frontend Components

All 16 components are located in `resources/js/components/Dashboard/`.

### 10.1 `DashboardCard.vue`

**File:** `resources/js/components/Dashboard/DashboardCard.vue`

Generic container card used by Dashboard.vue to wrap each dashboard section. Provides:
- White background with rounded border (`bg-white rounded-lg border border-gray-200 p-6`)
- Clickable with hover effect (`hover:shadow-md hover:-translate-y-0.5 hover:border-primary-400`)
- Loading skeleton (animated pulse with grey bars)
- Title header (`text-lg font-semibold`)
- Default slot for content

**Props:**
| Prop | Type | Default | Description |
|---|---|---|---|
| `title` | String | required | Card header text |
| `loading` | Boolean | false | Shows loading skeleton when true |

**Emits:** `click`

### 10.2 `FinancialHealthScore.vue`

**File:** `resources/js/components/Dashboard/FinancialHealthScore.vue`

SVG radial gauge displaying a composite 0-100 financial health score. **This component reads directly from five module Vuex store getters** (not from the backend DashboardAggregator):

| Store | Getter | Maps To |
|---|---|---|
| `protection` | `adequacyScore` | Protection Coverage score |
| `savings` | `emergencyFundRunway` | Emergency Fund score (converted: `min(100, runway/6*100)`) |
| `retirement` | `retirementReadinessScore` | Retirement Readiness score |
| `investment` | `diversificationScore` | Investment Diversification score |
| `estate` | `probateReadiness` | Estate Planning score |

**Weighted formula (mirrors backend):**
```
composite = (protection * 0.20) + (emergencyFund * 0.15) + (retirement * 0.25) + (investment * 0.20) + (estate * 0.20)
```

**Visual elements:**
- SVG circle with `r=85`, `stroke-width=20`, `circumference = 2 * PI * 85 = 534.07`
- `dashOffset = circumference * (1 - score/100)`
- Colour thresholds via `getColorByThreshold()` from design system
- Score text class: >= 80 green, >= 60 blue, < 60 red
- Badge classes: >= 80 green bg, >= 60 blue bg, < 60 red bg
- Expandable "View Details" section showing per-module breakdown with progress bars

**Labels:** Same as backend (Excellent >= 80, Good >= 60, Fair >= 40, Needs Improvement < 40) with " Financial Health" suffix.

### 10.3 `AlertsPanel.vue`

**File:** `resources/js/components/Dashboard/AlertsPanel.vue`

Displays severity-sorted alerts with colour-coded borders and icons.

**Props:**
| Prop | Type | Default | Description |
|---|---|---|---|
| `alerts` | Array | `[]` | Alert objects to display |
| `maxDisplay` | Number | 5 | Maximum visible alerts |

**Severity styling:**
| Severity | Border/BG | Icon Colour | Text Colour |
|---|---|---|---|
| `critical` | `border-red-300 bg-red-50` | `text-red-600` | `text-red-800` |
| `important` | `border-blue-300 bg-blue-50` | `text-blue-600` | `text-blue-800` |
| `info` | `border-blue-300 bg-blue-50` | `text-blue-600` | `text-blue-800` |

**Module badge classes:**
| Module | Badge Class |
|---|---|
| Protection | `bg-red-100 text-red-700` |
| Savings | `bg-blue-100 text-blue-700` |
| Investment | `bg-green-100 text-green-700` |
| Retirement | `bg-purple-100 text-purple-700` |
| Estate | `bg-blue-100 text-blue-700` |

Sorting: by severity order (critical=0, important=1, info=2), then by `created_at` descending.

**Emits:** `dismiss` (alertId), `show-all`

### 10.4 `NetWorthOverviewCard.vue`

**File:** `resources/js/components/Dashboard/NetWorthOverviewCard.vue`

Standalone card (not used in current Dashboard.vue - Dashboard.vue has its own inline net worth rendering). Shows:
- Total Net Worth (green if positive, red if negative)
- Asset breakdown by category (Pensions, Property, Investments, Cash, Business, Personal Valuables)
- Liability breakdown (Mortgages, Loans, Credit Cards, Other)
- Loading skeleton with animated gradient
- Error state with retry button
- Navigates to `/net-worth/wealth-summary` on click

**Store sources:** `netWorth` store (state: `loading`, `error`, `overview`; getters: `formattedNetWorth`, `netWorth`, `totalAssets`, `totalLiabilities`).

### 10.5 `NetWorthSummary.vue`

**File:** `resources/js/components/Dashboard/NetWorthSummary.vue`

Alternative net worth view (not used in current Dashboard.vue). Shows:
- Total net worth with optional trend indicator (up/down arrow with percentage change)
- Asset breakdown: Savings, Investments, Pensions, Other Assets
- **Other Assets calculation to avoid double-counting:** `otherAssets = max(0, estateAssets - savings - investments)`
- Total liabilities from estate module
- "View Detailed Breakdown" button navigating to `/estate`

**Store sources:** `savings/totalSavings`, `investment/totalPortfolioValue`, `retirement/totalPensionWealth`, `estate/totalAssets`, `estate/totalLiabilities`.

### 10.6 `GoalsOverviewCard.vue`

**File:** `resources/js/components/Dashboard/GoalsOverviewCard.vue`

Goals summary card (not used in current Dashboard.vue - Dashboard.vue uses GoalsProjectionChartDashboard instead). Shows:
- Overall progress with progress bar and currency totals
- Top 5 goals with individual progress bars and type-specific icons
- Time remaining formatter (days -> `Xd`, `Xm`, `Xy Xm`)
- Streak banner when `bestStreak >= 3` months
- Status banner: all on track (green border) or N goals need attention (blue border)
- Empty state with "Create Goal" button

**Goal type icons mapping:**
| Goal Type | Icon |
|---|---|
| `emergency_fund` | Shield |
| `property_purchase` | House |
| `home_deposit` | Key |
| `education` | Graduation cap |
| `retirement` | Sun |
| `wealth_accumulation` | Chart |
| `wedding` | Ring |
| `holiday` | Plane |
| `car_purchase` | Car |
| `debt_repayment` | Credit card |
| `custom` | Star |
| Default | Target |

### 10.7 `GoalsProjectionChartDashboard.vue`

**File:** `resources/js/components/Dashboard/GoalsProjectionChartDashboard.vue`

**ACTIVELY USED in Dashboard.vue.** ApexCharts bar chart showing projected net worth by age with floating event icons above bars.

**Features:**
- Bar chart type, height 300px, colour `#A8B8D8` (muted periwinkle blue)
- X-axis: Age, Y-axis: Net Worth (formatted as compact currency `£XK`/`£XM`)
- Y-axis max: 115% of peak net worth (15% headroom for icons)
- Grid padding top: 80px for event icons
- Retirement age annotation: dashed vertical line at retirement age with "Retire" label
- Event markers: floating `EventIcon` components positioned above bars using ApexCharts internal coordinates
- Event grouping: multiple events at same age stack vertically (22px icon + 6px gap)
- Completed events: marked when `is_completed` or `age < currentAge`
- Custom HTML tooltip: shows age, net worth, goals, and life events with colour-coded impact (income = green, expense = red)
- Reactive marker repositioning on chart update and projection data changes

**Store sources:** `goals/projectionData`, `goals/projectionLoading`

### 10.8 `GoalsProjectionChartMini.vue`

**File:** `resources/js/components/Dashboard/GoalsProjectionChartMini.vue`

Sparkline area chart variant (not used in current Dashboard.vue). Height 180px.

- Sparkline mode enabled (no axes, no grid, no toolbar)
- Gradient fill (opacity 0.4 to 0.1, vertical)
- Smooth curve, stroke width 2
- Primary colour from design system
- Retirement age annotation (dashed line with "Retire" label)
- Tooltip: `Age X` with currency-formatted net worth

### 10.9 `ActionsOverviewCard.vue`

**File:** `resources/js/components/Dashboard/ActionsOverviewCard.vue`

**NOT used in current Dashboard.vue** (was previously part of layout). Fetches and merges action items from three module APIs in parallel:

**Data sources (loaded on mount via `Promise.allSettled`):**
| Source | API Call | Mapped Fields |
|---|---|---|
| Investment | `investmentService.getPortfolioStrategy()` | `recommendations[]` -> title, description, priority, potential_saving |
| Protection | `protectionService.getRecommendations()` | `recommendations[]` -> action/recommendation_text, rationale, priority |
| Estate | `estateService.calculateSecondDeathIHTPlanning()` | `gifting_strategy.strategies[]` + `life_cover_strategy.recommendation` |

**Props:** `compact` (Boolean, default false), `limit` (Number, default 0 = no limit)

**Priority system:**
| Priority | Dot Colour | Label |
|---|---|---|
| <= 1 | Red (`bg-red-500`) | Urgent |
| <= 2 | Blue (`bg-blue-500`) | High |
| <= 3 | Sky (`bg-sky-500`) | Medium |
| > 3 | Green (`bg-green-500`) | Low |

Protection priorities are mapped from strings: `high -> 1`, `medium -> 2`, `low -> 3`.

Module badge colours: Investment blue, Estate purple, Protection green.

### 10.10 `AreasToConsiderCard.vue`

**File:** `resources/js/components/Dashboard/AreasToConsiderCard.vue`

**ACTIVELY USED in Dashboard.vue** with `<AreasToConsiderCard :limit="5" />`. Shows gap areas where the user's financial profile is incomplete.

**Props:** `limit` (Number, default 0 = no limit)

**11 Gap Areas with Priorities:**

| Priority | ID | Title | Condition to Show | Route |
|---|---|---|---|---|
| 1 | `letter` | Letter to Spouse / Expression of Wishes | No letter content (checks API `/user/letter-to-spouse/exists`) | `/valuable-info?section=letter` |
| 2 | `will` | Will | No will (`has_will` false, checks API `/estate/will`) | `/valuable-info?section=will` |
| 3 | `critical-illness` | Critical Illness Cover | No CI policies AND not retired AND age <= 50 | `/protection` |
| 4 | `income-protection` | Income Protection | No IP policies AND not retired | `/protection` |
| 5 | `life-insurance` | Life Insurance | No life policies AND married AND not retired | `/protection` |
| 6 | `pensions` | Pensions | No DC, DB, or state pensions | `/net-worth/retirement` |
| 7 | `isa` | ISA Allowance | No ISA investment or savings accounts | `/net-worth/investments` |
| 8 | `emergency-fund` | Emergency Fund | Total savings < 1000 | `/net-worth/savings` |
| 9 | `goals` | Financial Goals | No goals set | `/goals` |
| 10 | `income` | Income Details | No employment/pension income recorded | `/valuable-info?section=income` |
| 11 | `properties` | Your Properties | No property assets AND not paying rent | `/net-worth` |

**Additional API calls on mount:**
- `GET /user/letter-to-spouse/exists` -> sets `hasLetterContent`
- `GET /estate/will` -> sets `willData`

Each area has a unique icon, icon background class, and icon text class.

### 10.11 `AreasToCompleteCard.vue`

**File:** `resources/js/components/Dashboard/AreasToCompleteCard.vue`

**ACTIVELY USED in Dashboard.vue** when `currentUser.onboarding_skipped_steps` is non-empty. Shows onboarding steps that the user skipped.

**Display condition:** `v-if="hasAreasToComplete"` which checks `currentUser.onboarding_skipped_steps.length > 0`

**Step configuration (10 possible steps):**

| Step Key | Title | Route |
|---|---|---|
| `personal_info` | Personal Information | `/valuable-info?section=personal` |
| `family_info` | Family Information | `/valuable-info?section=family` |
| `income` | Income Details | `/valuable-info?section=income` |
| `expenditure` | Expenditure | `/valuable-info?section=expenditure` |
| `domicile_info` | Domicile Information | `/valuable-info?section=domicile` |
| `protection_policies` | Protection Policies | `/protection` |
| `assets` | Assets | `/net-worth` |
| `liabilities` | Liabilities | `/net-worth` |
| `will_info` | Will Information | `/valuable-info?section=will` |
| `trust_info` | Trust Information | `/estate` |

Footer link: "Complete Setup in Onboarding" -> `/onboarding`

### 10.12 `AffordabilityOverviewCard.vue`

**File:** `resources/js/components/Dashboard/AffordabilityOverviewCard.vue`

**NOT used in current Dashboard.vue** (InvestmentsOverviewCard has an integrated cash flow section instead). Shows monthly cash flow analysis.

**Computation:**
- `monthlyIncome = totalAnnualIncome / 12`
- `monthlyExpenditure = expenditureProfile.total_monthly_expenditure + financialCommitmentsTotal`
- `monthlySurplus = monthlyIncome - monthlyExpenditure`

**Financial commitments** loaded via `userProfileService.getFinancialCommitments()` on mount.

Spouse section shown when user has a linked spouse. Red warning banner when `monthlySurplus < 0`.

Navigates to `/net-worth/cash` on click.

### 10.13 `InvestmentsOverviewCard.vue`

**File:** `resources/js/components/Dashboard/InvestmentsOverviewCard.vue`

**NOT directly used in current Dashboard.vue** (Dashboard.vue has its own inline investment/savings rendering within DashboardCard). Dual-section card:

**Section 1 - Portfolio (top, navigates to `/net-worth/investments`):**
- Total portfolio value
- Weighted average annualised return
- Account list with provider names and joint ownership badges

**Section 2 - Cash Flow (bottom, navigates to `/net-worth/cash`):**
- Current month cash flow (surplus/deficit)
- Money In / Money Out breakdown

Account type formatting map: `stocks_and_shares_isa -> ISA`, `sipp -> Self-Invested Personal Pension`, `gia -> General Investment Account`, etc.

### 10.14 `TaxOptimisationCard.vue`

**File:** `resources/js/components/Dashboard/TaxOptimisationCard.vue`

**NOT used in current Dashboard.vue** (Allowances section is inline in Dashboard.vue). Shows progress bars for tax allowance usage:

**Allowances tracked:**
| Allowance | 2025/26 Limit | Source |
|---|---|---|
| ISA | 20,000 | Sum of investment ISA subscriptions + savings ISA contributions YTD |
| Pension Annual Allowance | 60,000 | From `annualAllowance` API or fallback DC pension calculation |
| Pension Carry Forward | 60,000 | Shown only when contributions exceed standard allowance |
| Capital Gains Tax | 3,000 | Only if user has non-ISA investments (GIA/trading). CGT used is always 0 (no disposal tracking) |
| Dividend | 500 | Only if user has dividend income. Uses `annual_dividend_income` from profile |

**Progress bar colours:** >= 90% green, >= 50% primary, >= 25% blue, < 25% grey.

**Expiring allowances warning:** Shown within 3 months of tax year end (5 April) for ISA (> 5000 remaining), CGT (> 1000), Dividend (> 100). Pension is excluded because it can carry forward.

### 10.15 `UKTaxesAllowancesCard.vue`

**File:** `resources/js/components/Dashboard/UKTaxesAllowancesCard.vue`

**NOT used in current Dashboard.vue** (removed from grid - accessible via `/uk-taxes` route). Full tax reference modal with 7 tabbed sections and **hardcoded 2025/26 values** defined in the component's `setup()`:

**Tabs:**
1. **Income Tax & National Insurance** - Bands, rates, personal allowance, NI Class 1
2. **Capital Gains Tax & Dividends** - CGT rates (general + residential property), dividend tax rates, allowances
3. **Inheritance Tax** - NRB, RNRB, taper, PET taper relief, gifting exemptions
4. **Pensions** - Annual allowance, MPAA, carry forward, tapered AA, state pension
5. **Tax-Free Savings (ISAs)** - ISA allowance, LISA details, Junior ISA
6. **Other Allowances** - Marriage allowance, savings allowance, child benefit
7. **Calculations** - Step-by-step examples for income tax, IHT, CGT, pension AA, emergency fund

**Key hardcoded values:**
- Personal Allowance: 12,570
- Income Tax bands: 20% (0-37,700), 40% (37,700-125,140), 45% (125,140+)
- NI Employee: 12% (12,570-50,270), 2% above
- CGT Annual Exempt: 3,000
- Dividend Allowance: 500
- IHT NRB: 325,000, RNRB: 175,000, Rate: 40%
- Pension AA: 60,000, MPAA: 10,000
- ISA: 20,000, LISA: 4,000, Junior ISA: 9,000
- Full New State Pension: 11,502.40/yr

### 10.16 `UKTaxesOverviewCard.vue`

**File:** `resources/js/components/Dashboard/UKTaxesOverviewCard.vue`

**NOT used in current Dashboard.vue** (comment in template: "UK Taxes card removed - accessible via /uk-taxes route and admin panel"). Lightweight teaser card:
- Shows tax year 2025/26
- Lists key allowances: Personal Allowance 12,570, ISA 20,000, Pension AA 60,000
- Badges: Income Tax, Capital Gains Tax, Inheritance Tax, Pensions
- Navigates to `/uk-taxes` on click

---

## 11. Frontend Routing

### Dashboard Route

**File:** `resources/js/router/index.js`

```javascript
{
    path: '/dashboard',
    name: 'Dashboard',
    component: Dashboard,  // () => import('@/views/Dashboard.vue')
    meta: { requiresAuth: true },
}
```

The dashboard is the default post-login destination. After successful authentication, users are redirected to `/dashboard`. The route requires authentication via the `requiresAuth` meta flag.

The `/dashboard` path is also used as the "Home" breadcrumb root across other module routes.

---

## 12. Cross-Module Integration

Dashboard.vue is the most cross-module-dependent view in the application. It reads from the following Vuex stores and APIs:

### Vuex Stores Read

| Store | State Properties | Getters Used |
|---|---|---|
| `auth` | - | `isAdmin`, `currentUser` |
| `netWorth` | - | `netWorth`, `totalAssets`, `totalLiabilities`, `overview` |
| `protection` | - | `totalCoverage`, `totalPremium`, `lifePolicies`, `criticalIllnessPolicies`, `incomeProtectionPolicies` |
| `estate` | `willInfo` | `ihtLiability`, `taxableEstate` |
| `trusts` | `trusts` | - |
| `retirement` | `dcPensions`, `dbPensions`, `statePension`, `profile`, `requiredCapital`, `analysis`, `annualAllowance`, `projections` | `totalPensionWealth`, `yearsToRetirement`, `projectedIncome` |
| `investment` | `accounts` (as investmentAccounts), `analysis` | `totalPortfolioValue` |
| `savings` | `expenditureProfile`, `accounts`, `isaAllowance` | - |
| `goals` | `dashboardOverview`, `projectionData` | `dashboardData` |
| `userProfile` | - | - (used by child components) |

### Direct API Calls from Dashboard.vue

| API Service | Method | Purpose |
|---|---|---|
| `userProfileService` | `getFinancialCommitments()` | Loads financial commitments for affordability display |

### Direct API Calls from Child Components

| Component | API Call | Purpose |
|---|---|---|
| `AreasToConsiderCard` | `GET /user/letter-to-spouse/exists` | Check if letter content exists |
| `AreasToConsiderCard` | `GET /estate/will` | Check if user has a will |
| `ActionsOverviewCard` | `investmentService.getPortfolioStrategy()` | Fetch investment recommendations |
| `ActionsOverviewCard` | `protectionService.getRecommendations()` | Fetch protection recommendations |
| `ActionsOverviewCard` | `estateService.calculateSecondDeathIHTPlanning()` | Fetch estate strategies |

---

## 13. Profile Completeness

### AreasToCompleteCard Logic

The `AreasToCompleteCard` component surfaces onboarding steps that the user explicitly skipped. The data source is `currentUser.onboarding_skipped_steps`, an array stored on the user model.

**Display condition in Dashboard.vue:**
```javascript
hasAreasToComplete() {
    const skippedSteps = this.currentUser?.onboarding_skipped_steps || [];
    return skippedSteps.length > 0;
}
```

When displayed, `AreasToCompleteCard` is rendered FIRST in the grid (before the Net Worth card) to prompt the user to fill in missing information.

### AreasToConsiderCard Logic

The `AreasToConsiderCard` analyses the user's actual data state (not onboarding steps) to identify financial planning gaps. It checks 11 distinct areas (see section 10.10) and only shows areas where data is genuinely missing or insufficient.

Key intelligence features:
- **Retirement-awareness:** Retired users are not shown income protection, life insurance, or critical illness suggestions
- **Age-gating:** Critical illness only suggested for users aged 50 or under
- **Marital-awareness:** Life insurance only suggested for married users
- **Renter-awareness:** Property suggestion hidden for users paying rent
- **API-driven checks:** Letter-to-spouse and will status verified via API rather than local state

---

## 14. Seeder Data

The Dashboard module has **no dedicated seeders**. It relies entirely on data seeded by other modules:

| Seeder | Dashboard Relevance |
|---|---|
| `PreviewUserSeeder` | Creates the 6 preview personas that Dashboard renders |
| `TaxConfigurationSeeder` | Populates tax values used by allowance calculations |
| All module seeders | Seed the data that Dashboard aggregates |

---

## 15. API Routing

**File:** `routes/api.php` (lines 309-316)

```php
// Dashboard routes (aggregated data from all modules)
Route::middleware('auth:sanctum')->prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/financial-health-score', [DashboardController::class, 'financialHealthScore']);
    Route::get('/alerts', [DashboardController::class, 'alerts']);
    Route::post('/alerts/{id}/dismiss', [DashboardController::class, 'dismissAlert']);
    Route::post('/invalidate-cache', [DashboardController::class, 'invalidateCache']);
});
```

| Method | URI | Controller Method | Middleware |
|---|---|---|---|
| GET | `/api/dashboard` | `DashboardController@index` | `auth:sanctum` |
| GET | `/api/dashboard/financial-health-score` | `DashboardController@financialHealthScore` | `auth:sanctum` |
| GET | `/api/dashboard/alerts` | `DashboardController@alerts` | `auth:sanctum` |
| POST | `/api/dashboard/alerts/{id}/dismiss` | `DashboardController@dismissAlert` | `auth:sanctum` |
| POST | `/api/dashboard/invalidate-cache` | `DashboardController@invalidateCache` | `auth:sanctum` |

**Related routes used by Dashboard.vue (cross-module):**
- `GET /api/net-worth/overview`
- `GET /api/protection`
- `GET /api/estate`
- `GET /api/estate/iht/...`
- `GET /api/trusts`
- `GET /api/retirement/...` (multiple endpoints)
- `GET /api/investment/...` (multiple endpoints)
- `GET /api/savings`
- `GET /api/retirement/annual-allowance/2025-26`
- `GET /api/goals/dashboard-overview`
- `GET /api/goals/projection`
- `GET /api/user/profile`
- `GET /api/user/financial-commitments`
- `GET /api/user/letter-to-spouse/exists`
- `GET /api/estate/will`

---

## 16. Key Constants

### Financial Health Score Weights

```
Protection Coverage:        20% (0.20)
Emergency Fund:             15% (0.15)
Retirement Readiness:       25% (0.25)
Investment Diversification: 20% (0.20)
Estate Planning:            20% (0.20)
                           -----
Total:                     100%
```

### FHS Labels

| Score Range | Label |
|---|---|
| >= 80 | Excellent (Financial Health) |
| >= 60 | Good (Financial Health) |
| >= 40 | Fair (Financial Health) |
| < 40 | Needs Improvement |

### Emergency Fund Score Formula

```
score = min(100, (runway_months / 6) * 100)
```

Where `runway_months = total_savings / monthly_essential_expenses`. Target is 6 months = 100%.

### Age Gates

| Feature | Condition | Effect |
|---|---|---|
| Retirement card | `userAge < 35` | Hidden entirely |
| Estate Planning card | `userAge <= 35` | Hidden entirely |
| LISA Allowance | `userAge >= 40` OR has property | Not shown |
| Critical Illness suggestion | `userAge > 50` OR retired | Not suggested |
| Life Insurance suggestion | Not married OR retired | Not suggested |

### Cache TTLs

| Cache | TTL (seconds) | Human Readable |
|---|---|---|
| Dashboard overview | 300 | 5 minutes |
| Financial health score | 3600 | 1 hour |
| Alerts | 900 | 15 minutes |

### UK Tax Allowances (2025/26, hardcoded in TaxOptimisationCard)

| Allowance | Amount |
|---|---|
| ISA Annual Allowance | 20,000 |
| Pension Annual Allowance | 60,000 |
| CGT Annual Exempt Amount | 3,000 |
| Dividend Allowance | 500 |
| LISA Annual Allowance | 4,000 |

### Allowance Progress Bar Colours (Dashboard.vue)

| Condition | Colour |
|---|---|
| >= 95% used OR over limit | `bg-red-500` |
| >= 75% used | `bg-blue-500` |
| < 75% used | `bg-green-500` |

### Net Worth Donut Chart Asset Colours

From `ASSET_COLORS` in `resources/js/constants/designSystem.js`:

| Category | Colour | Hex |
|---|---|---|
| Pensions | Trust Blue | `#1257A0` |
| Property | Green | `#15803D` |
| Investments | Slate | `#475569` |
| Cash | Blue | `#60A5FA` |
| Business | Purple | `#7C3AED` |
| Chattels | Blue light | `#93C5FD` |

Liability colours (defined in Dashboard.vue):

| Category | Hex |
|---|---|
| Mortgages | `#B91C1C` |
| Loans | `#991B1B` |
| Credit Cards | `#DC2626` |
| Other | `#7F1D1D` |

### Retired User Income Calculation

For retired users, the retirement card shows actual income breakdown:
```
Pension Drawdown = DC pension wealth * 0.04 (4% safe withdrawal rate)
DB Pension Income = sum of all DB schemes' accrued_annual_pension
State Pension Income = user's configured amount OR £11,502 default
Total Income = Pension Drawdown + DB Pension + State Pension
```

### Ownership Value Adjustment

Used throughout Dashboard.vue for joint/tenants-in-common assets:
```javascript
ownershipValue(account, valueField) {
    const value = parseFloat(account[valueField] || 0);
    if (account.ownership_type === 'joint' || account.ownership_type === 'tenants_in_common') {
        const percentage = account.ownership_percentage ?? 50;
        return value * (percentage / 100);
    }
    return value;
}
```

---

## 17. Known Issues

### 17.1 Backend Aggregator is a Stub

**Severity: Architectural debt**

The `DashboardAggregator` service injects four agents but never calls them. All 10 private methods (`getProtectionSummary`, `getSavingsSummary`, `getInvestmentSummary`, `getRetirementSummary`, `getEstateSummary`, `getProtectionScore`, `getEmergencyFundScore`, `getRetirementScore`, `getInvestmentScore`, `getEstateScore`) return hardcoded values. The five private alert methods (`getProtectionAlerts`, `getSavingsAlerts`, `getInvestmentAlerts`, `getRetirementAlerts`, `getEstateAlerts`) also return hardcoded arrays.

**Impact:** The backend dashboard API endpoints work but return meaningless data. The frontend works correctly by bypassing the backend entirely.

### 17.2 Dashboard Store Largely Unused

**Severity: Low - dead code**

The `dashboard.js` Vuex store and `dashboardService.js` are fully implemented but Dashboard.vue does not dispatch any dashboard store actions. Instead, it dispatches actions to individual module stores. The dashboard store would become relevant if the frontend were switched to use the backend aggregator.

### 17.3 DismissAlert Has No Persistence

**Severity: Medium - feature gap**

`DashboardController::dismissAlert()` only clears the alerts cache. The comment says "In a real implementation, this would update a database record." Since alerts are re-generated from hardcoded data, dismissing an alert has no lasting effect.

### 17.4 UKTaxesAllowancesCard Has Hardcoded Tax Values

**Severity: Medium - maintenance burden**

The `UKTaxesAllowancesCard` component has all 2025/26 tax values hardcoded in its `setup()` function rather than fetching from the `TaxConfigService` backend. When tax values change for a new tax year, this component must be manually updated separately from the database-driven tax configuration.

### 17.5 CGT Usage Always Zero

**Severity: Low - feature gap**

`TaxOptimisationCard` shows CGT allowance usage but `cgtUsed` is always 0 because the system does not track investment disposals.

### 17.6 Several Dashboard Components Not Currently Rendered

**Severity: Low - dead code**

The following components exist in `components/Dashboard/` but are not used in the current `Dashboard.vue`:
- `NetWorthOverviewCard.vue` (Dashboard has inline net worth rendering)
- `NetWorthSummary.vue` (alternative view)
- `GoalsOverviewCard.vue` (replaced by GoalsProjectionChartDashboard)
- `GoalsProjectionChartMini.vue` (sparkline variant)
- `ActionsOverviewCard.vue` (was previously included)
- `AffordabilityOverviewCard.vue` (InvestmentsOverviewCard has cash flow inline)
- `InvestmentsOverviewCard.vue` (Dashboard has inline investment rendering)
- `TaxOptimisationCard.vue` (Dashboard has inline allowances rendering)
- `UKTaxesAllowancesCard.vue` (removed from grid)
- `UKTaxesOverviewCard.vue` (removed from grid)
- `FinancialHealthScore.vue` (not in current grid)
- `AlertsPanel.vue` (not in current grid)

Only 4 of 16 components are actively rendered: `DashboardCard`, `GoalsProjectionChartDashboard`, `AreasToConsiderCard`, `AreasToCompleteCard`.

### 17.7 MFA Banner Dismiss Uses localStorage

**Severity: Low**

The 2FA security reminder banner dismissal is stored in `localStorage.setItem('mfaBannerDismissed', 'true')`. This means:
- Clearing browser data re-shows the banner
- The dismissal does not sync across devices
- Preview users never see the banner (guarded by `user.is_preview_user`)

### 17.8 Estate IHT Calculation Branching

**Severity: None - working as designed**

Dashboard.vue selects the IHT calculation action based on marital status:
```javascript
const estateCalculationAction = isMarried
    ? 'estate/calculateSecondDeathIHTPlanning'
    : 'estate/calculateIHT';
```

This is correct behaviour but worth noting as an architectural decision.

---

## 18. Deep Dive: Cross-Module Data Loading Architecture

### The `loadAllData()` Method

`Dashboard.vue` orchestrates all data loading through a single method called from a watcher on `currentUser`. This prevents race conditions where data would be fetched before the user is authenticated.

```javascript
watch: {
    currentUser: {
        immediate: true,
        handler(user) {
            if (user && !this.dataLoaded) {
                this.dataLoaded = true;
                this.loadAllData();
            }
        }
    }
}
```

### Module Loaders Definition

The method defines an array of 15 module loader objects, each mapping a loading flag name to a Vuex action:

```javascript
const moduleLoaders = [
    { name: 'netWorth', action: 'netWorth/fetchOverview' },
    { name: 'protection', action: 'protection/fetchProtectionData' },
    { name: 'estate', action: 'estate/fetchEstateData' },
    { name: 'estate', action: estateCalculationAction, payload: {} },
    { name: 'retirement', action: 'trusts/fetchTrusts' },
    { name: 'investment', action: 'userProfile/fetchProfile' },
    { name: 'retirement', action: 'retirement/fetchRetirementData' },
    { name: 'retirement', action: 'retirement/fetchRequiredCapital' },
    { name: 'retirement', action: 'retirement/analyseRetirement' },
    { name: 'retirement', action: 'retirement/fetchProjections' },
    { name: 'investment', action: 'investment/fetchInvestmentData' },
    { name: 'investment', action: 'investment/analyseInvestment' },
    { name: 'taxAllowances', action: 'savings/fetchSavingsData' },
    { name: 'taxAllowances', action: 'retirement/fetchAnnualAllowance', payload: '2025/26' },
    { name: 'goals', action: 'goals/fetchDashboardOverview' },
];
```

### Loading Flag Management

Multiple actions can share a loading flag name (e.g., `retirement` has 4 actions). The loading flag only clears when ALL actions for that module have completed:

```javascript
const moduleActionCounts = {};
moduleLoaders.forEach(loader => {
    moduleActionCounts[loader.name] = (moduleActionCounts[loader.name] || 0) + 1;
});

// After each result:
moduleCompletedCounts[module] = (moduleCompletedCounts[module] || 0) + 1;
if (moduleCompletedCounts[module] >= moduleActionCounts[module]) {
    this.loading[module] = false;
}
```

Loading flags:
| Flag | Action Count | Actions |
|---|---|---|
| `netWorth` | 1 | `netWorth/fetchOverview` |
| `protection` | 1 | `protection/fetchProtectionData` |
| `estate` | 2 | `estate/fetchEstateData`, `estate/calculateSecondDeathIHTPlanning` (or `calculateIHT`) |
| `retirement` | 4 | `trusts/fetchTrusts`, `retirement/fetchRetirementData`, `retirement/fetchRequiredCapital`, `retirement/analyseRetirement`, `retirement/fetchProjections` |
| `investment` | 3 | `userProfile/fetchProfile`, `investment/fetchInvestmentData`, `investment/analyseInvestment` |
| `taxAllowances` | 2 | `savings/fetchSavingsData`, `retirement/fetchAnnualAllowance` |
| `goals` | 1 | `goals/fetchDashboardOverview` |

**Note:** The `retirement` flag is actually mapped to 5 actions (trusts/fetchTrusts is listed under `retirement`). The `investment` flag maps to `userProfile/fetchProfile` which loads user profile data needed for investment context.

### Parallel Execution with Graceful Failure

All 15 actions are dispatched simultaneously via `Promise.allSettled`. Each promise is wrapped to return a result object:

```javascript
const promises = moduleLoaders.map(loader =>
    this.$store.dispatch(loader.action, loader.payload)
        .then(() => ({ module: loader.name, success: true }))
        .catch(error => ({
            module: loader.name,
            success: false,
            error: error.response?.data?.message || error.message || 'Unknown error'
        }))
);

const results = await Promise.allSettled(promises);
```

This ensures that a failure in one module (e.g., retirement API is down) does not prevent other modules from loading. Each card independently shows its content or loading skeleton.

### Post-Load: Goals Projection

After the main parallel load completes, the goals projection chart data is fetched separately:

```javascript
try {
    await this.fetchProjection();
} catch (e) {
    // Projection is optional, don't block
}
```

### Additional Parallel Load: Financial Commitments

`loadFinancialCommitments()` is called outside the `Promise.allSettled` array, running in parallel but independently:

```javascript
this.loadFinancialCommitments(); // No await - fire and forget
```

### Security Banner (2FA Reminder)

Shown at the top of the dashboard when:
1. User is authenticated (`currentUser` exists)
2. User is NOT a preview user (`!user.is_preview_user`)
3. MFA is not enabled (`user.mfa_enabled !== true`)
4. Banner has not been dismissed (`!mfaBannerDismissed`)

Green colour scheme (`bg-green-100 border border-green-300`). Links to `/settings/security`. Dismissible via localStorage.

### Will Question Inline

The Estate Planning card includes an inline "Do you currently have a valid will?" question when `willInfo.will_answered` is not true. Clicking Yes/No dispatches `estate/saveWill` with `{ has_will: boolean }`. The click handler has `@click.stop` to prevent the card's navigation event from firing.

### Net Worth Donut Chart

Dashboard.vue renders an ApexCharts donut chart for net worth with:
- Asset categories from `netWorthBreakdown.assets` (property, pensions, investments, cash, business, chattels)
- Liability categories from `netWorthBreakdown.liabilities` (mortgages, loans, credit_cards, other)
- Centre label showing total net worth (green if positive, red if negative)
- Donut hole size: 65%
- Chart height: 260px
- Dynamic chart key for re-rendering: `nw-donut-{seriesLength}-{roundedTotal}`
- Responsive: height reduces to 240px on screens < 768px
- Tooltip shows value and percentage of total

### Grid Layout

Three-column responsive grid: `grid-cols-1 md:grid-cols-2 lg:grid-cols-3`

Card rendering order:
1. `AreasToCompleteCard` (if `hasAreasToComplete`)
2. Net Worth card (always shown, has empty state)
3. Estate Planning card (if `hasEstateData` AND age > 35)
4. Investments & Savings card (if `hasInvestmentData`)
5. Protection card (if `hasProtectionData`)
6. Retirement card (if `hasRetirementData` AND age >= 35)
7. Allowances card (always shown, has empty state)
8. Goals & Life Events card (always shown, spans `lg:col-span-2`)
9. AreasToConsiderCard (always shown, has "all complete" state)

### Tax Year Computation

Used by allowance display:
```javascript
currentTaxYear() {
    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();
    const day = now.getDate();
    const taxYearStart = (month > 3 || (month === 3 && day >= 6)) ? year : year - 1;
    return `${taxYearStart}/${String(taxYearStart + 1).slice(-2)}`;
}
```

Carry forward year spans previous 3 years: `${taxYearStart - 3}/${...}`.

### ISA Allowance Computed Properties

Dashboard.vue computes ISA allowance data from the savings store:

**LISA eligibility:** `userAge < 40 AND no property asset in netWorthBreakdown`

When LISA eligible, the ISA allowance display subtracts 4,000 from the total 20,000 allowance and shows LISA as a separate section.

ISA data source: `savings/isaAllowance` (from the savings API, which aggregates cash ISA and investment ISA subscriptions).

### Pension Allowance Computed Properties

Two data paths:
1. **Primary:** From `retirement/annualAllowance` API response (includes `available_allowance`, `total_contributions`, `remaining_allowance`, `is_tapered`, `carry_forward_available`)
2. **Fallback:** Calculated from DC pension data by summing `(employee_contribution + employer_contribution) * frequency_multiplier`

Carry forward is shown only when total contributions exceed the standard allowance AND `carry_forward_available > 0`.
