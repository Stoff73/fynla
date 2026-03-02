# Revolut Embedded Checkout — Implementation & Deployment

## Summary

Full Revolut payment integration using the Embedded Checkout widget with the pseudo-subscription model (one-off orders, no auto-recurring billing).

---

## What Was Built

### New files (4)

| File | Purpose |
|------|---------|
| `app/Services/Payment/RevolutService.php` | Revolut Merchant API wrapper: createOrder(), getOrder(), verifyWebhookSignature() |
| `app/Http/Controllers/Api/WebhookController.php` | Revolut webhook handler for ORDER_COMPLETED / ORDER_AUTHORISED |
| `resources/js/components/Payment/PlanSelectionModal.vue` | Plan selection modal with monthly/yearly toggle, 3 plan cards |
| `database/migrations/2026_02_25_100001_add_columns_to_payments_table.php` | Add description, plan_slug, billing_cycle to payments table |

### Modified files (9)

| File | Changes |
|------|---------|
| `.env.example` | Added REVOLUT_PUBLIC_KEY, VITE_REVOLUT_* vars |
| `config/services.php` | Added revolut config block |
| `app/Models/Payment.php` | Added revolut_order_id, revolut_payment_data, plan_slug, billing_cycle to $fillable/$casts |
| `app/Models/Subscription.php` | Added revolut_order_id, revolut_subscription_id to $fillable |
| `app/Http/Controllers/Api/PaymentController.php` | Added plans(), createOrder(), confirmPayment(), billingHistory(), cancelSubscription(), deleteAllData() |
| `app/Http/Middleware/SecurityHeaders.php` | Added Revolut domains to CSP (script-src, connect-src, frame-src, img-src) + Permissions-Policy payment |
| `routes/api.php` | Added 6 authenticated payment routes + 1 unauthenticated webhook route |
| `resources/js/views/Auth/CheckoutPage.vue` | Full rebuild with Revolut embeddedCheckout() widget via CDN |
| `resources/js/components/Trial/TrialCountdownBanner.vue` | Opens PlanSelectionModal instead of linking to /checkout |
| `resources/js/components/UserProfile/SubscriptionManagement.vue` | Opens PlanSelectionModal, "Renew" instead of "Resubscribe", 2dp amounts, removed renewal countdown |

### Key decisions

- Preview users blocked at controller level (is_preview_user check)
- plan_slug and billing_cycle persisted on Payment record as source of truth
- Webhook + confirm endpoint both idempotent with lockForUpdate() transactions
- Order ID validated as UUID before any Revolut API calls
- SDK loaded via CDN script tag (not npm package — see bug fixes below)
- confirmPayment accepts "processing" state (Revolut fires onSuccess before order completes)

---

## Bug Fixes (25 Feb 2026)

### 1. Revolut Widget Loading (3 issues)

See `revolut/bugFix.md` for full details.

**Issue A: SDK API Mismatch** — npm package `@revolut/checkout` doesn't expose static `embeddedCheckout()`. Switched to CDN script loading.

**Issue B: CSP Blocking CDN** — SecurityHeaders middleware blocked external Revolut scripts. Added domains to all CSP directives.

**Issue C: Localhost Redirect URL** — Revolut API rejects `localhost` in redirect_url. Fixed to use `https://fynla.org` in sandbox mode.

| File | Change | Deploy Action |
|------|--------|---------------|
| `resources/js/views/Auth/CheckoutPage.vue` | CDN script loading for Revolut SDK | **Rebuild frontend** |
| `app/Http/Middleware/SecurityHeaders.php` | Revolut domains in CSP + Permissions-Policy | **Upload PHP file** |
| `app/Http/Controllers/Api/PaymentController.php` | Production URL for redirect in sandbox | **Upload PHP file** |
| `package.json` | Removed @revolut/checkout dependency | Build-time only |

### 2. Subscription Not Activating After Payment

Revolut fires `onSuccess` while order is in `"processing"` state. The `confirmPayment` endpoint only accepted `"completed"`, so the subscription was never activated.

| File | Change | Deploy Action |
|------|--------|---------------|
| `app/Http/Controllers/Api/PaymentController.php` | Accept `"processing"` as valid order state | **Upload PHP file** |

### 3. UI Polish

- Checkout: amounts show 2 decimal places, removed period suffix from total, card-level "Payment Method" heading, Revolut duplicate heading hidden via CSS
- Subscription tab: amounts show 2 decimal places, removed "monthly" suffix, removed renewal countdown timer

| File | Change | Deploy Action |
|------|--------|---------------|
| `resources/js/views/Auth/CheckoutPage.vue` | Price formatting, heading cleanup | **Rebuild frontend** |
| `resources/js/components/UserProfile/SubscriptionManagement.vue` | Amount formatting, removed countdown | **Rebuild frontend** |

---

## Deployment Steps

### 1. Build frontend locally

```bash
./deploy/fynla-org/build.sh
```

### 2. Upload files to SiteGround

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
```

**Vue files (included in frontend build, but list for reference):**
```
resources/js/components/Payment/PlanSelectionModal.vue
resources/js/components/Trial/TrialCountdownBanner.vue
resources/js/components/UserProfile/SubscriptionManagement.vue
resources/js/views/Auth/CheckoutPage.vue
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

### 3. SSH and run commands

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

### 4. Verify

- Navigate to checkout page with a plan selected
- Revolut widget should load showing Revolut Pay, Card, and Google Pay
- No console errors
- Complete test payment — subscription activates immediately
- Subscription tab shows correct amounts with 2 decimal places
- Cancel subscription flow works with reason selection
