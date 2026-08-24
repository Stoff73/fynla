# Estate Planning Module Fixes

**Date:** 21 February 2026
**Branch:** `estate`
**Status:** COMPLETED
**Commits:** `5245ba8` (6 fixes), `pending` (cache bugfix)

---

## Issues Addressed

### From Section 17 (Known Issues and Limitations)

| # | Priority | Issue | Status |
|---|----------|-------|--------|
| 17.1 | HIGH | IHT Calculation Cache Temporarily Disabled | DONE + BUGFIX |
| 17.2 | MEDIUM | Life Cover Integration TODO in EstateAgent | DONE |
| 17.3 | MEDIUM | Simplified Actuarial Calculations (3 locations) | DONE |
| 17.4 | LOW | Inline Validation in EstateController | DONE |
| 17.5 | MEDIUM | Investment Projection Fallback (hardcoded 4.7%) | DONE |
| 17.6 | LOW | Deprecated/broken endpoints | DONE |

---

## Changes By File

### New Files

| File | Purpose |
|------|---------|
| `database/migrations/2026_02_21_130000_add_projection_columns_to_iht_calculations.php` | Adds `projected_cash`, `projected_investments`, `projected_properties`, `retirement_age` to `iht_calculations` |
| `database/migrations/2026_02_21_140000_add_result_json_to_iht_calculations.php` | Adds `result_json` JSON column for complete cache storage |
| `app/Http/Requests/Estate/StoreAssetRequest.php` | FormRequest for asset creation |
| `app/Http/Requests/Estate/UpdateAssetRequest.php` | FormRequest for asset update |
| `app/Http/Requests/Estate/StoreLiabilityRequest.php` | FormRequest for liability creation |
| `app/Http/Requests/Estate/UpdateLiabilityRequest.php` | FormRequest for liability update |
| `app/Http/Requests/Estate/StoreGiftRequest.php` | FormRequest for gift creation |
| `app/Http/Requests/Estate/UpdateGiftRequest.php` | FormRequest for gift update |

### Modified Files

| File | Change |
|------|--------|
| `app/Services/Estate/IHTCalculationService.php` | Save 4 projection columns + `result_json` in `saveCalculation()`; cache returns `result_json` instead of `toArray()`; source fallback rate from `AssumptionsService`; add `getFallbackGrowthRate()` helper |
| `app/Models/Estate/IHTCalculation.php` | Add `projected_cash`, `projected_investments`, `projected_properties`, `retirement_age`, `result_json` to `$fillable` and `$casts` |
| `app/Agents/EstateAgent.php` | Load `LifeInsurancePolicy` in `analyze()`; pass real cover data to `step3ExistingLifeCover()` |
| `app/Services/Estate/ComprehensiveEstatePlanService.php` | Replace hardcoded life expectancy (male:79/female:83) with actuarial table lookup |
| `app/Services/Estate/LifeCoverCalculator.php` | Inject `AssumptionsService`; source self-insurance return rate dynamically via `getInvestmentReturnRate()` |
| `app/Http/Controllers/Api/Estate/GiftingController.php` | Add `gender` parameter to `calculateDiscountedGiftDiscount()`; call `calculateIHT()` instead of deprecated alias |
| `app/Services/Estate/TrustService.php` | Accept optional `gender` in `estimateDiscountedGiftDiscount()`; use actuarial tables when gender provided |
| `app/Http/Controllers/Api/EstateController.php` | Replace inline `$request->validate()` with 6 FormRequest type hints |
| `app/Http/Controllers/Api/Estate/IHTController.php` | Remove `calculateSecondDeathIHTPlanning()` method |
| `app/Http/Controllers/Api/Estate/LifePolicyController.php` | Call `calculateIHT()` instead of deprecated alias |
| `routes/api.php` | Remove both deprecated estate routes (`calculate-surviving-spouse-iht`, `calculate-second-death-iht-planning`) |
| `resources/js/services/estateService.js` | Rename method to `calculateIHTPlanning()`; update URL to `/estate/calculate-iht` |
| `resources/js/store/modules/estate.js` | Rename action from `calculateSecondDeathIHTPlanning` to `calculateIHTPlanning` |
| `resources/js/store/modules/netWorth.js` | Update dispatch to `estate/calculateIHTPlanning` |
| `resources/js/views/Dashboard.vue` | Update dispatch to `estate/calculateIHTPlanning` |
| `resources/js/components/Dashboard/ActionsOverviewCard.vue` | Update service call to `calculateIHTPlanning()` |
| `resources/js/components/Estate/IHTPlanning.vue` | Update mapped action and calls to `calculateIHTPlanning` |

---

## Bugfix: Cache 500 Error

**Problem discovered after initial commit:** Re-enabling the cache via `$cached->toArray()` returned only DB columns, missing keys like `nrb_individual`, `nrb_transferred`, `rnrb_individual`, `rnrb_transferred`, `is_widowed`, `iht_rate`, `iht_rate_type`, `iht_rate_message`, `charitable_giving_percent`, etc. The IHTController expects the full result array from `calculate()`, causing `Undefined array key "nrb_individual"` at line 108.

**Fix:** Added `result_json` JSON column to `iht_calculations` table. The `saveCalculation()` method now stores the complete result array as JSON. The `getCachedCalculation()` method returns `$cached->result_json` (the full array) instead of `$cached->toArray()` (incomplete DB columns). Stale cache entries without `result_json` are skipped and recalculated.

---

## Test Results

- 102 estate tests: PASS
- 42 risk tests: PASS
- 23 savings tests: PASS
- **167 total tests: ALL PASS**

---

## Deployment Notes

### Migration Required
```bash
php artisan migrate
php artisan db:seed
```

### Files to Upload

**New files (8):**
- `database/migrations/2026_02_21_130000_add_projection_columns_to_iht_calculations.php`
- `database/migrations/2026_02_21_140000_add_result_json_to_iht_calculations.php`
- `app/Http/Requests/Estate/StoreAssetRequest.php`
- `app/Http/Requests/Estate/UpdateAssetRequest.php`
- `app/Http/Requests/Estate/StoreLiabilityRequest.php`
- `app/Http/Requests/Estate/UpdateLiabilityRequest.php`
- `app/Http/Requests/Estate/StoreGiftRequest.php`
- `app/Http/Requests/Estate/UpdateGiftRequest.php`

**Modified PHP files (11):**
- `app/Services/Estate/IHTCalculationService.php`
- `app/Models/Estate/IHTCalculation.php`
- `app/Agents/EstateAgent.php`
- `app/Services/Estate/ComprehensiveEstatePlanService.php`
- `app/Services/Estate/LifeCoverCalculator.php`
- `app/Http/Controllers/Api/Estate/GiftingController.php`
- `app/Services/Estate/TrustService.php`
- `app/Http/Controllers/Api/EstateController.php`
- `app/Http/Controllers/Api/Estate/IHTController.php`
- `app/Http/Controllers/Api/Estate/LifePolicyController.php`
- `routes/api.php`

**Modified JS/Vue files (6):**
- `resources/js/services/estateService.js`
- `resources/js/store/modules/estate.js`
- `resources/js/store/modules/netWorth.js`
- `resources/js/views/Dashboard.vue`
- `resources/js/components/Dashboard/ActionsOverviewCard.vue`
- `resources/js/components/Estate/IHTPlanning.vue`

**Frontend build required** — run `./deploy/fynla-org/build.sh` and upload `public/build/`

### Post-deploy
```bash
php artisan migrate
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```
