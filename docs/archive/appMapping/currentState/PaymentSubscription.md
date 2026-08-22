# Payment & Subscription System - Complete System Map

## 1. System Overview

The Payment & Subscription system handles Fynla's monetisation layer: a 7-day free trial on registration, Revolut-powered payment processing for upgrades, subscription lifecycle management (trialing, active, expired, past_due, cancelled), cancellation with access retention, and data deletion during grace period.

**Key components:**
- **Revolut Embedded Checkout** integration using CDN script API for one-time order-based payments (not recurring billing)
- **7-day free trial** initiated automatically when a user selects a plan during registration
- **Three pricing tiers:** Student, Standard, Pro (each with monthly and yearly billing cycles) — stored in `subscription_plans` database table
- **Feature flag:** The entire payment/subscription enforcement system is gated behind `config('app.payment_enabled')` (env: `PAYMENT_ENABLED`). When false, all users have unrestricted access regardless of subscription status
- **Scheduled commands** for trial expiration and reminder emails
- **Cancellation flow** with access retention until period end and optional data deletion during 30-day grace period
- **Email notifications:** Payment confirmation, subscription cancellation, data deletion confirmation, trial reminders

**Current state:** Payment is feature-flagged ON in production. The Revolut Embedded Checkout widget loads showing Revolut Pay, Card, and Google Pay payment methods.

---

## 2. Database Schema

### 2.1 `subscriptions` table

**Migration:** `database/migrations/2026_02_12_100001_create_subscriptions_table.php`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint (auto) | PK | |
| `user_id` | bigint unsigned | FK -> users.id, CASCADE DELETE | One subscription per user |
| `plan` | enum | `student`, `standard`, `pro` | Selected pricing tier |
| `billing_cycle` | enum | `monthly`, `yearly` | Billing frequency |
| `status` | enum | `trialing`, `active`, `cancelled`, `expired`, `past_due` (default: `trialing`) | Current subscription state |
| `trial_started_at` | timestamp | nullable | When the 7-day trial began |
| `trial_ends_at` | timestamp | nullable | When the trial expires |
| `current_period_start` | timestamp | nullable | Start of current paid period |
| `current_period_end` | timestamp | nullable | End of current paid period |
| `cancelled_at` | timestamp | nullable | When user cancelled |
| `cancellation_reason` | text | nullable | User-provided cancellation reason |
| `data_retention_starts_at` | timestamp | nullable | When 30-day grace period began |
| `revolut_order_id` | varchar | nullable | Revolut order ID from most recent payment |
| `revolut_subscription_id` | varchar | nullable | Reserved for future Revolut subscription API |
| `amount` | integer | NOT NULL | Price in pence (e.g. 1099 = GBP 10.99) |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |
| `deleted_at` | timestamp | nullable | Soft delete support |

### 2.2 `payments` table

**Migration:** `database/migrations/2026_02_12_100002_create_payments_table.php` + `2026_02_25_100001_add_columns_to_payments_table.php`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint (auto) | PK | |
| `subscription_id` | bigint unsigned | FK -> subscriptions.id, CASCADE DELETE | Parent subscription |
| `user_id` | bigint unsigned | FK -> users.id, CASCADE DELETE | Paying user |
| `revolut_order_id` | varchar | NOT NULL | Revolut order UUID |
| `amount` | integer | NOT NULL | Amount in pence |
| `currency` | varchar(3) | default `GBP` | ISO currency code |
| `status` | enum | `pending`, `completed`, `failed`, `refunded` (default: `pending`) | Payment outcome |
| `revolut_payment_data` | json | nullable | Full Revolut order response data |
| `description` | varchar | nullable | Human-readable description (e.g. "Standard — Monthly") |
| `plan_slug` | varchar | nullable | Plan slug at time of payment (source of truth) |
| `billing_cycle` | varchar | nullable | Billing cycle at time of payment (source of truth) |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

### 2.3 `subscription_plans` table

**Migration:** `database/migrations/2026_02_24_100001_create_subscription_plans_table.php`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint (auto) | PK | |
| `slug` | varchar | UNIQUE | Plan identifier (`student`, `standard`, `pro`) |
| `name` | varchar | NOT NULL | Display name |
| `monthly_price` | integer | NOT NULL | Monthly price in pence |
| `yearly_price` | integer | NOT NULL | Yearly price in pence |
| `trial_days` | integer | default 7 | Trial duration |
| `is_active` | boolean | default true | Whether plan is available |
| `features` | json | nullable | Feature list for plan cards |
| `sort_order` | integer | default 0 | Display ordering |

### 2.4 `trial_reminder_log` table

**Migration:** `database/migrations/2026_02_12_100004_create_trial_reminder_log_table.php`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint (auto) | PK | |
| `user_id` | bigint unsigned | FK -> users.id, CASCADE DELETE | User who received reminder |
| `days_remaining` | integer | NOT NULL | Days remaining when email was sent (3, 2, or 1) |
| `sent_at` | timestamp | NOT NULL | When the email was sent |

**Unique constraint:** `(user_id, days_remaining)` — prevents duplicate reminders for the same day threshold.

### 2.5 Related columns on `users` table

| Column | Type | Description |
|--------|------|-------------|
| `plan` | varchar | Current plan name (`free`, `student`, `standard`, `pro`). Set on trial start/payment; reverted to `free` on trial expiry |
| `trial_ends_at` | datetime | Denormalised copy of `subscriptions.trial_ends_at`. Cleared on payment confirmation |

---

## 3. Models

### 3.1 Subscription

**File:** `app/Models/Subscription.php` (137 lines)

**Traits:** `HasFactory`, `SoftDeletes`

**Fillable:** `user_id`, `plan`, `billing_cycle`, `status`, `amount`, `trial_started_at`, `trial_ends_at`, `current_period_start`, `current_period_end`, `cancelled_at`, `cancellation_reason`, `data_retention_starts_at`, `revolut_order_id`, `revolut_subscription_id`

**Casts:**
| Attribute | Cast |
|-----------|------|
| `trial_started_at` | datetime |
| `trial_ends_at` | datetime |
| `current_period_start` | datetime |
| `current_period_end` | datetime |
| `cancelled_at` | datetime |
| `data_retention_starts_at` | datetime |
| `amount` | integer |

**Relationships:**
| Method | Type | Target |
|--------|------|--------|
| `user()` | BelongsTo | `User` |
| `payments()` | HasMany | `Payment` |

**Scopes:** `scopeActive`, `scopeTrialing`, `scopeExpired`

**Methods:**
| Method | Return | Description |
|--------|--------|-------------|
| `isTrialing()` | bool | True if status is `trialing` AND `trial_ends_at` is in the future |
| `isActive()` | bool | True if `active`, or `cancelled`/`past_due` with `current_period_end` in the future |
| `isInGracePeriod()` | bool | True if `data_retention_starts_at` + 30 days is in the future |
| `gracePeriodEndsAt()` | ?Carbon | Returns the date when grace period ends |
| `daysLeftInTrial()` | int | Days between now and `trial_ends_at` (min 0) |
| `trialProgress()` | float | Percentage of trial elapsed (0-100) |

### 3.2 Payment

**File:** `app/Models/Payment.php` (42 lines)

**Traits:** `HasFactory`

**Fillable:** `subscription_id`, `user_id`, `revolut_order_id`, `amount`, `currency`, `status`, `revolut_payment_data`, `description`, `plan_slug`, `billing_cycle`

**Casts:**
| Attribute | Cast |
|-----------|------|
| `amount` | integer |
| `revolut_payment_data` | array |

**Relationships:**
| Method | Type | Target |
|--------|------|--------|
| `subscription()` | BelongsTo | `Subscription` |
| `user()` | BelongsTo | `User` |

### 3.3 SubscriptionPlan

**File:** `app/Models/SubscriptionPlan.php`

**Key methods:**
| Method | Description |
|--------|-------------|
| `findBySlug(string $slug)` | Find plan by slug |
| `getPriceForCycle(string $cycle)` | Get price in pence for billing cycle |
| `scopeActive($query)` | Filter active plans |

### 3.4 User model (subscription-related)

**Relationship:** `subscription()` → HasOne → `Subscription`

**Cast:** `trial_ends_at` => `datetime`

**Helper methods:** `onTrial()`, `hasActivePlan()`, `trialDaysRemaining()`, `planIs(string $plan)`

---

## 4. Controllers

### 4.1 PaymentController

**File:** `app/Http/Controllers/Api/PaymentController.php` (492 lines)

**Constructor:** Injects `RevolutService`

**Trait:** `SanitizedErrorResponse`

| Method | HTTP | Route | Description |
|--------|------|-------|-------------|
| `plans()` | GET | `/api/payment/plans` | Returns active subscription plans from database |
| `createOrder(Request)` | POST | `/api/payment/create-order` | Creates Revolut order via embedded checkout flow. Creates pending Payment record, calls RevolutService, returns `{ token, order_id }` |
| `confirmPayment(Request)` | POST | `/api/payment/confirm` | Confirms payment after Revolut `onSuccess`. Verifies order state with Revolut API (accepts `completed` and `processing`). Activates subscription in DB transaction. Sends PaymentConfirmation email |
| `trialStatus(Request)` | GET | `/api/payment/trial-status` | Returns subscription details including trial countdown, grace period, and payment_enabled flag |
| `billingHistory(Request)` | GET | `/api/payment/billing-history` | Returns up to 24 completed payments with references (FYN-XXXXXX format) |
| `cancelSubscription(Request)` | POST | `/api/payment/cancel-subscription` | Cancels subscription with optional reason. Access retained until `current_period_end`. Sends SubscriptionCancellation email |
| `deleteAllData(Request, DataPurgeService)` | POST | `/api/payment/delete-all-data` | Permanently deletes all user data during grace period. Requires password confirmation and typing "DELETE". Sends DataDeletionConfirmation email |

**Key design decisions:**
- Preview users blocked at controller level (`is_preview_user` check)
- `plan_slug` and `billing_cycle` persisted on Payment record as source of truth
- Both `confirmPayment` and webhook are idempotent with `lockForUpdate()` transactions
- Order ID validated as UUID before any Revolut API calls
- `confirmPayment` accepts `"processing"` state because Revolut fires `onSuccess` before order reaches `"completed"`

### 4.2 WebhookController

**File:** `app/Http/Controllers/Api/WebhookController.php` (175 lines)

**Constructor:** Injects `RevolutService`

| Method | HTTP | Route | Description |
|--------|------|-------|-------------|
| `handleRevolut(Request)` | POST | `/api/webhooks/revolut` | Verifies HMAC signature, handles `ORDER_COMPLETED` / `ORDER_AUTHORISED` events |

**Private method: `handleOrderCompleted(string $orderId, ?string $merchantRef)`**
- Finds Payment by `revolut_order_id` with `lockForUpdate()`
- Cross-references `merchant_ref` against `payment_{id}`
- Verifies order state with Revolut API (accepts `completed`, `authorised`)
- Activates payment, subscription, and user plan
- Sends PaymentConfirmation email
- Fully idempotent (skips if payment already completed)

---

## 5. Services

### 5.1 RevolutService

**File:** `app/Services/Payment/RevolutService.php` (182 lines)

**Configuration:**
- API key: `config('services.revolut.api_key')` (env: `REVOLUT_API_KEY`)
- Sandbox mode: `config('services.revolut.sandbox')` (env: `REVOLUT_SANDBOX`)
- Sandbox URL: `https://sandbox-merchant.revolut.com/api`
- Production URL: `https://merchant.revolut.com/api`
- Webhook secret: `config('services.revolut.webhook_secret')` (env: `REVOLUT_WEBHOOK_SECRET`)
- API version header: `Revolut-Api-Version: 2025-12-04`

**Public methods:**

| Method | Signature | Description |
|--------|-----------|-------------|
| `createOrder` | `(int $amount, string $currency, string $description, string $redirectUrl, ?string $merchantRef, ?string $email): array` | Creates Revolut order via `POST /orders`. Returns order JSON with `id`, `token`, `state` |
| `getOrder` | `(string $orderId): array` | Retrieves order via `GET /orders/{orderId}`. Returns full order JSON |
| `verifyWebhookSignature` | `(string $rawPayload, string $signatureHeader, string $timestampHeader): bool` | HMAC-SHA256 verification with 5-minute replay protection. Constructs `v1.{timestamp}.{payload}`, compares against `Revolut-Signature` header |

### 5.2 TrialService

**File:** `app/Services/Payment/TrialService.php`

**Public methods:**
| Method | Signature | Description |
|--------|-----------|-------------|
| `startTrial` | `(User $user, string $plan, string $billingCycle): Subscription` | Creates subscription with `trialing` status, 7-day trial. Uses `SubscriptionPlan::findBySlug()` for pricing |
| `expireTrials` | `(): int` | Bulk-expires overdue trials. Returns count |

### 5.3 DataPurgeService

**File:** `app/Services/Payment/DataPurgeService.php`

**Public methods:**
| Method | Signature | Description |
|--------|-----------|-------------|
| `purgeUserData` | `(User $user): array` | Permanently deletes all financial data. Returns `{ tables_purged, records_deleted }` |

---

## 6. Frontend Components

### 6.1 CheckoutPage

**File:** `resources/js/views/Auth/CheckoutPage.vue` (331 lines)

**Purpose:** Full checkout page with Revolut Embedded Checkout widget. Two-column layout: Order Summary (left) and Payment Method (right).

**SDK Loading:** Dynamic CDN script loading via `loadRevolutSDK(sandbox)` helper. Loads `sandbox-merchant.revolut.com/embed.js` or `merchant.revolut.com/embed.js`. The npm package `@revolut/checkout` does NOT support the static `embeddedCheckout()` API — CDN is required.

**Flow:**
1. Page loads with `?plan=standard&cycle=monthly` query params
2. Fetches plan data from `/api/payment/plans` for price display
3. Initialises `RevolutCheckout.embeddedCheckout()` with `publicToken` (pk_...)
4. Widget's `createOrder` callback calls `POST /api/payment/create-order`, stores `order_id` (UUID), returns `{ publicId: token }` to widget
5. On `onSuccess`: calls `POST /api/payment/confirm` with stored UUID (NOT the callback's token)
6. Shows success modal, then navigates to `/dashboard?payment=success`

**CSS:** Hides Revolut's duplicate "Payment method" heading via `margin-top: -40px` on iframe with `overflow: hidden` on container.

### 6.2 PlanSelectionModal

**File:** `resources/js/components/Payment/PlanSelectionModal.vue` (210 lines)

**Purpose:** Modal with monthly/yearly toggle showing 3 plan cards (Student, Standard, Pro). Fetches plans from `/api/payment/plans`. Emits `select` with `{ plan, billingCycle }` and `close`.

**Used by:** TrialCountdownBanner, SubscriptionManagement (for upgrade, renew, and re-subscribe flows)

### 6.3 TrialCountdownBanner

**File:** `resources/js/components/Trial/TrialCountdownBanner.vue`

**Purpose:** Persistent blue banner at top of application showing trial countdown and "Upgrade Now" button. Opens PlanSelectionModal instead of linking directly to /checkout.

**Mounting:** `AppLayout.vue`: `<TrialCountdownBanner v-if="isAuthenticated && !isPreviewMode" />`

**Visibility:** Shows only during `trialing` status. Dismissible when > 2 days remain; non-dismissible in final 2 days.

### 6.4 SubscriptionManagement

**File:** `resources/js/components/UserProfile/SubscriptionManagement.vue` (611 lines)

**Purpose:** Subscription tab on User Profile page. Shows different UI states based on subscription status:

| State | Display |
|-------|---------|
| `trialing` | Free Trial card with countdown, plan details, "Subscribe Now" button |
| `active` | Your Subscription card (Plan, Billing Cycle, Amount, Next Renewal), "Cancel Subscription" link |
| `cancelled` | Cancelled card with access countdown, "Renew" button |
| `past_due` | Payment Issue card with warning, "Update Payment Method" button |
| `expired` / `none` | Expired/No Subscription card with optional grace period countdown, "Subscribe Now" button |

**Billing History:** Table with Date, Description, Reference (FYN-XXXXXX), Amount columns. Shows completed payments.

**Cancel flow:** Modal with reason dropdown (too_expensive, not_using_enough, missing_features, found_alternative, temporary_break, technical_issues, other). Calls `POST /api/payment/cancel-subscription`.

**Amount formatting:** Uses `formatCurrencyWithPence()` for 2 decimal places. No period suffix on amounts.

### 6.5 PricingPage

**File:** `resources/js/views/Public/PricingPage.vue` (268 lines)

**Purpose:** Public-facing pricing page with monthly/yearly toggle. "Start Free Trial" buttons navigate to `/register?plan={plan}&billing={cycle}`.

---

## 7. API Routing

**File:** `routes/api.php`

### 7.1 Payment Routes (authenticated)

All routes require `auth:sanctum` middleware. Prefix: `/api/payment/`

| Method | Path | Controller Method | Throttle | Description |
|--------|------|-------------------|----------|-------------|
| GET | `/plans` | `PaymentController@plans` | default | Available subscription plans |
| GET | `/trial-status` | `PaymentController@trialStatus` | default | Trial/subscription info |
| GET | `/billing-history` | `PaymentController@billingHistory` | default | Payment history |
| POST | `/create-order` | `PaymentController@createOrder` | 10/min | Create Revolut order |
| POST | `/confirm` | `PaymentController@confirmPayment` | 10/min | Confirm payment after onSuccess |
| POST | `/cancel-subscription` | `PaymentController@cancelSubscription` | 1/min | Cancel subscription |
| POST | `/delete-all-data` | `PaymentController@deleteAllData` | 1/5min | Delete all user data (grace period only) |

### 7.2 Webhook Route (public)

| Method | Path | Controller Method | Throttle | Description |
|--------|------|-------------------|----------|-------------|
| POST | `/api/webhooks/revolut` | `WebhookController@handleRevolut` | 60/min | Revolut webhook (HMAC-verified) |

### 7.3 Admin Route

| Method | Path | Controller Method | Description |
|--------|------|-------------------|-------------|
| GET | `/api/admin/subscriptions/stats` | `AdminController@getSubscriptionStats` | Subscription/revenue statistics |

---

## 8. Email Notifications

### 8.1 PaymentConfirmation

**File:** `app/Mail/PaymentConfirmation.php` (49 lines)

**Subject:** "Payment confirmation - Fynla"
**Template:** `emails.payment-confirmation`
**Variables:** user, payment, planName, billingCycle, amount, paymentDate
**Triggered by:** `confirmPayment()` and webhook `handleOrderCompleted()`

### 8.2 SubscriptionCancellation

**File:** `app/Mail/SubscriptionCancellation.php` (45 lines)

**Subject:** "Subscription cancelled - Fynla"
**Template:** `emails.subscription-cancellation`
**Variables:** user, planName, billingCycle, accessUntil
**Triggered by:** `cancelSubscription()`

### 8.3 DataDeletionConfirmation

**File:** `app/Mail/DataDeletionConfirmation.php`

**Subject:** Data deletion confirmation
**Triggered by:** `deleteAllData()`

### 8.4 TrialExpirationReminder

**File:** `app/Mail/TrialExpirationReminder.php` (46 lines)

**Subject:** "Your Fynla trial ends tomorrow" (1 day) or "Your Fynla trial ends in {N} days"
**Template:** `emails.trial-expiration-reminder`
**Triggered by:** `trials:send-reminders` scheduled command

---

## 9. Middleware

### 9.1 CheckSubscription

**File:** `app/Http/Middleware/CheckSubscription.php`

**Logic:** When `PAYMENT_ENABLED=true`, blocks requests from users without active subscription or trial. Bypasses preview users. Returns 403 with `subscription_required` error.

### 9.2 SecurityHeaders (Revolut CSP)

**File:** `app/Http/Middleware/SecurityHeaders.php`

Revolut domains added to CSP directives:
- `script-src`: `sandbox-merchant.revolut.com`, `merchant.revolut.com`
- `connect-src`: Same domains + `sandbox-assets.revolut.com`, `assets.revolut.com`
- `frame-src`: All Revolut domains
- `img-src`: All Revolut domains
- `Permissions-Policy`: `payment` allowed for Revolut domains

---

## 10. Cross-Module Integration

### 10.1 Registration Flow
- `AuthController` injects `TrialService`
- `PendingRegistration` stores `plan` and `billing_cycle` from registration
- On email verification, starts 7-day trial via `TrialService::startTrial()`

### 10.2 User Plan State
- `users.plan` set to plan name on trial start or payment
- Reverted to `free` on trial expiry
- Updated on successful payment (both confirm endpoint and webhook)
- `users.trial_ends_at` cleared on payment confirmation

### 10.3 Preview Mode Bypass
- `CheckSubscription` middleware bypasses preview users
- `TrialCountdownBanner` only shows for non-preview authenticated users
- `PaymentController` blocks preview users at controller level
- Webhook route excluded from `PreviewWriteInterceptor`

---

## 11. Scheduled Tasks

| Command | Schedule | Purpose |
|---------|----------|---------|
| `trials:expire` | Daily 00:05 | Marks overdue trials as expired |
| `trials:send-reminders` | Daily 09:00 | Sends reminders at 3, 2, and 1 days before expiry |

---

## 12. Configuration Reference

### Environment Variables

| Variable | Config Path | Default | Description |
|----------|-------------|---------|-------------|
| `PAYMENT_ENABLED` | `app.payment_enabled` | `false` | Master feature flag |
| `REVOLUT_API_KEY` | `services.revolut.api_key` | `''` | Revolut Merchant API secret key (sk_...) |
| `REVOLUT_PUBLIC_KEY` | `services.revolut.public_key` | `''` | Revolut public key for widget (pk_...) |
| `REVOLUT_WEBHOOK_SECRET` | `services.revolut.webhook_secret` | `''` | HMAC secret for webhook verification |
| `REVOLUT_SANDBOX` | `services.revolut.sandbox` | `true` | Use sandbox API |
| `VITE_REVOLUT_PUBLIC_KEY` | (frontend) | `''` | Public key exposed to Vue (mirrors REVOLUT_PUBLIC_KEY) |
| `VITE_REVOLUT_SANDBOX` | (frontend) | `''` | Sandbox flag exposed to Vue |

### Config Files

- `config/app.php` — contains `payment_enabled` key
- `config/services.php` — contains `revolut` section with `api_key`, `public_key`, `webhook_secret`, `sandbox`

---

## 13. Pricing

Stored in `subscription_plans` table (seeded by `SubscriptionPlanSeeder`):

| Plan | Monthly | Yearly | Yearly Equivalent |
|------|---------|--------|-------------------|
| Student | £3.99 (399p) | £30.00 (3000p) | £2.50/mo (37% saving) |
| Standard | £10.99 (1099p) | £100.00 (10000p) | £8.33/mo (24% saving) |
| Pro | £19.99 (1999p) | £200.00 (20000p) | £16.67/mo (17% saving) |

---

## 14. Payment Flow (End-to-End)

### Happy Path: Trial → Payment → Active

1. User selects plan on `/pricing` → navigates to `/register?plan=standard&billing=monthly`
2. Registration creates `PendingRegistration` with plan/billing_cycle
3. Email verification triggers `TrialService::startTrial()` → subscription `trialing`, 7-day trial
4. Trial countdown banner shows on dashboard with "Upgrade Now" button
5. User clicks "Upgrade Now" → PlanSelectionModal opens → selects plan → navigates to `/checkout?plan=standard&cycle=monthly`
6. CheckoutPage loads Revolut SDK from CDN, initialises `embeddedCheckout()` with public token
7. User clicks Pay → widget calls `createOrder` callback → `POST /api/payment/create-order`
8. Backend creates pending Payment record, calls `RevolutService::createOrder()`, returns `{ token, order_id }`
9. Frontend returns `{ publicId: token }` to widget, stores `order_id` (UUID)
10. User enters card details (or uses Revolut Pay / Google Pay)
11. Revolut fires `onSuccess` → frontend calls `POST /api/payment/confirm` with stored UUID
12. Backend verifies order state (accepts `completed` or `processing`), activates subscription in DB transaction
13. Sends PaymentConfirmation email
14. Frontend shows success modal → user clicks "Go to Dashboard"
15. Revolut later sends `ORDER_COMPLETED` webhook → idempotent handler skips (already completed)

### Cancellation Flow

1. User goes to Profile → Subscription tab → clicks "Cancel Subscription"
2. Modal appears with reason dropdown → user selects reason → confirms
3. `POST /api/payment/cancel-subscription` → sets status to `cancelled`, records reason
4. Access continues until `current_period_end`
5. SubscriptionCancellation email sent
6. After period ends, user enters expired state with 30-day grace period for data retention

### Data Deletion Flow

1. During grace period, user can request data deletion on Profile → Subscription tab
2. Must enter password and type "DELETE" to confirm
3. `POST /api/payment/delete-all-data` → `DataPurgeService::purgeUserData()` cascades through all modules
4. DataDeletionConfirmation email sent

---

## 15. File Inventory

| File | Lines | Purpose |
|------|-------|---------|
| `app/Http/Controllers/Api/PaymentController.php` | 492 | Payment API endpoints (plans, create-order, confirm, trial-status, billing-history, cancel, delete-data) |
| `app/Http/Controllers/Api/WebhookController.php` | 175 | Revolut webhook handler (ORDER_COMPLETED, ORDER_AUTHORISED) |
| `app/Services/Payment/RevolutService.php` | 182 | Revolut Merchant API wrapper (createOrder, getOrder, verifyWebhookSignature) |
| `app/Services/Payment/TrialService.php` | ~60 | Trial start and expiry |
| `app/Services/Payment/DataPurgeService.php` | ~120 | Full data purge for user accounts |
| `app/Models/Subscription.php` | 137 | Subscription model with lifecycle methods |
| `app/Models/Payment.php` | 42 | Payment model |
| `app/Models/SubscriptionPlan.php` | ~50 | Database-backed plan pricing |
| `app/Http/Middleware/CheckSubscription.php` | ~41 | Subscription enforcement middleware |
| `app/Http/Middleware/SecurityHeaders.php` | — | CSP headers including Revolut domains |
| `app/Mail/PaymentConfirmation.php` | 49 | Payment confirmation email |
| `app/Mail/SubscriptionCancellation.php` | 45 | Cancellation confirmation email |
| `app/Mail/DataDeletionConfirmation.php` | ~40 | Data deletion confirmation email |
| `app/Mail/TrialExpirationReminder.php` | 46 | Trial reminder email |
| `resources/js/views/Auth/CheckoutPage.vue` | 331 | Revolut Embedded Checkout page |
| `resources/js/components/Payment/PlanSelectionModal.vue` | 210 | Plan selection modal |
| `resources/js/components/Trial/TrialCountdownBanner.vue` | ~120 | Trial countdown banner |
| `resources/js/components/UserProfile/SubscriptionManagement.vue` | ~611 | Subscription management tab |
| `resources/js/views/Public/PricingPage.vue` | 268 | Public pricing page |
| `config/services.php` | — | Revolut config block |
| `routes/api.php` | — | Payment + webhook routes |
| `database/migrations/*` | — | 4 payment/subscription migrations |
| `database/seeders/SubscriptionPlanSeeder.php` | — | Seeds 3 plans |

---

## 16. Known Limitations

### 16.1 No Recurring Billing
The Revolut integration creates one-time orders, not recurring subscriptions. When `current_period_end` passes, no automatic charge occurs. Users must manually renew.

### 16.2 No Plan Upgrade/Downgrade Mid-Cycle
Users can change plans at renewal time but there is no prorated upgrade/downgrade during an active billing period.

### 16.3 No Automated Renewal Reminders
No scheduled task sends reminders before `current_period_end`. Users must remember to renew.

### 16.4 Dual Activation Paths
Both `confirmPayment` (frontend-triggered) and webhook handle activation. Both are idempotent, but the dual path adds complexity. The frontend path accepts `processing` state; the webhook only accepts `completed`/`authorised`.
