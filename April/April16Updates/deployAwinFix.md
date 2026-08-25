# Deploy Guide — Awin Tracking Fix + Email Consolidation

*Date: 16 April 2026*
*Branch: `awinPlusDev`*
*Commits: `81d0b74`, `fde4724`*
*PR: #216 (awinPlusDev → dev)*
*Status: **DEPLOYED TO PRODUCTION** 16 April 2026*

---

## What Changed

### Awin Tracking (fixes the voucher code mismatch warning)

1. **`resources/js/utils/awinTracking.js`** — 3 fixes:
   - `voucherCode` renamed to `voucher` (MasterTag reads `AWIN.Tracking.Sale.voucher`)
   - Removed `async` attribute from MasterTag script (Awin guide says `defer` only)
   - MasterTag appended to `<body>` instead of `<head>` (Awin guide says before `</body>`)

### Payment Email Consolidation (one email instead of two)

2. **`app/Mail/PaymentConfirmation.php`** — Added `attachments()` method to attach invoice PDF. Added `invoiceNumber` and `awinOrderRef` template variables. CRITICAL log warnings when invoice or PDF is missing.

3. **`app/Http/Controllers/Api/PaymentController.php`** — Removed `emailInvoice()` call (no more separate InvoiceEmail). Invoice is still generated. Email always sent from `confirmPayment()` with `$payment->refresh()` so invoice relationship is loaded.

4. **`app/Http/Controllers/Api/WebhookController.php`** — Removed `PaymentConfirmation` email send (too early, invoice doesn't exist yet at webhook time). Cleaned up unused `Mail` and `PaymentConfirmation` imports.

5. **`resources/views/emails/payment-confirmation.blade.php`** — Reference row replaced with "Invoice Reference" (always shown) and "Affiliate Reference" (shown only for Awin-attributed payments).

---

## Files to Upload

### Frontend (requires production build)

```bash
./deploy/fynla-org/build.sh
```

Upload `public/build/` to `~/www/fynla.org/public_html/public/build/`

### Backend (PHP files)

Upload these 4 files to `~/www/fynla.org/public_html/`:

| Local Path | Server Path |
|---|---|
| `app/Mail/PaymentConfirmation.php` | `app/Mail/PaymentConfirmation.php` |
| `app/Http/Controllers/Api/PaymentController.php` | `app/Http/Controllers/Api/PaymentController.php` |
| `app/Http/Controllers/Api/WebhookController.php` | `app/Http/Controllers/Api/WebhookController.php` |
| `resources/views/emails/payment-confirmation.blade.php` | `resources/views/emails/payment-confirmation.blade.php` |

### No new migrations, no new config files, no .env changes.

---

## Post-Upload SSH Commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Verification

### 1. Awin Tracking Fix

The voucher code fix only surfaces when a discount code is used. To verify:

- Go to the Awin merchant dashboard > Tracking Diagnosis
- Create a test transaction or wait for the next real purchase with a discount code
- All three tracking methods (Conversion Tag, Server-to-Server, Fall-back Pixel) should show matching `vc=` values
- No more "Parameter Values not matching across Tracking Tags" warning

### 2. Email Consolidation

After a test payment:
- User should receive **one** email ("Payment confirmation - Fynla"), not two
- The email should have the invoice PDF attached
- "Invoice Reference" row should show the invoice number (e.g. FYN-08)
- "Affiliate Reference" row should only appear for Awin-attributed payments

### 3. Monitor Logs

For the first 24 hours, check for CRITICAL log entries:

```bash
grep -i "CRITICAL.*invoice" storage/logs/laravel.log | tail -20
```

If any appear, an invoice failed to generate — investigate immediately (legal requirement).

---

## Rollback

If issues arise, the previous versions of these 4 PHP files + 1 Blade template can be restored from the pre-deploy backup. The frontend build can be rolled back by re-running `./deploy/fynla-org/build.sh` on the previous commit and re-uploading `public/build/`.

The `InvoiceEmail` Mailable class and its template still exist in the codebase (used by `SubscriptionRenewalService` for renewal payments) — they were not deleted, just no longer called from the initial payment flow.
