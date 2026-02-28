# Revolut Checkout Iframe Height Bug Fix

**Date:** 2026-02-26
**File:** `resources/js/views/Auth/CheckoutPage.vue`

## Bug

The Revolut embedded checkout payment form was invisible on the checkout page (`/checkout?plan=...&cycle=...`). The order summary displayed correctly, but the "Payment Method" section appeared empty - no payment options (Revolut Pay, Card, Google Pay) were visible.

### Root Cause

The Revolut SDK creates an iframe (`sandbox-merchant.revolut.com/embedded-checkout.html`) and uses a postMessage-based mechanism to auto-resize the iframe height based on its content. The CSS on the checkout container was:

```css
.revolut-checkout-container {
  overflow: hidden;
}
.revolut-checkout-container :deep(iframe) {
  margin-top: -40px;
}
```

`overflow: hidden` on the container was interfering with the Revolut SDK's iframe auto-resize mechanism. The SDK was setting the iframe height to only `13px`, making the payment form content (which was fully loaded inside the iframe) completely invisible.

The `overflow: hidden` was originally added to clip the iframe's negative margin, which hides Revolut's duplicate "Payment method" heading.

### Evidence

- Network requests all returned 200 OK (order creation, payment methods, SDK resources)
- The iframe `src` was correct (`sandbox-merchant.revolut.com/embedded-checkout.html`)
- Iframe HTML content contained the full payment form (Revolut Pay, Card, Google Pay)
- Iframe inline style was `height: 13px` - the SDK's auto-resize was broken
- Manually forcing the iframe height to 500px revealed the fully rendered payment form

## Fix

Replaced `overflow: hidden` on the container with `clip-path: inset(40px 0 0 0)` on the iframe itself:

```css
.revolut-checkout-container :deep(iframe[src*="embedded-checkout"]) {
  margin-top: -40px;
  clip-path: inset(40px 0 0 0);
  min-height: 500px !important;
}
```

### How it works

1. **`clip-path: inset(40px 0 0 0)`** on the iframe clips the top 40px of the iframe content (Revolut's duplicate "Payment method" heading) without using `overflow: hidden` on the parent container
2. **`margin-top: -40px`** pulls the iframe up so there's no gap where the clipped heading was
3. **`min-height: 500px !important`** is a safety net that ensures the iframe is always visible, even if the SDK's auto-resize sets a tiny initial height
4. **`iframe[src*="embedded-checkout"]`** selector targets only the checkout iframe, not the Revolut upsell modals iframe

### Result

- Revolut SDK auto-resize now works correctly (measured at 519px on load)
- Duplicate heading is hidden via clip-path
- Card form expands properly when selected (SDK resizes dynamically)
- All three payment methods visible: Revolut Pay, Card (Visa/Mastercard), Google Pay

## Deploy

Upload: `resources/js/views/Auth/CheckoutPage.vue` (requires frontend rebuild)
