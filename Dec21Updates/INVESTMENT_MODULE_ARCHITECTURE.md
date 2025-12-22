# Investment Module - Comprehensive Technical Report

**Version**: v0.4.1
**Last Updated**: December 21, 2025
**Module Status**: Production Ready

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Backend Architecture](#2-backend-architecture)
3. [Database Structure](#3-database-structure)
4. [Frontend Architecture](#4-frontend-architecture)
5. [API Routes](#5-api-routes)
6. [Business Logic & Algorithms](#6-business-logic--algorithms)
7. [Advanced Features](#7-advanced-features)
8. [Dependencies & Integrations](#8-dependencies--integrations)
9. [Caching Strategy](#9-caching-strategy)
10. [Validation Rules](#10-validation-rules)
11. [Error Handling](#11-error-handling)
12. [Performance Considerations](#12-performance-considerations)
13. [Testing Infrastructure](#13-testing-infrastructure)
14. [Security Considerations](#14-security-considerations)
15. [File Paths Reference](#15-file-paths-reference)

---

## 1. Executive Summary

The Investment Module is a sophisticated, multi-layered system for managing investment accounts, analyzing portfolios, and generating intelligent recommendations. It implements advanced financial planning features including Monte Carlo simulations, Modern Portfolio Theory (efficient frontier), tax optimization, and comprehensive portfolio analysis.

### Key Statistics

| Metric | Count |
|--------|-------|
| API Controllers | 18 |
| Service Classes | 24+ |
| Vue Components | 20+ |
| Database Models | 8 |
| API Routes | 100+ |
| Vuex Getters | 38+ |
| Account Types | 8 |
| Asset Types | 10 |
| Risk Levels | 5 |

---

## 2. Backend Architecture

### 2.1 Agent Layer - InvestmentAgent

**File**: `app/Agents/InvestmentAgent.php`

The InvestmentAgent orchestrates all investment business logic.

#### Constructor Dependencies

```php
public function __construct(
    private PortfolioAnalyzer $portfolioAnalyzer,
    private MonteCarloSimulator $monteCarloSimulator,
    private FeeAnalyzer $feeAnalyzer,
    private TaxEfficiencyCalculator $taxCalculator,
    private TaxConfigService $taxConfig
)
```

#### Key Methods

| Method | Purpose | Returns |
|--------|---------|---------|
| `analyze()` | Comprehensive portfolio analysis | Complete analysis package |
| `generateRecommendations()` | Prioritized investment recommendations | Array of recommendations |
| `buildScenarios()` | What-if scenarios (conservative, balanced, aggressive) | Scenario comparison data |
| `getPortfolioProjections()` | Monte Carlo projections for multiple periods | Projection data with percentiles |
| `clearCache()` | Clear cached analysis for a user | void |

#### Analysis Output Structure

```php
[
    'total_value' => 150000.00,
    'accounts_count' => 3,
    'returns' => [
        'ytd' => 5.2,
        'one_year' => 8.5,
        'three_year' => 25.3,
        'five_year' => 45.8
    ],
    'asset_allocation' => [
        'uk_equity' => 30,
        'us_equity' => 25,
        'international_equity' => 15,
        'bond' => 20,
        'cash' => 10
    ],
    'diversification_score' => 78,
    'risk_metrics' => [...],
    'fee_analysis' => [...],
    'tax_efficiency' => [...]
]
```

### 2.2 Controllers (18 Total)

#### Core Controllers

##### InvestmentController

**File**: `app/Http/Controllers/Api/InvestmentController.php` (26.8 KB)

Main CRUD and analysis controller.

| Method | Route | Purpose |
|--------|-------|---------|
| `index()` | GET / | Get all accounts, goals, risk profile |
| `analyze()` | POST /analyze | Run comprehensive analysis |
| `recommendations()` | GET /recommendations | Get prioritized recommendations |
| `scenarios()` | POST /scenarios | Build what-if scenarios |
| `startMonteCarlo()` | POST /monte-carlo | Start Monte Carlo job |
| `getMonteCarloResults()` | GET /monte-carlo/{jobId} | Check Monte Carlo results |
| `storeAccount()` | POST /accounts | Create investment account |
| `updateAccount()` | PUT /accounts/{id} | Update account |
| `destroyAccount()` | DELETE /accounts/{id} | Delete account |
| `storeHolding()` | POST /holdings | Create holding |
| `updateHolding()` | PUT /holdings/{id} | Update holding |
| `destroyHolding()` | DELETE /holdings/{id} | Delete holding |
| `storeGoal()` | POST /goals | Create investment goal |
| `updateGoal()` | PUT /goals/{id} | Update goal |
| `destroyGoal()` | DELETE /goals/{id} | Delete goal |
| `storeOrUpdateRiskProfile()` | POST /risk-profile | Set risk profile |

##### InvestmentProjectionController

**File**: `app/Http/Controllers/Api/InvestmentProjectionController.php` (1.3 KB)

| Method | Route | Purpose |
|--------|-------|---------|
| `getProjections()` | POST /projections | Get Monte Carlo portfolio projections |

#### Advanced Feature Controllers

**Directory**: `app/Http/Controllers/Api/Investment/`

| Controller | File Size | Purpose |
|-----------|-----------|---------|
| `AssetLocationController.php` | 7.8 KB | Tax wrapper optimization |
| `ContributionOptimizerController.php` | 4.5 KB | Contribution planning |
| `EfficientFrontierController.php` | 8.2 KB | Modern Portfolio Theory |
| `FeeImpactController.php` | 6.3 KB | Fee analysis |
| `GoalProgressController.php` | 5.1 KB | Goal tracking |
| `InvestmentPlanController.php` | 4.8 KB | Plan generation |
| `InvestmentRecommendationController.php` | 6.2 KB | Recommendation management |
| `InvestmentScenarioController.php` | 5.9 KB | Scenario management |
| `ModelPortfolioController.php` | 5.4 KB | Pre-built portfolios |
| `PerformanceAttributionController.php` | 7.1 KB | Performance analysis |
| `RebalancingActionsController.php` | 4.2 KB | Rebalancing CRUD |
| `RebalancingCalculationController.php` | 6.8 KB | Rebalancing calculations |
| `RebalancingStrategiesController.php` | 5.5 KB | Strategy evaluation |
| `RiskProfileController.php` | 5.7 KB | Risk questionnaire |
| `TaxOptimizationController.php` | 7.4 KB | Tax efficiency strategies |

### 2.3 Services (24+ Total)

#### Core Portfolio Services

**Directory**: `app/Services/Investment/`

##### PortfolioAnalyzer.php

Calculates portfolio metrics and analysis.

```php
public function analyze(int $userId): array
public function calculateAllocation(array $holdings): array
public function calculateReturns(array $accounts): array
public function calculateDiversificationScore(array $allocation): int
public function calculateRiskMetrics(array $holdings): array
```

**Diversification Score Calculation**:
- Base score: 100
- Penalty for concentration >30%: -10
- Penalty for concentration >40%: -20
- Penalty for concentration >50%: -30
- Bonus for 3+ asset types: +5
- Penalty for single asset type: -20

##### MonteCarloSimulator.php

Box-Muller Monte Carlo simulation engine.

```php
public function simulate(array $params): array
public function generateNormalRandom(float $mean, float $stdDev): float
public function calculatePercentiles(array $results): array
```

**Box-Muller Implementation**:
```php
$u1 = mt_rand() / mt_getrandmax();
$u2 = mt_rand() / mt_getrandmax();
$z0 = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
return $mean + $z0 * $stdDev;
```

##### InvestmentProjectionService.php

Comprehensive portfolio projections.

```php
public function getProjections(User $user, array $params): array
public function projectPortfolio(array $holdings, int $years): array
public function calculateProbabilities(array $simulations, float $target): array
```

##### FeeAnalyzer.php

Fee impact analysis.

```php
public function analyze(int $userId): array
public function calculatePlatformFees(array $accounts): float
public function calculateFundFees(array $holdings): float
public function calculateFeeDrag(float $totalFees, float $portfolioValue): float
public function suggestLowCostAlternatives(array $holdings): array
```

##### TaxEfficiencyCalculator.php

Tax position and optimization.

```php
public function analyze(int $userId): array
public function calculateUnrealizedGains(array $holdings): array
public function calculateDividendTax(float $income, float $totalIncome): float
public function calculateCGTLiability(float $gains, float $totalIncome): float
public function identifyHarvestingOpportunities(array $holdings): array
public function calculateTaxEfficiencyScore(array $accounts): int
```

##### ContributionEstimatorService.php

Contribution estimation per account.

```php
public function estimate(InvestmentAccount $account): float
public function estimateForISA(InvestmentAccount $account): float
public function estimateForGIA(InvestmentAccount $account): float
```

#### Advanced Analytics Services

**Directory**: `app/Services/Investment/Analytics/`

| Service | Purpose |
|---------|---------|
| `CorrelationMatrixCalculator.php` | Asset correlation calculations |
| `CovarianceMatrixCalculator.php` | Portfolio variance calculations |
| `EfficientFrontierCalculator.php` | MPT frontier generation |
| `HoldingsDataExtractor.php` | Extract holdings for analysis |
| `MarkowitzOptimizer.php` | Modern portfolio optimization |

#### Tax Optimization Services

**Directory**: `app/Services/Investment/Tax/`

| Service | Purpose |
|---------|---------|
| `TaxOptimizationAnalyzer.php` | Comprehensive tax position |
| `CGTHarvestingCalculator.php` | Capital gains loss harvesting |
| `ISAAllowanceOptimizer.php` | ISA allocation optimization |
| `BedAndISACalculator.php` | Bed and ISA transfer analysis |

#### Risk Management Services

**Directory**: `app/Services/Investment/RiskProfile/`

| Service | Purpose |
|---------|---------|
| `RiskProfiler.php` | Risk profile generation |
| `RiskQuestionnaire.php` | Risk assessment questionnaire |
| `CapacityForLossAnalyzer.php` | Loss capacity analysis |

#### Rebalancing Services

**Directory**: `app/Services/Investment/Rebalancing/`

| Service | Purpose |
|---------|---------|
| `RebalancingCalculator.php` | Calculate rebalancing actions |
| `TaxAwareRebalancer.php` | CGT-aware rebalancing |
| `DriftAnalyzer.php` | Drift from target allocation |
| `RebalancingStrategyService.php` | Strategy evaluation |

#### Performance Analysis Services

**Directory**: `app/Services/Investment/Performance/`

| Service | Purpose |
|---------|---------|
| `PerformanceAttributionAnalyzer.php` | Attribution analysis |
| `BenchmarkComparator.php` | Benchmark comparison |
| `AlphaBetaCalculator.php` | Alpha/beta calculations |

#### Other Specialized Services

| Service | Purpose |
|---------|---------|
| `AssetAllocationOptimizer.php` | Optimize asset allocation |
| `InvestmentPlanGenerator.php` | Generate comprehensive plans |
| `ScenarioService.php` | Build investment scenarios |
| `ContributionOptimizer.php` | Optimize contribution strategy |

### 2.4 Database Models

#### InvestmentAccount

**File**: `app/Models/Investment/InvestmentAccount.php`

```php
protected $fillable = [
    'user_id',
    'joint_owner_id',
    'household_id',
    'trust_id',
    'ownership_type',
    'ownership_percentage',
    'account_type',
    'account_type_other',
    'country',
    'provider',
    'account_number',
    'platform',
    'current_value',
    'total_holdings_value',
    'contributions_ytd',
    'tax_year',
    'platform_fee_percent',
    'isa_type',
    'isa_subscription_current_year',
    'risk_preference',
    'has_custom_risk'
];

protected $casts = [
    'current_value' => 'decimal:2',
    'ownership_percentage' => 'decimal:2',
    'platform_fee_percent' => 'decimal:4',
    'isa_subscription_current_year' => 'decimal:2',
    'has_custom_risk' => 'boolean'
];
```

**Relationships**:
```php
public function holdings(): MorphMany
public function user(): BelongsTo
public function jointOwner(): BelongsTo
public function household(): BelongsTo
public function trust(): BelongsTo
```

**Account Types**:
- `isa` - Individual Savings Account (no joint allowed)
- `gia` - General Investment Account
- `nsi` - NS&I (National Savings & Investments)
- `onshore_bond` - Onshore bond
- `offshore_bond` - Offshore bond
- `vct` - Venture Capital Trust
- `eis` - Enterprise Investment Scheme
- `other` - Custom type

#### Holding

**File**: `app/Models/Investment/Holding.php`

Polymorphic model that can belong to InvestmentAccount or DCPension.

```php
protected $fillable = [
    'holdable_id',
    'holdable_type',
    'asset_type',
    'security_name',
    'ticker',
    'isin',
    'allocation_percent',
    'quantity',
    'purchase_price',
    'purchase_date',
    'current_price',
    'current_value',
    'cost_basis',
    'dividend_yield',
    'ocf_percent'
];

protected $casts = [
    'allocation_percent' => 'decimal:2',
    'quantity' => 'decimal:6',
    'purchase_price' => 'decimal:4',
    'current_price' => 'decimal:4',
    'current_value' => 'decimal:2',
    'cost_basis' => 'decimal:2',
    'dividend_yield' => 'decimal:4',
    'ocf_percent' => 'decimal:4'
];
```

**Relationships**:
```php
public function holdable(): MorphTo
public function investmentAccount(): BelongsTo
```

**Asset Types**:
- `equity` - Generic equity
- `bond` - Bonds
- `fund` - Mutual fund
- `etf` - Exchange-traded fund
- `alternative` - Alternative investments
- `uk_equity` - UK stocks
- `us_equity` - US stocks
- `international_equity` - International stocks
- `cash` - Cash holdings
- `property` - Property/real estate

#### RiskProfile

**File**: `app/Models/Investment/RiskProfile.php`

```php
protected $fillable = [
    'user_id',
    'risk_tolerance',
    'risk_level',
    'capacity_for_loss_percent',
    'time_horizon_years',
    'knowledge_level',
    'attitude_to_volatility',
    'esg_preference',
    'risk_assessed_at',
    'is_self_assessed'
];

protected $casts = [
    'capacity_for_loss_percent' => 'decimal:2',
    'esg_preference' => 'boolean',
    'is_self_assessed' => 'boolean',
    'risk_assessed_at' => 'datetime'
];
```

**Risk Tolerance (3-level questionnaire)**:
- `cautious`
- `balanced`
- `adventurous`

**Risk Level (5-level self-select)**:
- `low` - Conservative, capital preservation
- `lower_medium` - Cautious growth
- `medium` - Balanced growth
- `upper_medium` - Growth-oriented
- `high` - Aggressive growth

#### InvestmentGoal

**File**: `app/Models/Investment/InvestmentGoal.php`

```php
protected $fillable = [
    'user_id',
    'goal_name',
    'goal_type',
    'target_amount',
    'target_date',
    'priority',
    'is_essential',
    'linked_account_ids'
];

protected $casts = [
    'target_amount' => 'decimal:2',
    'target_date' => 'date',
    'is_essential' => 'boolean',
    'linked_account_ids' => 'array'
];
```

**Goal Types**:
- `retirement` - Retirement funding
- `education` - Education funding
- `wealth` - Wealth building
- `home` - Home purchase

#### InvestmentPlan

**File**: `app/Models/Investment/InvestmentPlan.php`

```php
protected $fillable = [
    'user_id',
    'plan_version',
    'plan_data',
    'portfolio_health_score',
    'is_complete',
    'completeness_score',
    'generated_at'
];

protected $casts = [
    'plan_data' => 'array',
    'is_complete' => 'boolean',
    'generated_at' => 'datetime'
];
```

#### InvestmentRecommendation

**File**: `app/Models/Investment/InvestmentRecommendation.php`

```php
protected $fillable = [
    'user_id',
    'investment_plan_id',
    'category',
    'priority',
    'title',
    'description',
    'action_required',
    'impact_level',
    'potential_saving',
    'estimated_effort',
    'status',
    'due_date',
    'completed_at',
    'dismissed_at',
    'dismissal_reason'
];

protected $casts = [
    'potential_saving' => 'decimal:2',
    'due_date' => 'date',
    'completed_at' => 'datetime',
    'dismissed_at' => 'datetime'
];
```

**Status Values**:
- `pending` - Not yet actioned
- `completed` - Action taken
- `dismissed` - Dismissed with reason

#### InvestmentScenario

**File**: `app/Models/Investment/InvestmentScenario.php`

```php
protected $fillable = [
    'user_id',
    'name',
    'description',
    'scenario_type',
    'scenario_parameters',
    'results',
    'is_bookmark'
];

protected $casts = [
    'scenario_parameters' => 'array',
    'results' => 'array',
    'is_bookmark' => 'boolean'
];
```

#### RebalancingAction

**File**: `app/Models/Investment/RebalancingAction.php`

```php
protected $fillable = [
    'investment_account_id',
    'holding_id',
    'action_type',
    'current_percent',
    'target_percent',
    'amount_to_transact',
    'transaction_cost',
    'tax_impact',
    'priority',
    'notes'
];

protected $casts = [
    'current_percent' => 'decimal:2',
    'target_percent' => 'decimal:2',
    'amount_to_transact' => 'decimal:2',
    'transaction_cost' => 'decimal:2',
    'tax_impact' => 'decimal:2'
];
```

**Action Types**:
- `buy` - Purchase more
- `sell` - Reduce position
- `hold` - No action needed

### 2.5 Request Validation Classes

**Directory**: `app/Http/Requests/Investment/`

| Class | Purpose |
|-------|---------|
| `CalculateEfficientFrontierRequest.php` | Efficient frontier parameters |
| `OptimizePortfolioRequest.php` | Portfolio optimization parameters |

---

## 3. Database Structure

### 3.1 Tables

#### investment_accounts

```sql
CREATE TABLE `investment_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `joint_owner_id` bigint DEFAULT NULL,
  `household_id` bigint unsigned DEFAULT NULL,
  `trust_id` bigint unsigned DEFAULT NULL,
  `ownership_type` enum('individual','joint','trust') DEFAULT 'individual',
  `ownership_percentage` decimal(5,2) NOT NULL DEFAULT '100.00',
  `account_type` varchar(255) DEFAULT NULL,
  `account_type_other` varchar(255) DEFAULT NULL,
  `country` varchar(255) NOT NULL DEFAULT 'United Kingdom',
  `provider` varchar(255) DEFAULT NULL,
  `account_number` varchar(255) DEFAULT NULL,
  `platform` varchar(255) DEFAULT NULL,
  `current_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_holdings_value` decimal(15,2) DEFAULT NULL,
  `contributions_ytd` decimal(15,2) DEFAULT '0.00',
  `tax_year` varchar(10) DEFAULT NULL,
  `platform_fee_percent` decimal(5,4) DEFAULT '0.0000',
  `isa_type` varchar(50) DEFAULT NULL,
  `isa_subscription_current_year` decimal(15,2) DEFAULT '0.00',
  `risk_preference` enum('low','lower_medium','medium','upper_medium','high') DEFAULT NULL,
  `has_custom_risk` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `investment_accounts_user_id_index` (`user_id`),
  KEY `investment_accounts_user_id_account_type_index` (`user_id`,`account_type`),
  KEY `investment_accounts_user_id_tax_year_index` (`user_id`,`tax_year`),
  KEY `investment_accounts_household_id_index` (`household_id`),
  KEY `investment_accounts_trust_id_index` (`trust_id`),
  KEY `investment_accounts_joint_owner_id_index` (`joint_owner_id`),
  KEY `investment_accounts_ownership_type_idx` (`ownership_type`),
  CONSTRAINT `investment_accounts_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `investment_accounts_household_id_foreign`
    FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE SET NULL,
  CONSTRAINT `investment_accounts_trust_id_foreign`
    FOREIGN KEY (`trust_id`) REFERENCES `trusts` (`id`) ON DELETE SET NULL
);
```

#### holdings

```sql
CREATE TABLE `holdings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `holdable_id` bigint unsigned NOT NULL,
  `holdable_type` varchar(255) NOT NULL,
  `asset_type` enum('equity','bond','fund','etf','alternative',
                    'uk_equity','us_equity','international_equity','cash','property') NOT NULL,
  `allocation_percent` decimal(5,2) DEFAULT NULL,
  `security_name` varchar(255) NOT NULL,
  `ticker` varchar(255) DEFAULT NULL,
  `isin` varchar(255) DEFAULT NULL,
  `quantity` decimal(15,6) DEFAULT NULL,
  `purchase_price` decimal(15,4) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `current_price` decimal(15,4) DEFAULT NULL,
  `current_value` decimal(15,2) NOT NULL,
  `cost_basis` decimal(15,2) DEFAULT NULL,
  `dividend_yield` decimal(5,4) NOT NULL DEFAULT '0.0000',
  `ocf_percent` decimal(5,4) NOT NULL DEFAULT '0.0000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `holdings_asset_type_index` (`asset_type`),
  KEY `holdings_holdable_type_holdable_id_index` (`holdable_type`,`holdable_id`),
  KEY `holdings_holdable_id_type_idx` (`holdable_id`,`holdable_type`)
);
```

#### risk_profiles

```sql
CREATE TABLE `risk_profiles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `risk_tolerance` enum('cautious','balanced','adventurous') NOT NULL,
  `risk_level` enum('low','lower_medium','medium','upper_medium','high') DEFAULT NULL,
  `capacity_for_loss_percent` decimal(5,2) NOT NULL,
  `time_horizon_years` int NOT NULL,
  `knowledge_level` enum('novice','intermediate','experienced') NOT NULL,
  `attitude_to_volatility` varchar(255) DEFAULT NULL,
  `esg_preference` tinyint(1) NOT NULL DEFAULT '0',
  `risk_assessed_at` timestamp NULL DEFAULT NULL,
  `is_self_assessed` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `risk_profiles_user_id_index` (`user_id`),
  CONSTRAINT `risk_profiles_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
```

#### investment_goals

```sql
CREATE TABLE `investment_goals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `goal_name` varchar(255) NOT NULL,
  `goal_type` enum('retirement','education','wealth','home') NOT NULL,
  `target_amount` decimal(15,2) NOT NULL,
  `target_date` date NOT NULL,
  `priority` enum('high','medium','low') NOT NULL DEFAULT 'medium',
  `is_essential` tinyint(1) NOT NULL DEFAULT '0',
  `linked_account_ids` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `investment_goals_user_id_index` (`user_id`),
  CONSTRAINT `investment_goals_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
```

### 3.2 Relationships Diagram

```
users (1)
├── investment_accounts (1:N)
│   ├── holdings (1:N via polymorphic MorphMany)
│   ├── joint_owner_id → users (N:1)
│   ├── household_id → households (N:1)
│   └── trust_id → trusts (N:1)
├── investment_goals (1:N)
├── risk_profiles (1:1)
├── investment_plans (1:N)
│   └── investment_recommendations (1:N)
├── investment_scenarios (1:N)
└── dc_pensions (via Retirement module)
    └── holdings (1:N via polymorphic MorphMany)
```

### 3.3 Polymorphic Holdings

Holdings can belong to multiple parent types:

```php
// Holdings table structure
holdable_id: 123
holdable_type: 'App\Models\Investment\InvestmentAccount'
// OR
holdable_type: 'App\Models\DCPension'
```

---

## 4. Frontend Architecture

### 4.1 Vuex Store Module

**File**: `resources/js/store/modules/investment.js`

#### State (25+ Properties)

```javascript
{
  accounts: [],
  goals: [],
  riskProfile: null,
  analysis: null,
  recommendations: [],
  monteCarloResults: {},
  monteCarloStatus: {},
  optimizationResult: null,
  scenarios: null,
  investmentPlan: null,
  investmentPlans: [],
  investmentRecommendations: [],
  scenarioTemplates: [],
  investmentScenarios: [],
  scenarioComparison: null,
  contributionOptimization: null,
  assetLocationAnalysis: null,
  performanceAttribution: null,
  goalProjections: {},
  feeAnalysis: null,
  portfolioProjections: null,
  projectionsLoading: false,
  loading: false,
  error: null
}
```

#### Getters (38+)

**Portfolio Metrics**:
| Getter | Returns |
|--------|---------|
| `totalPortfolioValue` | Sum of all account values |
| `ytdReturn` | Year-to-date return percentage |
| `assetAllocation` | Allocation by asset type |
| `allHoldings` | Flattened holdings from all accounts |
| `holdingsCount` | Total number of holdings |

**Fee Analysis**:
| Getter | Returns |
|--------|---------|
| `totalFees` | Annual fee total |
| `feeDragPercent` | Fee as percentage of portfolio |

**Tax Efficiency**:
| Getter | Returns |
|--------|---------|
| `unrealisedGains` | Total unrealized gains |
| `taxEfficiencyScore` | Score 0-100 |

**Risk Assessment**:
| Getter | Returns |
|--------|---------|
| `riskLevel` | Current risk level |
| `mainRiskLevel` | Main profile risk level |
| `hasRiskProfile` | Boolean |

**ISA Tracking**:
| Getter | Returns |
|--------|---------|
| `isaAccounts` | Array of ISA accounts |
| `totalISAValue` | Sum of ISA values |
| `isaPercentage` | ISA as % of portfolio |
| `totalISAContributions` | YTD ISA contributions |

### 4.2 Vue Views

**Directory**: `resources/js/views/Investment/`

| View | Purpose |
|------|---------|
| `InvestmentDashboard.vue` | Main investment dashboard |
| `AccountDetailView.vue` | Individual account overview |
| `AccountSummaryPanel.vue` | Portfolio summary |
| `AccountFeesPanel.vue` | Fee breakdown |
| `AccountHoldingsPanel.vue` | Holdings display |
| `AccountPerformancePanel.vue` | Performance metrics |

### 4.3 Vue Components

**Directory**: `resources/js/components/Investment/`

#### Overview Components

| Component | Purpose |
|-----------|---------|
| `InvestmentOverviewCard.vue` | Portfolio overview card |
| `ComprehensiveInvestmentPlan.vue` | Full plan display |
| `InvestmentRecommendationsTracker.vue` | Recommendation tracking |

#### Chart Components

| Component | Purpose |
|-----------|---------|
| `InvestmentProjectionChart.vue` | Monte Carlo visualization |
| `EfficientFrontier.vue` | MPT frontier chart |

#### Goal Components

| Component | Purpose |
|-----------|---------|
| `Goals.vue` | Goal management |
| `GoalCard.vue` | Individual goal card |

#### Holdings Components

| Component | Purpose |
|-----------|---------|
| `HoldingForm.vue` | Holding CRUD form |
| `HoldingsTable.vue` | Holdings list display |

#### Scenario Components

| Component | Purpose |
|-----------|---------|
| `WhatIfScenarios.vue` | Scenario builder |

#### Tax Optimization Components

| Component | Purpose |
|-----------|---------|
| `TaxOptimizationRecommendations.vue` | Tax recommendations |
| `CGTHarvestingOpportunities.vue` | CGT loss harvesting |
| `BedAndISATransfers.vue` | Transfer strategy |

#### Asset Location Components

| Component | Purpose |
|-----------|---------|
| `AssetLocationOptimizer.vue` | Account type optimization |
| `WrapperOptimizer.vue` | Tax wrapper optimization |

#### Performance Components

| Component | Purpose |
|-----------|---------|
| `PerformanceAttribution.vue` | Performance analysis |

### 4.4 Frontend Service

**File**: `resources/js/services/investmentService.js`

#### Data Retrieval

| Method | Endpoint | Returns |
|--------|----------|---------|
| `getInvestmentData()` | GET /investment | All accounts, goals, risk profile |
| `analyzeInvestment()` | POST /investment/analyze | Comprehensive analysis |
| `getRecommendations()` | GET /investment/recommendations | Prioritized recommendations |

#### Account Management

| Method | Endpoint |
|--------|----------|
| `createAccount(data)` | POST /investment/accounts |
| `updateAccount(id, data)` | PUT /investment/accounts/{id} |
| `deleteAccount(id)` | DELETE /investment/accounts/{id} |

#### Holdings Management

| Method | Endpoint |
|--------|----------|
| `createHolding(data)` | POST /investment/holdings |
| `updateHolding(id, data)` | PUT /investment/holdings/{id} |
| `deleteHolding(id)` | DELETE /investment/holdings/{id} |

#### Goals Management

| Method | Endpoint |
|--------|----------|
| `createGoal(data)` | POST /investment/goals |
| `updateGoal(id, data)` | PUT /investment/goals/{id} |
| `deleteGoal(id)` | DELETE /investment/goals/{id} |

#### Risk Profile

| Method | Endpoint |
|--------|----------|
| `getRiskProfile()` | GET /investment/risk-profile |
| `createOrUpdateRiskProfile(data)` | POST /investment/risk-profile |

#### Scenarios & Projections

| Method | Endpoint |
|--------|----------|
| `runScenario(params)` | POST /investment/scenarios |
| `startMonteCarlo(params)` | POST /investment/monte-carlo |
| `getMonteCarloResults(jobId)` | GET /investment/monte-carlo/{jobId} |
| `getProjections(params)` | POST /investment/projections |

#### Advanced Analysis

| Method | Endpoint |
|--------|----------|
| `analyzeAssetLocation()` | GET /investment/asset-location/analyze |
| `getTaxOptimization()` | GET /investment/tax-optimization/analyze |
| `getPerformanceAnalysis()` | GET /investment/performance/analyze |
| `calculateEfficientFrontier(params)` | POST /investment/efficient-frontier/calculate |
| `analyzeGoalProgress(goalId)` | GET /investment/goals/{goalId}/progress |

---

## 5. API Routes

**Base Route**: `/api/investment`
**Middleware**: `auth:sanctum`

### 5.1 Core Routes

```
GET    /                              - Get all accounts, goals, risk profile
POST   /analyze                       - Run comprehensive analysis
GET    /recommendations               - Get recommendations
POST   /scenarios                     - Build what-if scenarios
```

### 5.2 Monte Carlo Routes

```
POST   /monte-carlo                   - Start simulation job
GET    /monte-carlo/{jobId}           - Check simulation results
```

### 5.3 Projections Routes

```
POST   /projections                   - Get portfolio projections
```

### 5.4 Account CRUD Routes

```
POST   /accounts                      - Create account
PUT    /accounts/{id}                 - Update account
DELETE /accounts/{id}                 - Delete account
```

### 5.5 Holdings CRUD Routes

```
POST   /holdings                      - Create holding
PUT    /holdings/{id}                 - Update holding
DELETE /holdings/{id}                 - Delete holding
```

### 5.6 Goals CRUD Routes

```
POST   /goals                         - Create goal
PUT    /goals/{id}                    - Update goal
DELETE /goals/{id}                    - Delete goal
```

### 5.7 Risk Profile Route

```
POST   /risk-profile                  - Create/update risk profile
```

### 5.8 Portfolio Optimization Routes

```
POST   /optimization/efficient-frontier  - Calculate efficient frontier
GET    /optimization/current-position    - Get current portfolio position
GET    /optimization/correlation-matrix  - Get correlation matrix
POST   /optimization/minimize-variance   - Minimize variance portfolio
POST   /optimization/maximize-sharpe     - Maximum Sharpe ratio portfolio
POST   /optimization/target-return       - Target return portfolio
POST   /optimization/risk-parity         - Risk parity portfolio
```

### 5.9 Rebalancing Routes

```
POST   /rebalancing/calculate            - Calculate rebalancing actions
POST   /rebalancing/compare-cgt          - Compare CGT impact
POST   /rebalancing/analyze-drift        - Analyze allocation drift
POST   /rebalancing/evaluate-strategies  - Evaluate strategies
GET    /rebalancing/actions              - Get rebalancing actions
POST   /rebalancing/actions              - Create action
PUT    /rebalancing/actions/{id}         - Update action
DELETE /rebalancing/actions/{id}         - Delete action
```

### 5.10 Contribution Routes

```
POST   /contribution/optimize            - Optimize contributions
POST   /contribution/affordability       - Analyze affordability
POST   /contribution/lump-sum-vs-dca     - Compare lump sum vs DCA
```

### 5.11 Tax Optimization Routes

```
GET    /tax-optimization/isa-strategy    - ISA strategy recommendations
GET    /tax-optimization/cgt-harvesting  - CGT harvesting opportunities
GET    /tax-optimization/bed-and-isa     - Bed and ISA analysis
POST   /tax-optimization/calculate-savings - Calculate tax savings
```

### 5.12 Asset Location Routes

```
GET    /asset-location/analyze           - Analyze asset location
GET    /asset-location/recommendations   - Get recommendations
GET    /asset-location/tax-drag          - Calculate tax drag
POST   /asset-location/compare-accounts  - Compare account types
```

### 5.13 Performance Routes

```
GET    /performance/analyze              - Performance analysis
GET    /performance/benchmark            - Benchmark comparison
GET    /performance/multi-benchmark      - Multi-benchmark comparison
GET    /performance/risk-metrics         - Risk metrics
```

### 5.14 Goals Analysis Routes

```
GET    /goals/{goalId}/progress          - Goal progress
GET    /goals/{goalId}/shortfall         - Shortfall analysis
POST   /goals/calculate-probability      - Calculate goal probability
```

### 5.15 Fees Routes

```
GET    /fees/analyze                     - Analyze portfolio fees
GET    /fees/holdings                    - Analyze holding fees
POST   /fees/ocf-impact                  - OCF impact calculation
GET    /fees/active-vs-passive           - Active vs passive comparison
GET    /fees/alternatives/{holdingId}    - Find cheaper alternatives
GET    /fees/compare-platforms           - Compare platform fees
```

### 5.16 Risk Profile Routes

```
GET    /risk-profile/questionnaire       - Get questionnaire
POST   /risk-profile/calculate-score     - Calculate score
POST   /risk-profile/generate            - Generate profile
GET    /risk-profile                     - Get current profile
POST   /risk-profile/capacity            - Analyze capacity for loss
```

### 5.17 Risk Preference Routes (5-level system)

```
GET    /risk/levels                      - Get all risk levels
GET    /risk/profile                     - Get user's risk profile
POST   /risk/profile                     - Set risk profile
GET    /risk/allowed-levels              - Get allowed product levels
POST   /risk/validate-product-level      - Validate product risk level
GET    /risk/config/{level}              - Get risk configuration
```

### 5.18 Model Portfolio Routes

```
GET    /model-portfolio/{riskLevel}      - Get model portfolio
GET    /model-portfolio/all              - Get all portfolios
POST   /model-portfolio/compare          - Compare with model
GET    /model-portfolio/glide-path       - Get glide path
POST   /model-portfolio/optimize-by-age  - Optimize by age
POST   /model-portfolio/optimize-by-horizon - Optimize by horizon
```

### 5.19 Efficient Frontier Routes

```
POST   /efficient-frontier/calculate      - Calculate frontier
POST   /efficient-frontier/optimal-by-return - Find by target return
POST   /efficient-frontier/optimal-by-risk   - Find by risk level
POST   /efficient-frontier/compare        - Compare portfolios
GET    /efficient-frontier/current-position - Current portfolio position
```

### 5.20 Investment Plan Routes

```
POST   /plan/generate                    - Generate comprehensive plan
GET    /plan                             - Get latest plan
GET    /plan/all                         - Get all plans
GET    /plan/{id}                        - Get specific plan
DELETE /plan/{id}                        - Delete plan
```

### 5.21 Recommendation Routes

```
GET    /recommendations/dashboard        - Recommendation dashboard
GET    /recommendations                  - List recommendations
POST   /recommendations                  - Create recommendation
GET    /recommendations/{id}             - Get specific recommendation
PUT    /recommendations/{id}             - Update recommendation
DELETE /recommendations/{id}             - Delete recommendation
PUT    /recommendations/{id}/status      - Update status
POST   /recommendations/bulk-update-status - Bulk update status
```

### 5.22 Scenario Routes

```
GET    /scenarios/templates              - Get scenario templates
GET    /scenarios                        - List scenarios
POST   /scenarios                        - Create scenario
GET    /scenarios/{id}                   - Get specific scenario
PUT    /scenarios/{id}                   - Update scenario
DELETE /scenarios/{id}                   - Delete scenario
POST   /scenarios/{id}/run               - Run scenario
GET    /scenarios/{id}/results           - Get scenario results
POST   /scenarios/compare                - Compare scenarios
POST   /scenarios/{id}/save              - Bookmark scenario
POST   /scenarios/{id}/unsave            - Remove bookmark
```

---

## 6. Business Logic & Algorithms

### 6.1 Monte Carlo Simulation

**File**: `MonteCarloSimulator.php`

**Algorithm**: Box-Muller Transform for Normal Distribution

```php
// For each iteration (default 1000):
for ($i = 0; $i < $iterations; $i++) {
    $value = $startValue;

    // For each month:
    for ($month = 0; $month < $totalMonths; $month++) {
        // Generate random return ~ N(monthly_return, monthly_volatility)
        $monthlyReturn = $this->generateNormalRandom(
            $expectedReturn / 12,
            $volatility / sqrt(12)
        );

        // Update portfolio
        $value = $value * (1 + $monthlyReturn) + $monthlyContribution;

        // Store year-end values
        if (($month + 1) % 12 === 0) {
            $yearEndValues[$year][] = $value;
        }
    }
}

// Calculate percentiles (10th, 25th, 50th, 75th, 90th)
```

**Box-Muller Implementation**:
```php
public function generateNormalRandom(float $mean, float $stdDev): float
{
    $u1 = mt_rand() / mt_getrandmax();
    $u2 = mt_rand() / mt_getrandmax();
    $z0 = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    return $mean + $z0 * $stdDev;
}
```

**Parameters**:
- `start_value`: Initial portfolio value
- `monthly_contribution`: Regular contribution
- `expected_return`: Annual return (e.g., 0.07 = 7%)
- `volatility`: Annual volatility (e.g., 0.15 = 15%)
- `years`: Projection period (1-50)
- `iterations`: Simulation runs (100-10,000, default 1000)

### 6.2 Portfolio Analysis

**File**: `PortfolioAnalyzer.php`

#### Total Value Calculation

```php
$totalValue = $accounts->sum('current_value');
```

#### Returns Calculation

```php
$totalCostBasis = $holdings->sum('cost_basis');
$totalCurrentValue = $holdings->sum('current_value');
$totalGain = $totalCurrentValue - $totalCostBasis;
$returnPercent = ($totalGain / $totalCostBasis) * 100;
```

#### Asset Allocation

```php
$allocation = $holdings->groupBy('asset_type')
    ->map(function ($group) use ($totalValue) {
        $assetValue = $group->sum('current_value');
        return round(($assetValue / $totalValue) * 100, 2);
    });
```

#### Diversification Score

```php
// Base score: 100
$score = 100;

// Penalties for concentration
foreach ($allocation as $percent) {
    if ($percent > 50) $score -= 30;
    elseif ($percent > 40) $score -= 20;
    elseif ($percent > 30) $score -= 10;
}

// Bonus for multiple asset types
if (count($allocation) >= 3) $score += 5;

// Penalty for single asset type
if (count($allocation) === 1) $score -= 20;

return max(0, min(100, $score));
```

### 6.3 Tax Efficiency Calculation

**File**: `TaxEfficiencyCalculator.php`

#### Unrealized Gains

```php
foreach ($holdings as $holding) {
    if ($holding->cost_basis && $holding->current_value > $holding->cost_basis) {
        $gain = $holding->current_value - $holding->cost_basis;
        $gains[] = [
            'holding' => $holding,
            'gain' => $gain
        ];
    }
}
```

#### Dividend Tax

```php
public function calculateDividendTax(float $dividendIncome, float $totalIncome): float
{
    $allowance = $this->taxConfig->getDividendAllowance(); // £1,000
    $taxableIncome = max(0, $dividendIncome - $allowance);

    // Determine rate based on total income
    $rate = $this->getDividendRate($totalIncome);

    return $taxableIncome * $rate;
}
```

#### CGT Liability

```php
public function calculateCGTLiability(float $realizedGains, float $totalIncome): float
{
    $exemptAmount = $this->taxConfig->getCGTExemptAmount(); // £3,000
    $taxableGains = max(0, $realizedGains - $exemptAmount);

    // Determine rate (20% or 40% based on income)
    $rate = $totalIncome > 50270 ? 0.40 : 0.20;

    return $taxableGains * $rate;
}
```

#### Tax Efficiency Score

```php
public function calculateTaxEfficiencyScore(array $accounts): int
{
    $score = 100;

    // Calculate ISA percentage
    $isaValue = collect($accounts)
        ->where('account_type', 'isa')
        ->sum('current_value');
    $totalValue = collect($accounts)->sum('current_value');
    $isaPercent = ($isaValue / $totalValue) * 100;

    // Penalties/bonuses
    if ($isaPercent < 30) $score -= 20;
    elseif ($isaPercent < 50) $score -= 10;
    elseif ($isaPercent > 50) $score += 10;

    // Penalty for large unrealized gains in GIA
    $giaGains = $this->calculateUnrealizedGainsInGIA($accounts);
    if ($giaGains > 10000) $score -= 20;
    elseif ($giaGains > 5000) $score -= 10;

    return max(0, min(100, $score));
}
```

### 6.4 Fee Impact Analysis

**File**: `FeeAnalyzer.php`

```php
// Total Annual Fees Calculation
$platformFees = 0;
foreach ($accounts as $account) {
    $platformFees += $account->current_value * ($account->platform_fee_percent / 100);
}

$fundFees = 0;
foreach ($holdings as $holding) {
    $fundFees += $holding->current_value * ($holding->ocf_percent / 100);
}

$transactionCosts = $totalValue * 0.001; // Estimated 0.1%

$totalFees = $platformFees + $fundFees + $transactionCosts;

// Fee Drag
$feeDrag = ($totalFees / $totalValue) * 100;

// Low-Cost Alternative Analysis
$currentWeightedOCF = $holdings->sum(function ($h) use ($totalValue) {
    return ($h->current_value / $totalValue) * $h->ocf_percent;
});
$lowCostOCF = 0.15; // Typical index fund
$annualSaving = ($currentWeightedOCF - $lowCostOCF) * $totalValue / 100;
```

### 6.5 Contribution Estimation

**File**: `ContributionEstimatorService.php`

```php
public function estimate(InvestmentAccount $account): float
{
    // User override
    if ($account->monthly_contribution_override) {
        return $account->monthly_contribution_override;
    }

    // ISA estimation
    if ($account->account_type === 'isa') {
        if ($account->isa_subscription_current_year) {
            $monthsElapsed = $this->getMonthsElapsedInTaxYear();
            return $account->isa_subscription_current_year / $monthsElapsed;
        }
        return 20000 / 12; // £1,666.67/month default
    }

    // GIA estimation
    if ($account->account_type === 'gia') {
        return $account->current_value * 0.05 / 12; // 5% annually
    }

    return 0; // SIPP handled via Retirement module
}

private function getMonthsElapsedInTaxYear(): int
{
    $now = Carbon::now();
    $taxYearStart = Carbon::create($now->year, 4, 6);
    if ($now->lt($taxYearStart)) {
        $taxYearStart = $taxYearStart->subYear();
    }
    return max(1, $now->diffInMonths($taxYearStart) + 1);
}
```

### 6.6 Efficient Frontier Calculation

**File**: `EfficientFrontierCalculator.php`

Implementation of Modern Portfolio Theory:

1. **Correlation Matrix**: Calculate pairwise correlations between assets
2. **Covariance Matrix**: Calculate variance-covariance matrix
3. **Markowitz Optimizer**: Find efficient portfolios
4. **Optimization Strategies**:
   - Minimum Variance Portfolio
   - Maximum Sharpe Ratio Portfolio
   - Target Return Portfolio
   - Risk Parity Portfolio

### 6.7 Risk Profile Scoring

**File**: `RiskProfiler.php`

Combines multiple factors:
1. **Risk Tolerance** (questionnaire): cautious/balanced/adventurous
2. **Capacity for Loss**: Percentage willing to lose
3. **Time Horizon**: Years until funds needed
4. **Knowledge Level**: novice/intermediate/experienced
5. **Attitude to Volatility**: Qualitative assessment
6. **ESG Preference**: Environmental/social/governance

Outputs 5-level risk profile:
- `low`: Conservative, capital preservation
- `lower_medium`: Cautious growth
- `medium`: Balanced growth
- `upper_medium`: Growth-oriented
- `high`: Aggressive growth

---

## 7. Advanced Features

### 7.1 Joint Account Handling

**Single-Record Architecture**:

```php
// ONE database record stores:
[
    'user_id' => 1,              // Primary owner (can edit/delete)
    'joint_owner_id' => 2,       // Secondary owner (view only)
    'current_value' => 320000,   // FULL value, not split
    'ownership_percentage' => 50 // Primary owner's share %
]

// Example: Joint GIA worth £320,000 (50/50 split)
// Database: current_value = 320000, ownership_percentage = 50
// Primary owner's share: 320000 * (50/100) = £160,000
// Joint owner also sees this record via query
// NO reciprocal records created
```

**Query Pattern**:
```php
// Get all accounts where user is owner OR joint owner
InvestmentAccount::where('user_id', $userId)
    ->orWhere('joint_owner_id', $userId)
    ->get();
```

### 7.2 Polymorphic Holdings

Holdings can belong to multiple parent types:

```php
// Holdings table
holdable_id: 123
holdable_type: 'App\Models\Investment\InvestmentAccount'
// OR
holdable_type: 'App\Models\DCPension' (from Retirement module)

// Model relationship
public function holdable(): MorphTo
{
    return $this->morphTo();
}
```

### 7.3 Monte Carlo Queue System

Queue job system for long-running simulations:

```php
// 1. Frontend: POST /investment/monte-carlo
$jobId = Str::uuid();

// 2. Backend: Dispatch job
RunMonteCarloSimulation::dispatch($userId, $params, $jobId);
Cache::put("monte_carlo_status_{$jobId}", 'running', 3600);

// 3. Job execution
// Runs simulation, stores results in Cache
Cache::put("monte_carlo_results_{$jobId}", $results, 3600);
Cache::put("monte_carlo_status_{$jobId}", 'completed', 3600);

// 4. Frontend: Polls GET /monte-carlo/{jobId}
// Returns status and results when complete
```

**Status Values**:
- `running` - Job in progress
- `completed` - Results available
- `failed` - Error occurred

### 7.4 ISA Tax Wrapper Rules

```php
// ISAs can ONLY be individually owned (UK tax rule)
if ($accountType === 'isa' && $ownershipType !== 'individual') {
    throw new ValidationException("ISAs cannot be joint or in trust");
}

// Track ISA subscription (max £20,000/year)
'isa_subscription_current_year' => 'decimal:2'

// ISA types
'stocks_and_shares' - Stocks and shares ISA
'lifetime' - Lifetime ISA (age 18-40)
'innovative_finance' - Peer-to-peer lending ISA
```

### 7.5 Automatic Cash Holding Management

When holdings are added/updated/deleted:

```php
// Calculate total allocation of non-cash holdings
$nonCashTotal = $holdings->where('asset_type', '!=', 'cash')
    ->sum('allocation_percent');

// Cash holding gets remaining allocation
$cashAllocation = 100 - $nonCashTotal;

// Update cash holding value proportionally
$cashValue = ($cashAllocation / 100) * $accountValue;
```

---

## 8. Dependencies & Integrations

### 8.1 Internal Module Dependencies

**Investment → Retirement Module**:
- DC Pension holdings are polymorphic holdings (shared table)
- Can include retirement accounts in asset allocation analysis
- Retirement projections may reference investment returns

**Investment → Savings Module**:
- Some accounts might be ISAs (overlap with savings)
- Tax efficiency considers both modules

**Investment → Protection Module**:
- Investment projections inform life insurance needs
- Overall financial health score includes investments

**Investment → Estate Module**:
- Investment accounts are part of gross estate
- IHT calculations include investment values
- Tax wrappers affect IHT treatment

**Investment → Profile Module**:
- User income affects tax efficiency calculations
- Household structure affects ownership types
- Age/time horizon affects risk profile

### 8.2 Service Dependencies

| Service | Source | Usage |
|---------|--------|-------|
| `TaxConfigService` | Core | Tax rates and allowances |
| `RiskPreferenceService` | Investment | Risk parameters |
| `Queue Jobs` | Laravel | Monte Carlo processing |

### 8.3 External Interfaces

- **No live market data feeds** - All data is internal
- Uses hardcoded/configurable return assumptions
- Manual price updates via holdings

---

## 9. Caching Strategy

### 9.1 Cache Keys

| Key Pattern | TTL | Use Case |
|-------------|-----|----------|
| `investment_analysis_{userId}` | Per-request | Portfolio analysis results |
| `monte_carlo_status_{jobId}` | 1 hour | Simulation job status |
| `monte_carlo_results_{jobId}` | 1 hour | Simulation results |
| `monte_carlo_error_{jobId}` | 30 min | Simulation error message |
| `_all_monte_carlo_keys` | Per-request | Track all job IDs |
| `efficient_frontier_{userId}` | Per-request | Frontier calculations |
| `correlation_matrix_{userId}` | Per-request | Correlation data |

### 9.2 Cache Invalidation

Cache is cleared when:
- Account created/updated/deleted
- Holding created/updated/deleted
- Risk profile updated
- Goal created/updated/deleted

```php
public function clearCache(int $userId): void
{
    Cache::forget("investment_analysis_{$userId}");
    Cache::forget("efficient_frontier_{$userId}");
    Cache::forget("correlation_matrix_{$userId}");
}
```

---

## 10. Validation Rules

### 10.1 Account Validation

```php
'account_type' => 'required|in:isa,gia,nsi,onshore_bond,offshore_bond,vct,eis,other',
'provider' => 'required|string|max:255',
'current_value' => 'required|numeric|min:0',
'ownership_type' => 'nullable|in:individual,joint,trust',
'ownership_percentage' => 'nullable|numeric|min:0|max:100',
'isa_type' => 'nullable|in:stocks_and_shares,lifetime,innovative_finance',
'isa_subscription_current_year' => 'nullable|numeric|min:0|max:20000',
'joint_owner_id' => 'nullable|exists:users,id'

// Special Rule: ISAs cannot be joint or in trust
if ($accountType === 'isa' && $ownershipType !== 'individual') {
    // Validation error
}
```

### 10.2 Holding Validation

```php
'investment_account_id' => 'required|exists:investment_accounts,id',
'asset_type' => 'required|in:equity,bond,fund,etf,alternative,...',
'security_name' => 'required|string|max:255',
'allocation_percent' => 'required|numeric|min:0|max:100',
'current_value' => 'required|numeric|min:0',
'purchase_price' => 'nullable|numeric|min:0',
'current_price' => 'nullable|numeric|min:0',
'ocf_percent' => 'nullable|numeric|min:0|max:100'
```

### 10.3 Monte Carlo Validation

```php
'start_value' => 'required|numeric|min:0',
'monthly_contribution' => 'required|numeric|min:0',
'expected_return' => 'required|numeric|min:0|max:0.5',  // 0-50%
'volatility' => 'required|numeric|min:0|max:1',         // 0-100%
'years' => 'required|integer|min:1|max:50',
'iterations' => 'nullable|integer|min:100|max:10000',   // default 1000
'goal_amount' => 'nullable|numeric|min:0'
```

### 10.4 Risk Profile Validation

```php
'risk_tolerance' => 'required|in:cautious,balanced,adventurous',
'capacity_for_loss_percent' => 'required|numeric|min:0|max:100',
'time_horizon_years' => 'required|integer|min:0|max:100',
'knowledge_level' => 'required|in:novice,intermediate,experienced',
'esg_preference' => 'nullable|boolean'
```

---

## 11. Error Handling

### 11.1 Common Error Scenarios

| Scenario | Status | Response |
|----------|--------|----------|
| Invalid account type | 422 | Validation error with field details |
| ISA joint ownership attempt | 422 | "ISAs can only be individually owned" |
| Account not found | 404 | "Not found" |
| Insufficient authorization | 403 | User cannot edit/delete others' accounts |
| Monte Carlo validation fails | 422 | Validation error with parameters |
| Monte Carlo job fails | 500 | Error stored in cache |
| Account access denied | 403 | Only primary owner can edit/delete |

### 11.2 Logging

```php
Log::info('Investment account created', [
    'user_id' => $userId,
    'account_type' => $data['account_type'],
    'current_value' => $data['current_value']
]);

Log::error('Monte Carlo simulation failed', [
    'job_id' => $jobId,
    'user_id' => $userId,
    'error' => $exception->getMessage()
]);
```

---

## 12. Performance Considerations

### 12.1 Query Optimization

**Indexes on**:
- `investment_accounts.user_id`
- `investment_accounts.user_id, account_type`
- `investment_accounts.joint_owner_id`
- `holdings.holdable_type, holdable_id`
- `risk_profiles.user_id`
- `investment_goals.user_id`

**Eager Loading**:
```php
InvestmentAccount::with('holdings')->where('user_id', $userId)->get();
```

### 12.2 Cache Usage

- Portfolio analysis results cached per user
- Prevents recalculation on repeated requests
- Cache cleared on data modifications

### 12.3 Monte Carlo Optimization

- Runs in background queue job (non-blocking)
- Polling mechanism in frontend
- Results stored in cache, not database
- Limited to 10,000 iterations max

### 12.4 Efficient Frontier Calculation

- Computationally intensive (matrix operations)
- Consider caching results per user
- May need optimization for large portfolios (100+ holdings)

---

## 13. Testing Infrastructure

### 13.1 Test Files

**Backend**:
```
tests/Unit/Services/Investment/
├── MonteCarloSimulatorTest.php
├── PortfolioAnalyzerTest.php
├── TaxEfficiencyCalculatorTest.php
└── [Other service tests]

tests/Feature/
└── InvestmentModuleTest.php
```

**Frontend**:
```
tests/Frontend/Components/Investment/
└── InvestmentOverviewCard.test.js
```

### 13.2 Key Test Scenarios

- Monte Carlo percentile calculations
- Portfolio analysis accuracy
- Tax efficiency scoring logic
- Fee impact calculations
- Joint account handling
- ISA validation rules
- Polymorphic holdings relationships
- Cache invalidation

---

## 14. Security Considerations

### 14.1 Authentication

- All routes require `auth:sanctum` middleware
- Bearer token authentication

### 14.2 Authorization

- User can only access their own accounts
- Primary owner (user_id) controls edit/delete
- Joint owners have read-only access

### 14.3 Validation

- All inputs validated with explicit rule sets
- Type hints on all parameters
- Enum validation for restricted values
- Numeric range validation for percentages

### 14.4 Data Protection

- No hardcoded tax values (use TaxConfigService)
- Sensitive calculations protected in services
- Joint account updates logged

---

## 15. File Paths Reference

### Backend

```
app/Agents/
└── InvestmentAgent.php

app/Http/Controllers/Api/
├── InvestmentController.php
├── InvestmentProjectionController.php
└── Investment/
    ├── AssetLocationController.php
    ├── ContributionOptimizerController.php
    ├── EfficientFrontierController.php
    ├── FeeImpactController.php
    ├── GoalProgressController.php
    ├── InvestmentPlanController.php
    ├── InvestmentRecommendationController.php
    ├── InvestmentScenarioController.php
    ├── ModelPortfolioController.php
    ├── PerformanceAttributionController.php
    ├── RebalancingActionsController.php
    ├── RebalancingCalculationController.php
    ├── RebalancingStrategiesController.php
    ├── RiskProfileController.php
    └── TaxOptimizationController.php

app/Services/Investment/
├── PortfolioAnalyzer.php
├── MonteCarloSimulator.php
├── InvestmentProjectionService.php
├── FeeAnalyzer.php
├── TaxEfficiencyCalculator.php
├── ContributionEstimatorService.php
├── AssetAllocationOptimizer.php
├── InvestmentPlanGenerator.php
├── ScenarioService.php
├── ContributionOptimizer.php
├── Analytics/
│   ├── CorrelationMatrixCalculator.php
│   ├── CovarianceMatrixCalculator.php
│   ├── EfficientFrontierCalculator.php
│   ├── HoldingsDataExtractor.php
│   └── MarkowitzOptimizer.php
├── Tax/
│   ├── TaxOptimizationAnalyzer.php
│   ├── CGTHarvestingCalculator.php
│   ├── ISAAllowanceOptimizer.php
│   └── BedAndISACalculator.php
├── RiskProfile/
│   ├── RiskProfiler.php
│   ├── RiskQuestionnaire.php
│   └── CapacityForLossAnalyzer.php
├── Rebalancing/
│   ├── RebalancingCalculator.php
│   ├── TaxAwareRebalancer.php
│   ├── DriftAnalyzer.php
│   └── RebalancingStrategyService.php
└── Performance/
    ├── PerformanceAttributionAnalyzer.php
    ├── BenchmarkComparator.php
    └── AlphaBetaCalculator.php

app/Models/Investment/
├── InvestmentAccount.php
├── Holding.php
├── RiskProfile.php
├── InvestmentGoal.php
├── InvestmentPlan.php
├── InvestmentRecommendation.php
├── InvestmentScenario.php
└── RebalancingAction.php

app/Http/Requests/Investment/
├── CalculateEfficientFrontierRequest.php
└── OptimizePortfolioRequest.php
```

### Frontend

```
resources/js/views/Investment/
├── InvestmentDashboard.vue
├── AccountDetailView.vue
├── AccountSummaryPanel.vue
├── AccountFeesPanel.vue
├── AccountHoldingsPanel.vue
└── AccountPerformancePanel.vue

resources/js/components/Investment/
├── InvestmentOverviewCard.vue
├── ComprehensiveInvestmentPlan.vue
├── InvestmentRecommendationsTracker.vue
├── InvestmentProjectionChart.vue
├── EfficientFrontier.vue
├── Goals.vue
├── GoalCard.vue
├── HoldingForm.vue
├── HoldingsTable.vue
├── WhatIfScenarios.vue
├── TaxOptimizationRecommendations.vue
├── CGTHarvestingOpportunities.vue
├── BedAndISATransfers.vue
├── AssetLocationOptimizer.vue
├── WrapperOptimizer.vue
└── PerformanceAttribution.vue

resources/js/store/modules/
└── investment.js

resources/js/services/
└── investmentService.js
```

### Routes

```
routes/api.php (lines 295-625)
```

### Tests

```
tests/Unit/Services/Investment/
├── MonteCarloSimulatorTest.php
├── PortfolioAnalyzerTest.php
└── TaxEfficiencyCalculatorTest.php

tests/Feature/
└── InvestmentModuleTest.php

tests/Frontend/Components/Investment/
└── InvestmentOverviewCard.test.js
```

---

## Conclusion

The Investment Module is a sophisticated, enterprise-grade financial planning system featuring:

- **18 API controllers** handling specialized investment domains
- **24+ service classes** with advanced financial algorithms
- **Monte Carlo simulations** with Box-Muller normal distribution
- **Modern Portfolio Theory** (efficient frontier, Markowitz optimization)
- **Tax optimization** (CGT harvesting, ISA optimization, Bed & ISA)
- **Comprehensive portfolio analysis** with 15+ metrics
- **Risk profiling** with questionnaire and 5-level self-select system
- **Polymorphic holdings** shared with Retirement module
- **Single-record joint ownership** architecture
- **Background job processing** for long-running calculations

The module demonstrates thoughtful design choices balancing data integrity, performance, and practical usability for UK-focused financial planning.
