Implementation is complete. Here's a summary of everything that was built:

---
Summary

What was built

Full Revolut payment integration using the Embedded Checkout widget with the pseudo-subscription model (one-off orders, no auto-recurring billing).

New files (4)

| File | Purpose |
|------|---------|
| app/Services/Payment/RevolutService.php | Revolut Merchant API wrapper: createOrder(), getOrder(), verifyWebhookSignature() |
| app/Http/Controllers/Api/WebhookController.php | Revolut webhook handler for ORDER_COMPLETED / ORDER_AUTHORISED |
| resources/js/components/Payment/PlanSelectionModal.vue | Plan selection modal with monthly/yearly toggle, 3 plan cards |
| database/migrations/2026_02_25_100001_add_columns_to_payments_table.php | Add description, plan_slug, billing_cycle to payments table |

Modified files (8)

| File | Changes |
|------|---------|
| .env.example | Added REVOLUT_PUBLIC_KEY, VITE_REVOLUT_* vars |
| config/services.php | Added revolut config block |
| app/Models/Payment.php | Added revolut_order_id, revolut_payment_data, plan_slug, billing_cycle to $fillable/$casts |
| app/Models/Subscription.php | Added revolut_order_id, revolut_subscription_id to $fillable |
| app/Http/Controllers/Api/PaymentController.php | Added constructor with RevolutService, plans(), createOrder(), confirmPayment() |
| routes/api.php | Added 3 authenticated payment routes + 1 unauthenticated webhook route |
| resources/js/views/Auth/CheckoutPage.vue | Full rebuild with Revolut embeddedCheckout() widget |
| resources/js/components/Trial/TrialCountdownBanner.vue | Opens PlanSelectionModal instead of linking to /checkout |
| resources/js/components/UserProfile/SubscriptionManagement.vue | Opens PlanSelectionModal, "Renew" instead of "Resubscribe" |

Key decisions

- Preview users blocked at controller level (is_preview_user check)
- plan_slug and billing_cycle persisted on Payment record as source of truth
- Webhook + confirm endpoint both idempotent with lockForUpdate() transactions
- Order ID validated as UUID before any Revolut API calls
- SDK loaded via CDN script tag (not npm package — see bug fix below)

---

## Bug Fix: Revolut Widget Loading (25 Feb 2026)

The Revolut embedded checkout widget failed to load due to 3 issues. See `revolut/bugFix.md` for full details.

### Additional files changed by bug fix

| File | Change | Deploy Action |
|------|--------|---------------|
| resources/js/views/Auth/CheckoutPage.vue | Replaced npm import with CDN script loading for Revolut SDK | **Rebuild frontend** |
| app/Http/Middleware/SecurityHeaders.php | Added Revolut domains to CSP (script-src, connect-src, frame-src, img-src) + Permissions-Policy payment | **Upload PHP file** |
| app/Http/Controllers/Api/PaymentController.php | Fixed redirect_url to use production URL in sandbox mode | **Upload PHP file** |
| package.json | Removed @revolut/checkout npm dependency | Build-time only |
| package-lock.json | Updated lockfile | Build-time only |

### Deployment Steps

#### 1. Build frontend locally

```bash
./deploy/fynla-org/build.sh
```

#### 2. Upload files to SiteGround

**Frontend build (entire directory):**
```
public/build/ → ~/www/fynla.org/public_html/public/build/
```

**PHP files (upload individually):**
```
app/Http/Middleware/SecurityHeaders.php
app/Http/Controllers/Api/PaymentController.php
app/Services/Payment/RevolutService.php
app/Http/Controllers/Api/WebhookController.php
app/Models/Payment.php
app/Models/Subscription.php
config/services.php
routes/api.php
resources/js/components/Payment/PlanSelectionModal.vue
resources/js/components/Trial/TrialCountdownBanner.vue
resources/js/components/UserProfile/SubscriptionManagement.vue
```

**Migration (if not already run):**
```
database/migrations/2026_02_25_100001_add_columns_to_payments_table.php
```

**Environment variables (add to .env on server):**
```
REVOLUT_API_KEY=sk_...
REVOLUT_PUBLIC_KEY=pk_...
REVOLUT_WEBHOOK_SECRET=...
REVOLUT_SANDBOX=false
PAYMENT_ENABLED=true
VITE_REVOLUT_PUBLIC_KEY=${REVOLUT_PUBLIC_KEY}
VITE_REVOLUT_SANDBOX=${REVOLUT_SANDBOX}
```

#### 3. SSH and run commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

#### 4. Verify

- Navigate to checkout page with a plan selected
- Revolut widget should load showing Revolut Pay, Card, and Google Pay
- No console errors
