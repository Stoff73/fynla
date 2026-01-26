# Investment Monte Carlo Projection - Data Flow Documentation

This document maps the complete data flow for Monte Carlo projections, using the Mitchell persona (peak_earners) as a working example.

---

## Mitchell Portfolio Overview

### Investment Accounts

| Account | Owner | Type | Value | Risk Preference | Custom Risk |
|---------|-------|------|-------|-----------------|-------------|
| David's S&S ISA | David | ISA | £95,000 | high | Yes |
| Sarah's S&S ISA | Sarah | ISA | £85,000 | medium | No |
| Joint GIA | Joint | GIA | £95,000 | upper_medium | No |
| VCT Holdings | David | VCT | £30,000 | high | Yes |

### DC Pensions (also use Monte Carlo)

| Account | Owner | Type | Value | Risk Preference |
|---------|-------|------|-------|-----------------|
| Global Finance Corp Pension | David | Workplace | £180,000 | upper_medium |
| David's SIPP | David | SIPP | £320,000 | upper_medium |

### User Risk Profiles

| User | Main Risk Level | Source |
|------|-----------------|--------|
| David | upper_medium | Self-assessed |
| Sarah | medium | Self-assessed |

---

## 1. Portfolio-Level Monte Carlo

### Data Sources

**File**: `app/Services/Investment/InvestmentProjectionService.php`
**Method**: `getPortfolioProjections()` (lines 51-94)

#### Step 1: Calculate Total Portfolio Value

```
Portfolio Value = Σ (Account Value × Ownership Percentage / 100)
```

For Mitchells (David's view):
```
David's S&S ISA:    £95,000 × 100% = £95,000
Sarah's S&S ISA:    £85,000 × 0%   = £0 (spouse-owned)
Joint GIA:          £95,000 × 50%  = £47,500
VCT Holdings:       £30,000 × 100% = £30,000
────────────────────────────────────────────
Total Portfolio Value:              £172,500
```

**Source**: `InvestmentAccount` model fields:
- `current_value` (float)
- `ownership_type` (individual/joint/trust)
- `ownership_percentage` (decimal)

#### Step 2: Calculate Weighted Average Risk

**File**: `app/Services/Investment/InvestmentProjectionService.php`
**Method**: `calculateWeightedPortfolioRisk()` (lines 322-368)

```
Risk Levels → Numeric Values:
  low           = 1
  lower_medium  = 2
  medium        = 3
  upper_medium  = 4
  high          = 5
```

For Mitchells (David's view):
```
Account              | Value    | Weight | Risk      | Numeric | Weighted
─────────────────────|──────────|────────|───────────|─────────|─────────
David's S&S ISA      | £95,000  | 55.1%  | high      | 5       | 2.755
Joint GIA (50%)      | £47,500  | 27.5%  | upper_med | 4       | 1.100
VCT Holdings         | £30,000  | 17.4%  | high      | 5       | 0.870
─────────────────────|──────────|────────|───────────|─────────|─────────
Total                | £172,500 | 100%   |           |         | 4.725

Weighted Risk = 4.725 → Rounds to "upper_medium" (4-5 range)
```

**Source**: Each account's risk determined by:
1. `risk_preference` field (if `has_custom_risk = true`)
2. User's main `risk_level` from `RiskProfile` model
3. Default: "medium"

#### Step 3: Get Return Parameters for Portfolio Risk

**File**: `app/Services/Risk/RiskPreferenceService.php`
**Method**: `getReturnParameters()` (lines 314-324)

| Risk Level | Expected Return (Typical) | Volatility |
|------------|---------------------------|------------|
| low | 2.0% | 3% |
| lower_medium | 3.5% | 6% |
| medium | 5.0% | 10% |
| upper_medium | 6.5% | 15% |
| high | 8.0% | 20% |

For Mitchells' portfolio (upper_medium):
- **Expected Return**: 6.5%
- **Volatility**: 15%

#### Step 4: Estimate Portfolio Contributions

**File**: `app/Services/Investment/ContributionEstimatorService.php`

| Account Type | Estimation Method |
|--------------|-------------------|
| ISA | `isa_subscription_current_year ÷ months_elapsed` OR `£20,000 ÷ 12` |
| GIA | `current_value × 5% ÷ 12` |
| VCT | £0 (typically no regular contributions) |

For Mitchells:
```
David's S&S ISA:   £10,000 ÷ 10 months = £1,000/month
Joint GIA (50%):   £47,500 × 5% ÷ 12   = £198/month
VCT Holdings:      £0/month
───────────────────────────────────────────────────
Estimated Monthly: £1,198/month
```

#### Step 5: Run Monte Carlo Simulation

**File**: `app/Services/Investment/MonteCarloSimulator.php`
**Method**: `simulate()` (lines 20-94)

**Input Parameters**:
```php
$simulator->simulate(
    startValue: 172500,           // Portfolio value
    monthlyContribution: 1198,    // Estimated contributions
    expectedReturn: 0.065,        // 6.5% annual
    volatility: 0.15,             // 15% annual
    years: 20,                    // Projection period
    iterations: 1000              // Simulation runs
);
```

**Algorithm**:
1. Convert annual → monthly: `monthlyReturn = 0.065/12`, `monthlyVol = 0.15/√12`
2. For each of 1,000 iterations:
   - For each month:
     - Generate random return using Box-Muller normal distribution
     - `portfolio = portfolio × (1 + randomReturn) + monthlyContribution`
   - Store year-end values
3. Calculate percentiles across all iterations

**Output**: Year-by-year percentile bands (p5, p10, p15, p20, p50, p75, p90)

---

## 2. Individual Account Monte Carlo

### Data Sources per Account

**File**: `app/Services/Investment/InvestmentProjectionService.php`
**Method**: `calculateAccountProjection()` (lines 156-226)

#### Risk Priority Hierarchy

```
1. Account has risk_preference AND has_custom_risk = true
   → Use account's risk_preference

2. Account has risk_preference but has_custom_risk = false
   → Use user's main risk_level

3. Account has no risk_preference
   → Use user's main risk_level

4. User has no risk profile
   → Default to "medium"
```

#### Mitchell Individual Account Projections

**David's S&S ISA**:
```
Value:            £95,000 × 100% = £95,000
Risk Source:      Account-level (has_custom_risk = true)
Risk Level:       high
Expected Return:  8.0%
Volatility:       20%
Est. Contribution: £1,000/month (from ISA subscription)
```

**Sarah's S&S ISA** (spouse account, not in David's portfolio):
```
Value:            £85,000 × 100% = £85,000
Risk Source:      Account-level
Risk Level:       medium
Expected Return:  5.0%
Volatility:       10%
Est. Contribution: £833/month (£10,000 ÷ 12)
```

**Joint GIA** (David's 50% share):
```
Value:            £95,000 × 50% = £47,500
Risk Source:      Account-level
Risk Level:       upper_medium
Expected Return:  6.5%
Volatility:       15%
Est. Contribution: £198/month (5% of share ÷ 12)
```

**VCT Holdings**:
```
Value:            £30,000 × 100% = £30,000
Risk Source:      Account-level (has_custom_risk = true)
Risk Level:       high
Expected Return:  8.0%
Volatility:       20%
Est. Contribution: £0/month
```

### Monte Carlo per Account

Each account runs independent simulation with its own parameters:

```php
// David's S&S ISA
$simulator->simulate(95000, 1000, 0.08, 0.20, 20, 1000);

// Joint GIA (David's share)
$simulator->simulate(47500, 198, 0.065, 0.15, 20, 1000);

// VCT Holdings
$simulator->simulate(30000, 0, 0.08, 0.20, 20, 1000);
```

---

## 3. Risk Assignment Flow

### How Risk Gets to Each Account

```
┌─────────────────────────────────────────────────────────────────────┐
│                     RISK ASSIGNMENT FLOW                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌──────────────────┐                                               │
│  │ RiskProfile Model│ ← User completes risk questionnaire           │
│  │ (per user)       │                                               │
│  │                  │                                               │
│  │ • risk_level     │ ← "low" / "lower_medium" / "medium" /        │
│  │ • is_self_assess │   "upper_medium" / "high"                    │
│  └────────┬─────────┘                                               │
│           │                                                         │
│           ▼                                                         │
│  ┌──────────────────────────────────────────────────────────┐       │
│  │              InvestmentAccount Model                      │       │
│  │                                                          │       │
│  │  risk_preference ─────┬─── If has_custom_risk = true    │       │
│  │                       │    → Use this value              │       │
│  │                       │                                  │       │
│  │  has_custom_risk ─────┼─── If false or not set          │       │
│  │                       │    → Fall back to user's main   │       │
│  │                       │       risk_level                │       │
│  │                       │                                  │       │
│  │                       └─── If no user risk profile      │       │
│  │                            → Default to "medium"        │       │
│  └──────────────────────────────────────────────────────────┘       │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Risk Constraints

**File**: `app/Services/Risk/RiskPreferenceService.php`
**Method**: `getAllowedProductRiskLevels()` (lines 216-236)

Account-level risk must be within ±1 of user's main risk level:

| User Main Risk | Allowed Account Risks |
|----------------|----------------------|
| low | low, lower_medium |
| lower_medium | low, lower_medium, medium |
| medium | lower_medium, medium, upper_medium |
| upper_medium | medium, upper_medium, high |
| high | upper_medium, high |

For David (main: upper_medium):
- Can set accounts to: medium, upper_medium, or high ✓
- His "high" ISA is valid (within ±1)

---

## 4. Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        MONTE CARLO DATA FLOW                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  DATABASE                    SERVICES                      FRONTEND         │
│  ────────                    ────────                      ────────         │
│                                                                             │
│  ┌─────────────────┐                                                        │
│  │ investment_     │                                                        │
│  │ accounts        │─────┐                                                  │
│  │                 │     │                                                  │
│  │ • current_value │     │     ┌──────────────────────┐                     │
│  │ • ownership_%   │     ├────▶│ InvestmentProjection │                     │
│  │ • risk_pref     │     │     │ Service              │                     │
│  │ • has_custom    │     │     │                      │                     │
│  └─────────────────┘     │     │ • getPortfolio       │                     │
│                          │     │   Projections()      │                     │
│  ┌─────────────────┐     │     │                      │    ┌──────────────┐ │
│  │ risk_profiles   │─────┤     │ • calculateAccount   │───▶│ API Response │ │
│  │                 │     │     │   Projection()       │    │              │ │
│  │ • risk_level    │     │     │                      │    │ • percentile │ │
│  │ • user_id       │     │     │ • calculateWeighted  │    │   bands      │ │
│  └─────────────────┘     │     │   PortfolioRisk()    │    │ • year_by_   │ │
│                          │     └──────────┬───────────┘    │   year       │ │
│  ┌─────────────────┐     │                │                │ • risk_level │ │
│  │ users           │─────┘                │                └──────┬───────┘ │
│  │                 │                      ▼                       │         │
│  │ • id            │         ┌────────────────────────┐           │         │
│  │ • spouse_id     │         │ RiskPreferenceService  │           │         │
│  └─────────────────┘         │                        │           │         │
│                              │ • getReturnParameters()│           │         │
│                              │   → expected_return    │           ▼         │
│                              │   → volatility         │  ┌────────────────┐ │
│                              └────────────┬───────────┘  │ Vue Component  │ │
│                                           │              │                │ │
│                                           ▼              │ Investment     │ │
│                              ┌────────────────────────┐  │ Projection     │ │
│                              │ MonteCarloSimulator    │  │ Chart.vue     │ │
│                              │                        │  │                │ │
│                              │ • simulate()           │  │ • ApexCharts   │ │
│                              │   - 1000 iterations    │  │ • Area chart   │ │
│                              │   - Box-Muller random  │  │ • Percentile   │ │
│                              │   - Year-end captures  │  │   bands        │ │
│                              │                        │  └────────────────┘ │
│                              │ • calculatePercentiles │                     │
│                              │   → p5, p10, p15, p20  │                     │
│                              │   → p50, p75, p90      │                     │
│                              └────────────────────────┘                     │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 5. API Endpoints

### Portfolio Projections

```
POST /api/investment/projections

Request:
{
  "projection_periods": [5, 10, 20, 30],
  "selected_period": 20,
  "contribution_overrides": {
    "1": 1200,  // Override account ID 1 contribution
    "3": 500    // Override account ID 3 contribution
  }
}

Response:
{
  "portfolio": {
    "total_value": 172500,
    "risk_level": "upper_medium",
    "expected_return": 6.5,
    "volatility": 15,
    "projections": {
      "20": {
        "years": 20,
        "median_value": 892450,
        "percentiles": {...},
        "year_by_year": [...]
      }
    }
  },
  "accounts": [
    {
      "account_id": 1,
      "account_name": "David's S&S ISA",
      "risk_level": "high",
      "projections": {...}
    },
    ...
  ]
}
```

### Individual Account Projections (with risk override)

```
GET /api/investment/accounts/{id}/projections?risk_level=medium

Response:
{
  "account_id": 1,
  "account_name": "David's S&S ISA",
  "risk_level": "medium",        // Override applied
  "risk_source": "override",
  "expected_return": 5.0,
  "volatility": 10,
  "projections": {...}
}
```

---

## 6. Key Files Reference

| Component | File | Key Methods |
|-----------|------|-------------|
| Monte Carlo Engine | `app/Services/Investment/MonteCarloSimulator.php` | `simulate()`, `calculatePercentiles()` |
| Portfolio Projections | `app/Services/Investment/InvestmentProjectionService.php` | `getPortfolioProjections()`, `calculateAccountProjection()` |
| Risk Parameters | `app/Services/Risk/RiskPreferenceService.php` | `getReturnParameters()`, `getMainRiskLevel()` |
| Contribution Estimation | `app/Services/Investment/ContributionEstimatorService.php` | `estimateAccountContribution()` |
| Controller | `app/Http/Controllers/Api/InvestmentController.php` | `getAccountProjections()`, `startMonteCarlo()` |
| Frontend Chart | `resources/js/components/Investment/InvestmentProjectionChart.vue` | ApexCharts area chart |
| Results Display | `resources/js/components/Investment/MonteCarloResults.vue` | Full results modal |

---

## 7. Risk Level Parameters Summary

| Risk Level | Numeric | Expected Return | Volatility | Equity % | Bond % |
|------------|---------|-----------------|------------|----------|--------|
| low | 1 | 2.0% | 3% | 10% | 70% |
| lower_medium | 2 | 3.5% | 6% | 30% | 55% |
| medium | 3 | 5.0% | 10% | 50% | 40% |
| upper_medium | 4 | 6.5% | 15% | 75% | 20% |
| high | 5 | 8.0% | 20% | 90% | 5% |

---

## 8. Mitchell Example: 20-Year Portfolio Projection

**Input**:
- Start Value: £172,500
- Monthly Contribution: £1,198
- Expected Return: 6.5%
- Volatility: 15%
- Years: 20
- Iterations: 1,000

**Output (example percentiles at year 20)**:
- 10th percentile: £485,000 (poor market scenario)
- 25th percentile: £620,000
- 50th percentile (median): £892,000
- 75th percentile: £1,280,000
- 90th percentile: £1,850,000 (strong market scenario)

Total contributions over 20 years: £172,500 + (£1,198 × 240) = £460,020
Median growth: £892,000 - £460,020 = £431,980 (growth from returns)
