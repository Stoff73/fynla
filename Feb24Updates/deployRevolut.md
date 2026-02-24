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

---

## Task 2: Fix Checkout Page

### Summary

Rewrote the checkout page to use the Revolut Subscriptions API with three switchable widget modes (popup, embedded, card_field). Added security fixes: IDOR protection, race condition prevention, CSP whitelisting, rate limiting, and preview user guards.

### What Changed

#### PaymentController.php (Major Rewrite)
- New `subscribe()` endpoint: lazily creates Revolut customer (with DB lock to prevent duplicates), creates Revolut subscription, returns `setup_order_id`
- Subscription deduplication: returns existing `setup_order_id` if subscription already created
- Preview user guard: returns 403 for preview users on payment endpoints
- `orderStatus()` rewritten: no longer accepts arbitrary order ID — uses authenticated user's own subscription order
- Filtered responses: only returns fields the frontend needs (no internal Revolut IDs leaked)
- `trialStatus()` now includes `checkout.mode` and `checkout.sandbox` for frontend widget config

#### CheckoutPage.vue (Full Rewrite)
- Three checkout modes switchable via `REVOLUT_CHECKOUT_MODE` env var:
  - `popup` — Revolut-branded modal overlay (card + Apple Pay + Google Pay)
  - `embedded` — Full payment widget inline on page (card + wallets)
  - `card_field` — Card-only input fields with custom submit button
- Calls `POST /payment/subscribe` instead of old `POST /payment/create-order`
- SDK polling: waits up to 5 seconds for Revolut SDK to load (deferred script)
- Proper widget cleanup on unmount
- Uses `currencyMixin` (Rule 6 compliance)
- Uses `logger` utility instead of `console.error` (no PII in production console)

#### app.blade.php (Modified)
- Added Revolut Checkout SDK script tag (conditional on `PAYMENT_ENABLED`)
- Sandbox/production CDN URL selected dynamically
- `crossorigin="anonymous"` attribute for CORS error reporting

#### SecurityHeaders.php (Modified)
- CSP updated: Revolut merchant and checkout domains whitelisted in `script-src`, `connect-src`, `frame-src`
- Only added when `PAYMENT_ENABLED=true` (zero impact when payments off)
- Permissions-Policy: `payment=(self)` when payments enabled (required for Apple Pay/Google Pay)

#### config/services.php (Modified)
- Added `public_key` for Revolut frontend SDK
- Added `checkout_mode` (popup/embedded/card_field) switchable via `REVOLUT_CHECKOUT_MODE` env var

#### routes/api.php (Modified)
- New route: `POST /payment/subscribe` with `throttle:3,1`
- Rate limiting: `throttle:3,1` on `create-order` and `subscribe`
- Changed `GET /order/{id}/status` to `GET /order-status` (no user-supplied ID)

#### Payment.php (Modified)
- Fixed `amount` cast from `float` to `integer` (pence as integers, matching Subscription model)

### New .env Variables

```
REVOLUT_PUBLIC_KEY=          # Revolut public key (for future frontend SDK use)
REVOLUT_CHECKOUT_MODE=popup  # Options: popup, embedded, card_field
```

### Files to Upload (Task 2 additions)

#### Modified files (replace on server)
```
app/Http/Controllers/Api/PaymentController.php
app/Http/Middleware/SecurityHeaders.php
app/Models/Payment.php
config/services.php
routes/api.php
resources/views/app.blade.php
resources/js/views/Auth/CheckoutPage.vue
```

### Security Fixes Applied

| Issue | Fix |
|-------|-----|
| IDOR on order status | Removed `{id}` param, uses authenticated user's own order |
| Duplicate Revolut customers | DB lock (`lockForUpdate`) in transaction |
| Duplicate Revolut subscriptions | Returns existing `setup_order_id` if already created |
| CSP blocks Revolut SDK | Whitelisted merchant + checkout domains |
| No rate limiting on payments | `throttle:3,1` on subscribe and create-order |
| Preview user can trigger API calls | Explicit 403 guard in controller |
| Raw error objects in console | Uses `logger` utility (dev-only logging) |
| Payment amount as float | Cast to integer (pence) |
| Payment API permission blocked | `payment=(self)` in Permissions-Policy |

---

## Task 3: Webhook Handling Updates

### Summary

Updated webhook handling to support all 6 Revolut event types (order + subscription events), rewrote signature verification to use Revolut's `v1=<sig>,t=<timestamp>` format with replay protection, added webhook registration artisan command, and fixed access control for cancelled/overdue subscriptions.

### What Changed

#### PaymentWebhookController.php (Major Rewrite)
- 6 event handlers: `ORDER_COMPLETED`, `ORDER_PAYMENT_FAILED`, `SUBSCRIPTION_INITIATED`, `SUBSCRIPTION_FINISHED`, `SUBSCRIPTION_CANCELLED`, `SUBSCRIPTION_OVERDUE`
- Signature verification: parses `v1=<signature>,t=<timestamp>` header format with HMAC-SHA256
- 5-minute replay protection: rejects webhooks with timestamps older than 300 seconds
- Removed legacy plain-HMAC fallback (security fix: defeats replay protection)
- Timestamp must be > 0 (prevents bypassing replay check with malformed headers)
- `SUBSCRIPTION_FINISHED` is idempotent: checks for existing Payment by `revolut_order_id` before creating
- `SUBSCRIPTION_CANCELLED` sets `cancelled_at` but preserves access until `current_period_end`
- `SUBSCRIPTION_OVERDUE` sets `past_due` with grace period (access continues until period end)
- Local environment with no webhook secret configured: auto-passes signature check (dev/testing only)

#### RevolutService.php (Modified)
- Added `registerWebhook(string $url, array $events)` method
- Added `listWebhooks()` method
- Both use v1.0 headers (webhooks are part of the v1 API, not Subscriptions API)

#### Subscription.php (Modified)
- Added `cancelled_at` to `$fillable` and `$casts` (datetime)
- `isActive()` now returns true for `cancelled` and `past_due` statuses when `current_period_end` is still in the future (grace period access)

#### RegisterRevolutWebhook.php (New)
- Artisan command: `php artisan revolut:register-webhook [url] [--list]`
- Registers webhook URL with all 6 event types
- `--list` flag shows currently registered webhooks
- Duplicate detection: warns if URL already registered, asks for confirmation before creating duplicate

### New Files (Task 3)

| File | Purpose |
|------|---------|
| `app/Console/Commands/RegisterRevolutWebhook.php` | Artisan command to register/list Revolut webhooks |
| `database/migrations/2026_02_24_100003_add_cancelled_at_to_subscriptions_table.php` | Adds `cancelled_at` timestamp to subscriptions |

### Modified Files (Task 3)

```
app/Http/Controllers/Api/PaymentWebhookController.php
app/Services/Payment/RevolutService.php
app/Models/Subscription.php
```

### Post-upload Commands (Task 3)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migration (adds cancelled_at column)
php artisan migrate --force

# Register webhook endpoint with Revolut
php artisan revolut:register-webhook

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize
```

### .env Variables Required (Task 3)

```
REVOLUT_WEBHOOK_SECRET=     # Webhook signing secret from Revolut dashboard (REQUIRED for production)
```

### Security Fixes Applied (Task 3)

| Issue | Fix |
|-------|-----|
| Legacy signature fallback defeats replay protection | Removed — only structured `v1=,t=` format accepted |
| Duplicate payments on webhook retry | Idempotency check on `revolut_order_id` before creating Payment |
| Cancelled subscription immediately cuts access | `isActive()` now honours `current_period_end` for cancelled/past_due |
| Overdue subscription cuts access immediately | Grace period: `past_due` retains access until period end |
| Register-webhook creates duplicates | Duplicate URL detection with confirmation prompt |
| Malformed timestamp bypasses replay check | Validates `timestamp > 0` |

### Subscription Status Lifecycle

```
trialing → (checkout completes) → active → (cancels) → cancelled (access until period end) → expired
                                    ↓
                               (payment fails) → past_due (grace period, Revolut retries) → active (if retry succeeds)
                                                                                          → expired (if all retries fail)
```

---

## Task 4: User Profile Subscription Management

### Summary

Added a Subscription tab to the User Profile page showing subscription status with live d/h/m countdowns. Five states: trialing (trial countdown + subscribe), active (plan details + renewal countdown + cancel), cancelled (access countdown + resubscribe), past_due (payment failed + retry info), expired/none (grace period countdown + subscribe). Cancel flow with confirmation modal and reason selection. Backend cancel endpoint with row locking and Revolut API integration.

### What Changed

#### SubscriptionManagement.vue (New)
- New component at `resources/js/components/UserProfile/SubscriptionManagement.vue`
- Five subscription states with distinct UI cards and status badges
- Live countdown timers (days, hours, minutes) updating every 60 seconds for trial, renewal, and access periods
- Cancel confirmation modal with reason dropdown: too expensive, not using enough, missing features, found alternative, temporary break, technical issues, other (with free-text)
- Auto-refreshes data when countdown reaches zero (trial expires or access ends)
- Uses `currencyMixin` (Rule 6), `logger` utility, semantic `error-*` design tokens (Rule 11)
- Grace period indicator for expired state (simplified — full overlay in Task 8)

#### UserProfile.vue (Modified)
- Added "Subscription" tab after "Family" in tab navigation
- Imported and rendered `SubscriptionManagement` component

#### PaymentController.php (Modified)
- New `cancelSubscription()` endpoint (`POST /api/payment/cancel-subscription`)
- Row locking via `DB::transaction` + `lockForUpdate` to prevent race conditions on concurrent cancel requests
- Preview user guard (403)
- Validates subscription is `active` or `past_due` before cancelling
- Calls `RevolutService::cancelSubscription()` then updates local status
- Catches `\Throwable` (not just `\RuntimeException`) for network/timeout errors
- Empty reason coalesced to `null` (not empty string)
- `trialStatus()` now returns `current_period_start`, `current_period_end`, and `cancelled_at`

#### Subscription.php (Modified)
- Added `cancellation_reason` to `$fillable`

#### routes/api.php (Modified)
- New route: `POST /payment/cancel-subscription` with `throttle:1,1` (1 request per minute — destructive external API call)

#### Migration (New)
- `2026_02_24_100004_add_cancellation_reason_to_subscriptions_table.php`
- Adds `cancellation_reason` varchar(500) nullable to subscriptions

### New Files (Task 4)

| File | Purpose |
|------|---------|
| `resources/js/components/UserProfile/SubscriptionManagement.vue` | Subscription management tab component |
| `database/migrations/2026_02_24_100004_add_cancellation_reason_to_subscriptions_table.php` | Adds cancellation_reason column |

### Modified Files (Task 4)

```
resources/js/views/UserProfile.vue
app/Http/Controllers/Api/PaymentController.php
app/Models/Subscription.php
routes/api.php
```

### Post-upload Commands (Task 4)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migration (adds cancellation_reason column)
php artisan migrate --force

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize
```

### Security Fixes Applied (Task 4)

| Issue | Fix |
|-------|-----|
| Race condition on concurrent cancel clicks | Row lock via `lockForUpdate` in DB transaction |
| Exception catch too narrow (missed network errors) | Changed from `\RuntimeException` to `\Throwable` |
| Empty string stored as cancellation reason | Coalesced to `null` |
| Rate limit too permissive for destructive action | Tightened from `throttle:3,1` to `throttle:1,1` |
| Raw `red-*` Tailwind classes | Replaced with semantic `error-*` design tokens |
| Countdown zero shows stale state | Auto-refreshes data when countdown hits zero |

---

## Task 5: Trial Flow (7-Day Full Access)

### Summary

Registered the CheckSubscription middleware to enforce subscription access control on all API routes (feature-flagged via PAYMENT_ENABLED). Added `data_retention_starts_at` field to track the 30-day grace period when a trial expires. Updated the middleware with read-only/write path exclusion categories, eager subscription loading, and grace period read-only access. Updated the frontend to use backend grace period data instead of a frontend estimate.

### What Changed

#### CheckSubscription.php (Full Rewrite)
- Was: Existed but not registered in Kernel.php or applied to any routes
- Now: Added to the `api` middleware group — runs on all API routes
- Feature-flagged: `PAYMENT_ENABLED=false` means complete no-op (zero impact)
- Two-tier path exclusions: ALWAYS_EXCLUDED_PATHS (payment, auth, webhooks, admin) and READ_ONLY_EXCLUDED_PATHS (user profile, settings)
- Grace period: expired users with `data_retention_starts_at` within 30 days get read-only access (GET/HEAD/OPTIONS pass, writes blocked with `grace_period` error)
- Eagerly loads subscription relation to avoid 3 separate queries per request
- Preview users bypass all subscription checks

#### Kernel.php (Modified)
- Added `CheckSubscription::class` to the `api` middleware group (after PreviewWriteInterceptor)

#### TrialService.php (Modified)
- `expireTrials()` now sets `data_retention_starts_at = now()` when expiring trials
- This starts the 30-day grace period countdown
- Added comment documenting the observer bypass trade-off of bulk updates

#### Subscription.php (Modified)
- Added `data_retention_starts_at` to `$fillable` and `$casts` (datetime)
- New `isInGracePeriod(): bool` — checks if within 30 days of `data_retention_starts_at`
- New `gracePeriodEndsAt(): ?Carbon` — returns the date when the grace period ends

#### User.php (Modified)
- New `isInGracePeriod(): bool` — delegates to `Subscription::isInGracePeriod()`

#### PaymentController.php (Modified)
- `trialStatus()` now returns `data_retention_starts_at`, `grace_period_ends_at`, and `is_in_grace_period`
- Grace period fields are guarded behind `payment_enabled` flag (no confusing data when payments disabled)

#### SubscriptionManagement.vue (Modified)
- Grace period countdown now uses backend `grace_period_ends_at` instead of frontend estimate (`current_period_end + 30 days`)
- Full d/h/m countdown for grace period (matching trial and renewal countdown pattern)
- Removed dead `gracePeriodDaysLeft` code (replaced by `gracePeriodCountdown` and `isInGracePeriod`)

### New Files (Task 5)

| File | Purpose |
|------|---------|
| `database/migrations/2026_02_24_100005_add_data_retention_starts_at_to_subscriptions_table.php` | Adds `data_retention_starts_at` timestamp to subscriptions |

### Modified Files (Task 5)

```
app/Http/Kernel.php
app/Http/Middleware/CheckSubscription.php
app/Services/Payment/TrialService.php
app/Models/Subscription.php
app/Models/User.php
app/Http/Controllers/Api/PaymentController.php
resources/js/components/UserProfile/SubscriptionManagement.vue
```

### Post-upload Commands (Task 5)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migration (adds data_retention_starts_at column)
php artisan migrate --force

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize
```

### Security Fixes Applied (Task 5)

| Issue | Fix |
|-------|-----|
| Middleware not applied to any routes | Registered in api middleware group (feature-flagged) |
| Expired users can write to profile/settings | Split exclusions: READ_ONLY_EXCLUDED_PATHS only allow GET/HEAD/OPTIONS |
| 3 subscription queries per request | Eager load subscription once in middleware |
| Grace period data shown when payments disabled | Guarded behind `payment_enabled` flag |
| HEAD/OPTIONS blocked during grace period | Added to safe method list alongside GET |
| Frontend grace period estimated from wrong field | Uses backend `grace_period_ends_at` from `data_retention_starts_at + 30 days` |

### Middleware Access Control Summary

| User State | Full API Access | Read-Only Access | Excluded Paths Only |
|-----------|----------------|------------------|---------------------|
| `PAYMENT_ENABLED=false` | All | All | All |
| Preview user | All | All | All |
| Trialing | All | All | All |
| Active | All | All | All |
| Cancelled (within period) | All | All | All |
| Past due (within period) | All | All | All |
| Expired (in grace period) | No | Yes | Yes |
| Expired (no grace period) | No | No | Yes |
| No subscription | No | No | Yes |

---

## Task 6: Auto-Renewal, Renewal Reminders, and Invoicing

### Summary

Added 7-day pre-renewal email reminders with dedup tracking, payment confirmation emails on successful charges, billing history endpoint and UI, and payment descriptions. Revolut handles auto-renewal via saved payment methods; this task adds the notification and billing visibility layer.

### What Changed

#### SendRenewalReminderEmails.php (New)
- Artisan command: `subscriptions:send-renewal-reminders`
- Queries active subscriptions where `current_period_end` is exactly 7 days away
- Dedup via `renewal_reminder_log` table (unique on subscription_id + period_end_date)
- Sends `SubscriptionRenewalReminder` mailable with plan name, billing cycle, amount, renewal date, and manage/cancel link
- Scheduled daily at 09:00 (matching trial reminders)

#### SubscriptionRenewalReminder.php + Template (New)
- Subject: "Your Fynla subscription renews in 7 days"
- Shows plan details, renewal amount, and renewal date in a blue info box
- CTA links to `/profile#subscription` for managing/cancelling

#### PaymentConfirmation.php + Template (New)
- Subject: "Payment confirmation - Fynla"
- Shows green success badge, receipt details (plan, amount, date, reference)
- Reference format: `FYN-000001` (zero-padded payment ID)
- CTA links to dashboard

#### PaymentWebhookController.php (Modified)
- `handleOrderCompleted()`: Now creates Payment with `description`, sends PaymentConfirmation email
- `handleSubscriptionFinished()`: Same + strengthened idempotency guard (requires order_id, logs and returns early if missing)
- New `sendPaymentConfirmation()` private helper with try/catch (email failure cannot break webhook response)

#### PaymentController.php (Modified)
- New `billingHistory()` endpoint: returns completed payments with reference, description, amount, date
- Limited to 24 most recent payments (covers 2 years of monthly billing)

#### Payment.php (Modified)
- Added `description` to `$fillable`

#### routes/api.php (Modified)
- New route: `GET /payment/billing-history`

#### Kernel.php (Modified)
- Added `subscriptions:send-renewal-reminders` to daily 09:00 schedule

#### SubscriptionManagement.vue (Modified)
- New "Billing History" table section below subscription status cards
- Shows date, description, reference, and amount for each completed payment
- Fetches from `GET /api/payment/billing-history` after loading subscription data

### New Files (Task 6)

| File | Purpose |
|------|---------|
| `database/migrations/2026_02_24_100006_create_renewal_reminder_log_table.php` | Dedup table for renewal reminders |
| `database/migrations/2026_02_24_100007_add_description_to_payments_table.php` | Adds description column to payments |
| `app/Console/Commands/SendRenewalReminderEmails.php` | Daily renewal reminder command |
| `app/Mail/SubscriptionRenewalReminder.php` | Renewal reminder mailable |
| `resources/views/emails/subscription-renewal-reminder.blade.php` | Renewal reminder email template |
| `app/Mail/PaymentConfirmation.php` | Payment confirmation mailable |
| `resources/views/emails/payment-confirmation.blade.php` | Payment confirmation email template |

### Modified Files (Task 6)

```
app/Console/Kernel.php
app/Http/Controllers/Api/PaymentWebhookController.php
app/Http/Controllers/Api/PaymentController.php
app/Models/Payment.php
routes/api.php
resources/js/components/UserProfile/SubscriptionManagement.vue
```

### Post-upload Commands (Task 6)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migrations (creates renewal_reminder_log, adds description to payments)
php artisan migrate --force

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize
```

### .env Variables Required (Task 6)

No new env variables. Relies on existing mail configuration and `PAYMENT_ENABLED`.

### Security Fixes Applied (Task 6)

| Issue | Fix |
|-------|-----|
| SUBSCRIPTION_FINISHED with null order_id bypasses idempotency | Require order_id, log warning and return early if missing |
| Unbounded billing history query | Limited to 24 most recent payments |
| Email failure could break webhook response | try/catch around sendPaymentConfirmation |
| No payment descriptions for billing history | Description set on Payment::create in both webhook handlers |

### Email Notification Summary (Updated)

| Trigger | When | Subject |
|---------|------|---------|
| Trial ending | 3, 2, 1 days before | "Your Fynla trial ends in N days" / "...ends tomorrow" |
| Renewal approaching | 7 days before auto-renewal | "Your Fynla subscription renews in 7 days" |
| Payment successful | On ORDER_COMPLETED / SUBSCRIPTION_FINISHED webhook | "Payment confirmation - Fynla" |
| Subscription cancelled | On user cancellation or Revolut webhook | "Subscription cancelled - Fynla" |

---

## Task 7: Cancellation Flow

### Summary

Completed the cancellation lifecycle: confirmation email, cancelled→expired transition when billing period ends, and resubscribe flow for expired users. The cancel endpoint and confirmation modal were already implemented in Task 4.

### New Files

| File | Purpose |
|------|---------|
| `app/Mail/SubscriptionCancellation.php` | Mailable for cancellation confirmation |
| `resources/views/emails/subscription-cancellation.blade.php` | Email template with plan details, access-until date, grace period notice |

### Modified Files

| File | Change |
|------|--------|
| `app/Http/Controllers/Api/PaymentController.php` | Send cancellation email after successful cancel; wrapped subscribe flow in DB transaction with lockForUpdate to prevent duplicate Revolut subscriptions; clear stale revolut IDs for expired/cancelled users on resubscribe |
| `app/Http/Controllers/Api/PaymentWebhookController.php` | Send cancellation email from SUBSCRIPTION_CANCELLED webhook (admin-initiated only); clear cancellation_reason and data_retention_starts_at in both ORDER_COMPLETED and SUBSCRIPTION_FINISHED handlers |
| `app/Services/Payment/TrialService.php` | New `expireCancelledSubscriptions()` method: transitions cancelled subscriptions to expired when current_period_end passes, sets data_retention_starts_at |
| `app/Console/Commands/ExpireTrials.php` | Now also calls expireCancelledSubscriptions(); updated description |

### Upload to Server

```
app/Mail/SubscriptionCancellation.php
resources/views/emails/subscription-cancellation.blade.php
app/Http/Controllers/Api/PaymentController.php
app/Http/Controllers/Api/PaymentWebhookController.php
app/Services/Payment/TrialService.php
app/Console/Commands/ExpireTrials.php
```

### SSH Commands

```bash
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

No new migrations or scheduled commands — the existing `trials:expire` daily cron now also handles cancelled subscription expiry.

### Quality Review Fixes Applied

| Issue | Fix |
|-------|-----|
| `getOriginal('cancelled_at')` called after `update()` returns new value, not old | Capture `cancelled_at` before `update()` to correctly detect admin-initiated cancellations |
| Subscribe endpoint not protected against concurrent requests creating duplicate Revolut subs | Wrapped status check, stale-ID clear, and createSubscription in DB::transaction with lockForUpdate |
| SUBSCRIPTION_FINISHED handler leaves stale cancellation_reason and data_retention_starts_at | Added nulling of both fields alongside cancelled_at in renewal update |

### Cancellation Lifecycle

1. **User clicks Cancel** → `PaymentController::cancelSubscription()` → calls Revolut API → status=cancelled, cancelled_at=now → sends cancellation email → user retains access until `current_period_end`
2. **SUBSCRIPTION_CANCELLED webhook** (from Revolut/admin) → updates status if not already cancelled → sends email only for admin-initiated cancellations
3. **current_period_end passes** → `trials:expire` daily cron → `expireCancelledSubscriptions()` → status=expired, data_retention_starts_at=now → user enters 30-day grace period
4. **User resubscribes during grace period** → `subscribe()` clears stale IDs → creates new Revolut subscription → `ORDER_COMPLETED` webhook clears data_retention_starts_at, cancelled_at, cancellation_reason → access restored

---

## Task 8: 30-Day Data Retention & Deletion Policy

### Summary

Implemented the full 30-day data retention lifecycle: warning emails on a scheduled cadence (Day 1, 15, 20-29), automated data purge after 30 days, user-initiated "Delete All Data" option during grace period, non-dismissable overlay for expired users, and soft deletes on the User model. The DataPurgeService cascades through ~50 tables across all 7 modules respecting FK constraints, anonymises audit logs, deletes document files from disk, and scrubs all PII from the user record.

### New Files

| File | Purpose |
|------|---------|
| `database/migrations/2026_02_24_100008_create_data_retention_email_log_table.php` | Dedup table for retention warning emails (unique on subscription_id + day_number) |
| `database/migrations/2026_02_24_100009_add_soft_deletes_to_users_table.php` | Adds `deleted_at` column to users table |
| `app/Console/Commands/SendDataRetentionWarnings.php` | Daily command: sends warning emails on Day 1, 15, 20-29 of grace period |
| `app/Console/Commands/PurgeExpiredUserData.php` | Daily command: purges data for users past 30-day grace period |
| `app/Services/Payment/DataPurgeService.php` | 8-phase cascade deletion service covering ~50 tables |
| `app/Mail/DataRetentionWarning.php` | Mailable with dynamic subjects based on urgency (final warning, urgent, halfway, initial) |
| `app/Mail/DataDeletionConfirmation.php` | Mailable confirming permanent deletion of all data |
| `resources/views/emails/data-retention-warning.blade.php` | Warning email with countdown, data list, Subscribe CTA |
| `resources/views/emails/data-deletion-confirmation.blade.php` | Deletion confirmation email |
| `resources/js/components/Payment/DataRetentionOverlay.vue` | Non-dismissable modal overlay for grace period users |

### Modified Files

| File | Change |
|------|--------|
| `app/Models/User.php` | Added `SoftDeletes` trait |
| `app/Http/Controllers/Api/PaymentController.php` | New `deleteAllData()` endpoint with password re-entry and DELETE confirmation |
| `app/Console/Kernel.php` | Scheduled `data-retention:send-warnings` at 09:00 and `data-retention:purge-expired` at 00:30 |
| `routes/api.php` | New route: `POST /payment/delete-all-data` with `throttle:1,5` |
| `resources/js/layouts/AppLayout.vue` | Added `DataRetentionOverlay` component |

### Upload to Server

```
# New files
database/migrations/2026_02_24_100008_create_data_retention_email_log_table.php
database/migrations/2026_02_24_100009_add_soft_deletes_to_users_table.php
app/Console/Commands/SendDataRetentionWarnings.php
app/Console/Commands/PurgeExpiredUserData.php
app/Services/Payment/DataPurgeService.php
app/Mail/DataRetentionWarning.php
app/Mail/DataDeletionConfirmation.php
resources/views/emails/data-retention-warning.blade.php
resources/views/emails/data-deletion-confirmation.blade.php
resources/js/components/Payment/DataRetentionOverlay.vue

# Modified files
app/Models/User.php
app/Http/Controllers/Api/PaymentController.php
app/Console/Kernel.php
routes/api.php
resources/js/layouts/AppLayout.vue
```

### SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Run migrations (creates data_retention_email_log, adds soft deletes to users)
php artisan migrate --force

# Clear caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

### DataPurgeService — 8-Phase Cascade

| Phase | What | Details |
|-------|------|---------|
| 1 | Reverse references | Nulls spouse_id, linked_user_id, beneficiary_id, beneficiary_user_id in other users' records |
| 2 | Document files | Deletes physical files from disk via Storage facade |
| 3 | Polymorphic holdings | Deletes Holdings for user's InvestmentAccounts and DCPensions |
| 4 | Module tables | ~50 tables in FK-safe order (leaves first, parents last) |
| 5 | Login attempts | Deletes by email (no user_id FK) |
| 6 | Audit logs | Anonymises: nulls user_id, ip_address, user_agent, old_values, new_values, metadata |
| 7 | Sanctum tokens | Deletes polymorphic personal_access_tokens |
| 8 | User soft-delete | Preserves email (re-registration), scrubs all PII: name, NI number, address, employment, income, expenditure, MFA secrets, password (randomised) |

### Data Retention Email Schedule

| Day | Days Left | Subject |
|-----|-----------|---------|
| 1 | 30 | "Important: Your Fynla data will be deleted in 30 days" |
| 15 | 15 | "Reminder: Your Fynla data will be deleted in 15 days" |
| 20-28 | 10-2 | "Urgent: Your Fynla data will be deleted in N days" |
| 29 | 1 | "Final warning: Your Fynla data will be deleted tomorrow" |

### Security Fixes Applied

| Issue | Fix |
|-------|-----|
| No `deleted_at` column on users table (purge transaction would roll back) | Created migration + added SoftDeletes trait to User model |
| Audit log JSON fields (old_values, new_values, metadata) retained PII after anonymisation | Nulled all JSON fields alongside scalar PII |
| `last_name` vs `surname` column mismatch in PII scrub | Fixed to use `surname` (matching User model) |
| Incomplete PII scrub (missed NI number, address, income, MFA) | Expanded to ~30 fields: identity, address, employment, income, expenditure, auth, relationships, subscription |
| `password_reset_sessions` not in deletion order | Added to Auth/Sessions section of getDeletionOrder() |
| Preview users could receive retention warning emails | Added `$user->is_preview_user` filter in SendDataRetentionWarnings |
| Delete endpoint accepts just confirmation text (no password) | Added `current_password` validation with Hash::check |
| User email logged in purge service (GDPR: log retains PII post-deletion) | Removed email from Log::info, only logs user_id |
| Day calculation drifts with DST changes | Normalised with startOfDay() on both now and data_retention_starts_at |
| Purge cutoff includes partial days (time-of-day dependent) | Normalised with startOfDay()->subDays(30) |

### Frontend: DataRetentionOverlay

- **Visibility**: `status === 'expired' && is_in_grace_period && payment_enabled`
- **Non-dismissable**: No close button, overlay blocks all interaction
- **Countdown**: Days, hours, minutes from `grace_period_ends_at`
- **Subscribe button**: Links to `/checkout`
- **Delete All Data**: Requires password entry + typing "DELETE" to confirm
- **On success**: Redirects to `/` via `window.location.href`

### Scheduled Commands Summary (Updated)

| Command | Schedule | Purpose |
|---------|----------|---------|
| `trials:expire` | Daily at 00:00 | Expires ended trials AND cancelled subscriptions past period end |
| `data-retention:purge-expired` | Daily at 00:30 | Purges data for users past 30-day grace period |
| `subscriptions:send-renewal-reminders` | Daily at 09:00 | 7-day pre-renewal email |
| `subscriptions:send-trial-reminders` | Daily at 09:00 | Trial ending reminders (3, 2, 1 day) |
| `data-retention:send-warnings` | Daily at 09:00 | Grace period warning emails |

### Email Notification Summary (Updated)

| Trigger | When | Subject |
|---------|------|---------|
| Trial ending | 3, 2, 1 days before | "Your Fynla trial ends in N days" / "...ends tomorrow" |
| Renewal approaching | 7 days before auto-renewal | "Your Fynla subscription renews in 7 days" |
| Payment successful | On ORDER_COMPLETED / SUBSCRIPTION_FINISHED webhook | "Payment confirmation - Fynla" |
| Subscription cancelled | On user cancellation or Revolut webhook | "Subscription cancelled - Fynla" |
| Data retention warning | Days 1, 15, 20-29 of grace period | Dynamic subject based on urgency |
| Data deletion confirmation | After automated or user-initiated purge | "Your Fynla data has been permanently deleted" |

---

## Task 9: Environment Config

### Summary

Audited and fixed all Revolut environment configuration. Resolved a critical CSP conflict where static `.htaccess` headers were overriding the dynamic SecurityHeaders middleware (blocking Revolut domains in production). Added missing env vars to `.env.example`, startup validation for misconfigured environments, and `$hidden` on the Subscription model to prevent Revolut ID leakage.

### What Changed

#### `.env.example` (Updated)
- Added `REVOLUT_PUBLIC_KEY` (was in `config/services.php` but missing from `.env.example`)
- Added `REVOLUT_CHECKOUT_MODE=popup` (was in `config/services.php` but missing from `.env.example`)
- Added documentation comments: sandbox vs production credential URLs, feature flag explanation, checkout mode options

#### `.htaccess` CSP Conflict (CRITICAL FIX)
- **Problem**: Both `deploy/fynla-org/.htaccess` and `deploy/csjones-fynla/.htaccess` had `Header set Content-Security-Policy` with a static policy that excluded Revolut domains. Apache's `Header set` runs AFTER PHP-FPM output, overriding the `SecurityHeaders` middleware's dynamic CSP. This would block the Revolut checkout widget, SDK scripts, and API calls in production when `PAYMENT_ENABLED=true`.
- **Fix**: Changed CSP and Permissions-Policy from `Header set` to `Header setifempty`. This makes the `.htaccess` headers apply only to static files served directly by Apache (no PHP). For PHP responses, the middleware sets the header first, and `setifempty` leaves it alone.

#### `RevolutService.php` (Modified)
- Added startup validation when `PAYMENT_ENABLED=true`:
  - `Log::critical` if `REVOLUT_API_KEY` is empty
  - `Log::critical` if `REVOLUT_SANDBOX=true` in production environment (would silently route payments to sandbox API — no real money collected)

#### `Subscription.php` (Modified)
- Added `$hidden` array: `revolut_order_id`, `revolut_subscription_id` — prevents Revolut internal IDs from leaking if the model is ever serialized to JSON

### Modified Files

```
.env.example
deploy/fynla-org/.htaccess
deploy/csjones-fynla/.htaccess
app/Services/Payment/RevolutService.php
app/Models/Subscription.php
```

### Upload to Server

```
deploy/fynla-org/.htaccess
app/Services/Payment/RevolutService.php
app/Models/Subscription.php
```

### SSH Commands

```bash
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize
```

No migrations needed.

### Security Fixes Applied

| Issue | Fix |
|-------|-----|
| `.htaccess` static CSP overrides middleware dynamic CSP (blocks Revolut in production) | Changed `Header set` to `Header setifempty` for CSP and Permissions-Policy |
| `REVOLUT_PUBLIC_KEY` and `REVOLUT_CHECKOUT_MODE` missing from `.env.example` | Added with documentation |
| No warning if `PAYMENT_ENABLED=true` with empty `REVOLUT_API_KEY` | Added `Log::critical` in RevolutService constructor |
| No warning if `REVOLUT_SANDBOX=true` in production | Added `Log::critical` for sandbox-in-production |
| Subscription model leaks `revolut_order_id` and `revolut_subscription_id` in JSON | Added `$hidden` array |

### Complete Environment Variable Reference

| Env Var | Type | Default | Required For | Purpose |
|---------|------|---------|-------------|---------|
| `PAYMENT_ENABLED` | bool | `false` | All payment features | Master feature flag — disables entire payment system when false |
| `REVOLUT_API_KEY` | string | `''` | API calls | Secret key from Revolut Business dashboard |
| `REVOLUT_PUBLIC_KEY` | string | `''` | Frontend SDK | Public key for Revolut Checkout widget (future use) |
| `REVOLUT_WEBHOOK_SECRET` | string | `''` | Webhook verification | Signing secret for HMAC-SHA256 signature verification |
| `REVOLUT_SANDBOX` | bool | `true` | Host selection | `true` = sandbox-merchant.revolut.com, `false` = merchant.revolut.com |
| `REVOLUT_CHECKOUT_MODE` | string | `'popup'` | Checkout UI | Widget mode: `popup`, `embedded`, or `card_field` |

### Production .env Settings

```env
PAYMENT_ENABLED=true
REVOLUT_API_KEY=sk_live_...
REVOLUT_PUBLIC_KEY=pk_live_...
REVOLUT_WEBHOOK_SECRET=whsec_...
REVOLUT_SANDBOX=false
REVOLUT_CHECKOUT_MODE=popup
```

### Sandbox .env Settings (Local/Staging)

```env
PAYMENT_ENABLED=true
REVOLUT_API_KEY=sk_test_...
REVOLUT_PUBLIC_KEY=pk_test_...
REVOLUT_WEBHOOK_SECRET=whsec_test_...
REVOLUT_SANDBOX=true
REVOLUT_CHECKOUT_MODE=popup
```

### Configuration Flow

```
.env → config/services.php + config/app.php
          ↓
    ├→ RevolutService (API host, auth, startup validation)
    ├→ SecurityHeaders middleware (dynamic CSP with Revolut domains)
    ├→ app.blade.php (Revolut SDK script: sandbox vs production CDN)
    ├→ PaymentController (feature gates, checkout config to frontend)
    ├→ PaymentWebhookController (signature verification)
    └→ CheckSubscription middleware (subscription enforcement gate)
```

---

## Task 10: Sandbox Testing & Bug Fixes

### Summary

Full end-to-end Revolut sandbox testing of yearly and monthly subscription flows. Fixed three bugs discovered during testing: CDN embed.js 503, webhook order_id field mismatch, and trial banner not updating after payment. Switched Revolut SDK loading from CDN script tag to `@revolut/checkout` npm package.

### Bug Fixes

#### 1. CDN embed.js returning HTTP 503
- **Problem**: `https://sandbox-merchant.revolut.com/embed.js` was returning 503 Service Unavailable, blocking the checkout page entirely
- **Fix**: Installed `@revolut/checkout` npm package (`npm install @revolut/checkout`), imported directly in CheckoutPage.vue, removed CDN script tag from app.blade.php
- **Benefit**: More reliable than CDN — bundled with the app, no external dependency at page load

#### 2. ORDER_COMPLETED webhook silently failing
- **Problem**: Revolut sends `{"event":"ORDER_COMPLETED","order_id":"xxx"}` with `order_id` at top level. The `match` statement passes `$payload['order'] ?? $payload` — since there's no `order` key, the full payload is passed. Handler looked for `$orderData['id']` which was null, causing a silent return with no subscription activation.
- **Fix**: Changed to `$orderData['id'] ?? $orderData['order_id'] ?? null` in both `handleOrderCompleted` and `handlePaymentFailed`. Added warning log when order_id is missing.

#### 3. Trial banner not disappearing after payment
- **Problem**: TrialCountdownBanner fetches trial-status only on `mounted()`. After payment success, CheckoutPage redirects to `/dashboard?payment=success`, but AppLayout persists across route changes so the banner doesn't re-mount — continues showing stale "trialing" data.
- **Fix**: Added `$route.query.payment` watcher in TrialCountdownBanner that re-fetches trial-status when `payment=success` is detected. API returns `status: 'active'`, `shouldShow` returns false, banner disappears instantly.

### Tests Completed

| Test | User | Plan | Billing | Amount | Webhooks | Result |
|------|------|------|---------|--------|----------|--------|
| Yearly subscription | chris@fynla.org | Standard | Yearly | £100/year | SUBSCRIPTION_INITIATED + ORDER_COMPLETED | PASS |
| Monthly subscription | c.jones@csjones.co | Standard | Monthly | £10.99/month | SUBSCRIPTION_INITIATED + ORDER_COMPLETED | PASS |

Both tests verified:
- Revolut checkout popup renders correctly (plan name, amount, billing cycle)
- Test card payment succeeds in sandbox
- Webhooks delivered via ngrok and processed correctly
- Subscription status updated to `active` in database
- Payment record created with correct amount, currency, and description
- User plan updated
- Trial countdown banner disappears after payment redirect

### Webhook Testing Setup

```bash
# Start ngrok tunnel (must be running during sandbox testing)
ngrok http 8000

# Register webhook with Revolut (using ngrok URL)
php artisan revolut:register-webhook https://<ngrok-id>.ngrok-free.app/api/payment/webhook
```

### Modified Files (Task 10)

```
resources/js/views/Auth/CheckoutPage.vue
resources/views/app.blade.php
app/Http/Controllers/Api/PaymentWebhookController.php
resources/js/components/Trial/TrialCountdownBanner.vue
package.json
package-lock.json
```

### Files to Upload

```
resources/js/views/Auth/CheckoutPage.vue
resources/views/app.blade.php
app/Http/Controllers/Api/PaymentWebhookController.php
resources/js/components/Trial/TrialCountdownBanner.vue
```

Note: `package.json` and `package-lock.json` changes are build-time only. The `@revolut/checkout` package is bundled by Vite into the frontend build output — no npm install needed on the server.

### SSH Commands

```bash
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

No new migrations needed.

### Revolut SDK: CDN vs npm Package

| Aspect | CDN (removed) | npm Package (current) |
|--------|---------------|----------------------|
| Loading | `<script>` tag in app.blade.php, deferred | Bundled by Vite at build time |
| Import | `window.RevolutCheckout` global | `import RevolutCheckout from '@revolut/checkout'` |
| Init | `RevolutCheckout(orderToken)` | `RevolutCheckout(orderToken, 'sandbox' \| 'prod')` |
| Reliability | Subject to CDN outages (503s observed) | Always available, bundled with app |
| CSP | Requires `script-src` for merchant.revolut.com | No external script domain needed |

### Notes

- Revolut sandbox test cards are specific to Revolut — standard test cards (4111..., 4929...) are rejected with "Please use one of our test cards"
- The ngrok tunnel URL changes each restart — webhook must be re-registered each session
- Signature verification is auto-skipped in local/testing environments when `REVOLUT_WEBHOOK_SECRET` is empty
