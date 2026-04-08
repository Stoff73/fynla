# Discount Code Not Reaching Backend

## Problem

User applies discount code (e.g. LAUNCH20) on checkout page. UI shows discount correctly (strikethrough, discount line, reduced total). But when user clicks Pay, the `discount_code` field is NOT included in the POST to `/api/payment/create-order`. Backend receives `discount_code: null`, charges full price.

## Proven

Backend produces correct values when discount_code IS received:
```
$amount = 10000
$discountAmount = 2000
$finalAmount = 8000
Revolut receives: 8000
Payment: amount=8000, discount_code_id=16, discount_amount=2000
Invoice: subtotal=10000, discount=2000, total=8000
```

Actual payment had: `amount=10000, discount_code_id=NULL, discount_amount=0`

## Root Cause

The Revolut `embeddedCheckout()` widget's `createOrder` callback is an async function passed to the Revolut SDK at init time. The SDK may be invoking this callback in a different execution context where Vue reactive `this` properties are not accessible, or the SDK serialises/clones the callback losing the closure scope.

The `createOrder` callback reads `this.discountCodeInput` — but when Revolut calls it, `this` may not be the Vue instance.

## Fix

Stop relying on Vue reactive data inside the Revolut callback entirely. Instead, store the validated discount code in a plain module-level variable that the callback can always read regardless of execution context.
