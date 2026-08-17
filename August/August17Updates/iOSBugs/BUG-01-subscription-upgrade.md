---
id: BUG-01
raised: 2026-08-17
surface: web (root-caused) · /m (root-caused, separate cause) · native (not reproduced)
severity: blocker
status: root cause found — not yet fixed
fixed_in: null
testflight_build: null
---

# BUG-01 — Subscription upgrade fails at the payment engine

CSJ: *"the subscription service and upgrade process is not working, it does not
show correctly, link through to the payment engine, or actually do anything
useful."*

No fix applied yet.

## 0. Correction to my first report

My earlier report said the web **"Compare plans" button does nothing** and called
that the blocker. **That was wrong.** The button works.

What actually happened: the browser automation tool had stopped delivering mouse
events to the page. Proven by a control test — clicking the "General" settings tab
did nothing either, and instrumented listeners on `document` in capture phase
recorded **zero** `pointerdown`/`mousedown`/`click` events for either click. A
synthetic `.click()` on the same element in the same tab opens the modal
immediately. So the dead click was my instrument, not the app.

Everything below is verified through paths that do not depend on that tool:
synthetic clicks, direct controller invocation, the database, and the Laravel log.

## 1. The upgrade journey actually works right up to the payment engine

Walked end to end:

1. `/settings/subscription` → "Free" card + "Compare plans". ✅
2. "Compare plans" → **modal opens**: "Upgrade Your Plan", Monthly/Yearly toggle,
   Premium **£59.99/year**, "Save 28% vs monthly", all 10 features from the API. ✅
3. "Choose Plan" → routes to `/checkout?plan=premium&cycle=yearly`. ✅
4. Checkout renders a correct Order Summary — Premium / Yearly / **£59.99** —
   plus a "Have a discount code?" link. ✅
5. **Payment Method panel fails.** ❌

So it *does* show, and it *does* link through. It dies inside the payment step.

## 2. The failure, exactly

Two errors on screen simultaneously:

```
Your previous checkout is still being reconciled. Please try again shortly.   [Try again]

Payment Method
  Failed to load
  The provided order is not valid                                            [Try again]
```

`"still being reconciled"` is ours — `PaymentController.php:598`.
`"The provided order is not valid"` appears **nowhere in the codebase**; it is the
Revolut widget rejecting the order id it was handed.

## 3. Root cause — verified from the log and the database

`storage/logs/laravel.log`, 11:54:48:

```
local.ERROR: Revolut createOrderWithCustomer failed
  {"status":400,"revolut_error_code":"validation","amount":5999,
   "customer_id":"aa76d34b-373e-41bf-9577-0536e5ac1da8"}

local.ERROR: Creating tier payment order failed
  HTTP request returned status code 400
  #1 RevolutService.php(232): Response->throw()
  #2 PaymentController.php(515): createOrderWithCustomer(5999,'GBP','Premium — Yea…',
                                  'http://localhos…','aa76d34b…','payment_1',
                                  'john@example.co…', false)
```

### 3.1 Why Revolut rejects it — the app is talking to PRODUCTION Revolut

`RevolutService.php:21-24`

```php
$sandbox = config('services.revolut.sandbox');
$this->apiUrl = $sandbox
    ? 'https://sandbox-merchant.revolut.com/api'
    : 'https://merchant.revolut.com/api';
```

`config('services.revolut.sandbox')` resolves to **`false`** — so this local
machine posts orders to **`https://merchant.revolut.com`**, the live endpoint.
`config/services.php:64` defaults it to `true`; an explicit `REVOLUT_SANDBOX`
value in `.env` overrides that.

Production Revolut then returns `400 validation`. Two candidates, both consistent
with "validation" and not yet separated:

- `redirect_url` is `http://localhost:8000/…` — production Revolut will not accept
  a non-public, non-HTTPS redirect URL.
- The API key in `.env` is a sandbox key, invalid against the live endpoint.

*(Correcting myself: the trailing `false` in the stack trace is
`savePaymentMethod` — signature at `RevolutService.php:187-196` — not the sandbox
flag. The sandbox evidence is the config read above.)*

### 3.2 The serious defect — a failed order permanently bricks checkout

This one is **environment-independent and would hit real users on production.**

The `Payment` row is written **before** the remote call succeeds, with a
placeholder id, and is **not cleaned up when Revolut fails**:

```sql
SELECT id, status, revolut_order_id, plan_slug, billing_cycle, amount FROM payments …
```
```
id: 1  status: pending  revolut_order_id: pending_45e9eda9-d8f6-4ff3-9a3d-bf8b95eab938
plan: premium  cycle: yearly  amount: 5999.00
```

`id: 1` — the **first payment row ever created in this database**. So a single
failed first attempt from a clean state produced it.

Now the guard at `PaymentController.php:583-600` runs on every later attempt:

```php
if (str_starts_with($pendingPayment->revolut_order_id, 'pending_')) {
    try { $reconciledOrder = $this->revolutService->findOrderByMerchantReference("payment_{$pendingPayment->id}"); }
    catch (\Throwable) { $reconciledOrder = null; }

    if ($reconciledOrder === null) {
        return response()->json([... 'Your previous checkout is still being reconciled…'], 409);
    }
```

The order does not exist remotely — it never got created. So
`findOrderByMerchantReference` returns null (or throws, and the `catch` flattens
that to null), and the method returns **409 forever**. There is no expiry, no
cleanup, no escape path.

**The "Try again" button can never succeed.** Any transient Revolut failure — a
timeout, a 5xx, a blip — permanently locks that user out of checkout until
someone deletes the row by hand. That is the bug worth fixing regardless of the
sandbox misconfiguration.

Note also `catch (\Throwable) { $reconciledOrder = null; }` silently converts a
network or auth error into "still reconciling", so the logs blame reconciliation
for what may be an authentication failure.

## 4. `/m` fails for a completely different reason — also confirmed

`resources/mobile/views/Subscription.vue:104-108`

```js
canUpgrade()         { return this.status?.tier === 'free' && this.status?.payment_enabled === true; },
paymentUnavailable() { return this.status?.tier === 'free' && this.status?.payment_enabled !== true; },
```

`GET /api/payment/subscription-status` (`PaymentController::subscriptionStatus`)
**does not emit `payment_enabled`** — verified with `array_key_exists`, genuinely
absent, not null. But `SubscriptionStatusService.php:86` *does* emit it, and
`config('app.payment_enabled')` is **`true`**.

So payments are enabled, the server knows, a service says so — and `/m` shows
**"Upgrades are temporarily unavailable"** permanently, because it reads the one
producer that omits the field. Two producers of one payload; Rule 20.

`/m` never even reaches the checkout, so it never sees §3 at all.

## 5. Native — still not reproduced

No sign-in yet. Code-level concerns unchanged: `SystemStoreKitClient.swift:63-67`
throws `productUnavailable` if the product set does not match exactly, so zero
products takes the whole paywall down with no partial-availability path. Product
IDs do match the `.storekit` config, so the six red StoreKit tests mean *zero
products loaded*. Whether `org.fynla.premium.monthly` / `.annual` exist as in-app
purchases on the `Fynla Dev` ASC record (6793193337) is **still unverified**.

## 6. Lesser findings

- `/api/payment/subscription-status` is fetched **5×** per page view.
- `SubscriptionSettings.vue:6-8` promises "plan, billing, invoices, and discount
  codes"; the free state shows only the plan card. Discount codes do exist in the
  modal and at checkout, so this is a heading-vs-free-state mismatch rather than a
  missing feature.
- Backend `plans()` and `subscriptionStatus()` both return 200 with correct data —
  £6.99/£59.99, matching the StoreKit prices.

## 7. Fix order

1. **`PaymentController` — never leave an unrecoverable `pending_` row.** Either
   create the row only after Revolut confirms, or make the guard self-healing
   (expire/void placeholder rows past a short TTL, and distinguish "remote error"
   from "genuinely still reconciling" instead of `catch (\Throwable) → null`).
   Highest priority: this is a production dead-end.
2. **Decide `REVOLUT_SANDBOX` locally** (CSJ's call — `.env` untouched). Sandbox
   would also fix the `http://localhost` redirect_url issue.
3. **`payment_enabled` — one producer.** Add it to
   `PaymentController::subscriptionStatus`, or point `/m` at
   `SubscriptionStatusService`. Then have the server state upgrade eligibility
   rather than `/m` deriving it.
4. Clear the orphaned row for John (`payments.id = 1`) so local checkout is
   testable again — with CSJ's agreement, since it is a data delete.
5. The 5× fetch, then the §6 heading mismatch.
6. Native: verify the ASC in-app purchase products exist before touching
   `SystemStoreKitClient`.
