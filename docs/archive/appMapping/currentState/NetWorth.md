# Net Worth Module - Current State Documentation

**Last Updated:** 2026-02-19
**Module Version:** Part of Fynla v0.7.0
**Status:** Fully functional as the central aggregation layer pulling data from all other modules (Property, Savings, Investment, Retirement, Estate, Business Interests, Chattels) to present a unified wealth picture with cross-module cache invalidation, joint ownership support, and spouse net worth display

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
18. [Cross-Module Asset Aggregation System](#18-cross-module-asset-aggregation-system)

---

## 1. System Overview

The Net Worth module is the central aggregation layer of Fynla. It does not represent a single asset class but instead pulls data from every other financial module to present a unified wealth picture. It directly owns three asset types (Business Interests, Chattels, and the NetWorthStatement snapshot model) while aggregating Property, Savings, Investment, Pension, and Liability data from their respective modules via the `CrossModuleAssetAggregator` service and direct model queries.

**Important architectural distinction:** Like Property, Net Worth does NOT have a dedicated Agent. It uses a direct Controller to Service pattern. The module is purely an aggregation and display layer. Net worth data IS consumed by other agents (EstateAgent via `EstateAssetAggregatorService`, CoordinatingAgent indirectly via `HolisticPlanner`) but no `NetWorthAgent` exists to generate net-worth-specific insights or recommendations.

**Frontend layout:** The module uses a sidebar-based layout with `NetWorthDashboard.vue` as the shell component. The sidebar provides navigation to each asset category (Wealth Summary, Retirement, Property, Investments, Cash, Business Interests, Personal Valuables) and loads the corresponding sub-route view in the main content area. Properties and Mortgages are managed within this module's frontend (nested under `/net-worth/property`) but have their own dedicated controllers.

### Architecture Flow

```
NetWorthDashboard.vue (sidebar shell + router-view)
  -> NetWorthWealthSummary.vue (WealthSummary table + AssetAllocationDonut charts)
  -> NetWorthOverview.vue (asset category cards with item previews)
  -> PropertyList.vue / BusinessInterestsList.vue / ChattelsList.vue (CRUD views)
  -> PensionList.vue / InvestmentList.vue / CashOverview.vue (read-only aggregation views)
  -> netWorthService.js / propertyService.js / mortgageService.js (API wrappers)
  -> Vuex netWorth.js store (aggregation state + property/mortgage management state)
  -> NetWorthController / BusinessInterestController / ChattelController / PropertyController / MortgageController
  -> NetWorthService / CrossModuleAssetAggregator / BusinessInterestService / ChattelCGTService / PropertyService / PropertyTaxService / MortgageService
  -> BusinessInterest / Chattel / NetWorthStatement / Property / Mortgage / SavingsAccount / InvestmentAccount / DCPension / DBPension / StatePension / Liability
  -> DB (net_worth_statements + business_interests + chattels + properties + mortgages + savings_accounts + investment_accounts + dc_pensions + db_pensions + state_pensions + liabilities)
```

### File Count Summary

| Category | Count |
|---|---|
| Models (directly owned) | 3 (BusinessInterest, Chattel, NetWorthStatement) |
| Models (aggregated from) | 7+ (Property, Mortgage, SavingsAccount, InvestmentAccount, DCPension, DBPension, StatePension, Liability) |
| Services | 7 (NetWorthService, CrossModuleAssetAggregator, BusinessInterestService, ChattelCGTService, PropertyService, PropertyTaxService, MortgageService) |
| Controllers | 5 (NetWorthController, BusinessInterestController, ChattelController, PropertyController, MortgageController) |
| Agents | 0 (no NetWorthAgent) |
| Validation Requests | 8 (4 business interest + 4 property/mortgage) |
| API Resources | 4 (BusinessInterestResource, ChattelResource, PropertyResource, MortgageResource) |
| Shared Traits | 1 (CalculatesOwnershipShare) |
| Vue Components | ~20+ |
| API Services (JS) | 3 (netWorthService.js, propertyService.js, mortgageService.js) |
| API Endpoints | ~40+ across all sub-modules |

---

## 2. Database Schema

### 2.1 `net_worth_statements`

Snapshot table for historical net worth storage. **Not actively used for live calculations** -- the `NetWorthService` calculates on demand and caches in Redis/file. This table exists for future historical snapshot functionality.

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK, AUTO_INCREMENT |
| `user_id` | bigint unsigned | NOT NULL, FK -> users(id) CASCADE |
| `statement_date` | date | NOT NULL |
| `total_assets` | decimal(15,2) | NOT NULL DEFAULT 0.00 |
| `total_liabilities` | decimal(15,2) | NOT NULL DEFAULT 0.00 |
| `net_worth` | decimal(15,2) | NOT NULL DEFAULT 0.00 |
| `created_at` / `updated_at` | timestamps | |

**Indexes:** `user_id`, `(user_id, statement_date)` composite

### 2.2 `business_interests`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK, AUTO_INCREMENT |
| `user_id` | bigint unsigned | NOT NULL, FK -> users(id) CASCADE |
| `joint_owner_id` | bigint | NULL (no FK constraint) |
| `household_id` | bigint unsigned | NULL, FK -> households(id) SET NULL |
| `trust_id` | bigint unsigned | NULL, FK -> trusts(id) SET NULL |
| `business_name` | varchar(255) | NOT NULL |
| `company_number` | varchar(255) | NULL, Companies House registration |
| `business_type` | enum | `sole_trader`, `partnership`, `limited_company`, `llp`, `other`; NOT NULL |
| `ownership_type` | enum | `individual`, `joint`, `trust`; NOT NULL DEFAULT `individual` |
| `ownership_percentage` | decimal(5,2) | NOT NULL DEFAULT 100.00 |
| `country` | varchar(255) | NOT NULL DEFAULT 'United Kingdom' |
| `vat_registered` | tinyint(1) | NOT NULL DEFAULT 0 |
| `vat_number` | varchar(255) | NULL |
| `utr_number` | varchar(255) | NULL, Unique Tax Reference |
| `tax_year_end` | date | NULL, Company financial year-end |
| `employee_count` | int unsigned | NOT NULL DEFAULT 0 |
| `paye_reference` | varchar(255) | NULL |
| `trading_status` | enum | `trading`, `dormant`, `pre_trading`; NOT NULL DEFAULT `trading` |
| `acquisition_date` | date | NULL, used for BADR eligibility |
| `acquisition_cost` | decimal(15,2) | NULL, original cost basis |
| `bpr_eligible` | tinyint(1) | NOT NULL DEFAULT 0, Business Property Relief for IHT |
| `industry_sector` | varchar(255) | NULL |
| `current_valuation` | decimal(15,2) | NOT NULL (stores FULL business value) |
| `valuation_date` | date | NOT NULL |
| `valuation_method` | varchar(255) | NULL |
| `annual_revenue` | decimal(15,2) | NULL |
| `annual_profit` | decimal(15,2) | NULL |
| `annual_dividend_income` | decimal(15,2) | NULL |
| `description` | text | NULL |
| `notes` | text | NULL |
| `created_at` / `updated_at` | timestamps | |

**Indexes:** `user_id`, `household_id`, `trust_id`, `business_type`, `joint_owner_id`, `ownership_type`, `trading_status`

**Single-Record Pattern:** One database record stores the FULL business valuation in `current_valuation`. `user_id` = primary owner (edit/delete rights), `joint_owner_id` = secondary owner (view access only), `ownership_percentage` = primary owner's share. **Key difference from other assets:** For individual ownership, `ownership_percentage` STILL applies (represents shareholding percentage). Other asset types treat individual ownership as 100%.

### 2.3 `chattels`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK, AUTO_INCREMENT |
| `user_id` | bigint unsigned | NOT NULL, FK -> users(id) CASCADE |
| `joint_owner_id` | bigint | NULL (no FK constraint) |
| `household_id` | bigint unsigned | NULL, FK -> households(id) SET NULL |
| `trust_id` | bigint unsigned | NULL, FK -> trusts(id) SET NULL |
| `chattel_type` | enum | `vehicle`, `art`, `antique`, `jewelry`, `collectible`, `other`; NOT NULL |
| `name` | varchar(255) | NOT NULL |
| `description` | text | NULL |
| `ownership_type` | enum | `individual`, `joint`, `trust`; NOT NULL DEFAULT `individual` |
| `country` | varchar(255) | NOT NULL DEFAULT 'United Kingdom' |
| `ownership_percentage` | decimal(5,2) | NOT NULL DEFAULT 100.00 |
| `purchase_price` | decimal(15,2) | NULL |
| `purchase_date` | date | NULL |
| `current_value` | decimal(15,2) | NOT NULL (stores FULL value) |
| `valuation_date` | date | NOT NULL |
| `make` | varchar(255) | NULL, vehicle make |
| `model` | varchar(255) | NULL, vehicle model |
| `year` | year | NULL, vehicle year |
| `registration_number` | varchar(255) | NULL |
| `notes` | text | NULL |
| `created_at` / `updated_at` | timestamps | |

**Indexes:** `user_id`, `household_id`, `trust_id`, `chattel_type`, `joint_owner_id`, `ownership_type`

**Single-Record Pattern:** One database record stores the FULL chattel value in `current_value`. Same primary/joint owner pattern as other assets.

---

## 3. Models

### 3.1 BusinessInterest (`app/Models/BusinessInterest.php`, 99 lines)

**Namespace:** `App\Models`
**Traits:** `Auditable`, `HasFactory`, `HasJointOwnership`

**Relationships:**
- `user()` - belongsTo User
- `jointOwner()` - belongsTo User (via `joint_owner_id`)
- `household()` - belongsTo Household
- `trust()` - belongsTo Trust

**Casts:**
- `valuation_date` -> date
- `current_valuation` -> decimal:2
- `ownership_percentage` -> decimal:2
- `annual_revenue` / `annual_profit` / `annual_dividend_income` -> decimal:2
- `vat_registered` / `bpr_eligible` -> boolean
- `tax_year_end` -> date
- `employee_count` -> integer
- `acquisition_date` -> date
- `acquisition_cost` -> decimal:2

**Key difference from other models:** For individual ownership, `ownership_percentage` still applies (represents shareholding). In all other asset models, individual ownership is treated as 100% regardless of the stored percentage.

### 3.2 Chattel (`app/Models/Chattel.php`, 81 lines)

**Namespace:** `App\Models`
**Traits:** `Auditable`, `HasFactory`, `HasJointOwnership`

**Relationships:**
- `user()` - belongsTo User
- `jointOwner()` - belongsTo User (via `joint_owner_id`)
- `household()` - belongsTo Household
- `trust()` - belongsTo Trust

**Extra field:** `joint_owner_name` (free-text for non-registered users)

**Casts:**
- `purchase_date` / `valuation_date` -> date
- `purchase_price` / `current_value` / `ownership_percentage` -> decimal:2
- `year` -> integer

**CGT rules:** Vehicles are wasting assets (predictable life <= 50 years) and are always CGT exempt. Non-vehicle chattels with disposal proceeds below the chattel threshold (currently 6,000) are CGT exempt.

### 3.3 NetWorthStatement (`app/Models/Estate/NetWorthStatement.php`, 38 lines)

**Namespace:** `App\Models\Estate`
**Note:** Located in the Estate model namespace, not a standalone Net Worth namespace.

Simple snapshot model, NOT actively used for live calculations. The `NetWorthService` calculates net worth on demand and caches in Redis/file. This table exists for future historical snapshot storage.

**Fields:** `user_id`, `statement_date`, `total_assets`, `total_liabilities`, `net_worth`

---

## 4. Controllers

### 4.1 NetWorthController (`app/Http/Controllers/Api/NetWorthController.php`, 241 lines)

**Dependencies:** `NetWorthService`
**Traits:** `SanitizedErrorResponse`

| # | HTTP | Route | Method | Description |
|---|---|---|---|---|
| 1 | GET | `/net-worth/overview` | `getOverview` | Full net worth calculation with spouse data. If user has `spouse_id`, also calculates spouse net worth and attaches as `spouse_data` with camelCase keys: `totalAssets`, `totalLiabilities`, `netWorth`, `breakdown`, `liabilitiesBreakdown`, `hasDbPensions`. |
| 2 | GET | `/net-worth/breakdown` | `getBreakdown` | Asset breakdown with percentage per category. |
| 3 | GET | `/net-worth/trend` | `getTrend` | Trend data for 1-36 months. Query param: `months` (default 12). |
| 4 | GET | `/net-worth/assets-summary` | `getAssetsSummary` | Counts and totals per asset category. |
| 5 | GET | `/net-worth/assets-summary-detailed` | `getAssetsSummaryWithDetails` | Full item lists per category with individual items. |
| 6 | GET | `/net-worth/joint-assets` | `getJointAssets` | All jointly owned assets across all modules. |
| 7 | POST | `/net-worth/refresh` | `refresh` | Invalidate cache and recalculate net worth. |

**Spouse data handling in `getOverview`:** When the authenticated user has a `spouse_id`, the controller calls `calculateNetWorth()` for the spouse as well and includes the result as `spouse_data` in the response. The spouse data uses camelCase keys for frontend consistency.

### 4.2 BusinessInterestController (`app/Http/Controllers/Api/BusinessInterestController.php`, 358 lines)

**Dependencies:** `BusinessInterestService`
**Traits:** `CalculatesOwnershipShare`

| # | HTTP | Route | Method | Description |
|---|---|---|---|---|
| 1 | GET | `/business-interests` | `index` | All businesses where user is owner OR joint_owner. Adds computed fields: `user_share`, `full_value`, `is_primary_owner`, `is_shared`, `business_type_label`. |
| 2 | POST | `/business-interests` | `store` | Create business interest. Defaults: `ownership_type` = individual, `ownership_percentage` = 100, `trading_status` = trading, `country` = United Kingdom. Joint defaults to 50/50. |
| 3 | GET | `/business-interests/{id}` | `show` | Single business with full summary from service. Accessible by owner OR joint_owner. |
| 4 | PUT | `/business-interests/{id}` | `update` | Primary owner only. |
| 5 | DELETE | `/business-interests/{id}` | `destroy` | Primary owner only. |
| 6 | GET | `/business-interests/{id}/tax-deadlines` | `taxDeadlines` | Tax calendar deadlines based on business type. Owner OR joint_owner. |
| 7 | GET | `/business-interests/{id}/exit-calculation` | `exitCalculation` | CGT/BADR exit scenario calculation. Owner OR joint_owner. |

### 4.3 ChattelController (`app/Http/Controllers/Api/ChattelController.php`, 220 lines)

**Dependencies:** `ChattelCGTService`

| # | HTTP | Route | Method | Description |
|---|---|---|---|---|
| 1 | GET | `/chattels` | `index` | All chattels where user is owner OR joint_owner, ordered by value DESC. |
| 2 | POST | `/chattels` | `store` | Create chattel. |
| 3 | GET | `/chattels/{id}` | `show` | Single chattel with CGT exempt status from `wouldBeExempt()`. Accessible by owner OR joint_owner. |
| 4 | PUT | `/chattels/{id}` | `update` | Primary owner only. |
| 5 | DELETE | `/chattels/{id}` | `destroy` | Primary owner only. Returns 204 No Content. |
| 6 | POST | `/chattels/{id}/calculate-cgt` | `calculateCGT` | CGT calculation with `disposal_price` and `disposal_costs` inputs. |

### 4.4 PropertyController and MortgageController

Documented separately in `Property.md`. These controllers manage property and mortgage CRUD and are accessed via the Net Worth frontend routing (`/net-worth/property`).

---

## 5. Agent

**No NetWorthAgent exists.** Like Property, Net Worth uses a direct Controller to Service architecture. The module is purely an aggregation layer that collects data from other modules and presents a unified view.

Net worth data IS consumed by other agents:
- **EstateAgent** via `EstateAssetAggregatorService` -- pulls property, investment, and savings data for IHT calculations using the same `CrossModuleAssetAggregator` pattern
- **CoordinatingAgent** indirectly via `HolisticPlanner` -- references net worth figures for holistic financial planning

However, there is no dedicated agent to generate net-worth-specific insights, recommendations, or analysis. All net worth calculations are performed directly by `NetWorthService`.

---

## 6. Services

### 6.1 NetWorthService (`app/Services/NetWorthService.php`, 593 lines)

**Dependencies:** `CrossModuleAssetAggregator` (injected via constructor)
**Traits:** `CalculatesOwnershipShare`

The primary service for all net worth calculations. Aggregates data from `CrossModuleAssetAggregator` (property, investment, cash) and direct model queries (business interests, chattels, pensions, liabilities).

**Public Methods:**

| Method | Signature | Returns | Description |
|---|---|---|---|
| `calculateNetWorth` | `(User $user, ?Carbon $asOfDate)` | `array` | Primary calculation. Returns `total_assets`, `total_liabilities`, `net_worth`, `as_of_date`, `breakdown`, `has_db_pensions`, `liabilities_breakdown`. |
| `getNetWorthTrend` | `(User $user, int $months = 12)` | `array` | Returns `[{date, month, net_worth}]` for 1-36 months. Currently returns the same current figure for each point (no historical snapshots yet). |
| `getAssetBreakdown` | `(User $user)` | `array` | Returns breakdown with percentages per category. Each entry: `{value, percentage}`. |
| `getAssetsSummary` | `(User $user)` | `array` | Counts and totals per category. Pensions broken down: DC count, DB count, State count. |
| `getAssetsSummaryWithDetails` | `(User $user)` | `array` | Full item lists per category. See below for detail format. |
| `getJointAssets` | `(User $user)` | `array` | Joint/tenants-in-common assets across properties, investments, businesses, chattels. Note: savings joint query not yet implemented (returns empty with TODO comment). |
| `getCachedNetWorth` | `(User $user)` | `array` | Cache wrapper. Key: `net_worth:user_{id}:date_{Y-m-d}`. TTL: 1800 seconds (30 minutes). Calls `calculateNetWorth` on cache miss. |
| `invalidateCache` | `(int $userId)` | `void` | Forgets the current-day cache key. |

**`calculateNetWorth` breakdown structure:**

```php
[
    'total_assets' => float,
    'total_liabilities' => float,
    'net_worth' => float,
    'as_of_date' => string,
    'breakdown' => [
        'pensions' => float,    // DC pension fund values only
        'property' => float,    // User's share of property equity
        'investments' => float, // User's share of investment accounts
        'cash' => float,        // User's share of savings accounts
        'business' => float,    // User's share of business interests
        'chattels' => float,    // User's share of chattels
    ],
    'has_db_pensions' => bool,  // Flag for DB pension existence
    'liabilities_breakdown' => [
        'mortgages' => float,
        'loans' => float,
        'credit_cards' => float,
        'other' => float,
    ],
]
```

**`getAssetsSummaryWithDetails` item formats:**

| Category | Item Fields |
|---|---|
| Pensions (DC) | `fund_value` |
| Pensions (DB) | `capital_value` = `(accrued_annual_pension * 20) + lump_sum_entitlement` (display only) |
| Property | `address`, `type`, `value` |
| Investments | `provider`, `type`, `value` |
| Cash | `institution`, `type`, `balance`, `is_isa`, `is_emergency_fund` |
| Business | `name`, `type`, `user_share`, `full_value`, `revenue`, `profit` |
| Chattels | `name`, `type`, `user_share`, `full_value`, vehicle fields |

**Private Methods:**

| Method | Description |
|---|---|
| `calculateBusinessValue(userId)` | Queries `BusinessInterest` where user OR joint_owner, sums `calculateUserShare()` |
| `calculateChattelValue(userId)` | Same pattern for `Chattel` |
| `calculateLiabilitiesBreakdown(userId)` | Queries `Liability` table. Maps types to categories (see Section 16). Skips mortgage type (handled by `CrossModuleAssetAggregator`). |
| `calculatePensionBreakdown(userId)` | DC sum from `DCPension::sum('current_fund_value')`. DB existence check only. State Pension excluded. |

**What is included vs excluded in Net Worth:**

| Included | Excluded |
|---|---|
| Property (user's share) | DB Pensions (not accessible capital, flagged via `has_db_pensions`) |
| Savings/Cash (user's share) | State Pension (not capital) |
| Investments (user's share) | |
| DC Pensions (fund value) | |
| Business Interests (user's share) | |
| Chattels (user's share) | |
| Mortgages (liability, user's share) | |
| Loans / credit cards / other liabilities | |

### 6.2 CrossModuleAssetAggregator (`app/Services/CrossModuleAssetAggregator.php`, 277 lines)

**Traits:** `CalculatesOwnershipShare`

Single source of truth for Property, Investment, and Cash aggregation. Used by `NetWorthService`, `EstateAssetAggregatorService`, and potentially other consumers.

**Public Methods:**

| Method | Signature | Returns | Description |
|---|---|---|---|
| `getAllAssets` | `(int $userId)` | `Collection` | Standardized asset objects across all three types |
| `getPropertyAssets` | `(int $userId)` | `Collection` | Property assets with `is_iht_exempt` = false |
| `getInvestmentAssets` | `(int $userId)` | `Collection` | Investment assets with `is_iht_exempt` = false (ISAs are IHT taxable) |
| `getSavingsAssets` | `(int $userId)` | `Collection` | Savings assets with `is_iht_exempt` = false |
| `getAssetTotals` | `(int $userId)` | `array` | Returns `{property, investment, cash}` totals |
| `calculatePropertyTotal` | `(int $userId)` | `float` | Sum of user's share of all properties |
| `calculateInvestmentTotal` | `(int $userId)` | `float` | Sum of user's share of all investments |
| `calculateCashTotal` | `(int $userId)` | `float` | Sum of user's share of all savings accounts |
| `getMortgages` | `(int $userId)` | `Collection` | All mortgages where user is owner OR joint_owner |
| `calculateMortgageTotal` | `(int $userId)` | `float` | Sum of user's share of all mortgages |
| `getAssetBreakdown` | `(int $userId)` | `array` | Counts and totals for property, investment, cash, mortgages |

**Query pattern for ALL asset types:**

```php
Model::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get()
```

**Standardized asset format (from `getAllAssets()`):**

```php
[
    'asset_type' => 'property' | 'investment' | 'cash',
    'asset_name' => string,     // address / provider / institution
    'current_value' => float,   // User's share (after ownership calculation)
    'full_value' => float,      // Raw database value
    'ownership_type' => string, // From model
    'ownership_percentage' => float,
    'is_primary_owner' => bool,
    'is_shared' => bool,        // joint or tenants_in_common
    'is_iht_exempt' => false,   // Always false (ISAs are IHT taxable)
    'source_id' => int,         // Model ID
    'source_model' => string,   // Class name
]
```

### 6.3 BusinessInterestService (`app/Services/BusinessInterestService.php`, 512 lines)

**Dependencies:** `TaxConfigService`

| Method | Signature | Returns | Description |
|---|---|---|---|
| `getBusinessSummary` | `(BusinessInterest $business)` | `array` | Structured with sections: ownership, valuation, financials, tax_compliance, exit_planning |
| `calculateUserShare` | `(BusinessInterest $business, int $userId)` | `float` | Business-specific: individual ownership still applies ownership_percentage |
| `getTaxDeadlines` | `(BusinessInterest $business)` | `array` | Sorted deadline list by business type |
| `calculateExitScenario` | `(BusinessInterest $business, ?User $user)` | `array` | CGT/BADR exit scenario calculation |

**Tax Deadlines by Business Type:**

| Business Type | Deadlines |
|---|---|
| Sole Trader / Partnership | Self Assessment: 31 October (paper), 31 January (online), 31 July (Payment on Account) |
| Limited Company | Corporation Tax (9 months + 1 day), Annual Accounts (9 months), CT600 (12 months), Confirmation Statement (14 days) |
| VAT Registered | Quarterly returns (1 month + 7 days after quarter end) |
| Employers (PAYE) | Monthly PAYE/NIC (22nd of following month) |

**Exit Scenario Calculation (`calculateExitScenario`) returns:**

```php
[
    'sale_price' => float,
    'user_sale_proceeds' => float,
    'capital_gain' => float,
    'badr_eligible' => bool,
    'cgt_rate' => float,
    'cgt_due' => float,
    'post_tax_proceeds' => float,
    'bpr_eligible' => bool,
    'warnings' => array,
]
```

### 6.4 ChattelCGTService (`app/Services/ChattelCGTService.php`, 223 lines)

**Dependencies:** `TaxConfigService`
**Constants:** `CHATTEL_THRESHOLD = 6000`, `MARGINAL_RELIEF_MULTIPLIER = 5/3`

UK Chattel CGT Rules:
1. **Wasting assets** (predictable life <= 50 years): always exempt. Currently only vehicles qualify.
2. **Disposal proceeds <= 6,000**: exempt
3. **Marginal relief** for proceeds 6,000-15,000: max gain = `(proceeds - 6000) * 5/3`
4. **Annual exempt amount** from `TaxConfigService` (3,000 for 2025/26)
5. **Non-residential CGT rates**: Basic 10%, Higher 20%
6. **Loss restriction**: if proceeds < 6,000, loss calculated as if proceeds = 6,000

| Method | Signature | Returns | Description |
|---|---|---|---|
| `calculateCGT` | `(Chattel $chattel, float $disposalPrice, float $disposalCosts, User $user)` | `array` | Full CGT calculation with exemptions, marginal relief, and rates |
| `isWastingAsset` | `(Chattel $chattel)` | `bool` | Returns true only for vehicles |
| `wouldBeExempt` | `(Chattel $chattel, float $estimatedDisposalPrice)` | `array` | Preview of exempt status without full calculation |

### 6.5 PropertyService, PropertyTaxService, MortgageService

Documented separately in `Property.md`. These services handle property CRUD, SDLT/CGT/Section 24 tax calculations, and mortgage amortisation.

---

## 7. Validation Requests

### 7.1 StoreBusinessInterestRequest (`app/Http/Requests/StoreBusinessInterestRequest.php`, 107 lines)

All fields nullable. Key validation rules:
- `business_type`: in `sole_trader`, `partnership`, `limited_company`, `llp`, `other`
- `ownership_type`: in `individual`, `joint`, `trust`
- `trading_status`: in `trading`, `dormant`, `pre_trading`
- `joint_owner_id`: `exists:users`
- `ownership_percentage`: numeric, 0-100
- `employee_count`: integer, >= 0
- `description` / `notes`: max 2000 characters

### 7.2 UpdateBusinessInterestRequest

Same rules as `StoreBusinessInterestRequest` (all nullable for partial updates).

### 7.3 StoreChattelRequest (`app/Http/Requests/StoreChattelRequest.php`, 63 lines)

- `chattel_type`: in `vehicle`, `art`, `antique`, `jewelry`, `collectible`, `other`
- `ownership_type`: in `individual`, `joint`, `trust`
- `year`: integer, 1900 to current year + 1
- `registration_number`: max 20 characters
- `joint_owner_name`: free-text string
- `notes`: max 2000 characters

### 7.4 UpdateChattelRequest

Same rules as `StoreChattelRequest`.

### 7.5 Property/Mortgage Validation Requests

4 additional validation requests for property and mortgage CRUD -- documented in `Property.md`.

---

## 8. Vuex Store

**File:** `resources/js/store/modules/netWorth.js` (805 lines)
**Namespaced:** `netWorth`

The store manages BOTH the cross-module net worth aggregation state AND property/mortgage management state.

### State

```javascript
{
    // Net Worth Aggregation
    overview: {
        totalAssets: 0,
        totalLiabilities: 0,
        netWorth: 0,
        breakdown: {
            pensions: 0,
            property: 0,
            investments: 0,
            cash: 0,
            business: 0,
            chattels: 0,
        },
        liabilitiesBreakdown: {
            mortgages: 0,
            loans: 0,
            credit_cards: 0,
            other: 0,
        },
        asOfDate: null,
        hasDbPensions: false,
    },
    spouseOverview: null,       // Same structure as overview, populated for married users
    trend: [],                  // [{date, month, net_worth}]
    assetsSummary: {
        pensions: { count: 0, total_value: 0, breakdown: { dc: 0, db: 0, state: 0 } },
        property: { count: 0, total_value: 0 },
        investments: { count: 0, total_value: 0 },
        cash: { count: 0, total_value: 0 },
        business: { count: 0, total_value: 0 },
        chattels: { count: 0, total_value: 0 },
    },
    assetsSummaryDetailed: {},  // Same keys but with items[] arrays
    jointAssets: [],

    // Property Management
    properties: [],
    selectedProperty: null,
    mortgages: [],
    selectedMortgage: null,

    // UI State
    loading: false,
    error: null,
    isDetailView: false,
}
```

### Mutations (21)

| Mutation | Description |
|---|---|
| `SET_OVERVIEW` | Remaps snake_case API response to camelCase: `total_assets` -> `totalAssets`, `has_db_pensions` -> `hasDbPensions`, etc. |
| `SET_SPOUSE_OVERVIEW` | Sets spouse net worth data |
| `SET_TREND` | Sets trend data array |
| `SET_ASSETS_SUMMARY` | Sets asset counts and totals |
| `SET_ASSETS_SUMMARY_DETAILED` | Sets detailed item lists |
| `SET_JOINT_ASSETS` | Sets joint asset list |
| `SET_LOADING` / `SET_ERROR` / `CLEAR_ERROR` | UI state |
| `SET_DETAIL_VIEW` | Toggle detail view mode |
| `RESET_STATE` | Reset to initial state |
| `SET_PROPERTIES` / `SET_SELECTED_PROPERTY` / `ADD_PROPERTY` / `UPDATE_PROPERTY` / `REMOVE_PROPERTY` | Property CRUD state |
| `SET_MORTGAGES` / `SET_SELECTED_MORTGAGE` / `ADD_MORTGAGE` / `UPDATE_MORTGAGE` / `REMOVE_MORTGAGE` | Mortgage CRUD state |

### Actions (25+)

**Net Worth Aggregation Actions:**

| Action | API Call | Description |
|---|---|---|
| `fetchOverview` | GET `/overview` | Sets `overview` + `spouseOverview` |
| `fetchTrend` | GET `/trend?months=N` | Default 12 months |
| `fetchAssetsSummary` | GET `/assets-summary` | Category counts and totals |
| `fetchAssetsSummaryDetailed` | GET `/assets-summary-detailed` | Full item lists |
| `fetchJointAssets` | GET `/joint-assets` | Joint assets across modules |
| `refreshNetWorth` | POST `/refresh`, then GET `/overview`, then parallel `fetchAssetsSummary` + `fetchTrend` | Full cache invalidation and refresh chain |
| `loadAllData` | Parallel: `fetchOverview` + `fetchTrend` + `fetchAssetsSummary` | Initial page load |

**Property Management Actions:**

| Action | Description |
|---|---|
| `fetchProperties` | Get all properties |
| `fetchProperty(id)` | Get single property |
| `createProperty(data)` | Create property |
| `updateProperty({id, data})` | Update property |
| `deleteProperty(id)` | Delete property |

**Mortgage Management Actions:**

| Action | Description |
|---|---|
| `fetchPropertyMortgages(propertyId)` | Get mortgages for a property |
| `fetchMortgage(id)` | Get single mortgage |
| `createMortgage({propertyId, data})` | Create mortgage |
| `updateMortgage({id, data, propertyId})` | Update mortgage |
| `deleteMortgage({id, propertyId})` | Delete mortgage |

**Tax Calculation Actions (no state mutation - return data directly):**

| Action | Description |
|---|---|
| `calculateSDLT(data)` | Stamp Duty Land Tax calculation |
| `calculateCGT({propertyId, data})` | Capital Gains Tax calculation |
| `calculateRentalIncomeTax(propertyId)` | Section 24 rental income tax |
| `getAmortizationSchedule(mortgageId)` | Mortgage amortisation schedule |
| `calculateMortgagePayment(data)` | Mortgage payment calculation |

**Cross-Module Sync Action:**

| Action | Description |
|---|---|
| `syncRelatedModules` | Dispatches `estate/fetchEstateData` + for married users `estate/calculateSecondDeathIHTPlanning` (fire-and-forget) |

**UI Actions:**

| Action | Description |
|---|---|
| `setDetailView(bool)` | Toggle detail view |
| `resetState` | Reset store to initial state |

### Getters (12)

| Getter | Returns | Description |
|---|---|---|
| `overview` | Object | Full overview state |
| `netWorth` | Number | `overview.netWorth` |
| `totalAssets` | Number | `overview.totalAssets` |
| `totalLiabilities` | Number | `overview.totalLiabilities` |
| `assetBreakdown` | Array | Maps breakdown to `[{type, value, percentage}]` |
| `trendData` | Array | Remaps `net_worth` -> `value` |
| `hasAssets` | Boolean | True if any asset value > 0 |
| `assetCounts` | Object | `{pensions, property, investments, cash, business, chattels}` |
| `totalAssetCount` | Number | Sum of all asset counts |
| `formattedNetWorth` | String | GBP formatted via `Intl.NumberFormat` |
| `formattedAssets` | String | GBP formatted |
| `formattedLiabilities` | String | GBP formatted |

---

## 9. API Service

### 9.1 netWorthService.js (`resources/js/services/netWorthService.js`, 65 lines)

| Method | HTTP | Endpoint |
|---|---|---|
| `getOverview()` | GET | `/net-worth/overview` |
| `getBreakdown()` | GET | `/net-worth/breakdown` |
| `getTrend(months = 12)` | GET | `/net-worth/trend?months=N` |
| `getAssetsSummary()` | GET | `/net-worth/assets-summary` |
| `getAssetsSummaryDetailed()` | GET | `/net-worth/assets-summary-detailed` |
| `getJointAssets()` | GET | `/net-worth/joint-assets` |
| `refresh()` | POST | `/net-worth/refresh` |

### 9.2 propertyService.js and mortgageService.js

Documented in `Property.md`. `propertyService.js` has 12 methods; `mortgageService.js` has 5 methods. Both are used by the Vuex `netWorth` store for property/mortgage management within the net worth frontend.

---

## 10. Frontend Components

### 10.1 Shell Component

| Component | Lines | Description |
|---|---|---|
| `NetWorthDashboard.vue` | 351 | Top-level layout shell. Collapsible sidebar (240px expanded, 60px collapsed) + `<router-view>`. Sidebar items: Wealth Summary, Retirement, Property, Investments, Cash, Business Interests, Personal Valuables. Auto-collapses for investments/cash/retirement/property sub-routes. Sticky sidebar: `top: 100px`, `max-height: calc(100vh - 140px)`. |

### 10.2 Wealth Summary Components

| Component | Lines | Description |
|---|---|---|
| `NetWorthWealthSummary.vue` | 218 | Grid layout: `WealthSummary` table + `AssetAllocationDonut` charts. Shows 1-3 donuts (user, spouse, combined). Spouse section filtered out for widowed/divorced users. On mount: dispatches `loadAllData()`. |
| `WealthSummary.vue` | - | Tabular breakdown. Rows: Pensions, Property, Investments, Cash, Business, Chattels, then Liabilities (Mortgages, Loans, Credit Cards, Other), then Net Worth total. Clickable rows navigate to the corresponding sub-section. Shows DB pension note inline when `hasDbPensions` is true. 1-3 column layout depending on spouse status (user only / user + spouse / user + spouse + combined total). |
| `AssetAllocationDonut.vue` | - | ApexCharts donut chart. Categories: Pensions, Property, Investments, Cash & Savings, Business, Chattels. Colours from `ASSET_COLORS` constant. Filters out zero-value categories. |
| `NetWorthTrendChart.vue` | - | Currently displays a "Coming Soon" overlay. The area chart code is built (primary blue with gradient fill) but is hidden behind the overlay. |

### 10.3 Overview Components

| Component | Lines | Description |
|---|---|---|
| `NetWorthOverview.vue` | 621 | 3x2 grid of clickable asset category cards. Each card shows: icon, category name, total value, item list (max 3 items + "N more" indicator). Clicking navigates to the section. Badges: ISA (blue), Emergency Fund (blue), business-type (fuchsia), chattel-type (pink). |
| `NetWorthOverviewCard.vue` | - | Dashboard widget used on the main application Dashboard. Shows total net worth + asset/liability breakdown bar. |

### 10.4 Business Interest Components

| Component | Description |
|---|---|
| `BusinessInterestsList.vue` | Inline detail/list toggle. Summary bar: total count + "Total Value (Your Share)". Add/edit via `BusinessInterestForm` modal. Loading indicator: purple-600. |
| `BusinessInterestCard.vue` | Card display for a single business interest. Shows name, type, ownership, valuation. |
| `BusinessInterestDetailInline.vue` | Full detail view with tax deadlines and exit calculation sections. |
| `BusinessInterestForm.vue` | Form modal for create/edit. Emits `save` event per the standard form modal pattern. |

### 10.5 Chattel Components

| Component | Description |
|---|---|
| `ChattelsList.vue` | Inline detail/list toggle with type filter dropdown (All / Vehicle / Art / Antique / Jewellery / Collectible / Other). Loading indicator: pink-600. |
| `ChattelCard.vue` | Card display for a single chattel. |
| `ChattelDetailInline.vue` | Full detail view with CGT exempt status. |
| `ChattelFormModal.vue` | Form modal for create/edit. |

### 10.6 Cross-Module Aggregation Views

These components are thin wrappers that render components from other modules within the Net Worth sidebar layout:

| Component | Description |
|---|---|
| `CashOverview.vue` | Cash/savings sub-route. Uses Savings module components. |
| `InvestmentList.vue` | Investments sub-route. Uses Investment module components. |
| `PensionList.vue` | Retirement sub-route. Uses Retirement module components. |

### 10.7 Other Components

| Component | Description |
|---|---|
| `JointAccountHistory.vue` | Joint account change audit log. |

---

## 11. Frontend Routing

All routes nested under `/net-worth` with `NetWorthDashboard` as the layout shell:

| Path | Name | Component | Description |
|---|---|---|---|
| `/net-worth` | (redirect) | -> `/net-worth/wealth-summary` | Default redirect |
| `/net-worth/overview` | (redirect) | -> `/net-worth/wealth-summary` | Legacy redirect |
| `/net-worth/wealth-summary` | `NetWorthWealthSummary` | `NetWorthWealthSummary.vue` | Tabular summary + donut charts |
| `/net-worth/retirement` | `NetWorthRetirement` | `PensionList.vue` | Pension aggregation view |
| `/net-worth/property` | `NetWorthProperty` | `PropertyList.vue` | Property management |
| `/net-worth/investments` | `NetWorthInvestments` | `InvestmentList.vue` | Investment aggregation view |
| `/net-worth/investment-detail` | `InvestmentDetail` | `InvestmentProjections.vue` | Investment deep dive |
| `/net-worth/tax-efficiency` | `TaxEfficiencyDetail` | `TaxEfficiencyDetail.vue` | Tax efficiency analysis |
| `/net-worth/holdings-detail` | `HoldingsDetail` | `HoldingsDetail.vue` | Holdings breakdown |
| `/net-worth/fees-detail` | `FeesDetail` | `FeesDetail.vue` | Fee analysis |
| `/net-worth/strategy-detail` | `StrategyDetail` | `StrategyDetail.vue` | Strategy analysis |
| `/net-worth/cash` | `NetWorthCash` | `CashOverview.vue` | Cash/savings aggregation view |
| `/net-worth/business` | `NetWorthBusiness` | `BusinessInterestsList.vue` | Business interests management |
| `/net-worth/chattels` | `NetWorthChattels` | `ChattelsList.vue` | Chattels management |
| `/net-worth/joint-history` | `JointAccountHistory` | `JointAccountHistory.vue` | Joint account audit log |

All routes are duplicated under `/preview/net-worth/...` for preview mode.

---

## 12. Cross-Module Integration

### Data Sources Aggregated

| Source Model | Source Module | Net Worth Category | Aggregation Method |
|---|---|---|---|
| `Property` | Property | `breakdown.property` | `CrossModuleAssetAggregator` |
| `SavingsAccount` | Savings | `breakdown.cash` | `CrossModuleAssetAggregator` |
| `InvestmentAccount` | Investment | `breakdown.investments` | `CrossModuleAssetAggregator` |
| `DCPension` | Retirement | `breakdown.pensions` | `NetWorthService` direct query |
| `DBPension` | Retirement | NOT included (flagged via `has_db_pensions`) | `NetWorthService` direct query |
| `StatePension` | Retirement | NOT included | `NetWorthService` direct query |
| `BusinessInterest` | Net Worth (owned) | `breakdown.business` | `NetWorthService` direct query |
| `Chattel` | Net Worth (owned) | `breakdown.chattels` | `NetWorthService` direct query |
| `Mortgage` | Property | `liabilities_breakdown.mortgages` | `CrossModuleAssetAggregator` |
| `Liability` | Estate | `liabilities_breakdown.loans` / `credit_cards` / `other` | `NetWorthService` direct query |

### Cache Invalidation Chain

When asset data changes in other modules, the net worth cache must be invalidated for accurate figures:

```
Property/Mortgage CRUD (Vuex)
  -> refreshNetWorth() action
  -> POST /net-worth/refresh
  -> invalidateCache()
  -> fresh calculateNetWorth()

Savings account CRUD (savings store)
  -> dispatches netWorth/refreshNetWorth

Investment account CRUD (investment store)
  -> dispatches netWorth/refreshNetWorth

Estate liability CRUD (estate store)
  -> dispatches netWorth/refreshNetWorth

Business Interest/Chattel CRUD
  -> NO automatic invalidation (picked up on next 30-min cache cycle or manual refresh)
```

### Estate Module Integration

- `syncRelatedModules` action (fire-and-forget): dispatches `estate/fetchEstateData` and for married users `estate/calculateSecondDeathIHTPlanning`
- `EstateAssetAggregatorService` uses the same `CrossModuleAssetAggregator` pattern to pull property/investment/savings data for IHT calculations

---

## 13. Profile Completeness Integration

The Net Worth module does not have a dedicated profile completeness tracker. Instead, completeness is inferred from asset counts:

- Properties count via `users.properties_count` counter cache
- `AreasToConsiderCard` checks `netWorth.overview.breakdown.property > 0`
- The `NetWorthOverview` asset category cards show item counts which implicitly indicate completeness per category (e.g., showing "0 items" for a category suggests the user has not yet added data)
- `assetsSummary` provides per-category counts used throughout the application to determine whether a user has populated each financial area

---

## 14. Seeder Data

### PreviewUserSeeder Methods

**Business Interests:** `createBusinessInterests(User $user, array $businesses)` -- creates business interest records per persona configuration.

**Chattels:** `createChattels(User $user, ?User $spouse, array $chattels)` -- owner assignment logic:
1. If explicit `'owner' => 'spouse'` flag is set, assigns to spouse
2. If chattel name contains the spouse's first name, assigns to spouse
3. Otherwise assigns to primary user
4. Joint ownership automatically sets `joint_owner_id`

**Letter to Spouse Integration:** `createLetterToSpouse()` auto-generates `valuable_items_info` from chattel data, feeding net worth chattel information into the estate planning Letter to Spouse document.

### Cleanup on Re-seed

```php
BusinessInterest::where('user_id', $userId)->delete();
Chattel::where('user_id', $userId)->delete();
```

Hard deletes all existing records for the user before re-seeding.

---

## 15. API Routing

All routes under `auth:sanctum` middleware:

### Net Worth Aggregation

```
GET    /api/net-worth/overview                              -> NetWorthController@getOverview
GET    /api/net-worth/breakdown                             -> NetWorthController@getBreakdown
GET    /api/net-worth/trend                                 -> NetWorthController@getTrend
GET    /api/net-worth/assets-summary                        -> NetWorthController@getAssetsSummary
GET    /api/net-worth/assets-summary-detailed               -> NetWorthController@getAssetsSummaryWithDetails
GET    /api/net-worth/joint-assets                          -> NetWorthController@getJointAssets
POST   /api/net-worth/refresh                               -> NetWorthController@refresh
```

### Business Interests

```
GET    /api/business-interests                              -> BusinessInterestController@index
POST   /api/business-interests                              -> BusinessInterestController@store
GET    /api/business-interests/{id}                         -> BusinessInterestController@show
PUT    /api/business-interests/{id}                         -> BusinessInterestController@update
DELETE /api/business-interests/{id}                         -> BusinessInterestController@destroy
GET    /api/business-interests/{id}/tax-deadlines           -> BusinessInterestController@taxDeadlines
GET    /api/business-interests/{id}/exit-calculation        -> BusinessInterestController@exitCalculation
```

### Chattels

```
GET    /api/chattels                                        -> ChattelController@index
POST   /api/chattels                                        -> ChattelController@store
GET    /api/chattels/{id}                                   -> ChattelController@show
PUT    /api/chattels/{id}                                   -> ChattelController@update
DELETE /api/chattels/{id}                                   -> ChattelController@destroy
POST   /api/chattels/{id}/calculate-cgt                     -> ChattelController@calculateCGT
```

### Property and Mortgage Routes

Approximately 22 additional endpoints for property and mortgage CRUD, tax calculations, and amortisation -- documented in `Property.md`.

---

## 16. Key Constants and Business Logic

### Single-Record Architecture

Applies to ALL assets across the application:

- **ONE record** stores the FULL value (not split)
- `user_id` = primary owner (can edit/delete)
- `joint_owner_id` = secondary owner (view access only)
- `ownership_percentage` = primary owner's share
- Spouse's share = `100 - ownership_percentage`

### Business Interest Ownership Exception

Business interests differ from all other asset types: **individual ownership STILL applies `ownership_percentage`** (representing shareholding). In all other asset models, individual ownership is treated as 100% regardless of the stored percentage.

### Canonical Enums

| Type | Values |
|---|---|
| Business Types | `sole_trader`, `partnership`, `limited_company`, `llp`, `other` |
| Chattel Types | `vehicle` (wasting, CGT exempt), `art`, `antique`, `jewelry`, `collectible`, `other` |
| Trading Statuses | `trading`, `dormant`, `pre_trading` |
| Ownership Types | `individual`, `joint`, `trust` (business/chattel); also `tenants_in_common` for property |

### DB Pension Capital Value Formula

Display only (not included in net worth total):

```
capital_value = (accrued_annual_pension * 20) + lump_sum_entitlement
```

### BADR Eligibility Criteria

All four conditions must be met:
1. `acquisition_date` is present
2. Held for 2+ years (from `acquisition_date` to today)
3. `trading_status` = `trading`
4. `business_type` in (`sole_trader`, `partnership`, `llp`, `limited_company`)

**BADR Rate:** From `TaxConfigService` (10%)
**Lifetime Limit:** 1,000,000

### Chattel CGT Thresholds

| Rule | Value |
|---|---|
| Exemption threshold | 6,000 |
| Marginal relief formula | `(proceeds - 6000) * 5/3` |
| Wasting asset definition | Predictable life <= 50 years (currently only vehicles) |
| Non-residential CGT rates | Basic 10%, Higher 20% |
| Loss restriction | If proceeds < 6,000, treat proceeds as 6,000 for loss calculation |
| Annual exempt amount | From `TaxConfigService` (3,000 for 2025/26) |

### Net Worth Cache

- **Key:** `net_worth:user_{id}:date_{Y-m-d}`
- **TTL:** 1800 seconds (30 minutes)
- **Invalidation:** `invalidateCache()` forgets the current-day cache key

### Liability Type Mapping

The `calculateLiabilitiesBreakdown` method maps `Liability.type` values to net worth categories:

| Liability Type | Net Worth Category |
|---|---|
| `loan`, `secured_loan`, `personal_loan`, `hire_purchase`, `student_loan`, `business_loan` | `loans` |
| `credit_card` | `credit_cards` |
| `mortgage` | Skipped (handled by `CrossModuleAssetAggregator`) |
| `overdraft`, `other` | `other` |

---

## 17. Known Issues and Limitations

| # | Issue | Severity | Details |
|---|---|---|---|
| 1 | No NetWorthAgent | Info | No agent layer for net-worth-specific analysis or recommendations. Unlike other modules (Protection, Savings, Investment, Retirement, Estate, Goals, Coordination) which have dedicated agents. |
| 2 | Trend data is flat | Medium | `getNetWorthTrend` returns the same current figure for all months. No historical snapshots are stored. The `net_worth_statements` table exists but is never written to. |
| 3 | NetWorthTrendChart disabled | Medium | Frontend chart shows a "Coming Soon" overlay. The chart code (area chart with gradient fill) is built but hidden behind the overlay. |
| 4 | Joint savings not in `getJointAssets` | Low | `getJointAssets` returns an empty array for savings (has a TODO comment in the code). Properties, investments, businesses, and chattels are all included. |
| 5 | Business/chattel changes do not auto-invalidate cache | Medium | Unlike property/savings/investment CRUD which triggers `refreshNetWorth`, business interest and chattel CRUD operations do not invalidate the net worth cache. Changes appear on the next 30-minute cache cycle or on manual refresh. |
| 6 | DB Pensions excluded from net worth | Info | By design. DB pensions are not accessible capital. Flagged via the `has_db_pensions` boolean so the frontend can display an explanatory note. Capital value formula shown in detailed summary for reference only. |
| 7 | State Pension excluded from net worth | Info | By design. Not capital. Only counted in the assets summary counts. |
| 8 | No soft deletes on business interests or chattels | Low | Hard deletes only. No audit trail for deletions. |
| 9 | ChattelResource duplicates ownership calculation | Low | `ChattelResource` has its own inline `calculateUserShare()` method rather than using the shared `CalculatesOwnershipShare` trait. |
| 10 | `joint_owner_id` has no FK constraint on business_interests and chattels | Low | Unlike properties (which have cascading FK), the `business_interests` and `chattels` tables have no foreign key constraint on `joint_owner_id`. Orphaned references possible if a user is deleted. |

---

## 18. Cross-Module Asset Aggregation System

This section documents the `CrossModuleAssetAggregator` service and the `CalculatesOwnershipShare` trait, which together form the core mechanism for how Net Worth pulls data from all other modules.

### CrossModuleAssetAggregator (`app/Services/CrossModuleAssetAggregator.php`, 277 lines)

Single source of truth for Property, Investment, and Cash aggregation. Used by `NetWorthService`, `EstateAssetAggregatorService`, and potentially other consumers.

**Query pattern for ALL asset types:**

```php
Model::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get()
```

This ensures that both primary owners and joint owners see the asset, with the ownership share calculated per the `CalculatesOwnershipShare` trait.

**Standardized asset format returned by `getAllAssets()`:**

| Field | Type | Description |
|---|---|---|
| `asset_type` | string | `property`, `investment`, or `cash` |
| `asset_name` | string | Address / provider / institution |
| `current_value` | float | User's share (after ownership calculation) |
| `full_value` | float | Raw database value (FULL amount) |
| `ownership_type` | string | From the model (`individual`, `joint`, `tenants_in_common`, `trust`) |
| `ownership_percentage` | float | From the model |
| `is_primary_owner` | bool | True if `user_id` matches the queried user |
| `is_shared` | bool | True if `joint` or `tenants_in_common` |
| `is_iht_exempt` | bool | Always `false` (ISAs are IHT taxable) |
| `source_id` | int | Model primary key |
| `source_model` | string | Fully qualified class name |

### CalculatesOwnershipShare Trait (`app/Traits/CalculatesOwnershipShare.php`, 169 lines)

Shared by: `NetWorthService`, `CrossModuleAssetAggregator`, `PropertyController`, `MortgageController`, `BusinessInterestController`, `EstateAssetAggregatorService`

**Core method:** `calculateUserShare(object $asset, int $userId)`

Logic flow:
1. **Detect value field:** `current_value` -> `current_balance` -> `current_valuation` -> `outstanding_balance`
2. **Business interest detection:** if `current_valuation` AND `business_name` exist -> always apply `ownership_percentage` (even for individual ownership)
3. **Non-business individual/trust:** primary owner = 100%, others = 0%
4. **Joint/tenants-in-common:** primary owner = `ownership_percentage`%, joint owner = `(100 - ownership_percentage)`%
5. **Edge case:** joint with `ownership_percentage` = 100 -> defaults to 50/50

### Data Flow Diagram

```
                    +-----------------------------------------+
                    |          NetWorthService                 |
                    |       calculateNetWorth()                |
                    +-------------------+---------------------+
                                        |
              +-------------------------+-------------------------+
              |                         |                         |
    +---------v-----------+   +---------v----------+   +----------v-----------+
    | CrossModule          |   | Direct Queries     |   | Direct Queries       |
    | AssetAggregator      |   |                    |   |                      |
    |                      |   | DCPension          |   | BusinessInterest     |
    | Property             |   | DBPension          |   | Chattel              |
    | InvestmentAccount    |   | StatePension       |   | Liability            |
    | SavingsAccount       |   |                    |   |                      |
    | Mortgage             |   |                    |   |                      |
    +----------------------+   +--------------------+   +----------------------+
```

### Cache Invalidation Matrix

| Module CRUD Event | Triggers `refreshNetWorth`? | How |
|---|---|---|
| Property create/update/delete | Yes | Vuex `netWorth` store action chain |
| Mortgage create/update/delete | Yes | Vuex `netWorth` store action chain |
| Savings account create/update/delete | Yes | Savings store dispatches `netWorth/refreshNetWorth` |
| Investment account create/update/delete | Yes | Investment store dispatches `netWorth/refreshNetWorth` |
| Estate liability create/update/delete | Yes | Estate store dispatches `netWorth/refreshNetWorth` |
| Business interest create/update/delete | **No** | No automatic invalidation |
| Chattel create/update/delete | **No** | No automatic invalidation |
| DC/DB Pension changes | **No** | No automatic invalidation |

### Shared Consumer Pattern

The `CrossModuleAssetAggregator` is reused across multiple services:

```
CrossModuleAssetAggregator
  |
  +-- NetWorthService (for net worth overview, breakdown, detailed summary)
  |
  +-- EstateAssetAggregatorService (for IHT estate value calculations)
  |
  +-- (Future consumers follow the same pattern)
```

This shared aggregator ensures that net worth and estate planning always use the same underlying asset calculations, preventing discrepancies between modules.
