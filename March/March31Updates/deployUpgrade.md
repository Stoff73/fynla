# Deploy Guide — Dashboard UI + Subscription & Upgrade Logic

**Date:** 31 March 2026
**Commits:** 12 (c426a0c → d8372bd)

## Changes Summary

### Dashboard UI
1. **Preview banner** — moved above SubNavBar so it stays directly below the top nav on all module screens
2. **Session timeout** — await preview store init before session lifecycle, so persona users no longer get 15-minute inactivity timeout
3. **Fyn chat icon** — centred in top navbar, opens docked chat panel on click (hidden in preview mode)
4. **Countdown timer** — moved from inline nav to its own horizon-500 bar below the nav
5. **Admin button** — removed from top nav (accessible via sidebar)
6. **Sign Up Now** — shown in navbar and sidebar for preview users, links to /register

### Subscription & Upgrade Logic
7. **Free trial** — "Free trial ends in X days" + "Choose a Plan" button. No plan name, no billing cycle, no price shown during trial
8. **Trial expiry modal** — non-dismissable plan selection overlay when trial expires. User can see data (read-only GET access) but must choose a plan. Title: "Your Trial Has Ended"
9. **Upgrade button** — shown for active Student/Standard/Family subscribers as "Upgrade Now". Hidden for Pro. Opens modal filtered to plans above current tier. Title: "Upgrade Your Plan"
10. **Launch pricing** — PlanSelectionModal shows strikethrough original price with raspberry launch price. "Launch Discount — First 500 Users" banner. "Most Popular" badge on Family
11. **Checkout flow** — Revolut order uses launch price from DB. CheckoutPage displays launch price in order summary. Confirm retries up to 5 times with 2s delay for Revolut state settlement. Backend accepts `pending` state.
12. **Single source of truth** — all pricing from `subscription_plans` DB table via `/api/payment/plans` API (includes launch prices). No hardcoded prices
13. **CheckSubscription middleware** — allows read-only GET access for expired users (data visible behind modal)
14. **Subscription data** — fetched once in AppLayout, passed as props to Navbar and SideMenu. Re-fetches on route change so UI updates after payment
15. **PlanSelectionModal ownership** — modal lives in AppLayout only. Navbar and SideMenu emit `open-plan-modal` event. Prevents modal rendering inside sidebar DOM
16. **Admin metrics guard** — ActivityCharts/UserMetrics guard against non-array activity data
17. **Family plan validation** — added `family` to createOrder plan validation (was missing, would have 422'd)

### Pricing Page (deployed earlier in session)
18. **Discount prices** — raspberry-500 colour matching launch discount banner
19. **Banner text** — "First 500 Users" (was "Limited Time")
20. **Most Popular** — moved from Standard to Family

## Browser Tested States

| State | Nav | Sidebar | Modal | Result |
|-------|-----|---------|-------|--------|
| Free trial | "Free trial ends in X days" + "Choose a Plan" | "Choose a Plan" | All 4 plans | PASS |
| Active Standard | "Upgrade Now" | "Upgrade Now" | Family + Pro only | PASS |
| Active Pro | No upgrade buttons | No upgrade button | N/A | PASS |
| Trial expired | No upgrade/trial | Shows sidebar | Non-dismissable, all 4 plans, "Your Trial Has Ended" | PASS |

## Files to Upload

### PHP Files (2)

```
app/Http/Controllers/Api/PaymentController.php
app/Http/Middleware/CheckSubscription.php
```

### Frontend Build

```
public/build/ → ~/www/fynla.org/public_html/public/build/
```

Build with `./deploy/fynla-org/build.sh` before uploading.

## Deployment Steps

### 1. Build

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload via SiteGround File Manager

**Frontend:**
```
public/build/ → ~/www/fynla.org/public_html/public/build/
```

**PHP files:**
```
app/Http/Controllers/Api/PaymentController.php → ~/www/fynla.org/public_html/app/Http/Controllers/Api/PaymentController.php
app/Http/Middleware/CheckSubscription.php → ~/www/fynla.org/public_html/app/Http/Middleware/CheckSubscription.php
```

### 3. Clear Caches (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## No Database Changes

No migrations or seeders required. Launch prices and Family tier already exist in `subscription_plans` table from previous deploy.

## Admin Setup

To add new admin users, update `.env` on the server:
```
ADMIN_EMAILS=chris@fynla.org,brett@fynla.org,azlan@fynla.org
```
Then `php artisan config:clear && php artisan optimize`. Admin role is auto-assigned on next login.

## Production Subscription Fix

If `chris@fynla.org` subscription status is still `trialing` on production after payment, fix via SSH:
```bash
php artisan tinker --execute="\$u = \App\Models\User::where('email','chris@fynla.org')->first(); \$s = \$u->subscription; \$s->status = 'active'; \$s->save(); echo 'Done';"
```
