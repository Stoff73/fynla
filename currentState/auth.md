# Fynla Authentication System - Complete Reference

**Last Updated:** 18 February 2026
**Version:** v0.7.0

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Authentication Flows](#2-authentication-flows)
3. [Database Schema](#3-database-schema)
4. [Backend Services](#4-backend-services)
5. [Middleware Stack](#5-middleware-stack)
6. [Controllers](#6-controllers)
7. [API Routes](#7-api-routes)
8. [Frontend Authentication](#8-frontend-authentication)
9. [Permissions & Roles (RBAC)](#9-permissions--roles-rbac)
10. [Multi-Factor Authentication (MFA)](#10-multi-factor-authentication-mfa)
11. [Rate Limiting & Lockout](#11-rate-limiting--lockout)
12. [Session Management](#12-session-management)
13. [Password Security](#13-password-security)
14. [Preview User System](#14-preview-user-system)
15. [CORS & CSRF](#15-cors--csrf)
16. [Input Sanitisation](#16-input-sanitisation)
17. [Audit Logging](#17-audit-logging)
18. [GDPR & Consent](#18-gdpr--consent)
19. [Configuration Reference](#19-configuration-reference)
20. [Security Assessment](#20-security-assessment)
21. [Recommended Improvements](#21-recommended-improvements)

---

## 1. Architecture Overview

```
                        +-----------------------+
                        |   Vue.js SPA (Frontend)   |
                        |                       |
                        | sessionStorage: auth_token  |
                        | sessionLifecycleService.js  |
                        | auth.js (Vuex store)        |
                        | authService.js              |
                        | api.js (interceptors)       |
                        +-----------+-----------+
                                    |
                            Bearer Token (Header)
                                    |
                        +-----------v-----------+
                        |   Laravel API (Backend)     |
                        |                       |
                        | Global Middleware:     |
                        |  - TrustHosts         |
                        |  - TrustProxies       |
                        |  - HandleCors         |
                        |  - ValidatePostSize   |
                        |  - TrimStrings        |
                        |  - ConvertEmptyStrings|
                        |                       |
                        | API Middleware Group:  |
                        |  - EnsureFrontendRequestsAreStateful  |
                        |  - ThrottleRequests:api               |
                        |  - SubstituteBindings                 |
                        |  - SanitizeInput                      |
                        |  - PreviewWriteInterceptor            |
                        |                       |
                        | Route Middleware:      |
                        |  - auth:sanctum       |
                        |  - admin              |
                        |  - role:admin,support |
                        |  - permission:X       |
                        |  - mfa.verified       |
                        |  - throttle:N,M       |
                        +-----------+-----------+
                                    |
                        +-----------v-----------+
                        |    Sanctum Token Auth       |
                        |  personal_access_tokens     |
                        +-----------+-----------+
                                    |
                        +-----------v-----------+
                        |       MySQL 8 Database      |
                        |  users, roles, permissions  |
                        |  user_sessions, audit_logs  |
                        |  login_attempts, etc.       |
                        +---------------------------+
```

**Token Strategy:** Sanctum API tokens (Bearer) stored in `sessionStorage` on the frontend. No cookie-based sessions for API auth. CSRF is disabled for API routes; authentication relies entirely on the Bearer token.

---

## 2. Authentication Flows

### 2.1 Registration Flow

```
User fills form --> POST /api/auth/register
                        |
                  RegisterRequest validates:
                    - first_name: required, string, max:255
                    - middle_name: optional, string, max:255
                    - surname: required, string, max:255
                    - email: required, email, unique:users
                    - password: required, min:8, confirmed, regex (complexity)
                        |
                  Check if email exists in users table
                  (if yes: 422 "already registered")
                        |
                  PendingRegistration::createOrUpdate()
                  (keyed on email - overwrites existing pending)
                        |
                  Mail: VerificationCode (6-digit, type: registration)
                        |
                  Response: { pending_id, masked_email, requires_verification: true }
                        |
User enters code --> POST /api/auth/verify-code
                        |
                  Validate code matches pending_registrations.verification_code
                        |
                  Create User from PendingRegistration data
                  Set is_admin if email in ADMIN_EMAILS env
                  Start trial if plan selected
                  Delete PendingRegistration
                  Create Sanctum token + UserSession
                        |
                  Response: { user, access_token, token_type: "Bearer" }
```

**Key Details:**
- `PendingRegistration` uses `updateOrCreate` keyed on email - a second registration attempt for the same email before verification overwrites the first
- The verification code is stored directly on the `pending_registrations` row (field: `verification_code`), NOT in `email_verification_codes`
- No expiry on PendingRegistration records
- Password is hashed via the `'hashed'` cast on PendingRegistration model before storage

### 2.2 Login Flow (Standard - No MFA)

```
User enters credentials --> POST /api/auth/login
                                |
                          LoginRequest validates:
                            - email: required, string, email
                            - password: required, string
                                |
                          LoginLockoutService::isLocked(email)
                          LoginLockoutService::isIpLocked()
                          (if locked: 423 with remaining seconds)
                                |
                          User::where('email', $email)->first()
                          (if not found: 401, record failed attempt)
                                |
                          Auth::attempt(['email', 'password'])
                          (if fails: 401, record failed attempt)
                                |
                          is_preview_user? --> YES: skip verification, return token
                                |
                          mfa_enabled && mfa_confirmed_at? --> YES: MFA flow (see 2.3)
                                |
                          EmailVerificationCode::generate(user_id, 'login')
                          Mail: VerificationCode (6-digit, 15-min expiry)
                                |
                          Response: { user_id, masked_email, requires_verification: true }
                                |
User enters code --> POST /api/auth/verify-code
                                |
                          EmailVerificationCode::findValidCode()
                          Checks: user_id matches, code matches, type matches,
                                  not already verified, not expired
                                |
                          Mark code verified, reset lockout counters
                          Create Sanctum token + UserSession
                          Audit: login_success
                                |
                          Response: { user, access_token, token_type: "Bearer",
                                     must_change_password, mfa_enabled }
```

### 2.3 Login Flow (With MFA)

```
After Auth::attempt succeeds and mfa_enabled check:
                                |
                          MFAController::generateChallengeToken(user_id)
                          Stores { user_id, created_at } in Cache with random 64-char key
                          TTL: 5 minutes
                                |
                          Response: { mfa_token, user_id, masked_email, requires_mfa: true }
                                |
User enters TOTP code --> POST /api/auth/mfa/verify
                                |
                          MFAController::validateChallengeToken(mfa_token)
                          Retrieves and DELETES from Cache (one-time use)
                          Resolves user_id from cached data
                                |
                          Decrypt mfa_secret with Crypt::decryptString()
                          Google2FA::verifyKey(secret, code, window=2)
                                |
                          Record successful login, create token + session
                          Audit: login_success (method: mfa)
                                |
                          Response: { user, access_token, token_type: "Bearer" }

ALTERNATIVE - Recovery code:
User enters recovery code --> POST /api/auth/mfa/recovery
                                |
                          Same challenge token validation
                          MFAService::verifyRecoveryCode()
                          Iterates all 10 bcrypt-hashed codes, Hash::check each
                          On match: remove used code from array, save user
                                |
                          Same token creation flow
```

### 2.4 Logout Flow

**Normal Logout (via UI):**
```
POST /api/auth/logout (auth:sanctum)
    |
    Audit: logout
    Delete UserSession (by token_id)
    Delete Sanctum token
    |
    Frontend: clearAuth() in authService
    Removes: auth_token, preview_mode, previewLoggedIn from sessionStorage
    Cleans up localStorage legacy keys
    Resets Vuex stores: auth, userProfile, netWorth
```

**Beacon Logout (browser/tab close):**
```
POST /api/auth/logout-beacon (no auth middleware)
    |
    Token sent in POST body (sendBeacon can't set headers)
    Parse JSON body for 'token'
    PersonalAccessToken::findToken(tokenValue) - hashes and looks up
    |
    If found: audit logout (method: beacon), delete session + token
```

**Inactivity Logout (frontend):**
```
sessionLifecycleService.js monitors:
  mousedown, keydown, scroll, touchstart, mousemove
  |
  15-minute timeout with no activity
  |
  store.dispatch('auth/logout')
  Redirect to /login?reason=inactivity
```

**Browser/Tab Close:**
```
sessionStorage automatically clears (no explicit handler needed)
Token disappears; orphaned server records cleaned by:
  - Sanctum token expiry (8 hours)
  - SessionService::cleanupOrphanedSessions()
```

### 2.5 Password Reset Flow

```
POST /api/auth/password-reset/request
    |
    PasswordResetService::initiateReset()
    Always returns success message (prevents account enumeration)
    If user exists: creates PasswordResetSession (15-min expiry, 64-char token)
    Sends PasswordResetCode email with 6-digit code
    |
    Response: { reset_token } (if user exists)

POST /api/auth/password-reset/verify-email
    |
    Validate session not expired, code matches
    Mark email_verified_at
    Return { requires_mfa, can_reset_password }

POST /api/auth/password-reset/verify-mfa (if MFA enabled)
    |
    Verify TOTP code via MFAService
    Mark mfa_verified_at
    Return { can_reset_password: true }

POST /api/auth/password-reset/reset
    |
    Check session.canResetPassword():
      email_verified_at set AND (mfa_verified_at set OR user has no MFA)
    |
    Update password (Hash::make)
    Set must_change_password = false
    Mark session as used
    Revoke ALL existing Sanctum tokens ($user->tokens()->delete())
    Audit: password_reset_completed
```

### 2.6 Password Change Flow (Authenticated)

```
POST /api/auth/change-password (auth:sanctum, throttle:5,1)
    |
    Validate: current_password, new_password (min:8, confirmed, complexity, different from current)
    Hash::check(current_password, user.password)
    |
    Update password, set must_change_password = false
    Audit: password_changed
```

---

## 3. Database Schema

### 3.1 `users` Table (Auth-Related Fields)

| Field | Type | Default | Notes |
|-------|------|---------|-------|
| `id` | bigint unsigned | auto | Primary key, guarded |
| `role_id` | bigint unsigned, nullable | null | FK to `roles.id`, ON DELETE SET NULL |
| `first_name` | string | - | Required |
| `middle_name` | string, nullable | null | Optional |
| `surname` | string | - | Required |
| `email` | string, unique | - | Used for authentication |
| `email_verified_at` | timestamp, nullable | null | Guarded, cast: datetime |
| `password` | string | - | Cast: `hashed` (auto-hashes on set), hidden |
| `remember_token` | string, nullable | null | Hidden, guarded |
| `is_admin` | boolean | false | Guarded, cast: boolean |
| `is_preview_user` | boolean | false | Guarded, cast: boolean |
| `preview_persona_id` | string, nullable | null | Guarded |
| `must_change_password` | boolean | false | Cast: boolean |
| `mfa_enabled` | boolean | false | Cast: boolean |
| `mfa_secret` | text, nullable | null | Encrypted (AES-256-CBC via Crypt), hidden |
| `mfa_recovery_codes` | json, nullable | null | Array of bcrypt hashes, cast: array, hidden |
| `mfa_confirmed_at` | timestamp, nullable | null | Cast: datetime |
| `failed_login_count` | integer | 0 | Cast: integer, hidden |
| `locked_until` | timestamp, nullable | null | Cast: datetime, hidden |
| `last_failed_login_at` | timestamp, nullable | null | Cast: datetime, hidden |
| `plan` | enum(free,student,standard,pro) | free | Subscription tier |
| `trial_ends_at` | timestamp, nullable | null | Trial period expiry |
| `created_at` | timestamp | auto | Guarded |
| `updated_at` | timestamp | auto | Guarded |

**User Model Traits:** `HasApiTokens` (Sanctum), `HasFactory`, `Notifiable`

**Mass Assignment Strategy:** Uses `$guarded` (not `$fillable`). Protected fields: `id`, `is_admin`, `is_preview_user`, `preview_persona_id`, `email_verified_at`, `remember_token`, `created_at`, `updated_at`. All other fields are mass-assignable.

**Appended Attributes:** `name` (concatenation: `first_name middle_name surname`)

**Auth-Related Relationships:**
- `spouse()` - BelongsTo(User) via `spouse_id`
- `role()` - BelongsTo(Role) via `role_id`
- `sessions()` - HasMany(UserSession)
- `consents()` - HasMany(UserConsent)

### 3.2 `personal_access_tokens` Table (Sanctum)

| Field | Type | Notes |
|-------|------|-------|
| `id` | bigint unsigned | Primary key |
| `tokenable_type` | string | Always `App\Models\User` |
| `tokenable_id` | bigint unsigned | FK to `users.id` |
| `name` | string | Always `auth_token` |
| `token` | varchar(64), unique | SHA-256 hash of plaintext token |
| `abilities` | text, nullable | Always `["*"]` (JSON) |
| `last_used_at` | timestamp, nullable | Updated by Sanctum on each use |
| `expires_at` | timestamp, nullable | Not explicitly set; uses config expiration |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### 3.3 `pending_registrations` Table

| Field | Type | Notes |
|-------|------|-------|
| `id` | bigint unsigned | Primary key |
| `email` | string, unique | Keyed for updateOrCreate |
| `first_name` | string | |
| `middle_name` | string, nullable | |
| `surname` | string | |
| `password` | string | Cast: `hashed` |
| `verification_code` | varchar(6) | 6-digit verification code |
| `registration_source` | string, nullable | `'preview'` or null |
| `preview_persona_id` | string, nullable | |
| `plan` | string, nullable | Selected plan during registration |
| `billing_cycle` | string, nullable | monthly/yearly |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### 3.4 `email_verification_codes` Table

| Field | Type | Notes |
|-------|------|-------|
| `id` | bigint unsigned | Primary key |
| `user_id` | bigint unsigned | FK to `users.id` |
| `code` | varchar(6) | 6-digit string |
| `type` | string | `login` or `registration` |
| `resend_count` | integer, default 0 | Max: 2 |
| `expires_at` | timestamp | 15 minutes from creation |
| `verified_at` | timestamp, nullable | Set when code is used |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### 3.5 `login_attempts` Table

| Field | Type | Notes |
|-------|------|-------|
| `id` | bigint unsigned | Primary key |
| `email` | varchar(255) | Email attempted |
| `ip_address` | varchar(45) | IPv4 or IPv6 |
| `user_agent` | text, nullable | Browser user agent |
| `successful` | boolean, default false | |
| `failure_reason` | varchar(100), nullable | `invalid_credentials`, `account_locked`, `mfa_required`, `mfa_failed`, `email_not_verified` |
| `created_at` | timestamp | Indexed with email and ip_address |

**Indexes:** `(email, created_at)`, `(ip_address, created_at)`

### 3.6 `user_sessions` Table

| Field | Type | Notes |
|-------|------|-------|
| `id` | bigint unsigned | Primary key |
| `user_id` | bigint unsigned | FK to `users.id`, CASCADE DELETE |
| `token_id` | bigint unsigned | FK to `personal_access_tokens.id`, CASCADE DELETE |
| `ip_address` | varchar(45), nullable | |
| `user_agent` | text, nullable | |
| `device_name` | varchar(255), nullable | Auto-detected: iPhone, iPad, Android, Mac, Windows, Linux |
| `last_activity_at` | timestamp, nullable | Updated on activity |
| `created_at` | timestamp | |

**Index:** `(user_id, created_at)`

### 3.7 `password_reset_sessions` Table

| Field | Type | Notes |
|-------|------|-------|
| `id` | bigint unsigned | Primary key |
| `user_id` | bigint unsigned | FK to `users.id`, CASCADE DELETE |
| `token` | varchar(64), unique | Random reset session token |
| `email_code` | varchar(6) | 6-digit verification code |
| `email_code_resend_count` | tinyint unsigned, default 0 | Max: 2 |
| `email_verified_at` | timestamp, nullable | |
| `mfa_verified_at` | timestamp, nullable | |
| `ip_address` | varchar(45), nullable | |
| `expires_at` | timestamp | 15 minutes from creation |
| `used_at` | timestamp, nullable | Set when password is reset |
| `created_at` | timestamp | |

**Indexes:** `(token, expires_at)`, `(user_id, created_at)`

### 3.8 `roles` Table

| Field | Type | Notes |
|-------|------|-------|
| `id` | bigint unsigned | Primary key |
| `name` | varchar(50), unique | `user`, `support`, `admin` |
| `display_name` | varchar(100) | |
| `description` | string, nullable | |
| `level` | integer, default 0 | user=0, support=50, admin=100 |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### 3.9 `permissions` Table

| Field | Type | Notes |
|-------|------|-------|
| `id` | bigint unsigned | Primary key |
| `name` | varchar(100), unique | e.g. `users.view`, `admin.access` |
| `display_name` | varchar(100) | |
| `description` | string, nullable | |
| `category` | varchar(50), nullable | `users`, `content`, `settings`, `admin` |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### 3.10 `role_permission` Pivot Table

| Field | Type | Notes |
|-------|------|-------|
| `role_id` | bigint unsigned | FK to `roles.id`, CASCADE DELETE |
| `permission_id` | bigint unsigned | FK to `permissions.id`, CASCADE DELETE |

**Primary Key:** `(role_id, permission_id)`

### 3.11 `audit_logs` Table

| Field | Type | Notes |
|-------|------|-------|
| `id` | bigint unsigned | Primary key |
| `user_id` | bigint unsigned, nullable | FK to `users.id`, ON DELETE SET NULL |
| `event_type` | varchar(50) | `auth`, `data_access`, `data_change`, `admin`, `gdpr` |
| `action` | varchar(100) | e.g. `login_success`, `logout`, `password_changed` |
| `model_type` | varchar(100), nullable | |
| `model_id` | bigint unsigned, nullable | |
| `old_values` | json, nullable | |
| `new_values` | json, nullable | |
| `metadata` | json, nullable | Additional context (IP, method, etc.) |
| `ip_address` | varchar(45), nullable | |
| `user_agent` | text, nullable | |
| `created_at` | timestamp | |

**Indexes:** `(user_id, created_at)`, `(event_type, action)`, `(model_type, model_id)`, `(created_at)`

### 3.12 `user_consents` Table

| Field | Type | Notes |
|-------|------|-------|
| `id` | bigint unsigned | Primary key |
| `user_id` | bigint unsigned | FK to `users.id`, CASCADE DELETE |
| `consent_type` | varchar(100) | `terms`, `privacy`, `marketing`, `data_processing` |
| `version` | varchar(50) | `v1.0`, `v2.0`, etc. |
| `consented` | boolean, default false | |
| `consented_at` | timestamp, nullable | |
| `withdrawn_at` | timestamp, nullable | |
| `ip_address` | varchar(45), nullable | |
| `user_agent` | text, nullable | |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Unique:** `(user_id, consent_type, version)`
**Index:** `(user_id, consent_type)`

---

## 4. Backend Services

### 4.1 LoginLockoutService

**File:** `app/Services/Auth/LoginLockoutService.php`

Progressive account lockout with IP-based protection.

| Method | Purpose |
|--------|---------|
| `isLocked(email)` | Check if user is locked (checks `locked_until` on User model) |
| `isIpLocked()` | Check if IP has exceeded 50 failed attempts in the last hour |
| `getRemainingLockoutSeconds(email)` | Time until lockout expires |
| `recordFailedAttempt(email, reason)` | Increment `failed_login_count`, set `locked_until` based on thresholds, record LoginAttempt |
| `recordSuccessfulLogin(email)` | Reset `failed_login_count`, `locked_until`, `last_failed_login_at` to null/0 |
| `resetFailedAttempts(user)` | Explicit reset (e.g., after password reset) |
| `getLockoutInfo(email)` | Returns `{ locked, remaining_seconds, message }` for API response |

**Lockout Thresholds** (from `config/auth.php`):

| Failed Attempts | Lockout Duration |
|-----------------|-----------------|
| 3 | 1 minute |
| 5 | 5 minutes |
| 10 | 30 minutes |
| 15+ | 24 hours (1440 minutes) |

**IP Lockout:** 50 failed attempts per hour from same IP address.

### 4.2 MFAService

**File:** `app/Services/Auth/MFAService.php`

TOTP-based multi-factor authentication using `PragmaRX\Google2FA`.

| Method | Purpose |
|--------|---------|
| `generateSecret()` | 32-character secret key |
| `getQRCodeDataUri(user, secret)` | SVG QR code as base64 data URI (300x300px via bacon/bacon-qr-code) |
| `verifyCode(user, code)` | Decrypt stored secret, verify TOTP with 2-window tolerance |
| `verifySetupCode(secret, code)` | Verify during initial setup (secret not yet stored) |
| `enableMFA(user, secret)` | Encrypt secret, generate 10 recovery codes, save to user |
| `disableMFA(user)` | Clear all MFA fields |
| `generateRecoveryCodes(count=10)` | Format: `XXXX-XXXX-XXXX` (uppercase alphanumeric) |
| `hashRecoveryCodes(codes)` | Individually bcrypt hash each code |
| `verifyRecoveryCode(user, code)` | Check against all hashed codes; consume on match |
| `regenerateRecoveryCodes(user)` | Generate new set, replace existing |
| `hasMFAEnabled(user)` | Checks both `mfa_enabled` flag AND `mfa_secret` is not null |

**MFA Secret Storage:** Encrypted with `Crypt::encryptString()` (AES-256-CBC using `APP_KEY`). Stored in `users.mfa_secret` (text column - encrypted values exceed 255 chars).

**Recovery Codes:** Each individually bcrypt hashed. Stored as JSON array in `users.mfa_recovery_codes`. Consumed (removed from array) on use.

### 4.3 SessionService

**File:** `app/Services/Auth/SessionService.php`

Manages `user_sessions` records tied to Sanctum tokens.

| Method | Purpose |
|--------|---------|
| `createSession(user, token)` | Create UserSession for a new Sanctum token |
| `getUserSessions(user)` | List all sessions with device/IP/activity info |
| `revokeSession(session)` | Delete both Sanctum token AND UserSession |
| `revokeAllExceptCurrent(user)` | Revoke all other sessions |
| `revokeAllSessions(user)` | Revoke everything |
| `updateCurrentSessionActivity(user)` | Touch `last_activity_at` |
| `findSession(user, sessionId)` | Lookup by user + session ID |
| `cleanupOrphanedSessions()` | Delete sessions where token no longer exists |

### 4.4 PasswordResetService

**File:** `app/Services/Auth/PasswordResetService.php`

Multi-step password reset with optional MFA verification.

| Method | Purpose |
|--------|---------|
| `initiateReset(email)` | Create PasswordResetSession, send code. Always returns success (anti-enumeration) |
| `verifyEmailCode(session, code)` | Verify 6-digit code, mark `email_verified_at` |
| `verifyMfaCode(session, code)` | Verify TOTP, mark `mfa_verified_at` |
| `verifyRecoveryCode(session, code)` | Alternative to TOTP, consumes recovery code |
| `resetPassword(session, newPassword)` | Update password, revoke ALL tokens, mark session used |
| `resendCode(session)` | Regenerate email code (max 2 resends) |

**Password Reset Completion Requirements:**
- `email_verified_at` must be set
- If user has MFA: `mfa_verified_at` must also be set
- If user has no MFA: only email verification needed

### 4.5 PermissionService

**File:** `app/Services/Auth/PermissionService.php`

RBAC service for role and permission checks.

| Method | Purpose |
|--------|---------|
| `hasRole(user, roleName)` | Check user's role name |
| `hasAnyRole(user, roleNames)` | Check against array of role names |
| `hasPermission(user, permissionName)` | Admin bypass; otherwise check role's permissions |
| `hasAnyPermission(user, permissions)` | Any match from array |
| `hasAllPermissions(user, permissions)` | All must match |
| `isAtLeastLevel(user, level)` | Check role level (0/50/100) |
| `isAdmin(user)` | Checks BOTH `is_admin` boolean AND admin role |
| `isSupport(user)` | Admin OR support role |
| `assignRole(user, roleName)` | Set `role_id` on user |
| `syncDefaultRolesAndPermissions()` | Create default roles/permissions, assign to roles |

### 4.6 AuditService

**File:** `app/Services/Audit/AuditService.php`

Centralised audit logging for all auth events.

**Auth Event Types:**
- `login_attempt`, `login_success`, `login_failed`
- `logout`
- `mfa_enabled`, `mfa_disabled`, `mfa_verified`
- `password_changed`, `password_reset_requested`, `password_reset_completed`
- `session_revoked`

### 4.7 TrialService

**File:** `app/Services/Payment/TrialService.php`

Handles trial period initiation on registration when a plan is selected.

---

## 5. Middleware Stack

### 5.1 Global Middleware (Every Request)

Defined in `app/Http/Kernel.php`:

| Order | Middleware | Purpose |
|-------|-----------|---------|
| 1 | `TrustHosts` | Validate request host |
| 2 | `TrustProxies` | Handle reverse proxy headers |
| 3 | `HandleCors` | CORS handling (config-driven) |
| 4 | `PreventRequestsDuringMaintenance` | Maintenance mode |
| 5 | `ValidatePostSize` | POST size limits |
| 6 | `TrimStrings` | Whitespace trimming |
| 7 | `ConvertEmptyStringsToNull` | Empty string -> null |

### 5.2 API Middleware Group (All `api/*` Routes)

| Order | Middleware | Purpose |
|-------|-----------|---------|
| 1 | `EnsureFrontendRequestsAreStateful` | Sanctum SPA cookie support |
| 2 | `ThrottleRequests:api` | 300/min (prod), 1000/min (local) |
| 3 | `SubstituteBindings` | Route model binding |
| 4 | `SanitizeInput` | Strip HTML tags (except password fields) |
| 5 | `PreviewWriteInterceptor` | Fake write responses for preview users |

### 5.3 Named Middleware Aliases

| Alias | Class | Purpose |
|-------|-------|---------|
| `auth` | `App\Http\Middleware\Authenticate` | Returns 401 JSON (no redirect) |
| `admin` | `App\Http\Middleware\IsAdmin` | Checks `$user->is_admin` boolean, returns 403 |
| `role` | `App\Http\Middleware\HasRole` | Uses PermissionService; legacy `is_admin` support |
| `permission` | `App\Http\Middleware\HasPermission` | Uses PermissionService; `is_admin` bypasses all |
| `mfa.verified` | `App\Http\Middleware\EnsureMFAVerified` | For Bearer tokens: trusts login-time verification; for sessions: checks `session('mfa_verified')` |
| `guest` | `RedirectIfAuthenticated` | Redirects authenticated users |
| `throttle` | `ThrottleRequests` | Rate limiting |
| `verified` | `EnsureEmailIsVerified` | Standard Laravel (not actively used) |

### 5.4 Authenticate Middleware Detail

**File:** `app/Http/Middleware/Authenticate.php`

- Overrides `redirectTo()` to always return `null` (no redirect, always 401 JSON)
- Applies to both API and web requests (API check: `$request->expectsJson() || $request->is('api/*')`)

### 5.5 IsAdmin Middleware Detail

**File:** `app/Http/Middleware/IsAdmin.php`

- Simple check: `$request->user()->is_admin`
- Does NOT check role system (only the boolean flag)
- Returns 403 JSON: `"Unauthorized. Admin access required."`

### 5.6 HasRole Middleware Detail

**File:** `app/Http/Middleware/HasRole.php`

- Accepts variadic role names: `role:admin,support`
- Uses `PermissionService::hasAnyRole()`
- **Legacy support:** Also allows if `$user->is_admin && in_array('admin', $roles)`
- Returns 403 with message listing required roles

### 5.7 HasPermission Middleware Detail

**File:** `app/Http/Middleware/HasPermission.php`

- Accepts variadic permission names: `permission:users.view,users.edit`
- **Admin bypass:** `$user->is_admin` always passes
- Otherwise uses `PermissionService::hasAnyPermission()`
- Returns 403 with message listing required permissions

### 5.8 EnsureMFAVerified Middleware Detail

**File:** `app/Http/Middleware/EnsureMFAVerified.php`

- For API token requests (Bearer): **always passes** - MFA was verified at login before token was issued
- For session-based requests: checks `session('mfa_verified', false)` flag
- Returns 403 with `mfa_required: true` if session MFA not verified

### 5.9 SanitizeInput Middleware Detail

**File:** `app/Http/Middleware/SanitizeInput.php`

- Recursively processes all input fields
- **Strips HTML tags** with `strip_tags()` on all string inputs
- **Exempt fields** (not sanitised): `password`, `password_confirmation`, `current_password`
- **HTML allowed fields:** configurable array (currently empty)
- Trims whitespace on all strings

### 5.10 PreviewWriteInterceptor Middleware Detail

**File:** `app/Http/Middleware/PreviewWriteInterceptor.php`

See [Section 14: Preview User System](#14-preview-user-system).

---

## 6. Controllers

### 6.1 AuthController

**File:** `app/Http/Controllers/Api/AuthController.php` (648 lines)

| Method | Route | Auth | Purpose |
|--------|-------|------|---------|
| `register` | POST `/api/auth/register` | None | Create PendingRegistration, send verification email |
| `login` | POST `/api/auth/login` | None | Validate credentials, send verification or MFA challenge |
| `logout` | POST `/api/auth/logout` | `auth:sanctum` | Revoke token and session |
| `logoutBeacon` | POST `/api/auth/logout-beacon` | None | Token in body; for sendBeacon |
| `user` | GET `/api/auth/user` | `auth:sanctum` | Return authenticated user with spouse |
| `changePassword` | POST `/api/auth/change-password` | `auth:sanctum` | Verify current, set new password |
| `verifyCode` | POST `/api/auth/verify-code` | None | Verify 6-digit code (login or registration) |
| `resendCode` | POST `/api/auth/resend-code` | None | Resend verification code |

**Private Methods:**
- `createAuthTokenWithSession(user)` - Creates Sanctum token + UserSession record
- `buildAuthSuccessResponse(user, token, message)` - Standardised response format
- `maskEmail(email)` - `c***s@domain.com` style masking

**Dependencies:** `LoginLockoutService`, `MFAService`, `SessionService`, `AuditService`, `TrialService`

### 6.2 MFAController

**File:** `app/Http/Controllers/Api/MFAController.php` (344 lines)

| Method | Route | Auth | Purpose |
|--------|-------|------|---------|
| `setup` | POST `/api/auth/mfa/setup` | `auth:sanctum` | Generate secret + QR code |
| `verifySetup` | POST `/api/auth/mfa/verify-setup` | `auth:sanctum` | Confirm TOTP works, enable MFA |
| `verify` | POST `/api/auth/mfa/verify` | None | Verify TOTP during login (uses challenge token) |
| `useRecoveryCode` | POST `/api/auth/mfa/recovery` | None | Use recovery code during login |
| `disable` | POST `/api/auth/mfa/disable` | `auth:sanctum` | Requires current password |
| `regenerateRecoveryCodes` | POST `/api/auth/mfa/recovery-codes` | `auth:sanctum` | Requires current password |
| `status` | GET `/api/auth/mfa/status` | `auth:sanctum` | MFA enabled status + recovery code count |

**Static Methods:**
- `generateChallengeToken(userId)` - Store `{user_id, created_at}` in Cache with `mfa_challenge_` prefix, 5-min TTL
- `validateChallengeToken(token)` - Retrieve + delete from Cache (one-time use)

### 6.3 PasswordResetController

**File:** `app/Http/Controllers/Api/PasswordResetController.php` (157 lines)

Thin controller that delegates entirely to `PasswordResetService`.

| Method | Route | Purpose |
|--------|-------|---------|
| `request` | POST `/api/auth/password-reset/request` | Initiate reset |
| `verifyEmail` | POST `/api/auth/password-reset/verify-email` | Verify email code |
| `resendCode` | POST `/api/auth/password-reset/resend-code` | Resend email code |
| `verifyMfa` | POST `/api/auth/password-reset/verify-mfa` | Verify TOTP |
| `useMfaRecovery` | POST `/api/auth/password-reset/mfa-recovery` | Use recovery code |
| `reset` | POST `/api/auth/password-reset/reset` | Set new password |

### 6.4 SessionController

**File:** `app/Http/Controllers/Api/SessionController.php` (96 lines)

| Method | Route | Auth | Purpose |
|--------|-------|------|---------|
| `index` | GET `/api/auth/sessions` | `auth:sanctum` | List all user sessions |
| `destroy` | DELETE `/api/auth/sessions/{id}` | `auth:sanctum` | Revoke specific session (not current) |
| `destroyOthers` | DELETE `/api/auth/sessions/others/all` | `auth:sanctum` | Revoke all except current |

### 6.5 PreviewController

**File:** `app/Http/Controllers/Api/PreviewController.php`

| Method | Route | Auth | Purpose |
|--------|-------|------|---------|
| `getPersonas` | GET `/api/preview/personas` | None | List available preview personas |
| `login` | POST `/api/preview/login/{personaId}` | None | Create token for preview user (skips email verification) |
| `switch` | POST `/api/preview/switch/{personaId}` | `auth:sanctum` | Revoke current token, create for different persona |
| `exit` | POST `/api/preview/exit` | `auth:sanctum` | Revoke token |

---

## 7. API Routes

### 7.1 Public Auth Routes (No Authentication)

```
POST   /api/auth/register                    [throttle:5,1]
POST   /api/auth/login                       [throttle:5,1]
POST   /api/auth/verify-code                 [throttle:10,1]
POST   /api/auth/resend-code                 [throttle:5,1]
POST   /api/auth/logout-beacon               [throttle:10,1]
POST   /api/auth/mfa/verify                  [throttle:10,1]
POST   /api/auth/mfa/recovery                [throttle:5,1]
```

### 7.2 Password Reset Routes (No Authentication)

```
POST   /api/auth/password-reset/request      [throttle:3,1]
POST   /api/auth/password-reset/verify-email  [throttle:10,1]
POST   /api/auth/password-reset/resend-code   [throttle:5,1]
POST   /api/auth/password-reset/verify-mfa    [throttle:10,1]
POST   /api/auth/password-reset/mfa-recovery  [throttle:5,1]
POST   /api/auth/password-reset/reset         [throttle:5,1]
```

### 7.3 Authenticated Auth Routes (`auth:sanctum`)

```
POST   /api/auth/logout
GET    /api/auth/user
POST   /api/auth/change-password              [throttle:5,1]
GET    /api/auth/mfa/status
POST   /api/auth/mfa/setup
POST   /api/auth/mfa/verify-setup
POST   /api/auth/mfa/disable
POST   /api/auth/mfa/recovery-codes
GET    /api/auth/sessions
DELETE /api/auth/sessions/{id}
DELETE /api/auth/sessions/others/all
```

### 7.4 GDPR Routes (`auth:sanctum`)

```
GET    /api/auth/gdpr/consents
PUT    /api/auth/gdpr/consents
GET    /api/auth/gdpr/consents/history
POST   /api/auth/gdpr/export                  [throttle:export]  (3/hour)
GET    /api/auth/gdpr/export/status
GET    /api/auth/gdpr/export/{id}/download
POST   /api/auth/gdpr/erasure/initiate        [throttle:sensitive]  (3/min)
POST   /api/auth/gdpr/erasure/verify          [throttle:sensitive]
POST   /api/auth/gdpr/erasure/execute         [throttle:sensitive]
POST   /api/auth/gdpr/erasure/resend-code     [throttle:sensitive]
```

### 7.5 Preview Routes (Mixed)

```
GET    /api/preview/personas                   [public]
POST   /api/preview/login/{personaId}          [throttle:10,1]
POST   /api/preview/switch/{personaId}         [auth:sanctum, throttle:20,1]
POST   /api/preview/exit                       [auth:sanctum]
```

### 7.6 Admin Routes (`auth:sanctum` + `admin`)

```
All /api/admin/* routes
All /api/uk-taxes/* routes
All /api/tax-settings/* routes
```

### 7.7 Standard Authenticated Routes (`auth:sanctum`)

All remaining application routes (properties, savings, investment, retirement, etc.) require `auth:sanctum`.

---

## 8. Frontend Authentication

### 8.1 Auth Service (`resources/js/services/authService.js`)

**Token Storage:** `sessionStorage` under key `auth_token`

| Method | Purpose |
|--------|---------|
| `setToken(token)` | Store in sessionStorage; clean up legacy localStorage |
| `getToken()` | Read from sessionStorage |
| `clearAuth()` | Remove auth_token, preview_mode, previewLoggedIn, preview_persona_id from sessionStorage; clean localStorage user-specific keys |
| `isAuthenticated()` | `!!getToken()` |
| `register(userData)` | POST `/api/auth/register` |
| `login(credentials)` | POST `/api/auth/login` |
| `logout()` | POST `/api/auth/logout`; clearAuth on success |
| `getUser()` | GET `/api/auth/user` |
| `getUserById(userId)` | GET `/api/users/{userId}` |
| `changePassword(data)` | POST `/api/auth/change-password` |
| `verifyCode(data)` | POST `/api/auth/verify-code` |
| `resendCode(data)` | POST `/api/auth/resend-code` |
| `requestPasswordReset(email)` | POST `/api/auth/password-reset/request` |
| `verifyPasswordResetEmail(data)` | POST `/api/auth/password-reset/verify-email` |
| `resendPasswordResetCode(data)` | POST `/api/auth/password-reset/resend-code` |
| `verifyPasswordResetMfa(data)` | POST `/api/auth/password-reset/verify-mfa` |
| `passwordResetMfaRecovery(data)` | POST `/api/auth/password-reset/mfa-recovery` |
| `resetPassword(data)` | POST `/api/auth/password-reset/reset` |

### 8.2 API Interceptors (`resources/js/services/api.js`)

**Base URL:**
- Development: `http://localhost:8000/api`
- Production: `{window.location.origin}/api`

**Request Interceptor:**
- Reads token from `sessionStorage` (fallback: `localStorage`)
- Sets `Authorization: Bearer {token}` header

**Response Interceptor:**
- Detects `preview_mode: true` flag in responses
- 401 errors: redirect to `/login` (except for auth endpoints and preview mode)
- 422 errors: extract validation messages

**Retry Interceptor:**
- Exponential backoff with jitter for 5xx, network errors, and 429
- Only for idempotent methods: GET, HEAD, OPTIONS, PUT, DELETE
- Max 3 retries

### 8.3 Vuex Auth Store (`resources/js/store/modules/auth.js`)

**State:**
- `token` - Initialised from `authService.getToken()` (sessionStorage)
- `user` - Always null; fetched fresh from API (never cached from login response)
- `loading` - Boolean
- `error` - String or null

**Getters:**
- `isAuthenticated` - `!!state.token`
- `currentUser` / `user` - `state.user`
- `isAdmin` - `state.user?.is_admin === true || state.user?.is_admin === 1`

**Actions:**
- `register` - Clear existing auth + userProfile + preview state -> register -> store token -> fetchUser
- `login` - Clear existing auth + userProfile + preview state -> login -> store token -> fetchUser
- `logout` - Call logout API -> clearAuth -> reset userProfile + netWorth stores
- `fetchUser` - GET `/api/auth/user` -> setUser (only clears auth on error if no token)
- `fetchUserById` - GET `/api/users/{userId}`

**Security Pattern:** On both login and register, the store always clears existing state BEFORE making the API call. This prevents data leakage between users/sessions.

### 8.4 Session Lifecycle Service (`resources/js/services/sessionLifecycleService.js`)

**Inactivity Timeout:** 15 minutes (900,000ms)

**Monitored Events:** `mousedown`, `keydown`, `scroll`, `touchstart`, `mousemove` (all with `{ passive: true }`)

**How it Works:**
1. On initialisation, starts a 15-minute `setTimeout`
2. Any monitored event clears and restarts the timer
3. On timeout: dispatches `auth/logout`, redirects to `/login?reason=inactivity`
4. Patches `sessionStorage.setItem` to reset timer when `auth_token` key changes

**What it Does NOT Do:**
- No `pagehide`/`beforeunload` handlers (avoids false logouts on page refresh)
- `sessionStorage` naturally clears on browser/tab close

### 8.5 Router Guards (`resources/js/router/index.js`)

**Route Meta Flags:**
- `requiresAuth` - Must be authenticated
- `requiresGuest` - Must NOT be authenticated
- `requiresAdmin` - Must be admin
- `public` - Accessible to all
- `previewMode` - Preview-specific routes
- `devOnly` - Development environment only

**Navigation Guard Logic:**
```
beforeEach(to, from, next):
  if (requiresAuth && !authenticated && !preview) -> /login
  if (requiresGuest && authenticated && !preview) -> /dashboard
  if (requiresAdmin && !isAdmin) -> /dashboard
  if (devOnly && production) -> /dashboard (via beforeEnter guard)
```

### 8.6 Vue Auth Components

| Component | File | Purpose |
|-----------|------|---------|
| `VerificationCodeModal.vue` | 307 lines | 6 individual digit inputs; auto-advance on input; paste support (splits string); auto-submit when all 6 entered; handles both login and registration types |
| `MFASetupModal.vue` | 345 lines | 3-step wizard: (1) QR code + manual secret, (2) verify TOTP code, (3) display recovery codes with copy button |
| `MFAVerifyModal.vue` | 309 lines | TOTP code entry OR recovery code entry (toggle); uses challenge token from login |
| `ForgotPasswordModal.vue` | 739 lines | 5-step flow: email -> code -> MFA (conditional) -> new password -> success |
| `ChangePasswordModal.vue` | 203 lines | Current password + new password + confirmation; `isRequired` prop for forced change (`must_change_password`) |
| `LogoutSuccessModal.vue` | 97 lines | Simple confirmation display after logout |

---

## 9. Permissions & Roles (RBAC)

### 9.1 Dual Admin System

The application has TWO parallel mechanisms for admin determination:

1. **Legacy:** `is_admin` boolean on the `users` table (set from `ADMIN_EMAILS` env on registration)
2. **RBAC:** `role_id` FK to `roles` table with admin role (level 100)

**How Each Component Uses Them:**

| Component | Checks |
|-----------|--------|
| `IsAdmin` middleware | `$user->is_admin` only |
| `HasRole` middleware | `PermissionService::hasAnyRole()` + legacy `$user->is_admin` fallback for 'admin' role |
| `HasPermission` middleware | `$user->is_admin` bypass + `PermissionService::hasAnyPermission()` |
| `PermissionService::isAdmin()` | `$user->is_admin` OR role name === 'admin' |
| Vuex `isAdmin` getter | `state.user?.is_admin === true` |

### 9.2 Roles

| Role | Name | Level | Description |
|------|------|-------|-------------|
| User | `user` | 0 | Regular user with access to their own data |
| Support | `support` | 50 | Support staff with limited admin capabilities |
| Admin | `admin` | 100 | Full system administrator |

### 9.3 Permissions

| Permission | Display Name | Category |
|------------|-------------|----------|
| `users.view` | View Users | users |
| `users.edit` | Edit Users | users |
| `users.delete` | Delete Users | users |
| `users.impersonate` | Impersonate Users | users |
| `admin.access` | Access Admin Panel | admin |
| `admin.audit.view` | View Audit Logs | admin |
| `admin.tax_config` | Manage Tax Configuration | admin |
| `admin.erasure_process` | Process Erasure Requests | admin |
| `settings.view` | View Settings | settings |
| `settings.edit` | Edit Settings | settings |

### 9.4 Default Role Permission Assignments

| Role | Permissions |
|------|------------|
| **User** | (none assigned by default) |
| **Support** | `users.view`, `admin.access`, `admin.audit.view` |
| **Admin** | ALL permissions |

---

## 10. Multi-Factor Authentication (MFA)

### 10.1 Setup Flow

1. User opens Settings -> MFA section
2. `POST /api/auth/mfa/setup` -> generates secret, returns QR code data URI
3. User scans QR code with authenticator app (Google Authenticator, Authy, etc.)
4. User enters 6-digit TOTP code
5. `POST /api/auth/mfa/verify-setup` -> verifies code, enables MFA, returns 10 recovery codes
6. User saves recovery codes securely

### 10.2 Login Verification

1. After successful credential check, `MFAController::generateChallengeToken(userId)` stores `{user_id, created_at}` in Laravel Cache
2. Client receives `mfa_token` (64-char random string)
3. Client sends TOTP code + `mfa_token` to `POST /api/auth/mfa/verify`
4. Server validates challenge token (one-time use, retrieved + deleted from cache), decrypts secret, verifies TOTP
5. On success: creates Sanctum token + session

### 10.3 Recovery Codes

- **Format:** `XXXX-XXXX-XXXX` (uppercase alphanumeric, 12 chars + dashes)
- **Count:** 10 codes generated
- **Storage:** Each individually bcrypt hashed in `users.mfa_recovery_codes` (JSON array)
- **Verification:** Iterates all hashed codes, `Hash::check()` each (slow by design)
- **Consumption:** Matched code is removed from the array; remaining codes saved
- **Regeneration:** Requires current password; replaces entire set

### 10.4 Disabling MFA

- Requires current password verification
- Clears: `mfa_enabled`, `mfa_secret`, `mfa_recovery_codes`, `mfa_confirmed_at`
- Audit logged: `mfa_disabled`

### 10.5 TOTP Parameters

- **Library:** PragmaRX\Google2FA
- **Secret length:** 32 characters
- **Algorithm:** SHA1 (TOTP standard)
- **Window tolerance:** 2 (allows 2 periods before/after current)
- **Period:** 30 seconds (default)
- **QR Code:** SVG via bacon/bacon-qr-code, 300x300px, returned as base64 data URI

---

## 11. Rate Limiting & Lockout

### 11.1 Named Rate Limiters

Defined in `app/Providers/RouteServiceProvider.php`:

| Limiter | Limit | Key |
|---------|-------|-----|
| `api` | 300/min (prod), 1000/min (local) | User ID or IP |
| `auth` | 5/min | IP address |
| `export` | 3/hour | User ID or IP |
| `sensitive` | 3/min | User ID or IP |
| `bug-reports` | 5/hour | User ID or IP |

### 11.2 Per-Route Throttling

| Route | Throttle |
|-------|----------|
| `POST /api/auth/register` | `5,1` (5/min) |
| `POST /api/auth/login` | `5,1` |
| `POST /api/auth/verify-code` | `10,1` |
| `POST /api/auth/resend-code` | `5,1` |
| `POST /api/auth/logout-beacon` | `10,1` |
| `POST /api/auth/mfa/verify` | `10,1` |
| `POST /api/auth/mfa/recovery` | `5,1` |
| `POST /api/auth/password-reset/request` | `3,1` |
| `POST /api/auth/password-reset/verify-email` | `10,1` |
| `POST /api/auth/password-reset/resend-code` | `5,1` |
| `POST /api/auth/password-reset/verify-mfa` | `10,1` |
| `POST /api/auth/password-reset/mfa-recovery` | `5,1` |
| `POST /api/auth/password-reset/reset` | `5,1` |
| `POST /api/auth/change-password` | `5,1` |
| `POST /api/preview/login/{id}` | `10,1` |
| `POST /api/preview/switch/{id}` | `20,1` |
| GDPR erasure routes | `throttle:sensitive` (3/min) |
| GDPR export | `throttle:export` (3/hour) |

### 11.3 Application-Level Lockout

**Progressive lockout** (per-user, stored on `users` table):

| Failed Attempts | Lockout Duration |
|-----------------|-----------------|
| 3 | 1 minute |
| 5 | 5 minutes |
| 10 | 30 minutes |
| 15+ | 24 hours |

**IP-based lockout:** 50 failed attempts per hour from same IP (checked via `login_attempts` table).

**Reset:** Counters reset to zero on successful login.

**Logging:** Lockouts of 30+ minutes trigger `\Log::warning` with email, failed count, locked_until, and IP.

---

## 12. Session Management

### 12.1 Server-Side Session (Laravel)

**File:** `config/session.php`

| Setting | Value |
|---------|-------|
| Driver | `file` (stored in `storage/framework/sessions`) |
| Lifetime | 120 minutes |
| Encryption | Enabled (`SESSION_ENCRYPT=true`) |
| HTTP Only | `true` (no JS access to session cookie) |
| Same-Site | `lax` |
| Secure | From `SESSION_SECURE_COOKIE` env |
| Expire on Close | `false` |
| Cookie Name | `laravel_session` |
| Domain | From `SESSION_DOMAIN` env |

Note: Server-side sessions are primarily used for the web middleware group (SPA catch-all route). API authentication uses Sanctum tokens, not sessions.

### 12.2 Sanctum Token Expiration

| Setting | Value |
|---------|-------|
| Expiration | 480 minutes (8 hours) via `SANCTUM_TOKEN_EXPIRATION` env |
| Prefix | Empty |
| Abilities | Always `["*"]` |

### 12.3 Frontend Session Handling

| Mechanism | Detail |
|-----------|--------|
| Token storage | `sessionStorage` (key: `auth_token`) |
| Legacy cleanup | `localStorage` cleared on `setToken()` and `clearAuth()` |
| Inactivity timeout | 15 minutes (sessionLifecycleService.js) |
| Browser/tab close | `sessionStorage` auto-clears (no explicit handler) |
| Page refresh | Token persists (sessionStorage survives refresh) |
| Beacon logout | `navigator.sendBeacon` with token in JSON body on beforeunload |

### 12.4 User-Visible Session Management

Users can view and manage their active sessions via the Settings page:

- **View all sessions:** Device name, IP, last activity time, current session indicator
- **Revoke specific session:** Cannot revoke current session (must use logout)
- **Revoke all others:** Keeps current session, revokes everything else
- All revocations are audit logged

---

## 13. Password Security

### 13.1 Hashing Configuration

**File:** `config/hashing.php`

| Setting | Value |
|---------|-------|
| Algorithm | bcrypt |
| Rounds | 12 (from `BCRYPT_ROUNDS` env) |

### 13.2 Password Complexity Requirements

Enforced by regex: `/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).+$/`

- Minimum 8 characters
- At least one lowercase letter
- At least one uppercase letter
- At least one digit
- At least one special character
- Must be `confirmed` (password_confirmation field) for registration
- Must be `different` from current password on change

### 13.3 Where Hashing Occurs

| Context | Method |
|---------|--------|
| User model `password` field | Cast `'hashed'` (auto-hash on set via Eloquent) |
| PendingRegistration `password` field | Cast `'hashed'` (auto-hash on set) |
| AuthController register | `Hash::make($request->password)` (explicit, before PendingRegistration) |
| AuthController changePassword | `Hash::make($request->new_password)` |
| PasswordResetService resetPassword | `Hash::make($newPassword)` |
| MFA recovery codes | Individual `Hash::make()` per code |
| MFA secret | `Crypt::encryptString()` (AES-256-CBC encryption, NOT hashing) |

### 13.4 Must-Change-Password Feature

- `users.must_change_password` boolean field
- When `true`, the frontend shows `ChangePasswordModal` with `isRequired` prop (cannot be dismissed)
- Cleared (set to `false`) on password change or password reset

---

## 14. Preview User System

### 14.1 Overview

Preview users (`is_preview_user = true`) are seeded test personas that demonstrate the application without requiring registration. They are completely isolated from real user data.

### 14.2 Available Personas

| Persona ID | Users | Focus |
|------------|-------|-------|
| `young_family` | James & Emily Carter | Mortgage, workplace pensions |
| `peak_earners` | David & Sarah Mitchell | Multiple properties, SIPP + NHS pension |
| `widow` | Margaret Thompson | Estate planning |
| `entrepreneur` | Alex Chen | SIPP, business interests |
| `young_saver` | John Morgan | Emergency fund, first-time savings |
| `retired_couple` | Robert & Patricia Williams | Decumulation, estate planning |

### 14.3 PreviewWriteInterceptor

**File:** `app/Http/Middleware/PreviewWriteInterceptor.php` (166 lines)

**Position in stack:** Runs as part of the API middleware group, AFTER SanitizeInput but effectively BEFORE `auth:sanctum` (it manually resolves the user from the Bearer token).

**Behaviour:**
1. Resolves user from Bearer token via `PersonalAccessToken::findToken()`
2. If not a preview user or read method (GET/HEAD/OPTIONS): pass through
3. Check excluded routes and patterns: pass through
4. For write operations (POST/PUT/PATCH/DELETE): return fake success response

**Excluded Routes** (work normally even for preview users):

| Route | Reason |
|-------|--------|
| `api/preview/exit` | Allow exiting preview |
| `api/preview/switch` | Allow switching personas |
| `api/auth/login` | Allow real login with stale preview token |
| `api/auth/logout` | Allow logout |
| `api/auth/logout-beacon` | Beacon logout for browser close |
| `api/auth/register` | Allow preview users to create real accounts |
| `api/auth/verify-code` | Required for registration verification |
| `api/auth/resend-code` | Required for registration verification |
| `api/auth/forgot-password` | Allow password reset |
| `api/auth/reset-password` | Allow password reset |
| `api/onboarding` | Allow onboarding |
| `api/documents/upload` | Allow document upload & AI extraction |
| `api/documents/upload-only` | Allow document upload without extraction |
| `api/webhooks/revolut` | Payment webhook |

**Excluded Patterns** (read-only POST endpoints):

| Pattern | Reason |
|---------|--------|
| `/calculate` | All calculation endpoints |
| `/calculate-` | Hyphenated calculations |
| `/projections` | Projection endpoints |
| `/recalculate` | Risk profile recalculation |
| `/reprocess` | Document re-extraction |
| `/analyze` | Analysis endpoints |
| `/toggle-retirement` | Retirement inclusion toggle |

**Fake Success Response Format:**
```json
{
  "success": true,
  "message": "Preview: Record created (not saved)",
  "preview_mode": true,
  "preview_notice": "Changes are session-only and will be lost on refresh.",
  "data": { /* submitted data with fake ID for POST */ }
}
```

### 14.4 Preview Login

- Preview login via `PreviewController::login()` creates a Sanctum token directly (skips email verification and MFA)
- Preview users also skip email verification in the standard `AuthController::login()` flow
- Token has standard 8-hour expiry

---

## 15. CORS & CSRF

### 15.1 CORS Configuration

**File:** `config/cors.php`

| Setting | Value |
|---------|-------|
| Paths | `api/*`, `sanctum/csrf-cookie` |
| Allowed Methods | GET, POST, PUT, PATCH, DELETE, OPTIONS |
| Allowed Origins | Explicit list from `ALLOWED_ORIGINS` + `FRONTEND_URL` + `APP_URL` env (NO wildcards) |
| Allowed Origin Patterns | None |
| Allowed Headers | Accept, Authorization, Content-Type, X-Requested-With, X-XSRF-TOKEN |
| Exposed Headers | X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset |
| Max Age | 3600 seconds (1 hour preflight cache) |
| Supports Credentials | `true` |

### 15.2 CSRF Protection

- `VerifyCsrfToken` middleware is in the **web** middleware group
- All `api/*` routes are excluded (API uses Bearer token auth, not CSRF)
- Web routes (only the SPA catch-all `/{any}`) have CSRF protection

---

## 16. Input Sanitisation

**File:** `app/Http/Middleware/SanitizeInput.php`

Applied to ALL API requests (part of the `api` middleware group).

| Action | Detail |
|--------|--------|
| Trim whitespace | `trim()` on all string values |
| Strip HTML | `strip_tags()` on all string values |
| Exempt fields | `password`, `password_confirmation`, `current_password` |
| HTML allowed fields | None (configurable array, currently empty) |
| Recursive | Processes nested arrays |

---

## 17. Audit Logging

### 17.1 Auth Events Logged

| Action Constant | When Logged |
|-----------------|-------------|
| `login_attempt` | On login attempt (before verification) |
| `login_success` | After successful verification (email or MFA) |
| `login_failed` | On invalid credentials (includes email and reason) |
| `logout` | On normal and beacon logout |
| `mfa_enabled` | After MFA setup confirmed |
| `mfa_disabled` | After MFA disabled |
| `mfa_verified` | After MFA code verified during login |
| `password_changed` | After successful password change |
| `password_reset_requested` | When reset email sent |
| `password_reset_completed` | After password successfully reset |
| `session_revoked` | When a session is revoked |

### 17.2 What's Captured

Each audit log entry includes:
- `user_id` (nullable for failed login with non-existent user)
- `event_type` (auth, data_access, data_change, admin, gdpr)
- `action` (specific action string)
- `metadata` (JSON: IP address, auth method, failure reason, etc.)
- `ip_address`
- `user_agent`
- `created_at`

### 17.3 Retention

| Log Type | Retention |
|----------|-----------|
| Default | 90 days |
| GDPR-related | 2555 days (~7 years) |

---

## 18. GDPR & Consent

### 18.1 Consent Types

| Type | Version | Purpose |
|------|---------|---------|
| `terms` | v1.0 | Terms of service |
| `privacy` | v1.0 | Privacy policy |
| `marketing` | v1.0 | Marketing communications |
| `data_processing` | v1.0 | Data processing agreement |

### 18.2 Consent Tracking

- Each consent is versioned (allows re-consent on policy updates)
- Tracks: `consented_at`, `withdrawn_at`, IP address, user agent
- Full consent history available via API
- Unique constraint: `(user_id, consent_type, version)`

### 18.3 Data Rights

- **Right to Portability:** Data export endpoint with 3/hour rate limit
- **Right to Erasure:** Self-service 3-step flow (initiate -> verify -> execute) with verification code
- **Right to Access:** User profile and data accessible via standard API endpoints

---

## 19. Configuration Reference

### 19.1 `config/auth.php`

```php
'defaults' => ['guard' => 'web', 'passwords' => 'users']
'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']]
'providers' => ['users' => ['driver' => 'eloquent', 'model' => User::class]]
'passwords' => ['users' => ['expire' => 60, 'throttle' => 60]]
'password_timeout' => 10800  // 3 hours
'admin_emails' => // from ADMIN_EMAILS env
'lockout' => [
    'thresholds' => [3 => 1, 5 => 5, 10 => 30, 15 => 1440],
    'ip_max_attempts' => 50,
]
'audit' => ['retention_days' => 90, 'gdpr_retention_days' => 2555]
```

### 19.2 `config/sanctum.php`

```php
'stateful' => 'localhost,localhost:3000,localhost:5173,127.0.0.1,...'
'guard' => ['web']
'expiration' => 480  // 8 hours
'token_prefix' => ''
```

### 19.3 `config/session.php`

```php
'driver' => 'file'
'lifetime' => 120
'encrypt' => true
'http_only' => true
'same_site' => 'lax'
'secure' => env('SESSION_SECURE_COOKIE')
```

### 19.4 `config/hashing.php`

```php
'driver' => 'bcrypt'
'bcrypt' => ['rounds' => 12]
```

### 19.5 Key Environment Variables

| Variable | Purpose | Default |
|----------|---------|---------|
| `ADMIN_EMAILS` | Comma-separated admin emails | '' |
| `BCRYPT_ROUNDS` | Bcrypt cost factor | 12 |
| `SANCTUM_TOKEN_EXPIRATION` | Token TTL (minutes) | 480 |
| `SANCTUM_STATEFUL_DOMAINS` | Domains for stateful auth | localhost,... |
| `SESSION_DRIVER` | Session storage driver | file |
| `SESSION_LIFETIME` | Session TTL (minutes) | 120 |
| `SESSION_SECURE_COOKIE` | HTTPS-only cookies | false |
| `SESSION_ENCRYPT` | Encrypt session data | true |
| `ALLOWED_ORIGINS` | CORS allowed origins | '' |
| `FRONTEND_URL` | SPA URL for CORS | |
| `PAYMENT_ENABLED` | Enable payment/trial features | false |

---

## 20. Security Assessment

### 20.1 Strengths

| Feature | Assessment |
|---------|-----------|
| **Password hashing** | bcrypt with 12 rounds - industry standard, strong |
| **MFA implementation** | TOTP with proper secret encryption (AES-256-CBC) and bcrypt-hashed recovery codes |
| **Progressive lockout** | Well-designed escalating thresholds with IP-based protection |
| **Rate limiting** | Comprehensive per-route throttling on all auth endpoints |
| **Email verification** | Required for both login and registration (6-digit, 15-min expiry, max 2 resends) |
| **Account enumeration prevention** | Password reset always returns success; login returns generic "invalid email or password" |
| **Input sanitisation** | HTML stripping on all inputs (except passwords) |
| **CORS** | No wildcards; explicit origin allowlist |
| **Token storage** | `sessionStorage` (not `localStorage`) - auto-clears on browser close |
| **Audit logging** | Comprehensive logging of all auth events with IP and user agent |
| **Session management** | Users can view/revoke sessions; orphaned session cleanup |
| **Password reset security** | Multi-step with optional MFA verification; revokes all tokens on reset |
| **Challenge tokens** | MFA login uses one-time cache-based challenge tokens (not user ID in request) |
| **GDPR compliance** | Consent tracking, data export, self-service erasure |
| **Preview isolation** | Write operations intercepted with fake responses |

### 20.2 Vulnerabilities & Concerns

#### HIGH Priority

**1. PendingRegistration Never Expires**
- **File:** `app/Models/PendingRegistration.php`, `database/migrations/2026_01_02_171718_create_pending_registrations_table.php`
- **Issue:** No expiry mechanism on pending registrations. Records persist indefinitely until verified. No cleanup job.
- **Risk:** Database bloat from abandoned registrations. Potential for attackers to create unlimited pending registrations.
- **Recommendation:** Add `expires_at` field (e.g., 24 hours) and a scheduled cleanup job.

**2. Dual Admin System Inconsistency**
- **Files:** `IsAdmin.php`, `HasRole.php`, `HasPermission.php`, `PermissionService.php`
- **Issue:** `IsAdmin` middleware checks only `$user->is_admin` boolean. `PermissionService::isAdmin()` checks both `is_admin` and role. A user with admin role but `is_admin=false` would be blocked by `IsAdmin` middleware but pass `PermissionService::isAdmin()` checks.
- **Risk:** Inconsistent access control. Routes protected by `admin` middleware and routes checked via `PermissionService` may grant different access.
- **Recommendation:** Unify admin determination. Either deprecate `is_admin` boolean or update `IsAdmin` middleware to use `PermissionService::isAdmin()`.

#### MEDIUM Priority

**3. Beacon Logout Exposes Token in POST Body**
- **File:** `app/Http/Controllers/Api/AuthController.php:268-305`
- **Issue:** `logoutBeacon` receives the plaintext token in the POST body (necessary because `sendBeacon` cannot set headers). This means the token could appear in web server access logs if POST body logging is enabled.
- **Risk:** Token leakage via server logs. The token is about to be revoked, so the exposure window is small.
- **Recommendation:** Ensure server access logs do not capture POST bodies. Consider using a hash/fingerprint instead.

**4. No Re-Authentication for Session Viewing/Revocation**
- **File:** `app/Http/Controllers/Api/SessionController.php`
- **Issue:** Viewing all sessions and revoking other sessions do not require password re-entry. An attacker with a stolen token could enumerate all active sessions and revoke them.
- **Risk:** With a stolen token, attacker could lock out the legitimate user by revoking all their sessions.
- **Recommendation:** Require current password for `destroyOthers` action.

**5. Registration Code Has No Expiry**
- **File:** `app/Models/PendingRegistration.php`
- **Issue:** The verification code stored on `pending_registrations.verification_code` has no expiry. Unlike `email_verification_codes` (15-min expiry), the registration code is valid forever until used.
- **Risk:** Old registration codes remain valid indefinitely.
- **Recommendation:** Add expiry to pending registration verification codes, or switch to the `email_verification_codes` table for registration as well.

#### LOW Priority

**6. Recovery Code Verification Performance**
- **File:** `app/Services/Auth/MFAService.php:137-158`
- **Issue:** Verifying a recovery code iterates all 10 bcrypt hashes with `Hash::check()`. Each check takes ~250ms with 12 rounds, so worst case is ~2.5 seconds.
- **Risk:** UX issue (slow response), not a security issue.
- **Recommendation:** Consider using HMAC-SHA256 instead of bcrypt for recovery codes (they're already high-entropy random strings).

**7. No Token Refresh Mechanism**
- **Issue:** Tokens expire after 8 hours with no refresh. Users must re-authenticate.
- **Risk:** No security risk. UX consideration only. Frontend inactivity timeout (15 min) means most users never hit the 8-hour limit.

**8. Session File Driver**
- **File:** `config/session.php`
- **Issue:** File-based session storage. Not suitable for horizontal scaling.
- **Risk:** None for current single-server setup. Would need Redis/database driver for multi-server.

**9. CORS X-XSRF-TOKEN Header Allowed**
- **File:** `config/cors.php`
- **Issue:** `X-XSRF-TOKEN` is in allowed headers but CSRF is disabled for all API routes.
- **Risk:** No security risk. Cosmetic cleanup only.

**10. AuthServiceProvider Is Empty**
- **File:** `app/Providers/AuthServiceProvider.php`
- **Issue:** No policies or gates defined. All authorisation is middleware-based.
- **Risk:** No model-level authorisation checks. Users can potentially access other users' resources if controllers don't explicitly check ownership.
- **Note:** This is mitigated by controllers scoping queries to the authenticated user.

---

## 21. Recommended Improvements

### 21.1 Critical

1. **Add expiry to PendingRegistrations** - Add `expires_at` timestamp (24h), scheduled cleanup job
2. **Unify admin system** - Migrate `IsAdmin` middleware to use `PermissionService::isAdmin()` or deprecate the boolean flag

### 21.2 Important

3. **Add expiry to registration verification codes** - Either add `expires_at` to pending_registrations or use the `email_verification_codes` table
4. **Require re-authentication for session revocation** - Add password check to `destroyOthers`
5. **Clean up orphaned sessions** - Add a scheduled command to run `SessionService::cleanupOrphanedSessions()`

### 21.3 Nice to Have

6. **Token prefix** - Set `SANCTUM_TOKEN_PREFIX` for GitHub secret scanning detection
7. **Switch to Redis for cache/sessions** - Future-proof for scaling
8. **Rate limit the `/api/auth/user` endpoint** - Currently no per-route throttle
9. **Add CSP headers** - Content-Security-Policy headers for XSS mitigation
10. **Remove X-XSRF-TOKEN from CORS allowed headers** - Cosmetic cleanup
11. **Consider Argon2id** - Configured but not used; better resistance to GPU attacks than bcrypt
12. **Add security headers middleware** - X-Content-Type-Options, X-Frame-Options, Strict-Transport-Security

---

## File Reference

| File | Lines | Purpose |
|------|-------|---------|
| `app/Http/Controllers/Api/AuthController.php` | 648 | Main auth controller |
| `app/Http/Controllers/Api/MFAController.php` | 344 | MFA setup/verify |
| `app/Http/Controllers/Api/PasswordResetController.php` | 157 | Password reset |
| `app/Http/Controllers/Api/SessionController.php` | 96 | Session management |
| `app/Http/Controllers/Api/PreviewController.php` | - | Preview user management |
| `app/Http/Middleware/Authenticate.php` | 26 | Auth check (401 JSON) |
| `app/Http/Middleware/IsAdmin.php` | 30 | Admin check |
| `app/Http/Middleware/HasRole.php` | 49 | Role check |
| `app/Http/Middleware/HasPermission.php` | 49 | Permission check |
| `app/Http/Middleware/EnsureMFAVerified.php` | 44 | MFA session check |
| `app/Http/Middleware/SanitizeInput.php` | 100 | XSS prevention |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | 166 | Preview write interception |
| `app/Http/Kernel.php` | 77 | Middleware registration |
| `app/Models/User.php` | 619 | User model |
| `app/Models/EmailVerificationCode.php` | 148 | Verification codes |
| `app/Models/LoginAttempt.php` | 118 | Login attempt tracking |
| `app/Models/PendingRegistration.php` | 90 | Pending registrations |
| `app/Models/PasswordResetSession.php` | 192 | Password reset sessions |
| `app/Models/UserSession.php` | 162 | Active sessions |
| `app/Models/AuditLog.php` | 249 | Audit logging |
| `app/Models/Role.php` | 137 | RBAC roles |
| `app/Models/Permission.php` | 91 | RBAC permissions |
| `app/Models/UserConsent.php` | 135 | GDPR consents |
| `app/Services/Auth/LoginLockoutService.php` | 189 | Progressive lockout |
| `app/Services/Auth/MFAService.php` | 194 | MFA operations |
| `app/Services/Auth/SessionService.php` | 139 | Session CRUD |
| `app/Services/Auth/PasswordResetService.php` | 284 | Password reset flow |
| `app/Services/Auth/PermissionService.php` | 198 | RBAC service |
| `app/Services/Audit/AuditService.php` | - | Audit logging |
| `app/Services/Payment/TrialService.php` | - | Trial management |
| `app/Http/Requests/RegisterRequest.php` | - | Registration validation |
| `app/Http/Requests/LoginRequest.php` | - | Login validation |
| `resources/js/services/authService.js` | 250 | Frontend auth API |
| `resources/js/services/api.js` | 194 | API interceptors |
| `resources/js/services/sessionLifecycleService.js` | 121 | Inactivity timeout |
| `resources/js/store/modules/auth.js` | 174 | Vuex auth store |
| `resources/js/router/index.js` | 835 | Route guards |
| `config/auth.php` | 162 | Auth config |
| `config/sanctum.php` | 83 | Sanctum config |
| `config/session.php` | - | Session config |
| `config/cors.php` | 55 | CORS config |
| `config/hashing.php` | - | Hashing config |
| `routes/api.php` | 1020+ | API route definitions |
