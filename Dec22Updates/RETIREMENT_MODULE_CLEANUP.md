# Retirement Module Cleanup - December 22, 2025

**Branch**: `retirementupdate`
**Version**: v0.4.2

---

## Summary

Removed unused ReadinessScorer service and ReadinessGauge component from the Retirement module. The application now uses income gap-based analysis instead of readiness scores.

---

## Changes Made

### 1. Deleted Files (4 files)

| File | Type | Reason |
|------|------|--------|
| `app/Services/Retirement/ReadinessScorer.php` | Backend Service | Replaced by income_gap based logic |
| `resources/js/components/Retirement/ReadinessGauge.vue` | Vue Component | Not imported/used anywhere |
| `tests/Unit/Services/Retirement/ReadinessScorerTest.php` | Unit Test | Service deleted |
| `tests/frontend/components/Retirement/ReadinessGauge.test.js` | Frontend Test | Component deleted |

### 2. Modified Backend Files (6 files)

#### `app/Agents/RetirementAgent.php`
- Removed `ReadinessScorer` dependency injection
- Removed `readiness_score`, `readiness_category`, `readiness_color` from summary
- Replaced readiness-based recommendations with income_gap based logic
- Updated scenario methods to remove readiness_score
- Changed `compareScenarios()` to use "smallest_gap" instead of "best_score"

#### `app/Http/Controllers/Api/RetirementController.php`
- Removed `readiness_score`, `readiness_category`, `readiness_color` from API response
- Updated scenario difference calculation to remove `score_difference`

#### `app/Services/Coordination/HolisticPlanner.php`
- Replaced all `readiness_score` references with `income_gap` based logic
- Added `getRetirementStatus()` method for status determination
- Updated risk identification to use income gap thresholds
- Updated overall score calculation to derive retirement score from income gap

#### `app/Services/Coordination/PriorityRanker.php`
- Updated retirement urgency calculation to use income_gap instead of readiness_score
- Income gap thresholds: >£15k (80), >£10k (70), >£5k (55), else (35)

#### `app/Services/Dashboard/DashboardAggregator.php`
- Removed hardcoded `readiness_score: 68`
- Added `income_gap: 5000` to retirement summary

#### `app/Services/Retirement/PensionProjector.php`
- Added `RiskPreferenceService` dependency injection
- Added `getGrowthRateForUser()` method to get growth rate from user's risk profile
- Changed from hardcoded 5% growth rate to user's risk-based expected return
- Falls back to 5% default only if user has no risk profile

### 3. Modified Frontend Files (2 files)

#### `resources/js/store/modules/retirement.js`
- Updated `retirementReadinessScore` getter to calculate from income_gap
- Formula: `100 - (income_gap / 500)` (every £500 gap reduces score by 1)
- Maintains backwards compatibility with FinancialHealthScore.vue

#### `resources/js/components/Holistic/ModuleSummaries.vue`
- Removed readiness score display
- Added income gap display with conditional coloring (red if gap, green if surplus)

### 4. Modified Test Files (3 files)

#### `tests/Feature/RetirementIntegrationTest.php`
- Updated assertions to check `income_gap` instead of `readiness_score`
- Removed `readiness_score` from expected JSON structure

#### `tests/Feature/RetirementModuleTest.php`
- Removed `readiness_score` from expected API response structure

#### `tests/Integration/DashboardIntegrationTest.php`
- Updated test to check for `income_gap` instead of `readiness_score`

---

## New Scoring Logic

### Before (ReadinessScorer)
```php
$score = ($projectedIncome / $targetIncome) * 100;
// Categories: Excellent (90+), Good (70-89), Fair (50-69), Critical (0-49)
```

### After (Income Gap Based)
```php
$incomeGap = $targetIncome - $projectedIncome;

// For scoring (where needed for backwards compatibility):
$score = max(0, min(100, 100 - ($incomeGap / 500)));

// For status determination:
if ($incomeGap <= 0) return 'excellent';       // Surplus
if ($incomeGap < 5000) return 'good';          // Small gap
if ($incomeGap < 10000) return 'needs_improvement';
return 'critical';                              // Large gap
```

---

## PensionProjector Enhancement

### Before
```php
// Hardcoded 5% growth rate for all users
$defaultGrowthRate = 0.05;
$projectedValue = $this->projectDCPension($dcPension, $yearsToRetirement, $defaultGrowthRate);
```

### After
```php
// Gets growth rate from user's risk profile
$growthRate = $this->getGrowthRateForUser($userId);
// Returns risk-adjusted expected return, or 5% default if no risk profile

private function getGrowthRateForUser(int $userId): float
{
    $riskProfile = $this->riskService->getRiskProfile($userId);

    if ($riskProfile && $riskProfile['risk_level']) {
        $riskParams = $this->riskService->getReturnParameters($riskProfile['risk_level']);
        return ($riskParams['expected_return_typical'] ?? 5) / 100;
    }

    return self::DEFAULT_GROWTH_RATE; // 5%
}
```

---

## API Response Changes

### `/api/retirement/analyze` Response

#### Removed Fields
- `readiness_score`
- `readiness_category`
- `readiness_color`

#### Kept Fields
- `projected_income`
- `target_income`
- `income_gap` (already existed)
- `years_to_retirement`
- `total_pension_wealth`
- `recommendations`
- All projection data

---

## Default Values Summary

All defaults are only used when user has no input:

| Field | Default | When Used |
|-------|---------|-----------|
| Retirement age | 65-67 | No `target_retirement_age` AND no pension `retirement_age` |
| Growth rate | 5% | No risk profile set |
| State pension age | 67 | No state pension record |
| Withdrawal rate | 4% | Standard assumption |

---

## Files Changed Summary

```
Modified:
 M app/Agents/RetirementAgent.php
 M app/Http/Controllers/Api/RetirementController.php
 M app/Services/Coordination/HolisticPlanner.php
 M app/Services/Coordination/PriorityRanker.php
 M app/Services/Dashboard/DashboardAggregator.php
 M app/Services/Retirement/PensionProjector.php
 M resources/js/components/Holistic/ModuleSummaries.vue
 M resources/js/store/modules/retirement.js
 M tests/Feature/RetirementIntegrationTest.php
 M tests/Feature/RetirementModuleTest.php
 M tests/Integration/DashboardIntegrationTest.php

Deleted:
 D app/Services/Retirement/ReadinessScorer.php
 D resources/js/components/Retirement/ReadinessGauge.vue
 D tests/Unit/Services/Retirement/ReadinessScorerTest.php
 D tests/frontend/components/Retirement/ReadinessGauge.test.js
```

---

## Testing Notes

- Frontend tests that mock `retirementReadinessScore` getter still work (getter still exists, just calculates differently)
- Backend tests updated to check `income_gap` instead of `readiness_score`
- `FinancialHealthScore.vue` unchanged - uses same getter name with new calculation

---

## Migration Notes

No database migrations required - all changes are code-only.

---

## Backwards Compatibility

- `retirementReadinessScore` Vuex getter maintained for `FinancialHealthScore.vue`
- Income gap was already being calculated and returned
- No breaking changes to critical frontend components

---

## Risk Level Consistency (Additional Changes)

### Problem
The Retirement module had inconsistent risk level handling:
- `RetirementProfile.risk_tolerance` - legacy 3-level system (cautious/balanced/adventurous)
- `DCPension.risk_preference` - per-pension 5-level system
- Neither used the Risk module as the primary source

### Solution
Established consistent risk level hierarchy:

1. **Primary**: User's main risk level from Risk module (`RiskPreferenceService::getRiskProfile()`)
2. **Per-Pension Override**: Each DC pension can override with its own `risk_preference` (if `has_custom_risk = true`)
3. **Default**: 'medium' risk level (5% expected return)

### Files Modified

#### `app/Services/Retirement/PensionProjector.php`
- Added `getGrowthRateForPension()` method for per-pension risk overrides
- Added `getUserMainRiskLevel()` to get user's main risk from Risk module
- Added `getGrowthRateForRiskLevel()` to convert risk level to growth rate
- Updated `projectTotalRetirementIncome()` to use per-pension growth rates
- Now includes `growth_rate_used` in DC projection output

```php
// Priority for each DC pension:
// 1. Pension's own risk_preference (if has_custom_risk = true)
// 2. User's main risk level from Risk module
// 3. Default 5%

private function getGrowthRateForPension(DCPension $pension, int $userId): float
{
    if ($pension->has_custom_risk && $pension->risk_preference) {
        return $this->getGrowthRateForRiskLevel($pension->risk_preference);
    }
    return $this->getGrowthRateForUser($userId);
}
```

#### `app/Models/RetirementProfile.php`
- Added deprecation notice for `risk_tolerance` field
- New code should use `RiskPreferenceService::getRiskProfile()`

### Risk Level Mapping

| Level | Expected Return | Use Case |
|-------|-----------------|----------|
| low | 3% | Very conservative |
| lower_medium | 4% | Conservative |
| medium | 5% | Balanced (default) |
| upper_medium | 6% | Growth |
| high | 7% | Aggressive |

### RetirementProjectionService (Already Correct)
The `RetirementProjectionService` already followed the correct pattern:
```php
private function getUserRiskLevel(User $user): string
{
    // 1. Check risk profile via service (primary source)
    $riskProfile = $this->riskService->getRiskProfile($user->id);
    if ($riskProfile && isset($riskProfile['risk_level'])) {
        return $riskProfile['risk_level'];
    }

    // 2. Check DC pensions for custom risk
    foreach ($user->dcPensions as $pension) {
        if ($pension->risk_preference) {
            return $pension->risk_preference;
        }
    }

    return 'medium'; // 3. Default
}
```

### Deprecation Notes

**`RetirementProfile.risk_tolerance`**: DEPRECATED
- Kept for backward compatibility only
- New code should use `RiskPreferenceService::getRiskProfile()` for user's main risk level
- Legacy 3-level values (cautious/balanced/adventurous) can be mapped via `RiskPreferenceService::mapLegacyTolerance()`

### Database Schema (Already Correct)

The database already supports the risk level hierarchy. No migrations needed.

**dc_pensions table**:
```sql
`risk_preference` enum('low','lower_medium','medium','upper_medium','high') DEFAULT NULL,
`has_custom_risk` tinyint(1) NOT NULL DEFAULT '0',
```

**retirement_profiles table**:
```sql
`risk_tolerance` enum('cautious','balanced','adventurous') DEFAULT 'balanced',
```

Existing migrations that created these columns:
- `2025_12_16_152552_add_risk_preference_to_dc_pensions_table`

### Test File Updates

#### `tests/Unit/Services/Retirement/PensionProjectorTest.php`
- Updated to use Laravel service container for dependency injection
- Fixed import: `App\Services\Risk\RiskPreferenceService` (was incorrectly `App\Services\Investment\RiskPreferenceService`)

---

## Complete Files Changed Summary

```
Modified:
 M Dec21Updates/RETIREMENT_MODULE_ARCHITECTURE.md
 M app/Agents/RetirementAgent.php
 M app/Http/Controllers/Api/RetirementController.php
 M app/Models/RetirementProfile.php
 M app/Services/Coordination/HolisticPlanner.php
 M app/Services/Coordination/PriorityRanker.php
 M app/Services/Dashboard/DashboardAggregator.php
 M app/Services/Retirement/PensionProjector.php
 M resources/js/components/Holistic/ModuleSummaries.vue
 M resources/js/store/modules/retirement.js
 M tests/Feature/RetirementIntegrationTest.php
 M tests/Feature/RetirementModuleTest.php
 M tests/Integration/DashboardIntegrationTest.php
 M tests/Unit/Services/Retirement/PensionProjectorTest.php

Deleted:
 D app/Services/Retirement/ReadinessScorer.php
 D resources/js/components/Retirement/ReadinessGauge.vue
 D tests/Unit/Services/Retirement/ReadinessScorerTest.php
 D tests/frontend/components/Retirement/ReadinessGauge.test.js

Created:
 A Dec22Updates/RETIREMENT_MODULE_CLEANUP.md
```

---

## Test Results

All tests pass after changes:
- **PensionProjectorTest**: 7 tests, 10 assertions
- **RetirementModuleTest**: 17 passed, 6 skipped (pre-existing skips)
