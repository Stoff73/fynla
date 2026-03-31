# Deploy Guide — Feature Gating

**Date:** 31 March 2026
**Branch:** `productionReview`
**Commits:** `4568e5c`, `67cc5e7`
**Status:** DEPLOYED — production tested and verified

---

## Pre-Deploy Checklist

- [x] 10/10 Pest tests pass
- [x] Vite compiles without errors
- [x] Browser tested locally: greyed items, tooltips, upgrade link, preview bypass, trial bypass
- [x] Production build (`./deploy/fynla-org/build.sh`)
- [x] Upload PHP files (SSH upload + scp)
- [x] Upload frontend build (scp)
- [x] Clear caches
- [x] Production browser tested: student tier gating, tooltip, pro tier full access

---

## Step 1: Build Frontend

```bash
./deploy/fynla-org/build.sh
```

---

## Step 2: Upload Files

### New PHP Files
```
app/Http/Middleware/CheckFeatureAccess.php
```

### Modified PHP Files
```
app/Http/Kernel.php
app/Models/Subscription.php
routes/api.php
```

### Frontend Build
```
public/build/                    (entire directory — REPLACE, do not nest)
```

### Files NOT to upload (tests/docs only)
```
tests/Feature/Middleware/CheckFeatureAccessTest.php
March/March31Updates/*
CSJTODO.md
resources/js/*                   (compiled into public/build/)
```

---

## Step 3: SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Clear all caches (required — new middleware registered in Kernel.php)
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Step 4: Verify

```bash
# Verify new middleware is registered
php artisan route:list --path=properties | head -3

# Verify feature gating code in build
grep -o "isLocked\|requiredPlan" public/build/assets/AppLayout*.js | head -5

# Verify routes compile
php artisan route:list --json 2>&1 | head -3
```

---

## What Changed

| Area | Change |
|------|--------|
| **Backend** | New `CheckFeatureAccess` middleware gates API routes by tier |
| **Routes** | `feature:standard` on properties, mortgages, business, chattels, what-if, letter-to-spouse write |
| **Routes** | `feature:family` on family member POST/PUT/DELETE |
| **Routes** | `feature:pro` on estate (all), holistic planning |
| **Frontend** | Sidebar greyed items with hover tooltip for locked features |
| **Frontend** | Router guard redirects gated URLs to dashboard |
| **Model** | Subscription.php fillable ordering synced (no functional change) |

---

## No Database Changes

No migrations. No seeders. Uses existing `subscription.plan` field.

---

## Issues During Deployment

### Issue 1: First frontend build did not contain feature gating code

**Symptom:** After uploading the first build, all sidebar items showed as clickable links on production. `grep -r "isLocked" public/build/assets/*.js` returned nothing.

**Cause:** The build was run before the tooltip fix commit (`67cc5e7`). The Vite dev server had died during testing, and the production build script compiled from stale cached assets that didn't include the new `SideMenuItem.vue` changes.

**Fix:** Rebuilt with `./deploy/fynla-org/build.sh` after confirming the dev server had recompiled. Verified the new build contained `isLocked` and `requiredPlan` strings in the output JS.

### Issue 2: scp created nested `public/build/build/` directory

**Symptom:** After re-uploading the frontend build via `scp -r public/build/ ...public/build/`, production was still serving the old build. The new files were at `public/build/build/assets/` instead of `public/build/assets/`.

**Cause:** `scp -r public/build/ target:public/build/` copies the `build` directory INTO the target `build` directory, creating a nested structure.

**Fix:** On production SSH:
```bash
rm -rf public/build/assets public/build/manifest.json public/build/manifest.webmanifest public/build/registerSW.js public/build/sw.js
mv public/build/build/* public/build/
rm -rf public/build/build
```
See `patchGate.md` for the exact commands run.

### Issue 3: Tooltip clipped by sidebar overflow (caught during local testing)

**Symptom:** CSS-only tooltip (`group-hover:opacity-100` with `absolute left-full`) was invisible on hover.

**Cause:** The sidebar's scrollable container (`overflow-y-auto`) clips content extending beyond its bounds. CSS cannot have `overflow-y: auto` with `overflow-x: visible`.

**Fix:** Changed to `<Teleport to="body">` with JavaScript-calculated `position: fixed` coordinates from `getBoundingClientRect()`. Commit `67cc5e7`.

---

## Production Test Results

Tested as chris@fynla.org with subscription temporarily set to `student/active`:

| Test | Result |
|------|--------|
| 11 sidebar items greyed (student tier) | PASS — screenshot confirmed |
| Tooltip on hover ("Available on Standard/Pro plan") | PASS — screenshot confirmed |
| "Upgrade now" link in tooltip | PASS — visible in screenshot |
| Pro tier — all items accessible | PASS — restored to pro, all items dark/clickable |
| Backend API returns correct plan | PASS — `/api/payment/trial-status` returned `plan: student` |
| Build contains gating code | PASS — `isLocked` x12, `requiredPlan` x13 in AppLayout JS |

chris@fynla.org restored to `pro/active` after testing.

---

## Rollback

If issues arise, revert to previous files:
- Restore previous `Kernel.php` (remove `'feature'` alias line)
- Restore previous `routes/api.php` (remove `'feature:X'` from middleware arrays)
- Delete `CheckFeatureAccess.php`
- Restore previous frontend build
- Clear caches
