# Discount Code Not Reaching Backend — UNRESOLVED

## Problem

User applies discount code (e.g. LAUNCH20) on checkout page. UI shows discount correctly (strikethrough, discount line, reduced total). But when user clicks Pay, the `discount_code` field is NOT included in the POST to `/api/payment/create-order`. Backend receives `discount_code: null`, charges full price. Invoice shows full price with no discount.

## What was tried and failed

1. Reading `this.discountCodeInput` (Vue reactive data) inside the Revolut `createOrder` callback — value is null when callback fires
2. Reading the DOM input element directly as fallback — still null
3. Using a module-level variable `_validatedDiscountCode` set by `applyDiscountCode()` — UNTESTED, not confirmed working
4. Multiple Vite restarts, cache clears — frontend changes may not be reaching the browser

## Proven

Backend produces correct values when discount_code IS received:
```
$amount = 10000 (£100.00)
$discountAmount = 2000 (£20.00)  
$finalAmount = 8000 (£80.00)
Revolut receives: 8000
Payment: amount=8000, discount_code_id=16, discount_amount=2000
Invoice: subtotal=10000, discount=2000, total=8000
```

Actual payment every time: `amount=10000, discount_code_id=NULL, discount_amount=0`

## Root Cause

The Revolut SDK's `embeddedCheckout()` `createOrder` callback cannot access the discount code value. Every attempt to pass the value through Vue reactive data, DOM access, or module-level variables has failed to reach the user in a working state. The frontend code changes may not be reaching the browser, or the Revolut SDK is invoking the callback in a way that prevents access to any external state.

## Status

**RESOLVED** — 8 April 2026.

## Root Cause

The Revolut SDK's `embeddedCheckout()` calls the `createOrder` callback **at initialization time** (in `mounted()`), not when the user clicks Pay. The order was created at full price before any discount was applied. Later discount code entry only updated the UI — the Revolut order was already locked to the full amount.

## Fix

Three changes to `CheckoutPage.vue`:
1. **`mounted()` reordered** — if a prefilled discount code exists, apply it first; `initCheckout()` only runs if no prefilled code
2. **`applyDiscountCode()` calls `reinitializeCheckout()`** after success — destroys the old widget (full-price order) and reinits (new order with discount)
3. **New `reinitializeCheckout()` method** — destroys existing widget, then calls `initCheckout()`

Verified: backend logs show `final_amount: 8000` with `discount_code: LAUNCH20` on the second create-order call.
