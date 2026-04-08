# Revolut Live — Implementation Task List

**Branch:** `revolutLive`
**Date:** 8 April 2026
**Plan:** `April/April8Updates/revolutLive-plan.md`

---

## Build Order

```
Phase 1 → Phase 2 → Phase 3a-3d (parallel) → Phase 4a-4c → Phase 5
→ Phase 6a-6d (parallel) + Phase 7 + Phase 8 → Phase 9 → Phase 10
```

---

## Phase 1: Database Migrations & Dependency

- [ ] `composer require barryvdh/laravel-dompdf`
- [ ] Create migration: `2026_04_08_100001_create_discount_codes_table.php`
- [ ] Create migration: `2026_04_08_100002_create_discount_code_usages_table.php`
- [ ] Create migration: `2026_04_08_100003_create_invoices_table.php`
- [ ] Create migration: `2026_04_08_100004_create_invoice_sequences_table.php` (seed single row)
- [ ] Create migration: `2026_04_08_100005_add_subscription_and_discount_fields.php` (payments + subscriptions)
- [ ] Run `php artisan migrate`
- [ ] Run `php artisan db:seed`

**Checkpoint 1:**
- [ ] `php artisan migrate:status` — all 5 new migrations show "Ran"
- [ ] `php artisan tinker --execute="echo Schema::hasTable('discount_codes') ? 'OK' : 'FAIL';"` — OK
- [ ] `php artisan tinker --execute="echo Schema::hasTable('invoices') ? 'OK' : 'FAIL';"` — OK
- [ ] `php artisan tinker --execute="echo Schema::hasColumn('payments', 'discount_code_id') ? 'OK' : 'FAIL';"` — OK
- [ ] `php artisan tinker --execute="echo Schema::hasColumn('subscriptions', 'auto_renew') ? 'OK' : 'FAIL';"` — OK
- [ ] `php artisan tinker --execute="echo DB::table('invoice_sequences')->count();"` — returns 1
- [ ] Existing test suite passes: `./vendor/bin/pest` (no regressions from migrations)

---

## Phase 2: Models

- [ ] Create `app/Models/DiscountCode.php` — fillable, casts, enums, validation methods (`isValid`, `isValidForPlan`, `isValidForCycle`, `hasUsesRemaining`, `userUsageCount`), `calculateDiscount(int $amountPence)`, relationships (`usages`, `creator`)
- [ ] Create `app/Models/DiscountCodeUsage.php` — fillable, casts, relationships (`discountCode`, `user`, `payment`)
- [ ] Create `app/Models/Invoice.php` — fillable, casts, relationships (`user`, `payment`, `subscription`), `generateNumber()` (atomic sequence), `getFormattedTotalAttribute()`
- [ ] Modify `app/Models/Subscription.php` — add `revolut_plan_id`, `revolut_plan_variation_id`, `auto_renew`, `payment_method_saved` to fillable/casts. Add `invoices()` relationship
- [ ] Modify `app/Models/Payment.php` — add `discount_code_id`, `discount_amount`, `invoice_id`, `revolut_subscription_payment` to fillable/casts. Add `discountCode()`, `invoice()` relationships
- [ ] Create factory: `database/factories/DiscountCodeFactory.php` with state methods for percentage/fixed/trial
- [ ] Create factory: `database/factories/InvoiceFactory.php`

**Checkpoint 2:**
- [ ] `php artisan tinker --execute="\App\Models\DiscountCode::create(['code'=>'TEST99','type'=>'percentage','value'=>10]); echo 'OK';"` — OK
- [ ] `php artisan tinker --execute="echo (new \App\Models\Invoice)->generateNumber();"` — returns `FYN-INV-000001`
- [ ] `php artisan tinker --execute="echo (new \App\Models\Invoice)->generateNumber();"` — returns `FYN-INV-000002` (sequential)
- [ ] `php artisan tinker --execute="\App\Models\DiscountCode::where('code','TEST99')->forceDelete(); echo 'cleaned';"` — clean up
- [ ] Existing test suite passes: `./vendor/bin/pest`

---

## Phase 3a: RevolutSubscriptionService

- [ ] Create `app/Services/Payment/RevolutSubscriptionService.php`
- [ ] Implement `createCustomer(User $user)` — POST /customers, stores revolut_customer_id, idempotent
- [ ] Implement `getCustomer(string $customerId)` — GET /customers/{id}
- [ ] Implement `createSubscriptionPlan(SubscriptionPlan $plan)` — POST /subscription-plans with 2 variations (P1M + P1Y), trial_duration P7D, cycle_count null
- [ ] Implement `getSubscriptionPlan(string $planId)` — GET /subscription-plans/{id}
- [ ] Implement `getSubscriptionPlans()` — GET /subscription-plans (paginated)
- [ ] Implement `createSubscription(User $user, string $planVariationId, string $redirectUrl, ?string $trialDuration)` — POST /subscriptions with Idempotency-Key header
- [ ] Implement `getSubscription(string $subscriptionId)` — GET /subscriptions/{id}
- [ ] Implement `getSubscriptions(?string $externalReference)` — GET /subscriptions (paginated, filterable)
- [ ] Implement `updateSubscription(string $subscriptionId, string $externalReference)` — PATCH /subscriptions/{id}
- [ ] Implement `cancelSubscription(string $subscriptionId)` — POST /subscriptions/{id}/cancel (returns 204 void)
- [ ] Implement `getSubscriptionCycles(string $subscriptionId)` — GET /subscriptions/{id}/cycles
- [ ] Implement `getSubscriptionCycle(string $subscriptionId, string $cycleId)` — GET /subscriptions/{id}/cycles/{id}
- [ ] Modify `app/Services/Payment/RevolutService.php` — add `createOrderWithCustomer()` method

**Checkpoint 3a:**
- [ ] PHP syntax check: `php -l app/Services/Payment/RevolutSubscriptionService.php` — no errors
- [ ] PHP syntax check: `php -l app/Services/Payment/RevolutService.php` — no errors
- [ ] Service resolves from container: `php artisan tinker --execute="app(\App\Services\Payment\RevolutSubscriptionService::class); echo 'OK';"` — OK
- [ ] Existing test suite passes: `./vendor/bin/pest`

---

## Phase 3b: DiscountCodeService

- [ ] Create `app/Services/Payment/DiscountCodeService.php`
- [ ] Implement `validate(string $code, int $userId, string $planSlug, string $billingCycle, int $amountPence)` — checks: exists, is_active, not expired, date range, uses remaining, per-user limit, applicable plan/cycle
- [ ] Implement `apply(DiscountCode $discount, int $userId, int $paymentId, int $originalAmountPence)` — records usage, increments times_used, returns discounted amount
- [ ] Implement `calculateDiscount(DiscountCode $discount, int $amountPence)` — percentage/fixed/trial logic

**Checkpoint 3b:**
- [ ] PHP syntax check: `php -l app/Services/Payment/DiscountCodeService.php` — no errors
- [ ] Write + run quick unit test: `tests/Unit/Services/Payment/DiscountCodeServiceTest.php`
  - [ ] Valid percentage discount calculates correctly (e.g., 20% of 1099 = 220)
  - [ ] Valid fixed amount discount calculates correctly (e.g., 1000 off 1099 = 99)
  - [ ] Trial extension returns 0 discount
  - [ ] Expired code rejected
  - [ ] Inactive code rejected
  - [ ] Max uses exceeded rejected
  - [ ] Per-user limit exceeded rejected
  - [ ] Wrong plan rejected
  - [ ] Wrong cycle rejected
- [ ] `./vendor/bin/pest tests/Unit/Services/Payment/DiscountCodeServiceTest.php` — all pass

---

## Phase 3c: InvoiceService + PDF Template

- [ ] Create `resources/views/invoices/pdf.blade.php` — Fynla logo, invoice number, date, customer, line items, discount, total, next renewal date, footer
- [ ] Create `app/Services/Payment/InvoiceService.php`
- [ ] Implement `generateInvoice(Payment $payment, ?DiscountCode $discount)` — atomic sequence number, snapshot data, generate PDF, store to storage
- [ ] Implement `generatePdf(Invoice $invoice)` — DomPDF render, returns storage path
- [ ] Implement `emailInvoice(Invoice $invoice, User $user)` — dispatches InvoiceEmail mailable

**Checkpoint 3c:**
- [ ] PHP syntax check: `php -l app/Services/Payment/InvoiceService.php` — no errors
- [ ] Blade template compiles: `php artisan view:clear && php artisan view:cache` — no errors on invoices/pdf
- [ ] Write + run quick unit test: `tests/Unit/Services/Payment/InvoiceServiceTest.php`
  - [ ] Invoice number generates sequentially
  - [ ] PDF file created at expected storage path
  - [ ] Invoice record has correct snapshot data
- [ ] `./vendor/bin/pest tests/Unit/Services/Payment/InvoiceServiceTest.php` — all pass

---

## Phase 3d: SubscriptionRenewalService

- [ ] Create `app/Services/Payment/SubscriptionRenewalService.php`
- [ ] Implement `handleRenewalPayment(string $orderId, array $orderData)` — create Payment, update period dates, generate invoice, email invoice
- [ ] Implement `handleSubscriptionOverdue(array $subscriptionData)` — set status past_due, send PaymentFailedNotification
- [ ] Implement `handleSubscriptionCancelled(array $subscriptionData)` — set status cancelled, set data_retention_starts_at
- [ ] Implement `handleSubscriptionFinished(array $subscriptionData)` — mark completed

**Checkpoint 3d:**
- [ ] PHP syntax check: `php -l app/Services/Payment/SubscriptionRenewalService.php` — no errors
- [ ] Service resolves: `php artisan tinker --execute="app(\App\Services\Payment\SubscriptionRenewalService::class); echo 'OK';"` — OK
- [ ] Full test suite still passes: `./vendor/bin/pest` — no regressions

---

## Phase 4a: PaymentController — Subscription Flow Rewrite

- [ ] Add `validateDiscountCode()` endpoint — input: {code, plan, billing_cycle}, returns validation result + discount preview
- [ ] Add `downloadInvoice()` endpoint — validates invoice belongs to auth user, returns PDF
- [ ] Rewrite `createOrder()`:
  - [ ] Accept optional `discount_code` in validation
  - [ ] Validate discount via DiscountCodeService if provided
  - [ ] Handle trial_extension type (extend trial, return early)
  - [ ] Create Revolut customer if revolut_customer_id is null
  - [ ] Create Revolut subscription via RevolutSubscriptionService (returns setup_order_id)
  - [ ] Get setup order via getOrder(setup_order_id) for token
  - [ ] Create pending Payment with revolut_order_id = setup order id
  - [ ] Store revolut_subscription_id + revolut_plan_variation_id on subscription
  - [ ] Return {token, order_id} — same shape as before
  - [ ] Fallback: if revolut_subscription_id exists, use old one-off flow (migration path)
- [ ] Modify `confirmPayment()` — generate invoice, apply discount usage, set auto_renew + payment_method_saved
- [ ] Modify `cancelSubscription()` — call RevolutSubscriptionService::cancelSubscription if revolut_subscription_id exists
- [ ] Modify `billingHistory()` — include invoice_id, has_invoice, discount_applied per payment
- [ ] Modify `trialStatus()` — include auto_renew, next_renewal_date

**Checkpoint 4a:**
- [ ] PHP syntax check: `php -l app/Http/Controllers/Api/PaymentController.php` — no errors
- [ ] `php artisan route:list --path=payment` — all routes compile, new endpoints listed
- [ ] Write + run: `tests/Feature/Payment/DiscountCodeValidationTest.php`
  - [ ] POST /api/payment/validate-discount with valid code returns discount preview
  - [ ] POST /api/payment/validate-discount with invalid code returns error
  - [ ] Rate limiting works (throttle:20,1)
- [ ] Write + run: `tests/Feature/Payment/InvoiceDownloadTest.php`
  - [ ] Auth user can download own invoice
  - [ ] Cannot download another user's invoice (403)
  - [ ] 404 for non-existent invoice
- [ ] `./vendor/bin/pest tests/Feature/Payment/` — all pass

---

## Phase 4b: WebhookController — Subscription Events

- [ ] Add `handleSubscriptionInitiated(array $payload)` — delegates to SubscriptionRenewalService
- [ ] Add `handleSubscriptionOverdue(array $payload)` — delegates to SubscriptionRenewalService
- [ ] Add `handleSubscriptionCancelled(array $payload)` — delegates to SubscriptionRenewalService
- [ ] Add `handleSubscriptionFinished(array $payload)` — delegates to SubscriptionRenewalService
- [ ] Modify `handleRevolut()` — match on new events alongside ORDER_COMPLETED/ORDER_AUTHORISED
- [ ] Handle ORDER_COMPLETED for renewal cycles — detect by revolut_subscription_id, create Payment, update period, generate invoice

**Checkpoint 4b:**
- [ ] PHP syntax check: `php -l app/Http/Controllers/Api/WebhookController.php` — no errors
- [ ] Write + run: `tests/Feature/Payment/WebhookSubscriptionEventsTest.php`
  - [ ] SUBSCRIPTION_INITIATED webhook activates subscription
  - [ ] SUBSCRIPTION_OVERDUE webhook sets past_due
  - [ ] SUBSCRIPTION_CANCELLED webhook sets cancelled
  - [ ] SUBSCRIPTION_FINISHED webhook marks completed
  - [ ] ORDER_COMPLETED for renewal creates payment + invoice
  - [ ] Signature verification works for all event types
  - [ ] Idempotent — duplicate webhook doesn't create duplicate records
- [ ] `./vendor/bin/pest tests/Feature/Payment/WebhookSubscriptionEventsTest.php` — all pass

---

## Phase 4c: AdminController — Discount Code CRUD

- [ ] Add `listDiscountCodes()` — returns all codes ordered by created_at desc, with usage counts
- [ ] Add `createDiscountCode()` — validates all fields, auto-uppercases code
- [ ] Add `updateDiscountCode()` — same validation, uniqueness excludes self
- [ ] Add `deleteDiscountCode()` — soft-delete
- [ ] Add `toggleDiscountCode()` — flips is_active

**Checkpoint 4c:**
- [ ] PHP syntax check: `php -l app/Http/Controllers/Api/AdminController.php` — no errors
- [ ] Write + run: `tests/Feature/Admin/AdminDiscountCodeTest.php`
  - [ ] Admin can list discount codes
  - [ ] Admin can create a discount code (all fields)
  - [ ] Admin can update a discount code
  - [ ] Admin can delete (soft-delete) a discount code
  - [ ] Admin can toggle active/inactive
  - [ ] Non-admin gets 403
  - [ ] Validation rejects invalid data (duplicate code, invalid type, etc.)
- [ ] `./vendor/bin/pest tests/Feature/Admin/AdminDiscountCodeTest.php` — all pass

---

## Phase 5: Routes

- [ ] Add to `routes/api.php` payment group: `POST /validate-discount` (throttle:20,1)
- [ ] Add to `routes/api.php` payment group: `GET /invoices/{invoice}/download` (throttle:10,1)
- [ ] Add to `routes/api.php` admin group: `GET /admin/discount-codes`
- [ ] Add to `routes/api.php` admin group: `POST /admin/discount-codes`
- [ ] Add to `routes/api.php` admin group: `PUT /admin/discount-codes/{id}`
- [ ] Add to `routes/api.php` admin group: `DELETE /admin/discount-codes/{id}`
- [ ] Add to `routes/api.php` admin group: `PATCH /admin/discount-codes/{id}/toggle`

**Checkpoint 5:**
- [ ] `php artisan route:list --path=payment` — shows validate-discount + invoice download
- [ ] `php artisan route:list --path=admin/discount` — shows all 5 discount code routes
- [ ] `php artisan route:clear && php artisan route:cache` — no errors
- [ ] Full backend test suite: `./vendor/bin/pest` — ALL PASS, no regressions

---

## Phase 6a: CheckoutPage.vue — Discount Code UI

- [ ] Add discount code section in Order Summary panel (left column, below existing summary)
  - [ ] Text input + "Apply" button
  - [ ] Success feedback (spring) / error feedback (raspberry)
  - [ ] When applied: original price strikethrough, discount line, final price
  - [ ] Wire to `POST /api/payment/validate-discount`
- [ ] Pass `discount_code` in `createOrder` callback POST body
- [ ] Add auto-renewal notice below payment widget: "Your subscription will automatically renew each [month/year]. You can cancel at any time from your profile."

**Checkpoint 6a:**
- [ ] `./dev.sh` compiles without errors (check terminal for Vue warnings)
- [ ] No linting errors in CheckoutPage.vue
- [ ] Design system compliance: raspberry CTA, spring success, horizon text, no banned colours

---

## Phase 6b: SubscriptionManagement.vue — Auto-Renewal + Invoices

- [ ] Active state: show "Auto-renewal: Active" + next renewal date + payment amount/date text
- [ ] Billing history table: add "Invoice" column with download icon-link
- [ ] Cancelled state: "Auto-renewal has been cancelled. You retain access until [date]."
- [ ] Past due state: "We were unable to process your automatic renewal payment. We will retry automatically."

**Checkpoint 6b:**
- [ ] Compiles without errors
- [ ] Design system compliance checked

---

## Phase 6c: PlanSelectionModal.vue — Discount Code Input

- [ ] Add "Have a discount code?" collapsible section with text input
- [ ] Pass code through `@select` event payload: `{ plan, billingCycle, isUpgrade, discountCode }`
- [ ] CheckoutPage pre-fills discount input from this value

**Checkpoint 6c:**
- [ ] Compiles without errors

---

## Phase 6d: Admin Panel — Discount Codes Tab

- [ ] Modify `AdminPanel.vue`: add navItem `{ id: 'discount-codes', label: 'Discount Codes', shortLabel: 'Codes' }`, icon, component render
- [ ] Create `resources/js/components/Admin/DiscountCodes.vue`:
  - [ ] Table: Code (monospace) | Type (badge) | Value | Uses (used/max) | Status (toggle) | Valid Period | Actions (edit/delete)
  - [ ] Loading spinner, empty state, success/error auto-dismiss
  - [ ] Edit opens modal, delete has confirm dialog, toggle calls PATCH
- [ ] Create `resources/js/components/Admin/DiscountCodeModal.vue`:
  - [ ] Code input (auto-uppercase, alphanumeric)
  - [ ] Type button group (Percentage / Fixed Amount / Trial Extension)
  - [ ] Value input (label adapts to type, validation per type)
  - [ ] Max uses, uses per user, plan checkboxes, cycle checkboxes, date range, active toggle
  - [ ] Cancel + Save buttons, emits `save` (parent handles API)
- [ ] Add methods to `adminService.js`: getDiscountCodes, createDiscountCode, updateDiscountCode, deleteDiscountCode, toggleDiscountCode

**Checkpoint 6d:**
- [ ] Compiles without errors
- [ ] All frontend compiles: `./dev.sh` output clean (no Vue warnings/errors)
- [ ] Design system: savannah-100 table headers, raspberry CTAs, violet/horizon/spring type badges, no banned colours

---

## Phase 7: Email Templates

- [ ] Create `app/Mail/InvoiceEmail.php` — subject: "Your Fynla invoice — FYN-INV-XXXXXX", attach PDF
- [ ] Create `resources/views/emails/invoice.blade.php` — inline summary, download CTA, British spelling
- [ ] Create `app/Mail/PaymentFailedNotification.php` — subject: "Action required — payment issue with your Fynla subscription"
- [ ] Create `resources/views/emails/payment-failed.blade.php` — explains auto-retry, update payment link, British spelling

**Checkpoint 7:**
- [ ] PHP syntax: `php -l app/Mail/InvoiceEmail.php && php -l app/Mail/PaymentFailedNotification.php` — no errors
- [ ] Blade templates compile: `php artisan view:clear && php artisan view:cache` — no errors
- [ ] Full test suite: `./vendor/bin/pest` — no regressions

---

## Phase 8: Seeders + Artisan Commands

- [ ] Create `database/seeders/DiscountCodeSeeder.php`:
  - [ ] LAUNCH20 — 20% off, max 500, expires 3 months
  - [ ] FYNLA10 — £10 off (1000 pence), unlimited, 1/user
  - [ ] TRYME — 14 extra trial days, max 200
- [ ] Add `DiscountCodeSeeder` to `DatabaseSeeder.php`
- [ ] Create `app/Console/Commands/SyncRevolutPlans.php` (`php artisan revolut:sync-plans`)
- [ ] Create `app/Console/Commands/CheckOverdueSubscriptions.php` (`php artisan subscriptions:check-overdue`)
- [ ] Add schedule entry in Kernel.php: `$schedule->command('subscriptions:check-overdue')->dailyAt('01:00')`

**Checkpoint 8:**
- [ ] `php artisan db:seed --class=DiscountCodeSeeder --force` — seeds 3 codes
- [ ] `php artisan tinker --execute="echo \App\Models\DiscountCode::count();"` — returns 3 (or more if test code still exists)
- [ ] `php artisan revolut:sync-plans --help` — command registered
- [ ] `php artisan subscriptions:check-overdue --help` — command registered
- [ ] `php artisan schedule:list` — shows subscriptions:check-overdue at 01:00
- [ ] Full reseed: `php artisan db:seed` — completes without errors
- [ ] Full test suite: `./vendor/bin/pest` — ALL PASS

---

## Phase 9: Pest Tests (comprehensive)

- [ ] `tests/Unit/Services/Payment/DiscountCodeServiceTest.php` — all validation scenarios (written in Phase 3b, verify still passing)
- [ ] `tests/Unit/Services/Payment/InvoiceServiceTest.php` — sequential numbering, PDF gen (written in Phase 3c, verify still passing)
- [ ] `tests/Feature/Payment/DiscountCodeValidationTest.php` — endpoint + rate limiting (written in Phase 4a, verify still passing)
- [ ] `tests/Feature/Payment/InvoiceDownloadTest.php` — auth, ownership, 404 (written in Phase 4a, verify still passing)
- [ ] `tests/Feature/Payment/WebhookSubscriptionEventsTest.php` — 4 subscription events + renewal (written in Phase 4b, verify still passing)
- [ ] `tests/Feature/Admin/AdminDiscountCodeTest.php` — CRUD + auth (written in Phase 4c, verify still passing)
- [ ] Write `tests/Feature/Payment/CreateOrderWithDiscountTest.php`:
  - [ ] Order creation with valid percentage discount code
  - [ ] Order creation with valid fixed amount discount code
  - [ ] Order creation with trial extension code (extends trial, no order)
  - [ ] Order creation without discount code (existing flow works)
  - [ ] Discount code usage recorded after payment confirmation
  - [ ] Invalid discount code rejected at order creation
- [ ] Run full test suite: `./vendor/bin/pest` — ALL PASS
- [ ] Run architecture tests: `./vendor/bin/pest --testsuite=Architecture` — ALL PASS

**Checkpoint 9:**
- [ ] Total test count increased (record new count: ____ tests)
- [ ] 0 failures, 0 errors
- [ ] No skipped tests related to payment/discount/invoice

---

## Phase 10: Browser Testing (MANDATORY — Playwright)

**Pre-flight:**
- [ ] `php artisan db:seed` — fresh seed
- [ ] `./dev.sh` running on :8000/:5173
- [ ] Log in as test user (`john@example.com` / `password`, fetch verification code from DB)

**Checkout + Discount Code Flow:**
- [ ] Navigate to checkout page with a plan selected
- [ ] Enter discount code "LAUNCH20" in discount field, click Apply
- [ ] Verify: original price shown with strikethrough, discount line "20% off", final price correct
- [ ] Enter invalid code "BADCODE", click Apply — verify error message shown
- [ ] Clear code, enter "FYNLA10", click Apply — verify £10 off shown correctly
- [ ] Complete payment with test card `4929 4205 7359 5709`
- [ ] Verify success modal appears
- [ ] Click "Go to Dashboard" — verify redirect

**Subscription Management:**
- [ ] Navigate to Profile > Subscription Management
- [ ] Verify "Auto-renewal: Active" shown with next renewal date
- [ ] Verify billing history table has "Invoice" column
- [ ] Click invoice download link — verify PDF opens/downloads
- [ ] Click "Cancel Subscription" — verify confirm dialog
- [ ] Confirm cancel — verify "Auto-renewal has been cancelled" message

**Admin Discount Codes:**
- [ ] Log in as admin (`chris@fynla.org` / `Password1!`)
- [ ] Navigate to Admin Panel
- [ ] Click "Discount Codes" tab — verify table loads with seeded codes (LAUNCH20, FYNLA10, TRYME)
- [ ] Click "Add Code" — verify modal opens
- [ ] Fill all fields: code "TESTBROWSER", type Percentage, value 15, max uses 100
- [ ] Click Save — verify code appears in table
- [ ] Click Edit on "TESTBROWSER" — change value to 25, save — verify updated in table
- [ ] Toggle "TESTBROWSER" inactive — verify status changes
- [ ] Toggle back to active — verify status changes back
- [ ] Click Delete on "TESTBROWSER" — confirm — verify removed from table

**Post-fix regression (if any fix was made):**
- [ ] Re-test from "Checkout + Discount Code Flow" step 1
- [ ] Re-run `./vendor/bin/pest` — ALL PASS

---

## Final Checklist

- [ ] All 18 tasks completed
- [ ] Full test suite passes: `./vendor/bin/pest` — 0 failures
- [ ] Architecture tests pass: `./vendor/bin/pest --testsuite=Architecture`
- [ ] No PHP syntax errors: `for f in $(git diff --name-only -- '*.php'); do php -l "$f"; done`
- [ ] No hardcoded tax values (stop hook passes)
- [ ] Design system compliance: no banned colours (amber, orange, primary-*, secondary-*, gray-*)
- [ ] British spelling in all user-facing text
- [ ] `php artisan db:seed` completes without errors
- [ ] `php artisan route:list` compiles without errors
- [ ] All browser tests above checked off with actual Playwright interactions
- [ ] Commit on `revolutLive` branch
