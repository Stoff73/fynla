# Division by Zero Audit and Fixes

**Date:** January 17, 2026
**Scope:** Full codebase audit for potential division by zero vulnerabilities

## Summary

Conducted comprehensive search of the FPS codebase for division operations that could result in division by zero errors. Found and fixed 7 unprotected locations across 5 files.

## Files Fixed

### 1. RetirementStrategyService.php (2 fixes)

**Location:** `app/Services/Retirement/RetirementStrategyService.php`

**Line 676** - Income coverage calculation:
```php
// Before
$incomeCoverage = ($totalIncome / $targetIncome) * 100;

// After
$incomeCoverage = $targetIncome > 0 ? ($totalIncome / $targetIncome) * 100 : 0;
```

**Line 700** - Best income coverage calculation:
```php
// Before
$bestIncomeCoverage = ($totalIncomeAtMax / $targetIncome) * 100;

// After
$bestIncomeCoverage = $targetIncome > 0 ? ($totalIncomeAtMax / $targetIncome) * 100 : 0;
```

### 2. ImageResizeService.php (1 fix)

**Location:** `app/Services/Documents/ImageResizeService.php`

**Method:** `calculateNewDimensions()`
```php
// Added guard at start of method
if ($width <= 0 || $height <= 0) {
    return [1, 1];
}
```

### 3. IHTPeriodicChargeCalculator.php (1 fix)

**Location:** `app/Services/Trust/IHTPeriodicChargeCalculator.php`

**Method:** `calculateExitCharge()`
```php
// Added guard after getting trust value
$trustValue = $trust->total_asset_value ?? $trust->current_value ?? 0;

if ($trustValue <= 0) {
    return [
        'charge_applicable' => false,
        'reason' => 'Trust has no value',
        'charge_amount' => 0,
    ];
}
```

### 4. EfficientFrontierController.php (1 fix)

**Location:** `app/Http/Controllers/Api/Investment/EfficientFrontierController.php`

**Method:** Portfolio comparison endpoint
```php
$totalValue = $holdings->sum('current_value');

if ($totalValue <= 0) {
    return response()->json([
        'success' => false,
        'message' => 'Portfolio has no value',
    ], 400);
}
```

### 5. RebalancingStrategiesController.php (3 fixes)

**Location:** `app/Http/Controllers/Api/Investment/RebalancingStrategiesController.php`

Same guard pattern added to three methods:
- `compare()` - around line 75
- `evaluateThreshold()` - around line 216
- `evaluateOpportunistic()` - around line 345

```php
$totalValue = $holdings->sum('current_value');

if ($totalValue <= 0) {
    return response()->json([
        'success' => false,
        'message' => 'Portfolio has no value',
    ], 400);
}
```

## Files Verified Safe (Already Protected)

The following files were audited and found to have existing protection:

| File | Protection Type |
|------|-----------------|
| IntestacyCalculator.php | if-conditions check count > 0 before division |
| FeeAnalyzer.php | Guards at multiple locations (lines 37, 92, 106, 200, 247) |
| PortfolioAnalyzer.php | Guards at lines 29 and 60 |
| HolisticPlanner.php | Hardcoded array size; guard at line 484 |
| CashFlowCoordinator.php | Logic flow prevents zero divisor |
| NetWorthService.php | Guard at line 234 |
| ConflictResolver.php | Guards at lines 207 and 403 |
| PortfolioStatisticsCalculator.php | Guards at lines 124, 152, 206 |
| PropertyService.php | Guard at line 110 |
| MortgageService.php | Guard at line 24 |
| HoldingsDataExtractor.php | Guards at lines 182, 197 |
| OnboardingService.php | Divisor always 1 or 2 |
| ChattelCGTService.php | Ternary guard |
| GoalProgressCalculator.php | Ternary guard |
| EmergencyFundCalculator.php | Guard clause |
| AutoRiskCalculator.php | Ternary guards |

## Recommended Pattern

For future development, use this pattern for all divisions:

```php
// Option 1: Ternary guard
$result = $divisor > 0 ? ($dividend / $divisor) * 100 : 0;

// Option 2: Early return
if ($divisor <= 0) {
    return 0; // or appropriate default/error
}
$result = ($dividend / $divisor) * 100;

// Option 3: API response for controllers
if ($totalValue <= 0) {
    return response()->json([
        'success' => false,
        'message' => 'Descriptive error message',
    ], 400);
}
```

## Testing

All modified files passed PHP syntax check:
```bash
php -l app/Services/Retirement/RetirementStrategyService.php
php -l app/Services/Documents/ImageResizeService.php
php -l app/Services/Trust/IHTPeriodicChargeCalculator.php
php -l app/Http/Controllers/Api/Investment/EfficientFrontierController.php
php -l app/Http/Controllers/Api/Investment/RebalancingStrategiesController.php
```
