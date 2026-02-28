# DEPLOYED: Authentication Security Fixes

**Date:** 20 February 2026
**Branch:** `auth`
**PR:** https://github.com/Stoff73/fynla/pull/77
**Status:** Built and ready for upload
**Build:** Production build completed (`./deploy/fynla-org/build.sh`)

---

## Rebuild Required?

**No frontend rebuild required.** All changes are backend-only (PHP, config, migration). No Vue/JS files were modified.

---

## Pre-Upload: Run Migration on Server

SSH into the server and run the pending migration before uploading files:

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan migrate
```

This adds the `expires_at` column to `pending_registrations` and backfills existing records.

---

## Files to Upload

Upload via SiteGround File Manager to `~/www/fynla.org/public_html/`

### New Files (4)

| Local Path | Upload To |
|------------|-----------|
| `database/migrations/2026_02_20_000001_add_expires_at_to_pending_registrations_table.php` | `database/migrations/` |
| `app/Console/Commands/CleanupPendingRegistrations.php` | `app/Console/Commands/` |
| `app/Console/Commands/CleanupOrphanedSessions.php` | `app/Console/Commands/` |
| `app/Http/Middleware/SecurityHeaders.php` | `app/Http/Middleware/` |

### Modified Files (10)

| Local Path | Upload To |
|------------|-----------|
| `app/Models/PendingRegistration.php` | `app/Models/` |
| `app/Http/Controllers/Api/AuthController.php` | `app/Http/Controllers/Api/` |
| `app/Http/Controllers/Api/SessionController.php` | `app/Http/Controllers/Api/` |
| `app/Services/Auth/PermissionService.php` | `app/Services/Auth/` |
| `app/Http/Middleware/IsAdmin.php` | `app/Http/Middleware/` |
| `app/Http/Kernel.php` | `app/Http/` |
| `app/Console/Kernel.php` | `app/Console/` |
| `config/sanctum.php` | `config/` |
| `config/cors.php` | `config/` |
| `routes/api.php` | `routes/` |

---

## Post-Upload: Clear Caches

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan route:clear && php artisan config:clear
```

---

## Post-Upload: Verify Scheduler

Ensure the Laravel scheduler cron is active on the server. The new scheduled commands are:

| Command | Schedule | Purpose |
|---------|----------|---------|
| `registrations:cleanup` | Hourly | Delete expired pending registrations |
| `sessions:cleanup` | Daily at 02:00 | Delete orphaned user sessions |
| `audit:purge` | Weekly (Sunday 03:00) | Purge old audit logs per retention policy |

If the cron isn't already set up:
```bash
crontab -e
# Add:
* * * * * cd ~/www/fynla.org/public_html && php artisan schedule:run >> /dev/null 2>&1
```

---

## What Changed

| Change | Files |
|--------|-------|
| **PendingRegistration 24h expiry** | Migration, `PendingRegistration.php`, `AuthController.php`, `CleanupPendingRegistrations.php` |
| **Unified admin system** | `PermissionService.php`, `IsAdmin.php`, `Kernel.php` (alias) |
| **Re-auth for session revocation** | `SessionController.php` |
| **Orphaned session cleanup** | `CleanupOrphanedSessions.php`, `Console/Kernel.php` |
| **Sanctum token prefix (`fynla_`)** | `config/sanctum.php` |
| **Rate limit `/api/auth/user`** | `routes/api.php` |
| **Remove X-XSRF-TOKEN from CORS** | `config/cors.php` |
| **Security headers (CSP, HSTS, etc.)** | `SecurityHeaders.php`, `Kernel.php` (global middleware) |
| **Scheduled audit log purge** | `Console/Kernel.php` |

---

## Breaking Changes

| Change | Impact | Action Required |
|--------|--------|-----------------|
| **Sanctum token prefix** | New tokens will be prefixed with `fynla_`. Existing tokens are unaffected and continue working until they expire. | None - transparent to users |
| **Session revoke-all requires password** | `DELETE /api/auth/sessions/others/all` now requires `current_password` in request body | Frontend must send password if this feature is used (Settings page session management) |
| **Security headers** | CSP may block external resources if any were loaded | Verify no external scripts/styles/fonts are used in production |

---

## Bug Fix During Testing

**Issue:** The `SecurityHeaders` middleware CSP blocked the Vite dev server (`127.0.0.1:5173`) in local development, causing a blank page.

**Root cause:** Vite serves assets from `http://127.0.0.1:5173` but the initial CSP only allowed `localhost:5173`. These are different origins from the browser's perspective.

**Fix:** Updated `SecurityHeaders.php` to detect the local environment and include both `localhost:5173` and `127.0.0.1:5173` (plus WebSocket variants) in the CSP. Production CSP remains strict `'self'` only.

---

## Deployment Order

1. Upload migration file
2. Run `php artisan migrate`
3. Upload all other files (order doesn't matter)
4. Run cache clears
5. Verify scheduler cron is active
6. Test login flow, session management, and admin access
