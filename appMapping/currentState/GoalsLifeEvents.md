# Goals & Life Events Module - Current State Documentation

**Last Updated:** 2026-02-18
**Module Version:** Part of Fynla v0.7.0
**Status:** Fully functional with 11 goal types, life events, contribution tracking, projections, and household views

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Database Schema](#2-database-schema)
3. [Models](#3-models)
4. [Controllers](#4-controllers)
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
18. [Goal Module Assignment Decision Tree](#18-goal-module-assignment-decision-tree)

---

## 1. System Overview

The Goals & Life Events module covers two complementary concepts:

| Concept | Description | Direction |
|---|---|---|
| **Goals** | Things you save towards (emergency fund, property, retirement) | User-driven actions |
| **Life Events** | Things that happen to you (inheritance, bonus, large purchase) | External occurrences |

Both feed into a unified projection chart that shows future net worth with events overlaid as icons.

### Architecture Flow

```
GoalsController/LifeEventController
    --> GoalsAgent (analysis, recommendations, scenarios)
    --> GoalAssignmentService (module routing)
    --> GoalAffordabilityService (budget analysis)
    --> GoalProgressService (streaks, milestones, contributions)
    --> GoalRiskService (investment projections, probability)
    --> GoalsProjectionService (net worth projection chart)
    --> LifeEventService (CRUD, event types, age grouping)
```

### Key Features

- **11 goal types** with auto-assignment to modules (savings, investment, property, retirement)
- **Contribution tracking** with streak counting and milestone celebrations (25%, 50%, 75%, 100%)
- **Affordability analysis** comparing goal commitments against monthly surplus
- **Investment projections** with risk-adjusted Monte Carlo probability estimation
- **What-if scenarios** (increase contribution, reach earlier, reduce target, add lump sum)
- **Property cost calculator** with UK SDLT, legal fees, survey costs
- **16 life event types** (9 income, 7 expense) with certainty levels
- **Projection chart** combining net worth, goals, and life events over time
- **Household view** combining user and spouse goals/events with permission check
- **Glide path** asset allocation recommendations based on time to goal

---

## 2. Database Schema

### 2.1 `goals` Table (Migration: `2026_01_24_160001`)

```
id                          BIGINT PK AUTO_INCREMENT
user_id                     BIGINT FK -> users (CASCADE)
goal_name                   VARCHAR(100)
goal_type                   ENUM(emergency_fund, property_purchase, home_deposit,
                                 education, retirement, wealth_accumulation,
                                 wedding, holiday, car_purchase, debt_repayment, custom)
custom_goal_type_name       VARCHAR(100) NULLABLE
description                 TEXT NULLABLE
target_amount               DECIMAL(15,2)
current_amount              DECIMAL(15,2) DEFAULT 0
target_date                 DATE
start_date                  DATE NULLABLE
assigned_module             ENUM(savings, investment, property, retirement)
module_override             BOOLEAN DEFAULT false
priority                    ENUM(critical, high, medium, low) DEFAULT medium
is_essential                BOOLEAN DEFAULT false
status                      ENUM(active, paused, completed, abandoned) DEFAULT active
monthly_contribution        DECIMAL(12,2) NULLABLE
contribution_frequency      ENUM(weekly, monthly, quarterly, annually) DEFAULT monthly
contribution_streak         INT UNSIGNED DEFAULT 0
longest_streak              INT UNSIGNED DEFAULT 0
last_contribution_date      DATE NULLABLE
linked_account_ids          JSON NULLABLE
linked_savings_account_id   BIGINT FK -> savings_accounts NULLABLE (SET NULL)
risk_preference             TINYINT UNSIGNED NULLABLE (1-5)
use_global_risk_profile     BOOLEAN DEFAULT true
ownership_type              ENUM(individual, joint) DEFAULT individual
joint_owner_id              BIGINT FK -> users NULLABLE (SET NULL)
ownership_percentage        DECIMAL(5,2) DEFAULT 100
show_in_projection          BOOLEAN DEFAULT true
show_in_household_view      BOOLEAN DEFAULT true
property_location           VARCHAR(255) NULLABLE
property_type               ENUM(house, flat, bungalow, terraced, semi_detached, detached, other) NULLABLE
is_first_time_buyer         BOOLEAN NULLABLE
estimated_property_price    DECIMAL(15,2) NULLABLE
deposit_percentage          DECIMAL(5,2) NULLABLE
stamp_duty_estimate         DECIMAL(12,2) NULLABLE
additional_costs_estimate   DECIMAL(12,2) NULLABLE
milestones                  JSON NULLABLE
projection_data             JSON NULLABLE
completed_at                TIMESTAMP NULLABLE
completion_notes            TEXT NULLABLE
created_at / updated_at     TIMESTAMPS
deleted_at                  TIMESTAMP NULLABLE (soft deletes)

INDEXES:
  (user_id, status)
  (user_id, assigned_module)
  (user_id, goal_type)
```

### 2.2 `goal_contributions` Table (Migration: `2026_01_24_160002`)

```
id                      BIGINT PK AUTO_INCREMENT
goal_id                 BIGINT FK -> goals (CASCADE)
user_id                 BIGINT FK -> users (CASCADE)
amount                  DECIMAL(12,2)
contribution_date       DATE
contribution_type       ENUM(manual, automatic, lump_sum, interest, adjustment)
notes                   TEXT NULLABLE
goal_balance_after      DECIMAL(15,2)
streak_qualifying       BOOLEAN DEFAULT true
created_at / updated_at TIMESTAMPS

INDEXES:
  (goal_id, contribution_date)
  (user_id, contribution_date)
```

### 2.3 `life_events` Table (Migration: `2026_02_03_120001`)

```
id                      BIGINT PK AUTO_INCREMENT
user_id                 BIGINT FK -> users (CASCADE)
event_name              VARCHAR(100)
event_type              ENUM(inheritance, gift_received, bonus, redundancy_payment,
                             property_sale, business_sale, pension_lump_sum,
                             lottery_windfall, large_purchase, home_improvement,
                             wedding, education_fees, gift_given, medical_expense,
                             custom_income, custom_expense)
description             TEXT NULLABLE
amount                  DECIMAL(15,2)
impact_type             ENUM(income, expense)
expected_date           DATE
certainty               ENUM(confirmed, likely, possible, speculative) DEFAULT likely
icon                    VARCHAR(50) NULLABLE
show_in_projection      BOOLEAN DEFAULT true
show_in_household_view  BOOLEAN DEFAULT true
ownership_type          ENUM(individual, joint) DEFAULT individual
joint_owner_id          BIGINT FK -> users NULLABLE
ownership_percentage    DECIMAL(5,2) DEFAULT 100
status                  ENUM(expected, confirmed, completed, cancelled) DEFAULT expected
occurred_at             TIMESTAMP NULLABLE
created_at / updated_at TIMESTAMPS
deleted_at              TIMESTAMP NULLABLE (soft deletes)

INDEXES:
  (user_id, status)
  (user_id, expected_date)
  (user_id, impact_type)
```

### 2.4 Legacy Tables

| Table | Location | Notes |
|---|---|---|
| `savings_goals` | `mysql-schema.sql:1419` | Legacy table from Savings module; replaced by unified `goals` table |
| `investment_goals` | `mysql-schema.sql:707` | Separate table used by Investment module's `GoalProgressController` |

---

## 3. Models

### 3.1 `Goal` (362 lines)

**File:** `app/Models/Goal.php`
**Traits:** `Auditable`, `HasFactory`, `HasJointOwnership`, `SoftDeletes`

**Relationships:**
| Relationship | Type | Target |
|---|---|---|
| `user` | BelongsTo | `User` |
| `jointOwner` | BelongsTo | `User` (via `joint_owner_id`) |
| `linkedSavingsAccount` | BelongsTo | `SavingsAccount` |
| `contributions` | HasMany | `GoalContribution` |

**Appended Attributes:**
- `progress_percentage` - `(current_amount / target_amount) * 100`, capped at 100
- `days_remaining` - Days until target_date, min 0
- `months_remaining` - Months until target_date, min 0
- `is_on_track` - Linear projection with 10% margin; false if current_amount is 0
- `display_goal_type` - Human-readable goal type label

**Computed Attributes (not appended):**
- `amount_remaining` - `max(0, target - current)`
- `required_monthly_contribution` - `remaining / months_remaining`
- `current_milestone` - Last reached milestone (25, 50, 75, 100)
- `next_milestone` - Next milestone target

**Scopes:**
- `active()` - `status = 'active'`
- `completed()` - `status = 'completed'`
- `forModule($module)` - Filter by `assigned_module`
- `byPriority($priority)` - Filter by priority
- `onTrack()` - Active with `current_amount > 0` (basic SQL filter; full check requires PHP)

**Helper Methods:**
- `isPropertyGoal()` - Returns true for `property_purchase` or `home_deposit`
- `isInvestmentGoal()` - Returns true if `assigned_module === 'investment'`
- `isJoint()` - Returns true if `ownership_type === 'joint'` and `joint_owner_id` set

### 3.2 `GoalContribution` (73 lines)

**File:** `app/Models/GoalContribution.php`
**Traits:** `HasFactory`

**Relationships:**
- `goal` -> BelongsTo `Goal`
- `user` -> BelongsTo `User`

**Scopes:**
- `streakQualifying()` - Only streak-qualifying contributions
- `ofType($type)` - Filter by contribution type
- `inDateRange($start, $end)` - Filter by date range

### 3.3 `LifeEvent` (267 lines)

**File:** `app/Models/LifeEvent.php`
**Traits:** `Auditable`, `HasFactory`, `HasJointOwnership`, `SoftDeletes`

**Constants:**
- `INCOME_EVENT_TYPES` - 9 types: inheritance, gift_received, bonus, redundancy_payment, property_sale, business_sale, pension_lump_sum, lottery_windfall, custom_income
- `EXPENSE_EVENT_TYPES` - 7 types: large_purchase, home_improvement, wedding, education_fees, gift_given, medical_expense, custom_expense

**Relationships:**
- `user` -> BelongsTo `User`
- `jointOwner` -> BelongsTo `User`

**Appended Attributes:**
- `signed_amount` - Positive for income, negative for expense
- `display_event_type` - Human-readable label
- `years_until_event` - Years until expected_date

**Scopes:**
- `active()` - `status IN ('expected', 'confirmed')`
- `forProjection()` - `show_in_projection = true`
- `forHousehold()` - `show_in_household_view = true`
- `income()` / `expense()` - Filter by impact_type
- `inDateRange($from, $to)` - Filter by expected_date range
- `byCertainty($certainty)` - Filter by certainty level

**Methods:**
- `isPositive()` / `isNegative()` - Check impact type
- `getAgeAtEvent(User $user)` - Calculate user's age at event date
- `getUserShare(int $userId)` - Ownership share (primary or joint)
- `getAmountForUser(int $userId)` - Amount attributed to specific user

### 3.4 `SavingsGoal` (49 lines) - Legacy

**File:** `app/Models/SavingsGoal.php`
**Note:** Legacy model from Savings module. Simpler structure with `goal_name`, `target_amount`, `current_saved`, `target_date`, `priority`, `linked_account_id`, `auto_transfer_amount`.

---

## 4. Controllers

### 4.1 `GoalsController` (589 lines)

**File:** `app/Http/Controllers/Api/GoalsController.php`
**Dependencies:** GoalsAgent, GoalAssignmentService, GoalAffordabilityService, GoalProgressService, GoalRiskService, GoalsProjectionService, LifeEventService

| Method | Route | Description |
|---|---|---|
| `index` | GET /goals | List all goals (filterable by module, status, priority) |
| `analysis` | GET /goals/analysis | Comprehensive analysis with recommendations |
| `dashboardOverview` | GET /goals/dashboard-overview | Summary data for dashboard card |
| `store` | POST /goals | Create goal (auto-assigns module, calculates property costs) |
| `show` | GET /goals/{id} | Goal detail with progress, milestones, affordability, projections |
| `update` | PUT /goals/{id} | Update goal (handles completion, module re-assignment, ownership changes) |
| `destroy` | DELETE /goals/{id} | Soft delete goal |
| `recordContribution` | POST /goals/{id}/contribution | Record contribution, update streak, check completion |
| `getProjections` | GET /goals/{id}/projections | Risk-adjusted projections for investment goals |
| `getScenarios` | GET /goals/{id}/scenarios | What-if scenarios for a goal |
| `getContributionHistory` | GET /goals/{id}/contributions | Contribution history with monthly summary |
| `getProjection` | GET /goals/projection | Net worth projection with events |
| `getHouseholdSummary` | GET /goals/household-summary | Combined user + spouse goals and events |
| `calculatePropertyCosts` | POST /goals/calculate-property-costs | SDLT + fees calculator |
| `getGoalTypes` | GET /goals/types | Available goal types with metadata |
| `getRiskLevels` | GET /goals/risk-levels | Available risk levels for investment goals |

**Key Behaviours:**
- `store()` auto-assigns module unless `module_override` is true
- `store()` calculates SDLT, legal fees, survey costs for property goals
- `update()` handles status transition to `completed` via `progressService->completeGoal()`
- `update()` re-calculates module if goal_type or target_date changes
- `update()` manages single-record ownership pattern for joint goals
- `recordContribution()` auto-completes goal if progress reaches 100%
- All mutations clear GoalsAgent cache

### 4.2 `LifeEventController` (235 lines)

**File:** `app/Http/Controllers/Api/LifeEventController.php`
**Dependencies:** LifeEventService

| Method | Route | Description |
|---|---|---|
| `index` | GET /life-events | List events (optional `?household=true`) |
| `getEventTypes` | GET /life-events/types | Event types + certainty levels |
| `store` | POST /life-events | Create event (auto-determines impact_type from event_type) |
| `show` | GET /life-events/{id} | Get specific event |
| `update` | PUT /life-events/{id} | Update event |
| `destroy` | DELETE /life-events/{id} | Soft delete event |
| `markCompleted` | POST /life-events/{id}/complete | Mark as completed with optional occurred_at date |
| `getByAge` | GET /life-events/by-age | Events grouped by user's age for chart display |

---

## 5. Agent

### `GoalsAgent` (465 lines)

**File:** `app/Agents/GoalsAgent.php`
**Extends:** `BaseAgent`
**Dependencies:** GoalAssignmentService, GoalAffordabilityService, GoalProgressService, GoalRiskService

| Method | Description |
|---|---|
| `analyze(userId)` | Full analysis: by-module breakdown, summary, top goals, affordability, streaks |
| `generateRecommendations(analysisData)` | Priority-ranked recommendations (behind schedule, overcommitted, no emergency fund, streak praise) |
| `buildScenarios(userId, params)` | What-if scenarios: +20% contribution, 6 months earlier, -20% target, +GBP1,000 lump sum |
| `getDashboardOverview(userId)` | Dashboard card data: progress, on-track count, top 5 goals, streaks |
| `clearCache(userId)` | Clear user's cached analysis |

**Module Breakdown:**
Analysis groups active goals into 4 modules: savings, investment, property, retirement. Each module section includes count, total_target, total_current, average_progress, on_track_count.

**Priority Ordering:**
critical (1) > high (2) > medium (3) > low (4)

---

## 6. Services

### 6.1 `GoalAssignmentService` (278 lines)

**File:** `app/Services/Goals/GoalAssignmentService.php`
**Dependencies:** TaxConfigService

**Key Constants:**
- `SHORT_TERM_YEARS = 3` - Goals <= 3 years are short-term (savings)
- `INVESTMENT_MIN_AMOUNT = 5000` - Minimum for investment assignment

**Methods:**
| Method | Description |
|---|---|
| `determineModule(goalData)` | Auto-assign module: type-first, then time horizon + amount |
| `calculatePropertyCosts(goalData)` | Full UK property cost calculation |
| `calculateSDLT(price, isFirstTimeBuyer)` | Stamp Duty using TaxConfigService bands |
| `getRecommendedAllocation(goalData)` | Glide path: equities/bonds/cash based on years to goal |
| `getGoalTypes()` | 11 types with labels, default modules, and icons |

**Glide Path Allocation:**

| Years to Goal | Equities | Bonds | Cash | Risk Level |
|---|---|---|---|---|
| >15 | 80% | 15% | 5% | Aggressive |
| 10-15 | 70% | 25% | 5% | Growth |
| 5-10 | 50% | 40% | 10% | Balanced |
| 3-5 | 30% | 50% | 20% | Cautious |
| 1-3 | 15% | 45% | 40% | Defensive |
| <1 | 0% | 20% | 80% | Cash |

### 6.2 `GoalAffordabilityService` (311 lines)

**File:** `app/Services/Goals/GoalAffordabilityService.php`

**Key Methods:**
| Method | Description |
|---|---|
| `analyzeAffordability(goal, user)` | Full affordability check for a single goal |
| `calculateMonthlySurplus(user)` | Net income minus expenditure |
| `analyzeAllGoals(user)` | Portfolio-level affordability: total commitments vs surplus |
| `categorizeAffordability(ratio, surplus, required)` | 6 categories from comfortable to overcommitted |

**Affordability Categories:**

| Ratio | Category | Colour | Achievable? |
|---|---|---|---|
| surplus <= 0 | unaffordable | red | No |
| required <= 0 | completed | green | Yes |
| <= 0.3 | comfortable | green | Yes |
| 0.3-0.5 | moderate | blue | Yes |
| 0.5-0.75 | challenging | yellow | Yes |
| 0.75-1.0 | stretch | orange | Yes |
| > 1.0 | overcommitted | red | No |

**Tax Estimation:** Simplified UK income tax (20%/40%/45%) + NI (8%/2%) for affordability purposes. Does not use full TaxConfigService.

### 6.3 `GoalProgressService` (343 lines)

**File:** `app/Services/Goals/GoalProgressService.php`

**Milestones:** `[25, 50, 75, 100]`

**Key Methods:**
| Method | Description |
|---|---|
| `calculateProgress(goal)` | Detailed progress with expected vs actual, delta, status |
| `recordContribution(goal, amount, type, notes)` | Create contribution, update balance, update streak |
| `checkMilestones(goal)` | Check and record newly reached milestones |
| `updateContributionStreak(goal)` | Increment/reset streak based on frequency |
| `getContributionHistory(goal, limit)` | Recent contributions list |
| `getMonthlySummary(goal, months)` | Monthly contribution summary |
| `completeGoal(goal, notes)` | Mark as completed, ensure 100% milestone recorded |
| `getStreakDisplay(goal)` | Current streak, longest streak, intensity label |

**Progress Status:**
- `ahead` - delta >= 10%
- `on_track` - delta >= -5%
- `slightly_behind` - delta >= -15%
- `behind` - delta < -15%

**Streak Qualifying:** Contribution must be >= 80% of expected monthly contribution.

**Streak Grace Periods:**
- Weekly: 10 days
- Monthly: 35 days
- Quarterly: 95 days
- Annually: 370 days

**Streak Intensity Levels:**
- `blazing` - 12+ periods
- `hot` - 6-11 periods
- `warm` - 3-5 periods
- `starting` - 1-2 periods
- `cold` - 0 periods

### 6.4 `GoalRiskService` (357 lines)

**File:** `app/Services/Goals/GoalRiskService.php`

**Risk Levels (1-5):**

| Level | Label | Expected Return | Volatility |
|---|---|---|---|
| 1 | Conservative | 3% | 5% |
| 2 | Cautious | 4.5% | 8% |
| 3 | Balanced | 6% | 12% |
| 4 | Growth | 7.5% | 16% |
| 5 | Aggressive | 9% | 20% |

**Key Methods:**
| Method | Description |
|---|---|
| `getRiskParameters(goal, globalRiskProfile)` | Determine risk level (goal-specific or global) |
| `getProjections(goal, riskProfile)` | Full projection with probability, yearly data, confidence bands |
| `getUserRiskProfile(user)` | Fetch global RiskProfile |
| `getAvailableRiskLevels()` | All 5 levels with descriptions |

**Projection Calculations:**
- **Deterministic:** FV = PV(1+r)^n + PMT * ((1+r)^n - 1) / r (monthly compounding)
- **Probability:** Log-normal distribution approximation using Abramowitz-Stegun CDF
- **Confidence Bands:** 95% using exp(+/-1.96 * volatility * sqrt(years))
- **Required Contribution:** Solve annuity formula for PMT given target FV

### 6.5 `GoalsProjectionService` (612 lines)

**File:** `app/Services/Goals/GoalsProjectionService.php`
**Dependencies:** NetWorthService, LifeEventService, AssumptionsService, UKTaxCalculator

**Constants:**
- `DEFAULT_RETIREMENT_AGE = 68`
- `DEFAULT_PROJECTION_END_AGE = 90`
- `CACHE_TTL = 1800` (30 minutes)

**Key Methods:**
| Method | Description |
|---|---|
| `generateProjection(userId, household)` | Year-by-year projection from current age to 90 |
| `clearCache(userId)` | Clear individual + household projection caches |

**Projection Model:**
- FV = PV * (1 + real_rate)^n where real_rate = nominal_growth - inflation
- Each asset class grows independently:
  - **Cash:** No growth (used to cover annual expenditure deficit)
  - **Investments/Pensions:** `investment_growth - inflation` (from user assumptions)
  - **Property:** `property_growth - inflation`
- **Cash deficit model:** If expenditure > cash, deficit drawn from investments
- **Life events** applied as one-off income/expense at the year they occur
- **Goals** applied as expense at target_date year
- **Mortgage** reduces linearly to retirement age

**Output Structure:**
```
{
  current_age, retirement_age, projection_end_age,
  yearly_data: [{ age, year, phase, net_worth, income, expenditure, surplus,
                  assets: { cash, investments, property, pensions },
                  liabilities: { mortgage }, has_events }],
  events: [{ id, age, year, type, category, name, amount, impact, icon, color }],
  assumptions: { inflation_rate, investment_growth, property_growth },
  summary: { starting_net_worth, ending_net_worth, retirement_net_worth, peak_net_worth, ... },
  is_household
}
```

**Supports 3 Chart Views (frontend):**
1. Net Worth - Total net worth over time
2. Cash Flow - Income vs Expenditure
3. Asset Breakdown - Stacked asset categories

### 6.6 `LifeEventService` (383 lines)

**File:** `app/Services/Goals/LifeEventService.php`

**Key Methods:**
| Method | Description |
|---|---|
| `getEvents(userId, includeHousehold)` | All events, optionally including spouse's household-visible events |
| `getActiveEventsForProjection(userId, includeHousehold)` | Active events with `show_in_projection = true` |
| `getEventsByAge(userId, includeHousehold)` | Events grouped by user's age at occurrence |
| `calculateTotalImpactAtAge(userId, age)` | Sum of signed amounts for events in a specific year |
| `createEvent(userId, data)` | Create event (auto-determines impact_type from event_type) |
| `updateEvent(event, data)` | Update event (re-determines impact_type if type changed) |
| `deleteEvent(event)` | Soft delete |
| `markCompleted(event, occurredAt)` | Mark as completed |
| `getEventTypes()` | 16 event types with labels, impact_type, icons, colours, descriptions |
| `getCertaintyLevels()` | 4 levels with weights: confirmed=1.0, likely=0.75, possible=0.5, speculative=0.25 |

---

## 7. Validation Requests

### 7.1 `StoreGoalRequest` (62 lines)

**File:** `app/Http/Requests/Goals/StoreGoalRequest.php`

| Field | Rules |
|---|---|
| `goal_name` | required, string, max:100 |
| `goal_type` | required, in:11 types |
| `custom_goal_type_name` | nullable, required_if:goal_type=custom |
| `target_amount` | required, numeric, min:1, max:100000000 |
| `target_date` | required, date, after:today |
| `assigned_module` | nullable, in:savings,investment,property,retirement |
| `priority` | nullable, in:critical,high,medium,low |
| `ownership_type` | nullable, in:individual,joint |
| `joint_owner_id` | nullable, required_if:ownership_type=joint |
| Property fields | property_location, property_type (7 types), is_first_time_buyer, estimated_property_price, deposit_percentage |

### 7.2 `UpdateGoalRequest` (51 lines)

**File:** `app/Http/Requests/Goals/UpdateGoalRequest.php`
Same fields as Store but `sometimes` instead of `required`. Adds `status` (in:active,paused,completed,abandoned) and `completion_notes`.

### 7.3 `StoreLifeEventRequest` (57 lines)

**File:** `app/Http/Requests/StoreLifeEventRequest.php`

| Field | Rules |
|---|---|
| `event_name` | required, string, max:100 |
| `event_type` | required, in:16 types |
| `amount` | required, numeric, min:0.01, max:999999999.99 |
| `expected_date` | required, date, after:today |
| `certainty` | nullable, in:confirmed,likely,possible,speculative |
| `impact_type` | nullable (auto-determined), in:income,expense |
| `show_in_projection` | nullable, boolean |
| `ownership_type` | nullable, in:individual,joint |

### 7.4 `UpdateLifeEventRequest` (53 lines)

**File:** `app/Http/Requests/UpdateLifeEventRequest.php`
Same fields as Store but `sometimes`. Adds `status` (in:expected,confirmed,completed,cancelled).

---

## 8. Vuex Store

**File:** `resources/js/store/modules/goals.js` (695 lines)
**Namespace:** `goals`

### State

| Key | Type | Description |
|---|---|---|
| `goals` | Array | All user goals |
| `summary` | Object | Overall summary (total_goals, on_track_count, total_target, total_current, overall_progress) |
| `topGoals` | Array | Top priority goals |
| `byModule` | Object | Goals grouped by module (savings, investment, property, retirement) |
| `bestStreak` | Number | Best current contribution streak |
| `analysis` | Object | Full analysis data |
| `recommendations` | Array | Recommendations from agent |
| `goalTypes` | Array | Available goal types (lazy-loaded) |
| `riskLevels` | Array | Available risk levels (lazy-loaded) |
| `dashboardOverview` | Object | Dashboard card data |
| `selectedGoal` | Object | Currently selected goal |
| `lifeEvents` | Array | All life events |
| `eventTypes` | Array | Available event types (lazy-loaded) |
| `projectionData` | Object | Net worth projection data |
| `chartView` | String | Active chart view: `net_worth`, `cash_flow`, `asset_breakdown` |
| `viewMode` | String | `individual` or `household` |

### Key Getters

| Getter | Description |
|---|---|
| `activeGoals` | Goals with status === 'active' |
| `goalsForModule(module)` | Active goals for specific module |
| `goalsOnTrack` / `goalsBehind` | Filtered by is_on_track |
| `totalTargetAmount` / `totalCurrentAmount` | Sums for active goals |
| `overallProgress` | Percentage of target achieved |
| `priorityGoals` | Critical + high priority active goals |
| `activeLifeEvents` | Events with status expected or confirmed |
| `incomeEvents` / `expenseEvents` | Filtered by impact_type |
| `isHouseholdView` | Whether viewing household data |

### Key Actions

| Action | Description |
|---|---|
| `fetchGoals(filters)` | Fetch with optional module/status/priority filters |
| `fetchAnalysis` | Full analysis + sets summary, byModule, topGoals, recommendations |
| `fetchDashboardOverview` | Dashboard card data |
| `createGoal(goalData)` | Create + refresh dashboard and projection |
| `updateGoal({goalId, goalData})` | Update + refresh dashboard and projection |
| `deleteGoal(goalId)` | Delete + refresh dashboard and projection |
| `recordContribution({goalId, data})` | Record contribution + refresh dashboard |
| `fetchLifeEvents({household})` | Fetch all life events |
| `createLifeEvent(data)` | Create + refresh projection |
| `updateLifeEvent({eventId, data})` | Update + refresh projection |
| `deleteLifeEvent(eventId)` | Delete + refresh projection |
| `fetchProjection` | Fetch projection (respects viewMode) |
| `setChartView(view)` | Switch chart view |
| `setViewMode(mode)` | Switch individual/household + refresh data |

---

## 9. API Service

**File:** `resources/js/services/goalsService.js` (224 lines)

### Goals Endpoints

| Method | Endpoint |
|---|---|
| `getGoals(filters)` | GET /goals?module=X&status=X&priority=X |
| `getAnalysis()` | GET /goals/analysis |
| `getDashboardOverview()` | GET /goals/dashboard-overview |
| `getGoalTypes()` | GET /goals/types |
| `getRiskLevels()` | GET /goals/risk-levels |
| `createGoal(data)` | POST /goals |
| `getGoal(id)` | GET /goals/{id} |
| `updateGoal(id, data)` | PUT /goals/{id} |
| `deleteGoal(id)` | DELETE /goals/{id} |
| `recordContribution(goalId, data)` | POST /goals/{goalId}/contribution |
| `getProjections(goalId)` | GET /goals/{goalId}/projections |
| `getScenarios(goalId)` | GET /goals/{goalId}/scenarios |
| `getContributionHistory(goalId, limit)` | GET /goals/{goalId}/contributions?limit=X |
| `calculatePropertyCosts(data)` | POST /goals/calculate-property-costs |
| `getProjection(options)` | GET /goals/projection?household=true |
| `getHouseholdSummary()` | GET /goals/household-summary |

### Life Events Endpoints

| Method | Endpoint |
|---|---|
| `getLifeEvents(filters)` | GET /life-events?household=true |
| `getEventTypes()` | GET /life-events/types |
| `createLifeEvent(data)` | POST /life-events |
| `getLifeEvent(id)` | GET /life-events/{id} |
| `updateLifeEvent(id, data)` | PUT /life-events/{id} |
| `deleteLifeEvent(id)` | DELETE /life-events/{id} |
| `markLifeEventCompleted(id)` | POST /life-events/{id}/complete |
| `getLifeEventsByAge(filters)` | GET /life-events/by-age?household=true |

---

## 10. Frontend Components

### Goals Components (`resources/js/components/Goals/`)

| Component | Lines | Purpose |
|---|---|---|
| `GoalsOverview.vue` | - | Main goals overview tab |
| `GoalsList.vue` | - | Filterable list of all goals |
| `GoalsByModule.vue` | - | Goals grouped by assigned module |
| `GoalsAnalysis.vue` | - | Analysis and recommendations view |
| `GoalCard.vue` | - | Individual goal card with progress |
| `GoalFormModal.vue` | - | Create/edit goal modal form |
| `GoalProgressBar.vue` | - | Visual progress bar |
| `GoalMilestoneTracker.vue` | - | Milestone progress (25/50/75/100%) |
| `GoalCountdown.vue` | - | Days/months remaining countdown |
| `GoalContributionStreak.vue` | - | Streak display with intensity |
| `ContributionModal.vue` | - | Record contribution modal |
| `GoalsProjectionChart.vue` | - | Main projection chart (ApexCharts) |
| `ChartTypeToggle.vue` | - | Toggle: net worth / cash flow / asset breakdown |
| `ProjectionSummaryCards.vue` | - | Summary stats above projection chart |
| `AssumptionsDisclosure.vue` | - | Collapsible assumptions panel |
| `EventsTab.vue` | - | Life events management tab |
| `LifeEventCard.vue` | - | Individual life event display |
| `LifeEventForm.vue` | - | Create/edit life event form |
| `EventIcon.vue` | - | Renders event icon by type |
| `EventIconsOverlay.vue` | - | Icons overlaid on projection chart |
| `EventIconLegend.vue` | - | Legend for event icons |
| `EventTooltip.vue` | - | Tooltip for event details on hover |

**Total:** 22 components

### Dashboard Integration

| Component | File | Purpose |
|---|---|---|
| `GoalsOverviewCard.vue` | `components/Dashboard/GoalsOverviewCard.vue` | Dashboard card showing top 5 goals, progress, streaks |

### Views

| View | Route | Component |
|---|---|---|
| Goals Dashboard | `/goals` | `GoalsDashboard` |
| Preview Goals | `/preview/goals` | `GoalsDashboard` (preview mode) |

---

## 11. Frontend Routing

**File:** `resources/js/router/index.js`

| Path | Name | Component | Auth | Notes |
|---|---|---|---|---|
| `/goals` | Goals | GoalsDashboard | Yes | Main goals page |
| `/preview/goals` | PreviewGoals | GoalsDashboard | No | Preview mode |

**Module Map:** `/goals` maps to `goals` module for navigation highlighting.

---

## 12. Cross-Module Integration

### Goals -> Other Modules

| Integration | Description |
|---|---|
| **Module Assignment** | Goals auto-assigned to savings, investment, property, or retirement based on type + time horizon + amount |
| **Risk Profile** | Investment goals can use global `RiskProfile` from Investment module or goal-specific risk preference |
| **Linked Savings Account** | Goals can link to a `SavingsAccount` via `linked_savings_account_id` |
| **Net Worth Service** | Projection uses `NetWorthService` for current asset/liability breakdown |
| **UK Tax Calculator** | Projection uses `UKTaxCalculator` for net income calculation |
| **Assumptions Service** | Projection uses `AssumptionsService` for growth rates (inflation, investment, property) |
| **SDLT Calculation** | Property goals use `TaxConfigService` for stamp duty bands |

### Other Modules -> Goals

| Module | Integration |
|---|---|
| **Dashboard** | `GoalsOverviewCard` shows top 5 goals, progress, streaks on main dashboard |
| **Savings Module** | Has its own legacy `savings/goals` routes via `SavingsController` (separate system) |
| **Investment Module** | Has its own `InvestmentGoal` model and `GoalProgressController` (separate system) |

### Joint Ownership / Household

- Goals support single-record joint ownership pattern (`joint_owner_id`, `ownership_percentage`)
- Household view requires `hasAcceptedSpousePermission()` check
- Spouse goals visible if `show_in_household_view = true`
- Life events follow same pattern

---

## 13. Profile Completeness Integration

No dedicated goals profile completeness check exists. The goals module is self-contained - users can create goals directly without prerequisite profile data.

The projection chart requires some profile data to function well:
- `date_of_birth` - Required for age-based projections (defaults to age 45 if missing)
- `target_retirement_age` - Used for phase switching (defaults to 68)
- Income/expenditure data - Used for annual surplus calculation in projections
- Net worth data - Used as starting point for projections

---

## 14. Seeder Data

**File:** `database/seeders/PreviewUserSeeder.php`

### `createGoals()` Method (lines 1433-1483)

Creates goals for preview personas using the persona data arrays. Handles:
- Owner assignment (user vs spouse based on `'owner' => 'spouse'` flag)
- Joint ownership with automatic `joint_owner_id` assignment
- All goal fields including property-specific fields
- Contribution streaks and last contribution dates

### `createLifeEvents()` Method (lines 1489-1522)

Creates life events for preview personas. Handles:
- Owner assignment (user vs spouse)
- Joint ownership
- Auto-defaults: certainty='likely', status='expected', show_in_projection=true

### Persona Goal Examples

Each persona has goals appropriate to their life stage:
- **Young Family (Carters):** Emergency fund, home deposit, education savings
- **Peak Earners (Mitchells):** Wealth building, retirement boost, holiday
- **Widow (Thompson):** Legacy planning, travel, charitable giving
- **Entrepreneur (Chen):** Business growth fund, property portfolio
- **Young Saver (Morgan):** Emergency fund, first home deposit, car purchase
- **Retired Couple (Williams):** Home renovation, travel, legacy

---

## 15. API Routing

**File:** `routes/api.php` (lines 399-440)

### Goals Routes (`/goals` prefix, auth:sanctum)

| Method | Path | Controller Method |
|---|---|---|
| GET | `/goals` | `GoalsController@index` |
| GET | `/goals/analysis` | `GoalsController@analysis` |
| GET | `/goals/dashboard-overview` | `GoalsController@dashboardOverview` |
| GET | `/goals/projection` | `GoalsController@getProjection` |
| GET | `/goals/household-summary` | `GoalsController@getHouseholdSummary` |
| GET | `/goals/types` | `GoalsController@getGoalTypes` |
| GET | `/goals/risk-levels` | `GoalsController@getRiskLevels` |
| POST | `/goals/calculate-property-costs` | `GoalsController@calculatePropertyCosts` |
| POST | `/goals` | `GoalsController@store` |
| GET | `/goals/{id}` | `GoalsController@show` |
| PUT | `/goals/{id}` | `GoalsController@update` |
| DELETE | `/goals/{id}` | `GoalsController@destroy` |
| POST | `/goals/{id}/contribution` | `GoalsController@recordContribution` |
| GET | `/goals/{id}/projections` | `GoalsController@getProjections` |
| GET | `/goals/{id}/scenarios` | `GoalsController@getScenarios` |
| GET | `/goals/{id}/contributions` | `GoalsController@getContributionHistory` |

### Life Events Routes (`/life-events` prefix, auth:sanctum)

| Method | Path | Controller Method |
|---|---|---|
| GET | `/life-events` | `LifeEventController@index` |
| GET | `/life-events/types` | `LifeEventController@getEventTypes` |
| GET | `/life-events/by-age` | `LifeEventController@getByAge` |
| POST | `/life-events` | `LifeEventController@store` |
| GET | `/life-events/{id}` | `LifeEventController@show` |
| PUT | `/life-events/{id}` | `LifeEventController@update` |
| DELETE | `/life-events/{id}` | `LifeEventController@destroy` |
| POST | `/life-events/{id}/complete` | `LifeEventController@markCompleted` |

### Legacy Savings Goals Routes (`/savings/goals` prefix)

| Method | Path | Controller |
|---|---|---|
| GET | `/savings/goals` | `SavingsController@indexGoals` |
| POST | `/savings/goals` | `SavingsController@storeGoal` |
| PUT | `/savings/goals/{id}` | `SavingsController@updateGoal` |
| DELETE | `/savings/goals/{id}` | `SavingsController@destroyGoal` |
| PATCH | `/savings/goals/{id}/progress` | `SavingsController@updateGoalProgress` |

**Total:** 29 endpoints (16 goals + 8 life events + 5 legacy savings goals)

---

## 16. Key Constants and Business Logic

### Goal Types and Module Assignment

| Goal Type | Default Module | Fallback Logic |
|---|---|---|
| `emergency_fund` | savings | Always savings |
| `property_purchase` | property | Always property |
| `home_deposit` | property | Always property |
| `retirement` | retirement | Always retirement |
| `wealth_accumulation` | investment | Always investment |
| `education` | null | <= 3yr = savings; >3yr + >= GBP5k = investment |
| `wedding` | null | <= 3yr = savings; >3yr + >= GBP5k = investment |
| `holiday` | null | <= 3yr = savings; >3yr + >= GBP5k = investment |
| `car_purchase` | null | <= 3yr = savings; >3yr + >= GBP5k = investment |
| `debt_repayment` | null | <= 3yr = savings; >3yr + >= GBP5k = investment |
| `custom` | null | <= 3yr = savings; >3yr + >= GBP5k = investment |

### Property Cost Defaults

| Cost | Estimate |
|---|---|
| Legal/Conveyancing Base Fee | GBP1,200 (GBP1,500 >GBP500k, GBP2,000 >GBP1M) |
| Disbursements | GBP400 |
| Survey (< GBP250k) | GBP400 |
| Survey (GBP250k-GBP500k) | GBP600 |
| Survey (GBP500k-GBP1M) | GBP900 |
| Survey (> GBP1M) | GBP1,200 |
| Moving Costs | GBP1,500 |

### On-Track Calculation

A goal is considered "on track" if:
1. Status is `active`
2. `current_amount > 0`
3. Start date and target date are both set
4. Actual progress >= (expected linear progress - 10%)

### Projection Defaults

| Setting | Value |
|---|---|
| Default retirement age | 68 |
| Projection end age | 90 |
| Default inflation rate | 2% |
| Default investment growth | 5% |
| Default property growth | 3% |
| Cache TTL | 30 minutes |

---

## 17. Known Issues and Limitations

1. **Two Goal Systems:** The unified Goals module (`goals` table) coexists with the legacy `savings_goals` table (SavingsModule) and `investment_goals` table (InvestmentModule). These are not yet merged.

2. **Simplified Tax in Affordability:** `GoalAffordabilityService` uses a simplified tax calculation with hardcoded thresholds rather than `TaxConfigService`. The Projection service correctly uses `UKTaxCalculator`.

3. **No Contribution Auto-Import:** Contributions must be manually recorded. No integration with bank feeds or automatic tracking.

4. **Linear Mortgage Reduction:** The projection service reduces mortgages linearly to retirement age rather than using actual amortisation schedules.

5. **Single Cash Growth Rate:** Cash in projections does not grow. Only investments, pensions, and property have growth applied.

6. **No Goal Dependencies:** Goals cannot be marked as dependent on other goals (e.g., "pay off debt before saving for house").

7. **Probability Approximation:** Monte Carlo probability uses an analytical log-normal approximation rather than full simulation. The Investment module's `GoalProbabilityCalculator` has a more sophisticated implementation.

8. ~~**Missing Schema in `mysql-schema.sql`:**~~ **RESOLVED** - Schema dump regenerated on 2026-02-23. Now includes `goals`, `goal_contributions`, `life_events`, and `goal_dependencies` tables.

---

## 18. Goal Module Assignment Decision Tree

```
Goal Created
    |
    v
1. Check goal_type explicitly assigned?
    |-- emergency_fund ---------> SAVINGS
    |-- property_purchase ------> PROPERTY
    |-- home_deposit -----------> PROPERTY
    |-- retirement -------------> RETIREMENT
    |-- wealth_accumulation ----> INVESTMENT
    |-- (all others) -----------> Continue to step 2
    |
    v
2. Check time horizon
    |-- <= 3 years ------------> SAVINGS (short-term = cash)
    |-- > 3 years ------------> Continue to step 3
    |
    v
3. Check target amount
    |-- >= GBP5,000 -----------> INVESTMENT
    |-- < GBP5,000 ------------> SAVINGS (default)
    |
    v
4. Module override?
    |-- module_override = true -> Use user-specified assigned_module
    |-- module_override = false -> Use auto-assigned module
```

**Note:** Module override is checked first in the controller. The decision tree above shows the auto-assignment logic in `GoalAssignmentService.determineModule()`.
