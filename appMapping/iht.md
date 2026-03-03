# Complete IHT (Inheritance Tax) Calculation & Strategies Flow

## Overview

This document provides comprehensive documentation of the IHT calculation system in the FPS (Fynla) application, covering data sources, calculations, configurations, API endpoints, services, and frontend display.

---

## 1. DATABASE SCHEMA & MODELS

### Primary Models

#### IHTProfile Model
- **File**: `app/Models/Estate/IHTProfile.php`
- **Table**: `iht_profiles`
- **Purpose**: Stores user-specific IHT eligibility flags

| Field | Type | Description |
|-------|------|-------------|
| `user_id` | FK | Foreign key to users |
| `marital_status` | enum | single, married, widowed, divorced |
| `has_spouse` | boolean | Whether user has a spouse |
| `own_home` | boolean | For RNRB eligibility |
| `home_value` | float | Main residence value for RNRB calculation |
| `nrb_transferred_from_spouse` | float | Spousal NRB allowance (deceased spouse) |
| `charitable_giving_percent` | float | % of estate to charity (for 36% rate) |

#### IHTCalculation Model
- **File**: `app/Models/Estate/IHTCalculation.php`
- **Table**: `iht_calculations`
- **Purpose**: Caches computed IHT calculations to avoid recalculation

| Field Group | Fields |
|-------------|--------|
| **User/Spouse Gross Assets** | `user_gross_assets`, `spouse_gross_assets`, `total_gross_assets` |
| **Liabilities** | `user_total_liabilities`, `spouse_total_liabilities`, `total_liabilities` |
| **Net Estate** | `user_net_estate`, `spouse_net_estate`, `total_net_estate` |
| **Allowances** | `nrb_available`, `nrb_message`, `rnrb_available`, `rnrb_status`, `rnrb_message`, `total_allowances` |
| **Tax Calculation** | `taxable_estate`, `iht_liability`, `effective_rate` |
| **Projected Values** | `projected_gross_assets`, `projected_liabilities`, `projected_net_estate`, `projected_taxable_estate`, `projected_iht_liability`, `years_to_death`, `estimated_age_at_death` |
| **Status** | `is_married`, `data_sharing_enabled`, `assets_hash`, `liabilities_hash`, `calculation_date` |

### Asset Source Models

Assets are aggregated from multiple modules:

| Model | Table | IHT Relevance |
|-------|-------|---------------|
| `Property` | `properties` | Includes `property_type` (main_residence for RNRB) |
| `InvestmentAccount` | `investment_accounts` | All types (ISA, SIPP, general) |
| `SavingsAccount` | `savings_accounts` | Cash savings |
| `BusinessInterest` | `business_interests` | `bpr_eligible` flag, `trading_status` |
| `Chattel` | `chattels` | Personal property (art, jewelry) |
| `DCPension` | `dc_pensions` | IHT-exempt with nominated beneficiaries |
| `DBPension` | `db_pensions` | Zero IHT value (pensions die with member) |

### Liability Source Models

| Model | Table | Description |
|-------|-------|-------------|
| `Mortgage` | `mortgages` | `ownership_type` tracks joint/individual |
| `Liability` | `liabilities` | Personal loans, credit cards, overdrafts |

### Actuarial Data

- **Table**: `actuarial_life_tables`
- **Fields**: `age`, `gender` (M/F), `table_year` (2020-2022), `life_expectancy_years`
- **Purpose**: Calculate years to death for estate projections
- **Seeder**: `ActuarialLifeTablesSeeder`

---

## 2. TAX CONFIGURATION

### TaxConfigService
- **File**: `app/Services/TaxConfigService.php`
- **Pattern**: Request-scoped singleton, loads active config once per request
- **Key Method**: `getInheritanceTax()` returns IHT config from database

### IHT Configuration Values (2025/26)

```php
'inheritance_tax' => [
    'nil_rate_band' => 325000,              // £325,000 per person
    'residence_nil_rate_band' => 175000,    // £175,000 RNRB per person
    'rnrb_taper_threshold' => 2000000,      // Taper starts at £2m
    'rnrb_taper_rate' => 0.5,               // £1 lost per £2 over threshold
    'standard_rate' => 0.40,                // 40% IHT rate
    'reduced_rate_charity' => 0.36,         // 36% if 10%+ to charity
    'potentially_exempt_transfers' => [
        'taper_relief' => [
            ['years' => 3, 'rate' => 0.80],
            ['years' => 4, 'rate' => 0.60],
            ['years' => 5, 'rate' => 0.40],
            ['years' => 6, 'rate' => 0.20]
        ]
    ]
],
'gifting_exemptions' => [
    'annual_exemption' => 3000,             // £3,000/year
    'small_gift_exemption' => 250           // £250 per recipient
]
```

---

## 3. API ENDPOINTS

### Main IHT Endpoints

| Method | Endpoint | Controller Method | Purpose |
|--------|----------|-------------------|---------|
| POST | `/api/estate/calculate-iht` | `IHTController::calculateIHT()` | Unified IHT calculation |
| POST | `/api/estate/profile` | `IHTController::storeOrUpdateIHTProfile()` | Create/update IHT profile |
| GET | `/api/estate/profile` | `IHTController::getIHTProfile()` | Get IHT profile |

### Estate CRUD Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/estate` | List all estate data |
| POST | `/api/estate/assets` | Create asset |
| PUT | `/api/estate/assets/{id}` | Update asset |
| DELETE | `/api/estate/assets/{id}` | Delete asset |
| POST | `/api/estate/liabilities` | Create liability |
| PUT | `/api/estate/liabilities/{id}` | Update liability |
| DELETE | `/api/estate/liabilities/{id}` | Delete liability |
| POST | `/api/estate/gifts` | Create gift |
| PUT | `/api/estate/gifts/{id}` | Update gift |
| DELETE | `/api/estate/gifts/{id}` | Delete gift |

### Strategy Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/estate/gifts/personalized-strategy` | Asset-based gifting strategy |
| GET | `/api/estate/gifts/trust-strategy` | Trust planning recommendations |
| GET | `/api/estate/life-policy-strategy` | Life insurance analysis |
| GET | `/api/estate/comprehensive-plan` | Full estate plan |

### API Response Structure

```json
{
  "success": true,
  "calculation": {
    "total_gross_assets": 2500000,
    "total_liabilities": 350000,
    "total_net_estate": 2150000,
    "nrb_available": 650000,
    "rnrb_available": 350000,
    "taxable_estate": 1150000,
    "iht_liability": 460000,
    "effective_rate": 21.4,
    "projected_iht_liability": 2860000,
    "years_to_death": 31,
    "estimated_age_at_death": 89
  },
  "assets_breakdown": {
    "user": { "assets": {...}, "total": 1500000 },
    "spouse": { "assets": {...}, "total": 1000000 }
  },
  "liabilities_breakdown": {
    "user": { "mortgages": [...], "other_liabilities": [...], "total": 200000 },
    "spouse": { "mortgages": [...], "total": 150000 }
  },
  "iht_summary": {
    "current": { /* current IHT data */ },
    "projected": { /* projected IHT at death */ }
  }
}
```

---

## 4. CONTROLLERS

### IHTController
- **File**: `app/Http/Controllers/Api/Estate/IHTController.php`

#### Key Methods

| Method | Line | Purpose |
|--------|------|---------|
| `calculateIHT()` | 35-145 | Main unified calculation endpoint |
| `storeOrUpdateIHTProfile()` | 463-489 | Save IHT profile settings |
| `invalidateCache()` | 494-504 | Clear cached calculations |
| `formatAssetsBreakdown()` | 153-268 | Format assets by type for response |
| `formatLiabilitiesBreakdown()` | 275-435 | Format mortgages/liabilities for response |
| `calculateLifeExpectancyForProjection()` | 510-532 | Use actuarial tables |

### EstateController
- **File**: `app/Http/Controllers/Api/EstateController.php`

| Method | Line | Purpose |
|--------|------|---------|
| `index()` | 33-84 | Get all estate data |
| `getComprehensiveEstatePlan()` | 89-113 | Delegate to ComprehensiveEstatePlanService |
| `getNetWorth()` | 118-135 | Delegate to NetWorthAnalyzer |

---

## 5. SERVICES & CALCULATION LOGIC

### IHTCalculationService
- **File**: `app/Services/Estate/IHTCalculationService.php`
- **Core Method**: `calculate()` (line 32-144)

#### Calculation Flow

```
1. Check Cache (SHA256 hash of assets/liabilities)
       ↓
2. Get Tax Config (NRB, RNRB from TaxConfigService)
       ↓
3. Fetch Assets (EstateAssetAggregatorService::gatherUserAssets)
   - User assets
   - Spouse assets (if married + data sharing)
   - Filter out IHT-exempt assets
       ↓
4. Sum Assets
   - user_gross_assets = sum(user taxable assets)
   - spouse_gross_assets = sum(spouse taxable assets)
   - total_gross_assets = user + spouse
       ↓
5. Fetch & Sum Liabilities
   - Mortgages + other liabilities
       ↓
6. Calculate Net Estate
   - net_estate = gross_assets - total_liabilities
       ↓
7. Calculate NRB (Nil Rate Band)
   - Single: £325,000
   - Married: £650,000 (£325,000 × 2)
       ↓
8. Calculate RNRB (Residence Nil Rate Band)
   - Check eligibility (main residence + direct descendants)
   - Full: £175,000 single / £350,000 married
   - Apply taper if estate > £2m
       ↓
9. Calculate Taxable Estate & IHT
   - total_allowances = nrb + rnrb
   - taxable_estate = max(0, net_estate - allowances)
   - iht_liability = taxable_estate × 0.40
       ↓
10. Calculate Projected Values at Death
    - Use actuarial tables for life expectancy
    - Apply 4.7% annual growth
    - For married: use SECOND DEATH (longer life expectancy)
       ↓
11. Save to Cache
    - Store calculation with asset/liability hashes
```

#### Key Private Methods

| Method | Line | Purpose |
|--------|------|---------|
| `calculateProjectedValues()` | 152-215 | Project estate to death using 4.7% growth |
| `calculateRNRB()` | 222-275 | RNRB eligibility and taper calculation |
| `calculateLifeExpectancy()` | 280-302 | Query actuarial tables |
| `hasMainResidence()` | 307-324 | Check for main_residence property |
| `getCachedCalculation()` | 329-353 | Retrieve cached calculation if valid |

### EstateAssetAggregatorService
- **File**: `app/Services/Estate/EstateAssetAggregatorService.php`

#### `gatherUserAssets()` (line 44-198)

Collects assets from all modules using single-record ownership pattern:

```php
// Query pattern for joint ownership
->where('user_id', $id)
->orWhere('joint_owner_id', $id)

// User's share calculation
$userShare = $currentValue * ($ownershipPercentage / 100);
```

**Asset Types Collected:**

| Type | Source | IHT Treatment |
|------|--------|---------------|
| Estate Assets | `Asset` model | Taxable |
| Investment Accounts | `InvestmentAccount` | Taxable (including ISAs) |
| Properties | `Property` | Taxable; main_residence for RNRB |
| Savings Accounts | `SavingsAccount` | Taxable |
| Business Interests | `BusinessInterest` | 100% BPR relief if eligible |
| Chattels | `Chattel` | Taxable |
| DC Pensions | `DCPension` | IHT-exempt (nominated beneficiaries) |
| DB Pensions | `DBPension` | Zero value (dies with member) |

#### `calculateUserLiabilities()` (line 206-227)

Returns total liabilities using ownership share calculation.

### IHTStrategyGeneratorService
- **File**: `app/Services/Estate/IHTStrategyGeneratorService.php`

#### Strategy Types Generated

| Priority | Strategy | Trigger |
|----------|----------|---------|
| 1 | Gifting Strategy | Always (lifetime gifting) |
| 2 | Life Insurance in Trust | After gifting quantified |
| 3 | Claim RNRB | Not claimed and estate < £2m |
| 4 | Charitable Giving | If 10%+ saves 4% rate |
| 5 | Discretionary Trust Planning | Estate > £100k IHT |
| 6 | Pension Planning | Maximize annual allowance |
| 7 | Spend & Enjoy | Reduce estate naturally |
| 8 | Business Relief Investments | AIM shares, BR funds |

---

## 6. CALCULATION FORMULAS

### Inheritance Tax Formula

```
IHT = (Net Estate - Total Allowances) × 40%

Where:
  Net Estate = Gross Assets - Liabilities
  Total Allowances = NRB + RNRB
```

### NRB (Nil Rate Band)

```
Single:  £325,000
Married: £650,000 (£325,000 × 2 on second death)
```

### RNRB (Residence Nil Rate Band)

```
Eligibility:
  - Must own main residence (property_type = 'main_residence')
  - Must pass to direct descendants

Full RNRB:
  Single:  £175,000
  Married: £350,000

Taper (if estate > £2,000,000):
  excess = estate - £2,000,000
  reduction = excess × 0.5
  rnrb_available = max(0, full_rnrb - reduction)

Example:
  Estate = £2.5m
  Excess = £500,000
  Reduction = £250,000
  RNRB = max(0, £175,000 - £250,000) = £0
```

### Charitable Reduction

```
If 10%+ of estate to charity:
  IHT Rate = 36% (instead of 40%)
```

### Projected Estate (at Death)

```
Growth Rate = 4.7% annual compound

For Married Couples:
  User Years = actuarial_table[user_age, user_gender]
  Spouse Years = actuarial_table[spouse_age, spouse_gender]
  Years to Project = max(User Years, Spouse Years)  // SECOND DEATH

Projection:
  growth_multiplier = (1 + 0.047)^years_to_death
  projected_gross = current_gross × growth_multiplier
  projected_liabilities = current (mortgages paid off by age 70)
  projected_net = projected_gross - projected_liabilities
  projected_taxable = max(0, projected_net - allowances)
  projected_iht = projected_taxable × 0.40
```

### Life Expectancy Lookup

```sql
SELECT life_expectancy_years
FROM actuarial_life_tables
WHERE age <= :current_age
  AND gender = :gender
  AND table_year = '2020-2022'
ORDER BY age DESC
LIMIT 1
```

Fallback: `85 - current_age`

---

## 7. FRONTEND COMPONENTS

### Vue Components

| Component | File | Purpose |
|-----------|------|---------|
| `EstateDashboard` | `resources/js/views/Estate/EstateDashboard.vue` | Main estate view with tabs |
| `IHTPlanning` | `resources/js/components/Estate/IHTPlanning.vue` | IHT calculations display |
| `IHTLiabilityGauge` | `resources/js/components/Estate/IHTLiabilityGauge.vue` | Visual gauge |
| `IHTMitigationStrategies` | `resources/js/components/Estate/IHTMitigationStrategies.vue` | Strategy cards |
| `GiftingStrategy` | `resources/js/components/Estate/GiftingStrategy.vue` | Lifetime gifting |
| `LifePolicyStrategy` | `resources/js/components/Estate/LifePolicyStrategy.vue` | Life insurance |
| `TrustPlanning` | `resources/js/components/Estate/TrustPlanning.vue` | Trust recommendations |

### IHTPlanning Component Features

- Current vs. projected IHT side-by-side
- Married: Second Death analysis
- Single: Age X projection
- Assets/liabilities breakdown table
- Collapsible -5/+5 year projection columns
- Mitigation strategy cards

### Key Computed Properties

```javascript
// In IHTPlanning.vue
computed: {
  ihtData() { /* Current IHT calculation */ },
  projection() { /* Projected values at death */ },
  projectionMinus5() { /* -5 years from life expectancy */ },
  projectionPlus5() { /* +5 years from life expectancy */ },
  isMarried() { /* User marital status */ },
  secondDeathData() { /* Married couple data */ },
  hasSpouse() { /* Linked spouse exists */ }
}
```

---

## 8. VUEX STORE

### Estate Module
- **File**: `resources/js/store/modules/estate.js`

#### State

```javascript
{
  assets: [],                    // Manual estate assets
  investmentAccounts: [],        // From Investment module
  liabilities: [],               // Mortgages, loans
  gifts: [],                     // For PET tracking
  trusts: [],                    // Trust records
  ihtProfile: null,              // IHT profile data
  netWorth: null,                // Net worth summary
  analysis: null,                // Estate analysis
  recommendations: [],           // Prioritized recommendations
  secondDeathPlanning: null,     // Married couple IHT data
  loading: false,
  error: null
}
```

#### Key Getters

| Getter | Purpose |
|--------|---------|
| `totalAssets` | Sum of all assets including investments |
| `totalLiabilities` | Sum of all liabilities |
| `netWorthValue` | Total assets - total liabilities |
| `ihtLiability` | Current IHT liability (multiple fallbacks) |
| `taxableEstate` | Estate after allowances |
| `futureIHTLiability` | Projected IHT at death |
| `ihtExemptAssets` | Filter of exempt assets |

#### Key Actions

| Action | API Call | Purpose |
|--------|----------|---------|
| `fetchEstateData()` | GET `/estate` | Get all estate records |
| `calculateIHT()` | POST `/estate/calculate-iht` | Calculate IHT |
| `createAsset()` | POST `/estate/assets` | Create asset |
| `updateAsset()` | PUT `/estate/assets/{id}` | Update asset |
| `deleteAsset()` | DELETE `/estate/assets/{id}` | Delete asset |

---

## 9. API SERVICE LAYER

### EstateService
- **File**: `resources/js/services/estateService.js`

```javascript
// Key methods
getEstateData()                    // GET /estate
calculateIHT(data)                 // POST /estate/calculate-iht
storeOrUpdateProfile(profileData)  // POST /estate/profile
getPersonalizedGiftingStrategy()   // GET /estate/gifts/personalized-strategy
getPersonalizedTrustStrategy()     // GET /estate/gifts/trust-strategy
getComprehensiveEstatePlan()       // GET /estate/comprehensive-plan
```

---

## 10. COMPLETE REQUEST FLOW

### Example: Married User Calculates Second Death IHT

```
1. FRONTEND (IHTPlanning.vue)
   mounted() → this.$store.dispatch('estate/calculateIHT')
                    ↓
2. VUEX ACTION (estate.js)
   const response = await estateService.calculateIHT()
   commit('setSecondDeathPlanning', response)
                    ↓
3. API SERVICE (estateService.js)
   api.post('/estate/calculate-iht', {})
                    ↓
4. ROUTER (routes/api.php)
   POST /api/estate/calculate-iht → IHTController::calculateIHT()
                    ↓
5. CONTROLLER (IHTController.php)
   - Get authenticated user and spouse
   - Check data sharing enabled
   - Call IHTCalculationService::calculate()
   - Format assets/liabilities breakdowns
   - Return JSON response
                    ↓
6. SERVICE (IHTCalculationService.php)
   - Check cache (SHA256 hash)
   - Get tax config from TaxConfigService
   - Gather user assets (EstateAssetAggregatorService)
   - Gather spouse assets (if data sharing)
   - Calculate net estate
   - Calculate NRB (£650k married)
   - Calculate RNRB (with taper if > £2m)
   - Calculate taxable estate and IHT
   - Project to SECOND DEATH using actuarial tables
   - Save to cache
                    ↓
7. RESPONSE → VUEX MUTATION → VUE COMPONENT RENDER
```

---

## 11. OWNERSHIP DATA MODEL

### Single-Record Pattern

Each asset has ONE database record storing the FULL value:

```
Database Record:
  user_id = A (primary owner)
  joint_owner_id = B (secondary owner)
  current_value = 200,000 (FULL value)
  ownership_percentage = 50
  ownership_type = 'joint'

Query Pattern:
  WHERE user_id = :userId OR joint_owner_id = :userId

User Share Calculation:
  share = current_value × (ownership_percentage / 100)
  User A's share = £200,000 × 50% = £100,000
```

**Note**: For joint assets, BOTH users must have separate records for accurate IHT calculation.

---

## 12. CACHE INVALIDATION

### Hash-Based Caching

```php
// Generate hash of current values
$assetsHash = hash('sha256', json_encode($assetValues));
$liabilitiesHash = hash('sha256', json_encode($liabilityValues));

// Store with calculation
IHTCalculation::create([
    'assets_hash' => $assetsHash,
    'liabilities_hash' => $liabilitiesHash,
    // ... other fields
]);

// On next request, compare hashes
if ($cached->assets_hash === $currentAssetsHash
    && $cached->liabilities_hash === $currentLiabilitiesHash) {
    return $cached; // Use cache
}
// Else recalculate
```

---

## 13. CRITICAL IMPLEMENTATION DETAILS

| Detail | Value/Behavior |
|--------|----------------|
| Actuarial Table Year | `2020-2022` |
| Growth Rate | 4.7% annual compound |
| Second Death | Uses MAX(user, spouse) life expectancy |
| Cache Hash | SHA256 of asset/liability values |
| BPR Relief | 100% if `bpr_eligible` + `trading` + 2+ years |
| Spouse Exemption | Unlimited on first death; NRB doubles on second |
| Charitable Rate | 36% if 10%+ to charity |
| Fallback Age | 85 if no actuarial data |
| Mortgage Assumption | Paid off by age 70 in projections |
| ISA Treatment | Taxable (not IHT-exempt) |
| DC Pensions | IHT-exempt (nominated beneficiaries) |
| DB Pensions | Zero IHT value (dies with member) |

---

## 14. FILE REFERENCE

### Backend

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Api/Estate/IHTController.php` | Main IHT API controller |
| `app/Http/Controllers/Api/EstateController.php` | Estate CRUD controller |
| `app/Services/Estate/IHTCalculationService.php` | Core calculation service |
| `app/Services/Estate/EstateAssetAggregatorService.php` | Asset collection |
| `app/Services/Estate/IHTStrategyGeneratorService.php` | Strategy generation |
| `app/Services/TaxConfigService.php` | Tax configuration access |
| `app/Models/Estate/IHTProfile.php` | IHT profile model |
| `app/Models/Estate/IHTCalculation.php` | Cached calculation model |
| `app/Agents/EstateAgent.php` | Estate analysis orchestrator |

### Frontend

| File | Purpose |
|------|---------|
| `resources/js/views/Estate/EstateDashboard.vue` | Main estate view |
| `resources/js/components/Estate/IHTPlanning.vue` | IHT display component |
| `resources/js/components/Estate/IHTMitigationStrategies.vue` | Strategy cards |
| `resources/js/store/modules/estate.js` | Vuex store module |
| `resources/js/services/estateService.js` | API service layer |

### Database

| Table | Purpose |
|-------|---------|
| `iht_profiles` | User IHT settings |
| `iht_calculations` | Cached calculations |
| `actuarial_life_tables` | Life expectancy data |
| `tax_configurations` | Tax rates and thresholds |
| `properties` | Property assets |
| `investment_accounts` | Investment assets |
| `savings_accounts` | Savings assets |
| `business_interests` | Business assets |
| `chattels` | Personal property |
| `mortgages` | Mortgage liabilities |
| `liabilities` | Other liabilities |
