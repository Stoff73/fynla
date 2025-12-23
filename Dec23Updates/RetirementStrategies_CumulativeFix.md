# Retirement Strategies - Cumulative Probability Fix

**Date:** 2025-12-23
**Module:** Retirement
**File:** `app/Services/Retirement/RetirementStrategyService.php`

## Problem

The retirement strategies tab was showing additional strategies even after reaching 100%+ income coverage. Strategies were being recommended beyond what was needed to reach the 95% "on track" threshold.

## Root Causes Identified

### 1. Inconsistent Probability Caps
Some methods capped probability at 100% while others capped at 95%:
- `calculateEmployerMatchImpact()` - capped at 100
- `calculateContributionImpact()` - capped at 100
- `calculateIncomeTargetImpact()` - capped at 100
- `calculateStrategyImpact()` return value - capped at 100

### 2. Non-Cumulative Strategy Calculations
Each strategy's probability was calculated independently rather than cumulatively. When multiple employer match strategies existed, each was calculated as if no prior strategies applied.

### 3. Retirement Age Probability Mismatch
The retirement age strategy's probability was calculated using different values than what was shown in the projection, causing inconsistency (e.g., showing 94% probability when coverage was 102.9%).

### 4. Income Target Strategy Wrong Coverage
The income target strategy recommended setting target = sustainable income only, but coverage was calculated using total income (sustainable + guaranteed), resulting in coverage > 100%.

## Fixes Applied

### Fix 1: Consistent Probability Caps
Changed all probability caps to use `self::ON_TRACK_PROBABILITY` (95%):
```php
// Before
return min(100, $baseProbability + $improvement);

// After
return min(self::ON_TRACK_PROBABILITY, $baseProbability + $improvement);
```

### Fix 2: True Cumulative Probability Calculation
Updated the main `getStrategies()` loop to recalculate probability based on actual cumulative income:
```php
// Recalculate probability based on TRUE cumulative income
$trueCumulativeProbability = $this->calculateNewProbability(
    $currentStatus['projected_income'] + $cumulativeAdditionalIncome,
    $currentStatus['target_income'],
    $strategy['impact']['additional_annual_income'] ?? 0
);
```

### Fix 3: Retirement Age Probability from Projection
The retirement age strategy now calculates probability from the projection values to ensure consistency:
```php
// Recalculate probability from projection to ensure consistency
$projectionIncome = $retirementAgeStrategy['projection']['with_strategy']['total_retirement_income'];
$incomeRatio = $targetIncome > 0 ? $projectionIncome / $targetIncome : 0;
$trueCumulativeProbability = min(self::ON_TRACK_PROBABILITY, max(10, round(10 + ($incomeRatio * 85), 0)));
```

### Fix 4: Income Target Uses Total Achievable Income
Updated to recommend target = sustainable + guaranteed income:
```php
// Before
$recommendedIncome = $originalProjectedIncome;  // Sustainable only

// After
$totalAchievableIncome = $originalSustainableIncome + $guaranteedIncome;
$recommendedIncome = $totalAchievableIncome;  // Total income
```

## Testing Results

### Young Family Persona
- Starting: 25% probability
- Strategy 1 (employer_match): 95% probability, 109.5% coverage
- **Only 1 strategy shown** - correctly stops at 95%

### Peak Earners Persona
- Starting: 45% probability
- Strategy 1 (increase_contribution): 59% probability
- Strategy 2 (retirement_age): 95% probability, 106% coverage
- **Only 2 strategies shown** - correctly stops at 95%

### Widow Persona
- Starting: 65% probability
- Strategy 1 (increase_contribution): 87% probability
- Strategy 2 (retirement_age): 95% probability
- **Only 2 strategies shown** - correctly stops at 95%

## Key Behaviour Change

Before: Strategies were shown even after reaching 95%+ probability, sometimes showing 3-4 strategies with coverage exceeding 125%.

After: Strategies correctly stop being added once 95% probability is reached. The `on_track_at_strategy` indicator correctly identifies which strategy achieves the target.
