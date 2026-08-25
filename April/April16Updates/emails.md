# Fynla Email Notification Reference

*Last updated: 16 April 2026*
*Branch: `awinPlusDev` (live) + `lifecycle-email-engine` (PR #212, not yet deployed)*

All emails sent from `noreply@fynla.org` ("Fynla") unless noted.

---

## 1. Scheduled Emails (Cron)

Emails sent automatically by Laravel's scheduler (`app/Console/Kernel.php`). Production cron runs `php artisan schedule:run` every minute.

### 1a. Trial Expiration Reminders

| | |
|---|---|
| **Command** | `trials:send-reminders` |
| **Schedule** | Daily at 09:00 |
| **Recipients** | Trial users with `trial_ends_at` 3, 2, or 1 day(s) away |
| **Frequency** | Up to 3 emails per user (one per day at each threshold) |
| **Deduplication** | `trial_reminder_log` table (per user, per day-count) |
| **Mailable** | `App\Mail\TrialExpirationReminder` |
| **Template** | `resources/views/emails/trial-expiration-reminder.blade.php` |
| **Subject** | "Your Fynla trial ends in {N} days" / "Your Fynla trial ends tomorrow" |
| **Command class** | `app/Console/Commands/SendTrialReminderEmails.php` |

### 1b. Subscription Renewal Reminders

| | |
|---|---|
| **Command** | `subscriptions:send-renewal-reminders` |
| **Schedule** | Daily at 09:00 |
| **Recipients** | Active subscribers where `current_period_end` is exactly 7 days from now |
| **Frequency** | 1 email per subscription per renewal period |
| **Deduplication** | `renewal_reminder_log` table (per subscription, per period) |
| **Mailable** | `App\Mail\SubscriptionRenewalReminder` |
| **Template** | `resources/views/emails/subscription-renewal-reminder.blade.php` |
| **Subject** | "Your Fynla subscription renews in 7 days" |
| **Command class** | `app/Console/Commands/SendRenewalReminderEmails.php` |

### 1c. Data Retention Warnings

| | |
|---|---|
| **Command** | `data-retention:send-warnings` |
| **Schedule** | Daily at 09:00 |
| **Recipients** | Users with expired subscriptions in the 30-day grace period (`data_retention_starts_at` set) |
| **Frequency** | Up to 12 emails over 30 days per user |
| **Deduplication** | `data_retention_email_log` table (per subscription, per day-number) |
| **Mailable** | `App\Mail\DataRetentionWarning` |
| **Template** | `resources/views/emails/data-retention-warning.blade.php` |
| **Command class** | `app/Console/Commands/SendDataRetentionWarnings.php` |

**Email days and subjects:**

| Day | Days Remaining | Subject |
|-----|---------------|---------|
| 1 | 30 | "Your Fynla access has ended - your data will be deleted in 30 days" |
| 15 | 15 | "15 days until your Fynla data is permanently deleted" |
| 20 | 10 | "10 days left - your Fynla data will be permanently deleted" |
| 21 | 9 | "9 days left - your Fynla data will be permanently deleted" |
| 22 | 8 | "8 days left - ..." |
| 23 | 7 | "7 days left - ..." |
| 24 | 6 | "6 days left - ..." |
| 25 | 5 | "5 days left - ..." |
| 26 | 4 | "4 days left - ..." |
| 27 | 3 | "3 days left - ..." |
| 28 | 2 | "2 days left - ..." |
| 29 | 1 | "1 day left - FINAL WARNING: your Fynla data will be permanently deleted" |

---

## 2. Lifecycle Email Engine (PR #212 - NOT YET DEPLOYED)

Branch: `lifecycle-email-engine`. 5 campaigns, 84 tests passing, E2E verified 14 April 2026. Awaiting conflict resolution in `trial-expiration-reminder.blade.php` before merge.

| | |
|---|---|
| **Command** | `lifecycle:run-daily` |
| **Schedule** | Daily at 08:30 |
| **Engine** | `App\Services\Lifecycle\LifecycleEngine` |
| **Kill switch** | `config('lifecycle.enabled')` - can be disabled without deploy |
| **Deduplication** | `lifecycle_email_log` table (per user, per campaign) |
| **User opt-out** | Per-campaign toggle in `notification_preferences` table |
| **Magic links** | Signed URLs, expire after 7 days (configurable) |
| **Test commands** | `lifecycle:e2e-test --recipient=EMAIL` / `lifecycle:e2e-cleanup` |

### Campaign 1: Cancelled Trialer (priority 1)

| | |
|---|---|
| **Target** | Users who cancelled mid-trial (cancelled_at < trial_ends_at), exactly 3 days after cancellation |
| **Opt-out field** | `lifecycle_cancelled_trialer` |
| **Mailable** | `App\Mail\Lifecycle\CancelledTrialerMail` |
| **Template** | `resources/views/emails/lifecycle/cancelled-trialer.blade.php` |
| **Subject** | "Sorry to see you go - what could we have done better?" |
| **Action** | Feedback quick-pick buttons (reason codes) + optional free-text |
| **Campaign class** | `app/Services/Lifecycle/Campaigns/CancelledTrialerCampaign.php` |

### Campaign 2: Churned Subscriber (priority 2)

| | |
|---|---|
| **Target** | Users who cancelled after trial ended (cancelled_at >= trial_ends_at), exactly 3 days after cancellation |
| **Opt-out field** | `lifecycle_churned_subscriber` |
| **Mailable** | `App\Mail\Lifecycle\ChurnedSubscriberMail` |
| **Template** | `resources/views/emails/lifecycle/churned-subscriber.blade.php` |
| **Subject** | "Thank you for being a Fynla subscriber - we'd love your feedback" |
| **Action** | Feedback quick-pick buttons + optional free-text. Includes subscription duration context |
| **Campaign class** | `app/Services/Lifecycle/Campaigns/ChurnedSubscriberCampaign.php` |

### Campaign 3: Lapsed Subscriber (priority 3)

| | |
|---|---|
| **Target** | Users with `past_due` subscription where `current_period_end` is at least 5 days ago (after Revolut retry window) |
| **Opt-out field** | `lifecycle_lapsed_subscriber` |
| **Mailable** | `App\Mail\Lifecycle\LapsedSubscriberMail` |
| **Template** | `resources/views/emails/lifecycle/lapsed-subscriber.blade.php` |
| **Subject** | "Your Fynla payment didn't go through - let's get you back on track" |
| **Action** | Update payment link + feedback quick-pick buttons. Shows grace period end date |
| **Campaign class** | `app/Services/Lifecycle/Campaigns/LapsedSubscriberCampaign.php` |

### Campaign 4: Empty Trialer (priority 4)

| | |
|---|---|
| **Target** | Users whose trial ended, no active/past_due subscription, AND no data in any module table |
| **Opt-out field** | `lifecycle_empty_trialer` |
| **Mailable** | `App\Mail\Lifecycle\EmptyTrialerMail` |
| **Template** | `resources/views/emails/lifecycle/empty-trialer.blade.php` |
| **Subject** | "It's been a while - come back and try Fynla again" |
| **Action** | Restart trial link (grants fresh 14-day trial) |
| **Campaign class** | `app/Services/Lifecycle/Campaigns/EmptyTrialerCampaign.php` |

### Campaign 5: Engaged Trialer (priority 5)

| | |
|---|---|
| **Target** | Users whose trial ended, no active/past_due subscription, AND has data in at least one module table |
| **Opt-out field** | `lifecycle_engaged_trialer` |
| **Mailable** | `App\Mail\Lifecycle\EngagedTrialerMail` |
| **Template** | `resources/views/emails/lifecycle/engaged-trialer.blade.php` |
| **Subject** | "Your Fynla picture so far, {Name} - and 25-45% off to finish it" |
| **Action** | Magic link to login with auto-generated discount code pre-filled at checkout. Includes personalised data snapshot (modules used, key metrics) |
| **Discount** | Auto-generated `lifecycle_welcome` code via `LifecycleDiscountCodeGenerator` |
| **Campaign class** | `app/Services/Lifecycle/Campaigns/EngagedTrialerCampaign.php` |

---

## 3. Webhook-Triggered Emails (Revolut Payment Events)

These fire immediately when Revolut sends a webhook to `/api/webhooks/revolut`.

### 3a. Payment Confirmation

| | |
|---|---|
| **Trigger** | Revolut `ORDER_COMPLETED` or `ORDER_AUTHORISED` webhook / `PaymentController::confirmPayment()` |
| **Recipient** | The user who made the payment |
| **Mailable** | `App\Mail\PaymentConfirmation` |
| **Template** | `resources/views/emails/payment-confirmation.blade.php` |
| **Subject** | "Payment confirmation - Fynla" |
| **Content** | Plan name, billing cycle, amount paid, discount details (if any), next renewal date, auto-renew status |
| **Sent from** | `WebhookController::handleOrderCompleted()` and `PaymentController::confirmPayment()` |

### 3b. Payment Failed

| | |
|---|---|
| **Trigger** | Revolut `SUBSCRIPTION_OVERDUE` webhook |
| **Recipient** | The user whose payment failed |
| **Mailable** | `App\Mail\PaymentFailedNotification` |
| **Template** | `resources/views/emails/payment-failed.blade.php` |
| **Subject** | "Action required - payment issue with your Fynla subscription" |
| **Content** | Plan name, amount due, period end date |
| **Sent from** | `SubscriptionRenewalService::handleSubscriptionOverdue()` |

### 3c. Invoice

| | |
|---|---|
| **Trigger** | Successful renewal payment (after webhook processing) |
| **Recipient** | The subscriber |
| **Mailable** | `App\Mail\InvoiceEmail` |
| **Template** | `resources/views/emails/invoice.blade.php` |
| **Subject** | "Your Fynla invoice - {invoice_number}" |
| **Content** | Plan name, billing cycle, amount, period dates, next renewal, discount details |
| **Attachment** | Invoice PDF (if generated and stored) |
| **Sent from** | `InvoiceService::emailInvoice()` called from `SubscriptionRenewalService::handleRenewalPayment()` |

---

## 4. User-Action-Triggered Emails

These are sent in direct response to a user action, not on a schedule.

### 4a. Email Verification Code

| | |
|---|---|
| **Trigger** | User registers or logs in without verified email |
| **Recipient** | The user |
| **Mailable** | `App\Mail\VerificationCode` |
| **Template** | `resources/views/emails/verification-code.blade.php` |
| **Subject** | "Your Fynla Verification Code" |
| **Content** | 6-digit verification code, context-aware label ("complete your registration" or "log in") |

### 4b. Password Reset Code

| | |
|---|---|
| **Trigger** | User requests password reset |
| **Recipient** | The user |
| **Mailable** | `App\Mail\PasswordResetCode` |
| **Template** | `resources/views/emails/password-reset-code.blade.php` |
| **Subject** | "Reset Your Fynla Password" |
| **Content** | 6-digit reset code |

### 4c. Account Deletion Verification Code

| | |
|---|---|
| **Trigger** | User initiates account deletion |
| **Recipient** | The user |
| **Mailable** | `App\Mail\DeletionVerificationCode` |
| **Template** | `resources/views/emails/deletion-verification-code.blade.php` |
| **Subject** | "Account Deletion Verification Code" |
| **Content** | 6-digit verification code |

### 4d. Data Deletion Confirmation

| | |
|---|---|
| **Trigger** | Account/data permanently deleted |
| **Recipient** | The user's email (sent after deletion) |
| **Mailable** | `App\Mail\DataDeletionConfirmation` |
| **Template** | `resources/views/emails/data-deletion-confirmation.blade.php` |
| **Subject** | "Your Fynla data has been permanently deleted" |

### 4e. Spouse Account Created

| | |
|---|---|
| **Trigger** | Primary user creates a spouse account |
| **Recipient** | The new spouse |
| **Mailable** | `App\Mail\SpouseAccountCreated` |
| **Template** | `resources/views/emails/spouse-account-created.blade.php` |
| **Subject** | "Your Fynla Account Has Been Created" |
| **Content** | Created-by name, temporary password |

### 4f. Spouse Account Linked

| | |
|---|---|
| **Trigger** | Spouse account linked to primary |
| **Recipient** | The spouse |
| **Mailable** | `App\Mail\SpouseAccountLinked` |
| **Template** | `resources/views/emails/spouse-account-linked.blade.php` |
| **Subject** | "Your Fynla Account Has Been Linked" |

### 4g. Spouse Data Sharing Request

| | |
|---|---|
| **Trigger** | User requests permission to view spouse's data |
| **Recipient** | The spouse being asked |
| **Notification** | `App\Notifications\SpousePermissionRequest` (via `mail` channel, queued) |
| **Subject** | "Spouse Data Sharing Request" |
| **Sent from** | `SpousePermissionController::request()` |

### 4h. Subscription Cancellation

| | |
|---|---|
| **Trigger** | User cancels their subscription |
| **Recipient** | The user |
| **Mailable** | `App\Mail\SubscriptionCancellation` |
| **Template** | `resources/views/emails/subscription-cancellation.blade.php` |
| **Subject** | "Subscription cancelled - Fynla" |
| **Content** | Plan name, billing cycle, access-until date |
| **Sent from** | `PaymentController::cancelSubscription()` |

### 4i. Referral Invitation

| | |
|---|---|
| **Trigger** | User sends a referral invite |
| **Recipient** | The friend being invited |
| **Mailable** | `App\Mail\ReferralInvitationEmail` |
| **Template** | `resources/views/emails/referral-invitation.blade.php` |
| **Subject** | "{Name} thinks you'd like Fynla" |
| **Content** | Referrer name, referral code, registration link with `?ref=CODE` |

### 4j. Bug Report (Internal)

| | |
|---|---|
| **Trigger** | User submits a bug report |
| **Recipient** | Internal support team (not users) |
| **Mailable** | `App\Mail\BugReportMail` |
| **Template** | `resources/views/emails/bug-report.blade.php` |
| **Subject** | "Bug Report - User {userId}" (with [PREVIEW] badge for preview users) |

---

## 5. Scheduled Notifications (NOT Email)

These are scheduled commands that create push or database notifications only. No emails are sent.

| Time | Command | Channel | Notification Type |
|------|---------|---------|-------------------|
| 00:05 | `trials:expire` | None | Expires trials, no notification |
| 01:00 | `subscriptions:check-overdue` | None | Status check only |
| 08:00 | `notifications:daily-insight` | Push | `DailyInsightNotification` |
| 09:00 | `notifications:policy-renewals` | Push | `PolicyRenewalNotification` — 30 days before policy renewal |
| 09:15 | `protection:send-alerts` | Database | `ProtectionAlertNotification` — expired policies, approaching renewals (24/12/3 months), annual review |
| 09:30 | `notifications:mortgage-rate-alerts` | Push | `MortgageRateAlertNotification` — 90/60/30 days before fixed rate ends |
| 10:00 | `savings:send-alerts` | Database | `SavingsMaturityAlertNotification` (90/30/7 days), `SavingsRateExpiryNotification` (90/30/7 days), `ISAAllowanceWarningNotification` (90 days before tax year end), `EmergencyFundAlertNotification` (savings < 1 month expenditure) |
| 10:30 | `estate:send-alerts` | Database | `GiftExemptionNotification` (at 6yr/6.5yr/6yr11mo), `TrustAnniversaryNotification` (90 days before 10-year charge), Annual IHT review (first 30 days of tax year) |

---

## 6. Full Daily Timeline

Complete chronological view of all scheduled automated activity (emails + notifications).

| Time | Type | Action |
|------|------|--------|
| 00:05 | System | `trials:expire` — expire ended trials |
| 00:30 | System | `data-retention:purge-expired` — permanently delete data past 30-day grace |
| 01:00 | System | `subscriptions:check-overdue` — detect missed webhooks |
| 02:00 | System | `sessions:cleanup` — clean orphaned sessions |
| 03:00 (Sun) | System | `audit:purge` — purge old audit log entries |
| Hourly | System | `registrations:cleanup` — remove stale pending registrations |
| 08:00 | Push | Daily insight notification |
| 08:30 | **Email** | Lifecycle engine: up to 5 campaigns *(NOT YET DEPLOYED)* |
| 09:00 | **Email** | Trial expiration reminders (3/2/1 day warnings) |
| 09:00 | **Email** | Subscription renewal reminders (7 days before) |
| 09:00 | **Email** | Data retention warnings (Day 1, 15, 20-29) |
| 09:00 | Push | Policy renewal reminders (30 days before) |
| 09:15 | Database | Protection alerts (expiry, renewal, annual review) |
| 09:30 | Push | Mortgage rate alerts (90/60/30 days before) |
| 10:00 | Database | Savings alerts (maturity, rate expiry, ISA allowance, emergency fund) |
| 10:30 | Database | Estate alerts (gift exemption, trust anniversary, annual IHT review) |

---

## 7. Summary Counts

| Category | Count | Status |
|----------|-------|--------|
| Scheduled email commands | 3 | Live |
| Lifecycle email campaigns | 5 | PR #212 (not deployed) |
| Webhook-triggered emails | 3 | Live |
| User-action emails | 9 | Live |
| Internal emails (bug report) | 1 | Live |
| **Total email types** | **21** | 16 live, 5 pending |
| Scheduled push notifications | 4 | Live |
| Scheduled database notifications | 3 commands (9+ alert types) | Live |
| Mailable classes (`app/Mail/`) | 15 (+ 5 on lifecycle branch) | |
| Notification classes (`app/Notifications/`) | 15 | |
| Blade templates (`resources/views/emails/`) | 15 (+ 6 on lifecycle branch) | |
