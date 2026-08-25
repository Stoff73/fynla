# Discount Code Trace Report — Actual Values

## Database Values

| Table | Column | Value |
|-------|--------|-------|
| subscription_plans | slug | standard |
| subscription_plans | launch_yearly_price | 10000 |
| subscription_plans | yearly_price | 13500 |
| discount_codes | code | LAUNCH20 |
| discount_codes | type | percentage |
| discount_codes | value | 20 |
| discount_codes | id | 16 |

## Step-by-step trace

| Step | Variable | Value | Where |
|------|----------|-------|-------|
| 1. Page loads | _validatedDiscountCode | "" | CheckoutPage.vue line 238 |
| 2. User types code | this.discountCodeInput | "LAUNCH20" | CheckoutPage.vue line 83 (v-model) |
| 3. User clicks Apply | code | "LAUNCH20" | CheckoutPage.vue line 482 |
| 4. POST validate-discount sends | { code, plan, billing_cycle } | { "LAUNCH20", "standard", "yearly" } | CheckoutPage.vue line 490 |
| 5. Backend validates | $amount | 10000 | PaymentController.php line 92 |
| 6. Backend calculates | calculateDiscount(10000) | 2000 | DiscountCodeService.php |
| 7. Backend returns | data.discount_amount | 2000 | PaymentController.php validateDiscountCode |
| 8. Backend returns | data.final_amount | 8000 | PaymentController.php validateDiscountCode |
| 9. Backend returns | data.original_amount | 10000 | PaymentController.php validateDiscountCode |
| 10. Frontend stores | _validatedDiscountCode | "LAUNCH20" | CheckoutPage.vue line 505 |
| 11. Frontend stores | this.discountAmountPence | 2000 | CheckoutPage.vue line 499 |
| 12. Frontend stores | this.finalAmountPence | 8000 | CheckoutPage.vue line 500 |
| 13. UI shows | Subtotal (strikethrough) | £100.00 | CheckoutPage.vue line 46 |
| 14. UI shows | 20% off | -£20.00 | CheckoutPage.vue line 50 |
| 15. UI shows | Total | £80.00 | CheckoutPage.vue line 302-304 |
| 16. User clicks Pay | discountCode | "LAUNCH20" | CheckoutPage.vue line 387 (reads _validatedDiscountCode) |
| 17. POST create-order sends | payload | { plan: "standard", billing_cycle: "yearly", discount_code: "LAUNCH20" } | CheckoutPage.vue line 395 |
| 18. Backend receives | $request->input('discount_code') | "LAUNCH20" | PaymentController.php line 101 |
| 19. Backend sets | $amount | 10000 | PaymentController.php line 92 |
| 20. Backend sets | $finalAmount (initial) | 10000 | PaymentController.php line 99 |
| 21. Backend validates | discountCodeService->validate() | valid=true | PaymentController.php line 102 |
| 22. Backend sets | $discountAmount | 2000 | PaymentController.php line 139 |
| 23. Backend sets | $finalAmount (after discount) | 8000 | PaymentController.php line 140 |
| 24. Revolut receives | POST /api/orders { amount } | 8000 | RevolutService.php line 148 |
| 25. Revolut charges | card charge | £80.00 | Revolut API |
| 26. Payment record | amount | 8000 | PaymentController.php line 191 |
| 27. Payment record | discount_code_id | 16 | PaymentController.php line 197 |
| 28. Payment record | discount_amount | 2000 | PaymentController.php line 198 |
| 29. Subscription record | amount (renewal price) | 10000 | PaymentController.php line 330 |
| 30. Invoice | subtotal_amount | 10000 | InvoiceService.php line 27 |
| 31. Invoice | discount_amount | 2000 | InvoiceService.php line 28 |
| 32. Invoice | total_amount | 8000 | InvoiceService.php line 29 |
| 33. Invoice | discount_code | "LAUNCH20" | InvoiceService.php line 44 |
| 34. Invoice | discount_description | "20% off" | InvoiceService.php line 45 |
| 35. Invoice PDF line item | Standard Plan, Yearly | £100.00 | invoices/pdf.blade.php line 86-88 |
| 36. Invoice PDF discount | 20% off — Code: LAUNCH20 | -£20.00 | invoices/pdf.blade.php line 99-103 |
| 37. Invoice PDF total | Total Paid | £80.00 | invoices/pdf.blade.php line 111-113 |
| 38. Invoice PDF renewal | Auto-renewal | £100.00/year | invoices/pdf.blade.php line 117-123 |
