# Duplication Analysis Report
## Investment & Retirement Modules

**Generated**: December 21, 2025
**Updated**: December 21, 2025 (Consolidations Complete)
**Scope**: Routes, Controllers, Services, Vue Components, Database Tables
**Modules Analyzed**: Investment, Retirement (with cross-module comparison)

---

## Executive Summary

This report analyzes the Investment and Retirement modules for unnecessary duplication across all layers of the application. The analysis revealed:

- **2 Duplications Identified and RESOLVED**
- **0 Route Duplications** - all routes are unique and properly namespaced
- **0 Controller Duplications** - controllers serve distinct purposes
- **4 Intentional Separations** - similar naming but different domain logic

---

## 1. RESOLVED DUPLICATIONS

### 1.1 FeeAnalyzer - CONSOLIDATED

**Status**: RESOLVED

**Original Issue**: Two separate FeeAnalyzer classes existed with overlapping functionality.

| Original Location | Status |
|-------------------|--------|
| `app/Services/Investment/FeeAnalyzer.php` | KEPT (consolidated) |
| `app/Services/Investment/Fees/FeeAnalyzer.php` | DELETED |

**Resolution**:
- Merged all methods from `Fees/FeeAnalyzer.php` into the main `FeeAnalyzer.php`
- Updated imports in `FeeImpactController.php` and `InvestmentPlanGenerator.php`
- Deleted duplicate file
- Consolidated class now has 584 lines with both collection-based and database-driven methods

**Files Updated**:
```
app/Services/Investment/FeeAnalyzer.php (consolidated)
app/Http/Controllers/Api/Investment/FeeImpactController.php (import updated)
app/Services/Investment/InvestmentPlanGenerator.php (import updated)
```

---

### 1.2 EfficientFrontierCalculator - CONSOLIDATED

**Status**: RESOLVED

**Original Issue**: Two separate EfficientFrontierCalculator classes existed.

| Original Location | Status |
|-------------------|--------|
| `app/Services/Investment/Analytics/EfficientFrontierCalculator.php` | KEPT (consolidated) |
| `app/Services/Investment/EfficientFrontier/EfficientFrontierCalculator.php` | DELETED |
| `app/Services/Investment/EfficientFrontier/PortfolioStatisticsCalculator.php` | MOVED to Analytics/ |

**Resolution**:
- Merged user portfolio methods and asset class methods into single class
- Moved `PortfolioStatisticsCalculator` to Analytics namespace
- Updated import in `EfficientFrontierController.php`
- Deleted entire `EfficientFrontier/` directory
- Consolidated class now supports both user holdings analysis and generic asset class calculations

**Files Updated**:
```
app/Services/Investment/Analytics/EfficientFrontierCalculator.php (consolidated, 807 lines)
app/Services/Investment/Analytics/PortfolioStatisticsCalculator.php (moved from EfficientFrontier/)
app/Http/Controllers/Api/Investment/EfficientFrontierController.php (imports updated)
```

---

## 2. INTENTIONAL SEPARATIONS (No Action Required)

### 2.1 ContributionOptimizer - SEPARATE DOMAINS

| Module | File | Purpose |
|--------|------|---------|
| Investment | `app/Services/Investment/ContributionOptimizer.php` | ISA/GIA contribution optimization, wrapper selection |
| Retirement | `app/Services/Retirement/ContributionOptimizer.php` | Pension contribution optimization, Annual Allowance |

**Why These Are NOT Duplicates**:
- Investment ContributionOptimizer handles ISA allowance tracking, GIA optimization, tax-efficient wrapper selection
- Retirement ContributionOptimizer handles pension Annual Allowance, employer matching, carry forward rules
- Different tax rules, different allowances, different optimization criteria
- **Correctly separated by domain**

---

### 2.2 WhatIfScenarios Components - MODULE-SPECIFIC

| Module | Component | Purpose |
|--------|-----------|---------|
| Investment | `resources/js/components/Investment/WhatIfScenarios.vue` | Investment scenario modeling |
| Retirement | `resources/js/components/Retirement/WhatIfScenarios.vue` | Retirement scenario modeling |
| Savings | `resources/js/components/Savings/WhatIfScenarios.vue` | Savings scenario modeling |

**Why These Are NOT Duplicates**:
- Each module has unique scenario parameters and calculations
- Investment: contribution changes, allocation changes, fee impacts
- Retirement: retirement age changes, contribution changes, annuity vs drawdown
- Savings: deposit changes, goal adjustments
- **Correctly separated - each serves its module's specific needs**

---

### 2.3 ProjectionService - MODULE-SPECIFIC

| Module | Service | Purpose |
|--------|---------|---------|
| Investment | `app/Services/Investment/ProjectionService.php` | Investment growth projections, Monte Carlo |
| Retirement | `app/Services/Retirement/ProjectionService.php` | Retirement income projections |

**Why These Are NOT Duplicates**:
- Investment ProjectionService: Asset growth, Monte Carlo simulations, volatility modeling
- Retirement ProjectionService: Income replacement, pension pot projections, drawdown modeling
- Different calculation models, different outputs
- **Correctly separated by domain**

---

### 2.4 Goals Tables - DIFFERENT PURPOSES

| Table | Module | Purpose |
|-------|--------|---------|
| `savings_goals` | Savings | Emergency fund targets, short-term goals |
| `investment_goals` | Investment | Long-term investment objectives, risk targets |

**Why These Are NOT Duplicates**:
- Different fields: savings_goals focuses on target amounts and deadlines
- investment_goals includes risk tolerance, time horizon, investment strategy
- Different relationships and business logic
- **Correctly separated - different financial planning purposes**

---

## 3. ROUTE ANALYSIS

### 3.1 Investment Routes (Lines 295-625 in api.php)

All Investment routes are properly prefixed with `/investment/` and namespaced:

```
/api/investment                          - Main investment data
/api/investment/accounts                 - Account management
/api/investment/holdings                 - Holdings management
/api/investment/risk/*                   - Risk profiling
/api/investment/projections/*            - Projection calculations
/api/investment/analytics/*              - Portfolio analytics
/api/investment/monte-carlo/*            - Monte Carlo simulations
/api/investment/efficient-frontier/*     - MPT calculations
/api/investment/tax/*                    - Tax optimization
/api/investment/goals/*                  - Goal management
/api/investment/plans/*                  - Investment planning
/api/investment/performance/*            - Performance tracking
```

**Finding**: No duplicate routes. All endpoints serve distinct purposes.

---

### 3.2 Retirement Routes (Lines 701-744 in api.php)

All Retirement routes are properly prefixed with `/retirement/` and namespaced:

```
/api/retirement                          - Main retirement data
/api/retirement/dc-pensions              - DC pension management
/api/retirement/db-pensions              - DB pension management
/api/retirement/state-pension            - State pension
/api/retirement/projections              - Retirement projections
/api/retirement/strategies               - Strategy recommendations
/api/retirement/monte-carlo/*            - Monte Carlo simulations
/api/retirement/readiness                - Readiness assessment
```

**Finding**: No duplicate routes. All endpoints serve distinct purposes.

---

### 3.3 Cross-Module Route Comparison

| Endpoint Pattern | Investment | Retirement | Duplicate? |
|-----------------|------------|------------|------------|
| Main data | `/investment` | `/retirement` | No - different data |
| Monte Carlo | `/investment/monte-carlo/*` | `/retirement/monte-carlo/*` | No - different models |
| Projections | `/investment/projections/*` | `/retirement/projections` | No - different calculations |
| Holdings | `/investment/holdings/*` | N/A (via pensions) | No |
| Accounts | `/investment/accounts/*` | `/retirement/dc-pensions/*` | No - different entities |

**Finding**: No route duplication. Each module has its own properly namespaced routes.

---

## 4. CONTROLLER ANALYSIS

### 4.1 Investment Controllers (18 total)

```
app/Http/Controllers/Api/Investment/
├── AccountController.php
├── AllocationController.php
├── AnalyticsController.php
├── ContributionController.php
├── DividendController.php
├── EfficientFrontierController.php
├── FeeImpactController.php
├── GoalController.php
├── HoldingController.php
├── InvestmentController.php
├── MonteCarloController.php
├── PerformanceController.php
├── PlanController.php
├── PortfolioOptimizationController.php
├── ProjectionController.php
├── RebalanceController.php
├── RiskController.php
└── TaxController.php
```

### 4.2 Retirement Controllers (7 total)

```
app/Http/Controllers/Api/
├── RetirementController.php
├── DCPensionController.php
├── DBPensionController.php
├── StatePensionController.php
├── RetirementProjectionController.php
├── RetirementMonteCarloController.php
└── RetirementStrategyController.php
```

**Finding**: No controller duplication. Each controller serves a distinct purpose within its module.

---

## 5. SERVICE ANALYSIS

### 5.1 Investment Services (24+ services)

```
app/Services/Investment/
├── Analytics/
│   ├── EfficientFrontierCalculator.php    ← DUPLICATE (see 1.2)
│   ├── PerformanceCalculator.php
│   └── PortfolioAnalyzer.php
├── EfficientFrontier/
│   └── EfficientFrontierCalculator.php    ← DUPLICATE (see 1.2)
├── Fees/
│   └── FeeAnalyzer.php                     ← DUPLICATE (see 1.1)
├── MonteCarlo/
│   ├── MonteCarloService.php
│   ├── SimulationRunner.php
│   └── BoxMullerTransform.php
├── ContributionOptimizer.php
├── DividendTracker.php
├── FeeAnalyzer.php                         ← DUPLICATE (see 1.1)
├── GoalTracker.php
├── HoldingService.php
├── InvestmentPlanGenerator.php
├── InvestmentService.php
├── PerformanceService.php
├── PortfolioOptimizer.php
├── ProjectionService.php
├── RebalanceService.php
├── RiskProfileService.php
├── TaxOptimizer.php
└── ValueCalculator.php
```

### 5.2 Retirement Services (8 services)

```
app/Services/Retirement/
├── AnnualAllowanceChecker.php
├── ContributionOptimizer.php              ← NOT duplicate (different domain)
├── DCPensionService.php
├── DBPensionService.php
├── ProjectionService.php                  ← NOT duplicate (different domain)
├── RetirementProjectionService.php
├── RetirementReadinessService.php
└── RetirementStrategyService.php
```

**Finding**:
- 2 pairs of duplicate services identified (FeeAnalyzer, EfficientFrontierCalculator)
- ContributionOptimizer and ProjectionService are correctly separated by domain

---

## 6. VUE COMPONENT ANALYSIS

### 6.1 Investment Components

```
resources/js/components/Investment/
├── AccountCard.vue
├── AccountFormModal.vue
├── AllocationChart.vue
├── EfficientFrontierChart.vue
├── GoalsTab.vue
├── HoldingFormModal.vue
├── HoldingsTable.vue
├── InvestmentDetailInline.vue
├── MonteCarloChart.vue
├── PerformanceChart.vue
├── PerformanceTab.vue
├── PlanningTab.vue
├── ProjectionsTab.vue
├── RebalancePanel.vue
├── RiskProfileCard.vue
├── TaxOptimizationPanel.vue
└── WhatIfScenarios.vue                    ← NOT duplicate (module-specific)
```

### 6.2 Retirement Components

```
resources/js/components/Retirement/
├── DBPensionCard.vue
├── DBPensionForm.vue
├── DCPensionCard.vue
├── DCPensionForm.vue
├── PensionCard.vue
├── ProjectionsChart.vue
├── ReadinessGauge.vue
├── StatePensionCard.vue
├── StatePensionForm.vue
├── StrategiesTab.vue
├── StrategyCard.vue
└── WhatIfScenarios.vue                    ← NOT duplicate (module-specific)
```

**Finding**: No component duplication. WhatIfScenarios components are intentionally separate as they have different parameters and calculations per module.

---

## 7. DATABASE TABLE ANALYSIS

### 7.1 Investment Tables

| Table | Purpose | Unique? |
|-------|---------|---------|
| `investment_accounts` | Account storage | Yes |
| `holdings` | Polymorphic holdings | Yes (shared with DC pensions) |
| `risk_profiles` | User risk assessment | Yes |
| `investment_goals` | Investment objectives | Yes |
| `investment_plans` | Generated plans | Yes |
| `investment_recommendations` | AI recommendations | Yes |
| `investment_scenarios` | What-if scenarios | Yes |

### 7.2 Retirement Tables

| Table | Purpose | Unique? |
|-------|---------|---------|
| `retirement_profiles` | Retirement settings | Yes |
| `dc_pensions` | Defined contribution pensions | Yes |
| `db_pensions` | Defined benefit pensions | Yes |
| `state_pensions` | State pension records | Yes |

### 7.3 Cross-Module Table Analysis

| Comparison | Table 1 | Table 2 | Duplicate? |
|------------|---------|---------|------------|
| Goals | `savings_goals` | `investment_goals` | No - different fields and purposes |
| Accounts | `savings_accounts` | `investment_accounts` | No - different structures |
| Holdings | `holdings` | N/A | Polymorphic - correctly shared |

**Finding**: No table duplication. The `holdings` table is correctly designed as polymorphic to serve both investment accounts and DC pensions.

---

## 8. RECOMMENDATIONS

### 8.1 Immediate Actions (Priority: High)

1. **Consolidate FeeAnalyzer**
   - Merge `Investment/Fees/FeeAnalyzer.php` into `Investment/FeeAnalyzer.php`
   - Update 4 affected files
   - Delete empty Fees/ directory
   - Estimated effort: 1-2 hours

2. **Consolidate EfficientFrontierCalculator**
   - Compare both implementations
   - Merge into `Investment/Analytics/EfficientFrontierCalculator.php`
   - Update affected controllers and agents
   - Delete EfficientFrontier/ directory
   - Estimated effort: 2-3 hours

### 8.2 No Action Required

- ContributionOptimizer (correctly separated by domain)
- WhatIfScenarios components (correctly module-specific)
- ProjectionService classes (different calculation models)
- Goals tables (different purposes and fields)
- All routes (properly namespaced)
- All controllers (distinct purposes)

---

## 9. VERIFICATION CHECKLIST

Before consolidating duplicates, verify:

- [ ] Both FeeAnalyzer classes have been fully compared
- [ ] All methods from Fees/FeeAnalyzer are accounted for
- [ ] Both EfficientFrontierCalculator classes have been compared
- [ ] All imports/use statements are updated after consolidation
- [ ] Tests pass after consolidation
- [ ] No broken references remain

---

## 10. APPENDIX: File Locations

### Duplicate Files to Consolidate

```
# FeeAnalyzer - Keep this, merge into it:
app/Services/Investment/FeeAnalyzer.php

# FeeAnalyzer - Delete after merging:
app/Services/Investment/Fees/FeeAnalyzer.php

# EfficientFrontierCalculator - Keep this, merge into it:
app/Services/Investment/Analytics/EfficientFrontierCalculator.php

# EfficientFrontierCalculator - Delete after merging:
app/Services/Investment/EfficientFrontier/EfficientFrontierCalculator.php
```

### Files That Import Duplicates (Need Updating)

```
# Import FeeAnalyzer from Fees/:
app/Services/Investment/InvestmentPlanGenerator.php
app/Http/Controllers/Api/Investment/FeeImpactController.php

# Import EfficientFrontierCalculator from EfficientFrontier/:
(Run: grep -r "EfficientFrontier\\\\EfficientFrontierCalculator" app/)
```

---

**Report Complete**

This analysis confirms that the Investment and Retirement modules are well-architected with minimal duplication. Only 2 pairs of duplicate service classes were found, both within the Investment module's service layer. All other apparent similarities are intentional domain separations following proper software architecture principles.
