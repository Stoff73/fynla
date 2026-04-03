# Deploy Guide — Cache Fix (24-Hour TTL + Centralised Invalidation)

**Date:** 3 April 2026
**Branch:** cacheFix
**Generated from:** `git diff --name-only`

---

## What Changed

- Removed all `Cache::tags()` calls (were silently failing on production file driver)
- Extended all cache TTLs to 24 hours (invalidated immediately on data change)
- Created centralised `CacheInvalidationService` — single point of truth for clearing user caches
- Dashboard, plans, recommendations, analysis all now invalidate when any user data changes

## Files to Upload

### New File (create directory first)

```
app/Services/Cache/CacheInvalidationService.php
```

**Create the directory on server:** `mkdir -p app/Services/Cache/`

### Modified PHP Files (33 files)

```
app/Agents/BaseAgent.php
app/Agents/ProtectionAgent.php
app/Constants/TaxDefaults.php
app/Http/Controllers/Api/DashboardController.php
app/Http/Controllers/Api/Estate/TrustController.php
app/Http/Controllers/Api/Estate/WillController.php
app/Http/Controllers/Api/EstateController.php
app/Http/Controllers/Api/FamilyMembersController.php
app/Http/Controllers/Api/HolisticPlanningController.php
app/Http/Controllers/Api/Investment/AssetLocationController.php
app/Http/Controllers/Api/Investment/FeeImpactController.php
app/Http/Controllers/Api/Investment/GoalProgressController.php
app/Http/Controllers/Api/Investment/PerformanceAttributionController.php
app/Http/Controllers/Api/Investment/TaxOptimizationController.php
app/Http/Controllers/Api/Plans/PlanController.php
app/Http/Controllers/Api/PortfolioOptimizationController.php
app/Http/Controllers/Api/ProfileCompletenessController.php
app/Http/Controllers/Api/ProtectionController.php
app/Http/Controllers/Api/Retirement/DCPensionHoldingsController.php
app/Http/Controllers/Api/RetirementController.php
app/Http/Controllers/Api/SavingsController.php
app/Http/Controllers/Api/UserProfileController.php
app/Http/Controllers/Api/V1/Mobile/InsightsController.php
app/Http/Controllers/Api/V1/Mobile/ModuleSummaryController.php
app/Observers/LifeEventMonteCarloObserver.php
app/Services/Estate/LpaService.php
app/Services/Goals/GoalsProjectionService.php
app/Services/Mobile/MobileDashboardAggregator.php
app/Services/NetWorth/NetWorthService.php
app/Services/Onboarding/OnboardingService.php
app/Services/Retirement/RetirementProjectionService.php
app/Services/Risk/RiskPreferenceService.php
app/Traits/PolicyCRUDTrait.php
```

### No Frontend Build Required

This is a backend-only change. No Vue/JS files modified. No `public/build/` upload needed.

## Post-Upload SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Create directory for new service
mkdir -p app/Services/Cache

# Clear all caches (important — clears old cached data so new TTLs take effect)
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Verification

After deploy, the `Cache::tags` warnings in the Laravel log should stop:
```bash
grep "Failed to clear module caches" storage/logs/laravel.log | tail -5
```

No new warnings should appear after the deploy.
