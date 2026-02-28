Revolut Payment Integration Plan

 Context

 Users currently click "Start Free Trial" on the Pricing page and go to /register. There is no trial tracking, payment collection, or
 subscription management. This plan adds a trial-first flow where users register and immediately start a 7-day free trial with no card
  details required. Payment is collected later via a "Purchase" button on their dashboard. Trial countdown emails warn users before
 expiry.

 Architecture

 PricingPage "Start Free Trial" → /register?plan=student&billing=yearly
                                       ↓
                               Registration creates user + subscription (status=trialing)
                                       ↓
                               User Dashboard (trial countdown banner + "Upgrade" button)
                                       ↓
                               /checkout → Revolut Embedded Widget (card, Apple Pay, Google Pay)
                                       ↓
                               Revolut Webhook → activates subscription

 Scheduled: SendTrialReminderEmails command runs daily → emails at 3, 2, 1 days before expiry

 ---
 Step 1: Database Migrations

 Migration 1: create_subscriptions_table
 ┌───────────────────────────┬────────────────────┬────────────────────────────────────────────────┐
 │          Column           │        Type        │                  Description                   │
 ├───────────────────────────┼────────────────────┼────────────────────────────────────────────────┤
 │ id                        │ bigint             │ Primary key                                    │
 ├───────────────────────────┼────────────────────┼────────────────────────────────────────────────┤
 │ user_id                   │ foreignId          │ References users                               │
 ├───────────────────────────┼────────────────────┼────────────────────────────────────────────────┤
 │ plan                      │ enum               │ student, standard, pro                         │
 ├───────────────────────────┼────────────────────┼────────────────────────────────────────────────┤
 │ billing_cycle             │ enum               │ monthly, yearly                                │
 ├───────────────────────────┼────────────────────┼────────────────────────────────────────────────┤
 │ status                    │ enum               │ trialing, active, cancelled, expired, past_due │
 ├───────────────────────────┼────────────────────┼────────────────────────────────────────────────┤
 │ trial_started_at          │ timestamp          │ When trial began                               │
 ├───────────────────────────┼────────────────────┼────────────────────────────────────────────────┤
 │ trial_ends_at             │ timestamp          │ 7 days after trial_started_at                  │
 ├───────────────────────────┼────────────────────┼────────────────────────────────────────────────┤
 │ current_period_start      │ timestamp nullable │ Start of paid billing period                   │
 ├───────────────────────────┼────────────────────┼────────────────────────────────────────────────┤
 │ current_period_end        │ timestamp nullable │ End of paid billing period                     │
 ├───────────────────────────┼────────────────────┼────────────────────────────────────────────────┤
 │ revolut_order_id          │ string nullable    │ Revolut order reference                        │
 ├───────────────────────────┼────────────────────┼────────────────────────────────────────────────┤
 │ amount                    │ integer            │ Amount in pence for selected plan/cycle        │
 ├───────────────────────────┼────────────────────┼────────────────────────────────────────────────┤
 │ timestamps + soft deletes │                    │                                                │
 └───────────────────────────┴────────────────────┴────────────────────────────────────────────────┘
 Migration 2: create_payments_table
 ┌──────────────────────┬───────────────┬──────────────────────────────────────┐
 │        Column        │     Type      │             Description              │
 ├──────────────────────┼───────────────┼──────────────────────────────────────┤
 │ id                   │ bigint        │ Primary key                          │
 ├──────────────────────┼───────────────┼──────────────────────────────────────┤
 │ subscription_id      │ foreignId     │ References subscriptions             │
 ├──────────────────────┼───────────────┼──────────────────────────────────────┤
 │ user_id              │ foreignId     │ References users                     │
 ├──────────────────────┼───────────────┼──────────────────────────────────────┤
 │ revolut_order_id     │ string        │ Revolut order ID                     │
 ├──────────────────────┼───────────────┼──────────────────────────────────────┤
 │ amount               │ integer       │ Amount in pence                      │
 ├──────────────────────┼───────────────┼──────────────────────────────────────┤
 │ currency             │ string        │ Default GBP                          │
 ├──────────────────────┼───────────────┼──────────────────────────────────────┤
 │ status               │ enum          │ pending, completed, failed, refunded │
 ├──────────────────────┼───────────────┼──────────────────────────────────────┤
 │ revolut_payment_data │ json nullable │ Raw Revolut response                 │
 ├──────────────────────┼───────────────┼──────────────────────────────────────┤
 │ timestamps           │               │                                      │
 └──────────────────────┴───────────────┴──────────────────────────────────────┘
 Migration 3: add_plan_fields_to_users_table
 ┌───────────────┬────────────────────┬─────────────────────────────────────────────┐
 │    Column     │        Type        │                 Description                 │
 ├───────────────┼────────────────────┼─────────────────────────────────────────────┤
 │ plan          │ enum               │ free, student, standard, pro (default free) │
 ├───────────────┼────────────────────┼─────────────────────────────────────────────┤
 │ trial_ends_at │ timestamp nullable │ Denormalised for quick dashboard queries    │
 └───────────────┴────────────────────┴─────────────────────────────────────────────┘
 Migration 4: create_trial_reminder_log_table
 ┌────────────────┬───────────┬─────────────────────┐
 │     Column     │   Type    │     Description     │
 ├────────────────┼───────────┼─────────────────────┤
 │ id             │ bigint    │ Primary key         │
 ├────────────────┼───────────┼─────────────────────┤
 │ user_id        │ foreignId │ References users    │
 ├────────────────┼───────────┼─────────────────────┤
 │ days_remaining │ integer   │ 3, 2, or 1          │
 ├────────────────┼───────────┼─────────────────────┤
 │ sent_at        │ timestamp │ When email was sent │
 └────────────────┴───────────┴─────────────────────┘
 Prevents duplicate emails if the scheduled command runs more than once.

 ---
 Step 2: Models

 app/Models/Subscription.php (new)

 - Relationships: belongsTo(User), hasMany(Payment)
 - Scopes: scopeActive(), scopeTrialing(), scopeExpired()
 - Helpers: isTrialing(), isActive(), daysLeftInTrial(), trialProgress() (percentage 0-100)

 app/Models/Payment.php (new)

 - Relationships: belongsTo(Subscription), belongsTo(User)

 app/Models/User.php (modify)

 - Add: subscription() hasOne relationship
 - Add: payments() hasMany relationship
 - Add helpers: onTrial(), hasActivePlan(), trialDaysRemaining(), planIs($plan)
 - Add plan, trial_ends_at to $fillable and $casts

 ---
 Step 3: Backend Services

 app/Services/Payment/RevolutService.php (new)

 - createOrder(User $user, string $plan, string $billingCycle): array — POST to Revolut Merchant API, returns public_id
 - getOrderStatus(string $orderId): array — GET order status
 - Sandbox/production toggle via config
 - Plan pricing map (amounts in pence):
 ┌──────────┬─────────┬────────┐
 │   Plan   │ Monthly │ Yearly │
 ├──────────┼─────────┼────────┤
 │ student  │ 399     │ 3000   │
 ├──────────┼─────────┼────────┤
 │ standard │ 1099    │ 10000  │
 ├──────────┼─────────┼────────┤
 │ pro      │ 1999    │ 20000  │
 └──────────┴─────────┴────────┘
 app/Services/Payment/TrialService.php (new)

 - startTrial(User $user, string $plan, string $billingCycle): Subscription — creates subscription with status=trialing, sets
 trial_started_at=now, trial_ends_at=now+7days, updates user plan and trial_ends_at
 - expireTrials(): int — finds all trialing subscriptions past trial_ends_at, sets status=expired, sets user plan=free, returns count

 Config: config/services.php

 Add revolut key:
 - api_key → env('REVOLUT_API_KEY')
 - webhook_secret → env('REVOLUT_WEBHOOK_SECRET')
 - sandbox → env('REVOLUT_SANDBOX', true)

 ---
 Step 4: Controllers

 app/Http/Controllers/Api/PaymentController.php (new)

 - POST /api/payment/create-order — Authenticated. Creates Revolut order, returns public_id for frontend widget
 - GET /api/payment/order/{id}/status — Check order status
 - GET /api/payment/trial-status — Returns trial info (days remaining, progress %, plan, billing cycle, trial_ends_at)

 app/Http/Controllers/Api/PaymentWebhookController.php (new)

 - POST /api/webhooks/revolut — Public endpoint, HMAC SHA-256 signature verification
 - Handles ORDER_COMPLETED → sets subscription status=active, records payment, updates user plan
 - Handles ORDER_PAYMENT_FAILED → sets subscription status=past_due

 ---
 Step 5: Registration Flow Update

 resources/js/views/Public/PricingPage.vue (modify)

 - Update startTrial() to include billing cycle: /register?plan=standard&billing=yearly
 - Buttons already say "Start Free Trial" — no change needed

 Backend registration (app/Http/Controllers/Api/AuthController.php) (modify)

 - Accept plan and billing_cycle params during registration
 - After user creation (post email-verification), call TrialService::startTrial() to create subscription and start the 7-day trial
 - Redirect to dashboard (not checkout)

 ---
 Step 6: Dashboard Trial Banner

 resources/js/components/Trial/TrialCountdownBanner.vue (new)

 Placed in AppLayout.vue above PreviewBanner. Only shows for non-preview users with an active trial.

 Content:
 - Shows plan name and days remaining (e.g. "Your Standard trial ends in 3 days")
 - Progress bar showing trial progress (0-100%)
 - "Upgrade Now" button → routes to /checkout
 - Dismissible per-session (reappears next login) — but always visible in final 2 days
 - Blue colour scheme (no amber/orange per design rules)

 Conditions to show:
 - User is authenticated
 - User is NOT a preview user (is_preview_user = false)
 - User has a subscription with status = trialing

 resources/js/layouts/AppLayout.vue (modify)

 - Import and place TrialCountdownBanner above PreviewBanner
 - Fetch trial status from /api/payment/trial-status on mount

 ---
 Step 7: Checkout Page

 resources/js/views/Auth/CheckoutPage.vue (new)

 - Install @revolut/checkout npm package
 - Display order summary: plan name, price, billing cycle
 - Call POST /api/payment/create-order to get Revolut public_id
 - Mount Revolut embedded widget via RevolutCheckout(public_id) — handles card, Apple Pay, Google Pay
 - On success → redirect to dashboard with success toast
 - On failure → show error, allow retry
 - "Coming Soon" state when PAYMENT_ENABLED=false

 Router (resources/js/router/index.js) (modify)

 - Add /checkout route (requires auth, no subscription check)

 ---
 Step 8: Trial Reminder Emails

 app/Mail/TrialExpirationReminder.php (new)

 - Constructor: User $user, int $daysRemaining
 - Subject: "Your Fynla trial ends in {X} days" (or "ends tomorrow")
 - From: noreply@fynla.org
 - Content: Friendly reminder with plan details, what they'll lose, and "Upgrade Now" link to /checkout

 resources/views/emails/trial-expiration-reminder.blade.php (new)

 - Branded email template matching existing verification email style
 - Shows: days remaining, plan name, features they'll lose, CTA button

 app/Console/Commands/SendTrialReminderEmails.php (new)

 - Finds users with trialing subscriptions where trial_ends_at is 3, 2, or 1 days away
 - Checks trial_reminder_log to avoid duplicates
 - Sends TrialExpirationReminder mail for each
 - Logs each send to trial_reminder_log

 app/Console/Commands/ExpireTrials.php (new)

 - Finds all subscriptions where status = trialing and trial_ends_at < now()
 - Calls TrialService::expireTrials()
 - Sets user plan = free

 app/Console/Kernel.php (modify)

 $schedule->command('trials:send-reminders')->dailyAt('09:00');
 $schedule->command('trials:expire')->dailyAt('00:05');

 ---
 Step 9: Middleware & Routes

 app/Http/Middleware/CheckSubscription.php (new)

 - Checks if user has active subscription or is trialing
 - When PAYMENT_ENABLED=false → all users pass through (feature flag)
 - When enabled → redirects expired users to checkout

 Route additions (routes/api.php)

 - POST /api/payment/create-order (auth:sanctum)
 - GET /api/payment/order/{id}/status (auth:sanctum)
 - GET /api/payment/trial-status (auth:sanctum)
 - POST /api/webhooks/revolut (no auth, signature-verified)

 app/Http/Middleware/PreviewWriteInterceptor.php (modify)

 - Add webhook route to EXCLUDED_ROUTES

 ---
 Step 10: Admin Panel — Subscription & Trial Columns

 app/Http/Controllers/Api/AdminController.php (modify)

 - Update getUsers() to eager-load subscription and subscription.payments relationships
 - Include subscription/trial fields in the user response:
   - plan — current plan (free/student/standard/pro)
   - subscription_status — trialing/active/cancelled/expired/past_due or null
   - trial_started_at — when trial began
   - trial_ends_at — when trial expires
   - trial_days_remaining — computed days left (null if not trialing)
   - last_payment_at — most recent successful payment date
   - last_payment_amount — most recent payment amount (formatted)
   - total_paid — sum of all completed payments
 - Add new admin endpoint: GET /admin/subscriptions/stats — returns aggregate counts (trialing, active, expired, revenue total)

 resources/js/components/Admin/UserManagement.vue (modify)

 Current columns: ID, Name, Email, Role, Spouse, Created, Actions

 Add new columns after "Spouse":
 ┌─────────┬─────────┬──────────────────────────────────────────────────────────────────────────────────┐
 │ Column  │ Display │                                     Details                                      │
 ├─────────┼─────────┼──────────────────────────────────────────────────────────────────────────────────┤
 │ Plan    │ Badge   │ Colour-coded: free (gray), student (blue), standard (blue-600), pro (emerald)    │
 ├─────────┼─────────┼──────────────────────────────────────────────────────────────────────────────────┤
 │ Status  │ Badge   │ trialing (blue), active (green), expired (red), cancelled (gray), past_due (red) │
 ├─────────┼─────────┼──────────────────────────────────────────────────────────────────────────────────┤
 │ Trial   │ Text    │ "Day 3/7" or "Ended" or "-" for non-trial users                                  │
 ├─────────┼─────────┼──────────────────────────────────────────────────────────────────────────────────┤
 │ Payment │ Text    │ Last payment date + amount, or "-" if none                                       │
 └─────────┴─────────┴──────────────────────────────────────────────────────────────────────────────────┘
 Add filter/sort options:
 - Filter by subscription status (All / Trialing / Active / Expired / Cancelled)
 - Sort by trial end date (to see who's expiring soonest)

 resources/js/views/Admin/AdminPanel.vue (modify)

 - Update the Dashboard tab stats to include subscription counts (trialing, active, expired, total revenue)
 - Call new /admin/subscriptions/stats endpoint

 resources/js/services/adminService.js (modify)

 - Add getSubscriptionStats() method

 routes/api.php (modify)

 - Add GET /admin/subscriptions/stats route under admin middleware group

 ---
 Step 11: Environment & Feature Flag

 .env additions

 REVOLUT_API_KEY=sandbox_key_here
 REVOLUT_WEBHOOK_SECRET=webhook_secret_here
 REVOLUT_SANDBOX=true
 PAYMENT_ENABLED=false

 .env.example — add all four keys with empty/default values

 When PAYMENT_ENABLED=false:
 - Trial banner still shows on dashboard (so we can test)
 - Checkout page shows "Coming Soon" message
 - CheckSubscription middleware passes all users through
 - Trials still start on registration (so trial tracking works)

 ---
 Files Summary
 ┌──────────────────────────────────────────────────────────────┬────────┐
 │                             File                             │ Action │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ database/migrations/xxxx_create_subscriptions_table.php      │ Create │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ database/migrations/xxxx_create_payments_table.php           │ Create │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ database/migrations/xxxx_add_plan_fields_to_users_table.php  │ Create │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ database/migrations/xxxx_create_trial_reminder_log_table.php │ Create │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ app/Models/Subscription.php                                  │ Create │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ app/Models/Payment.php                                       │ Create │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ app/Models/User.php                                          │ Modify │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ app/Services/Payment/RevolutService.php                      │ Create │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ app/Services/Payment/TrialService.php                        │ Create │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ app/Http/Controllers/Api/PaymentController.php               │ Create │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ app/Http/Controllers/Api/PaymentWebhookController.php        │ Create │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ app/Http/Controllers/Api/AuthController.php                  │ Modify │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ app/Http/Controllers/Api/AdminController.php                 │ Modify │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ app/Http/Middleware/CheckSubscription.php                    │ Create │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ app/Http/Middleware/PreviewWriteInterceptor.php              │ Modify │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ app/Mail/TrialExpirationReminder.php                         │ Create │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ app/Console/Commands/SendTrialReminderEmails.php             │ Create │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ app/Console/Commands/ExpireTrials.php                        │ Create │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ app/Console/Kernel.php                                       │ Modify │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ config/services.php                                          │ Modify │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ routes/api.php                                               │ Modify │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ resources/views/emails/trial-expiration-reminder.blade.php   │ Create │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ resources/js/components/Trial/TrialCountdownBanner.vue       │ Create │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ resources/js/layouts/AppLayout.vue                           │ Modify │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ resources/js/views/Auth/CheckoutPage.vue                     │ Create │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ resources/js/views/Public/PricingPage.vue                    │ Modify │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ resources/js/components/Admin/UserManagement.vue             │ Modify │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ resources/js/views/Admin/AdminPanel.vue                      │ Modify │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ resources/js/services/adminService.js                        │ Modify │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ resources/js/router/index.js                                 │ Modify │
 ├──────────────────────────────────────────────────────────────┼────────┤
 │ .env.example                                                 │ Modify │
 └──────────────────────────────────────────────────────────────┴────────┘
 ---
 Implementation Order

 1. Database migrations (subscriptions, payments, users, trial_reminder_log)
 2. Models (Subscription, Payment) + User relationship updates
 3. TrialService + RevolutService
 4. PaymentController + PaymentWebhookController
 5. AuthController modification (start trial on registration)
 6. Routes + middleware
 7. Trial reminder emails (Mailable + Blade template + Commands + Kernel schedule)
 8. Frontend: TrialCountdownBanner + AppLayout integration
 9. Frontend: CheckoutPage + router update
 10. PricingPage billing cycle param update
 11. Admin panel: subscription columns + stats endpoint + filters
 12. Feature flag + config
 13. Tests

 ---
 Verification

 1. Registration flow: Register a new user with ?plan=standard&billing=yearly → verify subscription created with status=trialing,
 trial_ends_at = 7 days out
 2. Dashboard banner: Log in as trial user → verify countdown banner shows days remaining, progress bar, and "Upgrade Now" button
 3. Preview users: Log in as Mitchell persona → verify NO trial banner appears
 4. Checkout page: Click "Upgrade Now" → verify checkout page loads (shows "Coming Soon" when PAYMENT_ENABLED=false)
 5. Trial reminders: Run php artisan trials:send-reminders with a user whose trial ends in 3 days → verify email sent, logged in
 trial_reminder_log
 6. Trial expiry: Run php artisan trials:expire with an expired trial → verify subscription status=expired, user plan=free
 7. Webhook: POST to /api/webhooks/revolut with valid HMAC signature → verify subscription activated
 8. Admin panel: Log in as admin → verify User Management table shows Plan, Status, Trial, and Payment columns for each user
 9. Admin filters: Filter by "Trialing" → verify only trial users shown; sort by trial end date
 10. Admin stats: Dashboard tab shows subscription counts (trialing, active, expired) and total revenue
