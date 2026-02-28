# Protection Module - Current State Documentation

**Last Updated:** 2026-02-18
**Module Version:** Part of Fynla v0.7.0
**Status:** Fully functional with 5 policy types, gap analysis, scenario modelling, and comprehensive plan generation

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
18. [Coverage Gap Allocation Logic](#18-coverage-gap-allocation-logic)

---

## 1. System Overview

The Protection module covers insurance and risk management across five policy types:

| Policy Type | Model | Purpose |
|---|---|---|
| Life Insurance | `LifeInsurancePolicy` | Death benefit, mortgage protection, family income |
| Critical Illness | `CriticalIllnessPolicy` | Lump sum on diagnosis of specified conditions |
| Income Protection | `IncomeProtectionPolicy` | Ongoing income replacement during illness/injury |
| Disability | `DisabilityPolicy` | Accident and/or sickness cover |
| Sickness/Illness | `SicknessIllnessPolicy` | Condition-specific benefit cover |

### Architecture Flow

```
ProtectionDashboard.vue
  -> CurrentSituation.vue (policy listing, gap analysis, recommendations)
  -> PolicyFormModal.vue (unified CRUD for all 5 types)
  -> protectionService.js (22 API methods)
  -> ProtectionController.php (uses PolicyCRUDTrait)
  -> ProtectionAgent.php (orchestrates analysis, caching)
  -> Services: CoverageGapAnalyzer, AdequacyScorer, RecommendationEngine, ScenarioBuilder
  -> ComprehensiveProtectionPlanService (11-section document)
```

### File Count Summary

| Category | Count |
|---|---|
| Models | 6 (ProtectionProfile + 5 policy types) |
| Services | 5 |
| Vue Components | 15 |
| Vue Views | 2 |
| Validation Requests | 13 (Base + Store/Update for each type + Profile + Scenario) |
| API Endpoints | 22 |

---

## 2. Database Schema

### 2.1 `protection_profiles`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK, AUTO_INCREMENT |
| `user_id` | bigint unsigned | FK -> users(id) CASCADE, UNIQUE |
| `annual_income` | decimal(15,2) | NOT NULL |
| `monthly_expenditure` | decimal(10,2) | NOT NULL |
| `mortgage_balance` | decimal(15,2) | NOT NULL DEFAULT 0.00 |
| `other_debts` | decimal(15,2) | NOT NULL DEFAULT 0.00 |
| `number_of_dependents` | int | NOT NULL DEFAULT 0 |
| `dependents_ages` | json | NULL |
| `retirement_age` | int | NOT NULL DEFAULT 67 |
| `occupation` | varchar(255) | NULL |
| `smoker_status` | tinyint(1) | NOT NULL DEFAULT 0 |
| `health_status` | varchar(255) | NOT NULL DEFAULT 'good' |
| `has_no_policies` | tinyint(1) | NOT NULL DEFAULT 0 |
| `created_at` / `updated_at` | timestamp | NULL |

**Indexes:** `protection_profiles_user_id_unique`, `protection_profiles_user_id_index`

### 2.2 `life_insurance_policies`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK, AUTO_INCREMENT |
| `user_id` | bigint unsigned | FK -> users(id) CASCADE |
| `policy_type` | enum | `term`, `whole_of_life`, `decreasing_term`, `family_income_benefit`, `level_term` DEFAULT 'term' |
| `provider` | varchar(255) | NULL |
| `policy_number` | varchar(255) | NULL |
| `sum_assured` | decimal(15,2) | NULL |
| `start_value` | decimal(15,2) | NULL (for decreasing term) |
| `decreasing_rate` | decimal(5,4) | NULL (for decreasing term) |
| `premium_amount` | decimal(10,2) | NULL |
| `premium_frequency` | enum | `monthly`, `quarterly`, `annually` DEFAULT 'monthly' |
| `policy_start_date` | date | NULL |
| `policy_term_years` | int | NULL |
| `policy_end_date` | date | NULL |
| `indexation_rate` | decimal(5,4) | DEFAULT 0.0000 |
| `in_trust` | tinyint(1) | NOT NULL DEFAULT 0 |
| `is_mortgage_protection` | tinyint(1) | NOT NULL DEFAULT 0 |
| `beneficiaries` | text | NULL |
| `created_at` / `updated_at` | timestamp | NULL |

**Indexes:** `life_insurance_policies_user_id_index`, `life_policies_user_type_idx (user_id, policy_type)`

### 2.3 `critical_illness_policies`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK, AUTO_INCREMENT |
| `user_id` | bigint unsigned | FK -> users(id) CASCADE |
| `policy_type` | enum | `standalone`, `accelerated`, `additional` DEFAULT 'standalone' |
| `provider` | varchar(255) | NULL |
| `policy_number` | varchar(255) | NULL |
| `sum_assured` | decimal(15,2) | NULL |
| `premium_amount` | decimal(10,2) | NULL |
| `premium_frequency` | enum | `monthly`, `quarterly`, `annually` DEFAULT 'monthly' |
| `policy_start_date` | date | NULL |
| `policy_end_date` | date | NULL |
| `policy_term_years` | int | NULL |
| `conditions_covered` | json | NULL |
| `created_at` / `updated_at` | timestamp | NULL |

**Indexes:** `critical_illness_policies_user_id_index`, `ci_policies_user_type_idx (user_id, policy_type)`

### 2.4 `income_protection_policies`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK, AUTO_INCREMENT |
| `user_id` | bigint unsigned | FK -> users(id) CASCADE |
| `provider` | varchar(255) | NULL |
| `policy_number` | varchar(255) | NULL |
| `benefit_amount` | decimal(10,2) | NOT NULL |
| `benefit_frequency` | enum | `monthly`, `weekly` DEFAULT 'monthly' |
| `deferred_period_weeks` | int | NULL |
| `benefit_period_months` | int | NULL |
| `premium_amount` | decimal(10,2) | NULL |
| `premium_frequency` | enum | `monthly`, `quarterly`, `annually` DEFAULT 'monthly' |
| `occupation_class` | varchar(255) | NULL |
| `policy_start_date` | date | NULL |
| `policy_end_date` | date | NULL |
| `created_at` / `updated_at` | timestamp | NULL |

**Note:** No `policy_type` column. No `sum_assured` - uses `benefit_amount` instead.

### 2.5 `disability_policies`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK, AUTO_INCREMENT |
| `user_id` | bigint unsigned | FK -> users(id) CASCADE |
| `provider` | varchar(255) | NULL |
| `policy_number` | varchar(255) | NULL |
| `benefit_amount` | decimal(10,2) | NOT NULL |
| `benefit_frequency` | enum | `monthly`, `weekly` DEFAULT 'monthly' |
| `deferred_period_weeks` | int | NULL |
| `benefit_period_months` | int | NULL |
| `premium_amount` | decimal(10,2) | NULL |
| `premium_frequency` | enum | `monthly`, `quarterly`, `annually` DEFAULT 'monthly' |
| `occupation_class` | varchar(255) | NULL |
| `policy_start_date` | date | NULL |
| `policy_end_date` | date | NULL |
| `policy_term_years` | int | NULL |
| `coverage_type` | enum | `accident_only`, `accident_and_sickness` DEFAULT 'accident_and_sickness' |
| `created_at` / `updated_at` | timestamp | NULL |

### 2.6 `sickness_illness_policies`

| Column | Type | Constraints |
|---|---|---|
| `id` | bigint unsigned | PK, AUTO_INCREMENT |
| `user_id` | bigint unsigned | FK -> users(id) CASCADE |
| `provider` | varchar(255) | NULL |
| `policy_number` | varchar(255) | NULL |
| `benefit_amount` | decimal(10,2) | NOT NULL |
| `benefit_frequency` | enum | `monthly`, `weekly`, `lump_sum` DEFAULT 'lump_sum' |
| `deferred_period_weeks` | int | NULL |
| `benefit_period_months` | int | NULL |
| `premium_amount` | decimal(10,2) | NULL |
| `premium_frequency` | enum | `monthly`, `quarterly`, `annually` DEFAULT 'monthly' |
| `policy_start_date` | date | NULL |
| `policy_end_date` | date | NULL |
| `policy_term_years` | int | NULL |
| `conditions_covered` | json | NULL |
| `exclusions` | text | NULL |
| `created_at` / `updated_at` | timestamp | NULL |

**Note:** Only policy type supporting `lump_sum` as `benefit_frequency`.

### Migration History

| Migration | Batch | Purpose |
|---|---|---|
| `2025_10_13_131230_create_protection_profiles_table` | 1 | Initial profile table |
| `2025_10_13_131230_create_life_insurance_policies_table` | 1 | Initial life table |
| `2025_10_13_131230_create_critical_illness_policies_table` | 1 | Initial CI table |
| `2025_10_13_131230_create_income_protection_policies_table` | 1 | Initial IP table |
| `2025_10_13_132846_create_disability_policies_table` | 1 | Initial disability table |
| `2025_10_13_132846_create_sickness_illness_policies_table` | 1 | Initial sickness table |
| `2025_10_28_115155_add_has_no_policies_to_protection_profiles_table` | 3 | Added `has_no_policies` flag |
| `2025_11_12_083427_add_decreasing_policy_fields_to_life_insurance_policies_table` | 3 | Added `start_value`, `decreasing_rate` |
| `2025_11_14_120204_add_end_date_and_make_fields_optional_on_life_insurance_policies_table` | 3 | Added `policy_end_date`, made fields nullable |
| `2025_11_15_125142_add_is_mortgage_protection_to_life_insurance_policies_table` | 3 | Added `is_mortgage_protection` |
| `2025_11_24_124735_make_policy_end_date_nullable_on_life_insurance_policies_table` | 3 | Made `policy_end_date` nullable |

---

## 3. Models

### 3.1 `ProtectionProfile`

**File:** `app/Models/ProtectionProfile.php` (59 lines)

- **Relationships:** `user()` BelongsTo User
- **Fillable (12):** `user_id`, `annual_income`, `monthly_expenditure`, `mortgage_balance`, `other_debts`, `number_of_dependents`, `dependents_ages`, `retirement_age`, `occupation`, `smoker_status`, `health_status`, `has_no_policies`
- **Casts:** `dependents_ages` as array, `smoker_status` as boolean, `has_no_policies` as boolean

### 3.2 `LifeInsurancePolicy`

**File:** `app/Models/LifeInsurancePolicy.php` (65 lines)

- **Traits:** `Auditable`, `HasFactory`
- **Relationships:** `user()` BelongsTo User
- **Fillable (16):** `user_id`, `policy_type`, `provider`, `policy_number`, `sum_assured`, `start_value`, `decreasing_rate`, `premium_amount`, `premium_frequency`, `policy_start_date`, `policy_end_date`, `policy_term_years`, `indexation_rate`, `in_trust`, `is_mortgage_protection`, `beneficiaries`
- **Casts:** `in_trust` boolean, `is_mortgage_protection` boolean, dates, decimals

### 3.3 `CriticalIllnessPolicy`

**File:** `app/Models/CriticalIllnessPolicy.php` (54 lines)

- **Traits:** `HasFactory`
- **Fillable (10):** `user_id`, `policy_type`, `provider`, `policy_number`, `sum_assured`, `premium_amount`, `premium_frequency`, `policy_start_date`, `policy_term_years`, `conditions_covered`
- **Casts:** `conditions_covered` as array

### 3.4 `IncomeProtectionPolicy`

**File:** `app/Models/IncomeProtectionPolicy.php` (54 lines)

- **Traits:** `HasFactory`
- **Fillable (10):** `user_id`, `provider`, `policy_number`, `benefit_amount`, `benefit_frequency`, `deferred_period_weeks`, `benefit_period_months`, `premium_amount`, `occupation_class`, `policy_start_date`
- **Note:** Uses `benefit_amount` instead of `sum_assured`. Benefit is always treated as monthly in calculations.

### 3.5 `DisabilityPolicy`

**File:** `app/Models/DisabilityPolicy.php` (57 lines)

- **Traits:** `HasFactory`
- **Fillable (13):** `user_id`, `provider`, `policy_number`, `benefit_amount`, `benefit_frequency`, `deferred_period_weeks`, `benefit_period_months`, `premium_amount`, `premium_frequency`, `occupation_class`, `policy_start_date`, `policy_term_years`, `coverage_type`
- **Unique field:** `coverage_type` enum (`accident_only`, `accident_and_sickness`)

### 3.6 `SicknessIllnessPolicy`

**File:** `app/Models/SicknessIllnessPolicy.php` (58 lines)

- **Traits:** `HasFactory`
- **Fillable (13):** `user_id`, `provider`, `policy_number`, `benefit_amount`, `benefit_frequency`, `deferred_period_weeks`, `benefit_period_months`, `premium_amount`, `premium_frequency`, `policy_start_date`, `policy_term_years`, `conditions_covered`, `exclusions`
- **Casts:** `conditions_covered` as array
- **Unique field:** `benefit_frequency` supports `lump_sum` (only policy type with this option)

### User Model Relationships

**File:** `app/Models/User.php` (lines 225-260)

```php
protectionProfile(): HasOne          // One ProtectionProfile per user
lifeInsurancePolicies(): HasMany     // LifeInsurancePolicy
criticalIllnessPolicies(): HasMany   // CriticalIllnessPolicy
incomeProtectionPolicies(): HasMany  // IncomeProtectionPolicy
disabilityPolicies(): HasMany        // DisabilityPolicy
sicknessIllnessPolicies(): HasMany   // SicknessIllnessPolicy
```

---

## 4. Controller

**File:** `app/Http/Controllers/Api/ProtectionController.php` (429 lines)

**Dependencies:** `ProtectionAgent`, `ComprehensiveProtectionPlanService`
**Traits:** `PolicyCRUDTrait`, `SanitizedErrorResponse`

### Endpoints

| Method | HTTP | Route | Purpose |
|---|---|---|---|
| `index()` | GET | `/api/protection` | Returns profile + all 5 policy types. Auto-creates profile if missing |
| `analyze()` | POST | `/api/protection/analyze` | Full gap analysis via Agent |
| `recommendations()` | GET | `/api/protection/recommendations` | Runs analysis then returns recommendations |
| `scenarios()` | POST | `/api/protection/scenarios` | What-if scenarios (validated by ScenarioRequest) |
| `storeProfile()` | POST | `/api/protection/profile` | Upserts ProtectionProfile, invalidates cache |
| `updateHasNoPolicies()` | PATCH | `/api/protection/profile/has-no-policies` | Updates only `has_no_policies` flag |
| `getComprehensiveProtectionPlan()` | GET | `/api/protection/comprehensive-plan` | Full plan document |

**Policy CRUD (via PolicyCRUDTrait):** 5 policy types x 3 operations (store/update/destroy) = 15 endpoints. Each uses a specific validation request class and delegates to the trait methods.

### Key Behaviours

- **Auto-create profile:** `index()` creates a `ProtectionProfile` with income/expenditure defaults from user's profile if one doesn't exist
- **Eager loading:** `index()` loads all 5 policy relationships
- **Cache invalidation:** All write operations call `$this->protectionAgent->invalidateCache($userId)`

### PolicyCRUDTrait

**File:** `app/Traits/PolicyCRUDTrait.php` (136 lines)

Three methods providing standardised CRUD:
- `storePolicy($modelClass, $validated, $userId, $policyTypeName)` - Creates, returns 201
- `updatePolicy($modelClass, $validated, $userId, $id, $policyTypeName)` - Finds by user_id AND id, returns 200
- `destroyPolicy($modelClass, $userId, $id, $policyTypeName)` - Finds by user_id AND id, deletes

All three return 404 if `ModelNotFoundException` (ownership check built into query via `where('user_id', $userId)->findOrFail($id)`).

### No API Resources

The controller returns raw models via `response()->json()` directly. There are no dedicated `ProtectionPolicyResource` or `ProtectionProfileResource` classes.

---

## 5. Agent

**File:** `app/Agents/ProtectionAgent.php` (275 lines)

Extends `BaseAgent`. Injected: `CoverageGapAnalyzer`, `AdequacyScorer`, `RecommendationEngine`, `ScenarioBuilder`, `ProfileCompletenessChecker`.

### Cache Strategy

- **Key:** `protection_analysis_{userId}`
- **Tags:** `['protection', 'user_{userId}']`
- **Invalidation:** Tagged cache flush when supported; falls back to forgetting specific key

### `analyze(int $userId): array`

Cached. Loads user with all 5 policy relationships and protectionProfile. Returns:

```php
[
    'profile' => [
        'annual_income', 'total_annual_income', 'monthly_expenditure',
        'mortgage_balance', 'other_debts', 'number_of_dependents',
        'retirement_age', 'current_age'
    ],
    'needs' => [
        'human_capital', 'debt_protection', 'education_funding',
        'final_expenses', 'income_protection_need', 'total_need',
        'gross_income', 'net_income', 'continuing_income',
        'income_that_stops', 'income_that_continues', 'net_income_difference',
        'income_tax', 'national_insurance',
        'spouse_included', 'spouse_gross_income', 'spouse_net_income',
        'spouse_continuing_income', 'spouse_permission_denied'
    ],
    'coverage' => [
        'life_coverage', 'critical_illness_coverage',
        'income_protection_coverage', 'disability_coverage',
        'sickness_illness_coverage', 'total_coverage', 'total_income_coverage'
    ],
    'gaps' => [
        'total_need', 'total_coverage', 'total_coverage_used', 'total_gap',
        'gaps_by_category', 'coverage_allocated', 'income_replacement_coverage',
        'coverage_percentage'
    ],
    'adequacy_score' => [
        'overall_score', 'rating', 'color', 'insights',
        'life_insurance_score', 'critical_illness_score', 'income_protection_score',
        'score', 'category'  // legacy aliases
    ],
    'recommendations' => [],
    'scenarios' => [
        'death' => {...},
        'critical_illness' => {...},
        'disability' => {...}
    ],
    'debt_breakdown' => ['mortgage', 'other', 'total'],
    'policies' => [
        'life_insurance' => [...],
        'critical_illness' => [...],
        'income_protection' => [...],
        'disability' => [...],
        'sickness_illness' => [...]
    ],
    'profile_completeness' => [...]
]
```

### `generateRecommendations(array $analysisData): array`

Extracts and returns recommendations from already-computed analysis data. Used by the `/recommendations` endpoint.

### `buildScenarios(int $userId, array $parameters): array`

Builds specific scenarios based on `scenario_types` parameter array. Supports: `death`, `critical_illness`, `disability`, `premium_change`. If `scenario_types` is empty, builds all three default scenarios. The `premium_change` scenario requires a `new_coverage` parameter.

### `invalidateCache(int $userId): void`

Uses tagged cache flush when supported; falls back to forgetting the specific key.

---

## 6. Services

### 6.1 CoverageGapAnalyzer

**File:** `app/Services/Protection/CoverageGapAnalyzer.php` (355 lines)

Injected: `UKTaxCalculator`

**`calculateProtectionNeeds(ProtectionProfile $profile): array`**
- Calls `UKTaxCalculator::calculateNetIncome()` on employment + self-employment + other income
- Excludes rental/dividend income (these CONTINUE after death, not an insurable loss)
- If user is married and `hasAcceptedSpousePermission()`, fetches spouse income and reduces protection need
- Falls back to `profile->annual_income` if user income fields are 0
- Human capital = 0 if spouse earns more than user
- `income_protection_need` = `gross_income * 0.6` (60% standard)

**`calculateHumanCapital(float $annualIncomeNeed): float`**
- Sustainable drawdown approach: capital needed so family can draw income indefinitely at 4.7% rate
- `result = annualIncomeNeed / 0.047`

**`calculateDebtProtectionNeed(ProtectionProfile $profile): float`**
- Uses `profile->mortgage_balance + profile->other_debts` if non-zero
- Falls back to live DB: `user->mortgages()->sum('outstanding_balance') + user->liabilities()->sum('current_balance')`

**`calculateEducationFunding(int $numChildren, array $ages): float`**
- Fixed at £9,000 per year per child until age 21

**`calculateFinalExpenses(): float`**
- Fixed: £7,500

**`calculateTotalCoverage(Collection ...policyCollections): array`**
- Life/CI: sums `sum_assured` directly
- Income Protection/Disability: converts to annual (`monthly * 12` or `weekly * 52`)
- Sickness/Illness: monthly/weekly annualised OR lump_sum added directly
- Returns `total_coverage` (life + CI) and `total_income_coverage` (IP + disability + sickness)

**`calculateCoverageGap(array $needs, array $coverage): array`**
- Priority allocation of life insurance coverage (see Section 18)

### 6.2 AdequacyScorer

**File:** `app/Services/Protection/AdequacyScorer.php` (133 lines)

**`calculateAdequacyScore(array $gaps, array $needs): int`**
- Formula: `((total_need - total_gap) / total_need) * 100`
- Clamped 0-100

**Score Categories:**

| Score | Rating | Colour |
|---|---|---|
| 80-100 | Excellent | green |
| 60-79 | Good | blue |
| 40-59 | Fair | orange |
| 0-39 | Critical | red |

**`generateScoreInsights()`** returns: `overall_score`, `rating`, `color`, `insights[]`, `life_insurance_score`, `critical_illness_score`, `income_protection_score`

**Note:** `critical_illness_score` and `income_protection_score` are currently placeholders returning 0.

### 6.3 RecommendationEngine

**File:** `app/Services/Protection/RecommendationEngine.php` (266 lines)

**7 Recommendation Triggers:**

| # | Trigger | Action |
|---|---|---|
| 1 | Life gap > £10,000 AND has dependants | "Increase life insurance coverage" |
| 2 | Debt protection gap > 0 | "Add decreasing term cover for debts" |
| 3 | Human capital gap > 0 AND no CI policies | "Consider critical illness cover" |
| 4 | IP gap > 0 | "Add income protection insurance" |
| 5 | Education gap > 0 AND has dependants | "Consider family income benefit policy" |
| 6 | Any life policies not in trust | "Place policies in trust" |
| 7 | Total premiums > 5% of annual income | "Review and optimise existing policies" |

**Priority Calculation** (based on gap/income ratio):

| Ratio | Priority | Level |
|---|---|---|
| > 5x | 1 | Critical |
| > 2x | 2 | High |
| > 1x | 3 | Medium |
| <= 1x | 4 | Low |

Recommendations sorted by priority (ascending = most critical first).

Each recommendation contains: `priority`, `category`, `action`, `rationale`, `impact`, `estimated_cost`.

**Premium Estimation (simplified):**

| Type | Base Rate | Adjustments |
|---|---|---|
| Life insurance | £0.50 per £1,000 sum assured per year | +50% smoker, +20% age 40-50, +50% age 50+ |
| Critical illness | Life premium x 2.5 | Same adjustments |
| Income protection | 2% of annual benefit | +30% smoker |
| Debt protection | Life premium x 0.8 | Decreasing term is cheaper |
| Family income benefit | Life premium x 0.7 | |

### 6.4 ScenarioBuilder

**File:** `app/Services/Protection/ScenarioBuilder.php` (259 lines)

**`modelDeathScenario()`**
- Life coverage - debts = remaining funds
- 3% safe withdrawal rate for annual income
- Calculates months of support from remaining funds

**`modelCriticalIllnessScenario()`**
- CI coverage - 6 months emergency fund (immediate needs) = remaining
- Calculates months of support

**`modelDisabilityScenario()`**
- IP annualised benefit as monthly income
- Monthly shortfall vs expenditure
- Income replacement ratio

**`modelPremiumChangeScenario(array $coverage, float $newCoverage)`**
- Estimates new premium at £0.50 per £1,000 per year

**Adequacy Ratings:**

| Type | Excellent | Good | Fair | Poor |
|---|---|---|---|---|
| Death/CI | 10+ years support | 5-9 years | 2-4 years | < 2 years |
| Income Protection | 60%+ replacement | 50-59% | 40-49% | < 40% |

### 6.5 ComprehensiveProtectionPlanService

**File:** `app/Services/Protection/ComprehensiveProtectionPlanService.php` (769 lines)

Uses `FormatsCurrency` trait. Calls `ProtectionAgent::analyze()` then builds an 11-section structured document.

**Plan Sections:**

| Section | Content |
|---|---|
| `plan_metadata` | Generated date/time, plan version, user name, completeness score, plan type |
| `completeness_warning` | Score, severity, disclaimer, missing fields, recommendations (or null) |
| `executive_summary` | Title, adequacy score, critical gaps, total gap amount, recommended action |
| `user_profile` | Name, age, gender, marital status, occupation, smoker, health, dependants |
| `financial_summary` | Total income, income breakdown, expenditure, debt breakdown |
| `current_coverage` | Life, CI, IP summaries, total annual premiums |
| `protection_needs` | Total need, breakdown by category, income analysis, spouse info |
| `coverage_analysis` | Life/CI/IP adequacy with scores, overall score + rating |
| `recommendations` | Categorised recommendations with plan type awareness |
| `scenario_analysis` | Death, critical illness, disability scenarios |
| `optimized_strategy` | Strategy name, prioritised recommendations, summary |
| `implementation_timeline` | Phased action plan |
| `next_steps` | Immediate, short-term, ongoing management |

**Plan Type Classification** (based on profile completeness score):

| Score | Plan Type |
|---|---|
| 100% | Personalised |
| 70-99% | Mostly Personalised |
| 50-69% | Partially Generic |
| < 50% | Generic |

**Coverage Analysis Thresholds (in plan context):**
- Critical illness need = `gross_income * 3`
- Income protection need = `net_income * 0.7 / 12` (monthly)

**Optimised Strategy Priorities:** 1 = Life Insurance, 2 = Critical Illness, 3 = Income Protection

---

## 7. Validation Requests

**Directory:** `app/Http/Requests/Protection/`

### BasePolicyRequest (abstract)

**File:** `app/Http/Requests/Protection/BasePolicyRequest.php` (83 lines)

Common rules shared across policy types:
- `provider` - nullable string max 255
- `policy_number` - nullable string max 255
- `sum_assured` - nullable numeric 0 to 9,999,999,999,999.99
- `premium_amount` - nullable numeric 0 to 9,999,999.99
- `premium_frequency` - nullable, in: monthly/quarterly/annually
- `policy_start_date` - nullable date, before_or_equal today
- `policy_end_date` - nullable date, after today
- `policy_term_years` - nullable integer 1-50

### StoreProtectionProfileRequest

All fields nullable. `retirement_age` 50-85. `number_of_dependents` 0-20. `dependents_ages` array of integers 0-100. `health_status` in: excellent/good/fair/poor.

### StoreLifePolicyRequest

`policy_type` in: `term`, `whole_of_life`, `decreasing_term`, `family_income_benefit`, `level_term`. Conditional rules for `decreasing_term` (adds `start_value`, `decreasing_rate`). `indexation_rate` 0-0.10. Boolean: `in_trust`, `is_mortgage_protection`. `beneficiaries` max 1000. **Note:** All dates made optional per v0.2.13 patch.

### StoreCriticalIllnessPolicyRequest

`policy_type` in: `standalone`, `accelerated`, `additional`. `conditions_covered` array. `policy_end_date` validates `after:policy_start_date` (not `after:today`).

### StoreIncomeProtectionPolicyRequest

**Required fields:** `provider`, `benefit_amount` (min 1000), `benefit_frequency` (monthly/weekly), `premium_amount`. Optional: `deferred_period_weeks` (0-104), `benefit_period_months` (1-720).

### StoreDisabilityPolicyRequest

All nullable. `coverage_type` in: `accident_only`, `accident_and_sickness`. `benefit_frequency` in: `monthly`, `weekly`.

### StoreSicknessIllnessPolicyRequest

All nullable. `benefit_frequency` in: `monthly`, `weekly`, `lump_sum`. `conditions_covered` array with string items. `exclusions` string max 2000.

### ScenarioRequest

`scenario_types` nullable array, each in: `death`, `critical_illness`, `disability`, `premium_change`. `new_coverage` nullable numeric.

### Update Requests

Mirror their Store counterparts but use `sometimes` instead of `required`.

---

## 8. Vuex Store

**File:** `resources/js/store/modules/protection.js` (434 lines)

### State

```javascript
{
    profile: null,
    policies: {
        life: [],
        criticalIllness: [],
        incomeProtection: [],
        disability: [],
        sicknessIllness: [],
    },
    analysis: null,
    recommendations: [],
    loading: false,
    error: null,
}
```

### Key Getters

| Getter | Returns |
|---|---|
| `policies` | All policy collections |
| `adequacyScore` | `analysis.data.adequacy_score` |
| `totalCoverage` | Sum of life + criticalIllness `sum_assured` |
| `totalPremium` | Total monthly premium across all 5 types |
| `coverageGaps` | `analysis.data.gaps` |
| `lifePolicies`, `criticalIllnessPolicies`, etc. | Individual policy arrays |
| `priorityRecommendations` | Filtered where `priority === 'high'` |
| `allPolicies` | Flat array with `policy_type` override (life preserves original as `policy_subtype`) |
| `premiumBreakdown` | Monthly premium by type object |
| `hasLifePoliciesInTrust` | Any life policy `in_trust === true` |
| `hasLifePoliciesNotInTrust` | Any life policy not in trust |
| `hasIncomeProtection` | Boolean |
| `hasCriticalIllness` | Boolean |
| `hasDisabilityInsurance` | Boolean |

### Factory Pattern

Uses `createPolicyActionFactory`, `updatePolicyActionFactory`, `deletePolicyActionFactory` to generate CRUD actions for all 5 policy types. All factory-generated actions trigger `analyseProtection` after any mutation.

### Key Actions

- `fetchProfile()` - calls `getProtectionData()`, sets profile
- `fetchProtectionData()` - fetches data + runs analysis (analysis failure does not fail the whole action)
- `analyseProtection()` - calls `analyzeProtection()`, sets analysis
- `fetchRecommendations()` - calls `getRecommendations()`, sets recommendations
- `createPolicy`, `updatePolicy`, `deletePolicy` - generic dispatchers using type maps

### Key Mutations

- `setPolicies(state, policies)` - maps API snake_case (`life_insurance`, `critical_illness`, etc.) to store camelCase keys
- `addPolicy`, `updatePolicy`, `removePolicy` - operate on individual policy arrays

---

## 9. API Service

**File:** `resources/js/services/protectionService.js` (245 lines)

All methods use the `api` axios wrapper. 22 methods total.

| Method | HTTP | Endpoint |
|---|---|---|
| `getProtectionData()` | GET | `/protection` |
| `saveProfile(data)` | POST | `/protection/profile` |
| `updateHasNoPolicies(flag)` | PATCH | `/protection/profile/has-no-policies` |
| `analyzeProtection(data)` | POST | `/protection/analyze` |
| `getRecommendations()` | GET | `/protection/recommendations` |
| `runScenario(data)` | POST | `/protection/scenarios` |
| `getComprehensiveProtectionPlan()` | GET | `/protection/comprehensive-plan` |
| `createLifePolicy(data)` | POST | `/protection/policies/life` |
| `updateLifePolicy(id, data)` | PUT | `/protection/policies/life/{id}` |
| `deleteLifePolicy(id)` | DELETE | `/protection/policies/life/{id}` |
| `createCriticalIllnessPolicy(data)` | POST | `/protection/policies/critical-illness` |
| `updateCriticalIllnessPolicy(id, data)` | PUT | `/protection/policies/critical-illness/{id}` |
| `deleteCriticalIllnessPolicy(id)` | DELETE | `/protection/policies/critical-illness/{id}` |
| `createIncomeProtectionPolicy(data)` | POST | `/protection/policies/income-protection` |
| `updateIncomeProtectionPolicy(id, data)` | PUT | `/protection/policies/income-protection/{id}` |
| `deleteIncomeProtectionPolicy(id)` | DELETE | `/protection/policies/income-protection/{id}` |
| `createDisabilityPolicy(data)` | POST | `/protection/policies/disability` |
| `updateDisabilityPolicy(id, data)` | PUT | `/protection/policies/disability/{id}` |
| `deleteDisabilityPolicy(id)` | DELETE | `/protection/policies/disability/{id}` |
| `createSicknessIllnessPolicy(data)` | POST | `/protection/policies/sickness-illness` |
| `updateSicknessIllnessPolicy(id, data)` | PUT | `/protection/policies/sickness-illness/{id}` |
| `deleteSicknessIllnessPolicy(id)` | DELETE | `/protection/policies/sickness-illness/{id}` |

---

## 10. Frontend Components

### Views (2)

| View | File | Purpose |
|---|---|---|
| `ProtectionDashboard` | `resources/js/views/Protection/ProtectionDashboard.vue` | Main entry point. Shows `ProfileCompletenessAlert`, delegates to `CurrentSituation`, hosts `PolicyFormModal` |
| `ComprehensiveProtectionPlan` | `resources/js/views/Protection/ComprehensiveProtectionPlan.vue` | Full plan document view with PDF download, gradient header, multi-section layout |

### Components (15)

| Component | File | Purpose |
|---|---|---|
| `CurrentSituation` | `components/Protection/CurrentSituation.vue` | Main policy listing. Shows "No Protection" notice when `has_no_policies` or no policies exist. Lists policies with filters. Emits `add-policy` and `edit-policy` events |
| `PolicyFormModal` | `components/Protection/PolicyFormModal.vue` | Unified modal for all 5 policy types. Policy type selector shows secondary `life_policy_type` dropdown for life insurance. Uses `@submit.prevent` + `$emit('save')` pattern |
| `PolicyCard` | `components/Protection/PolicyCard.vue` | Individual policy summary card |
| `PolicyDetail` | `components/Protection/PolicyDetail.vue` | Routable detailed policy view (`/protection/policy/:policyType/:id`) |
| `PolicyDetails` | `components/Protection/PolicyDetails.vue` | Policy details display component (used within cards/detail views) |
| `GapAnalysis` | `components/Protection/GapAnalysis.vue` | Gap analysis visualisation and breakdown |
| `Recommendations` | `components/Protection/Recommendations.vue` | Recommendation list container |
| `RecommendationCard` | `components/Protection/RecommendationCard.vue` | Individual recommendation display |
| `CoverageAdequacyGauge` | `components/Protection/CoverageAdequacyGauge.vue` | Adequacy score gauge (0-100) |
| `CoverageGapChart` | `components/Protection/CoverageGapChart.vue` | Visual chart of coverage vs needs |
| `CoverageTimelineChart` | `components/Protection/CoverageTimelineChart.vue` | Timeline of coverage over years |
| `PremiumBreakdownChart` | `components/Protection/PremiumBreakdownChart.vue` | Premium allocation by policy type |
| `ProtectionOverviewCard` | `components/Protection/ProtectionOverviewCard.vue` | Summary card for dashboard integration |
| `ScenarioBuilder` | `components/Protection/ScenarioBuilder.vue` | What-if scenario configuration UI |
| `WhatIfScenarios` | `components/Protection/WhatIfScenarios.vue` | Scenario results display |

### Key UI Patterns

- **"No Protection" state:** Shows informational notice with "Why Protection is Important" when user has no policies. Offers "View Gap Analysis" and "I Have Protection to Add" buttons plus "I currently have no protection policies" checkbox
- **`v-preview-disabled` directive:** Used on add/edit/checkbox elements to disable write operations in preview mode
- **PDF generation:** ComprehensiveProtectionPlan includes `PrintHeader`, `pdf-header` class, and `downloadPDF` method for generating downloadable plan documents

---

## 11. Frontend Routing

**File:** `resources/js/router/index.js`

| Route | Name | Component | Auth |
|---|---|---|---|
| `/protection` | `Protection` | `ProtectionDashboard` | `requiresAuth: true` |
| `/protection/policy/:policyType/:id` | `PolicyDetail` | `PolicyDetail` | `requiresAuth: true` |
| `/protection-plan` | `ComprehensiveProtectionPlan` | `ComprehensiveProtectionPlan` | `requiresAuth: true` |
| `/preview/protection` | `PreviewProtection` | `ProtectionDashboard` | `public: true, previewMode: true` |

**Breadcrumbs:**
- Protection: Home > Protection
- PolicyDetail: Home > Protection > Policy Details
- ComprehensiveProtectionPlan: Home > Comprehensive Protection Plan

**Module map entry:** `/protection` maps to `'protection'` (used for profile completeness routing).

---

## 12. Cross-Module Integration

### CoordinatingAgent

**File:** `app/Agents/CoordinatingAgent.php`

- Injects `ProtectionAgent` and calls `$this->protectionAgent->analyze($userId)` as part of holistic cross-module analysis
- Module priority weight: **80** (highest of all modules; savings=75, retirement=70, investment=60)
- Handles `protection_vs_savings_conflict` via `ConflictResolver::resolveProtectionVsSavings()`
- Maps `'protection'` category to `'protection'` in module-to-category mapping

### EstateAgent

**File:** `app/Agents/EstateAgent.php`

- References protection in `step3ExistingLifeCover()` for Letter to Spouse
- Currently uses a TODO placeholder: `$existingCover = 0; // Would be populated from Protection module`
- Plans to query actual life insurance from Protection module in future

### Spouse Income Integration

- `CoverageGapAnalyzer::calculateProtectionNeeds()` checks `$user->hasAcceptedSpousePermission()`
- If accepted, fetches spouse's income and includes as `continuing_income` (reduces protection need)
- Spouse gross income that continues after user's death reduces the insurance gap
- If spouse earns more than user, human capital need = 0

### Debt Integration

- `CoverageGapAnalyzer::calculateDebtProtectionNeed()` can fall back to live queries:
  - `$user->mortgages()->sum('outstanding_balance')` (from Net Worth module)
  - `$user->liabilities()->sum('current_balance')` (from Net Worth module)

---

## 13. Profile Completeness Integration

**File:** `app/Services/UserProfile/ProfileCompletenessChecker.php`

The `protection_plans` check appears in multiple module completeness definitions with `required: true` and `priority: 'high'`.

### `hasProtectionPlans(User $user): bool`

1. Returns `false` if no `ProtectionProfile` exists
2. Returns `true` if `profile->has_no_policies` is checked (user explicitly opted out)
3. Otherwise returns `true` if user has at least one policy across any of the 5 types

This means the protection profile is considered "complete" if:
- User has added at least one policy, OR
- User has checked "I have no protection policies"

---

## 14. Seeder Data

**File:** `database/seeders/PreviewUserSeeder.php`

Persona data loaded from `resources/js/data/personas/{persona_id}.json`.

### Seeder Methods

- `createLifeInsurancePolicies($user, $spouse, $data['life_insurance_policies'] ?? [])` - maps `provider_name` to `provider`, `policy_reference` to `policy_number`
- `createCriticalIllnessPolicies($user, $spouse, $data['critical_illness_policies'] ?? [])` - same field mapping
- `createIncomeProtectionPolicies($user, $spouse, $data['income_protection_policies'] ?? [])` - maps `monthly_benefit` to `benefit_amount`

**Note:** Disability and Sickness/Illness policies are NOT seeded via PreviewUserSeeder.

### Persona Protection Data

| Persona | Life Insurance | Critical Illness | Income Protection |
|---|---|---|---|
| peak_earners (David Mitchell) | 1 policy: Vitality Level Term £500k, £85/mo, in trust | 1 policy: L&G Standalone £200k, £125/mo | None |
| young_family (James Carter) | 1 policy: L&G Level Term £350k, £32/mo, NOT in trust | None | None |
| widow (Margaret Thompson) | TBC | TBC | TBC |
| entrepreneur (Alex Chen) | TBC | TBC | TBC |
| young_saver (John Morgan) | TBC | TBC | TBC |
| retired_couple (Robert Williams) | TBC | TBC | TBC |

---

## 15. API Routing

**File:** `routes/api.php` (lines 318-367)

All routes under `auth:sanctum` middleware and `protection` prefix:

```
GET    /api/protection                              -> index
POST   /api/protection/analyze                      -> analyze
GET    /api/protection/recommendations              -> recommendations
POST   /api/protection/scenarios                    -> scenarios
GET    /api/protection/comprehensive-plan           -> getComprehensiveProtectionPlan
POST   /api/protection/profile                      -> storeProfile
PATCH  /api/protection/profile/has-no-policies      -> updateHasNoPolicies
POST   /api/protection/policies/life                -> storeLifePolicy
PUT    /api/protection/policies/life/{id}           -> updateLifePolicy
DELETE /api/protection/policies/life/{id}           -> destroyLifePolicy
POST   /api/protection/policies/critical-illness    -> storeCriticalIllnessPolicy
PUT    /api/protection/policies/critical-illness/{id}
DELETE /api/protection/policies/critical-illness/{id}
POST   /api/protection/policies/income-protection   -> storeIncomeProtectionPolicy
PUT    /api/protection/policies/income-protection/{id}
DELETE /api/protection/policies/income-protection/{id}
POST   /api/protection/policies/disability          -> storeDisabilityPolicy
PUT    /api/protection/policies/disability/{id}
DELETE /api/protection/policies/disability/{id}
POST   /api/protection/policies/sickness-illness    -> storeSicknessIllnessPolicy
PUT    /api/protection/policies/sickness-illness/{id}
DELETE /api/protection/policies/sickness-illness/{id}
```

---

## 16. Key Constants and Business Logic

### Protection Needs Calculation

| Concept | Value | Source |
|---|---|---|
| Life cover capital (sustainable drawdown) | Annual income need / 0.047 (4.7% withdrawal rate) | `CoverageGapAnalyzer::calculateHumanCapital()` |
| Education cost per child | £9,000/year until age 21 | `calculateEducationFunding()` |
| Final expenses | £7,500 (fixed) | `calculateFinalExpenses()` |
| Income protection need | 60% of gross income | `calculateProtectionNeeds()` |

### Premium Estimation

| Type | Base Rate | Smoker | Age 40-50 | Age 50+ |
|---|---|---|---|---|
| Life insurance | £0.50 per £1,000 SA/year | +50% | +20% | +50% |
| Critical illness | Life x 2.5 | +50% | +20% | +50% |
| Income protection | 2% of annual benefit | +30% | - | - |
| Debt protection | Life x 0.8 | Same | Same | Same |
| Family income benefit | Life x 0.7 | Same | Same | Same |

### Thresholds and Triggers

| Threshold | Value | Context |
|---|---|---|
| Life recommendation trigger | Gap > £10,000 AND has dependants | RecommendationEngine |
| Premium optimisation trigger | Total premiums > 5% of annual income | RecommendationEngine |
| Safe withdrawal rate (death scenario) | 3% per annum | ScenarioBuilder |
| Emergency fund (CI scenario) | 6 months expenditure | ScenarioBuilder |
| CI need (comprehensive plan) | 3x gross annual income | ComprehensiveProtectionPlanService |
| IP need (comprehensive plan) | 70% of net monthly income | ComprehensiveProtectionPlanService |

### Adequacy Scoring

| Score Range | Rating | Colour |
|---|---|---|
| 80-100 | Excellent | green |
| 60-79 | Good | blue |
| 40-59 | Fair | orange |
| 0-39 | Critical | red |

### Scenario Adequacy Ratings

| Type | Excellent | Good | Fair | Poor |
|---|---|---|---|---|
| Death/CI (years of support) | 10+ | 5-9 | 2-4 | < 2 |
| Income Protection (replacement %) | 60%+ | 50-59% | 40-49% | < 40% |

---

## 17. Known Issues and Limitations

### Placeholder Scores
- `critical_illness_score` and `income_protection_score` in AdequacyScorer are placeholders returning 0
- Only `life_insurance_score` is calculated from actual data

### EstateAgent Integration Gap
- `EstateAgent::step3ExistingLifeCover()` has a TODO to pull actual life insurance data from the Protection module
- Currently estimates `$existingCover = 0` rather than querying policies

### No API Resources
- Controller returns raw Eloquent models rather than API Resources
- No transformation layer for response structure consistency

### Unseeded Policy Types
- Disability and Sickness/Illness policies are not seeded in PreviewUserSeeder
- Only Life Insurance, Critical Illness, and Income Protection are seeded from persona JSON

### No Spouse Policies
- Policies belong to individual users via `user_id`
- No joint policy concept (unlike investment/property modules)
- Spouse's policies would need to be entered under the spouse's user account

### Life Insurance Only Has Auditable Trait
- `LifeInsurancePolicy` uses the `Auditable` trait; the other 4 policy models do not
- Audit trail inconsistency across policy types

### StoreLifePolicyRequest Does Not Extend BasePolicyRequest
- `StoreLifePolicyRequest` and `StoreCriticalIllnessPolicyRequest` extend `FormRequest` directly
- Other Store requests extend `BasePolicyRequest`
- This means life/CI validation rules are duplicated rather than shared

---

## 18. Coverage Gap Allocation Logic

The coverage gap calculation in `CoverageGapAnalyzer::calculateCoverageGap()` uses a priority allocation system to determine how existing life insurance coverage is applied against multiple needs:

### Allocation Priority Order

1. **Debt Protection** (highest priority) - Mortgage balance + other debts
2. **Human Capital** - Sustainable drawdown capital (annual income need / 0.047)
3. **Final Expenses** - Fixed £7,500
4. **Education Funding** (lowest priority) - £9,000/year per child until 21

### How It Works

```
Total Life Coverage (sum_assured across all life policies)
  |
  v
[1] Debt Protection Need
  - Coverage allocated = min(remaining_coverage, debt_need)
  - Remaining coverage decreases
  - Gap = debt_need - allocated
  |
  v
[2] Human Capital Need
  - Coverage allocated = min(remaining_coverage, human_capital_need)
  - Gap = human_capital - allocated
  |
  v
[3] Final Expenses
  - Coverage allocated = min(remaining_coverage, £7,500)
  - Gap = £7,500 - allocated
  |
  v
[4] Education Funding
  - Coverage allocated = min(remaining_coverage, education_need)
  - Gap = education_need - allocated
```

The `total_gap` is the sum of all individual gaps. The `coverage_percentage` is `((total_need - total_gap) / total_need) * 100`.

This priority system means that if a user has limited life coverage, debts are covered first, and education funding has the largest gap. This reflects the financial planning principle that secured debts (mortgages) should be the primary insurance target.

### Income-Based Coverage (Separate Track)

Income Protection, Disability, and Sickness/Illness coverage is tracked separately as `total_income_coverage` and compared against the `income_protection_need` (60% of gross income). This does not participate in the priority allocation above.
