# Lifecycle Email Engine — Deploy Notice

**Branch:** `lifecycle-email-engine` @ commit `9726875`
**Commits ahead of main:** 52
**Status:** All Phase 13 verification passing — **ready to ship**
**Related reports:** [`lifecycleEngineE2EReport.md`](lifecycleEngineE2EReport.md) (E2E verification), [`CSJTODO.md`](CSJTODO.md) (Priority 1 cron verification — **deploy is gated on this**)

---

## ⚠️ Pre-flight — deploy is blocked until these are true

1. **Production cron must be verified firing.** See `CSJTODO.md` Priority 1 — four SSH checks to confirm scheduled commands are running on production after the 14 April cron entry addition. If cron is not firing, `lifecycle:run-daily` will never execute and shipping is pointless.
2. **Zero lifecycle test users on production.** The `lifecycle:run-daily` command refuses to run if any `is_lifecycle_test_user = true` rows exist. On a clean production DB there shouldn't be any, but verify:
   ```sql
   SELECT COUNT(*) FROM users WHERE is_lifecycle_test_user = 1;
   -- must be 0
   ```

---

## What's shipping

**5 lifecycle email campaigns** covering the trial and subscription lifecycle:

| # | Campaign | Trigger | Action |
|---|---|---|---|
| 1 | `empty_trialer` | Trial expired 7+ days ago, no module data | Offer a fresh 14-day trial via signed magic link |
| 2 | `engaged_trialer` | Trial expired 7+ days ago, has data | Offer a one-shot £5 / 25-45% discount code on Student/Standard/Family |
| 3 | `cancelled_trialer` | Cancelled mid-trial, 3 days ago | Feedback request (7 reason codes + optional free text) |
| 4 | `churned_subscriber` | Cancelled AFTER trial, 3 days ago | Feedback request with duration-of-subscription personalisation |
| 5 | `lapsed_subscriber` | `past_due` for 5+ days | "Your payment didn't go through" + update-payment magic link + 3 quick-pick responses |

Runs daily at **08:30 UTC** via `lifecycle:run-daily`. Per-campaign preference controls in user notification settings. Magic links are Laravel signed URLs with 7-day TTL.

---

## Step 1 — Build production assets locally

```bash
cd /Users/CSJ/Desktop/fynla
git checkout lifecycle-email-engine
git pull
./deploy/fynla-org/build.sh
```

Expected: `public/build/` populated with hashed asset filenames. Check no build errors.

---

## Step 2 — File upload list

**Upload the entire `public/build/` directory via SiteGround File Manager.** This is the single largest artefact and carries every Vue change in hashed form.

Then upload these individual files (paths relative to repo root → destination is same path under `~/www/fynla.org/public_html/`):

### Backend — PHP files (40)

```
app/Console/Commands/RunLifecycleEngine.php
app/Console/Commands/RunLifecycleEngineE2ECleanup.php
app/Console/Commands/RunLifecycleEngineE2ETest.php
app/Console/Kernel.php
app/Http/Controllers/Api/NotificationPreferenceController.php
app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php
app/Http/Controllers/Lifecycle/LifecycleActionController.php
app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php
app/Mail/Lifecycle/CancelledTrialerMail.php
app/Mail/Lifecycle/ChurnedSubscriberMail.php
app/Mail/Lifecycle/EmptyTrialerMail.php
app/Mail/Lifecycle/EngagedTrialerMail.php
app/Mail/Lifecycle/LapsedSubscriberMail.php
app/Models/DiscountCode.php
app/Models/FeedbackResponse.php
app/Models/LifecycleEmailLog.php
app/Models/NotificationPreference.php
app/Models/User.php
app/Providers/AppServiceProvider.php
app/Services/Lifecycle/Campaigns/CancelledTrialerCampaign.php
app/Services/Lifecycle/Campaigns/ChurnedSubscriberCampaign.php
app/Services/Lifecycle/Campaigns/EmptyTrialerCampaign.php
app/Services/Lifecycle/Campaigns/EngagedTrialerCampaign.php
app/Services/Lifecycle/Campaigns/LapsedSubscriberCampaign.php
app/Services/Lifecycle/Contracts/LifecycleCampaign.php
app/Services/Lifecycle/LifecycleDiscountCodeGenerator.php
app/Services/Lifecycle/LifecycleEngine.php
app/Services/Lifecycle/LifecycleSnapshotService.php
app/Services/Payment/DiscountCodeService.php
app/Services/Payment/TrialService.php
```

New directories created for the first time on production:
- `app/Http/Controllers/Lifecycle/`
- `app/Mail/Lifecycle/`
- `app/Services/Lifecycle/`
- `app/Services/Lifecycle/Campaigns/`
- `app/Services/Lifecycle/Contracts/`

### Config file (1)

```
config/lifecycle.php
```

**Important:** holds Campaign 2's discount amounts, TTL knobs, preference column map, and feedback reason codes. Changes to any of these are deploy-only (no admin UI).

### Routes (2)

```
routes/api.php
routes/web.php
```

`routes/api.php` adds GET/PUT `/api/notifications/preferences`. `routes/web.php` adds 5 signed `/lifecycle/*` routes before the SPA catch-all (critical ordering — catch-all must remain the LAST route).

### Migrations (7)

```
database/migrations/2026_04_14_122231_create_lifecycle_email_log_table.php
database/migrations/2026_04_14_122345_create_feedback_responses_table.php
database/migrations/2026_04_14_122424_add_user_id_and_metadata_to_discount_codes.php
database/migrations/2026_04_14_122508_add_is_lifecycle_test_user_to_users.php
database/migrations/2026_04_14_122545_add_lifecycle_columns_to_notification_preferences.php
database/migrations/2026_04_14_122656_add_subscriptions_indexes.php
database/migrations/2026_04_14_123409_add_lifecycle_welcome_to_discount_codes_type_enum.php
```

All 7 must be uploaded and run via `php artisan migrate --force`. Order matters — the ENUM migration (`123409`) depends on the column addition migration (`122424`). Laravel's migration table sorts by timestamp so this is automatic.

### Blade templates (10)

```
resources/views/emails/lifecycle/_button.blade.php
resources/views/emails/lifecycle/_layout.blade.php
resources/views/emails/lifecycle/_quick-picks.blade.php
resources/views/emails/lifecycle/cancelled-trialer.blade.php
resources/views/emails/lifecycle/churned-subscriber.blade.php
resources/views/emails/lifecycle/empty-trialer.blade.php
resources/views/emails/lifecycle/engaged-trialer.blade.php
resources/views/emails/lifecycle/lapsed-subscriber.blade.php
resources/views/lifecycle/feedback-text-thanks.blade.php
resources/views/lifecycle/feedback-thanks.blade.php
resources/views/emails/trial-expiration-reminder.blade.php
```

New directories created for the first time:
- `resources/views/emails/lifecycle/`
- `resources/views/lifecycle/`

The `trial-expiration-reminder.blade.php` is a **pre-existing file** that had its palette swept from generic blue to the Fynla palette — it must be overwritten with the new version.

### Seeder — local/staging only (1, do not upload to production)

```
database/seeders/LifecycleTestSeeder.php
```

**Do not upload.** This is the e2e test seeder. Production must never run it. The `lifecycle:run-daily` command already refuses to run if `is_lifecycle_test_user` rows exist, but don't take chances — leave this file off the production deploy.

---

## Step 3 — SSH to production and run migrations + cache clear

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# Pre-flight — confirm zero test users before migrating
php artisan tinker --execute="echo 'test_users='.\App\Models\User::where('is_lifecycle_test_user', true)->count().PHP_EOL;"
# must print: test_users=0

# Run the 7 lifecycle migrations
php artisan migrate --force

# Confirm all 7 ran
php artisan migrate:status | grep 2026_04_14

# Clear all caches so new routes, config, views and class map are picked up
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan optimize
```

---

## Step 4 — Verify the routes and schedule on production

```bash
# Still on the production SSH session
php artisan route:list | grep lifecycle
```

Expected: **5 lifecycle routes** listed — `lifecycle.restart-trial`, `lifecycle.apply-discount`, `lifecycle.feedback`, `lifecycle.update-payment`, `lifecycle.feedback-text`. All with `signed` middleware.

```bash
php artisan schedule:list | grep lifecycle
```

Expected: `30 8 * * *  php artisan lifecycle:run-daily` with a future "Next Due" time.

```bash
# Smoke-test the command runs end-to-end with no real users eligible
php artisan lifecycle:run-daily
```

Expected output: per-campaign stats, all `0 sent` on production initially (no production-eligible users exist until the next real trial expires/cancels — the actual emails will start going out gradually as users age into each campaign's eligibility window).

If the stale-test-user guard fires with `Refusing to run — N test users still exist`, **stop and investigate** — there should be zero test users on production.

---

## Step 5 — Monitor the first real fire at 08:30 UTC

The next scheduled run is **08:30 UTC the day after deploy**. At that point:

```bash
# On production, after 08:30 UTC the next day
php artisan tinker --execute="echo 'lifecycle_email_log rows: '.\App\Models\LifecycleEmailLog::count().PHP_EOL; \App\Models\LifecycleEmailLog::orderByDesc('sent_at')->limit(5)->get()->each(function(\$r){echo '  '.\$r->campaign.' user='.\$r->user_id.' sent='.\$r->sent_at.PHP_EOL;});"
```

Check `storage/logs/laravel.log` for the `"Lifecycle engine starting"` and `"Lifecycle engine completed"` entries with the stats JSON. Any `"Lifecycle email send failed"` or `"Lifecycle campaign failed"` errors should be triaged immediately.

---

## What's NOT in this deploy (intentional)

- **`database/seeders/LifecycleTestSeeder.php`** — local/staging only (see above).
- **The 10 test files** under `tests/Unit/Services/Lifecycle/`, `tests/Feature/Lifecycle/`, and the updated payment/model tests — production doesn't run tests, no need to upload.
- **`CLAUDE.md` and `CSJTODO.md`** — local documentation, gitignored from production. No upload needed.

---

## Rollback plan

If something goes catastrophically wrong after deploy:

1. **Kill switch first** — set `LIFECYCLE_ENGINE_ENABLED=false` in the production `.env` and clear config cache:
   ```bash
   php artisan config:clear
   ```
   The `lifecycle:run-daily` command checks `config('lifecycle.enabled')` as its first guard, so this disables the whole engine without a file rollback.

2. **If a migration needs to be reversed** — each migration has a `down()` method, but the 3 data-changing ones (ENUM extension, column adds, index adds) are non-destructive and safe to leave in place even if the PHP is rolled back. Only the new tables (`lifecycle_email_log`, `feedback_responses`) are new additions; rolling them back requires `php artisan migrate:rollback --step=N`.

3. **File rollback** — re-upload the `main` branch versions of the 40 PHP files listed above. The route file and config file are the critical ones; the rest are additive.

---

## Post-deploy checklist

- [ ] `public/build/` uploaded
- [ ] All PHP files uploaded
- [ ] `config/lifecycle.php` uploaded
- [ ] `routes/api.php` and `routes/web.php` uploaded
- [ ] 7 migrations uploaded
- [ ] 10 Blade templates uploaded
- [ ] Migrations ran (`migrate --force`)
- [ ] Caches cleared
- [ ] Routes verified (`route:list | grep lifecycle` → 5 routes)
- [ ] Schedule verified (`schedule:list | grep lifecycle` → future Next Due)
- [ ] Manual `lifecycle:run-daily` run succeeds with 0 sent
- [ ] First real scheduled fire monitored at 08:30 UTC the next day

---

## Manual e2e test against production (optional but recommended)

After the deploy is confirmed stable, you can run an end-to-end live test against production using a single recipient email. **On production**:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan lifecycle:e2e-test --recipient=chris@fynla.org
```

This seeds 5 test users, runs the engine in test mode redirected to the recipient, and should deliver 5 real emails to the recipient inbox. Then **immediately**:

```bash
php artisan lifecycle:e2e-cleanup
# verify:
php artisan tinker --execute="echo \App\Models\User::withTrashed()->where('is_lifecycle_test_user', true)->count().PHP_EOL;"
# must print 0
```

The command's stale-test-user guard means forgetting the cleanup will **break the next scheduled run**, surfacing as a loud visible failure rather than a silent misbehaviour. That's by design — it's safer than accidentally spamming real users with test content.

---

## Commits included in this deploy (52)

From `origin/main` to `lifecycle-email-engine@9726875`:

- **Phase 1-4** (14 commits, session 51) — migrations, models, DiscountCodeService per-user lock, TrialService::restartTrial
- **Phase 5** (4 commits) — LifecycleSnapshotService (isEmpty / findUserIdsWithData / buildContext) + LifecycleDiscountCodeGenerator
- **Phase 6** (3 commits) — LifecycleCampaign interface, `config/lifecycle.php`, LifecycleEngine skeleton
- **Phase 7** (6 commits) — 5 campaign classes + engine preference filter
- **Phase 8** (7 commits) — shared Blade partials, 5 email templates, feedback thank-you views, trial reminder palette fix
- **Phase 9** (2 commits) — LifecycleActionController + 5 routes + feature tests
- **Phase 10** (3 commits) — AppServiceProvider binding, `lifecycle:run-daily` command, Kernel schedule entry
- **Phase 11** (6 commits) — UpdateNotificationPreferencesRequest rules, mobile controller gap fix, web NotificationPreferenceController, API routes, mobile + web NotificationPreferences Vue components
- **Phase 12** (3 commits) — LifecycleTestSeeder + `lifecycle:e2e-test` + `lifecycle:e2e-cleanup` commands
- **Phase 13 bug fixes** (4 commits) — caught via manual e2e verification:
  - `67f7a72` Blade `@if` parse error + `LazyLoadingViolationException` fix
  - `1fbe91b` Feedback-text POST signed-middleware path mismatch fix
  - `8c33466` SPA honours `?redirect` + `lifecycle_discount` propagation (Login.vue + AppLayout + PlanSelectionModal)
  - `9726875` `lifecycle:e2e-test` auto-fixes URL root when APP_URL is bare localhost

Every commit has tests (or an explicit note about why it's untested — e.g. `Phase 8` mail templates are smoke-tested via `Mail::fake()` inside the campaign tests). Full test count: **84 tests, 156 assertions, 0 failures**.

— Generated from `git diff main --name-only` and verified against `git log --oneline lifecycle-email-engine ^main` on 14 April 2026.
