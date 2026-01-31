# Implementation Plan: Required Capital Calculations with Present Value

## Overview

Add detailed "Required Capital" calculations to the Retirement module. When clicking the pension pot projection card, show a comprehensive breakdown with:
- Required income (75% of net income or user-entered)
- Required capital at retirement (Future Value)
- Required capital in today's money (Present Value - discounted)
- Year-by-year table showing compounding and discounting

## Formulas

**Future Value (Compounding):**
```
FV = PV × (1 + r/m)^(m×n)
```
- m = compounding periods per year (4 for quarterly)
- r = rate of return (net of fees)
- n = years to retirement

**Present Value (Discounting):**
```
PV = FV / (1 + r)^n
```
- r = discount rate (inflation 2.5%)
- n = years

---

## Phase 1: Backend Service

**New File:** `app/Services/Retirement/RequiredCapitalCalculator.php`

```php
class RequiredCapitalCalculator
{
    private const DEFAULT_WITHDRAWAL_RATE = 0.047; // 4.7%
    private const DEFAULT_FEE_RATE = 0.01;         // 1% default
    private const DEFAULT_INFLATION_RATE = 0.025;  // 2.5% for discounting
    private const DEFAULT_COMPOUND_PERIODS = 4;    // Quarterly
    private const TARGET_INCOME_PERCENT = 0.75;    // 75% of net income

    public function calculate(int $userId): array
    {
        // 1. Get user assumptions from AssumptionsService
        // 2. Get required income (profile or 75% of net)
        // 3. Calculate required capital at retirement
        // 4. Build year-by-year table
        // 5. Calculate present value (today's money)
    }
}
```

**Key Methods:**

| Method | Purpose |
|--------|---------|
| `calculate($userId)` | Main calculation returning full breakdown |
| `buildYearByYearTable()` | Generate year-by-year data with FV and PV |
| `calculateFutureValue()` | FV = PV × (1 + r/m)^(m×n) |
| `calculatePresentValue()` | PV = FV / (1 + r)^n |
| `getRequiredIncome()` | From RetirementProfile or 75% of net income |

---

## Phase 2: API Endpoint

**Modify:** `app/Http/Controllers/Api/RetirementController.php`

Add method:
```php
public function getRequiredCapital(Request $request): JsonResponse
{
    $calculator = app(RequiredCapitalCalculator::class);
    return response()->json([
        'success' => true,
        'data' => $calculator->calculate($request->user()->id),
    ]);
}
```

**Modify:** `routes/api.php`

Add route within retirement group:
```php
Route::get('/required-capital', [RetirementController::class, 'getRequiredCapital']);
```

---

## Phase 3: API Response Structure

```json
{
  "success": true,
  "data": {
    "required_income": 45000.00,
    "required_capital_at_retirement": 957446.81,
    "required_capital_today": 632456.23,
    "assumptions": {
      "return_rate": 6.00,
      "net_return_rate": 5.00,
      "inflation_rate": 2.50,
      "compound_periods": 4,
      "fees_total": 1.00,
      "withdrawal_rate": 4.70
    },
    "retirement_info": {
      "current_age": 45,
      "retirement_age": 67,
      "years_to_retirement": 22
    },
    "year_by_year": [
      {
        "year_number": 0,
        "calendar_year": 2026,
        "age": 45,
        "accumulated_value": 321456.23,
        "present_value_today": 321456.23,
        "target_in_today_money": 557842.15,
        "is_retirement_year": false
      }
    ]
  }
}
```

---

## Phase 4: Frontend Service

**Modify:** `resources/js/services/retirementService.js`

Add method:
```javascript
async getRequiredCapital() {
    const response = await api.get(`${API_BASE}/required-capital`);
    return response.data;
},
```

---

## Phase 5: Frontend Component

**New File:** `resources/js/components/Retirement/RequiredCapitalDetail.vue`

**Layout:**
```
┌─────────────────────────────────────────────────────────────────────┐
│ ← Back to Projections                                               │
├─────────────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐     │
│  │ TARGET INCOME   │  │ REQUIRED CAPITAL│  │ TODAY'S VALUE   │     │
│  │ £45,000         │  │ £957,447        │  │ £632,456        │     │
│  │ 75% of income   │  │ At age 67       │  │ Present Value   │     │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘     │
├─────────────────────────────────────────────────────────────────────┤
│ CALCULATION ASSUMPTIONS                                             │
│ ┌──────────────┬──────────────┬──────────────┬──────────────┐      │
│ │ Return: 6%   │ Fees: 1%     │ Inflation:   │ Compound:    │      │
│ │ Net: 5%      │              │ 2.5%         │ Quarterly    │      │
│ └──────────────┴──────────────┴──────────────┴──────────────┘      │
│                                          [Edit Assumptions →]       │
├─────────────────────────────────────────────────────────────────────┤
│ YEAR-BY-YEAR PROJECTION                                             │
│ ┌────────┬─────┬────────────────────┬────────────────────┐         │
│ │ Year   │ Age │ Accumulated Value  │ In Today's Money   │         │
│ ├────────┼─────┼────────────────────┼────────────────────┤         │
│ │ 2026   │ 45  │ £321,456           │ £321,456           │         │
│ │ 2027   │ 46  │ £337,529           │ £313,687           │         │
│ │ ...    │ ... │ ...                │ ...                │         │
│ │ 2048   │ 67  │ £957,447           │ £557,842           │ ← Retire│
│ └────────┴─────┴────────────────────┴────────────────────┘         │
├─────────────────────────────────────────────────────────────────────┤
│ HOW THIS IS CALCULATED                                              │
│ • Required Capital = Target Income / 4.7% withdrawal rate           │
│ • Accumulated Value uses FV = PV × (1 + r/m)^(m×n)                  │
│ • Today's Money uses PV = FV / (1 + inflation)^n                    │
└─────────────────────────────────────────────────────────────────────┘
```

**Component Structure:**
- Summary cards (blue/purple/green)
- Assumptions panel with link to Settings
- Scrollable year-by-year table
- Formula explanation section

---

## Phase 6: Integration with FutureValueTab

**Modify:** `resources/js/components/Retirement/FutureValueTab.vue`

Changes:
1. Add `showRequiredCapitalDetail` data property
2. Make "Required Capital" summary card clickable
3. Conditionally render RequiredCapitalDetail component
4. Import and register RequiredCapitalDetail

```vue
<template>
  <!-- Show detail view when clicked -->
  <RequiredCapitalDetail
    v-if="showRequiredCapitalDetail"
    @back="showRequiredCapitalDetail = false"
  />

  <!-- Existing content when not showing detail -->
  <template v-else>
    <!-- Make this card clickable -->
    <div class="summary-card purple clickable" @click="showRequiredCapitalDetail = true">
      <p class="summary-label">Required Capital</p>
      <p class="summary-value">{{ formatCurrency(requiredCapital) }}</p>
      <p class="summary-subtitle">Click for breakdown →</p>
    </div>
  </template>
</template>
```

---

## Phase 7: Calculation Flow

```
Step 1: Get Required Income
├── Check RetirementProfile.target_retirement_income
└── Fallback: net_income × 0.75

Step 2: Get User Assumptions (AssumptionsService)
├── return_rate (from risk level, e.g., 6%)
├── inflation_rate (2.5% for discounting)
├── compound_periods (4 = quarterly)
└── fees_total (weighted average, default 1%)

Step 3: Calculate Net Return Rate
└── net_return = return_rate - fees_total

Step 4: Calculate Required Capital at Retirement
└── FV = required_income / 0.047

Step 5: Calculate Present Value Needed Today
└── PV = FV / (1 + net_return/m)^(m×n)

Step 6: Build Year-by-Year Table
For year = 0 to years_to_retirement:
├── accumulated_value = PV × (1 + net_return/m)^(m×year)
└── today_money = accumulated_value / (1 + inflation)^year
```

---

## Files Summary

| Action | File |
|--------|------|
| Create | `app/Services/Retirement/RequiredCapitalCalculator.php` |
| Modify | `app/Http/Controllers/Api/RetirementController.php` |
| Modify | `routes/api.php` |
| Modify | `resources/js/services/retirementService.js` |
| Create | `resources/js/components/Retirement/RequiredCapitalDetail.vue` |
| Modify | `resources/js/components/Retirement/FutureValueTab.vue` |
| Update | `Jan30Updates/deploy30.md` |

---

## Default Values

| Parameter | Default | Notes |
|-----------|---------|-------|
| Withdrawal Rate | 4.7% | Sustainable withdrawal rate constant |
| Inflation Rate | 2.5% | Discount rate for present value |
| Compound Periods | 4 | Quarterly compounding |
| Fee Rate | 1% | Default if no fees recorded |
| Target Income | 75% of net | Fallback if not set in profile |
| Retirement Age | 68 | From user profile or pensions |

---

## Verification

1. Run `./dev.sh` to start development server
2. Navigate to Retirement Dashboard → Future Value tab
3. Click the "Required Capital" summary card
4. Verify detail view shows:
   - Target Income (check against 75% of net income)
   - Required Capital at Retirement
   - Required Capital in Today's Money
   - Assumptions panel with correct values
   - Year-by-year table with all years to retirement
5. Click "Edit Assumptions" link → navigates to Settings
6. Click "Back" → returns to projection view
7. Test with user who has custom target_retirement_income set
8. Test with user who has custom assumptions saved
