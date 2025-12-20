# Retirement Future Value & Projections Implementation

**Date**: December 20, 2024
**Branch**: `retirementAccStrategies`
**Status**: Complete

---

## Overview

Added two new tabs to the retirement section within `/net-worth/retirement`:
1. **Future Value** - Monte Carlo projections for DC pensions with income drawdown analysis
2. **Strategies** - Interactive strategy recommendations with sliders

---

## What Was Built

### Backend Services

#### 1. RetirementProjectionService.php
**File:** `app/Services/Retirement/RetirementProjectionService.php`

Provides Monte Carlo projections for retirement planning:

```php
class RetirementProjectionService
{
    public function getProjections(int $userId): array
    // Returns pension pot projections and income drawdown analysis

    public function projectPensionPot(int $userId): array
    // Monte Carlo simulation for DC pensions
    // - Aggregates all DC pensions (current value + contributions)
    // - Uses risk profile for return/volatility assumptions
    // - Runs 1000 iterations
    // - Returns percentile bands (5th, 10th, 15th, 20th, 50th)

    public function projectIncomeDrawdown(int $userId): array
    // Income analysis from retirement to age 100
    // - DC pension drawdown at 4.7% withdrawal rate
    // - Adds DB pension income (if applicable)
    // - Adds State pension income
    // - Compares against target income (75% of current net)
    // - Tracks fund depletion
```

#### 2. RetirementStrategyService.php
**File:** `app/Services/Retirement/RetirementStrategyService.php`

Generates personalized strategy recommendations:

```php
class RetirementStrategyService
{
    public function getStrategies(int $userId): array
    // Returns prioritized strategies based on user's situation

    public function calculateStrategyImpact(int $userId, string $type, float $value): array
    // Recalculates projections with modified parameters for slider interaction
```

**Strategy Priority Order:**
1. Maximise employer pension matching
2. Increase contributions (within affordability & annual allowance)
3. Adjust retirement age
4. Reduce income target

---

### Frontend Components

#### 1. FutureValueTab.vue
**File:** `resources/js/components/Retirement/FutureValueTab.vue`

Main container with:
- Summary cards (projected pot, probability, target income)
- Pension Pot Projection chart
- Income Drawdown chart
- On-track status indicator

#### 2. PensionPotProjectionChart.vue
**File:** `resources/js/components/Retirement/PensionPotProjectionChart.vue`

ApexCharts stacked area chart showing:
- 5 probability bands (5th/10th/15th/20th/50th percentiles)
- Years on X-axis, pot value on Y-axis
- Color gradient from dark to light blue

#### 3. IncomeDrawdownChart.vue
**File:** `resources/js/components/Retirement/IncomeDrawdownChart.vue`

ApexCharts bar chart showing:
- Yearly income from retirement to age 100
- Green bars = above target income
- Red bars = below target / fund depleted
- Target income line annotation
- Income breakdown (DC drawdown + DB + State)

#### 4. TargetIncomeDrawdownChart.vue
**File:** `resources/js/components/Retirement/TargetIncomeDrawdownChart.vue`

Alternative drawdown visualization with target comparison.

#### 5. StrategiesTab.vue
**File:** `resources/js/components/Retirement/StrategiesTab.vue`

Interactive strategies interface:
- Summary cards (probability, monthly disposable, annual allowance)
- Strategy cards with sliders
- Combined impact summary
- On-track banner when >= 95% probability

#### 6. StrategyCard.vue
**File:** `resources/js/components/Retirement/StrategyCard.vue`

Individual strategy card with:
- Priority badge
- Description
- Interactive slider
- Real-time impact calculation
- Constraints display (affordability, allowance limits)

---

### API Endpoints

#### GET `/api/retirement/projections`
Returns pension pot and income projections.

**Response:**
```json
{
  "success": true,
  "data": {
    "pension_pot_projection": {
      "current_value": 125000,
      "monthly_contribution": 500,
      "retirement_age": 65,
      "median_at_retirement": 650000,
      "year_by_year": [
        {
          "year": 2025,
          "age": 35,
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
}
```

#### GET `/api/retirement/strategies`
Returns personalized strategy recommendations.

#### POST `/api/retirement/strategies/impact`
Calculates impact of a strategy change (for slider interaction).

---

### Files Modified

| File | Changes |
|------|---------|
| `resources/js/components/NetWorth/PensionList.vue` | Added tabs (Pensions, Future Value, Strategies) |
| `app/Http/Controllers/Api/RetirementController.php` | Added getProjections(), getStrategies(), calculateStrategyImpact() |
| `routes/api.php` | Added retirement projection and strategy routes |
| `resources/js/store/modules/retirement.js` | Added projections and strategies state/actions |
| `resources/js/services/retirementService.js` | Added getProjections(), getStrategies(), calculateStrategyImpact() |

---

### Key Parameters

| Parameter | Value | Source |
|-----------|-------|--------|
| Risk Returns | 2% (low) to 8% (high) | User's risk profile |
| Withdrawal Rate | 4.7% | UK sustainable withdrawal research |
| Inflation | 2% | Target inflation assumption |
| Target Income | 75% of net income | UK retirement benchmark |
| Projection End | Age 100 | Standard planning horizon |
| Monte Carlo Iterations | 1000 | Statistical confidence |

---

### Probability Bands

| Percentile | Meaning | Color |
|------------|---------|-------|
| 5th | 95% chance of achieving this or better | `#1e3a5f` (darkest) |
| 10th | 90% chance | `#2563eb` |
| 15th | 85% chance | `#3b82f6` |
| 20th | 80% chance | `#60a5fa` |
| 50th | Median outcome | `#93c5fd` (lightest) |

---

### Income Chart Colors

| Status | Color | Meaning |
|--------|-------|---------|
| Above Target | `#10b981` (green) | Total income >= target |
| Below Target | `#ef4444` (red) | Total income < target or fund depleted |
| Target Line | `#f59e0b` (amber) | Target income annotation |

---

## Testing

```bash
# Get projections for James Carter
TOKEN=$(curl -s -X POST "http://localhost:8000/api/preview/login/young_family" | grep -o '"token":"[^"]*"' | cut -d'"' -f4)

# Pension projections
curl -H "Authorization: Bearer $TOKEN" "http://localhost:8000/api/retirement/projections"

# Strategies
curl -H "Authorization: Bearer $TOKEN" "http://localhost:8000/api/retirement/strategies"
```

---

## Screenshots

The Future Value tab displays:
1. **Top row**: Summary cards with key metrics
2. **Pension Pot Chart**: Monte Carlo projection bands
3. **Income Drawdown Chart**: Year-by-year income vs target

The Strategies tab displays:
1. **Top row**: Probability, Disposable Income, Annual Allowance
2. **Strategy Cards**: Prioritized recommendations with sliders
3. **Combined Impact**: Projected improvement summary
