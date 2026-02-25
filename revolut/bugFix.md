# Bug Fix: Revolut Widget Loading Error

## Symptoms

- Console: `[Checkout init failed] TypeError: RevolutCheckout.embeddedCheckout is not a function`
- UI: "Failed to initialise payment system. Please try again."

## Root Cause (3 issues)

### 1. SDK API Mismatch

The npm package `@revolut/checkout` v1.1.24 exports `RevolutCheckoutLoader` as its default — a **function** `(token, mode) => Promise<RevolutCheckoutInstance>`. It does NOT have a static `.embeddedCheckout()` method.

`embeddedCheckout` only exists on `RevolutCheckoutInstance` (returned by calling `RevolutCheckout(orderToken, mode)`), which requires an order token upfront — a chicken-and-egg problem since the embedded checkout widget creates orders on demand via its `createOrder` callback.

The implementation plan docs describe the **CDN script tag API** where the global `RevolutCheckout` object has `embeddedCheckout` as a static method accepting `{ publicToken, mode, ... }`. The npm package wrapper does not expose this.

### 2. Content Security Policy Blocking CDN Script

After switching to CDN loading, the `SecurityHeaders` middleware blocked the external script with: `Loading the script 'https://sandbox-merchant.revolut.com/embed.js' violates the following Content Security Policy directive: "script-src 'self' 'unsafe-inline' ..."`.

The Revolut widget also requires `frame-src`, `connect-src`, and `img-src` permissions for its iframes, API calls, and assets.

### 3. Revolut API Rejecting localhost redirect_url

`PaymentController::createOrder()` built `redirect_url` from `config('app.url')` which is `http://localhost:8000` in dev. Revolut's API returns 400: `"host must not be equal to localhost"`.

## Resolution

### File: `resources/js/views/Auth/CheckoutPage.vue`
- Removed `import RevolutCheckout from '@revolut/checkout'`
- Added `loadRevolutSDK(sandbox)` helper that dynamically loads `https://sandbox-merchant.revolut.com/embed.js` (or prod equivalent) via script tag
- `initCheckout()` now awaits SDK load then calls `window.RevolutCheckout.embeddedCheckout(...)` — the documented static API

### File: `package.json`
- Removed `@revolut/checkout` npm dependency (no longer needed)

### File: `app/Http/Middleware/SecurityHeaders.php`
- Added Revolut domains (`sandbox-merchant.revolut.com`, `merchant.revolut.com`, `sandbox-assets.revolut.com`, `assets.revolut.com`) to CSP `script-src`, `connect-src`, `frame-src`, and `img-src`
- Updated `Permissions-Policy` to allow `payment` for Revolut domains (needed for Apple Pay/Google Pay)

### File: `app/Http/Controllers/Api/PaymentController.php`
- Fixed `redirect_url` to use `https://fynla.org` when in sandbox mode instead of `localhost`

## Verified

Widget loads successfully showing Revolut Pay, Card (Visa/Mastercard), and Google Pay payment methods. No console errors.
