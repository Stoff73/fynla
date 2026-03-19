# Deployment Guide — 19 March 2026

## Pre-Deployment: Run Pending Migrations

The 3 migrations from 18 March must be run **before** deploying today's changes:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate
```

Migrations:
- `2026_03_18_100000` — SoftDeletes on models
- `2026_03_18_100001` — Unique constraints (check for duplicates first)
- `2026_03_18_100002` — Database indexes

## Files to Upload

### Build Step (run locally first)

```bash
./deploy/fynla-org/build.sh
```

Upload entire `public/build/` directory.

### PHP Files

```
app/Http/Controllers/Api/DashboardController.php
app/Http/Middleware/AdvisorImpersonationMiddleware.php
app/Services/Dashboard/DashboardAggregator.php
app/Services/Investment/MonteCarloSimulator.php
app/Services/Shared/MonteCarloEngine.php
database/seeders/PreviewUserSeeder.php
routes/api.php
```

### Deleted Files (remove from server)

```
resources/js/components/Dashboard/FinancialHealthScore.vue
tests/frontend/components/Dashboard/FinancialHealthScore.test.js
```

### Frontend Files (included in build, but listed for completeness)

```
resources/css/app.css
resources/js/constants/lifeStageConfig.js
resources/js/services/dashboardService.js
resources/js/store/modules/dashboard.js
resources/js/store/modules/retirement.js
resources/js/views/Public/LandingPage.vue
resources/js/components/Onboarding/FocusAreaSelection.vue
resources/js/components/SideMenu.vue
resources/js/components/Preview/PersonaSelector.vue
resources/js/components/Preview/PersonaSelectionModal.vue
tailwind.config.js
```

Plus 43 Vue files with `error-*` → `raspberry-*` token changes (all compiled into the build).

## Post-Upload: Clear Caches

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Post-Deploy Testing

1. **Login flow** — verify `UserResource` changes don't break frontend (fields may have changed)
2. **Dashboard** — verify loads without 500 errors (FinancialHealthScore endpoint removed)
3. **Landing page** — verify journey cards show name + tagline only (no age ranges)
4. **Sidebar** — verify stage label shows without age range
5. **Preview personas** — verify persona selector works without age ranges
6. **Color tokens** — spot-check error/danger states use raspberry colors (not amber/orange)
7. **Session length** — Sanctum expiry changed from 8hr to 4hr, may cause logouts

## Risk Areas

| Area | Risk | Mitigation |
|------|------|------------|
| `UserResource` auth response | Frontend may expect removed fields | Test login + dashboard immediately |
| Sanctum 4hr expiry | Unexpected logouts for long sessions | Monitor user feedback |
| Unique constraint migration | May fail if duplicate records exist | Check for duplicates before migrating |
| FinancialHealthScore removal | Frontend may still call deleted endpoint | 404 is handled gracefully by error boundaries |
