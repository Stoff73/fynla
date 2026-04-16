# Awin Affiliate Integration — Deployment Runbook

Branch: `awinIntegrate` → `dev` → `main`
Merchant ID: `126105`
Last updated: 15 April 2026 (end of session 56)

---

## 1. What Gets Deployed

### 1.1 Phases 1–3 changed files

Generated from `git diff main...awinIntegrate` on 15 April 2026. Always regenerate before a real deploy:

```bash
git diff --name-only main...awinIntegrate
```

**Backend — new files:**
```
config/awin.php
app/Http/Middleware/CaptureAwcCookie.php
app/Services/Marketing/AwinTrackingService.php
app/Jobs/FireAwinConversionJob.php
database/migrations/2026_04_15_153100_add_awin_tracking_to_payments_table.php
```

**Backend — modified files:**
```
app/Http/Kernel.php                               (register CaptureAwcCookie)
app/Http/Middleware/EncryptCookies.php            (add 'awc' to $except)
app/Http/Middleware/SecurityHeaders.php           (conditional CSP for Awin domains)
app/Models/Payment.php                            (4 new awin_* fillables + cast)
app/Http/Controllers/Api/PaymentController.php    (createOrder capture + confirm dispatch + response payload)
app/Http/Controllers/Api/WebhookController.php    (dispatch from handleOrderCompleted)
```

**Frontend — new files:**
```
resources/js/utils/awinTracking.js
```

**Frontend — modified files:**
```
resources/js/utils/cookieConsent.js               (accept/decline/init hooks)
resources/js/router/index.js                      (afterEach load/unload)
resources/js/views/Auth/CheckoutPage.vue          (fireAwinConversion after GA4)
```

**Config:**
```
deploy/fynla-org/.env.production                  (AWIN_* + VITE_AWIN_*)
deploy/csjones-fynla/.env.production              (AWIN_* + VITE_AWIN_*)
```

**Tests (for reference — not deployed):**
```
tests/Unit/Services/Marketing/AwinTrackingServiceTest.php
tests/Feature/Payment/AwinConversionFlowTest.php
tests/Feature/Payment/FireAwinConversionJobTest.php
```

### 1.2 Built assets

Run the build for the target environment locally:

```bash
./deploy/csjones-fynla/build.sh       # staging (csjones.co/fynla)
./deploy/fynla-org/build.sh           # production (fynla.org)
```

Each script produces `public/build/` which is the full Vite manifest + bundle. Upload the entire directory — do not cherry-pick.

---

## 2. Environment Variables to Set on the Target

### 2.1 csjones.co (staging)

```env
AWIN_ENABLED=false
AWIN_MERCHANT_ID=126105
AWIN_COOKIE_DOMAIN=csjones.co
AWIN_HTTP_TIMEOUT_SECONDS=3

VITE_AWIN_ENABLED=false
VITE_AWIN_MERCHANT_ID=126105
VITE_AWIN_MASTER_TAG_URL=https://www.dwin1.com/126105.js
VITE_AWIN_FALLBACK_PIXEL=https://www.awin1.com/sread.img
```

**To run an attribution test on staging:** flip both `AWIN_ENABLED=true` and `VITE_AWIN_ENABLED=true` for the test window, rebuild and re-upload `public/build/`, complete the test, then flip both back to `false` and rebuild again. Never leave staging with `true` long-term.

### 2.2 fynla.org (production)

```env
AWIN_ENABLED=true
AWIN_MERCHANT_ID=126105
AWIN_COOKIE_DOMAIN=fynla.org
AWIN_HTTP_TIMEOUT_SECONDS=3

VITE_AWIN_ENABLED=true
VITE_AWIN_MERCHANT_ID=126105
VITE_AWIN_MASTER_TAG_URL=https://www.dwin1.com/126105.js
VITE_AWIN_FALLBACK_PIXEL=https://www.awin1.com/sread.img
```

Both backend and frontend flags must stay in sync. Asymmetry causes attribution to fire on one path only, breaking the dual-track safety net.

---

## 3. Deployment Steps (csjones.co Staging)

Target: `https://csjones.co/fynla`
Server layout: sibling-dir + symlink pattern (Laravel app at `~/www/csjones.co/fynla-app/`, NOT `public_html/fynla`)

### 3.1 Pre-flight (local)

```bash
# Make sure you're on the right branch and up to date
git checkout awinIntegrate
git fetch origin
git status                                    # working tree should be clean

# Run the full Awin test suite one last time
./vendor/bin/pest tests/Unit/Services/Marketing/ \
                  tests/Feature/Payment/AwinConversionFlowTest.php \
                  tests/Feature/Payment/FireAwinConversionJobTest.php

# Build the csjones assets
./deploy/csjones-fynla/build.sh
```

Expected: 32 tests green, `public/build/` regenerated, no Vite errors.

### 3.2 Upload

Via SiteGround File Manager, upload to `~/www/csjones.co/fynla-app/`:

**Always upload:**
- `public/build/` (entire directory — replaces existing)
- `config/awin.php`
- `app/Http/Middleware/CaptureAwcCookie.php`
- `app/Http/Middleware/EncryptCookies.php`
- `app/Http/Middleware/SecurityHeaders.php`
- `app/Http/Kernel.php`
- `app/Services/Marketing/AwinTrackingService.php`
- `app/Jobs/FireAwinConversionJob.php`
- `app/Http/Controllers/Api/PaymentController.php`
- `app/Http/Controllers/Api/WebhookController.php`
- `app/Models/Payment.php`
- `database/migrations/2026_04_15_153100_add_awin_tracking_to_payments_table.php`

**Also upload if the values differ from what's already on the server:**
- `.env` (Awin vars — copy the block from `deploy/csjones-fynla/.env.production`)

### 3.3 Server-side commands

```bash
ssh -p 18765 -i ~/.ssh/fynlaDev u... @csjones.co      # replace with real user
cd ~/www/csjones.co/fynla-app

# Run the migration (nullable columns, safe on existing data)
php artisan migrate --force

# Clear all Laravel caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan optimize

# Sanity: verify the middleware registered and the migration applied
php artisan migrate:status | grep awin
php artisan route:list --path=payment | head -20
php artisan tinker --execute="echo config('awin.enabled') ? 'ENABLED' : 'disabled';"
```

### 3.4 Smoke test on csjones.co

1. Open `https://csjones.co/fynla/?awc=TEST-CLICK-REF-1` in an incognito window
2. Devtools → Application → Cookies → confirm `awc=TEST-CLICK-REF-1` with 365d expiry
3. Register a new test user, complete onboarding, select a paid plan
4. On `/auth/checkout`, complete a Revolut **sandbox** payment
5. Check the confirm response — should include an `awin` object with `order_ref`, `amount`, `customer_acquisition: "new"`, etc.
6. Check browser network tab for a GET to `www.awin1.com/sread.img`
7. On the server, check `storage/logs/laravel.log` for `[awin] s2s fired` with status 200
8. Tinker verify:
   ```bash
   php artisan tinker --execute="\$p = \App\Models\Payment::latest()->first(); echo json_encode(['id'=>\$p->id,'cks'=>\$p->awin_cks,'ref'=>\$p->awin_order_ref,'acq'=>\$p->awin_customer_acquisition,'fired'=>\$p->awin_fired_at?->toIso8601String()]);"
   ```
9. Wait up to 2h and confirm the sale appears in the Awin dashboard

---

## 4. Deployment Steps (fynla.org Production)

Gate: do NOT deploy to production until step 3.4 passes cleanly on staging with a real Awin publisher test link.

Same process as §3 but with:
- Build script: `./deploy/fynla-org/build.sh`
- Upload target: `~/www/fynla.org/public_html/`
- SSH: `ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org`

Additional post-deploy:
- Monitor `storage/logs/laravel.log` for the first 24 hours — grep for `[awin]`. Expected: only `[awin] s2s fired` entries, no errors
- Monitor Awin dashboard daily for the first week, cross-reference commission events against `payments` where `awin_fired_at IS NOT NULL`

---

## 5. Rollback

If anything goes wrong post-deploy, the kill switch is:

```bash
# On the server
cd ~/www/fynla.org/public_html    # or csjones equivalent
# Edit .env and set:
AWIN_ENABLED=false

php artisan config:clear
```

The backend immediately stops:
- Writing `awc` cookies
- Capturing `awin_cks` on new payments
- Dispatching `FireAwinConversionJob`
- Including the `awin` payload in the confirm response

The frontend MasterTag stays loaded until users refresh, but the Sale object will no longer be set on new purchases. For a full frontend kill, rebuild with `VITE_AWIN_ENABLED=false` and re-upload `public/build/`.

**Data rollback:** the four `payments.awin_*` columns are nullable and additive — they can stay in place indefinitely with no functional impact. Don't run the migration `down` unless you are also rolling back the code.

---

## 6. Troubleshooting

| Symptom | Likely Cause | Fix |
|---|---|---|
| CSP blocks the MasterTag | `SecurityHeaders` not updated or `AWIN_ENABLED=false` | Verify both on server; rebuild CSP via `config:clear` |
| `payments.awin_cks IS NULL` after a purchase | User arrived without `?awc=` OR `awc` cookie was lost between navigations | Check `Request::cookie('awc')` at `createOrder` time via a temporary `Log::info` |
| `awin_fired_at` never populates | Queue worker not running, or Awin returning non-2xx | Check `storage/logs/laravel.log` for `[awin] s2s non-2xx`; verify queue worker status |
| Browser pixel fires twice | `router.afterEach` hook running + direct call from `fireConversion` | Already handled — `loadMasterTag()` is idempotent |
| Conversion missing in Awin dashboard | Both pixel and S2S blocked, or merchant ID wrong | Double-check `AWIN_MERCHANT_ID=126105` in both env blocks |
| Sandbox payment on staging not firing | `AWIN_ENABLED=false` on staging (default) | Flip to `true` for the test window then back |

---

## 7. References

- Plan: `April/April15Updates/awinIntegrate.md`
- Vault doc: `fynlaBrain/Current State/AwinIntegration.md`
- Awin merchant dashboard: `https://ui.awin.com/merchant/126105`
- Integration snippets from Awin: `awin/integration.md`
