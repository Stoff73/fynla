# Property Module - Current State Documentation

**Last Updated:** 2026-02-18
**Module Version:** Part of Fynla v0.7.0
**Status:** Fully functional with property management, mortgage tracking, SDLT/CGT/Section 24 tax calculations, joint ownership, and cross-module estate/net worth integration

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
18. [Tax Calculations System](#18-tax-calculations-system-sdlt-cgt-section-24-rental-income-tax)

---

## 1. System Overview

The Property module manages residential and buy-to-let properties, associated mortgages, and property-related tax calculations (SDLT, CGT, Section 24 rental income tax). Properties are part of the Net Worth module on the frontend -- there is no standalone `/property` route. Both properties and mortgages use the single-record architecture for joint ownership.

**Important architectural distinction:** Unlike all other Fynla modules (Protection, Savings, Investment, Retirement, Estate Planning, Goals & Life Events, Coordination), Property does NOT have a dedicated Agent. It uses a direct Controller to Service pattern. Property data is consumed by other agents (EstateAgent via EstateAssetAggregatorService, CoordinatingAgent indirectly) but no PropertyAgent exists to generate property-specific insights or recommendations.

### Architecture Flow

```
PropertyList.vue / PropertyDetailInline.vue
  -> PropertyCard.vue (display, ownership badges, equity)
  -> PropertyForm.vue (multi-step wizard with embedded mortgage)
  -> PropertyFinancials.vue (costs, BTL Section 24)
  -> PropertyTaxCalculator.vue (SDLT, CGT)
  -> propertyService.js / mortgageService.js (API wrappers)
  -> Vuex netWorth.js store (property/mortgage state + syncRelatedModules)
  -> PropertyController.php / MortgageController.php
  -> PropertyService / PropertyTaxService / MortgageService
  -> Property / Mortgage models
  -> DB (properties + mortgages tables)
```

### File Count Summary

| Category | Count |
|---|---|
| Models | 2 (Property, Mortgage) |
| Services | 3 (PropertyService, PropertyTaxService, MortgageService) |
| Controllers | 2 (PropertyController, MortgageController) |
| Agents | 0 (no PropertyAgent) |
| Validation Requests | 4 |
| API Resources | 2 |
| Observers | 1 (PropertyRiskObserver) |
| Vue Components | ~8 |
| API Services (JS) | 2 (propertyService.js, mortgageService.js) |
| API Endpoints | ~22 |

---

## 2. Database Schema

### 2.1 `properties`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK, AUTO_INCREMENT |
| `user_id` | bigint unsigned | NOT NULL, FK -> users(id) CASCADE |
| `joint_owner_id` | bigint | NULL |
| `joint_owner_name` | varchar(255) | NULL, free-text for non-system users |
| `household_id` | bigint unsigned | NULL, FK -> households(id) SET NULL |
| `trust_id` | bigint unsigned | NULL, FK -> trusts(id) SET NULL |
| `trust_name` | varchar(255) | NULL, free-text for non-registered trusts |
| `property_type` | varchar(255) | NULL (`main_residence`, `secondary_residence`, `buy_to_let`) |
| `ownership_type` | enum | `individual`, `joint`, `tenants_in_common`, `trust`; DEFAULT `individual` |
| `joint_ownership_type` | enum | `joint_tenancy`, `tenants_in_common`; NULL |
| `tenure_type` | enum | `freehold`, `leasehold`; NOT NULL DEFAULT `freehold` |
| `lease_remaining_years` | int unsigned | NULL |
| `lease_expiry_date` | date | NULL |
| `country` | varchar(255) | NOT NULL DEFAULT 'United Kingdom' |
| `ownership_percentage` | decimal(5,2) | NOT NULL DEFAULT 100.00 |
| `address_line_1` | varchar(255) | NULL |
| `address_line_2` | varchar(255) | NULL |
| `city` | varchar(255) | NULL |
| `county` | varchar(255) | NULL |
| `postcode` | varchar(10) | NULL |
| `purchase_date` | date | NULL |
| `purchase_price` | decimal(15,2) | NULL |
| `current_value` | decimal(15,2) | NULL (stores FULL property value) |
| `valuation_date` | date | NULL |
| `sdlt_paid` | decimal(15,2) | NULL |
| `monthly_rental_income` | decimal(10,2) | NULL |
| `outstanding_mortgage` | decimal(15,2) | NULL (legacy simple field) |
| `mortgages_count` | tinyint unsigned | NOT NULL DEFAULT 0 |
| `total_mortgage_balance` | decimal(15,2) | NOT NULL DEFAULT 0.00 |
| `tenant_name` | varchar(255) | NULL |
| `tenant_email` | varchar(255) | NULL |
| `managing_agent_name` | varchar(255) | NULL |
| `managing_agent_company` | varchar(255) | NULL |
| `managing_agent_email` | varchar(255) | NULL |
| `managing_agent_phone` | varchar(255) | NULL |
| `managing_agent_fee` | decimal(10,2) | NULL |
| `lease_start_date` | date | NULL |
| `lease_end_date` | date | NULL |
| `monthly_council_tax` | decimal(10,2) | NULL |
| `monthly_gas` | decimal(10,2) | NULL |
| `monthly_electricity` | decimal(10,2) | NULL |
| `monthly_water` | decimal(10,2) | NULL |
| `monthly_building_insurance` | decimal(10,2) | NULL |
| `monthly_contents_insurance` | decimal(10,2) | NULL |
| `monthly_service_charge` | decimal(10,2) | NULL |
| `monthly_maintenance_reserve` | decimal(10,2) | NULL |
| `other_monthly_costs` | decimal(10,2) | NULL |
| `annual_service_charge` | decimal(10,2) | NULL (legacy, not in model fillable) |
| `annual_ground_rent` | decimal(10,2) | NULL (legacy, not in model fillable) |
| `annual_insurance` | decimal(10,2) | NULL (legacy, not in model fillable) |
| `annual_maintenance_reserve` | decimal(10,2) | NULL (legacy, not in model fillable) |
| `other_annual_costs` | decimal(10,2) | NULL (legacy, not in model fillable) |
| `notes` | text | NULL |
| `created_at` / `updated_at` | timestamps | |

**Indexes:** `user_id`, `household_id`, `trust_id`, `property_type`, `ownership_type`, `joint_owner_id`

**Single-Record Pattern:** One database record stores the FULL property value in `current_value`. `user_id` = primary owner (edit/delete rights), `joint_owner_id` = secondary owner (view access only), `ownership_percentage` = primary owner's share (defaults to 50 for joint, 100 for individual). The spouse's share is calculated as `(100 - ownership_percentage)`.

### 2.2 `mortgages`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK, AUTO_INCREMENT |
| `property_id` | bigint unsigned | NOT NULL, FK -> properties(id) CASCADE |
| `country` | varchar(255) | NOT NULL DEFAULT 'United Kingdom' |
| `user_id` | bigint unsigned | NOT NULL, FK -> users(id) CASCADE |
| `joint_owner_id` | bigint | NULL |
| `joint_owner_name` | varchar(255) | NULL |
| `lender_name` | varchar(255) | NULL |
| `mortgage_account_number` | varchar(255) | NULL |
| `mortgage_type` | enum | `repayment`, `interest_only`, `mixed`; NOT NULL |
| `repayment_percentage` | decimal(5,2) | NULL (for mixed type) |
| `interest_only_percentage` | decimal(5,2) | NULL (for mixed type) |
| `original_loan_amount` | decimal(15,2) | NULL |
| `outstanding_balance` | decimal(15,2) | NOT NULL (stores FULL balance) |
| `interest_rate` | decimal(8,4) | NULL |
| `rate_type` | enum | `fixed`, `variable`, `tracker`, `discount`, `mixed`; NOT NULL |
| `fixed_rate_percentage` | decimal(5,2) | NULL |
| `variable_rate_percentage` | decimal(5,2) | NULL |
| `fixed_interest_rate` | decimal(5,4) | NULL |
| `variable_interest_rate` | decimal(5,4) | NULL |
| `rate_fix_end_date` | date | NULL |
| `monthly_payment` | decimal(10,2) | NULL |
| `monthly_interest_portion` | decimal(10,2) | NULL (for Section 24 BTL tax calculation) |
| `start_date` | date | NULL |
| `maturity_date` | date | NULL |
| `remaining_term_months` | int | NOT NULL |
| `ownership_type` | enum | `individual`, `joint`, `tenants_in_common`; DEFAULT `individual` |
| `ownership_percentage` | decimal(5,2) | NOT NULL DEFAULT 100.00 |
| `notes` | text | NULL |
| `created_at` / `updated_at` | timestamps | |

**Indexes:** `property_id`, `user_id`, `mortgage_type`, `joint_owner_id`, `start_date`

**Single-Record Pattern:** Same as properties -- one record stores the FULL outstanding balance. Mortgage inherits `joint_owner_id` from its parent property on creation. The `ownership_type` field on mortgages only supports `individual`, `joint`, and `tenants_in_common` (no `trust`), and `tenants_in_common` is mapped to `joint` during creation.

---

## 3. Models

### 3.1 Property.php (~225 lines)

**Location:** `app/Models/Property.php`
**Traits:** `Auditable`, `HasFactory`, `HasJointOwnership`
**Fillable:** 65 fields

**Casts:**

| Cast Type | Fields |
|---|---|
| `date` | `purchase_date`, `valuation_date`, `lease_start_date`, `lease_end_date`, `lease_expiry_date` |
| `decimal:2` | All monetary fields (`purchase_price`, `current_value`, `sdlt_paid`, `monthly_rental_income`, `outstanding_mortgage`, `total_mortgage_balance`, all monthly cost fields, `managing_agent_fee`, `ownership_percentage`) |
| `integer` | `lease_remaining_years` |

**Appended Attributes:** `equity`

**Relationships:**

| Relationship | Method | Type | Target |
|---|---|---|---|
| `user()` | BelongsTo | `User` | via `user_id` |
| `household()` | BelongsTo | `Household` | via `household_id` |
| `trust()` | BelongsTo | `Estate\Trust` | via `trust_id` |
| `mortgages()` | HasMany | `Mortgage` | via `property_id` |
| `jointOwner()` | BelongsTo | `User` | via `joint_owner_id` |

**Computed Accessors:**

| Accessor | Returns | Logic |
|---|---|---|
| `jointOwnerDisplayName` | string | Returns `jointOwner->name` if relation loaded and exists, otherwise free-text `joint_owner_name` |
| `trustDisplayName` | string | Returns `trust->trust_name` if relation loaded and exists, otherwise free-text `trust_name` |
| `isLeaseholdExpiring` | bool | `true` if `tenure_type === 'leasehold'` and `lease_remaining_years < 80` |
| `ownershipDescription` | string | Human-readable ownership string including joint tenancy sub-type |
| `equity` | float | `current_value - sum(mortgages.outstanding_balance)`; falls back to `outstanding_mortgage` field if no mortgage records; does NOT re-apply `ownership_percentage` |

### 3.2 Mortgage.php (~82 lines)

**Location:** `app/Models/Mortgage.php`
**Traits:** `Auditable`, `HasFactory`, `HasJointOwnership`

**Casts:**

| Cast Type | Fields |
|---|---|
| `date` | Date fields |
| `decimal:2` | All monetary fields |
| `decimal:4` | All interest rate fields |
| `integer` | `remaining_term_months` |

**Relationships:**

| Relationship | Method | Type | Target |
|---|---|---|---|
| `property()` | BelongsTo | `Property` | via `property_id` |
| `user()` | BelongsTo | `User` | via `user_id` |

---

## 4. Controller

### 4.1 PropertyController.php (~457 lines)

**Location:** `app/Http/Controllers/Api/PropertyController.php`
**Trait:** `CalculatesOwnershipShare`
**Dependencies:** `PropertyService`, `PropertyTaxService`, `MortgageService`

| # | HTTP | Route | Method | Auth | Description |
|---|---|---|---|---|---|
| 1 | GET | `/properties` | `index` | Owner OR joint_owner | All properties where user is owner or joint_owner. Adds computed fields: `user_share`, `full_value`, `is_primary_owner`, `is_shared`, `mortgage_user_share`, `mortgage_full_balance`. Ordered by `property_type` then `created_at` DESC. |
| 2 | POST | `/properties` | `store` | Authenticated | Create property. Auto-defaults: `ownership_type=individual`, `ownership_percentage=100`, `valuation_date=now`, `country=United Kingdom`. Joint with 100% auto-defaults to 50%. Maps `address` to `address_line_1`, `rental_income` to `monthly_rental_income`. Creates mortgage via `mortgageService->createFromPropertyData()`. Syncs rental income. |
| 3 | GET | `/properties/{id}` | `show` | Owner OR joint_owner | Single property detail. Loads `mortgages`, `household`, `trust`, `user`, `jointOwner`. Merges `PropertyResource` with `propertyService->getPropertySummary()`. |
| 4 | PUT | `/properties/{id}` | `update` | Primary owner only | Update property. Handles ownership percentage switching. Logs joint changes to `JointAccountLog`. Syncs rental income. |
| 5 | DELETE | `/properties/{id}` | `destroy` | Primary owner only | Delete property. Cascade deletes mortgages. Syncs rental income. |
| 6 | POST | `/properties/calculate-sdlt` | `calculateSDLT` | Authenticated | SDLT calculation. Validates `purchase_price`, `property_type`, `is_first_home`. |
| 7 | POST | `/properties/{id}/calculate-cgt` | `calculateCGT` | Owner OR joint_owner | CGT calculation. Validates `disposal_price`, `disposal_costs`. |
| 8 | POST | `/properties/{id}/rental-income-tax` | `calculateRentalIncomeTax` | Owner OR joint_owner | Rental income tax calculation. Loads mortgages for Section 24. |

**Private Helpers:**

- `logJointPropertyUpdate(User, Property, array)` -- logs ownership changes to `JointAccountLog`
- `syncUserRentalIncome(User)` -- sums `monthly_rental_income * ownership_share * 12` across all user properties, updates `users.annual_rental_income`

### 4.2 MortgageController.php (~385 lines)

**Location:** `app/Http/Controllers/Api/MortgageController.php`
**Trait:** `CalculatesOwnershipShare`
**Dependency:** `MortgageService`

| # | HTTP | Route | Method | Auth | Description |
|---|---|---|---|---|---|
| 1 | GET | `/properties/{propertyId}/mortgages` | `index` | Owner OR joint_owner | Mortgages for a property. Adds `user_share`, `full_balance`, `is_primary_owner`. |
| 2 | POST | `/properties/{propertyId}/mortgages` | `store` | Primary owner only | Create mortgage. Auto-defaults: `lender='To be completed'`, `type=repayment`, `rate=0`, `rate_type=fixed`, `start=now()`, `maturity=now()+25yr`, `remaining=300mo`. Inherits `joint_owner_id` from parent property. |
| 3 | GET | `/mortgages/{id}` | `show` | Owner OR joint_owner | Single mortgage detail. |
| 4 | PUT | `/mortgages/{id}` OR `/properties/{propertyId}/mortgages/{mortgageId}` | `update` | Primary owner only | Update mortgage. Handles both route patterns. Logs joint changes. |
| 5 | DELETE | `/mortgages/{id}` OR `/properties/{propertyId}/mortgages/{mortgageId}` | `destroy` | Primary owner only | Delete mortgage. Both route patterns supported. |
| 6 | GET | `/mortgages/{id}/amortization-schedule` | `amortizationSchedule` | Owner OR joint_owner | Full payment-by-payment amortization schedule. |
| 7 | POST | `/mortgages/calculate-payment` | `calculatePayment` | Authenticated | Standalone calculation. Returns `loan_amount`, `rate`, `term`, `type`, monthly/annual/total payments. |

---

## 5. Agent

**No PropertyAgent exists.** This is a deliberate architectural distinction -- Property is the only Fynla module without a dedicated Agent layer.

All other modules follow the pattern: `Controller -> Agent -> Services`. The Property module uses the simpler `Controller -> Services` pattern directly.

### Why No Agent?

The Agent layer in other modules serves as an orchestrator for:
- Generating analysis and recommendations
- Running scenario modelling
- Caching complex computed results
- Coordinating multiple services for a unified response

Property currently relies on:
- **PropertyController** for direct CRUD and tax calculation orchestration
- **PropertyService** for equity and rental calculations
- **PropertyTaxService** for SDLT, CGT, and Section 24 calculations
- **MortgageService** for amortization and payment calculations

### Cross-Agent Consumption

Although no PropertyAgent exists, property data IS consumed by other agents:

| Agent | How It Uses Property Data |
|---|---|
| **EstateAgent** | Via `EstateAssetAggregatorService` -- queries properties for estate asset aggregation, maps `main_residence` for RNRB eligibility |
| **CoordinatingAgent** | Indirectly via estate data and net worth calculations |
| **Risk Profile** | Via `PropertyRiskObserver` triggering `RecalculateRiskProfileJob` |

---

## 6. Services

### 6.1 PropertyService.php (~402 lines)

**Location:** `app/Services/Property/PropertyService.php`
**Trait:** `CalculatesOwnershipShare`

| Method | Signature | Returns | Description |
|---|---|---|---|
| `calculateEquity` | `(Property): float` | float | `current_value - sum(mortgages.outstanding_balance)`. Falls back to `outstanding_mortgage` if no mortgage records. Returns full value, not user share. |
| `calculateUserEquity` | `(Property, userId): float` | float | Calls `calculateEquity()` then applies `ownership_percentage` for the given user. |
| `calculateTaxPosition` | `(Property, ?userId): array` | array | BTL Section 24 tax position (see Section 18 for full detail). |
| `calculateTotalMonthlyCosts` | `(Property): float` | float | Sum of all mortgage payments + all monthly cost fields. |
| `calculateNetRentalYield` | `(Property): float` | float | `(net_monthly * 12 / current_value) * 100`, incorporating `occupancy_rate_percent`. |
| `getPropertySummary` | `(Property): array` | array | Comprehensive response with top-level, address, valuation, financial, rental, costs, and mortgages sections. |

**`calculateTaxPosition` detail:**

Non-mortgage allowable costs: `monthly_gas + monthly_electricity + monthly_water + monthly_building_insurance + monthly_contents_insurance + monthly_service_charge + monthly_ground_rent + managing_agent_fee`

Mortgage interest calculation by type:
- `interest_only`: full monthly payment
- `repayment`: `monthly_interest_portion` field
- `mixed`: `interest_only_percentage * payment + monthly_interest_portion`

Section 24 credit = `mortgage_interest * 20%`

Returns: `property_name`, `monthly_taxable_income`, `annual_taxable_income`, `monthly_mortgage_interest`, `section_24_monthly_credit`, `section_24_annual_credit`, `monthly_allowable_costs`, `ownership_percentage`, `has_interest_portion_missing`

### 6.2 PropertyTaxService.php (~267 lines)

**Location:** `app/Services/Property/PropertyTaxService.php`
**Dependency:** `TaxConfigService`

| Method | Signature | Returns | Description |
|---|---|---|---|
| `calculateSDLT` | `(purchasePrice, propertyType, isFirstHome): array` | array | Banded SDLT calculation from TaxConfigService rates. |
| `calculateCGT` | `(Property, disposalPrice, disposalCosts, User): array` | array | Capital Gains Tax on property disposal. |
| `calculateRentalIncomeTax` | `(Property, User): array` | array | Annual rental income tax with Section 24 relief. |
| `calculateBandedTax` | `(amount, configBands, &outputBands): float` | float | Private generic banded tax engine used by SDLT calculation. |

Full detail on each tax calculation is in [Section 18](#18-tax-calculations-system-sdlt-cgt-section-24-rental-income-tax).

### 6.3 MortgageService.php (~280 lines)

**Location:** `app/Services/Property/MortgageService.php`

| Method | Signature | Returns | Description |
|---|---|---|---|
| `createFromPropertyData` | `(Property, validated, User): ?Mortgage` | Mortgage or null | Creates mortgage from property form's embedded mortgage fields. Returns null if no `outstanding_mortgage`. Maps `mortgage_*` prefixed fields. Normalises `tenants_in_common` to `joint`. |
| `calculateMonthlyPayment` | `(loanAmount, annualRate, termMonths, type): float` | float | `interest_only`: `P * (r/12)`. `repayment`: standard amortization `M = P[r(1+r)^n] / [(1+r)^n - 1]`. |
| `generateAmortizationSchedule` | `(Mortgage): array` | array | Payment-by-payment schedule. Skips past payments. Returns `mortgage_id`, `lender`, `original_loan`, `outstanding_balance`, `interest_rate`, `monthly_payment`, `remaining_months`, `schedule[]`, `total_payments`, `total_interest`, `total_principal`. |
| `calculateRemainingTerm` | `(Mortgage): int` | int | Months remaining to maturity date. |
| `calculateTotalInterest` | `(Mortgage): float` | float | Total interest via amortization schedule. |
| `calculateAnnualEquityBuild` | `(Mortgage): array` | array | Groups amortization by year. Returns `{ mortgage_id, annual_breakdown[{ year, principal_paid, interest_paid, total_paid }] }`. |

---

## 7. Validation Requests

### 7.1 StorePropertyRequest.php (~129 lines)

**Location:** `app/Http/Requests/Property/StorePropertyRequest.php`

All fields nullable. Key rules:

| Field | Validation |
|---|---|
| `property_type` | `in:main_residence,secondary_residence,buy_to_let` |
| `ownership_type` | `in:individual,joint,tenants_in_common,trust` |
| `joint_ownership_type` | `in:joint_tenancy,tenants_in_common` |
| `tenure_type` | `in:freehold,leasehold` |
| `joint_owner_id` | `exists:users,id` |
| `trust_id` | `exists:trusts,id` |
| `lease_remaining_years` | `integer`, `min:0`, `max:999` |
| `postcode` | `max:10` |
| `mortgage_type` | `in:repayment,interest_only,mixed` |
| `mortgage_rate_type` | `in:fixed,variable,tracker,discount,mixed` |
| `mortgage_ownership_type` | `in:individual,joint` |
| `lease_end_date` | `after_or_equal:lease_start_date` |

Also validates embedded mortgage fields prefixed with `mortgage_*` for inline mortgage creation during property creation.

### 7.2 UpdatePropertyRequest.php (~120 lines)

**Location:** `app/Http/Requests/Property/UpdatePropertyRequest.php`

All fields use `sometimes`. Adds: `annual_rental_income`, `occupancy_rate_percent`. Uses `withValidator` to add conditional UK postcode regex validation when `country` is `United Kingdom`.

### 7.3 StoreMortgageRequest.php (~89 lines)

**Location:** `app/Http/Requests/Mortgage/StoreMortgageRequest.php`

| Field | Validation |
|---|---|
| `mortgage_type` | `in:repayment,interest_only,mixed` |
| `rate_type` | `in:fixed,variable,tracker,discount,mixed` |
| `ownership_type` | `in:individual,joint` (only 2 values, no `tenants_in_common`) |
| `maturity_date` | `after_or_equal:start_date` |
| `monthly_interest_portion` | Included for Section 24 calculation support |

### 7.4 UpdateMortgageRequest.php (~89 lines)

**Location:** `app/Http/Requests/Mortgage/UpdateMortgageRequest.php`

All fields use `sometimes`. Same validation rules as store but all optional.

---

## 8. Vuex Store

Properties are managed within the `netWorth` Vuex module -- there is no dedicated property store file.

**File:** `resources/js/store/modules/netWorth.js` (~805 lines)
**Namespaced:** `true`

### State (Property-Relevant)

```javascript
state: {
  properties: [],
  selectedProperty: null,
  mortgages: [],
  selectedMortgage: null,
  isDetailView: false,
  // ... other net worth state (accounts, pensions, etc.)
}
```

### Mutations

| Mutation | Purpose |
|---|---|
| `SET_PROPERTIES` | Replace full properties array |
| `SET_SELECTED_PROPERTY` | Set detail view property |
| `ADD_PROPERTY` | Push new property to array |
| `UPDATE_PROPERTY` | Replace property in array by ID |
| `REMOVE_PROPERTY` | Filter property from array by ID |
| `SET_MORTGAGES` | Replace full mortgages array |
| `SET_SELECTED_MORTGAGE` | Set detail view mortgage |
| `ADD_MORTGAGE` | Push new mortgage to array |
| `UPDATE_MORTGAGE` | Replace mortgage in array by ID |
| `REMOVE_MORTGAGE` | Filter mortgage from array by ID |

### Property Actions

| Action | Flow |
|---|---|
| `fetchProperties` | API GET -> `SET_PROPERTIES` |
| `fetchProperty(id)` | API GET -> `SET_SELECTED_PROPERTY` |
| `createProperty(data)` | API POST -> `ADD_PROPERTY` -> `refreshNetWorth()` -> `syncRelatedModules()` |
| `updateProperty({id, data})` | API PUT -> `UPDATE_PROPERTY` -> `refreshNetWorth()` -> `syncRelatedModules()` |
| `deleteProperty(id)` | API DELETE -> `REMOVE_PROPERTY` -> `refreshNetWorth()` -> `syncRelatedModules()` |

### Mortgage Actions

| Action | Flow |
|---|---|
| `fetchPropertyMortgages(propertyId)` | API GET -> `SET_MORTGAGES` |
| `fetchMortgage(id)` | API GET -> `SET_SELECTED_MORTGAGE` |
| `createMortgage({propertyId, data})` | API POST -> `ADD_MORTGAGE` -> `fetchProperty()` -> `refreshNetWorth()` -> `syncRelatedModules()` |
| `updateMortgage({id, data, propertyId})` | API PUT -> `UPDATE_MORTGAGE` -> optional `fetchProperty()` -> `refreshNetWorth()` -> `syncRelatedModules()` |
| `deleteMortgage({id, propertyId})` | API DELETE -> `REMOVE_MORTGAGE` -> optional `fetchProperty()` -> `refreshNetWorth()` -> `syncRelatedModules()` |

### Tax Calculation Actions (No State Mutation)

These actions call the API and return data directly without mutating store state:

| Action | Purpose |
|---|---|
| `calculateSDLT(data)` | SDLT calculation via `propertyService.calculateSDLT()` |
| `calculateCGT({propertyId, data})` | CGT calculation via `propertyService.calculateCGT()` |
| `calculateRentalIncomeTax(propertyId)` | Rental income tax via `propertyService.calculateRentalIncomeTax()` |
| `getAmortizationSchedule(mortgageId)` | Amortization schedule via `mortgageService.getAmortizationSchedule()` |
| `calculateMortgagePayment(data)` | Standalone payment calculation via `mortgageService.calculatePayment()` |

### syncRelatedModules Action

After every property or mortgage CRUD operation, the store dispatches background syncs:

```javascript
async syncRelatedModules({ dispatch, rootGetters }) {
  // Always sync estate data
  dispatch('estate/fetchEstateData', null, { root: true });

  // For married/civil_partnership users, also sync IHT planning
  const maritalStatus = rootGetters['user/maritalStatus'];
  if (maritalStatus === 'married' || maritalStatus === 'civil_partnership') {
    dispatch('estate/calculateSecondDeathIHTPlanning', null, { root: true });
  }
}
```

Errors are caught and logged (background sync should not block the primary operation).

### Getters

The `netWorth.js` store exposes these getters (shared across all asset types including property):

`overview`, `netWorth`, `totalAssets`, `totalLiabilities`, `assetBreakdown`, `trendData`, `hasAssets`, `assetCounts`, `totalAssetCount`, `formattedNetWorth`, `formattedAssets`, `formattedLiabilities`

---

## 9. API Service

### 9.1 propertyService.js (~66 lines)

**Location:** `resources/js/services/propertyService.js`

| Method | HTTP | Endpoint |
|---|---|---|
| `getProperties()` | GET | `/properties` |
| `getProperty(id)` | GET | `/properties/{id}` |
| `createProperty(data)` | POST | `/properties` |
| `updateProperty(id, data)` | PUT | `/properties/{id}` |
| `deleteProperty(id)` | DELETE | `/properties/{id}` |
| `calculateSDLT(data)` | POST | `/properties/calculate-sdlt` |
| `calculateCGT(propertyId, data)` | POST | `/properties/{id}/calculate-cgt` |
| `calculateRentalIncomeTax(propertyId)` | POST | `/properties/{id}/rental-income-tax` |
| `getPropertyMortgages(propertyId)` | GET | `/properties/{id}/mortgages` |
| `createPropertyMortgage(propertyId, data)` | POST | `/properties/{id}/mortgages` |
| `updatePropertyMortgage(propertyId, mortgageId, data)` | PUT | `/properties/{id}/mortgages/{mortgageId}` |
| `deletePropertyMortgage(propertyId, mortgageId)` | DELETE | `/properties/{id}/mortgages/{mortgageId}` |

### 9.2 mortgageService.js (Standalone Routes)

**Location:** `resources/js/services/mortgageService.js`

| Method | HTTP | Endpoint |
|---|---|---|
| `getMortgage(id)` | GET | `/mortgages/{id}` |
| `updateMortgage(id, data)` | PUT | `/mortgages/{id}` |
| `deleteMortgage(id)` | DELETE | `/mortgages/{id}` |
| `getAmortizationSchedule(id)` | GET | `/mortgages/{id}/amortization-schedule` |
| `calculatePayment(data)` | POST | `/mortgages/calculate-payment` |

---

## 10. Frontend Components

All property components live under `resources/js/components/NetWorth/` -- properties are part of the Net Worth module, not a standalone section.

### 10.1 PropertyList.vue (~427 lines)

**Location:** `resources/js/components/NetWorth/PropertyList.vue`

Top-level container component. Switches between list view and detail view. Uses `PropertyCard`, `PropertyForm`, and `PropertyDetailInline`. Fetches property data directly via `api.get('/properties')` rather than dispatching a Vuex action. Sorts properties by `current_value` DESC. Handles save with mortgage sub-form data flattening into the property payload.

### 10.2 PropertyCard.vue (~318 lines)

**Location:** `resources/js/components/NetWorth/PropertyCard.vue`
**Mixins:** `currencyMixin`

Card display for each property in the list view. Shows:
- **Type badge**: blue for `main_residence`/`secondary_residence`, green for `buy_to_let`
- **Ownership badge**: purple for `joint`, green for `tenants_in_common`
- **Financial data**: full value (if shared), user share, mortgage amount, equity
- **Mortgage amount**: computed with ownership percentage applied for joint properties

### 10.3 PropertyDetailInline.vue (~300+ lines)

**Location:** `resources/js/components/NetWorth/Property/PropertyDetailInline.vue`

Full detail view with tabs:

| Tab | Content |
|---|---|
| Overview | Property details, address, tenure, ownership |
| Financials | Monthly costs breakdown, BTL Section 24 calculations |
| Tax Calculator | Interactive SDLT and CGT calculators |
| Mortgages | Mortgage list, create/edit, amortization schedule |

Shows: Full Value, Your Share, Mortgage Balance, Net Rental Yield. Edit/Delete buttons use `v-preview-disabled` directive.

### 10.4 PropertyForm.vue (~300+ lines)

**Location:** `resources/js/components/NetWorth/Property/PropertyForm.vue`

Multi-step modal wizard with 6 steps:

| Step | Fields | Condition |
|---|---|---|
| 1. Basic Info | Property type, address, tenure (freehold/leasehold) | Always shown |
| 2. Ownership | Ownership type, joint owner, percentage | Always shown |
| 3. Financial | Purchase price/date, current value, SDLT paid | Always shown |
| 4. Mortgage | Embedded mortgage fields (lender, type, rate, balance) | Conditional |
| 5. Monthly Costs | Council tax, utilities, insurance, service charge | Conditional |
| 6. BTL Details | Rental income, tenant info, managing agent | Conditional (buy_to_let only) |

Uses `@submit.prevent` on internal form element, then `$emit('save', formData)` to parent.

### 10.5 PropertyFinancials.vue (~200+ lines)

**Location:** `resources/js/components/NetWorth/Property/PropertyFinancials.vue`

Monthly costs breakdown tab. For buy-to-let properties, shows rental income, taxable income, and Section 24 basic rate credit. Shows ownership tooltip for shared properties explaining the user's proportional share.

### 10.6 PropertyTaxCalculator.vue (~200+ lines)

**Location:** `resources/js/components/NetWorth/Property/PropertyTaxCalculator.vue`

Interactive calculator with two modes:
- **SDLT Calculator**: Purchase price input, property type selection, first-time buyer toggle. Shows banded breakdown and effective rate.
- **CGT Calculator**: Disposal price and costs inputs. Shows acquisition cost, gain, annual exempt amount, taxable gain, liability.

### 10.7 PropertyForm.vue (Legacy)

**Location:** `resources/js/components/NetWorth/PropertyForm.vue`

An older property form in the parent `NetWorth/` directory alongside the newer `Property/PropertyForm.vue`. Likely superseded by the wizard-based form but still present in the codebase.

---

## 11. Frontend Routing

Properties are a sub-route of Net Worth -- no standalone `/property` route exists.

| Route | Component | Context |
|---|---|---|
| `/net-worth/property` | `PropertyList` | Nested under authenticated Net Worth layout |
| `/preview/net-worth/property` | `PropertyList` | Nested under preview Net Worth layout |

**Route-to-module mapping:** `'/net-worth'` maps to the `'netWorth'` Vuex module.

The Net Worth section acts as the parent container, with property being one of several asset categories (alongside savings accounts, pensions, investments, etc.).

---

## 12. Cross-Module Integration

### 12.1 Estate Planning

**Service:** `EstateAssetAggregatorService`

- Queries properties where user is owner OR `joint_owner_id`
- Maps properties to estate assets with `property_type` for RNRB eligibility
- Mortgages mapped as liabilities with ownership share applied
- `property_type = 'main_residence'` controls Residence Nil-Rate Band (RNRB) eligibility in IHT calculations

### 12.2 Net Worth

**Service:** `NetWorthService`

- Property is one of 6 asset categories in net worth calculation
- Mortgages tracked as liabilities
- `getAssetsSummaryDetailed` queries `Property::where('user_id')` (note: primary owner only)
- `getJointAssets` returns `joint`/`tenants_in_common` properties

### 12.3 User Model

- `users.annual_rental_income` is synced on every property CRUD operation via `syncUserRentalIncome()`
- `users.properties_count` counter cache maintained in the database
- Total income calculation: employment + self-employment + **rental** + dividend + other

### 12.4 Goals Module

- `GoalsController@calculatePropertyCosts` calculates costs for property purchase goals
- `Goal` model has property-specific fields: `property_location`, `property_type`, `is_first_time_buyer`, `estimated_property_price`, `deposit_percentage`, `stamp_duty_estimate`

### 12.5 Risk Profile

- `PropertyRiskObserver` triggers `RecalculateRiskProfileJob` on property `create`/`delete` or when `current_value`, `purchase_price`, or `ownership_percentage` changes
- 5-second debounce via cache key prevents duplicate jobs

### 12.6 Retirement Module

- `IHTCalculationService` reads properties for estate projection at retirement
- Property growth projected at a configurable rate (default 3%) in estate projections

### 12.7 Coordination / Dashboard

- `netWorth` store's `syncRelatedModules` dispatches `estate/fetchEstateData` after property changes
- For married/civil partnership users, also dispatches `estate/calculateSecondDeathIHTPlanning`

---

## 13. Profile Completeness Integration

Properties count toward profile completeness via `users.properties_count` counter cache and the Net Worth module's asset tracking.

The `AreasToConsiderCard` on the dashboard checks:

```javascript
netWorth.overview.breakdown.property > 0
```

If this evaluates to `true`, the user is considered to have property data entered, and the property area is marked as addressed in their financial plan.

---

## 14. Seeder Data

**Location:** `PreviewUserSeeder` methods

### Property Creation

`createProperties(User, ?User spouse, array properties): array`

- Accepts a user, optional spouse, and array of property definitions
- Returns `propertyMap` (seeder_id to database ID mapping) for mortgage linking
- Uses single-record architecture -- no reciprocal records created for the spouse

### Mortgage Creation

`createMortgages(User, ?User spouse, array mortgages, array propertyMap): void`

- Links mortgages to properties via the `propertyMap`
- Inherits joint ownership from parent property

### Cleanup on Re-seed

```php
Property::where('user_id', $user->id)->delete();  // Cascades to mortgages
Mortgage::where('user_id', $user->id)->delete();
```

Hard deletes all existing property and mortgage records for the user before re-seeding.

---

## 15. API Routing

All routes are under `auth:sanctum` middleware.

### Property CRUD

```
GET    /api/properties                                         → PropertyController@index
POST   /api/properties                                         → PropertyController@store
GET    /api/properties/{id}                                    → PropertyController@show
PUT    /api/properties/{id}                                    → PropertyController@update
DELETE /api/properties/{id}                                    → PropertyController@destroy
```

### Property Tax Calculations

```
POST   /api/properties/calculate-sdlt                          → PropertyController@calculateSDLT
POST   /api/properties/{id}/calculate-cgt                      → PropertyController@calculateCGT
POST   /api/properties/{id}/rental-income-tax                  → PropertyController@calculateRentalIncomeTax
```

### Nested Mortgages (Scoped to Property)

```
GET    /api/properties/{propertyId}/mortgages                  → MortgageController@index
POST   /api/properties/{propertyId}/mortgages                  → MortgageController@store
PUT    /api/properties/{propertyId}/mortgages/{mortgageId}     → MortgageController@update
DELETE /api/properties/{propertyId}/mortgages/{mortgageId}     → MortgageController@destroy
```

### Standalone Mortgage Routes

```
GET    /api/mortgages/{id}                                     → MortgageController@show
PUT    /api/mortgages/{id}                                     → MortgageController@update
DELETE /api/mortgages/{id}                                     → MortgageController@destroy
GET    /api/mortgages/{id}/amortization-schedule               → MortgageController@amortizationSchedule
POST   /api/mortgages/calculate-payment                        → MortgageController@calculatePayment
```

**Total:** ~22 endpoints

---

## 16. Key Constants and Business Logic

### Single-Record Architecture

- ONE record stores the FULL property value in `current_value`
- ONE record stores the FULL mortgage balance in `outstanding_balance`
- `user_id` = primary owner (can edit/delete)
- `joint_owner_id` = secondary owner (view access only)
- `ownership_percentage` = primary owner's share (default 50 for joint, 100 for individual)
- Query pattern: `where('user_id', $id)->orWhere('joint_owner_id', $id)`

### Enumerations

| Type | Values |
|---|---|
| Property Types | `main_residence`, `secondary_residence`, `buy_to_let` |
| Ownership Types (properties) | `individual`, `joint`, `tenants_in_common`, `trust` |
| Joint Ownership Sub-types | `joint_tenancy`, `tenants_in_common` |
| Mortgage Ownership Types | `individual`, `joint` (`tenants_in_common` mapped to `joint`) |
| Tenure Types | `freehold`, `leasehold` |
| Mortgage Types | `repayment`, `interest_only`, `mixed` |
| Rate Types | `fixed`, `variable`, `tracker`, `discount`, `mixed` |

### UK Business Rules

| Rule | Detail |
|---|---|
| Leasehold < 80 years | Harder to mortgage, flagged by `isLeaseholdExpiring` accessor |
| SDLT | Banded tax from `TaxConfigService` (not hardcoded) |
| CGT residential rates (2025/26) | Basic rate 18%, higher rate 24% |
| Section 24 | Mortgage interest not directly deductible for BTL; replaced by 20% basic rate credit (Finance Act 2015, effective April 2020) |
| Annual exempt amount | From `TaxConfigService` |

### Equity Calculation

```
equity = current_value - sum(mortgages.outstanding_balance)
```

Falls back to `outstanding_mortgage` field if no mortgage records exist. Does NOT re-apply `ownership_percentage` -- values represent full amounts, and the user's share is calculated at read time by controllers.

### Rental Income Sync

After every property CRUD operation:

```
users.annual_rental_income = sum(monthly_rental_income * ownership_share) * 12
```

This ensures the user model always reflects current rental income for total income calculations used in tax rate determination.

### Risk Observer

- `PropertyRiskObserver` triggers `RecalculateRiskProfileJob` on property changes
- Monitored fields: `current_value`, `purchase_price`, `ownership_percentage`
- 5-second debounce via cache key prevents duplicate job dispatches
- Triggers on `create` and `delete` events unconditionally

### Default Mortgage Values on Create

| Field | Default |
|---|---|
| `lender_name` | `'To be completed'` |
| `mortgage_type` | `'repayment'` |
| `interest_rate` | `0` |
| `rate_type` | `'fixed'` |
| `start_date` | `now()` |
| `maturity_date` | `now() + 25 years` |
| `remaining_term_months` | `300` |

---

## 17. Known Issues and Limitations

| # | Issue | Severity | Details |
|---|---|---|---|
| 1 | No PropertyAgent | Info | Unlike all other modules, Property has no Agent layer for analysis, recommendations, or scenarios. Property data IS consumed by other agents (Estate, Coordination) but there is no PropertyAgent to generate property-specific insights. |
| 2 | Legacy `outstanding_mortgage` field | Low | Both a simple `outstanding_mortgage` field on the `properties` table AND a full `mortgages` table exist. The equity accessor falls back to the simple field. Could lead to stale data if mortgage records are updated but `outstanding_mortgage` is not. |
| 3 | Legacy annual cost fields in DB | Low | `annual_service_charge`, `annual_ground_rent`, `annual_insurance`, `annual_maintenance_reserve`, `other_annual_costs` exist in the database but are NOT in the model's fillable array. Replaced by monthly equivalents. |
| 4 | Legacy `PropertyForm.vue` | Low | An older `PropertyForm.vue` exists in `NetWorth/` alongside the newer `Property/PropertyForm.vue`. Potential confusion about which is active. |
| 5 | PropertyList fetches directly via API | Medium | `PropertyList.vue` uses direct `api.get('/properties')` instead of dispatching a Vuex action. This bypasses the store for the main list view, although detail views do use the store. |
| 6 | Missing `monthly_interest_portion` | Medium | Section 24 BTL tax calculation requires `monthly_interest_portion` for repayment/mixed mortgages. If this field is null, the tax position calculation flags it via `has_interest_portion_missing` but may produce inaccurate results. |
| 7 | Net Worth summary queries only primary owner | Low | `NetWorthService.getAssetsSummaryDetailed` queries `where('user_id')` only, potentially missing properties where the user is `joint_owner_id`. The main properties index query correctly uses `orWhere`. |
| 8 | No soft deletes | Low | Properties and mortgages use hard deletes with no audit trail for deleted records. |
| 9 | Hardcoded CGT rates | Medium | CGT rates (18%/24%) are partially hardcoded in `PropertyTaxService`. SDLT uses `TaxConfigService` but the CGT rate selection logic contains hardcoded basic/higher rate percentages. |
| 10 | `mortgage_account_number` not encrypted | Low | Unlike `savings_accounts.account_number` which uses `Crypt` encryption, `mortgage_account_number` is stored in plain text. |

---

## 18. Tax Calculations System (SDLT, CGT, Section 24 Rental Income Tax)

### 18.1 SDLT (Stamp Duty Land Tax)

**Source:** `PropertyTaxService.calculateSDLT()`
**Config:** `TaxConfigService.getStampDuty()` (reads from `tax_configurations` table)

Three rate structures:

| Structure | Applies When |
|---|---|
| Standard rates | Default for existing homeowners |
| First-time buyer rates | `isFirstHome = true` AND price is at or below `max_property_value` threshold; otherwise falls back to standard |
| Additional property rates | `property_type` is `secondary_residence` or `buy_to_let`; higher surcharge bands |

**Calculation Engine:**

Uses a generic banded tax engine (`calculateBandedTax`). For each band, tax is calculated as `(amount in band) * rate`. Returns:

| Field | Description |
|---|---|
| `purchase_price` | Input purchase price |
| `property_type` | Input property type |
| `is_first_home` | Input first-time buyer flag |
| `total_sdlt` | Total stamp duty payable |
| `effective_rate` | `total_sdlt / purchase_price * 100` |
| `bands[]` | Per-band breakdown (threshold, rate, amount taxed, tax in band) |

### 18.2 CGT (Capital Gains Tax)

**Source:** `PropertyTaxService.calculateCGT()`
**Config:** `TaxConfigService.getCapitalGainsTax()` and `TaxConfigService.getIncomeTax()`

**Calculation Steps:**

1. **Acquisition cost** = `purchase_price + sdlt_paid`
2. **Gross gain** = `disposal_price - acquisition_cost - disposal_costs`
3. **Taxable gain** = `gross_gain - annual_exempt_amount`
4. **Rate determination**: if user total income exceeds `basic_rate_threshold` then higher rate (24%); else basic rate (18%)
5. **CGT liability** = `taxable_gain * rate`

**Returns:**

| Field | Description |
|---|---|
| `disposal_price` | Input disposal price |
| `acquisition_cost` | Purchase price + SDLT paid |
| `disposal_costs` | Input disposal costs (solicitor, agent fees) |
| `gain` | Net gain after all deductions |
| `gross_gain` | Gain before annual exempt amount |
| `annual_exempt_amount` | From TaxConfigService |
| `taxable_gain` | Amount subject to CGT |
| `cgt_rate` | 18% or 24% |
| `cgt_liability` | Tax payable |
| `effective_rate` | `cgt_liability / disposal_price * 100` |

**Note:** Main residence is exempt from CGT via Private Residence Relief. This is not explicitly enforced in the calculation -- the controller allows any property type to be submitted for CGT calculation.

### 18.3 Rental Income Tax (Section 24)

Two complementary calculations exist for rental income tax.

#### PropertyService.calculateTaxPosition() -- Per-Property Monthly Breakdown

This provides a granular monthly breakdown per individual property:

1. **Monthly taxable income** = `rental_income - non-mortgage allowable costs`
2. **Non-mortgage allowable costs**: `gas + electricity + water + building_insurance + contents_insurance + service_charge + ground_rent + managing_agent_fee`
3. **Mortgage interest by type**:
   - `interest_only`: full monthly payment
   - `repayment`: `monthly_interest_portion` field
   - `mixed`: `interest_only_percentage * payment + monthly_interest_portion`
4. **Section 24 credit** = `monthly mortgage interest * 20%`
5. Applies ownership multiplier for joint properties

Returns: `property_name`, `monthly_taxable_income`, `annual_taxable_income`, `monthly_mortgage_interest`, `section_24_monthly_credit`, `section_24_annual_credit`, `monthly_allowable_costs`, `ownership_percentage`, `has_interest_portion_missing`

#### PropertyTaxService.calculateRentalIncomeTax() -- Annual with Marginal Rate

This provides an annual view incorporating the user's marginal tax rate:

1. **Annual rental income** = `monthly_rental_income * 12`
2. **Allowable expenses** from annual fields
3. **Mortgage interest** = `outstanding_balance * (interest_rate / 100)`
4. **20% basic rate relief** on mortgage interest
5. **Marginal tax rate** determined from user's total income
6. Returns nested breakdown structure

### UK Section 24 Rule

Since April 2020, landlords cannot deduct mortgage interest as an expense from rental income. Instead, they receive a 20% basic rate tax credit on mortgage interest payments (Section 24, Finance Act 2015). This means:

- Higher-rate and additional-rate taxpayers pay more tax than under the old system
- Basic-rate taxpayers are unaffected (20% deduction replaced by 20% credit)
- The credit is calculated at the basic rate regardless of the taxpayer's marginal rate

### Data Flow

```
PropertyTaxCalculator.vue (user inputs disposal price / views rental tax)
  -> Vuex netWorth actions (calculateCGT / calculateRentalIncomeTax / calculateSDLT)
  -> propertyService.js API calls
  -> PropertyController (orchestrates, loads property + mortgages)
  -> PropertyTaxService (SDLT, CGT, annual rental)
  -> PropertyService.calculateTaxPosition() (monthly BTL breakdown)
  -> TaxConfigService (reads tax_configurations table for rates/bands)
  -> Response returned to frontend for display
```
