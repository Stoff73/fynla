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

### 3.1 Source

All IHT values come from the database via `TaxConfigService` - **never hardcoded**.

```php
// app/Services/TaxConfigService.php

// Get IHT configuration
$ihtConfig = $this->taxConfig->getInheritanceTax();
// Returns:
// [
//     'nil_rate_band' => 325000,           // £325,000
//     'residence_nil_rate_band' => 175000, // £175,000
//     'rnrb_taper_threshold' => 2000000,   // £2,000,000
//     'rnrb_taper_rate' => 0.5,            // £1 per £2 over threshold
//     'standard_rate' => 0.40,             // 40%
// ]

// Get gifting exemptions
$giftingConfig = $this->taxConfig->getGiftingExemptions();
// Returns:
// [
//     'annual_exemption' => 3000,          // £3,000/year
//     'small_gifts' => 250,                // £250 per recipient
//     'wedding_gifts' => 2500,             // £2,500 parent, £1,000 other
// ]
```

### 3.2 Current Tax Year Values (2025/26)

| Allowance | Value | Notes |
|-----------|-------|-------|
| Nil Rate Band (NRB) | £325,000 | Per person, transferable to spouse |
| Residence Nil Rate Band (RNRB) | £175,000 | Requires main residence left to direct descendants |
| RNRB Taper Threshold | £2,000,000 | RNRB reduces by £1 for every £2 above |
| IHT Rate | 40% | On taxable estate |
| Reduced Rate | 36% | If 10%+ left to charity |
| Annual Exemption | £3,000 | Immediately exempt |
| Small Gift Exemption | £250 | Per recipient, unlimited recipients |

### 3.3 Constant Rates (Hardcoded)

| Rate | Value | Usage |
|------|-------|-------|
| CLT Lifetime Charge | 20% | On excess over NRB |
| CLT Settlor Pays Rate | 25% | If settlor pays the charge |
| CLT Death Charge | 40% | If death within 7 years (less 20% already paid) |
| 10-Year Anniversary | 6% | On trust value above NRB |
| Asset Growth Rate | 4.7% | For projections |
| Income Gifting Rate | 50% | Conservative rate for surplus income |

### 3.4 Taper Relief Rates

| Years Since Gift | Tax Rate (of 40%) |
|------------------|-------------------|
| 0-3 years | 100% (full 40%) |
| 3-4 years | 80% (32%) |
| 4-5 years | 60% (24%) |
| 5-6 years | 40% (16%) |
| 6-7 years | 20% (8%) |
| 7+ years | 0% (fully exempt) |

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
Decision Tree:
├─ IF IHT Liability > £100,000
│  └─ PRIORITY 1: Trust structures + lifetime gifting
├─ IF Trust recommendations available
│  └─ PRIORITY 2: Trust Planning Opportunities
├─ IF Gifting opportunities exist
│  └─ PRIORITY 3: Execute gifting strategy
└─ IF Health score < 50
   └─ PRIORITY 4: Complete profile, update will, review beneficiaries
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

**File:** `IHTCalculationService.php` lines 152-215

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

### 7.2 Asset Growth Projection

```php
// Annual growth rate: 4.7%
$growthMultiplier = pow(1 + 0.047, $yearsUntilDeath);

// Future Value = Present Value × (1 + r)^n
$projectedGrossAssets = $currentGrossAssets * $growthMultiplier;

// Liabilities assumed constant (conservative)
$projectedLiabilities = $currentLiabilities;

// Projected net estate
$projectedNetEstate = $projectedGrossAssets - $projectedLiabilities;

// Projected IHT
$projectedTaxableEstate = max(0, $projectedNetEstate - $totalAllowances);
$projectedIHTLiability = $projectedTaxableEstate * 0.40;
```

### 7.3 Assumptions

| Assumption | Value | Rationale |
|------------|-------|-----------|
| Asset Growth | 4.7% | Conservative market return |
| Liability Change | 0% | Conservative (debt paid down) |
| Inflation | Implied | Included in growth rate |
| Mortality | Actuarial tables | English life tables 2020-2022 |

---

## 8. IHT Mitigation Strategies

### 8.1 Decision Tree

**File:** `EstateAgent.php` lines 125-204

```
Recommendation Priority:

1. HIGH IHT LIABILITY (> £100,000)
   ├─ Recommend trust structures
   ├─ Recommend lifetime gifting
   └─ Explore business relief opportunities

2. TRUST PLANNING OPPORTUNITIES
   └─ Review recommendations from PersonalizedTrustStrategyService

3. GIFTING STRATEGY
   ├─ Use £3,000 annual exemption
   ├─ Consider normal expenditure from income
   └─ Review PET (7-year cycle) opportunities

4. LOW HEALTH SCORE (< 50)
   ├─ Complete IHT profile
   ├─ Review beneficiary designations
   └─ Write/update will
```

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

### 9.4 Integration Points

- Will status affects estate health score
- Executor information in probate readiness
- Bequests inform asset distribution scenarios
- Asset beneficiary designations can override will

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
