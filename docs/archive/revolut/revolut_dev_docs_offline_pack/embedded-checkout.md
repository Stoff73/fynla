# Revolut Merchant Web SDK — Embedded checkout (`embeddedCheckout()`)

Source page: https://developer.revolut.com/docs/sdks/merchant-web-sdk/payment-methods/embedded-checkout

## Overview

Mounts the Revolut Checkout widget to a DOM element, providing access to all enabled payment methods through a single, unified interface. The widget aggregates Revolut Pay, Card, Apple Pay, Google Pay, Pay by Bank, and other payment methods configured in your Business Dashboard.

Key features:
- Unified widget for all payment methods
- Dashboard-configured (no code changes to add/reorder methods)
- Automatic payment method optimisation
- Built-in customisation options

For a complete implementation guide with examples, see:
- https://developer.revolut.com/docs/guides/accept-payments/online-payments/revolut-checkout/web

## Prerequisites

** Make sure we have this first **

This payment method requires direct initialisation:
- https://developer.revolut.com/docs/sdks/merchant-web-sdk/initialisation/direct

# Direct initialisation
Initialise the SDK with your public API key to access the embedded checkout widget with all payment methods.

Direct initialisation creates an EmbeddedCheckoutInstance with unified payment methods. Use this method when you want all payment methods in one widget and create orders on-demand during checkout.

Payment method	Reference	Integration guide
Embedded checkout	SDK reference	Guide
Payment methods available:

Card, Revolut Pay, Apple Pay, Google Pay, Pay by Bank (configured via Revolut Business)

Type signature​
```ts
RevolutCheckout.embeddedCheckout(options: {
  publicToken: string
  mode?: 'prod' | 'sandbox'
  locale?: Locale | 'auto'
}): Promise<EmbeddedCheckoutInstance>
```

Parameters​
Parameter	Description	Type	Required
publicToken	Your Merchant API public key.	string	Yes
mode	API environment. Default: 'prod'	'prod' | 'sandbox'	No
locale	Widget language. Default: 'auto'	Locale | 'auto'	No
Tip
The mode parameter must match the environment used for your public API key. Production keys work only in 'prod' mode, and sandbox keys only in 'sandbox' mode.

For additional configuration options including target, createOrder, callbacks, and customer details, see the Embedded checkout reference.

Returns​

Returns a `Promise<EmbeddedCheckoutInstance>` with the following methods:
```ts
interface EmbeddedCheckoutInstance {
  destroy: () => void
}
```

`destroy` - Manually destroy the instance and remove the widget from the page

Example
```ts​
import RevolutCheckout from '@revolut/checkout'

const { destroy } = await RevolutCheckout.embeddedCheckout({
  publicToken: 'pk_...',
  mode: 'prod',
  locale: 'auto'
})
```

## Type signature

```ts
RevolutCheckout.embeddedCheckout: (
  options: EmbeddedCheckoutOptions
) => Promise<EmbeddedCheckoutInstance>

interface EmbeddedCheckoutOptions {
  publicToken: string
  mode: 'prod' | 'sandbox'
  locale?: Locale | 'auto'
  target: HTMLElement
  createOrder: () => Promise<{ publicId: string }>
  onSuccess?: (payload: { orderId: string }) => void
  onError?: (payload: { error: RevolutCheckoutError; orderId: string }) => void
  onCancel?: (payload: { orderId: string | undefined }) => void
  email?: string
  billingAddress?: Address
}

interface EmbeddedCheckoutInstance {
  destroy: () => void
}
```

## Parameters

`options` — configuration object for the embedded checkout widget.

### `EmbeddedCheckoutOptions`

- `publicToken` — your Merchant API public key (string, required)
- `mode` — API environment: `'prod' | 'sandbox'` (required)
- `locale` — widget language: `Locale | 'auto'` (optional, default `'auto'`)
- `target` — DOM element where the widget mounts: `HTMLElement` (required)
- `createOrder` — async function that calls your backend to create an order and returns the order token: `() => Promise<{ publicId: string }>` (required)
- `onSuccess` — called when payment completes successfully: `(payload: { orderId: string }) => void` (optional)
- `onError` — called when payment fails; receives a `RevolutCheckoutError`: `(payload: { error: RevolutCheckoutError, orderId: string }) => void` (optional)
- `onCancel` — called when user cancels; `orderId` may be `undefined` if order creation failed: `(payload: { orderId?: string }) => void` (optional)
- `email` — pre-fill customer email (optional)
- `billingAddress` — pre-fill customer billing address (optional)

## Return value

Resolves to an `EmbeddedCheckoutInstance` containing:
- `destroy(): void` — remove the widget from the page and clean up resources

## Callback events notes

Important reliability note:
- Widget callbacks are not guaranteed to fire (network issues, browser closures, ad blockers). Use webhooks for critical backend operations like order fulfilment.

Also:
- In callbacks, `orderId` refers to the order public token (e.g., `order.token` from API response), not internal order id.

## Error handling

Throws: `RevolutCheckoutError`

Typical causes:
- invalid `publicToken`
- failed order creation in `createOrder`
- network issues
- invalid config
- widget loading failures

Example:

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

## Usage example

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

// Later:
// destroy()
```

### Pre-fill customer information

```js
const { destroy } = await RevolutCheckout.embeddedCheckout({
  // ... other config
  email: 'customer@example.com',
  billingAddress: {
    countryCode: 'GB',
    region: 'Greater London',
    city: 'London',
    postcode: 'EC1A 1BB',
    streetLine1: '1 Example Street',
    streetLine2: 'Flat 2B'
  }
})
```

## See also

- Web guide: https://developer.revolut.com/docs/guides/accept-payments/online-payments/revolut-checkout/web
- Direct initialisation: https://developer.revolut.com/docs/sdks/merchant-web-sdk/initialisation/direct
