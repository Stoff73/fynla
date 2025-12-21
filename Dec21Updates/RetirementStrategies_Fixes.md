# Retirement Strategies Fixes - Dec 21, 2025

## Overview

Fixed multiple issues with the Retirement Strategies tab to ensure accurate recommendations, proper chart updates, and correct constraint handling.

## Issues Fixed

### 1. Chart Not Updating When Slider Moves

**Problem**: ApexCharts wasn't re-rendering when slider values changed. The chart showed stale data.

**Solution**: Added a dynamic `:key` binding to force chart re-render:

```vue
<apexchart
  :key="chartKey"
  type="area"
  height="280"
  :options="chartOptions"
  :series="chartSeries"
/>
```

```javascript
chartKey() {
  const pot = this.displayProjection?.with_strategy?.pot_at_retirement || 0;
  const withStrategyFinal = this.displayProjection?.pot_growth?.slice(-1)[0]?.pot_with_strategy || 0;
  return `chart-${pot}-${withStrategyFinal}`;
}
```

**File**: `resources/js/components/Retirement/StrategyCard.vue`

---

### 2. Contribution Strategy Description Misleading

**Problem**: Description showed disposable income when annual allowance was the binding constraint.

**Solution**: Updated description to show the actual binding constraint:

```php
$isAllowanceBinding = $remainingAllowance < $disposableIncome;
$description = $isAllowanceBinding
    ? sprintf(
        'You can add up to %s/month within your annual allowance (disposable income: %s/month).',
        $this->formatCurrency($maxAdditionalMonthly),
        $this->formatCurrency($disposableIncome / 12)
    )
    : sprintf(
        'You have disposable income of %s/month available for additional contributions.',
        $this->formatCurrency($disposableIncome / 12)
    );
```

**File**: `app/Services/Retirement/RetirementStrategyService.php`

---

### 3. Retirement Age Strategy - Missing Chart/Projection

**Problem**: Retirement age strategy card had no projection data, so no chart was displayed.

**Solution**: Created `buildRetirementAgeProjection()` method that:
- Shows pot growth up to original retirement age (both lines same)
- Extends projection for delayed years
- "Without strategy" stops at original retirement pot value
- "With strategy" continues growing with contributions + returns

```php
private function buildRetirementAgeProjection(array $currentStatus, int $yearsDelay): array
{
    // Build projection up to original retirement age
    for ($year = 0; $year <= $yearsToRetirement; $year++) {
        // Both lines same up to original retirement
    }

    // Extend for delay years
    for ($extraYear = 1; $extraYear <= $yearsDelay; $extraYear++) {
        // Without strategy: pot stays at original value
        // With strategy: continued growth
    }

    return [
        'pot_growth' => $yearByYear,
        'with_strategy' => [...],
        'without_strategy' => [...],
    ];
}
```

**File**: `app/Services/Retirement/RetirementStrategyService.php`

---

### 4. Retirement Age Recommendation Logic

**Problem**: Recommendation was simply "current age + 2 years" regardless of what's needed.

**Solution**: Calculate age needed for 95% probability, capped at 68:

```php
// Calculate the age needed to reach 95% probability
$incomeGapPercent = ($targetIncome - $currentIncome) / $currentIncome;
$yearsNeededFor95 = ceil($incomeGapPercent / 0.10); // Each year adds ~10%
$ageFor95 = min($currentRetirementAge + $yearsNeededFor95, $maxSliderAge);

// Cap recommendation at 68, but allow slider to go to 75
$maxRecommendedAge = 68;
$maxSliderAge = 75;
$recommendedAge = min((int) $ageFor95, $maxRecommendedAge);

// Show message when capped
if ($ageFor95 > $maxRecommendedAge) {
    $description = sprintf(
        'We recommend retiring at %d (State Pension age). You can use the slider to explore later ages up to %d.',
        $maxRecommendedAge,
        $maxSliderAge
    );
}
```

**File**: `app/Services/Retirement/RetirementStrategyService.php`

---

### 5. Retirement Age Slider Impact Calculation

**Problem**: Slider changes returned errors due to missing array keys.

**Solution**: Updated `calculateRetirementAgeImpact()` to calculate income from available data:

```php
private function calculateRetirementAgeImpact(User $user, int $newAge, array $projections): float
{
    // Calculate current projected income from pot and guaranteed income
    $startingPot = $projections['income_drawdown']['starting_pot'];
    $withdrawalRate = $projections['income_drawdown']['withdrawal_rate'] / 100;
    $guaranteedIncome = $projections['income_drawdown']['guaranteed_income']['total'] ?? 0;
    $currentIncome = ($startingPot * $withdrawalRate) + $guaranteedIncome;

    // Each year delay adds ~10% to projected income
    $additionalIncomePercent = $yearsDelay * 0.10;
    $newIncome = $currentIncome * (1 + $additionalIncomePercent);

    // Use same probability formula as other strategies
    $incomeRatio = $targetIncome > 0 ? $newIncome / $targetIncome : 0;
    $probability = 10 + ($incomeRatio * 85);

    return min(95, max(10, round($probability, 0)));
}
```

Updated `calculateStrategyImpact()` to handle retirement_age:

```php
$projection = match ($strategyType) {
    'income_target' => $this->buildIncomeTargetProjection($currentStatus, $newValue),
    'retirement_age' => $this->buildRetirementAgeProjection(
        $currentStatus,
        (int) $newValue - ($user->target_retirement_age ?? 65)
    ),
    default => $this->buildStrategyProjection($currentStatus, $additionalMonthly, $additionalAnnualIncome),
};
```

**File**: `app/Services/Retirement/RetirementStrategyService.php`

---

## Test Results

### David Mitchell (Peak Earners Persona)

**Profile:**
- Current Age: 49
- Target Retirement Age: 60
- Current Pot: ~£500,000
- Monthly Disposable Income: £2,524
- Target Retirement Income: £83,545

**Contribution Strategy:**
- Current: £3,933/month
- Recommended: £4,467/month (£534 additional)
- Slider Max: £5,000/month (constrained by annual allowance, not affordability)
- Description: "You can add up to £1,067/month within your annual allowance (disposable income: £2,524/month)."

**Retirement Age Strategy:**

| Slider Value | Probability | Projection Years | Pot at Retirement |
|--------------|-------------|------------------|-------------------|
| 60 (current) | 45% | 12 | £911,168 |
| 62 | 73% | 14 | £1,072,469 |
| 65 | 92% | 17 | £1,510,335 |
| 68 (recommended) | 95% | 20 | £1,971,452 |
| 70 | 95% | 22 | £2,364,179 |

---

## Files Modified

1. **`resources/js/components/Retirement/StrategyCard.vue`**
   - Added `chartKey` computed property for chart re-rendering
   - Added `:key` binding to ApexChart component

2. **`app/Services/Retirement/RetirementStrategyService.php`**
   - Updated `checkContributionIncreaseStrategy()` - better description text
   - Updated `checkRetirementAgeStrategy()` - calculate age for 95%, cap at 68
   - Added `buildRetirementAgeProjection()` - projection data for charts
   - Updated `calculateRetirementAgeImpact()` - correct probability calculation
   - Updated `calculateStrategyImpact()` - handle retirement_age projections

---

## Key Formulas

### Probability Calculation (Linear Interpolation)
```
probability = 10 + (incomeRatio * 85)
```
- 0% income coverage = 10% probability
- 100% income coverage = 95% probability

### Retirement Age Income Impact
```
Each year delay = +10% to projected income
newIncome = currentIncome * (1 + (yearsDelay * 0.10))
```

### Contribution Constraints
```
maxAdditionalAnnual = min(disposableIncome, remainingAllowance)
```
- Uses the lower of affordability or tax efficiency
