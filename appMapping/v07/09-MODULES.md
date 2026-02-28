# Module Guide

Fynla v0.7.0 contains seven modules. Each follows the same pattern: a Vue frontend communicates through API services to Laravel controllers, which delegate to an Agent that orchestrates domain-specific Services operating on Eloquent Models. All agents extend `BaseAgent`, which provides caching, currency formatting, compound growth calculations, and a standardised response format.

Every agent implements three abstract methods: `analyze()`, `generateRecommendations()`, and `buildScenarios()`. The system invalidates cache per-user with optional tag support for Redis/Memcached stores.

---

## 1. Protection Module

### Purpose

Analyses insurance needs across five policy types (life, critical illness, income protection, disability, sickness/illness) against existing coverage. Identifies gaps, scores adequacy, generates priority-ranked recommendations, and models what-if scenarios for death, critical illness, and disability events.

### Data Model

| Model | Key Fields |
|-------|------------|
| `ProtectionProfile` | `annual_income`, `monthly_expenditure`, `mortgage_balance`, `other_debts`, `number_of_dependents`, `retirement_age` |
| `LifeInsurancePolicy` | `policy_type` (term, whole_of_life, decreasing_term, family_income_benefit, level_term), `sum_assured`, `premium_amount`, `premium_frequency`, `in_trust`, `is_mortgage_protection` |
| `CriticalIllnessPolicy` | `policy_type`, `sum_assured`, `premium_amount`, `premium_frequency` |
| `IncomeProtectionPolicy` | `benefit_amount`, `benefit_frequency`, `deferred_period_weeks`, `coverage_percent` |
| `DisabilityPolicy` | `benefit_amount`, `benefit_frequency`, `deferred_period_weeks`, `coverage_type` |
| `SicknessIllnessPolicy` | `benefit_amount`, `benefit_frequency`, `conditions_covered` |

### Agent: ProtectionAgent

**File:** `app/Agents/ProtectionAgent.php`

**Dependencies:** CoverageGapAnalyzer, AdequacyScorer, RecommendationEngine, ScenarioBuilder, ProfileCompletenessChecker

**Cache:** Tagged with `['protection', 'user_<id>']`, standard TTL.

The agent eager-loads the user with all six policy relationships, then runs gap analysis, adequacy scoring, recommendations, and scenario modelling in a single cached `analyze()` call. It also returns a debt breakdown (mortgage + other liabilities) and profile completeness data.

### Services

| Service | File | Responsibility |
|---------|------|----------------|
| `CoverageGapAnalyzer` | `app/Services/Protection/CoverageGapAnalyzer.php` | Calculates human capital value (net income * years to retirement), debt protection need (mortgage + other debts), education funding (GBP 9,000/year per child to age 21), final expenses (GBP 7,500 fixed). Compares calculated needs against total coverage across all policy types. Uses `UKTaxCalculator` for net income derivation. |
| `AdequacyScorer` | `app/Services/Protection/AdequacyScorer.php` | Produces a 0-100 adequacy score with textual insights. Factors in dependant status. |
| `RecommendationEngine` | `app/Services/Protection/RecommendationEngine.php` | Generates priority-ranked insurance recommendations based on identified gaps. |
| `ScenarioBuilder` | `app/Services/Protection/ScenarioBuilder.php` | Models three scenario types: death, critical illness, disability. Also supports premium change scenarios with user-supplied new coverage parameters. |
| `ComprehensiveProtectionPlanService` | `app/Services/Protection/ComprehensiveProtectionPlanService.php` | Generates a full protection plan combining gap analysis, recommendations, and scenarios. |

### Key Calculations

**Human capital value:**
```
net_income * 10 * min(years_to_retirement, 10)
```

**Debt protection need:**
```
mortgage_balance + other_debts
```
Falls back to actual mortgage and liability records if profile summary fields are zero.

**Education funding:**
```
sum(GBP 9,000 * max(0, 21 - child_age)) for each child
```

**Final expenses:** Fixed GBP 7,500.

**Total annual income** (for reference): employment + self-employment + rental + dividend + other income.

### Frontend Components (15)

| Component | Purpose |
|-----------|---------|
| `PolicyCard` | Summary card for a single policy |
| `PolicyDetail` | Detailed policy view |
| `PolicyDetails` | Policy details panel |
| `PolicyFormModal` | Create/edit policy form |
| `CurrentSituation` | Current protection overview tab |
| `GapAnalysis` | Gap analysis tab |
| `Recommendations` | Recommendations tab |
| `RecommendationCard` | Single recommendation display |
| `CoverageAdequacyGauge` | Visual adequacy score gauge |
| `CoverageGapChart` | Chart showing gaps by policy type |
| `CoverageTimelineChart` | Coverage over time visualisation |
| `PremiumBreakdownChart` | Premium allocation chart |
| `ScenarioBuilder` | What-if scenario configuration |
| `WhatIfScenarios` | Scenario results display |
| `ProtectionOverviewCard` | Dashboard overview card |

**Vuex Store:** `protection.js` -- state includes profile, policies by type, analysis results, recommendations. Key getters: `adequacyScore`, `totalMonthlyPremium`, `allPolicies`, `activePolicies`.

### API Endpoints

| Method | Endpoint | Action |
|--------|----------|--------|
| GET | `/api/protection` | List all protection data |
| POST | `/api/protection/analyze` | Run full analysis |
| GET | `/api/protection/recommendations` | Get recommendations |
| POST | `/api/protection/scenarios` | Run what-if scenarios |
| GET | `/api/protection/comprehensive-plan` | Full protection plan |
| POST | `/api/protection/profile` | Create/update profile |
| PATCH | `/api/protection/profile/has-no-policies` | Mark user has no policies |
| POST/PUT/DELETE | `/api/protection/policies/life/{id}` | Life policy CRUD |
| POST/PUT/DELETE | `/api/protection/policies/critical-illness/{id}` | Critical illness policy CRUD |
| POST/PUT/DELETE | `/api/protection/policies/income-protection/{id}` | Income protection policy CRUD |
| POST/PUT/DELETE | `/api/protection/policies/disability/{id}` | Disability policy CRUD |
| POST/PUT/DELETE | `/api/protection/policies/sickness-illness/{id}` | Sickness/illness policy CRUD |

### Cross-Module Links

- **Coordination:** CoordinatingAgent checks `protection_vs_savings_conflict` when protection premiums compete with savings targets for available surplus.
- **Estate:** Life insurance policies written in trust bypass the estate for IHT purposes. The estate module uses life cover amounts in its IHT mitigation strategy (Steps 3 and 5 of the 7-step decision tree).

---

## 2. Savings Module

### Purpose

Tracks cash savings accounts, monitors ISA allowance usage across savings and investment accounts, assesses emergency fund adequacy, analyses liquidity distribution, compares interest rates to market benchmarks, and tracks savings goals progress.

### Data Model

| Model | Key Fields |
|-------|------------|
| `SavingsAccount` | `account_type`, `institution`, `current_balance`, `interest_rate`, `is_isa`, `isa_type` (cash, LISA), `isa_subscription_year`, `isa_subscription_amount`, `access_type`, `notice_period_days`, `beneficiary`, `include_in_retirement`, joint ownership fields |
| `SavingsGoal` | `goal_name`, `target_amount`, `current_amount`, `target_date`, `priority` |
| `ExpenditureProfile` | `total_monthly_expenditure` (used for emergency fund calculation) |
| `ISAAllowanceTracking` | `tax_year`, `cash_isa_used`, `stocks_shares_isa_used`, `lisa_used`, `total_used`, `total_allowance` |

### Agent: SavingsAgent

**File:** `app/Agents/SavingsAgent.php`

**Dependencies:** EmergencyFundCalculator, ISATracker, GoalProgressCalculator, LiquidityAnalyzer, RateComparator

**Cache TTL:** 30 minutes (1800 seconds). Tagged with `['savings', 'user_<id>']`.

The agent queries SavingsAccount, SavingsGoal, User, and ExpenditureProfile, then runs all five service analyses. Monthly expenditure resolution follows a priority chain: ExpenditureProfile total -> User monthly_expenditure -> User annual_expenditure / 12.

### Services

| Service | File | Responsibility |
|---------|------|----------------|
| `EmergencyFundCalculator` | `app/Services/Savings/EmergencyFundCalculator.php` | Calculates runway (months), adequacy score against 6-month target, category (Critical/Fair/Good/Excellent), monthly top-up needed to reach target in N months. |
| `ISATracker` | `app/Services/Savings/ISATracker.php` | Tracks GBP 20,000 annual ISA allowance across cash ISA (from savings accounts), stocks & shares ISA (from investment accounts), and LISA. Tax year aware (April 6 to April 5). Uses `TaxConfigService` for allowance amount. |
| `LiquidityAnalyzer` | `app/Services/Savings/LiquidityAnalyzer.php` | Categorises accounts into liquidity tiers. Builds a liquidity ladder (instant access, 1-3 months, 3-12 months, locked). Produces summary with risk level assessment. |
| `RateComparator` | `app/Services/Savings/RateComparator.php` | Compares each account's interest rate to market benchmarks. Calculates annual interest difference if the user switched to market rate. Categorises rate as Poor/Fair/Good/Excellent. |
| `GoalProgressCalculator` | `app/Services/Savings/GoalProgressCalculator.php` | Calculates progress percentage, months remaining, and projects goal achievement timeline. Prioritises goals by target date and priority. |

### Key Calculations

**Emergency fund runway:**
```
total_savings / monthly_expenditure
```

**ISA remaining:**
```
GBP 20,000 - (cash_isa_used + stocks_shares_isa_used + lisa_used)
```
for the current tax year. Cross-module: stocks & shares ISA usage comes from `InvestmentAccount` records.

**Future value with contributions:**
```
PV * (1 + r)^n + PMT * (((1 + r)^n - 1) / r)
```
where `r` = monthly rate, `n` = months.

### Frontend Components (12)

| Component | Purpose |
|-----------|---------|
| `CurrentSituation` | Overview of savings position |
| `AccountDetails` | Single account detail view |
| `SaveAccountModal` | Create/edit savings account form |
| `EmergencyFund` | Emergency fund analysis tab |
| `EmergencyFundGauge` | Visual emergency fund gauge |
| `ISAAllowanceTracker` | ISA usage tracking display |
| `SavingsGoals` | Goals list for savings module |
| `SaveGoalModal` | Create/edit savings goal form |
| `InterestRateComparisonChart` | Rate comparison visualisation |
| `Recommendations` | Savings recommendations tab |
| `WhatIfScenarios` | Scenario modelling |
| `SavingsOverviewCard` | Dashboard overview card |

**Vuex Store:** `savings.js` -- state includes accounts, goals, expenditureProfile, isaAllowance. Key getters: `totalSavings` (joint-weighted), `emergencyFundRunway`, `isaAllowanceRemaining`, `isaUsagePercent`.

### API Endpoints

| Method | Endpoint | Action |
|--------|----------|--------|
| GET | `/api/savings` | List all savings data |
| POST | `/api/savings/analyze` | Run full analysis |
| GET | `/api/savings/recommendations` | Get recommendations |
| POST | `/api/savings/scenarios` | Run what-if scenarios |
| GET | `/api/savings/isa-allowance/{taxYear}` | ISA allowance status |
| POST/GET/PUT/DELETE | `/api/savings/accounts/{id}` | Account CRUD |
| PATCH | `/api/savings/accounts/{id}/toggle-retirement` | Toggle retirement inclusion |
| GET/POST/PUT/DELETE | `/api/savings/goals/{id}` | Goal CRUD |
| PATCH | `/api/savings/goals/{id}/progress` | Update goal progress |

### Cross-Module Links

- **Investment:** ISA allowance is shared -- the ISATracker queries both `SavingsAccount` and `InvestmentAccount` to calculate total ISA usage against the combined GBP 20,000 limit.
- **Retirement:** Accounts with `include_in_retirement = true` feed into retirement income calculations.
- **Goals:** Goals assigned to the savings module appear on the savings dashboard.
- **Coordination:** Emergency fund adequacy feeds into CoordinatingAgent priority ranking. Low emergency fund elevates savings priority above investment recommendations.

---

## 3. Investment Module

### Purpose

Portfolio analysis covering total value, returns, asset allocation, diversification scoring, fee analysis, tax efficiency, Monte Carlo projections, risk profiling, rebalancing recommendations, contribution optimisation, and what-if scenarios. The largest module by component count.

### Data Model

| Model | Key Fields |
|-------|------------|
| `InvestmentAccount` | `account_type` (isa, gia, onshore_bond, offshore_bond, plus BADR, private investment, employee share scheme fields), `provider`, `platform`, `current_value`, `monthly_contribution_amount`, `platform_fee_percent`, `advisor_fee_percent`, `isa_subscription_current_year`, `risk_preference`, `include_in_retirement`, `rebalance_threshold_percent`, joint ownership fields, trust linkage. 100+ fillable fields total. |
| `Holding` (polymorphic) | `security_name`, `isin`, `security_type`, `quantity`, `cost_basis`, `current_price`, `current_value`, `ocf` (ongoing charges figure). Shared with DC pensions via `MorphMany`. |
| `RiskProfile` | `risk_level` (cautious/balanced/adventurous 5-level system), `capacity_for_loss`, `time_horizon`, `knowledge_level`, `is_self_assessed` |
| `InvestmentGoal` | `goal_name`, `target_amount`, `target_date` |

### Agent: InvestmentAgent

**File:** `app/Agents/InvestmentAgent.php`

**Dependencies:** PortfolioAnalyzer, MonteCarloSimulator, AssetAllocationOptimizer, FeeAnalyzer, TaxEfficiencyCalculator, TaxConfigService

**Cache:** Tagged with `['investment', 'user_<id>']`, standard TTL.

The agent eager-loads accounts with their holdings, queries risk profile and goals, then runs portfolio analysis, fee analysis, tax efficiency analysis, and allocation deviation checks. It also calculates tax wrapper summary (ISA, GIA, bonds).

### Services

| Service | File | Responsibility |
|---------|------|----------------|
| `PortfolioAnalyzer` | `app/Services/Investment/PortfolioAnalyzer.php` | Calculates total value, returns (YTD, 1yr, 3yr), asset allocation percentages, diversification score (0-100), and risk metrics (volatility, beta, Sharpe ratio). |
| `FeeAnalyzer` | `app/Services/Investment/FeeAnalyzer.php` | Calculates total annual fees, breaks down by type (fund OCF, platform, advisor), flags high-fee holdings (OCF > 0.5%) and platform fees (> 0.8%), and compares to low-cost alternatives with annual savings estimate. |
| `TaxEfficiencyCalculator` | `app/Services/Investment/TaxEfficiencyCalculator.php` | Calculates unrealised gains, scores tax efficiency (0-100), analyses ISA vs GIA allocation, and identifies tax loss harvesting opportunities with potential tax saving. |
| `AssetAllocationOptimizer` | `app/Services/Investment/AssetAllocationOptimizer.php` | Determines target allocation by risk level, analyses deviation between current and target, and flags rebalancing when deviation exceeds threshold. |
| `MonteCarloSimulator` | `app/Services/Investment/MonteCarloSimulator.php` | Runs Geometric Brownian Motion with 1,000 iterations. Produces percentiles (p10, p25, p50, p75, p90). Caches results in `monte_carlo_cache` DB table with 24-hour TTL. |
| `InvestmentProjectionService` | `app/Services/Investment/InvestmentProjectionService.php` | Projects portfolio values by combining Monte Carlo simulation with contribution schedules over configurable periods (5, 10, 20, 30 years). |
| `DiversificationAnalyzer` | `app/Services/Investment/DiversificationAnalyzer.php` | Analyses diversification across asset classes, sectors, and geographies. |
| `ContributionOptimizer` | `app/Services/Investment/ContributionOptimizer.php` | Compares lump sum vs DCA, analyses affordability, and optimises contribution strategy. |

**Additional sub-services (in subdirectories):**

- **Analytics:** `CorrelationMatrixCalculator`, `CovarianceMatrixCalculator`, `EfficientFrontierCalculator`, `PortfolioStatisticsCalculator`, `HoldingsDataExtractor`, `MarkowitzOptimizer`
- **Rebalancing:** `DriftAnalyzer`, `TaxAwareRebalancer`, `RebalancingStrategyService`, `RebalancingCalculator`
- **Tax:** `ISAAllowanceOptimizer`, `BedAndISACalculator`, `CGTHarvestingCalculator`
- **Fees:** `OCFImpactCalculator`, `PlatformComparator`
- **Goals:** `ShortfallAnalyzer`, `GoalProbabilityCalculator`, `GoalProgressAnalyzer`
- **Performance:** `BenchmarkComparator`, `AlphaBetaCalculator`, `PerformanceAttributionAnalyzer`
- **ModelPortfolio:** `ModelPortfolioBuilder`, `FundSelector`
- **AssetLocation:** `AccountTypeRecommender`

### Key Calculations

**Monte Carlo simulation (per iteration, per month):**
```
value = value * (1 + NORMAL(expected_return/12, volatility/sqrt(12))) + monthly_contribution
```
Runs 1,000 iterations. Extracts percentiles from final values.

**Diversification score:** 0-100 scale. Below 70 triggers a recommendation.

**Fee drag:**
```
total_annual_fees / portfolio_value * 100
```

**Tax efficiency:** Analyses the proportion of holdings in tax-efficient wrappers (ISA, pension) vs taxable wrappers (GIA).

### Frontend Components (57)

The investment module has the most Vue components. Key groups:

- **Portfolio overview:** PortfolioOverview, InvestmentOverviewCard, AssetAllocationChart, Performance, PerformanceLineChart
- **Holdings:** HoldingsTable, Holdings, HoldingForm
- **Accounts:** AccountForm, AccountStrategyCard, StandardInvestmentFields, PrivateInvestmentFields, EmployeeShareSchemeFields
- **Fees:** TaxFees, FeeBreakdown, FeeSavingsCalculator
- **Tax:** TaxOptimization, TaxOptimizationOverview, TaxOptimizationRecommendations, TaxEfficiencyPanel, ISAOptimizationStrategy, BedAndISATransfers, BedAndISAWizardModal, CGTHarvestingOpportunities, HarvestLossModal, AssetLocationOptimizer, WrapperOptimizer, BondWrapperInfoModal
- **Rebalancing:** RebalancingCalculator, RebalancingActions, AllocationComparison
- **Analysis:** DiversificationTab, CorrelationMatrix, EfficientFrontier, PortfolioOptimizer, PortfolioOptimization, BenchmarkComparison, PerformanceAttribution, GeographicAllocationMap
- **Projections:** InvestmentProjectionChart, MonteCarloResults, ContributionPlanner
- **Goals:** Goals, GoalCard, GoalForm, GoalProjection
- **Scenarios:** WhatIfScenarios, WhatIfScenariosBuilder
- **Recommendations:** Recommendations, StrategyRecommendationCard, InvestmentRecommendationsTracker
- **Plan:** ComprehensiveInvestmentPlan, plus PlanSections/ (CurrentSituationSection, ActionPlanSection, FeeAnalysisSection, GoalProgressSection, RecommendationsSection, RiskAnalysisSection, TaxStrategySection)

**Vuex Store:** `investment.js` (1,300+ lines, 40+ getters, 40+ actions). Key getters: `totalPortfolioValue`, `ytdReturn`, `assetAllocation`, `diversificationScore`, `totalFees`.

### API Endpoints

The investment module has the most extensive API surface. Major route groups under `/api/investment`:

| Group | Key Endpoints |
|-------|---------------|
| Core | `GET /`, `POST /analyze`, `GET /recommendations`, `POST /scenarios` |
| Accounts | `POST /accounts`, `PUT /accounts/{id}`, `DELETE /accounts/{id}`, `GET /accounts/{id}/projections`, `GET /accounts/{id}/rebalancing`, `GET /accounts/{id}/diversification`, `PATCH /accounts/{id}/toggle-retirement` |
| Holdings | `POST /holdings`, `PUT /holdings/{id}`, `DELETE /holdings/{id}` |
| Monte Carlo | `POST /monte-carlo`, `GET /monte-carlo/{jobId}` |
| Projections | `POST /projections` |
| Optimization | `POST /efficient-frontier`, `POST /minimize-variance`, `POST /maximize-sharpe`, `POST /target-return`, `POST /risk-parity`, `GET /correlation-matrix` |
| Rebalancing | `POST /rebalancing/calculate`, `POST /rebalancing/compare-cgt`, `POST /rebalancing/analyze-drift`, `POST /rebalancing/evaluate-strategies`, multiple strategy endpoints |
| Tax Optimization | `GET /tax-optimization/analyze`, `GET /tax-optimization/isa-strategy`, `GET /tax-optimization/cgt-harvesting`, `GET /tax-optimization/bed-and-isa`, `GET /tax-optimization/efficiency-score` |
| Asset Location | `GET /asset-location/analyze`, `GET /asset-location/recommendations`, `GET /asset-location/tax-drag` |
| Performance | `GET /performance/analyze`, `GET /performance/benchmark`, `GET /performance/risk-metrics` |
| Goals | `GET /goals/{id}/progress`, `GET /goals/{id}/shortfall`, `POST /goals/{id}/what-if`, `POST /goals/calculate-probability` |
| Fees | `GET /fees/analyze`, `POST /fees/ocf-impact`, `GET /fees/compare-platforms` |
| Risk | `GET /risk/levels`, `GET /risk/profile`, `POST /risk/profile`, `POST /risk/recalculate` |
| Model Portfolio | `GET /model-portfolio/{riskLevel}`, `POST /model-portfolio/compare`, `GET /model-portfolio/glide-path` |
| Efficient Frontier | `POST /efficient-frontier/calculate`, `POST /efficient-frontier/optimal-by-return`, `GET /efficient-frontier/analyze-current` |
| Plans | `POST /plan/generate`, `GET /plan`, `GET /plan/all` |
| Recommendations | `GET /recommendations/dashboard`, CRUD endpoints |
| Scenarios | `GET /scenarios/templates`, CRUD endpoints, `POST /scenarios/{id}/run`, `POST /scenarios/compare` |
| Contribution | `POST /contribution/optimize`, `POST /contribution/affordability`, `POST /contribution/lump-sum-vs-dca` |

### Cross-Module Links

- **Savings:** ISA allowance is shared (combined GBP 20,000 limit). ISATracker queries investment ISA subscriptions.
- **Retirement:** Accounts with `include_in_retirement = true` feed into retirement income calculations. Risk profile influences pension portfolio analysis.
- **Estate:** Investment account values are aggregated into estate net worth and IHT calculations.
- **Goals:** Investment-type goals are assigned to this module.
- **Coordination:** Portfolio analysis feeds into holistic planning. ISA allowance conflicts are detected by ConflictResolver.

---

## 4. Retirement Module

### Purpose

Manages DC pensions (workplace, SIPP, personal), DB pensions, and State Pension. Projects retirement income, monitors annual allowance (including tapering and MPAA), optimises contributions, analyses pension portfolio holdings, plans decumulation strategy, and calculates required capital at retirement.

### Data Model

| Model | Key Fields |
|-------|------------|
| `DCPension` | `scheme_name`, `scheme_type` (workplace/SIPP/personal), `provider`, `current_fund_value`, `employee_contribution_percent`, `employer_contribution_percent`, `monthly_contribution_amount`, `expected_return_percent`, `retirement_age`, `projected_value_at_retirement`, `risk_preference`, `beneficiary`. Has `MorphMany` holdings (shared polymorphic relation with investment). |
| `DBPension` | `scheme_name`, `scheme_type`, `accrued_annual_pension`, `pensionable_service_years`, `pensionable_salary`, `normal_retirement_age`, `revaluation_method`, `spouse_pension_percent`, `lump_sum_entitlement`, `inflation_protection` |
| `StatePension` | `estimated_annual_amount`, `ni_years_completed`, `ni_years_required`, `state_pension_age` |
| `RetirementProfile` | `target_retirement_age`, `current_age`, `target_retirement_income`, decumulation strategy fields |
| `MonteCarloCache` | `cache_key`, `results` (JSON), `expires_at` |

### Agent: RetirementAgent

**File:** `app/Agents/RetirementAgent.php`

**Dependencies:** PensionProjector, AnnualAllowanceChecker, ContributionOptimizer, DecumulationPlanner, PensionPortfolioAnalyzer, plus shared investment services (PortfolioAnalyzer, MonteCarloSimulator, AssetAllocationOptimizer, FeeAnalyzer, TaxEfficiencyCalculator)

**Cache TTL:** 1 hour (3600 seconds). Tagged with `['retirement', 'user_<id>']`.

The agent queries RetirementProfile, DCPension, DBPension, and StatePension. It projects total retirement income, calculates the income gap against target, and checks annual allowance status.

### Services

| Service | File | Responsibility |
|---------|------|----------------|
| `PensionProjector` | `app/Services/Retirement/PensionProjector.php` | Projects total retirement income from DC pensions (projected fund value * drawdown rate), DB pensions (accrued annual pension), and State Pension. Returns dc_total_value, db_annual_income, state_pension_income, total_projected_income. |
| `AnnualAllowanceChecker` | `app/Services/Retirement/AnnualAllowanceChecker.php` | Checks GBP 60,000 annual allowance. Handles tapering for high earners (threshold income from TaxConfigService, adjusted income threshold, minimum tapered allowance). Checks Money Purchase Annual Allowance (MPAA). All thresholds loaded from TaxConfigService. |
| `ContributionOptimizer` | `app/Services/Retirement/ContributionOptimizer.php` | Recommends contribution adjustments based on income gap, available allowance, and employer matching. |
| `DecumulationPlanner` | `app/Services/Retirement/DecumulationPlanner.php` | Plans retirement income drawdown strategy. |
| `PensionPortfolioAnalyzer` | `app/Services/Retirement/PensionPortfolioAnalyzer.php` | Analyses DC pension holdings for risk metrics: alpha, beta, Sharpe ratio, volatility, max drawdown, Value at Risk (VaR). Shares the same analytics framework as the investment module. |
| `RetirementProjectionService` | Referenced in CLAUDE.md | Full projections with Monte Carlo integration. |
| `RequiredCapitalCalculator` | Referenced in CLAUDE.md | Calculates required capital at retirement based on target income, life expectancy, inflation, and drawdown rate. Both future value and present value calculations. |
| `RetirementIncomeService` | Referenced in CLAUDE.md | Income configuration, custom scenarios, available accounts for drawdown. |

### Key Calculations

**DC pension projection (per year):**
```
FV = FV * (1 + expected_return - fees) + annual_contribution
```
where `annual_contribution = (salary * employee_pct) + (salary * employer_pct) + (monthly_amount * 12)`.

**Income gap:**
```
target_retirement_income - projected_total_income
```

**Increased contribution scenario:**
```
additional_value = additional_annual_contribution * ((1 + growth_rate)^years - 1) / growth_rate
new_dc_income = (dc_total_value + additional_value) * 0.04
```

**Annual allowance tapering:** For adjusted income above the threshold, allowance reduces by GBP 1 for every GBP 2 above, down to the minimum tapered allowance. All values from TaxConfigService.

### Frontend Components (20)

| Component | Purpose |
|-----------|---------|
| `UnifiedPensionForm` | Combined DC/DB pension form |
| `DCPensionForm` | DC pension-specific form |
| `DBPensionForm` | DB pension-specific form |
| `StatePensionForm` | State Pension form |
| `AccumulationChart` | Pension pot growth chart |
| `PensionPotProjectionChart` | Pension projection visualisation |
| `IncomeProjectionChart` | Projected income chart |
| `IncomeDrawdownChart` | Drawdown income chart |
| `TargetIncomeDrawdownChart` | Target vs actual income chart |
| `FundDepletionChart` | Fund depletion timeline |
| `IncomeSourceSlider` | Adjust income sources |
| `AnnualAllowanceTracker` | AA usage display |
| `DrawdownSimulator` | Interactive drawdown modelling |
| `RequiredCapitalDetail` | Required capital breakdown |
| `StrategiesTab` | Retirement strategies tab |
| `StrategyCard` | Individual strategy card |
| `TaxBreakdownCard` | Tax impact on retirement income |
| `CapitalAdequacyTab` | Capital adequacy analysis |
| `FutureValueTab` | Future value projections |
| `RetirementIncomeTab` | Retirement income planning |

**Vuex Store:** `retirement.js` -- state includes dcPensions, dbPensions, statePension, projections, strategies, requiredCapital, retirementIncome, incomeAllocations.

### API Endpoints

| Method | Endpoint | Action |
|--------|----------|--------|
| GET | `/api/retirement` | List all retirement data |
| GET | `/api/retirement/projections` | Income projections |
| GET | `/api/retirement/required-capital` | Required capital calculation |
| GET | `/api/retirement/dc-pensions/{id}/projections` | DC pension projection |
| POST | `/api/retirement/analyze` | Run full analysis |
| GET | `/api/retirement/recommendations` | Get recommendations |
| POST | `/api/retirement/scenarios` | Run what-if scenarios |
| GET | `/api/retirement/portfolio-analysis` | DC pension portfolio analytics |
| GET | `/api/retirement/annual-allowance/{taxYear}` | Annual allowance check |
| GET | `/api/retirement/strategies` | Available strategies |
| GET | `/api/retirement/strategies/impact` | Strategy impact calculation |
| GET | `/api/retirement/income` | Retirement income overview |
| POST | `/api/retirement/income/calculate` | Calculate retirement income |
| GET | `/api/retirement/income/accounts` | Available income accounts |
| POST/PUT/DELETE | `/api/retirement/pensions/dc/{id}` | DC pension CRUD |
| POST/PUT/DELETE | `/api/retirement/pensions/dc/{id}/holdings/{holdingId}` | DC pension holdings CRUD |
| POST/PUT/DELETE | `/api/retirement/pensions/db/{id}` | DB pension CRUD |
| POST | `/api/retirement/state-pension` | Update State Pension |

### Cross-Module Links

- **Savings/Investment:** Accounts with `include_in_retirement = true` feed into income calculations.
- **Estate:** DC pension values are aggregated into estate net worth. DC pensions are generally IHT-exempt (outside the estate). DB pension income ends at death (except spouse pension percentage).
- **Coordination:** CoordinatingAgent checks cashflow conflicts between pension contributions and other demands. Pension contributions compete for available surplus.
- **Investment:** Shares portfolio analytics framework. DC pension holdings use the same polymorphic `Holding` model and identical analysis services.

---

## 5. Estate Planning Module

### Purpose

Calculates current and projected IHT liability, manages gifting strategies (annual exemption, PETs, CLTs), trust planning, will and bequest management, charitable giving analysis, and life cover recommendations for IHT mitigation. Aggregates assets from all other modules to build the complete estate picture.

### Data Model

| Model | Key Fields |
|-------|------------|
| `IHTProfile` | NRB tracking fields, `nrb_transferred_from_spouse`, `rnrb_transferred_from_spouse` |
| `IHTCalculation` | Cached IHT calculation results |
| `Trust` | 10 types (bare, discretionary, interest_in_possession, accumulation_and_maintenance, mixed, charitable, vulnerable_person, disabled_person, settlor_interested, life_interest), `trust_name`, `country`, settlor/trustee/beneficiary details |
| `Bequest` | `beneficiary_type` (individual, charity, trust, organization), `amount`, `charity_registration_number` |
| `Gift` | `gift_type` (pet, clt, exempt, small_gift, annual_exemption, normal_expenditure, wedding), `gift_date`, `recipient`, `gift_value`, `donor_survival_years` |
| `Will` | Will details with `HasMany` bequests |
| `Liability` (Estate) | `type`, `amount`, `lender`, `interest_rate`, `current_balance` |
| `Asset` (Estate) | Generic estate asset |

The estate module also reads from: `Property`, `InvestmentAccount`, `SavingsAccount`, `DCPension`, `BusinessInterest`, `Chattel`, `LifeInsurancePolicy`, and `Mortgage` -- aggregated via EstateAssetAggregatorService.

### Agent: EstateAgent

**File:** `app/Agents/EstateAgent.php`

**Dependencies:** IHTCalculationService, EstateAssetAggregatorService, ComprehensiveEstatePlanService, GiftingStrategyOptimizer, PersonalizedTrustStrategyService, WillAnalysisService, TaxConfigService

**Cache:** Tagged with `['estate', 'user_<id>']`, standard TTL.

The agent eager-loads the user with ihtProfile, assets, properties, liabilities, mortgages, spouse, familyMembers, trusts, and gifts. It aggregates all estate assets, calculates IHT (if profile exists), computes the health score, and gathers trust recommendations, gifting opportunities, will analysis, and charitable bequest analysis.

### Services

| Service | File | Responsibility |
|---------|------|----------------|
| `IHTCalculationService` | `app/Services/Estate/IHTCalculationService.php` (1,290 lines) | Full current + projected IHT calculation. Uses asset-specific projection methods: cash (income/expense surplus model), investments (Monte Carlo 80% confidence or custom rate), properties (configurable growth, default 3%), liabilities (amortisation to end date). |
| `EstateAssetAggregatorService` | `app/Services/Estate/EstateAssetAggregatorService.php` | Gathers assets from all modules into a unified estate view with gross estate, net estate, total liabilities, and categorised breakdown. |
| `GiftingStrategyOptimizer` | `app/Services/Estate/GiftingStrategyOptimizer.php` | Identifies gifting opportunities: annual exemption (GBP 3,000), small gifts (GBP 250 per recipient), wedding gifts (GBP 5,000 parents, GBP 2,500 grandparents), normal expenditure out of income, PET cycles. |
| `PersonalizedTrustStrategyService` | `app/Services/Estate/PersonalizedTrustStrategyService.php` | Trust type recommendations based on family situation, estate size, and objectives. |
| `WillAnalysisService` | `app/Services/Estate/WillAnalysisService.php` | Detects will wishes that require trust structures. Analyses charitable bequests for 36% rate qualification (10%+ of net estate to charity reduces rate from 40% to 36%). |
| `LifeCoverCalculator` | `app/Services/Estate/LifeCoverCalculator.php` | Estimates whole-of-life premium for IHT cover. |
| `GiftingStrategyOptimizer` / `GiftingTimelineService` | Gift-related services | Strategy generation and timeline visualisation. |
| `CashFlowProjector` | `app/Services/Estate/CashFlowProjector.php` | Projects estate cash flow over time, integrating income, expenditure, and drawdown. |
| `FutureValueCalculator` | `app/Services/Estate/FutureValueCalculator.php` | Asset-specific future value projections. |
| `LifePolicyStrategyService` | `app/Services/Estate/LifePolicyStrategyService.php` | Life policy recommendations for IHT mitigation. |
| `SpouseNRBTrackerService` | `app/Services/Estate/SpouseNRBTrackerService.php` | Tracks NRB and RNRB transfer from deceased spouse. |
| `IntestacyCalculator` | `app/Services/Estate/IntestacyCalculator.php` | Calculates intestacy distribution rules. |
| `IHTStrategyGeneratorService` | `app/Services/Estate/IHTStrategyGeneratorService.php` | Generates comprehensive IHT mitigation strategies. |
| `AssetLiquidityAnalyzer` | `app/Services/Estate/AssetLiquidityAnalyzer.php` | Analyses estate liquidity for IHT payment. |

### Key Calculations

Refer to `08-FINANCIAL-CALCULATIONS.md` for complete IHT formulas. Summary:

- **NRB:** GBP 325,000 (doubled for married/civil partnership via transferable NRB)
- **RNRB:** GBP 175,000 (tapered above GBP 2m estate, reducing by GBP 1 for every GBP 2 over GBP 2m)
- **IHT rate:** 40% standard; 36% if 10%+ of net estate goes to charity
- **CLT rate:** 20% on amount exceeding NRB (additional 20% if death within 7 years)

**Estate health score (0-100):** Starts at 100, deductions for:
- Missing IHT profile: -20
- High IHT ratio (>30% of estate): -25; (>20%): -15; (>10%): -10
- No trusts when estate > GBP 650,000: -10
- Missing spouse link when married: -5
- Liquidity risk (liquid assets < 50% of IHT liability): -15

**7-step IHT mitigation decision tree** (recommendation generation):
1. Charitable bequest check (rate reduction from 40% to 36%)
2. Liquidity and affordability assessment
3. Check existing life cover
4. Annual gifting strategy (first resort -- immediately exempt)
5. Life cover strategy (second resort -- only if age <= 50)
6. PET gifting strategy (third resort -- 7-year survival requirement)
7. CLT into trust (last resort only)

### Frontend Components (30)

| Component | Purpose |
|-----------|---------|
| `IHTLiabilityGauge` | Visual IHT liability display |
| `IHTLiabilityBreakdown` | Detailed IHT breakdown |
| `IHTCalculationTable` | Calculation step-by-step |
| `IHTPlanning` | IHT planning tab |
| `IHTMitigationStrategies` | Strategy recommendations |
| `IHTAssetBreakdown` | Assets by category for IHT |
| `NRBRNRBTracker` | NRB and RNRB usage tracking |
| `GiftForm` | Create/edit gift form |
| `GiftCard` | Gift summary card |
| `GiftingStrategy` | Gifting strategy overview |
| `GiftingTimelineChart` | Timeline of gifts and PET status |
| `DualGiftingTimeline` | Side-by-side gifting timeline |
| `TrustForm` | Create/edit trust form |
| `TrustPlanning` | Trust planning tab |
| `TrustPlanningStrategy` | Trust strategy recommendations |
| `WillPlanning` | Will management tab |
| `AssetsLiabilities` | Estate assets and liabilities tab |
| `AssetForm` | Create/edit estate asset form |
| `LiabilityForm` | Create/edit liability form |
| `NetWorth` | Estate net worth display |
| `NetWorthWaterfallChart` | Waterfall chart of estate composition |
| `CashFlow` | Estate cash flow tab |
| `CashFlowProjectionChart` | Cash flow projection chart |
| `EstateProjectionComparison` | Compare current vs optimised |
| `EstateOverviewCard` | Dashboard overview card |
| `SpouseExemptionNotice` | Spouse exemption information |
| `LifeCoverRecommendations` | Life cover recommendations |
| `LifePolicyStrategy` | Life policy strategy display |
| `IntestacyRules` | Intestacy distribution display |
| `MissingDataAlert` | Prompt for incomplete data |

**Vuex Store:** `estate.js` (600+ lines) -- state includes assets, liabilities, gifts, trusts, bequests, ihtProfile, ihtCalculation, netWorth, cashFlow, analysis, secondDeathPlanning.

### API Endpoints

| Method | Endpoint | Action |
|--------|----------|--------|
| GET | `/api/estate` | List all estate data |
| POST | `/api/estate/calculate-iht` | Calculate IHT |
| POST | `/api/estate/calculate-surviving-spouse-iht` | Surviving spouse IHT |
| POST | `/api/estate/calculate-second-death-iht-planning` | Second death planning |
| GET | `/api/estate/net-worth` | Estate net worth |
| GET | `/api/estate/cash-flow` | Estate cash flow |
| GET | `/api/estate/comprehensive-plan` | Full estate plan |
| POST | `/api/estate/profile` | Create/update IHT profile |
| POST/PUT/DELETE | `/api/estate/assets/{id}` | Asset CRUD |
| POST/PUT/DELETE | `/api/estate/liabilities/{id}` | Liability CRUD |
| POST/PUT/DELETE | `/api/estate/gifts/{id}` | Gift CRUD |
| GET | `/api/estate/gifts/planned-strategy` | Planned gifting strategy |
| GET | `/api/estate/gifts/personalized-strategy` | Personalised gifting strategy |
| GET | `/api/estate/gifts/trust-strategy` | Trust strategy from gifting context |
| GET | `/api/estate/life-policy-strategy` | Life policy strategy |
| GET/POST/PUT/DELETE | `/api/estate/trusts/{id}` | Trust CRUD |
| GET | `/api/estate/trusts/{id}/analyze` | Trust analysis |
| GET | `/api/estate/trusts/{id}/assets` | Trust assets |
| POST | `/api/estate/trusts/{id}/calculate-iht-impact` | Trust IHT impact |
| GET | `/api/estate/trust-recommendations` | Trust recommendations |
| GET | `/api/estate/will` | Get will |
| POST | `/api/estate/will` | Create/update will |
| POST | `/api/estate/calculate-intestacy` | Intestacy calculation |
| GET/POST/PUT/DELETE | `/api/estate/bequests/{id}` | Bequest CRUD |

### Cross-Module Links

- **All modules:** Aggregates assets from properties, investments, savings, DC pensions, business interests, and chattels.
- **Protection:** Life insurance policies written in trust bypass the estate. Whole-of-life policies are recommended for IHT cover.
- **Retirement:** DC pensions are generally IHT-exempt (excluded from taxable estate). DB pension income ends at death except for spouse pension percentage.
- **Properties:** Main residence feeds into RNRB eligibility (must pass to lineal descendants).
- **Savings/Investment:** Account values feed into gross estate.
- **User Profile:** Gifting strategy considers annual income for "normal expenditure out of income" exemption.

---

## 6. Goals & Life Events Module

### Purpose

Unified goal tracking across all financial modules. Manages financial goals (11 types), life event forecasting, net worth projections with event overlays, contribution streak tracking, and affordability analysis. Goals are assigned to the appropriate module (savings, investment, property, retirement) based on type, time horizon, and amount.

### Data Model

| Model | Key Fields |
|-------|------------|
| `Goal` | `goal_type` (emergency_fund, house_deposit, wedding, education, car, holiday, home_improvement, debt_repayment, retirement, investment_target, custom), `target_amount`, `current_amount`, `target_date`, `start_date`, `assigned_module` (savings/investment/property/retirement), `status` (active/paused/completed/abandoned), `priority` (critical/high/medium/low), `monthly_contribution`, `contribution_frequency`, `contribution_streak`, `longest_streak`, `last_contribution_date`, `milestones` (JSON), `linked_savings_account_id`, `linked_account_ids` (JSON), `risk_preference`, `use_global_risk_profile`, joint ownership fields, property-specific fields (`property_location`, `property_type`, `is_first_time_buyer`, `estimated_property_price`, `deposit_percentage`, `stamp_duty_estimate`, `additional_costs_estimate`), `show_in_projection`, `show_in_household_view` |
| `GoalContribution` | `goal_id`, `type` (manual/automatic/lump_sum/interest/adjustment), `amount`, `date`, `streak_qualifying`, `notes` |
| `LifeEvent` | 9 income types (inheritance, gift_received, bonus, redundancy_payment, property_sale, business_sale, pension_lump_sum, lottery_windfall, custom_income), 7 expense types (large_purchase, home_improvement, wedding, education_fees, gift_given, medical_expense, custom_expense), `amount`, `impact_type`, `expected_date`, `certainty` (confirmed/likely/possible/speculative), `show_in_projection`, joint ownership fields |

### Agent: GoalsAgent

**File:** `app/Agents/GoalsAgent.php`

**Dependencies:** GoalAssignmentService, GoalAffordabilityService, GoalProgressService, GoalRiskService

**Cache:** Uses `rememberForUser()` pattern with standard TTL.

The agent queries all goals for a user, splits them by status (active/completed), analyses by module, calculates summary metrics, runs affordability analysis, and tracks contribution streaks.

### Services

| Service | File | Responsibility |
|---------|------|----------------|
| `GoalAssignmentService` | `app/Services/Goals/GoalAssignmentService.php` | Determines the appropriate module for a goal. Explicit mapping by goal type (emergency_fund -> savings, house_deposit -> property, retirement -> retirement). Falls back to time horizon: <= 3 years -> savings; > 3 years and >= GBP 5,000 -> investment; otherwise savings. |
| `GoalAffordabilityService` | `app/Services/Goals/GoalAffordabilityService.php` | Analyses all goals against available cashflow surplus. Flags overcommitted status when total monthly contributions exceed available surplus. |
| `GoalProgressService` | `app/Services/Goals/GoalProgressService.php` | Calculates progress percentage, on-track status, months remaining. |
| `GoalRiskService` | `app/Services/Goals/GoalRiskService.php` | Risk assessment per goal based on time horizon, amount, and current progress. |
| `LifeEventService` | `app/Services/Goals/LifeEventService.php` | Life event management and projection integration. |

### Key Calculations

**Progress percentage:**
```
(current_amount / target_amount) * 100
```

**On-track assessment:** Compares current progress against expected progress based on elapsed time vs total time.

**Module assignment logic:**
1. Check goal type for explicit module mapping
2. If time horizon <= 3 years: savings
3. If time horizon > 3 years and target >= GBP 5,000: investment
4. Default: savings

**Scenario modelling (4 scenarios per goal):**
1. Increase contribution by 20%
2. Reach goal 6 months earlier (calculates required contribution)
3. Reduce target by 20%
4. Add GBP 1,000 lump sum (calculates months saved)

### Frontend Components (22)

| Component | Purpose |
|-----------|---------|
| `GoalsList` | Main goals list view |
| `GoalCard` | Individual goal card |
| `GoalFormModal` | Create/edit goal form |
| `GoalProgressBar` | Visual progress indicator |
| `GoalContributionStreak` | Streak display |
| `GoalCountdown` | Days remaining countdown |
| `GoalMilestoneTracker` | Milestone tracking |
| `GoalsAnalysis` | Goals analysis tab |
| `GoalsByModule` | Goals grouped by module |
| `GoalsOverview` | Goals overview panel |
| `ContributionModal` | Record contribution form |
| `LifeEventForm` | Create/edit life event form |
| `LifeEventCard` | Life event display card |
| `EventsTab` | Life events tab |
| `GoalsProjectionChart` | Net worth projection with goals/events |
| `ProjectionSummaryCards` | Projection summary metrics |
| `ChartTypeToggle` | Toggle between net_worth/cash_flow/asset_breakdown views |
| `AssumptionsDisclosure` | Projection assumptions display |
| `EventTooltip` | Tooltip for events on chart |
| `EventIconsOverlay` | Event icons on projection chart |
| `EventIcon` | Individual event icon |
| `EventIconLegend` | Legend for event icons |

**Vuex Store:** `goals.js` -- state includes goals, summary, byModule, lifeEvents, projectionData. Chart options: `chartView` (net_worth/cash_flow/asset_breakdown), `viewMode` (individual/household).

### API Endpoints

| Method | Endpoint | Action |
|--------|----------|--------|
| GET | `/api/goals` | List all goals |
| GET | `/api/goals/analysis` | Goals analysis |
| GET | `/api/goals/dashboard-overview` | Dashboard overview data |
| GET | `/api/goals/projection` | Net worth projection with events |
| GET | `/api/goals/household-summary` | Household summary |
| GET | `/api/goals/types` | Available goal types |
| GET | `/api/goals/risk-levels` | Risk levels reference |
| POST | `/api/goals/calculate-property-costs` | Property cost calculator |
| POST/GET/PUT/DELETE | `/api/goals/{id}` | Goal CRUD |
| POST | `/api/goals/{id}/contribution` | Record contribution |
| GET | `/api/goals/{id}/projections` | Goal projections |
| GET | `/api/goals/{id}/scenarios` | Goal scenarios |
| GET | `/api/goals/{id}/contributions` | Contribution history |
| GET/POST/PUT/DELETE | `/api/life-events/{id}` | Life event CRUD |
| GET | `/api/life-events/types` | Event types reference |
| GET | `/api/life-events/by-age` | Events by age |
| POST | `/api/life-events/{id}/complete` | Mark event completed |

### Cross-Module Links

- **Savings/Investment/Retirement:** Goals are assigned to modules and appear in their respective dashboards. The assignment is determined by GoalAssignmentService based on goal type, time horizon, and amount.
- **Net Worth:** Life events feed into net worth projections displayed on the Goals projection chart.
- **Savings:** Emergency fund goals link to the emergency fund analysis. Goals can be linked to specific savings accounts via `linked_savings_account_id`.
- **Property:** Property-specific goals include deposit calculation, stamp duty estimation, and additional costs.
- **Coordination:** Goal affordability analysis integrates with cashflow surplus from CoordinatingAgent.

---

## 7. Coordination Module

### Purpose

Cross-module holistic planning. Collects analysis from all module agents, identifies and resolves conflicts between competing demands, ranks recommendations by user context and urgency, optimises cashflow allocation across modules, and generates comprehensive financial plans.

### Agent: CoordinatingAgent

**File:** `app/Agents/CoordinatingAgent.php`

**Dependencies:** ConflictResolver, PriorityRanker, HolisticPlanner, CashFlowCoordinator, ProtectionAgent, InvestmentAgent, SavingsAgent, RetirementAgent, TaxConfigService

**Cache:** Standard TTL from BaseAgent.

The agent's `analyze()` method delegates to `orchestrateAnalysis()`, which runs through a five-step pipeline:
1. Collect analysis from all module agents (protection, savings, investment, retirement, estate)
2. Calculate available surplus via CashFlowCoordinator
3. Extract and aggregate recommendations from all modules
4. Identify and resolve conflicts
5. Rank recommendations and optimise cashflow allocation

### Services

| Service | File | Responsibility |
|---------|------|----------------|
| `CashFlowCoordinator` | `app/Services/Coordination/CashFlowCoordinator.php` | Calculates available monthly surplus (income minus expenses minus existing commitments). Optimises allocation across competing demands by urgency. Identifies cashflow shortfalls when total demand exceeds surplus. |
| `ConflictResolver` | `app/Services/Coordination/ConflictResolver.php` | Detects three conflict types: `protection_vs_savings_conflict` (protection premiums vs savings targets), `cashflow_conflict` (total demands exceed surplus), `isa_allowance_conflict` (Cash ISA vs Stocks & Shares ISA competing for GBP 20,000 limit). Resolves each with specific strategies -- e.g., ISA allocation uses TaxConfigService for allowance amount. |
| `PriorityRanker` | `app/Services/Coordination/PriorityRanker.php` | Ranks recommendations using weighted module priorities (protection: 80, savings: 75, retirement: 70, investment: 60, estate: 50). Creates a structured action plan from ranked recommendations. |
| `HolisticPlanner` | `app/Services/Coordination/HolisticPlanner.php` | Creates a comprehensive financial plan integrating findings from all module analyses. |

### Key Operations

**`orchestrateAnalysis(userId)`** -- the primary entry point:

1. Calls `analyze()` on each module agent (protection, savings, investment, retirement). Estate uses placeholder data pending full integration.
2. Resolves user age from `date_of_birth` (defaults to 40 if missing).
3. Calculates available surplus via `CashFlowCoordinator::calculateAvailableSurplus()`.
4. Extracts recommendations from all module results.
5. Detects conflicts via `ConflictResolver::identifyConflicts()`.
6. Resolves conflicts with module-specific strategies.
7. Ranks recommendations via `PriorityRanker::rankRecommendations()` using user context.
8. Extracts monetary demands from ranked recommendations.
9. Optimises cashflow allocation via `CashFlowCoordinator::optimizeContributionAllocation()`.
10. Runs shortfall analysis.

Returns: `module_analysis`, `available_surplus`, `conflicts`, `ranked_recommendations`, `cashflow_allocation`, `shortfall_analysis`, and a summary.

**`generateHolisticPlan(userId)`** -- extends orchestration:

1. Runs full orchestration.
2. Generates holistic plan via `HolisticPlanner::createHolisticPlan()`.
3. Creates action plan via `PriorityRanker::createActionPlan()`.
4. Merges ranked recommendations, action plan, cashflow allocation, shortfall analysis, and conflicts into the plan.

**Conflict resolution types:**

| Conflict | Detection | Resolution |
|----------|-----------|------------|
| `protection_vs_savings_conflict` | Protection premium recommendations compete with savings targets | `ConflictResolver::resolveProtectionVsSavings()` -- balances based on adequacy scores |
| `cashflow_conflict` | Total monthly demands exceed available surplus | `ConflictResolver::resolveContributionConflicts()` -- allocates by urgency |
| `isa_allowance_conflict` | Cash ISA and Stocks & Shares ISA both want ISA allowance | `ConflictResolver::resolveISAAllocation()` -- splits based on time horizon and goals |

### Frontend Components (6)

| Component | Purpose |
|-----------|---------|
| `ModuleSummaries` | Summary cards for all modules |
| `ExecutiveSummary` | High-level financial overview |
| `PrioritizedRecommendations` | Ranked recommendation list |
| `NetWorthProjectionChart` | Net worth projection |
| `CashFlowAllocationChart` | Cashflow allocation visualisation |
| `RiskAssessment` | Cross-module risk assessment |

**Vuex Stores:** `holistic.js` (holistic plan data, module summaries), `recommendations.js` (unified recommendations with tracking: mark done, in progress, dismiss, notes).

### API Endpoints

| Method | Endpoint | Action |
|--------|----------|--------|
| POST | `/api/holistic/analyze` | Run cross-module analysis |
| POST | `/api/holistic/plan` | Generate holistic plan |
| GET | `/api/holistic/recommendations` | Get ranked recommendations |
| GET | `/api/holistic/cash-flow-analysis` | Cashflow analysis |
| POST | `/api/holistic/recommendations/{id}/mark-done` | Mark recommendation done |
| POST | `/api/holistic/recommendations/{id}/in-progress` | Mark in progress |
| POST | `/api/holistic/recommendations/{id}/dismiss` | Dismiss recommendation |
| GET | `/api/holistic/recommendations/completed` | List completed |
| PATCH | `/api/holistic/recommendations/{id}/notes` | Update notes |
| GET | `/api/recommendations` | Unified recommendations |
| GET | `/api/recommendations/summary` | Recommendations summary |
| GET | `/api/recommendations/top` | Top priority recommendations |
| GET | `/api/recommendations/completed` | Completed recommendations |
| POST | `/api/recommendations/{id}/mark-done` | Mark done |
| POST | `/api/recommendations/{id}/in-progress` | Mark in progress |
| POST | `/api/recommendations/{id}/dismiss` | Dismiss |
| PATCH | `/api/recommendations/{id}/notes` | Update notes |

### Cross-Module Links

The Coordination module connects to every other module:

- **Protection:** Collects protection analysis and checks for protection-vs-savings conflicts.
- **Savings:** Collects savings analysis. Emergency fund adequacy influences priority ranking.
- **Investment:** Collects investment analysis. ISA allowance conflicts detected between savings and investment ISAs.
- **Retirement:** Collects retirement analysis. Pension contribution demands compete for cashflow.
- **Estate:** Estate analysis feeds into holistic view (currently using placeholder data pending full integration).
- **Goals:** Goal affordability integrates with cashflow surplus calculations.

---

## Module Interaction Summary

The following diagram shows the primary data flows between modules:

```
                    +-----------------------+
                    |  CoordinatingAgent    |
                    |  (Holistic Planning)  |
                    +-----------+-----------+
                                |
            +-------+-------+---+---+-------+-------+
            |       |       |       |       |       |
            v       v       v       v       v       v
      Protection Savings Investment Retirement Estate Goals
            |       |       |       |       |       |
            |       +---+---+       |       |       |
            |           |           |       |       |
            |     ISA Allowance     |       |       |
            |     (shared GBP      |       |       |
            |      20,000)         |       |       |
            |           |          |       |       |
            |       +---+---+------+       |       |
            |       | include_in_  |       |       |
            |       | retirement   |       |       |
            |       +------+-------+       |       |
            |              |               |       |
            |              v               |       |
            |  +---------------------+     |       |
            +->|  EstateAsset        |<----+       |
               |  AggregatorService  |<-----+------+
               |  (all assets)       |
               +---------------------+
```

Each module operates independently but shares data through:
1. **Shared ISA allowance** between Savings and Investment (GBP 20,000 combined limit)
2. **Retirement inclusion flag** on savings accounts and investment accounts
3. **Estate asset aggregation** pulling values from all asset-holding modules
4. **Goal assignment** routing goals to the appropriate module
5. **Cashflow coordination** balancing competing demands across all modules
6. **Conflict resolution** handling ISA allowance, protection-vs-savings, and cashflow conflicts
