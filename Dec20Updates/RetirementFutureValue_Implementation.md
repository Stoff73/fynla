# Retirement Future Value & Strategies Tabs Implementation Plan

**Date**: December 20, 2025
**Branch**: `retirementAccStrategies`
**Status**: In Progress

---

## Overview

Add two new tabs to the retirement section within `/net-worth/retirement`:
1. **Future Value** - Monte Carlo projections for DC pensions with income drawdown analysis
2. **Strategies** - Coming Soon placeholder (for optimization recommendations)

---

## Key Requirements

- **Pension Pot Projection**: Monte Carlo simulation for DC pensions only (DB/State are guaranteed)
- **Probability Bands**: 95%, 90%, 85%, 80% (darkest = highest probability)
- **Risk Returns**: 2% (low) to 8% (high) based on user's risk profile
- **Income Drawdown**: 4.7% sustainable withdrawal rate, 2% annual inflation
- **Target Income**: 75% of current after-tax income
- **Period**: Retirement age to 100
- **Bar Colors**: Green = above target, Red = below/depleted

---

## Files to Create

### 1. Backend Service
**`app/Services/Retirement/RetirementProjectionService.php`**

```php
class RetirementProjectionService
{
    public function __construct(
        private MonteCarloSimulator $simulator,
        private RiskPreferenceService $riskService
    ) {}

    public function projectPensionPot(int $userId): array
    // - Aggregate DC pensions (current value + monthly contributions)
    // - Get risk level return/volatility from RiskPreferenceService
    // - Run Monte Carlo with 1000 iterations
    // - Return 5th/10th/15th/20th/50th percentiles year-by-year

    public function projectIncomeDrawdown(int $userId): array
    // - Get median pot at retirement
    // - Calculate 4.7% withdrawal + DB + State pension
    // - Apply 2% inflation to target each year
    // - Return yearly income vs target from retirement to age 100
    // - Calculate on-track status and probability
```

### 2. Frontend Components

**`resources/js/components/Retirement/FutureValueTab.vue`**
- Summary cards (projected pot, on-track status, target income)
- Contains PensionPotProjectionChart and IncomeDrawdownChart

**`resources/js/components/Retirement/PensionPotProjectionChart.vue`**
- ApexCharts stacked area chart
- 5 series for probability bands (95%/90%/85%/80%/50%)
- Colors: dark blue to light blue gradient
- X-axis: years, Y-axis: pot value

**`resources/js/components/Retirement/IncomeDrawdownChart.vue`**
- ApexCharts bar chart
- Green bars = above target, Red bars = below target
- Horizontal target line annotation
- X-axis: age 65-100, Y-axis: annual income

**`resources/js/components/Retirement/StrategiesTab.vue`**
- Coming Soon overlay with placeholder strategy cards

---

## Files to Modify

### 1. PensionList.vue
**`resources/js/components/NetWorth/PensionList.vue`**

Add tab navigation:
```javascript
data() {
  return {
    activeTab: 'current',
    tabs: [
      { id: 'current', label: 'Pensions' },
      { id: 'future', label: 'Future Value' },
      { id: 'strategies', label: 'Strategies' },
    ],
  };
}
```

Template: Add tab buttons, conditionally render current content or new tab components.

### 2. RetirementController.php
**`app/Http/Controllers/Api/RetirementController.php`**

Add method:
```php
public function getProjections(Request $request): JsonResponse
```

### 3. API Routes
**`routes/api.php`**

Add: `Route::get('/retirement/projections', [RetirementController::class, 'getProjections']);`

### 4. Vuex Store
**`resources/js/store/modules/retirement.js`**

Add state: `projections`, `projectionsLoading`
Add action: `fetchProjections()`

### 5. Retirement Service
**`resources/js/services/retirementService.js`**

Add: `getProjections()` method

---

## API Response Structure

```json
{
  "pension_pot_projection": {
    "current_value": 125000,
    "monthly_contribution": 500,
    "risk_level": "medium",
    "retirement_age": 65,
    "median_at_retirement": 650000,
    "year_by_year": [
      {
        "year": 2025,
        "percentile_5": 130000,
        "percentile_10": 132000,
        "percentile_15": 134000,
        "percentile_20": 136000,
        "percentile_50": 145000
      }
    ]
  },
  "income_drawdown": {
    "target_income": 35000,
    "retirement_age": 65,
    "on_track_status": "On Track",
    "probability": 78,
    "fund_depletion_age": 92,
    "yearly_income": [
      {
        "age": 65,
        "dc_drawdown": 30550,
        "db_income": 8500,
        "state_pension": 11500,
        "total_income": 50550,
        "target_income": 35000,
        "remaining_fund": 620000,
        "above_target": true
      }
    ]
  }
}
```

---

## Key Dependencies (Already Exist)

- `app/Services/Investment/MonteCarloSimulator.php` - Monte Carlo engine
- `app/Services/Risk/RiskPreferenceService.php` - Risk level returns (2-8%)
- `vue3-apexcharts` - Charting library

---

## Implementation Order

| Step | Task | Status |
|------|------|--------|
| 1 | Create `RetirementProjectionService.php` | Pending |
| 2 | Add `getProjections()` to `RetirementController.php` | Pending |
| 3 | Add route to `routes/api.php` | Pending |
| 4 | Update Vuex store `retirement.js` | Pending |
| 5 | Update `retirementService.js` | Pending |
| 6 | Add tabs to `PensionList.vue` | Pending |
| 7 | Create `FutureValueTab.vue` | Pending |
| 8 | Create `PensionPotProjectionChart.vue` | Pending |
| 9 | Create `IncomeDrawdownChart.vue` | Pending |
| 10 | Create `StrategiesTab.vue` | Pending |
| 11 | Test with preview personas | Pending |

---

## Styling Reference

Follow patterns from:
- `resources/js/components/Retirement/IncomeProjectionChart.vue` - Area chart styling
- `resources/js/components/Estate/CashFlowProjectionChart.vue` - Bar chart with colors
- `resources/js/views/Savings/SavingsDashboard.vue` - Tab navigation pattern

Colors:
- Probability bands: `#1e3a5f`, `#2563eb`, `#3b82f6`, `#60a5fa`, `#93c5fd`
- Above target: `#10b981` (green)
- Below target: `#ef4444` (red)
- Target line: `#f59e0b` (amber)

---

## Notes

- Uses existing Monte Carlo engine (no new simulation code needed)
- Risk levels already configured with appropriate returns (2-8%)
- DC pensions only for projections (DB/State are guaranteed income streams)
- Target income = 75% of current after-tax income (standard UK retirement planning benchmark)
- Sustainable withdrawal rate of 4.7% based on UK research (vs US 4% rule)
