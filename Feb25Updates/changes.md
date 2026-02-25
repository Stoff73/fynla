# Feb 25 Updates — Auth Flow Fixes & Revolut Embedded Checkout

## Summary

Fixed confusing auth flow UX (login error hints, duplicate registration) and implemented Revolut Embedded Checkout using the **direct initialisation** pattern (`RevolutCheckout.embeddedCheckout()` static method). The embedded widget renders all payment methods (Card, Google Pay, Apple Pay, Revolut Pay) inline on the page.

Branch: `revolut`

## What Changed

### 1. AuthController.php — Existing Email Registration Fix

**Problem:** When a user tried to register with an email that already existed, the backend returned a fake success response (`201` with `requires_verification: true` and `pending_id: 0`) as an anti-enumeration measure. This caused the verification modal to appear, and clicking "resend code" failed with "Registration not found" because `PendingRegistration::find(0)` returned null.

**Fix:** Changed to return a clear `422` error with `email_exists: true` flag and a helpful message directing the user to sign in or reset their password.

### 2. Login.vue — Error Message Hints

**Problem:** When login failed with "incorrect username/password", there was no "Forgot your password?" link shown in the error area.

**Fix:**
- Renamed `showRegisterHint` to `showLoginHints` and broadened matching to include `invalid`, `no account`, and `incorrect`
- Added "Forgot your password?" button (opens ForgotPasswordModal) inside the error message area
- Added "Register here" link alongside

### 3. Register.vue — Email Exists Handling

**Problem:** No handling for the `email_exists` error response. Registration catch block only handled validation errors and generic messages.

**Fix:**
- Added `emailExists` ref
- Added check for `error.response?.data?.email_exists` in catch block
- Shows "Sign in to your account" link when an existing email is detected

### 4. AuthController.php — Default Trial on Registration

**Problem:** When a user registered directly from `/register` (not via the pricing page), no trial subscription was created.

**Fix:** Registration now always starts a trial. If no plan was selected, it defaults to `standard` with yearly billing.

### 5. CheckoutPage.vue — Revolut Inline Card Field (Token-based Initialisation)

**Problem:** The static `RevolutCheckout.embeddedCheckout({ publicToken })` method (CDN direct initialisation) showed "Failed to load — An error has occurred. Please contact the merchant to resolve this problem" inside Revolut's iframe. The `embeddedCheckout` static method is deprecated/broken in the current SDK — it's not documented in the latest Revolut developer docs.

**Root cause:** The static `embeddedCheckout` method (public-key-based, on-demand order creation) no longer works. The correct approach is the **token-based** pattern: create an order first, then initialise with `RevolutCheckout(orderToken, mode)` to get an instance, then use `instance.createCardField()` to render card input inline.

**Fix:** Rewrote CheckoutPage.vue to use the token-based approach:
1. On page load: create an order via `POST /api/payment/create-order` to get the order token
2. Load Revolut SDK from CDN (`embed.js`)
3. Call `RevolutCheckout(orderToken, mode)` which returns a Promise resolving to an instance
4. Call `instance.createCardField({ target, onStatusChange, onValidation, onSuccess, onError })` to render card input inline
5. User fills in card details → clicks "Pay" button → calls `cardField.submit({ name, email, savePaymentMethodFor: 'merchant' })`

```javascript
// Step 1: Create order upfront
const orderResponse = await api.post('/payment/create-order');
const orderToken = orderResponse.data.publicId;

// Step 2: Load SDK and initialise with order token
const RC = await loadRevolutSDK(mode);
const instance = await RC(orderToken, mode);

// Step 3: Render card field inline
this.cardField = instance.createCardField({
  target: this.$refs.cardFieldContainer,
  onStatusChange: (status) => { this.cardCompleted = status.completed; },
  onValidation: (errors) => { this.cardErrors = errors.map(e => e.message); },
  onSuccess: () => { ... },
  onError: (error) => { ... },
});

// Step 4: User clicks Pay button
this.cardField.submit({ name, email, savePaymentMethodFor: 'merchant' });
```

### 6. PaymentController.php — Updated createOrder Endpoint

**Change:** `POST /api/payment/create-order` now:
- Calls `ensureRevolutCustomer()` before creating the order (needed for saving payment methods later)
- Returns `{ publicId }` (was `public_id`) to match what the frontend/widget expects
- Wrapped in try/catch with proper error logging

### 7. RevolutService.php — Customer ID on Orders

**Change:** `createOrder()` now passes `customer_id` in the order payload when the user has a Revolut customer ID. This associates the order with the customer, which is required for saving payment methods for recurring charges.

## Architecture Decision: Option A (Regular Orders)

Following Revolut's recommendation for inline embedded checkout, we use **Option A** (regular orders + save payment method) rather than Option B (Subscriptions API):

- **First payment:** Embedded widget creates a regular order via `createOrder` callback
- **Payment method saving:** Order includes `customer_id` for future payment method association
- **Recurring billing:** (future) Backend job creates orders and charges saved payment methods
- **Why not Subscriptions API:** Revolut's Subscriptions API onboarding is primarily designed for hosted/redirect flows, not inline embedded checkout

## Files Modified

| File | Change |
|------|--------|
| `app/Http/Controllers/Api/AuthController.php` | Existing email returns 422 with `email_exists: true`; always starts trial |
| `resources/js/views/Login.vue` | Added forgot password + register links in error area |
| `resources/js/views/Register.vue` | Added `emailExists` handling with sign-in link |
| `resources/js/views/Auth/CheckoutPage.vue` | Rewritten: CDN-loaded SDK, static `embeddedCheckout`, `createOrder` callback |
| `app/Http/Controllers/Api/PaymentController.php` | `createOrder` returns `publicId`, adds customer creation, error handling |
| `app/Services/Payment/RevolutService.php` | `createOrder` passes `customer_id` when available |
| `Feb25Updates/revolut-checkout-manual.md` | Revolut SDK documentation reference |

## Files to Upload (when deploying)

```
app/Http/Controllers/Api/AuthController.php
app/Http/Controllers/Api/PaymentController.php
app/Services/Payment/RevolutService.php
resources/js/views/Login.vue
resources/js/views/Register.vue
resources/js/views/Auth/CheckoutPage.vue
```

Plus rebuilt frontend assets (`public/build/`).

## Testing

1. **Login with wrong password** — Should show error with "Forgot your password?" and "Register here" links
2. **Register with existing email** — Should show "An account with this email address already exists" with "Sign in to your account" link
3. **Register with new email** — Should work as before (verification modal)
4. **Register without pricing page** — Direct `/register` should still create a trial (standard/yearly default)
5. **Checkout page** — Revolut payment widget should render inline showing available payment methods
6. **Checkout payment flow** — Click Pay, card form should appear, payment should process
7. **Browser console** — No CSP violations, no "Failed to load" errors

## Next Steps (not yet implemented)

1. **Save payment method for merchant** — Add `save_payment_method_for: "merchant"` to order creation
2. **Retrieve saved payment method** — After successful payment, fetch and store `payment_method.id`
3. **Recurring billing job** — Laravel scheduled command to create orders and charge saved payment methods
4. **Webhook signature verification** — Verify Revolut webhook signatures using HMAC SHA-256
5. **Production payment methods** — Enable Apple Pay / Google Pay in Revolut Business dashboard (not available in sandbox)
