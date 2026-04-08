# Revolut Live: Auto-Renewing Subscriptions, Discount Codes & Invoicing

**Date:** 8 April 2026
**Branch:** `revolutLive`

---

## Context

The current payment system uses one-off Revolut orders. Users must manually re-pay when their subscription period expires. This causes churn. We need to switch to Revolut's native Subscription API which handles auto-renewal, plus add discount codes and proper PDF invoicing.

**Key discovery:** `revolut_customer_id` on `users` and `revolut_subscription_id` on `subscriptions` already exist (migration `2026_02_24_100002`) but are unused. We build on these.

---

## Revolut Subscription API — How It Works

From the OpenAPI spec (`merchant-2025-12-04.yaml`), here is the exact flow:

### Hierarchy
```
Subscription Plan
  └── Variations (e.g., monthly, yearly)
        └── Phases (sequential billing stages within a variation)
              - ordinal (1, 2, 3...)
              - cycle_duration (ISO 8601: P1M, P1Y, P7D)
              - cycle_count (null = indefinite, N = limited)
              - amount (pence)
              - currency (GBP)
```

### Plan Creation Example
```
POST /api/subscription-plans
{
  "name": "Fynla Standard Plan",
  "trial_duration": "P7D",          // 7-day trial (only days allowed: P{N}D)
  "variations": [
    {                                 // Variation 1: Monthly
      "phases": [{
        "ordinal": 1,
        "cycle_duration": "P1M",
        "amount": 1099,               // £10.99 in pence (launch price)
        "currency": "GBP"
      }]
    },
    {                                 // Variation 2: Yearly
      "phases": [{
        "ordinal": 1,
        "cycle_duration": "P1Y",
        "amount": 10000,              // £100.00 in pence (launch price)
        "currency": "GBP"
      }]
    }
  ]
}
```

**Response** returns `id` (plan UUID) and each variation gets an `id` (variation UUID) and each phase gets an `id`.

### Subscription Creation — The Setup Order Flow

```
POST /api/subscriptions
{
  "plan_variation_id": "<variation-uuid>",    // REQUIRED
  "customer_id": "<customer-uuid>",           // REQUIRED (must exist)
  "external_reference": "fynla_sub_123",      // Optional tracking ID
  "setup_order_redirect_url": "https://fynla.org/checkout?status=complete",  // Optional
  "trial_duration": "P7D"                     // Optional override (P0D = skip trial)
}
```

**Response:**
```json
{
  "id": "<subscription-uuid>",
  "state": "pending",
  "customer_id": "<customer-uuid>",
  "plan_id": "<plan-uuid>",
  "plan_variation_id": "<variation-uuid>",
  "payment_method_type": "automatic",
  "setup_order_id": "<order-uuid>",           // THIS IS THE KEY
  "current_cycle_id": "<cycle-uuid>",
  "created_at": "...",
  "updated_at": "..."
}
```

**Critical:** Revolut automatically creates a **setup order** (`setup_order_id`). We then:
1. `GET /api/orders/{setup_order_id}` to get the order `token`
2. Pass that `token` to our existing `embeddedCheckout()` widget
3. Customer completes payment — card is saved for future auto-charges
4. Subscription moves from `pending` → `active`
5. Revolut auto-charges on each billing cycle using the saved card

### Subscription States
| State | Description |
|-------|-------------|
| `pending` | Created, awaiting first payment (setup order) |
| `active` | Billing normally, auto-charges each cycle |
| `overdue` | Failed payment, awaiting resolution |
| `paused` | Temporarily paused |
| `cancelled` | Will not renew |
| `finished` | All cycles completed (limited-duration only) |

### Billing Cycles

Each cycle is a separate record with its own `order_id`:
```json
{
  "id": "<cycle-uuid>",
  "subscription_id": "<sub-uuid>",
  "number": 2,
  "state": "active",                          // pending | active | finished
  "start_date": "2025-07-05T21:00:00Z",
  "end_date": "2025-08-05T21:00:00Z",
  "order_id": "<order-uuid>",                 // Each cycle creates an order
  "trial": false
}
```

**This means ORDER_COMPLETED webhooks fire for every renewal cycle** — we can use our existing `handleOrderCompleted` logic but also need the 4 subscription webhooks.

### Subscription Webhooks
- `SUBSCRIPTION_INITIATED` — subscription became active
- `SUBSCRIPTION_OVERDUE` — payment failed
- `SUBSCRIPTION_CANCELLED` — subscription cancelled
- `SUBSCRIPTION_FINISHED` — all cycles completed

### Key API Endpoints
| Method | Endpoint | Purpose |
|--------|----------|---------|
| `POST` | `/api/customers` | Create customer (needed before subscription) |
| `POST` | `/api/subscription-plans` | Create plan with variations |
| `GET` | `/api/subscription-plans` | List plans |
| `GET` | `/api/subscription-plans/{id}` | Get plan details |
| `POST` | `/api/subscriptions` | Create subscription (returns setup_order_id) |
| `GET` | `/api/subscriptions/{id}` | Get subscription state |
| `PATCH` | `/api/subscriptions/{id}` | Update external_reference |
| `POST` | `/api/subscriptions/{id}/cancel` | Cancel subscription |
| `GET` | `/api/subscriptions/{id}/cycles` | List billing cycles |
| `GET` | `/api/subscriptions/{id}/cycles/{id}` | Get cycle details |
| `GET` | `/api/orders/{setup_order_id}` | Get setup order token for widget |

### What Revolut Does NOT Provide
- **Discount codes / coupons** — we build our own
- **Invoicing / PDFs** — we build our own
- **Trial management UI** — we already have this

---

## Implementation Plan

### Step 1: Database Migrations

**File: `database/migrations/2026_04_08_100001_create_discount_codes_table.php`**
```
discount_codes
  id                  bigint PK
  code                string(50) unique index (uppercase, alphanumeric)
  type                enum('percentage', 'fixed_amount', 'trial_extension')
  value               integer — % (1-100) OR pence amount OR extra trial days
  max_uses            integer nullable (null = unlimited)
  times_used          integer default 0
  max_uses_per_user   integer default 1
  applicable_plans    json nullable (null = all plans, e.g. ['student','family'])
  applicable_cycles   json nullable (null = both, e.g. ['yearly'])
  starts_at           timestamp nullable
  expires_at          timestamp nullable
  is_active           boolean default true
  created_by          foreignId('users') nullable
  timestamps, softDeletes
```

**File: `database/migrations/2026_04_08_100002_create_discount_code_usages_table.php`**
```
discount_code_usages
  id                  bigint PK
  discount_code_id    foreignId cascade
  user_id             foreignId cascade
  payment_id          foreignId nullable nullOnDelete
  applied_at          timestamp
  timestamps
  index: [discount_code_id, user_id]
```

**File: `database/migrations/2026_04_08_100003_create_invoices_table.php`**
```
invoices
  id                  bigint PK
  user_id             foreignId cascade
  payment_id          foreignId nullable nullOnDelete
  subscription_id     foreignId nullable nullOnDelete
  invoice_number      string(20) unique — FYN-INV-000001
  status              enum('draft', 'issued', 'void') default 'issued'
  subtotal_amount     integer (pence, before discount)
  discount_amount     integer default 0 (pence)
  tax_amount          integer default 0 (pence)
  total_amount        integer (pence)
  currency            string(3) default 'GBP'
  discount_code       string(50) nullable — snapshot of code used
  discount_description string(100) nullable — e.g. '20% off'
  plan_name           string(100)
  billing_cycle       string(10)
  period_start        date
  period_end          date
  next_renewal_date   date nullable
  issued_at           timestamp
  pdf_path            string(255) nullable
  billing_name        string(255) nullable
  billing_email       string(255) nullable
  timestamps, softDeletes
```

**File: `database/migrations/2026_04_08_100004_create_invoice_sequences_table.php`**
```
invoice_sequences
  id          bigint PK
  next_value  bigint default 1
```
Seed with single row. Use `lockForUpdate()` for atomic gap-free numbering.

**File: `database/migrations/2026_04_08_100005_add_subscription_and_discount_fields.php`**

On `payments`:
- `discount_code_id` foreignId nullable constrained nullOnDelete
- `discount_amount` integer default 0
- `invoice_id` foreignId nullable constrained nullOnDelete
- `revolut_subscription_payment` boolean default false

On `subscriptions`:
- `revolut_plan_id` string nullable (Revolut plan UUID)
- `revolut_plan_variation_id` string nullable (Revolut variation UUID)
- `auto_renew` boolean default true
- `payment_method_saved` boolean default false

---

### Step 2: Models

**New: `app/Models/DiscountCode.php`**
- Validation methods: `isValid()`, `isValidForPlan($slug)`, `isValidForCycle($cycle)`, `hasUsesRemaining()`, `userUsageCount(int $userId)`
- Calculation: `calculateDiscount(int $amountPence): int`
- Relationships: `usages(): HasMany`, `creator(): BelongsTo`

**New: `app/Models/DiscountCodeUsage.php`**
- Simple model: `discount_code_id`, `user_id`, `payment_id`, `applied_at`

**New: `app/Models/Invoice.php`**
- Relationships: `user()`, `payment()`, `subscription()`
- Methods: `generateNumber()` (atomic sequence via `invoice_sequences` table)
- Accessors: `getFormattedTotalAttribute()` (pence → pounds)

**Modified: `app/Models/Subscription.php`**
- Add to `$fillable`: `revolut_plan_id`, `revolut_plan_variation_id`, `auto_renew`, `payment_method_saved`
- Add to `$casts`: `auto_renew => boolean`, `payment_method_saved => boolean`
- Add: `invoices(): HasMany`

**Modified: `app/Models/Payment.php`**
- Add to `$fillable`: `discount_code_id`, `discount_amount`, `invoice_id`, `revolut_subscription_payment`
- Add to `$casts`: `discount_amount => integer`, `revolut_subscription_payment => boolean`
- Add: `discountCode(): BelongsTo`, `invoice(): BelongsTo`

---

### Step 3: Backend Services

#### 3a. `app/Services/Payment/RevolutSubscriptionService.php` (NEW)

Uses same auth (`Bearer sk_...`) and API URL as `RevolutService`.

```php
// ── Customer Management ──
createCustomer(User $user): array
  // POST /customers { email, full_name }
  // Stores revolut_customer_id on User. Idempotent (returns existing if set).

getCustomer(string $customerId): array
  // GET /customers/{id}

// ── Subscription Plan Management ──
createSubscriptionPlan(SubscriptionPlan $plan): array
  // POST /subscription-plans
  // Creates plan with 2 variations (monthly + yearly)
  // trial_duration: "P7D" (only days allowed, pattern: ^P[0-9]+D$)
  // Monthly variation: phases[{ordinal:1, cycle_duration:"P1M", amount:launchPrice, currency:"GBP"}]
  // Yearly variation: phases[{ordinal:1, cycle_duration:"P1Y", amount:launchPrice, currency:"GBP"}]
  // cycle_count: null (indefinite billing — no auto-stop)
  // Returns plan with plan.id, variation[].id, variation[].phases[].id

getSubscriptionPlan(string $planId): array
  // GET /subscription-plans/{id}
  // Returns: {id, name, trial_duration, state, created_at, updated_at, variations[]}

getSubscriptionPlans(): array
  // GET /subscription-plans (paginated: limit, from, to, page_token)
  // Returns: {next_page_token, subscription_plans[]}
  // Needed by revolut:sync-plans to check existing plans before creating

// ── Subscription Management ──
createSubscription(User $user, string $planVariationId, string $redirectUrl, ?string $trialDuration = null): array
  // POST /subscriptions with Idempotency-Key header (prevents duplicate subscriptions)
  // Body: { plan_variation_id, customer_id, setup_order_redirect_url, external_reference, trial_duration }
  // trial_duration: "P7D" (uses plan default), "P0D" (skip trial), "P30D" (promotional override)
  // Returns: { id, state:"pending", setup_order_id, customer_id, plan_id, plan_variation_id,
  //            payment_method_type:"automatic", current_cycle_id, created_at, updated_at }

getSubscription(string $subscriptionId): array
  // GET /subscriptions/{id}
  // Returns full state: id, external_reference, state, customer_id, plan_id, plan_variation_id,
  //   payment_method_type (automatic|manual), payment_method_id, created_at, updated_at,
  //   start_date (null until active), current_cycle_id, trial_duration, trial_end_date, setup_order_id

getSubscriptions(?string $externalReference = null): array
  // GET /subscriptions (paginated: limit, from, to, page_token, external_reference)
  // Returns: {next_page_token, subscriptions[]}
  // Needed by overdue check command and for admin lookups / reconciliation

updateSubscription(string $subscriptionId, string $externalReference): array
  // PATCH /subscriptions/{id} with { external_reference }
  // Only works in states: pending, active, overdue, paused
  // Cannot modify in: cancelled, finished (these are final states)
  // Returns full subscription object

cancelSubscription(string $subscriptionId): void
  // POST /subscriptions/{id}/cancel — returns 204 No Content (empty body)
  // Can cancel in any state EXCEPT cancelled or finished
  // Marks as cancelled, no further billing cycles created, pending orders cancelled

// ── Billing Cycle Management ──
getSubscriptionCycles(string $subscriptionId): array
  // GET /subscriptions/{id}/cycles (paginated: limit, from, to, page_token)
  // Returns: {next_page_token, cycles[]}
  // Each cycle: {id, subscription_id, plan_variation_id, plan_variation_phase_id,
  //   number, previous_cycle_id, state (pending|active|finished), start_date, end_date,
  //   order_id, trial (bool)}
  // CRITICAL: each non-trial cycle has an order_id — ORDER_COMPLETED webhook fires per renewal

getSubscriptionCycle(string $subscriptionId, string $cycleId): array
  // GET /subscriptions/{id}/cycles/{cycle_id}
  // Returns single cycle with same fields as above
```

#### 3b. `app/Services/Payment/DiscountCodeService.php` (NEW)

```php
validate(string $code, int $userId, string $planSlug, string $billingCycle, int $amountPence): array
  // Returns: {valid, message, discount, discount_amount, final_amount}
  // Checks: exists, is_active, not expired, date range, uses remaining,
  //         per-user limit, applicable plan/cycle, minimum amount

apply(DiscountCode $discount, int $userId, int $paymentId, int $originalAmountPence): int
  // Records usage, increments times_used, returns discounted amount

calculateDiscount(DiscountCode $discount, int $amountPence): int
  // percentage: round(amount * value / 100)
  // fixed_amount: min(value, amount)
  // trial_extension: 0 (no price change)
```

#### 3c. `app/Services/Payment/InvoiceService.php` (NEW)

```php
generateInvoice(Payment $payment, ?DiscountCode $discount = null): Invoice
  // 1. Atomic sequence number via invoice_sequences lockForUpdate()
  // 2. Format: FYN-INV-000001
  // 3. Snapshot all data (plan, amount, discount, period, renewal date)
  // 4. Generate PDF
  // 5. Store to storage/app/invoices/{userId}/{invoiceNumber}.pdf

generatePdf(Invoice $invoice): string
  // DomPDF renders resources/views/invoices/pdf.blade.php
  // Returns storage path

emailInvoice(Invoice $invoice, User $user): void
  // Dispatches InvoiceEmail mailable with PDF attachment
```

**Dependency:** `composer require barryvdh/laravel-dompdf` (pure PHP, no external binaries — works on SiteGround)

#### 3d. `app/Services/Payment/SubscriptionRenewalService.php` (NEW)

Handles subscription lifecycle from webhooks and renewal order completions:

```php
handleRenewalPayment(string $orderId, array $orderData): void
  // Find payment by revolut_order_id (cycle orders)
  // Or find subscription via cycle lookup
  // Create Payment record (revolut_subscription_payment = true)
  // Update subscription period dates
  // Generate invoice, email invoice

handleSubscriptionOverdue(array $subscriptionData): void
  // Set status 'past_due'
  // Send PaymentFailedNotification email

handleSubscriptionCancelled(array $subscriptionData): void
  // Set status 'cancelled'
  // Set data_retention_starts_at after current_period_end passes

handleSubscriptionFinished(array $subscriptionData): void
  // Mark completed (shouldn't happen for indefinite plans, but handle gracefully)
```

#### 3e. Modify `app/Services/Payment/RevolutService.php`

Add one method:
```php
createOrderWithCustomer(int $amount, string $currency, string $description,
    string $redirectUrl, string $customerId, ?string $merchantRef = null,
    ?string $email = null, bool $savePaymentMethod = false): array
  // Like createOrder() but adds:
  //   'customer_id' => $customerId
  //   'save_payment_method_for' => $savePaymentMethod ? 'merchant' : null
```

---

### Step 4: Controller Changes

#### 4a. `app/Http/Controllers/Api/PaymentController.php`

**New endpoint: `POST /api/payment/validate-discount`**
```php
validateDiscountCode(Request $request): JsonResponse
  // Input: {code, plan, billing_cycle}
  // Returns: {valid, message, discount_amount, final_amount, discount_type, discount_description}
```

**New endpoint: `GET /api/payment/invoices/{invoice}/download`**
```php
downloadInvoice(Invoice $invoice): Response
  // Validates invoice belongs to auth user
  // Returns PDF file from storage
```

**Modified: `createOrder()` — MAJOR REWRITE**

New flow using Revolut Subscription API:
1. Accept optional `discount_code` in request
2. If discount code provided, validate via `DiscountCodeService`
3. If `trial_extension` type, extend trial and return early
4. Create Revolut customer if not exists
5. **Create Revolut subscription** via `RevolutSubscriptionService::createSubscription()`
   - This returns `setup_order_id`
6. **Get setup order** via `RevolutService::getOrder(setup_order_id)` to get the `token`
7. Create pending Payment record with `revolut_order_id = setup_order.id`
8. Store `revolut_subscription_id` and `revolut_plan_variation_id` on subscription
9. Return `{token: setup_order.token, order_id: setup_order.id}` — **same shape as current response**
10. Existing `embeddedCheckout()` widget works unchanged with this token

**Modified: `confirmPayment()`**
- After successful activation: generate invoice, email invoice
- Apply discount code usage if applicable
- Set `auto_renew = true`, `payment_method_saved = true`

**Modified: `cancelSubscription()`**
- If `revolut_subscription_id` exists: call `RevolutSubscriptionService::cancelSubscription()`
- Existing logic unchanged: access continues until `current_period_end`

**Modified: `billingHistory()`**
- Add `invoice_id`, `has_invoice` flag per payment
- Add `discount_applied` info

**Modified: `trialStatus()`**
- Add `auto_renew`, `next_renewal_date` (= `current_period_end`)

#### 4b. `app/Http/Controllers/Api/WebhookController.php`

Add subscription event handling:
```php
match ($event) {
    'ORDER_COMPLETED', 'ORDER_AUTHORISED' => $this->handleOrderCompleted(...),
    'SUBSCRIPTION_INITIATED' => $this->handleSubscriptionInitiated($payload),
    'SUBSCRIPTION_OVERDUE'   => $this->handleSubscriptionOverdue($payload),
    'SUBSCRIPTION_CANCELLED' => $this->handleSubscriptionCancelled($payload),
    'SUBSCRIPTION_FINISHED'  => $this->handleSubscriptionFinished($payload),
};
```

**ORDER_COMPLETED for renewal cycles:** Revolut creates an order for each billing cycle. When that order completes, we get an ORDER_COMPLETED webhook. We detect renewal payments by checking if the payment is linked to a subscription with `revolut_subscription_id`. The webhook handler creates a new Payment record, updates period dates, and generates an invoice.

---

#### 4c. `app/Http/Controllers/Api/AdminController.php`

Add discount code CRUD methods (follows existing admin pattern — defence-in-depth admin check, try-catch with `safeErrorResponse`):

```php
listDiscountCodes(): JsonResponse
  // Returns all discount codes with usage counts
  // Includes: code, type, value, max_uses, times_used, is_active, starts_at, expires_at,
  //           applicable_plans, applicable_cycles, created_at
  // Ordered by created_at desc

createDiscountCode(Request $request): JsonResponse
  // Validates: code (required, unique, uppercase alpha-numeric), type (required, enum),
  //   value (required, integer, min:1), max_uses (nullable, integer, min:1),
  //   max_uses_per_user (integer, min:1, default:1),
  //   applicable_plans (nullable, array of valid plan slugs),
  //   applicable_cycles (nullable, array: monthly/yearly),
  //   starts_at (nullable, date), expires_at (nullable, date, after:starts_at),
  //   is_active (boolean, default:true)
  // Auto-uppercases the code
  // Returns created discount code

updateDiscountCode(Request $request, int $id): JsonResponse
  // Same validation as create (except code uniqueness checks exclude self)
  // Returns updated discount code

deleteDiscountCode(int $id): JsonResponse
  // Soft-deletes the discount code
  // Returns success message

toggleDiscountCode(int $id): JsonResponse
  // Flips is_active boolean
  // Returns updated code with new status
```

---

### Step 5: Routes

Add to `routes/api.php` inside the `payment` group:
```php
Route::post('/validate-discount', [PaymentController::class, 'validateDiscountCode'])->middleware('throttle:20,1');
Route::get('/invoices/{invoice}/download', [PaymentController::class, 'downloadInvoice'])->middleware('throttle:10,1');
```

Add to `routes/api.php` inside the existing `admin` group:
```php
// Discount Code Management
Route::get('/discount-codes', [AdminController::class, 'listDiscountCodes']);
Route::post('/discount-codes', [AdminController::class, 'createDiscountCode']);
Route::put('/discount-codes/{id}', [AdminController::class, 'updateDiscountCode']);
Route::delete('/discount-codes/{id}', [AdminController::class, 'deleteDiscountCode']);
Route::patch('/discount-codes/{id}/toggle', [AdminController::class, 'toggleDiscountCode']);
```

---

### Step 6: Frontend Changes

#### 6a. `resources/js/views/Auth/CheckoutPage.vue`

**Add discount code section** in Order Summary (left column):
- Text input + "Apply" button
- Success (spring) / error (raspberry) feedback
- When applied: original price (strikethrough), discount line, final price
- Calls `POST /api/payment/validate-discount`
- Passes `discount_code` in `createOrder` callback POST body

**Add auto-renewal notice** below payment widget:
- "Your subscription will automatically renew each [month/year]. You can cancel at any time from your profile."

**No changes to widget initialisation** — the `token` returned from our backend (now from the setup order) works identically with `embeddedCheckout()`.

#### 6b. `resources/js/components/UserProfile/SubscriptionManagement.vue`

**Active state:**
- Show "Auto-renewal: Active" with next renewal date
- "Your next payment of [amount] will be taken on [date]"

**Billing history table:**
- Add "Invoice" column with download link (calls `/api/payment/invoices/{id}/download`)

**Cancelled state:**
- "Auto-renewal has been cancelled. You retain access until [date]."

**Past due state:**
- "We were unable to process your automatic renewal payment. We will retry automatically."

#### 6c. `resources/js/components/Payment/PlanSelectionModal.vue`

- "Have a discount code?" collapsible section with input
- Pass code through `@select` event payload

#### 6d. Admin Panel — Discount Codes Tab

**Modify: `resources/js/views/Admin/AdminPanel.vue`**
- Add `{ id: 'discount-codes', label: 'Discount Codes', shortLabel: 'Codes' }` to `navItems`
- Add discount/tag icon to `getTabIcon()` method
- Add `<DiscountCodes v-if="activeTab === 'discount-codes'" />` to template
- Import + register `DiscountCodes` component

**New: `resources/js/components/Admin/DiscountCodes.vue`** (follows AdminRetirementActions pattern)

Table view with CRUD:
- **Header**: title "Discount Codes" + "Add Code" button (raspberry CTA)
- **Table columns**: Code | Type | Value | Uses (used/max) | Status | Valid Period | Actions
  - **Code**: displayed in monospace, uppercase (e.g., `LAUNCH20`)
  - **Type**: badge — "Percentage" (violet), "Fixed Amount" (horizon), "Trial Extension" (spring)
  - **Value**: shows "20%" for percentage, "£10.00" for fixed_amount, "14 days" for trial_extension
  - **Uses**: "45 / 500" or "12 / unlimited"
  - **Status**: toggle switch (spring active, neutral inactive)
  - **Valid Period**: "1 Apr — 30 Jun 2026" or "No expiry"
  - **Actions**: Edit (pencil icon), Delete (trash icon with confirm dialog)
- **Empty state**: "No discount codes yet. Create your first code to offer promotions."
- **Table header bg**: `bg-savannah-100`, row hover: `hover:bg-savannah-50`
- **Loading**: standard spinner
- **Success/error messages**: auto-dismiss after 3 seconds

**New: `resources/js/components/Admin/DiscountCodeModal.vue`**

Form modal for create/edit (emits `save`, parent handles API):
- **Code**: text input, auto-uppercased, alphanumeric only (no spaces/special chars)
- **Discount type**: button group selector — Percentage / Fixed Amount / Trial Extension
- **Value**: number input
  - Label changes based on type: "Discount (%)" / "Discount (pence)" / "Extra trial days"
  - Validation: percentage 1-100, fixed min 1, trial min 1
- **Maximum uses**: number input (optional, blank = unlimited)
- **Uses per user**: number input (default 1)
- **Applicable plans**: multi-select checkboxes — Student, Standard, Family, Pro (blank = all)
- **Applicable billing cycles**: checkboxes — Monthly, Yearly (blank = both)
- **Valid from**: date input (optional)
- **Valid until**: date input (optional, must be after valid from)
- **Active**: toggle (default on)
- **Buttons**: Cancel (neutral) + Save (raspberry)

**Modify: `resources/js/services/adminService.js`**

Add methods:
```javascript
getDiscountCodes() { return api.get('/admin/discount-codes'); },
createDiscountCode(data) { return api.post('/admin/discount-codes', data); },
updateDiscountCode(id, data) { return api.put(`/admin/discount-codes/${id}`, data); },
deleteDiscountCode(id) { return api.delete(`/admin/discount-codes/${id}`); },
toggleDiscountCode(id) { return api.patch(`/admin/discount-codes/${id}/toggle`); },
```

---

### Step 7: Email Templates & Invoice PDF

**New mailables:**
- `app/Mail/InvoiceEmail.php` + `resources/views/emails/invoice.blade.php`
  - Subject: "Your Fynla invoice — FYN-INV-XXXXXX"
  - Inline summary + PDF attachment + download CTA
- `app/Mail/PaymentFailedNotification.php` + `resources/views/emails/payment-failed.blade.php`
  - Subject: "Action required — payment issue with your Fynla subscription"
  - Explains auto-retry, link to update payment method

**Invoice PDF template:**
- `resources/views/invoices/pdf.blade.php`
- Fynla logo, invoice number, date, customer details
- Line item: plan name, billing period, unit price, discount (if any), total
- Next renewal date
- Footer with support email

---

### Step 8: Seeders & Commands

**`database/seeders/DiscountCodeSeeder.php`** (NEW)
```
LAUNCH20  — 20% off, max 500, expires 3 months, all plans
FYNLA10   — £10 off (1000 pence), unlimited uses, 1 per user
TRYME     — 14 extra trial days, max 200
```

**`app/Console/Commands/SyncRevolutPlans.php`** (NEW)
```
php artisan revolut:sync-plans
```
Creates/updates Revolut subscription plans for each of our 4 SubscriptionPlans. Stores the Revolut plan_id and variation IDs. Run once on deploy, then when pricing changes.

**`app/Console/Commands/CheckOverdueSubscriptions.php`** (NEW)
```
php artisan subscriptions:check-overdue
```
Safety net: checks active subscriptions past `current_period_end`, queries Revolut API. Schedule `dailyAt('01:00')`.

---

### Step 9: Migration Path for Existing Subscribers

**Strategy: Graceful hybrid — no forced migration.**

1. All new columns have safe defaults — no breaking changes
2. Existing active subscribers (no `revolut_subscription_id`) continue on old one-off flow
3. When their period expires and they re-subscribe, the new flow kicks in:
   - Creates Revolut customer
   - Creates Revolut subscription (with setup order)
   - Card saved, auto-renewal from that point forward
4. Backend checks `revolut_subscription_id` before calling subscription API. If null, falls back to one-off order flow.
5. `SubscriptionRenewalReminder` email updated to mention auto-renewals

---

### Step 10: Verification

**Local testing:**
1. `php artisan migrate && php artisan db:seed`
2. `php artisan revolut:sync-plans` (sandbox mode)
3. Test checkout with discount code LAUNCH20
4. Test checkout without discount code
5. Test TRYME trial extension code
6. Verify invoice PDF in `storage/app/invoices/`
7. Verify invoice download endpoint
8. Test cancellation cancels Revolut subscription
9. Verify existing subscribers still work on old flow

**Browser testing (Playwright — MANDATORY):**
- Fill discount code, click Apply, verify price updates
- Submit with invalid code, verify error
- Complete payment with test card `4929 4205 7359 5709`
- Verify success modal + dashboard redirect
- Profile > Subscription Management: verify auto-renewal date shown
- Click invoice download, verify PDF
- Cancel subscription flow
- **Admin panel: navigate to Discount Codes tab**
- **Create a new code (fill all fields, save, verify appears in table)**
- **Edit existing code (change value, save, verify updated)**
- **Toggle code active/inactive (verify status changes)**
- **Delete a code (confirm dialog, verify removed)**
- Test from step 1 after any fix

**Pest tests:**
- `DiscountCodeServiceTest` — all validation scenarios
- `InvoiceServiceTest` — sequential numbering, PDF gen
- `WebhookSubscriptionEventsTest` — 4 subscription events + renewal ORDER_COMPLETED
- `DiscountCodeValidationTest` — endpoint + rate limiting
- `InvoiceDownloadTest` — auth, ownership, 404

---

## File Summary

### New files (~22):
| File | Purpose |
|------|---------|
| 5 migrations | discount_codes, discount_code_usages, invoices, invoice_sequences, add fields |
| `app/Models/DiscountCode.php` | Discount code model + validation |
| `app/Models/DiscountCodeUsage.php` | Usage tracking pivot |
| `app/Models/Invoice.php` | Invoice model + numbering |
| `app/Services/Payment/RevolutSubscriptionService.php` | Revolut Subscription API integration |
| `app/Services/Payment/DiscountCodeService.php` | Code validation + application |
| `app/Services/Payment/InvoiceService.php` | PDF generation + emailing |
| `app/Services/Payment/SubscriptionRenewalService.php` | Renewal lifecycle handling |
| `app/Mail/InvoiceEmail.php` + template | Invoice email with PDF |
| `app/Mail/PaymentFailedNotification.php` + template | Failed payment notification |
| `resources/views/invoices/pdf.blade.php` | Invoice PDF template |
| `resources/js/components/Admin/DiscountCodes.vue` | Admin tab: list, toggle, delete discount codes |
| `resources/js/components/Admin/DiscountCodeModal.vue` | Admin modal: create/edit discount codes |
| `database/seeders/DiscountCodeSeeder.php` | Initial discount codes |
| `app/Console/Commands/SyncRevolutPlans.php` | Sync plans to Revolut |
| `app/Console/Commands/CheckOverdueSubscriptions.php` | Safety net for missed webhooks |

### Modified files (~12):
| File | Changes |
|------|---------|
| `app/Models/Subscription.php` | New fillable/casts/relationships |
| `app/Models/Payment.php` | New fillable/casts/relationships |
| `app/Services/Payment/RevolutService.php` | Add `createOrderWithCustomer()` |
| `app/Http/Controllers/Api/PaymentController.php` | Subscription flow, discount endpoints, invoice download |
| `app/Http/Controllers/Api/AdminController.php` | Discount code CRUD (list, create, update, delete, toggle) |
| `app/Http/Controllers/Api/WebhookController.php` | 4 subscription webhook events + renewal handling |
| `routes/api.php` | Payment routes (2) + admin discount routes (5) |
| `resources/js/views/Admin/AdminPanel.vue` | Add Discount Codes tab to navItems + render |
| `resources/js/services/adminService.js` | Add discount code API methods |
| `resources/js/views/Auth/CheckoutPage.vue` | Discount code UI, auto-renewal notice |
| `resources/js/components/UserProfile/SubscriptionManagement.vue` | Invoice downloads, auto-renewal display |
| `resources/js/components/Payment/PlanSelectionModal.vue` | Discount code input |
| `database/seeders/DatabaseSeeder.php` | Add DiscountCodeSeeder |
| `composer.json` | Add `barryvdh/laravel-dompdf` |
