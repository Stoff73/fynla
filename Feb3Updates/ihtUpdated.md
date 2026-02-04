# Estate Planning Module - Complete Technical Documentation

**Version:** 1.0
**Last Updated:** 3 February 2026
**Module:** Estate Planning / IHT

---

## Table of Contents

1. [Overview](#1-overview)
2. [Data Models](#2-data-models)
3. [Tax Configuration](#3-tax-configuration)
4. [Backend Architecture](#4-backend-architecture)
5. [Asset Aggregation](#5-asset-aggregation)
6. [IHT Calculation Logic](#6-iht-calculation-logic)
7. [Projection Calculations](#7-projection-calculations)
8. [IHT Mitigation Strategies](#8-iht-mitigation-strategies)
9. [Will Integration](#9-will-integration)
10. [User Type Handling](#10-user-type-handling)
11. [Linked Accounts (Spouse Integration)](#11-linked-accounts-spouse-integration)
12. [Frontend Architecture](#12-frontend-architecture)
13. [API Endpoints](#13-api-endpoints)
14. [Complete Data Flow](#14-complete-data-flow)

---

## 1. Overview

The Estate Planning module calculates Inheritance Tax (IHT) liability, generates mitigation strategies, and provides comprehensive estate planning recommendations. It integrates data from all other modules (Properties, Investments, Savings, Pensions, Protection) to build a complete picture of a user's estate.

### Key Capabilities

- Real-time IHT liability calculation (current and projected at death)
- Spouse integration with combined estate calculations
- Gifting strategy optimisation (PETs, Annual Exemptions, Normal Expenditure)
- Trust strategy recommendations (CLTs, Loan Trusts, Discounted Gift Trusts)
- Life insurance coverage recommendations
- Will and bequest management
- Support for single, married, widowed users

### Key Files

| Component | Path |
|-----------|------|
| Agent | `app/Agents/EstateAgent.php` |
| IHT Calculation | `app/Services/Estate/IHTCalculationService.php` |
| Asset Aggregation | `app/Services/Estate/EstateAssetAggregatorService.php` |
| Gifting Strategy | `app/Services/Estate/GiftingStrategyOptimizer.php` |
| Trust Strategy | `app/Services/Estate/PersonalizedTrustStrategyService.php` |
| Comprehensive Plan | `app/Services/Estate/ComprehensiveEstatePlanService.php` |
| Controller | `app/Http/Controllers/Api/EstateController.php` |
| Vuex Store | `resources/js/store/modules/estate.js` |
| API Service | `resources/js/services/estateService.js` |

---

## 2. Data Models

All estate models are located in `app/Models/Estate/`.

### 2.1 IHTProfile

Stores user's IHT planning profile and transferred allowances.

```php
// app/Models/Estate/IHTProfile.php
protected $fillable = [
    'user_id',
    'marital_status',           // single, married, widowed
    'has_spouse',               // Boolean
    'own_home',                 // Boolean - required for RNRB
    'home_value',               // Main residence value
    'nrb_transferred_from_spouse', // Transferred NRB from deceased spouse (widowed users)
    'charitable_giving_percent',   // For reduced IHT rate (36% if 10%+ to charity)
];
```

### 2.2 Will

Stores will information and distribution instructions.

```php
// app/Models/Estate/Will.php
protected $fillable = [
    'user_id',
    'has_will',                    // Boolean
    'spouse_primary_beneficiary',  // Boolean - spouse gets estate first
    'spouse_bequest_percentage',   // 0-100% to spouse
    'executor_name',
    'executor_notes',
    'will_last_updated',           // Date
];

// Relationships
public function bequests(): HasMany  // Individual bequests
```

### 2.3 Bequest

Individual bequests within a will.

```php
// app/Models/Estate/Bequest.php
protected $fillable = [
    'will_id',
    'beneficiary_name',
    'beneficiary_user_id',         // If beneficiary is a system user
    'bequest_type',                // percentage_of_estate, specific_amount, specific_asset
    'percentage_of_estate',        // For percentage type
    'specific_amount',             // For amount type
    'priority_order',              // For intestacy fallback ordering
];
```

### 2.4 Gift

Records lifetime gifts for IHT calculation.

```php
// app/Models/Estate/Gift.php
protected $fillable = [
    'user_id',
    'gift_date',
    'recipient',
    'gift_type',                   // pet (Potentially Exempt Transfer), clt (Chargeable Lifetime Transfer), exempt
    'gift_value',
    'status',                      // pending, completed
    'taper_relief_applicable',     // Boolean - if 3-7 years since gift
];
```

### 2.5 Trust

Trust arrangements for IHT planning.

```php
// app/Models/Estate/Trust.php
protected $fillable = [
    'user_id',
    'household_id',
    'trust_name',
    'trust_type',                  // bare, discounted_gift, loan, life_insurance,
                                   // interest_in_possession, discretionary, accumulation_maintenance
    'current_value',
    'loan_amount',                 // For loan trusts
    'is_relevant_property_trust',  // Subject to 10-year charges
    'last_periodic_charge_date',
    'beneficiaries',               // JSON array
    'trustees',                    // JSON array
    'settlor',
];
```

### 2.6 Asset

Manual estate assets (separate from module-specific assets).

```php
// app/Models/Estate/Asset.php
protected $fillable = [
    'user_id',
    'asset_type',                  // property, investment, cash, business, chattel, etc.
    'asset_name',
    'current_value',
    'is_iht_exempt',               // Boolean
    'exemption_reason',            // BPR, charity, etc.
    'is_main_residence',           // Boolean - for RNRB
    'ownership_type',              // individual, joint, tenants_in_common, trust
    'beneficiary_designation',     // Nominated beneficiary
];
```

### 2.7 Liability

Estate liabilities.

```php
// app/Models/Estate/Liability.php
protected $fillable = [
    'user_id',
    'joint_owner_id',
    'liability_type',              // mortgage, loan, credit_card, etc.
    'liability_name',
    'current_balance',
    'monthly_payment',
    'interest_rate',
    'maturity_date',
    'is_priority_debt',            // Boolean
    'ownership_type',              // individual, joint
    'ownership_percentage',        // Primary owner's share
];
```

### 2.8 IHTCalculation

Cached IHT calculation results.

```php
// app/Models/Estate/IHTCalculation.php
protected $fillable = [
    'user_id',
    'user_gross_assets',
    'spouse_gross_assets',
    'total_gross_assets',
    'user_total_liabilities',
    'spouse_total_liabilities',
    'total_liabilities',
    'nrb_available',
    'nrb_message',
    'rnrb_available',
    'rnrb_status',                 // none, full, tapered
    'rnrb_message',
    'total_allowances',
    'taxable_estate',
    'iht_liability',
    'effective_rate',
    'projected_gross_assets',
    'projected_liabilities',
    'projected_net_estate',
    'projected_taxable_estate',
    'projected_iht_liability',
    'years_to_death',
    'estimated_age_at_death',
    'is_married',
    'data_sharing_enabled',
    'assets_hash',                 // For cache invalidation
    'liabilities_hash',
    'calculation_date',
];
```

---

## 3. Tax Configuration

**CRITICAL: All IHT values come from `TaxConfigService` - NOTHING is hardcoded in calculation services.**

The single source of truth is the `TaxConfiguration` database table, accessed via `TaxConfigService`. This ensures consistency across all calculations and allows values to be updated without code changes.

### 3.1 Service Access Methods

```php
// app/Services/TaxConfigService.php

// Core IHT configuration
$ihtConfig = $taxConfig->getInheritanceTax();

// Gifting exemptions
$giftingConfig = $taxConfig->getGiftingExemptions();

// PET (Potentially Exempt Transfer) rules
$petRules = $taxConfig->getPETRules();

// CLT (Chargeable Lifetime Transfer) rules
$cltRules = $taxConfig->getCLTRules();

// 14-year rule configuration
$fourteenYearRule = $taxConfig->getFourteenYearRule();

// Trust charges (entry, periodic, exit)
$trustCharges = $taxConfig->getTrustCharges();

// Get taper relief for specific gift type
$taperRelief = $taxConfig->getTaperRelief('pet'); // or 'clt'

// Get tax rate based on years survived
$rate = $taxConfig->getGiftTaxRate(4.5, 'pet'); // Returns 0.24 (60% of 40%)

// Business/Agricultural Relief
$businessRelief = $taxConfig->getBusinessRelief();
$agriculturalRelief = $taxConfig->getAgriculturalRelief();

// Normal Expenditure from Income
$normalExpenditure = $taxConfig->getNormalExpenditureFromIncome();
```

### 3.2 Core Thresholds and Rates

All values from `inheritance_tax.*` in TaxConfigService:

| Config Key | Value | Description |
|------------|-------|-------------|
| `nil_rate_band` | £325,000 | Per person, frozen until April 2030 |
| `residence_nil_rate_band` | £175,000 | For main residence left to direct descendants |
| `rnrb_taper_threshold` | £2,000,000 | RNRB tapers if estate exceeds this |
| `rnrb_taper_rate` | 0.5 | £1 lost per £2 over threshold |
| `standard_rate` | 0.40 | 40% on taxable estate |
| `reduced_rate_charity` | 0.36 | 36% if 10%+ left to charity |
| `charity_threshold_percent` | 0.10 | 10% of baseline for reduced rate |
| `spouse_exemption` | true | Unlimited to UK-domiciled spouse |
| `transferable_nil_rate_band` | true | Unused NRB transfers to survivor |
| `transferable_rnrb` | true | Unused RNRB also transfers |

### 3.3 Potentially Exempt Transfers (PETs)

Config path: `inheritance_tax.potentially_exempt_transfers`

Gifts to individuals that become exempt if donor survives 7 years.

| Config Key | Value | Description |
|------------|-------|-------------|
| `years_to_exemption` | 7 | Fully exempt after 7 years |
| `immediate_charge` | false | No tax on gift when made |
| `becomes_chargeable_on_death` | true | Chargeable if donor dies within 7 years |
| `uses_donor_nrb` | true | Uses donor's NRB when calculating tax |
| `cumulation_period` | 7 | PETs in 7 years before death are cumulated |

**Taper Relief** (`inheritance_tax.potentially_exempt_transfers.taper_relief`):

| Years Survived | Tax Rate | Description |
|----------------|----------|-------------|
| 0-3 | 40% | Full rate |
| 3-4 | 32% | 80% of 40% |
| 4-5 | 24% | 60% of 40% |
| 5-6 | 16% | 40% of 40% |
| 6-7 | 8% | 20% of 40% |
| 7+ | 0% | Fully exempt |

**Failed PET Rules** (`inheritance_tax.potentially_exempt_transfers.failed_pet_rules`):

| Rule | Value |
|------|-------|
| `becomes_chargeable_transfer` | true |
| `affects_later_clt_nrb` | true |
| `affects_estate_nrb` | true |
| `calculation_order` | chronological |

### 3.4 Chargeable Lifetime Transfers (CLTs)

Config path: `inheritance_tax.chargeable_lifetime_transfers`

Gifts to most trusts - immediately chargeable.

| Config Key | Value | Description |
|------------|-------|-------------|
| `lookback_period` | 7 | CLTs in 7 years before this CLT use up NRB |
| `cumulation_period` | 7 | Rolling 7-year cumulation |
| `lifetime_rate` | 0.20 | 20% immediate charge on excess over NRB |
| `lifetime_rate_grossed_up` | 0.25 | 25% if settlor pays the tax |
| `death_rate` | 0.40 | 40% rate on death within 7 years |
| `additional_death_charge` | 0.20 | Extra 20% due if death within 7 years |
| `taper_relief_applies` | true | Taper relief reduces additional charge |

**CLT Taper Relief** (`inheritance_tax.chargeable_lifetime_transfers.taper_relief`):

| Years | Relief % | Effective Tax % |
|-------|----------|-----------------|
| 0-3 | 0% | 100% of additional charge |
| 3-4 | 20% | 80% |
| 4-5 | 40% | 60% |
| 5-6 | 60% | 40% |
| 6-7 | 80% | 20% |
| 7+ | 100% | 0% (no additional charge) |

### 3.5 The 14-Year Rule

Config path: `inheritance_tax.fourteen_year_rule`

When calculating IHT on a CLT made within 7 years of death, failed PETs made in the 7 years before that CLT reduce the available NRB.

| Config Key | Value | Description |
|------------|-------|-------------|
| `applies_to` | clt_with_prior_failed_pet | Specific scenario |
| `lookback_for_failed_pets` | 7 | Years to look back for failed PETs |
| `lookback_for_clts` | 7 | Years before death to check CLTs |
| `maximum_window` | 14 | Total maximum window |

**Calculation Steps** (from config):
1. Identify all CLTs made within 7 years of death
2. For each CLT, identify any failed PETs made in the 7 years before that CLT
3. Failed PETs reduce the NRB available for the CLT
4. This can result in additional tax on the CLT even if it was within NRB when made

### 3.6 Trust Charges

Config path: `inheritance_tax.trust_charges`

#### Entry Charge

| Config Key | Value | Description |
|------------|-------|-------------|
| `entry.rate` | 0.20 | 20% on value exceeding NRB |
| `entry.rate_grossed_up` | 0.25 | 25% if settlor pays |
| `entry.nrb_available` | true | NRB applies (less previous 7-year CLTs) |

#### Periodic (10-Year) Charge

| Config Key | Value | Description |
|------------|-------|-------------|
| `periodic.interval_years` | 10 | Charged every 10 years |
| `periodic.max_rate` | 0.06 | Maximum 6% of trust value |
| `periodic.calculation_formula` | "30% of lifetime rate" | 30% × 20% = 6% |
| `periodic.lifetime_rate_multiplier` | 0.30 | 30% multiplier |
| `periodic.base_rate` | 0.20 | Base lifetime rate |
| `periodic.nrb_available` | true | NRB reduces the charge |

**Formula:** `Trust Value × Effective Rate`
- Effective Rate = 30% × (Excess over NRB / Trust Value) × 20%
- Maximum effective rate = 6%

#### Exit Charge

| Config Key | Value | Description |
|------------|-------|-------------|
| `exit.max_rate` | 0.06 | Maximum 6% |
| `exit.calculation_basis` | proportionate | Based on time since last anniversary |
| `exit.quarters_in_period` | 40 | 40 complete quarters in 10 years |
| `exit.formula` | "effective_rate × 30% × (quarters / 40)" | Proportionate formula |

**No Exit Charge Periods:**

| Scenario | Period |
|----------|--------|
| After trust setup | 3 months |
| After 10-year anniversary | 3 months |
| Discretionary will trust | 24 months from death |

### 3.7 Gifting Exemptions

Config path: `gifting_exemptions`

#### Annual Exemption

| Config Key | Value |
|------------|-------|
| `annual_exemption` | £3,000 |
| `annual_exemption_can_carry_forward` | true |
| `carry_forward_years` | 1 |

#### Small Gifts

| Config Key | Value |
|------------|-------|
| `small_gifts_limit` | £250 per recipient |
| `small_gifts_unlimited_recipients` | true |

#### Wedding/Civil Partnership Gifts

| Relationship | Amount |
|--------------|--------|
| `parent_to_child` | £5,000 |
| `grandparent_to_grandchild` | £2,500 |
| `other_person` | £1,000 |

#### Normal Expenditure from Income

Config path: `gifting_exemptions.normal_expenditure_from_income`

| Config Key | Value |
|------------|-------|
| `limit` | null (unlimited) |
| `immediately_exempt` | true |

**Conditions (all must be met):**
- From income, not capital
- Regular/habitual pattern
- Does not affect donor's standard of living

**Evidence Required:**
- Income and expenditure records
- Pattern of regular giving (typically 3+ years)
- Proof that standard of living maintained

### 3.8 Business & Agricultural Relief

Config path: `inheritance_tax.business_relief` and `inheritance_tax.agricultural_relief`

| Asset Type | Relief Rate |
|------------|-------------|
| Trading business (sole trader/partnership) | 100% |
| Unquoted company shares | 100% |
| AIM-listed shares | 100% |
| Controlling holding in quoted company | 100% |
| Land/buildings used by controlled business | 50% |
| Investment companies | 0% |

**Requirements:**
- Minimum 2 years ownership
- Trading status (not investment holding)
- From April 2026: Combined APR/BPR capped at £1m at 100%, then 50%

### 3.9 Quick Succession Relief

Config path: `inheritance_tax.quick_succession_relief`

Applies when beneficiary dies shortly after receiving an inheritance.

| Years After Receiving | Relief |
|-----------------------|--------|
| 0-1 | 100% |
| 1-2 | 80% |
| 2-3 | 60% |
| 3-4 | 40% |
| 4-5 | 20% |

### 3.10 Assumptions (for Projections)

Config path: `assumptions`

Estate projections use sophisticated asset-specific growth models, all configurable via **Settings > Assumptions > Estate Planning**.

#### Asset-Specific Growth Methods

Unlike simple flat-rate growth, estate projections apply different growth methodologies to different asset types:

| Asset Type | Growth Method | Configurable | Default |
|------------|---------------|--------------|---------|
| **Cash Accounts** | Income/Expense Surplus Model | No | See below |
| **Investment Accounts** | Monte Carlo (80% confidence) | Yes | Monte Carlo |
| **Properties** | Fixed Annual % | Yes | 3% |
| **Liabilities** | Amortisation to end date | No | End at retirement |

#### Cash Account Projection (Income/Expense Surplus Model)

Cash accounts do **NOT** use a flat growth rate. Instead, the projection models the user's actual cash flow:

```
Pre-Retirement (current age → retirement age):
─────────────────────────────────────────────
Annual Surplus = Total Annual Income − Total Annual Expenses

Where:
  Total Annual Income = user.annual_employment_income
                      + user.annual_self_employment_income
                      + user.annual_rental_income
                      + user.annual_dividend_income
                      + user.annual_interest_income
                      + user.annual_other_income
                      + user.annual_trust_income
                      + spouse income (if linked and data sharing enabled)

  Total Annual Expenses = expenditure_profile.total_monthly_expenditure × 12

Cash grows by adding annual surplus each year (can be negative if deficit).
```

```
Post-Retirement (retirement age → death):
─────────────────────────────────────────
Annual Surplus = Retirement Income − Retirement Expenses

Where:
  Retirement Income = retirement_profile.target_retirement_income
                    + State Pension (from state_pension_age onwards)
                    + Pension income (DC/DB drawdown)
                    + spouse retirement income (if linked)

  Retirement Expenses = retirement_profile.essential_expenditure
                      + retirement_profile.lifestyle_expenditure

State Pension timing:
  - Included from user.state_pension_age (typically 66-68)
  - Amount from state_pensions.estimated_annual_amount
  - For couples, both state pensions included at respective ages
```

#### Investment Growth (Monte Carlo 80% Confidence)

**Default Method: Monte Carlo Simulation**
- Uses the 20th percentile (p20) from 1000 Monte Carlo iterations
- This represents the value that has an 80% probability of being achieved or exceeded
- Calculated by `MonteCarloSimulator` using normal distribution (Box-Muller transform)
- Risk-adjusted based on portfolio allocation
- Already used in the Net Worth module's Investments tab

**Alternative: Custom Rate**
- User specifies a fixed annual growth percentage
- Applied as simple compound growth: `FV = PV × (1 + r)^n`
- Useful for conservative or aggressive projections

#### Property Growth (3% Default)

- All properties grow at a fixed annual rate (default 3%)
- Configurable per user via Assumptions settings
- Same rate applied to main residence, secondary residences, and buy-to-let

#### Liability Projection

Liabilities are projected to their end date:

| Liability Type | End Date Logic |
|----------------|----------------|
| **Mortgages** | Use `mortgage.end_date` if set, otherwise assume retirement age |
| **Other Liabilities** | Use `liability.end_date` if set, otherwise assume retirement age |
| **No end date** | Default to `retirement_profile.target_retirement_age` or `user.target_retirement_age` |

Liabilities reduce linearly to £0 at their end date (simplified amortisation).

#### User-Configurable Options

The Assumptions Settings page (`/settings/assumptions`) allows users to customise:

| Setting | Options | Default |
|---------|---------|---------|
| **Inflation Rate** | 0% - 20% (editable) | 2.5% |
| **Property Growth Rate** | -10% - 20% (editable) | 3% |
| **Investment Growth Method** | Dropdown: "Monte Carlo (80%)" or "Custom %" | Monte Carlo (80%) |
| **Custom Investment Rate** | -10% to 30% (only shown if Custom selected) | — |

**Note:** Cash account growth is NOT configurable as it's derived from actual income/expenses.

#### Data Sources

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        PROJECTION DATA SOURCES                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  INCOME (Pre-Retirement)              INCOME (Post-Retirement)          │
│  ─────────────────────                ────────────────────────          │
│  users.annual_employment_income       retirement_profiles.              │
│  users.annual_self_employment_income    target_retirement_income        │
│  users.annual_rental_income           state_pensions.                   │
│  users.annual_dividend_income           estimated_annual_amount         │
│  users.annual_interest_income         (from state_pension_age)          │
│  users.annual_other_income                                              │
│  users.annual_trust_income                                              │
│                                                                         │
│  EXPENSES (Pre-Retirement)            EXPENSES (Post-Retirement)        │
│  ─────────────────────────            ─────────────────────────         │
│  expenditure_profiles.                retirement_profiles.              │
│    total_monthly_expenditure × 12       essential_expenditure           │
│                                       retirement_profiles.              │
│                                         lifestyle_expenditure           │
│                                                                         │
│  RETIREMENT AGE                                                         │
│  ──────────────                                                         │
│  retirement_profiles.target_retirement_age                              │
│  OR users.target_retirement_age                                         │
│  OR dc_pensions.retirement_age (fallback)                              │
│  OR 68 (default)                                                        │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

#### How Values Flow

```
User Settings (UserAssumption model)
        ↓
AssumptionsService.getEstateAssumptions()
        ↓
IHTCalculationService.calculateProjectedValues()
        ↓
    ┌───────────────────────────────────────────────────────────────────┐
    │ For each year from current_age to estimated_death_age:            │
    │                                                                   │
    │   CASH ACCOUNTS:                                                  │
    │     if year < retirement_age:                                     │
    │       cash += (total_income - current_expenses)                   │
    │     else:                                                         │
    │       retirement_income = target_retirement_income                │
    │       if year >= state_pension_age:                               │
    │         retirement_income += state_pension_amount                 │
    │       cash += (retirement_income - retirement_expenses)           │
    │                                                                   │
    │   INVESTMENTS:                                                    │
    │     if monte_carlo_method:                                        │
    │       investments = MonteCarloSimulator.getP20Value(years)        │
    │     else:                                                         │
    │       investments *= (1 + custom_rate)                            │
    │                                                                   │
    │   PROPERTIES:                                                     │
    │     properties *= (1 + property_growth_rate)                      │
    │                                                                   │
    │   LIABILITIES:                                                    │
    │     if year <= liability_end_year:                                │
    │       remaining_term = liability_end_year - year                  │
    │       liability = original × (remaining_term / total_term)        │
    │     else:                                                         │
    │       liability = 0                                               │
    │                                                                   │
    └───────────────────────────────────────────────────────────────────┘
        ↓
Projected Estate at Death = Cash + Investments + Properties - Liabilities
```

#### Storage

User overrides are stored in `user_assumptions` table:

```php
UserAssumption::where('assumption_type', 'estate_planning')
    ->where('user_id', $userId)
    ->first();

// Fields:
// - inflation_rate (decimal)
// - property_growth_rate (decimal)
// - investment_growth_method (enum: 'monte_carlo', 'custom')
// - custom_investment_rate (decimal, nullable)
```

If no override exists, defaults from `TaxConfigService.getAssumptions()` are used.

---

## 4. Backend Architecture

### 4.1 EstateAgent

The orchestrator that coordinates all estate planning calculations.

**File:** `app/Agents/EstateAgent.php`

```php
class EstateAgent extends BaseAgent
{
    public function __construct(
        private IHTCalculationService $ihtCalculator,
        private EstateAssetAggregatorService $assetAggregator,
        private ComprehensiveEstatePlanService $estatePlanService,
        private GiftingStrategyOptimizer $giftingOptimizer,
        private PersonalizedTrustStrategyService $trustStrategyService
    ) {}
}
```

#### Key Methods

**`analyze($userId)`** (line 34-120)
- Loads user with all relationships
- Aggregates estate assets via `EstateAssetAggregatorService`
- Calculates IHT via `IHTCalculationService`
- Calculates estate health score (0-100)
- Gets trust recommendations via `PersonalizedTrustStrategyService`
- Gets gifting opportunities via `GiftingStrategyOptimizer`

**`generateRecommendations($analysisData)`** (line 125-204)

```
IHT Mitigation Decision Tree:

IF IHT Liability > £0:
│
├─ STEP 1: CHARITABLE BEQUEST CHECK (Rate Reduction)
│  ├─ Check user.charitable_bequest toggle
│  ├─ IF TRUE: Calculate baseline (Net Estate - NRB)
│  │  ├─ Minimum donation = baseline × 10%
│  │  └─ Apply reduced rate: 36% instead of 40%
│  ├─ IF FALSE: Use standard 40% rate
│  └─ Recalculate IHT liability with effective rate
│
├─ STEP 2: LIQUIDITY & AFFORDABILITY CHECK
│  ├─ Assess which assets can be utilised (liquid vs illiquid)
│  ├─ Calculate available surplus income
│  └─ Identify any affordability constraints
│
├─ STEP 3: CHECK EXISTING LIFE COVER
│  ├─ Query Protection module for existing policies
│  ├─ Check if policies are written in trust (bypasses estate)
│  ├─ Calculate excess cover above outstanding debts
│  └─ Only usable cover = (Total Cover - Outstanding Debts)
│
├─ STEP 4: ANNUAL GIFTING STRATEGY (First Resort)
│  ├─ Calculate annual exemption capacity (£3,000 × years to death)
│  ├─ Add normal expenditure from income potential
│  ├─ Add small gifts allowance (£250 per recipient)
│  ├─ IF annual gifting covers IHT liability → RECOMMEND
│  └─ IF NOT → Calculate remaining liability after gifting
│
├─ STEP 5: LIFE COVER STRATEGY (Second Resort)
│  ├─ IF user age ≤ 50:
│  │  ├─ Calculate whole of life premium for remaining liability
│  │  ├─ Check affordability against surplus income
│  │  └─ RECOMMEND whole of life in trust if affordable
│  ├─ IF user age > 50:
│  │  ├─ Life cover may be prohibitively expensive
│  │  ├─ Skip to PET strategy if no existing cover
│  │  └─ Only use existing cover excess for mitigation
│  └─ Remaining liability = IHT - Gifting - Life Cover Excess
│
├─ STEP 6: PET GIFTING STRATEGY (Third Resort)
│  ├─ Calculate 7-year cycle capacity
│  ├─ IF user age > 50 AND no life cover:
│  │  └─ PRIORITISE PETs over new life cover
│  ├─ Apply taper relief projections
│  └─ Calculate remaining liability after PETs
│
└─ STEP 7: CLT INTO TRUST (Last Resort Only)
   ├─ Only if Steps 4-6 do not fully cover liability
   ├─ Calculate immediate 20% charge
   ├─ Calculate potential additional 20% on death within 7 years
   ├─ Consider periodic (10-year) and exit charges
   └─ RECOMMEND with full cost/benefit analysis
```

**`buildScenarios($userId, $parameters)`** (line 209-240)
- Builds what-if scenarios: current, optimized, gifting, property downsizing, trust creation
- Each scenario shows projected IHT liability, estate value, and beneficiary amounts

**`calculateEstateHealthScore($user, $assetSummary, $ihtLiability)`** (line 245-284)
```
Base score: 100
Deductions:
-20  Missing IHT profile
-25  IHT ratio > 30%
-15  IHT ratio > 20%
-10  IHT ratio > 10%
-10  Estate > £650k with no trusts
-5   Married without linked spouse
-15  IHT liability > 50% of liquid assets
```

### 4.2 Service Layer

| Service | Purpose |
|---------|---------|
| `IHTCalculationService` | Core IHT calculation, NRB/RNRB, projections |
| `EstateAssetAggregatorService` | Aggregates assets from all modules |
| `GiftingStrategyOptimizer` | Calculates optimal gifting strategy |
| `PersonalizedTrustStrategyService` | Generates trust recommendations |
| `ComprehensiveEstatePlanService` | Builds complete estate plan document |
| `AssetLiquidityAnalyzer` | Analyzes asset liquidity for trust planning |
| `NetWorthAnalyzer` | Net worth calculations and breakdowns |
| `FutureValueCalculator` | Compound growth calculations |

---

## 5. Asset Aggregation

### 5.1 Single-Record Architecture

The system uses a **single-record pattern** for joint assets:
- ONE database record stores the FULL asset value
- `user_id` = primary owner
- `joint_owner_id` = secondary owner (if joint)
- `ownership_percentage` = primary owner's share

**File:** `app/Services/Estate/EstateAssetAggregatorService.php`

```php
// Query pattern for all modules
$assets = Model::where('user_id', $user->id)
    ->orWhere('joint_owner_id', $user->id)
    ->get();

// Ownership calculation
$userIsOwner = $asset->user_id === $user->id;
$share = $userIsOwner
    ? ($ownership_percentage / 100)
    : ((100 - $ownership_percentage) / 100);
$userValue = $asset->full_value * $share;
```

### 5.2 Asset Sources

```
EstateAssetAggregatorService.gatherUserAssets($user)
├─ Manual Estate Assets (Asset model)
│  └─ Direct user entries
├─ Properties (Property model)
│  ├─ Query: user_id OR joint_owner_id
│  ├─ Apply: ownership_percentage
│  └─ property_type: main_residence, secondary_residence, buy_to_let
├─ Investment Accounts (InvestmentAccount model)
│  ├─ Query: user_id OR joint_owner_id
│  ├─ Apply: ownership_percentage
│  └─ ISAs are NOT IHT-exempt
├─ Savings Accounts (SavingsAccount model)
│  ├─ Query: user_id OR joint_owner_id
│  └─ Apply: ownership_percentage
├─ Business Interests (BusinessInterest model)
│  ├─ Query: user_id OR joint_owner_id
│  ├─ BPR eligibility check:
│  │  └─ bpr_eligible=true AND trading_status='trading' AND 2+ years owned
│  └─ 100% IHT exempt if qualifying
├─ Chattels (Chattel model)
│  ├─ Query: user_id OR joint_owner_id
│  └─ Apply: ownership_percentage
├─ DC Pensions (DCPension model)
│  ├─ is_iht_exempt = true
│  └─ Outside estate if beneficiary nominated
└─ DB Pensions (DBPension model)
   ├─ current_value = 0
   └─ Die with member (no transfer value)
```

### 5.3 Liability Sources

```
EstateAssetAggregatorService.calculateUserLiabilities($user)
├─ Mortgages (Mortgage model)
│  ├─ Query: user_id OR joint_owner_id
│  └─ Apply: ownership share
└─ Estate Liabilities (Liability model)
   ├─ Query: user_id OR joint_owner_id
   └─ Apply: ownership share
```

### 5.4 Standardised Asset Object

All assets are converted to a standardised format:

```php
(object) [
    'user_id' => $user->id,
    'asset_type' => 'investment',          // property, cash, business, etc.
    'asset_name' => 'Provider - ISA',
    'current_value' => 50000,              // User's share
    'full_value' => 100000,                // Total asset value
    'ownership_type' => 'joint',           // individual, joint, tenants_in_common
    'ownership_percentage' => 50,          // Primary owner's %
    'is_primary_owner' => true,
    'is_iht_exempt' => false,
    'property_type' => null,               // For RNRB: main_residence, etc.
    'bpr_eligible' => false,               // For business assets
];
```

---

## 6. IHT Calculation Logic

**File:** `app/Services/Estate/IHTCalculationService.php`

### 6.1 Calculation Flow

```php
public function calculate(User $user, ?User $spouse = null, bool $dataSharingEnabled = false): array
```

**Step-by-step:**

1. **Check Cache**
   - Compare asset/liability hashes
   - Return cached result if unchanged

2. **Get Tax Config**
   - NRB, RNRB, rates from `TaxConfigService`

3. **Aggregate Taxable Assets**
   - User assets via `EstateAssetAggregatorService`
   - Spouse assets (if married + data sharing)
   - Filter out IHT-exempt (DC pensions, BPR business)

4. **Calculate Liabilities**
   - User liabilities
   - Spouse liabilities (if applicable)

5. **Calculate Net Estate**
   ```
   Net Estate = Gross Assets - Liabilities
   ```

6. **Calculate NRB**
   ```
   Single: £325,000
   Married: £650,000 (£325k × 2)
   Widowed: Up to £650,000 (if spouse's NRB transferred)
   ```

7. **Calculate RNRB** (see 6.2)

8. **Calculate Taxable Estate**
   ```
   Taxable = Net Estate - NRB - RNRB
   ```

9. **Calculate IHT**
   ```
   IHT = Taxable Estate × 40%
   ```

10. **Calculate Projections** (see Section 7)

11. **Save to Database**
    - Store in `IHTCalculation` table with hashes

### 6.2 RNRB Calculation

**File:** `IHTCalculationService.php` lines 222-275

```php
private function calculateRNRB(
    float $totalNetEstate,
    User $user,
    ?User $spouse,
    array $ihtConfig,
    bool $isMarried
): array
```

**Eligibility Requirements:**
- Must own main residence (`property_type = 'main_residence'`)
- Must leave to direct descendants (children, grandchildren)

**Calculation:**

```
Full RNRB:
- Single: £175,000
- Married: £350,000 (£175k × 2)

If estate > £2,000,000:
  Excess = Estate - £2,000,000
  Reduction = Excess × 0.5 (£1 per £2)
  RNRB Available = Full RNRB - Reduction

  If Reduction > Full RNRB:
    RNRB Available = £0 (fully tapered)
```

**Status Values:**
- `full` - Full RNRB available
- `tapered` - Partially or fully reduced
- `none` - No main residence

### 6.3 Result Structure

```php
[
    // Current values
    'user_gross_assets' => 500000,
    'spouse_gross_assets' => 300000,
    'total_gross_assets' => 800000,

    'user_total_liabilities' => 150000,
    'spouse_total_liabilities' => 50000,
    'total_liabilities' => 200000,

    'user_net_estate' => 350000,
    'spouse_net_estate' => 250000,
    'total_net_estate' => 600000,

    'nrb_available' => 650000,
    'nrb_message' => 'Combined Nil Rate Band of £650,000...',

    'rnrb_available' => 350000,
    'rnrb_status' => 'full',
    'rnrb_message' => 'Full Residence Nil Rate Band of £350,000...',

    'total_allowances' => 1000000,
    'taxable_estate' => 0,
    'iht_liability' => 0,
    'effective_rate' => 0,

    // Projected values at death
    'projected_gross_assets' => 1200000,
    'projected_liabilities' => 200000,
    'projected_net_estate' => 1000000,
    'projected_taxable_estate' => 0,
    'projected_iht_liability' => 0,
    'years_to_death' => 25,
    'estimated_age_at_death' => 85,

    'is_married' => true,
    'data_sharing_enabled' => true,
];
```

---

## 7. Projection Calculations

**File:** `IHTCalculationService.php` lines 152-350

Estate projections calculate the expected estate value at the user's (or surviving spouse's) estimated death. Unlike simple flat-rate projections, the system uses asset-specific growth models that account for income, expenses, and lifecycle changes.

### 7.1 Life Expectancy

```php
private function calculateLifeExpectancy(User $user): int
{
    // Query actuarial_life_tables
    $lifeExpectancy = DB::table('actuarial_life_tables')
        ->where('age', '<=', $currentAge)
        ->where('gender', $user->gender)
        ->where('table_year', '2020-2022')
        ->orderBy('age', 'desc')
        ->first();

    // Fallback: 85 - current_age
}
```

**For Married Couples:**
- Calculate life expectancy for BOTH spouses
- Use the LONGER life expectancy (second death scenario)
- IHT is calculated on the combined estate at second death

### 7.2 Retirement Age Determination

The retirement age is a critical transition point in projections:

```php
private function getRetirementAge(User $user): int
{
    // Priority order for retirement age
    if ($user->retirementProfile?->target_retirement_age) {
        return $user->retirementProfile->target_retirement_age;
    }

    if ($user->target_retirement_age) {
        return $user->target_retirement_age;
    }

    // Check DC pensions for retirement age
    $pensionRetirementAge = $user->dcPensions()
        ->whereNotNull('retirement_age')
        ->value('retirement_age');

    if ($pensionRetirementAge) {
        return $pensionRetirementAge;
    }

    return 68; // Default UK state pension age
}
```

### 7.3 Asset-Specific Projection Methods

#### 7.3.1 Cash Account Projection (Income/Expense Surplus Model)

Cash accounts do **NOT** use a flat growth percentage. Instead, they grow (or shrink) based on the user's income minus expenses.

```php
private function projectCashAccounts(
    User $user,
    float $currentCash,
    int $currentAge,
    int $retirementAge,
    int $deathAge
): float {
    $projectedCash = $currentCash;

    // Pre-Retirement Phase: currentAge → retirementAge
    for ($age = $currentAge; $age < $retirementAge; $age++) {
        $annualIncome = $this->getTotalAnnualIncome($user);
        $annualExpenses = $this->getCurrentAnnualExpenses($user);
        $surplus = $annualIncome - $annualExpenses;
        $projectedCash += $surplus;
    }

    // Post-Retirement Phase: retirementAge → deathAge
    for ($age = $retirementAge; $age < $deathAge; $age++) {
        $retirementIncome = $this->getRetirementIncome($user, $age);
        $retirementExpenses = $this->getRetirementExpenses($user);
        $surplus = $retirementIncome - $retirementExpenses;
        $projectedCash += $surplus;
    }

    return max(0, $projectedCash); // Cannot go negative (would draw from investments)
}
```

**Income Calculation (Pre-Retirement):**
```php
private function getTotalAnnualIncome(User $user): float
{
    $income = $user->annual_employment_income
            + $user->annual_self_employment_income
            + $user->annual_rental_income
            + $user->annual_dividend_income
            + $user->annual_interest_income
            + $user->annual_other_income
            + $user->annual_trust_income;

    // Include spouse income if linked and data sharing enabled
    if ($user->spouse && $user->dataSharingEnabled()) {
        $income += $this->getTotalAnnualIncome($user->spouse);
    }

    return $income;
}
```

**Expenses Calculation with Fallbacks:**
```php
// Pre-Retirement (from ExpenditureProfile with income-based fallback)
private function getCurrentAnnualExpenses(User $user): float
{
    $profile = $user->expenditureProfile;
    if ($profile && $profile->total_monthly_expenditure > 0) {
        return $profile->total_monthly_expenditure * 12;
    }

    // FALLBACK: Use 70% of total annual income as estimated expenses
    // This is more realistic than £0 and prevents unrealistic cash growth
    $totalIncome = $this->getTotalAnnualIncome($user);
    return $totalIncome * 0.70;
}

// Post-Retirement (from RetirementProfile with fallbacks)
private function getRetirementExpenses(User $user): float
{
    $profile = $user->retirementProfile;

    // Priority 1: Use explicit retirement expenses if set
    if ($profile) {
        $expenses = ($profile->essential_expenditure ?? 0) + ($profile->lifestyle_expenditure ?? 0);
        if ($expenses > 0) {
            return $expenses;
        }

        // Priority 2: Use target_retirement_income as proxy for expenses
        if ($profile->target_retirement_income > 0) {
            return $profile->target_retirement_income;
        }
    }

    // Priority 3: Use 50% of pre-retirement income as retirement expenses
    // (assumes reduced lifestyle in retirement)
    $preRetirementIncome = $this->getTotalAnnualIncome($user);
    return $preRetirementIncome * 0.50;
}
```

**Expense Fallback Summary:**
| Scenario | Pre-Retirement Expenses | Retirement Expenses |
|----------|------------------------|---------------------|
| Full data | `total_monthly_expenditure × 12` | `essential + lifestyle` |
| No expenditure profile | `income × 70%` | `target_retirement_income` |
| No retirement profile | `income × 70%` | `pre-retirement income × 50%` |
| No income data | `£0` | `£0` |

**Retirement Income with State Pension:**
```php
private function getRetirementIncome(User $user, int $age): float
{
    $income = $user->retirementProfile?->target_retirement_income ?? 0;

    // Add State Pension if user has reached state pension age
    $statePensionAge = $user->state_pension_age ?? 67;
    if ($age >= $statePensionAge) {
        $statePension = $user->statePensions()->first();
        $income += $statePension?->estimated_annual_amount ?? 0;
    }

    // Include spouse retirement income and state pension
    if ($user->spouse && $user->dataSharingEnabled()) {
        $spouseIncome = $user->spouse->retirementProfile?->target_retirement_income ?? 0;
        $spouseStatePensionAge = $user->spouse->state_pension_age ?? 67;

        if ($age >= $spouseStatePensionAge) {
            $spouseStatePension = $user->spouse->statePensions()->first();
            $spouseIncome += $spouseStatePension?->estimated_annual_amount ?? 0;
        }

        $income += $spouseIncome;
    }

    return $income;
}
```

#### 7.3.2 Investment Account Projection (Monte Carlo 80%)

Investment accounts use Monte Carlo simulation by default, returning the 80% confidence value:

```php
private function projectInvestments(
    User $user,
    int $yearsToProject
): float {
    $assumptions = $this->assumptionsService->getEstateAssumptions($user);

    if ($assumptions['investment_growth_method'] === 'monte_carlo') {
        // Monte Carlo: returns p20 (80% probability of achieving or exceeding)
        return $this->investmentProjectionService
            ->getAccountProjectedValue80($user, $yearsToProject);
    }

    // Custom rate: simple compound growth
    $customRate = $assumptions['custom_investment_rate'] / 100;
    $currentValue = $this->getTotalInvestmentValue($user);

    return $currentValue * pow(1 + $customRate, $yearsToProject);
}
```

**Monte Carlo Implementation:**
- 1000 iterations using Box-Muller transform for normal distribution
- Expected returns based on portfolio risk profile
- Returns 20th percentile (p20) = 80% confidence level
- Factors in annual volatility and mean reversion

#### 7.3.3 Property Projection (3% Default, Configurable)

Properties use a fixed annual growth rate:

```php
private function projectProperties(
    User $user,
    int $yearsToProject
): float {
    $assumptions = $this->assumptionsService->getEstateAssumptions($user);
    $propertyGrowthRate = $assumptions['property_growth_rate'] / 100; // Default 3%

    $currentPropertyValue = $user->properties->sum('current_value');

    // Include spouse properties if linked
    if ($user->spouse && $user->dataSharingEnabled()) {
        $currentPropertyValue += $user->spouse->properties->sum('current_value');
    }

    return $currentPropertyValue * pow(1 + $propertyGrowthRate, $yearsToProject);
}
```

#### 7.3.4 Liability Projection (Amortisation to End Date)

Liabilities reduce to zero at their end date. If no end date is specified, retirement age is assumed:

```php
private function projectLiabilities(
    User $user,
    int $currentAge,
    int $retirementAge,
    int $deathAge
): float {
    $projectedLiabilities = 0;
    $yearsToProject = $deathAge - $currentAge;

    // Project mortgages
    foreach ($user->mortgages as $mortgage) {
        $endDate = $mortgage->end_date;

        // If no end date, assume paid off at retirement
        if (!$endDate) {
            $endYear = $retirementAge - $currentAge;
        } else {
            $endYear = Carbon::parse($endDate)->year - now()->year;
        }

        if ($yearsToProject < $endYear) {
            // Still have remaining balance at death
            $remainingTerm = $endYear - $yearsToProject;
            $totalTerm = $endYear;
            $projectedBalance = $mortgage->outstanding_balance * ($remainingTerm / $totalTerm);
            $projectedLiabilities += max(0, $projectedBalance);
        }
        // else: mortgage paid off before death, contributes £0
    }

    // Project other liabilities
    foreach ($user->liabilities as $liability) {
        $endDate = $liability->end_date;

        // If no end date, assume cleared at retirement
        if (!$endDate) {
            $endYear = $retirementAge - $currentAge;
        } else {
            $endYear = Carbon::parse($endDate)->year - now()->year;
        }

        if ($yearsToProject < $endYear) {
            $remainingTerm = $endYear - $yearsToProject;
            $totalTerm = $endYear;
            $projectedBalance = $liability->current_balance * ($remainingTerm / $totalTerm);
            $projectedLiabilities += max(0, $projectedBalance);
        }
    }

    return $projectedLiabilities;
}
```

#### 7.3.5 Integrated Cash-Investment Drawdown Model

When cash surplus turns negative during retirement, the deficit is drawn from investment accounts. This creates an integrated year-by-year projection where:

1. **Cash deficit reduces investments** - Before applying growth
2. **Deficit split equally** - Across all investment accounts
3. **Growth applied after drawdown** - Investment growth happens on reduced balance

```php
/**
 * Integrated projection: Cash deficits drawn from investments year-by-year
 *
 * For each year:
 * 1. Calculate cash surplus (income - expenses)
 * 2. If negative, deduct deficit from investments BEFORE applying growth
 * 3. Apply investment growth rate to reduced balance
 * 4. Repeat for each year until death
 */
private function projectCashAndInvestmentsIntegrated(
    User $user,
    ?User $spouse,
    int $currentAge,
    int $retirementAge,
    int $deathAge,
    bool $dataSharingEnabled
): array {
    // Get initial values
    $cashBalance = $this->getCurrentCashValue($user);
    $investments = $this->getInvestmentAccounts($user, $spouse, $dataSharingEnabled);
    $totalInvestments = array_sum(array_column($investments, 'balance'));

    // Get growth rate (Monte Carlo 80% annualised or custom)
    $assumptions = $this->assumptionsService->getEstateAssumptions($user);
    $investmentGrowthRate = $assumptions['investment_growth_method'] === 'monte_carlo'
        ? $this->getMonteCarloAnnualRate($user)
        : ($assumptions['custom_investment_rate'] / 100);

    // Year-by-year projection
    for ($age = $currentAge; $age < $deathAge; $age++) {
        // Step 1: Calculate cash surplus for this year
        if ($age < $retirementAge) {
            $income = $this->getTotalAnnualIncome($user);
            $expenses = $this->getCurrentAnnualExpenses($user);
        } else {
            $income = $this->getRetirementIncome($user, $age);
            $expenses = $this->getRetirementExpenses($user);
        }
        $surplus = $income - $expenses;

        // Step 2: Update cash balance
        $cashBalance += $surplus;

        // Step 3: If cash goes negative, draw from investments
        if ($cashBalance < 0) {
            $deficit = abs($cashBalance);
            $cashBalance = 0; // Reset cash to zero

            // Distribute deficit equally across all investment accounts
            $accountCount = count($investments);
            if ($accountCount > 0 && $totalInvestments > 0) {
                $deficitPerAccount = $deficit / $accountCount;

                foreach ($investments as &$account) {
                    $account['balance'] = max(0, $account['balance'] - $deficitPerAccount);
                }
                unset($account);

                // Recalculate total after drawdown
                $totalInvestments = array_sum(array_column($investments, 'balance'));
            }
        }

        // Step 4: Apply investment growth AFTER drawdown
        foreach ($investments as &$account) {
            $account['balance'] *= (1 + $investmentGrowthRate);
        }
        unset($account);
        $totalInvestments = array_sum(array_column($investments, 'balance'));
    }

    return [
        'projected_cash' => round($cashBalance, 2),
        'projected_investments' => round($totalInvestments, 2),
        'investment_accounts' => $investments, // For individual account projections
    ];
}

/**
 * Get all investment accounts with current balances
 */
private function getInvestmentAccounts(User $user, ?User $spouse, bool $dataSharingEnabled): array
{
    $accounts = [];

    // User's investment accounts
    foreach ($user->investmentAccounts as $account) {
        if (!$account->is_iht_exempt) {
            $accounts[] = [
                'id' => $account->id,
                'name' => $account->account_name,
                'owner' => 'user',
                'balance' => (float) $account->current_value,
            ];
        }
    }

    // Spouse's investment accounts (if data sharing enabled)
    if ($dataSharingEnabled && $spouse) {
        foreach ($spouse->investmentAccounts as $account) {
            if (!$account->is_iht_exempt) {
                $accounts[] = [
                    'id' => $account->id,
                    'name' => $account->account_name,
                    'owner' => 'spouse',
                    'balance' => (float) $account->current_value,
                ];
            }
        }
    }

    return $accounts;
}
```

**Integrated Projection Timeline Example (Mitchell Family):**

```
Year  Age  Phase          Income    Expenses   Surplus    Cash      Investments
────  ───  ─────────────  ────────  ─────────  ─────────  ────────  ────────────
1     49   Pre-Ret        £265,000  £185,500   +£79,500   £193,730  £305,000
...
11    59   Pre-Ret        £265,000  £185,500   +£79,500   £988,730  £611,000
12    60   Retired        £75,000   £135,000   -£60,000   £928,730  £639,000
...
27    75   Retired        £75,000   £135,000   -£60,000   £28,730   £1,200,000
28    76   Retired        £75,000   £135,000   -£60,000   £0        £1,168,730  ← Deficit drawn
29    77   Retired        £75,000   £135,000   -£60,000   £0        £1,138,xxx  ← £60k drawn
...
37    85   Retired        £75,000   £135,000   -£60,000   £0        £xxx,xxx
```

**Key Points:**
- Cash cannot go negative; deficits draw from investments
- Investment growth is applied AFTER the annual drawdown
- Deficit is split equally across all accounts (fair distribution)
- If investments are exhausted, a warning is generated

### 7.4 Combined Projection Calculation

The combined projection uses the **integrated cash-investment model** to ensure that:
1. Cash deficits are drawn from investments before growth is applied
2. Properties and chattels are projected independently
3. Individual investment account values are tracked for the breakdown display

```php
public function calculateProjectedValues(
    User $user,
    ?User $spouse,
    bool $isMarried,
    bool $dataSharingEnabled = false
): array {
    $currentAge = $user->date_of_birth?->age ?? 50;
    $retirementAge = $this->getRetirementAge($user);
    $deathAge = $this->getEstimatedDeathAge($user, $spouse, $isMarried);
    $yearsToProject = $deathAge - $currentAge;

    // Use INTEGRATED cash-investment projection (cash deficits draw from investments)
    $cashInvestmentResult = $this->projectCashAndInvestmentsIntegrated(
        $user,
        $spouse,
        $currentAge,
        $retirementAge,
        $deathAge,
        $dataSharingEnabled
    );

    $projectedCash = $cashInvestmentResult['projected_cash'];
    $projectedInvestments = $cashInvestmentResult['projected_investments'];
    $investmentAccountBreakdown = $cashInvestmentResult['investment_accounts'];

    // Project properties independently (no cash interaction)
    $projectedProperties = $this->projectProperties($user, $yearsToProject);

    // Get chattels at current value (no appreciation assumed)
    $projectedChattels = $this->getCurrentChattelsValue($user, $spouse, $dataSharingEnabled);

    // Project liabilities (mortgages, debts)
    $projectedLiabilities = $this->projectLiabilities(
        $user,
        $currentAge,
        $retirementAge,
        $deathAge
    );

    // Calculate totals (include chattels)
    $projectedGrossAssets = $projectedCash + $projectedInvestments + $projectedProperties + $projectedChattels;
    $projectedNetEstate = $projectedGrossAssets - $projectedLiabilities;

    // Calculate projected IHT
    $totalAllowances = $this->calculateAllowances($user, $spouse, $isMarried);
    $projectedTaxableEstate = max(0, $projectedNetEstate - $totalAllowances);
    $ihtRate = $this->determineIHTRate($user, $projectedNetEstate, $totalAllowances['nrb']);
    $projectedIHTLiability = $projectedTaxableEstate * $ihtRate['rate'];

    return [
        'projected_cash' => round($projectedCash, 2),
        'projected_investments' => round($projectedInvestments, 2),
        'projected_properties' => round($projectedProperties, 2),
        'projected_gross_assets' => round($projectedGrossAssets, 2),
        'projected_liabilities' => round($projectedLiabilities, 2),
        'projected_net_estate' => round($projectedNetEstate, 2),
        'projected_taxable_estate' => round($projectedTaxableEstate, 2),
        'projected_iht_liability' => round($projectedIHTLiability, 2),
        'years_to_death' => $yearsToProject,
        'retirement_age' => $retirementAge,
        'estimated_age_at_death' => $deathAge,
    ];
}
```

### 7.5 Assumptions Summary

| Asset Type | Projection Method | Default Value | Configurable |
|------------|-------------------|---------------|--------------|
| **Cash Accounts** | Income - Expenses surplus | n/a | No (derived) |
| **Investment Accounts** | Monte Carlo (p20) with drawdown | 80% confidence | Yes |
| **Properties** | Compound growth | 3% annual | Yes |
| **Chattels/Valuables** | No growth (current value) | 0% | No |
| **Mortgages** | Linear amortisation | End at retirement | No |
| **Other Liabilities** | Linear amortisation | End at retirement | No |
| **Inflation** | Applied to expenses | 2.5% | Yes |
| **State Pension** | Included from SPA | Actual amount | No |

**Integrated Cash-Investment Model:**
- Cash and investments are projected together year-by-year
- When cash goes negative, deficit is drawn from investments BEFORE growth
- Deficit is split equally across all investment accounts
- Growth is then applied to the reduced investment balance

### 7.6 Key Transition Points

```
Timeline: Current Age → Retirement Age → State Pension Age → Death
          ─────────────────────────────────────────────────────────

Pre-Retirement Phase:
├─ Income: Employment, self-employment, rental, dividends, interest, other
├─ Expenses: ExpenditureProfile.total_monthly_expenditure × 12 (or income × 70%)
├─ Cash: Grows by (Income - Expenses) annually
└─ Investments: Grow at Monte Carlo rate (no drawdown typically needed)

Post-Retirement Phase:
├─ Income: target_retirement_income + pension drawdown
├─ State Pension: Added from state_pension_age onwards
├─ Expenses: essential + lifestyle (or target_retirement_income or income × 50%)
├─ Cash: Grows/shrinks by (Retirement Income - Retirement Expenses)
└─ Investments: IF cash goes negative → drawdown deficit → THEN apply growth

Cash-Investment Interaction:
├─ IF cash surplus positive → cash grows, investments grow independently
├─ IF cash surplus negative AND cash > 0 → cash reduces, investments grow
├─ IF cash surplus negative AND cash = 0 → deficit drawn from investments
└─ IF investments exhausted → estate insolvency warning

Liability End Points:
├─ Mortgages: End at mortgage.end_date OR retirement_age
└─ Other Debts: End at liability.end_date OR retirement_age
```

### 7.7 Edge Cases

| Scenario | Handling |
|----------|----------|
| No income data | Use £0 (conservative - no cash growth) |
| No expenditure profile | Use £0 (all income becomes surplus) |
| No retirement profile | Use 50% of pre-retirement income as retirement expenses |
| Already retired | Skip pre-retirement phase, use retirement figures |
| Negative cash surplus | Draw deficit from investment accounts equally |
| Cash goes to zero | All future deficits drawn from investments |
| Investments exhausted | Generate warning; estate likely insolvent before death |
| No end date on liability | Assume cleared at retirement age |
| Already past retirement age | All liabilities assume £0 remaining |
| No investment accounts | Cash deficit cannot be covered; cap cash at £0 |
| Single investment account | All deficit drawn from that one account |

**Investment Drawdown Sequence:**
1. Cash balance goes negative → deficit calculated
2. Deficit divided equally by number of investment accounts
3. Each account reduced by `deficit ÷ account_count`
4. Investment growth applied to reduced balance
5. Process repeats for each remaining year

**Warning Conditions:**
- If total investments fall below £0 before death age → "Estate may be insolvent"
- If projected cash + investments < 0 → "Lifestyle unsustainable without asset sales"

---

## 8. IHT Mitigation Strategies

### 8.1 Decision Tree

**File:** `EstateAgent.php` lines 125-204

The mitigation strategy follows a **cost-efficient priority order**, using CLTs only as a last resort:

```
┌─────────────────────────────────────────────────────────────────────┐
│                    IHT LIABILITY > £0?                              │
└─────────────────────────────────────────────────────────────────────┘
                              │ YES
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│ STEP 1: CHARITABLE BEQUEST CHECK (Rate Reduction)                  │
├─────────────────────────────────────────────────────────────────────┤
│ Check user.charitable_bequest toggle:                               │
│                                                                     │
│ IF charitable_bequest = TRUE:                                       │
│   • Calculate baseline: Net Estate − NRB (excludes RNRB)           │
│   • Minimum donation required: baseline × 10%                       │
│   • Apply reduced IHT rate: 36% (instead of 40%)                   │
│   • Rate saving: 4% of taxable estate                               │
│                                                                     │
│ IF charitable_bequest = FALSE:                                      │
│   • Use standard IHT rate: 40%                                      │
│   • Consider recommending charitable giving if:                     │
│     - User has existing charitable intentions                       │
│     - Rate saving exceeds emotional cost of bequest                 │
│                                                                     │
│ ➤ effective_iht_rate = charitable_bequest ? 0.36 : 0.40            │
│ ➤ Recalculate IHT liability using effective rate                   │
│                                                                     │
│ Data Sources:                                                       │
│ • Toggle: users.charitable_bequest (boolean)                        │
│ • Percentage: iht_profiles.charitable_giving_percent (decimal)      │
│ • Rate: TaxConfigService.get('inheritance_tax.reduced_rate_charity')│
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│ STEP 2: LIQUIDITY & AFFORDABILITY ASSESSMENT                       │
├─────────────────────────────────────────────────────────────────────┤
│ • Query all assets from EstateAssetAggregatorService               │
│ • Categorise: liquid (cash, investments) vs illiquid (property)    │
│ • Calculate surplus income (income - expenditure)                  │
│ • Identify affordability constraints for ongoing commitments       │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│ STEP 3: CHECK EXISTING LIFE COVER                                  │
├─────────────────────────────────────────────────────────────────────┤
│ • Query Protection module for all life policies                    │
│ • Check: Is policy written in trust? (bypasses estate if yes)      │
│ • Calculate: Usable Cover = Total Cover − Outstanding Debts        │
│ • Only excess above debts can offset IHT liability                 │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│ STEP 4: ANNUAL GIFTING STRATEGY (First Resort)                     │
├─────────────────────────────────────────────────────────────────────┤
│ Immediately exempt gifts - no 7-year wait, no tax risk:            │
│                                                                     │
│ • Annual Exemption: £3,000/year × years to life expectancy         │
│ • Normal Expenditure from Income: surplus × safe percentage        │
│ • Small Gifts: £250 per recipient (unlimited recipients)           │
│ • Wedding Gifts: £5,000 (parent), £2,500 (grandparent), £1,000     │
│                                                                     │
│ ➤ IF total covers IHT liability → RECOMMEND (stop here)            │
│ ➤ IF NOT → remaining_liability = IHT - gifting_capacity            │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│ STEP 5: LIFE COVER STRATEGY (Second Resort)                        │
├─────────────────────────────────────────────────────────────────────┤
│ IF user age ≤ 50:                                                   │
│   • Calculate whole of life premium for remaining liability        │
│   • Check premium affordability against surplus income             │
│   • Policy MUST be written in trust to bypass estate               │
│   • RECOMMEND if affordable                                         │
│                                                                     │
│ IF user age > 50:                                                   │
│   • Life cover premiums likely prohibitively expensive             │
│   • Only use EXISTING cover excess (Step 3)                        │
│   • Skip new life cover → proceed to PET strategy                  │
│                                                                     │
│ ➤ remaining_liability = IHT - gifting - life_cover_excess          │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│ STEP 6: PET GIFTING STRATEGY (Third Resort)                        │
├─────────────────────────────────────────────────────────────────────┤
│ Potentially Exempt Transfers - exempt if donor survives 7 years:   │
│                                                                     │
│ • Calculate 7-year cycles: floor(years_to_death / 7)               │
│ • Each cycle can gift up to NRB (£325,000) tax-efficiently         │
│ • Apply taper relief if donor likely to die within 7 years         │
│                                                                     │
│ IF user age > 50 AND no existing life cover:                       │
│   → PRIORITISE PETs over purchasing new life cover                 │
│                                                                     │
│ ➤ remaining_liability = IHT - gifting - life_cover - PETs          │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│ STEP 7: CLT INTO TRUST (Last Resort ONLY)                          │
├─────────────────────────────────────────────────────────────────────┤
│ ⚠️  Only if Steps 4-6 do NOT fully cover the liability             │
│                                                                     │
│ Chargeable Lifetime Transfer costs:                                 │
│ • Immediate 20% charge on amount exceeding available NRB           │
│ • Additional 20% if death within 7 years (40% total)               │
│ • Periodic charges: max 6% every 10 years                          │
│ • Exit charges: proportionate to time since last anniversary       │
│                                                                     │
│ RECOMMEND with:                                                     │
│ • Full cost/benefit analysis                                        │
│ • Comparison of immediate charge vs potential IHT saving           │
│ • Multi-cycle strategy if time permits (7-year cycles)             │
└─────────────────────────────────────────────────────────────────────┘

Additional Recommendations (if health score < 50):
├─ Complete IHT profile
├─ Review beneficiary designations
└─ Write/update will
```

#### Charitable Bequest Calculation Detail

The 36% reduced rate requires leaving at least 10% of the "baseline amount" to charity:

```php
// Baseline calculation (excludes RNRB - only NRB counts)
$baseline = max(0, $netEstate - $nrbAvailable);

// Minimum charitable donation for reduced rate
$minimumDonation = $baseline * 0.10;

// IHT rate determination
$ihtRate = ($charitableDonation >= $minimumDonation) ? 0.36 : 0.40;

// Potential saving from charitable giving
$potentialSaving = $taxableEstate * 0.04; // 40% - 36% = 4% saving
```

**Example:**
- Net Estate: £1,000,000
- NRB Available: £325,000
- Baseline: £675,000
- Minimum Donation: £67,500 (10% of baseline)
- Taxable Estate: £675,000
- IHT at 40%: £270,000
- IHT at 36%: £243,000
- Saving: £27,000 (by donating £67,500 to charity)

#### Charity Code Consolidation (Implementation Note)

The charity logic currently exists in multiple locations and needs consolidation:

| Location | Current State | Consolidation Action |
|----------|---------------|----------------------|
| `users.charitable_bequest` | Boolean toggle | Keep as user preference |
| `iht_profiles.charitable_giving_percent` | Decimal percentage | **Consolidate** - use this as source of truth |
| `IHTCalculator.calculateCharitableReduction()` | Returns 0.36 or 0.40 | Keep - uses TaxConfigService |
| `IHTCalculationService` | **Does NOT use charitable_giving_percent** | **FIX** - integrate charity rate |
| `IHTPlanning.vue` | Frontend calculations only | Keep for display, backend is source of truth |
| `GiftingStrategy.php` | Recommends 10% giving | Keep as recommendation engine |

**Required Changes:**

1. **IHTCalculationService.php** - Must query `IHTProfile.charitable_giving_percent` and apply reduced rate:

```php
// In calculateIHT() method
$charitablePercent = $ihtProfile->charitable_giving_percent ?? 0;
$ihtRate = $this->ihtCalculator->calculateCharitableReduction(
    $netEstate,
    $charitablePercent
);
```

2. **Sync Toggle and Percentage** - When `user.charitable_bequest` is toggled:
   - TRUE → Set `iht_profile.charitable_giving_percent = 10` (minimum)
   - FALSE → Set `iht_profile.charitable_giving_percent = 0`

3. **Single Source of Truth** - All calculations should use:
   - Rate: `TaxConfigService.get('inheritance_tax.reduced_rate_charity')` → 0.36
   - Threshold: `TaxConfigService.get('inheritance_tax.charity_threshold_percent')` → 0.10
   - Never hardcode 0.36 or 10% in services

### 8.2 Gifting Strategies

**File:** `app/Services/Estate/GiftingStrategyOptimizer.php`

#### Priority 1: Annual Exemption

```php
// £3,000 per year, immediately exempt
$totalGifted = 3000 * $yearsUntilDeath;
$ihtSaved = $totalGifted * 0.40;

// Example: 20 years × £3,000 = £60,000 gifted
// IHT saved: £60,000 × 40% = £24,000
```

#### Priority 2: Normal Expenditure from Income

```php
// Must be regular, from surplus income, not affecting standard of living
$surplusIncome = $totalIncome - $annualExpenditure;
$safeGiftingAmount = $surplusIncome * 0.5; // Conservative 50%

// Minimum £1,000/year to be worthwhile
// Immediately exempt if regular pattern established (3+ years)
```

#### Priority 3: Potentially Exempt Transfers (PETs)

```php
// 7-year cycle strategy
$complete7YearCycles = floor($yearsUntilDeath / 7);
$amountPerCycle = $totalNRBAvailable; // Up to NRB per cycle

// Gift becomes exempt after 7 years
// Taper relief applies 3-7 years
```

| Years | Tax Rate |
|-------|----------|
| 0-3 | 40% |
| 3-4 | 32% |
| 4-5 | 24% |
| 5-6 | 16% |
| 6-7 | 8% |
| 7+ | 0% |

#### Priority 4: Chargeable Lifetime Transfers (CLTs)

```php
// Last resort - attracts immediate charge
$immediateCLTCharge = $targetGiftAmount * 0.20; // 20% on excess over NRB

// If death within 7 years: additional charge to bring total to 40%
$potentialDeathCharge = ($excessOverNRB * 0.40) - $immediateCLTCharge;
```

### 8.3 Trust Strategies

**File:** `app/Services/Estate/PersonalizedTrustStrategyService.php`

#### Strategy 1: Immediate Discretionary Trust (CLT)

```php
[
    'strategy_name' => 'Immediate Discretionary Trust (CLT)',
    'priority' => 1,
    'amount' => $liquidAssets,
    'iht_saving_potential' => min($amount, $availableNRB) * 0.40,
    'lifetime_tax_charge' => max(0, $amount - $availableNRB) * 0.20,
    'time_frame' => '7 years for full effectiveness',
    'risk_level' => $excessOverNRB > 0 ? 'Medium' : 'Low',
]
```

**Benefits:**
- Assets immediately outside estate
- Growth outside estate
- Flexibility for trustees
- Protection from creditors

**Risks:**
- 20% immediate charge if over NRB
- Additional 20% if death within 7 years
- 10-year anniversary charges (6%)
- Loss of control

#### Strategy 2: Multi-Cycle CLT

```php
// Use multiple 7-year cycles
$cyclesPossible = floor($yearsUntilDeath / 7);
$amountPerCycle = $availableNRB;
$totalOverLifetime = $amountPerCycle * $cyclesPossible;
```

**Example:**
- 21 years until death = 3 cycles
- £325,000 per cycle = £975,000 total
- No immediate charge (each within NRB)
- IHT saved: £975,000 × 40% = £390,000

#### Strategy 3: Loan Trust

```php
// Loan to trust (NOT a gift)
$loanAmount = $liquidAssets;
$assumedGrowthRate = 0.05; // 5%/year
$growthOver20Years = $loanAmount * (pow(1.05, 20) - 1);
$ihtSaving = $growthOver20Years * 0.40;
```

**Benefits:**
- No immediate IHT charge
- Growth outside estate
- Retain access to capital
- No 7-year wait for growth benefit

**Mechanics:**
- Loan stays in estate (no IHT benefit on principal)
- Investment growth is outside estate
- Can write off loan using annual exemptions (£3,000/year)

#### Strategy 4: Discounted Gift Trust

```php
// Gift with retained income rights
$discountRate = min(0.50, ($incomeRate * min($lifeExpectancy, 20)) / 2);
$giftValue = $totalLiquid;
$discountValue = $giftValue * $discountRate;
$cltValue = $giftValue - $discountValue; // Lower chargeable amount
```

**Benefits:**
- Reduced CLT value
- Retain income for life
- All growth outside estate

#### Strategy 5: Property Trust Planning

**Cannot gift main residence and continue living in it (GROB rules)**

Options:
- **Downsizing:** Release equity for gifting
- **Life Interest Trust (in Will):** Spouse lives in property for life
- **Shared Ownership:** Gift % to children, they pay market rent

### 8.4 Life Insurance Strategy

**File:** `ComprehensiveEstatePlanService.php`

```php
// Whole of Life policy in trust
[
    'recommended_approach' => 'Whole of Life Policy',
    'sum_assured_required' => $ihtLiability,
    'written_in_trust' => true, // Proceeds outside estate
]
```

**Premium Estimation:**
```php
$monthlyRatePer1000 = 0.50 + ($age - 40) * 0.05;
$monthlyPremium = ($sumAssured / 1000) * $monthlyRatePer1000;
```

---

## 9. Will Integration

### 9.1 Will Model

**File:** `app/Models/Estate/Will.php`

```php
[
    'has_will' => true,
    'spouse_primary_beneficiary' => true,
    'spouse_bequest_percentage' => 100,
    'executor_name' => 'John Smith',
    'executor_notes' => 'Professional executor',
    'will_last_updated' => '2024-01-15',
]
```

### 9.2 Bequest Types

| Type | Description |
|------|-------------|
| `percentage_of_estate` | e.g., "50% of residual estate" |
| `specific_amount` | e.g., "£10,000 to charity" |
| `specific_asset` | e.g., "Main residence to eldest child" |

### 9.3 Intestacy Rules

**File:** `resources/js/components/Estate/IntestacyRules.vue`

| Scenario | Distribution |
|----------|--------------|
| Spouse + children | Spouse: £322,000 + 50% remainder; Children: 50% remainder |
| Children only | Equal shares |
| Spouse only | 100% to spouse |
| No relatives | Escheated to Crown |

### 9.4 Charitable Bequest Analysis

The system automatically checks charitable bequests against the 10% threshold for the reduced 36% IHT rate.

#### Bequest Evaluation Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│ STEP 1: IDENTIFY CHARITABLE BEQUESTS                               │
├─────────────────────────────────────────────────────────────────────┤
│ Query bequests where:                                               │
│ • beneficiary_type = 'charity' OR                                   │
│ • beneficiary_name contains registered charity name/number          │
│                                                                     │
│ Sum all charitable bequests:                                        │
│ • percentage_of_estate → Convert to £ value                        │
│ • specific_amount → Direct value                                    │
│ • specific_asset → Use asset current_value                          │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│ STEP 2: CALCULATE THRESHOLD                                        │
├─────────────────────────────────────────────────────────────────────┤
│ baseline = Net Estate − NRB (excludes RNRB)                        │
│ threshold = baseline × 10%                                          │
│                                                                     │
│ Example: £1,000,000 net estate - £325,000 NRB = £675,000 baseline  │
│          Threshold = £67,500                                        │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│ STEP 3: COMPARE AND INFORM                                         │
├─────────────────────────────────────────────────────────────────────┤
│ IF charitable_bequest_total < threshold:                            │
│   ├─ Status: BELOW THRESHOLD                                        │
│   ├─ IHT Rate: 40% (standard)                                       │
│   ├─ Shortfall: threshold - charitable_bequest_total                │
│   └─ Message: "Increase charitable bequests by £X to qualify        │
│               for 36% reduced rate and save £Y in IHT"              │
│                                                                     │
│ IF charitable_bequest_total = threshold (±1%):                      │
│   ├─ Status: AT THRESHOLD                                           │
│   ├─ IHT Rate: 36% (reduced)                                        │
│   └─ Message: "Your charitable bequests qualify for the reduced     │
│               36% IHT rate. Consider increasing slightly for        │
│               margin of safety."                                    │
│                                                                     │
│ IF charitable_bequest_total > threshold:                            │
│   ├─ Status: ABOVE THRESHOLD                                        │
│   ├─ IHT Rate: 36% (reduced)                                        │
│   ├─ Excess: charitable_bequest_total - threshold                   │
│   └─ Message: "Charitable bequests exceed minimum threshold.        │
│               You comfortably qualify for the 36% reduced rate."    │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│ STEP 4: AUTO-ADJUST IHT CALCULATION                                │
├─────────────────────────────────────────────────────────────────────┤
│ IF status = AT_THRESHOLD or ABOVE_THRESHOLD:                        │
│   • Set effective_iht_rate = 0.36                                   │
│   • Recalculate: iht_liability = taxable_estate × 0.36             │
│   • Update IHTProfile.charitable_giving_percent                     │
│   • Sync with user.charitable_bequest toggle                        │
│                                                                     │
│ IF status = BELOW_THRESHOLD:                                        │
│   • Set effective_iht_rate = 0.40                                   │
│   • Generate recommendation to increase charitable giving           │
│   • Show potential IHT saving if threshold met                      │
└─────────────────────────────────────────────────────────────────────┘
```

#### Charitable Bequest Status Display

```php
// In WillAnalysisService or IHTCalculationService
public function analyzeCharitableBequests(User $user, float $netEstate): array
{
    $nrb = $this->taxConfig->get('inheritance_tax.nil_rate_band');
    $baseline = max(0, $netEstate - $nrb);
    $threshold = $baseline * 0.10;

    $charitableBequests = $this->getCharitableBequestTotal($user);

    if ($charitableBequests >= $threshold) {
        $status = $charitableBequests > $threshold * 1.01 ? 'above' : 'at';
        $effectiveRate = 0.36;
        $shortfall = 0;
        $excess = $charitableBequests - $threshold;
    } else {
        $status = 'below';
        $effectiveRate = 0.40;
        $shortfall = $threshold - $charitableBequests;
        $excess = 0;
    }

    $potentialSaving = $baseline * 0.04; // 40% - 36% = 4%

    return [
        'status' => $status,                    // 'below', 'at', 'above'
        'charitable_total' => $charitableBequests,
        'threshold' => $threshold,
        'shortfall' => $shortfall,
        'excess' => $excess,
        'effective_rate' => $effectiveRate,
        'potential_saving' => $status === 'below' ? $potentialSaving : 0,
        'current_saving' => $status !== 'below' ? $potentialSaving : 0,
    ];
}
```

### 9.5 Trust-Triggering Wishes

Certain wishes in a will indicate the need for trust structures to be created. The system scans bequest notes and executor instructions for these patterns.

#### Wish Patterns That Trigger Trust Recommendations

| Wish Pattern | Trust Type Suggested | Reason |
|--------------|---------------------|--------|
| "Education provided for children/kids" | Bare Trust or 18-25 Trust | Funds held until child reaches education age |
| "Income provided for family/spouse" | Interest in Possession Trust | Provides income stream while preserving capital |
| "Income provided for children" | Discretionary Trust | Flexible distribution for minors |
| "Inheritance at age 25" | Age 18-25 Trust | Delays full access until specified age |
| "Protect from divorce/creditors" | Discretionary Trust | Asset protection |
| "Special needs provision" | Disabled Person's Trust | IHT-efficient, preserves benefits |
| "Business to continue" | Business Property Trust | Succession planning |
| "Property to be managed" | Property Trust | Professional management |

#### Wish Detection Logic

```php
// In WillAnalysisService
public function detectTrustTriggeringWishes(Will $will): array
{
    $triggers = [];
    $wishPatterns = [
        'education_trust' => [
            'patterns' => ['education', 'school fees', 'university', 'college'],
            'trust_type' => 'bare_trust',
            'description' => 'Education Trust for Children',
            'iht_treatment' => 'Bare trust = PET, not CLT',
        ],
        'income_family' => [
            'patterns' => ['income for family', 'income for spouse', 'living expenses'],
            'trust_type' => 'interest_in_possession',
            'description' => 'Interest in Possession Trust',
            'iht_treatment' => 'Pre-2006 IIP = not relevant property',
        ],
        'income_children' => [
            'patterns' => ['income for child', 'income for kids', 'maintenance'],
            'trust_type' => 'discretionary',
            'description' => 'Discretionary Trust for Minors',
            'iht_treatment' => 'Relevant property - 10-year charges apply',
        ],
        'age_restriction' => [
            'patterns' => ['at age 25', 'when they reach', 'upon turning'],
            'trust_type' => 'age_18_to_25',
            'description' => 'Age 18-25 Trust',
            'iht_treatment' => 'Special treatment - reduced exit charges',
        ],
        'asset_protection' => [
            'patterns' => ['protect from divorce', 'creditor protection', 'bankruptcy'],
            'trust_type' => 'discretionary',
            'description' => 'Asset Protection Trust',
            'iht_treatment' => 'Relevant property - full charges apply',
        ],
        'special_needs' => [
            'patterns' => ['special needs', 'disability', 'disabled', 'vulnerable'],
            'trust_type' => 'disabled_person',
            'description' => 'Disabled Person\'s Trust',
            'iht_treatment' => 'Exempt from periodic/exit charges',
        ],
    ];

    // Scan bequest notes and executor instructions
    $textToScan = collect($will->bequests)->pluck('notes')->join(' ')
        . ' ' . ($will->executor_notes ?? '');

    foreach ($wishPatterns as $key => $config) {
        foreach ($config['patterns'] as $pattern) {
            if (stripos($textToScan, $pattern) !== false) {
                $triggers[] = [
                    'wish_type' => $key,
                    'matched_pattern' => $pattern,
                    'trust_type' => $config['trust_type'],
                    'description' => $config['description'],
                    'iht_treatment' => $config['iht_treatment'],
                    'recommendation' => "Consider creating a {$config['description']} to fulfil this wish",
                ];
                break; // One match per category is enough
            }
        }
    }

    return $triggers;
}
```

#### Integration with Estate Planning

When trust-triggering wishes are detected:

1. **Alert in Will Summary** - Display detected wishes with trust recommendations
2. **Link to Trust Strategy** - Connect with `PersonalizedTrustStrategyService` for detailed recommendations
3. **IHT Impact Analysis** - Show how the suggested trust would affect IHT:
   - Entry charge (if CLT)
   - Periodic charges (if relevant property)
   - Potential IHT savings

```php
// In EstateAgent.php - integrate wish detection
public function analyze($userId): array
{
    // ... existing analysis ...

    // Check will for trust-triggering wishes
    $will = Will::where('user_id', $userId)->with('bequests')->first();
    if ($will) {
        $wishTriggers = $this->willAnalysisService->detectTrustTriggeringWishes($will);

        if (!empty($wishTriggers)) {
            $analysisData['trust_wish_triggers'] = $wishTriggers;
            $analysisData['recommendations'][] = [
                'type' => 'will_trust_setup',
                'priority' => 'medium',
                'title' => 'Will Wishes Require Trust Structures',
                'description' => count($wishTriggers) . ' wishes in your will may require trust arrangements',
                'details' => $wishTriggers,
            ];
        }
    }

    return $analysisData;
}
```

### 9.6 Integration Points

- Will status affects estate health score
- Executor information in probate readiness
- Bequests inform asset distribution scenarios
- Asset beneficiary designations can override will
- Charitable bequests auto-adjust IHT rate calculation
- Wish patterns trigger trust recommendations

---

## 10. User Type Handling

### 10.1 Single Users

```php
// Standard calculation
$nrbAvailable = 325000;        // Single NRB
$rnrbAvailable = 175000;       // If main residence owned
$yearsUntilDeath = $this->calculateLifeExpectancy($user);
```

### 10.2 Married/Civil Partnership

```php
// Combined calculation at second death
$isMarried = in_array($user->marital_status, ['married']) && $spouse !== null;

if ($isMarried && $dataSharingEnabled) {
    // Combine assets
    $totalGrossAssets = $userGrossAssets + $spouseGrossAssets;
    $totalLiabilities = $userLiabilities + $spouseLiabilities;

    // Combined allowances
    $nrbAvailable = 650000;    // £325k × 2
    $rnrbAvailable = 350000;   // £175k × 2 (if main residence)

    // Second death projection
    $yearsUntilDeath = max(
        $this->calculateLifeExpectancy($user),
        $this->calculateLifeExpectancy($spouse)
    );
}
```

**Key Features:**
- Spouse exemption: Transfers between spouses are IHT-free
- Transferable NRB: Unused spouse NRB transfers to survivor
- Transferable RNRB: Unused RNRB also transfers
- Second death scenario: Projects to longer-lived spouse

### 10.3 Widowed Users

```php
// Check for transferred allowances
$transferredNRB = $ihtProfile->nrb_transferred_from_spouse ?? 0;

// Maximum: £650,000 (own £325k + transferred £325k)
$nrbAvailable = 325000 + min(325000, $transferredNRB);

// RNRB can also be enhanced
// Up to £500k if both main residence benefits available
```

### 10.4 Divorced Users

- Standard single logic applies
- No transferable allowances
- Can remarry for new spouse benefits

---

## 11. Linked Accounts (Spouse Integration)

### 11.1 Account Linking

```php
// User model relationships
$user->spouse_id;           // Links to spouse User record
$user->spouse;              // BelongsTo relationship
$user->hasAcceptedSpousePermission(); // Data sharing consent
```

### 11.2 Data Sharing Permission

```php
// Required for viewing spouse financial data
$dataSharingEnabled = $spouse && $user->hasAcceptedSpousePermission();

if ($dataSharingEnabled) {
    // Fetch spouse assets
    $spouseAssets = $this->assetAggregator->gatherUserAssets($spouse);
    // Calculate combined IHT
    // Show dual balance sheets
}
```

### 11.3 Joint Asset Pattern

```php
// Single record, ownership split by percentage
Property::create([
    'user_id' => $user->id,
    'joint_owner_id' => $spouse->id,
    'ownership_type' => 'tenants_in_common',
    'ownership_percentage' => 70,  // User gets 70%, spouse gets 30%
    'current_value' => 320000,
]);

// Query for either owner
$properties = Property::where('user_id', $user->id)
    ->orWhere('joint_owner_id', $user->id)
    ->get();

// Calculate share
$userShare = $property->user_id === $user->id
    ? $property->current_value * ($property->ownership_percentage / 100)
    : $property->current_value * ((100 - $property->ownership_percentage) / 100);
```

### 11.4 Dual Balance Sheets

**File:** `ComprehensiveEstatePlanService.php` lines 575-852

```php
// When data sharing enabled
[
    'user' => [
        'name' => 'John Smith',
        'total_assets' => 500000,
        'total_liabilities' => 100000,
        'net_worth' => 400000,
    ],
    'spouse' => [
        'name' => 'Jane Smith',
        'total_assets' => 300000,
        'total_liabilities' => 50000,
        'net_worth' => 250000,
    ],
    'combined' => [
        'total_assets' => 800000,
        'total_liabilities' => 150000,
        'net_worth' => 650000,
    ],
]
```

---

## 12. Frontend Architecture

### 12.1 Vuex Store

**File:** `resources/js/store/modules/estate.js`

#### State

```javascript
const state = {
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
    loading: false,
    error: null,
};
```

#### Key Getters

| Getter | Description |
|--------|-------------|
| `allAssets` | Combines manual + investment accounts |
| `totalAssets` | Sum of all asset values |
| `totalLiabilities` | Sum of all liabilities |
| `netWorthValue` | Assets - Liabilities |
| `ihtLiability` | Current IHT liability |
| `taxableEstate` | Estate after allowances |
| `grossEstate` | Estate before allowances |
| `giftsWithin7Years` | PETs in 7-year window |
| `futureDeathAge` | Projected age at death |
| `futureTaxableEstate` | Projected taxable estate |
| `futureIHTLiability` | Projected IHT liability |

#### Key Actions

| Action | Description |
|--------|-------------|
| `fetchEstateData` | Load all estate data |
| `calculateIHT` | Trigger IHT calculation |
| `calculateSecondDeathIHTPlanning` | Married couple planning |
| `fetchNetWorth` | Net worth analysis |
| `fetchCashFlow` | Cash flow projections |
| `createAsset` / `updateAsset` / `deleteAsset` | Asset CRUD |
| `createLiability` / `updateLiability` / `deleteLiability` | Liability CRUD |
| `createGift` / `updateGift` / `deleteGift` | Gift CRUD |

### 12.2 Vue Components

**Location:** `resources/js/components/Estate/`

#### Planning Components

| Component | Purpose |
|-----------|---------|
| `IHTPlanning.vue` | IHT analysis and calculations |
| `IHTMitigationStrategies.vue` | Strategy recommendations |
| `TrustPlanning.vue` | Trust planning interface |
| `WillPlanning.vue` | Will creation/management |
| `GiftingStrategy.vue` | Gifting strategy UI |
| `LifePolicyStrategy.vue` | Life insurance recommendations |

#### Data Management

| Component | Purpose |
|-----------|---------|
| `AssetForm.vue` | Add/edit assets |
| `AssetsLiabilities.vue` | Asset/liability list view |
| `LiabilityForm.vue` | Add/edit liabilities |
| `GiftForm.vue` | Add/edit gifts |
| `TrustForm.vue` | Add/edit trusts |

#### Visualisation

| Component | Purpose |
|-----------|---------|
| `IHTCalculationTable.vue` | IHT breakdown table |
| `IHTAssetBreakdown.vue` | Asset category breakdown |
| `IHTLiabilityGauge.vue` | Visual gauge for IHT |
| `EstateProjectionComparison.vue` | Current vs projected |
| `NRBRNRBTracker.vue` | NRB/RNRB utilization |
| `DualGiftingTimeline.vue` | Gifting schedule |
| `NetWorthWaterfallChart.vue` | Waterfall visualization |

---

## 13. API Endpoints

**File:** `app/Http/Controllers/Api/EstateController.php`

### Core Endpoints

| Method | Endpoint | Handler | Purpose |
|--------|----------|---------|---------|
| GET | `/api/estate` | `index()` | Fetch all estate data |
| GET | `/api/estate/comprehensive-plan` | `getComprehensiveEstatePlan()` | Full estate plan |
| GET | `/api/estate/net-worth` | `getNetWorth()` | Net worth analysis |
| GET | `/api/estate/cash-flow` | `getCashFlow()` | Cash flow projections |

### Asset Endpoints

| Method | Endpoint | Handler |
|--------|----------|---------|
| POST | `/api/estate/assets` | `storeAsset()` |
| PATCH | `/api/estate/assets/{id}` | `updateAsset()` |
| DELETE | `/api/estate/assets/{id}` | `destroyAsset()` |

### Liability Endpoints

| Method | Endpoint | Handler |
|--------|----------|---------|
| POST | `/api/estate/liabilities` | `storeLiability()` |
| PATCH | `/api/estate/liabilities/{id}` | `updateLiability()` |
| DELETE | `/api/estate/liabilities/{id}` | `destroyLiability()` |

### Gift Endpoints

| Method | Endpoint | Handler |
|--------|----------|---------|
| POST | `/api/estate/gifts` | `storeGift()` |
| PATCH | `/api/estate/gifts/{id}` | `updateGift()` |
| DELETE | `/api/estate/gifts/{id}` | `destroyGift()` |

### Cache Invalidation

All write operations invalidate cache:
```php
Cache::forget("estate_analysis_{$user->id}");
```

---

## 14. Complete Data Flow

### Example: User Creates Asset → IHT Recalculation → Display

```
1. FRONTEND INPUT
   ┌─────────────────────────────────────────────────────────┐
   │ Vue Component (AssetForm.vue)                          │
   │ - Asset name, current_value, asset_type                │
   │ - Ownership type (individual/joint)                     │
   │ - Is main residence? IHT exempt?                        │
   └─────────────────────────────────────────────────────────┘
                              │
                              ▼
2. API SUBMISSION
   ┌─────────────────────────────────────────────────────────┐
   │ POST /api/estate/assets → EstateController.storeAsset() │
   │ - Validates request                                     │
   │ - Creates Asset model with user_id                      │
   │ - Invalidates cache                                     │
   │ - Returns created asset                                 │
   └─────────────────────────────────────────────────────────┘
                              │
                              ▼
3. STATE UPDATE
   ┌─────────────────────────────────────────────────────────┐
   │ Vuex estate module                                      │
   │ - Commit 'addAsset' mutation                            │
   │ - UI automatically updates via getters                  │
   │ - totalAssets getter recalculates                       │
   └─────────────────────────────────────────────────────────┘
                              │
                              ▼
4. IHT CALCULATION
   ┌─────────────────────────────────────────────────────────┐
   │ User clicks "Calculate IHT"                             │
   │ → $store.dispatch('estate/calculateIHT')                │
   │ → estateService.calculateIHT()                          │
   │ → POST /api/estate/calculate-iht                        │
   └─────────────────────────────────────────────────────────┘
                              │
                              ▼
5. BACKEND CALCULATION
   ┌─────────────────────────────────────────────────────────┐
   │ IHTCalculationService.calculate($user, $spouse, $flag)  │
   │                                                         │
   │ ┌─ EstateAssetAggregatorService.gatherUserAssets() ───┐ │
   │ │ - Query Asset.where('user_id', $id)                 │ │
   │ │ - Includes new asset                                │ │
   │ │ - Aggregates all module assets                      │ │
   │ └─────────────────────────────────────────────────────┘ │
   │                                                         │
   │ ┌─ Calculate IHT ────────────────────────────────────┐  │
   │ │ - NRB: £325k (or £650k married)                    │  │
   │ │ - RNRB: Up to £175k (or £350k) if main residence   │  │
   │ │ - Taxable = Net Estate - Allowances                │  │
   │ │ - IHT = Taxable × 40%                              │  │
   │ └─────────────────────────────────────────────────────┘ │
   │                                                         │
   │ ┌─ Project to Death ─────────────────────────────────┐  │
   │ │ - Life expectancy from actuarial tables            │  │
   │ │ - Growth: (1 + 4.7%)^years                         │  │
   │ │ - Projected IHT at death                           │  │
   │ └─────────────────────────────────────────────────────┘ │
   │                                                         │
   │ - Save to IHTCalculation table                          │
   │ - Return full calculation                               │
   └─────────────────────────────────────────────────────────┘
                              │
                              ▼
6. RECOMMENDATIONS (Optional)
   ┌─────────────────────────────────────────────────────────┐
   │ EstateAgent.generateRecommendations()                   │
   │ - If > £100k: Trust + gifting recommendations           │
   │ - PersonalizedTrustStrategyService: 5 trust strategies  │
   │ - GiftingStrategyOptimizer: 4 gifting strategies        │
   │ - Return prioritized recommendations                    │
   └─────────────────────────────────────────────────────────┘
                              │
                              ▼
7. DISPLAY
   ┌─────────────────────────────────────────────────────────┐
   │ Vue Components render updated data:                     │
   │ - IHTCalculationTable: Breakdown                        │
   │ - EstateProjectionComparison: Current vs projected      │
   │ - IHTMitigationStrategies: Recommended actions          │
   │ - TrustPlanning: Trust strategies with cost/benefit     │
   │ - GiftingStrategy: Gifting schedule                     │
   └─────────────────────────────────────────────────────────┘
```

---

## Appendix: File Reference

### Backend Files

| File | Purpose |
|------|---------|
| `app/Agents/EstateAgent.php` | Module orchestrator |
| `app/Services/Estate/IHTCalculationService.php` | IHT calculations |
| `app/Services/Estate/EstateAssetAggregatorService.php` | Asset aggregation |
| `app/Services/Estate/GiftingStrategyOptimizer.php` | Gifting strategies |
| `app/Services/Estate/PersonalizedTrustStrategyService.php` | Trust strategies |
| `app/Services/Estate/ComprehensiveEstatePlanService.php` | Full plan generation |
| `app/Services/Estate/AssetLiquidityAnalyzer.php` | Liquidity analysis |
| `app/Services/Estate/NetWorthAnalyzer.php` | Net worth calculations |
| `app/Services/TaxConfigService.php` | Tax configuration |
| `app/Http/Controllers/Api/EstateController.php` | API controller |
| `app/Models/Estate/*.php` | All estate models |

### Frontend Files

| File | Purpose |
|------|---------|
| `resources/js/store/modules/estate.js` | Vuex store |
| `resources/js/services/estateService.js` | API service |
| `resources/js/views/Estate/EstateDashboard.vue` | Dashboard view |
| `resources/js/views/Estate/ComprehensiveEstatePlan.vue` | Full plan view |
| `resources/js/components/Estate/*.vue` | 30+ components |

---

*Document generated: 3 February 2026*
