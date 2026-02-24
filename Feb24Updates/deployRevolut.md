# Feb 24 Updates — Revolut Subscriptions API Migration (Task 1)

## Summary

Migrated from Revolut Orders API v1.0 to the full Subscriptions API v2025-12-04. Centralised pricing in a database table (`subscription_plans`) instead of hardcoded constants. Added Revolut customer, subscription, plan, and billing cycle API methods. Created artisan command to sync plans with Revolut.

Branch: `revolut`

## What Changed

### RevolutService.php (Full Rewrite)
- Was: 2 methods (createOrder, getOrderStatus) using Orders API v1.0
- Now: 10 methods covering customers, orders, subscriptions, plans, billing cycles
- Dual URL structure: `/api/1.0/` for customers/orders, `/api/` for subscriptions with `Revolut-Api-Version: 2025-12-04` header
- Extracted private `request()` helper (eliminated 9x repeated HTTP/error/throw boilerplate)
- Pricing now comes from `SubscriptionPlan::findBySlug()` instead of hardcoded values
- Throws `InvalidArgumentException` on unknown plan (was silently sending amount:0)

### TrialService.php (Modified)
- Removed hardcoded `PLAN_PRICING` and `TRIAL_DAYS` constants
- Now uses `SubscriptionPlan::findBySlug($plan)` for pricing and trial days
- Throws `InvalidArgumentException` on missing plan (was silently defaulting to 0/7)
- `expireTrials()` optimised from N+1 to 2 bulk queries

### Subscription Model (Modified)
- Added `revolut_subscription_id` to `$fillable`
- Fixed `amount` cast from `float` to `integer` (stores pence as integers)

### User Model (Modified)
- Added `revolut_customer_id` to `$hidden` array (sensitive external ID)

### SubscriptionFactory (Modified)
- Fixed pricing to match canonical values: student 399/3000, standard 1099/10000, pro 1999/20000
- Changed `$this->faker` to `fake()` per project conventions

## New Files Created

| File | Purpose |
|------|---------|
| `database/migrations/2026_02_24_100001_create_subscription_plans_table.php` | New table: slug, name, monthly/yearly price, trial_days, is_active, features (JSON), sort_order, revolut_plan_id, revolut_monthly/yearly_variation_id |
| `database/migrations/2026_02_24_100002_add_revolut_ids_to_users_and_subscriptions.php` | Adds `revolut_customer_id` (users), `revolut_subscription_id` (subscriptions) |
| `app/Models/SubscriptionPlan.php` | Database-backed pricing model: `findBySlug()`, `getPriceForCycle()`, `getVariationIdForCycle()`, `scopeActive()` |
| `database/seeders/SubscriptionPlanSeeder.php` | Seeds 3 plans (student/standard/pro) with `updateOrCreate` idempotency |
| `app/Console/Commands/SeedRevolutPlans.php` | `revolut:seed-plans` — creates plans at Revolut API, stores variation IDs locally |

## Files to Upload (when deploying)

### New files (create these on server)
```
app/Models/SubscriptionPlan.php
app/Console/Commands/SeedRevolutPlans.php
database/migrations/2026_02_24_100001_create_subscription_plans_table.php
database/migrations/2026_02_24_100002_add_revolut_ids_to_users_and_subscriptions.php
database/seeders/SubscriptionPlanSeeder.php
```

### Modified files (replace on server)
```
app/Services/Payment/RevolutService.php
app/Services/Payment/TrialService.php
app/Models/Subscription.php
app/Models/User.php
database/seeders/DatabaseSeeder.php
database/factories/SubscriptionFactory.php
```

### Post-upload commands (SSH)
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migrations
php artisan migrate --force

# Seed subscription plans
php artisan db:seed --class=SubscriptionPlanSeeder --force

# Sync plans with Revolut API (creates plans + stores variation IDs)
php artisan revolut:seed-plans

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize
```

## Key Design Decisions

| Decision | Choice | Why |
|----------|--------|-----|
| Pricing storage | Database table (`subscription_plans`) | Supports price changes, discounts, referral programmes without deploys |
| Trial management | App-managed, not Revolut-managed | 7-day trial starts at registration without payment; Revolut subscription created with `trial_duration: "P0D"` at conversion |
| Customer creation | Lazy (at checkout) | No Revolut API calls during registration flow |
| API structure | Dual URL + header helpers | v1 endpoints need `/api/1.0/`, subscription endpoints need `/api/` + version header |

## Pricing (stored in subscription_plans table)

| Plan | Monthly | Yearly |
|------|---------|--------|
| Student | £3.99 (399p) | £30.00 (3000p) |
| Standard | £10.99 (1099p) | £100.00 (10000p) |
| Pro | £19.99 (1999p) | £200.00 (20000p) |

All plans: 7-day trial, is_active = true
