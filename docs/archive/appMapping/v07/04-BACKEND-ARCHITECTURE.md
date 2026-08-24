# 04 - Backend Architecture

This chapter documents Fynla's server-side structure: agents that orchestrate module logic, controllers that handle API requests, services that perform calculations, and API resources that shape JSON responses.

---

## 1. Agents

Eight files in `app/Agents/`. Each module agent extends `BaseAgent` and implements three abstract methods: `analyze()`, `generateRecommendations()`, and `buildScenarios()`. Agents orchestrate services but perform no domain calculations themselves.

### BaseAgent (Abstract)

`app/Agents/BaseAgent.php`

Provides shared infrastructure for all module agents.

**Caching:**
- `remember(key, callback, ttl, tags)` -- cached read with optional Redis tag support. Falls back to non-tagged caching on file/database stores.
- `rememberForUser(userId, suffix, callback, ttl)` -- generates standardised cache key `{agentname}_{userId}_{suffix}`.
- `invalidateUserCache(userId, additionalKeys)` -- flushes tagged cache (Redis/Memcached) and clears known key suffixes: `analysis`, `recommendations`, `scenarios`, `summary`, `projection`.
- `invalidateCacheForUsers(userIds)` -- bulk invalidation for joint accounts.
- `clearUserCache(userId, suffixes)` -- removes specific suffix-based keys.

**Math utilities:**
- `calculateCompoundGrowth(principal, rate, years)` returns `principal * (1 + rate)^years`.
- `calculatePresentValue(futureValue, discountRate, years)` returns `futureValue / (1 + discountRate)^years`.
- `calculatePercentageChange(oldValue, newValue)` returns percentage change; returns 0 if `oldValue` is zero.
- `roundToPenny(value)` rounds to 2 decimal places.

**Formatting:**
- `formatCurrency(amount, decimals)` returns string prefixed with pound sign.
- `formatPercentage(value, decimals)` returns string with percent suffix.

**Other:**
- `getCurrentTaxYear()` returns the active UK tax year (e.g. `"2025/26"`). Tax year runs 6 April to 5 April.
- `calculateAge(dateOfBirth)` returns integer age from date string or `DateTimeInterface`.
- `response(success, message, data)` returns standardised `{success, message, data, timestamp}` array.
- `validateRequired(data, required)` throws `InvalidArgumentException` for missing fields.

Default cache TTL is set from `TaxDefaults::CACHE_TTL_STANDARD`.

---

### ProtectionAgent

`app/Agents/ProtectionAgent.php`

**Dependencies:** CoverageGapAnalyzer, AdequacyScorer, RecommendationEngine, ScenarioBuilder, ProfileCompletenessChecker.

**`analyze(userId)`:**
1. Loads user with `protectionProfile`, five policy relationship collections (lifeInsurance, criticalIllness, incomeProtection, disability, sicknessIllness).
2. Calculates protection needs via `CoverageGapAnalyzer::calculateProtectionNeeds()`.
3. Calculates current coverage via `CoverageGapAnalyzer::calculateTotalCoverage()`.
4. Computes coverage gaps by comparing needs against coverage.
5. Scores adequacy (0-100) via `AdequacyScorer::calculateAdequacyScore()`.
6. Generates score insights factoring dependant status.
7. Generates recommendations via `RecommendationEngine`.
8. Models three default scenarios: death, critical illness, disability.
9. Calculates debt breakdown (mortgage and other liabilities) and profile completeness.

Returns: profile data, needs, coverage, gaps, adequacy score, recommendations, scenarios, debt breakdown, policy summaries, and profile completeness.

Cache tags: `['protection', 'user_{id}']`.

**`buildScenarios(userId, parameters)`:**
Supports scenario types: `death`, `critical_illness`, `disability`, `premium_change`. Defaults to death + critical illness + disability if no types specified.

---

### SavingsAgent

`app/Agents/SavingsAgent.php`

**Dependencies:** EmergencyFundCalculator, ISATracker, GoalProgressCalculator, LiquidityAnalyzer, RateComparator.

**Cache TTL:** 1,800 seconds (30 minutes).

**`analyze(userId)`:**
Returns six sections:
- **summary** -- `total_savings`, `total_accounts`, `total_goals`, `monthly_expenditure`.
- **emergency_fund** -- `runway_months`, `adequacy` (object with `adequacy_score` and `shortfall`), `category` (string classification).
- **isa_allowance** -- current tax year ISA status (used, remaining).
- **liquidity** -- `summary` with risk level, `ladder` (tiered accessibility breakdown).
- **rate_comparisons** -- per-account comparison to market rates with `potential_gain`.
- **goals** -- per-goal progress and prioritised goal list.

Cache tags: `['savings', 'user_{id}']`.

**`buildScenarios(userId, parameters)`:**
Future value formula for regular contributions:

```
FV = PV * (1 + r)^n + PMT * [((1 + r)^n - 1) / r]
```

Where `r` is the monthly rate (`annualRate / 12`) and `n` is months (`years * 12`).

---

### InvestmentAgent

`app/Agents/InvestmentAgent.php`

**Dependencies:** PortfolioAnalyzer, MonteCarloSimulator, AssetAllocationOptimizer, FeeAnalyzer, TaxEfficiencyCalculator, TaxConfigService.

**`analyze(userId)`:**
Returns:
- **portfolio_summary** -- `total_value`, `accounts_count`, `holdings_count`.
- **returns** -- historical return metrics from PortfolioAnalyzer.
- **asset_allocation** -- breakdown by asset class.
- **diversification_score** -- 0 to 100 from PortfolioAnalyzer.
- **risk_metrics** -- portfolio risk relative to the user's risk profile.
- **fee_analysis** -- total fees, fee breakdown by type, `high_fee_holdings` (OCF > 0.5%).
- **low_cost_comparison** -- potential savings by switching to low-cost alternatives.
- **tax_efficiency** -- `unrealized_gains`, `efficiency_score`, `harvesting_opportunities`.
- **tax_wrappers** -- ISA allowance tracking (GBP 20,000/year minus YTD contributions), GIA value, bond status.
- **allocation_deviation** -- drift from target allocation (if risk profile exists).
- **goals** -- investment goal progress.

Cache tags: `['investment', 'user_{id}']`.

**`generateRecommendations(analysis)`:**
Threshold-based triggers:
- Diversification score < 70/100: suggest spreading across asset classes.
- Fee savings > GBP 50/year: suggest lower-cost funds.
- Any holding with OCF > 0.5%: flag high-fee holdings.
- Platform fees > 0.8% of portfolio: flag platform cost.
- Allocation deviation needing rebalancing: suggest rebalance.
- GIA without ISA: suggest opening ISA.
- ISA allowance remaining with GIA holdings: suggest Bed and ISA.
- GIA value > GBP 50,000 without bonds: suggest tax-efficient bonds.
- Tax loss harvesting opportunities: flag losses for CGT offset.

**`buildScenarios(userId, parameters)`:**
Three default scenarios:
- Conservative Growth: 4% return, 8% volatility.
- Balanced Growth: 7% return, 12% volatility.
- Aggressive Growth: 10% return, 18% volatility.

Plus an increased contributions scenario if current contributions > 0.

**`getPortfolioProjections(userId, periods, contributionOverrides, selectedPeriod)`:**
Delegates to `InvestmentProjectionService` for Monte Carlo-based projections across configurable time horizons (default: 5, 10, 20, 30 years).

---

### RetirementAgent

`app/Agents/RetirementAgent.php`

**Dependencies:** PensionProjector, AnnualAllowanceChecker, ContributionOptimizer, DecumulationPlanner, PensionPortfolioAnalyzer. Also injects shared Investment services: PortfolioAnalyzer, MonteCarloSimulator, AssetAllocationOptimizer, FeeAnalyzer, TaxEfficiencyCalculator.

**Cache TTL:** 3,600 seconds (1 hour).

**`analyze(userId)`:**
Returns:
- **summary** -- `years_to_retirement`, `target_retirement_age`, `projected_retirement_income`, `target_retirement_income`, `income_gap`, `total_dc_value`, `total_pensions_count`.
- **income_projection** -- detailed projection from PensionProjector including DC total value, DB annual income, state pension income.
- **breakdown** -- DC pensions (each with current value, monthly contribution, projected value), DB pensions (accrued annual pension, normal retirement age), state pension (NI years, forecast).
- **annual_allowance** -- result from AnnualAllowanceChecker (has_excess, excess_contributions).

Cache tags: `['retirement', 'user_{id}']`.

**`buildScenarios(userId, parameters)`:**
Additional contribution formula:

```
additional_value = additional_annual * [((1 + r)^years - 1) / r]
```

Default growth rate from `TaxDefaults::DEFAULT_GROWTH_RATE`. Scenario types: current trajectory, increased contribution, later retirement age, lower target income. Includes side-by-side comparison identifying the scenario with the smallest income gap.

**`analyzeDCPensionPortfolio(userId, dcPensionId)`:**
Delegates to `PensionPortfolioAnalyzer` for pension-specific portfolio metrics: alpha, beta, Sharpe ratio, volatility, max drawdown, VaR, asset allocation, diversification scoring, and fee analysis.

---

### EstateAgent

`app/Agents/EstateAgent.php`

**Dependencies:** IHTCalculationService, EstateAssetAggregatorService, ComprehensiveEstatePlanService, GiftingStrategyOptimizer, PersonalizedTrustStrategyService, WillAnalysisService, TaxConfigService.

**Cache tags:** `['estate', 'user_{id}']`.

**`analyze(userId)`:**
Loads user with: ihtProfile, assets, properties, liabilities, mortgages, spouse, familyMembers, trusts, gifts. Returns:
- **summary** -- `gross_estate`, `net_estate`, `total_liabilities`, `iht_liability`, `effective_tax_rate`, `health_score` (0-100).
- **asset_breakdown** -- categorised by liquid/illiquid from aggregator.
- **iht_calculation** -- full IHT result (NRB, RNRB, taper, liability).
- **trust_recommendations** -- from PersonalizedTrustStrategyService.
- **gifting_opportunities** -- from GiftingStrategyOptimizer.
- **trust_wish_triggers** -- will bequests that require trust structures (from WillAnalysisService).
- **charitable_analysis** -- charitable bequest status and potential savings.
- **profile** -- current age, marital status, dependant/spouse/IHT profile flags.

**`generateRecommendations(analysisData)`:**
Implements a 7-step IHT mitigation decision tree, applied sequentially with a running `remainingLiability`:

| Step | Strategy | Detail |
|------|----------|--------|
| 1 | Charitable Bequest Check | If charitable giving falls short of 10% threshold, increasing it reduces IHT rate from 40% to 36%. |
| 2 | Liquidity & Affordability Assessment | Flags if liquid assets < 50% of IHT liability. |
| 3 | Existing Life Cover | Checks usable life cover after deducting liabilities. |
| 4 | Annual Gifting Strategy | GBP 3,000/year exemption. Calculates capacity over years to life expectancy. IHT saved at 40%. |
| 5 | Life Cover Strategy | Whole of life policy. Only recommended if age <= 50. Estimated premium ~2% of cover per year. |
| 6 | PET Gifting Strategy | Potentially Exempt Transfers. Calculates available 7-year cycles, each sheltering up to the NRB (GBP 325,000). |
| 7 | CLT into Trust | Last resort. 20% immediate charge on amount exceeding NRB. Additional 20% if death within 7 years. Periodic charges up to 6% every 10 years. |

Also adds recommendations for trust wish triggers from will analysis and low health score (< 50) planning gaps.

**`calculateEstateHealthScore(user, assetSummary, ihtLiability)`:**
Starts at 100, applies deductions:
- No IHT profile: -20.
- IHT ratio > 30% of gross estate: -25; > 20%: -15; > 10%: -10.
- No trusts when gross estate > GBP 650,000: -10.
- Married but no spouse record: -5.
- Liquid assets < 50% of IHT liability: -15.

Result clamped to 0-100.

**`buildScenarios(userId, parameters)`:**
Scenario types: `current`, `optimized`, `gifting`, `property_downsizing`, `trust_creation`. Each returns gross estate, net estate, IHT liability, and amount to beneficiaries.

---

### GoalsAgent

`app/Agents/GoalsAgent.php`

**Dependencies:** GoalAssignmentService, GoalAffordabilityService, GoalProgressService, GoalRiskService.

**`analyze(userId)`:**
Returns:
- **has_goals** -- boolean.
- **summary** -- `total_goals`, `total_target`, `total_current`, `overall_progress` (percentage), `on_track_count`, `behind_count`, `on_track_percentage`.
- **by_module** -- separate analysis for savings, investment, property, retirement goals (count, totals, average progress, on-track count, individual goal data).
- **top_goals** -- up to 5 goals sorted by priority then progress.
- **affordability** -- from GoalAffordabilityService.
- **streaks** -- `best_current_streak`, `longest_ever_streak`.

Priority ordering: critical (1), high (2), medium (3), low (4).

**`getDashboardOverview(userId)`:**
Returns summary card data: total active goals, on-track count, overall progress, top 5 goals with display type, assigned module, and progress percentage. Also returns best current streak and completed-this-year count.

**`buildScenarios(userId, parameters)`:**
Requires a `goal_id`. Generates four scenarios:
1. Increase contribution by 20%.
2. Reach goal 6 months earlier.
3. Reduce target by 20%.
4. Add GBP 1,000 lump sum.

---

### CoordinatingAgent

`app/Agents/CoordinatingAgent.php`

**Dependencies:** ConflictResolver, PriorityRanker, HolisticPlanner, CashFlowCoordinator, ProtectionAgent, InvestmentAgent, SavingsAgent, RetirementAgent, TaxConfigService.

**`orchestrateAnalysis(userId)`:**
1. Calls `analyze()` on Protection, Savings, Investment, Retirement agents (each wrapped in try/catch so one module failure does not block others).
2. Calculates available surplus via `CashFlowCoordinator::calculateAvailableSurplus()`.
3. Extracts recommendations from all module results.
4. Identifies conflicts via `ConflictResolver::identifyConflicts()`.
5. Resolves conflicts (see below).
6. Ranks recommendations via `PriorityRanker::rankRecommendations()` using user context with module priority weights (protection: 80, savings: 75, retirement: 70, investment: 60, estate: 50).
7. Optimises cashflow allocation via `CashFlowCoordinator::optimizeContributionAllocation()`.
8. Identifies cashflow shortfalls.

Returns: module analysis, available surplus, conflicts, ranked recommendations, cashflow allocation, shortfall analysis, summary counts.

**Conflict types handled:**
- `protection_vs_savings_conflict` -- protection premiums competing with savings goals.
- `cashflow_conflict` -- total contribution demands exceed available surplus.
- `isa_allowance_conflict` -- multiple modules competing for the GBP 20,000/year ISA limit.

**`generateHolisticPlan(userId)`:**
Runs orchestrated analysis, then generates a holistic plan via `HolisticPlanner::createHolisticPlan()` and creates a prioritised action plan via `PriorityRanker::createActionPlan()`.

---

## 2. Controllers

66 controller files across `app/Http/Controllers/Api/` and subdirectories. All API routes require `auth:sanctum` middleware.

### Standard Controller Pattern

Most module controllers follow a consistent structure:

```php
// Read operations
public function index(Request $request)       // List all records for authenticated user
public function analyze(Request $request)      // Call agent->analyze(user->id)
public function recommendations(Request $req)  // Call agent->generateRecommendations()
public function scenarios(Request $request)    // Call agent->buildScenarios()

// Write operations
public function store(StoreRequest $request)   // Create record, invalidate cache
public function update(UpdateRequest $req, $id)// Update record, invalidate cache
public function destroy(Request $request, $id) // Delete record, invalidate cache
```

**Authorisation:** Every controller verifies `$request->user()` ownership before CRUD. Records are queried with `where('user_id', $request->user()->id)` or verified after retrieval.

**Cache invalidation:** Every write operation calls `agent->invalidateCache($user->id)` and `netWorthService->invalidateCache($user->id)`. Joint asset changes also invalidate cache for the joint owner.

### Controllers by Module

**Protection:**
`ProtectionController` -- Protection profile CRUD plus five policy types (life insurance, critical illness, income protection, disability, sickness/illness). Endpoints: analyze, recommendations, scenarios, comprehensive plan.

**Savings:**
`SavingsController` -- Savings accounts CRUD, savings goals CRUD, ISA allowance check, toggle retirement inclusion flag.

**Investment:**
- `InvestmentController` -- Accounts CRUD, holdings CRUD, analyze, Monte Carlo simulation, projections.
- `InvestmentProjectionController` -- Portfolio projection endpoints.
- `PortfolioOptimizationController` -- Portfolio optimisation analysis.
- `Investment/ContributionOptimizerController` -- Contribution optimisation.
- `Investment/GoalProgressController` -- Investment goal tracking.
- `Investment/InvestmentScenarioController` -- Scenario modelling.
- `Investment/TaxOptimizationController` -- Tax efficiency analysis.
- `Investment/InvestmentRecommendationController` -- Recommendation generation.
- `Investment/FeeImpactController` -- Fee impact projections.
- `Investment/EfficientFrontierController` -- Efficient frontier calculations.
- `Investment/RebalancingStrategiesController`, `RebalancingActionsController`, `RebalancingCalculationController` -- Rebalancing operations.
- `Investment/ModelPortfolioController` -- Model portfolio construction.
- `Investment/AssetLocationController` -- Tax wrapper optimisation.
- `Investment/PerformanceAttributionController` -- Attribution analysis.
- `Investment/PortfolioStrategyController` -- Strategy management.
- `Investment/InvestmentPlanController` -- Plan generation.

**Retirement:**
`RetirementController` -- DC pension CRUD, DB pension CRUD, state pension CRUD, annual allowance check, portfolio analysis for pension holdings, retirement strategies, income configuration, required capital calculation.
`Retirement/DCPensionHoldingsController` -- Pension fund holdings CRUD.

**Estate & IHT:**
- `EstateController` -- Estate assets and liabilities CRUD.
- `Estate/IHTController` -- `calculateIHT`, `calculateSurvivingSpouseIHT`, `calculateSecondDeathIHTPlanning`, `storeOrUpdateIHTProfile`.
- `Estate/GiftingController` -- Gifts CRUD, gifting strategy analysis.
- `Estate/TrustController` -- Trusts CRUD.
- `Estate/WillController` -- Will CRUD, bequests.
- `Estate/LifePolicyController` -- Estate-related life policies.

**Goals & Life Events:**
- `GoalsController` -- Goals CRUD, contributions CRUD, projections, scenarios, dashboard overview.
- `LifeEventController` -- Life events CRUD, by-age retrieval, mark completed.

**Cross-Module:**
- `NetWorthController` -- Overview, breakdown, trend, assets summary, joint assets.
- `PropertyController` -- Properties CRUD, `calculateSDLT`, `calculateCGT`, `calculateRentalIncomeTax`.
- `MortgageController` -- Mortgages CRUD, amortisation schedule, calculate payment.
- `DashboardController` -- Dashboard index, financial health score, alerts.
- `HolisticPlanningController` -- Full analysis, holistic plan, recommendations, cashflow analysis.
- `RecommendationsController` -- Cross-module recommendation aggregation.

**User & Admin:**
- `AuthController` -- Login, register, logout.
- `MFAController` -- MFA setup, verification.
- `SessionController` -- Session management.
- `PasswordResetController` -- Password reset flow.
- `UserProfileController` -- Profile CRUD, income, expenditure.
- `FamilyMembersController` -- Family members CRUD.
- `RiskPreferenceController` -- Risk questionnaire and preferences.
- `OnboardingController` -- Onboarding flow state.
- `ProfileCompletenessController` -- Completeness checks.
- `PersonalAccountsController` -- Personal accounts management.
- `SpousePermissionController` -- Joint account permissions.
- `JointAccountLogController` -- Audit log for joint operations.
- `AdminController` -- Admin-only operations.
- `PreviewController` -- Preview mode persona switching.

**Supporting:**
- `ChattelController` -- Personal possessions CRUD.
- `BusinessInterestController` -- Business interests CRUD.
- `DocumentController` -- Document upload and parsing.
- `LetterToSpouseController` -- Letter to spouse feature.
- `BugReportController` -- In-app bug reporting.
- `PostcodeLookupController` -- UK postcode address lookup.
- `GDPRController` -- Data export and deletion.
- `OccupationController` -- Occupation lookup.
- `InfoGuideController` -- Help content.
- `TaxSettingsController`, `TaxProductInfoController`, `UKTaxesController` -- Tax reference data.
- `Settings/AssumptionsController` -- User-configurable planning assumptions.
- `Plans/InvestmentSavingsPlanController` -- Combined investment/savings plans.

---

## 3. Services

141 service files organised by module under `app/Services/`.

### Core Tax Services

**TaxConfigService** (`app/Services/TaxConfigService.php`):
UK tax thresholds and rates. Loads the active tax year configuration from the database once per request, then caches in memory. Provides dot-notation access (`get('income_tax.personal_allowance')`) plus typed accessors:

| Method | Returns |
|--------|---------|
| `getIncomeTax()` | Personal allowance, tax bands, rates |
| `getNationalInsurance()` | NI thresholds and rates |
| `getISAAllowances()` | Annual allowance (GBP 20,000), LISA limits |
| `getPensionAllowances()` | Annual allowance (GBP 60,000), LTA, money purchase AA |
| `getInheritanceTax()` | NRB (GBP 325,000), RNRB (GBP 175,000), taper, rates |
| `getCapitalGainsTax()` | Annual exempt amount, rates by asset type |
| `getDividendTax()` | Allowance and rates |
| `getStampDuty()` | SDLT bands and rates |
| `getGiftingExemptions()` | Annual exemption, small gifts, wedding gifts |
| `getTrusts()` | Trust tax rules |
| `getPETRules()` | PET 7-year rules |
| `getCLTRules()` | CLT rates (20% lifetime, 40% on death) |
| `getTaperRelief(type)` | Taper relief percentages by year survived |
| `getBusinessRelief()` | BPR eligibility and rates |
| `getAgriculturalRelief()` | APR rules |
| `getPropertyOwnership()` | Joint ownership types and rules |
| `getAssumptions()` | Growth rates, inflation, life expectancy |
| `getChildBenefit()` | High Income Child Benefit Charge thresholds |

**UKTaxCalculator** (`app/Services/UKTaxCalculator.php`):
Income tax, National Insurance, and dividend tax calculations for individuals.

**TaxBandTracker** (`app/Services/TaxBandTracker.php`):
Tracks cumulative tax position across multiple income sources. Prevents double-counting of personal allowance when aggregating employment, rental, and dividend income.

### Protection Services (5)

| Service | Purpose |
|---------|---------|
| `AdequacyScorer` | Scores protection adequacy on a 0-100 scale. Generates narrative insights. |
| `CoverageGapAnalyzer` | Calculates protection needs (income replacement, debt cover, dependant provision) and compares against actual policy coverage. |
| `RecommendationEngine` | Generates prioritised recommendations based on coverage gaps and user profile. |
| `ScenarioBuilder` | Models death, critical illness, and disability scenarios showing financial impact. |
| `ComprehensiveProtectionPlanService` | Generates a complete protection plan combining all analysis outputs. |

### Savings Services (5)

| Service | Purpose |
|---------|---------|
| `EmergencyFundCalculator` | Calculates emergency fund runway (months), adequacy score, categorisation (Critical/Low/Adequate/Strong), and monthly top-up amounts. |
| `ISATracker` | Tracks ISA subscriptions against the GBP 20,000 annual allowance per tax year. |
| `LiquidityAnalyzer` | Categorises accounts by accessibility tier and builds a liquidity ladder. |
| `RateComparator` | Compares account interest rates to current market rates. Calculates potential annual gain from switching. |
| `GoalProgressCalculator` | Tracks goal progress percentage, projects achievement timelines, and prioritises goals. |

### Investment Services

**Core analysis (6+):**

| Service | Purpose |
|---------|---------|
| `PortfolioAnalyzer` | Calculates total value, returns, asset allocation, diversification score (0-100), and risk metrics. |
| `MonteCarloSimulator` | Geometric Brownian Motion simulation. Default 1,000 iterations. Returns percentile outcomes: p10, p25, p50, p75, p90. |
| `AssetAllocationOptimizer` | Target allocation based on risk profile. Calculates deviation from target and rebalancing needs. |
| `FeeAnalyzer` | Calculates total fees by type. Flags holdings with OCF > 0.5%. Compares to low-cost alternatives. |
| `TaxEfficiencyCalculator` | Unrealised gains calculation, tax efficiency scoring, CGT harvesting opportunity identification. |
| `InvestmentProjectionService` | Portfolio projections combining Monte Carlo with contribution schedules across multiple time horizons. |
| `DiversificationAnalyzer` | Asset class and geographic diversification analysis. |
| `ScenarioService` | Investment what-if scenario modelling. |
| `ContributionEstimatorService` | Estimates required contributions to meet targets. |
| `ContributionOptimizer` | Optimises contribution allocation across investment accounts. |
| `InvestmentPlanGenerator` | Generates complete investment plans. |

**Portfolio analytics (`Investment/Analytics/`):**
- `CorrelationMatrixCalculator` -- asset correlation analysis.
- `CovarianceMatrixCalculator` -- covariance computation.
- `EfficientFrontierCalculator` -- Markowitz efficient frontier.
- `PortfolioStatisticsCalculator` -- statistical measures.
- `HoldingsDataExtractor` -- extracts and normalises holdings data.
- `MarkowitzOptimizer` -- mean-variance optimisation.

**Performance (`Investment/Performance/`):**
- `BenchmarkComparator` -- compares portfolio to benchmarks.
- `AlphaBetaCalculator` -- alpha and beta computation.
- `PerformanceAttributionAnalyzer` -- attribution analysis.

**Fees (`Investment/Fees/`):**
- `OCFImpactCalculator` -- ongoing charges figure impact projection.
- `PlatformComparator` -- platform fee comparison.

**Tax (`Investment/Tax/`):**
- `ISAAllowanceOptimizer` -- ISA subscription optimisation.
- `BedAndISACalculator` -- Bed and ISA transfer calculations.
- `CGTHarvestingCalculator` -- capital gains tax loss harvesting.

**Rebalancing (`Investment/Rebalancing/`):**
- `DriftAnalyzer` -- allocation drift detection.
- `TaxAwareRebalancer` -- rebalancing with tax consideration.
- `RebalancingStrategyService` -- strategy selection.
- `RebalancingCalculator` -- trade calculations.

**Model portfolios (`Investment/ModelPortfolio/`):**
- `ModelPortfolioBuilder` -- constructs model portfolios.
- `FundSelector` -- selects funds for target allocation.
- `AssetAllocationOptimizer` -- model-specific allocation.

**Goals (`Investment/Goals/`):**
- `ShortfallAnalyzer` -- goal shortfall analysis.
- `GoalProbabilityCalculator` -- probability of achieving goals.
- `GoalProgressAnalyzer` -- investment goal tracking.

**Asset location (`Investment/AssetLocation/`):**
- `AccountTypeRecommender` -- recommends tax-wrapper placement.

**Utilities (`Investment/Utilities/`):**
- `MatrixOperations` -- matrix math for optimisation.
- `StatisticalFunctions` -- statistical helper functions.

### Retirement Services (7+)

| Service | Purpose |
|---------|---------|
| `PensionProjector` | Projects total retirement income from DC pensions, DB pensions, and state pension. |
| `AnnualAllowanceChecker` | Checks total pension contributions against the GBP 60,000 annual allowance. Handles carry forward. |
| `ContributionOptimizer` | Optimises contribution strategy across pension types (employer match, salary sacrifice, personal contributions). |
| `DecumulationPlanner` | Plans retirement income drawdown strategies. |
| `PensionPortfolioAnalyzer` | Analyses DC pension fund holdings for risk metrics (alpha, beta, Sharpe ratio, volatility, max drawdown, VaR), asset allocation, diversification, and fees. |
| `RetirementProjectionService` | Year-by-year retirement projections. |
| `RequiredCapitalCalculator` | Calculates the pension pot needed to achieve target retirement income. |
| `RetirementIncomeService` | Income configuration and strategy management. |

### Estate Services (6+)

| Service | Purpose |
|---------|---------|
| `IHTCalculationService` | The largest single service (1,290 lines). Computes IHT liability including NRB, RNRB, taper relief, spouse exemption, charitable rate reduction, projected estate at death, and surviving spouse / second death calculations. |
| `EstateAssetAggregatorService` | Aggregates all estate assets (property, savings, investments, pensions, chattels, business interests) with liquid/illiquid categorisation. |
| `ComprehensiveEstatePlanService` | Generates full estate plans combining IHT, trusts, gifting, and will analysis. |
| `GiftingStrategyOptimizer` | Identifies gifting opportunities. Calculates annual exemption usage, PET capacity, and 7-year cycles. |
| `PersonalizedTrustStrategyService` | Recommends trust structures based on estate size, family situation, and objectives. |
| `WillAnalysisService` | Analyses will bequests for trust-triggering wishes and charitable donation status. |
| `LifeCoverCalculator` | Calculates life cover needed to cover IHT liability. |
| `GiftingTimelineService` | Tracks gift timeline for PET taper relief. |
| `GiftingStrategy` | Gift strategy modelling. |
| `IHTStrategyGeneratorService` | Generates IHT mitigation strategy combinations. |
| `CashFlowProjector` | Estate cashflow projections. |
| `FutureValueCalculator` | Projects estate asset values to future dates. |
| `AssetLiquidityAnalyzer` | Analyses estate asset liquidity risk. |
| `SpouseNRBTrackerService` | Tracks transferable NRB between spouses. |
| `LifePolicyStrategyService` | Life policy strategy for estate planning. |
| `IntestacyCalculator` | Calculates intestacy distribution rules. |
| `NetWorthAnalyzer` | Analyses estate-specific net worth. |

### Goals Services (4)

| Service | Purpose |
|---------|---------|
| `GoalAffordabilityService` | Analyses whether planned monthly contributions across all goals are affordable given the user's surplus income. Returns `overcommitted` or `affordable` status. |
| `GoalAssignmentService` | Auto-assigns goals to the appropriate module (savings, investment, property, retirement) based on goal type and timeline. |
| `GoalProgressService` | Calculates goal progress, on-track status, milestones, and required monthly contributions. |
| `GoalRiskService` | Assesses risk of not achieving goals based on contribution consistency and timeline. |
| `LifeEventService` | Manages life event CRUD and milestone tracking. |

### Coordination Services (4)

| Service | Purpose |
|---------|---------|
| `CashFlowCoordinator` | Calculates available surplus (income minus expenses minus existing commitments). Allocates surplus across competing contribution demands by urgency weighting. Identifies shortfalls. |
| `ConflictResolver` | Detects and resolves conflicts: protection vs savings competition, cashflow overcommitment, ISA allowance contention. |
| `PriorityRanker` | Ranks cross-module recommendations by urgency score and user-configured module priorities. Creates tiered action plans (immediate / short-term / medium-term). |
| `HolisticPlanner` | Creates cross-module financial plans by synthesising analysis from all modules. |

### Supporting Services

| Service | Purpose |
|---------|---------|
| `NetWorthService` | Total assets minus total liabilities. Breakdown by category. Trend over time. |
| `RiskPreferenceService` | Risk questionnaire scoring and risk profile management. |
| `ProfileCompletenessChecker` | Checks what data the user has and has not entered across all modules. |
| `UserProfileService` | User profile data management. |
| `PropertyService` | Property-specific operations (equity calculation, portfolio summary). |
| `PropertyTaxService` | SDLT, CGT, and rental income tax calculations for properties. |
| `MortgageService` | Amortisation schedules, payment calculations. |
| `OnboardingService` | Onboarding state and flow management. |
| `PersonalAccountsService` | Personal (non-savings) account management. |

**Auth services (`Auth/`):**
- `LoginLockoutService` -- tracks failed login attempts, enforces lockout.
- `MFAService` -- TOTP-based two-factor authentication.
- `PasswordResetService` -- secure password reset flow.
- `PermissionService` -- role and permission checks.
- `SessionService` -- session management and validation.

**Document services (`Documents/`):**
- `DocumentUploadService` -- file upload handling.
- `DocumentTypeDetector` -- detects uploaded document type (pension statement, insurance policy).
- `ExcelParserService` -- parses uploaded Excel files.
- `ImageResizeService` -- document image processing.
- Field mappers (`FieldMappers/`) -- `DBPensionMapper`, `DCPensionMapper`, `InvestmentAccountMapper`, `LifeInsuranceMapper` -- map parsed document fields to database columns.

**Other:**
- `AuditService` -- audit trail logging.
- `ConsentService` (GDPR) -- consent management.
- `LetterToSpouseService` -- letter to spouse generation.
- `DatabaseMetricsService` (Admin) -- database health metrics.
- `CrossModuleAssetAggregator` (Shared) -- aggregates assets across all modules.
- `TrustAssetAggregatorService` (Trust) -- aggregates trust-held assets.
- `IHTPeriodicChargeCalculator` (Trust) -- calculates 10-year periodic charges.
- `InvestmentSavingsPlanService` (Plans) -- combined investment/savings plan generation.

---

## 4. API Resources

10 resource classes in `app/Http/Resources/`. Each transforms an Eloquent model into a JSON structure. Relationships are conditionally included using `whenLoaded()` to avoid N+1 queries.

### UserResource

```json
{
    "id": 1,
    "name": "James Carter",
    "first_name": "James",
    "last_name": "Carter",
    "email": "james@example.com",
    "date_of_birth": "1988-03-15",
    "retirement_date": "2053-03-15",
    "created_at": "2025-01-01T00:00:00+00:00"
}
```

### PropertyResource

```json
{
    "id": 1,
    "address_line_1": "42 Oak Drive",
    "address_line_2": null,
    "city": "Bristol",
    "county": "Somerset",
    "postcode": "BS1 4AA",
    "country": "GB",
    "property_type": "main_residence",
    "tenure_type": "freehold",
    "current_value": 450000,
    "purchase_price": 320000,
    "purchase_date": "2018-06-01",
    "valuation_date": "2024-11-01",
    "ownership_type": "joint",
    "ownership_percentage": 50,
    "joint_ownership_type": "joint_tenants",
    "equity": 280000,
    "outstanding_mortgage": 170000,
    "monthly_rental_income": null,
    "lease_remaining_years": null,
    "created_at": "...",
    "updated_at": "...",
    "mortgages": [],
    "user": { },
    "joint_owner": { },
    "links": { "self": "/api/properties/1" }
}
```

Conditional fields: `monthly_rental_income` only appears for `buy_to_let` properties. `lease_remaining_years` only appears for `leasehold` tenure. Relationships (`mortgages`, `user`, `joint_owner`) only appear when eager loaded.

### InvestmentAccountResource

```json
{
    "id": 1,
    "account_name": "Stocks & Shares ISA",
    "account_type": "isa",
    "provider": "Vanguard",
    "platform": "Vanguard Investor",
    "current_value": 85000,
    "contributions_ytd": 12000,
    "monthly_contribution_amount": 500,
    "contribution_frequency": "monthly",
    "ownership_type": "individual",
    "ownership_percentage": 100,
    "country": "GB",
    "tax_year": "2025/26",
    "platform_fee_percent": 0.15,
    "advisor_fee_percent": 0,
    "risk_preference": "balanced",
    "has_custom_risk": false,
    "include_in_retirement": true,
    "isa_type": "stocks_and_shares",
    "isa_subscription_current_year": 12000,
    "created_at": "...",
    "updated_at": "...",
    "holdings": [],
    "links": { "self": "/api/investment/accounts/1" }
}
```

Conditional fields: `isa_type` and `isa_subscription_current_year` only for ISA account types. `account_type_other` only when type is `other`. Employee share scheme fields (`employer_name`, `grant_date`, `units_granted`, `units_vested`, `exercise_price`, `current_share_price`, `intrinsic_value`) only for employee share scheme accounts.

### MortgageResource

```json
{
    "id": 1,
    "lender": "Nationwide",
    "lender_name": "Nationwide",
    "mortgage_type": "repayment",
    "original_amount": 250000,
    "original_loan_amount": 250000,
    "current_balance": 170000,
    "outstanding_balance": 170000,
    "interest_rate": 4.25,
    "rate_type": "fixed",
    "monthly_payment": 1350,
    "monthly_interest_portion": 602,
    "start_date": "2018-06-01",
    "end_date": "2043-06-01",
    "maturity_date": "2043-06-01",
    "remaining_term_months": 216,
    "ownership_type": "joint",
    "ownership_percentage": 50,
    "country": "GB",
    "repayment_percentage": null,
    "interest_only_percentage": null,
    "rate_fix_end_date": "2026-06-01",
    "notes": null,
    "created_at": "...",
    "updated_at": "...",
    "property": { },
    "links": { "self": "/api/mortgages/1" }
}
```

Conditional fields: `repayment_percentage` and `interest_only_percentage` only for `mixed` type. `rate_fix_end_date` only for `fixed` rate. `fixed_interest_rate` for fixed/mixed. `variable_interest_rate` for variable/mixed.

### GoalResource

```json
{
    "id": 1,
    "name": "Emergency Fund",
    "goal_type": "emergency_fund",
    "display_goal_type": "Emergency Fund",
    "description": "6 months of expenses",
    "target_amount": 18000,
    "current_amount": 12000,
    "target_date": "2026-06-01",
    "start_date": "2025-01-01",
    "priority": "high",
    "is_essential": true,
    "status": "active",
    "assigned_module": "savings",
    "progress_percentage": 66.7,
    "amount_remaining": 6000,
    "days_remaining": 145,
    "months_remaining": 5,
    "is_on_track": true,
    "current_milestone": "50%",
    "next_milestone": "75%",
    "required_monthly_contribution": 1200,
    "monthly_contribution": 1000,
    "contribution_frequency": "monthly",
    "contribution_streak": 3,
    "longest_streak": 5,
    "last_contribution_date": "2025-12-15",
    "ownership_type": "individual",
    "ownership_percentage": 100,
    "completed_at": null,
    "created_at": "...",
    "updated_at": "...",
    "contributions": [],
    "linked_savings_account": { },
    "links": { "self": "/api/goals/1" }
}
```

Conditional fields: `custom_goal_type_name` only for `custom` goal type. Property-specific fields (`property_location`, `property_type`, `is_first_time_buyer`, `estimated_property_price`, `deposit_percentage`) only for property goals.

### SavingsAccountResource

```json
{
    "id": 1,
    "account_name": "cash_isa",
    "provider": "Nationwide",
    "account_type": "cash_isa",
    "current_balance": 15000,
    "interest_rate": 4.5,
    "access_type": "instant",
    "ownership_type": "individual",
    "ownership_percentage": 100,
    "country": "GB",
    "is_emergency_fund": true,
    "include_in_retirement": false,
    "is_isa": true,
    "isa_type": "cash",
    "isa_subscription_year": "2025/26",
    "isa_subscription_amount": 10000,
    "regular_contribution_amount": 500,
    "contribution_frequency": "monthly",
    "planned_lump_sum_amount": null,
    "planned_lump_sum_date": null,
    "created_at": "...",
    "updated_at": "...",
    "links": { "self": "/api/savings/accounts/1" }
}
```

Conditional fields: `notice_period_days` for notice accounts. `maturity_date` for fixed-term accounts. ISA fields (`isa_type`, `isa_subscription_year`, `isa_subscription_amount`) only when `is_isa` is true. Junior ISA fields (`beneficiary_name`, `beneficiary_dob`) only for junior ISA type.

### HoldingResource

```json
{
    "id": 1,
    "asset_type": "equity_fund",
    "security_name": "Vanguard FTSE Global All Cap Index Fund",
    "ticker": "VAFTGAG",
    "isin": "GB00BD3RZ582",
    "allocation_percent": 60,
    "quantity": 1500,
    "current_value": 52500,
    "current_price": 35.00,
    "cost_basis": 45000,
    "purchase_price": 30.00,
    "purchase_date": "2022-03-15",
    "dividend_yield": 1.8,
    "ocf_percent": 0.23,
    "gain_loss": 7500,
    "gain_loss_percent": 16.67,
    "created_at": "...",
    "updated_at": "...",
    "holdable": { "id": 1, "type": "InvestmentAccount" }
}
```

Computed fields: `gain_loss` and `gain_loss_percent` only appear when both `cost_basis` and `current_value` are present. The `holdable` relationship is polymorphic (can be InvestmentAccount or DCPension).

### ChattelResource

```json
{
    "id": 1,
    "user_id": 1,
    "household_id": 1,
    "joint_owner_id": 2,
    "trust_id": null,
    "name": "2022 BMW X5",
    "description": "Family car",
    "chattel_type": "vehicle",
    "category": "vehicle",
    "ownership_type": "joint",
    "ownership_percentage": 50,
    "purchase_price": 55000,
    "current_value": 38000,
    "purchase_date": "2022-01-15",
    "valuation_date": "2024-11-01",
    "country": "GB",
    "make": "BMW",
    "model": "X5",
    "year": 2022,
    "registration_number": "AB22 XYZ",
    "appreciation": -17000,
    "full_value": 38000,
    "user_share": 19000,
    "is_primary_owner": true,
    "is_shared": true,
    "is_wasting_asset": true,
    "notes": null,
    "created_at": "...",
    "updated_at": "...",
    "links": { "self": "/api/chattels/1" }
}
```

Conditional fields: vehicle-specific fields (`make`, `model`, `year`, `registration_number`) only for vehicle type. Computed fields include `appreciation` (current minus purchase), `user_share` (applies ownership percentage), `is_wasting_asset` (true for vehicles).

### BusinessInterestResource

```json
{
    "id": 1,
    "business_name": "Chen Technology Solutions",
    "business_type": "limited_company",
    "company_number": "12345678",
    "ownership_type": "individual",
    "ownership_percentage": 100,
    "current_valuation": 750000,
    "valuation_date": "2024-09-01",
    "valuation_method": "earnings_multiple",
    "annual_revenue": 2400000,
    "annual_profit": 320000,
    "annual_dividend_income": 50000,
    "description": "IT consulting firm",
    "country": "GB",
    "vat_registered": true,
    "vat_number": "GB123456789",
    "tax_year_end": "2025-03-31",
    "employee_count": 15,
    "trading_status": "active",
    "acquisition_date": "2015-06-01",
    "acquisition_cost": 50000,
    "bpr_eligible": true,
    "industry_sector": "technology",
    "notes": null,
    "created_at": "...",
    "updated_at": "...",
    "links": { "self": "/api/business-interests/1" }
}
```

Conditional field: `vat_number` only appears when `vat_registered` is true. BPR (Business Property Relief) eligibility is tracked for IHT calculations.

### GoalContributionResource

```json
{
    "id": 1,
    "goal_id": 1,
    "amount": 1000,
    "contribution_date": "2025-12-15",
    "contribution_type": "regular",
    "notes": null,
    "goal_balance_after": 12000,
    "streak_qualifying": true,
    "created_at": "...",
    "updated_at": "..."
}
```

Tracks individual contributions to goals. `streak_qualifying` indicates whether the contribution counts towards the user's contribution streak. `goal_balance_after` records the goal's running total after the contribution.
