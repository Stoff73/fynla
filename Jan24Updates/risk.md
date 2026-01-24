# Risk Module - Comprehensive Mapping

## Overview

The Risk module provides a 5-level risk profiling system that determines investment risk appetite through a 7-factor automatic calculation or user self-assessment. It integrates with Investment, Retirement, Goals, and other modules for return assumptions and asset allocation.

---

## 1. API Endpoints

**Route prefix:** `/api/investment/risk`

| Endpoint | Method | Controller | Purpose |
|----------|--------|-----------|---------|
| `/levels` | GET | `RiskPreferenceController::getLevels()` | Get all 5 risk levels with configurations |
| `/profile` | GET | `RiskPreferenceController::getProfile()` | Fetch user's risk profile (auto-calculates if none exists) |
| `/profile` | POST | `RiskPreferenceController::setProfile()` | Set/update main risk level (self-assessed) |
| `/recalculate` | POST | `RiskPreferenceController::recalculate()` | Force recalculation from financial factors |
| `/allowed-levels` | GET | `RiskPreferenceController::getAllowedLevels()` | Get allowed product-level overrides (+/-1 from main) |
| `/validate-product-level` | POST | `RiskPreferenceController::validateProductLevel()` | Validate if product risk level is allowed |
| `/config/{level}` | GET | `RiskPreferenceController::getRiskConfig()` | Get detailed config for specific risk level |

---

## 2. Controllers

| Controller | Path | Lines | Purpose |
|-----------|------|-------|---------|
| `RiskPreferenceController` | `app/Http/Controllers/Api/RiskPreferenceController.php` | 305 | User risk preference management |

---

## 3. Risk Scoring - 7-Factor Automatic Calculation

**File:** `app/Services/Risk/AutoRiskCalculator.php` (422 lines)

### Factor 1: Capacity for Loss (Lines 67-110)
- **Formula:** `(investments + pensions) / net worth`
- **Data sources:** `InvestmentAccount.current_value` + `DCPension.current_fund_value`
- **Returns:** `components` array with `investments_total`, `pensions_total`, `net_worth` (actual £ values for display)
- **Levels (4 thresholds):**
  - HIGH: 0-15% of net worth in investments/pensions (less at risk, more capacity to lose)
  - MEDIUM: 15-50%
  - LOWER_MEDIUM: 50-75% (medium-low capacity)
  - LOW: >75% (most at risk, least capacity to lose)

### Factor 2: Time Horizon (Lines 111-146)
- **Data:** Years to retirement from `target_retirement_age` or calculated from `date_of_birth`
- **Levels:**
  - LOWER_MEDIUM: Retired or < 3 years
  - MEDIUM: 3-15 years
  - UPPER_MEDIUM: 15-20 years
  - HIGH: 20+ years

### Factor 3: Education Level (Lines 154-180)
- **Field:** `User.education_level`
- **Levels:**
  - LOWER_MEDIUM: secondary, GCSE, A-level, none
  - MEDIUM: degree or above

### Factor 4: Dependants (Lines 190-216)
- **Data:** Count of `FamilyMember` where `is_dependent = true`
- **Levels:**
  - UPPER_MEDIUM: 0 dependants
  - MEDIUM: 1 dependant
  - LOWER_MEDIUM: 2+ dependants

### Factor 5: Employment Status (Lines 224-254)
- **Field:** `User.employment_status`
- **Levels:**
  - MEDIUM: employed, self-employed, full-time, part-time
  - LOWER_MEDIUM: retired, semi-retired, unemployed

### Factor 6: Emergency Cash (Lines 264-301)
- **Formula:** `emergency_fund_balance / monthly_expenditure` (runway in months)
- **Data:** `SavingsAccount` where `is_emergency_fund = true`
- **Levels:**
  - LOWER_MEDIUM: < 3 months
  - MEDIUM: 3-6 months
  - UPPER_MEDIUM: 6+ months

### Factor 7: Surplus Cash (Lines 311-346)
- **Formula:** `(total annual income / 12) - monthly_expenditure`
- **Income sources:** employment, self-employment, rental, dividend, interest, trust, other
- **Levels:**
  - LOWER_MEDIUM: <= 0
  - MEDIUM: 0-500
  - UPPER_MEDIUM: 500+

### Final Level Determination (Lines 353-382)
- Uses **MODE** (most frequently occurring level across 7 factors)
- Tie-breaking: Prefers **lower risk** for safety
- Risk order: `low < lower_medium < medium < upper_medium < high`

---

## 4. Risk Level Configuration

**File:** `app/Services/Risk/RiskPreferenceService.php` (Lines 34-85)

| Level | Numeric | Equities | Bonds | Cash | Alts | Volatility | Expected Return | Colour |
|-------|---------|----------|-------|------|------|-----------|-----------------|--------|
| `low` | 1 | 10% | 70% | 20% | 0% | 3% | 1-3% | green |
| `lower_medium` | 2 | 30% | 55% | 10% | 5% | 6% | 2-4.5% | teal |
| `medium` | 3 | 50% | 35% | 10% | 5% | 10% | 3.5-6.5% | blue |
| `upper_medium` | 4 | 75% | 20% | 0% | 5% | 15% | 5-8.5% | amber |
| `high` | 5 | 90% | 0% | 5% | 5% | 20% | 6-12% | red |

---

## 5. Two Risk Systems

### System 1: Automatic (Default)
- Triggered on: Data changes, first login, manual recalculation
- Uses: `AutoRiskCalculator` -> 7 factors -> mode
- Stores: `risk_level` + `factor_breakdown` (JSON, nullable) + `risk_assessed_at` + `is_self_assessed=false`
- `getRiskProfile()` recalculates factor_breakdown live (always uses current financial data)

### System 2: Self-Assessment (User Override)
- User manually selects from 5 levels via `RiskLevelSelector.vue`
- Stores: `risk_level` only, `is_self_assessed=true`
- Overrides auto-calculated profile

---

## 6. Where Risk is Displayed

### Dedicated Risk Pages

| Component | Path | Purpose |
|-----------|------|---------|
| `RiskProfilePage.vue` | `resources/js/views/Risk/RiskProfilePage.vue` | Main risk profile with factor breakdown |
| `RiskLevelsExplainedPage.vue` | `resources/js/views/Risk/RiskLevelsExplainedPage.vue` | Educational page - all 5 levels |
| `RiskFactorDetailPage.vue` | `resources/js/views/Risk/RiskFactorDetailPage.vue` | Detailed individual factor explanation |

### Risk Components

| Component | Path | Purpose |
|-----------|------|---------|
| `RiskProfileSummary.vue` | `resources/js/components/Risk/RiskProfileSummary.vue` | Compact risk profile summary card |
| `FactorBreakdownCard.vue` | `resources/js/components/Risk/FactorBreakdownCard.vue` | Individual factor display card |
| `RiskLevelSelector.vue` | `resources/js/components/Shared/RiskLevelSelector.vue` | Interactive 5-level selector with validation |
| `CapacityForLossSection.vue` | `resources/js/components/Risk/CapacityForLossSection.vue` | Capacity for loss visualisation |
| `TimeHorizonSection.vue` | `resources/js/components/Risk/TimeHorizonSection.vue` | Time horizon visualisation |
| `RiskFactorsPanel.vue` | `resources/js/components/Risk/RiskFactorsPanel.vue` | All 7 factors panel |
| `InvestmentTypesAccordion.vue` | `resources/js/components/Risk/InvestmentTypesAccordion.vue` | Educational investment types by risk |

---

## 7. Cross-Module Integration

### Investment Module
- `InvestmentProjectionService.php` - Uses `RiskPreferenceService::getMainRiskLevel()` for projection return assumptions
- `AssetAllocationOptimizer.php` - `getTargetAllocation(RiskProfile)` for portfolio recommendations
- `PortfolioAnalyzer.php` - `matchesRiskProfile()` to check alignment
- `InvestmentPlanGenerator.php` - Uses `RiskProfile` for recommendations
- Product-level risk overrides: +/-1 from main profile

### Retirement Module
- `PensionProjector.php` - Uses `getRiskProfile()` for pension growth assumptions
- `RetirementProjectionService.php` - Uses `getRiskProfile()` for income projections

### Goals Module
- `GoalRiskService.php` - Links goals to risk profiles
- `Goal.risk_preference` field (1-5) for goal-specific risk
- `Goal.use_global_risk_profile` boolean to choose global vs goal-specific
- `getRiskParameters()` returns expected return and volatility per goal
- `getProjections()` calculates goal probability based on risk level

### Protection Module
- Life insurance recommendations factor in risk profile

### Estate Planning Module
- Trust and gifting strategies reference risk preferences

### Holistic Planning Module
- Aggregates risk from all modules in holistic view
- Risk assessment section in `HolisticPlan.vue`

---

## 8. Forms & User Inputs

### Direct Risk Inputs

| Input | Location | Field |
|-------|----------|-------|
| Self-Assessment | `RiskLevelSelector.vue` | 5-level button selector |
| Product Override | Investment/Pension forms | Risk level dropdown (+/-1 constraint) |

### Indirect Inputs (Trigger Auto-Recalculation)

From `User` model:
- `date_of_birth` -> time horizon
- `target_retirement_age` -> time horizon
- `education_level` -> education factor
- `employment_status` -> employment factor
- `monthly_expenditure` -> emergency cash, surplus cash
- `annual_employment_income` -> surplus cash
- `annual_self_employment_income` -> surplus cash
- `annual_rental_income` -> surplus cash
- `annual_dividend_income` -> surplus cash
- `annual_interest_income` -> surplus cash
- `annual_trust_income` -> surplus cash
- `annual_other_income` -> surplus cash

From other models:
- `FamilyMember.is_dependent` -> dependants factor
- `SavingsAccount.current_balance` (where `is_emergency_fund=true`) -> emergency cash
- `InvestmentAccount.current_value` -> capacity for loss
- `DCPension.current_fund_value` -> capacity for loss
- `Property.current_value` -> net worth (capacity for loss)

---

## 9. Database Schema

### Primary Table: `risk_profiles`

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Primary key |
| `user_id` | bigint FK | Foreign key to users |
| `risk_level` | varchar | Main level: low, lower_medium, medium, upper_medium, high |
| `risk_tolerance` | enum nullable | Legacy (deprecated): cautious, balanced, adventurous |
| `risk_assessed_at` | timestamp | When last calculated |
| `is_self_assessed` | boolean | True if user-selected, false if auto-calculated |
| `capacity_for_loss_percent` | decimal(5,2) nullable | Capacity calculation result |
| `time_horizon_years` | int nullable | Years to retirement |
| `knowledge_level` | enum nullable | Investment knowledge |
| `attitude_to_volatility` | varchar nullable | Volatility attitude |
| `esg_preference` | boolean | ESG preference flag |
| `factor_breakdown` | json nullable | Array of 7 factor calculations with details |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### Related Table Columns

**`investment_accounts`:**
- `risk_preference` (varchar) - Product-level override
- `has_custom_risk` (boolean, default false) - Override flag
- `rebalance_threshold_percent` (decimal) - Drift threshold by risk

**`dc_pensions`:**
- `risk_preference` (varchar) - Product-level override
- `has_custom_risk` (boolean, default false) - Override flag

**`goals`:**
- `risk_preference` (integer 1-5) - Goal-specific risk
- `use_global_risk_profile` (boolean) - Global vs goal-specific

**`retirement_profiles`:**
- `risk_tolerance` (varchar) - Legacy field (deprecated, points to `RiskPreferenceService`)

### Migrations

| Migration | Purpose |
|-----------|---------|
| `2026_01_03_154132_make_risk_profile_columns_nullable.php` | Made optional fields nullable |
| `2026_01_16_151113_add_factor_breakdown_to_risk_profiles.php` | Added JSON factor_breakdown column |
| `2026_01_24_134257_make_factor_breakdown_nullable_on_risk_profiles.php` | Made factor_breakdown nullable (required for initial auto-calculation) |

---

## 10. Model

**File:** `app/Models/Investment/RiskProfile.php` (47 lines)

```
Relationships: belongsTo(User)

Fillable: user_id, risk_tolerance, risk_level, capacity_for_loss_percent,
          time_horizon_years, knowledge_level, attitude_to_volatility,
          esg_preference, risk_assessed_at, is_self_assessed, factor_breakdown

Casts:
  capacity_for_loss_percent -> float
  time_horizon_years -> integer
  esg_preference -> boolean
  risk_assessed_at -> datetime
  is_self_assessed -> boolean
  factor_breakdown -> array
```

---

## 11. Observers (Auto-Recalculation Triggers)

| Observer | Model | Events | Relevant Fields |
|----------|-------|--------|-----------------|
| `UserRiskObserver` | `User` | updated | income fields, education, employment, expenditure, retirement age, DOB |
| `InvestmentAccountRiskObserver` | `InvestmentAccount` | created, updated, deleted | current_value |
| `SavingsAccountRiskObserver` | `SavingsAccount` | created, updated, deleted | current_balance, is_emergency_fund |
| `FamilyMemberRiskObserver` | `FamilyMember` | created, updated, deleted | is_dependent |
| `DCPensionRiskObserver` | `DCPension` | created, updated, deleted | current_fund_value |
| `PropertyRiskObserver` | `Property` | created, updated, deleted | current_value, purchase_price, ownership_percentage |

**Base class:** `app/Observers/RiskRecalculationObserver.php` - dispatches `RecalculateRiskProfileJob`

---

## 12. Async Job

**File:** `app/Jobs/RecalculateRiskProfileJob.php` (91 lines)

- Implements `ShouldQueue`
- Unique ID: `'risk_recalc_' . $userId` (prevents duplicates)
- Delay: 5 seconds (debounces rapid changes)
- Timeout: 30 seconds
- Retries: 1
- Process: Calls `RiskPreferenceService::calculateAndSetRiskLevel()`, then backfills product-level `risk_preference` for accounts without custom risk

---

## 13. Frontend Service

**File:** `resources/js/services/riskService.js` (137 lines)

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `getLevels()` | GET `/investment/risk/levels` | All 5 levels with configs |
| `getProfile()` | GET `/investment/risk/profile` | User's risk profile |
| `setProfile(data)` | POST `/investment/risk/profile` | Set main risk level |
| `getAllowedLevels()` | GET `/investment/risk/allowed-levels` | +/-1 constraint levels |
| `validateProductLevel(level)` | POST `/investment/risk/validate-product-level` | Validate product override |
| `getRiskConfig(level)` | GET `/investment/risk/config/{level}` | Config for specific level |
| `recalculate()` | POST `/investment/risk/recalculate` | Force recalculation |
| `getDisplayName(level)` | - | Helper: level display name |
| `getRiskColor(level)` | - | Helper: Tailwind colour classes |
| `normalizeLegacyTolerance(tolerance)` | - | Map old 3-level to 5-level |

---

## 14. Vuex Store

**File:** `resources/js/store/modules/investment.js`

- `state.riskProfile` - Cached risk profile object
- `investmentRiskLevel` getter - from `state.analysis?.risk_metrics?.risk_level`
- `userRiskLevel` getter - from `state.riskProfile?.risk_level`
- `hasRiskProfile` getter - boolean check

---

## 15. Colour Mapping

| Level | Tailwind Classes | Hex Approximate |
|-------|-----------------|-----------------|
| `low` | `bg-green-*`, `text-green-*` | Green |
| `lower_medium` | `bg-teal-*`, `text-teal-*` | Teal |
| `medium` | `bg-blue-*`, `text-blue-*` | Blue |
| `upper_medium` | `bg-amber-*`, `text-amber-*` | Amber |
| `high` | `bg-red-*`, `text-red-*` | Red |

---

## 16. Legacy System (Deprecated)

Old 3-level system mapped to new 5-level:
- `cautious` -> `lower_medium`
- `balanced` -> `medium`
- `adventurous` -> `upper_medium`

Handled by `RiskPreferenceService::mapLegacyTolerance()` (Lines 341-349)

---

## 17. Key Files Summary

| File | Lines | Purpose |
|------|-------|---------|
| `app/Models/Investment/RiskProfile.php` | 47 | Risk profile model |
| `app/Services/Risk/AutoRiskCalculator.php` | 422 | 7-factor auto-calculation engine |
| `app/Services/Risk/RiskPreferenceService.php` | 367 | Main risk management service |
| `app/Http/Controllers/Api/RiskPreferenceController.php` | 305 | API endpoints |
| `app/Jobs/RecalculateRiskProfileJob.php` | 91 | Async recalculation job |
| `app/Observers/RiskRecalculationObserver.php` | 38 | Base observer class |
| `app/Observers/UserRiskObserver.php` | 43 | User model observer |
| `app/Observers/InvestmentAccountRiskObserver.php` | 34 | Investment observer |
| `app/Observers/SavingsAccountRiskObserver.php` | 39 | Savings observer |
| `app/Observers/FamilyMemberRiskObserver.php` | 38 | Family member observer |
| `app/Observers/DCPensionRiskObserver.php` | 34 | Pension observer |
| `app/Observers/PropertyRiskObserver.php` | 86 | Property observer |
| `app/Services/Goals/GoalRiskService.php` | 80+ | Goal-based risk service |
| `resources/js/services/riskService.js` | 137 | Frontend API service |
| `resources/js/views/Risk/RiskProfilePage.vue` | 377 | Main risk profile page |
| `resources/js/views/Risk/RiskLevelsExplainedPage.vue` | 245 | Educational levels page |
| `resources/js/views/Risk/RiskFactorDetailPage.vue` | 540 | Detailed factor page |
| `resources/js/components/Risk/RiskProfileSummary.vue` | 356 | Summary component |
| `resources/js/components/Risk/FactorBreakdownCard.vue` | 133 | Factor card component |
| `resources/js/components/Shared/RiskLevelSelector.vue` | 405 | Risk selector component |
| `resources/js/components/Risk/CapacityForLossSection.vue` | 195 | Capacity section (4-zone spectrum) |
| `resources/js/components/Risk/TimeHorizonSection.vue` | 275 | Time horizon section |
| `resources/js/components/Risk/RiskFactorsPanel.vue` | 181 | Factors panel |
| `resources/js/components/Risk/InvestmentTypesAccordion.vue` | 393 | Investment types accordion |
| `tests/Unit/Services/Risk/AutoRiskCalculatorTest.php` | 200+ | Calculator unit tests |
