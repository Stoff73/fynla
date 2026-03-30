# Admin User Metrics Dashboard & Subscription Tier Update

**Date:** 2026-03-30
**Status:** Draft
**Branch:** adminUserView

---

## Overview

Two related changes:

1. **Admin User Metrics tab** — a new tab in the admin panel providing real-time analytics on registrations, trials, subscriptions, churn, revenue, and user engagement, with time-period filtering (day/week/month/quarter/year).
2. **Subscription tier update** — adding a new "Family" tier, updating pricing across all tiers with launch discount display, and updating the public pricing page.

---

## 1. Subscription Tier Changes

### New Tier Structure

| Tier | Monthly (Full) | Monthly (Launch) | Yearly (Full) | Yearly (Launch) | Trial |
|------|---------------|-----------------|---------------|-----------------|-------|
| Student | £4.99 | £3.99 | £45.00 | £30.00 | 7 days |
| Standard | £14.99 | £10.99 | £135.00 | £100.00 | 7 days |
| Family | £21.99 | £14.99 | £199.00 | £150.00 | 7 days |
| Pro | £29.99 | £19.99 | £269.99 | £200.00 | 7 days |

### Feature Access by Tier

Features excluded per tier (Pro has full access):

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
| Letter to Spouse / Expression of Wishes | Excluded | Included | Included | Included |

**Key difference between Standard and Family:** Family gains access to the Family module.

### Pricing Page Changes

- Show full price with strikethrough, launch discount price displayed prominently
- "Launch Discount" heading/badge visible on the pricing section
- Update the `subscription_plans` database table and seeder with new Family tier and updated prices
- All four tiers displayed on the public pricing page

### Backend Changes

- Add `family` to the plan enum across: `User.plan`, `Subscription.plan`, `SubscriptionPlan.slug`
- Update `SubscriptionPlanSeeder` with Family tier data and updated pricing for all tiers
- Add `launch_monthly_price` and `launch_yearly_price` fields to `subscription_plans` table (or store as separate config)
- Update `CheckSubscription` middleware to recognise the Family tier and its feature access

---

## 2. Admin User Metrics Dashboard

### Location

New tab "User Metrics" in the admin panel (`AdminPanel.vue`), positioned after the existing "Dashboard" tab. The existing Dashboard tab remains for system health (DB size, backups, table stats).

### Layout (Top to Bottom)

#### Section A: Snapshot Cards (4 cards, always current state)

| Card | Data Source | Sub-text |
|------|------------|----------|
| Total Registered | `users` count (excluding preview users) | +N this week/period |
| Active Subscribers | `subscriptions` where status = active | Conversion rate % |
| On Trial | `subscriptions` where status = trialing | "7-day free trial" |
| Never Paid | Users with no active/past subscription | % of total |

Clean white cards, `shadow-card`, `rounded-card` (12px), `horizon-500` text for values, `neutral-500` for labels. No accent bars or coloured borders.

#### Section B: Trial Status Breakdown (6 cards in a row)

| Bucket | Query |
|--------|-------|
| 4+ Days | `trial_ends_at` > now + 3 days |
| 3 Days | `trial_ends_at` between now + 2d and now + 3d |
| 2 Days | `trial_ends_at` between now + 1d and now + 2d |
| 1 Day | `trial_ends_at` between now and now + 1d |
| Expiring Today | `trial_ends_at` is today |
| Expired | status = trialing AND `trial_ends_at` < now, OR status = expired with no subsequent active subscription |

Same clean card style as snapshot cards.

#### Section C: Active Subscribers by Plan (4 cards)

One card per tier: Student, Standard, Family, Pro. Each shows:
- Total count
- Monthly vs yearly split
- Monthly recurring revenue for that tier

#### Section D: Time-Filtered Activity (controlled by period selector)

**Period selector:** Day / Week / Month / Quarter / Year — pill-style toggle, active state uses `raspberry-500`.

**Charts (2x2 grid):**

1. **Registrations & Conversions** — grouped bar chart (Light Blue for registrations, Spring Green for conversions)
2. **Revenue** — bar chart (Spring Green)
3. **Churn** — grouped bar chart (Raspberry 200 for trial expired, Raspberry 500 for cancellations)
4. **Non-Converters Engagement** — three stat boxes showing:
   - % who completed onboarding
   - % who used 1+ module
   - % who used 3+ modules

**Data table** below charts showing exact numbers per period:
- Columns: Period, Registrations, Conversions, Cancellations, Trial Expired, Revenue
- Conversions in Spring Green, Cancellations in Raspberry, Revenue bold
- Hover row highlight with `savannah-100`

### Non-Converter Tracking

For users who registered but didn't pay, track:
- **Completed onboarding:** `users.onboarding_completed = true`
- **Module engagement:** Query across module tables (protection policies, savings accounts, investments, retirement plans, etc.) to determine which modules a user entered data into
- Show as aggregate percentages in the engagement panel, and optionally as a drillable list

### API Endpoints

All under `Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin')`:

| Endpoint | Purpose |
|----------|---------|
| `GET /api/admin/user-metrics/snapshot` | Current totals (registered, active, trial, never-paid) |
| `GET /api/admin/user-metrics/trials` | Trial breakdown by days remaining |
| `GET /api/admin/user-metrics/plans` | Subscriber counts and revenue by plan |
| `GET /api/admin/user-metrics/activity?period=day&range=7` | Time-series data for charts/table |
| `GET /api/admin/user-metrics/engagement` | Non-converter engagement stats |

### Backend Service

New `App\Services\Admin\UserMetricsService` with methods:
- `getSnapshot(): array`
- `getTrialBreakdown(): array`
- `getPlanBreakdown(): array`
- `getActivity(string $period, int $range): array`
- `getEngagementStats(): array`

Queries exclude preview users (`is_preview_user = false`) throughout.

### Frontend Components

| Component | Purpose |
|-----------|---------|
| `UserMetrics.vue` | Tab container, period selector state, data fetching |
| `SnapshotCards.vue` | Four top-level metric cards |
| `TrialBreakdown.vue` | Six trial status cards |
| `PlanBreakdown.vue` | Four subscription plan cards |
| `ActivityCharts.vue` | Chart rendering (registrations, revenue, churn, engagement) |
| `ActivityTable.vue` | Tabular data below charts |

All in `resources/js/components/Admin/`.

### Chart Library

Use Chart.js (already in the project dependencies) with the Fynla chart colour palette from `designSystem.js`.

---

## Design Compliance

- All colours from `fynlaDesignGuide.md` v1.2.0 palette only
- No accent bars, coloured borders, or decorative elements on cards
- Clean white cards with `shadow-card` and `rounded-card`
- Font: Segoe UI, weight 900 for stat values, 700 for headings, 400 for body
- Background: `eggshell-500`
- Text: `horizon-500` primary, `neutral-500` secondary
- Success/positive: `spring-500`
- Negative/churn: `raspberry-500`
- Info: `violet-500`
- No amber, orange, or non-palette colours

---

## Out of Scope

- Email notifications for trial expiry (already exists via trial reminder system)
- User-facing analytics or dashboards
- Export to CSV/PDF (can be added later)
- Real-time WebSocket updates (standard page refresh / polling is sufficient)
