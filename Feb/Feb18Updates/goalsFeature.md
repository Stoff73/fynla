# Goals & Life Events Module - Complete Feature Map

**Date:** 18 February 2026
**Module:** Goals & Life Events
**Status:** Active (in development banner still shown)

---

## Table of Contents

1. [Overview](#1-overview)
2. [Database Schema](#2-database-schema)
3. [Backend Models](#3-backend-models)
4. [Controllers & API Routes](#4-controllers--api-routes)
5. [Services](#5-services)
6. [Agent](#6-agent)
7. [API Resources & Form Requests](#7-api-resources--form-requests)
8. [Frontend Components](#8-frontend-components)
9. [Vuex Store](#9-vuex-store)
10. [Frontend API Service](#10-frontend-api-service)
11. [Router](#11-router)
12. [Cross-Module Integrations](#12-cross-module-integrations)
13. [Data Flows](#13-data-flows)
14. [Preview Persona Data](#14-preview-persona-data)
15. [Legacy Systems](#15-legacy-systems)
16. [Complete File Inventory](#16-complete-file-inventory)

---

## 1. Overview

The Goals & Life Events module is a unified financial planning system that allows users to:

- Create and track financial goals (emergency fund, property purchase, retirement, etc.)
- Record life events that impact their financial trajectory (inheritance, bonuses, large purchases, etc.)
- View a year-by-year net worth projection chart showing how goals and life events affect their financial future
- Track contribution streaks and milestone achievements
- Get affordability analysis and risk-based projections for investment goals
- Support joint/household goals with spouse integration

The module consolidates what were previously separate savings goals and investment goals into a single unified system.

**Architecture path:** `Vue Component -> goalsService.js -> GoalsController -> GoalsAgent -> Goal Services -> Goal/LifeEvent Models -> DB`

---

## 2. Database Schema

### 2.1 Goals Table (v2)

**Migration:** `database/migrations/2026_01_24_160001_create_goals_table_v2.php`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | auto | Primary key |
| user_id | foreignId | No | - | FK to users, cascade delete |
| goal_name | string(100) | No | - | Display name |
| goal_type | enum | No | - | See goal types below |
| custom_goal_type_name | string(100) | Yes | null | Only when goal_type = 'custom' |
| description | text | Yes | null | User description |
| target_amount | decimal(15,2) | No | - | Goal target value |
| current_amount | decimal(15,2) | No | 0.00 | Current saved/invested |
| target_date | date | No | - | Target completion date |
| start_date | date | Yes | null | When tracking began |
| assigned_module | enum | No | - | savings, investment, property, retirement |
| module_override | boolean | No | false | User manually set the module |
| priority | enum | No | 'medium' | critical, high, medium, low |
| is_essential | boolean | No | false | Marks as essential goal |
| status | enum | No | 'active' | active, paused, completed, abandoned |
| monthly_contribution | decimal(12,2) | Yes | null | Planned monthly amount |
| contribution_frequency | enum | No | 'monthly' | weekly, monthly, quarterly, annually |
| contribution_streak | unsignedInteger | No | 0 | Current consecutive contributions |
| longest_streak | unsignedInteger | No | 0 | Best ever streak |
| last_contribution_date | date | Yes | null | Last recorded contribution |
| linked_account_ids | json | Yes | null | Array of linked account IDs |
| linked_savings_account_id | foreignId | Yes | null | FK to savings_accounts |
| risk_preference | tinyInteger | Yes | null | 1-5 scale |
| use_global_risk_profile | boolean | No | true | Use user's global risk profile |
| ownership_type | enum | No | 'individual' | individual, joint |
| joint_owner_id | foreignId | Yes | null | FK to users |
| ownership_percentage | decimal(5,2) | No | 100.00 | Primary owner's share |
| show_in_projection | boolean | No | true | Include in projection chart |
| show_in_household_view | boolean | No | true | Show in household view |
| property_location | string | Yes | null | Property goal: location |
| property_type | string | Yes | null | Property goal: type |
| is_first_time_buyer | boolean | Yes | null | Property goal: FTB status |
| estimated_property_price | decimal(15,2) | Yes | null | Property goal: price |
| deposit_percentage | decimal(5,2) | Yes | null | Property goal: deposit % |
| stamp_duty_estimate | decimal(15,2) | Yes | null | Property goal: SDLT |
| additional_costs_estimate | decimal(15,2) | Yes | null | Property goal: other costs |
| milestones | json | Yes | null | Achievement milestones |
| projection_data | json | Yes | null | Cached projection data |
| completed_at | timestamp | Yes | null | When goal was completed |
| completion_notes | text | Yes | null | Notes on completion |
| created_at | timestamp | No | auto | |
| updated_at | timestamp | No | auto | |
| deleted_at | timestamp | Yes | null | Soft delete |

**Indexes:**
- `[user_id, status]`
- `[user_id, assigned_module]`
- `[user_id, goal_type]`

**Goal type enum values (11):** `emergency_fund`, `property_purchase`, `home_deposit`, `education`, `retirement`, `wealth_accumulation`, `wedding`, `holiday`, `car_purchase`, `debt_repayment`, `custom`

### 2.2 Goal Contributions Table (v2)

**Migration:** `database/migrations/2026_01_24_160002_create_goal_contributions_table_v2.php`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | auto | Primary key |
| goal_id | foreignId | No | - | FK to goals, cascade delete |
| user_id | foreignId | No | - | FK to users, cascade delete |
| amount | decimal(12,2) | No | - | Contribution amount |
| contribution_date | date | No | - | Date of contribution |
| contribution_type | enum | No | - | manual, automatic, lump_sum, interest, adjustment |
| notes | text | Yes | null | User notes |
| goal_balance_after | decimal(15,2) | No | - | Balance after contribution |
| streak_qualifying | boolean | No | true | Counts towards streak |
| created_at | timestamp | No | auto | |
| updated_at | timestamp | No | auto | |

**Indexes:**
- `[goal_id, contribution_date]`
- `[user_id, contribution_date]`

### 2.3 Life Events Table

**Migration:** `database/migrations/2026_02_03_120001_create_life_events_table.php`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigIncrements | No | auto | Primary key |
| user_id | foreignId | No | - | FK to users, cascade delete |
| event_name | string(100) | No | - | Display name |
| event_type | enum | No | - | See event types below |
| description | text | Yes | null | User description |
| amount | decimal(15,2) | No | - | Event amount |
| impact_type | enum | No | - | income, expense |
| expected_date | date | No | - | Expected date of event |
| certainty | enum | No | 'likely' | confirmed, likely, possible, speculative |
| icon | string(50) | Yes | null | Custom icon |
| show_in_projection | boolean | No | true | Include in projection chart |
| show_in_household_view | boolean | No | true | Show in household view |
| ownership_type | enum | No | 'individual' | individual, joint |
| joint_owner_id | foreignId | Yes | null | FK to users |
| ownership_percentage | decimal(5,2) | No | 100.00 | Primary owner's share |
| status | enum | No | 'expected' | expected, confirmed, completed, cancelled |
| occurred_at | timestamp | Yes | null | When event actually occurred |
| created_at | timestamp | No | auto | |
| updated_at | timestamp | No | auto | |
| deleted_at | timestamp | Yes | null | Soft delete |

**Indexes:**
- `[user_id, status]`
- `[user_id, expected_date]`
- `[user_id, impact_type]`

**Income event types (9):** `inheritance`, `gift_received`, `bonus`, `redundancy_payment`, `property_sale`, `business_sale`, `pension_lump_sum`, `lottery_windfall`, `custom_income`

**Expense event types (7):** `large_purchase`, `home_improvement`, `wedding`, `education_fees`, `gift_given`, `medical_expense`, `custom_expense`

### 2.4 Additional Migration

**Migration:** `database/migrations/2026_02_03_120002_add_projection_fields_to_goals_table.php`

Adds `show_in_projection` (boolean, default true) and `show_in_household_view` (boolean, default true) to goals table.

### 2.5 Historical Migrations (v1)

- `database/migrations/2026_01_18_000001_create_goals_table.php` - Original v1 goals table
- `database/migrations/2026_01_18_000002_create_goal_contributions_table.php` - Original v1 contributions table
- `database/migrations/2026_01_18_000003_migrate_existing_goals_data.php` - Data migration from legacy `savings_goals` and `investment_goals` tables to unified goals table

---

## 3. Backend Models

### 3.1 Goal Model

**File:** `app/Models/Goal.php`

**Traits:** `Auditable`, `HasFactory`, `HasJointOwnership`, `SoftDeletes`

**Fillable fields (39):** `user_id`, `goal_name`, `goal_type`, `custom_goal_type_name`, `description`, `target_amount`, `current_amount`, `target_date`, `start_date`, `assigned_module`, `module_override`, `priority`, `is_essential`, `status`, `monthly_contribution`, `contribution_frequency`, `contribution_streak`, `longest_streak`, `last_contribution_date`, `linked_account_ids`, `linked_savings_account_id`, `risk_preference`, `use_global_risk_profile`, `ownership_type`, `joint_owner_id`, `ownership_percentage`, `show_in_projection`, `show_in_household_view`, `property_location`, `property_type`, `is_first_time_buyer`, `estimated_property_price`, `deposit_percentage`, `stamp_duty_estimate`, `additional_costs_estimate`, `milestones`, `projection_data`, `completed_at`, `completion_notes`

**Casts:**

| Cast Type | Fields |
|-----------|--------|
| decimal:2 | target_amount, current_amount, monthly_contribution, ownership_percentage, estimated_property_price, deposit_percentage, stamp_duty_estimate, additional_costs_estimate |
| date | target_date, start_date, last_contribution_date |
| boolean | module_override, is_essential, use_global_risk_profile, is_first_time_buyer, show_in_projection, show_in_household_view |
| array | linked_account_ids, milestones, projection_data |
| integer | contribution_streak, longest_streak, risk_preference |
| datetime | completed_at |

**Appended attributes:** `progress_percentage`, `days_remaining`, `months_remaining`, `is_on_track`, `display_goal_type`

**Relationships:**

| Relationship | Type | Target | Key |
|-------------|------|--------|-----|
| user() | BelongsTo | User | user_id |
| jointOwner() | BelongsTo | User | joint_owner_id |
| linkedSavingsAccount() | BelongsTo | SavingsAccount | linked_savings_account_id |
| contributions() | HasMany | GoalContribution | goal_id |

**Accessors:**

| Accessor | Logic |
|----------|-------|
| getProgressPercentageAttribute | `min(100, (current_amount / target_amount) * 100)` |
| getDaysRemainingAttribute | `now()->diffInDays(target_date, false)` or null if no target_date |
| getMonthsRemainingAttribute | `now()->diffInMonths(target_date, false)` or null |
| getIsOnTrackAttribute | Calculates elapsed time ratio, expected progress based on time, compares with actual progress + 10% margin |
| getDisplayGoalTypeAttribute | Maps goal_type to human-readable label via match expression |
| getAmountRemainingAttribute | `max(0, target_amount - current_amount)` |
| getRequiredMonthlyContributionAttribute | `amount_remaining / months_remaining` |
| getCurrentMilestoneAttribute | Returns current 25/50/75/100 milestone level |
| getNextMilestoneAttribute | Returns next milestone above current progress |

**Methods:**
- `isPropertyGoal()` - true if goal_type is `property_purchase` or `home_deposit`
- `isInvestmentGoal()` - true if goal_type is `wealth_accumulation`
- `isJoint()` - true if `ownership_type === 'joint' && joint_owner_id !== null`

**Scopes:**
- `scopeActive` - `where('status', 'active')`
- `scopeCompleted` - `where('status', 'completed')`
- `scopeForModule($module)` - `where('assigned_module', $module)`
- `scopeByPriority($priority)` - `where('priority', $priority)`
- `scopeOnTrack` - filters to goals where is_on_track is true

### 3.2 GoalContribution Model

**File:** `app/Models/GoalContribution.php`

**Fillable:** `goal_id`, `user_id`, `amount`, `contribution_date`, `contribution_type`, `notes`, `goal_balance_after`, `streak_qualifying`

**Casts:**
- `decimal:2`: amount, goal_balance_after
- `date`: contribution_date
- `boolean`: streak_qualifying

**Relationships:**
- `goal()` - BelongsTo Goal
- `user()` - BelongsTo User

**Scopes:**
- `scopeStreakQualifying` - `where('streak_qualifying', true)`
- `scopeOfType($type)` - filter by contribution_type
- `scopeInDateRange($from, $to)` - filter by contribution_date range

### 3.3 LifeEvent Model

**File:** `app/Models/LifeEvent.php`

**Traits:** `Auditable`, `HasFactory`, `HasJointOwnership`, `SoftDeletes`

**Constants:**
- `INCOME_EVENT_TYPES` (9): inheritance, gift_received, bonus, redundancy_payment, property_sale, business_sale, pension_lump_sum, lottery_windfall, custom_income
- `EXPENSE_EVENT_TYPES` (7): large_purchase, home_improvement, wedding, education_fees, gift_given, medical_expense, custom_expense

**Fillable (17):** `user_id`, `event_name`, `event_type`, `description`, `amount`, `impact_type`, `expected_date`, `certainty`, `icon`, `show_in_projection`, `show_in_household_view`, `ownership_type`, `joint_owner_id`, `ownership_percentage`, `status`, `occurred_at`

**Casts:**
- `decimal:2`: amount, ownership_percentage
- `date`: expected_date
- `boolean`: show_in_projection, show_in_household_view
- `datetime`: occurred_at

**Appended attributes:** `signed_amount`, `display_event_type`, `years_until_event`

**Relationships:**
- `user()` - BelongsTo User
- `jointOwner()` - BelongsTo User (via joint_owner_id)

**Methods:**
- `isPositive()` - impact_type === 'income'
- `isNegative()` - impact_type === 'expense'
- `isJoint()` - ownership_type === 'joint' && joint_owner_id !== null
- `getUserShare(userId)` - returns ownership percentage for user (inverted if joint_owner_id)
- `getAmountForUser(userId)` - amount * getUserShare
- `getAgeAtEvent(User)` - calculates user's age at event expected_date

**Scopes:**
- `scopeActive` - status in ['expected', 'confirmed']
- `scopeForProjection` - where show_in_projection = true
- `scopeForHousehold` - where show_in_household_view = true
- `scopeIncome` - where impact_type = 'income'
- `scopeExpense` - where impact_type = 'expense'
- `scopeInDateRange($from, $to)` - filter by expected_date
- `scopeByCertainty($certainty)` - filter by certainty level

### 3.4 HasJointOwnership Trait

**File:** `app/Traits/HasJointOwnership.php`

Shared by Goal and LifeEvent models. Provides:
- `scopeForUserOrJoint(Builder, userId)` - where user_id = $userId OR joint_owner_id = $userId
- `scopeForUser(Builder, userId)` - where user_id = $userId
- `scopeForJointOwner(Builder, userId)` - where joint_owner_id = $userId
- `isOwnedBy(userId)` - checks if user_id or joint_owner_id matches
- `hasJointOwner()` - checks joint_owner_id is not null

### 3.5 SavingsGoal Model (Legacy)

**File:** `app/Models/SavingsGoal.php`

Legacy model from pre-unification. Fillable: `user_id`, `goal_name`, `target_amount`, `current_saved`, `target_date`, `priority`, `linked_account_id`, `auto_transfer_amount`. Has `user()` and `linkedAccount()` relationships. Still referenced by legacy savings routes and SavingsAgent.

---

## 4. Controllers & API Routes

### 4.1 GoalsController

**File:** `app/Http/Controllers/Api/GoalsController.php`

**Injected dependencies:** GoalsAgent, GoalAssignmentService, GoalAffordabilityService, GoalProgressService, GoalRiskService, GoalsProjectionService, LifeEventService

| Method | HTTP | Route | Description |
|--------|------|-------|-------------|
| index | GET | /api/goals | Lists goals with optional filters: module, status, priority. Eager loads contributions, linkedSavingsAccount |
| analysis | GET | /api/goals/analysis | Full analysis from GoalsAgent (goals by module, summary, affordability, recommendations) |
| dashboardOverview | GET | /api/goals/dashboard-overview | Dashboard summary data (has_goals, counts, top 5 goals, streak, progress) |
| store | POST | /api/goals | Create goal. Auto-assigns module via GoalAssignmentService. Calculates property costs if property goal. Returns GoalResource |
| show | GET | /api/goals/{id} | Full goal detail with progress analysis, milestones, streak display, affordability, projections |
| update | PUT | /api/goals/{id} | Update goal. Handles status changes (completed triggers completeGoal). Manages ownership logic |
| destroy | DELETE | /api/goals/{id} | Soft deletes goal |
| recordContribution | POST | /api/goals/{id}/contribution | Records contribution via GoalProgressService. Auto-completes if 100% reached |
| getProjections | GET | /api/goals/{id}/projections | Risk-based projections for a single goal via GoalRiskService |
| calculatePropertyCosts | POST | /api/goals/calculate-property-costs | SDLT + legal fees + survey + moving costs calculation |
| getScenarios | GET | /api/goals/{id}/scenarios | What-if scenarios via GoalsAgent.buildScenarios() |
| getGoalTypes | GET | /api/goals/types | Returns 11 goal type definitions with type, label, default_module, icon |
| getRiskLevels | GET | /api/goals/risk-levels | Returns 5 risk levels with descriptions |
| getContributionHistory | GET | /api/goals/{id}/contributions | Paginated contribution history |
| getProjection | GET | /api/goals/projection | Full projection chart data from GoalsProjectionService. Accepts ?household=true |
| getHouseholdSummary | GET | /api/goals/household-summary | Combined spouse data |

### 4.2 LifeEventController

**File:** `app/Http/Controllers/Api/LifeEventController.php`

**Injected:** LifeEventService

| Method | HTTP | Route | Description |
|--------|------|-------|-------------|
| index | GET | /api/life-events | List events. Accepts ?household=true for spouse inclusion |
| getEventTypes | GET | /api/life-events/types | Returns 17 event type definitions with type, label, impact_type, icon, color, description |
| store | POST | /api/life-events | Create event. Auto-determines impact_type from event_type |
| show | GET | /api/life-events/{id} | Single event detail |
| update | PUT | /api/life-events/{id} | Update event |
| destroy | DELETE | /api/life-events/{id} | Soft delete event |
| markCompleted | POST | /api/life-events/{id}/complete | Mark event as occurred (sets occurred_at, status=completed) |
| getByAge | GET | /api/life-events/by-age | Events grouped by user age at event |

### 4.3 Complete Route Listing

**Goals Routes (unified):**
```
GET    /api/goals                          -> GoalsController@index
GET    /api/goals/analysis                 -> GoalsController@analysis
GET    /api/goals/dashboard-overview       -> GoalsController@dashboardOverview
GET    /api/goals/projection               -> GoalsController@getProjection
GET    /api/goals/household-summary        -> GoalsController@getHouseholdSummary
GET    /api/goals/types                    -> GoalsController@getGoalTypes
GET    /api/goals/risk-levels              -> GoalsController@getRiskLevels
POST   /api/goals/calculate-property-costs -> GoalsController@calculatePropertyCosts
POST   /api/goals                          -> GoalsController@store
GET    /api/goals/{id}                     -> GoalsController@show
PUT    /api/goals/{id}                     -> GoalsController@update
DELETE /api/goals/{id}                     -> GoalsController@destroy
POST   /api/goals/{id}/contribution        -> GoalsController@recordContribution
GET    /api/goals/{id}/projections         -> GoalsController@getProjections
GET    /api/goals/{id}/scenarios           -> GoalsController@getScenarios
GET    /api/goals/{id}/contributions       -> GoalsController@getContributionHistory
```

**Life Events Routes:**
```
GET    /api/life-events                    -> LifeEventController@index
GET    /api/life-events/types              -> LifeEventController@getEventTypes
GET    /api/life-events/by-age             -> LifeEventController@getByAge
POST   /api/life-events                    -> LifeEventController@store
GET    /api/life-events/{id}               -> LifeEventController@show
PUT    /api/life-events/{id}               -> LifeEventController@update
DELETE /api/life-events/{id}               -> LifeEventController@destroy
POST   /api/life-events/{id}/complete      -> LifeEventController@markCompleted
```

**Legacy Savings Goals Routes (still active):**
```
GET    /api/savings/goals
POST   /api/savings/goals
PUT    /api/savings/goals/{id}
DELETE /api/savings/goals/{id}
PATCH  /api/savings/goals/{id}/progress
```

**Legacy Investment Goals Routes (still active):**
```
POST   /api/investment/goals
PUT    /api/investment/goals/{id}
DELETE /api/investment/goals/{id}
GET    /api/investment/goals/{goalId}/progress
GET    /api/investment/goals/progress/all
GET    /api/investment/goals/{goalId}/shortfall
POST   /api/investment/goals/{goalId}/what-if
POST   /api/investment/goals/calculate-probability
POST   /api/investment/goals/required-contribution
GET    /api/investment/goals/glide-path
DELETE /api/investment/goals/clear-cache
```

---

## 5. Services

### 5.1 GoalAssignmentService

**File:** `app/Services/Goals/GoalAssignmentService.php`

**Constants:**
- `SHORT_TERM_YEARS = 3`
- `INVESTMENT_MIN_AMOUNT = 5000`

**Methods:**

**`determineModule(array $data): string`** - Auto-assigns a goal to a module:
1. Direct type mapping:
   - `emergency_fund` -> savings
   - `property_purchase` / `home_deposit` -> property
   - `retirement` -> retirement
   - `wealth_accumulation` -> investment
2. Time horizon fallback (for all other types):
   - <= 3 years -> savings
   - > 3 years AND target_amount >= 5000 -> investment
   - > 3 years AND target_amount < 5000 -> savings

**`calculatePropertyCosts(array $data): array`** - Calculates total upfront costs for property goals:
- SDLT: Uses `TaxConfigService` bands, applies first-time buyer relief (up to 625k property price)
- Legal fees: 1200-2000 base + 400 disbursements (scaled by property price)
- Survey costs: 400-1200 (scaled by property price)
- Moving costs: 1500 fixed
- Returns: stamp_duty, legal_fees, survey_costs, moving_costs, total_upfront

**`getRecommendedAllocation(array $data): array`** - Returns equity/bond/cash glide path based on time horizon:
- > 15 years: 80/15/5 (aggressive)
- > 10 years: 70/25/5 (growth)
- > 5 years: 50/40/10 (balanced)
- > 3 years: 30/50/20 (cautious)
- > 1 year: 15/45/40 (defensive)
- <= 1 year: 0/20/80 (cash)

**`getGoalTypes(): array`** - Returns all 11 goal types with: type, label, default_module, icon

### 5.2 GoalAffordabilityService

**File:** `app/Services/Goals/GoalAffordabilityService.php`

**`analyzeAffordability(Goal, User): array`** - Returns:
- monthly_surplus: net income minus expenditure
- required_monthly_contribution: for this goal
- total_commitments: across all active goals
- affordability_ratio: commitments / surplus
- category: see categories below
- suggested_monthly_amount, suggested_target_date

**`calculateMonthlySurplus(User): float`** - Simplified UK tax calculation:
- Personal Allowance: 12570
- Basic rate: 20% (up to 50270)
- Higher rate: 40% (up to 125140)
- Additional rate: 45% (above 125140)
- NI: 8% on 12570-50270, 2% above 50270

**Affordability categories:**
| Category | Condition |
|----------|-----------|
| unaffordable | surplus <= 0 |
| completed | progress >= 100% |
| comfortable | ratio <= 0.3 |
| moderate | ratio <= 0.5 |
| challenging | ratio <= 0.75 |
| stretch | ratio <= 1.0 |
| overcommitted | ratio > 1.0 |

### 5.3 GoalProgressService

**File:** `app/Services/Goals/GoalProgressService.php`

**Constants:** `MILESTONES = [25, 50, 75, 100]`

**`calculateProgress(Goal): array`** - Returns:
- expected_progress: based on elapsed time / total time
- actual_progress: progress_percentage from model
- progress_delta: actual - expected
- status: ahead / on_track / slightly_behind / behind

**`recordContribution(Goal, amount, type, notes): GoalContribution`** - Flow:
1. Creates GoalContribution record with goal_balance_after = current_amount + amount
2. Updates goal's current_amount
3. Checks if contribution is streak-qualifying (>= 80% of expected monthly)
4. Updates contribution streak (with grace periods)
5. Checks milestones (25/50/75/100%)
6. If 100% reached, auto-completes goal
7. Returns the contribution

**`updateContributionStreak(Goal): void`** - Grace periods by frequency:
| Frequency | Grace Period |
|-----------|-------------|
| weekly | 10 days |
| monthly | 35 days |
| quarterly | 95 days |
| annually | 370 days |

Logic: If last_contribution_date is within grace period and contribution qualifies -> increment streak. If gap exceeds grace period -> reset to 1 (if qualifying) or 0. Updates longest_streak if current exceeds it.

**`checkMilestones(Goal): void`** - Records milestone achievements with `reached_at` timestamps in the milestones JSON field.

**`getContributionHistory(Goal, limit): Collection`** - Returns last N contributions ordered by contribution_date desc.

**`getMonthlySummary(Goal, months): array`** - Monthly contribution totals and target-met status for the last N months.

**`completeGoal(Goal, notes): void`** - Sets status to 'completed', records completed_at timestamp, records 100% milestone.

**`getStreakDisplay(Goal): array`** - Returns:
- current_streak, longest_streak
- label: "No streak" / "Starting" / "Building" / "Consistent" / "Amazing"
- intensity: cold(0) / starting(1-2) / warm(3-5) / hot(6-11) / blazing(12+)

### 5.4 GoalRiskService

**File:** `app/Services/Goals/GoalRiskService.php`

**Risk levels:**
| Level | Name | Expected Return | Volatility |
|-------|------|----------------|------------|
| 1 | Conservative | 3% | 5% |
| 2 | Cautious | 5% | 8% |
| 3 | Moderate | 6% | 12% |
| 4 | Growth | 7.5% | 16% |
| 5 | Aggressive | 9% | 20% |

**`getRiskParameters(Goal, RiskProfile): array`** - Resolves risk level from goal-specific `risk_preference` (if use_global_risk_profile is false) or from the user's global RiskProfile. Returns expected_return, volatility, risk_level, risk_name.

**`getProjections(Goal, RiskProfile): array`** - Returns:
- deterministic_fv: Future value via `FV = PV(1+r)^n + PMT * [((1+r)^n - 1) / r]`
- probability_of_success: Via log-normal CDF (Abramowitz & Stegun approximation)
- required_contribution: Monthly amount needed for 85% probability of success
- yearly_projections: Array of {year, age, expected_value, lower_bound, upper_bound} with 95% confidence bounds (mean +/- 1.96 * sigma)
- recommendation: "increase contribution" / "decrease contribution" / "on track"

**`standardNormalCDF(z): float`** - Abramowitz and Stegun approximation for the standard normal cumulative distribution function.

**`getAvailableRiskLevels(): array`** - Returns all 5 levels with level, name, expected_return, volatility, description.

### 5.5 GoalsProjectionService

**File:** `app/Services/Goals/GoalsProjectionService.php`

**Constants:**
- `DEFAULT_RETIREMENT_AGE = 68`
- `DEFAULT_PROJECTION_END_AGE = 90`
- `CACHE_TTL = 1800` (30 minutes)

**Injected:** NetWorthService, LifeEventService, AssumptionsService, UKTaxCalculator

**`generateProjection(userId, household): array`** - Main projection engine. Cached for 30 minutes. Returns:
- `yearly_data[]`: year-by-year financial position
- `events[]`: goals and life events for chart icons
- `summary`: starting/ending/retirement net worth, peak net worth and age
- `assumptions`: inflation_rate, investment_growth, property_growth
- `retirement_age`, `current_age`

**`generateYearlyData(...): array`** - Year-by-year simulation from current age to 90:
1. Each year: deduct annual expenditure from cash
2. Apply life event impacts at their expected ages:
   - Income events: add to cash
   - Expense events: deduct from cash
3. Apply goal completions at target dates: deduct target_amount from cash
4. Grow investments and pensions at real investment rate (nominal - inflation)
5. Grow property at real property rate (nominal - inflation)
6. Reduce mortgage linearly to retirement age

**`buildEventsArray(...): array`** - Creates events for chart icon rendering:
- Fields per event: id, type (goal/life_event), name, age, year, amount, impact (positive/negative), icon, color, is_completed, certainty

**`buildSummary(...): array`** - Summary statistics:
- starting_net_worth, ending_net_worth, retirement_net_worth
- peak_net_worth, peak_age
- total_income_events, total_expense_events
- total_income_amount, total_expense_amount

**Icon and colour mappings defined for all 11 goal types and 16 life event types.**

### 5.6 LifeEventService

**File:** `app/Services/Goals/LifeEventService.php`

**Constants:** `CACHE_TTL = 3600` (1 hour)

**`getEvents(userId, includeHousehold): Collection`** - Gets events for user + joint_owner. If includeHousehold is true and user `hasAcceptedSpousePermission()`, also fetches spouse's events.

**`getActiveEventsForProjection(userId, includeHousehold): Collection`** - Filters to `show_in_projection=true` and status in `[expected, confirmed]`.

**`getEventsByAge(userId, includeHousehold): array`** - Groups events by user's age at event expected_date.

**`calculateTotalImpactAtAge(userId, age): float`** - Sum of signed amounts for events occurring in that age year.

**`getEventTypes(): array`** - Returns 17 event types with: type, label, impact_type, icon, color, description.

**`getCertaintyLevels(): array`** - Returns certainty weighting:
| Certainty | Weight | Border Style |
|-----------|--------|-------------|
| confirmed | 1.0 | solid |
| likely | 0.75 | solid |
| possible | 0.5 | dashed |
| speculative | 0.25 | dotted |

**CRUD methods:** `createEvent(userId, data)`, `updateEvent(id, data)`, `deleteEvent(id)`, `markCompleted(id)` - all clear cache on mutation.

### 5.7 Legacy Services

**GoalProgressCalculator** (`app/Services/Savings/GoalProgressCalculator.php`):
- Uses `SavingsGoal` model
- `calculateProgress(SavingsGoal)` - months_remaining, shortfall, required_monthly_savings, progress_percent, on_track
- `projectGoalAchievement(SavingsGoal, monthlyContribution, interestRate)` - FV with compound interest
- `prioritizeGoals(Collection)` - Sorts by priority (high=1, medium=2, low=3) then target_date

**GoalProgressAnalyzer** (`app/Services/Investment/Goals/GoalProgressAnalyzer.php`):
- Uses `InvestmentGoal` model
- `analyzeGoalProgress(InvestmentGoal)` - Full analysis with Monte Carlo probability, track status (green/orange/red), milestones (25/50/75/90/100%), trajectory, contribution analysis, recommendations
- `analyzeAllGoals(userId)` - Summary across all investment goals with overall status
- Track status thresholds: green (>=85% prob), orange (>=60% prob), red (<60%)

**GoalProbabilityCalculator** (`app/Services/Investment/Goals/GoalProbabilityCalculator.php`):
- Monte Carlo simulation for investment goal probability
- Parameters: current_value, target_value, monthly_contribution, expected_return, volatility, years_to_goal, iterations

**ShortfallAnalyzer** (`app/Services/Investment/Goals/ShortfallAnalyzer.php`):
- Gap analysis (current trajectory vs target)
- Mitigation options: increase contributions, extend timeline, reduce target
- What-if scenario modelling, sensitivity analysis

---

## 6. Agent

### 6.1 GoalsAgent

**File:** `app/Agents/GoalsAgent.php`

Extends `BaseAgent`. Injected: GoalAssignmentService, GoalAffordabilityService, GoalProgressService, GoalRiskService.

**`analyze(userId): array`** - Cached via `rememberForUser`. Returns:
- `goals_by_module`: goals organized by savings/investment/property/retirement, each with progress, streak, affordability
- `summary`: total_goals, on_track_count, total_target, total_current, overall_progress
- `top_goals`: sorted by priority then target_date, limit 5
- `affordability`: overall affordability analysis
- `streaks`: best_streak across all goals

**`generateRecommendations(analysisData): array`** - Checks for:
- Behind-schedule goals -> recommends increasing contributions
- Overcommitted affordability -> recommends reducing/pausing goals
- Missing emergency fund -> recommends creating one
- Streak celebrations (>= 6 months) -> positive reinforcement

**`buildScenarios(userId, parameters): array`** - Generates 4 what-if scenarios for a goal:
1. Increase contribution by 20%
2. Reach goal 6 months earlier
3. Reduce target by 20%
4. Add 1000 lump sum contribution

**`getDashboardOverview(userId): array`** - Returns:
- has_goals, total_goals, on_track_count, overall_progress
- top_goals (limit 5)
- best_streak, completed_this_year
- total_target, total_current

**`priorityOrder(): array`** - critical=1, high=2, medium=3, low=4

---

## 7. API Resources & Form Requests

### 7.1 GoalResource

**File:** `app/Http/Resources/GoalResource.php`

Output fields: id, name (from goal_name), goal_type, custom_goal_type_name (conditional on type=custom), display_goal_type, description, target_amount, current_amount, target_date, start_date, priority, is_essential, status, assigned_module, progress_percentage, amount_remaining, days_remaining, months_remaining, is_on_track, current_milestone, next_milestone, required_monthly_contribution, monthly_contribution, contribution_frequency, contribution_streak, longest_streak, last_contribution_date, ownership_type, ownership_percentage, property fields (conditional on isPropertyGoal()), completed_at, created_at, updated_at.

Conditional relationships (via `whenLoaded`): user, jointOwner, contributions (GoalContributionResource collection), linkedSavingsAccount.

Includes `links.self` URL.

### 7.2 GoalContributionResource

**File:** `app/Http/Resources/GoalContributionResource.php`

Output fields: id, goal_id, amount, contribution_date, contribution_type, notes, goal_balance_after, streak_qualifying, created_at, updated_at. Conditional relationships: user, goal.

### 7.3 Form Requests

**StoreGoalRequest** (`app/Http/Requests/Goals/StoreGoalRequest.php`):
- Required: goal_name (max:100), goal_type (in 11 enum values), target_amount (min:1, max:100000000), target_date (after:today)
- Conditional: custom_goal_type_name (required_if goal_type=custom), joint_owner_id (required_if ownership_type=joint)
- Optional: description, current_amount, start_date, assigned_module, module_override, priority, is_essential, monthly_contribution, contribution_frequency, risk_preference (1-5), use_global_risk_profile, ownership_type, ownership_percentage, show_in_projection, show_in_household_view, all property fields

**UpdateGoalRequest** (`app/Http/Requests/Goals/UpdateGoalRequest.php`):
- All fields `sometimes` (partial update). Adds: status (in: active/paused/completed/abandoned), completion_notes

**StoreLifeEventRequest** (`app/Http/Requests/StoreLifeEventRequest.php`):
- Required: event_name (max:100), event_type (in 16 enum values), amount (min:0.01, max:999999999.99), expected_date (after:today)
- Optional: description (max:1000), impact_type, certainty, icon (max:50), show_in_projection, show_in_household_view, ownership_type, joint_owner_id, ownership_percentage

**UpdateLifeEventRequest** (`app/Http/Requests/UpdateLifeEventRequest.php`):
- All fields `sometimes`. Adds: status (in: expected/confirmed/completed/cancelled)

---

## 8. Frontend Components

### 8.1 Main View: GoalsDashboard

**File:** `resources/js/views/Goals/GoalsDashboard.vue`

The primary view for the Goals & Life Events module.

**Components used:** AppLayout, GoalFormModal, GoalsOverview, ContributionModal, EventsTab

**Data state:** activeTab (overview/events), showGoalModal, editingGoal, showContributionModal, contributionGoal, showDeleteModal, deletingGoal, deleteLoading

**Computed (mapped from goals store):** loading, error, goals, dashboardData, summary (total_goals, on_track_count, total_target, total_current, overall_progress), topGoals, bestStreak

**Tab structure:**
1. **Overview tab** - Renders `GoalsOverview` component with summary, topGoals, bestStreak props
2. **Life Events tab** - Renders `EventsTab` component

**On mount:** Fetches goals + dashboard overview in parallel. Checks `$route.query.action === 'create'` to auto-open create modal (used by dashboard CTA button).

**Template structure:**
1. Page header "Goals & Life Events"
2. Blue info banner (module in development notice)
3. Loading spinner / error state
4. Tab navigation bar
5. Active tab content
6. GoalFormModal, ContributionModal, Delete confirmation modal (all conditionally rendered)

### 8.2 Goals Components (22 files in resources/js/components/Goals/)

#### GoalsOverview.vue
Props: summary (Object), topGoals (Array), bestStreak (Number)
Emits: create-goal, create-event, view-goal

Layout:
1. Quick Add buttons row (Add Goal, Add Life Event)
2. GoalsProjectionChart (always shown)
3. Empty state (if no goals): blue card with CTA "Create Your First Goal"
4. Goals content (if goals exist):
   - Streak banner (if bestStreak >= 3): fire icon, streak count
   - Top Goals grid (3 columns): each card shows goal icon, name, module tag, status dot, progress bar, percentage, target amount
   - Status summary cards: all on track (green border) or N goals need attention (blue border)

Goal icon mapping (11 types): emergency_fund=shield, property_purchase=home, home_deposit=home, holiday=globe, car_purchase=car, wedding=heart, education=graduation, retirement=sun, wealth_accumulation=chart, debt_repayment=banknote, custom=flag

#### GoalCard.vue
Props: goal (Object, required), showActions (Boolean)
Emits: edit, delete, add-contribution

Card with left border coloured by module (savings=green, investment=blue, property=indigo, retirement=purple). Shows: goal name, module tag, status badge, progress bar, time remaining, monthly contribution, streak display, action buttons (edit/delete/add contribution).

#### GoalFormModal.vue
Props: isOpen (Boolean), goal (Object or null for create mode)
Emits: close, save

Form fields:
- goal_name (text input)
- goal_type (select with 11 options)
- custom_goal_type_name (shown only when type=custom)
- description (textarea)
- target_amount (currency input)
- current_amount (currency input)
- target_date (date picker)
- monthly_contribution (currency input)
- priority (4 button group: critical/high/medium/low)
- show_in_projection (checkbox)
- show_in_household_view (checkbox)

Property section (shown when goal_type is property_purchase or home_deposit):
- estimated_property_price
- deposit_percentage
- is_first_time_buyer (checkbox)
- "Calculate Property Costs" button -> calls API for SDLT + fees

Uses `@submit.prevent="handleSubmit"` pattern. Emits `save` with form data.

#### GoalProgressBar.vue
Props: percentage, currentAmount, targetAmount, isOnTrack, size (sm/md/lg), showLabel, label, showAmounts, showMilestones, variant (auto/success/warning/danger/info)

Renders a progress bar with:
- Percentage fill with colour based on variant or auto-determined from progress
- Optional milestone markers at 25/50/75/100 tick positions
- Optional amounts display (current / target)

#### GoalMilestoneTracker.vue
Props: progress, currentAmount, targetAmount, compact, showLastAchievement, achievedMilestones

Compact mode: milestone dots in a row.
Full mode: horizontal progress line with markers at 25/50/75/100, next milestone info, completion celebration animation.

Milestone icons: 25%=seedling, 50%=star, 75%=rocket, 100%=trophy

#### GoalCountdown.vue
Props: daysRemaining, compact, showDetailed, showUrgency, variant

Urgency levels:
| Level | Condition | Colour |
|-------|-----------|--------|
| overdue | < 0 days | red |
| critical | <= 7 days | red |
| urgent | <= 30 days | blue |
| approaching | <= 90 days | blue |
| normal | > 90 days | grey |

Compact mode: text display. Full mode: years/months/days breakdown.

#### GoalContributionStreak.vue
Props: streak, longestStreak, compact, showMeter, showMessage, showEncouragement, isAnimating

Streak display:
| Streak | Icon | Intensity |
|--------|------|-----------|
| 0 | snowflake | cold |
| 1-2 | sparkle | starting |
| 3-5 | fire | warm |
| 6-11 | fire | hot |
| 12+ | trophy | blazing |

Full mode: fire icon with pulse animation, streak meter with 12 segments filled proportionally, encouragement messages ("Keep it up!", "Amazing consistency!").

#### GoalsByModule.vue
Props: goalsByModule (Object with savings/investment/property/retirement arrays)
Emits: edit-goal, view-goal

Shows goals organized in module-specific sections with cards, progress indicators, time remaining, status badges.

#### GoalsAnalysis.vue
Props: analysis (Object), loading (Boolean)
Emits: refresh

Shows: summary stats grid, affordability analysis with icon/category/message/breakdown, goals by module cards, recommendations list with priority badges, investment goal risk analysis with allocation bars.

#### ContributionModal.vue
Props: isOpen (Boolean), goal (Object)
Emits: close, save

Form contents:
- Current progress preview (progress bar + amounts)
- Amount input
- Quick amount buttons: 50, 100, 250, 500, 1000, + monthly contribution amount
- Date (defaults to today)
- Notes (textarea)
- New balance preview (calculated live)

#### GoalsProjectionChart.vue (797 lines)
The main projection chart component. Uses ApexCharts.

Sub-components: ViewToggle, ProjectionSummaryCards, EventIconLegend, AssumptionsDisclosure, EventIcon

**Data:** chartView ('net_worth'), chartType ('bar'), viewMode ('Individual'), eventMarkers[], activeTooltip, tooltipPosition

**Three chart views:**
1. **Net Worth** - Single series, muted periwinkle (#A8B8D8) bars
2. **Cash Flow** - Income (green) + Expenditure (red) bar series
3. **Asset Breakdown** - Stacked bars: Pensions, Property, Investments, Cash

**Event icon overlay:** Accesses ApexCharts internal globals (gridWidth, gridHeight, translateX, translateY, minX, maxX) to position EventIcon components above the correct bar. Groups events by age, stacks icons vertically (iconSize=26, iconGap=8, floatGap=20 above bar top). Completed events rendered at 40% opacity.

**Custom tooltip:** HTML tooltip showing age, values by chart view type, and all events (goals + life events) at that age with colour-coded amounts.

**Retirement annotation:** Vertical dashed line at retirement age.

**ViewToggle:** Individual/Household toggle (household requires spouse permission).

#### EventsTab.vue
Life events management tab. Contains:
- Summary cards: expected income total, expected expense total, net impact
- Filter/sort controls (by impact_type, certainty, search)
- LifeEventCard grid
- LifeEventForm modal for create/edit
- Delete confirmation modal

#### LifeEventCard.vue
Individual event card showing:
- Impact badge (+ green for income, - red for expense)
- Certainty label (confirmed/likely/possible/speculative)
- Amount formatted as currency
- Expected date
- Years until event
- Description preview (truncated)
- Edit and delete action buttons
- Uses LIFE_EVENT_ICONS constant for icon/colour

#### LifeEventForm.vue
Props: isOpen (Boolean), event (Object or null for create)
Emits: close, save

Form fields:
- event_name (text input)
- event_type (select with two optgroups: Income Events / Expense Events)
- amount (currency input with pound sign)
- expected_date (date picker)
- certainty (4 button group: confirmed/likely/possible/speculative)
- description (textarea)
- show_in_projection (checkbox)
- show_in_household_view (checkbox)

Shows selected type description from eventTypes.

#### EventIcon.vue
Props: event (Object, required), size (Number, default 22), isCompleted (Boolean)

Renders a coloured circle with SVG icon inside. Circle colour from EVENT_ICONS mapping. Icon SVG is 60% of circle size. Completed events shown at opacity-40.

#### EventIconsOverlay.vue
Alternative overlay positioned by percentage. Props: events, yearlyData, chartType. Uses CERTAINTY_STYLES for opacity adjustments.

#### EventTooltip.vue
Teleported tooltip for event details. Shows: name, amount (green for income / red for expense), age, year, certainty level, type badge.

#### EventIconLegend.vue
Legend showing event categories present in the chart. Groups by goal/life_event type. Limited to 4 categories each. Shows completed/income/expense indicators.

#### ChartTypeToggle.vue
Toggle between area and bar chart types. Props: modelValue. Emits: update:modelValue.

#### AssumptionsDisclosure.vue
Collapsible disclosure showing:
- Inflation rate
- Investment growth rate
- Property growth rate
- Disclaimer text
- Link to Settings -> Assumptions page

#### ProjectionSummaryCards.vue
Four summary cards:
1. Current Net Worth (with user's current age)
2. Projected at Retirement (with retirement age)
3. Projected at 90
4. Life Events summary (income/expense counts and totals)

### 8.3 Dashboard Components

#### GoalsOverviewCard.vue
**File:** `resources/js/components/Dashboard/GoalsOverviewCard.vue`

Dashboard card for Goals module. Entire card is clickable (navigates to /goals).

**Empty state:** "Set Your First Goal" with stat "78% more likely to feel on track". CTA button navigates to `/goals?action=create`.

**Active state:**
- Overall progress bar with current/target amounts
- Top goals list (up to 5): icon, name, status dot, mini progress bar, target amount, time remaining
- "more goals" indicator when > 5 goals
- Streak banner (if >= 3)
- Status banner: all on track (green) or N goals need attention (blue)

#### GoalsProjectionChartDashboard.vue
**File:** `resources/js/components/Dashboard/GoalsProjectionChartDashboard.vue`

Simplified version of GoalsProjectionChart for the dashboard:
- Height: 300px (vs 400px in full view)
- Net Worth view only (no view selector)
- No household toggle
- Same event icon overlay system (iconSize=22, iconGap=6, floatGap=16)
- Same custom tooltip with goals + life events at each age
- Retirement age annotation line
- Y-axis max calculation with 15% headroom for event icons

#### GoalsProjectionChartMini.vue
**File:** `resources/js/components/Dashboard/GoalsProjectionChartMini.vue`

Sparkline area chart:
- Height: 180px
- No event icons
- No complex tooltips (simple value tooltip only)
- Retirement age marker
- Gradient fill
- Reads from goals store projectionData

#### AreasToConsiderCard.vue (Dashboard)
**File:** `resources/js/components/Dashboard/AreasToConsiderCard.vue`

Includes goals in the "areas to consider" recommendations. If user has no goals, adds a priority-9 item:
```
{
    id: 'goals',
    title: 'Financial Goals',
    description: 'Set and track your objectives',
    route: '/goals',
    icon: 'target',
    priority: 9
}
```

### 8.4 Frontend Constants

#### eventIcons.js
**File:** `resources/js/constants/eventIcons.js`

**GOAL_ICONS** (11 entries):
| Goal Type | Icon | Colour | Category |
|-----------|------|--------|----------|
| emergency_fund | ShieldCheckIcon | #15803D | savings |
| property_purchase | HomeIcon | #1257A0 | property |
| home_deposit | HomeIcon | #1257A0 | property |
| holiday | GlobeAltIcon | #14B8A6 | lifestyle |
| car_purchase | TruckIcon | #64748B | purchase |
| wedding | HeartIcon | #EC4899 | lifestyle |
| education | AcademicCapIcon | #7C3AED | education |
| retirement | SunIcon | #F59E0B | retirement |
| wealth_accumulation | ChartBarIcon | #3B82F6 | investment |
| debt_repayment | BanknotesIcon | #64748B | debt |
| custom | FlagIcon | #64748B | custom |

**LIFE_EVENT_ICONS** (15 entries):
| Event Type | Icon | Colour | Impact |
|------------|------|--------|--------|
| inheritance | GiftIcon | #15803D | income |
| gift_received | GiftTopIcon | #15803D | income |
| bonus | CurrencyPoundIcon | #15803D | income |
| redundancy_payment | BriefcaseIcon | #15803D | income |
| property_sale | HomeIcon | #15803D | income |
| business_sale | BuildingOfficeIcon | #15803D | income |
| pension_lump_sum | DocumentTextIcon | #15803D | income |
| lottery_windfall | SparklesIcon | #15803D | income |
| custom_income | PlusCircleIcon | #15803D | income |
| large_purchase | ShoppingCartIcon | #EF4444 | expense |
| home_improvement | WrenchScrewdriverIcon | #EF4444 | expense |
| education_fees | AcademicCapIcon | #EF4444 | expense |
| gift_given | GiftIcon | #EF4444 | expense |
| medical_expense | ShieldCheckIcon | #EF4444 | expense |
| custom_expense | MinusCircleIcon | #EF4444 | expense |

**EVENT_ICONS** - Combined spread of GOAL_ICONS and LIFE_EVENT_ICONS.

**IMPACT_COLORS:** income = #15803D (green), expense = #EF4444 (red)

**CERTAINTY_STYLES:**
| Certainty | Opacity | Border |
|-----------|---------|--------|
| confirmed | 1.0 | solid |
| likely | 0.9 | solid |
| possible | 0.7 | dashed |
| speculative | 0.5 | dotted |

**PHASE_COLORS:** accumulation (trust blue), retirement (amber - charts-only exception to no-amber rule)

**Helper functions:** `getEventIconConfig(type, source)`, `getEventColor(event)`

#### eventIconSvgs.js
**File:** `resources/js/constants/eventIconSvgs.js`

Contains 22 Heroicon v2 outline SVG path definitions: HomeIcon, GlobeAltIcon, GiftIcon, GiftTopIcon, HeartIcon, AcademicCapIcon, TruckIcon, ShieldCheckIcon, SunIcon, ChartBarIcon, BanknotesIcon, FlagIcon, BuildingOfficeIcon, BriefcaseIcon, DocumentTextIcon, CurrencyPoundIcon, SparklesIcon, PlusCircleIcon, MinusCircleIcon, ShoppingCartIcon, WrenchScrewdriverIcon, CalendarIcon.

Function: `getIconSvg(iconName)` - returns SVG path data or FlagIcon as fallback.

---

## 9. Vuex Store

**File:** `resources/js/store/modules/goals.js`

### State

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| goals | Array | [] | All user goals |
| summary | Object | {} | Summary statistics |
| topGoals | Array | [] | Top 5 priority goals |
| byModule | Object | {savings:[], investment:[], property:[], retirement:[]} | Goals grouped by module |
| bestStreak | Number | 0 | Best streak across all goals |
| analysis | Object | null | Full analysis from GoalsAgent |
| recommendations | Array | [] | Agent recommendations |
| goalTypes | Array | [] | Cached goal type definitions |
| riskLevels | Array | [] | Cached risk level definitions |
| dashboardOverview | Object | null | Dashboard overview data |
| selectedGoal | Object | null | Currently selected/viewed goal |
| loading | Boolean | false | Loading state |
| error | String | null | Error message |
| lifeEvents | Array | [] | All life events |
| lifeEventsLoading | Boolean | false | Life events loading state |
| eventTypes | Array | [] | Cached event type definitions |
| projectionData | Object | null | Projection chart data |
| projectionLoading | Boolean | false | Projection loading state |
| chartView | String | 'net_worth' | Current chart view selection |
| viewMode | String | 'individual' | Individual or household |

### Getters

| Getter | Returns |
|--------|---------|
| activeGoals | Goals where status = 'active' |
| goalsForModule(module) | Goals filtered by assigned_module |
| goalsOnTrack | Goals where is_on_track = true |
| goalsBehind | Active goals where is_on_track = false |
| completedGoals | Goals where status = 'completed' |
| totalTargetAmount | Sum of all target_amount |
| totalCurrentAmount | Sum of all current_amount |
| overallProgress | (totalCurrent / totalTarget) * 100 |
| hasGoals | goals.length > 0 |
| goalsByPriority(priority) | Goals filtered by priority |
| priorityGoals | Goals sorted by priority order |
| dashboardData | dashboardOverview state |
| activeLifeEvents | Events where status in [expected, confirmed] |
| incomeEvents | Events where impact_type = 'income' |
| expenseEvents | Events where impact_type = 'expense' |
| lifeEventsForProjection | Events where show_in_projection = true |
| currentChartView | chartView state |
| currentViewMode | viewMode state |
| isHouseholdView | viewMode === 'household' |

### Actions (21)

**Goals:**
- `fetchGoals(filters)` - GET /api/goals with optional filters
- `fetchAnalysis()` - GET /api/goals/analysis
- `fetchDashboardOverview()` - GET /api/goals/dashboard-overview
- `fetchGoalTypes()` - GET /api/goals/types (cached - only fetches once)
- `fetchRiskLevels()` - GET /api/goals/risk-levels (cached)
- `createGoal(data)` - POST /api/goals. Also dispatches fetchDashboardOverview + fetchProjection
- `fetchGoal(id)` - GET /api/goals/{id}
- `updateGoal({id, data})` - PUT /api/goals/{id}. Also dispatches fetchDashboardOverview + fetchProjection
- `deleteGoal(id)` - DELETE /api/goals/{id}. Also dispatches fetchDashboardOverview + fetchProjection
- `recordContribution({goalId, data})` - POST /api/goals/{id}/contribution
- `getProjections(goalId)` - GET /api/goals/{id}/projections
- `getScenarios(goalId)` - GET /api/goals/{id}/scenarios
- `calculatePropertyCosts(data)` - POST /api/goals/calculate-property-costs
- `clearGoals()` - Resets all goals state

**Life Events:**
- `fetchLifeEvents(filters)` - GET /api/life-events
- `fetchEventTypes()` - GET /api/life-events/types (cached)
- `createLifeEvent(data)` - POST /api/life-events. Also dispatches fetchProjection
- `updateLifeEvent({id, data})` - PUT /api/life-events/{id}. Also dispatches fetchProjection
- `deleteLifeEvent(id)` - DELETE /api/life-events/{id}. Also dispatches fetchProjection

**Projection:**
- `fetchProjection()` - GET /api/goals/projection (passes household flag if viewMode=household)
- `setChartView(view)` - Sets chart view (net_worth/cash_flow/asset_breakdown)
- `setViewMode(mode)` - Sets individual/household and re-fetches projection + life events

---

## 10. Frontend API Service

**File:** `resources/js/services/goalsService.js`

### Goals API Methods

| Method | HTTP | Endpoint |
|--------|------|----------|
| getGoals(filters) | GET | /api/goals |
| getAnalysis() | GET | /api/goals/analysis |
| getDashboardOverview() | GET | /api/goals/dashboard-overview |
| getGoalTypes() | GET | /api/goals/types |
| getRiskLevels() | GET | /api/goals/risk-levels |
| createGoal(data) | POST | /api/goals |
| getGoal(id) | GET | /api/goals/{id} |
| updateGoal(id, data) | PUT | /api/goals/{id} |
| deleteGoal(id) | DELETE | /api/goals/{id} |
| recordContribution(id, data) | POST | /api/goals/{id}/contribution |
| getProjections(id) | GET | /api/goals/{id}/projections |
| getScenarios(id) | GET | /api/goals/{id}/scenarios |
| getContributionHistory(id, limit) | GET | /api/goals/{id}/contributions |
| calculatePropertyCosts(data) | POST | /api/goals/calculate-property-costs |

### Life Events API Methods

| Method | HTTP | Endpoint |
|--------|------|----------|
| getLifeEvents(filters) | GET | /api/life-events |
| getEventTypes() | GET | /api/life-events/types |
| createLifeEvent(data) | POST | /api/life-events |
| getLifeEvent(id) | GET | /api/life-events/{id} |
| updateLifeEvent(id, data) | PUT | /api/life-events/{id} |
| deleteLifeEvent(id) | DELETE | /api/life-events/{id} |
| markLifeEventCompleted(id) | POST | /api/life-events/{id}/complete |
| getLifeEventsByAge(filters) | GET | /api/life-events/by-age |

### Projection API Methods

| Method | HTTP | Endpoint |
|--------|------|----------|
| getProjection(options) | GET | /api/goals/projection |
| getHouseholdSummary() | GET | /api/goals/household-summary |

---

## 11. Router

**File:** `resources/js/router/index.js`

| Path | Component | Name | Notes |
|------|-----------|------|-------|
| /goals | GoalsDashboard (lazy loaded) | Goals | Breadcrumbs: [{label:'Goals', path:'/goals'}] |
| /preview/goals | GoalsDashboard (same component) | PreviewGoals | Preview mode route |

Module mapping: `'/goals': 'goals'`

---

## 12. Cross-Module Integrations

### 12.1 Dashboard Integration

**Dashboard.vue** (`resources/js/views/Dashboard.vue`):
- Imports GoalsProjectionChartDashboard
- Maps state/getters from goals store: dashboardOverview, projectionData, dashboardData
- Fetches on load: `goals/fetchDashboardOverview` and `goals/fetchProjection`
- Goals & Life Events card spans 2 columns in dashboard grid
- Shows GoalsProjectionChartDashboard when data exists
- Shows empty state "Set Your First Goal" with CTA to `/goals?action=create` when no data
- goalsData computed: has_goals, totalGoals, hasProjection, lifeEventsCount
- hasGoalsData always returns true (card always renders, with either content or empty state)

**AreasToConsiderCard.vue** (`resources/js/components/Dashboard/AreasToConsiderCard.vue`):
- Maps dashboardOverview from goals store
- If user has no goals, adds "Financial Goals" as a priority-9 area to consider with route to /goals

### 12.2 Agent Cross-References

| Agent | Uses Goals? | Details |
|-------|-------------|---------|
| GoalsAgent | Yes (primary) | Fully documented in Section 6 |
| SavingsAgent | Yes (legacy) | Uses SavingsGoal model and GoalProgressCalculator. Includes goals in analysis output and scenario building |
| InvestmentAgent | Yes (legacy) | Uses InvestmentGoal model. Maps goals with progress %, goal_name, target_amount, target_date in analysis output |
| RetirementAgent | No | No references to goals or life events |
| EstateAgent | No | No references to goals or life events |
| ProtectionAgent | No | No references to goals or life events |
| CoordinatingAgent | No | No references to goals or life events |

### 12.3 Savings Module Integration

The legacy savings store (`resources/js/store/modules/savings.js`) maintains its own `goals[]` state using the legacy SavingsGoal model. Has its own actions: createGoal, updateGoal, deleteGoal, updateGoalProgress. Getters: goalsOnTrack, goalsOffTrack. These operate independently of the unified goals system.

### 12.4 Investment Module Integration

The legacy investment store (`resources/js/store/modules/investment.js`) maintains its own `goals[]` state using the legacy InvestmentGoal model. Has actions: createGoal, updateGoal, deleteGoal, projectGoal. Getter: goalsOnTrack. Also has goalProjections and monteCarloResultsByGoal state. The Investment GoalProgressController provides Monte Carlo probability analysis and shortfall analysis for investment goals.

### 12.5 Net Worth Integration

The GoalsProjectionService (backend) injects NetWorthService to get the current net worth breakdown (cash, investments, pensions, property, liabilities) as the starting point for year-by-year projections. No direct references to goals/life events exist in NetWorthService itself - it only provides the initial data.

### 12.6 Investment Recommendations

The InvestmentRecommendationController uses 'goal' as one valid recommendation category alongside rebalancing, tax, fees, risk, and contribution. Investment recommendations can be tagged as goal-related.

### 12.7 Letter to Spouse

No direct references to goals or life events exist in the LetterToSpouse component.

### 12.8 PreviewWriteInterceptor

Goals and life events routes are NOT excluded from the PreviewWriteInterceptor middleware. This means all write operations (create, update, delete goals/events, record contributions) are intercepted for preview users.

---

## 13. Data Flows

### 13.1 Goal Creation Flow

1. User clicks "Add Goal" (from GoalsDashboard, GoalsOverview, or GoalsOverviewCard on dashboard)
2. GoalFormModal opens with empty form
3. User fills required fields: goal_name, goal_type, target_amount, target_date
4. If property goal: user can click "Calculate Property Costs" -> API call to `GoalAssignmentService.calculatePropertyCosts()` returning SDLT + legal + survey + moving costs
5. Form validates and emits `save` event with form data
6. GoalsDashboard.handleSaveGoal() dispatches `goals/createGoal` Vuex action
7. Vuex action calls `goalsService.createGoal(data)` -> POST /api/goals
8. GoalsController@store validates via StoreGoalRequest, auto-assigns module via `GoalAssignmentService.determineModule()` (unless module_override=true), calculates property costs if property goal, creates Goal record, returns GoalResource
9. Vuex action also dispatches fetchDashboardOverview + fetchProjection to refresh
10. Modal closes, UI updates

### 13.2 Life Event Creation Flow

1. User clicks "Add Life Event" -> switches to Events tab (or from within EventsTab)
2. LifeEventForm modal opens
3. User fills required fields: event_name, event_type, amount, expected_date. Certainty defaults to 'likely'
4. Form validates and emits `save`
5. EventsTab dispatches `goals/createLifeEvent`
6. Vuex action calls `goalsService.createLifeEvent(data)` -> POST /api/life-events
7. LifeEventController@store validates, auto-determines impact_type from event_type (income vs expense), calls `LifeEventService.createEvent()` -> LifeEvent::create(), clears cache
8. Vuex also dispatches fetchProjection to refresh chart
9. Modal closes, events list and projection update

### 13.3 Contribution Recording Flow

1. User clicks "Add Contribution" on a GoalCard
2. ContributionModal opens showing current progress, quick amount buttons
3. User enters amount, date (defaults to today), optional notes
4. Submit dispatches `goals/recordContribution`
5. POST /api/goals/{id}/contribution -> GoalsController@recordContribution
6. GoalProgressService.recordContribution():
   - Creates GoalContribution record with goal_balance_after = current_amount + amount
   - Updates goal's current_amount
   - Checks streak qualifying (contribution >= 80% of expected monthly)
   - Updates contribution streak with grace periods
   - Checks milestones (25/50/75/100%)
   - If 100% reached -> auto-completes goal (sets status=completed, completed_at)
7. Returns updated goal data

### 13.4 Projection Generation Flow

1. Frontend dispatches `goals/fetchProjection` (triggers on page load, goal/event CRUD, view mode change)
2. GET /api/goals/projection?household={true|false}
3. GoalsController@getProjection -> GoalsProjectionService.generateProjection()
4. Service flow:
   - Gets current net worth breakdown from NetWorthService (cash, investments, pensions, property, liabilities)
   - Gets active goals from Goal model
   - Gets active life events via LifeEventService.getActiveEventsForProjection()
   - Gets assumptions from AssumptionsService (inflation, investment growth, property growth)
   - Year-by-year simulation from current age to 90:
     - Deduct annual expenditure from cash
     - Apply life event impacts (income adds to cash, expense deducts from cash)
     - Apply goal completions at target dates (deduct target_amount from cash)
     - Grow investments/pensions at real rate (nominal - inflation)
     - Grow property at real property rate
     - Reduce mortgage linearly to retirement
   - Build events array for chart icons
   - Build summary statistics
5. Returns: yearly_data[], events[], summary, assumptions, retirement_age, current_age
6. Frontend renders via GoalsProjectionChart:
   - Three view options: Net Worth, Cash Flow, Asset Breakdown
   - Event icons positioned above bars using ApexCharts internal coordinates
   - Custom HTML tooltips showing age, values, and events
   - Retirement age annotation line

### 13.5 Module Auto-Assignment Logic

GoalAssignmentService.determineModule() rules:

| Goal Type | Assigned Module |
|-----------|----------------|
| emergency_fund | savings |
| property_purchase | property |
| home_deposit | property |
| retirement | retirement |
| wealth_accumulation | investment |
| Other types, <= 3 years horizon | savings |
| Other types, > 3 years AND >= 5000 target | investment |
| Other types, > 3 years AND < 5000 target | savings |

Can be overridden by setting `module_override: true` and specifying `assigned_module`.

### 13.6 Affordability Analysis Flow

GoalAffordabilityService.analyzeAffordability(Goal, User):
1. Calculate monthly surplus = net monthly income - monthly expenditure
2. Net income via simplified UK tax (PA 12570, basic 20% to 50270, higher 40% to 125140, additional 45%, NI 8%/2%)
3. Calculate required monthly contribution for goal (amount_remaining / months_remaining)
4. Sum total existing goal commitments across all active goals
5. Affordability ratio = (commitments + required) / surplus
6. Categorise and return suggestions

### 13.7 Streak Tracking System

- Qualifying threshold: contribution >= 80% of expected monthly amount
- Grace periods: weekly=10d, monthly=35d, quarterly=95d, annually=370d
- Streak increment: if within grace period and qualifying -> streak++
- Streak reset: if gap exceeds grace period -> reset to 1 (qualifying) or 0 (not)
- Longest streak tracking: updates if current exceeds it
- Display intensity: cold(0), starting(1-2), warm(3-5), hot(6-11), blazing(12+)

### 13.8 Risk-Based Projections

GoalRiskService.getProjections(Goal, RiskProfile):
1. Resolve risk level (1-5) from goal-specific or global profile
2. Get expected return and volatility for that level
3. Deterministic projection: FV = PV(1+r)^n + PMT * [((1+r)^n - 1) / r]
4. Probability via log-normal CDF (Abramowitz & Stegun)
5. Required contribution for 85% success probability
6. Yearly projections with 95% confidence bounds (mean +/- 1.96 * sigma)
7. Recommendation: increase/decrease/on track

---

## 14. Preview Persona Data

All 6 preview personas include goals and life_events arrays. Seeded via PreviewUserSeeder which calls `createGoals()` and `createLifeEvents()` for each persona.

**Persona data files:**
- `resources/js/data/personas/young_family.json`
- `resources/js/data/personas/peak_earners.json`
- `resources/js/data/personas/widow.json`
- `resources/js/data/personas/retired_couple.json`
- `resources/js/data/personas/entrepreneur.json`
- `resources/js/data/personas/young_saver.json`

**Seeder logic** (`database/seeders/PreviewUserSeeder.php`):
- Imports Goal and LifeEvent models
- `createGoals($user, $spouse, $data['goals'])`: iterates goal data, determines owner (user or spouse via 'owner' => 'spouse' flag), determines joint_owner_id for joint goals, creates Goal with all fields
- `createLifeEvents($user, $spouse, $data['life_events'])`: same owner/joint logic, creates LifeEvent with all fields
- On cleanup: `Goal::where('user_id', $user->id)->delete()` and `LifeEvent::withTrashed()->where('user_id', $user->id)->forceDelete()`

**ComprehensiveDemoDataSeeder** (`database/seeders/ComprehensiveDemoDataSeeder.php`):
- Seeds legacy SavingsGoal (2 goals: Holiday Fund 2026, New Car) and InvestmentGoal (1 goal: Retirement Nest Egg) for demo user
- Does NOT use unified Goal model

---

## 15. Legacy Systems

The unified Goals module consolidates two earlier separate systems:

### 15.1 Legacy Savings Goals

- Model: `SavingsGoal` (`app/Models/SavingsGoal.php`)
- Service: `GoalProgressCalculator` (`app/Services/Savings/GoalProgressCalculator.php`)
- Routes: /api/savings/goals (CRUD + progress)
- Store: savings.js maintains own goals[] state
- Agent: SavingsAgent references SavingsGoal

### 15.2 Legacy Investment Goals

- Model: InvestmentGoal (referenced but not seen as separate model file)
- Services:
  - `GoalProgressAnalyzer` (`app/Services/Investment/Goals/GoalProgressAnalyzer.php`)
  - `GoalProbabilityCalculator` (`app/Services/Investment/Goals/GoalProbabilityCalculator.php`)
  - `ShortfallAnalyzer` (`app/Services/Investment/Goals/ShortfallAnalyzer.php`)
- Controller: `GoalProgressController` (`app/Http/Controllers/Api/Investment/GoalProgressController.php`)
- Routes: /api/investment/goals (CRUD + progress + Monte Carlo + shortfall + what-if + glide-path)
- Store: investment.js maintains own goals[] state

### 15.3 Data Migration

Migration `2026_01_18_000003_migrate_existing_goals_data.php` moved data from legacy savings_goals (goal_type: 'savings') and investment_goals to the unified goals table, setting assigned_module based on source.

---

## 16. Complete File Inventory

### Backend (32 files)

| Category | File |
|----------|------|
| Model | `app/Models/Goal.php` |
| Model | `app/Models/GoalContribution.php` |
| Model | `app/Models/LifeEvent.php` |
| Model (legacy) | `app/Models/SavingsGoal.php` |
| Trait | `app/Traits/HasJointOwnership.php` |
| Agent | `app/Agents/GoalsAgent.php` |
| Controller | `app/Http/Controllers/Api/GoalsController.php` |
| Controller | `app/Http/Controllers/Api/LifeEventController.php` |
| Controller (legacy) | `app/Http/Controllers/Api/Investment/GoalProgressController.php` |
| Service | `app/Services/Goals/GoalAssignmentService.php` |
| Service | `app/Services/Goals/GoalAffordabilityService.php` |
| Service | `app/Services/Goals/GoalProgressService.php` |
| Service | `app/Services/Goals/GoalRiskService.php` |
| Service | `app/Services/Goals/GoalsProjectionService.php` |
| Service | `app/Services/Goals/LifeEventService.php` |
| Service (legacy) | `app/Services/Savings/GoalProgressCalculator.php` |
| Service (legacy) | `app/Services/Investment/Goals/GoalProgressAnalyzer.php` |
| Service (legacy) | `app/Services/Investment/Goals/GoalProbabilityCalculator.php` |
| Service (legacy) | `app/Services/Investment/Goals/ShortfallAnalyzer.php` |
| Resource | `app/Http/Resources/GoalResource.php` |
| Resource | `app/Http/Resources/GoalContributionResource.php` |
| Request | `app/Http/Requests/Goals/StoreGoalRequest.php` |
| Request | `app/Http/Requests/Goals/UpdateGoalRequest.php` |
| Request | `app/Http/Requests/StoreLifeEventRequest.php` |
| Request | `app/Http/Requests/UpdateLifeEventRequest.php` |
| Migration | `database/migrations/2026_01_18_000001_create_goals_table.php` |
| Migration | `database/migrations/2026_01_18_000002_create_goal_contributions_table.php` |
| Migration | `database/migrations/2026_01_18_000003_migrate_existing_goals_data.php` |
| Migration | `database/migrations/2026_01_24_160001_create_goals_table_v2.php` |
| Migration | `database/migrations/2026_01_24_160002_create_goal_contributions_table_v2.php` |
| Migration | `database/migrations/2026_02_03_120001_create_life_events_table.php` |
| Migration | `database/migrations/2026_02_03_120002_add_projection_fields_to_goals_table.php` |

### Frontend (29 files)

| Category | File |
|----------|------|
| View | `resources/js/views/Goals/GoalsDashboard.vue` |
| Component | `resources/js/components/Goals/GoalsOverview.vue` |
| Component | `resources/js/components/Goals/GoalsList.vue` |
| Component | `resources/js/components/Goals/GoalCard.vue` |
| Component | `resources/js/components/Goals/GoalFormModal.vue` |
| Component | `resources/js/components/Goals/GoalProgressBar.vue` |
| Component | `resources/js/components/Goals/GoalMilestoneTracker.vue` |
| Component | `resources/js/components/Goals/GoalCountdown.vue` |
| Component | `resources/js/components/Goals/GoalContributionStreak.vue` |
| Component | `resources/js/components/Goals/GoalsByModule.vue` |
| Component | `resources/js/components/Goals/GoalsAnalysis.vue` |
| Component | `resources/js/components/Goals/ContributionModal.vue` |
| Component | `resources/js/components/Goals/GoalsProjectionChart.vue` |
| Component | `resources/js/components/Goals/EventsTab.vue` |
| Component | `resources/js/components/Goals/LifeEventCard.vue` |
| Component | `resources/js/components/Goals/LifeEventForm.vue` |
| Component | `resources/js/components/Goals/EventIcon.vue` |
| Component | `resources/js/components/Goals/EventIconsOverlay.vue` |
| Component | `resources/js/components/Goals/EventTooltip.vue` |
| Component | `resources/js/components/Goals/EventIconLegend.vue` |
| Component | `resources/js/components/Goals/ChartTypeToggle.vue` |
| Component | `resources/js/components/Goals/AssumptionsDisclosure.vue` |
| Component | `resources/js/components/Goals/ProjectionSummaryCards.vue` |
| Dashboard | `resources/js/components/Dashboard/GoalsOverviewCard.vue` |
| Dashboard | `resources/js/components/Dashboard/GoalsProjectionChartDashboard.vue` |
| Dashboard | `resources/js/components/Dashboard/GoalsProjectionChartMini.vue` |
| Dashboard | `resources/js/components/Dashboard/AreasToConsiderCard.vue` |
| Constants | `resources/js/constants/eventIcons.js` |
| Constants | `resources/js/constants/eventIconSvgs.js` |

### Store & Service (2 files)

| Category | File |
|----------|------|
| Vuex Store | `resources/js/store/modules/goals.js` |
| API Service | `resources/js/services/goalsService.js` |

### Seeders & Data (8 files)

| Category | File |
|----------|------|
| Seeder | `database/seeders/PreviewUserSeeder.php` |
| Seeder (legacy) | `database/seeders/ComprehensiveDemoDataSeeder.php` |
| Persona Data | `resources/js/data/personas/young_family.json` |
| Persona Data | `resources/js/data/personas/peak_earners.json` |
| Persona Data | `resources/js/data/personas/widow.json` |
| Persona Data | `resources/js/data/personas/retired_couple.json` |
| Persona Data | `resources/js/data/personas/entrepreneur.json` |
| Persona Data | `resources/js/data/personas/young_saver.json` |

### Shared Components (1 file)

| Category | File |
|----------|------|
| Shared | `resources/js/components/Shared/ViewToggle.vue` |

**Total: 72 files across the Goals & Life Events module**
