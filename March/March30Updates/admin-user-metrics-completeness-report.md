# Admin User Metrics Dashboard & Subscription Tier Update — Completeness Report

**Date:** 30 March 2026
**Branch:** `adminUserView`
**Spec:** `docs/superpowers/specs/2026-03-30-admin-user-metrics-design.md`
**Plan:** `docs/superpowers/plans/2026-03-30-admin-user-metrics.md`

---

## Summary

Two features implemented:
1. **New "Family" subscription tier** with updated pricing across all tiers, including launch discount display on the public pricing page
2. **Admin "User Metrics" tab** with real-time analytics on registrations, trials, subscriptions, churn, revenue, and user engagement

---

## Implementation — Files Changed

### Backend (New)

| File | Purpose |
|------|---------|
| `database/migrations/2026_03_30_000002_add_family_to_subscriptions_plan_enum.php` | Adds `family` to plan ENUM on subscriptions table |
| `database/migrations/2026_03_30_000003_add_launch_price_columns_to_subscription_plans_table.php` | Adds `launch_monthly_price` and `launch_yearly_price` columns |
| `app/Services/Admin/UserMetricsService.php` | Five analytics query methods (getSnapshot, getTrialBreakdown, getPlanBreakdown, getActivity, getEngagementStats) |
| `app/Http/Controllers/Api/UserMetricsController.php` | Five API endpoints under `/api/admin/user-metrics/` |

### Backend (Modified)

| File | Change |
|------|--------|
| `app/Models/SubscriptionPlan.php` | Added launch price fields to fillable/casts, added `getLaunchPriceForCycle()` |
| `database/seeders/SubscriptionPlanSeeder.php` | Added Family tier, updated all pricing (full + launch) |
| `database/factories/SubscriptionFactory.php` | Added `family` plan, new state methods |
| `routes/api.php` | Added 5 user-metrics routes in admin group |

### Frontend (New)

| File | Purpose |
|------|---------|
| `resources/js/components/Admin/metrics/SnapshotCards.vue` | 4 snapshot metric cards |
| `resources/js/components/Admin/metrics/TrialBreakdown.vue` | 6 trial status cards |
| `resources/js/components/Admin/metrics/PlanBreakdown.vue` | 4 subscription plan cards |
| `resources/js/components/Admin/metrics/ActivityCharts.vue` | 4 ApexCharts (registrations, revenue, churn, engagement) |
| `resources/js/components/Admin/metrics/ActivityTable.vue` | Activity data table |
| `resources/js/components/Admin/metrics/UserMetrics.vue` | Main tab container with period selector |

### Frontend (Modified)

| File | Change |
|------|--------|
| `resources/js/services/adminService.js` | Added 5 `getUserMetrics*()` API methods |
| `resources/js/views/Admin/AdminPanel.vue` | Added "User Metrics" tab with icon |
| `resources/js/views/Public/PricingPage.vue` | Added Family tier, launch discount strikethrough pricing, 4-column grid |

### Tests (New)

| File | Tests |
|------|-------|
| `tests/Unit/Services/Admin/UserMetricsServiceTest.php` | 20 unit tests |
| `tests/Feature/Admin/UserMetricsControllerTest.php` | 14 feature tests |

---

## Test Results

### Pest Tests — 34/34 PASS

```
PASS  Tests\Unit\Services\Admin\UserMetricsServiceTest
  getSnapshot: 4 tests (preview exclusion, active counting, trial counting, never-paid)
  getTrialBreakdown: 3 tests (bucket accuracy, expired counting, preview exclusion)
  getPlanBreakdown: 3 tests (grouping, all 4 plan types, preview exclusion)
  getActivity: 5 tests (bucket count, registrations, preview exclusion, conversions/cancellations, period types)
  getEngagementStats: 5 tests (onboarding %, module usage, active exclusion, empty state, preview exclusion)

PASS  Tests\Feature\Admin\UserMetricsControllerTest
  snapshot: 3 tests (admin 200, non-admin 403, unauth 401)
  trials: 2 tests (admin 200, non-admin 403)
  plans: 2 tests (admin 200, non-admin 403)
  activity: 4 tests (default params, custom params, invalid period 422, range clamping)
  engagement: 2 tests (admin 200, non-admin 403)
  auth: 1 test (unauthenticated 401)

Tests: 34 passed (158 assertions)
```

### Browser Tests — All Verified in Playwright

#### Admin User Metrics Tab
- [x] Logged in as admin@fps.com (with verification code from DB)
- [x] Navigated to Admin Panel
- [x] "User Metrics" tab visible in tab bar
- [x] Clicked "User Metrics" tab — loaded without errors
- [x] Snapshot cards displayed: Total Registered (1), Active Subscribers (0), On Trial (0), Never Paid (1)
- [x] Trial Breakdown: 6 cards (4+ Days, 3 Days, 2 Days, 1 Day, Expiring Today, Expired) — all showing 0
- [x] Plan Breakdown: 4 cards (Student, Standard, Family, Pro) with monthly/yearly split
- [x] Period selector: Day (active), Week, Month, Quarter, Year buttons rendered
- [x] Charts: Registrations & Conversions, Revenue, Churn, Engagement — all rendered with ApexCharts
- [x] Activity table: 7 rows (24-30 Mar), 30 Mar shows 1 registration
- [x] Clicked "Month" period button — charts and table updated to monthly view (Oct 2025 - Mar 2026)
- [x] Screenshot captured: `admin-user-metrics.png`

#### Public Pricing Page
- [x] Navigated to /pricing
- [x] "Launch Discount — Limited Time" badge visible
- [x] 4 plan cards displayed: Student, Standard (Most Popular), Family, Pro
- [x] Yearly view (default): correct strikethrough full prices and launch prices
  - Student: ~~£45.00~~ £30.00/year
  - Standard: ~~£135.00~~ £100.00/year
  - Family: ~~£199.00~~ £150.00/year
  - Pro: ~~£269.99~~ £200.00/year
- [x] Clicked "Monthly" toggle — prices updated correctly
  - Student: ~~£4.99~~ £3.99/month
  - Standard: ~~£14.99~~ £10.99/month
  - Family: ~~£21.99~~ £14.99/month
  - Pro: ~~£29.99~~ £19.99/month
- [x] Feature lists correct per tier
- [x] FAQ updated to cover all 4 tiers
- [x] Screenshot captured: `pricing-page-monthly.png`

---

## Subscription Tier Verification

### Database Verified

| Plan | monthly_price | launch_monthly | yearly_price | launch_yearly |
|------|--------------|---------------|-------------|--------------|
| student | 499 | 399 | 4500 | 3000 |
| standard | 1499 | 1099 | 13500 | 10000 |
| family | 2199 | 1499 | 19900 | 15000 |
| pro | 2999 | 1999 | 26999 | 20000 |

### Feature Access per Tier

| Feature | Student | Standard | Family | Pro |
|---------|---------|----------|--------|-----|
| Estate Planning | Excluded | Excluded | Excluded | Included |
| Holistic Plan | Excluded | Excluded | Excluded | Included |
| Wills | Excluded | Excluded | Excluded | Included |
| Powers | Excluded | Excluded | Excluded | Included |
| Trusts | Excluded | Excluded | Excluded | Included |
| Family | Excluded | Excluded | Included | Included |
| Personal Valuables | Excluded | Included | Included | Included |
| Business | Excluded | Included | Included | Included |
| Property | Excluded | Included | Included | Included |
| Letter to Spouse | Excluded | Included | Included | Included |

---

## API Endpoints

All protected by `auth:sanctum` + `permission:admin.access`:

| Endpoint | Response |
|----------|----------|
| `GET /api/admin/user-metrics/snapshot` | `{total_registered, active_subscribers, on_trial, never_paid}` |
| `GET /api/admin/user-metrics/trials` | `{four_plus_days, three_days, two_days, one_day, expiring_today, expired}` |
| `GET /api/admin/user-metrics/plans` | `[{plan, total, monthly, yearly, monthly_revenue, yearly_revenue}]` |
| `GET /api/admin/user-metrics/activity?period=day&range=7` | `[{period, label, registrations, conversions, cancellations, trial_expired, revenue}]` |
| `GET /api/admin/user-metrics/engagement` | `{total, onboarding_completed_pct, used_one_plus_modules_pct, used_three_plus_modules_pct}` |

---

## Design System Compliance

- All colours from fynlaDesignGuide.md v1.2.0 palette
- No accent bars or coloured borders on cards
- Clean white cards with shadow-card and rounded-card
- Font: Segoe UI, weight 900 for stats, 700 for headings
- Chart colours from `designSystem.js` CHART_COLORS array
- No amber, orange, or non-palette colours
- British spelling in user-facing text
- No acronyms in user-facing text
- Standard spinner pattern used

---

## Fixes Applied During Implementation

1. **getActivity boundary bug** — The original boundary calculation excluded the current period (records created "now" fell outside the last bucket). Fixed by capping the final boundary at `now()->addSecond()` instead of the start of the next period.

2. **Module table names** — Plan specified `protection_policies`, `investments`, `retirement_plans` but actual table names are `life_insurance_policies`, `investment_accounts`, `dc_pensions`. Service uses the correct actual table names.

---

## Deployment Notes

### Files to Upload

**PHP files:**
- `app/Services/Admin/UserMetricsService.php`
- `app/Http/Controllers/Api/UserMetricsController.php`
- `app/Models/SubscriptionPlan.php`
- `database/migrations/2026_03_30_000002_add_family_to_subscriptions_plan_enum.php`
- `database/migrations/2026_03_30_000003_add_launch_price_columns_to_subscription_plans_table.php`
- `database/seeders/SubscriptionPlanSeeder.php`
- `database/factories/SubscriptionFactory.php`
- `routes/api.php`

**Frontend (build required):**
- `resources/js/components/Admin/metrics/` (6 files)
- `resources/js/services/adminService.js`
- `resources/js/views/Admin/AdminPanel.vue`
- `resources/js/views/Public/PricingPage.vue`

### SSH Commands After Upload

```bash
php artisan migrate
php artisan db:seed --class=SubscriptionPlanSeeder --force
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```
