# Security Fixes - Deployment Notes

**Date:** 21 February 2026
**Branch:** `securityFix`
**Status:** DEPLOYED TO PRODUCTION (22 February 2026)

---

## Phase 1: Critical Fixes (COMPLETE)

### Changes Summary

| # | Fix | What Changed |
|---|-----|-------------|
| 1.1 | Encrypt CashAccount fields | `account_number` and `sort_code` encrypted at rest via `Crypt::encryptString` accessors |
| 1.2 | Encrypt Mortgage field | `mortgage_account_number` encrypted at rest |
| 1.3 | Encrypt InvestmentAccount field | `account_number` encrypted at rest |
| 1.4 | Encrypt FamilyMember field | `national_insurance_number` encrypted at rest, added to `$hidden` |
| 1.5 | MFA failure counting | `MFAController` now records failed attempts via `LoginLockoutService`, locks after threshold |
| 1.6 | Registration verification attempt counter | New `verification_attempts` column on `pending_registrations`, deletes after 5 failures |
| 1.7 | Login verification attempt counter | New `failed_attempts` column on `email_verification_codes`, blocks after 5 failures |

### Files to Upload

#### New PHP Files

```
database/migrations/2026_02_21_200005_add_verification_attempt_counters.php
```

#### Modified PHP Files

```
app/Models/CashAccount.php
app/Models/Mortgage.php
app/Models/Investment/InvestmentAccount.php
app/Models/FamilyMember.php
app/Models/EmailVerificationCode.php
app/Models/PendingRegistration.php
app/Http/Controllers/Api/MFAController.php
app/Http/Controllers/Api/AuthController.php
```

---

## Phase 2: High Fixes (COMPLETE)

### Changes Summary

| # | Fix | What Changed |
|---|-----|-------------|
| 2.1 | Add `$hidden` to LetterToSpouse | Hides `password_manager_info`, `bank_accounts_info`, `cryptocurrency_info`, `investment_accounts_info` from serialisation |
| 2.2 | Add `$hidden` to Payment | Hides `revolut_payment_data`, `revolut_order_id` from serialisation |
| 2.3 | Add `$hidden` to PasswordResetSession | Hides `token`, `email_code` from serialisation |
| 2.4 | Fix account enumeration | Registration returns identical 201 response whether email exists or not. Removed `unique:users` from `RegisterRequest` validation. |
| 2.5 | Challenge token for login flow | Login email verification uses cache-backed challenge token instead of exposing raw `user_id`. Backwards compatible. |
| 2.6 | Remove `unsafe-eval` from CSP | Removed from `SecurityHeaders.php` (both local and production) and `fynla-org/.htaccess` |
| 2.7 | Add HSTS and CSP to csjones.co | Added `Strict-Transport-Security` and `Content-Security-Policy` headers to subdirectory `.htaccess` |
| 2.8 | Add missing env vars | Added `SANCTUM_STATEFUL_DOMAINS`, `ALLOWED_ORIGINS`, `FRONTEND_URL` to `.env.example` |
| 2.9 | Default `SESSION_SECURE_COOKIE` | Changed default from `null` to `true` in `config/session.php` |

### Files to Upload

#### Modified PHP Files

```
app/Models/LetterToSpouse.php
app/Models/Payment.php
app/Models/PasswordResetSession.php
app/Http/Controllers/Api/AuthController.php
app/Http/Requests/RegisterRequest.php
app/Http/Middleware/SecurityHeaders.php
config/session.php
```

#### Deployment Config Files

```
deploy/fynla-org/.htaccess
deploy/csjones-fynla/.htaccess
.env.example
```

---

## Phase 3: Medium Fixes (COMPLETE)

### Changes Summary

| # | Fix | What Changed |
|---|-----|-------------|
| 3.1 | ~~Guard MFA fields on User~~ | Skipped - MFA fields already in `$hidden` which prevents API exposure. Adding to `$guarded` broke tests and MFA service. |
| 3.2 | Switch Subscription to `$fillable` | Replace `$guarded = ['id']` with explicit `$fillable` listing 10 fields |
| 3.3 | Add `Permissions-Policy` header | Camera, microphone, geolocation, payment, USB, bluetooth all disabled |
| 3.4 | Remove `localStorage` token fallback | Remove legacy `localStorage.getItem('auth_token')` fallback from `api.js`, add cleanup in `app.js` |
| 3.5 | Fix PreviewWriteInterceptor route matching | Exact match with trailing delimiter to prevent prefix bypass |
| 3.6 | Add webhook rate limiting | `throttle:30,1` on Revolut webhook route |
| 3.7 | Set `expire_on_close` to `true` | Session cookies expire when browser closes |
| 3.8 | Disable source maps | Add `sourcemap: false` to Vite build config |
| 3.9 | Add `$guarded` to ActuarialLifeTable | Prevent mass assignment on reference data model |
| 3.10 | Clean up debug console logs | Remove `console.log` from `RetirementIncomeTab.vue` and `UserProfile/BalanceSheetTab.vue` |
| 3.11 | Add `exercise_history_json` cast | Add `'exercise_history_json' => 'array'` to InvestmentAccount casts |

### Files to Upload

#### Modified PHP Files

```
app/Models/User.php
app/Models/Subscription.php
app/Models/ActuarialLifeTable.php
app/Models/Investment/InvestmentAccount.php
app/Http/Middleware/SecurityHeaders.php
app/Http/Middleware/PreviewWriteInterceptor.php
config/session.php
routes/api.php
```

#### Frontend Files

```
resources/js/services/api.js
resources/js/app.js
resources/js/components/Retirement/RetirementIncomeTab.vue
resources/js/components/UserProfile/BalanceSheetTab.vue
```

#### Build Config

```
vite.config.js
```

#### Deployment Config Files

```
deploy/fynla-org/.htaccess
deploy/csjones-fynla/.htaccess
```

#### Rebuild Required: YES

Frontend files changed (`api.js`, `app.js`, two Vue components, `vite.config.js`).

```bash
./deploy/fynla-org/build.sh
```

Then upload:

```
public/build/
```

---

## Phase 4: Low Fixes (COMPLETE)

### Changes Summary

| # | Fix | What Changed |
|---|-----|-------------|
| 4.1 | Generic error messages for role/permission middleware | Change error messages to generic "Access denied. Insufficient permissions." |
| 4.2 | Add `$hidden` to LoginAttempt | Hide `ip_address`, `user_agent` from serialisation |
| 4.3 | Add MFA fields to audit exclusions | Add `mfa_secret`, `mfa_recovery_codes` to `Auditable` default exclusions |
| 4.4 | Mask email in registration logs | Replace raw email with masked version in log entries |
| 4.5 | Reduce bug report console logs max size | Reduce `console_logs` max from 50,000 to 10,000 characters |

### Files to Upload

#### Modified PHP Files

```
app/Http/Middleware/HasRole.php
app/Http/Middleware/HasPermission.php
app/Models/LoginAttempt.php
app/Traits/Auditable.php
app/Http/Controllers/Api/AuthController.php
app/Http/Controllers/Api/BugReportController.php
```

#### Rebuild Required: NO

No frontend files changed in Phase 4.

---

## Complete File List (All Phases Combined)

### New PHP Files

```
database/migrations/2026_02_21_200005_add_verification_attempt_counters.php
database/migrations/2026_02_22_130000_widen_encrypted_columns_to_text.php
```

### Modified PHP Files

```
app/Console/Commands/EncryptExistingData.php
app/Http/Controllers/Api/AuthController.php
app/Http/Controllers/Api/BugReportController.php
app/Http/Controllers/Api/MFAController.php
app/Http/Middleware/HasPermission.php
app/Http/Middleware/HasRole.php
app/Http/Middleware/PreviewWriteInterceptor.php
app/Http/Middleware/SecurityHeaders.php
app/Http/Requests/RegisterRequest.php
app/Http/Controllers/Api/BugReportController.php
app/Models/ActuarialLifeTable.php
app/Models/CashAccount.php
app/Models/EmailVerificationCode.php
app/Models/FamilyMember.php
app/Models/Investment/InvestmentAccount.php
app/Models/LetterToSpouse.php
app/Models/LoginAttempt.php
app/Models/Mortgage.php
app/Models/Payment.php
app/Models/PasswordResetSession.php
app/Models/PendingRegistration.php
app/Models/Subscription.php
app/Models/User.php
app/Traits/Auditable.php
config/session.php
routes/api.php
```

### Frontend Files

```
resources/js/app.js
resources/js/services/api.js
resources/js/components/Retirement/RetirementIncomeTab.vue
resources/js/components/UserProfile/BalanceSheetTab.vue
```

### Build / Deployment Config

```
vite.config.js
deploy/fynla-org/.htaccess
deploy/csjones-fynla/.htaccess
.env.example
```

### Rebuild Required: YES (Phase 3)

```bash
./deploy/fynla-org/build.sh
```

Then upload:

```
public/build/
```

---

## Database Migrations Required: YES

Two migrations must run **before** clearing caches:

```bash
php artisan migrate --force
```

This adds:
- `verification_attempts` column to `pending_registrations`
- `failed_attempts` column to `email_verification_codes`
- Widens `national_insurance_number`, `sort_code`, `account_number`, `mortgage_account_number` columns to `TEXT` (required for encrypted values)

---

## Post-Upload Commands

Run in order after uploading all files:

```bash
php artisan migrate --force
php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan route:clear && php artisan optimize
```

---

## Production .env Changes Required

Add/verify these values in the production `.env` file:

```
SANCTUM_STATEFUL_DOMAINS=fynla.org,www.fynla.org
ALLOWED_ORIGINS=https://fynla.org,https://www.fynla.org
FRONTEND_URL=https://fynla.org
SESSION_SECURE_COOKIE=true
```

---

## Data Re-encryption (Separate Step)

After deployment, existing plaintext data in encrypted columns needs re-encryption. The `data:encrypt` artisan command (`app/Console/Commands/EncryptExistingData.php`) handles this.

**Fields added in this security fix:**

| Model | Fields |
|-------|--------|
| CashAccount | `account_number`, `sort_code` |
| Mortgage | `mortgage_account_number` |
| InvestmentAccount | `account_number` |
| FamilyMember | `national_insurance_number` |

**Important:** The column-widening migration (`2026_02_22_130000`) must run before encryption. Encrypted values are ~200 characters and won't fit in `varchar(10)` or `varchar(13)` columns.

**Step 1: Dry run** (shows what would be encrypted without making changes):

```bash
php artisan data:encrypt --dry-run
```

**Step 2: Encrypt all models:**

```bash
php artisan data:encrypt
```

The `--model=` flag can be used to resume from a specific model if the command fails partway through (e.g. `php artisan data:encrypt --model=Mortgage`).

The command:

- Processes records in batches of 100 (configurable with `--batch=50`)
- Skips values that are already encrypted (detects base64 JSON prefix)
- Uses `saveQuietly()` to bypass model events (prevents the `Auditable` trait from calling `getOriginal()` on encrypted accessors with plaintext data, which would throw "The payload is invalid")
- Fixed `InvestmentAccount` namespace (`App\Models\Investment\InvestmentAccount`) and `Liability` namespace (`App\Models\Estate\Liability`)

**Important:** Run this after deployment and before users access the affected data, or they will see garbled values where plaintext was expected.

**File to upload:** `app/Console/Commands/EncryptExistingData.php`

---

## Manual Testing Checklist

After deployment:
- [ ] Login flow works (email verification with challenge token)
- [ ] MFA flow works (lockout triggers after repeated failures)
- [ ] Registration flow works (no error leak for existing emails)
- [ ] Preview mode still works
- [ ] CashAccount, Mortgage, InvestmentAccount CRUD (encrypted fields read/write)
- [ ] Vue + ApexCharts still work without `unsafe-eval`
- [ ] csjones.co deployment has security headers
- [ ] Session cookies are secure-only
