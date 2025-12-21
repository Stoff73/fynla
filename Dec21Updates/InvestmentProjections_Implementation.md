# Investment Future Value Projections - Implementation Plan

## Overview
Add Monte Carlo future value projections to the Investment module's Performance tab, matching the Retirement module's probability band visualization style.

## Requirements
- **Scope**: Portfolio-wide + per-account projections
- **Contributions**: Auto-detect from ISA subscription/account type
- **Location**: Performance tab (currently "Coming Soon")
- **Visualization**: Probability bands (5th, 10th, 15th, 20th percentiles as stacked areas)

---

## Files to Create

### 1. `app/Services/Investment/ContributionEstimatorService.php`
Estimates monthly contributions based on:
- ISA accounts: Use `isa_subscription_current_year` / months elapsed
- GIA accounts: 5% of current value annually
- Allows user override

### 2. `app/Services/Investment/InvestmentProjectionService.php`
Orchestrates Monte Carlo projections:
- Uses existing `MonteCarloSimulator` (no changes needed)
- Uses `RiskPreferenceService` for return/volatility parameters
- Calculates weighted portfolio risk from account risk levels
- Extracts probability bands (5th, 10th, 15th, 20th, 50th, 75th, 90th percentiles)
- Returns year-by-year data for periods: 5, 10, 20, 30 years

### 3. `app/Http/Controllers/Api/InvestmentProjectionController.php`
Single endpoint: `POST /api/investment/projections`
- Accepts: `projection_periods[]`, `selected_period`, `contribution_overrides{}`
- Returns: Portfolio projection + array of account projections

### 4. `resources/js/components/Investment/InvestmentProjectionChart.vue`
ApexCharts stacked area chart (mirrors `PensionPotProjectionChart.vue`):
- 4 probability bands with blue gradient colors
- Opacity from 0.5 to 0.1
- Currency formatting with K/M suffixes

---

## Files to Modify

### 5. `routes/api.php`
Add route within investment prefix:
```php
Route::post('/projections', [InvestmentProjectionController::class, 'getProjections']);
```

### 6. `app/Agents/InvestmentAgent.php`
Add method:
```php
public function getPortfolioProjections($userId, $periods, $overrides, $selected): array
```

### 7. `resources/js/store/modules/investment.js`
Add state:
- `portfolioProjections`, `projectionsLoading`, `projectionsError`, `selectedProjectionPeriod`

Add action:
- `fetchPortfolioProjections({ periods, selectedPeriod, contributionOverrides })`

### 8. `resources/js/services/investmentService.js`
Add method:
```javascript
async getPortfolioProjections(params) { return api.post('/investment/projections', params); }
```

### 9. `resources/js/components/Investment/Performance.vue`
Add after "Portfolio Value Over Time" placeholder:
- Period selector (5/10/20/30 years dropdown)
- Portfolio projection chart with `InvestmentProjectionChart`
- Summary cards showing 95%, 80%, Median, Upside values
- Per-account projection cards (collapsible, smaller charts)

### 10. `resources/js/components/NetWorth/InvestmentList.vue`
Remove "Coming Soon" wrapper from Performance tab (lines 174-184):
```vue
<!-- Before: Coming Soon wrapper -->
<!-- After: Direct render -->
<div v-else-if="activePortfolioTab === 'performance'">
  <Performance />
  <div class="mt-8">
    <PerformanceAttribution />
  </div>
</div>
```

---

## Implementation Sequence

### Phase 1: Backend (3 files)
1. Create `ContributionEstimatorService.php`
2. Create `InvestmentProjectionService.php`
3. Add method to `InvestmentAgent.php`
4. Create `InvestmentProjectionController.php`
5. Add API route

### Phase 2: Frontend (4 files)
1. Update `investment.js` Vuex store
2. Update `investmentService.js`
3. Create `InvestmentProjectionChart.vue`
4. Update `Performance.vue` with projection section
5. Remove "Coming Soon" from `InvestmentList.vue`

### Phase 3: Testing
1. Test API with preview users via curl
2. Manual testing in browser

---

## Key Technical Details

### Risk Parameters (from RiskPreferenceService)
| Level | Expected Return | Volatility |
|-------|-----------------|------------|
| low | 2.0% | 3% |
| lower_medium | 3.5% | 6% |
| medium | 5.0% | 10% |
| upper_medium | 6.5% | 15% |
| high | 8.0% | 20% |

### Portfolio Risk Calculation
Weighted average of account risk levels based on current value:
```php
foreach ($accounts as $account) {
    $weight = $account->current_value / $totalValue;
    $weightedReturn += $weight * $params['expected_return_typical'];
    $weightedVolatility += $weight * $params['volatility'];
}
```

### Percentile Interpolation (from Retirement pattern)
Monte Carlo returns 10th, 25th, 50th, 75th, 90th. We calculate:
- 5th = 10th - (25th - 10th) * 0.33
- 15th = 10th + (25th - 10th) * 0.33
- 20th = 10th + (25th - 10th) * 0.67

---

## Existing Code to Reuse
- `app/Services/Investment/MonteCarloSimulator.php` - No changes needed
- `app/Services/Risk/RiskPreferenceService.php` - `getReturnParameters($level)`
- `resources/js/components/Retirement/PensionPotProjectionChart.vue` - Pattern for chart

---

## API Response Structure

```json
{
  "success": true,
  "data": {
    "portfolio": {
      "current_value": 125000.00,
      "estimated_monthly_contribution": 1200.00,
      "risk_level": "medium",
      "expected_return": 5.0,
      "volatility": 10.0,
      "account_count": 3,
      "projections": {
        "10": {
          "years": 10,
          "median_value": 285000.00,
          "percentiles": {
            "p5": 195000.00,
            "p10": 215000.00,
            "p15": 230000.00,
            "p20": 245000.00,
            "p50": 285000.00,
            "p75": 340000.00,
            "p90": 410000.00
          },
          "year_by_year": [
            {
              "year": 2025,
              "year_number": 1,
              "percentile_5": 130000.00,
              "percentile_10": 135000.00,
              "percentile_15": 138000.00,
              "percentile_20": 141000.00,
              "percentile_50": 152000.00,
              "percentile_75": 165000.00,
              "percentile_90": 180000.00
            }
          ]
        }
      }
    },
    "accounts": [
      {
        "account_id": 1,
        "account_name": "Vanguard ISA",
        "account_type": "isa",
        "current_value": 75000.00,
        "estimated_monthly_contribution": 800.00,
        "risk_level": "medium",
        "projections": { ... }
      }
    ],
    "projection_periods": [5, 10, 20, 30],
    "selected_period": 10
  }
}
```

---

## Contribution Estimation Logic

### ISA Accounts
```php
// If isa_subscription_current_year is set
$monthsElapsed = monthsSinceApril6();
$monthlyContribution = $isaSubscription / $monthsElapsed;

// Default fallback: assume max ISA utilisation
$monthlyContribution = 20000 / 12; // £1,666.67/month
```

### GIA Accounts
```php
// Assume 5% of value added annually
$monthlyContribution = ($currentValue * 0.05) / 12;
```

### User Override
Allow contribution override per account via API parameter:
```json
{
  "contribution_overrides": {
    "1": 500.00,
    "2": 1000.00
  }
}
```
