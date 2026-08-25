# Dev Server Update — Sync csjones.co/fynla with Production

*Date: 16 April 2026*
*Purpose: Bring the dev environment (csjones.co/fynla) in line with production (fynla.org)*

The `dev` branch is 17 commits behind `main` after today's merges (PRs #215, #216, #217). This guide syncs the dev server.

---

## Step 1 — Merge main back into dev

```bash
git checkout dev
git pull origin dev
git merge origin/main --no-ff -m "merge: main → dev — sync after PRs #215-#217"
git push origin dev
```

## Step 2 — Build for dev

```bash
./deploy/csjones-fynla/build.sh
```

This sets the correct `VITE_BASE_PATH=/fynla/build/`, `VITE_ROUTER_BASE=/fynla/`, `VITE_REVOLUT_SANDBOX=true`, and `VITE_AWIN_ENABLED=true`.

## Step 3 — Upload files

Upload to `~/www/csjones.co/fynla-app/` (NOT `public_html/fynla` — that's a symlink).

### Frontend build

Upload `public/build/` to `~/www/csjones.co/fynla-app/public/build/`

### Backend PHP files (13 files)

| Local Path | Upload To (under `~/www/csjones.co/fynla-app/`) |
|---|---|
| `app/Http/Controllers/Api/PaymentController.php` | `app/Http/Controllers/Api/` |
| `app/Http/Controllers/Api/WebhookController.php` | `app/Http/Controllers/Api/` |
| `app/Http/Kernel.php` | `app/Http/` |
| `app/Http/Middleware/CaptureAwcCookie.php` | `app/Http/Middleware/` (NEW) |
| `app/Http/Middleware/EncryptCookies.php` | `app/Http/Middleware/` |
| `app/Http/Middleware/SecurityHeaders.php` | `app/Http/Middleware/` |
| `app/Jobs/FireAwinConversionJob.php` | `app/Jobs/` (NEW) |
| `app/Mail/PaymentConfirmation.php` | `app/Mail/` |
| `app/Models/Payment.php` | `app/Models/` |
| `app/Services/LifeStage/LifeStageService.php` | `app/Services/LifeStage/` |
| `app/Services/Marketing/AwinTrackingService.php` | `app/Services/Marketing/` (NEW dir) |
| `config/awin.php` | `config/` (NEW) |
| `resources/views/emails/payment-confirmation.blade.php` | `resources/views/emails/` |

### Migration (1 file)

| Local Path | Upload To |
|---|---|
| `database/migrations/2026_04_15_153100_add_awin_tracking_to_payments_table.php` | `database/migrations/` (NEW) |

## Step 4 — SSH and finalise

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co
cd ~/www/csjones.co/fynla-app
php artisan migrate --force
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

## Step 5 — Update .env on dev server

Add these Awin variables to `~/www/csjones.co/fynla-app/.env` (if not already present):

```
AWIN_ENABLED=true
AWIN_MERCHANT_ID=126105
AWIN_COOKIE_DOMAIN=csjones.co
```

Note: `AWIN_COOKIE_DOMAIN` must be `csjones.co` on dev (not `fynla.org`).

## Step 6 — Smoke test

Visit `https://csjones.co/fynla` and verify:
- Landing page loads with insights section
- Insights hub page renders (`/fynla/insights`)
- Awin MasterTag present in DOM (`#awin-master-tag`)
- Login and dashboard work
- No console errors

---

## What's being synced

| Feature | PRs | Key Changes |
|---|---|---|
| Awin integration | #216 | Cookie capture, S2S conversion, MasterTag, 4 new payment columns |
| Awin tracking fix | #216 | `voucher` property name, defer-only, body placement |
| Email consolidation | #216 | Single payment email with invoice PDF, conditional affiliate ref |
| Insights hub | #215 | Editorial layout, latest insights on landing page |
| Meta Pixel CSP | (bundled) | `connect.facebook.net` + `facebook.com` in CSP |
| LifeStageService fix | (bundled) | `current_value` → `current_fund_value` typo |

---

## Rollback

If issues arise on dev, the dev server can be restored by reverting the merge:

```bash
git checkout dev
git revert HEAD --no-edit
git push origin dev
```

Then re-upload the previous `public/build/` and PHP files.
