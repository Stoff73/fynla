# Deploy Guide — Subscription & Upgrade Logic

**Branch:** `dashboardUI`
**Date:** 31 March 2026

## Changes Summary

1. **Trial expiry modal** — non-dismissable plan selection overlay when trial expires, user can see data but must choose a plan
2. **Upgrade button logic** — hidden for Pro subscribers; opens plan selection modal (not /pricing) for Student/Standard/Family
3. **Launch pricing in modal** — PlanSelectionModal now shows strikethrough original + raspberry launch price, matching /pricing page
4. **Single source of truth** — pricing comes from DB via `/api/payment/plans` (includes launch prices)
5. **Preview mode** — "Sign Up Now" links to /register in both navbar and sidebar

## Files Changed

| File | Type | Change |
|------|------|--------|
| `app/Http/Controllers/Api/PaymentController.php` | PHP | Added `launch_monthly_price` and `launch_yearly_price` to plans endpoint |
| `resources/js/components/Payment/PlanSelectionModal.vue` | Vue | `currentPlan` prop (filters tiers), `dismissable` prop, launch pricing display |
| `resources/js/components/Navbar.vue` | Vue | Upgrade opens modal with currentPlan, hidden for Pro, preview shows Sign Up |
| `resources/js/components/SideMenu.vue` | Vue | Same upgrade logic as Navbar, fetches subscription data |
| `resources/js/layouts/AppLayout.vue` | Vue | Non-dismissable modal on trial expiry |

## Deployment Steps

### 1. Build

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload via SiteGround File Manager

**Frontend build:**
```
public/build/ → ~/www/fynla.org/public_html/public/build/
```

**PHP file:**
```
app/Http/Controllers/Api/PaymentController.php → ~/www/fynla.org/public_html/app/Http/Controllers/Api/PaymentController.php
```

### 3. Clear Caches (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## No Database Changes

No migrations or seeders required. Launch prices already exist in `subscription_plans` table from previous deploy.
