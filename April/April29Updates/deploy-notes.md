# Deploy Notes — Session 2026-04-29

**Branch:** `feature/fyn-persona-split` → `dev` (then `main`)
**Session commits:** 7
**Files changed (excluding tests):** 18

---

## Pre-deploy checklist

1. Open PR `feature/fyn-persona-split → dev`, merge after approval.
2. Pull on local: `git checkout dev && git pull`.
3. Build for the target environment (see below).
4. Upload changed files via SiteGround File Manager (manual upload — no zip).
5. SSH in and run the post-deploy commands (see below).
6. Smoke test the URLs listed.
7. Once dev is green, repeat for `main` → production.

---

## Files to upload

### PHP backend (8 files)

```text
app/Http/Controllers/Api/AiChatController.php
app/Http/Controllers/Api/AuthController.php
app/Http/Controllers/Api/Public/TaxAllowancesController.php   ← NEW
app/Http/Requests/RegisterRequest.php
app/Models/PendingRegistration.php
app/Services/Onboarding/OnboardingStateMachine.php
config/onboarding.php
routes/api.php
```

### Database (2 files)

```text
database/migrations/2026_04_29_000001_add_signup_source_to_users_and_pending_registrations.php   ← NEW
database/seeders/TaxConfigurationSeeder.php   ← marriage_allowance added
```

### Frontend (8 files — rebuild required)

Don't upload the `resources/js/` files directly. Run the build script for the target environment, then upload the entire `public/build/` directory.

```text
resources/js/app.js
resources/js/router/index.js
resources/js/services/aiChatService.js
resources/js/store/modules/aiChat.js
resources/js/utils/sourceCapture.js                       ← NEW
resources/js/views/Dashboard.vue
resources/js/views/Public/SaveTaxCampaignPage.vue
resources/js/views/Register.vue
```

---

## Build (do this locally — server lacks RAM for npm)

| Target | Command | Output |
| --- | --- | --- |
| dev (csjones.co/fynla) | `./deploy/csjones-fynla/build.sh` | `public/build/` with `VITE_BASE_PATH=/fynla/build/` |
| production (fynla.org) | `./deploy/fynla-org/build.sh` | `public/build/` with `VITE_BASE_PATH=/build/` |

Never cross the streams. Building with `csjones-fynla/build.sh` and uploading to `fynla.org` will produce a blank-page 404 loop because the Vue router base path will be wrong.

---

## Post-deploy SSH commands

### Dev (csjones.co)

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/public_html/fynla
php artisan migrate --force
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

### Production (fynla.org)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate --force
php artisan db:seed --class=TaxConfigurationSeeder --force
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

The `db:seed --class=TaxConfigurationSeeder --force` is **mandatory** — without it, the new `marriage_allowance` row will be missing and the `/savetax` page will display £0 for that allowance.

The migration adds the `signup_source` column to both `users` and `pending_registrations` tables — both are nullable so it's a safe additive change.

---

## Smoke tests after deploy

1. **Landing page renders with live values:**
   - dev: <https://csjones.co/fynla/savetax>
   - prod: <https://fynla.org/savetax>
   - Check: tax year heading shows `2026/27`, all 4 income allowances + 4 investment allowances render with non-zero values, "Capital Gains Tax Allowance" reads £3,000.

2. **Public API responds:**
   - dev: <https://csjones.co/fynla/api/public/tax-allowances>
   - prod: <https://fynla.org/api/public/tax-allowances>
   - Check: HTTP 200, JSON has `tax_year: "2026/27"` and `marriage_allowance.amount: 1260`.

3. **Channel attribution captures:**
   - In an incognito window, navigate to `https://fynla.org/savetax?utm_source=linkedin`.
   - DevTools Console: `sessionStorage.getItem('fynla.signup_source')` → should log `"linkedin"`.

4. **Full registration end-to-end** (production smoke only — use a throwaway email):
   - Open `https://fynla.org/savetax?utm_source=facebook`.
   - Click "Start your free 7-day trial" → register → MFA → land on dashboard.
   - Verify Fyn opens with the campaign welcome ("welcome to Fynla — I'll help you build your tax-saving strategy").
   - SSH in and run `select id, email, signup_source, onboarding_fyn_path, onboarding_fyn_selection from users where email = '<your test email>';`
   - Expected: `signup_source=facebook`, `onboarding_fyn_path=campaign`, `onboarding_fyn_selection=savetax`.

5. **Watch the log** for the first 10–15 minutes:

   ```bash
   tail -f storage/logs/laravel.log
   ```

---

## Rollback

The migration is purely additive (nullable column, no data backfill). Rolling back means:

```bash
php artisan migrate:rollback --step=1
```

This reverses only the `signup_source` migration. Any rows already populated will be lost — but since this is a brand-new column, nothing else depends on it.

For application code, revert by checking out the previous SHA (`1672754`) and re-uploading the previous build. The frontend `?from=` wire-through gracefully degrades because `aiChatService.startOnboardingStream` still accepts the old `body: '{}'` shape.

---

## Notes for next deploy

- The **fallback constants** in `SaveTaxCampaignPage.vue:14-26` are hardcoded to current 2026/27 values. They are graceful-degradation fallbacks if the API fetch fails — the seeder is the source of truth in normal operation. Update the fallbacks manually whenever a new tax year is seeded so a momentary network blip during the rollover doesn't display last-year's values.
- Adding a new social platform requires editing the allowlist in **both** `resources/js/utils/sourceCapture.js` AND `app/Http/Requests/RegisterRequest.php`. The `SignupSourceCaptureTest` will fail until both lists match — that's intentional.

---

*Generated 2026-04-29 by session-end skill*
