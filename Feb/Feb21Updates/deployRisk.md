# Risk System Fixes - Deployment Notes

**Date:** 21 February 2026
**Branch:** `risk`
**Status:** DEPLOYED 21 February 2026

---

## Changes Summary

| # | Fix | What Changed |
|---|-----|-------------|
| 4 | GoalRiskService string-to-integer mapping | Goals using global risk profile now correctly map string levels (e.g. `'medium'`) to integers (e.g. `3`). Previously all global profile users silently got Conservative (level 1). |
| 5 | Education factor replaced with Investment Knowledge | Factor 3 now reads `knowledge_level` from `risk_profiles` table (novice/intermediate/experienced) instead of binary degree check from `users.education_level`. |
| 6 | PropertyRiskObserver standardised | Now extends `RiskRecalculationObserver` base class instead of duplicating debounce logic inline. |
| 7 | Surplus cash uses percentage thresholds | Threshold changed from hardcoded £500 to 10% of monthly income. Scales correctly for all income levels. |
| 8 | Rate limiting on recalculate endpoint | `POST /api/investment/risk/recalculate` now throttled to 6 requests per minute per user. |
| 9 | factor_breakdown stored on self-select | When a user self-selects their risk level, the current factor breakdown is now saved to the database for audit accuracy. |

---

## Files to Upload

### PHP Files

```
app/Services/Goals/GoalRiskService.php
app/Services/Risk/AutoRiskCalculator.php
app/Services/Risk/RiskPreferenceService.php
app/Observers/PropertyRiskObserver.php
app/Observers/UserRiskObserver.php
routes/api.php
```

### Frontend Files

```
resources/js/views/Risk/RiskFactorDetailPage.vue
```

### Rebuild Required: YES

The Vue component `RiskFactorDetailPage.vue` was changed, so a frontend rebuild is needed.

```bash
./deploy/fynla-org/build.sh
```

Then upload:

```
public/build/
```

---

## Post-Upload Commands

```bash
php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan optimize
```

---

## No Database Migrations

No migrations required. All changes are to existing PHP services, observers, routes, and one Vue component.
