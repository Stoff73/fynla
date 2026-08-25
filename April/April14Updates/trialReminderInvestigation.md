# Trial Reminder Email System — Investigation Report

**Date:** 14 April 2026
**Investigator:** Claude (session 51)
**Reported by:** CSJ — "the email reminder system for users on trial does not seem to be working"
**Status:** Root cause identified. Two related production fixes applied (ghost trials expired, `notifications` table created). Cron setup still pending — must be done by user via SiteGround Site Tools.
**Scope:** Production server (fynla.org) + local dev codebase

---

## Status Update — 14 April afternoon

After the initial investigation, the following actions were taken with explicit user authorisation:

### ✅ DONE — 11 ghost trialing subscriptions expired (production)

Ran `php artisan trials:expire` on production. Result:

- 11 subscriptions: `status: trialing → expired`
- 11 users: `plan → free`
- 11 `data_retention_starts_at` timestamps set (30-day grace period before data purge)
- Trialing total dropped from 22 → 11 (only legitimate active trials remain)

**Affected users — 10 of 11 are test accounts:**

| User ID | Email | Real or test |
|---|---|---|
| 301 | `jessicacracknell18@gmail.com` | **Real user** — 34 days overdue. Will lose access on next login. Never received a single reminder email. Worth flagging if she gets in touch. |
| 398–402 | `j1@fynla.or`–`j5@fynla.or` | Test (typo emails — `.or` not `.org`) |
| 413–417 | `j1@fynla.org`–`j5@fynla.org` | Test |

### ✅ DONE — `notifications` table created on production

**My original report was wrong about which command was affected.** The `notifications:daily-insight` command does NOT need this table — it sends FCM push notifications via HTTP and only touches `notification_preferences` and `device_tokens` (both already exist on production).

**Actual scope:** 5 OTHER scheduled commands call `$user->notify(...)` with notification classes that use `via(['database'])`. All 5 would crash daily once cron is enabled, writing stack traces to `laravel.log`:

| Command | Schedule | Notification class(es) using `via(['database'])` |
|---|---|---|
| `notifications:policy-renewals` | Daily 09:00 | `PolicyRenewalNotification` |
| `protection:send-alerts` | Daily 09:15 | `ProtectionAlertNotification` |
| `notifications:mortgage-rate-alerts` | Daily 09:30 | `MortgageRateAlertNotification` |
| `savings:send-alerts` | Daily 10:00 | `SavingsMaturityAlertNotification`, `SavingsRateExpiryNotification`, `ISAAllowanceWarningNotification`, `EmergencyFundAlertNotification` |
| `estate:send-alerts` | Daily 10:30 | `GiftExemptionNotification`, `TrustAnniversaryNotification`, plus an inline anonymous notification class |

**Fix applied:**

1. Generated migration locally: `php artisan notifications:table` → produced `database/migrations/2026_04_14_094042_create_notifications_table.php`
2. Edited the file to match Fynla conventions (`declare(strict_types=1);`, `Schema::hasTable()` safety check)
3. Verified locally: migration applied, schema accepts insert/readback/delete
4. Uploaded to production via SSH MCP to `~/www/fynla.org/public_html/database/migrations/2026_04_14_094042_create_notifications_table.php`
5. Ran `php artisan migrate --force` on production — applied in 49ms
6. Verified on production: table exists with 8 columns (`id`, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`, `created_at`, `updated_at`); insert + readback + delete all succeed

**The 5 commands above will no longer crash** when cron triggers them.

### ⏳ NOT DONE — System cron setup (still requires user)

The cron entry to trigger `php artisan schedule:run` every minute still needs to be added via SiteGround Site Tools → Devs → Cron Jobs. Instructions in section 6 below. **This is the only outstanding action.** Until it's done, none of the scheduled commands run, including the trial reminders that triggered this investigation.

---

---

## TL;DR

**The trial reminder emails (and every other scheduled command in the app) have never run on production.** The application code is correct and complete — the `trials:send-reminders` artisan command, the `TrialExpirationReminder` mail class, the `trial-expiration-reminder.blade.php` template, the `trial_reminder_log` dedup table, the `Kernel.php` schedule entry — all exist and work.

What does **not** exist is a system cron entry on the production server calling `php artisan schedule:run` every minute. Without that, Laravel's scheduler is never triggered, so none of the daily commands ever fire. This was never set up when the app was deployed to SiteGround.

**Fix:** Add one cron job via SiteGround Site Tools. Step-by-step instructions in section 6.

---

## 1. The Reported Problem

Trial users are not receiving the countdown reminder emails they should be receiving as their trial nears expiry. The system is supposed to send reminders at 3, 2, and 1 days before `trial_ends_at`.

---

## 2. Investigation Method

Followed the systematic-debugging skill (Phase 1: Root Cause Investigation). Gathered evidence at every layer of the chain before forming any hypothesis:

```
System cron  →  schedule:run  →  trials:send-reminders  →  Mail::send  →  SMTP
```

Investigated both local dev and production. Used the `mcp__ssh-fynla__ssh_exec` MCP tool for production checks.

---

## 3. What Is In Place — Code Inventory

The application code is **complete and correct**. Nothing needs to be built.

| Component | File | Status |
|---|---|---|
| Artisan command | `app/Console/Commands/SendTrialReminderEmails.php` | ✅ Correct logic, dedup against `trial_reminder_log` |
| Mail class | `app/Mail/TrialExpirationReminder.php` | ✅ Correct, takes `User` + `daysRemaining` |
| Email template | `resources/views/emails/trial-expiration-reminder.blade.php` | ✅ Exists, well-formatted HTML with CTA |
| Dedup table migration | `database/migrations/2026_02_12_100004_create_trial_reminder_log_table.php` | ✅ Already migrated on production (table exists) |
| Scheduler entry | `app/Console/Kernel.php:17` | ✅ `$schedule->command('trials:send-reminders')->dailyAt('09:00');` |
| Mail SMTP config | `.env` on prod | ✅ `mail.fynla.org:465` SSL, `noreply@fynla.org` (already proven by transactional emails) |
| Trial creation | `app/Services/Payment/TrialService.php` | ✅ Sets `trial_started_at` + `trial_ends_at` correctly from `SubscriptionPlan.trial_days` (7 days for all plans) |

### Logic the system implements

`SendTrialReminderEmails::handle()` lines 25–67:

> Each day at 09:00, find every subscription with `status = 'trialing'` whose `trial_ends_at` is exactly 3, 2, or 1 days away. For each match, check `trial_reminder_log` to see if a reminder for that `days_remaining` has already been sent. If not, send the email and insert a log row.

So a user on a 7-day trial receives **3 reminder emails** total: at trial day 5 (3 days left), day 6 (2 days left), and day 7 (1 day left). One email per `days_remaining` value, deduped via the `(user_id, days_remaining)` unique key on `trial_reminder_log`.

> ⚠️ **Worth confirming:** You said reminders should fire "from day 3 of their trial." That phrase is ambiguous:
> - **A:** "Starting when 3 days remain" → emails at trial days 5/6/7 (what the code does)
> - **B:** "Starting on day 3 after sign-up" → emails at trial days 3/4/5/6/7 (more aggressive)
>
> The system is built for A. If you want B, the command and the template need rework.

---

## 4. Root Cause

**No system cron entry exists on production to trigger `php artisan schedule:run`.**

The Laravel scheduler is correctly configured in `app/Console/Kernel.php` and the application knows about all 15 scheduled commands (verified via `php artisan schedule:list` on production). But Laravel's scheduler only runs when an external cron triggers `schedule:run` every minute. That trigger does not exist.

### Evidence

All checks below were run on production via SSH to `u2783-hrf1k8bpfg02@ssh.fynla.org`.

| Check | Result | Implication |
|---|---|---|
| `crontab -l` | exit code 1 — "no crontab for user" | No system cron entry for this user account |
| `~/.cron/` directory | does not exist | No alternative cron mechanism in user home |
| `/var/spool/cron/` and `/etc/cron.d/` | not accessible | (expected on shared hosting; ruled out for completeness) |
| `php artisan schedule:list` | works, shows all 15 commands | Scheduler is correctly *configured* — it's just never *triggered* |
| `trial_reminder_log` row count | **0** before investigation | `trials:send-reminders` has never sent anything |
| Trialing subs with `trial_ends_at < now()` still marked `trialing` | **11** (oldest from 2026-03-10, 34 days overdue) | `trials:expire` (00:05 daily) has never run |
| `pending_registrations` oldest row | **2026-01-02** (102 days old) | `registrations:cleanup` (hourly) has never run |
| Production trialing subscriptions (current) | 22 total — multiple users at 2 and 3 days remaining who should be getting reminders right now | Confirms the user-reported symptom |

The vault's own architecture doc explicitly documents the requirement, but it was never followed when deploying to SiteGround. From `fynlaBrain/Current State/ConsoleCommands.md` lines 30–34:

> **Important:** For the scheduler to work, the server must have a cron entry running `php artisan schedule:run` every minute:
> ```
> * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
> ```

---

## 5. Collateral Damage — Every Scheduled Command Is Affected

Because `schedule:run` has never been triggered on production, **none** of these 15 commands have ever run:

| Command | Frequency | Impact of not running |
|---|---|---|
| `trials:send-reminders` | Daily 09:00 | **0 reminder emails ever sent** (the reported issue) |
| `trials:expire` | Daily 00:05 | 11 ghost `trialing` subscriptions; users still see paid features they shouldn't |
| `subscriptions:send-renewal-reminders` | Daily 09:00 | No annual/monthly renewal reminders to live subscribers |
| `data-retention:send-warnings` | Daily 09:00 | GDPR data retention warnings never sent |
| `data-retention:purge-expired` | Daily 00:30 | Expired user data never purged (potential GDPR risk) |
| `subscriptions:check-overdue` | Daily 01:00 | Failed renewals never escalated |
| `registrations:cleanup` | Hourly | `pending_registrations` rows from 2026-01-02 still present |
| `sessions:cleanup` | Daily 02:00 | Orphaned sessions accumulating |
| `audit:purge` | Weekly Sun 03:00 | Audit logs growing unbounded |
| `notifications:daily-insight` | Daily 08:00 | No daily insights sent. ~~Also: would crash on missing `notifications` table~~ — **CORRECTED:** this specific command bypasses the Laravel notification system and sends FCM push notifications directly via HTTP, so it does NOT need the `notifications` table. The table-missing issue affects 5 OTHER commands instead — see Status Update at top. |
| `notifications:policy-renewals` | Daily 09:00 | Policy renewal reminders silent |
| `protection:send-alerts` | Daily 09:15 | Protection alerts silent |
| `notifications:mortgage-rate-alerts` | Daily 09:30 | Mortgage rate alerts silent |
| `savings:send-alerts` | Daily 10:00 | Savings alerts silent |
| `estate:send-alerts` | Daily 10:30 | Estate alerts silent |

This is a **system-wide outage of all background processing**, not just the trial emails.

---

## 6. What You Need To Do — Cron Setup Instructions

The fix is one cron line, added via the SiteGround Site Tools UI. SSH does not have permission to write to crontab on this account, so the panel is the only path.

### Step 1 — Open SiteGround Site Tools

1. Log in to your SiteGround account: https://my.siteground.com
2. Click **Websites** in the top nav
3. Find **fynla.org** in the site list and click **Site Tools**

### Step 2 — Navigate to Cron Jobs

In the Site Tools sidebar:
**Devs** → **Cron Jobs**

(If you don't see it under Devs, try **Site** → **Cron Jobs** — SiteGround occasionally moves things around.)

### Step 3 — Create the cron job

You'll see a form with two sections:

**Common Settings** — leave alone, use the custom fields below.

**Cron Job Schedule:**
- Minute: `*`
- Hour: `*`
- Day: `*`
- Month: `*`
- Weekday: `*`

(All five fields set to `*`. This means "every minute, every hour, every day".)

**Command:**
```
cd /home/u2783-hrf1k8bpfg02/www/fynla.org/public_html && /usr/local/php83/bin/php-cli artisan schedule:run >> /dev/null 2>&1
```

> **Why these specific paths:**
> - `/home/u2783-hrf1k8bpfg02/www/fynla.org/public_html` — the application root on production. Verified via `pwd` over SSH.
> - `/usr/local/php83/bin/php-cli` — the CLI PHP binary on this server. Verified by inspecting the output of `php artisan schedule:test` which prints the binary it uses. This is **not** the same as plain `php` on this server, so do not shorten it.
> - `>> /dev/null 2>&1` — suppress all output; the scheduler logs internally to `storage/logs/laravel.log` if anything errors.

### Step 4 — Save

Click **Create**. The cron should immediately appear in the cron jobs list with "Next Run" within the next minute.

### Step 5 — Verify it's working (do this 5 minutes after saving)

SSH to production:
```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
```

Then run these checks:

**(a) Did the scheduler tick at all?**
```bash
tail -50 storage/logs/laravel.log | grep -i "schedule\|trial\|cron"
```
Should be either empty (no errors) or show successful command runs. If you see PHP errors or stack traces, the cron is running but a command is failing — paste them and we'll diagnose.

**(b) Did `registrations:cleanup` actually fire?** (it runs hourly and is the fastest check)
```bash
php artisan tinker --execute="echo 'pending: '.\DB::table('pending_registrations')->count().PHP_EOL; echo 'oldest: '.(\DB::table('pending_registrations')->orderBy('created_at')->value('created_at')??'none').PHP_EOL;"
```
Within an hour, the oldest row should no longer be from 2026-01-02 (the ones from January should get cleaned up).

**(c) Will tomorrow's trial reminders fire?**
The trial reminder run is scheduled for 09:00. Tomorrow (15 April), at any time after 09:00, run:
```bash
php artisan tinker --execute="echo 'reminder log rows: '.\DB::table('trial_reminder_log')->count().PHP_EOL;"
```
You should see new rows added beyond the 10 that already exist (see section 7 below for what's already in the table).

---

## 7. ⚠️ Side Effect From This Investigation — Disclosure

While investigating I ran `php artisan schedule:test --name="trials:send-reminders"` on production expecting it to be a dry run. **It is not a dry run — Laravel's `schedule:test` actually executes the command.** As a result, **10 real reminder emails were sent to live users at 08:42 UTC on 14 April:**

| User ID | Days remaining | Reminder type |
|---:|---:|---|
| 581 | 3 | 3-day reminder |
| 582 | 3 | 3-day reminder |
| 583 | 3 | 3-day reminder |
| 584 | 3 | 3-day reminder |
| 586 | 3 | 3-day reminder |
| 587 | 3 | 3-day reminder |
| 588 | 3 | 3-day reminder |
| 551 | 2 | 2-day reminder |
| 552 | 2 | 2-day reminder |
| 580 | 2 | 2-day reminder |

The content sent was correct — these are exactly the users who legitimately should have been receiving these emails today under the system's design. The `trial_reminder_log` was correctly updated, so they will **not** receive duplicate emails when the cron is set up.

The issue is that I sent them via manual diagnostics instead of waiting to confirm with you first. That was my mistake — I should have known `schedule:test` is not a dry run. **I won't run any more commands that change production state without explicit permission.**

---

## 8. Outstanding Decisions For You

These are not things I'm fixing. They need your decision before any further action.

### 8a. Cron setup
Do step 6 above. This is the only mandatory action — without it the system is broken.

### 8b. Reminder frequency interpretation
Confirm whether the system's "3, 2, 1 days remaining" logic is what you actually want, or whether you meant "from day 3 onwards after sign-up" (more emails). See section 3 for the difference.

### 8c. The 11 ghost trialing subscriptions (from March) — ✅ DONE

**Status:** Resolved. `trials:expire` was run on production with user authorisation. All 11 expired, users moved to `plan='free'`, 30-day data retention countdown started for each. See "Status Update" at the top of this report for the full breakdown of affected users (10 test, 1 real).

### 8d. Missing `notifications` table on production — ✅ DONE (with corrected scope)

**Status:** Resolved. The `notifications` table was created on production via a new migration (`2026_04_14_094042_create_notifications_table.php`).

**Original scope was wrong** — the issue did NOT affect `notifications:daily-insight` (which uses FCM directly). It affected 5 OTHER scheduled commands that call `$user->notify(...)` with notification classes using `via(['database'])`:

- `notifications:policy-renewals`
- `protection:send-alerts`
- `notifications:mortgage-rate-alerts`
- `savings:send-alerts`
- `estate:send-alerts`

All would have crashed daily once cron was enabled. They will now work correctly. Full details in the Status Update at the top of this report.

### 8e. Document the cron requirement
`fynlaBrain/Architecture/v083/11-CONFIGURATION-DEPLOYMENT.md` documents .env vars and seeders but does **not** include the cron entry as a deploy step. Add it so this exact bug cannot recur on any future server migration. (`fynlaBrain/Current State/ConsoleCommands.md` does mention it, but only as a passing note; it should be in the deploy checklist.)

---

## 9. Files Read / Touched During Investigation

**Read (no changes):**
- `app/Console/Commands/SendTrialReminderEmails.php`
- `app/Console/Commands/ExpireTrials.php`
- `app/Console/Commands/SendDailyInsightNotifications.php`
- `app/Console/Kernel.php`
- `app/Mail/TrialExpirationReminder.php`
- `app/Services/Payment/TrialService.php`
- `app/Services/Mobile/PushNotificationService.php`
- `app/Models/Subscription.php`
- `app/Notifications/DailyInsightNotification.php`
- `app/Notifications/TrustAnniversaryNotification.php`
- `database/migrations/2026_02_12_100004_create_trial_reminder_log_table.php`
- `database/seeders/SubscriptionPlanSeeder.php` (referenced)
- `resources/views/emails/trial-expiration-reminder.blade.php`
- `fynlaBrain/Architecture/v083/11-CONFIGURATION-DEPLOYMENT.md`
- `fynlaBrain/Current State/ConsoleCommands.md`

**Created (local + uploaded to production):**
- `database/migrations/2026_04_14_094042_create_notifications_table.php` — creates Laravel default `notifications` table

**Production state changes:**
- `trial_reminder_log` table — 10 new rows inserted at 08:42 UTC via `schedule:test` diagnostic (see section 7)
- 10 reminder emails dispatched to users 551, 552, 580–584, 586–588 via `schedule:test` (see section 7)
- 11 subscriptions: `status: trialing → expired` via `trials:expire` (see Status Update)
- 11 users: `plan → free` via `trials:expire`
- 11 `data_retention_starts_at` timestamps set via `trials:expire`
- `notifications` table created via `migrate --force`

---

## 10. Test Plan After Cron Is Set Up

This is what to verify in browser/SSH after applying the fix in section 6.

- [ ] Cron entry visible in SiteGround Site Tools → Cron Jobs list
- [ ] `tail storage/logs/laravel.log` shows no scheduler errors after 5 minutes
- [ ] No "Base table or view not found: notifications" errors in the log after 09:00, 09:15, 09:30, 10:00, or 10:30 (the 5 commands that previously would have crashed)
- [ ] `pending_registrations` count drops within an hour as `registrations:cleanup` runs
- [ ] Tomorrow (15 April) after 09:00, `trial_reminder_log` has new entries
- [x] ~~Tomorrow after 00:05, ghost trialing count returns 0~~ — already done manually via authorised `trials:expire` run
- [ ] Send a test trial signup, verify reminder lands in your inbox 4 days later
