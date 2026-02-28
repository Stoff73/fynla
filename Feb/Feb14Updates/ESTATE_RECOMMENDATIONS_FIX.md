# Estate Planning Recommendations Fix
**Date:** 14 February 2026
**Issue:** Estate module dashboard card in planning view not showing recommendations correctly

## Problems Fixed

### 1. Missing Gifting Strategies ✅
**Root Cause:** Step 4 (Life Cover Strategy) was reducing `remainingLiability` even though it's a RECOMMENDATION, not actual existing coverage. This caused `remainingLiability` to become 0, preventing Steps 5 & 6 (gifting strategies) from executing.

**Fix:** Removed the line that reduced `remainingLiability` after Step 4. Life insurance recommendations should not reduce the liability calculation since they represent future actions, not current coverage.

**Files Changed:**
- `app/Agents/EstateAgent.php:202-210` - Removed liability reduction for Step 4
- `app/Agents/EstateAgent.php:471` - Added `potential_saving` field to life cover recommendation

### 2. Empty Asset Breakdown (Liquidity Warning Issue) ✅
**Root Cause:** `EstateAgent::analyze()` was setting `asset_breakdown => []` (empty array) instead of gathering actual user assets. This caused:
- `step2LiquidityAssessment()` to see £0 liquid assets
- False "Liquidity Risk Identified" warnings
- Gifting strategies unable to assess affordability

**Fix:**
- Injected `EstateAssetAggregatorService` (already in constructor)
- Called `gatherUserAssets()` to collect all user assets (properties, investments, cash, business, chattels, pensions)
- Calculated liquid assets (cash + investments) vs illiquid assets (property + business + chattels)
- Populated `asset_breakdown` with actual data structure:
  ```php
  [
      'liquid' => $liquidAssets,
      'illiquid' => $illiquidAssets,
      'total' => $liquidAssets + $illiquidAssets,
  ]
  ```

**Files Changed:**
- `app/Agents/EstateAgent.php:59-76` - Added asset aggregation logic
- `app/Agents/EstateAgent.php:128` - Changed from empty array to `$assetBreakdown`
- `app/Agents/EstateAgent.php:98` - Fixed `calculateEstateHealthScore()` parameter

### 3. Improved Liquidity Warning Title ✅
**Root Cause:** "Liquidity Risk Identified" sounded alarming and vague.

**Fix:** Changed title to "Liquidity Planning Required" with improved description emphasizing planning need rather than just identifying risk.

**Files Changed:**
- `app/Agents/EstateAgent.php:343-351` - Updated title and description, added `potential_saving: 0`

## Technical Details

### Asset Aggregation Implementation
The fix properly aggregates assets from all modules:
- **Investments** (`InvestmentAccount`) - ISAs, GIAs, SIPPs
- **Properties** (`Property`) - Main residence, buy-to-let, secondary
- **Cash** (`SavingsAccount`) - Current accounts, savings, cash ISAs
- **Business Interests** (`BusinessInterest`) - With BPR eligibility
- **Chattels** (`Chattel`) - Personal property
- **Pensions** (`DCPension`, `DBPension`) - For income projections

All use the **single-record joint ownership pattern**:
```php
Property::where('user_id', $user->id)
    ->orWhere('joint_owner_id', $user->id)
    ->get()
```

User's share calculated via `CalculatesOwnershipShare` trait:
```php
$userShare = $this->calculateUserShare($asset, $user->id);
```

### Recommendation Flow (7-Step Decision Tree)
Now working correctly:
1. **Charitable Bequest Check** - Rate reduction from 40% to 36%
2. **Liquidity Assessment** - Now uses actual liquid assets
3. **Existing Life Cover** - Checks Protection module
4. **Life Cover Strategy** - Recommendation only (doesn't reduce liability)
5. **Annual Gifting** - £3,000/year exemption ✅ NOW SHOWING
6. **PET Gifting** - 7-year cycle strategy ✅ NOW SHOWING
7. **CLT into Trust** - Last resort for estates > £2m

## Results

### Before Fix
- ❌ No recommendations showing OR all showing £0 savings
- ❌ No gifting strategies appearing
- ❌ False liquidity warnings with £0 liquid assets
- ❌ Asset data not being considered

### After Fix
- ✅ 6 recommendations showing with proper values
- ✅ Annual Gifting Strategy (£42,000 saving)
- ✅ PET Gifting Strategy (with affordability warning)
- ✅ Accurate liquidity assessment (£264,400 liquid assets vs £686,050 IHT)
- ✅ 100% planning effectiveness (£814,120 net benefit)
- ✅ All asset types properly considered

## Testing Evidence
**Test User:** Chris James Jones (ID: 1185)
- Current IHT Position: £686,050
- Planned IHT Position: £0
- Planning Effectiveness: 100%
- Net Benefit: £814,120
- Liquid Assets: £264,400 (properly calculated from investments + cash)

## Files Modified
1. `app/Agents/EstateAgent.php`
   - Lines 59-76: Asset aggregation
   - Lines 98: Health score calculation fix
   - Lines 128: Asset breakdown population
   - Lines 202-210: Step 4 liability reduction removed
   - Lines 343-351: Liquidity assessment title/description
   - Lines 471: Life cover potential_saving field

## Related Services
- `EstateAssetAggregatorService` - Gathers assets from all modules
- `IHTCalculationService` - Calculates inheritance tax
- `step2LiquidityAssessment()` - Now receives proper asset data
- `step5AnnualGiftingStrategy()` - Can now assess affordability
- `step6PETGiftingStrategy()` - Can now assess affordability

## Next Steps
- ✅ All critical issues resolved
- ✅ No trusts showing as inappropriate recommendations
- ✅ Asset data properly integrated
- ✅ Gifting strategies displaying correctly
- ✅ Ready for production deployment
