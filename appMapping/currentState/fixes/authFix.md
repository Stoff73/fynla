# Authentication Security Fixes

**Date:** 20 February 2026
**Branch:** `auth`
**Scope:** All actionable issues from auth.md Sections 20 (Security Assessment) and 21 (Recommended Improvements)

---

## Issues Addressed

### From Section 20 (Vulnerabilities)

| # | Priority | Issue | Fix |
|---|----------|-------|-----|
| 1 | HIGH | PendingRegistration never expires | Add `expires_at` column (24h), expiry checks, cleanup command |
| 2 | HIGH | Dual admin system inconsistency | Unify `IsAdmin` middleware to use `PermissionService::isAdmin()`, update `isAdmin()` to check both boolean and role |
| 3 | MEDIUM | Beacon logout token in body | **Not fixable** - `sendBeacon` API limitation; token is revoked immediately |
| 4 | MEDIUM | No re-auth for session revocation | Require `current_password` for `destroyOthers` |
| 5 | MEDIUM | Registration code has no expiry | Covered by fix #1 (expires_at on pending_registrations) |
| 6 | LOW | Recovery code verification performance | **Deferred** - changing from bcrypt to HMAC would alter security model and require data migration |
| 7 | LOW | No token refresh mechanism | **No action** - UX-only, 15-min inactivity timeout covers this |
| 8 | LOW | Session file driver | **No action** - infrastructure decision for future scaling |
| 9 | LOW | CORS X-XSRF-TOKEN header allowed | Remove from config |
| 10 | LOW | AuthServiceProvider is empty | **Deferred** - major architectural work; controllers already scope queries by authenticated user |

### From Section 21 (Recommended Improvements)

| # | Priority | Improvement | Fix |
|---|----------|-------------|-----|
| 1 | Critical | Add expiry to PendingRegistrations | Same as 20.1 above |
| 2 | Critical | Unify admin system | Same as 20.2 above |
| 3 | Important | Add expiry to registration verification codes | Same as 20.1 (expires_at on pending_registrations covers this) |
| 4 | Important | Require re-auth for session revocation | Same as 20.4 above |
| 5 | Important | Clean up orphaned sessions | New scheduled command |
| 6 | Nice | Token prefix | Set `fynla_` prefix in sanctum config |
| 7 | Nice | Switch to Redis | **No action** - infrastructure decision |
| 8 | Nice | Rate limit `/api/auth/user` | Add throttle middleware to route |
| 9 | Nice | Add CSP headers | New `SecurityHeaders` middleware |
| 10 | Nice | Remove X-XSRF-TOKEN from CORS | Same as 20.9 |
| 11 | Nice | Consider Argon2id | **Deferred** - requires careful migration of existing password hashes |
| 12 | Nice | Add security headers middleware | Same as 21.9 (combined into SecurityHeaders middleware) |

---

## Changes By File

### New Files

| File | Purpose |
|------|---------|
| `database/migrations/2026_02_20_000001_add_expires_at_to_pending_registrations_table.php` | Add `expires_at` column |
| `app/Console/Commands/CleanupPendingRegistrations.php` | Delete expired pending registrations |
| `app/Console/Commands/CleanupOrphanedSessions.php` | Delete sessions without valid tokens |
| `app/Http/Middleware/SecurityHeaders.php` | Add CSP, X-Content-Type-Options, X-Frame-Options, HSTS headers |

### Modified Files

| File | Change |
|------|--------|
| `app/Models/PendingRegistration.php` | Add `expires_at` to fillable/casts, add `isExpired()`, set 24h expiry in `createOrUpdate()` |
| `app/Http/Controllers/Api/AuthController.php` | Check expiry in `verifyCode()` and `resendCode()` for registration type |
| `app/Services/Auth/PermissionService.php` | Update `isAdmin()` to check both `$user->is_admin` boolean AND admin role |
| `app/Http/Middleware/IsAdmin.php` | Use `PermissionService::isAdmin()` instead of raw `$user->is_admin` check |
| `app/Http/Kernel.php` | Register `admin` alias for `IsAdmin`, add `SecurityHeaders` to global middleware |
| `app/Http/Controllers/Api/SessionController.php` | Require `current_password` in `destroyOthers()` |
| `app/Console/Kernel.php` | Schedule cleanup commands |
| `config/sanctum.php` | Set token prefix to `fynla_` |
| `config/cors.php` | Remove `X-XSRF-TOKEN` from allowed headers |
| `routes/api.php` | Add `throttle:60,1` to `/api/auth/user` route |

---

## Implementation Details

### 1. PendingRegistration Expiry

- `expires_at` set to `now() + 24 hours` on creation
- `verify()` method rejects expired records
- `isExpired()` helper method on model
- `resendCode()` checks expiry before regenerating code
- `CleanupPendingRegistrations` command deletes records where `expires_at < now()`
- Scheduled to run hourly

### 2. Unified Admin System

**Before:** `IsAdmin` checks only `$user->is_admin`; `PermissionService::isAdmin()` checks only role name
**After:** Both use the same logic: `$user->is_admin || role === 'admin'`

- `PermissionService::isAdmin()` updated to: `$user->is_admin || $this->hasRole($user, Role::ROLE_ADMIN)`
- `IsAdmin` middleware updated to inject `PermissionService` and call `isAdmin()`
- `admin` alias registered in Kernel (was missing)

### 3. Re-authentication for Session Revocation

- `destroyOthers()` now validates `current_password` field
- Returns 422 if password is incorrect
- Single session revocation (`destroy`) unchanged (lower risk, targets specific session)

### 4. Orphaned Session Cleanup

- `CleanupOrphanedSessions` command calls `SessionService::cleanupOrphanedSessions()`
- Scheduled daily at 02:00
- Also schedules `CleanupPendingRegistrations` hourly
- Also schedules `audit:purge` weekly

### 5. Security Headers Middleware

Adds to all responses:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Strict-Transport-Security: max-age=31536000; includeSubDomains` (production only)
- `Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'`
- In local dev: CSP also allows `localhost:5173` and `127.0.0.1:5173` (HTTP + WebSocket) for Vite HMR

### 6. Config Changes

- **Sanctum token prefix:** `fynla_` - enables GitHub secret scanning detection
- **CORS:** Removed unused `X-XSRF-TOKEN` header (CSRF disabled for API routes)
- **Route throttle:** `/api/auth/user` limited to 60 requests/minute

---

## Items Not Addressed (With Justification)

| Issue | Reason |
|-------|--------|
| Beacon token in POST body | `sendBeacon` API cannot set headers - this is a browser API limitation. Token is immediately revoked so exposure window is negligible. |
| Recovery code bcrypt performance | Low priority UX issue (~2.5s worst case). Changing to HMAC requires data migration of existing hashed codes. |
| No token refresh | UX-only concern. 15-minute inactivity timeout means most users never hit the 8-hour token expiry. |
| Session file driver | Infrastructure decision - requires Redis setup. No impact on single-server deployment. |
| Empty AuthServiceProvider | Major architectural work to add model policies. Controllers already scope all queries to the authenticated user, mitigating the risk. |
| Redis for cache/sessions | Infrastructure decision - not a code change. |
| Argon2id hashing | Requires migration strategy for existing bcrypt passwords. Both are strong algorithms. |
