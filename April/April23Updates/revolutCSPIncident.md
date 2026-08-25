---
type: incident-log
session: 65b
date: 2026-04-23
env: csjones.co/fynla (dev)
---

# Revolut Widget + CSP + Login Incident — 23 April 2026 (session 65b)

Post-session-end troubleshooting spree uncovered and fixed a cascade of issues on the dev server that surfaced when trying to exercise the newly-deployed Revolut checkout flow. Documented here so session 66 doesn't repeat the pattern.

## Timeline

1. **User reported Revolut widget blocked by CSP on /checkout**. Console showed:
   > Loading the script 'https://merchant.revolut.com/embed.js' violates the following Content Security Policy directive: "script-src 'self' 'unsafe-inline'"

2. **Diagnosed:** the dev server's `public/.htaccess` (uploaded Apr 14, byte-for-byte matching `deploy/csjones-fynla/.htaccess` in the repo) had a restrictive `Header set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; …"`. Apache's `Header set` runs after PHP-FPM, so it overwrote the `SecurityHeaders` middleware's richer CSP (which correctly allowlists Revolut, Google Fonts, GA, FB Pixel, Capacitor, AWIN).

3. **Discovered:** production (`fynla.org`) has been running **without** the three restrictive header lines (`Strict-Transport-Security`, `Content-Security-Policy`, `Permissions-Policy`) on its live `.htaccess` — someone hand-edited them off at some point, never reflected back to the repo template. That's why checkout has always worked on prod while this bomb sat on the dev template.

4. **Fix (committed):** removed `Strict-Transport-Security`, `Content-Security-Policy`, and `Permissions-Policy` `Header set` lines from **both** `deploy/csjones-fynla/.htaccess` and `deploy/fynla-org/.htaccess`. Replaced with an explanatory comment pointing at `app/Http/Middleware/SecurityHeaders.php`. Uploaded the new file to dev server, cleared Laravel caches.

## Cascade of secondary issues

### 5. `.env` parse error surfaced (pre-existing)

- After uploading the new `.htaccess` and running `php artisan config:clear`, Laravel started returning HTTP 500 with `"Failed to parse dotenv file. Encountered unexpected whitespace at [chris@fynla.org, brett@fynla.org, azlan@fynla.org]"`.
- Root cause: line 62 of the live `.env` has been `ADMIN_EMAILS=chris@fynla.org, brett@fynla.org, azlan@fynla.org` forever — unquoted comma-separated value with embedded whitespace is invalid dotenv syntax. `bootstrap/cache/config.php` had been masking this for weeks.
- **Fix:** added double-quotes around the value so it parses: `ADMIN_EMAILS="chris@fynla.org, brett@fynla.org, azlan@fynla.org"`. Backup at `.env.backup-2026-04-23-csp-fix`. Repo template `.env.example` was not touched (it's fine; this was a long-standing typo on the live .env only).

### 6. Revolut sandbox ↔ live environment mismatch

- After CSP fixed, widget loaded `merchant.revolut.com/embed.js` (live) but backend created orders via sandbox API (`REVOLUT_SANDBOX=true` on server). Sandbox tokens don't resolve on live — widget returned `"Failed to load, Order not found"` with 404 from `merchant-mgmt.revolut.com/orders/token/...`.
- CSJ toggled `REVOLUT_SANDBOX=false` on server thinking that'd fix it — it put us in the opposite broken state: live backend API with sandbox keys → 401 `Authentication failed` from Revolut.
- CSJ reverted to `REVOLUT_SANDBOX=true`. Backend is back on sandbox.
- Frontend build also needed to point at sandbox SDK. `deploy/csjones-fynla/build.sh` did not export `VITE_REVOLUT_SANDBOX`, so Vite inherited it from my local `.env` — producing a live-SDK build.
- **Fix (committed):** `deploy/csjones-fynla/build.sh` now exports `VITE_REVOLUT_SANDBOX=true` and `VITE_REVOLUT_PUBLIC_KEY=pk_D2JdE2srRipv0jdHerivLw1hMoWSrjqDa4lEozJxTwchuG04`. Future csjones builds bake in the sandbox SDK URL and the correct merchant pk regardless of who runs the build.

### 7. `VITE_REVOLUT_PUBLIC_KEY` mismatch

- Even with sandbox URLs aligned, widget got 403 on `sandbox-merchant.revolut.com/api/public/checkout-widget-appearance` and `.../available-payment-methods`.
- Cause: my local `.env` has `VITE_REVOLUT_PUBLIC_KEY=${REVOLUT_PUBLIC_KEY}` where local `REVOLUT_PUBLIC_KEY=pk_sY0uq1Q2…` (my own sandbox merchant). Vite's dotenv does `${VAR}` interpolation, so the build hardcoded my merchant's pk. That merchant has never seen the orders the server creates.
- **Fix:** exported the server's `pk_D2JdE2s…` at build time and rebuilt. New `CheckoutPage-CAePoYgl.js` chunk now has the correct pk. Now pinned into `build.sh` so it can never drift again.

### 8. Stale in-flight bundle broke login navigation

- Rebuilding replaced `public/build/` assets mid-session. User's open incognito tab still had the pre-rebuild main `app.js` in memory, which hard-references old dynamic-import chunks by content hash (`Dashboard-C0gqjDEC.js`, `AppLayout-kBPDibNr.js`, `currencyMixin-KLwdoPFF.js`, `EventIcon-r_zLrF2M.js`).
- Those old hashes no longer existed on disk, so Apache's SPA catch-all served `app.blade.php` (HTML) under those URLs. Browser rejected HTML-as-module → post-login navigation to Dashboard silently failed.
- **Temporary fix:** `cp -rn public/build.old/. public/build/` — merged old hashed assets alongside new ones (no-clobber preserves new manifest, new app.js, new chunks; restores missing old chunks for in-flight sessions). All routes except `/checkout` worked again in the user's existing session.
- **Checkout caveat:** old `CheckoutPage-Dq2ZEZzV.js` still in the user's memory has the wrong pk baked in. Can't be hotfixed server-side — user needs a fresh session to pick up the new `CheckoutPage-CAePoYgl.js`.

## What's now on the dev server

- `~/www/csjones.co/fynla-app/public/.htaccess` — updated, no CSP/HSTS/Permissions-Policy `Header set` lines (middleware does it)
- `~/www/csjones.co/fynla-app/.env` — `ADMIN_EMAILS` quoted, `REVOLUT_SANDBOX=true`, lifecycle env vars intact
- `~/www/csjones.co/fynla-app/public/build/` — merged state: new chunks with correct pk + sandbox SDK + SDK URL; old chunks preserved for in-flight session continuity
- `~/www/csjones.co/fynla-app/public/build.old/` and `.old2/` — backup of prior build generations (safe to remove after 24h when nobody is running a pre-rebuild session)
- Config cache rebuilt; runtime `config('services.revolut.sandbox') === true`

## Commits (all pushed to `origin/dev`)

- `f0770bb` — `fix(deploy): remove HSTS/CSP/Permissions-Policy from .htaccess templates`
- `921bb3d` — `chore(deploy): export VITE_REVOLUT_SANDBOX=true in csjones build`
- (this doc + pk pin) — session 65b cleanup commit

## Rules for next session

1. **Rebuilding the SPA mid-session invalidates active users' dynamic imports.** Before any rebuild during active browser testing, **warn CSJ** so they can either close sessions or accept the interruption.
2. **Preserve old `public/build/` when uploading a new one.** The `cp -rn old → new` pattern is now the default: `mv public/build public/build.old; tar -xf -; cp -rn public/build.old/. public/build/`. New sessions still get new manifest + new hashes; in-flight sessions can finish.
3. **Build scripts must export every VITE_REVOLUT_* var explicitly** when the build targets a specific server's merchant. Inheriting from the builder's local `.env` is a landmine — different builders have different Revolut test merchants.
4. **`.htaccess` and `SecurityHeaders` middleware must not both set the same security headers.** The middleware wins (richer, app-aware), `.htaccess` should only set what PHP can't (e.g. `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection`, `Referrer-Policy`, `X-Powered-By` unset).
5. **When `config:clear` surfaces a previously-hidden dotenv parse error, fix the syntax immediately** — leaving it means every future cache regeneration 500s the site until config is re-cached successfully. Quote any value containing whitespace or commas.

## Production implications

- `deploy/fynla-org/.htaccess` — repo template now matches what's actually on the live prod server. No prod-side change needed when this merges to `main`; prod's live `.htaccess` stays as-is.
- `deploy/fynla-org/build.sh` — unchanged; production keeps live Revolut settings. No VITE_REVOLUT_PUBLIC_KEY pinned here (prod merchant pk is in prod `.env`).
- **However:** before the next `dev → main` PR, confirm that prod's `.env` has `VITE_REVOLUT_PUBLIC_KEY` set to the live (not sandbox) pk, and that nobody ever runs `deploy/fynla-org/build.sh` from a local machine whose `.env` points at a sandbox merchant.
