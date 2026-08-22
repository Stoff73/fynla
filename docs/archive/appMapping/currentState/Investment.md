# Investment Module Documentation

> Comprehensive documentation of the Investment module across the entire Fynla application stack.
> Generated: 2026-02-18

---

## 1. System Overview

The Investment module is the most feature-rich module in Fynla. It covers portfolio management, risk profiling, Monte Carlo projections, tax-optimised wrapper strategies, fee analysis, rebalancing with CGT awareness, efficient frontier / Modern Portfolio Theory calculations, goal tracking, and comprehensive investment plan generation.

### Core Components

| Layer | Component | Lines | Purpose |
|-------|-----------|-------|---------|
| Models | 8 models in `app/Models/Investment/` | ~1,080 | Accounts, holdings, risk, goals, plans, scenarios, recommendations, rebalancing |
| Controllers | 1 main + 17 sub-controllers | ~4,500 | 100+ API endpoints across 14 route groups |
| Agent | `InvestmentAgent` | 382 | Orchestrator with caching, analysis, recommendations, scenarios |
| Services | 42 services (12 top-level + 30 in subdirectories) | ~8,000+ | Analytics, tax, fees, rebalancing, MPT, goals, performance |
| Vuex Store | `investment.js` | 1,050+ | 36 state properties, 40+ getters, 30+ actions |
| API Service | `investmentService.js` | 1,505 | 100+ API wrapper methods |
| Vue Components | 60 components + 9 views | ~12,000+ | Forms, charts, tables, strategy panels, modals |
| Validation | 10 Form Request classes | ~400 | Input validation |

### Architecture Flow

```
                    +----------------------------------------------+
                    |            8 Database Tables                  |
                    |  investment_accounts, holdings, risk_profiles,|
                    |  investment_goals, investment_plans,          |
                    |  investment_scenarios, investment_            |
                    |  recommendations, rebalancing_actions,        |
                    |  monte_carlo_cache                            |
                    +---------------------+------------------------+
                                          |
                    +---------------------+------------------------+
                    |           8 Eloquent Models                   |
                    +---------------------+------------------------+
                                          |
          +-------------------------------+------------------------------+
          |                               |                              |
   InvestmentAgent              42 Services                   18 Controllers
   (orchestrator)           (domain logic)                  (100+ endpoints)
          |                               |                              |
     +----+------+          +-------------+----------+     +-------------+
     | analyze   |          | Analytics/  | Tax/     |     | Main CRUD   |
     | recommend |          | Fees/      | Rebalance/|     | Optimization|
     | scenarios |          | Goals/     | MPT/     |     | Tax/Fee     |
     | portfolio |          | ModelPort/ | Perf/    |     | Scenarios   |
     +-----------+          +------------+----------+     +-------------+
                                          |
                    +---------------------+------------------------+
                    |        Frontend (Vue.js)                      |
                    |  Vuex Store -> API Service -> 60 Components   |
                    |  + 9 Account Detail Views                     |
                    +----------------------------------------------+
```

---

## 2. Database Schema

### 2.1 `investment_accounts` Table

The central table with ~183 fillable fields covering standard accounts, bonds, BADR, private investments, and employee share schemes.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `user_id` | bigint FK | Primary owner |
| `joint_owner_id` | bigint FK nullable | Joint owner (single-record pattern) |
| `account_name` | varchar nullable | User-friendly name |
| `account_type` | varchar | `isa`, `gia`, `onshore_bond`, `offshore_bond`, `private_company`, `crowdfunding`, `saye`, `csop`, `emi`, `unapproved_options`, `rsu`, `other` |
| `account_type_other` | varchar nullable | Custom type when `other` |
| `provider` | varchar | Platform/provider name |
| `platform` | varchar nullable | Platform name |
| `current_value` | decimal(12,2) | FULL value (not split for joint) |
| `contributions_ytd` | decimal(12,2) default 0 | Year-to-date contributions |
| `monthly_contribution_amount` | decimal(12,2) nullable | Regular monthly amount |
| `contribution_frequency` | varchar default `monthly` | Contribution frequency |
| `planned_lump_sum_amount` | decimal(12,2) nullable | Planned lump sum |
| `planned_lump_sum_date` | date nullable | When lump sum is planned |
| `ownership_type` | varchar default `individual` | `individual`, `joint`, `tenants_in_common`, `trust` |
| `ownership_percentage` | int default 100 | Primary owner's share |
| `country` | varchar nullable | Country of registration |
| `tax_year` | varchar | Active tax year (e.g. `2025/26`) |
| `platform_fee_percent` | decimal(5,2) default 0 | Platform fee % |
| `platform_fee_type` | varchar nullable | `percentage` or `fixed` |
| `platform_fee_frequency` | varchar nullable | Fee billing frequency |
| `advisor_fee_percent` | decimal(5,2) default 0 | Adviser fee % |
| `risk_preference` | varchar nullable | Account-level risk override |
| `has_custom_risk` | boolean default false | Whether account has custom risk |
| `rebalance_threshold_percent` | decimal(5,2) default 10 | Drift threshold for rebalancing |
| `include_in_retirement` | boolean default false | Show in Retirement Income Planner |

**ISA-specific fields:**

| Column | Type | Description |
|--------|------|-------------|
| `isa_type` | varchar nullable | ISA sub-type |
| `isa_subscription_current_year` | decimal(12,2) nullable | Current year ISA subscriptions |

**Bond-specific fields:**

| Column | Type | Description |
|--------|------|-------------|
| `bond_segments` | int nullable | Number of segments |
| `bond_start_date` | date nullable | Bond commencement |
| `bond_term_years` | int nullable | Bond term |
| `cumulative_withdrawals` | decimal(12,2) nullable | Total withdrawals |
| `top_slicing_available` | boolean nullable | Top-slicing eligibility |
| `five_percent_allowance_used` | decimal(12,2) nullable | Annual 5% withdrawals taken |

**BADR (Business Asset Disposal Relief) fields:**

| Column | Type | Description |
|--------|------|-------------|
| `badr_eligible` | boolean nullable | BADR eligibility |
| `badr_holding_period_start` | date nullable | Start of qualifying period |
| `badr_lifetime_allowance_used` | decimal(12,2) nullable | BADR lifetime allowance used |
| `disposal_restriction_date` | date nullable | EIS/SEIS restriction end |

**Private Investment fields:**

| Column | Type | Description |
|--------|------|-------------|
| `investment_date` | date nullable | Date of investment |
| `company_name` | varchar nullable | Private company name |
| `shares_held` | int nullable | Number of shares |
| `share_class` | varchar nullable | Share class |
| `last_valuation_date` | date nullable | Last valuation date |
| `eis_seis_relief` | varchar nullable | `eis`, `seis`, `none` |
| `tax_relief_claimed` | boolean nullable | Whether relief claimed |
| `exit_strategy` | varchar nullable | Expected exit route |

**Employee Share Scheme fields:**

| Column | Type | Description |
|--------|------|-------------|
| `employer_name` | varchar nullable | Employer name |
| `grant_date` | date nullable | Grant/award date |
| `vesting_date` | date nullable | Vesting date |
| `exercise_window_end` | date nullable | Exercise deadline |
| `units_granted` | int nullable | Total units granted |
| `units_vested` | int nullable | Vested units |
| `units_exercised` | int nullable | Exercised units |
| `exercise_price` | decimal(12,2) nullable | Option exercise price |
| `current_share_price` | decimal(12,2) nullable | Current market price |
| `savings_monthly` | decimal(12,2) nullable | SAYE monthly savings |
| `savings_term_months` | int nullable | SAYE savings term |
| `saye_maturity_date` | date nullable | Auto-calculated SAYE maturity |
| `csop_three_year_date` | date nullable | Auto-calculated CSOP 3-year date |
| `tax_treatment` | varchar nullable | Expected tax treatment |

### 2.2 `holdings` Table (Polymorphic)

Shared between `InvestmentAccount` and `DCPension` via MorphMany.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `holdable_id` | bigint | Parent ID |
| `holdable_type` | varchar | `App\Models\Investment\InvestmentAccount` or `App\Models\DCPension` |
| `asset_type` | varchar | `equity`, `bond`, `cash`, `fund`, `etf`, `alternative`, `property`, `commodity` |
| `allocation_percent` | decimal(5,2) nullable | Target allocation % |
| `security_name` | varchar | Holding name |
| `ticker` | varchar nullable | Ticker symbol |
| `isin` | varchar nullable | ISIN code |
| `quantity` | decimal(12,4) nullable | Units/shares held |
| `purchase_price` | decimal(12,4) nullable | Price per unit at purchase |
| `purchase_date` | date nullable | Date purchased |
| `current_price` | decimal(12,4) nullable | Current price per unit |
| `current_value` | decimal(12,2) | Current market value |
| `cost_basis` | decimal(12,2) nullable | Total cost basis |
| `dividend_yield` | decimal(5,2) nullable | Dividend yield % |
| `ocf_percent` | decimal(5,4) nullable | Ongoing Charges Figure % |

### 2.3 `risk_profiles` Table

One per user, stores their assessed risk preference.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `user_id` | bigint FK | Owner |
| `risk_tolerance` | varchar nullable | Self-assessed tolerance |
| `risk_level` | varchar nullable | 5-level: `low`, `lower_medium`, `medium`, `upper_medium`, `high` |
| `capacity_for_loss_percent` | int nullable | % capacity for loss |
| `time_horizon_years` | int nullable | Investment time horizon |
| `knowledge_level` | varchar nullable | Investment knowledge |
| `attitude_to_volatility` | varchar nullable | Volatility comfort |
| `esg_preference` | boolean default false | ESG preference |
| `risk_assessed_at` | timestamp nullable | When last assessed |
| `is_self_assessed` | boolean default true | Self-assessed vs adviser |
| `factor_breakdown` | json nullable | Detailed factor scores |

### 2.4 `investment_goals` Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `user_id` | bigint FK | Owner |
| `goal_name` | varchar | Goal description |
| `goal_type` | varchar | Type of goal |
| `target_amount` | decimal(12,2) | Target value |
| `target_date` | date nullable | Target achievement date |
| `priority` | int | Priority ranking |
| `is_essential` | boolean default false | Essential vs aspirational |
| `linked_account_ids` | json nullable | Linked account IDs |

### 2.5 `investment_plans` Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `user_id` | bigint FK | Owner |
| `plan_version` | varchar | Plan version |
| `plan_data` | json | Full plan content (8 sections) |
| `portfolio_health_score` | int nullable | 0-100 health score |
| `is_complete` | boolean default false | Whether plan is complete |
| `completeness_score` | int nullable | Data completeness score |
| `generated_at` | timestamp nullable | Generation timestamp |

### 2.6 `investment_scenarios` Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `user_id` | bigint FK | Owner |
| `scenario_name` | varchar | Name |
| `description` | text nullable | Description |
| `scenario_type` | varchar | Scenario category |
| `template_name` | varchar nullable | Pre-built template used |
| `parameters` | json | Input parameters |
| `results` | json nullable | Simulation results |
| `comparison_data` | json nullable | Comparison with baseline |
| `status` | varchar default `draft` | `draft`, `running`, `completed`, `failed` |
| `is_saved` | boolean default false | Bookmarked |
| `monte_carlo_job_id` | varchar nullable | Linked MC job |
| `completed_at` | timestamp nullable | Completion time |

### 2.7 `investment_recommendations` Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `investment_plan_id` | bigint FK | Parent plan |
| `user_id` | bigint FK | Owner |
| `category` | varchar | Recommendation category |
| `priority` | int | Priority (lower = higher) |
| `title` | varchar | Recommendation title |
| `description` | text | Detailed description |
| `action_required` | text nullable | What to do |
| `impact_level` | varchar | `high`, `medium`, `low` |
| `potential_saving` | decimal(12,2) nullable | Estimated savings |
| `estimated_effort` | varchar nullable | Time to implement |
| `status` | varchar default `pending` | `pending`, `in_progress`, `completed`, `dismissed` |
| `due_date` | date nullable | Target completion |
| `completed_at` | timestamp nullable | When completed |
| `dismissed_at` | timestamp nullable | When dismissed |
| `dismissal_reason` | text nullable | Why dismissed |

### 2.8 `rebalancing_actions` Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `user_id` | bigint FK | Owner |
| `holding_id` | bigint FK nullable | Target holding |
| `investment_account_id` | bigint FK | Target account |
| `action_type` | varchar | `buy`, `sell`, `switch` |
| `security_name` | varchar | Security name |
| `ticker` | varchar nullable | Ticker |
| `isin` | varchar nullable | ISIN |
| `shares_to_trade` | decimal(12,4) | Units to trade |
| `trade_value` | decimal(12,2) | Trade value |
| `current_price` | decimal(12,4) nullable | Price at calculation |
| `target_value` | decimal(12,2) nullable | Target value post-trade |
| `target_weight` | decimal(5,2) nullable | Target allocation % |
| `priority` | int | Execution priority |
| `rationale` | text nullable | Why this trade |
| `cost_basis` | decimal(12,2) nullable | CGT cost basis |
| `gain_or_loss` | decimal(12,2) nullable | Estimated gain/loss |
| `cgt_liability` | decimal(12,2) nullable | CGT if executed |
| `executed_at` | timestamp nullable | When executed |
| `executed_price` | decimal(12,4) nullable | Actual execution price |
| `executed_shares` | decimal(12,4) nullable | Actual shares traded |
| `status` | varchar default `pending` | `pending`, `approved`, `executed`, `cancelled` |
| `notes` | text nullable | Execution notes |

### 2.9 `monte_carlo_cache` Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `cache_key` | varchar unique | Lookup key |
| `results` | json | Simulation results |
| `expires_at` | timestamp | 24-hour TTL |

---

## 3. Models

### 3.1 InvestmentAccount (`app/Models/Investment/InvestmentAccount.php`, 498 lines)

The largest model in the application.

**Traits:** `Auditable`, `HasFactory`, `HasJointOwnership`

**Relationships:**
- `user()` - BelongsTo User (primary owner)
- `jointOwner()` - BelongsTo User (joint owner, nullable)
- `holdings()` - MorphMany Holding (polymorphic)
- `household()` - BelongsTo Household
- `trust()` - BelongsTo Trust

**Key Methods:**
- `isPrivateInvestment()` - Checks if account_type is `private_company` or `crowdfunding`
- `isEmployeeShareScheme()` - Checks for `saye`, `csop`, `emi`, `unapproved_options`, `rsu`
- `isOptionsScheme()` - Checks for `saye`, `csop`, `emi`, `unapproved_options`
- `isTaxAdvantagedScheme()` - Checks for `csop`, `emi`, `saye` with tax-relevant conditions
- `getIntrinsicValueAttribute()` - Accessor: `(current_share_price - exercise_price) * remaining_units`
- `getSchemeCurrentValueAttribute()` - Accessor: market value of all vested units
- `isInCsopTaxAdvantageWindow()` - Whether 3+ years from CSOP grant
- `getRemainingUnitsAttribute()` - `units_vested - units_exercised`

**Default Attributes:**
```php
'contributions_ytd' => 0,
'platform_fee_percent' => 0,
'has_custom_risk' => false,
'rebalance_threshold_percent' => 10,
'include_in_retirement' => false,
```

### 3.2 Holding (`app/Models/Investment/Holding.php`, 85 lines)

Polymorphic model shared with DCPension.

**Polymorphic relationship:** `holdable()` - MorphTo (InvestmentAccount or DCPension)

**Scopes:** `forInvestmentAccounts()` - Filters to InvestmentAccount holdable_type only

**Backward compatibility:** `investmentAccount()` BelongsTo, `getInvestmentAccountIdAttribute()` accessor

### 3.3 RiskProfile (`app/Models/Investment/RiskProfile.php`, 46 lines)

One-per-user risk profile. Nullable columns for progressive data entry.

**Casts:** `factor_breakdown` as array, `esg_preference` as boolean

### 3.4 InvestmentGoal (`app/Models/Investment/InvestmentGoal.php`, 41 lines)

**Casts:** `linked_account_ids` as array, `is_essential` as boolean

### 3.5 InvestmentPlan (`app/Models/Investment/InvestmentPlan.php`, 50 lines)

**Relationships:** `recommendations()` - HasMany InvestmentRecommendation

**Casts:** `plan_data` as array, `is_complete` as boolean

### 3.6 InvestmentScenario (`app/Models/Investment/InvestmentScenario.php`, 111 lines)

**Casts:** `parameters`, `results`, `comparison_data` all as array; `is_saved` as boolean

**Scopes:** `saved()`, `completed()`, `ofType($type)`

**Methods:** `isCompleted()`, `isRunning()`, `markAsCompleted($results)`, `markAsFailed($error)`

### 3.7 RebalancingAction (`app/Models/Investment/RebalancingAction.php`, 113 lines)

**Scopes:** `status($status)`, `actionType($type)`, `pending()`, `executed()`

### 3.8 InvestmentRecommendation (`app/Models/Investment/InvestmentRecommendation.php`, 82 lines)

**Relationships:** `investmentPlan()` - BelongsTo InvestmentPlan

**Scopes:** `pending()`, `completed()`, `highPriority()`

---

## 4. Controllers

### 4.1 Main Controller: `InvestmentController` (969 lines)

**Dependencies:** InvestmentAgent, InvestmentProjectionService, DiversificationAnalyzer

**Traits:** `CalculatesOwnershipShare`, `SanitizedErrorResponse`

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `index()` | GET | Fetch accounts (owner OR joint_owner), transform with InvestmentAccountResource |
| `analyze()` | POST | Run full portfolio analysis via InvestmentAgent |
| `recommendations()` | GET | Generate recommendations via InvestmentAgent |
| `scenarios()` | POST | Build scenarios via InvestmentAgent |
| `startMonteCarlo()` | POST | Start Monte Carlo simulation |
| `getMonteCarloResults($jobId)` | GET | Poll for MC results |
| `storeAccount()` | POST | Create account with ISA validation, risk auto-assign, auto-calculations |
| `updateAccount($id)` | PUT | Update account |
| `destroyAccount($id)` | DELETE | Delete account + holdings |
| `toggleRetirementInclusion($id)` | PATCH | Toggle `include_in_retirement` flag |
| `storeHolding()` | POST | Create holding with polymorphic fields, cash adjustment |
| `updateHolding($id)` | PUT | Update holding |
| `destroyHolding($id)` | DELETE | Delete holding |
| `storeGoal()` | POST | Create investment goal |
| `updateGoal($id)` | PUT | Update goal |
| `destroyGoal($id)` | DELETE | Delete goal |
| `storeOrUpdateRiskProfile()` | POST | Upsert risk profile |
| `getAccountProjections($id)` | GET | Account-level projections with risk override |
| `getAccountDiversification($id)` | GET | Account diversification analysis |
| `calculateAccountAnnualisedReturn($id)` | GET | Annualised return calculation |

**Key Business Logic in `storeAccount()`:**
- ISAs must be `individual` ownership (no joint ISAs in UK)
- Joint accounts default to 50% ownership
- Risk auto-assigned from user's RiskProfile if not specified
- EIS/SEIS: `disposal_restriction_date` = investment_date + 3 years
- CSOP: `csop_three_year_date` = grant_date + 3 years
- SAYE: `saye_maturity_date` = grant_date + savings_term_months
- Default `tax_treatment` set for share schemes

### 4.2 Sub-Controllers (15 in `app/Http/Controllers/Api/Investment/`)

| Controller | Lines | Purpose |
|-----------|-------|---------|
| `PortfolioStrategyController` | ~80 | Aggregated strategy recommendations |
| `InvestmentProjectionController` | ~80 | Portfolio projection calculations |
| `RebalancingCalculationController` | ~200 | CGT-aware rebalancing calculations |
| `RebalancingStrategiesController` | ~150 | Strategy evaluation (threshold, calendar, opportunistic) |
| `RebalancingActionsController` | ~120 | CRUD for rebalancing actions |
| `ContributionOptimizerController` | ~100 | Contribution optimisation, affordability, lump sum vs DCA |
| `TaxOptimizationController` | ~200 | Tax analysis, ISA strategy, CGT harvesting, Bed & ISA |
| `AssetLocationController` | ~120 | Asset location analysis, tax drag, account comparison |
| `PerformanceAttributionController` | ~120 | Performance attribution, benchmark comparison, risk metrics |
| `GoalProgressController` | ~150 | Goal progress, shortfall, what-if, probability, glide path |
| `FeeImpactController` | ~150 | Fee analysis, OCF impact, platform comparison |
| `RiskPreferenceController` | ~120 | 5-level risk system, profile management |
| `ModelPortfolioController` | ~120 | Model portfolios, age optimisation, glide path, fund recommendations |
| `EfficientFrontierController` | ~150 | MPT calculations, optimal portfolios, frontier analysis |
| `InvestmentPlanController` | ~100 | Plan generation and retrieval |
| `InvestmentRecommendationController` | ~150 | Recommendation CRUD with status management |
| `InvestmentScenarioController` | ~180 | Scenario CRUD, templates, comparison |

---

## 5. Agent

### `InvestmentAgent` (`app/Agents/InvestmentAgent.php`, 382 lines)

**Dependencies:** PortfolioAnalyzer, MonteCarloSimulator, AssetAllocationOptimizer, FeeAnalyzer, TaxEfficiencyCalculator, TaxConfigService

**Caching:** Uses `invalidateUserCache()` with tag `['investment', 'user_{userId}']`, key `investment_analysis`

### 5.1 `analyze()` Method

Returns comprehensive analysis object:
```php
[
    'portfolio_summary' => [...],     // Total value, account count
    'returns' => [...],               // YTD, annualised
    'asset_allocation' => [...],      // Current allocation by asset_type
    'diversification_score' => 0-100, // HHI-based score
    'risk_metrics' => [...],          // Risk level, volatility
    'fee_analysis' => [...],          // Platform fees, OCF, total cost
    'low_cost_comparison' => [...],   // Active vs passive
    'tax_efficiency' => [...],        // Unrealised gains, efficiency score
    'tax_wrappers' => [               // ISA allowance tracking
        'isa_allowance_remaining' => ...,
        'isa_utilisation_percent' => ...,
    ],
    'allocation_deviation' => [...],  // Target vs actual, needs_rebalancing
    'goals' => [...],                 // Goal progress
]
```

### 5.2 `generateRecommendations()` Method

Generates 8+ recommendation categories:

| Category | Trigger | Priority |
|----------|---------|----------|
| Risk Profile | No risk profile set | 1 |
| Portfolio Setup | No holdings | 1 |
| Diversification | Score < 70 | 3 |
| Fees | Potential saving > 50 | 3 |
| High Fees | Any holding OCF > 0.5% | 4 |
| Platform Fees | Platform fee > 0.8% | 4 |
| Asset Allocation | Deviation > 5% | 3 |
| Tax Efficiency | GIA assets that could be in ISA/Bond | 3 |
| Tax Loss Harvesting | Unrealised losses available | 4 |

### 5.3 `buildScenarios()` Method

Generates 4 comparison scenarios:

| Scenario | Return | Volatility |
|----------|--------|------------|
| Conservative | 4% | 8% |
| Balanced | 7% | 12% |
| Aggressive | 10% | 18% |
| Increased Contributions | 7% | 12% (1.5x contributions) |

---

## 6. Services

### 6.1 Top-Level Services (12)

#### MonteCarloSimulator (`MonteCarloSimulator.php`, 275 lines)

- **Cache:** 24-hour DB cache in `monte_carlo_cache` table
- **Algorithm:** Box-Muller normal distribution, monthly compounding
- **Default:** 1,000 iterations
- **Output:** Summary, year-by-year bands, final percentiles (10th/25th/50th/75th/90th), total_contributions, median_gain
- `simulate(startValue, monthlyContribution, expectedReturn, volatility, years, iterations, cacheKey)`
- `calculateGoalProbability(results, targetAmount)` - Success count / total iterations

#### PortfolioAnalyzer (`PortfolioAnalyzer.php`)

- `calculateTotalValue(accounts)` - Sum of all account values
- `calculateReturns(accounts)` - Simplified YTD return calculation
- `calculateAssetAllocation(holdings)` - Group by asset_type, calculate percentages

#### FeeAnalyzer (`FeeAnalyzer.php`)

- Platform fees (percentage-based or fixed)
- Fund OCF aggregation across holdings
- Transaction cost estimation (0.1% default)
- Fee drag percentage calculation

#### TaxEfficiencyCalculator (`TaxEfficiencyCalculator.php`)

Uses `TaxConfigService` for rates:
- `calculateUnrealizedGains(holdings)` - Current value vs cost basis
- `calculateDividendTax(holdings)` - Simplified dividend tax estimate
- Tax efficiency score (0-100)

#### AssetAllocationOptimizer (`AssetAllocationOptimizer.php`, 222 lines)

**5-Level Target Allocations:**

| Risk Level | Equity | Bond | Cash |
|------------|--------|------|------|
| `low` | 10% | 70% | 20% |
| `lower_medium` | 30% | 55% | 15% |
| `medium` | 50% | 40% | 10% |
| `upper_medium` | 75% | 20% | 5% |
| `high` | 90% | 5% | 5% |

**Legacy 3-Level (backward compatibility):**

| Risk Level | Equity | Bond | Cash |
|------------|--------|------|------|
| `cautious` | 20% | 60% | 20% |
| `balanced` | 60% | 30% | 10% |
| `adventurous` | 80% | 15% | 5% |

- `calculateDeviation()` - > 5% per class = overweight/underweight, > 15% total = `needs_rebalancing`
- `generateRebalancingTrades()` - Only for deviations > 5% of portfolio
- `suggestNewInvestorAllocation()` - 100-age equity rule with retirement adjustment

#### DiversificationAnalyzer (`DiversificationAnalyzer.php`)

- **HHI (Herfindahl-Hirschman Index):**
  - < 0.15 = Well Diversified
  - 0.15 - 0.25 = Moderate Concentration
  - > 0.25 = High Concentration
- Asset class mapping: equity -> equities, bond -> bonds, cash -> cash, fund/etf -> equities, alternative -> alternatives
- Target allocations per numeric risk level (1-5)
- Risk level normalisation: string -> numeric mapping

#### InvestmentProjectionService (`InvestmentProjectionService.php`, 250 lines)

- **Constants:** `DEFAULT_PROJECTION_PERIODS` = [5, 10, 20, 30], `MONTE_CARLO_ITERATIONS` = 1000
- **Dependencies:** MonteCarloSimulator, RiskPreferenceService, ContributionEstimatorService
- Joint ownership handling via `getUserShareValue()` (percentage-based)
- Portfolio + per-account projections with risk source tracking (default/profile)
- Probability bands: 10th, 15th, 20th, 25th, 50th, 75th, 90th percentile

#### InvestmentPlanGenerator (`InvestmentPlanGenerator.php`)

Generates comprehensive 8-section investment plans:
1. Executive Summary
2. Current Situation
3. Goal Progress
4. Risk Analysis
5. Tax Strategy
6. Fee Analysis
7. Recommendations
8. Action Plan

**Dependencies:** PortfolioAnalyzer, TaxOptimizationAnalyzer, FeeAnalyzer, DriftAnalyzer, PerformanceAttributionAnalyzer, AssetLocationOptimizer, GoalProgressAnalyzer

#### ScenarioService (`ScenarioService.php`)

5 pre-built templates:
- `market_crash` - Stress test
- `early_retirement` - Early drawdown
- `increased_contributions` - 1.5x contributions
- `fee_reduction` - Lower cost alternatives
- `aggressive_allocation` / `conservative_allocation` - Risk changes

#### ContributionOptimizer (`ContributionOptimizer.php`)

- ISA vs GIA contribution priority
- Pension vs ISA optimisation
- Lump sum vs DCA (Dollar-Cost Averaging) analysis
- Tax relief calculations
- Wrapper allocation optimisation

**Dependencies:** ISAAllowanceOptimizer, GoalProbabilityCalculator, TaxConfigService

#### ContributionEstimatorService (`ContributionEstimatorService.php`)

Estimates future contributions for projection purposes based on current contribution patterns.

#### PortfolioStrategyService (`PortfolioStrategyService.php`)

Aggregates recommendations from tax, fee, and rebalancing services into a unified, prioritised view.

**Priority constants:**
- `PRIORITY_TAX` = 1 (highest)
- `PRIORITY_WRAPPER` = 2
- `PRIORITY_FEES` = 3
- `PRIORITY_REBALANCING` = 4

**Bond wrapper thresholds:**
- Onshore bond minimum balance: 50,000
- Offshore bond minimum balance: 100,000

**Dependencies:** TaxOptimizationAnalyzer, FeeAnalyzer, DriftAnalyzer, TaxConfigService

### 6.2 Analytics Services (`Services/Investment/Analytics/`, 6 services)

| Service | Purpose |
|---------|---------|
| `EfficientFrontierCalculator` | MPT efficient frontier computation |
| `MarkowitzOptimizer` | Mean-variance optimisation |
| `CorrelationMatrixCalculator` | Asset correlation analysis |
| `CovarianceMatrixCalculator` | Covariance matrix for MPT |
| `PortfolioStatisticsCalculator` | Sharpe ratio, standard deviation, etc. |
| `HoldingsDataExtractor` | Extracts and normalises holdings data for analytics |

### 6.3 Asset Location Services (`Services/Investment/AssetLocation/`, 3 services)

| Service | Purpose |
|---------|---------|
| `AssetLocationOptimizer` | Optimal wrapper placement (ISA > GIA > Bond) |
| `AccountTypeRecommender` | Recommends account types for holdings |
| `TaxDragCalculator` | Calculates tax drag from suboptimal placement |

### 6.4 Fee Services (`Services/Investment/Fees/`, 2 services)

| Service | Purpose |
|---------|---------|
| `OCFImpactCalculator` | Long-term OCF impact projections |
| `PlatformComparator` | Compare platform costs |

### 6.5 Goal Services (`Services/Investment/Goals/`, 3 services)

| Service | Purpose |
|---------|---------|
| `GoalProgressAnalyzer` | Goal progress tracking |
| `GoalProbabilityCalculator` | Monte Carlo goal probability |
| `ShortfallAnalyzer` | Goal shortfall analysis |

### 6.6 Model Portfolio Services (`Services/Investment/ModelPortfolio/`, 3 services)

| Service | Purpose |
|---------|---------|
| `ModelPortfolioBuilder` | Build risk-appropriate model portfolios |
| `FundSelector` | Recommend specific funds |
| `AssetAllocationOptimizer` | Age/horizon-based allocation |

### 6.7 Performance Services (`Services/Investment/Performance/`, 3 services)

| Service | Purpose |
|---------|---------|
| `PerformanceAttributionAnalyzer` | Attribution analysis |
| `BenchmarkComparator` | Compare vs benchmarks |
| `AlphaBetaCalculator` | Alpha/beta calculations |

### 6.8 Rebalancing Services (`Services/Investment/Rebalancing/`, 4 services)

| Service | Purpose |
|---------|---------|
| `DriftAnalyzer` | Portfolio drift detection |
| `RebalancingCalculator` | Trade calculations |
| `TaxAwareRebalancer` | CGT-optimised rebalancing |
| `RebalancingStrategyService` | Threshold/calendar/opportunistic strategies |

### 6.9 Tax Services (`Services/Investment/Tax/`, 4 services)

| Service | Purpose |
|---------|---------|
| `TaxOptimizationAnalyzer` | Comprehensive tax analysis |
| `ISAAllowanceOptimizer` | ISA allowance utilisation |
| `CGTHarvestingCalculator` | Capital gains tax loss harvesting |
| `BedAndISACalculator` | Bed and ISA transfer analysis |

### 6.10 Utility Services (`Services/Investment/Utilities/`, 2 services)

| Service | Purpose |
|---------|---------|
| `MatrixOperations` | Matrix math for MPT calculations |
| `StatisticalFunctions` | Statistical helper functions |

---

## 7. Validation Requests

10 Form Request classes in `app/Http/Requests/Investment/`:

| Request | Purpose |
|---------|---------|
| `StoreHoldingRequest` | Validate new holding creation |
| `UpdateHoldingRequest` | Validate holding updates |
| `StoreInvestmentGoalRequest` | Validate new goal |
| `UpdateInvestmentGoalRequest` | Validate goal updates |
| `StoreRiskProfileRequest` | Validate risk profile data |
| `AccountProjectionsRequest` | Validate projection parameters |
| `StartMonteCarloRequest` | Validate Monte Carlo inputs |
| `ScenarioRequest` | Validate scenario parameters |
| `CalculateEfficientFrontierRequest` | Validate MPT inputs |
| `OptimizePortfolioRequest` | Validate optimisation parameters |

---

## 8. Vuex Store

### `resources/js/store/modules/investment.js` (~1,050 lines)

**State (36 properties):**

```javascript
{
    accounts: [],
    goals: [],
    riskProfile: null,
    analysis: null,
    recommendations: null,            // { recommendation_count, recommendations: [] }
    monteCarloResults: {},             // Keyed by jobId
    monteCarloStatus: {},              // Keyed by jobId
    monteCarloResultsByGoal: {},       // Keyed by goalId
    optimizationResult: null,          // Portfolio optimization result
    scenarios: null,
    investmentPlan: null,              // Latest plan
    investmentPlans: [],               // Historical plans
    investmentRecommendations: [],     // Tracked recommendations
    recommendationStats: null,
    recommendationsDashboard: null,
    scenarioTemplates: [],             // Pre-built templates
    investmentScenarios: [],           // User's scenarios
    scenarioStats: null,
    scenarioComparison: null,
    contributionOptimization: null,
    assetLocationAnalysis: null,
    performanceAttribution: null,
    benchmarkComparison: null,
    goalProjections: {},               // By goal ID
    feeAnalysis: null,
    portfolioProjections: null,
    projectionsLoading: false,
    projectionsError: null,
    selectedProjectionPeriod: 10,      // Default 10 years
    loading: false,
    error: null,
}
```

**Key Getters (40+):**

| Getter | Returns |
|--------|---------|
| `totalPortfolioValue` | Sum of all accounts (joint ownership aware: fullValue * percentage/100) |
| `ytdReturn` | Year-to-date return |
| `assetAllocation` | Current allocation breakdown |
| `totalFees` | Total annual fees |
| `feeDragPercent` | Fee drag as percentage |
| `unrealisedGains` | Total unrealised gains |
| `taxEfficiencyScore` | 0-100 score |
| `diversificationScore` | 0-100 HHI-based score |
| `riskLevel` | Portfolio risk level from analysis |
| `mainRiskLevel` | 5-level risk from profile |
| `hasRiskProfile` | Boolean check |
| `productsWithCustomRisk` | Accounts with custom risk override |
| `allHoldings` | Flattened holdings across all accounts |
| `holdingsCount` | Total holdings count |
| `accountsCount` | Total accounts count |
| `isaAccounts` | Filtered ISA accounts |
| `totalISAValue` | ISA value (ownership-adjusted) |
| `isaPercentage` | ISA as % of portfolio |
| `totalISAContributions` | Current year ISA subscriptions |
| `isaAllowancePercentage` | ISA utilisation % (from savings store API) |
| `investmentISASubscription` | S&S ISA subscriptions |
| `goalsOnTrack` | Goals with 50%+ progress |
| `needsRebalancing` | Boolean from analysis |
| `pendingRecommendations` | Filtered by status |
| `highPriorityRecommendations` | Priority <= 3 |
| `getRecommendationsByCategory` | Factory getter |
| `selectedPortfolioProjection` | Projection for selected period |

**Key Actions (30+):**

| Action | Effect |
|--------|--------|
| `fetchInvestmentData` | Loads accounts, goals, risk profile |
| `analyseInvestment` | Runs full analysis + recommendations |
| `createAccount` | Creates account, refreshes analysis + net worth |
| `updateAccount` | Updates account, refreshes analysis + net worth |
| `deleteAccount` | Deletes account, refreshes analysis + net worth |
| `createHolding` / `updateHolding` / `deleteHolding` | Holding CRUD + analysis refresh |
| `createGoal` / `updateGoal` / `deleteGoal` | Goal CRUD |
| `saveRiskProfile` | Save risk profile + refresh analysis |
| `startMonteCarlo` | Start simulation, track status |
| `pollMonteCarloResults` | Poll with status updates |
| `generateInvestmentPlan` | Generate comprehensive plan |
| `fetchRecommendationsDashboard` | Load recommendations dashboard |
| `fetchInvestmentRecommendations` | Load recommendations with filters |
| `fetchScenarioTemplates` | Load pre-built templates |
| `fetchInvestmentScenarios` | Load user scenarios |
| `runInvestmentScenario` | Run scenario with MC |

**Cross-Store Integration:**
- Account CRUD dispatches `netWorth/refreshNetWorth` (root: true) to update wealth summary
- ISA allowance reads from `rootState.savings.isaAllowance.total_allowance`

---

## 9. API Service

### `resources/js/services/investmentService.js` (1,505 lines)

100+ methods organised by domain:

| Domain | Method Count | Key Methods |
|--------|-------------|-------------|
| Core | 4 | `getInvestmentData`, `analyzeInvestment`, `getRecommendations`, `runScenario` |
| Monte Carlo | 2 | `startMonteCarlo`, `getMonteCarloResults` |
| Account CRUD | 4 | `createAccount`, `updateAccount`, `deleteAccount`, `toggleRetirementInclusion` |
| Holding CRUD | 3 | `createHolding`, `updateHolding`, `deleteHolding` |
| Goal CRUD | 3 | `createGoal`, `updateGoal`, `deleteGoal` |
| Risk | 1 | `saveRiskProfile` |
| Tax Optimisation | 7 | `analyzeTaxPosition`, `getISAStrategy`, `getCGTHarvestingOpportunities`, `getBedAndISAOpportunities`, `getTaxEfficiencyScore`, `getTaxRecommendations`, `calculatePotentialSavings` |
| Asset Location | 6 | `analyzeAssetLocation`, `getAssetLocationRecommendations`, `calculatePortfolioTaxDrag`, `getAssetLocationScore`, `compareAccountTypesForHolding` |
| Performance | 5 | `analyzePerformance`, `compareWithBenchmark`, `compareWithMultipleBenchmarks`, `getRiskMetrics` |
| Goals | 8 | `analyzeGoalProgress`, `analyzeAllGoals`, `analyzeShortfall`, `generateWhatIfScenarios`, `calculateGoalProbability`, `calculateRequiredContribution`, `getGlidePath` |
| Fees | 7 | `analyzePortfolioFees`, `analyzeHoldingFees`, `calculateOCFImpact`, `compareActiveVsPassive`, `findLowCostAlternatives`, `comparePlatforms`, `compareSpecificPlatforms` |
| Model Portfolio | 7 | `getModelPortfolio`, `getAllModelPortfolios`, `compareWithModelPortfolio`, `optimiseAllocationByAge`, `optimiseAllocationByTimeHorizon`, `getGlidePathAllocation`, `getFundRecommendations` |
| Rebalancing | 6 | `analyzeDrift`, `evaluateRebalancingStrategies`, `evaluateThresholdStrategy`, `evaluateCalendarStrategy`, `evaluateOpportunisticStrategy`, `recommendRebalancingFrequency` |
| Efficient Frontier | 8 | `calculateEfficientFrontier`, `findOptimalPortfolioByReturn`, `findOptimalPortfolioByRisk`, `compareWithEfficientFrontier`, `calculatePortfolioStatistics`, `analyzeCurrentPortfolioEfficiency`, `getDefaultAssetClassAssumptions` |
| Investment Plan | 6 | `generateInvestmentPlan`, `getLatestInvestmentPlan`, `getAllInvestmentPlans`, `getInvestmentPlanById`, `deleteInvestmentPlan` |
| Recommendations | 8 | Dashboard, CRUD, status management, bulk update |
| Scenarios | 10 | Templates, CRUD, run, results, compare, save/unsave |
| Contribution | 3 | `optimiseContributions`, `calculateAffordability`, `analyzeLumpSumVsDCA` |
| Projections | 2 | `getPortfolioProjections`, `getAccountProjections` |
| Portfolio Strategy | 2 | `getPortfolioStrategy`, `getAccountStrategy` |

---

## 10. Frontend Components

### 10.1 Investment Components (60 in `resources/js/components/Investment/`)

**Forms & Data Entry:**

| Component | Purpose |
|-----------|---------|
| `AccountForm.vue` | Main account creation/edit form |
| `StandardInvestmentFields.vue` | Fields for ISA, GIA accounts |
| `PrivateInvestmentFields.vue` | Fields for private company, crowdfunding |
| `EmployeeShareSchemeFields.vue` | Fields for SAYE, CSOP, EMI, RSU |
| `HoldingForm.vue` | Add/edit individual holdings |
| `GoalForm.vue` | Investment goal creation/edit |

**Portfolio Overview & Analysis:**

| Component | Purpose |
|-----------|---------|
| `PortfolioOverview.vue` | Main portfolio summary |
| `InvestmentOverviewCard.vue` | Portfolio value card |
| `AssetAllocationChart.vue` | Pie/donut allocation chart |
| `AllocationComparison.vue` | Target vs actual allocation |
| `GeographicAllocationMap.vue` | Geographic diversification |
| `DiversificationTab.vue` | Diversification analysis tab |
| `CorrelationMatrix.vue` | Asset correlation heatmap |

**Performance & Projections:**

| Component | Purpose |
|-----------|---------|
| `Performance.vue` | Main performance tab |
| `PerformanceAttribution.vue` | Performance attribution analysis |
| `PerformanceLineChart.vue` | Performance line chart |
| `BenchmarkComparison.vue` | Benchmark comparison view |
| `InvestmentProjectionChart.vue` | Projection chart (Monte Carlo bands) |
| `MonteCarloResults.vue` | Monte Carlo simulation results display |

**Holdings & Accounts:**

| Component | Purpose |
|-----------|---------|
| `Holdings.vue` | Holdings overview |
| `HoldingsTable.vue` | Tabular holdings display |

**Tax Optimisation:**

| Component | Purpose |
|-----------|---------|
| `TaxOptimization.vue` | Main tax optimisation container |
| `TaxOptimizationOverview.vue` | Tax overview summary |
| `TaxOptimizationRecommendations.vue` | Tax recommendations list |
| `TaxEfficiencyPanel.vue` | Tax efficiency score panel |
| `TaxFees.vue` | Combined tax/fee view |
| `ISAOptimizationStrategy.vue` | ISA strategy recommendations |
| `ISATransferModal.vue` | ISA transfer wizard |
| `CGTHarvestingOpportunities.vue` | CGT loss harvesting |
| `HarvestLossModal.vue` | Loss harvesting action modal |
| `BedAndISATransfers.vue` | Bed and ISA transfer list |
| `BedAndISAWizardModal.vue` | Bed and ISA transfer wizard |
| `BondWrapperInfoModal.vue` | Bond wrapper information |
| `WrapperOptimizer.vue` | Tax wrapper optimisation |

**Fees:**

| Component | Purpose |
|-----------|---------|
| `FeeBreakdown.vue` | Fee breakdown display |
| `FeeSavingsCalculator.vue` | Fee savings calculator |

**Goals:**

| Component | Purpose |
|-----------|---------|
| `Goals.vue` | Goals overview |
| `GoalCard.vue` | Individual goal card |
| `GoalProjection.vue` | Goal projection chart |

**Rebalancing:**

| Component | Purpose |
|-----------|---------|
| `RebalancingCalculator.vue` | Rebalancing calculations |
| `RebalancingActions.vue` | Action list with execution tracking |

**Scenarios & Recommendations:**

| Component | Purpose |
|-----------|---------|
| `WhatIfScenarios.vue` | What-if scenario display |
| `WhatIfScenariosBuilder.vue` | Scenario builder UI |
| `Recommendations.vue` | Recommendations display |
| `InvestmentRecommendationsTracker.vue` | Recommendation tracking dashboard |
| `StrategyRecommendationCard.vue` | Individual strategy card |

**Comprehensive Plan:**

| Component | Purpose |
|-----------|---------|
| `ComprehensiveInvestmentPlan.vue` | Full plan display |
| `AccountStrategyCard.vue` | Per-account strategy |

**Plan Sections (6 in `PlanSections/`):**

| Component | Purpose |
|-----------|---------|
| `CurrentSituationSection.vue` | Current portfolio analysis |
| `RiskAnalysisSection.vue` | Risk analysis section |
| `TaxStrategySection.vue` | Tax strategy section |
| `FeeAnalysisSection.vue` | Fee analysis section |
| `GoalProgressSection.vue` | Goal progress section |
| `RecommendationsSection.vue` | Recommendations section |
| `ActionPlanSection.vue` | Action plan section |

**Portfolio Optimisation:**

| Component | Purpose |
|-----------|---------|
| `PortfolioOptimization.vue` | MPT optimisation view |
| `PortfolioOptimizer.vue` | Optimiser controls |
| `EfficientFrontier.vue` | Efficient frontier chart |
| `AssetLocationOptimizer.vue` | Asset location view |
| `ContributionPlanner.vue` | Contribution planning view |

### 10.2 Account Detail Views (9 in `resources/js/views/Investment/`)

| View | Purpose |
|------|---------|
| `AccountDetailView.vue` | Main account detail container with tab navigation |
| `AccountSummaryPanel.vue` | Account overview panel |
| `AccountHoldingsPanel.vue` | Holdings for specific account |
| `AccountPerformancePanel.vue` | Account performance metrics |
| `AccountFeesPanel.vue` | Account fee analysis |
| `AccountRebalancingPanel.vue` | Account rebalancing |
| `PortfolioStrategyPanel.vue` | Account strategy recommendations |
| `PrivateInvestmentDetail.vue` | Private company/crowdfunding detail |
| `EmployeeShareSchemeDetail.vue` | Share scheme detail (SAYE, CSOP, EMI, RSU) |

### 10.3 Net Worth Integration Components

| Component | Location | Purpose |
|-----------|----------|---------|
| `InvestmentList.vue` | `components/NetWorth/` | Investment accounts list in Net Worth |
| `InvestmentProjections.vue` | `components/NetWorth/` | Investment projections from Net Worth |

---

## 11. Frontend Routing

Investment is accessed through the Net Worth module (no standalone `/investment` route):

```javascript
// Main routes
'/net-worth/investments'           -> NetWorthInvestments (InvestmentList.vue)
'/net-worth/investment-detail'     -> InvestmentDetail (InvestmentProjections.vue)

// Redirect: /investment -> /net-worth/investments
'/investment'                      -> redirect to '/net-worth/investments'

// Plans route
'/plans/investment-savings'        -> InvestmentSavingsPlan view

// Preview mode equivalents
'/preview/net-worth/investments'   -> PreviewNetWorthInvestments
'/preview/net-worth/investment-detail' -> PreviewInvestmentDetail
'/preview/investment'              -> redirect to '/preview/net-worth/investments'
```

**Module mapping:** Both `/investment` and `/net-worth/investments` map to `'investment'` module.

---

## 12. Cross-Module Integration

### 12.1 Retirement Module

- **`include_in_retirement` toggle:** Boolean on InvestmentAccount, controls visibility in Retirement Income Planner
- **RetirementIncomeService** queries:
  - Investment ISAs: `InvestmentAccount::where('include_in_retirement', true)->where('account_type', 'isa')`
  - Onshore bonds: `where('account_type', 'onshore_bond')`
  - Offshore bonds: `where('account_type', 'offshore_bond')`
  - GIA accounts: `where('account_type', 'gia')`
- **RetirementStrategyService** also queries investment accounts marked for retirement
- **Holding polymorphism:** DCPension model also has `morphMany(Holding::class, 'holdable')`, sharing the holdings infrastructure

### 12.2 Estate Module

- **IHTCalculationService** sums `InvestmentAccount::where('user_id', $user->id)->sum('current_value')` for estate value
- **EstateAssetAggregatorService** includes investment accounts in asset aggregation
- Both services handle spouse accounts and projected values

### 12.3 Net Worth Module

- **NetWorthService** includes investment accounts as an asset category:
  - Calculates total investment value
  - Provides per-account breakdown (id, name, type, provider, value, ownership)
  - Handles joint investments with ownership_percentage

### 12.4 Savings Module

- ISA allowance is shared: `rootState.savings.isaAllowance.total_allowance` (from API)
- Investment ISA subscriptions tracked via `isa_subscription_current_year` field
- Combined allowance tracking: Cash ISA + Stocks & Shares ISA subscriptions

---

## 13. Onboarding Integration

Investment data is captured during onboarding in several steps:

| Step | File | Investment Data |
|------|------|-----------------|
| Assets | `Onboarding/steps/AssetsStep.vue` | Investment accounts, values |
| Income | `Onboarding/steps/IncomeStep.vue` | Investment income |
| Expenditure | `Onboarding/steps/ExpenditureStep.vue` | Investment-related expenses |
| Focus Areas | `Onboarding/FocusAreaSelection.vue` | Investment module focus |
| Completion | `Onboarding/steps/CompletionStep.vue` | Investment summary |

---

## 14. Seeder Data

### PreviewUserSeeder (`database/seeders/PreviewUserSeeder.php`)

The `createInvestmentAccounts()` method (line 760) seeds investment data:

**Pattern:**
- Iterates over persona account definitions
- Determines owner (user vs spouse) via `determineAccountOwner()`
- Joint accounts: sets `joint_owner_id`, defaults to 50% ownership
- ISA/LISA: populates `isa_subscription_current_year`
- Stores FULL value (single-record pattern, no reciprocal)
- Creates holdings with polymorphic `holdable_type = InvestmentAccount::class`

**Fields seeded per account:**
- `account_name`, `provider`, `account_type`, `current_value`, `contributions_ytd`
- `monthly_contribution_amount`, `contribution_frequency`
- `planned_lump_sum_amount`, `planned_lump_sum_date`
- `isa_subscription_current_year`, `tax_year` (2025/26)
- `ownership_type`, `ownership_percentage`, `joint_owner_id`
- `platform_fee_percent`, `advisor_fee_percent`
- `risk_preference`, `has_custom_risk`

**Holdings seeded per holding:**
- `security_name`, `ticker`, `isin`, `asset_type`
- `quantity`, `purchase_price`, `current_price`, `current_value`
- `cost_basis`, `allocation_percent`, `ocf_percent`

---

## 15. API Routing

Full route tree under `Route::middleware('auth:sanctum')->prefix('investment')`:

```
GET    /                                    -> index (accounts list)
POST   /analyze                             -> analyze
GET    /recommendations                     -> recommendations
POST   /scenarios                           -> scenarios

# Portfolio Strategy
GET    /portfolio-strategy                  -> PortfolioStrategyController@index
GET    /portfolio-strategy/account/{id}     -> PortfolioStrategyController@forAccount

# Monte Carlo
POST   /monte-carlo                         -> startMonteCarlo
GET    /monte-carlo/{jobId}                 -> getMonteCarloResults

# Projections
POST   /projections                         -> InvestmentProjectionController@getProjections

# Accounts
POST   /accounts                            -> storeAccount
PUT    /accounts/{id}                       -> updateAccount
DELETE /accounts/{id}                       -> destroyAccount
GET    /accounts/{id}/projections           -> getAccountProjections
GET    /accounts/{id}/rebalancing           -> RebalancingCalculationController@getAccountRebalancing
PATCH  /accounts/{id}/rebalancing-threshold -> RebalancingCalculationController@updateRebalancingThreshold
GET    /accounts/{id}/diversification       -> getAccountDiversification
PATCH  /accounts/{id}/toggle-retirement     -> toggleRetirementInclusion

# Holdings
POST   /holdings                            -> storeHolding
PUT    /holdings/{id}                       -> updateHolding
DELETE /holdings/{id}                       -> destroyHolding

# Goals (CRUD)
POST   /goals                               -> storeGoal
PUT    /goals/{id}                          -> updateGoal
DELETE /goals/{id}                          -> destroyGoal

# Risk Profile
POST   /risk-profile                        -> storeOrUpdateRiskProfile

# Portfolio Optimization / MPT
POST   /optimization/efficient-frontier     -> calculateEfficientFrontier
GET    /optimization/current-position       -> getCurrentPosition
GET    /optimization/correlation-matrix     -> getCorrelationMatrix
POST   /optimization/minimize-variance      -> optimizeMinimumVariance
POST   /optimization/maximize-sharpe        -> optimizeMaximumSharpe
POST   /optimization/target-return          -> optimizeTargetReturn
POST   /optimization/risk-parity            -> optimizeRiskParity
DELETE /optimization/clear-cache            -> clearCache

# Rebalancing
POST   /rebalancing/calculate              -> calculateRebalancing
POST   /rebalancing/from-optimization      -> calculateFromOptimization
POST   /rebalancing/compare-cgt            -> compareCGTStrategies
POST   /rebalancing/within-cgt-allowance   -> rebalanceWithinCGTAllowance
POST   /rebalancing/analyze-drift          -> analyzeDrift
POST   /rebalancing/evaluate-strategies    -> evaluateStrategies
POST   /rebalancing/threshold-strategy     -> evaluateThresholdStrategy
POST   /rebalancing/calendar-strategy      -> evaluateCalendarStrategy
POST   /rebalancing/opportunistic-strategy -> evaluateOpportunisticStrategy
POST   /rebalancing/recommend-frequency    -> recommendFrequency
GET    /rebalancing/actions                -> getRebalancingActions
POST   /rebalancing/save                   -> saveRebalancingActions
PUT    /rebalancing/actions/{id}           -> updateRebalancingAction
DELETE /rebalancing/actions/{id}           -> deleteRebalancingAction

# Contribution Planning
POST   /contribution/optimize              -> optimize
POST   /contribution/affordability         -> affordability
POST   /contribution/lump-sum-vs-dca      -> lumpSumVsDCA

# Tax Optimization
GET    /tax-optimization/analyze           -> analyzeTaxPosition
GET    /tax-optimization/isa-strategy      -> getISAStrategy
GET    /tax-optimization/cgt-harvesting    -> getCGTHarvestingOpportunities
GET    /tax-optimization/bed-and-isa       -> getBedAndISAOpportunities
GET    /tax-optimization/efficiency-score  -> getTaxEfficiencyScore
GET    /tax-optimization/recommendations   -> getRecommendations
POST   /tax-optimization/calculate-savings -> calculatePotentialSavings
DELETE /tax-optimization/clear-cache       -> clearCache

# Asset Location
GET    /asset-location/analyze             -> analyzeAssetLocation
GET    /asset-location/recommendations     -> getRecommendations
GET    /asset-location/tax-drag            -> calculateTaxDrag
GET    /asset-location/optimization-score  -> getOptimizationScore
POST   /asset-location/compare-accounts    -> compareAccountTypes
DELETE /asset-location/clear-cache         -> clearCache

# Performance Attribution
GET    /performance/analyze                -> analyzePerformance
GET    /performance/benchmark              -> compareWithBenchmark
GET    /performance/multi-benchmark        -> compareWithMultipleBenchmarks
GET    /performance/risk-metrics           -> getRiskMetrics
DELETE /performance/clear-cache            -> clearCache

# Goal Progress
GET    /goals/{goalId}/progress            -> analyzeGoalProgress
GET    /goals/progress/all                 -> analyzeAllGoals
GET    /goals/{goalId}/shortfall           -> analyzeShortfall
POST   /goals/{goalId}/what-if             -> generateWhatIfScenarios
POST   /goals/calculate-probability        -> calculateProbability
POST   /goals/required-contribution        -> calculateRequiredContribution
GET    /goals/glide-path                   -> getGlidePath
DELETE /goals/clear-cache                  -> clearCache

# Fee Impact
GET    /fees/analyze                       -> analyzePortfolioFees
GET    /fees/holdings                      -> analyzeHoldingFees
POST   /fees/ocf-impact                    -> calculateOCFImpact
GET    /fees/active-vs-passive             -> compareActiveVsPassive
GET    /fees/alternatives/{holdingId}      -> findAlternatives
GET    /fees/compare-platforms             -> comparePlatforms
POST   /fees/compare-specific             -> compareSpecificPlatforms
DELETE /fees/clear-cache                   -> clearCache

# Risk Preference (5-level)
GET    /risk/levels                        -> getLevels
GET    /risk/profile                       -> getProfile
POST   /risk/profile                       -> setProfile
POST   /risk/recalculate                   -> recalculate
GET    /risk/allowed-levels                -> getAllowedLevels
POST   /risk/validate-product-level        -> validateProductLevel
GET    /risk/config/{level}                -> getRiskConfig

# Model Portfolio
GET    /model-portfolio/{riskLevel}        -> getModelPortfolio
GET    /model-portfolio/all                -> getAllPortfolios
POST   /model-portfolio/compare            -> compareWithModel
GET    /model-portfolio/optimize-by-age    -> optimizeByAge
POST   /model-portfolio/optimize-by-horizon -> optimizeByTimeHorizon
GET    /model-portfolio/glide-path         -> getGlidePath
POST   /model-portfolio/funds              -> getFundRecommendations

# Efficient Frontier / MPT
POST   /efficient-frontier/calculate       -> calculateEfficientFrontier
GET    /efficient-frontier/default         -> calculateWithDefaults
POST   /efficient-frontier/optimal-by-return -> findOptimalByReturn
POST   /efficient-frontier/optimal-by-risk -> findOptimalByRisk
POST   /efficient-frontier/compare         -> compareWithFrontier
POST   /efficient-frontier/statistics      -> calculateStatistics
GET    /efficient-frontier/analyze-current -> analyzeCurrentPortfolio
GET    /efficient-frontier/default-assumptions -> getDefaultAssumptions

# Investment Plans
POST   /plan/generate                      -> generatePlan
GET    /plan                               -> getLatestPlan
GET    /plan/all                           -> getAllPlans
GET    /plan/{id}                          -> getPlanById
DELETE /plan/{id}                          -> deletePlan
DELETE /plan/clear-cache                   -> clearCache

# Recommendations
GET    /recommendations/dashboard          -> dashboard
GET    /recommendations                    -> index
POST   /recommendations                    -> store
GET    /recommendations/{id}               -> show
PUT    /recommendations/{id}               -> update
DELETE /recommendations/{id}               -> destroy
PUT    /recommendations/{id}/status        -> updateStatus
POST   /recommendations/bulk-update-status -> bulkUpdateStatus

# Scenarios
GET    /scenarios/templates                -> templates
GET    /scenarios                           -> index
POST   /scenarios                           -> store
GET    /scenarios/{id}                     -> show
PUT    /scenarios/{id}                     -> update
DELETE /scenarios/{id}                     -> destroy
POST   /scenarios/{id}/run                 -> run
GET    /scenarios/{id}/results             -> results
POST   /scenarios/compare                  -> compare
POST   /scenarios/{id}/save               -> save
POST   /scenarios/{id}/unsave             -> unsave
```

**Total: ~130 API endpoints across 14 route groups**

---

## 16. Key Constants & Assumptions

### Risk Level System (5-Level)

| Level | Label | Equity | Bond | Cash |
|-------|-------|--------|------|------|
| 1 | Low | 10% | 70% | 20% |
| 2 | Lower Medium | 30% | 55% | 15% |
| 3 | Medium | 50% | 40% | 10% |
| 4 | Upper Medium | 75% | 20% | 5% |
| 5 | High | 90% | 5% | 5% |

**Product Risk Override:** Accounts can have `has_custom_risk = true` with `risk_preference` set to a level within +/- 1 of the user's main level.

### Monte Carlo Defaults

| Parameter | Default |
|-----------|---------|
| Iterations | 1,000 |
| Cache duration | 24 hours (DB) |
| Projection periods | 5, 10, 20, 30 years |
| Percentile bands | 10th, 15th, 20th, 25th, 50th, 75th, 90th |
| Job class | `RunMonteCarloSimulation` (ShouldQueue, timeout 300s, tries 1) |
| Status tracking | Laravel Cache: `monte_carlo_status_{jobId}` |
| Result storage | Laravel Cache: `monte_carlo_results_{jobId}` |

### Risk-to-Return Parameters
Investment projections use risk-level-specific return and volatility assumptions from `RiskPreferenceService`. See **risk.md Section 2** for the complete risk level definitions including asset allocation percentages, expected return ranges, and volatility figures.

The priority chain for resolving risk: account.risk_preference -> user's main risk_level -> default 'medium'.

### Rebalancing Thresholds

| Parameter | Default |
|-----------|---------|
| Account threshold | 10% drift |
| Deviation warning | > 5% per asset class |
| Needs rebalancing | > 15% total deviation |
| Minimum trade size | > 5% of portfolio |

### Fee Thresholds

| Parameter | Threshold |
|-----------|-----------|
| High OCF warning | > 0.5% |
| High platform fee | > 0.8% |
| Fee saving recommendation | > 50 potential saving |
| Transaction cost estimate | 0.1% |

### Diversification (HHI)

| HHI Score | Classification |
|-----------|---------------|
| < 0.15 | Well Diversified |
| 0.15 - 0.25 | Moderate Concentration |
| > 0.25 | High Concentration |

### Bond Wrapper Thresholds

| Wrapper | Minimum Balance |
|---------|----------------|
| Onshore bond | 50,000 |
| Offshore bond | 100,000 |

### Tax Wrapper Priority (Asset Location)

1. ISA (tax-free growth and income)
2. Pension (tax relief, but restricted access)
3. Onshore bond (5% annual withdrawal allowance)
4. Offshore bond (gross roll-up)
5. GIA (fully taxable)

### Account Types

| Type | Tax Treatment | Joint Allowed |
|------|--------------|---------------|
| `isa` | Tax-free | No (individual only) |
| `gia` | CGT + income tax | Yes |
| `onshore_bond` | Corporation tax + top-slicing | Yes |
| `offshore_bond` | Gross roll-up | Yes |
| `private_company` | Various (EIS/SEIS relief possible) | N/A |
| `crowdfunding` | Various | N/A |
| `saye` | Tax-free if held to maturity | N/A |
| `csop` | CGT only if 3+ year hold | N/A |
| `emi` | CGT only | N/A |
| `unapproved_options` | Income tax + NIC | N/A |
| `rsu` | Income tax at vest, CGT on gain | N/A |

---

## 17. Known Issues & Considerations

### Simplifications in Current Implementation

1. **PortfolioAnalyzer returns calculation** - Simplified YTD return, not time-weighted
2. **DB allocation for asset classes** - Uses basic asset_type grouping rather than look-through to underlying assets
3. **Monte Carlo model** - Single-factor (mean + volatility), no correlation between asset classes
4. **Transaction cost estimation** - Flat 0.1% assumption, not provider-specific
5. **Dividend tax** - Simplified calculation, does not account for dividend allowance stages

### Architecture Notes

1. **Polymorphic holdings** - Shared between InvestmentAccount and DCPension; changes to `holdings` table schema affect both
2. **Single-record pattern** - Joint accounts store FULL value once. Ownership % determines user's share. No reciprocal records.
3. **ISA validation** - Controller enforces individual-only for ISAs at creation, but existing data may not have this constraint
4. **Cache invalidation** - Analysis cache tagged with `['investment', 'user_{userId}']`. Must be cleared after any data change.
5. **Auto-calculations on store** - EIS/SEIS disposal date, CSOP 3-year date, SAYE maturity date all auto-calculated from grant dates

### InvestmentAccountResource Conditional Fields

The API resource conditionally includes fields based on account type:
- ISA fields only when `account_type` contains `'isa'`
- Employee share scheme fields only when `isEmployeeShareScheme()` returns true
- `account_type_other` only when `account_type === 'other'`

---

## 18. Employee Share Scheme Details

### Scheme Types

| Scheme | Tax Treatment | Key Dates |
|--------|--------------|-----------|
| **SAYE** | Tax-free if held to maturity. Savings plan with option to buy at discount | `saye_maturity_date` = grant + savings_term |
| **CSOP** | CGT only if exercised 3+ years after grant (tax-advantaged window) | `csop_three_year_date` = grant + 3 years |
| **EMI** | CGT only (qualified). Income tax on discount at grant if below market value | `vesting_date`, `exercise_window_end` |
| **Unapproved Options** | Income tax + NIC on exercise gain, CGT on subsequent disposal | `exercise_window_end` |
| **RSU** | Income tax at vesting on full value. CGT on disposal gain above vesting price | `vesting_date` |

### Auto-Calculations in Controller

```php
// EIS/SEIS: 3-year disposal restriction
if (in_array($type, ['eis', 'seis'])) {
    $data['disposal_restriction_date'] = Carbon::parse($data['investment_date'])->addYears(3);
}

// CSOP: 3-year tax advantage window
if ($type === 'csop' && isset($data['grant_date'])) {
    $data['csop_three_year_date'] = Carbon::parse($data['grant_date'])->addYears(3);
}

// SAYE: Maturity from savings term
if ($type === 'saye' && isset($data['grant_date']) && isset($data['savings_term_months'])) {
    $data['saye_maturity_date'] = Carbon::parse($data['grant_date'])->addMonths($data['savings_term_months']);
}
```

### Key Computed Properties

- **Intrinsic Value:** `(current_share_price - exercise_price) * remaining_units`
  - Only for options schemes (SAYE, CSOP, EMI, Unapproved)
  - Returns 0 if underwater (exercise price > current price)
- **Scheme Current Value:** `current_share_price * units_vested`
  - Full market value of vested units
- **Remaining Units:** `units_vested - units_exercised`
- **CSOP Tax Window:** `isInCsopTaxAdvantageWindow()` checks if 3+ years from grant
