# Security Audit Report — Fynla

**Date:** 9 April 2026
**Codebase:** Fynla v0.9.4
**Auditor:** Claude Opus 4.6 + security-reviewer agent
**Scope:** Full application — authentication, authorization, input validation, data protection, payment security, GDPR, dependencies, file uploads, headers, rate limiting

---

## Executive Summary

The application has a **mature, well-layered security architecture** appropriate for a UK financial planning application. No critical or high severity issues were found. The audit identified **3 medium** and **5 low** severity findings. The most impactful improvement would be migrating from `unsafe-inline` CSP to nonce-based CSP.

| Severity | Count |
|----------|-------|
| Critical | 0 |
| High | 0 |
| Medium | 3 |
| Low | 5 |

---

## Findings

### MEDIUM-01: `unsafe-inline` in Content Security Policy

**File:** `app/Http/Middleware/SecurityHeaders.php:51`

The production CSP includes `'unsafe-inline'` in `script-src`. Required by the Revolut checkout SDK, but weakens XSS protection — any injected inline script would execute.

**Fix:** Migrate to nonce-based CSP. Generate a unique nonce per request, pass to Blade layout as `<script nonce="...">`, use `script-src 'nonce-{value}'`. If Revolut SDK doesn't support nonces, isolate it in a sandboxed iframe.

**Effort:** Medium

---

### MEDIUM-02: `national_insurance_number` in FamilyMember `$fillable`

**File:** `app/Models/FamilyMember.php:33`

NI number is in `$fillable`, allowing mass assignment. While encrypted at rest and hidden from serialisation, any controller passing unfiltered request data to `create()`/`update()` could write arbitrary NI numbers.

**Fix:** Remove from `$fillable`. Set explicitly in controller/service:
```php
$member->national_insurance_number = $validated['national_insurance_number'];
$member->save();
```

**Effort:** Small

---

### MEDIUM-03: `storage.js` defaults to `localStorage`

**File:** `resources/js/utils/storage.js:10-19`

The general-purpose storage utility defaults to `localStorage`. Auth tokens correctly use `sessionStorage` (via `tokenStorage.js`), but any component using `storage.get/set` for financial data would persist it beyond the session.

**Fix:** Audit all callers to ensure no financial/PII data uses this path. Consider switching default to `sessionStorage`.

**Effort:** Small

---

### LOW-04: `v-html` in PublicLayout with stage config data

**File:** `resources/js/layouts/PublicLayout.vue:73`

`v-html="stage.menuName || stage.name"` renders data as raw HTML. Currently safe (hardcoded config), but would be an XSS vector if data ever came from user input or API.

**Fix:** Verify data source is hardcoded. Add comment documenting this.

---

### LOW-05: `whereRaw` with allowlisted column name

**File:** `app/Agents/CoordinatingAgent.php:1988`

`->whereRaw('LOWER('.$nameField.') = ?', [...])` — column name from an explicit allowlist, value parameterised. Currently safe but fragile for future maintenance.

**Fix:** Add security comment documenting the allowlist requirement.

---

### LOW-06: MFA secret in setup response

**File:** `app/Http/Controllers/Api/MFAController.php:55`

Raw TOTP secret returned in JSON during MFA setup. Standard practice for TOTP flows (users need it for manual entry). HSTS enforced in production.

**Fix:** No change required. Consider adding audit log when MFA secret is generated.

---

### LOW-07: Legacy `localStorage` token cleanup

**File:** `resources/js/app.js:39`

`localStorage.removeItem('auth_token')` — migration cleanup from when tokens were in localStorage. Appropriate one-time cleanup.

**Fix:** Can be removed after 6 months.

---

### LOW-08: npm audit — 14 vulnerabilities in dev dependencies

14 npm vulnerabilities (3 moderate, 11 high) in build toolchain dependencies (Vite, Capacitor CLI, tar). These do not ship in the production bundle.

**Fix:** Run `npm audit fix` where possible. Track remaining in backlog.

---

## Clean Areas (No Issues Found)

### Authentication
- Passwords hashed with bcrypt via `Hash::make()`
- Sanctum tokens expire after 4 hours, prefixed with `fynla_` for leak detection
- Progressive login lockout per email and IP
- Email verification codes with attempt limits (5 max) and expiry
- TOTP MFA with recovery codes, `mfa_verified` token ability
- Session revocation with password re-authentication

### Authorization (IDOR)
- All controllers scope queries to authenticated user's ID
- `forUserOrJoint()` scope correctly handles joint ownership
- Spouse data access verified via `spouse_id` check
- Document and invoice downloads verify ownership
- Admin routes protected by `permission:admin.access`
- Preview user writes blocked by `PreviewWriteInterceptor`

### Input Validation
- 83 FormRequest classes across all endpoints
- `SanitizeInput` middleware strips HTML from all API inputs globally
- All `DB::raw()` / `whereRaw()` uses parameterised values or DDL only
- Zero `{!! !!}` unescaped Blade output
- `AiMessageContent.vue` sanitises AI responses through `escapeHtml()` + `sanitizeHtml()`

### Sensitive Data Protection
- User model `$hidden`: password, remember_token, mfa_secret, mfa_recovery_codes, failed_login_count, locked_until, national_insurance_number
- Financial models `$hidden`: account_number, sort_code, mortgage_account_number
- `Crypt::encryptString` for account numbers, sort codes, NI numbers
- `UserResource` returns only explicitly listed fields
- `SanitizedErrorResponse` returns generic messages in production
- Email addresses masked in login/verification responses
- No hardcoded secrets in source code

### Security Headers
- HSTS: 1-year max-age + includeSubDomains (production)
- X-Frame-Options: DENY
- X-Content-Type-Options: nosniff
- Referrer-Policy: strict-origin-when-cross-origin
- Cross-Origin-Opener-Policy: same-origin
- Permissions-Policy: camera, microphone, geolocation, USB, Bluetooth disabled
- CORS: explicit allowed origins from environment, no wildcards

### Rate Limiting
- Auth: `throttle:5,1` on login/register/resend, `throttle:10,1` on verify/MFA
- Payment: `throttle:10,1` on create-order/confirm, `throttle:1,1` on cancel
- GDPR: `throttle:sensitive` on erasure, `throttle:export` (3/hour) on exports
- Global API throttle via middleware group

### Payment Security (Revolut)
- API keys from config, not hardcoded
- Webhook HMAC signature verification before processing
- Order ownership verified (`user_id` match) before confirmation
- Amounts calculated server-side from `SubscriptionPlan` records
- Idempotency with `lockForUpdate()` in DB transactions

### GDPR Compliance
- Data export in JSON/CSV, rate-limited, ownership-verified
- Data erasure with multi-step verification (email code or MFA + confirmation phrase)
- Versioned consent records with history
- SoftDeletes on User and all financial models
- 49 models use Auditable trait

### File Uploads
- MIME validation: pdf, jpeg, jpg, png, webp, xlsx, xls, csv only (20MB limit)
- UUID-based filenames (ignores user-supplied names)
- Stored on local disk in `documents/{user_id}/`, not web-accessible
- Secondary MIME type check in `DocumentUploadService`

### Session Configuration
- Encrypted sessions (`encrypt => true`)
- Secure cookies: httpOnly, secure (HTTPS only), sameSite lax, expire on close

### Dependencies
- Composer: zero known vulnerabilities

---

## Recommended Action Plan

### Immediate (this week)
1. MEDIUM-02: Remove NI number from FamilyMember `$fillable` (10 min)

### Short-term (this month)
2. MEDIUM-03: Audit `storage.js` callers for financial data (1 hour)
3. LOW-08: Run `npm audit fix` (4-6 hours with testing)

### Backlog
4. MEDIUM-01: Migrate to nonce-based CSP (4-8 hours)
5. LOW-04/05: Add security comments to `v-html` usage and `whereRaw` allowlist (15 min)

---

*Generated 9 April 2026 — Full security audit*
