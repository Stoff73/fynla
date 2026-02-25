# Revolut Payment Integration — Implementation Plan

## Overview

Integrates Revolut's Embedded Checkout widget into Fynla's existing subscription system using the `embeddedCheckout()` direct initialisation method from the Merchant Web SDK. Shows all enabled and applicable payment methods via embedded checkout. Uses the **Orders API** to create payment orders on-demand when the user clicks "Pay" inside the widget.

### Pseudo-Subscription Model

Revolut's Subscriptions API is not used. Instead, we implement a **pseudo-subscription** using one-off Orders:
- Each payment period (monthly/yearly) is a single Revolut order.
- When the period expires, the user must **renew** by making a new payment.
- There is no auto-recurring billing — each renewal is an explicit user action.
- The UI uses "renew" terminology (not "subscribe") for returning active/expired users.
- `plan_slug` and `billing_cycle` are persisted on each `Payment` record so that `confirmPayment()` and the webhook handler can correctly set the subscription plan, billing cycle, and period end without relying on stale subscription state.

### User Flow

```
Trial Banner "Upgrade Now" → Plan Selection Modal → /checkout page →
Revolut Embedded Checkout widget (all enabled payment methods) → onSuccess callback →
POST /api/payment/confirm → Success modal → Navigate to Dashboard (no banner)
```

Same flow from User Profile > Subscription tab via "Subscribe Now" / "Renew" buttons.

### Renewal Flow (when period expires)

```
Subscription status → expired → User sees "Renew" button →
Plan Selection Modal → /checkout page → Pay → confirm → active again
```

---

## Revolut SDK — Exact Specification (from offline docs)

All code in this section is taken directly from the Revolut documentation (`WebRevolutDocs.pdf` and `revolut_dev_docs_offline_pack/`).

### SDK Installation

Install the npm package (per official Revolut docs, Step 2):

```bash
npm install @revolut/checkout
```

Then import in your JavaScript/Vue file:

```js
import RevolutCheckout from '@revolut/checkout'
```

### Type Signature (from `embedded-checkout.md` lines 79-100)

```ts
RevolutCheckout.embeddedCheckout: (
  options: EmbeddedCheckoutOptions
) => Promise<EmbeddedCheckoutInstance>

interface EmbeddedCheckoutOptions {
  publicToken: string                                                    // Merchant API public key (pk_...)
  mode: 'prod' | 'sandbox'                                              // Must match key environment
  locale?: Locale | 'auto'                                               // Default: 'auto'
  target: HTMLElement                                                    // DOM element where widget mounts
  createOrder: () => Promise<{ publicId: string }>                       // Calls backend, returns order token
  onSuccess?: (payload: { orderId: string }) => void                     // Payment completed
  onError?: (payload: { error: RevolutCheckoutError; orderId: string }) => void  // Payment failed
  onCancel?: (payload: { orderId: string | undefined }) => void          // User cancelled
  email?: string                                                         // Pre-fill customer email
  billingAddress?: Address                                               // Pre-fill billing address
}

interface EmbeddedCheckoutInstance {
  destroy: () => void                                                    // Remove widget, clean up
}
```

### Critical Notes (from `embedded-checkout.md` lines 124-130)

1. **`publicToken`** = your Merchant API **public key** (`pk_...`). This is NOT an order token.
2. **`mode`** must match the key environment. Sandbox keys only work with `'sandbox'` mode.
3. **`createOrder`** is called by the widget on-demand (when user clicks Pay). It must call your backend to create a Revolut order and return `{ publicId: order.token }` where `token` is from the Revolut API Create Order response.
4. **Callbacks are NOT guaranteed to fire** (network issues, browser closures, ad blockers). Use webhooks for critical backend operations like order fulfilment.
5. **In callbacks, `orderId` refers to the order public token** (`order.token` from API response), **NOT** the internal order UUID (`order.id`). This is a critical distinction for the confirm endpoint.
6. **`destroy()`** must be called when removing the widget from the page.

### Usage Example (from `embedded-checkout.md` lines 159-192)

```js
import RevolutCheckout from '@revolut/checkout'

const { destroy } = await RevolutCheckout.embeddedCheckout({
  publicToken: process.env.REVOLUT_PUBLIC_KEY,
  mode: 'prod',
  locale: 'auto',
  target: document.getElementById('checkout-container'),
  createOrder: async () => {
    const response = await fetch('/api/create-order', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ amount: 1000, currency: 'GBP' })
    })
    const order = await response.json()
    return { publicId: order.token }
  },
  onSuccess: ({ orderId }) => {
    console.log('Payment successful!', orderId)
    window.location.href = `/confirmation?orderId=${orderId}`
  },
  onError: ({ error, orderId }) => {
    console.error('Payment failed:', error.message, orderId)
    alert(`Payment failed: ${error.message}`)
  },
  onCancel: ({ orderId }) => {
    console.log('Payment cancelled', orderId)
    alert('Payment was cancelled.')
  }
})

// Later: destroy()
```

### Error Handling (from `embedded-checkout.md` lines 143-155)

```js
try {
  const { destroy } = await RevolutCheckout.embeddedCheckout({
    // ... configuration
  })
} catch (error) {
  if (error.name === 'RevolutCheckout') {
    console.error('Checkout initialisation failed:', error.message)
  }
}
```

---

## Revolut Merchant API — Exact Specification (from OpenAPI spec `merchant-2025-12-04.yaml`)

### Create Order — `POST /api/orders`

**Request:**
```http
POST https://sandbox-merchant.revolut.com/api/orders
Authorization: Bearer sk_BElVCRQCfMXfQMInfHJufjrDHLG07_3AdpJWkr1-al65-SCvJFAdazEovR5RAazL
Revolut-Api-Version: 2025-12-04
Content-Type: application/json

{
  "amount": 10000,
  "currency": "GBP",
  "description": "Fynla Standard — Yearly",
  "redirect_url": "https://fynla.org/checkout?status=complete",
  "merchant_order_data": {
    "reference": "payment_42"
  },
  "customer": {
    "email": "user@example.com"
  }
}
```

Fields (from `Order-Creation-v6` schema):
- `amount` (integer, **required**) — Minor currency units. For GBP: 10000 = £100.00. Min ~£0.005 equivalent for card payments.
- `currency` (string, **required**) — ISO 4217, 3 chars uppercase. e.g. `"GBP"`
- `description` (string, optional) — Order description.
- `redirect_url` (string, optional) — URL the customer is redirected to after payment completes on hosted/redirect-based flows (e.g. Pay by Bank). Must be `https://`. Set to the checkout page so the user returns to the app after redirect-based payment methods.
- `merchant_order_data` (object, optional) — `{ url?: string, reference?: string }`. The `reference` value is echoed back in webhook events as `merchant_order_ext_ref`, enabling safe reconciliation to our internal `Payment.id`.
- `customer` (object, optional) — `{ email?: string, full_name?: string, phone?: string }`

**Response 201:**
```json
{
  "id": "6516e61c-d279-a454-a837-bc52ce55ed49",
  "token": "0adc0e3c-ab44-4f33-bcc0-534ded7354ce",
  "type": "payment",
  "state": "pending",
  "created_at": "2023-09-29T14:58:36.079398Z",
  "updated_at": "2023-09-29T14:58:36.079398Z",
  "amount": 10000,
  "currency": "GBP",
  "outstanding_amount": 10000,
  "capture_mode": "automatic",
  "authorisation_type": "final",
  "checkout_url": "https://checkout.revolut.com/payment-link/0adc0e3c-..."
}
```

Key response fields:
- `id` (string, UUID) — **Internal order ID**. Used to retrieve/manage the order server-side. This is what we store in `Payment.revolut_order_id`. This is what the webhook sends as `order_id`.
- `token` (string) — **Temporary public token**. Used by the frontend SDK (`{ publicId: token }`). This is what `onSuccess`/`onError`/`onCancel` receive as `orderId`. Expires when payment is authorised.
- `state` — One of: `pending`, `processing`, `authorised`, `completed`, `cancelled`, `failed`

### Retrieve Order — `GET /api/orders/{order_id}`

**Request:**
```http
GET https://sandbox-merchant.revolut.com/api/orders/6516e61c-d279-a454-a837-bc52ce55ed49
Authorization: Bearer sk_...
Revolut-Api-Version: 2025-12-04
```

**Response 200:** Same schema as Create Order response. Used to verify `state` is `completed` or `authorised`.

### Order States (from `Order-State` enum)

| State | Description |
|-------|-------------|
| `pending` | Order created, awaiting payment |
| `processing` | Payment is being processed |
| `authorised` | Payment authorised but not yet captured (for manual capture) |
| `completed` | Payment successfully captured and settled |
| `cancelled` | Order was cancelled |
| `failed` | Payment failed |

For our integration with automatic capture, a successful payment transitions: `pending` → `processing` → `completed`.

---

## Revolut Webhooks — Exact Specification

### Webhook Event Delivery

Revolut sends HTTP POST to your registered webhook URL when order status changes.

**Headers:**
```
Revolut-Request-Timestamp: 1683650202360
Revolut-Signature: v1=09a9989dd8d9282c1d34974fc730f5cbfc4f4296941247e90ae5256590a11e8c
```

**Body (for order events, from `Webhook-Order-Event` schema):**
```json
{
  "event": "ORDER_COMPLETED",
  "order_id": "6634c172-3398-ac93-aee9-50de0282e3ac",
  "merchant_order_ext_ref": "Example reference #123"
}
```

Fields:
- `event` (string, **required**) — Event type. We handle: `ORDER_COMPLETED`, `ORDER_AUTHORISED`
- `order_id` (string UUID, **required**) — The internal order UUID (same as `id` from Create Order response). NOT the token.
- `merchant_order_ext_ref` (string, optional) — Echoes the `merchant_order_data.reference` value set during order creation. We set this to `"payment_{id}"` (our internal `Payment.id`) for safe reconciliation. Primary lookup remains `Payment::where('revolut_order_id', $orderId)`, but this field provides a secondary cross-reference.

**Response:** Any HTTP 200-399 acknowledges delivery. On failure (4XX/timeout), Revolut retries 3 more times, each with a 10-minute delay.

### Signature Verification Algorithm

Source: [Verify payload signature tutorial](https://developer.revolut.com/docs/guides/accept-payments/tutorials/work-with-webhooks/verify-the-payload-signature)

**Step 1 — Prepare the signing payload:**

Concatenate with `.` (full stop) separator:
```
payload_to_sign = v1.{Revolut-Request-Timestamp}.{raw-payload}
```

Example:
```
v1.1683650202360.{"event": "ORDER_COMPLETED","order_id": "9fc01989-3f61-4484-a5d9-ffe768531be9","merchant_order_ext_ref": "Test #3928"}
```

**CAUTION:** The `v1.` version prefix MUST be included. The raw payload must not be modified (no whitespace changes, no re-serialisation).

**Step 2 — Compute HMAC-SHA256:**

Using the webhook `signing_secret` (format: `wsk_...`, obtained when creating/retrieving the webhook):

```python
# From Revolut docs — Python reference implementation
import hmac
import hashlib

signing_secret = 'wsk_r59a4HfWVAKycbCaNO1RvgCJec02gRd8'
timestamp = '1683650202360'
raw_payload = '{"event": "ORDER_COMPLETED", ...}'

payload_to_sign = 'v1.' + timestamp + '.' + raw_payload
expected_hex = hmac.new(
    bytes(signing_secret, 'utf-8'),
    msg=bytes(payload_to_sign, 'utf-8'),
    digestmod=hashlib.sha256
).hexdigest()
```

PHP equivalent:
```php
$payloadToSign = "v1.{$timestamp}.{$rawPayload}";
$expectedHex = hash_hmac('sha256', $payloadToSign, $webhookSecret);
```

**Step 3 — Compare signatures:**

The `Revolut-Signature` header value always uses the `v1=` prefix. It may contain **multiple `v1=...` values separated by commas** (during signing secret rotation). Every value in the header will have the `v1=` prefix — there is no raw-hex variant. Check each one:

```python
expected_hex = hmac.new(
    bytes(signing_secret, 'utf-8'),
    msg=bytes(payload_to_sign, 'utf-8'),
    digestmod=hashlib.sha256
).hexdigest()

signatures = signature_header.split(',')
for sig in signatures:
    sig = sig.strip()
    if sig.startswith('v1='):
        provided_hex = sig[3:]  # Strip 'v1=' prefix to get raw hex
        if hmac.compare_digest(expected_hex, provided_hex):
            return True
```

PHP equivalent:
```php
$expectedHex = hash_hmac('sha256', $payloadToSign, $webhookSecret);

$signatures = explode(',', $signatureHeader);
foreach ($signatures as $sig) {
    $sig = trim($sig);
    if (str_starts_with($sig, 'v1=')) {
        $providedHex = substr($sig, 3); // Strip 'v1=' prefix to get raw hex
        if (hash_equals($expectedHex, $providedHex)) {
            return true;
        }
    }
}
```

**Step 4 — Validate timestamp (replay protection):**

Ensure `Revolut-Request-Timestamp` falls within **5 minutes** of current UTC time. Reject if older.

---

## Implementation — Phase 1: Configuration & Backend Foundation

### 1.1 Update `.env` and `.env.example`

**Files:** `.env`, `.env.example`

```env
# Revolut Payment Integration
REVOLUT_API_KEY=sk_BElVCRQCfMXfQMInfHJufjrDHLG07_3AdpJWkr1-al65-SCvJFAdazEovR5RAazL
REVOLUT_PUBLIC_KEY=pk_D2JdE2srRipv0jdHerivLw1hMoWSrjqDa4lEozJxTwchuG04
REVOLUT_WEBHOOK_SECRET=
REVOLUT_SANDBOX=true
VITE_REVOLUT_PUBLIC_KEY=${REVOLUT_PUBLIC_KEY}
VITE_REVOLUT_SANDBOX=${REVOLUT_SANDBOX}
PAYMENT_ENABLED=true
```

- `REVOLUT_API_KEY` — Secret key (`sk_...`), server-side only, for Merchant API calls
- `REVOLUT_PUBLIC_KEY` — Public key (`pk_...`), passed to `embeddedCheckout({ publicToken })`
- `REVOLUT_WEBHOOK_SECRET` — Signing secret (`wsk_...`), obtained from webhook registration
- `REVOLUT_SANDBOX` — `true` for sandbox, `false` for production
- `VITE_` prefix — Exposes values to the Vite frontend build
- `PAYMENT_ENABLED` — Feature gate checked by `config('app.payment_enabled')`

### 1.2 Add Revolut config to `config/services.php`

**File:** `config/services.php`

```php
'revolut' => [
    'api_key' => env('REVOLUT_API_KEY', ''),
    'public_key' => env('REVOLUT_PUBLIC_KEY', ''),
    'webhook_secret' => env('REVOLUT_WEBHOOK_SECRET', ''),
    'sandbox' => env('REVOLUT_SANDBOX', true),
],
```

The API base URL is computed in `RevolutService` from the `sandbox` flag (not stored in config, because config values are cached and `env()` calls in config must be simple).

### 1.3 Add `description`, `plan_slug`, `billing_cycle` columns to payments table

**New file:** `database/migrations/2026_02_25_100001_add_columns_to_payments_table.php`

The `payments` table currently lacks `description`, `plan_slug`, and `billing_cycle`. These are needed because `confirmPayment()` and the webhook handler must know which plan/cycle the user paid for — they cannot rely on the subscription's current state (which may be stale, e.g. if the user is switching plans or the subscription is expired).

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'description')) {
                $table->string('description')->nullable()->after('status');
            }
            if (! Schema::hasColumn('payments', 'plan_slug')) {
                $table->string('plan_slug')->nullable()->after('description');
            }
            if (! Schema::hasColumn('payments', 'billing_cycle')) {
                $table->string('billing_cycle')->nullable()->after('plan_slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['description', 'plan_slug', 'billing_cycle']);
        });
    }
};
```

### 1.4 Update Model `$fillable` arrays

**File:** `app/Models/Subscription.php` — Add to `$fillable`:
```php
'revolut_order_id',
'revolut_subscription_id',
```

**File:** `app/Models/Payment.php` — Add to `$fillable`:
```php
'revolut_order_id',
'revolut_payment_data',
'description',
'plan_slug',
'billing_cycle',
```

Add to `$casts`:
```php
'revolut_payment_data' => 'array',
```

### 1.5 Create `RevolutService`

**New file:** `app/Services/Payment/RevolutService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RevolutService
{
    private string $apiKey;
    private string $apiUrl;
    private string $webhookSecret;

    public function __construct()
    {
        $this->apiKey = config('services.revolut.api_key');
        $sandbox = config('services.revolut.sandbox');
        $this->apiUrl = $sandbox
            ? 'https://sandbox-merchant.revolut.com/api'
            : 'https://merchant.revolut.com/api';
        $this->webhookSecret = config('services.revolut.webhook_secret');
    }

    /**
     * Create a Revolut order.
     *
     * POST {apiUrl}/orders
     *
     * @param int $amount Amount in minor currency units (pence for GBP)
     * @param string $currency ISO 4217 currency code (e.g. 'GBP')
     * @param string $description Order description
     * @param string $redirectUrl URL to redirect after hosted/redirect payment methods (must be https://)
     * @param string|null $merchantRef Internal reference echoed back in webhooks as merchant_order_ext_ref
     * @param string|null $email Customer email for pre-fill
     * @return array Revolut order response: { id, token, state, amount, currency, ... }
     */
    public function createOrder(
        int $amount,
        string $currency,
        string $description,
        string $redirectUrl,
        ?string $merchantRef = null,
        ?string $email = null
    ): array {
        $body = [
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'redirect_url' => $redirectUrl,
        ];

        if ($merchantRef) {
            $body['merchant_order_data'] = ['reference' => $merchantRef];
        }

        if ($email) {
            $body['customer'] = ['email' => $email];
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Revolut-Api-Version' => '2025-12-04',
            'Content-Type' => 'application/json',
        ])->post("{$this->apiUrl}/orders", $body);

        if ($response->failed()) {
            Log::error('Revolut createOrder failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'amount' => $amount,
                'currency' => $currency,
            ]);
            $response->throw();
        }

        $data = $response->json();

        Log::info('Revolut order created', [
            'order_id' => $data['id'],
            'state' => $data['state'],
            'amount' => $amount,
            'currency' => $currency,
            'merchant_ref' => $merchantRef,
        ]);

        return $data;
    }

    /**
     * Retrieve a Revolut order by its internal UUID.
     *
     * GET {apiUrl}/orders/{orderId}
     *
     * @param string $orderId The Revolut order UUID (order.id, NOT order.token)
     * @return array Order response including 'state'
     */
    public function getOrder(string $orderId): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Revolut-Api-Version' => '2025-12-04',
        ])->get("{$this->apiUrl}/orders/{$orderId}");

        if ($response->failed()) {
            Log::error('Revolut getOrder failed', [
                'order_id' => $orderId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    /**
     * Verify a Revolut webhook signature.
     *
     * Algorithm (from Revolut docs):
     * 1. Construct: payload_to_sign = "v1.{timestamp}.{raw_body}"
     * 2. Compute: HMAC-SHA256(signing_secret, payload_to_sign)
     * 3. Compare: "v1=" + hex_digest against each signature in Revolut-Signature header
     * 4. Validate: timestamp within 5 minutes of current UTC (replay protection)
     *
     * CRITICAL: $rawPayload must be the exact bytes from $request->getContent().
     * Do NOT trim whitespace, re-encode JSON, or alter the payload in any way —
     * even a single byte difference will invalidate the HMAC.
     *
     * @param string $rawPayload Raw request body (exact bytes from $request->getContent())
     * @param string $signatureHeader Revolut-Signature header value (may contain multiple comma-separated sigs)
     * @param string $timestampHeader Revolut-Request-Timestamp header value (UNIX timestamp in milliseconds)
     * @return bool True if signature is valid and timestamp is within tolerance
     */
    public function verifyWebhookSignature(string $rawPayload, string $signatureHeader, string $timestampHeader): bool
    {
        if (empty($this->webhookSecret)) {
            Log::warning('Revolut webhook secret not configured');
            return false;
        }

        // Step 4: Validate timestamp (5-minute tolerance for replay protection)
        // Both Revolut-Request-Timestamp and our comparison are in milliseconds since epoch.
        // This depends on the server clock being reasonably accurate (NTP-synced).
        // A clock drift beyond 5 minutes will cause all webhook verifications to fail.
        $webhookTimestamp = (int) $timestampHeader;
        $currentTimestamp = (int) (microtime(true) * 1000); // Current time in milliseconds
        $fiveMinutesMs = 5 * 60 * 1000;

        if (abs($currentTimestamp - $webhookTimestamp) > $fiveMinutesMs) {
            Log::warning('Revolut webhook timestamp outside 5-minute tolerance', [
                'webhook_timestamp_ms' => $timestampHeader,
                'current_timestamp_ms' => $currentTimestamp,
                'drift_ms' => abs($currentTimestamp - $webhookTimestamp),
            ]);
            return false;
        }

        // Step 1: Prepare the signing payload — "v1.{timestamp}.{raw_body}"
        $payloadToSign = "v1.{$timestampHeader}.{$rawPayload}";

        // Step 2: Compute expected HMAC-SHA256
        $expectedHex = hash_hmac('sha256', $payloadToSign, $this->webhookSecret);

        // Step 3: Compare against each signature in the header (may have multiple during rotation)
        $signatures = explode(',', $signatureHeader);
        foreach ($signatures as $sig) {
            $sig = trim($sig);
            if (str_starts_with($sig, 'v1=')) {
                $providedHex = substr($sig, 3); // Remove 'v1=' prefix
                if (hash_equals($expectedHex, $providedHex)) {
                    return true;
                }
            }
        }

        Log::warning('Revolut webhook signature mismatch');
        return false;
    }
}
```

---

## Implementation — Phase 2: Backend API Endpoints

### 2.1 Add methods to `PaymentController`

**File:** `app/Http/Controllers/Api/PaymentController.php`

Add constructor injection:
```php
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\SubscriptionPlan;
use App\Services\Payment\RevolutService;

class PaymentController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly RevolutService $revolutService
    ) {}
```

#### `plans()` — GET /api/payment/plans

```php
public function plans(): JsonResponse
{
    $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();

    return response()->json([
        'plans' => $plans->map(fn (SubscriptionPlan $plan) => [
            'slug' => $plan->slug,
            'name' => $plan->name,
            'monthly_price' => $plan->monthly_price,
            'yearly_price' => $plan->yearly_price,
            'features' => $plan->features,
        ]),
    ]);
}
```

#### `createOrder()` — POST /api/payment/create-order

Called by the Revolut widget's `createOrder` callback via the frontend.

```php
public function createOrder(Request $request): JsonResponse
{
    $user = $request->user();

    if ($user->is_preview_user) {
        return response()->json(['error' => 'Payment is not available in preview mode'], 403);
    }

    $request->validate([
        'plan' => 'required|string|in:student,standard,pro',
        'billing_cycle' => 'required|string|in:monthly,yearly',
    ]);

    $plan = SubscriptionPlan::findBySlug($request->input('plan'));
    if (! $plan) {
        return response()->json(['error' => 'Plan not found'], 404);
    }

    $billingCycle = $request->input('billing_cycle');
    $amount = $plan->getPriceForCycle($billingCycle); // Amount in pence
    $description = "{$plan->name} — " . ucfirst($billingCycle);

    try {
        // Ensure subscription record exists — may be null for users who never had one
        $subscription = $user->subscription ?? $user->subscription()->create([
            'plan' => 'free',
            'billing_cycle' => $billingCycle,
            'status' => 'trialing',
            'amount' => 0,
            'current_period_start' => now(),
            'current_period_end' => now(),
        ]);

        // Create pending Payment record FIRST so we have an ID for merchant_ref
        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'revolut_order_id' => 'pending', // Placeholder — updated below
            'amount' => $amount,
            'currency' => 'GBP',
            'status' => 'pending',
            'description' => $description,
            'plan_slug' => $plan->slug,
            'billing_cycle' => $billingCycle,
        ]);

        // Build redirect URL — user returns here after redirect-based payment methods.
        // Must be https:// in production. For sandbox, APP_URL must be a resolvable
        // domain (not localhost) or redirect-based methods won't work.
        $redirectUrl = config('app.url') . '/checkout?plan=' . $plan->slug
            . '&cycle=' . $billingCycle . '&status=complete';

        // POST to Revolut Merchant API: /api/orders
        $revolutOrder = $this->revolutService->createOrder(
            $amount,
            'GBP',
            $description,
            $redirectUrl,
            "payment_{$payment->id}",  // merchant_order_data.reference → echoed in webhook as merchant_order_ext_ref
            $user->email
        );

        // Update Payment with real Revolut order ID
        $payment->update([
            'revolut_order_id' => $revolutOrder['id'],
            'revolut_payment_data' => [
                'order_id' => $revolutOrder['id'],
                'token' => $revolutOrder['token'],
                'state' => $revolutOrder['state'],
                'created_at' => $revolutOrder['created_at'] ?? now()->toIso8601String(),
            ],
        ]);

        // Return token for SDK and order_id for confirm endpoint
        return response()->json([
            'token' => $revolutOrder['token'],      // Frontend returns { publicId: token } to widget
            'order_id' => $revolutOrder['id'],       // Frontend stores this for confirmPayment call
        ]);
    } catch (\Throwable $e) {
        return $this->errorResponse($e, 'Creating payment order');
    }
}
```

**Key points:**
- We create the `Payment` record **before** calling Revolut so we have a `payment.id` for the `merchant_order_data.reference` field (echoed back in webhooks as `merchant_order_ext_ref` for safe reconciliation).
- `plan_slug` and `billing_cycle` are persisted on the `Payment` so `confirmPayment()` and the webhook handler can set the subscription correctly without relying on stale subscription state.
- `redirect_url` is set to the checkout page so users return to the app after redirect-based payment methods (e.g. Pay by Bank). **Production constraint:** Must be `https://` (Revolut enforces `^https:\/\/` pattern). **Sandbox constraint:** Domain must be publicly resolvable — `localhost` will not work. `config('app.url')` is fine for production (fynla.org is HTTPS) but for local sandbox testing, set `APP_URL` to a resolvable domain or use a tunnel (e.g. ngrok). If the redirect_url is invalid, redirect-based payment methods may silently fail — card payments are unaffected.
- We return both `token` (for the widget's `{ publicId }`) and `order_id` (the UUID, for the confirm call). The frontend must store `order_id` because `onSuccess`'s `orderId` parameter is the TOKEN, not the UUID.

#### `confirmPayment()` — POST /api/payment/confirm

Called by `onSuccess` callback. Receives the **Revolut order UUID** (stored by frontend from createOrder response), NOT the token from the callback.

**Critical data model note:** `plan_slug` and `billing_cycle` are read from the `Payment` record (persisted during `createOrder()`), NOT from the current subscription state. This is because the subscription may be stale (expired, different plan, etc.) and the payment record is the single source of truth for what the user actually paid for.

```php
public function confirmPayment(Request $request): JsonResponse
{
    $user = $request->user();

    $request->validate([
        'order_id' => 'required|string', // Revolut order UUID from createOrder response
    ]);

    $orderId = $request->input('order_id');

    try {
        // Verify order state with Revolut: GET /api/orders/{order_id}
        $revolutOrder = $this->revolutService->getOrder($orderId);
        $state = $revolutOrder['state'];
        $captureMode = $revolutOrder['capture_mode'] ?? 'automatic';

        // For automatic capture (our default): require 'completed' — funds are already captured.
        // For manual capture: accept 'authorised' — funds are reserved but not yet captured.
        // We don't use manual capture, but handle it defensively.
        $acceptableStates = $captureMode === 'manual'
            ? ['completed', 'authorised']
            : ['completed'];

        if (! in_array($state, $acceptableStates)) {
            Log::warning('Revolut order not in acceptable state for confirmation', [
                'order_id' => $orderId,
                'state' => $state,
                'capture_mode' => $captureMode,
                'acceptable_states' => $acceptableStates,
            ]);
            return response()->json([
                'error' => 'Payment has not been completed yet',
                'state' => $state,
            ], 400);
        }

        // Activate subscription in DB transaction
        $result = DB::transaction(function () use ($user, $orderId, $revolutOrder) {
            $payment = Payment::where('revolut_order_id', $orderId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw new \RuntimeException("Payment not found for order: {$orderId}");
            }

            // Idempotent: if already completed, return early
            if ($payment->status === 'completed') {
                return ['already_completed' => true, 'payment' => $payment];
            }

            // Read plan and billing cycle from the Payment record (source of truth)
            $planSlug = $payment->plan_slug;
            $billingCycle = $payment->billing_cycle;

            // Compute period end from the payment's billing cycle
            $periodEnd = $billingCycle === 'monthly'
                ? now()->addMonth()
                : now()->addYear();

            // Look up the plan to get the amount for the subscription record
            $subscriptionPlan = SubscriptionPlan::findBySlug($planSlug);

            // Update Payment
            $payment->update([
                'status' => 'completed',
                'revolut_payment_data' => $revolutOrder,
            ]);

            // Update Subscription — set plan, billing_cycle, and period from payment data
            $subscription = $payment->subscription;
            $subscription->update([
                'status' => 'active',
                'plan' => $planSlug,
                'billing_cycle' => $billingCycle,
                'amount' => $subscriptionPlan ? $subscriptionPlan->getPriceForCycle($billingCycle) : $payment->amount,
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
                'revolut_order_id' => $orderId,
                'cancelled_at' => null,
                'cancellation_reason' => null,
            ]);

            // Update User denormalised fields
            $user->update([
                'plan' => $planSlug,
                'trial_ends_at' => null,
            ]);

            return ['already_completed' => false, 'payment' => $payment, 'subscription' => $subscription];
        });

        // Send confirmation email (only if newly activated)
        if (! $result['already_completed']) {
            try {
                Mail::to($user->email)->send(new PaymentConfirmation($user, $result['payment']));
            } catch (\Exception $e) {
                Log::error('Failed to send payment confirmation email', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription activated successfully',
        ]);
    } catch (\Throwable $e) {
        return $this->errorResponse($e, 'Confirming payment');
    }
}
```

### 2.2 Create `WebhookController`

**New file:** `app/Http/Controllers/Api/WebhookController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PaymentConfirmation;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Services\Payment\RevolutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WebhookController extends Controller
{
    public function __construct(
        private readonly RevolutService $revolutService
    ) {}

    /**
     * Handle Revolut webhook events.
     *
     * POST /api/webhooks/revolut
     *
     * Headers: Revolut-Signature, Revolut-Request-Timestamp
     * Body: { event, order_id }
     *
     * Responds 200 to acknowledge. Revolut retries 3x with 10-min delay on failure.
     */
    public function handleRevolut(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $signatureHeader = $request->header('Revolut-Signature', '');
        $timestampHeader = $request->header('Revolut-Request-Timestamp', '');

        // Verify HMAC signature (v1.{timestamp}.{payload})
        if (! $this->revolutService->verifyWebhookSignature($rawPayload, $signatureHeader, $timestampHeader)) {
            Log::warning('Revolut webhook signature verification failed');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawPayload, true);
        $event = $payload['event'] ?? null;
        $orderId = $payload['order_id'] ?? null;
        $merchantRef = $payload['merchant_order_ext_ref'] ?? null;

        Log::info('Revolut webhook received', [
            'event' => $event,
            'order_id' => $orderId,
            'merchant_order_ext_ref' => $merchantRef,
        ]);

        // Process order completion events.
        // ORDER_COMPLETED = automatic capture (funds captured).
        // ORDER_AUTHORISED = manual capture (funds reserved, not yet captured).
        // We use automatic capture, so ORDER_COMPLETED is the primary event.
        // ORDER_AUTHORISED is handled defensively in case capture_mode changes.
        if (in_array($event, ['ORDER_COMPLETED', 'ORDER_AUTHORISED']) && $orderId) {
            $this->handleOrderCompleted($orderId, $merchantRef);
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleOrderCompleted(string $orderId, ?string $merchantRef): void
    {
        try {
            DB::transaction(function () use ($orderId, $merchantRef) {
                // Primary lookup by Revolut order UUID
                $payment = Payment::where('revolut_order_id', $orderId)
                    ->lockForUpdate()
                    ->first();

                if (! $payment) {
                    Log::warning('Revolut webhook: payment not found', [
                        'order_id' => $orderId,
                        'merchant_ref' => $merchantRef,
                    ]);
                    return;
                }

                // Cross-reference check: if merchant_ref was provided, verify it matches
                if ($merchantRef && $merchantRef !== "payment_{$payment->id}") {
                    Log::warning('Revolut webhook: merchant_ref mismatch', [
                        'order_id' => $orderId,
                        'expected' => "payment_{$payment->id}",
                        'received' => $merchantRef,
                    ]);
                }

                // Idempotent: skip if already completed
                if ($payment->status === 'completed') {
                    Log::info('Revolut webhook: payment already completed', ['order_id' => $orderId]);
                    return;
                }

                // Verify with Revolut API
                $revolutOrder = $this->revolutService->getOrder($orderId);
                $captureMode = $revolutOrder['capture_mode'] ?? 'automatic';
                $acceptableStates = $captureMode === 'manual'
                    ? ['completed', 'authorised']
                    : ['completed'];

                if (! in_array($revolutOrder['state'], $acceptableStates)) {
                    Log::warning('Revolut webhook: order not in acceptable state', [
                        'order_id' => $orderId,
                        'state' => $revolutOrder['state'],
                        'capture_mode' => $captureMode,
                    ]);
                    return;
                }

                // Read plan and billing cycle from the Payment record (source of truth)
                $planSlug = $payment->plan_slug;
                $billingCycle = $payment->billing_cycle;

                // Compute period end from the payment's billing cycle
                $periodEnd = $billingCycle === 'monthly'
                    ? now()->addMonth()
                    : now()->addYear();

                $subscriptionPlan = SubscriptionPlan::findBySlug($planSlug);

                // Activate payment
                $payment->update([
                    'status' => 'completed',
                    'revolut_payment_data' => $revolutOrder,
                ]);

                // Update subscription from payment data (not stale subscription state)
                $subscription = $payment->subscription;
                $subscription->update([
                    'status' => 'active',
                    'plan' => $planSlug,
                    'billing_cycle' => $billingCycle,
                    'amount' => $subscriptionPlan ? $subscriptionPlan->getPriceForCycle($billingCycle) : $payment->amount,
                    'current_period_start' => now(),
                    'current_period_end' => $periodEnd,
                    'revolut_order_id' => $orderId,
                    'cancelled_at' => null,
                    'cancellation_reason' => null,
                ]);

                $user = $payment->user;
                $user->update([
                    'plan' => $planSlug,
                    'trial_ends_at' => null,
                ]);

                // Send confirmation email
                try {
                    Mail::to($user->email)->send(new PaymentConfirmation($user, $payment));
                } catch (\Exception $e) {
                    Log::error('Webhook: failed to send payment confirmation email', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                Log::info('Revolut webhook: subscription activated', [
                    'user_id' => $user->id,
                    'order_id' => $orderId,
                    'plan' => $planSlug,
                    'billing_cycle' => $billingCycle,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Revolut webhook processing failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

### 2.3 Register Routes

**File:** `routes/api.php`

Inside existing `auth:sanctum` payment group (lines 937-943), add 3 routes:
```php
Route::get('/plans', [\App\Http\Controllers\Api\PaymentController::class, 'plans']);
Route::post('/create-order', [\App\Http\Controllers\Api\PaymentController::class, 'createOrder'])->middleware('throttle:10,1');
Route::post('/confirm', [\App\Http\Controllers\Api\PaymentController::class, 'confirmPayment'])->middleware('throttle:10,1');
```

Outside auth group (webhook is unauthenticated — verified by HMAC signature):
```php
// Revolut webhook (no auth:sanctum — verified by HMAC signature)
Route::post('/webhooks/revolut', [\App\Http\Controllers\Api\WebhookController::class, 'handleRevolut'])->middleware('throttle:60,1');
```

### 2.4 Middleware — No Changes Needed

- **CSRF**: All `api/*` routes already excluded in `VerifyCsrfToken.php` (`$except = ['api/*']`)
- **PreviewWriteInterceptor**: Webhook has no Bearer token → `resolveUserFromToken()` returns null → passes through. `create-order` and `confirm` block preview users explicitly in controller code.
- **CheckSubscription**: Already excludes `api/payment/` and `api/webhooks/` prefix paths.

---

## Implementation — Phase 3: Frontend

### 3.1 Create `PlanSelectionModal`

**New file:** `resources/js/components/Payment/PlanSelectionModal.vue`

Modal showing 3 plan cards with monthly/yearly toggle. Follows codebase modal pattern from `designStyle.md`:
- Fixed overlay with `z-50`, grey backdrop `bg-gray-500 bg-opacity-75`
- White panel `bg-white rounded-lg shadow-xl`
- Emits `@select` with `{ plan, billingCycle }` and `@close`
- Fetches plans from `GET /api/payment/plans` on mount
- Uses `currencyMixin` — plan prices from API are in pence, divide by 100 for display
- Defaults to yearly billing cycle
- Shows savings percentage: `Math.round((1 - plan.yearly_price / (plan.monthly_price * 12)) * 100)`
- Standard plan has "Most Popular" badge
- No amber/orange colours — blue for highlights, primary-600 for CTAs

### 3.2 Rebuild `CheckoutPage`

**File:** `resources/js/views/Auth/CheckoutPage.vue` (replace placeholder)

Key implementation details for the Revolut integration:

**1. Read route query params:**
```javascript
computed: {
  plan() { return this.$route.query.plan; },
  billingCycle() { return this.$route.query.cycle; },
},
```

**2. Import SDK (npm package, not CDN):**

The SDK is installed via `npm install @revolut/checkout` and imported at the top of the component script:
```javascript
import RevolutCheckout from '@revolut/checkout'
```

No CDN script tag, no dynamic script loading, no `window.RevolutCheckout`. Standard npm import.

**3. Initialise embeddedCheckout — EXACT match to SDK docs:**
```javascript
async initCheckout() {
  try {
    const { destroy } = await RevolutCheckout.embeddedCheckout({
      publicToken: import.meta.env.VITE_REVOLUT_PUBLIC_KEY,
      mode: import.meta.env.VITE_REVOLUT_SANDBOX === 'true' ? 'sandbox' : 'prod',
      locale: 'auto',
      target: this.$refs.checkoutContainer,
      createOrder: async () => {
        // Called by widget when user clicks Pay
        // POST to our backend, which calls Revolut POST /api/orders
        const response = await api.post('/payment/create-order', {
          plan: this.plan,
          billing_cycle: this.billingCycle,
        });
        // Store the internal UUID for confirmPayment call
        // CRITICAL: onSuccess's orderId is the TOKEN, not the UUID
        this.revolutOrderId = response.data.order_id;
        // Return token to widget as { publicId }
        return { publicId: response.data.token };
      },
      onSuccess: ({ orderId }) => {
        // orderId here is the ORDER TOKEN (not UUID) per Revolut docs
        // We use this.revolutOrderId (the UUID) for the confirm call
        this.handlePaymentSuccess();
      },
      onError: ({ error, orderId }) => {
        this.paymentError = error.message || 'Payment failed. Please try again.';
      },
      onCancel: ({ orderId }) => {
        // User cancelled — stay on page
      },
      email: this.userEmail,
    });
    this.destroyWidget = destroy;
  } catch (error) {
    if (error.name === 'RevolutCheckout') {
      this.error = 'Failed to initialise checkout: ' + error.message;
    } else {
      this.error = 'Failed to initialise payment system.';
    }
  }
},
```

**4. Handle success — use stored UUID, not callback's orderId:**
```javascript
async handlePaymentSuccess() {
  this.processing = true;
  try {
    // Pass the Revolut order UUID (from createOrder response), NOT the token
    await api.post('/payment/confirm', { order_id: this.revolutOrderId });
    this.paymentComplete = true;
  } catch {
    // Webhook will handle as backup — still show success to user
    this.paymentComplete = true;
  } finally {
    this.processing = false;
  }
},
```

**5. Cleanup:**
```javascript
beforeUnmount() {
  if (this.destroyWidget) {
    this.destroyWidget();
  }
},
```

**6. Layout:**
- `AppLayout` wrapper
- Back button at top
- Two-column on desktop (left: order summary card, right: widget container), stacked on mobile
- `<div ref="checkoutContainer"></div>` for the widget target
- Success modal overlay: green checkmark, "Payment Successful", "Go to Dashboard" button → `this.$router.push('/dashboard')`

### 3.3 Update `TrialCountdownBanner`

**File:** `resources/js/components/Trial/TrialCountdownBanner.vue`

- Replace `<router-link to="/checkout">` (lines 30-35) with `<button @click="showPlanModal = true">` (same classes)
- Import and render `PlanSelectionModal`:
  ```html
  <PlanSelectionModal
    v-if="showPlanModal"
    @select="handlePlanSelect"
    @close="showPlanModal = false"
  />
  ```
- Add to `data`: `showPlanModal: false`
- Add method:
  ```javascript
  handlePlanSelect({ plan, billingCycle }) {
    this.showPlanModal = false;
    this.$router.push(`/checkout?plan=${plan}&cycle=${billingCycle}`);
  },
  ```

### 3.4 Update `SubscriptionManagement`

**File:** `resources/js/components/UserProfile/SubscriptionManagement.vue`

- Replace all 4 `<router-link to="/checkout">` (lines 92, 215, 265, 319) with `<button @click="showPlanModal = true">` (keep same classes: `btn-primary w-full text-center block`)
- Change button text from "Resubscribe" to "Renew" (pseudo-subscription model — no auto-recurring billing)
- Import `PlanSelectionModal`
- Add `showPlanModal` ref to setup
- Render `<PlanSelectionModal v-if="showPlanModal" ...>` same as banner
- Add `handlePlanSelect` method same as banner

---

## Files Summary

### New Files (4)
| File | Purpose |
|------|---------|
| `app/Services/Payment/RevolutService.php` | Revolut Merchant API wrapper: `createOrder()`, `getOrder()`, `verifyWebhookSignature()` |
| `app/Http/Controllers/Api/WebhookController.php` | Revolut webhook handler for `ORDER_COMPLETED` / `ORDER_AUTHORISED` |
| `resources/js/components/Payment/PlanSelectionModal.vue` | Plan selection modal with monthly/yearly toggle |
| `database/migrations/2026_02_25_100001_add_columns_to_payments_table.php` | Add `description`, `plan_slug`, `billing_cycle` columns to payments table |

### Modified Files (8)
| File | Changes |
|------|---------|
| `.env` / `.env.example` | Add `REVOLUT_PUBLIC_KEY`, `VITE_` prefix vars, `PAYMENT_ENABLED=true` |
| `config/services.php` | Add `revolut` config block |
| `app/Models/Subscription.php` | Add `revolut_order_id`, `revolut_subscription_id` to `$fillable` |
| `app/Models/Payment.php` | Add `revolut_order_id`, `revolut_payment_data`, `description`, `plan_slug`, `billing_cycle` to `$fillable`/`$casts` |
| `app/Http/Controllers/Api/PaymentController.php` | Add constructor, `plans()`, `createOrder()`, `confirmPayment()` |
| `routes/api.php` | Add 3 authenticated payment routes + 1 unauthenticated webhook route |
| `resources/js/views/Auth/CheckoutPage.vue` | Full rebuild with Revolut `embeddedCheckout()` |
| `resources/js/components/Trial/TrialCountdownBanner.vue` | Open PlanSelectionModal instead of `/checkout` link |
| `resources/js/components/UserProfile/SubscriptionManagement.vue` | Open PlanSelectionModal instead of `/checkout` links; "Renew" not "Resubscribe" |

### Build Order
```
1. Migration (description, plan_slug, billing_cycle columns) ← no deps
2. Config (.env, .env.example, services.php)                 ← no deps
3. Models (Subscription, Payment $fillable)                  ← no deps
4. RevolutService                                            ← depends on config
5. PaymentController (3 new methods)                         ← depends on RevolutService, models
6. WebhookController                                         ← depends on RevolutService, models
7. Routes                                                    ← depends on controllers
8. PlanSelectionModal                                        ← depends on plans API
9. CheckoutPage                                              ← depends on create-order + confirm APIs
10. TrialCountdownBanner update                              ← depends on PlanSelectionModal
11. SubscriptionManagement update                            ← depends on PlanSelectionModal
```

---

## Testing Checklist

1. `PAYMENT_ENABLED=true` in `.env`
2. `php artisan migrate && php artisan db:seed`
3. `./dev.sh`
4. Log in as trial user
5. Trial banner → "Upgrade Now" → plan selection modal with 3 plans
6. Yearly default, savings percentages correct
7. Select plan → `/checkout?plan=standard&cycle=yearly`
8. Revolut embedded widget loads showing all enabled and applicable payment methods
9. Sandbox test card: `4929 4212 3460 8027`, future expiry, any CVV
10. `onSuccess` fires → confirm called with stored UUID → success modal
11. "Go to Dashboard" → banner gone
12. User Profile > Subscription → "Active" with renewal date
13. DB: `subscription.status = active`, `subscription.plan = standard`, `subscription.billing_cycle = yearly`
14. DB: `payment.status = completed`, `payment.description` populated, `payment.plan_slug = standard`, `payment.billing_cycle = yearly`
15. DB: `payment.revolut_order_id` matches the Revolut order UUID
16. Payment confirmation email sent

### Edge Cases
- Browser close during payment → webhook processes `ORDER_COMPLETED` and activates subscription
- Double-confirm → idempotent (skips if already completed)
- Preview user → 403 on `create-order`
- Invalid plan → 422 validation error
- Revolut API failure → `SanitizedErrorResponse` returns safe error; pending Payment record has `revolut_order_id = 'pending'` and will not match any webhook
- Webhook replay attack → rejected by 5-minute timestamp tolerance
- Multiple webhook signatures during rotation → all checked
- Webhook `merchant_order_ext_ref` cross-referenced against `payment_{id}` — mismatch logged as warning
- Redirect-based payment method (Pay by Bank) → user redirected to `redirect_url` → returns to checkout page → confirm flow completes
- Renewal after expiry → same flow, `confirmPayment` reads `plan_slug`/`billing_cycle` from new payment record, not stale subscription
- Plan change (user was on student, pays for pro) → `confirmPayment` sets `subscription.plan = pro` from payment record
