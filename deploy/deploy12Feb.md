# Deployment Notes - 12 February 2026

## Branches: `gif`, `about`, `pricing`

## Changes

| File | Type | Description |
|------|------|-------------|
| `resources/js/views/Public/LandingPage.vue` | Frontend (build) | Added animated dashboard walkthrough GIF section below hero; changed hero stats cards to 2x2 grid layout |
| `resources/js/assets/logoTransparent.png` | Frontend (build) | Updated to new 3D Fynla logo (used in footer and across app) |
| `resources/js/views/Public/AboutPage.vue` | Frontend (build) | New About page with mission statement and founder profiles |
| `resources/js/views/Public/PricingPage.vue` | Frontend (build) | New Pricing page with Student/Standard/Pro tiers and monthly/yearly toggle; updated startTrial() to include billing cycle |
| `resources/js/layouts/PublicLayout.vue` | Frontend (build) | Added About and Pricing links to desktop nav, mobile nav, and footer |
| `resources/js/router/index.js` | Frontend (build) | Added /about, /pricing, and /checkout routes and public route guard entries |
| `public/images/fynla-dashboard-walkthrough.gif` | Static asset | 15-second animated GIF showing Mitchell persona dashboard walkthrough |
| `public/images/portraits/csj.png` | Static asset | Chris Slater-Jones portrait |
| `public/images/portraits/brett.png` | Static asset | Brett Isenberg portrait |
| `resources/js/views/Dashboard.vue` | Frontend (build) | Removed UK Taxes card from user dashboard |

---

## Revolut Payment Integration (Branch: `pricing`)

### Database Migrations (run on server)

| Migration | Description |
|-----------|-------------|
| `2026_02_12_100001_create_subscriptions_table` | Subscriptions with plan, billing_cycle, status, trial dates, revolut_order_id |
| `2026_02_12_100002_create_payments_table` | Payment records linked to subscriptions |
| `2026_02_12_100003_add_plan_fields_to_users_table` | Adds `plan` (enum) and `trial_ends_at` to users |
| `2026_02_12_100004_create_trial_reminder_log_table` | Prevents duplicate trial reminder emails |
| `2026_02_12_100005_add_plan_fields_to_pending_registrations_table` | Adds `plan` and `billing_cycle` to pending_registrations |

### New PHP Files (upload to server)

| File | Description |
|------|-------------|
| `app/Models/Subscription.php` | Subscription model with scopes, trial helpers |
| `app/Models/Payment.php` | Payment model |
| `app/Services/Payment/TrialService.php` | Start trial, expire trials |
| `app/Services/Payment/RevolutService.php` | Revolut Merchant API integration |
| `app/Http/Controllers/Api/PaymentController.php` | Create order, order status, trial status endpoints |
| `app/Http/Controllers/Api/PaymentWebhookController.php` | Revolut webhook handler with HMAC verification |
| `app/Http/Middleware/CheckSubscription.php` | Subscription guard middleware (feature-flagged) |
| `app/Mail/TrialExpirationReminder.php` | Trial expiry reminder mailable |
| `app/Console/Commands/SendTrialReminderEmails.php` | `trials:send-reminders` artisan command |
| `app/Console/Commands/ExpireTrials.php` | `trials:expire` artisan command |
| `resources/views/emails/trial-expiration-reminder.blade.php` | Branded trial reminder email template |

### Modified PHP Files (upload to server)

| File | Change |
|------|--------|
| `app/Models/User.php` | Added subscription(), payments() relationships, onTrial(), hasActivePlan(), trialDaysRemaining(), planIs() helpers, trial_ends_at cast |
| `app/Models/PendingRegistration.php` | Added plan, billing_cycle to $fillable and createOrUpdate() |
| `app/Http/Controllers/Api/AuthController.php` | Injects TrialService, passes plan/billing_cycle to PendingRegistration, starts trial on verifyCode(), auto-admin for chris@fynla.org and brett@fynla.org |
| `app/Http/Controllers/Api/AdminController.php` | Eager-loads subscription + payments in getUsers(), new getSubscriptionStats() endpoint, excludes preview users from user list and dashboard stats, fixed `name` → `first_name`/`surname` column references, added `nullable` to search validation |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | Added `api/webhooks/revolut` to EXCLUDED_ROUTES |
| `app/Console/Kernel.php` | Added scheduled commands: trials:send-reminders (09:00), trials:expire (00:05) |
| `config/services.php` | Added `revolut` config key (api_key, webhook_secret, sandbox) |
| `config/app.php` | Added `payment_enabled` config key |
| `routes/api.php` | Added payment routes, webhook route, admin subscription stats route |
| `.env.example` | Added REVOLUT_API_KEY, REVOLUT_WEBHOOK_SECRET, REVOLUT_SANDBOX, PAYMENT_ENABLED |

### New Frontend Files (included in build)

| File | Description |
|------|-------------|
| `resources/js/components/Trial/TrialCountdownBanner.vue` | Trial countdown banner with progress bar and upgrade button |
| `resources/js/views/Auth/CheckoutPage.vue` | Checkout page with Revolut widget / "Coming Soon" state |

### Modified Frontend Files (included in build)

| File | Change |
|------|--------|
| `resources/js/layouts/AppLayout.vue` | Added TrialCountdownBanner above PreviewBanner |
| `resources/js/views/Public/PricingPage.vue` | startTrial() now includes billing cycle query param |
| `resources/js/views/Register.vue` | Captures plan/billing from query params, sends to register API |
| `resources/js/router/index.js` | Added /checkout route (lazy loaded, requiresAuth) |
| `resources/js/services/adminService.js` | Added getSubscriptionStats() method |
| `resources/js/components/Admin/UserManagement.vue` | Added Plan, Status, Trial, Payment columns + status filter |
| `resources/js/components/Admin/AdminDashboard.vue` | Added subscription stats cards (trialing, active, expired, revenue) |
| `resources/js/views/Dashboard.vue` | Removed UK Taxes card from user dashboard (accessible via /uk-taxes route) |

### Environment Variables (add to server .env)

```
REVOLUT_API_KEY=sandbox_key_here
REVOLUT_WEBHOOK_SECRET=webhook_secret_here
REVOLUT_SANDBOX=true
PAYMENT_ENABLED=false
```

---

## Build Required: Yes

The Vue component, layout, router, and logo asset changes require a frontend build.

```bash
./deploy/fynla-org/build.sh
```

## Files to Upload

### Frontend Build (replace entire directory)

| File | Destination |
|------|-------------|
| `public/build/` | `~/www/fynla.org/public_html/public/build/` |

### Static Assets

| File | Destination |
|------|-------------|
| `public/images/fynla-dashboard-walkthrough.gif` | `~/www/fynla.org/public_html/public/images/fynla-dashboard-walkthrough.gif` |
| `public/images/portraits/csj.png` | `~/www/fynla.org/public_html/public/images/portraits/csj.png` |
| `public/images/portraits/brett.png` | `~/www/fynla.org/public_html/public/images/portraits/brett.png` |

### Database Migrations

| File | Destination |
|------|-------------|
| `database/migrations/2026_02_12_100001_create_subscriptions_table.php` | `~/www/fynla.org/public_html/database/migrations/` |
| `database/migrations/2026_02_12_100002_create_payments_table.php` | `~/www/fynla.org/public_html/database/migrations/` |
| `database/migrations/2026_02_12_100003_add_plan_fields_to_users_table.php` | `~/www/fynla.org/public_html/database/migrations/` |
| `database/migrations/2026_02_12_100004_create_trial_reminder_log_table.php` | `~/www/fynla.org/public_html/database/migrations/` |
| `database/migrations/2026_02_12_100005_add_plan_fields_to_pending_registrations_table.php` | `~/www/fynla.org/public_html/database/migrations/` |

### New PHP Files

| File | Destination |
|------|-------------|
| `app/Models/Subscription.php` | `~/www/fynla.org/public_html/app/Models/` |
| `app/Models/Payment.php` | `~/www/fynla.org/public_html/app/Models/` |
| `app/Services/Payment/TrialService.php` | `~/www/fynla.org/public_html/app/Services/Payment/` |
| `app/Services/Payment/RevolutService.php` | `~/www/fynla.org/public_html/app/Services/Payment/` |
| `app/Http/Controllers/Api/PaymentController.php` | `~/www/fynla.org/public_html/app/Http/Controllers/Api/` |
| `app/Http/Controllers/Api/PaymentWebhookController.php` | `~/www/fynla.org/public_html/app/Http/Controllers/Api/` |
| `app/Http/Middleware/CheckSubscription.php` | `~/www/fynla.org/public_html/app/Http/Middleware/` |
| `app/Mail/TrialExpirationReminder.php` | `~/www/fynla.org/public_html/app/Mail/` |
| `app/Console/Commands/SendTrialReminderEmails.php` | `~/www/fynla.org/public_html/app/Console/Commands/` |
| `app/Console/Commands/ExpireTrials.php` | `~/www/fynla.org/public_html/app/Console/Commands/` |
| `resources/views/emails/trial-expiration-reminder.blade.php` | `~/www/fynla.org/public_html/resources/views/emails/` |

### Modified PHP Files

| File | Destination |
|------|-------------|
| `app/Models/User.php` | `~/www/fynla.org/public_html/app/Models/` |
| `app/Models/PendingRegistration.php` | `~/www/fynla.org/public_html/app/Models/` |
| `app/Http/Controllers/Api/AuthController.php` | `~/www/fynla.org/public_html/app/Http/Controllers/Api/` |
| `app/Http/Controllers/Api/AdminController.php` | `~/www/fynla.org/public_html/app/Http/Controllers/Api/` |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | `~/www/fynla.org/public_html/app/Http/Middleware/` |
| `app/Console/Kernel.php` | `~/www/fynla.org/public_html/app/Console/` |
| `config/services.php` | `~/www/fynla.org/public_html/config/` |
| `config/app.php` | `~/www/fynla.org/public_html/config/` |
| `routes/api.php` | `~/www/fynla.org/public_html/routes/` |

### Config

| File | Destination |
|------|-------------|
| `.env.example` | `~/www/fynla.org/public_html/.env.example` |

## Post-Upload

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Add env vars to .env
# REVOLUT_API_KEY=sandbox_key_here
# REVOLUT_WEBHOOK_SECRET=webhook_secret_here
# REVOLUT_SANDBOX=true
# PAYMENT_ENABLED=false

# Run migrations
php artisan migrate

# Clear caches
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```
