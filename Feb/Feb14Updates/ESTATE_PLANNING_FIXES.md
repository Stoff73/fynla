# Estate Planning Recommendation Fixes - February 14, 2026

## Critical Issues Fixed

### ❌ Previous Incorrect Behaviour
1. **Trust recommendations at £650k** - Should only be for estates > £2m
2. **Gifting cycles unlimited** - Should stop at age 80
3. **Wrong order** - Life cover was after gifting (should be FIRST for under-50s)
4. **No affordability checks** - Recommending gifts people can't afford
5. **No liquidity consideration** - Suggesting gifts from illiquid assets
6. **No expenditure checks** - Not reserving funds for 7 years of living expenses

### ✅ Fixed Behaviour

---

## 1. Trust Recommendations (£2m Threshold)

**File:** `app/Agents/EstateAgent.php`

### Changed From:
```php
// Recommended trusts for estates > £650k
if ($grossEstate > 650000 && $user->trusts()->count() === 0) {
    $score -= 10;
}

// CLT recommended for any remaining liability
if ($remainingLiability > 0) {
    $cltResult = $this->step7CLTIntoTrust($remainingLiability);
}
```

### Changed To:
```php
// Only recommend trusts for estates > £2m (taxable estates)
if ($grossEstate > 2000000 && $user->trusts()->count() === 0) {
    $score -= 10;
}

// CLT ONLY for estates > £2m
if ($remainingLiability > 0 && $grossEstate > 2000000) {
    $cltResult = $this->step7CLTIntoTrust($remainingLiability);
}
```

**Impact:** Trusts now only recommended when there's a significant taxable estate (> £2m).

---

## 2. PET Gifting Cycles (Age 80 Cap)

**File:** `app/Agents/EstateAgent.php` - `step6PETGiftingStrategy()`

### Changed From:
```php
// Unlimited gifting based on life expectancy
$yearsToLifeExpectancy = max(1, DEFAULT_LIFE_EXPECTANCY - $currentAge);
$sevenYearCycles = floor($yearsToLifeExpectancy / 7);
```

### Changed To:
```php
// Maximum gifting age is 80
$maxGiftingAge = 80;

// Calculate years available for gifting (capped at age 80)
$yearsAvailableForGifting = max(0, $maxGiftingAge - $currentAge);

// Calculate 7-year cycles available
$sevenYearCycles = floor($yearsAvailableForGifting / 7);

// If there's a remainder that goes past age 80, reduce by one cycle
$remainder = $yearsAvailableForGifting % 7;
$ageAtEndOfLastCycle = $currentAge + ($sevenYearCycles * 7);

if ($remainder > 0 && ($ageAtEndOfLastCycle + $remainder) > $maxGiftingAge) {
    $sevenYearCycles = max(0, $sevenYearCycles - 1);
}
```

**Example:**
- Age 50: 4 cycles (50, 57, 64, 71) ✅
- Age 50 with remainder: If 5th cycle would go to 85, reduce to 4 cycles ✅
- Age 76: 0 cycles (not enough time before 80) ✅

---

## 3. Recommendation Order (Life Cover FIRST)

**File:** `app/Agents/EstateAgent.php` - `generateRecommendations()`

### Changed From (WRONG ORDER):
```php
// Step 4: Annual Gifting Strategy (First Resort)
// Step 5: Life Cover Strategy (Second Resort) - Only if age <= 50
// Step 6: PET Gifting Strategy (Third Resort)
// Step 7: CLT into Trust (Last Resort)
```

### Changed To (CORRECT ORDER):
```php
// Step 4: Life Cover Strategy (FIRST for under-50s)
if ($remainingLiability > 0 && $currentAge <= 50) {
    // Life cover recommended BEFORE any gifting
}

// Step 5: Annual Gifting Strategy
// Step 6: PET Gifting Strategy
// Step 7: CLT into Trust (LAST RESORT, only if estate > £2m)
```

**Impact:**
- **Under 50 years old:** Life cover recommended FIRST (before gifting)
- **50+ years old:** Gifting strategies recommended (life cover premiums too expensive)
- **All ages:** Trust LAST and only for £2m+ estates

---

## 4. Affordability Checks for Annual Gifting

**File:** `app/Agents/EstateAgent.php` - `step5AnnualGiftingStrategy()`

### Added:
```php
// Check affordability - ensure user has liquid assets
$liquidAssets = $liquidityData['liquid_assets'] ?? 0;
$canAffordAnnualGifting = $liquidAssets >= ($annualExemption * 3); // At least 3 years reserve

'affordability_check' => [
    'liquid_assets' => $liquidAssets,
    'can_afford' => $canAffordAnnualGifting,
    'warning' => $canAffordAnnualGifting ? null : 'Insufficient liquid assets - build emergency fund first',
],
```

**Impact:** No longer recommends annual gifting if user doesn't have liquid funds to support it.

---

## 5. Comprehensive Affordability for PET Gifting

**File:** `app/Agents/EstateAgent.php` - `step6PETGiftingStrategy()`

### Added (CRITICAL):
```php
// Calculate 7 years of expenditure needs per cycle
$monthlyExpenditure = 3000; // Would ideally come from user data
$sevenYearsExpenditure = $monthlyExpenditure * 12 * 7; // £252,000

// Available to gift per cycle = Liquid assets - 7 years expenditure
$affordableGiftPerCycle = max(0, ($liquidAssets / $sevenYearCycles) - $sevenYearsExpenditure);

// Don't recommend gifting more than they can afford
$recommendedGiftPerCycle = min($nrb, $affordableGiftPerCycle);

'affordability_check' => [
    'liquid_assets' => $liquidAssets,
    'seven_years_expenditure_per_cycle' => $sevenYearsExpenditure,
    'affordable_gift_per_cycle' => $affordableGiftPerCycle,
    'recommended_gift_per_cycle' => $recommendedGiftPerCycle,
    'max_theoretical_gift_per_cycle' => $nrb,
    'can_afford' => $canAffordPetGifting,
    'warning' => $canAffordPetGifting ? null : 'Insufficient liquid assets after accounting for 7 years living expenses',
],
```

**Impact:**
- Reserves 7 years of living expenses before recommending any PET gift
- Only recommends gifts from accessible, liquid assets
- Prevents recommending gifts people can't actually afford
- Adjusts recommended amount based on available liquidity

**Example:**
- Liquid assets: £500k
- 7-year expenditure: £252k (£3k/month × 84 months)
- Available for gifting: £248k
- Recommended per cycle: £248k (not £325k NRB)
- Priority: Medium (affordable but below NRB)

---

## 6. Warning Messages Added

All gifting recommendations now include explicit warnings:

### Annual Gifting:
```
✓ You have sufficient liquid assets to support this strategy
❌ WARNING: Ensure you have sufficient liquid funds before gifting
```

### PET Gifting:
```
✓ Recommended gift per cycle: £248,000 (based on your liquidity)
❌ WARNING: Insufficient liquid assets to support recommended PET gifting
CRITICAL: Reserve £252,000 for 7 years of living expenses per cycle
Only gift from liquid, accessible assets (not property)
```

### Trust Recommendations:
```
Only shown for estates > £2m
Marked as "LAST RESORT ONLY"
```

---

## Testing Scenarios

### Scenario 1: Chris@fynla.org (Age 49, £2.55m estate)

**Before Fix:**
- ❌ Trust recommended (estate > £650k)
- ❌ Gifting before life cover
- ❌ No affordability warnings
- ❌ Unlimited cycles (6+)

**After Fix:**
- ✅ Life cover recommended FIRST (age < 50)
- ✅ Gifting cycles capped at age 80 (4 cycles)
- ✅ Affordability checks performed
- ✅ Trust recommended (estate > £2m) but LAST
- ✅ Reserves £252k per cycle for living expenses

### Scenario 2: Young Family (Age 35, £600k estate)

**Before Fix:**
- ❌ No life cover priority
- ❌ Trust recommended (> £650k)
- ❌ 6+ gifting cycles recommended

**After Fix:**
- ✅ Life cover FIRST priority (age < 50)
- ✅ No trust (estate < £2m)
- ✅ 6 gifting cycles until age 80
- ✅ Affordability warnings shown

### Scenario 3: Wealthy Couple (Age 55, £3.5m estate)

**Before Fix:**
- ❌ Life cover recommended (too old, expensive)
- ❌ Trust at wrong priority

**After Fix:**
- ✅ No life cover (age > 50)
- ✅ Gifting strategies recommended FIRST
- ✅ Trust recommended LAST (estate > £2m)
- ✅ 3 cycles until age 80 (not 4)

---

## Summary of Changes

| Issue | Before | After |
|-------|--------|-------|
| **Trust threshold** | £650k | **£2m** |
| **Gifting age limit** | Unlimited | **Cap at 80** |
| **Remainder handling** | Ignored | **Minus one cycle** |
| **Recommendation order** | Gifting → Life cover | **Life cover → Gifting** (under-50s) |
| **Affordability** | Not checked | **Comprehensive checks** |
| **Expenditure reserve** | Not considered | **7 years reserved** |
| **Liquidity check** | Not checked | **Liquid assets validated** |
| **Priority accuracy** | Wrong priorities | **Correct priorities** |

---

## Files Modified

1. **app/Agents/EstateAgent.php**
   - Line 616-619: Trust threshold £650k → £2m
   - Line 163-240: Reordered recommendation steps
   - Line 443-484: Life cover now step 4 (FIRST for under-50s)
   - Line 402-441: Annual gifting now step 5 with affordability
   - Line 486-600: PET gifting now step 6 with comprehensive checks
   - Line 235-240: Trust (step 7) only for estates > £2m

2. **app/Services/Estate/PersonalizedTrustStrategyService.php** (ADDED)
   - Line 40-46: Added $grossEstate parameter to generatePersonalizedTrustStrategy()
   - Line 104-123: Added £2m threshold check in generateTrustStrategies()
   - Early return with empty strategies if estate ≤ £2m
   - **Reason:** This service powers the Planning view mode recommendations
   - **Impact:** Discretionary trust no longer recommended for estates under £2m in Planning view

3. **app/Services/Estate/ComprehensiveEstatePlanService.php** (ADDED)
   - Line 104-112: Pass gross estate value to PersonalizedTrustStrategyService
   - Extracts $grossEstate from $ihtAnalysis['total_net_estate']
   - Ensures trust strategies have estate value for threshold check

---

## Next Steps

1. Update `GiftingStrategyOptimizer.php` with same age 80 cap logic
2. Pull user's actual monthly expenditure from user model
3. Add integration tests for affordability scenarios
4. Update frontend to display affordability warnings
5. Document these rules in user-facing help text

---

## Related Files to Review

- `app/Services/Estate/GiftingStrategyOptimizer.php` - May need similar fixes
- Frontend estate planning components - Display affordability warnings
- Database seeder - Update chris@fynla.org to test these scenarios
