# Production Deploy Notice — Awin + PR #210 + PR #211 + CSP Fix

**Target:** `fynla.org` (production)
**Branch:** `awinPlusDev` (merge of `awinIntegrate` + `origin/dev`)
**Date prepared:** 15 April 2026 (session 56)
**Prepared by:** session 56 Claude

> **⚠️ Risk note:** The original plan had PRs #210/#211 going through csjones.co dev validation first. That stage is being skipped per owner decision (15 April). All changes have been tested locally — Awin suite 42/42 green, full suite 2157/2160 green (3 failures are a pre-existing WillBuilder test ordering flake on main, not introduced by this merge).

---

## 1. What this ship delivers

### 1.1 Awin Affiliate Integration (phases 1–3, new)

Full dual-track attribution — browser pixel + server-to-server — for Awin merchant 126105. Consent-gated MasterTag, cookie capture middleware, idempotent conversion job, CheckoutPage browser pixel, DB audit trail on `payments`.

See `fynlaBrain/Current State/AwinIntegration.md` for the full system map.

### 1.2 PR #210 — Insight articles (Icecube-acc)

Two new public insight pages:
- `/insights/stocks-shares-isa-uk` (Stocks & Shares ISA Guide)
- `/insights/how-much-to-retire-uk` (How Much Do I Need to Retire)

Plus sitemap updates and `InsightsHubPage` additions.

### 1.3 PR #211 — Email redesigns + marketing polish (Phailanx)

- **10 email templates redesigned** to match the Fynla brand: data-deletion-confirmation, data-retention-warning, deletion-verification-code, payment-confirmation, spouse-account-created, spouse-account-linked, subscription-cancellation, subscription-renewal-reminder, trial-expiration-reminder, verification-code
- **Review carousel** on `LandingPage.vue` (new `ReviewCarousel.vue` component)
- **Meta Pixel tracking** — PageView in `app.blade.php`, Subscribe event in `CheckoutPage.vue`
- **Persona selection modal** mobile optimisation
- **`email:test` artisan command** (`app/Console/Commands/SendTestEmails.php`) for manual template previews
- Email mockups under `public/mockups/`

### 1.4 Meta Pixel CSP fix (new on this branch)

PR #211 added the Meta Pixel JavaScript but did not update `SecurityHeaders.php`. Without this fix, production would ship with `connect.facebook.net` CSP-blocked and Meta Pixel events would silently fail. Commit `9d141ed` whitelists `connect.facebook.net` + `www.facebook.com` in `script-src`, `img-src`, and `connect-src`.

### 1.5 Two-environment workflow infrastructure

- `.github/CODEOWNERS` (new) — guards the `dev` branch
- `deploy/csjones-fynla/BOOTSTRAP.md` (new) — dev server setup guide
- `deploy/csjones-fynla/.htaccess` — removed `DirectoryMatch` (shared-hosting incompatible)
- `resources/js/services/api.js` — respects `VITE_ROUTER_BASE` for subdirectory deployments
- `public/sitemap.xml` — minor updates
- `.gitignore` updates

These are csjones.co-specific and generally don't affect fynla.org, but they ship in the bundle.

---

## 2. Pre-flight (local)

```bash
cd /Users/CSJ/Desktop/fynla
git checkout awinPlusDev
git status                                    # should be clean

# Seed the DB (per standing rule)
php artisan db:seed

# Run the Awin test suite
./vendor/bin/pest tests/Unit/Services/Marketing/ \
                  tests/Feature/Payment/AwinConversionFlowTest.php \
                  tests/Feature/Payment/FireAwinConversionJobTest.php \
                  tests/Feature/Payment/UpgradeSubscriptionTest.php \
                  tests/Feature/PaymentWebhookRaceTest.php
# Expected: 42 passed, 0 failed

# Build the production assets (VITE_AWIN_ENABLED=true is baked into the script)
./deploy/fynla-org/build.sh
# Expected: public/build/ regenerated, "VITE_AWIN_ENABLED: true" shown
```

---

## 3. Files to upload via SiteGround File Manager

Target: `~/www/fynla.org/public_html/`

### 3.1 Frontend bundle (always upload in full)

Upload the entire `public/build/` directory. **Replace** the existing one — don't cherry-pick files; the Vite manifest hashes cross-reference and partial uploads blank the app.

### 3.2 Backend — new files (10)

**Awin:**
```
app/Services/Marketing/AwinTrackingService.php
app/Jobs/FireAwinConversionJob.php
app/Http/Middleware/CaptureAwcCookie.php
config/awin.php
database/migrations/2026_04_15_153100_add_awin_tracking_to_payments_table.php
```

**PR #211:**
```
app/Console/Commands/SendTestEmails.php
```

**Public assets:**
```
public/mockups/email-designs.html
public/mockups/review-carousel.html
```

**Public articles (markdown, optional — for the vault):**
```
Articles/Fynla_How_Much_To_Retire_UK.md
Articles/stocks-shares-isa-uk.md
```

### 3.3 Backend — modified files (7)

```
app/Http/Kernel.php                              (Awin — register CaptureAwcCookie)
app/Http/Middleware/EncryptCookies.php           (Awin — 'awc' in $except)
app/Http/Middleware/SecurityHeaders.php          (Awin + Meta Pixel CSP)
app/Http/Controllers/Api/PaymentController.php   (Awin — createOrder capture + confirm dispatch + response payload)
app/Http/Controllers/Api/WebhookController.php   (Awin — dispatch from handleOrderCompleted)
app/Models/Payment.php                           (Awin — 4 new fillables + cast)
public/sitemap.xml                               (PR #210 — new insight URLs)
```

### 3.4 Email templates — modified (10)

```
resources/views/emails/data-deletion-confirmation.blade.php
resources/views/emails/data-retention-warning.blade.php
resources/views/emails/deletion-verification-code.blade.php
resources/views/emails/payment-confirmation.blade.php
resources/views/emails/spouse-account-created.blade.php
resources/views/emails/spouse-account-linked.blade.php
resources/views/emails/subscription-cancellation.blade.php
resources/views/emails/subscription-renewal-reminder.blade.php
resources/views/emails/trial-expiration-reminder.blade.php
resources/views/emails/verification-code.blade.php
```

### 3.5 Blade views — modified

```
resources/views/app.blade.php                    (PR #211 — Meta Pixel loader in <head>)
```

### 3.6 DO NOT upload

- `deploy/fynla-org/.env.production` — this is a template. The live server `.env` needs manual SSH edit (see §4.1)
- `deploy/csjones-fynla/*` — staging-only
- `deploy/fynla-org/build.sh` — local-only, never runs on server
- `deploy/awin/README.md` — vault/repo doc, not runtime
- `deploy/csjones-fynla/BOOTSTRAP.md` — dev-server doc
- `.github/CODEOWNERS` — GitHub config, not server runtime
- `.gitignore`, `CLAUDE.md` — repo metadata
- Test files under `tests/` — not required in production
- The `awin/` directory (onboarding PNG images + integration.md) — local only

### 3.7 Vue components — uploaded as part of `public/build/`

These are **compiled into the bundle** — do NOT upload the `.vue` source files to the server. Listed here only so you know what changed inside the build:

```
resources/js/utils/awinTracking.js                          (NEW, Awin)
resources/js/utils/cookieConsent.js                         (Awin + PR #211 integration)
resources/js/router/index.js                                (Awin afterEach + PR #210 routes)
resources/js/views/Auth/CheckoutPage.vue                    (Awin + Meta Pixel Subscribe)
resources/js/views/Public/LandingPage.vue                   (PR #211 review carousel)
resources/js/views/Public/insights/InsightsHubPage.vue      (PR #210 links)
resources/js/views/Public/insights/StocksSharesIsaUkPage.vue (NEW, PR #210)
resources/js/views/Public/insights/HowMuchToRetireUkPage.vue (NEW, PR #210)
resources/js/components/Public/ReviewCarousel.vue           (NEW, PR #211)
resources/js/components/Preview/PersonaSelectionModal.vue   (PR #211 mobile fix)
resources/js/components/Onboarding/OnboardingWizard.vue     (PR #211 minor tweaks)
resources/js/views/Dashboard.vue                            (PR #211 minor)
resources/js/views/Register.vue                             (PR #211 minor)
resources/js/services/api.js                                (two-env workflow — subdir support)
```

---

## 4. Server-side steps (SSH)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
```

### 4.1 Add Awin env vars to live `.env`

Append to `.env`:

```env
# Awin Affiliate Tracking (added 15 April 2026)
AWIN_ENABLED=true
AWIN_MERCHANT_ID=126105
AWIN_COOKIE_DOMAIN=fynla.org
AWIN_HTTP_TIMEOUT_SECONDS=3
```

The frontend `VITE_AWIN_*` vars are baked into `public/build/` at build time — they do NOT need to be set on the server.

### 4.2 Run the migration

```bash
php artisan migrate --force
```

Expected output:
```
2026_04_15_153100_add_awin_tracking_to_payments_table ..... RUNNING
2026_04_15_153100_add_awin_tracking_to_payments_table ..... DONE
```

The migration is additive and backfill-safe — all four new `payments.awin_*` columns are nullable.

### 4.3 Clear Laravel caches

```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan optimize
```

### 4.4 Sanity checks

```bash
# Awin config loaded?
php artisan tinker --execute="echo config('awin.enabled') ? 'ENABLED ✓' : 'disabled';"
# Expect: ENABLED ✓

# Migration applied?
php artisan migrate:status 2>&1 | grep awin
# Expect: 2026_04_15_153100_add_awin_tracking_to_payments_table  Ran

# Payment columns present?
php artisan tinker --execute="echo implode(',', array_intersect(\Schema::getColumnListing('payments'), ['awin_order_ref','awin_cks','awin_customer_acquisition','awin_fired_at']));"
# Expect: awin_order_ref,awin_cks,awin_customer_acquisition,awin_fired_at

# Insight routes registered?
php artisan route:list 2>&1 | grep insights
# Expect: stocks-shares-isa-uk and how-much-to-retire-uk listed (plus others)

# Queue worker running? (FireAwinConversionJob won't fire otherwise)
php artisan queue:work --once --stop-when-empty
# Expect: no errors, exits cleanly
```

---

## 5. Post-deploy smoke tests (within 15 min)

### 5.1 Homepage + review carousel

Open `https://fynla.org/` in incognito. Verify:
- Page renders
- Review carousel visible on the landing page
- **Browser console: ZERO CSP errors** (regression signal — if `connect.facebook.net` errors reappear, the CSP fix didn't land)

### 5.2 New insight pages

- `https://fynla.org/insights/stocks-shares-isa-uk` — renders
- `https://fynla.org/insights/how-much-to-retire-uk` — renders
- `https://fynla.org/insights` — hub page shows the 2 new cards

### 5.3 Meta Pixel

Open devtools → Network tab, filter for `facebook`:
- `https://connect.facebook.net/en_US/fbevents.js` loads with 200
- `https://www.facebook.com/tr?id=1878962689749080&ev=PageView&...` fires (200 or 204)
- In console: `typeof window.fbq` should be `'function'`

### 5.4 Awin cookie capture

`https://fynla.org/?awc=DEPLOY-SMOKE-2026-04-15` — devtools → Application → Cookies:
- Name: `awc`
- Value: `DEPLOY-SMOKE-2026-04-15`
- Domain: `.fynla.org`
- Expiry: ~365 days
- HttpOnly + Secure + SameSite=Lax

### 5.5 Awin MasterTag on non-checkout routes

View page source on the homepage, search for `dwin1.com`:
- Should see `<script id="awin-master-tag" src="https://www.dwin1.com/126105.js" defer async>`

Navigate to `/auth/checkout` (the Revolut widget page) — the script element should be removed.

### 5.6 Email templates (manual visual check)

```bash
# Tail the log while you register a test user and let it send a verification code
tail -f storage/logs/laravel.log | grep -i "mail\|email"
```

Or use the new artisan command to preview templates to yourself:
```bash
php artisan email:test --recipient=chris@fynla.org
```
(Check the exact signature — `SendTestEmails.php` is new. Run `php artisan list | grep email` if the `email:test` name differs.)

### 5.7 Log tail

```bash
tail -f storage/logs/laravel.log | grep -iE 'awin|error|exception'
```

Watch for any `[awin]` errors in the first few minutes.

---

## 6. First real conversion verification

When the first real purchase comes in post-deploy:

```bash
# Latest payment — full Awin snapshot
php artisan tinker --execute="\$p = \App\Models\Payment::latest()->first(); echo json_encode(['id'=>\$p->id,'status'=>\$p->status,'amount'=>\$p->amount,'cks'=>\$p->awin_cks,'ref'=>\$p->awin_order_ref,'acq'=>\$p->awin_customer_acquisition,'fired'=>\$p->awin_fired_at?->toIso8601String()], JSON_PRETTY_PRINT);"

# Any Awin log entries from the last hour
grep '\[awin\]' storage/logs/laravel.log | tail -20
```

Expected for an organic purchase (non-affiliate):
- `awin_cks` = null
- `awin_order_ref` = `FYN-PAY-{id}`
- `awin_customer_acquisition` = `new` or `existing`
- `awin_fired_at` = timestamp
- Log: `[awin] s2s fired` with status 200

Expected for an Awin-referred purchase:
- `awin_cks` = click reference
- `awin_fired_at` = timestamp
- Conversion visible in Awin dashboard within 2 hours

---

## 7. Kill switches

### 7.1 Awin kill switch

```bash
# On fynla.org
# Edit .env:
AWIN_ENABLED=false

php artisan config:clear
php artisan optimize
```

Effect: all backend Awin logic short-circuits. No new `awc` cookies, no job dispatches, no `awin` payload in confirm responses. Frontend MasterTag stays loaded until users refresh; for immediate frontend kill, rebuild with `VITE_AWIN_ENABLED=false` and re-upload `public/build/`.

### 7.2 Meta Pixel kill switch

Comment out the `<!-- Meta Pixel Code -->` block in `resources/views/app.blade.php`, upload, clear caches. Or rebuild with Meta Pixel disabled (requires a frontend flag — not currently wired; would need a new env var).

### 7.3 Full rollback

Upload the pre-deploy copy of each changed file (keep a backup locally before SSH'ing up). The migration columns can stay — they're nullable and additive, no data loss.

---

## 8. Known issues carried into this deploy

- **3 flaky WillBuilder tests** in `tests/Feature/Estate/WillBuilderApiTest.php` fail only when the full suite runs in a specific order. Pass individually (14/14). Pre-existing on main, not introduced by this merge. Non-blocker.

---

## 9. References

- Awin system map: `fynlaBrain/Current State/AwinIntegration.md`
- Awin runbook: `deploy/awin/README.md` (in the commit)
- Awin plan: `April/April15Updates/awinIntegrate.md`
- Merge branch: `awinPlusDev`
- Merge commit: `0a045f7`
- CSP fix commit: `9d141ed`
- Awin merchant dashboard: `https://ui.awin.com/merchant/126105`
