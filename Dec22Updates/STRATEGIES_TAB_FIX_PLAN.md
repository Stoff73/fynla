# Retirement Strategies Tab - Fix Implementation

**Date**: December 22, 2025
**Branch**: `retirementupdate`
**Status**: COMPLETED

---

## Issues Fixed

### Issue 1: Probability improvement doesn't show on initial load
- The `displayImpact` computed property in StrategyCard.vue falls back to `strategy.impact`
- However, the backend's `getStrategies()` method creates strategies via `check*Strategy` methods that may not include `probability_improvement` in the initial impact object
- The `probability_improvement` is only calculated in `calculateStrategyImpact()` which is called when slider moves

### Issue 2: Strategies not carrying cumulative improvements
- Backend `calculateStrategyImpact()` (line 159-200) calculates each strategy from the ORIGINAL baseline
- It uses `$baseProbability = $projections['income_drawdown']['probability']` - always the starting probability
- When user adjusts Strategy 2's slider, it doesn't account for Strategy 1's improvements
- The frontend needs to pass cumulative values from prior strategies to the backend

---

## Implementation Plan

### Step 1: Fix Issue 1 - Ensure probability_improvement is in initial impact

**File:** `app/Services/Retirement/RetirementStrategyService.php`

In the `check*Strategy` methods, ensure each strategy's `impact` object includes `probability_improvement`:

```php
// In checkEmployerMatchStrategies, checkContributionIncreaseStrategy, etc.
$strategy['impact'] = [
    'additional_monthly' => $additionalMonthly,
    'additional_annual_income' => $additionalAnnualIncome,
    'new_probability' => $newProbability,
    'probability_improvement' => $newProbability - $baseProbability,  // ADD THIS
];
```

Search for all places where `impact` is created in these methods:
- `checkEmployerMatchStrategies()`
- `checkContributionIncreaseStrategy()`
- `checkRetirementAgeStrategy()`
- `checkIncomeTargetStrategy()`

### Step 2: Fix Issue 2 - Pass cumulative context to calculateStrategyImpact

**Option A (Recommended): Frontend passes prior values**

Modify the API call to include cumulative values from prior strategies.

**File:** `resources/js/components/Retirement/StrategyCard.vue`

Change `calculateImpact()` to emit prior strategy values:

```javascript
async calculateImpact() {
  const response = await retirementService.calculateStrategyImpact(
    this.strategy.type,
    this.localValue,
    {
      // Pass cumulative values from strategies before this one
      prior_additional_monthly: this.strategy.prior_cumulative_monthly || 0,
      prior_additional_income: this.strategy.prior_cumulative_income || 0,
      prior_probability: this.strategy.prior_probability || null,
    }
  );
  // ...
}
```

**File:** `resources/js/components/Retirement/StrategiesTab.vue`

Augment strategies with cumulative context before passing to StrategyCard:

```javascript
computed: {
  strategiesWithCumulativeContext() {
    let cumulativeMonthly = 0;
    let cumulativeIncome = 0;
    let cumulativeProbability = this.currentProbability;

    return this.applicableStrategies.map((strategy, index) => {
      const augmented = {
        ...strategy,
        prior_cumulative_monthly: cumulativeMonthly,
        prior_cumulative_income: cumulativeIncome,
        prior_probability: cumulativeProbability,
      };

      // Update cumulative values for next strategy
      cumulativeMonthly += strategy.impact?.additional_monthly || 0;
      cumulativeIncome += strategy.impact?.additional_annual_income || 0;
      cumulativeProbability = strategy.impact?.new_probability || cumulativeProbability;

      return augmented;
    });
  }
}
```

**File:** `resources/js/services/retirementService.js`

Update `calculateStrategyImpact` to accept cumulative context:

```javascript
calculateStrategyImpact(strategyType, value, cumulativeContext = {}) {
  return api.post('/retirement/strategies/impact', {
    strategy_type: strategyType,
    value,
    ...cumulativeContext,
  });
}
```

**File:** `app/Http/Controllers/Api/RetirementController.php`

Accept cumulative context in the request.

**File:** `app/Services/Retirement/RetirementStrategyService.php`

Update `calculateStrategyImpact()` to use cumulative context:

```php
public function calculateStrategyImpact(
    int $userId,
    string $strategyType,
    float $newValue,
    float $priorAdditionalMonthly = 0,
    float $priorAdditionalIncome = 0,
    ?float $priorProbability = null
): array {
    // Use priorProbability as base instead of original baseline
    $baseProbability = $priorProbability ?? $projections['income_drawdown']['probability'];

    // Add prior cumulative values to calculations
    // ...
}
```

---

## Files to Modify

1. `app/Services/Retirement/RetirementStrategyService.php`
   - Add `probability_improvement` to initial strategy impacts
   - Update `calculateStrategyImpact()` to accept and use cumulative context

2. `app/Http/Controllers/Api/RetirementController.php`
   - Accept cumulative context parameters in strategy impact endpoint

3. `resources/js/components/Retirement/StrategiesTab.vue`
   - Add computed property to augment strategies with cumulative context
   - Pass augmented strategies to StrategyCard

4. `resources/js/components/Retirement/StrategyCard.vue`
   - Pass cumulative context when calling calculateImpact

5. `resources/js/services/retirementService.js`
   - Update API call to include cumulative context

---

## Testing Checklist

- [x] Initial load shows probability improvement for all strategy cards
- [x] Moving slider on Strategy 1 updates its values correctly
- [x] Strategy 2 shows cumulative improvement (Strategy 1 + Strategy 2)
- [x] Strategy 3 shows cumulative improvement (Strategy 1 + 2 + 3)
- [ ] Combined Impact Summary shows correct final probability
- [ ] Chart projections update correctly with slider changes

---

## Implementation Summary

### Backend Changes

1. **`app/Services/Retirement/RetirementStrategyService.php`**
   - Added `probability_improvement` to each strategy's impact in `getStrategies()`
   - Updated `calculateStrategyImpact()` method signature to accept cumulative context:
     - `priorAdditionalMonthly`: Monthly contributions from prior strategies
     - `priorAdditionalIncome`: Annual income from prior strategies
     - `priorProbability`: Probability after prior strategies (used as baseline)
   - Projection now uses cumulative totals for accurate chart data
   - Returns new fields: `total_additional_monthly`, `total_additional_income`

2. **`app/Http/Controllers/Api/RetirementController.php`**
   - Added validation for new optional parameters
   - Passes cumulative context to service method

### Frontend Changes

3. **`resources/js/components/Retirement/StrategiesTab.vue`**
   - Added `strategiesWithContext` computed property
   - Calculates cumulative values for each strategy based on all prior strategies
   - Updated `handleSliderChange` to pass cumulative context

4. **`resources/js/components/Retirement/StrategyCard.vue`**
   - Updated `calculateImpact()` to pass cumulative context from strategy props
   - Emits cumulative values in `slider-change` event

5. **`resources/js/services/retirementService.js`**
   - Updated `calculateStrategyImpact()` to accept `cumulativeContext` object
   - Adds prior values as query parameters when provided

6. **`resources/js/store/modules/retirement.js`**
   - Updated action to accept and pass cumulative context to service

---

## API Changes

### `/api/retirement/strategies/impact` (GET)

**New Optional Parameters:**
- `prior_additional_monthly` (float) - Cumulative monthly from prior strategies
- `prior_additional_income` (float) - Cumulative annual income from prior strategies
- `prior_probability` (float) - Probability after prior strategies

**New Response Fields:**
- `total_additional_monthly` - This strategy + prior cumulative monthly
- `total_additional_income` - This strategy + prior cumulative income

---

## How Cumulative Strategies Work

1. **Initial Load**: Backend calculates strategies in priority order, each building on prior ones. `probability_improvement` is included in initial impact.

2. **Slider Interaction**:
   - Frontend tracks cumulative values from strategies before the current one
   - When slider moves, passes cumulative context to API
   - Backend uses `priorProbability` as baseline (not original probability)
   - Projection uses total cumulative values for accurate chart

3. **Example Flow** (David Mitchell):
   ```
   Strategy 1 (Increase Contributions):
     Without: Pot £930k → Income £55k/yr
     With: Pot £1,033k → Income £60k/yr

   Strategy 2 (Retirement Age):
     Without: Pot £1,033k → Income £60k/yr  ← Carries S1's "With"!
     With: Pot £2,496k → Income £129k/yr
   ```

---

## Additional Fix: Retirement Age Projection

The initial implementation had a bug where `buildRetirementAgeProjection()` didn't account for cumulative contributions from prior strategies.

**Fix Applied:**
1. Updated `buildRetirementAgeProjection()` to accept `$priorAdditionalMonthly` parameter
2. "Without strategy" baseline now includes pot accumulated from prior strategies' extra contributions
3. "With strategy" continues to grow with total contributions (original + prior strategies)

This ensures that retirement age projections correctly show the cumulative impact of all prior strategies.
