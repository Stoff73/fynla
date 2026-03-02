# Fynla Risk Analysis System - Complete Reference

**Last Updated:** 18 February 2026
**Version:** v0.7.0

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Risk Level Definitions](#2-risk-level-definitions)
3. [Database Schema](#3-database-schema)
4. [Auto-Calculation Engine (7 Factors)](#4-auto-calculation-engine-7-factors)
5. [Reactive Recalculation (Observer Pattern)](#5-reactive-recalculation-observer-pattern)
6. [Risk Preference Service](#6-risk-preference-service)
7. [Controller & API Routes](#7-controller--api-routes)
8. [Frontend Architecture](#8-frontend-architecture)
9. [Risk in the Investment Module](#9-risk-in-the-investment-module)
10. [Risk in the Goals Module](#10-risk-in-the-goals-module)
11. [Risk in the Holistic/Coordination Module](#11-risk-in-the-holisticcoordination-module)
12. [Risk in Other Modules](#12-risk-in-other-modules)
13. [Preview Data & Seeding](#13-preview-data--seeding)
14. [Legacy System & Migration](#14-legacy-system--migration)
15. [Data Flow Summary](#15-data-flow-summary)
16. [Design System Constants](#16-design-system-constants)
17. [Vulnerabilities & Concerns](#17-vulnerabilities--concerns)
18. [Recommended Improvements](#18-recommended-improvements)
19. [Complete File Inventory](#19-complete-file-inventory)

---

## 1. System Overview

The Fynla risk system implements an **automated 5-level risk profiling engine** that calculates a user's investment risk profile from **7 financial factors** derived from their actual financial data. The system is reactive -- risk profiles automatically recalculate when underlying data changes (income, savings, investments, pensions, properties, family members).

### Core Principles

1. **Data-driven:** Risk level is computed from real financial data, not subjective questionnaires
2. **Reactive:** Model observers trigger automatic recalculation when relevant data changes
3. **Hierarchical:** Global profile -> per-product overrides -> per-goal overrides
4. **Mode-based:** Final level = most frequent level across 7 factors (ties broken towards lower risk)
5. **Dual-mode:** Users can accept the auto-calculated level or self-select an override

### Architecture

```
User changes data (income, savings, property, etc.)
        |
   Model Observer detects relevant change
        |
   Debounce (5-second cache window)
        |
   RecalculateRiskProfileJob (queued, 5s delay)
        |
   AutoRiskCalculator evaluates 7 factors
        |
   RiskPreferenceService saves to risk_profiles table
        |
   Backfills investment_accounts/dc_pensions with NULL risk_preference
        |
   Frontend reads via GET /api/investment/risk/profile
   (always recalculates factor_breakdown live for current values)
```

---

## 2. Risk Level Definitions

### 2.1 Current 5-Level System

| Level Key | Numeric | Display Name | Equities | Bonds | Cash | Alternatives | Expected Return | Volatility | Colour Class |
|-----------|---------|-------------|----------|-------|------|-------------|----------------|------------|-------------|
| `low` | 1 | Low | 10% | 70% | 20% | 0% | 1-3% (typical 2%) | 3% | `green` |
| `lower_medium` | 2 | Lower-Medium | 30% | 55% | 10% | 5% | 2-4.5% (typical 3.5%) | 6% | `teal` |
| `medium` | 3 | Medium | 50% | 35% | 10% | 5% | 3.5-6.5% (typical 5%) | 10% | `blue` |
| `upper_medium` | 4 | Upper-Medium | 75% | 20% | 0% | 5% | 5-8.5% (typical 6.5%) | 15% | `orange` |
| `high` | 5 | High | 90% | 0% | 5% | 5% | 6-12% (typical 8%) | 20% | `red` |

### 2.2 Full Descriptions (From RiskPreferenceService)

| Level | Description |
|-------|-------------|
| **Low** | You are a very cautious investor. You prioritise investment products of low uncertainty on risk and prefer to minimise investment loss. You may have limited knowledge and/or experience in financial investment. |
| **Lower-Medium** | You are a cautious investor. You are equipped with some knowledge and/or experience in financial investment and are willing to take modest risk for the potential to achieve returns better than bank deposits. |
| **Medium** | You are equipped with related investment knowledge and/or experience. You are willing to accept commensurable price fluctuation and take a certain degree of risk to achieve returns in comparison with the major stock market indexes. You possess good financial capability to deal with potential losses. |
| **Upper-Medium** | You are equipped with related investment knowledge and/or experience. You are willing to accept relatively higher price fluctuation and take relatively higher risk to achieve returns better than the major stock market indexes. You possess solid financial capability to deal with potential losses. |
| **High** | You demonstrate a strong preference, knowledge and/or experience for high-risk, complex or leveraged products. You possess substantial financial capability to deal with potential losses from financial investment. |

### 2.3 Legacy 3-Level System

| Legacy Value | Maps To |
|-------------|---------|
| `cautious` | `lower_medium` |
| `balanced` | `medium` |
| `adventurous` | `upper_medium` |

The legacy `risk_tolerance` field (enum: `cautious`, `balanced`, `adventurous`) is still in the database but nullable and unused for new calculations.

---

## 3. Database Schema

### 3.1 `risk_profiles` Table

| Field | Type | Default | Nullable | Notes |
|-------|------|---------|----------|-------|
| `id` | bigint unsigned | auto | No | Primary key |
| `user_id` | bigint unsigned | - | No | FK to `users.id`, CASCADE DELETE, indexed |
| `risk_tolerance` | enum(cautious,balanced,adventurous) | - | **Yes** | Legacy field, unused for new profiles |
| `risk_level` | enum(low,lower_medium,medium,upper_medium,high) | - | **Yes** | Current 5-level system |
| `capacity_for_loss_percent` | decimal(5,2) | - | **Yes** | Legacy manual input field |
| `time_horizon_years` | int | - | **Yes** | Legacy manual input field |
| `knowledge_level` | enum(novice,intermediate,experienced) | - | **Yes** | Legacy manual input field |
| `attitude_to_volatility` | varchar(255) | - | Yes | Legacy manual input field |
| `esg_preference` | boolean | false | No | ESG/sustainable investing preference |
| `risk_assessed_at` | timestamp | - | Yes | When last calculated/set |
| `is_self_assessed` | boolean | true | No | `false` = auto-calculated, `true` = user-selected |
| `factor_breakdown` | json | - | Yes | Array of 7 factor analysis results |
| `created_at` | timestamp | auto | Yes | |
| `updated_at` | timestamp | auto | Yes | |

**Index:** `risk_profiles_user_id_index` on `user_id`

### 3.2 Risk Fields on Other Tables

**`investment_accounts` table:**

| Field | Type | Notes |
|-------|------|-------|
| `risk_preference` | string, nullable | Per-product risk override (5-level enum string) |
| `has_custom_risk` | boolean, default false | Flag when product uses non-default risk level |

**`dc_pensions` table:**

| Field | Type | Notes |
|-------|------|-------|
| `risk_preference` | string, nullable | Per-pension risk override (5-level enum string) |
| `has_custom_risk` | boolean, default false | Flag when pension uses non-default risk level |

**`goals` table:**

| Field | Type | Notes |
|-------|------|-------|
| `risk_preference` | integer, nullable | Goal-specific risk (1-5 numeric, different from string system) |
| `use_global_risk_profile` | boolean, default true | Whether to use global profile or goal-specific |

### 3.3 Migration History

| Migration | Purpose |
|-----------|---------|
| Original `create_risk_profiles_table` (batch 1) | Base table with legacy fields |
| `add_risk_level_to_risk_profiles_table` | Added 5-level `risk_level` enum |
| `add_risk_preference_to_investment_accounts_table` | Per-product risk override |
| `add_risk_preference_to_dc_pensions_table` | Per-pension risk override |
| `2026_01_03_154132_make_risk_profile_columns_nullable.php` | Made legacy columns nullable for simple flow |
| `2026_01_16_151113_add_factor_breakdown_to_risk_profiles.php` | Added JSON `factor_breakdown` column |
| `2026_01_24_134257_make_factor_breakdown_nullable_on_risk_profiles.php` | Made `factor_breakdown` nullable |

---

## 4. Auto-Calculation Engine (7 Factors)

**File:** `app/Services/Risk/AutoRiskCalculator.php` (467 lines)

**Dependency:** `NetWorthService` (injected via constructor)

Each factor returns a structured array:

```php
[
    'factor' => 'capacity_for_loss',       // Machine key
    'display_name' => 'Capacity for Loss', // User-facing label
    'level' => 'medium',                   // Resulting risk level for this factor
    'value' => '32.5%',                    // Formatted display value
    'raw_value' => 32.5,                   // Numeric value for calculations
    'description' => '...',                // Contextual explanation
    'icon' => 'shield',                    // Icon identifier for frontend
    'components' => [                      // Raw data that fed the calculation
        'investments_total' => 150000,
        'pensions_total' => 200000,
        'net_worth' => 1075000,
    ],
]
```

### Factor 1: Capacity for Loss

**Formula:** `(investments + pensions) / net_worth * 100`

| Ratio | Level | Reasoning |
|-------|-------|-----------|
| 0-15% | `high` | Small proportion at risk; high capacity to absorb losses |
| 15-50% | `medium` | Moderate exposure |
| 50-75% | `lower_medium` | Significant exposure |
| >75% | `low` | Most wealth at risk; low capacity for loss |

**Data Sources:**
- `InvestmentAccount::where('user_id')->sum('current_value')`
- `DCPension::where('user_id')->sum('current_fund_value')`
- `NetWorthService::calculateNetWorth($user)['net_worth']`

**Icon:** `shield`

### Factor 2: Time Horizon

**Formula:** Years to target retirement age (or state pension age 67 as default)

| Years | Level | Reasoning |
|-------|-------|-----------|
| Retired or <=0 | `lower_medium` | Capital preservation priority |
| <3 years | `lower_medium` | Insufficient recovery time |
| 3-15 years | `medium` | Time for moderate risk and recovery |
| 15-20 years | `upper_medium` | Can accept higher volatility |
| 20+ years | `high` | Ample time to ride out volatility |

**Retirement Age Resolution:**
1. If `employment_status` is `retired`/`semi_retired`: 0 years
2. If `target_retirement_age` and `date_of_birth` set: `target_retirement_age - current_age`
3. If `retirement_date` set: years until that date
4. Default: state pension age 67 minus current age
5. Fallback: `null` (cannot determine)

**Icon:** `clock`

### Factor 3: Education Level

| Education | Level | Reasoning |
|-----------|-------|-----------|
| No degree (`secondary`, `a_level`, `gcse`, `none`, null) | `lower_medium` | More cautious approach to complex investments |
| Degree or higher | `medium` | Familiarity with complex concepts |

**Icon:** `academic-cap`

### Factor 4: Dependants

**Formula:** Count of `FamilyMember` where `is_dependent = true`

| Dependants | Level | Reasoning |
|------------|-------|-----------|
| 0 | `upper_medium` | More freedom to take risk |
| 1 | `medium` | Balanced approach needed |
| 2+ | `lower_medium` | Financial stability is priority |

**Components include:** List of dependant names and relationships

**Icon:** `users`

### Factor 5: Employment Status

| Status | Level | Reasoning |
|--------|-------|-----------|
| `employed`, `self_employed`, `full_time`, `part_time`, `contractor` | `medium` | Income to rebuild if investments fall |
| `retired`, `semi_retired` | `lower_medium` | No employment income for recovery |
| Other (unemployed, student, unknown) | `lower_medium` | Cautious approach recommended |

**Icon:** `briefcase`

### Factor 6: Emergency Cash

**Formula:** Emergency fund total / monthly expenditure = runway months

| Runway | Level | Reasoning |
|--------|-------|-----------|
| <3 months | `lower_medium` | Insufficient buffer |
| 3-6 months | `medium` | Reasonable buffer |
| 6+ months | `upper_medium` | Strong cushion for volatility |

**Edge case:** If `monthly_expenditure` is 0 but emergency fund exists, assumes 12 months runway.

**Data Sources:**
- `SavingsAccount::where('user_id')->where('is_emergency_fund', true)->sum('current_balance')`
- `$user->monthly_expenditure`

**Icon:** `cash`

### Factor 7: Surplus Cash

**Formula:** Monthly income - monthly expenditure

Monthly income = sum of all annual income fields / 12:
- `annual_employment_income`
- `annual_self_employment_income`
- `annual_rental_income`
- `annual_dividend_income`
- `annual_interest_income`
- `annual_other_income`
- `annual_trust_income`

| Surplus | Level | Reasoning |
|---------|-------|-----------|
| <=0 (deficit) | `lower_medium` | No capacity to top up investments |
| 1-500 | `medium` | Modest contribution capacity |
| >500 | `upper_medium` | Strong regular investing capacity |

**Icon:** `trending-up`

### Final Level Determination

**Method:** `determineFinalLevel(array $factors)`

1. Count occurrences of each level across all 7 factors
2. Find the maximum count (mode)
3. If multiple levels tie for highest count, iterate through `['low', 'lower_medium', 'medium', 'upper_medium', 'high']` and return the **first** (lowest risk) match
4. Default fallback: `'medium'`

**Example:**
```
Factor results: lower_medium, medium, medium, upper_medium, medium, upper_medium, lower_medium
Counts: lower_medium=2, medium=3, upper_medium=2
Winner: medium (highest count = 3)
```

**Tie example:**
```
Factor results: lower_medium, medium, lower_medium, medium, upper_medium, lower_medium, medium
Counts: lower_medium=3, medium=3, upper_medium=1
Tie between lower_medium and medium, both at 3
Winner: lower_medium (lower risk preferred)
```

---

## 5. Reactive Recalculation (Observer Pattern)

### 5.1 Architecture

```
Model Change
    |
    v
Model Observer (detects relevant field change)
    |
    v
RiskRecalculationObserver::dispatchRecalculation()
    |
    v
Debounce check (Cache::has("risk_recalc_pending_{$userId}"))
    |
    v
Cache::put(key, true, 5)  +  RecalculateRiskProfileJob::dispatch()
    |                              |
    (5-second debounce)     (5-second delay, ShouldQueue)
                                   |
                                   v
                           RiskPreferenceService::calculateAndSetRiskLevel()
                                   |
                                   v
                           Backfill NULL risk_preference on accounts/pensions
```

### 5.2 Base Observer

**File:** `app/Observers/RiskRecalculationObserver.php`

Provides `dispatchRecalculation(int $userId, string $trigger)`:
- Skips if `$userId <= 0`
- Checks cache key `risk_recalc_pending_{$userId}` (5-second window)
- If not pending: sets cache + dispatches `RecalculateRiskProfileJob` with 5-second delay

### 5.3 Model Observers

| Observer | File | Model | Triggers |
|----------|------|-------|----------|
| `UserRiskObserver` | `app/Observers/UserRiskObserver.php` | `User` | **updated:** if any of 12 fields changed: `annual_employment_income`, `annual_self_employment_income`, `annual_rental_income`, `annual_dividend_income`, `annual_interest_income`, `annual_other_income`, `annual_trust_income`, `monthly_expenditure`, `education_level`, `employment_status`, `target_retirement_age`, `date_of_birth` |
| `FamilyMemberRiskObserver` | `app/Observers/FamilyMemberRiskObserver.php` | `FamilyMember` | **created:** if `is_dependent`. **updated:** if `is_dependent` changed. **deleted:** if `is_dependent` |
| `SavingsAccountRiskObserver` | `app/Observers/SavingsAccountRiskObserver.php` | `SavingsAccount` | **created:** if `is_emergency_fund`. **updated:** if `current_balance` or `is_emergency_fund` changed AND account is/was emergency fund. **deleted:** if `is_emergency_fund` |
| `InvestmentAccountRiskObserver` | `app/Observers/InvestmentAccountRiskObserver.php` | `InvestmentAccount` | **created:** always. **updated:** if `current_value` changed. **deleted:** always |
| `DCPensionRiskObserver` | `app/Observers/DCPensionRiskObserver.php` | `DCPension` | **created:** always. **updated:** if `current_fund_value` changed. **deleted:** always |
| `PropertyRiskObserver` | `app/Observers/PropertyRiskObserver.php` | `Property` | **created:** always. **updated:** if `current_value`, `purchase_price`, or `ownership_percentage` changed. **deleted:** always |

**Note:** `PropertyRiskObserver` does NOT extend `RiskRecalculationObserver` -- it implements its own debounce logic inline (identical behaviour).

### 5.4 Observer Registration

**File:** `app/Providers/EventServiceProvider.php`

```php
protected $observers = [
    User::class => [UserRiskObserver::class],
    FamilyMember::class => [FamilyMemberRiskObserver::class],
    SavingsAccount::class => [SavingsAccountRiskObserver::class],
    InvestmentAccount::class => [InvestmentAccountRiskObserver::class],
    DCPension::class => [DCPensionRiskObserver::class],
    Property::class => [PropertyRiskObserver::class],
];
```

### 5.5 Recalculation Job

**File:** `app/Jobs/RecalculateRiskProfileJob.php`

| Setting | Value |
|---------|-------|
| Queue | Default (ShouldQueue) |
| Tries | 1 |
| Timeout | 30 seconds |
| Delay | 5 seconds |
| Unique ID | `risk_recalc_{$userId}` |

**Process:**
1. Calls `RiskPreferenceService::calculateAndSetRiskLevel($userId)`
2. Gets the new `risk_level`
3. **Backfills** investment accounts with `NULL risk_preference`: `UPDATE investment_accounts SET risk_preference = :level WHERE user_id = :uid AND risk_preference IS NULL`
4. **Backfills** DC pensions with `NULL risk_preference`: same pattern
5. Logs all actions (info level)
6. Catches and logs exceptions (error level) without re-throwing

---

## 6. Risk Preference Service

**File:** `app/Services/Risk/RiskPreferenceService.php` (350 lines)

Central service managing all risk profile operations. Holds risk level configuration data as private arrays.

### 6.1 Key Methods

| Method | Purpose |
|--------|---------|
| `getAvailableRiskLevels()` | Returns all 5 levels with full config (for UI display) |
| `setMainRiskLevel(userId, riskLevel)` | Self-select: saves to DB, `is_self_assessed = true`, clears cache |
| `calculateAndSetRiskLevel(userId)` | Auto-calculate: invokes `AutoRiskCalculator`, saves to DB, `is_self_assessed = false`, clears cache |
| `getRiskProfile(userId)` | Returns saved profile BUT always recalculates `factor_breakdown` live via `AutoRiskCalculator` |
| `getMainRiskLevel(userId)` | **Cached** (1 hour) lookup of user's `risk_level` |
| `getAssetAllocation(riskLevel)` | Returns `{equities, bonds, cash, alternatives}` percentages |
| `getReturnParameters(riskLevel)` | Returns `{expected_return_min, max, typical, volatility}` for projections |
| `getAllowedProductRiskLevels(userId)` | Returns ALL 5 levels (no restriction) |
| `getAllowedProductRiskLevelsWithConfig(userId)` | Returns all levels with config + `is_main_level` flag |
| `validateProductRiskLevel(userId, riskLevel)` | Validates against allowed levels (always true currently) |
| `isCustomRiskLevel(userId, productRiskLevel)` | Checks if product level differs from user's main level |
| `getRiskLevelConfig(riskLevel)` | Full config for a specific level |
| `getRiskLevelNumeric(riskLevel)` | String -> numeric (1-5) |
| `mapLegacyTolerance(tolerance)` | Legacy 3-level -> new 5-level mapping |
| `clearUserCache(userId)` | Clears `user_risk_level_{$userId}` and `risk_profile_{$userId}` cache keys |

### 6.2 Caching Strategy

| Cache Key | TTL | What |
|-----------|-----|------|
| `user_risk_level_{$userId}` | 1 hour | User's main `risk_level` string |
| `risk_profile_{$userId}` | - | Cleared but not currently used for caching reads |
| `risk_recalc_pending_{$userId}` | 5 seconds | Debounce flag for recalculation jobs |

Cache is cleared on both `setMainRiskLevel()` and `calculateAndSetRiskLevel()`.

### 6.3 Important Behaviour: Live Factor Recalculation

When `getRiskProfile()` is called, it:
1. Reads the saved `risk_level` and `is_self_assessed` from the database
2. **Recalculates** `factor_breakdown` live from `AutoRiskCalculator` (not from the stored JSON)
3. Returns the fresh factor data alongside the stored risk level

This means the stored `factor_breakdown` JSON is primarily for audit/historical purposes, while the API always returns current values.

---

## 7. Controller & API Routes

### 7.1 Controller

**File:** `app/Http/Controllers/Api/RiskPreferenceController.php` (238 lines)

Uses `SanitizedErrorResponse` trait for error handling.

### 7.2 API Routes

All routes are under `/api/investment/risk/` and require `auth:sanctum`.

**File:** `routes/api.php` (within the investment prefix group)

| Method | Endpoint | Controller Method | Purpose |
|--------|----------|-------------------|---------|
| GET | `/api/investment/risk/levels` | `getLevels()` | All 5 risk level configs with allocations and returns |
| GET | `/api/investment/risk/profile` | `getProfile()` | User's profile (auto-calculates if none exists) |
| POST | `/api/investment/risk/profile` | `setProfile()` | Self-select risk level |
| POST | `/api/investment/risk/recalculate` | `recalculate()` | Force recalculate from 7 financial factors |
| GET | `/api/investment/risk/allowed-levels` | `getAllowedLevels()` | Allowed per-product override levels |
| POST | `/api/investment/risk/validate-product-level` | `validateProductLevel()` | Validate a product-level override |
| GET | `/api/investment/risk/config/{level}` | `getRiskConfig()` | Config for a specific level |

### 7.3 Validation

**`setProfile` endpoint:**
```php
'risk_level' => 'required|string|in:low,lower_medium,medium,upper_medium,high'
```

**`validateProductLevel` endpoint:**
```php
'risk_level' => 'required|string|in:low,lower_medium,medium,upper_medium,high'
```

### 7.4 Auto-Calculate on First Access

The `getProfile()` endpoint auto-calculates a risk profile if none exists:
```php
if (! $profile) {
    $profile = $this->riskPreferenceService->calculateAndSetRiskLevel($user->id);
    // Returns with auto_calculated: true
}
```

### 7.5 Response Formats

**GET /api/investment/risk/profile response:**
```json
{
  "success": true,
  "data": {
    "risk_level": "medium",
    "risk_assessed_at": "2026-01-16T15:11:13+00:00",
    "is_self_assessed": false,
    "factor_breakdown": [
      {
        "factor": "capacity_for_loss",
        "display_name": "Capacity for Loss",
        "level": "medium",
        "value": "32.5%",
        "raw_value": 32.5,
        "description": "32.5% of your net worth is in investments/pensions...",
        "icon": "shield",
        "components": {
          "investments_total": 150000,
          "pensions_total": 200000,
          "net_worth": 1075000
        }
      }
      // ... 6 more factors
    ],
    "config": {
      "key": "medium",
      "level_numeric": 3,
      "display_name": "Medium",
      "short_description": "Balanced approach accepting moderate volatility.",
      "full_description": "...",
      "asset_allocation": {"equities": 50, "bonds": 35, "cash": 10, "alternatives": 5},
      "expected_returns": {"min": 3.5, "max": 6.5, "typical": 5.0},
      "volatility_percent": 10.0,
      "colour_class": "blue"
    }
  },
  "auto_calculated": false
}
```

---

## 8. Frontend Architecture

### 8.1 Service Layer

**File:** `resources/js/services/riskService.js` (121 lines)

| Method | API Call | Purpose |
|--------|----------|---------|
| `getLevels()` | GET `/investment/risk/levels` | All 5 level configs |
| `getProfile()` | GET `/investment/risk/profile` | User's profile |
| `setProfile(data)` | POST `/investment/risk/profile` | Self-select risk level |
| `getAllowedLevels()` | GET `/investment/risk/allowed-levels` | Allowed product overrides |
| `validateProductLevel(riskLevel)` | POST `/investment/risk/validate-product-level` | Validate product override |
| `getRiskConfig(level)` | GET `/investment/risk/config/{level}` | Specific level config |
| `recalculate()` | POST `/investment/risk/recalculate` | Force recalculation |
| `getDisplayName(level)` | - | Delegates to `designSystem.js` |
| `getRiskColor(level)` | - | Delegates to `designSystem.js` |
| `normalizeLegacyTolerance(tolerance)` | - | Delegates to `designSystem.js` |

### 8.2 Vuex Store Integration

**File:** `resources/js/store/modules/investment.js`

**State:** `riskProfile: null`

**Getters:**
- `riskLevel` -- From investment analysis `risk_metrics` (portfolio-level assessment)
- `mainRiskLevel` -- From `riskProfile.risk_level` (user's 5-level profile)
- `hasRiskProfile` -- Boolean: `!!state.riskProfile?.risk_level`
- `productsWithCustomRisk` -- Filtered accounts where `has_custom_risk === true`

**Mutations:**
- `setRiskProfile(state, profile)` -- Updates `state.riskProfile`

**Actions:**
- `saveRiskProfile({ commit, dispatch }, profileData)` -- Calls `investmentService.saveRiskProfile()`, then re-analyses portfolio

**Loading:** Risk profile is loaded alongside investment accounts when `fetchAccounts` dispatches. The response includes `risk_profile` in the payload.

### 8.3 Router Routes

**File:** `resources/js/router/index.js`

| Path | Component | Meta |
|------|-----------|------|
| `/risk-profile` | `RiskProfilePage` | `requiresAuth: true` |
| `/risk-profile/levels` | `RiskLevelsExplainedPage` | `requiresAuth: true` |
| `/risk-profile/factor/:factor` | `RiskFactorDetailPage` | `requiresAuth: true` |

**Navigation:** Accessible via Navbar user menu dropdown > "Risk Profile" (links to `/valuable-info?section=risk`).

### 8.4 Views

**RiskProfilePage.vue** (`resources/js/views/Risk/RiskProfilePage.vue`)
- On mount: calls `riskService.recalculate()` to ensure fresh data
- Displays: risk level circle with numeric value, description, 7-factor breakdown as card grid, asset allocation percentages, expected returns, products with custom risk settings, educational investment types accordion
- Links to: `/risk-profile/levels` and `/risk-profile/factor/{factor}`

**RiskFactorDetailPage.vue** (`resources/js/views/Risk/RiskFactorDetailPage.vue`)
- Shows detailed view for one of the 7 factors
- Custom layouts per factor type:
  - Formula display for: capacity_for_loss, time_horizon, emergency_cash, surplus_cash
  - Data table for: education, dependants, employment
- Shows threshold levels with "You are here" indicator
- Explains what would need to change to move the factor level

**RiskLevelsExplainedPage.vue** (`resources/js/views/Risk/RiskLevelsExplainedPage.vue`)
- Loads all 5 risk levels from API with full descriptions
- Highlights user's current level
- For each level: asset allocation bars, expected returns range, volatility, equities/bonds percentages

### 8.5 Components

| Component | File | Purpose |
|-----------|------|---------|
| **RiskProfileSummary** | `resources/js/components/Risk/RiskProfileSummary.vue` | Embeddable version of RiskProfilePage (used in ValuableInfo tab) |
| **FactorBreakdownCard** | `resources/js/components/Risk/FactorBreakdownCard.vue` | Card showing one factor with icon, formatted value, level badge, description |
| **InvestmentTypesAccordion** | `resources/js/components/Risk/InvestmentTypesAccordion.vue` | Educational accordion: Cash, Bonds, Property, Equities, Alternatives with descriptions |
| **CapacityForLossSection** | `resources/js/components/Risk/CapacityForLossSection.vue` | Visual spectrum showing capacity for loss with 4 coloured zones and marker |
| **TimeHorizonSection** | `resources/js/components/Risk/TimeHorizonSection.vue` | Interactive time horizon display with 5 options and risk matrix |
| **RiskFactorsPanel** | `resources/js/components/Risk/RiskFactorsPanel.vue` | Educational panel explaining 4 investment risks: value fall, capacity for loss, inflation, liquidity |
| **RiskBadge** | `resources/js/components/Shared/RiskBadge.vue` | Reusable badge for any risk level; handles legacy values; sizes: `xs`/`sm`/`md`/`lg`; shows ring indicator for custom risk |
| **RiskLevelSelector** | `resources/js/components/Shared/RiskLevelSelector.vue` | 5-button horizontal selector for choosing risk level; shows allocation/returns info; highlights current profile level; supports disabled states |

### 8.6 ValuableInfo Integration

**File:** `resources/js/views/ValuableInfo.vue`

The risk profile summary is embedded as a tab in the "Valuable Information" page:
- Tab IDs: `letter`, `will`, `income`, `expenditure`, `risk`
- Tab `risk` renders the `RiskProfileSummary` component

---

## 9. Risk in the Investment Module

### 9.1 Investment Projection Service

**File:** `app/Services/Investment/InvestmentProjectionService.php`

The most critical consumer of risk data. Uses risk levels to parameterise Monte Carlo simulations.

**Risk Level Resolution Hierarchy (per account):**
1. Account-specific `risk_preference` (custom override) -> source: `'custom'`
2. User's main `risk_level` from profile -> source: `'profile'`
3. Default `'medium'` -> source: `'default'`

**How risk feeds into projections:**
```php
$params = $this->riskPreferenceService->getReturnParameters($riskLevel);
// Returns: expected_return_min, expected_return_max, expected_return_typical, volatility
// These feed into Monte Carlo simulation parameters
```

**Portfolio-wide projections:** Calculates a **value-weighted** average of returns and volatility across all accounts, respecting each account's individual risk level.

**What-if scenarios:** `getAccountProjectionWithRiskOverride()` allows projecting an account with a hypothetical different risk level.

### 9.2 Portfolio Analyser

**File:** `app/Services/Investment/PortfolioAnalyzer.php`

- `calculatePortfolioRisk()` -- Determines portfolio risk from actual equity percentage in holdings
- `matchesRiskProfile()` -- Checks if actual allocation matches the target range for the user's risk profile

### 9.3 Asset Allocation Optimiser

Referenced by `InvestmentAgent`, uses `RiskProfile` to get target allocation:
```php
$targetAllocation = AssetAllocationOptimizer::getTargetAllocation($riskProfile);
```

### 9.4 Investment Agent

**File:** `app/Agents/InvestmentAgent.php`

- Loads user's `RiskProfile`
- Calculates `risk_metrics` using `PortfolioAnalyzer::calculatePortfolioRisk()`
- Gets target allocation from `AssetAllocationOptimizer`
- Calculates allocation deviation between actual and target
- **Recommendations generated:**
  - "Complete Your Risk Profile" -- if no profile exists
  - "Rebalance Portfolio" -- if allocation deviates significantly from risk profile target

### 9.5 Investment Account Resource

**File:** `app/Http/Resources/InvestmentAccountResource.php`

Returns `risk_preference` and `has_custom_risk` in API responses.

### 9.6 Frontend Investment Forms

**AccountForm.vue** (`resources/js/components/Investment/AccountForm.vue`)
- Passes `allowedRiskLevels` and `mainRiskLevel` to `StandardInvestmentFields`

**StandardInvestmentFields.vue** (`resources/js/components/Investment/StandardInvestmentFields.vue`)
- Shows `RiskLevelSelector` component (hidden during onboarding)
- Displays user's main risk profile level name
- Links to `/risk-profile` if no profile exists
- Binds `risk_preference` to the selector

### 9.7 Investment Plan - Risk Analysis Section

**File:** `resources/js/components/Investment/PlanSections/RiskAnalysisSection.vue`

Displays investment-specific risk analysis in the plan view:
- Current vs target risk score (out of 10)
- Risk alignment percentage
- Portfolio risk metrics: volatility, Sharpe ratio, max drawdown, Value at Risk
- Risk management recommendations with priority levels
- Risk tolerance profile display

---

## 10. Risk in the Goals Module

### 10.1 GoalRiskService

**File:** `app/Services/Goals/GoalRiskService.php` (357 lines)

Uses a **separate numeric 1-5 risk system** for goals (not the string-based main system):

| Numeric Level | Label | Expected Return | Volatility |
|---------------|-------|-----------------|------------|
| 1 | Conservative | 3% | 5% |
| 2 | Cautious | 4.5% | 8% |
| 3 | Balanced | 6% | 12% |
| 4 | Growth | 7.5% | 16% |
| 5 | Aggressive | 9% | 20% |

### 10.2 Risk Parameter Resolution

Goals can use either:
1. **Global risk profile** (`use_global_risk_profile = true`): maps user's `risk_level` string to numeric 1-5
2. **Goal-specific** (`use_global_risk_profile = false`): uses `goal.risk_preference` integer directly

Default: balanced (3) if no preference set. Clamped to 1-5 range.

### 10.3 Projection Calculations

The service provides:
- **Deterministic projection:** Future value formula with lump sum + regular contributions
- **Probability of success:** Log-normal approximation (Abramowitz & Stegun standard normal CDF)
- **Required contribution:** Solves for monthly contribution to reach target at expected return
- **Yearly projections:** Expected value + 95% confidence bands (exp(+-1.96 * volatility * sqrt(year)))

### 10.4 Recommendations

Based on probability of success:

| Probability | Status | Message |
|-------------|--------|---------|
| >=90% | `excellent` | Well on track |
| >=75% | `good` | Good chance, maintain contributions |
| >=50% | `moderate` | Consider increasing contribution by X/month |
| <50% | `at_risk` | Review target, timeline, or contributions |

### 10.5 Goals Agent

**File:** `app/Agents/GoalsAgent.php`

Injects `GoalRiskService` to calculate projections for each investment goal.

---

## 11. Risk in the Holistic/Coordination Module

### 11.1 HolisticPlanner

**File:** `app/Services/Coordination/HolisticPlanner.php`

The `assessOverallRisk()` method creates a **cross-module risk assessment** by scoring risk areas:

| Risk Area | Source | High Threshold | Medium Threshold |
|-----------|--------|----------------|------------------|
| Protection | Protection adequacy score | <50% | <75% |
| Emergency Fund | Emergency fund months | <3 months | <6 months |
| Retirement | Income gap | >10k gap | >5k gap |
| Investment | Risk warnings from analysis | Any warnings present | - |
| Inheritance Tax | IHT liability amount | - | >100k liability |

**Overall Risk Score:** `calculateOverallRiskScore()` averages 4 factors:
1. Protection adequacy inverse (100 - adequacy%)
2. Emergency fund inverse (100 - coverage%)
3. Retirement risk (based on income gap proportion)
4. IHT liability proportion (liability / net worth * 100)

**Score Labels:**

| Score | Label |
|-------|-------|
| >=70 | High Risk |
| >=50 | Moderate Risk |
| >=30 | Low Risk |
| <30 | Minimal Risk |

### 11.2 Frontend: RiskAssessment Component

**File:** `resources/js/components/Holistic/RiskAssessment.vue`

Displays holistic risk assessment with:
- Radial bar gauge showing overall risk score out of 100
- Colour-coded risk areas with severity badges (high/medium/low)
- Risk mitigation strategies list

---

## 12. Risk in Other Modules

### 12.1 Savings Module

**File:** `app/Agents/SavingsAgent.php`

- Evaluates liquidity `risk_level` based on emergency fund adequacy
- Generates recommendations if liquidity risk is "High" (e.g., build emergency fund)

### 12.2 Retirement Module

**File:** `app/Agents/RetirementAgent.php`

- Delegates to `PensionPortfolioAnalyzer` for pension fund risk metrics:
  - Alpha, Beta, Sharpe Ratio, Volatility, Max Drawdown, Value at Risk (VaR)
- DC pension `risk_preference` affects growth projection assumptions

### 12.3 Estate Planning Module

**File:** `app/Agents/EstateAgent.php`

- **Liquidity Risk:** Identified when liquid assets cannot cover IHT liability
- **Tax Risk:** 7-year wait period risk for gifting strategy (gifts within 7 years of death may still be taxed)
- Estate planning score deducted for high liquidity risk

---

## 13. Preview Data & Seeding

**File:** `database/seeders/PreviewUserSeeder.php`

The `createRiskProfiles()` method seeds risk profiles for preview personas:

| Data Point | Source |
|------------|--------|
| `risk_level` | `main_risk_level` from persona JSON |
| `risk_tolerance` | Mapped via `mapRiskLevelToTolerance()` |
| `capacity_for_loss_percent` | Mapped via `mapRiskLevelToCapacity()` |
| `time_horizon_years` | From persona data |
| `knowledge_level` | From persona data |
| `attitude_to_volatility` | From persona data |
| `is_self_assessed` | `false` |

**Mapping functions:**

```php
mapRiskLevelToTolerance():
  low -> cautious
  lower_medium -> cautious
  medium -> balanced
  upper_medium -> adventurous
  high -> adventurous

mapRiskLevelToCapacity():
  low -> 10
  lower_medium -> 30
  medium -> 50
  upper_medium -> 70
  high -> 90
```

**Per-product seeding:** Investment accounts and DC pensions are seeded with `risk_preference` and `has_custom_risk` from persona JSON data. Goals are seeded with `risk_preference` (integer) and `use_global_risk_profile`.

**Persona JSON files:** Located in `resources/js/data/personas/` (6 files, one per persona).

---

## 14. Legacy System & Migration

### 14.1 Original 3-Level System

The original risk system used:
- `risk_tolerance` enum: `cautious`, `balanced`, `adventurous`
- Manual input fields: `capacity_for_loss_percent`, `time_horizon_years`, `knowledge_level`, `attitude_to_volatility`
- Stored via `StoreRiskProfileRequest` form request

### 14.2 Migration to 5-Level System

1. Added `risk_level` enum column (5 levels)
2. Made all legacy columns nullable
3. Added `factor_breakdown` JSON column
4. Added per-product `risk_preference` to investment_accounts and dc_pensions
5. Backend mapping: `mapLegacyTolerance()` converts old -> new
6. Frontend mapping: `normalizeRiskLevel()` in designSystem.js handles both systems
7. `RiskBadge` component handles both legacy and new values transparently

### 14.3 Legacy Form Request

**File:** `app/Http/Requests/Investment/StoreRiskProfileRequest.php`

Still exists but unused by the current controller flow (which uses inline `Validator::make`).

Validates: `risk_tolerance` (enum), `capacity_for_loss_percent` (0-100), `time_horizon_years` (1-50), `knowledge_level` (enum), `attitude_to_volatility`, `esg_preference`.

---

## 15. Data Flow Summary

### 15.1 Auto-Calculation Flow

```
1. User updates income on profile page
2. UserProfileController saves to User model
3. UserRiskObserver::updated() fires
4. Detects 'annual_employment_income' in relevant fields
5. dispatchRecalculation(userId, 'user_updated')
6. Check debounce: Cache::has("risk_recalc_pending_42") -> false
7. Cache::put("risk_recalc_pending_42", true, 5)
8. RecalculateRiskProfileJob::dispatch(42, 'user_updated')->delay(5s)
9. [5 seconds later, job executes]
10. RiskPreferenceService::calculateAndSetRiskLevel(42)
11. AutoRiskCalculator evaluates all 7 factors
12. determineFinalLevel() picks mode -> "medium"
13. RiskProfile updated: risk_level=medium, factor_breakdown=[...], is_self_assessed=false
14. Backfill: UPDATE investment_accounts SET risk_preference='medium' WHERE user_id=42 AND risk_preference IS NULL
15. Cache cleared: user_risk_level_42, risk_profile_42
```

### 15.2 Self-Select Flow

```
1. User visits /risk-profile
2. Frontend calls POST /api/investment/risk/recalculate (fresh data)
3. User sees auto-calculated level and factors
4. User clicks different level on RiskLevelSelector
5. Frontend calls POST /api/investment/risk/profile { risk_level: 'upper_medium' }
6. RiskPreferenceService::setMainRiskLevel(42, 'upper_medium')
7. RiskProfile updated: risk_level=upper_medium, is_self_assessed=true
8. Cache cleared
9. Frontend receives updated profile with config
```

### 15.3 Investment Projection Flow

```
1. InvestmentProjectionService resolves risk per account:
   a. Account has risk_preference 'high'? -> Use 'high'
   b. No override? User's main risk_level 'medium'? -> Use 'medium'
   c. No profile? -> Use 'medium' (default)
2. getReturnParameters('medium') -> { typical: 5.0%, volatility: 10% }
3. Monte Carlo simulation with those parameters
4. Portfolio-wide: value-weighted average of all accounts' params
5. Returns projections at 1, 3, 5, 10, 20, 30 year intervals
```

### 15.4 Goal Projection Flow

```
1. Goal has use_global_risk_profile = true
2. GoalRiskService loads user's RiskProfile
3. Maps risk_level string to numeric (e.g., 'medium' -> 3)
4. Gets params: { expected_return: 6%, volatility: 12% }
5. Calculates: deterministic projection, probability of success, required contributions
6. Returns recommendation based on probability
```

---

## 16. Design System Constants

**File:** `resources/js/constants/designSystem.js` (lines 133-285)

### 16.1 Risk Colours

| Level | Background | Background Light | Border | Text |
|-------|-----------|-----------------|--------|------|
| `low` | `#EAB308` (yellow) | `#FEF9C3` | `#EAB308` | `#854D0E` |
| `lower_medium` | `#EC4899` (pink) | `#FCE7F3` | `#EC4899` | `#9D174D` |
| `medium` | `#22C55E` (green) | `#DCFCE7` | `#22C55E` | `#166534` |
| `upper_medium` | `#14B8A6` (teal) | `#CCFBF1` | `#14B8A6` | `#115E59` |
| `high` | `#3B82F6` (blue) | `#DBEAFE` | `#3B82F6` | `#1E40AF` |

### 16.2 Tailwind Classes

Each level has: `bg`, `text`, `border`, and `combined` (bg + text + border) Tailwind class strings.

### 16.3 Exported Helpers

| Function | Purpose |
|----------|---------|
| `getRiskClasses(level)` | Returns Tailwind classes, normalises legacy values |
| `getRiskDisplayName(level)` | Returns display name for any level (legacy or new) |
| `normalizeRiskLevel(level)` | Normalises legacy values to new system |

### 16.4 Display Names

| Key | Display Name |
|-----|-------------|
| `low` | Low |
| `lower_medium` | Lower-Medium |
| `medium` | Medium |
| `upper_medium` | Upper-Medium |
| `high` | High |
| `cautious` (legacy) | Cautious |
| `balanced` (legacy) | Balanced |
| `adventurous` (legacy) | Adventurous |

### 16.5 Abbreviated Labels

`Low`, `L-Med`, `Med`, `U-Med`, `High`

---

## 17. Vulnerabilities & Concerns

### HIGH Priority

**1. Factor Weighting -- All Factors Equal**
- **Issue:** All 7 factors carry equal weight in the mode calculation. Education level (which has only 2 outcomes) has the same influence as capacity for loss (which has 4 outcomes). A PhD-holding billionaire with no dependants but who is retired could be rated `lower_medium` if enough factors cluster there.
- **Risk:** Potential misalignment between actual risk capacity and calculated risk level.
- **Recommendation:** Consider weighted scoring or giving capacity-for-loss/time-horizon more influence.

**2. No Factor Reaches `low` Easily**
- **Issue:** Only Factor 1 (Capacity for Loss) can output `low` risk level (when >75% of net worth is at risk). No other factor ever outputs `low`. The `low` level is extremely unlikely to be selected as the mode.
- **Risk:** Users who should be at `low` risk may never get that recommendation.
- **Recommendation:** Add `low` outputs to other factors (e.g., 0 time horizon + retired, deficit + no emergency fund).

**3. Mode Tie-Breaking Towards Lower Risk**
- **Issue:** When two levels tie for highest count, the lower-risk level always wins. This could be overly conservative for users whose tie is between `medium` and `upper_medium`.
- **Risk:** Systematic bias towards lower risk levels.
- **Note:** This is arguably a feature (safety-first), not a bug.

### MEDIUM Priority

**4. Goals Use Separate Numeric Risk System**
- **Issue:** Goals use integers 1-5 with different labels (Conservative, Cautious, Balanced, Growth, Aggressive) and different return parameters than the main system (Low, Lower-Medium, Medium, Upper-Medium, High). When `use_global_risk_profile = true`, the `risk_level` string is used as-is but the Goal system expects an integer.
- **Risk:** Mapping ambiguity between the two systems. `GoalRiskService` line 33: `$riskLevel = $globalRiskProfile->risk_level ?? 3` -- this assigns a string to what's expected to be an integer, but PHP's loose comparison in `max(1, min(5, $riskLevel))` may handle it inconsistently.
- **Recommendation:** Add explicit mapping from 5-level strings to 1-5 integers in `GoalRiskService`.

**5. Education Factor Oversimplification**
- **Issue:** Only binary: degree vs no degree. Does not account for financial literacy specifically, or professional qualifications (CFA, financial adviser, etc.).
- **Risk:** A plumber with 20 years of investment experience gets `lower_medium`, while a fresh graduate with zero financial knowledge gets `medium`.
- **Recommendation:** Consider using `knowledge_level` (novice/intermediate/experienced) from the risk profile instead.

**6. PropertyRiskObserver Doesn't Extend Base**
- **File:** `app/Observers/PropertyRiskObserver.php`
- **Issue:** Does not extend `RiskRecalculationObserver` like all other observers. Instead implements its own inline debounce. Functionally identical but inconsistent.
- **Risk:** Maintenance burden -- if base debounce logic changes, PropertyRiskObserver won't inherit it.
- **Recommendation:** Refactor to extend `RiskRecalculationObserver`.

### LOW Priority

**7. Surplus Cash Threshold of 500 is Hardcoded**
- **Issue:** The 500/month surplus threshold is a fixed value regardless of the user's income level. For high earners, 500 surplus is very low; for low earners, it's significant.
- **Risk:** Inaccurate factor for users at income extremes.
- **Recommendation:** Consider using a percentage of income instead.

**8. No Rate Limiting on Recalculate Endpoint**
- **Issue:** `POST /api/investment/risk/recalculate` has no per-route throttle. The frontend calls this on every visit to `/risk-profile`.
- **Risk:** Performance concern if hit repeatedly (each call runs 7 database queries + net worth calculation).
- **Recommendation:** Add throttle or cache the result.

**9. Stale Cache After Self-Select**
- **Issue:** After `setMainRiskLevel()`, the `factor_breakdown` in the database is NOT updated (only `risk_level` and `is_self_assessed`). If the user self-selects and then calls `getProfile()`, the factor breakdown is recalculated live (correct), but the stored JSON may be stale.
- **Risk:** No functional impact (API always recalculates live), but the stored JSON is misleading for direct database queries.

**10. No Spouse Risk Profile**
- **Issue:** The risk system only profiles the primary user, not their spouse. Joint investment decisions may require understanding both partners' risk profiles.
- **Recommendation:** Future enhancement.

---

## 18. Recommended Improvements

### 18.1 Critical

1. **Fix GoalRiskService string-to-integer mapping** -- Add explicit mapping from 5-level risk_level strings to integer 1-5 when `use_global_risk_profile` is true
2. **Add `low` outputs to more factors** -- Currently only capacity_for_loss can output `low`, making it nearly impossible to auto-calculate as `low` risk

### 18.2 Important

3. **Consider weighted factor scoring** -- Give capacity_for_loss and time_horizon more influence than education level
4. **Use financial literacy instead of education** -- Factor 3 should use `knowledge_level` (novice/intermediate/experienced) rather than formal education
5. **Make surplus threshold relative** -- Use percentage of income rather than hardcoded 500/month
6. **Add throttle to recalculate endpoint** -- Prevent performance issues from repeated calls
7. **Standardise PropertyRiskObserver** -- Refactor to extend `RiskRecalculationObserver` base class

### 18.3 Nice to Have

8. **Spouse risk profile** -- Allow both partners to have independent risk profiles
9. **Risk questionnaire** -- Add optional subjective risk attitude questionnaire to complement the data-driven approach
10. **Historical risk tracking** -- Store risk profile snapshots over time to show how risk profile evolves
11. **Risk alerts** -- Notify users when their auto-calculated risk level changes significantly
12. **Unify goal risk system** -- Align the goals numeric 1-5 system with the main string-based system

---

## 19. Complete File Inventory

### Backend (PHP)

| Category | File | Lines |
|----------|------|-------|
| **Model** | `app/Models/Investment/RiskProfile.php` | 46 |
| **Service: Calculator** | `app/Services/Risk/AutoRiskCalculator.php` | 467 |
| **Service: Preferences** | `app/Services/Risk/RiskPreferenceService.php` | 350 |
| **Service: Goals Risk** | `app/Services/Goals/GoalRiskService.php` | 357 |
| **Controller** | `app/Http/Controllers/Api/RiskPreferenceController.php` | 238 |
| **Job** | `app/Jobs/RecalculateRiskProfileJob.php` | 90 |
| **Observer: Base** | `app/Observers/RiskRecalculationObserver.php` | 38 |
| **Observer: User** | `app/Observers/UserRiskObserver.php` | 43 |
| **Observer: FamilyMember** | `app/Observers/FamilyMemberRiskObserver.php` | 38 |
| **Observer: SavingsAccount** | `app/Observers/SavingsAccountRiskObserver.php` | 39 |
| **Observer: InvestmentAccount** | `app/Observers/InvestmentAccountRiskObserver.php` | 34 |
| **Observer: DCPension** | `app/Observers/DCPensionRiskObserver.php` | 34 |
| **Observer: Property** | `app/Observers/PropertyRiskObserver.php` | 86 |
| **Event Provider** | `app/Providers/EventServiceProvider.php` | - |
| **Request (legacy)** | `app/Http/Requests/Investment/StoreRiskProfileRequest.php` | - |
| **Resource** | `app/Http/Resources/InvestmentAccountResource.php` | - |
| **Factory** | `database/factories/Investment/RiskProfileFactory.php` | - |
| **Projections** | `app/Services/Investment/InvestmentProjectionService.php` | - |
| **Portfolio Analyser** | `app/Services/Investment/PortfolioAnalyzer.php` | - |
| **Investment Agent** | `app/Agents/InvestmentAgent.php` | - |
| **Goals Agent** | `app/Agents/GoalsAgent.php` | - |
| **Holistic Planner** | `app/Services/Coordination/HolisticPlanner.php` | - |
| **Savings Agent** | `app/Agents/SavingsAgent.php` | - |
| **Retirement Agent** | `app/Agents/RetirementAgent.php` | - |
| **Estate Agent** | `app/Agents/EstateAgent.php` | - |
| **Seeder** | `database/seeders/PreviewUserSeeder.php` | - |

### Frontend (Vue/JS)

| Category | File |
|----------|------|
| **Service** | `resources/js/services/riskService.js` |
| **Design System** | `resources/js/constants/designSystem.js` |
| **Store** | `resources/js/store/modules/investment.js` |
| **Router** | `resources/js/router/index.js` |
| **View: Profile** | `resources/js/views/Risk/RiskProfilePage.vue` |
| **View: Levels** | `resources/js/views/Risk/RiskLevelsExplainedPage.vue` |
| **View: Factor** | `resources/js/views/Risk/RiskFactorDetailPage.vue` |
| **Component: Summary** | `resources/js/components/Risk/RiskProfileSummary.vue` |
| **Component: Factor Card** | `resources/js/components/Risk/FactorBreakdownCard.vue` |
| **Component: Inv Types** | `resources/js/components/Risk/InvestmentTypesAccordion.vue` |
| **Component: Capacity** | `resources/js/components/Risk/CapacityForLossSection.vue` |
| **Component: Time Horizon** | `resources/js/components/Risk/TimeHorizonSection.vue` |
| **Component: Factors Panel** | `resources/js/components/Risk/RiskFactorsPanel.vue` |
| **Shared: Badge** | `resources/js/components/Shared/RiskBadge.vue` |
| **Shared: Selector** | `resources/js/components/Shared/RiskLevelSelector.vue` |
| **Holistic: Assessment** | `resources/js/components/Holistic/RiskAssessment.vue` |
| **Inv Plan: Analysis** | `resources/js/components/Investment/PlanSections/RiskAnalysisSection.vue` |
| **Account Form** | `resources/js/components/Investment/AccountForm.vue` |
| **Standard Fields** | `resources/js/components/Investment/StandardInvestmentFields.vue` |
| **Navbar** | `resources/js/components/Navbar.vue` |
| **ValuableInfo** | `resources/js/views/ValuableInfo.vue` |

### Database Migrations

| File | Purpose |
|------|---------|
| Original `create_risk_profiles_table` | Base table |
| `add_risk_level_to_risk_profiles_table` | Added 5-level enum |
| `add_risk_preference_to_investment_accounts_table` | Per-product override |
| `add_risk_preference_to_dc_pensions_table` | Per-pension override |
| `2026_01_03_154132_make_risk_profile_columns_nullable.php` | Made legacy columns nullable |
| `2026_01_16_151113_add_factor_breakdown_to_risk_profiles.php` | Added factor_breakdown JSON |
| `2026_01_24_134257_make_factor_breakdown_nullable_on_risk_profiles.php` | Made factor_breakdown nullable |

### Persona Data

| File |
|------|
| `resources/js/data/personas/young_family.json` |
| `resources/js/data/personas/peak_earners.json` |
| `resources/js/data/personas/widow.json` |
| `resources/js/data/personas/entrepreneur.json` |
| `resources/js/data/personas/young_saver.json` |
| `resources/js/data/personas/retired_couple.json` |
