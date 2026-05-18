# Fynla Security Audit Report

Date: 12 May 2026  
Auditor: Claude (security reviewer)  
Scope: Full Laravel 10 + Vue 3 + MySQL 8 application (production https://fynla.org, dev https://csjones.co/fynla, iOS Capacitor)  
Branch / commit: main (clean working tree at audit time)  
Method: Static review of controllers, middleware, models, services, routes, configs, deploy templates, frontend Vue components, plus composer audit and npm audit.

---

## Executive summary

Fynla's security posture is above average for a Laravel SaaS at this stage. Authentication is deep — Sanctum bearer tokens, progressive lockout, email-MFA-or-TOTP, MFA-protected destructive operations, MFA-secret encryption via Crypt, recovery codes hashed at rest, login-IP throttling, audit logging, GDPR erasure with multi-step verification, HMAC-signed AI audit hash-chain, Revolut webhook HMAC verification with timing-safe hash_equals, timestamp tolerance, idempotency for AI writes, and a thoughtful PreviewWriteInterceptor. The team has clearly read OWASP and applied many defensive patterns (constant-time comparisons in critical paths, model $guarded flags on User, mass-assignment via $fillable everywhere I sampled, JsonResource projections, ownership scoping on most controllers, HTMLPurifier on CMS content, Vue v-html paired with DOMPurify and a sanitizeHtml helper, prompt-injection defence via UserContentSanitiser, CSP / HSTS / Permissions-Policy in middleware, CORS allowlist driven from env, admin database backup uses escapeshellarg + a credentials file, etc.).

That said the audit surfaces 6 high-severity issues, 17 medium, and a long tail of low / info findings. The standout exploitable items:

1. High — supply-chain CVEs in @capgo/capacitor-native-biometric (Auth Bypass — GHSA-vx5f-vmr6-32wf), axios <1.15.x (prototype-pollution / SSRF / CRLF / cred injection — 13 advisories), @babel/plugin-transform-modules-systemjs, tar, serialize-javascript, vite, fast-uri, postcss flagged by npm audit. composer audit reports zero advisories — backend dependency hygiene is good; frontend is not.
2. High — TokenRefreshController calls $currentToken->delete() without an instanceof PersonalAccessToken guard (app/Http/Controllers/Api/V1/Auth/TokenRefreshController.php:23). This is the 7th known site of the TransientToken::$id family bug documented in reference_transient_token_family_bugs.md; under SPA cookie auth (EnsureFrontendRequestsAreStateful is in the api group) currentAccessToken() returns a TransientToken whose delete() is a no-op, and the new bearer issued one line later is unused — token rotation silently fails. Cookie-auth and bearer-auth mobile clients can produce a stale-token leak.
3. High — X-Frame-Options set twice with conflicting values (public/.htaccess:64 → SAMEORIGIN, app/Http/Middleware/SecurityHeaders.php:23 → DENY). Per the documented feedback_htaccess_vs_middleware_headers.md rule, never set the same security header in both layers. Apache wins on SiteGround — the stricter middleware value is silently overridden, and the X-XSS-Protection: 1; mode=block header in .htaccess is now deprecated and can be harmful.
4. High — JSON 6-digit email codes use non-constant-time comparison (PasswordResetSession::email_code !== $code at app/Services/Auth/PasswordResetService.php:79, also app/Http/Controllers/Api/GDPRController.php:426/502/594 for session_token). Practical exploitability is limited by the 6-digit space (1M values) + per-session attempt cap, but the password reset email code is the linchpin of the reset flow and should use hash_equals.
5. High — Eval-only routes refuse-in-prod uses app()->environment('production'), but the production env literally has APP_ENV=staging on csjones (see deploy CLAUDE.md). A future operator misconfiguration that mints a bypass-preview-mode token on prod would unlock writes for all preview personas. Defence-in-depth is mostly present (the routes themselves are inside if (! app()->environment('production'))), but the second-layer guard inside EvalAuthController is the only one that runs if someone removes the route guard — confirm APP_ENV on prod is exactly production.
6. High — BugReportController always emails to chris@fynla.org and includes console_logs up to 10 KB of attacker-controlled content (app/Http/Controllers/Api/BugReportController.php:59). Unauthenticated users can submit, rate limit is 5/hour by IP. The console_logs field is nullable|string|max:10000; combined with unbounded description / user_agent / page_url strings reaching a Mail::send template, this is a phishing-vehicle / email-flood vector against the support inbox.

The rest of the audit fills out the OWASP top 10 and Fynla-specific concerns. The remaining medium findings cluster around (a) the SPA /storage/{path} route's path-traversal filter being too narrow (no %2e%2e filter, no backslash filter — relies on Laravel's Storage::disk('public')), (b) web middleware not regenerating sessions on successful login (session fixation risk for cookie flow), (c) admin backup restoreBackup running with a shell call against a writable storage/app/backups directory (mitigated by realpath check + filename regex, but still a privileged operation), (d) the AwinTracking inclusion of awc cookie content into outbound HTTP, (e) failed_login_count increments via $user->save() without an atomic UPDATE — race condition on concurrent failed attempts could under-count locks, and (f) the Goal::find($current) call in the circular-dependency traversal walks goals across users (read-only, but exposes existence of arbitrary goal IDs through timing).

---

## Severity counts

| Severity | Count |
|----------|-------|
| Critical | 0 |
| High | 6 |
| Medium | 17 |
| Low | 19 |
| Info | 11 |
| Total | 53 |

---

## A01 — Broken Access Control

### [HIGH][high-conf] A01-01 TokenRefreshController missing instanceof PersonalAccessToken guard

- File: app/Http/Controllers/Api/V1/Auth/TokenRefreshController.php:20-26
- OWASP: A01 Broken Access Control (auth bypass via stale token)
- Impact: Under SPA cookie auth (which the api middleware group enables via EnsureFrontendRequestsAreStateful), currentAccessToken() returns Laravel\Sanctum\TransientToken. Calling $currentToken->delete() on a TransientToken does NOT revoke any DB row — it's a no-op. The "new" bearer token is created and returned to the client but the OLD token (in personal_access_tokens for native mobile, or the session cookie) remains valid. Logout flows that depend on this refresh path leave both tokens active until natural expiry (30 days). This is the 7th site of the TransientToken::$id bug family documented in the project memory.
- Reproduction:
  1. Authenticate the mobile app via Sanctum cookie (e.g. during local dev where the SPA refreshes through the same /api/v1/auth/refresh-token route).
  2. Call POST /api/v1/auth/refresh-token.
  3. Observe that no row is deleted from personal_access_tokens for the old token — but $newToken is returned.
- Fix: Add an instanceof guard before calling delete(). If not a PersonalAccessToken (i.e. cookie-auth), return a 400 telling the client to use a personal access token for refresh. Also audit the rest of the family per reference_transient_token_family_bugs.md — AdminController::resolvePreviousLoginAt already guards correctly (line 128), but flag for any new consumers.

### [HIGH][med-conf] A01-02 Eval routes' second-layer guard depends on APP_ENV=production

- File: app/Http/Controllers/Api/EvalAuthController.php:39,89; routes/api.php:1345
- OWASP: A01 + A04 Insecure Design
- Impact: The eval routes mint Sanctum tokens with the bypass-preview-mode ability (EvalAuthController::login line 60). PreviewWriteInterceptor honours that ability when accompanied by X-Eval-Run-Id. The route registration is gated by `if (! app()->environment('production'))` in routes/api.php:1345 — that's the primary guard. The controller's own App::environment('production') check is the secondary guard. Both check the same string. Dev / staging environments have APP_ENV=staging; if production ever drifts to APP_ENV=staging (e.g. accidental .env copy from csjones), every preview persona's data can be mutated. Already-issued tokens carrying bypass-preview-mode would also bypass interception during the drift window.
- Fix: Add a positive allowlist instead of negative — only allow APP_ENV in ['local','testing','staging']. Also reject mints when request()->getHost() === 'fynla.org'. Optionally add a runtime config flag (fyn_eval.enabled = false in production) so even a misconfigured .env cannot enable it.
- Belt-and-braces: confirm prod .env has literal APP_ENV=production not staging, and add a CI check that production .env.production template has the exact value.

### [HIGH][high-conf] A01-03 Admin emails auto-promotion at login (lateral privilege)

- File: app/Http/Controllers/Api/AuthController.php:184-192
- OWASP: A01 Broken Access Control (privilege escalation on login)
- Impact: Any user whose email is listed in the ADMIN_EMAILS env variable gets promoted to admin role on the next login (is_admin = true, role_id = adminRole->id). If an attacker can register a fresh account with a .fynla.org email that's in the list (e.g. via a typo in ADMIN_EMAILS, or a deletion + restoration replay using an email that was once an admin), they become admin without any approval workflow. Worse — if a previously-admin user is removed from ADMIN_EMAILS but they're already is_admin=true, the `! $user->is_admin` short-circuit means demotion never happens automatically. Promotion is one-way.
- Reproduction: Add attacker@example.com to ADMIN_EMAILS. Register a new account with that email. Verify code. Log in. is_admin = true.
- Fix: Remove the auto-promotion-at-login path entirely. Admin promotion should be a deliberate admin action (existing admin promotes new admin via /admin/users UI). Keep the equivalent code in verifyCode (registration path, line 501-503) only if necessary — but consider gating that behind a "must confirm via existing admin" step.

### [MEDIUM][high-conf] A01-04 SPA /storage/{path} path-traversal filter incomplete

- File: routes/web.php:71-79
- OWASP: A01 / A03
- Impact: The filter blocks literal `..` via str_contains($path, '..'). It does NOT block:
  - URL-encoded %2e%2e (Laravel decodes route segments before delivering them, so .. would already be decoded — but defence-in-depth-wise the route should reject any encoded form upstream).
  - Backslash `..\..\` (irrelevant on Linux but could matter on macOS dev).
  - Symlinks pointing outside storage/app/public (mitigated by Storage::disk('public') scope, but worth pinning).
  Combined with Storage::disk('public')->exists($path) the practical attack surface is small — disk('public') is rooted in storage/app/public and cannot reach storage/app/, storage/framework/, or storage/logs/. But the comment in public/.htaccess:95 explicitly notes this scope assumption.
- Fix: Use a stricter regex that rejects any `..` sequence, encoded forms, null bytes, and control characters. Plus explicit allowlist of file extensions (.png, .jpg, .webp, .pdf).

### [MEDIUM][high-conf] A01-05 GDPR deletion session_token comparisons not constant-time

- File: app/Http/Controllers/Api/GDPRController.php:426,502,594
- OWASP: A01 + A02
- Impact: `$session['token'] !== $request->session_token` allows a timing oracle to recover the 64-char token. With 3 attempts per session and 64 chars × 16 (hex implicit via Str::random(64) base62) the practical attack is hard but not infeasible over a fast LAN.
- Fix: `! hash_equals($session['token'], $request->session_token)`.

### [MEDIUM][med-conf] A01-06 Goal::find($current) in circular-dependency traversal walks goals across users

- File: app/Http/Controllers/Api/GoalsController.php:757
- OWASP: A01 IDOR
- Impact: When addDependency runs, wouldCreateCircularDependency walks the dependency graph by calling Goal::find($current) without scoping to user_id. The depends-on goal IS scoped at line 666, but the recursive traversal reads any user's goal. Practically, the traversal only returns booleans (no data is exposed in the response) — but goal existence/non-existence can be inferred via timing. Low impact because dependency IDs are scoped at insert time.
- Fix: Pass user_id into the helper and scope: `Goal::where('id', $current)->where('user_id', $userId)->first()`.

### [MEDIUM][high-conf] A01-07 Admin auto-bypass for self via $user->is_admin in dashboard delta calculation

- File: app/Http/Controllers/Api/AdminController.php:121-140
- OWASP: A01 (informational)
- Impact: resolvePreviousLoginAt correctly handles TransientToken (line 128 — instanceof PersonalAccessToken), but the fallback path (line 139) returns the second-most-recent session for ANY user, not the calling admin's session specifically. Re-reading the code shows it's filtered to `where('user_id', $user->id)` at line 130, so the bug doesn't bite — but it's fragile if anyone modifies the query.
- Fix: Inline comment locking the filter, or use a more explicit query builder method.

### [LOW][med-conf] A01-08 PreviewWriteInterceptor excluded patterns include /recalculate$ for risk recalculation

- File: app/Http/Middleware/PreviewWriteInterceptor.php:95
- OWASP: A01 — preview write isolation
- Impact: `#/recalculate$#` is allowed because risk-profile recalculation writes back. This means POST /api/investment/risk/recalculate from a preview persona will trigger a real DB write of the risk profile for the preview user. Preview users' data is meant to be immutable; the comment ("read + write, needed for risk page") acknowledges this. As long as the preview seed is reset, this is acceptable, but documents an explicit carve-out from the rule.
- Fix: Audit RiskPreferenceController::recalculate to ensure it short-circuits when $user->is_preview_user === true and returns a fake response, OR confirm that the seed reset (php artisan preview:reset) covers risk profile mutations.

### [LOW][high-conf] A01-09 /api/auth/user returns 60/min throttle — endpoint hit on every page navigation

- File: routes/api.php:145
- OWASP: A01 — DOS via legitimate path
- Impact: 60 requests/min on /auth/user could be insufficient for a chatty SPA, especially if compensation for slow API responses spawns retries. Conversely, removing it makes credential-stuffing checks cheap.
- Fix: Keep at 60/min; document the limit; add fast-path caching (already partly done via ApiCacheHeaders).

### [INFO] A01-10 Admin can demote themselves

- File: app/Http/Controllers/Api/AdminController.php:259-315 (updateUser)
- OWASP: A01
- Impact: No safeguard prevents the only remaining admin from removing their own admin role via updateUser. The deleteUser path does check "last admin" (line 331-338) but updateUser does not. Practical impact: admin lock-out.
- Fix: In updateUser, if changing role_id away from admin role AND target is current user AND admin count is 1, refuse.

---

## A02 — Cryptographic Failures

### [HIGH][high-conf] A02-01 Email verification code comparison not constant-time

- File: app/Services/Auth/PasswordResetService.php:79 (`$session->email_code !== $code`); app/Http/Controllers/Api/AuthController.php:491 (`$pending->verification_code !== $request->code`); app/Http/Controllers/Api/MFAController.php:130-143 (challenge token); also EmailVerificationCode::findValidCode uses `where('code', $code)` which is fine (DB equality dominates timing).
- OWASP: A02 + A07
- Impact: 6-digit codes (1M space). With a sufficiently fast network, a timing oracle on the PHP !== comparison could in principle reduce the search space. Per-attempt limits (5 for EmailVerificationCode, 3 for PasswordResetSession) plus 15-min TTL plus 2-resend cap make practical exploitation very hard, but the convention should still be hash_equals everywhere.
- Fix: `! hash_equals((string)$session->email_code, (string)$code)`.

### [MEDIUM][med-conf] A02-02 mfa_secret encrypted with app Crypt — APP_KEY rotation breaks all MFA

- File: app/Services/Auth/MFAService.php:69,93
- OWASP: A02
- Impact: Crypt::encryptString($secret) uses APP_KEY. Rotating APP_KEY (e.g. as part of incident response) makes every existing MFA secret undecryptable — users get locked out on next MFA challenge. Recovery codes are hashed so users can still recover, but it's a sharp edge.
- Fix: Document key rotation procedure; consider using a dedicated MFA_SECRET_KEY env var passed to a separate Crypt::encryptStringWith call, or store the encryption-key id on the row so re-encryption is feasible.

### [MEDIUM][med-conf] A02-03 NI numbers stored in plaintext (users.national_insurance_number)

- File: app/Models/User.php:57 (in $hidden but not cast as encrypted); app/Models/FamilyMember.php:21
- OWASP: A02
- Impact: UK NI numbers are PII. They're excluded from JSON output via $hidden, but stored in plaintext in the DB and in any backup. A DB-credential leak (or the SiteGround mysqldump backup files in storage/app/backups/*.sql) exposes them.
- Fix: Add `'national_insurance_number' => 'encrypted'` cast on User and FamilyMember models. (Note: this requires a migration to re-encrypt existing rows.) Equally consider the same for address_line_1/address_line_2/postcode if you don't want full addresses leaking in a DB-dump scenario, though that's a much bigger surface.

### [MEDIUM][high-conf] A02-04 Admin backup files (*.sql) stored under storage/app/backups/ — directory not encrypted, files contain full DB

- File: app/Http/Controllers/Api/AdminController.php:385-448
- OWASP: A02 + A05
- Impact: Plaintext SQL dumps in a directory served only by Laravel filesystem operations (not by Apache directly — verified by absence of route). But on SiteGround shared hosting, an SSH-level compromise of the site user reads them. SQL dumps include hashed passwords, encrypted MFA secrets, NI numbers, full transaction history, etc. The dir is created with `mkdir($path, 0750, true)` (line 393) — group readable.
- Fix: Encrypt backup at write time (e.g. gpg --symmetric with a key from env, or openssl enc with a key stored separately from the DB). Reduce perms to 0700. Document key custody.

### [LOW][high-conf] A02-05 mfa_setup_secret cached in plaintext for 5 minutes

- File: app/Http/Controllers/Api/MFAController.php:51
- OWASP: A02
- Impact: During MFA setup, the TOTP secret is cached unencrypted in the configured cache store (file/Redis). File cache writes to storage/framework/cache/ — readable by the same user. If the cache store is shared or compromised, the setup secret can be read.
- Fix: `Cache::put("mfa_setup_secret:{$user->id}", Crypt::encryptString($secret), 300)` and decrypt on pull.

### [LOW][med-conf] A02-06 Session-encryption enabled but only because SESSION_ENCRYPT=true is implicit

- File: config/session.php:49
- OWASP: A02
- Impact: Session encryption default is true (good). Confirm .env does not override to false in any env.
- Fix: Lock SESSION_ENCRYPT=true in .env.production templates.

### [LOW][low-conf] A02-07 No HSTS in deploy .env requirement

- File: app/Http/Middleware/SecurityHeaders.php:27-29
- OWASP: A02
- Impact: HSTS is set only when app()->environment('production'). csjones runs as APP_ENV=staging — no HSTS there. Fine for dev but if you push to a real domain pre-prod, browsers won't get HSTS hints.
- Fix: Consider HSTS on staging too, or document the staging exemption.

### [LOW][high-conf] A02-08 cancelSubscription rate limit throttle:1,1 is per-IP, no per-user

- File: routes/api.php:1064
- OWASP: A02 — abuse of cancellation
- Impact: throttle:1,1 = 1 request per minute per route+IP. Multiple users behind NAT share the throttle, blocking legit cancellations.
- Fix: Use throttle:cancel-subscription with a per-user RateLimiter, e.g. 5/hr.

### [INFO] A02-09 Password complexity regex is reasonable but no breach-DB check

- File: app/Http/Requests/RegisterRequest.php:47-53
- OWASP: A02 / A07
- Impact: Password rule requires 1 upper, 1 lower, 1 digit, 1 special, min 8. No Have-I-Been-Pwned API integration. NIST 800-63B prefers a breach-DB check over complexity rules.
- Fix: Optional enhancement — integrate HIBP Pwned-Passwords k-anonymity API.

---

## A03 — Injection

### [LOW][high-conf] A03-01 whereRaw in CoordinatingAgent::checkForDuplicate

- File: app/Agents/CoordinatingAgent.php:3443
- OWASP: A03 SQL injection
- Impact: Already mitigated — the column name is checked against a hardcoded allowlist (line 3437). The value is parameterised. Risk is purely "future maintainer adds user input to $allowedColumns".
- Fix: Already adequate. The comment-warning at line 3435 is good; no change needed.

### [LOW][high-conf] A03-02 whereRaw in DuplicateAcknowledgement

- File: app/Services/AI/DuplicateAcknowledgement.php:382
- OWASP: A03
- Impact: `whereRaw("UPPER(REPLACE(postcode, ' ', '')) = ?", [$needle])` — column is hardcoded, value is parameterised. Safe.
- Fix: None.

### [LOW][med-conf] A03-03 Shell call in admin backup uses escapeshellarg properly

- File: app/Http/Controllers/Api/AdminController.php:413-421,540-548
- OWASP: A03 Command injection
- Impact: The mysqldump and mysql commands are built with escapeshellarg on every interpolation site. Database credentials are placed in a .my.cnf file (not on command line — defends against ps). The $filename comes from a validated regex (backup_[\d\-_]+\.sql) plus basename() plus realpath containment check (line 508-516). Safe.
- Fix: None — this is a well-implemented admin shell-out.

### [INFO] A03-04 No NoSQL / LDAP injection vectors

- OWASP: A03
- Impact: App is MySQL only — no NoSQL surface.

### [INFO] A03-05 XSS protection via Vue auto-escape + DOMPurify

- File: resources/js/utils/sanitizeHtml.js (regex-based, used in Insights blocks); package.json (dompurify ^3.4.0); HTMLPurifier on backend admin CMS HTML
- OWASP: A03 XSS
- Impact: The Vue templates use v-html exclusively with either (a) hardcoded content (PublicLayout.vue:74 — stage.menuName), (b) admin-only sanitised HTML via the HTMLBodySanitiser HTMLPurifier wrapper, (c) sanitizeHtml() helper for Insight blocks, or (d) DOMPurify in RichTextEditor.vue. The sanitizeHtml.js helper is a regex-based blocklist — fine for a defence-in-depth pass over server-trusted HTML, but NOT sufficient as the only XSS defence for fully-untrusted HTML. Today no untrusted HTML reaches those components, but if anyone adds user-rich-text-input that feeds a v-html, replace with DOMPurify.
- Fix: Migrate sanitizeHtml.js to use DOMPurify everywhere (dompurify is already in package.json).

---

## A04 — Insecure Design

### [HIGH][high-conf] A04-01 BugReportController unauthenticated submission with attacker-controlled email body

- File: app/Http/Controllers/Api/BugReportController.php:25-81; routes/api.php:1338
- OWASP: A04 + A09 (logging / monitoring abuse)
- Impact: Public endpoint (/api/bug-report is not behind auth:sanctum). The description (5k), expected_behaviour (2k), console_logs (10k), page_url, and user_agent are user-controlled strings sent verbatim to chris@fynla.org via Mail. Rate limit is 5/hour per user/IP. Attack vectors:
  - Mail-list flooding (5/hour from N IPs adds up).
  - HTML / script injection into the support email (if the BugReportMail template doesn't escape — confirm). If Blade renders the bug-report fields with `{!! !!}` instead of `{{ }}`, you have stored XSS in the support inbox.
  - Indirect phishing — embedding fake "click here to confirm" URLs into a body marked "from Fynla".
  - Resource exhaustion if an attacker repeatedly submits 17 KB+ of payload.
- Fix:
  1. Lower limits drastically (1k description, 500 console_logs).
  2. Confirm BugReportMail.blade.php escapes all fields with `{{ }}` (Blade default).
  3. Strip HTML from console_logs and description.
  4. Add CAPTCHA for unauthenticated submissions (hCaptcha / Turnstile).
  5. Set the email's Reply-To to the user's email (if authenticated) so replies go to them, not Chris.

### [MEDIUM][med-conf] A04-02 EmailVerificationCode 2-resend cap creates a UX dead-end (per memory)

- File: app/Models/EmailVerificationCode.php:44,67; per reference_verification_resend_dead_end.md
- OWASP: A04
- Impact: The 2-resend cap + 15-min code TTL + verification-session TTL = a documented dead-end. Users who genuinely don't receive the email cannot recover without contacting support. From a security view the cap is correct (anti-spam) — but the system silently fails rather than offering an alternative path.
- Fix: When canResend() returns false, return a 429 with a "request fresh verification" CTA that invalidates the existing session and starts over. Already partly handled (line 706 returns 429), but consider auto-purging the old code after the third attempt.

### [MEDIUM][high-conf] A04-03 Session not regenerated on web login success

- File: app/Http/Controllers/Api/AuthController.php:215 (Auth::attempt) — no $request->session()->regenerate() afterwards
- OWASP: A04 + A07
- Impact: Session fixation. An attacker who can set a victim's session cookie (e.g. via subdomain XSS) before the victim logs in will share the post-login session. Mitigated by Sanctum's bearer token flow on mobile/SPA, but still applies for any pure cookie-auth (browser dashboard).
- Fix: Call $request->session()->regenerate() after a successful Auth::attempt.

### [MEDIUM][med-conf] A04-04 failed_login_count increment race condition

- File: app/Services/Auth/LoginLockoutService.php:96-118
- OWASP: A04
- Impact: Concurrent failed login attempts read $user->failed_login_count, increment in PHP, then save. Two concurrent attempts can both write count=N+1 instead of N+2. Lockout threshold can be evaded under sustained credential-stuffing.
- Fix: Use `User::where('id', $user->id)->increment('failed_login_count')` and re-fetch the count atomically.

### [MEDIUM][med-conf] A04-05 No CSRF token check on /api/bug-report (because api/* is in CSRF except list)

- File: app/Http/Middleware/VerifyCsrfToken.php:17; routes/api.php:1338
- OWASP: A04 / A08
- Impact: The bug-report endpoint is unauthenticated POST with no CSRF. The site can be used as a relay to send abuse mail at chris@fynla.org from any browser. Mitigated by 5/hour rate limit.
- Fix: Add CAPTCHA (Turnstile) for unauthenticated submission. CORS is restricted via ALLOWED_ORIGINS, so cross-origin scripted submission is partially mitigated.

### [LOW][high-conf] A04-06 Passwords use bcrypt by default — argon2id stronger

- File: config/hashing.php (default driver)
- OWASP: A04
- Impact: Bcrypt is fine but argon2id is the NIST-recommended modern algorithm.
- Fix: Optional — HASH_DRIVER=argon2id in .env.production. Re-hashing happens on next login automatically via Laravel's Hashed::needsRehash middleware.

### [LOW][med-conf] A04-07 restoration_token stored in Cache for 5 min — Cache file driver default

- File: app/Http/Controllers/Api/AuthController.php:836-846; app/Http/Controllers/Api/Auth/RestoreAccountController.php:73-83
- OWASP: A04 / A02
- Impact: Tokens stored in cache survive in storage/framework/cache/ (file driver default). Anyone reading that dir reads the token. Mitigated by short TTL (5 min).
- Fix: Use Redis or DB cache in production for short-lived auth tokens; or use single-use DB tokens like PasswordResetSession.

### [LOW][high-conf] A04-08 mfa_challenge:{token} cached with no rate-limit on verify

- File: app/Http/Controllers/Api/MFAController.php:113-125,148
- OWASP: A04
- Impact: Challenge token (64 chars) is stored 5 min. verify endpoint has throttle:10,1 (10/min/IP). 10 brute-force attempts per minute against a 6-digit code (1M space) = 50k/year — feasible to brute over months without lockout (the lockout service does increment failures on MFA failed code — line 188-191). Mitigated by LoginLockoutService::recordFailedAttempt.
- Fix: Already adequate; tighten throttle to throttle:5,1 for parity with login.

### [INFO] A04-09 Strong rate limiting in place — visit RouteServiceProvider

- See file app/Providers/RouteServiceProvider.php:30-106 — per-user-or-IP throttles are well-thought-out.

---

## A05 — Security Misconfiguration

### [HIGH][high-conf] A05-01 Conflicting X-Frame-Options (SAMEORIGIN vs DENY)

- Files: public/.htaccess:64 sets X-Frame-Options "SAMEORIGIN"; app/Http/Middleware/SecurityHeaders.php:23 sets X-Frame-Options 'DENY'
- OWASP: A05 (per feedback_htaccess_vs_middleware_headers.md)
- Impact: Apache (Header set) wins over PHP middleware for the same header — production responds SAMEORIGIN, weaker than the intended DENY. Embedding the app in another fynla.org page is allowed; clickjacking risk if you ever serve untrusted content on a subdomain.
- Fix: Remove the X-Frame-Options set in public/.htaccess. Keep middleware only. Same for X-Content-Type-Options (set in both), Referrer-Policy (set in both), and X-XSS-Protection (set in .htaccess but deprecated — drop it entirely; modern browsers ignore it and it has been known to cause issues).

### [HIGH][high-conf] A05-02 X-XSS-Protection header set in .htaccess is deprecated

- File: public/.htaccess:65
- OWASP: A05
- Impact: Modern browsers ignore X-XSS-Protection and Chromium-based browsers have documented harms with `1; mode=block` in some contexts. Just drop it.
- Fix: Remove from public/.htaccess.

### [MEDIUM][high-conf] A05-03 CSP allows 'unsafe-inline' for both script-src and style-src

- File: app/Http/Middleware/SecurityHeaders.php:56,60
- OWASP: A05 — XSS amplifier
- Impact: 'unsafe-inline' in script-src defeats CSP's main XSS defence. The TODO comment at line 59 acknowledges this. Combined with v-html usage, an attacker who lands a stored XSS gets to execute arbitrary inline scripts.
- Fix: Migrate to nonce-based CSP per the TODO. Until then, document the risk. Vue 3 itself doesn't require inline scripts; the 'unsafe-inline' is reportedly for Revolut + Plausible.

### [MEDIUM][high-conf] A05-04 CSP for production omits default-src 'self' enforcement on script-src-elem and connect-src for new domains

- File: app/Http/Middleware/SecurityHeaders.php:60
- OWASP: A05
- Impact: Adding a new third-party tracker requires updating the CSP. The current `connect-src 'self' ... {$capacitor}` allows mobile origins (capacitor://localhost http://localhost) on web responses too — should be conditional.
- Fix: Emit different CSP for mobile vs web requests using IdentifyMobileClient middleware result.

### [MEDIUM][med-conf] A05-05 SecurityHeaders middleware order — runs before HandleCors

- File: app/Http/Kernel.php:61-71 — global middleware list
- OWASP: A05
- Impact: SecurityHeaders is global but HandleCors is also global, and the order in $middleware array decides response-write order. Setting CSP before CORS could in theory let CORS override it (CORS doesn't, but order matters generally).
- Fix: Move SecurityHeaders to end of global middleware; or to a per-route group.

### [MEDIUM][high-conf] A05-06 .env lockdown via `<Files .env>` won't match .env.production etc.

- File: public/.htaccess:73-76
- OWASP: A05
- Impact: The Files block only matches .env (literal). .env.example, .env.local, .env.production are not blocked. Mitigated because these aren't placed in public/ — they're in the Laravel root. But if someone accidentally puts an env template into public/, it would be served.
- Fix: Use `<FilesMatch "^\.env">` instead.

### [MEDIUM][med-conf] A05-07 Options +SymLinksIfOwnerMatch on root .htaccess

- File: public/.htaccess:98
- OWASP: A05
- Impact: Necessary for public/storage symlink. If anyone creates a writable symlink elsewhere, they may be reachable. Mitigated by the dedicated /storage/{path} route guard and the unique site-user ownership requirement.
- Fix: None — current setup is correct for SiteGround.

### [LOW][high-conf] A05-08 TrustProxies::$proxies = null — Laravel doesn't trust any proxy

- File: app/Http/Middleware/TrustProxies.php:17
- OWASP: A05
- Impact: Behind SiteGround's reverse proxy/CDN, $request->ip() may return the proxy's internal IP, not the real client. Rate limiting, audit logging, lockout tracking — all use request()->ip(). If the value is the same for all clients, your per-IP limits are effectively per-server.
- Fix: Set `protected $proxies = '*'` if SiteGround is the only fronting layer; or specific subnets. Add Forwarded / X-Forwarded-For to trusted headers.

### [LOW][high-conf] A05-09 Storage::disk('public') symlink — confirm prod has the symlink absent (per memory)

- File: Per feedback_siteground_hosting_lore.md and routes/web.php:71-79
- OWASP: A05
- Impact: csjones intentionally omits php artisan storage:link because SiteGround 403s symlinks. Confirm prod fynla.org has the symlink or relies on the /storage/{path} route fallback. If both exist and the symlink works, Apache serves first (faster); if symlink is broken, route fires. Audit which path serves on prod today.
- Fix: Document the prod state explicitly.

### [LOW][med-conf] A05-10 APP_DEBUG exposure

- File: app/Http/Traits/SanitizedErrorResponse.php:46,54; app/Exceptions/Handler.php:79
- OWASP: A05
- Impact: When APP_DEBUG=true in production (or staging), error responses leak file name, line number, and exception class to the client. csjones runs APP_DEBUG=true per CLAUDE.md — an attacker who can reach csjones gets stack frames. fynla.org should be APP_DEBUG=false.
- Fix: Confirm APP_DEBUG=false in fynla.org .env. Consider a runtime banner check at boot if APP_ENV=production && APP_DEBUG=true.

### [LOW][low-conf] A05-11 CORS supports_credentials => true with broad allowed_origins

- File: config/cors.php:54
- OWASP: A05
- Impact: allowed_origins is driven by env('ALLOWED_ORIGINS') plus FRONTEND_URL, APP_URL, capacitor://localhost, http://localhost. Confirm prod ALLOWED_ORIGINS does NOT include localhost. The `! str_contains($origin, '*')` filter prevents `*` wildcards.
- Fix: Lock ALLOWED_ORIGINS to exactly https://fynla.org (and capacitor://localhost for mobile) on prod; remove http://localhost from production.

---

## A06 — Vulnerable & Outdated Components

### [HIGH][high-conf] A06-01 npm audit reports 11 advisories (3 moderate, 8 high)

- File: package.json / package-lock.json (run npm audit to reproduce)
- OWASP: A06
- Impact: Notable highs:
  - @capgo/capacitor-native-biometric < 8.3.6 — Authentication Bypass (GHSA-vx5f-vmr6-32wf). This is the biometric login library on iOS. Per CLAUDE.md the token is stored in iOS Keychain and biometric is the primary face-ID unlock — a bypass here means an attacker who has physical access to a locked device (without the user's face) could potentially access the saved token.
  - axios 1.0.0 – 1.15.1 — 13 advisories, including prototype-pollution credential injection, SSRF via NO_PROXY bypass, CRLF injection, header injection, null-byte injection. axios is the SPA's primary HTTP client.
  - @babel/plugin-transform-modules-systemjs 7.12.0-7.29.0 — RCE compiling malicious input (dev build chain only).
  - tar — multiple path-traversal / symlink-poisoning issues; used by @capacitor/cli (dev-only).
  - serialize-javascript — RCE via Date/RegExp (dev-only via workbox-build).
  - vite ≤ 6.4.1 — path traversal in optimized deps (dev-only).
  - fast-uri, postcss — moderate XSS / traversal.
- Fix:
  1. Run npm audit fix for non-breaking fixes (axios, babel, postcss, fast-uri, serialize-javascript).
  2. Upgrade @capgo/capacitor-native-biometric to ≥ 8.3.6 — even though it's a breaking change, this is biometric auth. Schedule this before next mobile release.
  3. Upgrade vite (breaking — required for the build pipeline).
  4. Re-run npm audit and pin 0 high-severity advisories in production.

### [INFO] A06-02 composer audit reports zero advisories

- Run output: "No security vulnerability advisories found."
- OWASP: A06
- Impact: Backend dependency hygiene is good. Notable backend versions:
  - laravel/framework v10.50.2 — latest 10.x as of audit date.
  - laravel/sanctum v3.3.3.
  - guzzlehttp/guzzle 7.10.0.
  - mews/purifier 3.4.4.
  - dompdf/dompdf v2.0.8 — there have been CVEs in older 2.x; 2.0.8 is the latest 2.x but a 3.x line exists. dompdf is invoked only for invoice generation from internal Blade templates (no user-controlled HTML), so SSRF risk via `<img src="http://...">` is low. Confirm dompdf 'isRemoteEnabled' is false in config (default is false).
- Fix: None urgent; consider scheduling dompdf 3.x upgrade in a quiet release.

### [LOW][low-conf] A06-03 barryvdh/laravel-dompdf v2.2.0 (older than current v3.x)

- OWASP: A06
- Impact: v2 is supported but pre-Laravel-11 era. No active advisories. Future-proof.
- Fix: Track Laravel 11 upgrade.

---

## A07 — Identification & Authentication Failures

### [HIGH][high-conf] A07-01 TransientToken family bug — see A01-01 (TokenRefreshController)

Already documented under A01.

### [MEDIUM][high-conf] A07-02 Account-enumeration via register endpoint email check

- File: app/Http/Controllers/Api/AuthController.php:75-84
- OWASP: A07
- Impact: Register endpoint explicitly returns 'email_exists' => true if email is in DB, leaking which emails are registered. While GDPR-erasure flow goes to lengths to avoid enumeration, the register endpoint trades it for UX ("an account already exists — sign in or reset password"). Combined with the soft-deleted "trashed-email detection" return (line 62-72), an attacker can map registered, deleted, and unknown emails accurately.
- Fix: Either accept that this is a deliberate UX trade-off (document explicitly) OR migrate to a "we sent you an email" pattern regardless and surface "email taken" via the verification email itself.

### [MEDIUM][high-conf] A07-03 Login response distinguishes "user not found" vs "wrong password"

- File: app/Http/Controllers/Api/AuthController.php:194-213,215-234
- OWASP: A07
- Impact: Both return the same message "Invalid email or password." (good) and HTTP 401 (good) — timing differs. The first path runs only User::where('email', $email)->first() + lockout record. The second path runs Auth::attempt which does bcrypt verification (~100 ms). An attacker can distinguish via response time.
- Fix: Equalise timing by running an artificial Hash::check against a static hash in the not-found branch.

### [MEDIUM][med-conf] A07-04 MFA disable doesn't require email verification

- File: app/Http/Controllers/Api/MFAController.php:289-313
- OWASP: A07
- Impact: Disabling MFA requires only the current password. If a user's password is compromised (e.g. via phishing), MFA can be disabled without a second factor. NIST guidance suggests MFA-protected operations require the second factor for changes.
- Fix: Require TOTP code in addition to password for MFA disable; or email confirmation.

### [LOW][high-conf] A07-05 Logout doesn't revoke all device tokens

- File: app/Http/Controllers/Api/AuthController.php:295-321
- OWASP: A07
- Impact: logout() revokes only the current token. Mobile devices and other browser tabs keep theirs. This is intentional for "session per device" but may surprise users.
- Fix: UI flag on logout: "Sign out from all devices" — already implemented via SessionController::destroyOthers.

### [LOW][high-conf] A07-06 Login doesn't check force_password_change for legacy users

- File: app/Http/Controllers/Api/AuthController.php:215
- OWASP: A07
- Impact: must_change_password flag on User is checked after issuing the token (response carries must_change_password). The user can still call any API with that token before changing password. Acceptable trade-off if frontend forces the password-change modal.
- Fix: Document the contract; add middleware that 403s on any non-change-password endpoint when must_change_password = true.

### [LOW][high-conf] A07-07 Sanctum token TTL 4 hours, no idle timeout

- File: config/sanctum.php:52
- OWASP: A07
- Impact: 4-hour fixed TTL is fine. Mobile tokens get 30 days (TokenRefreshController:26) — that's long. No idle-timeout enforcement.
- Fix: Already adequate; idle timeout via UserSession.last_activity_at already touched on every request via TouchSessionActivity. Document.

### [INFO] A07-08 Recovery codes use bcrypt at rest

- File: app/Services/Auth/MFAService.php:151 (Hash::check)
- Good practice.

### [INFO] A07-09 Login lockout thresholds are progressive — well-designed

- config/auth.php:139-147: 3 fails = 1 min, 5 = 5 min, 10 = 30 min, 15 = 24 hr. IP cap 50/hr.

---

## A08 — Software & Data Integrity Failures

### [MEDIUM][high-conf] A08-01 Document upload restricts MIME / extensions / size but no magic-byte check

- File: app/Http/Requests/Documents/UploadDocumentRequest.php:23-29
- OWASP: A08
- Impact: `mimes:pdf,jpeg,jpg,png,webp,xlsx,xls,csv` checks the file extension (Laravel) plus MIME (via file_get_contents) but mimes does a relatively shallow check. A malicious PDF could embed PHP/JS — though dompdf doesn't render it and the file is served as application/pdf, the AI extraction service uploads it to Anthropic/xAI vision APIs. If those APIs eventually surface a file URL back to the user (today they don't), there's a stored-file XSS surface. Limit also 20 MB — large enough for resource exhaustion if many concurrent uploads.
- Fix: Use mimetypes (validates against actual file content via finfo) instead of mimes. Add per-user concurrent-upload limits.

### [MEDIUM][high-conf] A08-02 LPA file upload uses mimes:pdf,jpg,jpeg,png only

- File: app/Http/Requests/Estate/UploadLpaRequest.php:19
- OWASP: A08
- Impact: Same as A08-01.
- Fix: Use mimetypes. Add antivirus scan if the LPA service ever displays uploaded files inline.

### [LOW][high-conf] A08-03 Document passedValidation filename sanitisation is unused

- File: app/Http/Requests/Documents/UploadDocumentRequest.php:47-58
- OWASP: A08
- Impact: The passedValidation method sanitises $sanitised but never writes it back into the request. The variable is discarded. The downstream DocumentUploadService::put() uses file_get_contents($file->getRealPath()) then writes to a controlled path (documents/{user_id}/... typically), so filename traversal isn't reachable today — but the sanitisation code is dead, which is confusing.
- Fix: Either remove the unused code OR actually mutate the file's stored name on the request.

### [LOW][high-conf] A08-04 Insight image upload uses image validator (good) but stores under user-controlled slug

- File: app/Http/Requests/Admin/Insights/UploadInsightImageRequest.php:20; app/Services/Insights/InsightImageService.php
- OWASP: A08
- Impact: slug is alpha_dash|max:255 — safe. Admin-only upload.

### [INFO] A08-05 Revolut webhook signature verification implementation is excellent

- File: app/Services/Payment/RevolutService.php:214-258
- HMAC-SHA256 with hash_equals, timestamp tolerance, multi-signature support for key rotation. Best-in-class.

### [INFO] A08-06 Lifecycle email magic-link routes use Laravel's signed-URL middleware

- File: routes/web.php:32-44
- Good — signed URLs are time-limited and tamper-proof.

### [INFO] A08-07 Newsletter token is 48 random alphanumeric chars

- File: routes/web.php:57-62
- Str::random(48) ≈ 285 bits — fine. Token IS the secret; no signed wrapper needed.

### [INFO] A08-08 AI audit hash-chain HMAC-signed with dedicated key (fallback to APP_KEY)

- File: app/Services/AI/AuditChainService.php:51-54; config/app.php:57
- M19 fail-loud behaviour is great.

---

## A09 — Security Logging & Monitoring

### [LOW][high-conf] A09-01 Audit log doesn't include request_id for tracing

- File: app/Services/Audit/AuditService.php (sample by grep)
- OWASP: A09
- Impact: Audit rows have user_id, action, old_values, new_values, but no per-request correlation id. Tying multi-step incidents (e.g. an XSS exploitation chain across endpoints) is harder.
- Fix: Add request_id header via middleware (Str::uuid()), inject into log context globally.

### [LOW][high-conf] A09-02 IP addresses logged in plaintext — GDPR consideration

- File: Many places — LoginLockoutService.php:113, AuthController.php, PasswordResetService.php:48, GDPRController.php
- OWASP: A09
- Impact: Under GDPR, IP addresses are personal data. The audit retention is 90 days (default) / 2555 days (GDPR — see config/auth.php:160-161). Retention is generous; consider hashing IPs after a shorter window.
- Fix: Already explicit in retention config — acceptable.

### [LOW][high-conf] A09-03 Email masked in logs (good practice)

- File: app/Http/Controllers/Api/AuthController.php:77,103,113,665 — maskEmail() consistently used
- Good defensive practice.

### [LOW][med-conf] A09-04 Bug report email includes raw IP in body — same controller doesn't mask

- File: app/Http/Controllers/Api/BugReportController.php:53
- OWASP: A09
- Impact: Bug report mail contains user_id, user_name, user_email, ip — these end up in Chris's inbox. Once a bug report mail is forwarded externally (to a contractor / 3rd-party diagnoses), PII is leaked.
- Fix: Document that bug report mails are internal-only; or mask the IP / email before sending.

### [INFO] A09-05 Comprehensive auth audit log coverage

Login success, login failed, MFA enabled, MFA disabled, MFA verified, session revoked, password changed, password reset requested, erasure requested/completed, admin actions — all covered. Good.

### [INFO] A09-06 Stack traces logged but not exposed

SanitizedErrorResponse logs trace server-side, returns generic message client-side. Correct.

---

## A10 — Server-Side Request Forgery

### [LOW][high-conf] A10-01 Postcode lookup proxy is well-locked-down

- File: app/Http/Controllers/Api/PostcodeLookupController.php:76-79
- OWASP: A10
- Impact: Proxy passes $postcode (validated against UK regex) to https://api.getaddress.io/autocomplete/{postcode} — URL is fixed, value is URL-encoded. Safe.

### [LOW][high-conf] A10-02 AI extraction services hit fixed Anthropic / xAI URLs

- File: app/Services/Documents/AIExtractionService.php:234-245,284-297,361
- OWASP: A10
- Impact: Endpoints are class constants (ANTHROPIC_API_URL, XAI_API_URL). User-controlled content goes into the body. No URL construction from user input. Safe.

### [LOW][low-conf] A10-03 Revolut SDK redirect URL constructed from config('app.url') or hardcoded

- File: app/Http/Controllers/Api/PaymentController.php:176-181
- OWASP: A10 + Open Redirect
- Impact: `$baseUrl = config('services.revolut.sandbox') ? 'https://fynla.org' : config('app.url'); $redirectUrl = $baseUrl . '/checkout?plan=...'`. App URL is trusted source. Safe.

### [LOW][high-conf] A10-04 Awin tracking outbound HTTP fires awc cookie content

- File: app/Services/Marketing/AwinTrackingService.php:131
- OWASP: A10 (informational)
- Impact: Outbound HTTP carries cookie content (awc) to Awin tracker. Cookie content is set by Awin pixel itself, not user input. Low risk.

### [INFO] A10-05 No HTTP-from-DB pattern anywhere in app code

Reviewed Guzzle / Http facade usage — all destinations are constants or config values, never user input.

---

## Fynla-specific findings

### Fynla-A — Preview user isolation

#### [LOW][high-conf] F-A1 PreviewWriteInterceptor::EXCLUDED_ROUTES contains api/onboarding

- File: app/Http/Middleware/PreviewWriteInterceptor.php:68
- Impact: Onboarding writes pass through for preview users (intentional per CLAUDE.md), but the comment "Allow onboarding to work in preview mode" needs an explicit guarantee that downstream onboarding writes are short-circuited for is_preview_user. Confirm OnboardingChatDirector::handleInlineCapture checks is_preview_user before writes.
- Fix: Audit OnboardingChatDirector to ensure preview short-circuit. Add a test.

#### [LOW][med-conf] F-A2 PreviewWriteInterceptor resolves user from token MANUALLY before auth:sanctum

- File: app/Http/Middleware/PreviewWriteInterceptor.php:103-113,164-181
- Impact: The middleware runs before Sanctum auth and uses PersonalAccessToken::findToken($token) directly. If a token is revoked DURING a request (rare but possible), the middleware sees the deleted user and lets the write through. Mitigated by token caching being short.
- Fix: Either move the middleware after auth:sanctum, or accept the race-condition window.

#### [INFO] F-A3 Eval bypass requires BOTH ability AND X-Eval-Run-Id header

Defence in depth. Good.

### Fynla-B — AI / Fyn security

#### [INFO] F-B1 AdviceFyn write-tool stripping is complete

- File: app/Services/AI/AdviceFyn.php:152-184
- All create_* / update_* / delete_* / capture_* / set_expenditure / navigate_to_page are in WRITE_TOOLS. The buildToolList call applies array_diff to remove them. Good.

#### [INFO] F-B2 System prompt injection defence via UserContentSanitiser

- File: app/Services/AI/Prompts/UserContentSanitiser.php
- Strips XML/HTML/template/shell metacharacters from user-controlled prompt-interpolation sites. Wraps in `<user_provided>` boundary markers. Excellent.

#### [INFO] F-B3 AI chat consent gate at entry and mid-stream

- File: app/Http/Controllers/Api/AiChatController.php:148-201
- INV-2.10.3 implementation is correct. Consent withdrawal mid-stream emits terminal event and closes.

#### [LOW][med-conf] F-B4 AI conversation metadata field accepts arbitrary nested JSON without depth limit

- File: app/Http/Controllers/Api/AiChatController.php:67
- Impact: current_route from request is stored in metadata.current_route. If a user POSTs arbitrarily deeply-nested JSON to current_route, it gets stored. Mitigated by Laravel's request-size limit. Cosmetic.
- Fix: Validate current_route as string only.

#### [LOW][high-conf] F-B5 AI audit chain HMAC fails-loud (good), but AI_AUDIT_HMAC_KEY defaults to APP_KEY if missing

- File: config/app.php:57
- Impact: Audit chain rows on prod become forgeable by anyone with APP_KEY access (backups, env leak). Defence requires dedicated secret.
- Fix: Set explicit AI_AUDIT_HMAC_KEY on prod and document its custody.

### Fynla-C — Payment / subscription

#### [INFO] F-C1 Revolut webhook signature verification with 5-min replay window

See A08-05 above.

#### [INFO] F-C2 Idempotency on /api/ai-chat/conversations/{id}/messages

Via idempotent middleware on a Idempotency-Key header. Cleaner than payment-side idempotency.

#### [LOW][med-conf] F-C3 Payment discount_code validated via service but accepted as string (max 50)

- File: app/Http/Controllers/Api/PaymentController.php:89
- Looks safe but confirm DiscountCodeService::validate handles SQL escaping if it uses LIKE queries.

### Fynla-D — Email

#### [LOW][med-conf] F-D1 LIFECYCLE_TEST_RECIPIENT leak into prod risk

- File: config/lifecycle.php:65
- Impact: If LIFECYCLE_TEST_RECIPIENT is accidentally set on prod, every lifecycle email goes to chris@fynla.org instead of the customer. Per CLAUDE.md, csjones sets it; prod must not.
- Fix: Add a boot-time check that throws RuntimeException if APP_ENV=production and LIFECYCLE_TEST_RECIPIENT is set.

#### [INFO] F-D2 Contact form sends from anonymous user with Reply-To set correctly

- File: app/Http/Controllers/Api/ContactFormController.php:53-54
- Good.

### Fynla-E — Mobile (Capacitor iOS)

#### [HIGH][med-conf] F-E1 @capgo/capacitor-native-biometric Authentication Bypass

See A06-01.

#### [INFO] F-E2 Token in iOS Keychain (per CLAUDE.md), mobileLogout distinction

Sound architecture. Confirm the SettingsList biometric toggle never writes the token to localStorage.

#### [LOW][med-conf] F-E3 Deep-link from email may not validate origin

- File: Cross-check LifecycleActionController (loaded by web.php signed routes). Signed URL middleware enforces origin via URL::signedRoute().
- Impact: Good — Laravel's signed-URL middleware verifies signature, expiry, and matches the route's name. The token is the signature plus query params hashed with APP_KEY. Safe.

---

## Positive observations

- Sanctum tokens prefixed with fynla_ for secret-scanning integration (config/sanctum.php:67).
- Progressive login lockout with IP cap (LoginLockoutService).
- Revolut webhook signature verification is well-implemented with timing-safe comparison, timestamp tolerance, and multi-signature support.
- Idempotency middleware for AI chat to prevent duplicate writes on retries.
- PreviewWriteInterceptor with comprehensive excluded-route allowlist and explicit eval bypass token mechanism.
- AI audit hash-chain with HMAC signing and fail-loud key configuration.
- HTMLPurifier integration for admin CMS content with custom document_article profile.
- DOMPurify available on frontend; v-html usage is constrained to sanitised/trusted content.
- CSRF protection correctly excluded from /api/* (bearer auth) but enforced elsewhere.
- SecurityHeaders middleware sets CSP, HSTS, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, X-Permitted-Cross-Domain-Policies, Cross-Origin-Opener-Policy.
- Custom rate limiters for auth, export, sensitive, ai-chat, mobile-dashboard, device-registration, bug-reports — per-user-or-IP.
- Audit logging of every auth event with retention policies for GDPR vs operational logs.
- Trash/restore flow with restoration token cache and password verification before token issue (prevents account enumeration via existence-by-error).
- Email enumeration mitigation in password reset (initiateReset always returns success).
- $guarded array on User prevents mass-assignment of is_admin, is_preview_user, remember_token, id, timestamps.
- $hidden array on User excludes password, mfa_secret, mfa_recovery_codes, national_insurance_number, lockout fields from JSON serialization.
- Constant-time comparisons in Revolut webhook (hash_equals) and AI audit chain (hash_equals).
- MFA secret encrypted at rest via Crypt::encryptString (separate from Hash::make).
- Recovery codes hashed at rest (Hash::check for verification).
- Admin backup writes credentials to .my.cnf rather than command line (no process-list leak).
- realpath containment check in backup restore prevents directory traversal.
- GDPR multi-step deletion with email/TOTP code + confirmation phrase + grace-period scheduling.
- SanitizeInput middleware strips HTML tags from non-allowed-list fields, with explicit exempt list for password/code/token fields.
- AI prompt injection sanitisation (UserContentSanitiser) covers control chars, format chars, template metacharacters.
- AdviceFyn read-only contract enforced via WRITE_TOOLS array-diff at the catalogue level.
- Admin routes protected by both route middleware (permission:admin.access) AND constructor abort in AdminController (defence in depth).

---

## Recommendations (prioritised)

### Immediate (this week)
1. Run npm audit fix for non-breaking advisories (axios, postcss, fast-uri, babel, serialize-javascript). Verify the SPA builds and tests pass.
2. Upgrade @capgo/capacitor-native-biometric to ≥ 8.3.6 (breaking change) before next mobile release.
3. Add instanceof PersonalAccessToken guard to TokenRefreshController::refresh (A01-01).
4. Remove duplicate / conflicting headers from public/.htaccess (A05-01, A05-02): drop X-Frame-Options, X-Content-Type-Options, Referrer-Policy, X-XSS-Protection. Keep middleware as the single source of truth.
5. Add CAPTCHA to /api/bug-report and reduce field-size limits (A04-01).
6. Replace !== with hash_equals for all session-token comparisons in PasswordResetService, GDPRController, and AuthController::verifyCode (A02-01, A01-05).

### Sprint
7. Audit production .env — confirm APP_ENV=production, APP_DEBUG=false, SESSION_ENCRYPT=true, dedicated AI_AUDIT_HMAC_KEY, no LIFECYCLE_TEST_RECIPIENT (A01-02, F-D1).
8. Add session regeneration after Auth::attempt success (A04-03).
9. Atomic failed_login_count increment to close lockout-evasion race (A04-04).
10. Encrypt national_insurance_number field with Eloquent encrypted cast + migration (A02-03).
11. Add encrypted directory or gpg-encrypt admin SQL backups (A02-04).
12. Tighten X-Frame-Options to DENY only via middleware, drop .htaccess version (A05-01).
13. Set TrustProxies::$proxies = '*' (or SiteGround proxy CIDR) so rate-limit / audit IPs are correct (A05-08).
14. Add mimetypes (content-based) validation to all uploaded files (A08-01, A08-02).
15. Remove admin-emails auto-promotion at login (A01-03).

### Backlog
16. Migrate CSP to nonce-based to remove 'unsafe-inline' (A05-03).
17. Move sanitizeHtml.js regex helper to DOMPurify everywhere on the frontend (A03-05).
18. Add HIBP Pwned-Passwords k-anonymity check to register/changePassword (A02-09).
19. Add per-request UUID for audit log correlation (A09-01).
20. Audit OnboardingChatDirector::handleInlineCapture for preview-mode short-circuit and add a regression test (F-A1).
21. Document key custody for AI_AUDIT_HMAC_KEY, MFA encryption, backup encryption keys (A02-02).
22. Equalise login timing between "user not found" and "wrong password" paths (A07-03).
23. Require MFA code (not just password) to disable MFA (A07-04).
24. Schedule dompdf 3.x upgrade in next maintenance window (A06-02).
25. Encrypt restoration_token cache values OR move to single-use DB tokens (A04-07).

---

## Methodology

- Static review of 110 controllers, 27 middleware classes, 297 services, 111 models, 83 form requests, route files, config files, deploy templates, frontend Vue components.
- Tool runs: composer audit, npm audit, npm audit --omit=dev.
- Pattern searches for SQL injection (whereRaw, selectRaw, DB::raw, DB::statement), command injection (shell calls), XSS (v-html), hardcoded secrets (sk-, ant-, sk_live, pk_live, AKIA, AIza), unsafe find without ownership scope, mass-assignment (update($request->all())), currentAccessToken() usage for TransientToken family, timing-unsafe comparisons on auth tokens, sensitive-data logging.
- Cross-referenced with project memory files (reference_transient_token_family_bugs.md, feedback_htaccess_vs_middleware_headers.md, feedback_siteground_prod_vhost_no_conditionals.md, reference_verification_resend_dead_end.md).

Total assertions made grounded in file:line evidence. No speculation about runtime behaviour beyond what static inspection supports — recommendations marked [low-conf] flag where dynamic verification would be helpful.
