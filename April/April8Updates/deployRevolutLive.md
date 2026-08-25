# Deploy Guide — revolutLive (8 April 2026)

**PR:** Stoff73/fynla#194
**Branch:** `revolutLive` merged to `main`

---

## Pre-deploy: Build locally

```bash
git checkout main && git pull
./deploy/fynla-org/build.sh
```

---

## Files to upload via SiteGround File Manager

### New PHP files (upload to `~/www/fynla.org/public_html/`)

```
app/Console/Commands/CheckOverdueSubscriptions.php
app/Console/Commands/SyncRevolutPlans.php
app/Console/Kernel.php
app/Http/Controllers/Api/AdminController.php
app/Http/Controllers/Api/PaymentController.php
app/Http/Controllers/Api/WebhookController.php
app/Mail/InvoiceEmail.php
app/Mail/PaymentConfirmation.php
app/Mail/PaymentFailedNotification.php
app/Models/DiscountCode.php
app/Models/DiscountCodeUsage.php
app/Models/Invoice.php
app/Models/Payment.php
app/Models/Subscription.php
app/Services/Payment/DiscountCodeService.php
app/Services/Payment/InvoiceService.php
app/Services/Payment/RevolutService.php
app/Services/Payment/RevolutSubscriptionService.php
app/Services/Payment/SubscriptionRenewalService.php
database/factories/DiscountCodeFactory.php
database/factories/InvoiceFactory.php
database/migrations/2026_04_08_100001_create_discount_codes_table.php
database/migrations/2026_04_08_100002_create_discount_code_usages_table.php
database/migrations/2026_04_08_100003_create_invoices_table.php
database/migrations/2026_04_08_100004_create_invoice_sequences_table.php
database/migrations/2026_04_08_100005_add_subscription_and_discount_fields.php
database/seeders/DatabaseSeeder.php
database/seeders/DiscountCodeSeeder.php
routes/api.php
composer.json
composer.lock
```

### New Blade templates

```
resources/views/emails/invoice.blade.php
resources/views/emails/payment-confirmation.blade.php
resources/views/emails/payment-failed.blade.php
resources/views/invoices/pdf.blade.php
```

### Frontend build (upload entire directory)

```
public/build/
```

---

## SSH commands (run in order)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Install new PHP dependency (dompdf for invoice PDFs)
composer install --no-dev --optimize-autoloader

# Run migrations (5 new tables + 2 altered tables)
php artisan migrate

# Seed discount codes (LAUNCH20, FYNLA10, TRYME)
php artisan db:seed --class=DiscountCodeSeeder --force

# Seed invoice sequence table
# (handled by migration, but verify)
php artisan tinker --execute="echo DB::table('invoice_sequences')->count();"
# Should return 1

# Clear all caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize

# Verify routes compiled
php artisan route:list --path=payment
php artisan route:list --path=admin/discount
```

---

## Environment variables

Add to `.env` on production if not already present:

```
# These should already exist from prior Revolut setup:
REVOLUT_API_KEY=sk_...
REVOLUT_PUBLIC_KEY=pk_...
REVOLUT_WEBHOOK_SECRET=...
REVOLUT_SANDBOX=false
PAYMENT_ENABLED=true
VITE_REVOLUT_PUBLIC_KEY=pk_...
VITE_REVOLUT_SANDBOX=false
```

No new env vars required for this deploy.

---

## Post-deploy verification

1. **Checkout page:** Go to checkout with a plan, click "Have a discount code?", enter LAUNCH20, click Apply — should show 20% off
2. **Admin panel:** Log in as admin, go to Admin > Discount Codes tab — should show LAUNCH20, FYNLA10, TRYME
3. **Route check:** `php artisan route:list --path=validate-discount` — should show POST route
4. **Invoice storage:** `mkdir -p storage/app/invoices && chmod 775 storage/app/invoices`

---

## New artisan commands

| Command | Purpose | Run |
|---------|---------|-----|
| `php artisan revolut:sync-plans` | Sync subscription plans to Revolut API | Once after deploy |
| `php artisan subscriptions:check-overdue` | Safety net for missed webhooks | Auto-scheduled daily at 01:00 |

Run `php artisan revolut:sync-plans` after deploy to create subscription plans in Revolut.

---

## Rollback

If issues: the 5 migrations can be rolled back with `php artisan migrate:rollback --step=5`. Frontend can be reverted by rebuilding from the previous commit. No destructive changes to existing tables — all new columns have safe defaults.
