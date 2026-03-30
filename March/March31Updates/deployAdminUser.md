# Deploy Guide: Admin User Metrics Dashboard & Subscription Tier Update

**Date:** 31 March 2026
**Branch:** `adminUserView`
**Base commit:** `2a25396` (main)

---

## Overview

- New "Family" subscription tier (between Standard and Pro)
- Updated pricing with launch discounts (strikethrough full price + discounted price)
- New "User Metrics" admin tab with registration/trial/subscription/churn/revenue analytics

---

## Step 1: Build Frontend Locally

```bash
git checkout adminUserView
./deploy/fynla-org/build.sh
```

This builds `public/build/` with all frontend changes (pricing page, admin metrics components).

---

## Step 2: Upload PHP Files to Production

Upload via SiteGround File Manager to `~/www/fynla.org/public_html/`:

### New Files

| Local Path | Remote Path |
|-----------|-------------|
| `app/Http/Controllers/Api/UserMetricsController.php` | `app/Http/Controllers/Api/UserMetricsController.php` |
| `app/Services/Admin/UserMetricsService.php` | `app/Services/Admin/UserMetricsService.php` |
| `database/migrations/2026_03_30_000002_add_family_to_subscriptions_plan_enum.php` | `database/migrations/2026_03_30_000002_add_family_to_subscriptions_plan_enum.php` |
| `database/migrations/2026_03_30_000003_add_launch_price_columns_to_subscription_plans_table.php` | `database/migrations/2026_03_30_000003_add_launch_price_columns_to_subscription_plans_table.php` |

### Modified Files

| Local Path | Remote Path |
|-----------|-------------|
| `app/Models/SubscriptionPlan.php` | `app/Models/SubscriptionPlan.php` |
| `database/seeders/SubscriptionPlanSeeder.php` | `database/seeders/SubscriptionPlanSeeder.php` |
| `routes/api.php` | `routes/api.php` |

### Frontend Build

| Local Path | Remote Path |
|-----------|-------------|
| `public/build/` (entire directory) | `public/build/` |

---

## Step 3: SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
```

### Run Migrations

```bash
php artisan migrate
```

Expected output:
```
2026_03_30_000002_add_family_to_subscriptions_plan_enum .......... DONE
2026_03_30_000003_add_launch_price_columns_to_subscription_plans_table .. DONE
```

### Seed Updated Subscription Plans

```bash
php artisan db:seed --class=SubscriptionPlanSeeder --force
```

### Clear Caches

```bash
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Step 4: Verify on Production

### Pricing Page (https://fynla.org/pricing)

- [ ] Four plan cards visible: Student, Standard, Family, Pro
- [ ] "Launch Discount -- Limited Time" badge displayed
- [ ] Strikethrough full prices shown with discounted launch prices
- [ ] Monthly toggle: Student £3.99, Standard £10.99, Family £14.99, Pro £19.99
- [ ] Yearly toggle: Student £30, Standard £100, Family £150, Pro £200

### Admin User Metrics (https://fynla.org/admin > User Metrics tab)

- [ ] "User Metrics" tab appears in admin panel
- [ ] Snapshot cards load (Total Registered, Active Subscribers, On Trial, Never Paid)
- [ ] Trial Breakdown shows 6 buckets
- [ ] Plan Breakdown shows 4 plans (Student, Standard, Family, Pro)
- [ ] Period selector works (Day, Week, Month, Quarter, Year)
- [ ] Charts render (Registrations & Conversions, Revenue, Churn, Engagement)
- [ ] Activity data table renders below charts

---

## Rollback

If issues arise:

1. **Revert PHP files** -- restore previous versions from SiteGround backup
2. **Revert migrations** -- `php artisan migrate:rollback --step=2`
3. **Reseed plans** -- `php artisan db:seed --class=SubscriptionPlanSeeder --force`
4. **Rebuild frontend** -- checkout main, run `./deploy/fynla-org/build.sh`, re-upload `public/build/`
5. **Clear caches** -- `php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize`

---

## Files NOT to Upload (test/dev only)

- `database/factories/SubscriptionFactory.php` -- dev only
- `tests/Feature/Admin/UserMetricsControllerTest.php` -- dev only
- `tests/Unit/Services/Admin/UserMetricsServiceTest.php` -- dev only
- `docs/superpowers/` -- specs and plans, dev only
- `March/` -- update notes, dev only
