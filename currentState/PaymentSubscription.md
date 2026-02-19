# Payment & Subscription System - Complete System Map

## 1. System Overview

The Payment & Subscription system handles Fynla's monetisation layer: a 7-day free trial on registration, Revolut-powered payment processing for upgrades, and subscription lifecycle management (trialing, active, expired, past_due, cancelled).

**Key components:**
- **Revolut Merchant API** integration for one-time order-based payments (not recurring billing)
- **7-day free trial** initiated automatically when a user selects a plan during registration
- **Three pricing tiers:** Student, Standard, Pro (each with monthly and yearly billing cycles)
- **Feature flag:** The entire payment/subscription enforcement system is gated behind `config('app.payment_enabled')` (env: `PAYMENT_ENABLED`). When false (current default), all users have unrestricted access regardless of subscription status
- **Scheduled commands** for trial expiration and reminder emails

**Current state:** Payment is feature-flagged OFF. The CheckoutPage shows a "Payment Coming Soon" message when `payment_enabled` is false.

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
| `current_period_start` | timestamp | nullable | Start of current paid period (set on ORDER_COMPLETED) |
| `current_period_end` | timestamp | nullable | End of current paid period (set on ORDER_COMPLETED) |
| `revolut_order_id` | varchar | nullable | Revolut order ID, set when `createOrder` is called |
| `amount` | integer | NOT NULL | Price in pence (e.g. 1099 = GBP 10.99) |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |
| `deleted_at` | timestamp | nullable | Soft delete support |

### 2.2 `payments` table

**Migration:** `database/migrations/2026_02_12_100002_create_payments_table.php`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint (auto) | PK | |
| `subscription_id` | bigint unsigned | FK -> subscriptions.id, CASCADE DELETE | Parent subscription |
| `user_id` | bigint unsigned | FK -> users.id, CASCADE DELETE | Paying user |
| `revolut_order_id` | varchar | NOT NULL | Revolut order reference |
| `amount` | integer | NOT NULL | Amount in pence |
| `currency` | varchar(3) | default `GBP` | ISO currency code |
| `status` | enum | `pending`, `completed`, `failed`, `refunded` (default: `pending`) | Payment outcome |
| `revolut_payment_data` | json | nullable | Subset of Revolut order data (id, type, state, created_at, completed_at, order_amount, settlement_amount, email) |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

### 2.3 `trial_reminder_log` table

**Migration:** `database/migrations/2026_02_12_100004_create_trial_reminder_log_table.php`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint (auto) | PK | |
| `user_id` | bigint unsigned | FK -> users.id, CASCADE DELETE | User who received reminder |
| `days_remaining` | integer | NOT NULL | Days remaining when email was sent (3, 2, or 1) |
| `sent_at` | timestamp | NOT NULL | When the email was sent |

**Unique constraint:** `(user_id, days_remaining)` -- prevents duplicate reminders for the same day threshold.

### 2.4 Related columns on `users` table

| Column | Type | Description |
|--------|------|-------------|
| `plan` | varchar | Current plan name (`free`, `student`, `standard`, `pro`). Set to the plan on trial start/payment; reverted to `free` on trial expiry |
| `trial_ends_at` | datetime | Denormalised copy of `subscriptions.trial_ends_at`. Cast to datetime in User model |

---

## 3. Models

### 3.1 Subscription

**File:** `app/Models/Subscription.php` (87 lines)

**Traits:** `HasFactory`, `SoftDeletes`

**Guard:** `$guarded = ['id']` (mass-assignable except id)

**Casts:**
| Attribute | Cast |
|-----------|------|
| `trial_started_at` | datetime |
| `trial_ends_at` | datetime |
| `current_period_start` | datetime |
| `current_period_end` | datetime |
| `amount` | integer |

**Relationships:**
| Method | Type | Target |
|--------|------|--------|
| `user()` | BelongsTo | `User` |
| `payments()` | HasMany | `Payment` |

**Scopes:**
| Scope | Filter |
|-------|--------|
| `scopeActive($query)` | `status = 'active'` |
| `scopeTrialing($query)` | `status = 'trialing'` |
| `scopeExpired($query)` | `status = 'expired'` |

**Methods:**
| Method | Return | Description |
|--------|--------|-------------|
| `isTrialing()` | bool | True if status is `trialing` AND `trial_ends_at` is in the future |
| `isActive()` | bool | True if status is `active` |
| `daysLeftInTrial()` | int | Days between now and `trial_ends_at` (min 0). Returns 0 if `trial_ends_at` is null |
| `trialProgress()` | float | Percentage of trial elapsed (0-100). Based on days elapsed vs total trial days. Returns 0 if dates missing, 100 if `totalDays` is 0 |

### 3.2 Payment

**File:** `app/Models/Payment.php` (31 lines)

**Traits:** `HasFactory`

**Guard:** `$guarded = ['id']`

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

### 3.3 User model (subscription-related methods)

**File:** `app/Models/User.php`

**Relationship:**
| Method | Type | Target |
|--------|------|--------|
| `subscription()` | HasOne | `Subscription` |

**Cast:** `trial_ends_at` => `datetime`

**Helper methods:**
| Method | Return | Description |
|--------|--------|-------------|
| `onTrial()` | bool | Loads subscription (lazy or eager), delegates to `$subscription->isTrialing()` |
| `hasActivePlan()` | bool | Loads subscription (lazy or eager), delegates to `$subscription->isActive()` |
| `trialDaysRemaining()` | int | Loads subscription, delegates to `$subscription->daysLeftInTrial()`. Returns 0 if no subscription |
| `planIs(string $plan)` | bool | Compares `$this->plan` to given plan name |

---

## 4. Controllers

### 4.1 PaymentController

**File:** `app/Http/Controllers/Api/PaymentController.php` (70 lines)

| Method | HTTP | Route | Parameters | Returns | Description |
|--------|------|-------|------------|---------|-------------|
| `createOrder(Request, RevolutService)` | POST | `/api/payment/create-order` | Auth user (implicit) | `JsonResponse { public_id, order_id }` | Creates a Revolut order for the user's current subscription plan/billing_cycle. Stores `revolut_order_id` on the subscription. Returns 404 if no subscription exists. |
| `orderStatus(Request, string $id, RevolutService)` | GET | `/api/payment/order/{id}/status` | `$id` (Revolut order ID) | `JsonResponse` (raw Revolut order data) | Proxies a Revolut order status check. Returns the full Revolut order object. |
| `trialStatus(Request)` | GET | `/api/payment/trial-status` | Auth user (implicit) | `JsonResponse` | Returns subscription details including: `has_subscription`, `plan`, `billing_cycle`, `status`, `trial_ends_at` (ISO string), `days_remaining`, `progress`, `amount`, `payment_enabled`. If no subscription exists, returns `{ has_subscription: false, payment_enabled }`. |

### 4.2 PaymentWebhookController

**File:** `app/Http/Controllers/Api/PaymentWebhookController.php` (126 lines)

| Method | HTTP | Route | Description |
|--------|------|-------|-------------|
| `handle(Request)` | POST | `/api/webhooks/revolut` | Main webhook entry point. Verifies HMAC signature, dispatches to event handlers via `match` on `$payload['event']`. Returns 403 on invalid signature, 200 otherwise. |

**Private methods:**

| Method | Trigger Event | Action |
|--------|--------------|--------|
| `handleOrderCompleted(array $orderData)` | `ORDER_COMPLETED` | Finds subscription by `revolut_order_id`. Updates subscription status to `active`, sets `current_period_start` to now, `current_period_end` to now + 1 month/year (based on billing_cycle). Creates a `Payment` record with status `completed`. Updates user's `plan` column to the subscription plan. |
| `handlePaymentFailed(array $orderData)` | `ORDER_PAYMENT_FAILED` | Finds subscription by `revolut_order_id`. Updates subscription status to `past_due`. Logs warning. |
| `verifySignature(Request)` | (internal) | Checks `Revolut-Signature` or `X-Revolut-Signature` header against HMAC-SHA256 of the raw payload body using `services.revolut.webhook_secret`. Returns true in local/testing environments if no secret is configured. Returns false (fail closed) in non-local environments without a configured secret. |

**Revolut payment data stored on Payment record (whitelisted keys):**
- `id`, `type`, `state`, `created_at`, `completed_at`, `order_amount`, `settlement_amount`, `email`

---

## 5. Agent

N/A -- No agent orchestrator exists for the payment system. Payment logic is handled directly through services and controllers.

---

## 6. Services

### 6.1 RevolutService

**File:** `app/Services/Payment/RevolutService.php` (84 lines)

**Configuration:**
- API key: `config('services.revolut.api_key')` (env: `REVOLUT_API_KEY`)
- Sandbox mode: `config('services.revolut.sandbox')` (env: `REVOLUT_SANDBOX`, default: true)
- Sandbox URL: `https://sandbox-merchant.revolut.com/api/1.0`
- Production URL: `https://merchant.revolut.com/api/1.0`

**Plan Pricing (amounts in pence):**

| Plan | Monthly | Yearly |
|------|---------|--------|
| Student | 399 (GBP 3.99) | 3000 (GBP 30.00) |
| Standard | 1099 (GBP 10.99) | 10000 (GBP 100.00) |
| Pro | 1999 (GBP 19.99) | 20000 (GBP 200.00) |

**Public methods:**

| Method | Signature | Description |
|--------|-----------|-------------|
| `createOrder` | `(User $user, string $plan, string $billingCycle): array` | Creates a Revolut order via `POST /orders`. Sends amount, currency (GBP), description, and metadata (user_id, plan, billing_cycle). Throws `RuntimeException` on failure. Returns Revolut order JSON. |
| `getOrderStatus` | `(string $orderId): array` | Retrieves order status via `GET /orders/{orderId}`. Throws `RuntimeException` on failure. Returns Revolut order JSON. |

### 6.2 TrialService

**File:** `app/Services/Payment/TrialService.php` (59 lines)

**Constants:**
- `TRIAL_DAYS = 7`
- `PLAN_PRICING` -- Same pricing table as RevolutService (duplicated)

**Public methods:**

| Method | Signature | Description |
|--------|-----------|-------------|
| `startTrial` | `(User $user, string $plan, string $billingCycle): Subscription` | Creates a Subscription record with status `trialing`, sets `trial_started_at` to now, `trial_ends_at` to now + 7 days, and `amount` based on plan/billing_cycle pricing. Also updates the User record: sets `plan` to the selected plan and `trial_ends_at` to the subscription's trial end date. |
| `expireTrials` | `(): int` | Finds all subscriptions where `status = 'trialing'` AND `trial_ends_at < now()`. Sets each to `status = 'expired'` and updates the user's `plan` to `'free'`. Returns the count of expired subscriptions. |

---

## 7. Validation Requests

No dedicated FormRequest classes exist for payment endpoints. The `PaymentController` methods use direct `Request` objects. Validation of plan/billing_cycle values occurs at the service level (array key lookup with `?? 0` fallback) and at the AuthController level (inline `in_array` checks).

---

## 8. Vuex Store

No dedicated Vuex store module exists for payment/subscription state. The trial and subscription data is fetched directly by components via API calls (`api.get('/payment/trial-status')`) and stored in local component data.

The existing store modules (`investment.js`, `savings.js`, `netWorth.js`) contain unrelated references to the word "subscription" (referring to household expense "subscriptions" like Netflix, not application subscriptions).

---

## 9. API Service (Frontend)

No dedicated payment service file exists. Components call the API directly using the shared `api` Axios instance:

```javascript
// TrialCountdownBanner.vue and CheckoutPage.vue
import api from '@/services/api';

api.get('/payment/trial-status');    // GET trial/subscription status
api.post('/payment/create-order');   // POST create Revolut order
```

The admin dashboard uses `adminService.js` which includes:
```javascript
getSubscriptionStats()  // GET /admin/subscriptions/stats
```

---

## 10. Frontend Components

### 10.1 TrialCountdownBanner

**File:** `resources/js/components/Trial/TrialCountdownBanner.vue` (114 lines)

**Purpose:** Displays a persistent blue banner at the top of the application showing trial countdown and an "Upgrade Now" button. Rendered inside `AppLayout.vue` for authenticated non-preview users only.

**Mounting:** `AppLayout.vue` line 6: `<TrialCountdownBanner v-if="isAuthenticated && !isPreviewMode" />`

**Data:** `trialData` (object from trial-status API), `dismissed` (boolean), `loading` (boolean)

**Computed properties:**
| Property | Logic |
|----------|-------|
| `shouldShow` | True if trialData exists, status is `trialing`, and not dismissed (or if `canDismiss` is false, always shows) |
| `planName` | Capitalised plan name from trialData |
| `daysRemaining` | `trialData.days_remaining` (default 0) |
| `progress` | `trialData.progress` (default 0) |
| `canDismiss` | True if `daysRemaining > 2` (cannot dismiss in final 2 days) |

**Behaviour:**
- On mount, calls `GET /api/payment/trial-status`
- Shows clock icon, trial message ("Your Standard trial ends in X days"), progress bar, and "Upgrade Now" link to `/checkout`
- Dismissible via X button when more than 2 days remain; non-dismissible in final 2 days
- Silently fails if API call fails (banner simply does not show)

### 10.2 CheckoutPage

**File:** `resources/js/views/Auth/CheckoutPage.vue` (178 lines)

**Purpose:** Checkout page for converting trial users to paid subscriptions. Has two states:
1. **"Coming Soon"** state (when `payment_enabled` is false) -- shows informational message with back-to-dashboard link
2. **Checkout** state (when `payment_enabled` is true) -- shows order summary and initialises Revolut payment widget

**Layout:** Uses `AppLayout`

**Data:** `trialData`, `loading`, `error`, `paymentEnabled`

**Computed:**
| Property | Logic |
|----------|-------|
| `planName` | Capitalised plan from trialData |
| `formattedPrice` | Converts pence to GBP display (e.g. "GBP 10.99/month") using `amount / 100` |

**Behaviour:**
- On mount, fetches trial status; if `payment_enabled` and trialData exist, calls `initCheckout()`
- `initCheckout()` calls `POST /api/payment/create-order`, receives `public_id`, then invokes the Revolut Checkout SDK's `payWithPopup()` method
- On success: redirects to `/dashboard?payment=success`
- On error: shows error message with retry button
- On cancel: does nothing (user stays on page)

### 10.3 PricingPage

**File:** `resources/js/views/Public/PricingPage.vue` (268 lines)

**Purpose:** Public-facing pricing page showing three plan tiers with monthly/yearly toggle. Does not require authentication.

**Layout:** Uses `PublicLayout`

**Data:** `isYearly` (boolean, default: `true`)

**Plans displayed:**

| Plan | Monthly | Yearly | Yearly Savings | Features |
|------|---------|--------|----------------|----------|
| Student | GBP 3.99/mo | GBP 30/yr (GBP 2.50/mo) | 37% | Budgeting, debt tracking, basic investment tracking, goal setting |
| Standard | GBP 10.99/mo | GBP 100/yr (GBP 8.33/mo) | 24% | All platform capabilities, protection/savings/investments, retirement & estate, spouse linking, 1 doc upload/day, 5/month |
| Pro | GBP 19.99/mo | GBP 200/yr (GBP 16.67/mo) | 17% | Everything in Standard, unlimited document uploads, priority support |

Standard is marked "Most Popular" with a highlighted border.

**Behaviour:**
- Each "Start Free Trial" button calls `startTrial(plan)` which navigates to `/register?plan={plan}&billing={yearly|monthly}`
- The Register page captures these query params and passes them through to the registration API

---

## 11. Frontend Routing

**File:** `resources/js/router/index.js`

| Path | Name | Component | Meta | Auth Required |
|------|------|-----------|------|---------------|
| `/pricing` | Pricing | `PricingPage.vue` | `{ public: true }` | No |
| `/checkout` | Checkout | `CheckoutPage.vue` | `{ requiresAuth: true }` | Yes |

The `/pricing` route is also listed in the `publicRoutes` array in the router guard, so it is accessible without authentication.

---

## 12. Cross-Module Integration

### 12.1 Registration Flow Integration

**File:** `app/Http/Controllers/Api/AuthController.php`

- `AuthController` constructor injects `TrialService`
- `PendingRegistration` model stores `plan` and `billing_cycle` (passed from frontend during registration)
- On `verifyCode()` (email verification success), if the pending registration has a valid plan (`student`, `standard`, `pro`), calls `$this->trialService->startTrial($user, $plan, $billingCycle)`
- Defaults billing_cycle to `'yearly'` if not specified or invalid

### 12.2 User Plan State

The `users.plan` column serves as a denormalised indicator of the user's current plan:
- Set to the plan name (e.g. `standard`) when a trial starts or payment completes
- Reverted to `free` when a trial expires (via `TrialService::expireTrials()`)
- Updated to the subscription plan on successful payment (via webhook `handleOrderCompleted`)

### 12.3 Admin Dashboard

**File:** `app/Http/Controllers/Api/AdminController.php`

The `getSubscriptionStats()` endpoint provides aggregate statistics:
- Count of trialing subscriptions
- Count of active subscriptions
- Count of expired subscriptions
- Count of cancelled subscriptions
- Total revenue (sum of all completed payment amounts)

Admin user list eager-loads `subscription` and `payments` relationships.

### 12.4 Preview Mode Bypass

- `CheckSubscription` middleware explicitly bypasses preview users (`$user->is_preview_user`)
- `TrialCountdownBanner` only renders for authenticated non-preview users (`v-if="isAuthenticated && !isPreviewMode"`)
- Revolut webhook route is excluded from `PreviewWriteInterceptor` middleware

---

## 13. Middleware

### 13.1 CheckSubscription

**File:** `app/Http/Middleware/CheckSubscription.php` (41 lines)

**Registration:** The middleware class exists but is NOT registered as a named middleware alias in `app/Http/Kernel.php`. Based on the SharedInfrastructure docs, it appears in the middleware pipeline documentation but may be applied at the route group level or pending registration.

**Logic flow:**

```
1. Is payment_enabled config false? -> PASS (let everyone through)
2. No authenticated user? -> PASS
3. User is preview_user? -> PASS (bypass subscription checks)
4. User has active plan (hasActivePlan()) OR on trial (onTrial())? -> PASS
5. Otherwise -> 403 JSON response:
   {
     "error": "subscription_required",
     "message": "Your trial has expired. Please upgrade to continue."
   }
```

**Feature flag:** When `PAYMENT_ENABLED=false` (the current default), the middleware passes all requests through, effectively disabling subscription enforcement entirely.

---

## 14. Scheduled Tasks

Two scheduled commands manage the trial lifecycle. See **ConsoleCommands.md Sections 2-3** for full implementation details.

| Command | Schedule | Purpose |
|---------|----------|---------|
| `trials:expire` | Daily 00:05 | Marks overdue trials as expired via `TrialService::expireTrials()` |
| `trials:send-reminders` | Daily 09:00 | Sends reminder emails at 3, 2, and 1 days before trial expiry |

---

## 15. API Routing

**File:** `routes/api.php`

### 15.1 Payment Routes (authenticated)

All routes require `auth:sanctum` middleware.

| Method | Path | Controller Method | Description |
|--------|------|-------------------|-------------|
| POST | `/api/payment/create-order` | `PaymentController@createOrder` | Create Revolut order for user's subscription |
| GET | `/api/payment/order/{id}/status` | `PaymentController@orderStatus` | Check Revolut order status |
| GET | `/api/payment/trial-status` | `PaymentController@trialStatus` | Get user's trial/subscription info |

### 15.2 Webhook Route (public)

| Method | Path | Controller Method | Description |
|--------|------|-------------------|-------------|
| POST | `/api/webhooks/revolut` | `PaymentWebhookController@handle` | Revolut webhook (HMAC-verified, no auth middleware) |

Note: The webhook route is listed in `PreviewWriteInterceptor::EXCLUDED_ROUTES` to prevent it from being blocked for preview users.

### 15.3 Admin Route (authenticated + admin)

| Method | Path | Controller Method | Description |
|--------|------|-------------------|-------------|
| GET | `/api/admin/subscriptions/stats` | `AdminController@getSubscriptionStats` | Subscription/revenue statistics |

---

## 16. Email Notifications

### 16.1 TrialExpirationReminder

**Mailable:** `app/Mail/TrialExpirationReminder.php` (46 lines)

**Constructor:** `(User $user, int $daysRemaining)`

**From:** `noreply@fynla.org` (name: "Fynla")

**Subject:**
- If 1 day remaining: "Your Fynla trial ends tomorrow"
- Otherwise: "Your Fynla trial ends in {N} days"

**View:** `resources/views/emails/trial-expiration-reminder.blade.php` (166 lines)

**Template variables:** `$user`, `$daysRemaining`, `$planName` (ucfirst of user's plan)

**Template content:**
- Greeting with user's first name
- Blue info box showing days remaining countdown
- Red warning box listing features that will be lost (financial planning tools, protection/savings/investment tracking, retirement & estate planning, document uploads)
- "Upgrade Now" CTA button linking to `{app.url}/checkout`
- Sign-off from "The Fynla Team (Chris & Brett)" with logo
- Footer with copyright and support email link

---

## 17. Known Issues and Limitations

### 17.1 Duplicated Pricing Constants
`PLAN_PRICING` is defined in both `RevolutService` and `TrialService` as separate `private const` arrays. If pricing changes, both must be updated independently. No shared pricing config or constant exists.

### 17.2 No Recurring Billing
The Revolut integration creates one-time orders, not recurring subscriptions. There is no automated renewal mechanism. When `current_period_end` passes, no automatic charge or expiry occurs. The system handles the initial payment conversion from trial to active, but subsequent billing cycles would require manual intervention or additional automation.

### 17.3 CheckSubscription Middleware Not Registered
The `CheckSubscription` middleware class exists but does not appear to be registered as a middleware alias in `app/Http/Kernel.php`. It is not applied to any route groups in `routes/api.php` via a named alias or direct class reference. It may be applied elsewhere or pending integration.

### 17.4 No Cancellation Flow
There is no endpoint or UI for users to cancel their subscription. The `cancelled` status exists in the database enum but no code path sets it.

### 17.5 No Plan Upgrade/Downgrade
There is no mechanism for changing plans (e.g. Student to Standard) or billing cycles after the initial selection. The plan is locked at registration time.

### 17.6 Single Subscription Per User
The `User` model has a `HasOne` relationship to `Subscription`. If a user's trial expires and they want to re-subscribe, the existing expired subscription record would need to be handled (potentially soft-deleted or updated in place). No re-subscription flow exists.

### 17.7 No Payment Success Handling on Dashboard
The CheckoutPage redirects to `/dashboard?payment=success` after a successful Revolut payment, but there is no code in the Dashboard component that reads or reacts to this query parameter (e.g. showing a success toast).

### 17.8 Revolut SDK Loading
The CheckoutPage checks for `typeof RevolutCheckout !== 'undefined'` but the Revolut checkout SDK script tag is not visible in the component. It must be loaded externally (likely in the HTML head or via a CDN script).

### 17.9 User trial_ends_at Denormalisation
`trial_ends_at` is stored on both the `users` table and `subscriptions` table. The User copy is set by `TrialService::startTrial()` but is never cleared or updated when the trial expires (only `users.plan` is set to `'free'`).

---

## 18. Deep Dive: Trial Lifecycle

### Phase 1: Plan Selection (Unauthenticated)

1. User visits `/pricing` (PricingPage)
2. User selects monthly/yearly billing toggle
3. User clicks "Start Free Trial" on a plan (Student, Standard, or Pro)
4. `startTrial(plan)` navigates to `/register?plan={plan}&billing={monthly|yearly}`

### Phase 2: Registration

5. Register page (`Register.vue`) captures `plan` and `billing` from query params
6. User fills registration form and submits
7. Frontend sends `POST /api/auth/register` with `plan` and `billing_cycle` in payload
8. `AuthController::register()` stores data in `PendingRegistration` (including `plan`, `billing_cycle`)
9. Verification code email is sent

### Phase 3: Verification & Trial Start

10. User enters verification code
11. `AuthController::verifyCode()` is called
12. If `$pending->plan` is valid (`student`, `standard`, or `pro`):
    - `$this->trialService->startTrial($user, $plan, $billingCycle)` is called
    - Creates `Subscription` record with `status = 'trialing'`, `trial_started_at = now`, `trial_ends_at = now + 7 days`
    - Updates `users.plan` to the selected plan
    - Updates `users.trial_ends_at` to the subscription's trial end date
13. User is logged in and redirected to onboarding/dashboard

### Phase 4: Active Trial (Days 1-5)

14. Every authenticated page load renders `TrialCountdownBanner` (via `AppLayout`)
15. Banner calls `GET /api/payment/trial-status` and displays countdown
16. Banner is dismissible during this period (more than 2 days remaining)

### Phase 5: Trial Reminders (Days 5-7)

17. At 09:00 daily, `trials:send-reminders` runs
18. **Day 5** (3 days before expiry): First reminder email sent, logged to `trial_reminder_log`
19. **Day 6** (2 days before expiry): Second reminder email sent. Banner becomes non-dismissible
20. **Day 7** (1 day before expiry): Final reminder email ("Your Fynla trial ends tomorrow")

### Phase 6: Trial Expiry

21. At 00:05 daily, `trials:expire` runs
22. Finds subscriptions with `status = 'trialing'` and `trial_ends_at` in the past
23. Sets `subscription.status = 'expired'`
24. Sets `users.plan = 'free'`
25. If `CheckSubscription` middleware is active (payment_enabled = true), user receives 403 on protected API calls with message "Your trial has expired. Please upgrade to continue."

### Phase 7: Payment Conversion (Upgrade)

26. User clicks "Upgrade Now" (from banner or email) -> navigates to `/checkout`
27. CheckoutPage fetches trial status
28. If `payment_enabled` is false: shows "Payment Coming Soon" message
29. If `payment_enabled` is true:
    - Calls `POST /api/payment/create-order`
    - Controller creates Revolut order via `RevolutService::createOrder()`
    - Stores `revolut_order_id` on subscription
    - Returns `public_id` to frontend
30. Frontend invokes `RevolutCheckout(public_id).payWithPopup()`
31. User completes payment in Revolut popup

### Phase 8: Payment Confirmation

32. Revolut sends `ORDER_COMPLETED` webhook to `POST /api/webhooks/revolut`
33. `PaymentWebhookController::handle()` verifies HMAC signature
34. `handleOrderCompleted()`:
    - Finds subscription by `revolut_order_id`
    - Sets `subscription.status = 'active'`
    - Sets `current_period_start = now`, `current_period_end = now + 1 month/year`
    - Creates `Payment` record with `status = 'completed'`
    - Updates `users.plan` to the subscription plan
35. Frontend receives `onSuccess` callback from Revolut widget
36. User is redirected to `/dashboard?payment=success`

### Alternative: Payment Failure

32b. Revolut sends `ORDER_PAYMENT_FAILED` webhook
33b. `handlePaymentFailed()` sets `subscription.status = 'past_due'`
34b. Frontend receives `onError` callback, shows error message with retry option

---

## 19. Configuration Reference

### Environment Variables

| Variable | Config Path | Default | Description |
|----------|-------------|---------|-------------|
| `PAYMENT_ENABLED` | `app.payment_enabled` | `false` | Master feature flag for subscription enforcement |
| `REVOLUT_API_KEY` | `services.revolut.api_key` | `''` | Revolut Merchant API key |
| `REVOLUT_WEBHOOK_SECRET` | `services.revolut.webhook_secret` | `''` | HMAC secret for webhook verification |
| `REVOLUT_SANDBOX` | `services.revolut.sandbox` | `true` | Whether to use Revolut sandbox API |

### Config Files

- `config/app.php` -- contains `payment_enabled` key
- `config/services.php` -- contains `revolut` section with `api_key`, `webhook_secret`, `sandbox`

---

## 20. File Inventory

| File | Lines | Purpose |
|------|-------|---------|
| `app/Models/Subscription.php` | 87 | Subscription model |
| `app/Models/Payment.php` | 31 | Payment model |
| `app/Services/Payment/RevolutService.php` | 84 | Revolut Merchant API integration |
| `app/Services/Payment/TrialService.php` | 59 | Trial start and expiry |
| `app/Http/Controllers/Api/PaymentController.php` | 70 | Payment API endpoints |
| `app/Http/Controllers/Api/PaymentWebhookController.php` | 126 | Revolut webhook handler |
| `app/Http/Middleware/CheckSubscription.php` | 41 | Subscription enforcement middleware |
| `app/Mail/TrialExpirationReminder.php` | 46 | Trial reminder mailable |
| `app/Console/Commands/ExpireTrials.php` | 24 | Artisan command to expire trials |
| `app/Console/Commands/SendTrialReminderEmails.php` | 73 | Artisan command for reminder emails |
| `resources/views/emails/trial-expiration-reminder.blade.php` | 166 | Email template |
| `resources/js/components/Trial/TrialCountdownBanner.vue` | 114 | Trial countdown banner component |
| `resources/js/views/Auth/CheckoutPage.vue` | 178 | Checkout/upgrade page |
| `resources/js/views/Public/PricingPage.vue` | 268 | Public pricing page |
| `database/migrations/2026_02_12_100001_create_subscriptions_table.php` | 34 | Subscriptions migration |
| `database/migrations/2026_02_12_100002_create_payments_table.php` | 30 | Payments migration |
| `database/migrations/2026_02_12_100004_create_trial_reminder_log_table.php` | 27 | Trial reminder log migration |
