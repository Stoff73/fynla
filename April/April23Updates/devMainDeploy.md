# Deploy Guide — dev → main release (fynla.org production)

**Status:** Build complete locally at 20:57 BST 23 April 2026. Ready to upload.
**Git state:** `main` tip `27bb188` (PR #228 merge) = `dev` tip `34b77a3` tree-identical.
**Target environment:** fynla.org (production, standard Laravel layout).
**Excludes:** PR #214 (`onboardingFyn`) and `feature/fyn-persona-split` — both unmerged to `dev`.

---

## What this ships (7 merged PRs + 1 release bundle)

| PR | Scope |
| --- | --- |
| **#212** | 5-campaign lifecycle email engine, magic-link routes, NotificationPreferences + 14 toggles, E2E test commands |
| **#220** | Session 63 tech-debt — decimal:2 cast remediation across 19 factories, form request conversions |
| **#221** | Campaign landing pages, ReviewCarousel, StaticFynChat on marketing pages, custom 404 |
| **#223** | `main → dev` back-merge (session 64 subscription hotfix) |
| **#224** | **composer-breaking:** `intervention/image` downgraded `^4.0 → ^3.0` for csjones dev (PHP 8.2) compat; InsightImageService ported to 3.11 API. Safe on prod PHP 8.3 — 3.11.7 runs on either PHP version. |
| **#225** | Pension Monte Carlo cache key content-addressed (fixes "added pension, projection still £0"), unified Add Pension form, SubNavBar hidden + inline CTAs, sticky top nav, `getAccountProjections` service restored |
| **#226** | Logout redirects straight to `/login`, dashboard progress hero for all users, pension/investment form collapse, joint net-worth two-donut layout + combined bar with per-user tooltip, root-cause spouse-name getter fix |
| **#227** | `/api/investment/analyze` 500 → 200 (decimal:2 cast to `(float)` at source in `CGTHarvestingCalculator`), Vue 2 `this._uid` → Vue 3 `this.$.uid` in `AssetAllocationDonut`, session 67 tech-debt (hex → designSystem, spouse-name dedup) |

**Totals:** 99 commits, 188 file operations (56 adds, 124 modifies, 3 deletes, 5 renames), +6,677 / −1,545 LOC.

---

## Built bundle

- **Main app chunk:** `public/build/assets/app-B31kpBbU.js` (1,195,754 bytes, built 20:57 BST)
- **Manifest:** `public/build/manifest.json` points `resources/js/app.js` → `assets/app-B31kpBbU.js`
- **Service worker:** `public/build/sw.js` regenerated (318 precache entries, 7,100 KiB)
- **Revolut:** bundle contains `merchant.revolut.com` (live, NOT `sandbox-merchant`) and a `pk_` that matches your local `.env`. If your local `.env` had a sandbox pk, **stop and rebuild** with the live pk sourced from prod `.env` — see pre-flight checks below.

---

## Scope boundaries

- **No new runtime npm deps.** `package-lock.json` modified but no new packages require `npm install` on the server — assets are built locally and uploaded.
- **`.htaccess` unchanged** — keep the existing prod `.htaccess` as-is.
- **No cron / queue infrastructure changes.** Lifecycle engine uses the existing `schedule:run` cron. Verify with `crontab -l` on the prod server; add `* * * * * cd ~/www/fynla.org/public_html && php artisan schedule:run >> /dev/null 2>&1` if missing.
- **No `.env` changes mandatory.** Optional: set `LIFECYCLE_ENGINE_ENABLED=false` if you want the new email engine off. Default is `true`.

---

## Pre-flight checks (do these before uploading)

### 1. Confirm the local build has the LIVE Revolut pk

The production build script does **not** pin `VITE_REVOLUT_*` — it inherits from your local `.env`. Session 65b baked a sandbox pk into a previous build; this must not repeat.

```bash
# confirm live merchant URL (not sandbox-merchant)
grep -c "sandbox-merchant\.revolut" public/build/assets/CheckoutPage-*.js
# should return 0

grep -c "merchant\.revolut" public/build/assets/CheckoutPage-*.js
# should return 1

# confirm a pk_ is present
grep -oE "pk_[A-Za-z0-9_-]{5,15}" public/build/assets/CheckoutPage-*.js
# compare first 15 chars against the LIVE pk in prod .env
```

If the prefix matches prod's live pk, proceed. If it matches the sandbox pk (e.g. `pk_D2JdE2srRipv0`), **stop**, swap your local `.env` to hold the live pk + `REVOLUT_SANDBOX=false`, rerun `./deploy/fynla-org/build.sh`, and re-verify.

### 2. Back up prod `public/build/`

Before replacing the build directory, preserve the current chunks so in-flight customer sessions survive the hash swap.

### 3. Verify prod PHP version + composer status

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org \
  "cd ~/www/fynla.org/public_html && php -v && composer show intervention/image"
```

Confirmed 23 April 2026: prod is on **PHP 8.3.30** (ZTS, OPcache). Either `intervention/image` 3.x or 4.x runs — the downgrade in PR #224 is for csjones dev (PHP 8.2), not prod. `composer install` after upload will move prod's installed version to whatever `composer.lock` pins (3.11.7).

---

## Files to upload

### Build directory (whole thing)

`public/build/` → `~/www/fynla.org/public_html/public/build/` via the preserve-old-chunks pattern below. Contains the new `app-B31kpBbU.js` plus every route chunk, the new manifest, the new service worker, and every static asset.

### Backend PHP — 52 files

**New (added):**

```text
app/Console/Commands/RunLifecycleEngine.php
app/Console/Commands/RunLifecycleEngineE2ECleanup.php
app/Console/Commands/RunLifecycleEngineE2ETest.php
app/Http/Controllers/Api/NotificationPreferenceController.php
app/Http/Controllers/Lifecycle/LifecycleActionController.php
app/Mail/Lifecycle/CancelledTrialerMail.php
app/Mail/Lifecycle/ChurnedSubscriberMail.php
app/Mail/Lifecycle/EmptyTrialerMail.php
app/Mail/Lifecycle/EngagedTrialerMail.php
app/Mail/Lifecycle/LapsedSubscriberMail.php
app/Models/FeedbackResponse.php
app/Models/LifecycleEmailLog.php
app/Services/Lifecycle/Campaigns/CancelledTrialerCampaign.php
app/Services/Lifecycle/Campaigns/ChurnedSubscriberCampaign.php
app/Services/Lifecycle/Campaigns/EmptyTrialerCampaign.php
app/Services/Lifecycle/Campaigns/EngagedTrialerCampaign.php
app/Services/Lifecycle/Campaigns/LapsedSubscriberCampaign.php
app/Services/Lifecycle/Contracts/LifecycleCampaign.php
app/Services/Lifecycle/LifecycleDiscountCodeGenerator.php
app/Services/Lifecycle/LifecycleEngine.php
app/Services/Lifecycle/LifecycleSnapshotService.php
```

**Modified:**

```text
app/Console/Kernel.php
app/Http/Controllers/Api/V1/Mobile/NotificationPreferenceController.php
app/Http/Requests/V1/UpdateNotificationPreferencesRequest.php
app/Models/DiscountCode.php
app/Models/Estate/Asset.php
app/Models/Estate/Gift.php
app/Models/Estate/IHTCalculation.php
app/Models/Estate/IHTProfile.php
app/Models/Estate/Liability.php
app/Models/ExpenditureProfile.php
app/Models/Investment/Holding.php
app/Models/Investment/InvestmentGoal.php
app/Models/Investment/RebalancingAction.php
app/Models/Investment/RiskProfile.php
app/Models/NotificationPreference.php
app/Models/ProtectionProfile.php
app/Models/RecommendationTracking.php
app/Models/User.php
app/Providers/AppServiceProvider.php
app/Services/Estate/IntestacyCalculator.php
app/Services/Estate/NetWorthAnalyzer.php
app/Services/Insights/InsightImageService.php           ← uses intervention/image 3.x API
app/Services/Investment/FeeAnalyzer.php
app/Services/Investment/ScenarioService.php
app/Services/Investment/Tax/CGTHarvestingCalculator.php ← (float) cast fix
app/Services/Investment/TaxEfficiencyCalculator.php
app/Services/Onboarding/OnboardingService.php
app/Services/Payment/DiscountCodeService.php
app/Services/Payment/TrialService.php
app/Services/Protection/ComprehensiveProtectionPlanService.php
app/Services/Retirement/RetirementProjectionService.php ← content-addressed MC cache key
```

Generate the authoritative list on the fly before uploading:

```bash
git diff --name-only origin/main~1..origin/main -- 'app/'
```

### Database — 27 files

**Migrations — 26 files.** Safe to upload all; Laravel's migrate tracker only runs ones not in `migrations` table. All 26 paths:

```bash
git diff --name-only origin/main~1..origin/main -- 'database/migrations/'
```

Critical new-April migrations (will run on prod):

- `2026_04_14_122231_create_lifecycle_email_log_table.php`
- `2026_04_14_122345_create_feedback_responses_table.php`
- `2026_04_14_122424_add_user_id_and_metadata_to_discount_codes.php`
- `2026_04_14_122508_add_is_lifecycle_test_user_to_users.php`
- `2026_04_14_122545_add_lifecycle_columns_to_notification_preferences.php`
- `2026_04_14_122656_add_subscriptions_indexes.php`
- `2026_04_14_123409_add_lifecycle_welcome_to_discount_codes_type_enum.php`

Plus 19 earlier migrations that already exist on prod — uploading them is idempotent (their `migrations` row is already present; Laravel skips them).

**Seeder — 1 file.** Upload it; it is only used by `php artisan lifecycle:e2e-test` (optional):

- `database/seeders/LifecycleTestSeeder.php`

### Config — 2 files

- `config/lifecycle.php` (new)
- `composer.json` + `composer.lock` (intervention/image ^3.0 pin)

### Routes — 2 files

- `routes/api.php` (lifecycle magic-link routes, notification-preference endpoints)
- `routes/web.php` (lifecycle feedback-thanks pages)

### Blade views — ~10 files

Lifecycle email templates + feedback pages:

```text
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
```

### Factories (test-only, safe to upload or skip)

19 factories modified for the decimal:2 remediation in PR #220. Prod never runs factories, so these are optional. Skipping them introduces drift between repo and server but causes no runtime effect. Recommend uploading for consistency.

### Deletes — 3 files

After upload, remove these on the server (they're unused):

```bash
~/www/fynla.org/public_html/resources/js/components/Auth/LogoutSuccessModal.vue
~/www/fynla.org/public_html/resources/js/components/Investment/Goals.vue
~/www/fynla.org/public_html/resources/js/components/UserProfile/Settings.vue
```

Vue source files — `public/build/` doesn't import them anymore. Removing just keeps the source tree tidy.

### Renames — 5 files

Moved source paths (build output is equivalent):

| From | To |
| --- | --- |
| `resources/js/components/Footer.vue` | `resources/js/components/AppFooter.vue` |
| `resources/js/components/Navbar.vue` | `resources/js/components/AppNavbar.vue` |
| `resources/js/components/Investment/Holdings.vue` | `resources/js/components/Investment/InvestmentHoldings.vue` |
| `resources/js/components/Investment/Performance.vue` | `resources/js/components/Investment/InvestmentPerformance.vue` |
| `resources/js/components/Savings/Recommendations.vue` | `resources/js/components/Savings/SavingsRecommendations.vue` |

After upload, optionally delete the old paths on the server. Not functionally required.

### Exclusions (do NOT upload)

- `April/` — local scratch directory, `/April/` in `.gitignore`
- `CSJTODO.md` — local working doc
- `.claude/` — local skill config
- `MEMORY.md` — local memory file
- `tests/` — never deployed
- `resources/js/**/*.vue` and `resources/js/**/*.js` source files — **built into `public/build/`, do not upload source**

---

## Upload

### 1. Back up the current prod build (preserve in-flight sessions)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
rm -rf public/build.old
mv public/build public/build.old
mkdir public/build
exit
```

### 2. Tar-pipe the new build from local machine

```bash
# --- from /Users/CSJ/Desktop/fynla on your local machine ---
tar -cf - -C public/build . | ssh -p 18765 -i ~/.ssh/production \
  u2783-hrf1k8bpfg02@ssh.fynla.org \
  "cd ~/www/fynla.org/public_html/public/build && tar -xf -"
```

### 3. Merge old chunks alongside new ones (preserve-old-chunks)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org \
  "cd ~/www/fynla.org/public_html && cp -rn public/build.old/. public/build/"
```

`-n` (no-clobber) keeps the new `manifest.json`, new `app-B31kpBbU.js`, and new chunks untouched. Missing old chunks get filled in from `build.old/`. New sessions pick up the new manifest; in-flight sessions keep resolving the old hashes.

### 4. Upload backend files

Via SiteGround File Manager (per the "Manual File Upload Only" rule) OR rsync. The rsync one-liner:

```bash
# --- from /Users/CSJ/Desktop/fynla on your local machine ---
rsync -avz --files-from=<(git diff --name-only origin/main~1..origin/main -- \
  'app/' 'config/' 'database/' 'routes/' 'resources/views/' \
  'composer.json' 'composer.lock') \
  -e "ssh -p 18765 -i ~/.ssh/production" \
  ./ u2783-hrf1k8bpfg02@ssh.fynla.org:~/www/fynla.org/public_html/
```

Verify before running by printing the file list:

```bash
git diff --name-only origin/main~1..origin/main -- 'app/' 'config/' 'database/' 'routes/' 'resources/views/' 'composer.json' 'composer.lock'
```

---

## SSH finalisation

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html

# 1. composer install — syncs intervention/image to 3.11.7 per composer.lock
composer install --no-dev --optimize-autoloader

# 2. verify the installed version
composer show intervention/image | grep versions
# expected: versions : * 3.11.7

# 3. run migrations
php artisan migrate --force

# 4. verify migrations all ran
php artisan migrate:status | grep -i pending
# expected: no output

# 5. clear every cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan optimize

# 6. (optional) verify lifecycle cron is scheduled
crontab -l | grep -c "schedule:run"
# expected: 1

# 7. (optional) sanity-check scheduled commands are registered
php artisan schedule:list | grep lifecycle
```

**`cache:clear` is load-bearing.** `InvestmentAgent->analyze()` wraps its result in `remember('investment_analysis_{userId}', 86400, …)`. Any customer with a cached pre-fix `analyze()` result will keep hitting the old payload (possibly a cached 500) for up to 24h without this step.

---

## Smoke tests (run in an incognito window, in order)

### A. Homepage + auth

1. `https://fynla.org` loads, landing page renders, hero CTA visible.
2. Navigate to `/login`, log in as `chris@fynla.org` / `Password1!`. You will be asked for an email verification code — **ask me for the code** (this is prod, cannot self-fetch).
3. Dashboard loads, no 500s in Network tab, no console errors.

### B. Investment analyze (PR #227 verification)

1. Navigate to the Investment module.
2. Open DevTools → Network → XHR.
3. `GET /api/investment/analyze` must return **200**. Response `data.tax_efficiency.harvesting_opportunities[]` items must have numeric `cost_basis` / `current_value` (not quoted strings).

### C. Net Worth (`_uid` + joint donut + bar tooltip)

1. Navigate to Net Worth.
2. Console must be clean — **no `Property "_uid" was accessed during render` warnings**.
3. If logged-in user has a spouse: both donuts render inline with real names in titles ("David Mitchell's Asset Allocation", "Sarah Mitchell's Asset Allocation"). Hover any bar in the combined Asset Breakdown chart — tooltip shows per-user split with correct palette colours (raspberry = liability, horizon = asset, violet dot = user, spring dot = spouse).

### D. Pension projection (PR #225)

1. Go to Retirement.
2. If the test account has at least one DC pension, projection chart shows non-zero percentile bands at retirement age.
3. Use the unified Add Pension flow — form offers DC / SIPP / Personal / Stakeholder / Final Salary / State Pension in one dropdown.

### E. Checkout with live Revolut

1. Log out, register a new test account.
2. Start a Pro plan upgrade.
3. Revolut widget loads (no CSP violation in console, no 403 on `embed.js`).
4. Use a real card with minimum value for the live flow OR cancel without paying — just confirm the widget rendered.
5. If you completed a payment: verify invoice PDF downloads, invoice email received, payment record in DB has `status=completed`.

### F. Lifecycle engine dry-run

1. SSH into prod.
2. `php artisan lifecycle:run-daily` — should run all 5 campaigns and report 0 eligible users (first run, nothing qualifies yet).
3. No errors in `storage/logs/laravel.log` during the run.

### G. Admin insights image upload (intervention/image 3.11.7 validation)

1. Log in as admin.
2. Navigate to Admin → Insights.
3. Upload a hero image to any article. Must succeed — if it throws on `ImageManager::gd()` or `->read()`, the downgrade didn't take (re-run `composer install`).

---

## Rollback

### Frontend (instant)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
rm -rf public/build
mv public/build.old public/build
```

Customers keep seeing pre-release bundle. Old backend + new frontend is unsafe — if you do this, also roll back backend.

### Backend (one PHP file per rollback target)

For any individual PHP file, SSH in and replace from the prior main tip:

```bash
git show bd9042e:app/Services/Investment/Tax/CGTHarvestingCalculator.php \
  > /tmp/rollback.php
# upload /tmp/rollback.php to the server
```

For a full rollback to the prior main tip `bd9042e`:

1. `git checkout bd9042e` locally.
2. Re-run `./deploy/fynla-org/build.sh`.
3. Repeat the full upload.
4. On the server: `composer install --no-dev --optimize-autoloader` (restores intervention/image ^4.0 — safe on prod PHP 8.3).

**Practical rollback plan:** frontend-only rollback (restore `build.old`) is always safe and instant — use it first if symptoms are UI-only. Full backend rollback is viable on prod PHP 8.3 but involves 188 files via File Manager or rsync; prefer forward-fix for anything that isn't an outright regression.

### Database

Migrations in this release are all additive (new tables, new columns, new enum values). No destructive changes, no column drops, no data transformations. No migration rollback needed unless a specific down() is called manually. All migrations are tested on csjones.co dev.

---

## Post-deploy

### Monitor

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
tail -f storage/logs/laravel.log
```

Watch for 15 minutes. Red flags: any 500 on `/api/investment/analyze`, `intervention/image` class-not-found, queue worker errors on `Mail\Lifecycle\*`, migration errors on `discount_codes_type` enum expansion.

### Cleanup after ~24h

Once you're confident no customer is still on a pre-rebuild session:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
rm -rf public/build.old
```

Frees ~12 MB.

### Update CSJTODO

Tick the "Cut `dev → main` PR when ready" line off session 65 / 66 / 67's outstanding list. Add a session 68 section noting the production cut timestamp and this release's merge commit `27bb188`.

---

## One-shot command summary

```bash
# --- local ---
# 1. Verify build has live Revolut pk
grep -c "sandbox-merchant\.revolut" public/build/assets/CheckoutPage-*.js  # expect 0

# 2. Back up prod build (separate SSH)
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org \
  "cd ~/www/fynla.org/public_html && rm -rf public/build.old && mv public/build public/build.old && mkdir public/build"

# 3. Tar-pipe new build
tar -cf - -C public/build . | ssh -p 18765 -i ~/.ssh/production \
  u2783-hrf1k8bpfg02@ssh.fynla.org \
  "cd ~/www/fynla.org/public_html/public/build && tar -xf -"

# 4. Merge old chunks
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org \
  "cd ~/www/fynla.org/public_html && cp -rn public/build.old/. public/build/"

# 5. rsync backend files
rsync -avz --files-from=<(git diff --name-only origin/main~1..origin/main -- \
  'app/' 'config/' 'database/' 'routes/' 'resources/views/' \
  'composer.json' 'composer.lock') \
  -e "ssh -p 18765 -i ~/.ssh/production" \
  ./ u2783-hrf1k8bpfg02@ssh.fynla.org:~/www/fynla.org/public_html/

# 6. Finalise on server
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org \
  "cd ~/www/fynla.org/public_html && \
   composer install --no-dev --optimize-autoloader && \
   php artisan migrate --force && \
   php artisan cache:clear && php artisan config:clear && \
   php artisan view:clear && php artisan route:clear && php artisan optimize"
```

Then run smoke tests A–G.
