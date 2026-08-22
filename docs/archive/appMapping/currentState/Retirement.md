# Retirement Module Documentation

> Comprehensive documentation of the Retirement module across the entire Fynla application stack.
> Generated: 2026-02-18

---

## 1. System Overview

The Retirement module is the largest and most complex module in Fynla. It covers pension accumulation, income projection, tax-optimised drawdown planning, and strategy recommendations across three pension types and multiple non-pension income sources.

### Core Components

| Layer | Component | Lines | Purpose |
|-------|-----------|-------|---------|
| Models | `DCPension`, `DBPension`, `StatePension`, `RetirementProfile` | ~255 | 4 Eloquent models across 4 tables |
| Controller | `RetirementController` | 689 | 17+ API endpoints |
| Agent | `RetirementAgent` | 461 | Orchestrator with caching |
| Services | 9 services in `app/Services/Retirement/` | ~3,800 | Projections, income, strategies, allowances |
| Vuex Store | `retirement.js` | 782 | Frontend state management |
| API Service | `retirementService.js` | 185 | 20+ API wrappers |
| Vue Components | 20 components | ~4,000 | Tabs, charts, forms, cards |
| Validation | 5 Form Request classes | ~200 | Input validation |

### Architecture Flow

```
                    ┌─────────────────────────────────────┐
                    │         4 Database Tables            │
                    │  dc_pensions, db_pensions,           │
                    │  state_pensions, retirement_profiles  │
                    └──────────────┬──────────────────────┘
                                   │
                    ┌──────────────┴──────────────────────┐
                    │         4 Eloquent Models            │
                    │  DCPension, DBPension,               │
                    │  StatePension, RetirementProfile     │
                    └──────────────┬──────────────────────┘
                                   │
          ┌────────────────────────┼─────────────────────────┐
          │                        │                         │
   RetirementAgent          9 Services                RetirementController
   (orchestrator)        (domain logic)                (17+ endpoints)
          │                        │                         │
     ┌────┴─────┐          ┌──────┴──────┐           ┌──────┴──────┐
     │ analyze  │          │ Projection  │           │ REST API    │
     │ recommend│          │ Income      │           │ CRUD        │
     │ scenarios│          │ Strategy    │           │ Analysis    │
     │ portfolio│          │ Allowance   │           │ Income      │
     └──────────┘          └─────────────┘           └──────────────┘
                                   │
                    ┌──────────────┴──────────────────────┐
                    │       Frontend (Vue.js)              │
                    │  Vuex Store → API Service → 20 Vue  │
                    │  components (tabs, charts, forms)    │
                    └─────────────────────────────────────┘
```

---

## 2. Database Schema

### 2.1 `dc_pensions` Table

Defined Contribution pensions (workplace, SIPP, personal).

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `user_id` | bigint FK | Owner of the pension |
| `scheme_name` | varchar | Name of the pension scheme |
| `scheme_type` | enum | `workplace`, `sipp`, `personal` |
| `provider` | varchar | Pension provider name |
| `pension_type` | enum | `occupational`, `sipp`, `personal`, `stakeholder` |
| `member_number` | varchar | Scheme member number |
| `current_fund_value` | decimal(12,2) | Current total fund value |
| `annual_salary` | decimal(12,2) | Pensionable salary (for % contributions) |
| `employee_contribution_percent` | decimal(5,2) | Employee contribution rate |
| `employer_contribution_percent` | decimal(5,2) | Employer contribution rate |
| `employer_matching_limit` | decimal(5,2) | Max employer will match |
| `monthly_contribution_amount` | decimal(12,2) | Fixed monthly contribution (alternative to %) |
| `lump_sum_contribution` | decimal(12,2) | One-off additional contribution |
| `investment_strategy` | varchar | Investment strategy description |
| `platform_fee_percent` | decimal(5,4) | Annual platform charge |
| `retirement_age` | int | Target retirement age for this pension (55-75) |
| `expected_return_percent` | decimal(5,2) | Expected annual return |
| `projected_value_at_retirement` | decimal(15,2) | Pre-calculated projected value |
| `risk_preference` | enum | `low`, `lower_medium`, `medium`, `upper_medium`, `high` (per-pension override) |
| `has_custom_risk` | boolean | Whether this pension uses custom risk (vs user default) |
| `beneficiary_id` | bigint FK nullable | Linked Fynla user as beneficiary |
| `beneficiary_name` | varchar nullable | Free-text beneficiary name |
| `timestamps` | | created_at, updated_at |

**Key behaviour**: Contribution calculation priority: `monthly_contribution_amount * 12` takes precedence over `annual_salary * (employee% + employer%)`.

### 2.2 `db_pensions` Table

Defined Benefit pensions (final salary, career average, public sector). Captured for projection only -- no DB-to-DC transfer advice is provided.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `user_id` | bigint FK | Owner |
| `scheme_name` | varchar | Scheme name |
| `scheme_type` | enum | `final_salary`, `career_average`, `public_sector` |
| `accrued_annual_pension` | decimal(12,2) | Current accrued annual pension |
| `pensionable_service_years` | decimal(5,2) | Years of pensionable service |
| `pensionable_salary` | decimal(12,2) | Pensionable salary |
| `normal_retirement_age` | int | Scheme's normal retirement age |
| `revaluation_method` | varchar | How pension is revalued (not yet applied in projections) |
| `spouse_pension_percent` | decimal(5,2) | Percentage payable to spouse on death |
| `lump_sum_entitlement` | decimal(12,2) | Tax-free lump sum option |
| `inflation_protection` | enum | `cpi`, `rpi`, `fixed`, `none` |
| `timestamps` | | |

### 2.3 `state_pensions` Table

UK State Pension with National Insurance tracking.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `user_id` | bigint FK | Owner (HasOne relationship) |
| `ni_years_completed` | int | NI qualifying years completed (0-50) |
| `ni_years_required` | int | NI years required for full pension |
| `state_pension_forecast_annual` | decimal(12,2) | Annual forecast from gov.uk |
| `state_pension_age` | int | State pension age (60-70) |
| `already_receiving` | boolean | Currently receiving state pension |
| `ni_gaps` | JSON | NI contribution gap years |
| `gap_fill_cost` | decimal(12,2) | Cost to fill NI gaps |
| `timestamps` | | |

### 2.4 `retirement_profiles` Table

User's retirement planning preferences and targets.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `user_id` | bigint FK | Owner (HasOne relationship) |
| `current_age` | int | Current age |
| `target_retirement_age` | int | Target retirement age |
| `current_annual_salary` | decimal(12,2) | Current gross salary |
| `target_retirement_income` | decimal(12,2) | Desired annual retirement income |
| `essential_expenditure` | decimal(12,2) | Essential expenditure in retirement |
| `lifestyle_expenditure` | decimal(12,2) | Lifestyle expenditure in retirement |
| `life_expectancy` | int | User's life expectancy |
| `spouse_life_expectancy` | int | Spouse's life expectancy |
| `risk_tolerance` | enum | **DEPRECATED** -- use `RiskPreferenceService` instead |
| `timestamps` | | |

---

## 3. Models

### 3.1 `DCPension` (`app/Models/DCPension.php`, 92 lines)

```
Traits: Auditable, HasFactory
Table: dc_pensions
Relationships:
  - user() → BelongsTo User
  - beneficiary() → BelongsTo User (via beneficiary_id)
  - holdings() → MorphMany Holding (polymorphic, shared with InvestmentAccount)
```

The `holdings()` MorphMany relationship allows DC pensions (specifically SIPPs) to have investment holdings tracked at the fund level, sharing the same `Holding` model used by `InvestmentAccount`. This enables portfolio analysis, fee analysis, and diversification checking.

### 3.2 `DBPension` (`app/Models/DBPension.php`, 55 lines)

```
Traits: Auditable, HasFactory
Table: db_pensions
Relationships:
  - user() → BelongsTo User
```

No holdings relationship -- DB pensions provide a fixed annual income and are not investable by the user.

### 3.3 `StatePension` (`app/Models/StatePension.php`, 51 lines)

```
Traits: Auditable, HasFactory
Table: state_pensions
Relationships:
  - user() → BelongsTo User
Casts:
  - ni_gaps → array (JSON)
  - already_receiving → boolean
```

### 3.4 `RetirementProfile` (`app/Models/RetirementProfile.php`, 58 lines)

```
Traits: HasFactory (no Auditable)
Table: retirement_profiles
Relationships:
  - user() → BelongsTo User
```

**Note**: `risk_tolerance` field is **DEPRECATED**. The docblock explicitly states: "Use `RiskPreferenceService::getRiskProfile()` for user's main risk level."

### 3.5 User Model Relationships (`app/Models/User.php`)

```php
dcPensions()       → HasMany DCPension
dbPensions()       → HasMany DBPension
statePension()     → HasOne StatePension
retirementProfile() → HasOne RetirementProfile
```

---

## 4. Controller

### `RetirementController` (`app/Http/Controllers/Api/RetirementController.php`, 689 lines)

**Dependencies (DI):**
- `RetirementAgent` -- orchestration
- `AnnualAllowanceChecker` -- AA calculations
- `RetirementProjectionService` -- Monte Carlo projections
- `RetirementStrategyService` -- strategy recommendations
- `RetirementIncomeService` -- tax-optimised drawdown
- `DiversificationAnalyzer` -- from Investment module
- `RequiredCapitalCalculator` -- capital adequacy

**Endpoints:**

| Method | Route | Handler | Purpose |
|--------|-------|---------|---------|
| GET | `/retirement` | `index` | All retirement data (profile, DC, DB, state) |
| GET | `/retirement/projections` | `getProjections` | Monte Carlo pension pot + income drawdown |
| GET | `/retirement/required-capital` | `getRequiredCapital` | Required vs projected capital |
| GET | `/retirement/dc-pensions/{id}/projections` | `getDCPensionProjection` | Individual DC pension Monte Carlo |
| POST | `/retirement/analyze` | `analyze` | Full retirement analysis via agent |
| GET | `/retirement/recommendations` | `recommendations` | AI recommendations |
| POST | `/retirement/scenarios` | `scenarios` | Scenario planning |
| GET | `/retirement/annual-allowance/{taxYear}` | `checkAnnualAllowance` | AA check for tax year |
| GET | `/retirement/portfolio-analysis` | `analyzeDCPensionPortfolio` | DC pension portfolio analysis |
| GET | `/retirement/portfolio-analysis/{id}` | `analyzeDCPensionPortfolio` | Individual DC portfolio |
| GET | `/retirement/strategies` | `getStrategies` | Strategy recommendations |
| GET | `/retirement/strategies/impact` | `calculateStrategyImpact` | Strategy impact calculation |
| GET | `/retirement/income` | `getRetirementIncome` | Tax-optimised income config |
| POST | `/retirement/income/calculate` | `calculateRetirementIncome` | Custom income scenario |
| GET | `/retirement/income/accounts` | `getIncomeAccounts` | Available income accounts |
| POST | `/retirement/pensions/dc` | `storeDCPension` | Create DC pension |
| PUT | `/retirement/pensions/dc/{id}` | `updateDCPension` | Update DC pension |
| DELETE | `/retirement/pensions/dc/{id}` | `destroyDCPension` | Delete DC pension |
| POST | `/retirement/pensions/db` | `storeDBPension` | Create DB pension |
| PUT | `/retirement/pensions/db/{id}` | `updateDBPension` | Update DB pension |
| DELETE | `/retirement/pensions/db/{id}` | `destroyDBPension` | Delete DB pension |
| POST | `/retirement/state-pension` | `updateStatePension` | Create/update state pension |

**Key behaviours:**

1. **Risk auto-assignment**: `storeDCPension()` auto-assigns `risk_preference` from the user's `RiskProfile` when creating a new DC pension (unless the user has set a custom risk).

2. **Cache invalidation**: Every pension CRUD operation calls `invalidateRetirementCache()`, which clears:
   - `retirement_analysis_{userId}` (agent cache)
   - Individual DC pension portfolio caches

3. **Response flattening**: `analyze()` flattens the nested agent response to match frontend expectations (removes nesting levels).

4. **Income accounts**: `getIncomeAccounts()` passes the 80% Monte Carlo projected pension pot value to the income service.

### DC Pension Holdings Sub-Routes

| Method | Route | Controller |
|--------|-------|------------|
| GET | `/retirement/pensions/dc/{id}/holdings` | `DCPensionHoldingsController@index` |
| POST | `/retirement/pensions/dc/{id}/holdings` | `DCPensionHoldingsController@store` |
| PUT | `/retirement/pensions/dc/{id}/holdings/{holdingId}` | `DCPensionHoldingsController@update` |
| DELETE | `/retirement/pensions/dc/{id}/holdings/{holdingId}` | `DCPensionHoldingsController@destroy` |
| POST | `/retirement/pensions/dc/{id}/holdings/bulk-update` | `DCPensionHoldingsController@bulkUpdate` |
| GET | `/retirement/pensions/dc/{id}/diversification` | `RetirementController@getDCPensionDiversification` |

---

## 5. Agent

### `RetirementAgent` (`app/Agents/RetirementAgent.php`, 461 lines)

Extends `BaseAgent`. Cache TTL: **1 hour**.

**Dependencies (DI):**
- `PensionProjector` -- FV calculations
- `AnnualAllowanceChecker` -- AA
- `ContributionOptimizer` -- contribution advice
- `DecumulationPlanner` -- drawdown planning
- `PensionPortfolioAnalyzer` -- portfolio analysis
- Plus 5 shared Investment module services: `PortfolioAnalyzer`, `MonteCarloSimulator`, `AssetAllocationOptimizer`, `FeeAnalyzer`, `TaxEfficiencyCalculator`

**Key methods:**

#### `analyze(int $userId): array`
- Cached for 1 hour with key `retirement_analysis_{userId}`
- Loads all pension data (DC, DB, State, Profile)
- Calls `PensionProjector` for each pension type
- Calculates income gap: `target_retirement_income - projected_total_income`
- Checks annual allowance
- Returns summary with `projected_income`, `target_income`, `income_gap`, `years_to_retirement`

#### `generateRecommendations(int $userId): array`
- Builds recommendations based on:
  - Income gap (if projected < target)
  - Contribution increase opportunities (employer match)
  - AA excess warnings
  - NI gap filling opportunities
  - Retirement age adjustment suggestion

#### `buildScenarios(int $userId): array`
- 4 scenario types:
  1. **Current trajectory** -- no changes
  2. **Increased contributions** -- +2% employee contribution
  3. **Later retirement** -- +3 years
  4. **Lower target income** -- 80% of current target

#### `analyzeDCPensionPortfolio(int $userId, ?int $pensionId): array`
- Delegates to `PensionPortfolioAnalyzer`
- Cached per pension or aggregated
- Returns risk metrics, fee analysis, asset allocation vs target

---

## 6. Services

### 6.1 `PensionProjector` (`app/Services/Retirement/PensionProjector.php`, 273 lines)

Core pension projection engine.

**Constants:**
- `DEFAULT_GROWTH_RATE`: 5% (fallback when no risk profile)
- `DEFAULT_RETIREMENT_AGE`: 67

**Key methods:**

| Method | Formula/Logic |
|--------|--------------|
| `projectDCPension()` | `FV = PV * (1+r)^n + PMT * [((1+r)^n - 1) / r]`, net of platform fees |
| `projectDBPension()` | Returns `accrued_annual_pension` (conservative, no revaluation applied) |
| `projectStatePension()` | Uses forecast if available, otherwise calculates from NI years (full: £11,502 p.a.) |
| `projectTotalRetirementIncome()` | Aggregates all sources; applies 4% withdrawal rate on DC pot |
| `getGrowthRateForPension()` | Priority: pension's custom risk -> user's main risk -> default 5% |
| `calculateAnnualContribution()` | Priority: `monthly_contribution_amount * 12` -> `salary * (employee% + employer%)` |

**Risk-driven growth rates**: Uses `RiskPreferenceService` to get expected returns based on risk level. Per-pension risk override is supported via the `has_custom_risk` / `risk_preference` fields.

### 6.2 `RetirementProjectionService` (`app/Services/Retirement/RetirementProjectionService.php`, 640 lines)

Monte Carlo simulation engine for pension pot projections.

**Constants:**
- `SUSTAINABLE_WITHDRAWAL_RATE`: 4.7%
- `INFLATION_RATE`: 2%
- `TARGET_INCOME_PERCENT`: 75% of current net income
- `END_AGE`: 100
- `MONTE_CARLO_ITERATIONS`: 1,000

**Key methods:**

| Method | Purpose |
|--------|---------|
| `getProjections()` | Complete projections: pot growth + income drawdown + target drawdown |
| `projectPensionPot()` | Aggregates all DC pensions, runs 1,000 Monte Carlo iterations, extracts probability bands (10th, 20th, 50th, 80th, 90th percentile), blends for early years |
| `projectIncomeDrawdown()` | Year-by-year from retirement to 100 with 4.7% sustainable withdrawal, calculates "on-track" status |
| `projectTargetIncomeDrawdown()` | Draws full target income until fund depletes |
| `calculateRetirementProbability()` | Income ratio + longevity bonus |
| `projectIndividualDCPension()` | Monte Carlo for a single DC pension |

**On-track status thresholds:**

| Probability | Status |
|-------------|--------|
| >= 90% | Excellent |
| >= 80% | On Track |
| >= 60% | Needs Attention |
| >= 35% | Off Track |
| >= 15% | Significantly Off Track |
| < 15% | Critical |

### 6.3 `RetirementIncomeService` (`app/Services/Retirement/RetirementIncomeService.php`, ~1,200 lines)

The most complex service in the module. Handles tax-optimised drawdown planning.

**Constants:**
- `DEFAULT_RETIREMENT_AGE`: 68
- `DEFAULT_GROWTH_RATE`: 4%
- `DEFAULT_INFLATION_RATE`: 2%
- `PROJECTION_END_AGE`: 100
- `ISA_WITHDRAWAL_RATE`: 4.7%
- `BOND_TAX_FREE_RATE`: 5%
- `GIA_WITHDRAWAL_RATE`: 4%

**Key methods:**

#### `getRetirementIncomeConfig(int $userId, bool $includeSpouse): array`
Full pipeline:
1. Get projected pension pot value (80% Monte Carlo confidence)
2. Get target income from `RequiredCapitalCalculator`
3. Check state pension status (show gov.uk link if missing)
4. Check if target income depletes funds before age 100
5. If depletes early, calculate sustainable income to last to 100
6. Optimise allocations for that income
7. Account for state pension timing

#### `getAvailableAccounts(int $userId): array`
Combines income sources from multiple modules:

| Source | Module | Calculation |
|--------|--------|-------------|
| Pension Pot (PCLS 25% + Drawdown 75%) | Retirement | 80% Monte Carlo projected value |
| Defined Benefit Pensions | Retirement | Accrued annual pension |
| State Pension | Retirement | Forecast or NI-based calculation |
| Cash ISAs | Savings | Projected at 2% growth, `include_in_retirement` filter |
| Investment ISAs (Stocks & Shares) | Investment | Monte Carlo 80%, `include_in_retirement` filter |
| Onshore/Offshore Bonds | Investment | 5% tax-free cumulative allowance |
| General Investment Accounts | Investment | Monte Carlo 80%, `include_in_retirement` filter |
| Non-ISA Savings | Savings | Projected at 2% growth, `include_in_retirement` filter |

#### `calculateTaxBreakdown(): array`
- Sorts income by tax efficiency
- Uses `TaxBandTracker` for stacking tax calculations
- Returns per-source breakdown with band usage

#### `projectFundDepletion(): array`
Year-by-year simulation with priority-based withdrawal order:
1. Bond 5% tax-free allowance
2. PCLS (Pension Commencement Lump Sum)
3. ISA withdrawals
4. Pension Drawdown
5. GIA withdrawals
6. Savings

Per-year tax calculation with Personal Allowance allocated to State Pension / DB Pension first.

#### `calculateSustainableIncome(): float`
Binary search (precision: £100) for max income that doesn't deplete funds before age 100.

### 6.4 `RequiredCapitalCalculator` (`app/Services/Retirement/RequiredCapitalCalculator.php`, 390 lines)

Calculates the capital needed to fund retirement.

**Constants:**
- Withdrawal rate: 4.7%
- Fund fees: 1%
- Inflation: 2.5%
- Compounding: Quarterly
- Default target: 75% of (gross income - pension contributions)

**Key methods:**

| Method | Purpose |
|--------|---------|
| `calculate()` | Full capital adequacy calculation with year-by-year table |
| `getRequiredIncome()` | `retirement_profile.target_retirement_income` OR 75% of (gross - contributions) |
| `calculateFutureValueWithContributions()` | Full FV formula with periodic contributions |
| `calculatePresentValue()` | `PV = FV / (1 + r)^n` |

**Retirement age resolution chain:**
1. `user.target_retirement_age`
2. `retirementProfile.target_retirement_age`
3. First DC pension's `retirement_age`
4. Default: 68

Uses `AssumptionsService` for user-configurable rates (overrides defaults).

### 6.5 `RetirementStrategyService` (`app/Services/Retirement/RetirementStrategyService.php`, ~300 lines)

Generates and evaluates improvement strategies.

**4 prioritised strategies (cumulative chaining):**

| Priority | Strategy | Description |
|----------|----------|-------------|
| 1 | Employer Match | Increase contributions to capture full employer match |
| 2 | Increase Contributions | Increase employee contributions (affordability checked) |
| 3 | Retirement Age | Delay retirement by 1-5 years |
| 4 | Income Target | Reduce target retirement income |

**On-track definition**: At 95% probability OR if achievable net income from ALL assets (not just pensions) meets target.

Each strategy considers:
- Cumulative impact from prior strategies
- Affordability (disposable income check)
- Annual allowance limits
- Capital position (all assets, not just pensions)

### 6.6 `AnnualAllowanceChecker` (`app/Services/Retirement/AnnualAllowanceChecker.php`, 229 lines)

Checks pension contributions against HMRC annual allowance limits.

**Key features:**
- Uses `TaxConfigService` for all rates (not hardcoded)
- Tapering: £1 reduction per £2 over adjusted income threshold, minimum £10,000
- Carry forward: Simplified implementation (always returns 1 year's allowance)
- MPAA check: Placeholder (always returns "not triggered")

**Method: `checkAnnualAllowance(int $userId, string $taxYear): array`**
- Totals all DC pension contributions (employee + employer)
- Checks tapering for high earners
- Calculates carry forward availability
- Returns excess amount and tax charge

### 6.7 `ContributionOptimizer` (`app/Services/Retirement/ContributionOptimizer.php`, 243 lines)

Optimises pension contributions for maximum benefit.

**Key features:**
- Employer match check (5% threshold)
- Required contribution calculation using PMT formula (4% withdrawal, 5% growth)
- Tax relief analysis (basic/higher/additional rates)

**Known vulnerability**: Tax bands are **HARDCODED** instead of using `TaxConfigService`. This should be refactored to use the centralised tax configuration.

### 6.8 `DecumulationPlanner` (`app/Services/Retirement/DecumulationPlanner.php`, 323 lines)

Models retirement income withdrawal strategies.

**Key methods:**

| Method | Purpose |
|--------|---------|
| `calculateSustainableWithdrawalRate()` | Tests 3%, 4%, 5% with portfolio survival simulation |
| `compareAnnuityVsDrawdown()` | Simplified annuity rates by age (4-7.5%), 85% reduction for spouse |
| `calculatePCLSStrategy()` | 25% tax-free lump sum, 4% income from remainder |
| `modelIncomePhasing()` | 3 phases: pre-state pension, state pension age, 75+ |

### 6.9 `PensionPortfolioAnalyzer` (`app/Services/Retirement/PensionPortfolioAnalyzer.php`, 166 lines)

Analyses DC pension holdings using Investment module services.

**Delegates to:**
- `PortfolioAnalyzer` -- risk metrics, Sharpe ratio
- `AssetAllocationOptimizer` -- target allocation vs actual deviation

**Fee analysis:**
- Platform fees (pension level, `platform_fee_percent`)
- Fund OCF (holdings level, `ocf_percent`)
- Low-cost comparison benchmark: 0.20%

---

## 7. Validation Requests

### `StoreDCPensionRequest` (`app/Http/Requests/Retirement/StoreDCPensionRequest.php`)

| Field | Validation |
|-------|------------|
| `scheme_name` | required, string, max:255 |
| `scheme_type` | required, in:workplace,sipp,personal |
| `pension_type` | required, in:occupational,sipp,personal,stakeholder |
| `current_fund_value` | required, numeric, min:0 |
| `retirement_age` | nullable, integer, min:55, max:75 |
| `risk_preference` | nullable, in:low,lower_medium,medium,upper_medium,high |
| `beneficiary_id` | nullable, exists:users,id |

**Note**: Same request class is used for both store and update. On update, additionally checks that the pension belongs to the authenticated user.

### `StoreDBPensionRequest` (`app/Http/Requests/Retirement/StoreDBPensionRequest.php`)

| Field | Validation |
|-------|------------|
| `scheme_name` | required, string, max:255 |
| `scheme_type` | required, in:final_salary,career_average,public_sector |
| `accrued_annual_pension` | required, numeric, min:0 |
| `inflation_protection` | nullable, in:cpi,rpi,fixed,none |
| `normal_retirement_age` | nullable, integer, min:55, max:75 |
| `spouse_pension_percent` | nullable, numeric, min:0, max:100 |

### `UpdateStatePensionRequest` (`app/Http/Requests/Retirement/UpdateStatePensionRequest.php`)

| Field | Validation |
|-------|------------|
| `ni_years_completed` | nullable, integer, min:0, max:50 |
| `ni_years_required` | nullable, integer, min:0, max:50 |
| `state_pension_forecast_annual` | nullable, numeric, min:0 |
| `state_pension_age` | nullable, integer, min:60, max:70 |
| `already_receiving` | nullable, boolean |

---

## 8. Vuex Store

### `retirement.js` (`resources/js/store/modules/retirement.js`, 782 lines)

**State properties (20+):**

```javascript
{
  // Core data
  dcPensions: [],
  dbPensions: [],
  statePension: null,
  profile: null,

  // Analysis & recommendations
  analysis: null,
  recommendations: null,
  annualAllowance: null,
  scenarios: null,
  portfolioAnalysis: null,

  // Projections
  projections: null,
  strategies: null,
  strategyImpact: null,
  requiredCapital: null,

  // Income planning
  retirementIncome: null,
  incomeAccounts: null,
  incomeAllocations: {},

  // UI state
  includeSpouseAssets: false,
  customTargetIncome: null,
  activeTab: 'overview',

  // Loading states
  loading: false,
  projectionsLoading: false,
  // ... etc.
}
```

**Request deduplication**: Uses an `ongoingRequests` tracker to prevent duplicate concurrent API calls. When a request is already in flight, subsequent calls return the existing promise instead of firing a new request.

**Key patterns:**
- All pension CRUD actions dispatch `analyseRetirement` + `netWorth/refreshNetWorth` afterwards
- `toggleIncludedInvestment()` / `toggleIncludedCash()` persist the `include_in_retirement` flag via API, then refresh the relevant store
- Getters include: `totalPensionWealth` (DC only), `retirementReadinessScore`, `incomeGap`, portfolio analysis, required capital, retirement income

---

## 9. API Service

### `retirementService.js` (`resources/js/services/retirementService.js`, 185 lines)

20+ API endpoint wrappers:

| Method | Endpoint |
|--------|----------|
| `getRetirementData()` | GET `/retirement` |
| `analyzeRetirement()` | POST `/retirement/analyze` |
| `getRecommendations()` | GET `/retirement/recommendations` |
| `runScenario(data)` | POST `/retirement/scenarios` |
| `getAnnualAllowance(taxYear)` | GET `/retirement/annual-allowance/{taxYear}` |
| `getProjections()` | GET `/retirement/projections` |
| `getRequiredCapital()` | GET `/retirement/required-capital` |
| `getDCPensionProjection(id)` | GET `/retirement/dc-pensions/{id}/projections` |
| `getStrategies()` | GET `/retirement/strategies` |
| `calculateStrategyImpact(params)` | GET `/retirement/strategies/impact` |
| `storeDCPension(data)` | POST `/retirement/pensions/dc` |
| `updateDCPension(id, data)` | PUT `/retirement/pensions/dc/{id}` |
| `deleteDCPension(id)` | DELETE `/retirement/pensions/dc/{id}` |
| `storeDBPension(data)` | POST `/retirement/pensions/db` |
| `updateDBPension(id, data)` | PUT `/retirement/pensions/db/{id}` |
| `deleteDBPension(id)` | DELETE `/retirement/pensions/db/{id}` |
| `updateStatePension(data)` | POST `/retirement/state-pension` |
| `getRetirementIncome()` | GET `/retirement/income` |
| `calculateRetirementIncome(data)` | POST `/retirement/income/calculate` |
| `getIncomeAccounts()` | GET `/retirement/income/accounts` |

---

## 10. Frontend Components

### 10.1 Tab Components

| Component | File | Purpose |
|-----------|------|---------|
| `RetirementIncomeTab` | `components/Retirement/RetirementIncomeTab.vue` | Tax-optimised drawdown planning with income sliders, fund depletion chart, state pension status |
| `StrategiesTab` | `components/Retirement/StrategiesTab.vue` | "On Track" banner or strategy cards with probability and capital position |
| `CapitalAdequacyTab` | `components/Retirement/CapitalAdequacyTab.vue` | "Am I saving enough?" - Required vs projected capital, year-by-year table |
| `FutureValueTab` | `components/Retirement/FutureValueTab.vue` | Monte Carlo projection charts with probability bands |

### 10.2 Chart Components

| Component | Type | Purpose |
|-----------|------|---------|
| `PensionPotProjectionChart` | Area | Monte Carlo probability fan (10th-90th percentile) |
| `AccumulationChart` | Area | Pension pot accumulation over time |
| `IncomeDrawdownChart` | Bar | Year-by-year income vs target, colour-coded by adequacy |
| `IncomeProjectionChart` | Area (stacked) | DC + DB + State pension income projection |
| `FundDepletionChart` | Area | Remaining fund value by age |
| `TargetIncomeDrawdownChart` | Bar | Target income drawdown until fund depletion |

### 10.3 Form Components

| Component | Purpose |
|-----------|---------|
| `DCPensionForm` | Create/edit DC pension with contribution type toggle |
| `DBPensionForm` | Create/edit DB pension |
| `StatePensionForm` | State pension NI data entry |
| `UnifiedPensionForm` | Unified form for adding any pension type |

### 10.4 View Components (`resources/js/views/Retirement/`)

| View | Purpose |
|------|---------|
| `RetirementReadiness.vue` | Main pension dashboard -- pension cards, add/upload buttons, risk badges |
| `PensionDetail.vue` | Individual pension detail view (route: `/pension/:type/:id`) |
| `Projections.vue` | Monte Carlo projections page |
| `DecumulationPlanning.vue` | Decumulation planning page |
| `ContributionsAllowances.vue` | Contributions and allowances page |
| `PortfolioAnalysis.vue` | DC pension portfolio analysis |
| `WhatIfScenarios.vue` | What-if scenario comparison |
| `Recommendations.vue` | Retirement recommendations |

### 10.5 Other Components

| Component | Purpose |
|-----------|---------|
| `StrategyCard` | Individual strategy card with impact visualisation |
| `DrawdownSimulator` | Interactive drawdown simulation |
| `AnnualAllowanceTracker` | AA usage and carry forward display |
| `IncomeSourceSlider` | Slider for adjusting income allocation per source |
| `RequiredCapitalDetail` | Detailed capital adequacy breakdown |
| `TaxBreakdownCard` | Tax breakdown by income source |

---

## 11. Frontend Routing

**File:** `resources/js/router/index.js`

| Path | Component | Name |
|------|-----------|------|
| `/net-worth/retirement` | `PensionList` | `NetWorthRetirement` |
| `/pension/:type/:id` | `PensionDetail` | `PensionDetail` |
| `/preview/net-worth/retirement` | `PensionList` | `PreviewNetWorthRetirement` |
| `/preview/retirement` | Redirect to `/preview/net-worth/retirement` | |

**Module mapping**: `/net-worth/retirement`, `/retirement`, `/pension` all map to the `'retirement'` module in the sidebar navigation.

---

## 12. Cross-Module Integration

### 12.1 Investment Module

**Shared services**: The `RetirementAgent` injects 5 services from the Investment module:
- `PortfolioAnalyzer`
- `MonteCarloSimulator`
- `AssetAllocationOptimizer`
- `FeeAnalyzer`
- `TaxEfficiencyCalculator`

**`include_in_retirement` flag**: Investment accounts (`InvestmentAccount`) and savings accounts (`SavingsAccount`) have a boolean `include_in_retirement` field. When true, these accounts are included in retirement income planning calculations. This feature spans 21+ files across models, controllers, services, and frontend components.

**Holdings polymorphism**: DC pensions use the same `Holding` model as `InvestmentAccount` via `MorphMany`, enabling shared portfolio analysis tools.

### 12.2 Estate Module

10 Estate services reference pension/retirement data:
- `IHTCalculationService` -- DC pensions are **IHT-exempt** (filtered out of taxable estate)
- `EstateAssetAggregator` -- includes pension values in total estate overview
- `CashFlowProjector` -- uses retirement age for liability amortisation end date
- Various other estate planning services reference pension data for comprehensive estate calculations

### 12.3 Protection Module

2 Protection services reference pension data:
- `ComprehensiveProtectionPlan` -- considers pension death benefits
- `CoverageGapAnalyzer` -- includes pension provision in cover analysis

### 12.4 Risk Module

- `RiskPreferenceService` provides growth rates to `PensionProjector` based on risk level
- DC pensions support per-pension risk override (`has_custom_risk` + `risk_preference`)
- When creating a new DC pension, the controller auto-assigns risk from the user's `RiskProfile`

### 12.5 Dashboard

`DashboardAggregator` includes retirement in the composite financial health score:
- Weight: **25%** (highest of all modules)
- Provides: `projected_income`, `target_income`, `income_gap`, `years_to_retirement`

### 12.6 Net Worth

Retirement pensions are listed under the `/net-worth/retirement` route, showing pension values as part of the overall net worth calculation.

---

## 13. Onboarding Integration

**File:** `app/Services/Onboarding/OnboardingService.php`

During the onboarding income step:
- `target_retirement_age` is captured and saved to both the `users` table and `retirement_profiles` table
- `retirement_date` is saved to the `users` table
- If employment status is `'retired'`, the retirement age is calculated from `retirement_date` and `date_of_birth`, and saved to `RetirementProfile`

---

## 14. Seeder Data

### `PreviewUserSeeder` Pension Methods

**`createDCPensions(User $user, ?User $spouse, array $pensions)`**
- Determines pension owner via `determinePensionOwner()`:
  1. Explicit `owner` field set to `spouse`
  2. Notes mentioning spouse's name
  3. Matching `annual_salary` with spouse's income
- Creates `Holding` records for SIPPs with fund-level data

**`createDBPensions(User $user, ?User $spouse, array $pensions)`**
- Same owner determination logic as DC pensions
- Maps `pension_type` to `scheme_type` field

**`createStatePension(User $user, ?User $spouse, ?array $statePension, ?array $spouseStatePension)`**
- Creates separate `StatePension` records for user and spouse
- Defaults: `ni_years_completed: 35`, `ni_years_required: 35`, `state_pension_age: 66`

**`createRetirementProfiles()`**
- Creates `RetirementProfile` for each persona with target ages and incomes

---

## 15. API Routing

All routes are under `auth:sanctum` middleware with `/retirement` prefix.

```
/retirement
  GET  /                                    → index
  GET  /projections                         → getProjections
  GET  /required-capital                    → getRequiredCapital
  GET  /dc-pensions/{id}/projections        → getDCPensionProjection
  POST /analyze                             → analyze
  GET  /recommendations                     → recommendations
  POST /scenarios                           → scenarios
  GET  /portfolio-analysis                  → analyzeDCPensionPortfolio
  GET  /portfolio-analysis/{dcPensionId}    → analyzeDCPensionPortfolio
  GET  /annual-allowance/{taxYear}          → checkAnnualAllowance
  GET  /strategies                          → getStrategies
  GET  /strategies/impact                   → calculateStrategyImpact
  GET  /income                              → getRetirementIncome
  POST /income/calculate                    → calculateRetirementIncome
  GET  /income/accounts                     → getIncomeAccounts

  /pensions/dc
    POST /                                  → storeDCPension
    PUT  /{id}                              → updateDCPension
    DELETE /{id}                            → destroyDCPension
    GET  /{dcPensionId}/holdings            → DCPensionHoldingsController@index
    POST /{dcPensionId}/holdings            → DCPensionHoldingsController@store
    PUT  /{dcPensionId}/holdings/{holdingId}→ DCPensionHoldingsController@update
    DELETE /{dcPensionId}/holdings/{holdingId}→ DCPensionHoldingsController@destroy
    POST /{dcPensionId}/holdings/bulk-update → DCPensionHoldingsController@bulkUpdate
    GET  /{id}/diversification              → getDCPensionDiversification

  /pensions/db
    POST /                                  → storeDBPension
    PUT  /{id}                              → updateDBPension
    DELETE /{id}                            → destroyDBPension

  /state-pension
    POST /                                  → updateStatePension
```

---

## 16. Key Constants & Assumptions

### Growth & Withdrawal Rates

| Constant | Value | Used In |
|----------|-------|---------|
| Default growth rate | 5% | PensionProjector |
| Monte Carlo growth (in drawdown) | 4% | RetirementIncomeService |
| Sustainable withdrawal rate | 4.7% | RetirementProjectionService, RetirementIncomeService |
| DC withdrawal rate (simple) | 4% | PensionProjector |
| Required capital withdrawal | 4.7% | RequiredCapitalCalculator |
| ISA withdrawal rate | 4.7% | RetirementIncomeService |
| Bond tax-free rate | 5% cumulative | RetirementIncomeService |
| GIA withdrawal rate | 4% | RetirementIncomeService |
| Inflation | 2% (projection), 2.5% (capital) | Various |
| Fund fees | 1% | RequiredCapitalCalculator |

### Age Defaults

| Parameter | Default | Service |
|-----------|---------|---------|
| Retirement age | 67 | PensionProjector |
| Retirement age | 68 | RetirementIncomeService, RetirementProjectionService, RequiredCapitalCalculator |
| State pension age | 67 | RetirementIncomeService |
| Projection end age | 100 | RetirementProjectionService, RetirementIncomeService |
| Current age fallback | 40 | RetirementProjectionService |

**Note**: There is inconsistency in default retirement age across services (67 vs 68). The resolution chain in `RequiredCapitalCalculator` prioritises user data.

### Monte Carlo Parameters

| Parameter | Value |
|-----------|-------|
| Iterations | 1,000 |
| Confidence level for income planning | 80% (20th percentile) |
| Probability bands | 10th, 20th, 50th, 80th, 90th percentile |

---

## 17. Known Issues & Vulnerabilities

### 17.1 Hardcoded Tax Bands
`ContributionOptimizer` has **hardcoded tax bands** instead of using `TaxConfigService`. All other services correctly use the centralised config.

### 17.2 Simplified Carry Forward
`AnnualAllowanceChecker` carry forward is simplified to always return 1 year's allowance, rather than calculating actual unused allowance from the previous 3 years.

### 17.3 MPAA Not Implemented
`AnnualAllowanceChecker.checkMPAA()` is a placeholder that always returns "not triggered". Money Purchase Annual Allowance (£10,000) should apply when a user has flexibly accessed their DC pension.

### 17.4 DB Pension Revaluation Not Applied
`PensionProjector.projectDBPension()` returns the raw `accrued_annual_pension` without applying revaluation. The `revaluation_method` and `inflation_protection` fields are captured but not used in calculations.

### 17.5 Inconsistent Default Retirement Ages
Different services use different defaults (67 vs 68). While the resolution chain handles this for real users, it can cause subtle discrepancies in edge cases.

### 17.6 State Pension Amount Outdated
`PensionProjector` uses £11,502 as the full state pension (2024/25), which should be updated annually or pulled from `TaxConfigService`.

### 17.7 RetirementProfile.risk_tolerance Deprecated
The `risk_tolerance` field on `retirement_profiles` is deprecated but still in the schema and fillable array. Should eventually be removed via migration.

---

## 18. Tax Withdrawal Priority Order

The fund depletion simulation in `RetirementIncomeService` uses the following priority for withdrawals, designed to be tax-efficient:

```
1. Bond 5% Tax-Free     → Use cumulative 5% annual tax-free allowance first
2. PCLS                  → 25% pension tax-free lump sum
3. ISA                   → Tax-free withdrawals (Stocks & Shares + Cash)
4. Pension Drawdown      → Taxable as income
5. GIA                   → Capital gains tax
6. Savings               → Interest taxable
```

Personal Allowance is allocated to State Pension and DB Pension income first (as these are fixed and cannot be controlled), before being applied to flexible income sources.
