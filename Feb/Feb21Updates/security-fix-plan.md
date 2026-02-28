# Security Fix Plan - 21 February 2026

Branch: `securityFix`

## Summary

Comprehensive security review identified **7 Critical**, **9 High**, **14 Medium**, and **16 Low** findings across 4 areas: Auth & Middleware, API Controllers, Models & Database, and Frontend & Config.

This plan covers all actionable fixes, grouped by priority.

---

## Phase 1: Critical Fixes

### 1.1 Encrypt Sensitive Fields in Models (Critical x4)

**Pattern:** Copy the encrypted accessor/mutator pattern from `SavingsAccount` to 4 models.

| Model | File | Fields to Encrypt |
|-------|------|-------------------|
| CashAccount | `app/Models/CashAccount.php` | `account_number`, `sort_code` |
| Mortgage | `app/Models/Mortgage.php` | `mortgage_account_number` |
| InvestmentAccount | `app/Models/Investment/InvestmentAccount.php` | `account_number` |
| FamilyMember | `app/Models/FamilyMember.php` | `national_insurance_number` |

**Implementation:** Add `Illuminate\Database\Eloquent\Casts\Attribute` and `Illuminate\Support\Facades\Crypt` imports, then add encrypted accessor/mutator for each field using the `Attribute::make(get/set)` pattern.

**Migration needed:** Create an artisan command to re-encrypt existing plaintext data in-place. Run once after deployment.

### 1.2 Add MFA Failure Counting (Critical)

**File:** `app/Http/Controllers/Api/MFAController.php`

- In `verify()`, after failed TOTP check, call `LoginLockoutService::recordFailedAttempt()` with `LoginAttempt::REASON_INVALID_MFA`
- Add new reason constant `REASON_INVALID_MFA` to `LoginAttempt` model if not present

### 1.3 Add Verification Code Attempt Counters (Critical x2)

**Registration flow:**
- **File:** `app/Http/Controllers/Api/AuthController.php` (verifyCode method)
- **Migration:** Add `verification_attempts` integer column (default 0) to `pending_registrations` table
- After 5 failed attempts, delete the pending registration and return error
- Increment `verification_attempts` on each failed check

**Login verification flow:**
- **File:** `app/Models/EmailVerificationCode.php` (findValidCode method)
- **Migration:** Add `failed_attempts` integer column (default 0) to `email_verification_codes` table
- Filter by `where('failed_attempts', '<', 5)` in `findValidCode()`
- Increment `failed_attempts` on mismatch in `AuthController::verifyCode()`

---

## Phase 2: High Fixes

### 2.1 Add `$hidden` to Sensitive Models (High x4)

| Model | File | Fields to Hide |
|-------|------|----------------|
| LetterToSpouse | `app/Models/LetterToSpouse.php` | `password_manager_info`, `bank_accounts_info`, `cryptocurrency_info`, `investment_accounts_info` |
| Payment | `app/Models/Payment.php` | `revolut_payment_data`, `revolut_order_id` |
| PasswordResetSession | `app/Models/PasswordResetSession.php` | `token`, `email_code` |
| EmailVerificationCode | `app/Models/EmailVerificationCode.php` | `code` |

### 2.2 Fix Account Enumeration (High)

**File:** `app/Http/Controllers/Api/AuthController.php` (register method)

- When email already exists, return the same 201 response as a successful registration ("Please check your email for verification code") instead of a distinct error
- Don't send an actual email to existing users

### 2.3 Replace User ID with Challenge Token in Login Flow (High)

**File:** `app/Http/Controllers/Api/AuthController.php`

- In `login()`, replace `'user_id' => $user->id` with a random challenge token stored in cache
- In `verifyCode()` and `resendCode()`, resolve `user_id` from the challenge token via `Cache::get()`
- Token expiry: 15 minutes (matches code lifetime)

### 2.4 Remove `unsafe-eval` from CSP (High)

**Files:**
- `app/Http/Middleware/SecurityHeaders.php` (line 37)
- `deploy/fynla-org/.htaccess` (line 62)

Remove `'unsafe-eval'` from `script-src`. Test that Vue + ApexCharts still work after removal. If ApexCharts requires eval, investigate alternatives or document the exception.

### 2.5 Add HSTS and CSP to csjones.co Deployment (High)

**File:** `deploy/csjones-fynla/.htaccess`

Add:
```apache
Header set Strict-Transport-Security "max-age=31536000; includeSubDomains"
Header set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; font-src 'self' data:; frame-ancestors 'none';"
```

### 2.6 Add Missing Environment Variables to `.env.example` (High)

**File:** `.env.example`

Add:
```
SANCTUM_STATEFUL_DOMAINS=fynla.org,www.fynla.org
ALLOWED_ORIGINS=https://fynla.org,https://www.fynla.org
FRONTEND_URL=https://fynla.org
SESSION_SECURE_COOKIE=true
```

### 2.7 Default `SESSION_SECURE_COOKIE` to `true` (High)

**File:** `config/session.php` (line 171)

Change: `env('SESSION_SECURE_COOKIE')` to `env('SESSION_SECURE_COOKIE', true)`

---

## Phase 3: Medium Fixes

### 3.1 Add MFA Fields to User `$guarded` (Medium)

**File:** `app/Models/User.php`

Add to `$guarded` array: `mfa_secret`, `mfa_enabled`, `mfa_recovery_codes`, `mfa_confirmed_at`

### 3.2 Switch Subscription to `$fillable` (Medium)

**File:** `app/Models/Subscription.php`

Replace `$guarded = ['id']` with explicit `$fillable` listing only the fields that should be mass-assignable.

### 3.3 Add `Permissions-Policy` Header (Medium)

**Files:**
- `app/Http/Middleware/SecurityHeaders.php`
- `deploy/fynla-org/.htaccess`
- `deploy/csjones-fynla/.htaccess`

Add: `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), bluetooth=()`

### 3.4 Remove `localStorage` Token Fallback (Medium)

**File:** `resources/js/services/api.js` (line 66-67)

- Remove `|| localStorage.getItem('auth_token')` fallback
- Add one-time cleanup in `resources/js/app.js` to remove legacy `auth_token` from `localStorage`

### 3.5 Fix PreviewWriteInterceptor Route Matching (Medium)

**File:** `app/Http/Middleware/PreviewWriteInterceptor.php`

Change `str_starts_with()` to exact match with trailing delimiter:
```php
if ($currentPath === $excludedRoute || str_starts_with($currentPath, $excludedRoute . '/')) {
```

### 3.6 Add Webhook Rate Limiting (Medium)

**File:** `routes/api.php` (line 947)

Add `throttle:30,1` middleware to the Revolut webhook route.

### 3.7 Set `expire_on_close` to `true` (Medium)

**File:** `config/session.php` (line 36)

Change `'expire_on_close' => false` to `'expire_on_close' => true`

### 3.8 Explicitly Disable Source Maps (Medium)

**File:** `vite.config.js`

Add `sourcemap: false` to the `build` configuration.

### 3.9 Add Explicit `$guarded` to ActuarialLifeTable (Medium)

**File:** `app/Models/ActuarialLifeTable.php`

Add `protected $guarded = ['id'];`

### 3.10 Clean Up Debug Console Logs (Medium)

Remove debug `console.log` statements from:
- `resources/js/components/Retirement/RetirementIncomeTab.vue` (lines 863-892)
- `resources/js/components/Coordination/BalanceSheetTab.vue` (line 402)

### 3.11 Add `exercise_history_json` to InvestmentAccount Casts (Medium)

**File:** `app/Models/Investment/InvestmentAccount.php`

Add `'exercise_history_json' => 'array'` to `$casts`.

---

## Phase 4: Low Fixes

### 4.1 Generic Error Messages for Role/Permission Middleware (Low)

**Files:**
- `app/Http/Middleware/HasRole.php` (line 40)
- `app/Http/Middleware/HasPermission.php` (line 40)

Change to: `'message' => 'Access denied. Insufficient permissions.'`

### 4.2 Add `$hidden` to LoginAttempt (Low)

**File:** `app/Models/LoginAttempt.php`

Add `protected $hidden = ['ip_address', 'user_agent'];`

### 4.3 Add MFA Fields to Auditable Default Exclusions (Low)

**File:** `app/Traits/Auditable.php` (line 91)

Add `'mfa_secret'` and `'mfa_recovery_codes'` to the `$defaults` array.

### 4.4 Mask Email in Registration Logs (Low)

**File:** `app/Http/Controllers/Api/AuthController.php` (lines 72-75)

Replace `'email' => $pending->email` with `'email_masked' => $this->maskEmail($pending->email)`

### 4.5 Reduce Bug Report Console Logs Max Size (Low)

**File:** `app/Http/Requests/StoreBugReportRequest.php` (or equivalent)

Reduce `console_logs` max from 50,000 to 10,000 characters.

---

## Files Changed Summary

| Category | Files |
|----------|-------|
| Models (encryption + $hidden) | `CashAccount.php`, `Mortgage.php`, `InvestmentAccount.php`, `FamilyMember.php`, `LetterToSpouse.php`, `Payment.php`, `PasswordResetSession.php`, `EmailVerificationCode.php`, `User.php`, `Subscription.php`, `ActuarialLifeTable.php`, `LoginAttempt.php` |
| Auth flow | `AuthController.php`, `MFAController.php`, `LoginAttempt.php` |
| Middleware | `SecurityHeaders.php`, `PreviewWriteInterceptor.php`, `HasRole.php`, `HasPermission.php` |
| Config | `session.php`, `.env.example`, `vite.config.js` |
| Deployment | `deploy/fynla-org/.htaccess`, `deploy/csjones-fynla/.htaccess` |
| Frontend | `api.js`, `app.js`, `RetirementIncomeTab.vue`, `BalanceSheetTab.vue` |
| Routes | `routes/api.php` |
| Traits | `Auditable.php` |
| Migrations (new) | `add_verification_attempts_to_pending_registrations`, `add_failed_attempts_to_email_verification_codes` |

---

## Not Included (Requires Separate Planning)

| Issue | Reason |
|-------|--------|
| Nonce-based CSP (replace `unsafe-inline`) | Requires Vite config changes and blade template updates; larger effort |
| Token rotation / reduced expiry | UX impact needs discussion; currently 8hrs with 15min frontend timeout |
| reCAPTCHA on preview login | Third-party integration; needs product decision |
| Data re-encryption migration | Requires careful artisan command to encrypt existing plaintext data; separate deploy step |
| SanitizeInput strengthening | Adding `htmlspecialchars` may break legitimate input; needs testing |
| Console capture sanitisation | Needs design decision on what to redact |

---

## Testing Required

After all fixes:
1. `./vendor/bin/pest` - All existing tests pass
2. `./vendor/bin/pest --testsuite=Architecture` - Architecture tests pass
3. Manual test: login flow (email verification still works with challenge token)
4. Manual test: MFA flow (lockout triggers after 5 failed attempts)
5. Manual test: registration flow (attempt counter resets on new registration)
6. Manual test: preview mode still works
7. Manual test: CashAccount, Mortgage, InvestmentAccount CRUD (encrypted fields read/write correctly)
8. Build test: `./deploy/fynla-org/build.sh` succeeds with CSP changes
