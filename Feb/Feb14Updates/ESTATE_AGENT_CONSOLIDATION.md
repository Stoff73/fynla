# Estate Planning Consolidation - February 14, 2026

## Problem

The application had **two separate systems** generating estate planning recommendations:

1. **EstateAgent.php** - Used by Current view mode
2. **ComprehensiveEstatePlanService.php + PersonalizedTrustStrategyService.php** - Used by Planning view mode

This caused:
- ❌ Inconsistent recommendations between views
- ❌ Bugs fixed in one place but not the other (trust £2m threshold issue)
- ❌ Double maintenance burden
- ❌ Confusing architecture

## Solution

**Consolidated to use EstateAgent.php as single source of truth**

### Changes Made

#### 1. Updated EstateController.php

**File:** `app/Http/Controllers/Api/EstateController.php`

**Changes:**
- Added EstateAgent to constructor (line 30)
- Updated `getComprehensiveEstatePlan()` method to call EstateAgent instead of ComprehensiveEstatePlanService
- Added `formatPlanningViewData()` method to transform EstateAgent output for Planning view

**Before:**
```php
public function getComprehensiveEstatePlan(Request $request): JsonResponse
{
    $plan = $this->comprehensiveEstatePlan->generateComprehensiveEstatePlan($user);
    return response()->json(['success' => true, 'data' => $plan]);
}
```

**After:**
```php
public function getComprehensiveEstatePlan(Request $request): JsonResponse
{
    // Use EstateAgent as single source of truth
    $analysis = $this->estateAgent->analyze($user->id);
    $recommendations = $this->estateAgent->generateRecommendations($analysis);

    // Format for Planning view
    $formattedPlan = $this->formatPlanningViewData($analysis, $recommendations);
    return response()->json(['success' => true, 'data' => $formattedPlan]);
}
```

#### 2. Data Structure Mapping

The Planning view expects:
```javascript
{
  optimized_recommendation: {
    current_liability: 123000,
    total_saving: 45000,
    remaining_liability: 78000,
    recommendations: [
      {
        priority: 1,  // 1=high, 2=medium, 3=low
        category: "Life Cover Strategy",
        actions: [
          {
            action: "Purchase whole of life policy",
            details: "...",
            iht_saving: 45000,
            cost: 200,
            timeframe: "Within 3 months"
          }
        ]
      }
    ]
  },
  estate_summary: {
    gross_estate: 2542850,
    net_estate: 2542850,
    iht_liability: 123000,
    health_score: 65
  }
}
```

EstateAgent provides:
```php
[
  'category' => 'life_cover',
  'priority' => 'high',
  'step' => 4,
  'title' => 'Purchase Whole of Life Policy',
  'description' => '...',
  'potential_savings' => 45000,
  'annual_cost' => 200,
  'timeframe' => 'Within 3 months'
]
```

The `formatPlanningViewData()` method transforms EstateAgent's output to match Planning view's expected structure.

## Benefits

### ✅ Single Source of Truth
- All estate planning recommendations come from EstateAgent
- Both Current and Planning views use the same logic
- Bug fixes applied once work everywhere

### ✅ Consistent Recommendations
- No more discrepancies between view modes
- Trust threshold (£2m) enforced consistently
- Recommendation order (life cover first for under-50s) consistent

### ✅ Simpler Architecture
- Follows established Agent pattern (ProtectionAgent, SavingsAgent, RetirementAgent, EstateAgent)
- Less code to maintain
- Easier to understand and debug

### ✅ Easier Testing
- Test EstateAgent once
- Both views automatically get the tested logic

## Next Steps (After User Testing)

Once user confirms both views work correctly:

1. **Remove duplicate services:**
   - Delete `app/Services/Estate/PersonalizedTrustStrategyService.php`
   - Delete `app/Services/Estate/ComprehensiveEstatePlanService.php`
   - Remove these from EstateAgent constructor dependencies

2. **Clean up imports:**
   - Remove unused service imports from EstateAgent.php
   - Update any other controllers/services that may reference these

3. **Update tests:**
   - Remove tests for deleted services
   - Update tests to use EstateAgent

## Testing Checklist

User should verify:

- [ ] Current view shows recommendations correctly
- [ ] Planning view shows recommendations correctly
- [ ] Both views show SAME recommendations
- [ ] No trust recommendations for estates under £2m in either view
- [ ] Trust recommendations appear for estates > £2m (only in Planning view as advanced strategy)
- [ ] Life cover appears FIRST for users under 50
- [ ] Gifting strategies appear after life cover
- [ ] No errors in console or server logs

## Rollback Plan

If issues occur, revert:
1. `app/Http/Controllers/Api/EstateController.php` - restore to use ComprehensiveEstatePlanService
2. Run `php artisan cache:clear`

---

## Files Modified

1. **app/Http/Controllers/Api/EstateController.php**
   - Added EstateAgent dependency
   - Updated getComprehensiveEstatePlan() to use EstateAgent
   - Added formatPlanningViewData() transformation method

## Files to Delete (After Testing)

1. **app/Services/Estate/PersonalizedTrustStrategyService.php** - No longer needed
2. **app/Services/Estate/ComprehensiveEstatePlanService.php** - No longer needed

---

**Status:** ✅ Consolidation complete, awaiting user testing
**Date:** February 14, 2026
**Impact:** Both Current and Planning views now use EstateAgent as single source of truth
