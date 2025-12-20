# Retirement Strategies Tab Implementation Plan

## Overview

Replace the "Coming Soon" placeholder in the Strategies tab with a fully functional interactive strategy recommendation system that helps users achieve their retirement goals.

## Key Requirements

1. **Interactive Sliders**: Users can adjust proposed strategies
2. **Strategy Priority Order**:
   - First: Check employer pension matching - recommend increasing to max match
   - Second: Check affordability (net income - expenses) and recommend increasing contributions within annual allowance
   - Third: Recommend retiring later or reducing income target
3. **Affordability Check**: Calculate disposable income = gross income - tax - NI - expenses
4. **Annual Allowance Tracking**:
   - Current year + 3 previous years for carry forward
   - Show "carry forward not available, three year contribution history needed" if no historical data
5. **Only Show Applicable Strategies**: Don't show strategies that don't apply
6. **Stop When On Track**: Stop showing more strategies once 95% probability is achieved

---

## Files to Create

### 1. Backend Service
**`app/Services/Retirement/RetirementStrategyService.php`**

```php
class RetirementStrategyService
{
    public function __construct(
        private TaxConfigService $taxConfig,
        private UserProfileService $userProfileService,
        private UKTaxCalculator $taxCalculator,
        private RetirementProjectionService $projectionService,
        private AnnualAllowanceChecker $allowanceChecker,
        private ContributionOptimizer $contributionOptimizer
    ) {}

    public function getStrategies(int $userId): array
    {
        // 1. Get current retirement readiness (95% probability from projectionService)
        // 2. If already >= 95%, return empty strategies with "on track" status
        // 3. Calculate disposable income (affordability)
        // 4. Get annual allowance status (with carry forward check)
        // 5. Build ordered list of applicable strategies
        // 6. For each strategy, calculate impact on probability
        // 7. Stop adding strategies once cumulative impact reaches 95%
    }

    public function calculateStrategyImpact(int $userId, string $strategyType, float $newValue): array
    {
        // Recalculate projections with modified parameter
        // Return new probability and readiness status
    }

    private function calculateDisposableIncome(User $user): array
    {
        // gross_income - tax - NI - annual_expenditure = disposable_income
    }

    private function checkEmployerMatchStrategy(User $user): ?array
    {
        // For each workplace DC pension:
        // - Check if employee_contribution_percent < employer_matching_limit
        // - If yes, return strategy with slider config
    }

    private function checkContributionIncreaseStrategy(User $user, float $disposableIncome, array $allowanceStatus): ?array
    {
        // Must have positive disposable income
        // Must have remaining annual allowance (including carry forward)
        // Max = min(disposable_income, remaining_allowance)
    }

    private function checkRetirementAgeStrategy(User $user): ?array
    {
        // Only if not on track after contribution strategies
        // Slider: min = current target, max = 75
    }

    private function checkIncomeTargetStrategy(User $user): ?array
    {
        // Only if not on track after all other strategies
        // Slider: min = guaranteed income (DB + State), max = current target
    }

    private function getCarryForwardStatus(int $userId): array
    {
        // Check for 3-year contribution history
        // If not available, return message: "Carry forward not available - three year contribution history needed"
    }
}
```

### 2. Frontend Components

**`resources/js/components/Retirement/StrategiesTab.vue`** (replace existing)
- Loading/error states
- "On Track" banner if >= 95% probability
- Summary cards: Current status, Affordability, Annual Allowance
- Strategy cards with sliders
- Combined impact summary

**`resources/js/components/Retirement/StrategyCard.vue`**
- Priority badge
- Title and description
- Range slider with current/recommended values
- Impact preview (probability improvement)
- "Apply" button

**`resources/js/components/Retirement/ProbabilityGauge.vue`**
- Visual gauge showing before/after probability
- Color-coded (red < 80% < amber < 95% <= green)

---

## Files to Modify

### 1. RetirementController.php
**`app/Http/Controllers/Api/RetirementController.php`**

Add methods:
```php
public function getStrategies(Request $request): JsonResponse
public function calculateStrategyImpact(Request $request): JsonResponse
```

### 2. API Routes
**`routes/api.php`**

Add within retirement prefix:
```php
Route::get('/strategies', [RetirementController::class, 'getStrategies']);
Route::post('/strategies/impact', [RetirementController::class, 'calculateStrategyImpact']);
```

### 3. Vuex Store
**`resources/js/store/modules/retirement.js`**

Add:
```javascript
state: {
    strategies: null,
    strategiesLoading: false,
    strategyImpact: null,
}

mutations: {
    SET_STRATEGIES, SET_STRATEGIES_LOADING, SET_STRATEGY_IMPACT
}

actions: {
    fetchStrategies(), calculateStrategyImpact({ strategyType, newValue })
}
```

### 4. Retirement Service (JS)
**`resources/js/services/retirementService.js`**

Add:
```javascript
getStrategies()
calculateStrategyImpact(strategyType, newValue)
```

---

## API Response Structure

**GET `/api/retirement/strategies`**

```json
{
  "success": true,
  "data": {
    "current_status": {
      "on_track_status": "Needs Attention",
      "probability": 65,
      "projected_income": 28000,
      "target_income": 35000,
      "income_gap": 7000
    },
    "affordability": {
      "gross_income": 65000,
      "net_income": 48000,
      "annual_expenditure": 36000,
      "disposable_income": 12000,
      "monthly_disposable": 1000
    },
    "annual_allowance": {
      "standard_allowance": 60000,
      "available_allowance": 60000,
      "current_contributions": 8400,
      "remaining_allowance": 51600,
      "carry_forward": {
        "available": false,
        "amount": 0,
        "message": "Carry forward not available - three year contribution history needed"
      }
    },
    "strategies": [
      {
        "type": "employer_match",
        "applicable": true,
        "priority": 1,
        "title": "Maximise Employer Match",
        "description": "Your employer matches up to 5% of salary. You're currently contributing 3%.",
        "pension_id": 123,
        "pension_name": "ABC Company Pension",
        "current_value": 3,
        "recommended_value": 5,
        "slider_config": {
          "min": 3,
          "max": 5,
          "step": 0.5,
          "unit": "%",
          "format": "percentage"
        },
        "impact": {
          "additional_monthly": 166,
          "probability_improvement": 8,
          "new_probability": 73
        }
      },
      {
        "type": "increase_contribution",
        "applicable": true,
        "priority": 2,
        "title": "Increase Pension Contributions",
        "description": "You have disposable income of £1,000/month available.",
        "current_value": 700,
        "recommended_value": 1200,
        "slider_config": {
          "min": 700,
          "max": 1700,
          "step": 50,
          "unit": "£/month",
          "format": "currency"
        },
        "constraints": {
          "affordability_limit": 1000,
          "annual_allowance_limit": 4300
        },
        "impact": {
          "probability_improvement": 15,
          "new_probability": 88
        }
      },
      {
        "type": "retirement_age",
        "applicable": true,
        "priority": 3,
        "title": "Adjust Retirement Age",
        "description": "Working longer allows more contributions and growth.",
        "current_value": 65,
        "recommended_value": 67,
        "slider_config": {
          "min": 65,
          "max": 75,
          "step": 1,
          "unit": "years",
          "format": "age"
        },
        "impact": {
          "probability_improvement": 10,
          "new_probability": 98
        }
      },
      {
        "type": "income_target",
        "applicable": false,
        "priority": 4,
        "reason": "Not needed - 95% probability achieved"
      }
    ],
    "on_track_at_strategy": 2
  }
}
```

---

## Key Existing Services to Use

| Service | Location | Purpose |
|---------|----------|---------|
| `ContributionOptimizer` | `app/Services/Retirement/ContributionOptimizer.php` | Has `checkEmployerMatch()` - extend to use `employer_matching_limit` field |
| `AnnualAllowanceChecker` | `app/Services/Retirement/AnnualAllowanceChecker.php` | Has `checkAnnualAllowance()` with carry forward (simplified) |
| `RetirementProjectionService` | `app/Services/Retirement/RetirementProjectionService.php` | Has Monte Carlo projections, use for impact calculations |
| `UKTaxCalculator` | `app/Services/Tax/UKTaxCalculator.php` | For calculating net income |
| `TaxConfigService` | `app/Services/TaxConfigService.php` | For annual allowance limits |

---

## Key Database Fields Used

**DCPension:**
- `employee_contribution_percent` - Current employee contribution %
- `employer_contribution_percent` - Current employer contribution %
- `employer_matching_limit` - Maximum employer match % (use this instead of hardcoded 5%)
- `monthly_contribution_amount` - Total monthly contribution
- `annual_salary` - For calculating contribution amounts
- `scheme_type` - Filter for 'workplace' pensions

**User:**
- `annual_employment_income`, `annual_self_employment_income`, etc.
- `monthly_expenditure` / `annual_expenditure`
- `target_retirement_age`

---

## Implementation Order

1. **Backend Service** - Create `RetirementStrategyService` with `getStrategies()` and `calculateStrategyImpact()`
2. **API Endpoints** - Add controller methods and routes
3. **Vuex Store** - Add state, mutations, actions for strategies
4. **JS Service** - Add API methods to retirementService.js
5. **StrategiesTab.vue** - Replace placeholder with full implementation
6. **Sub-components** - Create StrategyCard.vue and ProbabilityGauge.vue
7. **Integration** - Connect sliders to API for real-time impact calculation

---

## Slider Interaction Flow

1. User moves slider -> debounced (300ms) API call to `/strategies/impact`
2. API returns new probability for that single strategy change
3. Frontend updates impact preview on the card
4. User clicks "Apply" -> navigate to pension edit form or trigger update
5. After saving, refresh strategies to recalculate all

---

## Styling Notes

- Follow existing card patterns from `FutureValueTab.vue`
- Priority badges: Green (1), Blue (2), Amber (3), Gray (4)
- Probability colors: Red < 80%, Amber 80-94%, Green >= 95%
- Use native HTML5 range inputs with custom styling
- Debounce slider changes to avoid excessive API calls
