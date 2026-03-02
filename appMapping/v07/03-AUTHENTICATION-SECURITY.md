# Chapter 3: Authentication & Security

Fynla builds its security on Laravel Sanctum. Every request passes through rate limiting, input sanitisation, and token-based authentication before reaching application logic. This chapter documents each layer, from registration through session management and GDPR compliance.

---

## Authentication Stack

Fynla authenticates users with Laravel Sanctum bearer tokens. The backend runs Laravel 10 with the default `web` guard for session-based requests and Sanctum tokens for all API calls.

**Token lifecycle:**

- Sanctum creates a personal access token on login or registration.
- The database stores the token in the `personal_access_tokens` table.
- The frontend stores the token in `sessionStorage` (cleared when the tab closes).
- Tokens expire after 480 minutes (8 hours).

The frontend sends the token as a `Bearer` header on every API request. If the token is missing, expired, or revoked, the API returns a 401 response and the frontend redirects to the login screen.

---

## Middleware Pipeline

Every request passes through middleware layers before reaching a controller, grouped by context.

### Global middleware (all requests)

These run on every HTTP request regardless of route:

1. **TrustProxies** -- Handles proxy headers for correct IP detection behind load balancers.
2. **HandleCors** -- Applies CORS headers (see the CORS section below).
3. **PreventRequestsDuringMaintenance** -- Returns 503 when the application is in maintenance mode.
4. **ValidatePostSize** -- Rejects requests that exceed the maximum POST size.
5. **TrimStrings** -- Removes leading and trailing whitespace from all input values.
6. **ConvertEmptyStringsToNull** -- Converts empty string inputs to `null`.

### Web middleware group

Used for browser-based routes (login pages, SPA entry point):

1. **EncryptCookies** -- Encrypts all outbound cookies.
2. **AddQueuedCookiesToResponse** -- Attaches any queued cookies.
3. **StartSession** -- Initialises the server-side session.
4. **ShareErrorsFromSession** -- Makes validation errors available to views.
5. **VerifyCsrfToken** -- Validates the CSRF token on state-changing requests.
6. **SubstituteBindings** -- Resolves route model bindings.

### API middleware group

Used for all `/api/*` routes:

1. **EnsureFrontendRequestsAreStateful** -- Enables Sanctum's cookie-based auth for same-domain SPA requests.
2. **ThrottleRequests** -- Applies rate limiting (see Rate Limiting below).
3. **SubstituteBindings** -- Resolves route model bindings.
4. **SanitizeInput** -- Strips HTML tags and encodes special characters (see below).
5. **PreviewWriteInterceptor** -- Intercepts write operations from preview users (see below).

### Route-level middleware

Individual routes apply additional middleware as needed:

| Alias | Middleware | Purpose |
|---|---|---|
| `auth` | Authenticate | Requires a valid Sanctum token. Returns 401 if missing. |
| `guest` | RedirectIfAuthenticated | Blocks already-authenticated users from login/register routes. |
| `admin` | IsAdmin | Checks the `is_admin` flag on the user model. Returns 403 if false. |
| `mfa.verified` | EnsureMFAVerified | Checks that the session has a `mfa_verified` flag when MFA is enabled. Returns 403 if unverified. |
| `role` | HasRole | RBAC role check. Falls back to the `is_admin` flag if no roles are assigned. |
| `permission` | HasPermission | RBAC permission check. Admin users bypass all permission checks. |

---

## Custom Middleware Detail

### SanitizeInput

The middleware trims whitespace, strips HTML tags, and encodes special characters on all input fields before they reach a controller. This defends against cross-site scripting (XSS) attacks.

**Exempt fields:** `password`, `password_confirmation`, and `current_password` are excluded from sanitisation to avoid altering password values.

### PreviewWriteInterceptor

Fynla includes a preview mode that lets prospective users explore the application with seeded data. The PreviewWriteInterceptor ensures preview users cannot modify real data.

**Behaviour:**

- Intercepts all POST, PUT, PATCH, and DELETE requests from users where `is_preview_user = true`.
- Returns a fake success response with a `preview_mode: true` flag.
- The frontend detects this flag and stores changes in `sessionStorage` only.

**Excluded routes** (these must work regardless of preview status):

- `auth/login`, `auth/logout`, `auth/register`, `auth/verify-code`
- `preview/exit`, `preview/switch`
- `onboarding`, `documents/upload`

**Excluded URL patterns** (calculation endpoints that do not write to the database):

- Any route containing `/calculate`, `/projections`, `/analyze`, or `/toggle-retirement`.

### EnsureMFAVerified

When a user has `mfa_enabled = true` on their account, this middleware checks that the current session includes a `mfa_verified` flag. If the flag is absent, the middleware returns a 403 response. This prevents access to protected routes until the user completes the MFA challenge.

### IsAdmin

Checks `request->user()->is_admin`. Returns 403 if the flag is false. Used to protect admin-only routes such as user management and system configuration.

### HasRole and HasPermission

These middlewares implement role-based access control (RBAC). The system uses Role and Permission models with a numeric level system:

| Level | Role |
|---|---|
| 0 | User |
| 50 | Support |
| 100 | Admin |

Admin users (`is_admin = true`) bypass all permission checks automatically. Non-admin users receive a 403 with the required role or permission name.

---

## Registration Flow

Registration is a two-step process: submit credentials, then verify by email.

### Step 1: Submit registration

**Endpoint:** `POST /api/auth/register` (rate limit: 5 requests per minute)

1. The server validates the email (must be unique) and password (see Password Requirements below).
2. The server creates a `PendingRegistration` record with the hashed password and a 6-digit verification code. No `User` record exists yet.
3. The server sends a verification email containing the 6-digit code.
4. The API returns a masked email address (e.g., `c***@f***.org`).

### Step 2: Verify email code

**Endpoint:** `POST /api/auth/verify-code` with `type=registration` (rate limit: 10 requests per minute)

1. The server validates the 6-digit code against the `PendingRegistration` record.
2. On success, the server creates a `User` record from the pending registration data.
3. The server deletes the `PendingRegistration` record.
4. The server creates a Sanctum token and `UserSession` record.
5. The server logs `ACTION_LOGIN_SUCCESS` with `method=registration`.
6. The API returns the access token and user data.

---

## Login Flow

Login is also a two-step process: password verification, then email verification. If MFA is enabled, a third step is required.

### Step 1: Submit credentials

**Endpoint:** `POST /api/auth/login` (rate limit: 5 requests per minute)

1. The server checks whether the account is locked out (see Login Lockout below).
2. `Auth::attempt` validates the email and password.
3. **On failure:** The server creates a `LoginAttempt` record, increments `failed_login_count`, and evaluates lockout thresholds.
4. **Preview users:** Skip email verification. The server creates and returns a token immediately.
5. **MFA-enabled users:** The server generates a 64-character challenge token, caches it for 5 minutes (one-time use), and returns `requires_mfa: true` with the challenge token.
6. **Standard users:** The server generates a 6-digit `EmailVerificationCode` (15-minute expiry) and sends it by email. The API returns the masked email and user ID.

### Step 2: Verify email code

**Endpoint:** `POST /api/auth/verify-code` with `type=login` (rate limit: 10 requests per minute)

1. The server validates the 6-digit code.
2. The server marks the code as verified.
3. The server resets `failed_login_count` to zero.
4. The server creates a Sanctum token and `UserSession` record.
5. The server logs `ACTION_LOGIN_SUCCESS` with `method=email_verification`.
6. The API returns the access token, user data, and `mfa_enabled` flag.

### Step 3 (if MFA enabled): Verify TOTP

See the MFA During Login section below.

---

## Multi-Factor Authentication (MFA)

Fynla supports time-based one-time passwords (TOTP) as a second authentication factor. The implementation uses the `pragmarx/google2fa-laravel` library with `bacon/bacon-qr-code` for QR code generation.

**Technical details:**

- The server encrypts the TOTP secret with AES-256-CBC before storing it in `User.mfa_secret`.
- Setup generates 10 recovery codes, each in the format `XXXX-XXXX-XXXX`.
- Recovery codes are hashed (never stored in plain text) and are single-use.
- A window tolerance of 2 accepts codes from one time step before or after the current step, accommodating clock drift between the server and authenticator app.

### MFA Setup

Users enable MFA from their account settings. The process has two steps:

1. **Generate secret:** `POST /api/auth/mfa/setup` generates a TOTP secret, stores it in the server session, and returns a QR code as an SVG image. The user scans this QR code with an authenticator app (Google Authenticator, Authy, etc.).

2. **Confirm setup:** `POST /api/auth/mfa/verify-setup` accepts a TOTP code from the authenticator app. If the code is valid, MFA is enabled on the account and 10 recovery codes are generated and returned. The user must save these recovery codes -- they are shown only once.

### MFA During Login

After successful password and email verification, users with MFA enabled must complete an additional challenge:

1. The login flow returns a 64-character `mfa_token` (challenge token) cached for 5 minutes. This token is single-use.

2. The user submits either:
   - **TOTP code:** `POST /api/auth/mfa/verify` with the challenge token and 6-digit TOTP code from their authenticator app.
   - **Recovery code:** `POST /api/auth/mfa/recovery` with the challenge token and a recovery code. The recovery code is consumed (marked as used) and cannot be reused.

### MFA Management

| Endpoint | Method | Purpose |
|---|---|---|
| `/api/auth/mfa/status` | GET | Returns whether MFA is enabled, the confirmation date, and the number of remaining recovery codes. |
| `/api/auth/mfa/disable` | POST | Disables MFA. Requires the user's current password for verification. |
| `/api/auth/mfa/recovery-codes` | POST | Regenerates all recovery codes. Requires the user's current password. Previous codes are invalidated. |

---

## Login Lockout

Fynla uses progressive lockout to defend against brute-force attacks. Thresholds are defined in `config/auth.php`:

| Failed attempts | Lockout duration |
|---|---|
| 3 | 1 minute |
| 5 | 5 minutes |
| 10 | 30 minutes |
| 15 or more | 24 hours |

**IP-level blocking:** 50 failed attempts from a single IP address within one hour triggers an IP-level block.

**Tracking mechanism:** The `LoginLockoutService` uses two data sources:

- **User model fields:** `failed_login_count`, `locked_until`, and `last_failed_login_at` track per-account lockout state.
- **LoginAttempt model:** Records every login attempt with the email, IP address, success/failure status, and failure reason.

**On successful login:** The `failed_login_count` resets to zero and `locked_until` is cleared.

---

## Password Reset

Password reset is a multi-step flow that prevents email enumeration and supports MFA-enabled accounts.

### Step 1: Request reset

**Endpoint:** `POST /api/auth/password-reset/request` (rate limit: 3 requests per minute)

The server generates an `EmailVerificationCode` of type `password_reset` and sends it. The API returns a success response regardless of whether the email exists, preventing email enumeration.

### Step 2: Verify email

**Endpoint:** `POST /api/auth/password-reset/verify-email` (rate limit: 10 requests per minute)

The user submits the 6-digit code. On success, the server returns a `password_reset_token`.

### Step 3 (if MFA enabled): Verify MFA

**Endpoint:** `POST /api/auth/password-reset/verify-mfa` or `/mfa-recovery`

MFA-enabled accounts must verify their identity with a TOTP code or recovery code before the password can be changed.

### Step 4: Set new password

**Endpoint:** `POST /api/auth/password-reset/reset` (rate limit: 5 requests per minute)

1. The server hashes and saves the new password.
2. The server resets `failed_login_count` and `locked_until`.
3. The server revokes all existing Sanctum tokens, forcing re-authentication on every device.
4. The server logs `ACTION_PASSWORD_RESET_COMPLETED`.

---

## Password Requirements

Every password must meet these requirements:

- Minimum 8 characters.
- At least one uppercase letter.
- At least one lowercase letter.
- At least one digit.
- At least one special character.

The server hashes passwords with bcrypt: 12 rounds in production, 4 in the test environment.

---

## Session Management

Each authenticated session is tracked in the `UserSession` model, which records:

- `user_id` -- The authenticated user.
- `token_id` -- The associated Sanctum token.
- `ip_address` -- The IP address at login.
- `user_agent` -- The raw user agent string.
- `device_name` -- A human-readable device label.
- `last_activity_at` -- The timestamp of the most recent activity.

**Device detection** parses the user agent string into readable labels: iPhone, iPad, Android Phone, Android Tablet, Mac, Windows PC, Linux PC, or Web Browser.

### Session management API

| Endpoint | Method | Purpose |
|---|---|---|
| `/api/auth/sessions` | GET | Lists all active sessions for the current user. Each session includes an `is_current` flag. |
| `/api/auth/sessions/{id}` | DELETE | Revokes a specific session by deleting its token and session record. |
| `/api/auth/sessions/others/all` | DELETE | Revokes all sessions except the current one. |

**Orphaned session cleanup:** A maintenance process finds sessions where the `token_id` references a deleted token and removes them.

---

## Logout

### Standard logout

**Endpoint:** `POST /api/auth/logout` (requires authentication)

Deletes the current Sanctum token and its associated `UserSession` record. The server logs the event.

### Beacon logout

**Endpoint:** `POST /api/auth/logout-beacon` (rate limit: 10 requests per minute)

Handles logout when the user closes a browser tab. The frontend uses the `navigator.sendBeacon` API to send the token in the request body (since headers cannot be set with `sendBeacon`). The server finds the token, deletes the associated session, and revokes the token.

---

## Email Verification Codes

Registration, login, and password reset flows use verification codes.

**Properties:**

- 6 digits, randomly generated.
- 15-minute expiry from generation.
- Scoped by type: `login`, `registration`, or `password_reset`.

**Resend limits:**

- Maximum 2 resends per flow (3 total codes including the original).
- Each resend generates a new code, extends the expiry, and increments `resend_count`.

---

## Rate Limiting

The `RouteServiceProvider` defines rate limits by context:

| Context | Limit | Scope |
|---|---|---|
| General API | 1,000/min (local), 300/min (production) | Per authenticated user |
| Authentication routes | 5/min | Per IP address |
| GDPR data export | 3/hour | Per user |
| Sensitive operations (password change, account erasure) | 3/min | Per user |
| Bug reports | 5/hour | Per user and IP address |

When the limit is exceeded, the API returns 429. Every response includes `X-RateLimit-Limit`, `X-RateLimit-Remaining`, and `X-RateLimit-Reset` headers so the frontend can warn before the limit is reached.

---

## CORS Configuration

`config/cors.php` configures Cross-Origin Resource Sharing:

- **Paths:** `api/*` and `sanctum/csrf-cookie`.
- **Allowed methods:** GET, POST, PUT, PATCH, DELETE, OPTIONS.
- **Allowed origins:** Whitelist from the `ALLOWED_ORIGINS` environment variable, plus `FRONTEND_URL` and `APP_URL`. Production requires explicit domains (no wildcards).
- **Allowed headers:** Accept, Authorization, Content-Type, X-Requested-With, X-XSRF-TOKEN.
- **Exposed headers:** X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset.
- **Credentials:** Enabled (required for cookie-based Sanctum auth).
- **Preflight cache:** 3,600 seconds (1 hour).

---

## CSRF Protection

CSRF protection uses two mechanisms depending on the request type:

**Web routes:** The `VerifyCsrfToken` middleware validates a CSRF token on all state-changing requests. Laravel automatically includes the token in forms rendered by Blade templates.

**API routes (SPA):** Sanctum's stateful authentication uses a cookie-based CSRF flow:

1. The frontend sends `GET /sanctum/csrf-cookie`.
2. The server sets an `XSRF-TOKEN` cookie.
3. The frontend reads this cookie and sends it as an `X-XSRF-TOKEN` header on subsequent requests.

---

## Session Configuration

`config/session.php` configures server-side sessions:

| Setting | Value |
|---|---|
| Driver | File |
| Lifetime | 120 minutes |
| Cookie flags | HTTP-only, SameSite=lax |
| Encryption | Disabled |
| Garbage collection | 2% chance per request (lottery 2/100) |

Note: The 120-minute session lifetime applies to server-side sessions (used for MFA state and CSRF tokens). API authentication uses Sanctum tokens with a separate 480-minute expiry.

---

## GDPR Compliance

### Consent Tracking

The `UserConsent` model records user consent decisions:

- **Consent types:** `terms`, `privacy`, `marketing`, `data_processing`.
- Each consent record includes a `version` string and a `consented` boolean.
- Full consent history is retained for audit purposes.

**Endpoints:**

| Endpoint | Method | Purpose |
|---|---|---|
| `/api/auth/gdpr/consents` | GET | Returns current consent status for all types. |
| `/api/auth/gdpr/consents` | PUT | Updates consent for a specific type. |
| `/consents/history` | GET | Returns the full consent change history. |

### Data Export (Subject Access Request)

**Endpoint:** `POST /api/auth/gdpr/export` (rate limit: 3 per hour)

Users can request a full export of their personal data in JSON or CSV format. The process runs asynchronously:

1. The server creates a request with status `pending`.
2. A background job processes the export, changing status to `processing`.
3. On completion, the server sets status to `completed` and generates a download link.
4. The server marks failed exports as `failed` and uncollected exports as `expired`.

### Account Erasure (Right to Erasure)

Account deletion follows a verified multi-step process, with all steps rate-limited to 3 per minute:

1. **Initiate:** The user requests account erasure. Status is set to `pending`.
2. **Verify:** A 6-digit email verification code must be submitted to confirm intent.
3. **Execute:** The account and associated data are deleted. Status changes to `processing`, then `completed`.

The erasure record tracks which `data_categories_deleted` were removed. Users can cancel the request before execution, setting the status to `cancelled`.

---

## Audit Logging

The `AuditLog` model records security-relevant events across the application.

**Event types:**

| Type | Examples |
|---|---|
| `auth` | Login, logout, failed login, MFA events |
| `data_access` | Viewing sensitive records |
| `data_change` | Creating, updating, or deleting records |
| `admin` | Administrative actions |
| `gdpr` | Data export, account erasure, consent changes |

**Action constants** (20+ defined): `LOGIN_SUCCESS`, `LOGIN_FAILED`, `LOGOUT`, `MFA_ENABLED`, `MFA_DISABLED`, `PASSWORD_CHANGED`, `PASSWORD_RESET_COMPLETED`, and others.

**Fields recorded per entry:**

- `user_id` -- The user who performed the action (null for unauthenticated events).
- `event_type` -- Category of event.
- `action` -- Specific action constant.
- `model_type` and `model_id` -- The affected database record, if applicable.
- `old_values` and `new_values` -- JSON snapshots of changed data.
- `metadata` -- Additional context (e.g., login method, device info).
- `ip_address` and `user_agent` -- Request origin.

**Query scopes:** `byEventType()`, `byUser()`, `byModel()`, `recent()`.

**Retention:**

- Standard audit logs: 90 days.
- GDPR-related logs: 2,555 days (approximately 7 years), as required for regulatory compliance.
