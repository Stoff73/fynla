# Admin Fix Deployment Guide

**Date:** 18 March 2026
**Branch:** `main`

---

## Frontend Rebuild Required: YES

The file `resources/js/store/modules/auth.js` was changed (isAdmin getter). A Vite rebuild is required.

```bash
./deploy/fynla-org/build.sh
```

Upload `public/build/` directory after build.

---

## Files to Upload

### PHP Files (upload to `~/www/fynla.org/public_html/`)

| # | File | Change |
|---|------|--------|
| 1 | `app/Http/Controllers/Api/AdminController.php` | `--single-transaction` on mysqldump (prevents DB lock); listBackups returns raw bytes (fixes "NaN undefined" size) |
| 2 | `app/Http/Controllers/Api/AuthController.php` | Auto-promotes ADMIN_EMAILS users to admin on login; fetchUser returns `role: 'admin'` fallback for is_admin users |
| 3 | `app/Http/Middleware/HasPermission.php` | Admin users (is_admin or admin role) bypass permission checks |
| 4 | `app/Services/Admin/UserModuleTrackingService.php` | `cover_amount` -> `sum_assured` (correct column name) |

### Frontend Build (upload after running build script)

| # | File | Change |
|---|------|--------|
| 5 | `public/build/` | Rebuilt assets — auth.js isAdmin getter checks `role === 'admin'` OR `user.is_admin` |

### Test Files (do NOT upload to production)

| # | File | Change |
|---|------|--------|
| - | `tests/Feature/Api/AdminBackupTest.php` | Rate limit test fix; afterEach cleanup |
| - | `tests/Unit/Services/Admin/UserModuleTrackingServiceTest.php` | cover_amount -> sum_assured |

---

## SSH Commands (after upload)

```bash
ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org
cd ~/www/fynla.org/public_html
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Environment Check

Verify `ADMIN_EMAILS` is set in production `.env`:

```
ADMIN_EMAILS=chris@fynla.org,brett@fynla.org,azlan@fynla.org
```

If not present, add it and run `php artisan config:clear`.

---

## What These Fixes Do

1. **Admin CTA for chris/brett/azlan** — On next login, these users are auto-promoted to admin. The Admin button appears in the top nav and all admin panel features work.

2. **Backup size display** — Database tab no longer shows "NaN undefined" for backup file sizes. Shows formatted sizes like "767.83 KB".

3. **Backup no longer locks DB** — Creating a backup via the admin panel no longer locks all database tables. Uses `--single-transaction` for consistent snapshots without blocking.

4. **Module tracking fix** — User Management expandable rows correctly sum life insurance coverage using `sum_assured` column.

---

## Verification After Deploy

1. Log in as chris@fynla.org — Admin button should appear in top nav
2. Click Admin -> verify all 5 tabs load (Dashboard, User Management, Decision Matrix, Tax Settings, Database)
3. Go to Database tab -> verify backup sizes show correctly (not "NaN undefined")
4. Create a backup -> verify it completes without hanging
