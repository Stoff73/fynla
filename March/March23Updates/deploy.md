# Deploy Notes — 23 March 2026

## Summary

**Branch:** `dashboard`
**Changes:** 67 frontend files (Vue components, JS, CSS) — no PHP changes
**Build required:** Yes — frontend rebuild needed

## What Changed

### Dashboard Batch 5-7
- SubNav bar system (new component + store module)
- Side menu restructure with module sub-navigation
- Full-width layout (removed max-w constraints from all dashboard views)
- Recommended Actions in hero (sourced from plans store)
- Retirement progress bars — bigger, bolder, horizon blue
- Net worth donut chart centred
- Login logo smaller + linked
- Onboarding merge conflict resolved (duplicate header removed)
- Light-pink add buttons across all module pages
- Card hover standardisation
- Risk Profile link/back button cleanup

### Files Changed (67 total)
All under `resources/js/` and `resources/css/` — frontend only.

## Deploy Steps

### 1. Build locally
```bash
./deploy/fynla-org/build.sh
```

### 2. Upload via SiteGround File Manager
Upload the entire `public/build/` directory to:
```
~/www/fynla.org/public_html/public/build/
```

Also upload if changed:
- `public/images/Fyn/Fyn-Icon.png` → `~/www/fynla.org/public_html/public/images/Fyn/`

### 3. SSH — Clear caches
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Warnings
- No migrations needed
- No new PHP dependencies
- No config changes
- No seeder updates required
