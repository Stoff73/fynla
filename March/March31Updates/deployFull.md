# Complete Deploy Guide — Admin Metrics + Subscription Upgrade + Dashboard UI

**Date:** 31 March 2026
**Commits:** 33 (2a25396 → 8f0542a)
**Replaces:** `deployUpgrade.md` (incomplete — listed only 2 of 10 PHP files, missed migrations and seeders)

## What's Broken on Production

The previous deploy guide was incomplete. Production is 33 commits behind main. The errors:

- **429 Too Many Requests** on `trial-status` and `billing-history` — frontend code expects updated subscription logic from `CheckSubscription.php` and `AppLayout.vue`. Without it, the old code loops.
- **403 on analyze endpoints** — `CheckSubscription` middleware on production doesn't allow read-only GET access for expired/trial users (the fix is in the diff).
- **searchAnalyzer.js "Search engine null"** — separate issue, likely a stale build asset.

## Changes Summary

### Admin User Metrics (new feature)
- 5 API endpoints: snapshot, trials, plans, activity, engagement
- New controller, service, and 6 Vue components
- Admin panel tab for user analytics

### Subscription & Upgrade Logic
- Free trial UI ("Free trial ends in X days" + "Choose a Plan")
- Trial expiry modal (non-dismissable plan selection)
- Upgrade button for paid subscribers (filtered to higher tiers)
- Launch discount pricing (strikethrough + raspberry launch price)
- CheckSubscription middleware — read-only GET access for expired users
- Single fetch in AppLayout, passed as props (not multiple fetch calls)
- Checkout flow with Revolut retry logic for pending state

### Dashboard UI
- Preview banner above SubNavBar
- Session timeout fix for preview users
- Fyn chat icon centred in navbar
- Countdown timer moved to own bar below nav
- Admin button removed from nav (sidebar only)
- Sign Up Now for preview users

### Database
- `family` added to subscriptions plan ENUM
- `launch_monthly_price` and `launch_yearly_price` columns on `subscription_plans`
- Updated seeder with 4 tiers + launch pricing

---

## Files to Upload

### PHP Files (10)

**New files (create on server):**
```
app/Http/Controllers/Api/UserMetricsController.php
app/Services/Admin/UserMetricsService.php
database/migrations/2026_03_30_000002_add_family_to_subscriptions_plan_enum.php
database/migrations/2026_03_30_000003_add_launch_price_columns_to_subscription_plans_table.php
```

**Modified files (overwrite on server):**
```
app/Http/Controllers/Api/PaymentController.php
app/Http/Middleware/CheckSubscription.php
app/Models/SubscriptionPlan.php
database/seeders/SubscriptionPlanSeeder.php
routes/api.php
database/factories/SubscriptionFactory.php
```

**Server paths:**
```
app/Http/Controllers/Api/UserMetricsController.php → ~/www/fynla.org/public_html/app/Http/Controllers/Api/UserMetricsController.php
app/Http/Controllers/Api/PaymentController.php     → ~/www/fynla.org/public_html/app/Http/Controllers/Api/PaymentController.php
app/Http/Middleware/CheckSubscription.php           → ~/www/fynla.org/public_html/app/Http/Middleware/CheckSubscription.php
app/Models/SubscriptionPlan.php                     → ~/www/fynla.org/public_html/app/Models/SubscriptionPlan.php
app/Services/Admin/UserMetricsService.php           → ~/www/fynla.org/public_html/app/Services/Admin/UserMetricsService.php
database/migrations/2026_03_30_000002_add_family_to_subscriptions_plan_enum.php → ~/www/fynla.org/public_html/database/migrations/
database/migrations/2026_03_30_000003_add_launch_price_columns_to_subscription_plans_table.php → ~/www/fynla.org/public_html/database/migrations/
database/seeders/SubscriptionPlanSeeder.php         → ~/www/fynla.org/public_html/database/seeders/SubscriptionPlanSeeder.php
database/factories/SubscriptionFactory.php          → ~/www/fynla.org/public_html/database/factories/SubscriptionFactory.php
routes/api.php                                      → ~/www/fynla.org/public_html/routes/api.php
```

**Note:** Create the `app/Services/Admin/` directory on the server if it doesn't exist.

### Frontend Build

```
public/build/ → ~/www/fynla.org/public_html/public/build/
```

---

## Deployment Steps

### 1. Build frontend locally

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload via SiteGround File Manager

Upload all 10 PHP files listed above, then the `public/build/` directory.

### 3. SSH — Run migrations, seed, and clear caches

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Create directory for new service
mkdir -p app/Services/Admin

# Run the 2 new migrations
php artisan migrate

# Seed the subscription plans (adds Family tier + launch prices)
php artisan db:seed --class=SubscriptionPlanSeeder --force

# Clear all caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

### 4. Fix chris@fynla.org subscription if still stuck

If the subscription status is still `trialing` after payment:
```bash
php artisan tinker --execute="\$u = \App\Models\User::where('email','chris@fynla.org')->first(); \$s = \$u->subscription; \$s->status = 'active'; \$s->save(); echo 'Done';"
```

---

## Database Changes (REQUIRED)

| Migration | What it does |
|-----------|-------------|
| `2026_03_30_000002` | Adds `family` to the subscriptions `plan` ENUM |
| `2026_03_30_000003` | Adds `launch_monthly_price` and `launch_yearly_price` columns to `subscription_plans` |

**Seeder:** `SubscriptionPlanSeeder` must run after migrations to populate Family tier and launch prices for all 4 plans.

---

## Why the Previous Guide Was Wrong

`deployUpgrade.md` listed only 2 PHP files (`PaymentController.php` and `CheckSubscription.php`) and stated "No Database Changes". In reality, 33 commits introduced:
- 4 new PHP files
- 6 modified PHP files
- 2 database migrations
- 1 seeder update
- 16 new/modified Vue components (in the build)

The guide was generated from only the subscription-specific commits, not the full diff from production state.
