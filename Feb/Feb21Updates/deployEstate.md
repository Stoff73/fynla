# Estate Module Fixes - Deployment Notes

**Date:** 21 February 2026
**Branch:** `estate`
**Status:** DEPLOYED 21 February 2026

---

## Changes Summary

| # | Fix | What Changed |
|---|-----|-------------|
| 17.1 | IHT cache re-enablement | Added 4 projection columns + `result_json` to `iht_calculations`. Cache now stores the full calculation result as JSON and returns it on cache hit, eliminating redundant recalculation when data hasn't changed. |
| 17.2 | Life cover integration | `EstateAgent` now queries `LifeInsurancePolicy` records in trust and passes real cover data to `step3ExistingLifeCover()` instead of hardcoded 0. Downstream steps correctly reduce remaining liability. |
| 17.3 | Actuarial calculations | Replaced hardcoded life expectancy (male:79/female:83) with actuarial table lookup in `ComprehensiveEstatePlanService`. `LifeCoverCalculator` sources growth rate from `AssumptionsService`. Discounted gift endpoint accepts optional `gender` for actuarial accuracy. |
| 17.4 | FormRequest extraction | Extracted 6 FormRequest classes from inline validation in `EstateController` (store/update for assets, liabilities, gifts). |
| 17.5 | Investment projection fallback | `IHTCalculationService` sources fallback growth rate from `AssumptionsService` instead of hardcoded 4.7%. Monte Carlo degradation path uses compound growth at fallback rate instead of zero growth. |
| 17.6 | Deprecated endpoints removed | Removed broken `/calculate-surviving-spouse-iht` route (live 500 error). Migrated all 6 frontend call sites from deprecated `calculateSecondDeathIHTPlanning` to `calculateIHTPlanning`. Removed backend alias method. |

---

## Files to Upload

### New PHP Files

```
database/migrations/2026_02_21_130000_add_projection_columns_to_iht_calculations.php
database/migrations/2026_02_21_140000_add_result_json_to_iht_calculations.php
app/Http/Requests/Estate/StoreAssetRequest.php
app/Http/Requests/Estate/UpdateAssetRequest.php
app/Http/Requests/Estate/StoreLiabilityRequest.php
app/Http/Requests/Estate/UpdateLiabilityRequest.php
app/Http/Requests/Estate/StoreGiftRequest.php
app/Http/Requests/Estate/UpdateGiftRequest.php
```

### Modified PHP Files

```
app/Services/Estate/IHTCalculationService.php
app/Models/Estate/IHTCalculation.php
app/Agents/EstateAgent.php
app/Services/Estate/ComprehensiveEstatePlanService.php
app/Services/Estate/LifeCoverCalculator.php
app/Http/Controllers/Api/Estate/GiftingController.php
app/Services/Estate/TrustService.php
app/Http/Controllers/Api/EstateController.php
app/Http/Controllers/Api/Estate/IHTController.php
app/Http/Controllers/Api/Estate/LifePolicyController.php
routes/api.php
```

### Frontend Files

```
resources/js/services/estateService.js
resources/js/store/modules/estate.js
resources/js/store/modules/netWorth.js
resources/js/views/Dashboard.vue
resources/js/components/Dashboard/ActionsOverviewCard.vue
resources/js/components/Estate/IHTPlanning.vue
```

### Rebuild Required: YES

Six JS/Vue files were changed, so a frontend rebuild is needed.

```bash
./deploy/fynla-org/build.sh
```

Then upload:

```
public/build/
```

---

## Database Migrations Required: YES

Two migrations must be run **before** clearing caches:

```bash
php artisan migrate --force
```

This will:
1. Add `projected_cash`, `projected_investments`, `projected_properties`, `retirement_age` columns to `iht_calculations`
2. Add `result_json` JSON column to `iht_calculations`

---

## Post-Upload Commands

Run in order:

```bash
php artisan migrate --force
php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan optimize
```

---

## Notes

- No seeder changes required for this deployment
- Existing IHT cache entries will be recalculated on first access (stale entries without `result_json` are skipped)
- The removed `/calculate-surviving-spouse-iht` route was a live 500 error with no frontend callers -- removal is safe
- The deprecated `/calculate-second-death-iht-planning` route is removed along with all frontend references
