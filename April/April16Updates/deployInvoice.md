# Deploy Guide — Invoice Fix

**Branch:** `invoiceFix` (off `main`)
**Date:** 16 April 2026
**Commit:** `359bb31`

---

## What Changed

1. **Invoice view page** — new `/invoice/:id` route showing full invoice details
2. **Billing history fix** — was never rendering due to response parsing bug (`response.data.payments` → `response.data.data?.payments`)
3. **Email link fix** — all 3 payment emails linked to `/profile#subscription` which didn't activate the subscription tab. Now uses `?section=subscription` + hash fallback
4. **Email CTA copy** — payment confirmation says "View subscription" (not "View invoice") since the PDF is attached
5. **Billing history table** — shows invoice number as clickable link to `/invoice/:id` instead of download icon
6. **New API endpoint** — `GET /api/payment/invoices/{invoice}` returns invoice JSON

## Files to Upload

### PHP (2 files) → `~/www/fynla.org/public_html/`

```
app/Http/Controllers/Api/PaymentController.php
routes/api.php
```

### Blade templates (3 files) → `~/www/fynla.org/public_html/`

```
resources/views/emails/invoice.blade.php
resources/views/emails/payment-confirmation.blade.php
resources/views/emails/payment-failed.blade.php
```

### Frontend (built assets)

Build locally first, then upload `public/build/`:

```
resources/js/views/InvoiceView.vue              (NEW)
resources/js/views/UserProfile.vue
resources/js/router/index.js
resources/js/components/UserProfile/SubscriptionManagement.vue
```

## Deploy Steps

### 1. Build locally

```bash
git checkout invoiceFix
./deploy/fynla-org/build.sh
```

### 2. Upload files

Upload to `~/www/fynla.org/public_html/` via SiteGround File Manager:

- `public/build/` (entire directory — replaces existing)
- `app/Http/Controllers/Api/PaymentController.php`
- `routes/api.php`
- `resources/views/emails/invoice.blade.php`
- `resources/views/emails/payment-confirmation.blade.php`
- `resources/views/emails/payment-failed.blade.php`

### 3. SSH and clear caches

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

### 4. Verify

No migrations needed. No database changes.

- [ ] Go to `https://fynla.org/profile?section=subscription` — subscription tab should be active
- [ ] Billing history table should show with date, invoice number, description, amount
- [ ] Click invoice number — should navigate to `/invoice/{id}` with full invoice details
- [ ] Click "Back to subscription" — should return to profile subscription tab
- [ ] Test `https://fynla.org/profile#subscription` — hash fallback should also activate subscription tab

## No Migrations

This deploy has zero database changes. All invoice data already exists in production.

## Rollback

If something breaks, re-upload the previous versions of `PaymentController.php`, `api.php`, and the 3 blade templates from the `main` branch. Rebuild frontend from `main` and re-upload `public/build/`.
