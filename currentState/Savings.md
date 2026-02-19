# Savings Module - Current State Documentation

**Last Updated:** 2026-02-18
**Module Version:** Part of Fynla v0.7.0
**Status:** Fully functional with savings accounts, ISA tracking, emergency fund analysis, liquidity profiling, and rate comparison

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
18. [ISA Allowance Tracking System](#18-isa-allowance-tracking-system)

---

## 1. System Overview

The Savings module manages cash savings accounts, ISA allowance tracking, emergency fund analysis, liquidity profiling, and savings rate comparison. It supports joint ownership via the single-record pattern, encrypted account numbers, and cross-module ISA allowance aggregation with the Investment module.

### Architecture Flow

```
SavingsDashboard.vue
  -> CurrentSituation.vue (account listing, ownership badges, balance display)
  -> EmergencyFund.vue (gauge, expenditure, target adjustment)
  -> ISAAllowanceTracker.vue (cross-module allowance bar)
  -> SaveAccountModal.vue (complex CRUD with ISA validation)
  -> savingsService.js (20 API methods)
  -> SavingsController.php (16 endpoints, joint ownership logic)
  -> SavingsAgent.php (orchestrates analysis, caching)
  -> Services: EmergencyFundCalculator, ISATracker, LiquidityAnalyzer, RateComparator, GoalProgressCalculator
```

### File Count Summary

| Category | Count |
|---|---|
| Models | 3 (SavingsAccount, SavingsGoal, ISAAllowanceTracking) |
| Services | 5 |
| Vue Components | 12 + 3 views |
| Validation Requests | 6 |
| API Endpoints | 16 |

---

## 2. Database Schema

### 2.1 `savings_accounts`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK, AUTO_INCREMENT |
| `user_id` | bigint unsigned | FK -> users(id), NOT NULL |
| `joint_owner_id` | bigint | NULL |
| `ownership_type` | enum | `individual`, `joint`, `trust` DEFAULT 'individual' |
| `ownership_percentage` | decimal(5,2) | NOT NULL DEFAULT 100.00 |
| `account_type` | varchar(255) | NULL |
| `institution` | varchar(255) | NULL |
| `account_number` | varchar(255) | NULL (encrypted via `Crypt`) |
| `current_balance` | decimal(15,2) | NOT NULL DEFAULT 0.00 |
| `interest_rate` | decimal(5,4) | NOT NULL DEFAULT 0.0000 |
| `access_type` | enum | `immediate`, `notice`, `fixed` DEFAULT 'immediate' |
| `notice_period_days` | int | NULL |
| `maturity_date` | date | NULL |
| `is_isa` | tinyint(1) | NOT NULL DEFAULT 0 |
| `country` | varchar(255) | NOT NULL DEFAULT 'United Kingdom' |
| `is_emergency_fund` | tinyint(1) | NOT NULL DEFAULT 0 |
| `isa_type` | varchar(255) | NULL (`cash`, `stocks_shares`, `LISA`, `junior`) |
| `isa_subscription_year` | varchar(255) | NULL |
| `isa_subscription_amount` | decimal(15,2) | NULL |
| `beneficiary_id` | bigint unsigned | NULL, FK -> family_members |
| `beneficiary_name` | varchar(255) | NULL |
| `beneficiary_dob` | date | NULL |
| `regular_contribution_amount` | decimal(15,2) | NULL |
| `contribution_frequency` | varchar(255) | NULL (`monthly`, `quarterly`, `annually`) |
| `planned_lump_sum_amount` | decimal(15,2) | NULL |
| `planned_lump_sum_date` | date | NULL |
| `include_in_retirement` | tinyint(1) | NOT NULL DEFAULT 0 |
| `created_at` / `updated_at` | timestamp | NULL |

**Indexes:** `savings_accounts_user_id_index`, `savings_accounts_ownership_type_index`, `savings_accounts_joint_owner_id_index`, `savings_accounts_institution_idx`

**Single-Record Pattern:** One database record stores the FULL balance in `current_balance`. `user_id` = primary owner, `joint_owner_id` = secondary owner, `ownership_percentage` = primary owner's share (defaults to 50 for joint). The spouse's share is calculated as `(100 - ownership_percentage)`.

### 2.2 `savings_goals`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK, AUTO_INCREMENT |
| `user_id` | bigint unsigned | FK -> users(id), NOT NULL |
| `goal_name` | varchar(255) | NULL |
| `target_amount` | decimal(15,2) | NULL |
| `current_saved` | decimal(15,2) | NOT NULL DEFAULT 0.00 |
| `target_date` | date | NULL |
| `priority` | enum | `high`, `medium`, `low` DEFAULT 'medium' |
| `linked_account_id` | bigint unsigned | NULL, FK -> savings_accounts(id) ON DELETE SET NULL |
| `auto_transfer_amount` | decimal(10,2) | NULL |
| `created_at` / `updated_at` | timestamp | NULL |

**Indexes:** `savings_goals_linked_account_id_foreign`, `savings_goals_user_id_index`

**Note:** This is a LEGACY model from the Savings module. The newer Goals module (`Goal` model) supersedes it for goal-based planning.

### 2.3 `isa_allowance_tracking`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK, AUTO_INCREMENT |
| `user_id` | bigint unsigned | NOT NULL |
| `tax_year` | varchar(255) | NOT NULL |
| `cash_isa_used` | decimal(10,2) | NOT NULL DEFAULT 0.00 |
| `stocks_shares_isa_used` | decimal(10,2) | NOT NULL DEFAULT 0.00 |
| `lisa_used` | decimal(10,2) | NOT NULL DEFAULT 0.00 |
| `total_used` | decimal(10,2) | NOT NULL DEFAULT 0.00 |
| `total_allowance` | decimal(10,2) | NOT NULL DEFAULT 20000.00 |
| `created_at` / `updated_at` | timestamp | NULL |

**Indexes:** `isa_allowance_tracking_user_id_tax_year_unique` (UNIQUE composite), `isa_tracking_tax_year_idx`

---

## 3. Models

### 3.1 SavingsAccount (`app/Models/SavingsAccount.php`) - 103 lines

| Property | Details |
|---|---|
| Traits | `Auditable`, `HasFactory`, `HasJointOwnership` |
| Fillable | 30+ fields covering account details, ISA fields, ownership, beneficiary, retirement inclusion |
| Table | `savings_accounts` (default) |

**Casts:**

| Field | Cast |
|---|---|
| `current_balance` | `decimal:2` |
| `interest_rate` | `decimal:4` |
| `notice_period_days` | `integer` |
| `maturity_date` | `date` |
| `is_emergency_fund` | `boolean` |
| `is_isa` | `boolean` |
| `isa_subscription_amount` | `decimal:2` |
| `regular_contribution_amount` | `decimal:2` |
| `planned_lump_sum_amount` | `decimal:2` |
| `planned_lump_sum_date` | `date` |
| `beneficiary_dob` | `date` |
| `include_in_retirement` | `boolean` |

**Relationships:**

| Relationship | Type | Target |
|---|---|---|
| `user` | BelongsTo | `User` |
| `jointOwner` | BelongsTo | `User` via `joint_owner_id` |
| `beneficiary` | BelongsTo | `FamilyMember` via `beneficiary_id` |

**Encrypted Attribute:** `account_number` uses Laravel `Crypt::encryptString` / `Crypt::decryptString` for at-rest encryption.

### 3.2 SavingsGoal (`app/Models/SavingsGoal.php`) - 49 lines

| Property | Details |
|---|---|
| Traits | `HasFactory` |
| Fillable | `user_id`, `goal_name`, `target_amount`, `current_saved`, `target_date`, `priority`, `linked_account_id`, `auto_transfer_amount` |

**Casts:** `target_amount` decimal:2, `current_saved` decimal:2, `target_date` date, `auto_transfer_amount` decimal:2

**Relationships:**

| Relationship | Type | Target |
|---|---|---|
| `user` | BelongsTo | `User` |
| `linkedAccount` | BelongsTo | `SavingsAccount` via `linked_account_id` |

### 3.3 ISAAllowanceTracking (`app/Models/ISAAllowanceTracking.php`) - 43 lines

| Property | Details |
|---|---|
| Table | `isa_allowance_tracking` |
| Fillable | `user_id`, `tax_year`, `cash_isa_used`, `stocks_shares_isa_used`, `lisa_used`, `total_used`, `total_allowance` |

**Casts:** All monetary fields cast to `decimal:2`.

**Relationships:**

| Relationship | Type | Target |
|---|---|---|
| `user` | BelongsTo | `User` |

---

## 4. Controller

### SavingsController (`app/Http/Controllers/Api/SavingsController.php`) - 603 lines

**Traits:** `CalculatesOwnershipShare`, `SanitizedErrorResponse`

**Constructor Dependencies:** `SavingsAgent`, `ISATracker`, `NetWorthService`

#### Endpoints (16 total)

| # | Method | Route | Action | Description |
|---|---|---|---|---|
| 1 | GET | `/` | `index` | Returns all accounts (where user is owner OR joint_owner), goals, expenditure profile, ISA allowance. Transforms accounts with `user_share`, `full_balance`, `is_primary_owner`, `is_shared`. |
| 2 | POST | `/analyze` | `analyze` | Runs `SavingsAgent->analyze()` |
| 3 | GET | `/recommendations` | `recommendations` | Runs analyse then `generateRecommendations` |
| 4 | POST | `/scenarios` | `scenarios` | Runs `SavingsAgent->buildScenarios()` |
| 5 | GET | `/isa-allowance/{taxYear}` | `isaAllowance` | Gets ISA allowance status for a given tax year |
| 6 | POST | `/accounts` | `storeAccount` | Creates account. Defaults `ownership_type` to `individual`, ISA accounts forced to `United Kingdom`, joint defaults to 50%. Invalidates savings analysis cache + net worth cache. |
| 7 | GET | `/accounts/{id}` | `showAccount` | Shows single account. Access allowed if user is owner OR `joint_owner`. Loads `user` and `jointOwner` relationships. |
| 8 | PUT | `/accounts/{id}` | `updateAccount` | Only primary owner can update. Handles ownership percentage changes. Invalidates caches for both user and joint owner. |
| 9 | DELETE | `/accounts/{id}` | `destroyAccount` | Only primary owner can delete. Invalidates caches for both user and joint owner. |
| 10 | PATCH | `/accounts/{id}/toggle-retirement` | `toggleRetirementInclusion` | Toggles `include_in_retirement` flag. Allows both owner AND joint_owner. |
| 11 | GET | `/goals` | `indexGoals` | Lists goals with `linkedAccount` relationship |
| 12 | POST | `/goals` | `storeGoal` | Creates goal, defaults `current_saved` to 0 |
| 13 | PUT | `/goals/{id}` | `updateGoal` | Updates goal |
| 14 | DELETE | `/goals/{id}` | `destroyGoal` | Deletes goal |
| 15 | PATCH | `/goals/{id}/progress` | `updateGoalProgress` | Updates `current_saved` amount |

**Key Ownership Logic in `index()`:**

```php
// Accounts where user is primary owner OR joint owner
$accounts = SavingsAccount::where('user_id', $userId)
    ->orWhere('joint_owner_id', $userId)
    ->get();

// Transform each account with ownership context
$account->user_share = $account->current_balance * $ownershipMultiplier;
$account->full_balance = $account->current_balance;
$account->is_primary_owner = ($account->user_id === $userId);
$account->is_shared = !is_null($account->joint_owner_id);
```

---

## 5. Agent

### SavingsAgent (`app/Agents/SavingsAgent.php`) - 293 lines

**Extends:** `BaseAgent`
**Cache TTL:** 1800 seconds (30 minutes)

**Dependencies:**

| Service | Purpose |
|---|---|
| `EmergencyFundCalculator` | Emergency fund runway and adequacy |
| `ISATracker` | ISA allowance aggregation |
| `GoalProgressCalculator` | Goal progress and prioritisation |
| `LiquidityAnalyzer` | Liquidity categorisation and risk |
| `RateComparator` | Market rate comparison |

#### Methods

**`analyze(userId)`** - Cached analysis returning structured data:

| Section | Contents |
|---|---|
| `summary` | Total savings, monthly expenditure |
| `emergency_fund` | Runway, adequacy score, category |
| `isa_allowance` | Per-type usage, remaining, percentage used |
| `liquidity` | Categories, summary, ladder |
| `rate_comparisons` | Per-account market comparison, potential gain |
| `goals` | Per-goal progress, prioritised list |

Monthly expenditure source chain: `ExpenditureProfile` model -> `User.monthly_expenditure` -> `User.annual_expenditure / 12` (fallback).

**`generateRecommendations(analysisData)`** - Generates recommendations when:

| Condition | Category | Priority |
|---|---|---|
| Emergency fund adequacy < 100% | Emergency Fund | High |
| ISA allowance remaining > 0 | ISA Optimisation | Medium |
| Rate category = 'Poor' AND potential gain > £100 | Rate Improvement | Medium |
| Liquidity risk_level = 'High' | Liquidity | Low |

Each recommendation includes: `category`, `priority`, `title`, `description`, `action`.

**`buildScenarios(userId, parameters)`** - Two scenario types:

| Type | Formula |
|---|---|
| `increased_monthly_savings` | FV = PV(1+r)^n + PMT * ((1+r)^n - 1) / r |
| `goal_achievement` | Projection with compound interest to target date |

**`calculateFutureValueWithContributions(currentAmount, monthlyContribution, annualRate, years)`:**

```
FV = PV(1+r)^n + PMT * ((1+r)^n - 1) / r
```

Where `r` = monthly rate, `n` = total months.

---

## 6. Services

### 6.1 EmergencyFundCalculator (`app/Services/Savings/EmergencyFundCalculator.php`) - 75 lines

| Method | Parameters | Returns |
|---|---|---|
| `calculateRunway` | `totalSavings`, `monthlyExpenditure` | Months of coverage (decimal) |
| `calculateAdequacy` | `runway`, `targetMonths=6` | `{runway, target, adequacy_score (0-100), shortfall}` |
| `calculateMonthlyTopUp` | `shortfall`, `months` | Monthly amount needed to reach target |
| `categorizeAdequacy` | `runway` | Category string (see constants below) |

**Adequacy Categories:**

| Months | Category |
|---|---|
| 6+ | Excellent |
| 3-6 | Good |
| 1-3 | Fair |
| < 1 | Critical |

### 6.2 ISATracker (`app/Services/Savings/ISATracker.php`) - 172 lines

**Depends on:** `TaxConfigService`

| Method | Description |
|---|---|
| `getCurrentTaxYear()` | Returns format `"2025/26"` based on April 6 boundary |
| `getISAAllowanceStatus(userId, taxYear)` | **Cross-module query**: aggregates Cash ISA from `savings_accounts` AND Stocks & Shares ISA from `investment_accounts`. Returns `{cash_isa_used, stocks_shares_isa_used, lisa_used, total_used, total_allowance, remaining, percentage_used}`. Creates/updates `ISAAllowanceTracking` record. |
| `updateISAUsage(userId, isaType, amount?, taxYear?)` | Updates specific ISA type usage |
| `getTotalAllowance(taxYear)` | Gets from `TaxConfigService` |
| `getLISAAllowance()` | Gets LISA-specific limit from `TaxConfigService` |

### 6.3 LiquidityAnalyzer (`app/Services/Savings/LiquidityAnalyzer.php`) - 158 lines

| Method | Description |
|---|---|
| `categorizeLiquidity(accounts)` | Splits into `immediate`, `short_notice`, `fixed_term` collections by `access_type` |
| `buildLiquidityLadder(accounts)` | Sorted list of when funds become available. Each entry: `{date, account, amount, cumulative, days_from_now, access_type}` |
| `assessLiquidityRisk(liquidityProfile)` | Returns risk level based on percentage thresholds |
| `getLiquiditySummary(accounts)` | Returns `{total_liquid, total_short_notice, total_fixed, liquid_percent, risk_level}` |

**Liquidity Ladder Availability Timing:**

| Access Type | Available |
|---|---|
| `immediate` | Now |
| `notice` | Now + `notice_period_days` |
| `fixed` | At `maturity_date` |

**Risk Assessment Thresholds:**

| Risk Level | Criteria |
|---|---|
| Low | immediate >= 30% AND liquid >= 60% |
| Medium | immediate >= 20% AND liquid >= 40% |
| High | Everything else |

### 6.4 RateComparator (`app/Services/Savings/RateComparator.php`) - 120 lines

| Method | Description |
|---|---|
| `compareToMarketRates(account)` | Returns `{account_rate, market_rate, difference, is_competitive, category}` |
| `getMarketBenchmarks()` | Static UK market rates for 2024/25 |
| `calculateInterestDifference(account, marketRate)` | Annual monetary gain from switching |
| `getBenchmarkForAccount(account, benchmarks)` | Selects benchmark by `access_type` + `is_isa` + maturity term |

**Market Benchmarks (2024/25 - hardcoded):**

| Account Type | Rate |
|---|---|
| Easy access | 4.50% |
| Notice | 5.00% |
| Fixed 1 year | 5.25% |
| ISA versions | Slightly higher |

**Rate Categories:**

| Category | Criteria |
|---|---|
| Excellent | 1%+ above market |
| Good | At or above market |
| Fair | Within 1% of market |
| Poor | 1%+ below market |

**Competitive Threshold:** Within 0.5% of market rate.

### 6.5 GoalProgressCalculator (`app/Services/Savings/GoalProgressCalculator.php`) - 121 lines

| Method | Description |
|---|---|
| `calculateProgress(goal)` | Returns `{months_remaining, shortfall, required_monthly_savings, progress_percent, on_track}` |
| `projectGoalAchievement(goal, monthlyContribution, interestRate)` | FV formula with compound interest. Returns `{projected_final_amount, projected_completion_date, will_meet_goal}` |
| `prioritizeGoals(goals)` | Sorts by priority (`high`=1, `medium`=2, `low`=3) then by `target_date` (earliest first) |

**On-track determination:** A goal is on track when `auto_transfer_amount >= required_monthly_savings`.

---

## 7. Validation Requests

### 7.1 StoreSavingsAccountRequest

| Field | Rules |
|---|---|
| `account_type` | nullable, string |
| `institution` | nullable, string |
| `account_number` | nullable, string |
| `current_balance` | nullable, numeric, min:0 |
| `interest_rate` | nullable, numeric, min:0, max:20 |
| `access_type` | nullable, in:`immediate`,`notice`,`fixed` |
| `notice_period_days` | nullable, integer, min:0 |
| `maturity_date` | nullable, date, after:today |
| `is_emergency_fund` | nullable, boolean |
| `is_isa` | nullable, boolean |
| `country` | nullable, string |
| `isa_type` | nullable, in:`cash`,`stocks_shares`,`LISA` |
| `ownership_type` | nullable, in:`individual`,`joint`,`trust` |
| `ownership_percentage` | nullable, numeric, min:0, max:100 |
| `joint_owner_id` | nullable, exists:users,id |

### 7.2 UpdateSavingsAccountRequest

Same fields as `StoreSavingsAccountRequest` but uses `sometimes` instead of `nullable` for fields that are required when present.

### 7.3 StoreSavingsGoalRequest

| Field | Rules |
|---|---|
| `goal_name` | nullable, string |
| `target_amount` | nullable, numeric, min:0 |
| `current_saved` | nullable, numeric, min:0 |
| `target_date` | nullable, date, after:today |
| `priority` | nullable, in:`high`,`medium`,`low` |
| `linked_account_id` | nullable, exists:savings_accounts,id |
| `auto_transfer_amount` | nullable, numeric, min:0 |

### 7.4 UpdateSavingsGoalRequest

Same as `StoreSavingsGoalRequest` with `sometimes`.

### 7.5 SavingsAnalysisRequest

| Field | Rules |
|---|---|
| `target_emergency_fund_months` | nullable, integer, min:1, max:24 |
| `include_goals` | nullable, boolean |
| `include_rate_comparison` | nullable, boolean |

### 7.6 ScenarioRequest

| Field | Rules |
|---|---|
| `increased_monthly_savings` | nullable, numeric, min:0 |
| `interest_rate` | nullable, numeric, min:0, max:1 |
| `years` | nullable, integer, min:1, max:50 |
| `goal_id` | nullable, exists:savings_goals,id |
| `monthly_contribution` | nullable, numeric, min:0 |

---

## 8. Vuex Store

### `resources/js/store/modules/savings.js` - 439 lines

**Namespaced:** `true`

#### State

| Property | Type | Default |
|---|---|---|
| `accounts` | Array | `[]` |
| `goals` | Array | `[]` |
| `expenditureProfile` | Object | `null` |
| `analysis` | Object | `null` |
| `isaAllowance` | Object | `null` |
| `recommendations` | Array | `[]` |
| `loading` | Boolean | `false` |
| `error` | String | `null` |

#### Getters (12)

| Getter | Description |
|---|---|
| `totalSavings` | Sum of user's share for joint accounts (applies ownership percentage) |
| `emergencyFundTotal` | Sum of emergency fund accounts only (user's share) |
| `emergencyFundRunway` | Months of coverage based on expenditure |
| `isaAllowanceRemaining` | Remaining ISA allowance for current tax year |
| `isaUsagePercent` | Percentage of ISA allowance used |
| `currentYearISASubscription` | Current year ISA subscription total |
| `goalsOnTrack` | Goals where progress meets target trajectory |
| `goalsOffTrack` | Goals behind target trajectory |
| `totalISABalance` | Total balance across all ISA accounts |
| `accountsByAccessType` | Accounts grouped by `immediate`, `notice`, `fixed` |
| `monthlyExpenditure` | Monthly expenditure from expenditure profile |
| `loading` / `error` | Loading and error state accessors |

#### Actions (11)

| Action | API Call | Side Effects |
|---|---|---|
| `fetchSavingsData` | `GET /savings` | Populates accounts, goals, expenditureProfile, isaAllowance |
| `analyseSavings` | `POST /savings/analyze` | Updates `analysis` state |
| `fetchRecommendations` | `GET /savings/recommendations` | Updates `recommendations` state |
| `createAccount` | `POST /savings/accounts` | Dispatches `netWorth/refreshNetWorth` |
| `fetchAccount` | `GET /savings/accounts/{id}` | Returns single account |
| `updateAccount` | `PUT /savings/accounts/{id}` | Dispatches `netWorth/refreshNetWorth` |
| `deleteAccount` | `DELETE /savings/accounts/{id}` | Dispatches `netWorth/refreshNetWorth` |
| `createGoal` | `POST /savings/goals` | Adds to goals array |
| `updateGoal` | `PUT /savings/goals/{id}` | Updates goal in array |
| `deleteGoal` | `DELETE /savings/goals/{id}` | Removes from goals array |
| `updateGoalProgress` | `PATCH /savings/goals/{id}/progress` | Updates `current_saved` |

**Cross-store dispatch:** Account create/update/delete actions dispatch `netWorth/refreshNetWorth` to keep the wealth summary in sync.

---

## 9. API Service

### `resources/js/services/savingsService.js` - 181 lines

20 API endpoint wrappers:

| Method | HTTP | Endpoint |
|---|---|---|
| `getSavingsData()` | GET | `/savings` |
| `analyzeSavings(data)` | POST | `/savings/analyze` |
| `getRecommendations()` | GET | `/savings/recommendations` |
| `runScenario(data)` | POST | `/savings/scenarios` |
| `getISAAllowance(taxYear)` | GET | `/savings/isa-allowance/{taxYear}` |
| `createAccount(data)` | POST | `/savings/accounts` |
| `getAccount(id)` | GET | `/savings/accounts/{id}` |
| `updateAccount(id, data)` | PUT | `/savings/accounts/{id}` |
| `deleteAccount(id)` | DELETE | `/savings/accounts/{id}` |
| `toggleRetirementInclusion(id)` | PATCH | `/savings/accounts/{id}/toggle-retirement` |
| `getGoals()` | GET | `/savings/goals` |
| `createGoal(data)` | POST | `/savings/goals` |
| `updateGoal(id, data)` | PUT | `/savings/goals/{id}` |
| `deleteGoal(id)` | DELETE | `/savings/goals/{id}` |
| `updateGoalProgress(id, amount)` | PATCH | `/savings/goals/{id}/progress` |
| `getExpenditureProfile()` | GET | `/savings/expenditure-profile` |
| `updateExpenditureProfile(data)` | PUT | `/savings/expenditure-profile` |

---

## 10. Frontend Components

### 10.1 Components (`resources/js/components/Savings/`)

| Component | Lines | Description |
|---|---|---|
| `CurrentSituation.vue` | 635 | Main account overview. Shows account cards grid (in preview mode) or Open Banking promo (for real users). Each card displays: ownership badge, emergency fund/ISA badges, institution, account type, balance (full + user share for joint), interest rate. Includes `SaveAccountModal` and `DocumentUploadModal`. |
| `EmergencyFund.vue` | 252 | Emergency fund dashboard. Gauge visualisation, monthly expenditure total, target vs actual progress bars, adjustable target slider (3-12 months). Links to `/profile?tab=cashflow` for expenditure updates. |
| `EmergencyFundGauge.vue` | - | Visual gauge component for emergency fund runway vs target display. |
| `ISAAllowanceTracker.vue` | 207 | Tax-free savings allowance tracker. Stacked progress bar (Cash ISA blue, Stocks & Shares ISA purple). Shows LISA section if eligible (under 40, no property). Computes from `isaAllowance` state + investment accounts. |
| `SaveAccountModal.vue` | 978 | Complex form modal. Product types: Savings Account, Current Account, Easy Access, Instant Access, Notice, Fixed Term, Cash ISA, Junior ISA, Premium Bonds, NS&I. Auto-sets ISA/NS&I fields. Junior ISA beneficiary selection from family members. ISA allowance validation with usage bar. Regular contribution + lump sum planning. Joint ownership section (hidden for ISAs/NS&I). Country selector (hidden for ISAs). |
| `SaveGoalModal.vue` | - | Form modal for creating/editing savings goals. |
| `SavingsGoals.vue` | - | Goals list view with progress indicators. |
| `SavingsOverviewCard.vue` | - | Dashboard overview card for module summary. |
| `AccountDetails.vue` | - | Detailed view of a single account. |
| `Recommendations.vue` | - | Displays savings recommendations from agent analysis. |
| `WhatIfScenarios.vue` | - | Scenario builder UI for projecting future savings. |
| `InterestRateComparisonChart.vue` | - | Chart comparing account rates to market benchmarks. |

### 10.2 Views (`resources/js/views/Savings/`)

| View | Description |
|---|---|
| `SavingsDashboard.vue` | Main savings page with tabbed navigation |
| `SavingsAccountDetail.vue` | Single account detail page |
| `SavingsAccountDetailInline.vue` | Inline detail variant |

### 10.3 Key Component Patterns

**SaveAccountModal Product Types:**

```javascript
// Product type selection determines field visibility and defaults
const productTypes = [
  'savings_account', 'current_account', 'easy_access',
  'instant_access', 'notice', 'fixed', 'cash_isa',
  'junior_isa', 'premium_bonds', 'nsi'
];

// ISA accounts: country forced to 'United Kingdom', joint ownership hidden
// NS&I accounts: country forced to 'United Kingdom', joint ownership hidden
// Junior ISA: beneficiary selection from family members
```

**ISA Allowance Validation in SaveAccountModal:**

The modal validates ISA subscriptions during form submission. If `totalWithPlanned > ISA_ALLOWANCE`, an error message is displayed preventing the save.

**Cross-store Reads in ISAAllowanceTracker:**

```javascript
// Reads from investment store for S&S ISA usage
computed: {
  stocksSharesIsaUsed() {
    return this.$store.getters['investment/totalIsaContributions'] || 0;
  }
}
```

---

## 11. Frontend Routing

| Route | Component | Auth |
|---|---|---|
| `/savings` | `SavingsDashboard` | Required |
| `/savings/account/:id` | `SavingsAccountDetail` | Required |
| `/preview/savings` | `SavingsDashboard` | Preview mode |

**Store mapping:** `'/savings': 'savings'` - ensures the savings Vuex module is loaded when navigating to savings routes.

---

## 12. Cross-Module Integration

### 12.1 ISA Allowance (Savings <-> Investment)

The ISA allowance is shared across both modules. `ISATracker.getISAAllowanceStatus()` queries:
- `savings_accounts` where `is_isa = true` for Cash ISA usage
- `investment_accounts` where `account_type = 'isa'` for Stocks & Shares ISA usage

The frontend `ISAAllowanceTracker` component also reads from the investment store to display combined usage.

### 12.2 Net Worth (Savings -> Net Worth)

Account CRUD operations invalidate the net worth cache via `NetWorthService`. Vuex account actions dispatch `netWorth/refreshNetWorth` to keep the wealth summary synchronised.

### 12.3 Emergency Fund (Savings <- Expenditure)

Uses `ExpenditureProfile` model (from the Expenditure module) for monthly expenditure. Fallback chain:
1. `ExpenditureProfile` model
2. `User.monthly_expenditure`
3. `User.annual_expenditure / 12`

### 12.4 Retirement (Savings -> Retirement)

The `include_in_retirement` flag on savings accounts allows them to be included in the Retirement Income Planner. Toggled via `PATCH /accounts/{id}/toggle-retirement`.

### 12.5 Goals Module (Savings <-> Goals)

`SavingsGoal` is the legacy model within this module. The newer Goals module can link goals to savings accounts via `linked_savings_account_id` on the `Goal` model.

### 12.6 User Profile (Savings <- User Profile)

`SaveAccountModal` reads:
- `userProfile/spouse` getter for joint ownership options
- `userProfile/juniorIsaEligibleChildren` for Junior ISA beneficiary selection

---

## 13. Profile Completeness Integration

Savings accounts count is tracked via `savings_accounts_count` on the `users` table (counter cache pattern). This feeds into the overall profile completeness calculation displayed on the dashboard.

---

## 14. Seeder Data

### `PreviewUserSeeder::createSavingsAccounts()`

The seeder method populates savings accounts for preview personas:

- Determines account owner via `determineAccountOwner()` (may assign to spouse as primary owner)
- Sets `joint_owner_id` correctly for joint accounts
- Uses the **single-record pattern**: stores FULL balance in `current_balance`, no reciprocal records
- Sets ISA type from `account_type` mapping: `cash_isa` -> `cash`, `lisa` -> `LISA`
- Sets `isa_subscription_year` to current tax year `2025/26`
- Sets `regular_contribution_amount` and `contribution_frequency` for accounts with regular contributions

---

## 15. API Routing

All endpoints are under `/api/savings`, authenticated via `auth:sanctum` middleware:

```
GET    /api/savings                                    -> index
POST   /api/savings/analyze                            -> analyze
GET    /api/savings/recommendations                    -> recommendations
POST   /api/savings/scenarios                          -> scenarios
GET    /api/savings/isa-allowance/{taxYear}             -> isaAllowance
POST   /api/savings/accounts                           -> storeAccount
GET    /api/savings/accounts/{id}                       -> showAccount
PUT    /api/savings/accounts/{id}                       -> updateAccount
DELETE /api/savings/accounts/{id}                       -> destroyAccount
PATCH  /api/savings/accounts/{id}/toggle-retirement     -> toggleRetirementInclusion
GET    /api/savings/goals                               -> indexGoals
POST   /api/savings/goals                               -> storeGoal
PUT    /api/savings/goals/{id}                          -> updateGoal
DELETE /api/savings/goals/{id}                          -> destroyGoal
PATCH  /api/savings/goals/{id}/progress                 -> updateGoalProgress
```

**Note:** Expenditure profile endpoints (`GET /savings/expenditure-profile`, `PUT /savings/expenditure-profile`) are referenced in the API service but routed separately.

---

## 16. Key Constants and Business Logic

### Emergency Fund

| Constant | Value |
|---|---|
| Default target | 6 months |
| Excellent | 6+ months |
| Good | 3-6 months |
| Fair | 1-3 months |
| Critical | < 1 month |

### ISA Allowances

| Allowance | Value | Source |
|---|---|---|
| Total ISA allowance | £20,000 | `TaxConfigService` |
| LISA allowance | £4,000 | `TaxConfigService` |
| Junior ISA allowance | £9,000 | Hardcoded |
| LISA bonus | 25% (max £1,000/year) | Hardcoded |
| LISA eligibility | Under 40, no property | Hardcoded |

### Account Enumerations

| Type | Values |
|---|---|
| Access types | `immediate`, `notice`, `fixed` |
| Account types | `savings_account`, `current_account`, `easy_access`, `instant_access`, `notice`, `fixed`, `cash_isa`, `junior_isa`, `premium_bonds`, `nsi` |
| Ownership types | `individual`, `joint`, `trust` |
| Goal priorities | `high`, `medium`, `low` |
| Contribution frequencies | `monthly`, `quarterly`, `annually` |

### Liquidity Risk Thresholds

| Risk Level | Immediate Access | Total Liquid (immediate + short notice) |
|---|---|---|
| Low | >= 30% | >= 60% |
| Medium | >= 20% | >= 40% |
| High | Below medium thresholds | Below medium thresholds |

### Rate Comparison

| Metric | Value |
|---|---|
| Competitive threshold | Within 0.5% of market rate |
| Excellent | 1%+ above market |
| Good | At or above market |
| Fair | Within 1% of market |
| Poor | 1%+ below market |

### Caching

| Key | TTL |
|---|---|
| Savings analysis | 1800 seconds (30 minutes) |
| Net worth | Invalidated on account CRUD |

---

## 17. Known Issues and Limitations

| # | Issue | Impact | Severity |
|---|---|---|---|
| 1 | Market benchmark rates in `RateComparator` are hardcoded for 2024/25 | Rate comparison categories may be inaccurate as market rates change | Medium |
| 2 | `SavingsGoal` is a legacy model coexisting with the newer Goals module `Goal` model | Two competing goal systems; potential confusion in cross-module references | Low |
| 3 | `CashFlowCoordinator.calculateAvailableSurplus()` returns placeholder £1,000 | Surplus-based recommendations are not based on real data | Medium |
| 4 | Account number encryption uses Laravel `Crypt` but no validation on length/format | Invalid data could be encrypted without detection | Low |
| 5 | ISA subscription tracking depends on manual entry | No automatic calculation from regular contributions; users must update subscription amounts manually | Medium |
| 6 | `ExpenditureProfile` fallback chain (`ExpenditureProfile` -> `User.monthly_expenditure` -> `User.annual_expenditure/12`) could yield inconsistent results | Emergency fund calculations may vary depending on which source is used | Low |
| 7 | No soft deletes on `savings_accounts` or `savings_goals` | Deleted records are permanently lost; no audit trail for deletions | Low |
| 8 | Open Banking integration shown as "Coming Soon" | Real users see a promotional panel instead of account data; functionality not implemented | Info |

---

## 18. ISA Allowance Tracking System

The ISA allowance tracking system is a cross-module subsystem that aggregates ISA usage across both the Savings and Investment modules, enforcing the UK's shared £20,000 annual ISA allowance.

### Tax Year Calculation

The UK tax year runs from 6 April to 5 April. `ISATracker.getCurrentTaxYear()` determines the active year:

```php
// If current date is before April 6, use previous year as start
// e.g., 15 March 2026 -> "2025/26"
// e.g., 10 April 2026 -> "2026/27"
$year = (Carbon::now()->month < 4 || (Carbon::now()->month === 4 && Carbon::now()->day < 6))
    ? Carbon::now()->year - 1
    : Carbon::now()->year;
return $year . '/' . substr($year + 1, -2);
```

### Cross-Module Aggregation

ISA usage is collected from multiple sources:

| ISA Type | Source Table | Query |
|---|---|---|
| Cash ISA | `savings_accounts` | `is_isa = true AND isa_type = 'cash'` |
| Stocks & Shares ISA | `investment_accounts` | `account_type = 'isa'` |
| LISA | `savings_accounts` | `is_isa = true AND isa_type = 'LISA'` |

The `ISAAllowanceTracking` table persists the calculated totals per user per tax year, with a unique composite key on `(user_id, tax_year)`.

### Frontend Display (ISAAllowanceTracker Component)

The component renders:

1. **Overall allowance bar** - Stacked progress bar showing total usage against £20,000
2. **Per-type breakdown** - Cash ISA (blue), Stocks & Shares ISA (purple), LISA (separate)
3. **LISA section** - Conditionally shown when user is under 40 and does not own property
4. **Remaining calculation** - `£20,000 - total_used`

### SaveAccountModal ISA Validation

During form submission, the modal validates that the new ISA subscription does not exceed the remaining allowance:

```javascript
// Calculate total with planned contribution
const totalWithPlanned = currentIsaUsed + newSubscriptionAmount;
if (totalWithPlanned > ISA_ALLOWANCE) {
  // Show error: "This would exceed your ISA allowance by £X"
  return;
}
```

### Contribution Planning

The system supports forward-looking ISA contribution planning:

- **Regular contributions** (`regular_contribution_amount` + `contribution_frequency`) are projected for the remainder of the tax year
- **Planned lump sums** (`planned_lump_sum_amount` + `planned_lump_sum_date`) are included if the date falls within the current tax year
- **Payments remaining** calculation is based on the contribution frequency and the number of months elapsed since 6 April

### Data Flow

```
SaveAccountModal (validates ISA limits)
  -> savingsService.createAccount() / updateAccount()
  -> SavingsController (stores account with ISA fields)
  -> ISATracker.getISAAllowanceStatus() (aggregates across modules)
  -> ISAAllowanceTracking (persists per-user per-year totals)
  -> ISAAllowanceTracker.vue (displays combined usage)
```
