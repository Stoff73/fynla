# Changes Summary - 4 February 2026

## Quick Reference

### New Constants Files

#### `app/Constants/TaxDefaults.php`
UK tax values for 2025/26 tax year:
- `NRB` = 325,000 (Nil Rate Band)
- `RNRB` = 175,000 (Residence Nil Rate Band)
- `ISA_ALLOWANCE` = 20,000
- `PENSION_ANNUAL_ALLOWANCE` = 60,000
- `PERSONAL_ALLOWANCE` = 12,570
- `DEFAULT_GROWTH_RATE` = 0.05 (5%)
- `CACHE_TTL_STANDARD` = 3600 (1 hour)
- `CACHE_TTL_SIMULATION` = 86400 (24 hours)

#### `app/Constants/EstateDefaults.php`
Estate planning estimation constants:
- `ESTIMATED_PROPERTY_VALUE` = 300,000
- `ESTIMATED_INVESTMENT_VALUE` = 100,000
- `RNRB_TAPER_THRESHOLD` = 2,000,000
- `DEFAULT_LIFE_EXPECTANCY` = 85

### New Form Requests

#### `app/Http/Requests/StoreInvestmentAccountRequest.php`
Validation rules for creating investment accounts.

Usage:
```php
public function storeAccount(StoreInvestmentAccountRequest $request): JsonResponse
{
    $validated = $request->validated();
    // ...
}
```

#### `app/Http/Requests/UpdateInvestmentAccountRequest.php`
Validation rules for updating investment accounts (all fields optional).

### New Vuex Helper

#### `resources/js/utils/asyncAction.js`
Reduces Vuex action boilerplate.

Usage:
```javascript
import { createAsyncAction } from '@/utils/asyncAction';

const actions = {
    fetchData: createAsyncAction(
        (payload) => myService.getData(payload),
        'setData'
    ),
};
```

### BaseAgent New Methods

```php
// Clear all cache for a user (handles tagged and non-tagged stores)
$this->invalidateUserCache($userId);

// Clear cache for multiple users (joint accounts)
$this->invalidateCacheForUsers([$userId, $jointOwnerId]);
```

---

## Files Changed

### Created (5 files)
1. `app/Constants/TaxDefaults.php`
2. `app/Constants/EstateDefaults.php`
3. `app/Http/Requests/StoreInvestmentAccountRequest.php`
4. `app/Http/Requests/UpdateInvestmentAccountRequest.php`
5. `resources/js/utils/asyncAction.js`

### Modified (10 files)
1. `app/Agents/BaseAgent.php`
2. `app/Agents/InvestmentAgent.php`
3. `app/Agents/EstateAgent.php`
4. `app/Agents/RetirementAgent.php`
5. `app/Services/Investment/PortfolioAnalyzer.php`
6. `app/Services/Onboarding/EstateOnboardingFlow.php`
7. `app/Jobs/RunMonteCarloSimulation.php`
8. `app/Http/Controllers/Api/HolisticPlanningController.php`
9. `app/Http/Controllers/Api/InvestmentController.php`
10. `resources/js/store/modules/investment.js`

---

## Deployment Notes

These changes are **backward compatible** and require no database migrations.

### For Production Deployment:
1. Upload all new files in `app/Constants/`
2. Upload all new files in `app/Http/Requests/`
3. Upload `resources/js/utils/asyncAction.js`
4. Upload modified files
5. Clear cache: `php artisan cache:clear`
6. Rebuild frontend: `./deploy/fynla-org/build.sh`

### Testing:
```bash
./vendor/bin/pest
```
