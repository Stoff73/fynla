# Estate Planning Module - Current State Documentation

**Last Updated:** 2026-02-18
**Module Version:** Part of Fynla v0.7.0
**Status:** Fully functional with IHT calculation (current + projected), gifting strategies, trust planning, will management, life policy strategy, comprehensive estate plan generation, intestacy calculator, and Letter to Spouse

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
18. [IHT Mitigation Decision Tree](#18-iht-mitigation-decision-tree)

---

## 1. System Overview

The Estate Planning module covers Inheritance Tax (IHT) planning, gifting strategies, trust structures, will management, life policy strategy, and comprehensive estate plan generation. It is the most complex module in the application, integrating data from Properties, Investments, Savings, Pensions, Business Interests, Chattels, Mortgages, and Protection modules.

### Sub-Modules

| Sub-Module | Controllers | Purpose |
|---|---|---|
| IHT Calculation | `IHTController` | Current + projected IHT liability with NRB/RNRB/charitable rate |
| Assets & Liabilities | `EstateController` | Manual estate assets, liabilities, investment account integration |
| Gifting Strategy | `GiftingController` | PET cycles, annual exemptions, personalized asset-based strategies |
| Trust Planning | `TrustController` | 9 trust types, periodic charges, recommendations |
| Will & Bequests | `WillController` | Will management, bequests, intestacy calculator |
| Life Policy | `LifePolicyController` | Whole of Life vs Self-Insurance comparison |
| Comprehensive Plan | `EstateController` | Full estate plan combining all strategies |
| Net Worth | `EstateController` | Net worth analysis with concentration risk |
| Cash Flow | `EstateController` | Personal P&L by tax year |
| Letter to Spouse | `LetterToSpouseController` | 36-field practical guidance document |

### Architecture Flow

```
Vue Components → estateService.js → API Controllers → Agent/Services → Models → DB
                                                    ↓
                                          EstateAssetAggregatorService
                                          (cross-module asset gathering)
                                                    ↓
                                    Properties, Investments, Savings,
                                    Pensions, Business, Chattels, Mortgages
```

### Key Statistics

| Metric | Count |
|---|---|
| Backend Services | 21 |
| Controllers | 6 (1 main + 5 sub-controllers) |
| Models | 10 (9 in Estate/ + LetterToSpouse) |
| Vue Components | 30 + 2 views |
| API Endpoints | ~45 |
| Trust Types | 9 |
| Gift Types | 5 |
| Asset Types (manual) | 8 |
| Liability Types | 9 |

---

## 2. Database Schema

### 2.1 `assets` Table

```sql
CREATE TABLE assets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  asset_type ENUM('property','pension','investment','savings','business','life_insurance','personal','other'),
  asset_name VARCHAR(255),
  current_value DECIMAL(15,2),
  ownership_type ENUM('individual','joint','trust'),
  beneficiary_designation VARCHAR(255),
  is_iht_exempt TINYINT(1) DEFAULT 0,
  exemption_reason VARCHAR(255),
  valuation_date DATE,
  -- Indexes: user_id
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 2.2 `gifts` Table

```sql
CREATE TABLE gifts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  gift_date DATE NOT NULL,
  recipient VARCHAR(255) NOT NULL,
  gift_type ENUM('pet','clt','exempt','small_gift','annual_exemption'),
  gift_value DECIMAL(15,2) NOT NULL,
  status ENUM('within_7_years','survived_7_years') DEFAULT 'within_7_years',
  taper_relief_applicable TINYINT(1) DEFAULT 0,
  notes TEXT,
  -- Indexes: user_id, gift_date, composite(user_id, gift_date)
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 2.3 `trusts` Table

```sql
CREATE TABLE trusts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  household_id BIGINT UNSIGNED,
  trust_name VARCHAR(255) NOT NULL,
  trust_type ENUM('bare','interest_in_possession','discretionary','accumulation_maintenance',
                  'life_insurance','discounted_gift','loan','mixed','settlor_interested'),
  trust_creation_date DATE NOT NULL,
  initial_value DECIMAL(15,2) NOT NULL,
  current_value DECIMAL(15,2) NOT NULL,
  last_valuation_date DATE,
  discount_amount DECIMAL(15,2),          -- Actuarial discount for retained income
  retained_income_annual DECIMAL(15,2),   -- Annual income retained by settlor
  loan_amount DECIMAL(15,2),              -- Outstanding loan balance
  loan_interest_bearing TINYINT(1) DEFAULT 0,
  loan_interest_rate DECIMAL(5,4),
  sum_assured DECIMAL(15,2),              -- Life insurance policy sum assured
  annual_premium DECIMAL(15,2),
  is_relevant_property_trust TINYINT(1) DEFAULT 0,  -- Subject to 10-year periodic charges
  last_periodic_charge_date DATE,
  last_periodic_charge_amount DECIMAL(15,2),
  next_tax_return_due DATE,
  total_asset_value DECIMAL(15,2),
  beneficiaries TEXT,
  trustees TEXT,
  settlor VARCHAR(255),
  purpose TEXT,
  notes TEXT,
  is_active TINYINT(1) DEFAULT 1,
  -- Indexes: user_id, trust_type, is_relevant_property_trust, household_id
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (household_id) REFERENCES households(id) ON DELETE SET NULL
);
```

### 2.4 `wills` Table

```sql
CREATE TABLE wills (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  has_will TINYINT(1) DEFAULT 0,
  death_scenario ENUM('user_only','both_simultaneous') DEFAULT 'user_only',
  spouse_primary_beneficiary TINYINT(1) DEFAULT 1,
  spouse_bequest_percentage DECIMAL(5,2) DEFAULT 100.00,
  executor_name VARCHAR(255),
  executor_notes TEXT,
  will_last_updated DATE,
  -- Index: user_id
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 2.5 `bequests` Table

```sql
CREATE TABLE bequests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  will_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  beneficiary_name VARCHAR(255) NOT NULL,
  beneficiary_user_id BIGINT UNSIGNED,
  bequest_type ENUM('percentage','specific_amount','specific_asset','residuary') DEFAULT 'percentage',
  percentage_of_estate DECIMAL(5,2),
  specific_amount DECIMAL(15,2),
  specific_asset_description VARCHAR(255),
  asset_id BIGINT UNSIGNED,
  priority_order INT DEFAULT 1,
  conditions TEXT,
  -- Indexes: composite(will_id, priority_order), user_id, beneficiary_user_id
  FOREIGN KEY (will_id) REFERENCES wills(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (beneficiary_user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

### 2.6 `iht_profiles` Table

```sql
CREATE TABLE iht_profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  marital_status ENUM('single','married','widowed','divorced'),
  has_spouse TINYINT(1) DEFAULT 0,
  own_home TINYINT(1) DEFAULT 0,
  home_value DECIMAL(15,2),
  nrb_transferred_from_spouse DECIMAL(15,2) DEFAULT 0.00,
  charitable_giving_percent DECIMAL(5,2) DEFAULT 0.00,
  -- Index: user_id
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 2.7 `iht_calculations` Table (Cache)

```sql
CREATE TABLE iht_calculations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  -- Current values (9 fields)
  user_gross_assets DECIMAL(15,2), spouse_gross_assets DECIMAL(15,2), total_gross_assets DECIMAL(15,2),
  user_total_liabilities DECIMAL(15,2), spouse_total_liabilities DECIMAL(15,2), total_liabilities DECIMAL(15,2),
  user_net_estate DECIMAL(15,2), spouse_net_estate DECIMAL(15,2), total_net_estate DECIMAL(15,2),
  -- Allowances
  nrb_available DECIMAL(15,2), nrb_message TEXT,
  rnrb_available DECIMAL(15,2), rnrb_status ENUM('full','tapered','none'), rnrb_message TEXT,
  total_allowances DECIMAL(15,2),
  -- Results
  taxable_estate DECIMAL(15,2), iht_liability DECIMAL(15,2), effective_rate DECIMAL(5,2),
  -- Projected values
  projected_gross_assets DECIMAL(15,2), projected_liabilities DECIMAL(15,2),
  projected_net_estate DECIMAL(15,2), projected_taxable_estate DECIMAL(15,2),
  projected_iht_liability DECIMAL(15,2),
  years_to_death SMALLINT UNSIGNED, estimated_age_at_death TINYINT UNSIGNED,
  -- Metadata
  calculation_date TIMESTAMP, is_married TINYINT(1), data_sharing_enabled TINYINT(1),
  assets_hash VARCHAR(64), liabilities_hash VARCHAR(64),
  -- Indexes: composite(user_id, calculation_date), assets_hash, liabilities_hash
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 2.8 `net_worth_statements` Table

```sql
CREATE TABLE net_worth_statements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  statement_date DATE NOT NULL,
  total_assets DECIMAL(15,2), total_liabilities DECIMAL(15,2), net_worth DECIMAL(15,2),
  -- Indexes: user_id, composite(user_id, statement_date)
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 2.9 `letters_to_spouse` Table

36 fields across 4 parts: immediate actions (executor/attorney/advisor contacts), account access (bank/investment/insurance/crypto), long-term plans (estate docs/education/financial guidance), funeral wishes. All TEXT fields.

### 2.10 `liabilities` Table (Estate Module)

15 fields. Types: `mortgage`, `secured_loan`, `personal_loan`, `credit_card`, `overdraft`, `hire_purchase`, `student_loan`, `business_loan`, `other`. Supports `HasJointOwnership` trait with `joint_owner_id` and `ownership_percentage`. Has `trust_id` FK.

---

## 3. Models

### 3.1 `Estate\IHTProfile` (`app/Models/Estate/IHTProfile.php`, 45 lines)

**Fillable:** `user_id`, `marital_status`, `has_spouse`, `own_home`, `home_value`, `nrb_transferred_from_spouse`, `rnrb_transferred_from_spouse`, `charitable_giving_percent`

**Casts:** All financial fields cast to `float`.

**Relationships:** BelongsTo `User`.

### 3.2 `Estate\Will` (`app/Models/Estate/Will.php`, 59 lines)

**Fillable:** `user_id`, `has_will`, `spouse_primary_beneficiary`, `spouse_bequest_percentage`, `executor_name`, `executor_notes`, `will_last_updated`

**Relationships:** HasMany `Bequest`.

**Key Method:** `getNonSpouseAllocationPercentage()` - sums percentage bequests to determine what's allocated beyond spouse.

### 3.3 `Estate\Bequest` (`app/Models/Estate/Bequest.php`, 112 lines)

**Fillable (14):** `will_id`, `user_id`, `beneficiary_name`, `beneficiary_user_id`, `bequest_type`, `percentage_of_estate`, `specific_amount`, `specific_asset_description`, `asset_id`, `priority_order`, `conditions`, `beneficiary_type`, `charity_registration_number`

**Key Method:** `isCharitable()` - detects charities via `beneficiary_type='charity'`, `charity_registration_number` presence, or name keywords (13 charity indicators: foundation, trust, charity, hospice, nhs, cancer, heart, red cross, oxfam, unicef, rspca, wwf, salvation army).

**Relationships:** BelongsTo `Will`, `User`, `beneficiaryUser`.

### 3.4 `Estate\Trust` (`app/Models/Estate/Trust.php`, 128 lines)

**Fillable (26):** All trust-specific fields including `discount_amount`, `retained_income_annual`, `loan_amount`, `sum_assured`, `annual_premium`, `is_relevant_property_trust`.

**Key Method:** `getIHTValue()` - returns trust-type-specific IHT treatment:

| Trust Type | IHT Value | Reason |
|---|---|---|
| `bare` | 0 | PET treatment - outside estate after 7 years |
| `discretionary` | 0 | Relevant property trust - periodic charges instead |
| `accumulation_maintenance` | 0 | Relevant property trust - periodic charges instead |
| `interest_in_possession` | `current_value` | Beneficiary treated as owning trust assets |
| `discounted_gift` | `discount_amount` | Only discounted portion remains in estate |
| `loan` | `loan_amount` | Only outstanding loan remains in estate |
| `life_insurance` | 0 | Written in trust - outside estate |
| `mixed` | `current_value` | Conservative - full value in estate |
| `settlor_interested` | `current_value` | Settlor retains interest |

**Key Method:** `isRelevantPropertyTrust()` - returns true for `discretionary` and `accumulation_maintenance` (subject to 10-year periodic charges up to 6%).

**Relationships:** BelongsTo `User`, `Household`.

### 3.5 `Estate\Gift` (`app/Models/Estate/Gift.php`, 40 lines)

**Fillable (8):** `user_id`, `gift_date`, `recipient`, `gift_type`, `gift_value`, `status`, `taper_relief_applicable`, `notes`

**Gift Types:**

| Type | Treatment |
|---|---|
| `pet` | Potentially Exempt Transfer - exempt after 7 years |
| `clt` | Chargeable Lifetime Transfer - 20% immediate charge |
| `exempt` | Fully exempt (spouse, charity) |
| `small_gift` | £250 per recipient per year |
| `annual_exemption` | £3,000 per donor per year |

### 3.6 `Estate\Asset` (`app/Models/Estate/Asset.php`, 47 lines)

**Fillable (12):** Including `liquidity`, `is_giftable`, `not_giftable_reason`, `is_main_residence`, `ownership_type`, `is_iht_exempt`, `exemption_reason`.

**Asset Types:** `property`, `pension`, `investment`, `savings`, `business`, `life_insurance`, `personal`, `other`.

### 3.7 `Estate\Liability` (`app/Models/Estate/Liability.php`, 68 lines)

**Fillable (15):** Including `liability_type`, `liability_name`, `current_balance`, `monthly_payment`, `interest_rate`, `maturity_date`, `secured_against`, `is_priority_debt`, `mortgage_type`, `fixed_until`.

**Traits:** Uses `HasJointOwnership` for joint liability support.

**Relationships:** BelongsTo `User`, `jointOwner`, `Trust`.

### 3.8 `Estate\IHTCalculation` (`app/Models/Estate/IHTCalculation.php`, 81 lines)

26 fillable fields for cached calculation snapshots. Stores both current and projected values with hash-based cache invalidation.

### 3.9 `Estate\NetWorthStatement` (`app/Models/Estate/NetWorthStatement.php`, 38 lines)

4 fillable fields: `user_id`, `statement_date`, `total_assets`, `total_liabilities`, `net_worth`. For historical net worth tracking.

### 3.10 `LetterToSpouse` (`app/Models/LetterToSpouse.php`, 71 lines)

36 fillable fields across 4 parts:
- **Part 1 (Immediate Actions):** executor contacts, attorney contacts, advisor contacts
- **Part 2 (Account Access):** bank, investment, insurance, crypto details
- **Part 3 (Long-term Plans):** estate docs, education plans, financial guidance
- **Part 4 (Funeral Wishes):** burial/cremation preferences, ceremony details

---

## 4. Controllers

### 4.1 `EstateController` (`app/Http/Controllers/Api/EstateController.php`, 491 lines)

**Main controller** handling core estate data and CRUD operations.

| Method | Route | Purpose |
|---|---|---|
| `index()` | `GET /estate` | Returns all estate data: assets + investment_accounts + liabilities + gifts + trusts + iht_profile |
| `getComprehensiveEstatePlan()` | `GET /estate/comprehensive-plan` | Full estate plan via `ComprehensiveEstatePlanService` |
| `getNetWorth()` | `GET /estate/net-worth` | Net worth analysis via `NetWorthAnalyzer` |
| `getCashFlow()` | `GET /estate/cash-flow` | Cash flow via `CashFlowProjector` (tax year param) |
| `storeAsset()` | `POST /estate/assets` | Create asset (inline validation, 8 types) |
| `updateAsset()` | `PUT /estate/assets/{id}` | Update asset |
| `destroyAsset()` | `DELETE /estate/assets/{id}` | Delete asset |
| `storeLiability()` | `POST /estate/liabilities` | Create liability (inline validation, 9 types) |
| `updateLiability()` | `PUT /estate/liabilities/{id}` | Update liability |
| `destroyLiability()` | `DELETE /estate/liabilities/{id}` | Delete liability |
| `storeGift()` | `POST /estate/gifts` | Create gift (5 types) |
| `updateGift()` | `PUT /estate/gifts/{id}` | Update gift |
| `destroyGift()` | `DELETE /estate/gifts/{id}` | Delete gift |

**Investment Integration:** `index()` pulls `InvestmentAccount` records and formats them with IHT exemption flags. VCT/EIS accounts are flagged as potentially exempt via Business Relief.

**Cache Invalidation:** All CRUD operations call `Cache::forget("estate_analysis_{$user->id}")`.

### 4.2 `IHTController` (`app/Http/Controllers/Api/Estate/IHTController.php`, 242 lines)

**Unified IHT calculation** for single/married/widowed scenarios.

| Method | Route | Purpose |
|---|---|---|
| `calculateIHT()` | `POST /estate/calculate-iht` | Main IHT calculation (current + projected) |
| `storeOrUpdateIHTProfile()` | `POST /estate/profile` | Create/update IHT profile (6 fields) |
| `calculateSecondDeathIHTPlanning()` | `POST /estate/calculate-second-death-iht-planning` | Deprecated alias for `calculateIHT()` |

**Returns:** `calculation`, `assets_breakdown`, `liabilities_breakdown`, `iht_summary` (current + projected), `will_info`, `cash_projection_breakdown`.

**Spouse Logic:** Detects spouse via `user->spouse_id`, checks `hasAcceptedSpousePermission()` for data sharing.

**Life Cover Integration:** Queries `LifeInsurancePolicy` where `in_trust=true` to show existing IHT cover.

### 4.3 `WillController` (`app/Http/Controllers/Api/Estate/WillController.php`, 256 lines)

| Method | Route | Purpose |
|---|---|---|
| `getWill()` | `GET /estate/will` | Get will (auto-creates default: married→100% to spouse) |
| `storeOrUpdateWill()` | `POST /estate/will` | Create/update will via `StoreWillRequest` |
| `getBequests()` | `GET /estate/bequests` | List bequests ordered by `priority_order` |
| `storeBequest()` | `POST /estate/bequests` | Create bequest (auto-increment priority) |
| `updateBequest()` | `PUT /estate/bequests/{id}` | Update bequest |
| `deleteBequest()` | `DELETE /estate/bequests/{id}` | Delete bequest (returns 204) |
| `calculateIntestacy()` | `POST /estate/calculate-intestacy` | UK intestacy distribution via `IntestacyCalculator` |
| `getUpcomingTaxReturns()` | `GET /estate/trusts/upcoming-tax-returns` | Trust periodic charge dates |

### 4.4 `TrustController` (`app/Http/Controllers/Api/Estate/TrustController.php`, 331 lines)

| Method | Route | Purpose |
|---|---|---|
| `getTrusts()` | `GET /estate/trusts` | List all user trusts |
| `createTrust()` | `POST /estate/trusts` | Create trust (9 types, auto-sets `is_relevant_property_trust`) |
| `updateTrust()` | `PUT /estate/trusts/{id}` | Update trust |
| `deleteTrust()` | `DELETE /estate/trusts/{id}` | Delete trust |
| `analyzeTrust()` | `GET /estate/trusts/{id}/analyze` | Efficiency analysis via `TrustService` |
| `getTrustAssets()` | `GET /estate/trusts/{id}/assets` | Assets held in trust |
| `calculateTrustIHTImpact()` | `POST /estate/trusts/{id}/calculate-iht-impact` | IHT impact with periodic charges |
| `getTrustRecommendations()` | `GET /estate/trust-recommendations` | Trust recommendations based on estate |

### 4.5 `GiftingController` (`app/Http/Controllers/Api/Estate/GiftingController.php`, 422 lines)

| Method | Route | Purpose |
|---|---|---|
| `getPlannedGiftingStrategy()` | `GET /estate/gifts/planned-strategy` | PET cycles, annual exemptions, gifting schedule |
| `getPersonalizedGiftingStrategy()` | `GET /estate/gifts/personalized-strategy` | Asset-based strategy with liquidity analysis |
| `getPersonalizedTrustStrategy()` | `GET /estate/gifts/trust-strategy` | CLT taxation with 20% lifetime charge |
| `calculateDiscountedGiftDiscount()` | `POST /estate/calculate-discount` | Actuarial discount calculation |

**Critical Rule:** Lifetime gifting only uses user's own NRB (£325k), NOT spouse transferred NRB. Spouse's NRB is only available on death.

### 4.6 `LifePolicyController` (`app/Http/Controllers/Api/Estate/LifePolicyController.php`, 135 lines)

| Method | Route | Purpose |
|---|---|---|
| `getLifePolicyStrategy()` | `GET /estate/life-policy-strategy` | Whole of Life vs Self-Insurance comparison |

Requires `date_of_birth` + `gender`. Uses CURRENT IHT liability (not projected). Joint policy calculation for married users.

---

## 5. Agent

### `EstateAgent` (`app/Agents/EstateAgent.php`, 761 lines)

The core orchestrator for estate planning analysis.

**`analyze(User $user)`:** Main analysis method with cache (`estate_analysis_{userId}`, tagged `['estate', 'user_{userId}']`).

Loads user with: `ihtProfile`, `assets`, `properties`, `liabilities`, `mortgages`, `spouse`, `familyMembers`, `trusts`, `gifts`.

**`generateRecommendations()`:** 7-step IHT mitigation decision tree (see Section 18).

**`buildScenarios()`:** 5 scenario types:

| Scenario | Description |
|---|---|
| `current` | Current estate with no changes |
| `optimized` | With all recommended strategies applied |
| `gifting` | After implementing PET gifting strategy |
| `property_downsizing` | After downsizing main residence |
| `trust_creation` | After establishing recommended trusts |

**`calculateEstateHealthScore()`:** Score 0-100 with deductions:

| Condition | Deduction |
|---|---|
| Missing IHT profile | -20 |
| IHT ratio > 20% of estate | -10 to -25 (graduated) |
| No trusts when estate > £650k | -10 |
| Missing spouse link (married) | -5 |
| Liquidity risk (< 10% liquid) | -15 |

---

## 6. Services

### 6.1 `IHTCalculationService` (`app/Services/Estate/IHTCalculationService.php`, 1290 lines)

**The primary IHT calculation engine.** Handles current + projected calculations with asset-specific projection methods.

**`calculate(User $user, ?User $spouse, bool $dataSharingEnabled)`:**

1. Check cache (hash-based invalidation via `assets_hash` + `liabilities_hash`)
2. Get tax config from `TaxConfigService`
3. Gather assets via `EstateAssetAggregatorService` (excludes IHT-exempt)
4. Calculate liabilities
5. Calculate NRB (single: £325k, married: £650k, widowed: £325k + transferred)
6. Calculate RNRB (requires main residence, tapers above £2M)
7. Calculate taxable estate and IHT (charitable 36% rate if 10%+ to charity)
8. Calculate PROJECTED values at death (asset-specific methods)
9. Save to `iht_calculations` table

**Projection Methods:**

| Asset Class | Method | Details |
|---|---|---|
| Cash | Income/expense surplus model | Pre-retirement: employment income - expenses. Post-retirement: pension income - retirement expenses. Fallback: 70% of income if no profile. |
| Investments | Monte Carlo (p20) or custom rate | Integrated drawdown: cash deficits drawn from investments before growth applied each year. Default fallback: 4.7%. |
| Properties | Compound growth | Configurable rate (default 3% p.a.) |
| Liabilities | Linear amortisation | Assumes cleared by end date or retirement age |
| Chattels/Business | Current value | No appreciation modelled |

**Married Couple Logic:** Projects to SECOND DEATH (whoever lives longer) using actuarial life tables.

**Life Expectancy:** Uses `actuarial_life_tables` database table. Fallback: `85 - current_age`.

### 6.2 `EstateAssetAggregatorService` (`app/Services/Estate/EstateAssetAggregatorService.php`, 300 lines)

**Cross-module asset gathering** using single-record joint ownership pattern.

**`gatherUserAssets(User $user)`** aggregates from 8 sources:

| Source | Asset Type | IHT Exempt? | Notes |
|---|---|---|---|
| `Asset` (manual) | Various | Per record | Manual estate assets |
| `InvestmentAccount` | `investment` | No (ISAs NOT exempt) | Uses `CalculatesOwnershipShare` trait |
| `Property` | `property` | No | Includes `property_type` for RNRB |
| `SavingsAccount` | `cash` | No (cash ISAs NOT exempt) | All savings treated as cash |
| `BusinessInterest` | `business` | Yes if BPR eligible | 100% relief for qualifying trading 2+ years |
| `Chattel` | `chattel` | No | Personal valuables |
| `DCPension` | `dc_pension` | Yes | Outside estate if beneficiary nominated |
| `DBPension` | `db_pension` | Yes, value=0 | No IHT value (die with member) |

**`calculateUserLiabilities(User $user)`:** Aggregates from `Liability` + `Mortgage` models using joint ownership share calculation.

**Joint Ownership Query Pattern:** `where('user_id', $id)->orWhere('joint_owner_id', $id)` with `calculateUserShare()` trait.

### 6.3 `IHTCalculator` (`app/Services/Estate/IHTCalculator.php`, 209 lines)

Lower-level IHT calculation service handling NRB/RNRB calculation, charitable rate reduction, PET liability with taper relief, and RNRB eligibility checking.

**Taper Relief Schedule:** PET gifts 3-7 years before death get graduated relief from taper_relief config in `TaxConfigService`.

### 6.4 `ComprehensiveEstatePlanService` (`app/Services/Estate/ComprehensiveEstatePlanService.php`, 1199 lines)

Generates complete estate plans combining all strategies.

**`generateComprehensiveEstatePlan(User $user)` returns:**

| Section | Content |
|---|---|
| `plan_metadata` | Date, version, user name, completeness score, plan type (Personalised/Generic) |
| `completeness_warning` | Missing fields with severity (critical/warning/success) |
| `executive_summary` | Net estate, IHT liability (current + projected), key actions |
| `user_profile` | Name, age, gender, marital status, children, step-children (deduped with spouse) |
| `balance_sheet` | User/spouse/combined assets by type, liabilities, net worth |
| `estate_overview` | Asset breakdown by type with detailed listings |
| `estate_breakdown` | Separate user/spouse/combined with detailed liabilities |
| `current_iht_position` | NOW vs PROJECTED scenarios with all allowances |
| `gifting_strategy` | From `PersonalizedGiftingStrategyService` |
| `trust_strategy` | From `PersonalizedTrustStrategyService` |
| `life_policy_strategy` | Whole of Life recommendation with premium estimate |
| `optimized_recommendation` | Combined strategy with 4 priority levels |
| `implementation_timeline` | Actions with timeframes |
| `next_steps` | Immediate/Short-term/Medium-term/Long-term actions |

**Optimized Strategy Priorities:**
1. Immediate (annual exemption + trust within NRB)
2. Medium-term (PET gifting cycles, years 1-7)
3. Life insurance for remaining liability
4. Long-term property planning (downsizing)

### 6.5 `PersonalizedGiftingStrategyService` (`app/Services/Estate/PersonalizedGiftingStrategyService.php`, 411 lines)

Generates personalized gifting strategies based on actual asset liquidity.

**Strategy Priorities:**
1. **Annual Exemption** (£3,000/year, immediately exempt)
2. **Liquid Asset PETs** (cash/investments, 7-year cycles)
3. **Property Gifts** (rental/second homes)
4. **Main Residence Strategy** (downsizing)

Uses `AssetLiquidityAnalyzer` to classify assets as liquid/semi-liquid/illiquid.

### 6.6 `GiftingStrategy` (`app/Services/Estate/GiftingStrategy.php`, 247 lines)

Handles PET analysis, annual exemption calculation (with carry-forward), small gifts validation (£250/recipient), wedding gift allowances, and optimal strategy recommendations.

### 6.7 `TrustService` (`app/Services/Estate/TrustService.php`, 362 lines)

Trust analysis and recommendations. Calculates periodic charges (up to 6% of trust value above NRB every 10 years), trust efficiency analysis, and type-specific income tax/CGT rates.

**Trust Tax Rates by Type:**
- **Bare:** Taxed as beneficiary's income
- **Interest in Possession:** Income tax 20%/8.75% dividend
- **Discretionary/Accumulation:** Income tax 45%/39.35% dividend, CGT 24%
- **Settlor Interested:** Taxed as settlor's income
- **Life Insurance:** No regular income

### 6.8 `IntestacyCalculator` (`app/Services/Estate/IntestacyCalculator.php`, 293 lines)

Calculates UK intestacy distribution with decision tree logic.

**Distribution Hierarchy:**
1. Married with children, estate > £250k → Spouse gets first £250k + half remainder, children share other half
2. Married with children, estate ≤ £250k → Spouse gets all
3. Married without children → Spouse gets all
4. Not married with children → Children share equally
5. No children → Parents → Siblings → Half-siblings → Grandparents → Aunts/uncles → Half-blood aunts/uncles → The Crown

Returns `scenario`, `explanation`, `beneficiaries`, `decision_path`, `goes_to_crown`.

### 6.9 `NetWorthAnalyzer` (`app/Services/Estate/NetWorthAnalyzer.php`, 374 lines)

Net worth calculation using `CrossModuleAssetAggregator`. Provides asset/liability composition analysis, concentration risk identification (>50% = High, >30% = Medium), historical trend tracking, and health score (0-100).

### 6.10 `AssetLiquidityAnalyzer` (`app/Services/Estate/AssetLiquidityAnalyzer.php`, 261 lines)

Classifies assets for gifting strategy:
- **Liquid:** Cash, investments (immediately giftable)
- **Semi-liquid:** Rental properties, second homes, business, chattels (giftable with planning)
- **Illiquid:** Main residence (cannot gift - gift with reservation of benefit)

Includes gifting considerations per asset type (CGT, SDLT, ISA status loss, etc.).

### 6.11 Other Services

| Service | Lines | Purpose |
|---|---|---|
| `IHTFormattingService` | ~200 | Formats IHT calculation results for display |
| `IHTStrategyGeneratorService` | ~300 | Generates IHT mitigation strategy recommendations |
| `GiftingStrategyOptimizer` | ~200 | Optimizes gifting amounts across PET cycles |
| `GiftingTimelineService` | ~150 | Generates gifting timeline visualisation data |
| `PersonalizedTrustStrategyService` | ~300 | CLT taxation, trust recommendations |
| `SpouseNRBTrackerService` | ~150 | Tracks NRB/RNRB transfers from deceased spouse |
| `LifeCoverCalculator` | ~200 | Premium estimation for life cover |
| `LifePolicyStrategyService` | ~250 | Whole of Life vs Self-Insurance comparison |
| `FutureValueCalculator` | ~100 | Compound growth calculations |
| `CashFlowProjector` | ~200 | Personal P&L by tax year |
| `WillAnalysisService` | ~150 | Will analysis and recommendations |

---

## 7. Validation Requests

### 7.1 `StoreWillRequest`

```php
'has_will' => 'nullable|boolean',
'spouse_primary_beneficiary' => 'boolean',
'spouse_bequest_percentage' => 'nullable|numeric|min:0|max:100',
'executor_name' => 'nullable|string|max:255',
'executor_notes' => 'nullable|string',
'will_last_updated' => 'nullable|date',
```

### 7.2 `StoreBequestRequest`

```php
'beneficiary_name' => 'required|string|max:255',
'beneficiary_user_id' => 'nullable|exists:users,id',
'bequest_type' => 'required|in:percentage,specific_amount,specific_asset,residuary',
'percentage_of_estate' => 'nullable|numeric|min:0|max:100',
'specific_amount' => 'nullable|numeric|min:0',
'specific_asset_description' => 'nullable|string',
'asset_id' => 'nullable|exists:assets,id',
'priority_order' => 'nullable|integer|min:1',
'conditions' => 'nullable|string',
```

### 7.3 `UpdateBequestRequest`

Same as Store but with `sometimes` instead of `required`.

### 7.4 `CalculateIntestacyRequest`

```php
'estate_value' => 'nullable|numeric|min:0',
```

### 7.5 Inline Validation (EstateController)

Assets, liabilities, and gifts use inline `$request->validate()` rather than FormRequest classes. Asset types: `property,pension,investment,savings,business,life_insurance,personal,other`. Liability types: `mortgage,secured_loan,personal_loan,credit_card,overdraft,hire_purchase,student_loan,business_loan,other`. Gift types: `pet,clt,exempt,small_gift,annual_exemption`.

---

## 8. Vuex Store

### `estate.js` (`resources/js/store/modules/estate.js`, 748 lines)

**State:**

```javascript
{
  assets: [],
  investmentAccounts: [],
  liabilities: [],
  gifts: [],
  trusts: [],
  ihtProfile: null,
  netWorth: null,
  cashFlow: null,
  analysis: null,
  recommendations: [],
  secondDeathPlanning: null,
  willInfo: null,
  loading: false,
  error: null,
}
```

**Key Getters:**

| Getter | Logic |
|---|---|
| `allAssets` | Combines manual `assets` + `investmentAccounts` |
| `ihtLiability` | Backward-compatible extraction from old/new IHT summary structures |
| `giftsWithin7Years` | Filters gifts by date |
| `taxableEstate` | Net estate minus allowances |
| `grossEstate` | Total assets before liabilities |
| `futureDeathAge` / `futureTaxableEstate` / `futureIHTLiability` | Extracted from projected values |
| `probateReadiness` | Score based on will existence and completeness |

**Key Actions:**

| Action | Notes |
|---|---|
| `fetchEstateData` | Loads all estate data via `estateService.getEstateData()` |
| `calculateIHT` | Posts to `/calculate-iht` |
| `createAsset` / `updateAsset` / `deleteAsset` | CRUD with cache invalidation |
| `createLiability` / `updateLiability` / `deleteLiability` | CRUD + dispatches `netWorth/refreshNetWorth` |
| `createGift` / `updateGift` / `deleteGift` | CRUD |
| `createTrust` / `updateTrust` / `deleteTrust` | CRUD |
| `fetchWillInfo` / `saveWill` | Will management |

**Cross-Module:** Liability CRUD actions dispatch `netWorth/refreshNetWorth` to keep net worth module in sync.

---

## 9. API Service

### `estateService.js` (`resources/js/services/estateService.js`, 338 lines)

~30 methods covering all estate operations:

**Core:** `getEstateData()`, `analyzeEstate()`, `getRecommendations()`, `runScenario()`

**IHT:** `calculateIHT()`, `calculateSecondDeathIHTPlanning()`, `storeOrUpdateIHTProfile()`

**CRUD:** `createAsset()`, `updateAsset()`, `deleteAsset()`, `createLiability()`, `updateLiability()`, `deleteLiability()`, `createGift()`, `updateGift()`, `deleteGift()`, `createTrust()`, `updateTrust()`, `deleteTrust()`

**Strategies:** `getPlannedGiftingStrategy()`, `getPersonalizedGiftingStrategy()`, `getPersonalizedTrustStrategy()`, `getLifePolicyStrategy()`, `getComprehensiveEstatePlan()`

**Trusts:** `analyzeTrust()`, `getTrustRecommendations()`, `calculateDiscountedGiftDiscount()`

**Other:** `getNetWorth()`, `getCashFlow()`, `saveWill()`

---

## 10. Frontend Components

### 10.1 Views (2)

| Component | Path | Purpose |
|---|---|---|
| `EstateDashboard.vue` | `resources/js/views/Estate/EstateDashboard.vue` | Main estate planning dashboard |
| `ComprehensiveEstatePlan.vue` | `resources/js/views/Estate/ComprehensiveEstatePlan.vue` | Full estate plan view |

### 10.2 Components (30)

**IHT Components:**

| Component | Purpose |
|---|---|
| `IHTPlanning.vue` | Main IHT planning container |
| `IHTCalculationTable.vue` | IHT calculation breakdown table |
| `IHTLiabilityGauge.vue` | Visual gauge for IHT liability |
| `IHTLiabilityBreakdown.vue` | Detailed IHT breakdown |
| `IHTAssetBreakdown.vue` | Asset breakdown for IHT |
| `IHTMitigationStrategies.vue` | Mitigation strategy recommendations |
| `NRBRNRBTracker.vue` | NRB/RNRB allowance tracker |
| `SpouseExemptionNotice.vue` | Spouse exemption information |

**Will & Bequests:**

| Component | Purpose |
|---|---|
| `WillPlanning.vue` | Will management interface |
| `IntestacyRules.vue` | Intestacy distribution calculator |

**Trust Components:**

| Component | Purpose |
|---|---|
| `TrustPlanning.vue` | Trust planning overview |
| `TrustForm.vue` | Trust creation/edit form |
| `TrustPlanningStrategy.vue` | Trust strategy recommendations |

**Gifting Components:**

| Component | Purpose |
|---|---|
| `GiftingStrategy.vue` | Gifting strategy overview |
| `GiftCard.vue` | Individual gift display |
| `GiftForm.vue` | Gift creation/edit form |
| `GiftingTimelineChart.vue` | Gifting timeline visualisation |
| `DualGiftingTimeline.vue` | User + spouse gifting timeline |

**Net Worth & Cash Flow:**

| Component | Purpose |
|---|---|
| `NetWorth.vue` | Net worth dashboard |
| `NetWorthWaterfallChart.vue` | Waterfall chart visualisation |
| `AssetsLiabilities.vue` | Assets & liabilities management |
| `AssetForm.vue` | Asset creation/edit form |
| `LiabilityForm.vue` | Liability creation/edit form |
| `CashFlow.vue` | Cash flow display |
| `CashFlowProjectionChart.vue` | Cash flow projection chart |

**Life Cover & Other:**

| Component | Purpose |
|---|---|
| `LifeCoverRecommendations.vue` | Life cover recommendations for IHT |
| `LifePolicyStrategy.vue` | Whole of Life vs Self-Insurance |
| `MissingDataAlert.vue` | Alert for incomplete data |
| `EstateOverviewCard.vue` | Summary card |
| `EstateProjectionComparison.vue` | Current vs projected comparison |

---

## 11. Frontend Routing

| Route | Component | Name |
|---|---|---|
| `/estate` | `EstateDashboard` | `Estate` |
| `/estate-plan` | `ComprehensiveEstatePlan` | `ComprehensiveEstatePlan` |
| `/trusts` | `TrustsDashboard` | `Trusts` |
| `/trusts/:id` | `TrustDetailView` | `TrustDetail` |
| `/preview/estate` | `EstateDashboard` | `PreviewEstate` |

**Vuex Module Mapping:** Both `/estate` and `/trusts` map to the `estate` Vuex module.

---

## 12. Cross-Module Integration

### 12.1 Inbound (Estate consumes from other modules)

| Source Module | Data Used | How |
|---|---|---|
| **Properties** | Property values, types, ownership | `EstateAssetAggregatorService` queries `Property` model |
| **Investments** | Account values, types (ISA, GIA, SIPP) | `EstateAssetAggregatorService` queries `InvestmentAccount`. VCT/EIS flagged for BPR. |
| **Savings** | Account balances | `EstateAssetAggregatorService` queries `SavingsAccount` |
| **Retirement** | DC pension fund values, DB pension income | `EstateAssetAggregatorService` queries `DCPension` (exempt, value included) and `DBPension` (exempt, value=0) |
| **Protection** | Life insurance policies in trust | `IHTController` queries `LifeInsurancePolicy` where `in_trust=true` |
| **Business** | Business valuations, BPR eligibility | `EstateAssetAggregatorService` queries `BusinessInterest` with BPR check |
| **Chattels** | Personal property values | `EstateAssetAggregatorService` queries `Chattel` |
| **Mortgages** | Outstanding balances | `EstateAssetAggregatorService` queries `Mortgage` model |
| **Income** | Employment, rental, dividend income | `IHTCalculationService` uses user income fields for cash projection |
| **Expenditure** | Monthly expenditure | `IHTCalculationService` uses `ExpenditureProfile` for cash projection |
| **Retirement Profile** | Target retirement age/income | `IHTCalculationService` uses `retirementProfile` for projection phases |
| **Investment Projections** | Monte Carlo p20 percentile | `IHTCalculationService` uses `InvestmentProjectionService` for growth rates |
| **Assumptions** | Estate planning assumptions | `IHTCalculationService` uses `AssumptionsService` for growth rates |

### 12.2 Outbound (Estate provides to other modules)

| Consumer | Data Provided | How |
|---|---|---|
| **CoordinatingAgent** | Estate health score, IHT liability | EstateAgent priority = 80 in coordination |
| **Net Worth (Vuex)** | Liability changes | Liability CRUD dispatches `netWorth/refreshNetWorth` |
| **Profile Completeness** | Asset existence check | Indirectly via property/investment checks |

### 12.3 Estate TODO (Incomplete Integration)

- `EstateAgent` has a TODO placeholder for life cover integration from Protection module
- IHT calculation cache is temporarily disabled (projected columns not in `iht_calculations` schema yet)

---

## 13. Profile Completeness Integration

The Estate module does **not** have its own dedicated completeness check in `ProfileCompletenessChecker`. The checker references estate/retirement planning indirectly:

```
'Add at least one asset for estate and retirement planning'
```

This appears in the property/investment asset checks rather than as an estate-specific completeness criterion.

The `ComprehensiveEstatePlanService` uses `ProfileCompletenessChecker` to determine plan type (Personalised vs Generic) and generates completeness warnings with severity levels and missing field recommendations.

---

## 14. Seeder Data

### `PreviewUserSeeder` Estate Data

The seeder creates estate data for preview personas via dedicated methods:

| Method | Data Created |
|---|---|
| `createWills()` | Will + bequests for user and spouse |
| `createTrusts()` | Trust records per persona |
| `createGifts()` | Gift records (PETs, annual exemptions) |
| `createIHTProfiles()` | IHT profiles (transferred NRB for widows) |

**Key Personas with Estate Data:**
- **peak_earners (David & Sarah Mitchell):** Multiple properties, significant estate, full IHT planning
- **widow (Margaret Thompson):** Transferred NRB from deceased spouse, estate planning focus
- **retired_couple (Robert & Patricia Williams):** Decumulation, estate planning focus
- **entrepreneur (Alex Chen):** Business interests with BPR

Estate-relevant data also seeded via other methods: properties, investment accounts, savings accounts, business interests, life insurance policies (some `in_trust=true`), protection profiles.

---

## 15. API Routing

All routes under `auth:sanctum` middleware, prefix `estate`:

```
GET    /estate                                    → EstateController@index
POST   /estate/calculate-iht                      → IHTController@calculateIHT
POST   /estate/calculate-surviving-spouse-iht      → IHTController@calculateSurvivingSpouseIHT
POST   /estate/calculate-second-death-iht-planning → IHTController@calculateSecondDeathIHTPlanning
GET    /estate/net-worth                           → EstateController@getNetWorth
GET    /estate/cash-flow                           → EstateController@getCashFlow
GET    /estate/comprehensive-plan                  → EstateController@getComprehensiveEstatePlan
POST   /estate/profile                             → IHTController@storeOrUpdateIHTProfile

POST   /estate/assets                              → EstateController@storeAsset
PUT    /estate/assets/{id}                         → EstateController@updateAsset
DELETE /estate/assets/{id}                         → EstateController@destroyAsset

POST   /estate/liabilities                         → EstateController@storeLiability
PUT    /estate/liabilities/{id}                    → EstateController@updateLiability
DELETE /estate/liabilities/{id}                    → EstateController@destroyLiability

GET    /estate/gifts/planned-strategy              → GiftingController@getPlannedGiftingStrategy
GET    /estate/gifts/personalized-strategy         → GiftingController@getPersonalizedGiftingStrategy
GET    /estate/gifts/trust-strategy                → GiftingController@getPersonalizedTrustStrategy
POST   /estate/gifts                               → EstateController@storeGift
PUT    /estate/gifts/{id}                          → EstateController@updateGift
DELETE /estate/gifts/{id}                          → EstateController@destroyGift

GET    /estate/life-policy-strategy                → LifePolicyController@getLifePolicyStrategy

GET    /estate/trusts                              → TrustController@getTrusts
POST   /estate/trusts                              → TrustController@createTrust
PUT    /estate/trusts/{id}                         → TrustController@updateTrust
DELETE /estate/trusts/{id}                         → TrustController@deleteTrust
GET    /estate/trusts/{id}/analyze                 → TrustController@analyzeTrust
GET    /estate/trusts/{id}/assets                  → TrustController@getTrustAssets
POST   /estate/trusts/{id}/calculate-iht-impact    → TrustController@calculateTrustIHTImpact
GET    /estate/trust-recommendations               → TrustController@getTrustRecommendations
GET    /estate/trusts/upcoming-tax-returns         → TrustController@getUpcomingTaxReturns

GET    /estate/will                                → WillController@getWill
POST   /estate/will                                → WillController@storeOrUpdateWill
POST   /estate/calculate-intestacy                 → WillController@calculateIntestacy
GET    /estate/bequests                            → WillController@getBequests
POST   /estate/bequests                            → WillController@storeBequest
PUT    /estate/bequests/{id}                       → WillController@updateBequest
DELETE /estate/bequests/{id}                       → WillController@deleteBequest
POST   /estate/calculate-discount                  → GiftingController@calculateDiscountedGiftDiscount
```

**Separate routes (not under /estate prefix):**

```
GET    /letter-to-spouse                           → LetterToSpouseController@show
GET    /letter-to-spouse/exists                    → LetterToSpouseController@exists
GET    /letter-to-spouse/spouse                    → LetterToSpouseController@showSpouse
PUT    /letter-to-spouse                           → LetterToSpouseController@update
GET    /net-worth/...                              → NetWorthController (separate)
```

---

## 16. Key Constants and Business Logic

### EstateDefaults Constants
Default fallback values used when user data is missing. See **SharedInfrastructure.md Section 16.3** for the complete constants table.

Key constants: `ESTIMATED_PROPERTY_VALUE` (£200,000), `STANDARD_GROWTH_RATE` (3%), `DEFAULT_LIFE_EXPECTANCY` (85), `NRB` (£325,000), `RNRB` (£175,000).

### TaxDefaults (IHT Section)
IHT-related tax constants. See **SharedInfrastructure.md Section 16.3** for the complete TaxDefaults table covering all tax categories.

Key IHT constants: `NRB` (£325,000), `RNRB` (£175,000), `IHT_RATE` (40%), `IHT_CHARITABLE_RATE` (36%), `ANNUAL_GIFT_EXEMPTION` (£3,000).

### 16.3 Key Business Rules

**IHT Rate Determination:**
- Standard rate: 40% on estate above NRB + RNRB
- Reduced rate: 36% if 10%+ of baseline (net estate - NRB, excluding RNRB) left to charity
- Baseline calculation explicitly excludes RNRB

**NRB Availability:**
- Single: £325,000
- Married: £650,000 (£325k each, spouse exempt transfer on first death)
- Widowed: £325,000 + transferred amount from deceased spouse's estate
- Lifetime gifting ONLY uses own NRB (£325k), NOT spouse transferred NRB

**RNRB Eligibility:**
- Must own main residence and leave to direct descendants
- Full: £175,000 (or £350,000 for couples)
- Taper: Reduces by £1 for every £2 above £2,000,000 estate value
- Can be fully tapered away for estates significantly above £2M

**PET (Potentially Exempt Transfer) Rules:**
- Gifts become exempt after donor survives 7 years
- If donor dies within 7 years, gift becomes chargeable
- Taper relief applies from year 3 (graduated rates)
- Oldest gifts use NRB first (chronological allocation)

**CLT (Chargeable Lifetime Transfer) Rules:**
- 20% immediate charge on amount exceeding NRB
- Additional 20% if donor dies within 7 years (total 40%)

**Business Property Relief:**
- 100% relief for qualifying trading businesses owned 2+ years
- `bpr_eligible` flag + `trading_status === 'trading'` + `acquisition_date` 2+ years ago

---

## 17. Known Issues and Limitations

### 17.1 IHT Calculation Cache Temporarily Disabled

The hash-based caching in `IHTCalculationService.getCachedCalculation()` is disabled with a TODO comment:

```php
// TEMPORARILY DISABLED: The database schema doesn't include projected_cash,
// projected_investments, projected_properties, or retirement_age columns.
// Until these are added via migration, we must recalculate every time.
```

This means every IHT calculation runs fresh, which may impact performance for users who check frequently.

### 17.2 Life Cover Integration TODO

`EstateAgent` has a TODO placeholder for integrating life cover data from the Protection module. Currently the IHTController queries life policies directly, but the agent doesn't fully incorporate this into recommendations.

### 17.3 Simplified Actuarial Calculations

- Discounted gift trust discount uses a simplified calculation rather than full actuarial tables
- Life insurance premium estimation uses simplified age-based rates
- ComprehensiveEstatePlanService uses simplified life expectancy (male: 79, female: 83) rather than the actuarial table lookup used by IHTCalculationService

### 17.4 Inline Validation

Assets, liabilities, and gifts in `EstateController` use inline `$request->validate()` rather than dedicated FormRequest classes. Only wills and bequests have dedicated FormRequest classes.

### 17.5 Investment Projection Fallback

When Monte Carlo projection fails or returns no data, the system falls back to current investment value (no growth modelled). The default fallback growth rate is 4.7%.

### 17.6 Deprecated Endpoints

- `calculateSecondDeathIHTPlanning()` in IHTController is a deprecated alias that redirects to `calculateIHT()`
- `calculateSurvivingSpouseIHT()` route exists but may be unused

---

## 18. IHT Mitigation Decision Tree

The `EstateAgent.generateRecommendations()` implements a 7-step decision tree, applied in priority order:

### Step 1: Charitable Bequest (Reduce Rate from 40% to 36%)

**Condition:** `charitable_giving_percent < 10`
**Action:** Recommend increasing charitable giving to 10%+ of baseline estate to qualify for 36% rate
**Impact:** 4% rate reduction on taxable estate

### Step 2: Liquidity Assessment

**Condition:** Always runs
**Action:** Checks if estate has sufficient liquid assets to pay IHT bill. Flags liquidity risk if liquid assets < IHT liability.

### Step 3: Existing Life Cover

**Condition:** Always runs
**Action:** Checks existing life insurance policies written in trust. If cover >= IHT liability, flags as adequately covered.

### Step 4: Annual Gifting (First Resort)

**Condition:** IHT liability > 0
**Action:** Recommend using annual exemption (£3,000/year, £6,000 for couples). Immediately exempt, no 7-year wait.
**Priority:** Always recommended first as zero-risk strategy.

### Step 5: Life Cover (Second Resort, Age ≤ 50)

**Condition:** IHT liability > 0 AND user age ≤ 50
**Action:** Recommend Whole of Life policy written in trust to cover IHT liability.
**Rationale:** Younger applicants get significantly lower premiums.

### Step 6: PET Gifting (Third Resort)

**Condition:** Remaining IHT liability > 0 after annual exemption
**Action:** Recommend PET gifting from liquid assets over 7-year cycles.
**Calculation:** Based on available liquid/semi-liquid assets and remaining life expectancy.

### Step 7: CLT Trust (Last Resort)

**Condition:** Remaining IHT liability > 0 after all above
**Action:** Recommend Chargeable Lifetime Transfer into discretionary trust.
**Cost:** 20% immediate charge on amount exceeding NRB. Growth accrues outside estate.
**Use Case:** When gifting is not possible (e.g., want to retain control) or estate is very large.
