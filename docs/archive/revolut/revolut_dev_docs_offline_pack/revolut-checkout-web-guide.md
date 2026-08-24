# Accept payments via Revolut Checkout — Web (Implementation guide)

Source page: https://developer.revolut.com/docs/guides/accept-payments/online-payments/revolut-checkout/web

> Note: This is an offline capture of the key integration steps and code shown on the docs page, including the high-level flow, install steps, and core init/mount examples.

## What Revolut Checkout is

Revolut Checkout is an embedded widget that aggregates available payment methods (Card, Apple Pay, Google Pay, Pay by Bank, etc.) into a single, managed UI. Ordering and availability are configured in the Revolut Business Dashboard (not in code).

## When to use

Use embedded checkout when:
- You want a complete checkout solution with all payment methods in one widget
- You prefer configuration via Revolut Business dashboard over code
- You need automatic payment method ordering based on customer location/preferences
- You want to add new payment methods without deploying code changes

Use individual payment methods when:
- You need granular control over payment button placement
- You want custom UI around each method
- You need different payment methods on different pages
- You require specific customisation beyond the widget

## How it works (components)

1) Server-side: an endpoint is required to securely call the Merchant API to create orders.
2) Client-side: the widget uses your public API key to initialise and mount; it uses the order token to initiate payment.
3) Webhooks endpoint: your server should listen for webhook events to reliably track the payment lifecycle (recommended).

Payment flow:
1. Customer arrives on checkout page.
2. Frontend displays widget with enabled methods.
3. Customer selects method and proceeds.
4. Frontend calls server endpoint to create an order; receives order token.
5. Widget processes payment; frontend receives callbacks.
6. Server receives webhooks (recommended for reliability).

## Implementation overview

1. Set up an endpoint for creating orders
2. Install Revolut Checkout package
3. Initialise and mount the widget
4. Handle payment results

## Implement Revolut Checkout

### 1) Set up an endpoint for creating orders

You must create a server endpoint that uses your **secret** API key (never expose it to the browser). Your frontend calls this endpoint; it calls the Merchant API to create the order and returns the order token to the frontend.

(Example Merchant API response fields shown in the docs include `id`, `token`, `state`, `amount`, `currency`, `capture_mode`, `checkout_url`, etc.)

### 2) Install Revolut Checkout package

Install the SDK package (use latest version of `@revolut/checkout`):

```bash
npm install @revolut/checkout
```

Then import:

```js
import RevolutCheckout from '@revolut/checkout'
```

Alternative: you can also add an embed script directly (see the Installation section on the docs site).

### 3) Initialise and mount the widget

#### 3.1 Initialise the SDK

```js
import RevolutCheckout from '@revolut/checkout'

const { destroy } = await RevolutCheckout.embeddedCheckout({
  publicToken: '<yourPublicApiKey>',
  mode: 'prod', // 'prod' for production, 'sandbox' for testing
  locale: 'en'  // optional, defaults to 'auto'
  // Configuration will be added in next steps
})

// Call destroy() later if you need to remove the widget
```

Required parameters:
- `publicToken` — your Merchant API public key
- `mode` — `'prod'` or `'sandbox'`

Optional:
- `locale` — widget language (default `'auto'`)

#### 3.2 Add a DOM element

```html
<div id="checkout-container"></div>
```

#### 3.3 Mount and configure

At mount time, include:
- `target`: the DOM element
- `createOrder`: async callback that calls your backend endpoint and returns `{ publicId: order.token }`
- Optional callbacks: `onSuccess`, `onError`, `onCancel`

(See the Embedded Checkout API reference for the full signature and options.)

### 4) Handle payment results: callbacks vs webhooks

The docs highlight two complementary mechanisms:
- Client-side callbacks (`onSuccess`, `onError`, `onCancel`) for immediate UI updates
- Server-side webhooks for reliable lifecycle tracking and backend actions (recommended)

Typical webhook-backed backend actions:
- confirm completion before fulfilment
- update inventory
- initiate shipping / delivery
- release digital goods/access
- record transaction in DB
- send confirmation emails
- process refunds / chargebacks

High-level webhook flow:
1) order status changes
2) Revolut sends HTTP POST to your webhook endpoint
3) your server processes and performs backend operations
4) your server responds `200 OK`

### Customise Revolut Checkout (via dashboard)

Advantages: enable/disable methods, reorder priority, add modules, configure card schemes — all in Revolut Business dashboard:
`APIs > Merchant API tab > Revolut Checkout`

#### Reorder payment methods (as described in docs)

1. Enter reorder mode
2. Use drag handles next to payment methods
3. Drag & drop to preferred order
4. Save changes

## Examples section (init + mount)

Docs include full examples (async/await and Promise `.then()`). The key pattern:

```js
import RevolutCheckout from '@revolut/checkout'

const { destroy } = await RevolutCheckout.embeddedCheckout({
  publicToken: '<yourPublicApiKey>',
  mode: 'prod',
  locale: 'en', // optional
  target: document.getElementById('checkout-container'),
  createOrder: async () => {
    // call your backend create-order endpoint and return { publicId: token }
  },
  onSuccess({ orderId }) { /* UI success */ },
  onError({ error, orderId }) { /* UI error */ },
  onCancel({ orderId }) { /* UI cancel */ }
})
```

## Testing checklist (captured)

- Checkout completes successfully with test cards for successful payment.
- `onSuccess` triggers as expected.
- `onError` triggers for failed payments.
- `onCancel` triggers on cancel.
- Errors handled gracefully (including error-case test cards, network errors).

