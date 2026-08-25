# Fynla — Full Vulnerability & Security Code Review

**Date:** 2026-06-09 · **Branch:** `dev` · **Scope:** Laravel 10 + Vue 3 + Sanctum + MySQL 8 web app **and** the `/m` web mobile pathway. **Out of scope:** iOS native Capacitor app (legacy, per CSJ).

**Method:** 8 parallel specialised review agents, one per attack surface, each grounding every finding in `file:line` with severity + confidence. This is a read-only review — no code was modified.

---

## Executive summary

The codebase is in **good security health**. The two highest-yield attack surfaces came back clean: **no Critical or High IDOR** anywhere (ownership scoping is strikingly consistent), and **no injection** (raw SQL is parameterised/whitelisted, `exec()` uses `escapeshellarg`, no `unserialize`/`eval`, SSRF surfaces host-locked). Secrets are clean (none committed), the Revolut webhook is HMAC-verified with constant-time compare + replay window, and client-side hygiene is solid (correct token storage, DOMPurify for rich text, no `postMessage`).

The findings concentrate in: **auth enforcement wiring** (MFA middleware not attached; token-refresh over-privilege; MFA brute-force), **payment confirmation completeness** (no amount check; activates on un-captured states; a fail-open tier gate), **AI write-gating model** (denylist not allowlist), **dependency CVEs**, and **header/proxy hardening**.

| Severity | Count |
|----------|-------|
| Critical | 0 |
| High | 6 |
| Medium | 17 |
| Low | 15 |
| Info | 4 |

**Top 6 to action first:** H1 (MFA middleware unwired), H2 (refresh token `['*']`), H3 (MFA brute-force), H4 (payment amount not verified), H5 (tier gate fails open), H6 (dependency CVEs).

---

## HIGH

### H1 — `mfa.verified` middleware is registered but attached to ZERO routes
- **Domain:** Auth · **Confidence:** High
- **Location:** `app/Http/Kernel.php:126` (alias); 0 usages across `routes/*.php`
- **Detail:** `EnsureMFAVerified` enforcement is dead code on the live surface. MFA holds only because every token-minting path *happens* to attach the `mfa_verified` ability. There is no per-request guard that a request actually traversed MFA.
- **Impact:** If any path mints a token without the ability (or with `['*']` — see H2), nothing downstream rejects it. The control is an unenforced invariant.
- **Fix:** Attach `mfa.verified` to the authenticated route groups (at minimum the sensitive module groups) in `routes/api.php`.

### H2 — Mobile token-refresh mints a full-ability (`['*']`) token
- **Domain:** Auth · **Confidence:** High
- **Location:** `app/Http/Controllers/Api/V1/Auth/TokenRefreshController.php:39`
- **Detail:** `createToken('mobile-token', ['*'], ...)`. Wildcard ability makes `can('mfa_verified')` return true regardless of whether MFA happened, and widens privilege on rotation vs the web token's scoped `['mfa_verified']`. Route is in `PreviewWriteInterceptor::EXCLUDED_ROUTES`, so preview users also get a `*` token.
- **Impact:** Privilege widening on refresh; defeats H1's intended guard if it were wired.
- **Fix:** Mint the refresh token with the same scoped abilities as the original (`['mfa_verified']`), never `['*']`.

### H3 — MFA verify has no per-challenge attempt cap + a ±2 TOTP window
- **Domain:** Auth · **Confidence:** High
- **Location:** `app/Http/Controllers/Api/MFAController.php:149-198`; `app/Services/Auth/MFAService.php:71` (`verifyKey(secret, code, 2)`); lockout `LoginLockoutService.php:88-137`
- **Detail:** The only brake is *account* lockout, which an attacker resets by completing a fresh `/login` (clears `failed_login_count`) to mint a new `mfa_token`. The ±2 window accepts **5 valid 30s codes at once**, ~5× the guess surface. Route throttle is 10/min/IP.
- **Impact:** An attacker with the victim's password can brute-force the 6-digit TOTP: login → fresh `mfa_token` → 10 guesses → repeat.
- **Fix:** Per-user MFA attempt counter that survives challenge re-issuance and is NOT reset by login success; reduce TOTP tolerance from 2 to 1.

### H4 — Payment confirmation never verifies the charged amount
- **Domain:** Payments · **Confidence:** High
- **Location:** `PaymentController.php:509-532` (`confirmPayment`); `WebhookController.php:107-122` (`handleOrderCompleted`)
- **Detail:** Both fetch the Revolut order then check only `state` — the returned `amount`/`currency` is discarded and never compared to the `Payment` row. (Front-door price tampering IS closed — orders are created at server-derived prices — but the confirmer asserts no amount invariant.)
- **Impact:** A mismatched-but-`completed` order still activates the full subscription. Any future path that influences the amount, a partial capture, or an order-id swap goes undetected.
- **Fix:** After `getOrder`, assert `(int)order_amount.value === (int)payment.amount` and currency match before flipping to `completed`; on mismatch log `error`, leave `pending`, do not activate.

### H5 — `CheckFeatureAccess` fails OPEN for the entire SP2 cohort (tier-gate bypass)
- **Domain:** Payments / Authorization · **Confidence:** High
- **Location:** `app/Http/Middleware/CheckFeatureAccess.php:16,47-54`; live on `routes/api.php:299` (`feature:standard` letter-to-spouse), `:872` (`feature:pro` estate), `:1045` (`feature:pro` holistic), `:1366` (`feature:standard` what-if-scenarios)
- **Detail:** `PLAN_ORDER` is the legacy `['student','standard','family','pro']`. SP2 users carry `free|tier1|tier2|tier3` in `subscription.plan`; `array_search` returns `false`; line 52 "allow through (defensive)" lets them pass. Unknown-input in an authz gate must DENY, not ALLOW.
- **Impact:** Every current (SP2) user passes `feature:pro`/`feature:standard` on the estate, holistic-planning, what-if-scenarios, and letter-to-spouse route groups — paid features reachable without the paid tier. (Real-world exposure narrows if those features are *also* gated by `CheckSubscription` capability checks or client-side — verify; the broken gate must still be fixed.)
- **Fix:** Resolve tier via `TierResolver` and **fail closed** on unknown tiers, or retire `feature:` in favour of the `tier_configurations` capability matrix used by `CheckSubscription::checkCapability`.

### H6 — Dependency vulnerabilities (Composer + npm), incl. reachable framework CVEs
- **Domain:** Infra · **Confidence:** High
- **Location:** `composer audit` (4 advisories) + `npm audit` (1 critical, 3 high, 5 moderate)
- **Detail (runtime-reachable Composer):**
  - `laravel/framework` — CVE-2026-48019 (CRLF injection, default email rule)
  - `symfony/http-foundation` — CVE-2026-48736 (IPv6 transition forms bypass `PRIVATE_SUBNETS` → SSRF guard bypass)
  - `symfony/routing` — CVE-2026-48784 (UrlGenerator dot-segment encoding)
  - `symfony/polyfill-intl-idn` — CVE-2026-46644 (low)
  - **npm:** critical `vitest` (dev-only), high `serialize-javascript`/`tar` (build-only), moderate `vite` (dev), moderate `@capgo/capacitor-native-biometric` **auth-bypass** (gates the *out-of-scope* iOS Face ID — note for when iOS work resumes).
- **Fix:** `composer update laravel/framework symfony/http-foundation symfony/routing symfony/polyfill-intl-idn` then re-audit; `npm audit fix` (clears critical vitest, non-breaking). Schedule breaking transitive bumps (`tar`, `serialize-javascript`, `vite`) deliberately — `vite.config.js` is blank-screen-sensitive.

---

## MEDIUM

### Auth
- **M1 — User enumeration + PII leak via `/api/auth/register`.** `AuthController.php:68-77`: for a soft-deleted account, `register` returns `first_name`, `deleted_at`, `deletion_reason` with **no password check** (the `login`/`RestoreAccountController` paths correctly verify first). Anyone can confirm an account exists and learn the owner's first name + deletion date. *Fix: don't disclose restorability from `register`; require the authenticated restore flow.*
- **M2 — Login user-enumeration via timing/lockout divergence.** `AuthController.php:196-236`: non-existent users skip the bcrypt path (timing) and only ever get `401`, while existing users can reach `423 Locked`. *Fix: dummy `Hash::check` for the no-user branch; equalise the locked-vs-invalid signal pre-auth.*
- **M3 — Email-verification login code brute-force is resettable.** `EmailVerificationCode.php:90,136-145`: `findValidCode` caps `failed_attempts < 5` per row, but each `/login` calls `generate()` which deletes and recreates the row with `failed_attempts=0`, resetting the counter. 6-digit space, ~10 guesses/min. *Fix: track failed attempts per user+type across regenerations.*
- **M4 — Non-constant-time code comparison.** `EmailVerificationCode.php:136` (`where('code',$code)`) and `AuthController.php:516` (registration `!==`) compare secrets directly, inconsistent with the `hash_equals` used for password reset (`PasswordResetService.php:82`). *Fix: fetch by user+type, compare with `hash_equals`.*
- **M5 — Eval token-minting endpoint live if `APP_ENV ∈ {local,staging}`.** `EvalAuthController.php:47`, `routes/api.php:1424`: `POST /api/eval/login/{persona}` mints a `bypass-preview-mode` token for known persona names with **no secret**, only `throttle:20,1`. Code gating is fail-closed for production; the risk is purely operational — **confirm csjones.co runs `APP_ENV=production`** (or add a shared secret even in allowed envs). *This is the single highest-value operational check.*
- **M6 — Sanctum TTL env-mutable, no idle revocation.** `config/sanctum.php:52`: lifetime is `env('SANCTUM_TOKEN_EXPIRATION',240)` with no absolute server cap; `UserSession.last_activity_at` is collected but never used to revoke. A leaked bearer is valid for the full TTL. *Fix: absolute cap + idle-revocation sweep.*

### Payments
- **M7 — Activates subscription on `pending`/`processing` Revolut states.** `PaymentController.php:515-518`: auto-capture treats `['completed','processing','pending']` as acceptable and immediately activates the paid tier. A payment that later declines yields a free tier window. *Fix: only `completed` activates for automatic capture.*
- **M8 — Renewal handler can attach payment to the wrong subscription.** `SubscriptionRenewalService.php:41-47`: when no `Payment` matches the order, it falls back to "latest active subscription" globally — not bound to the order's customer. *Fix: resolve strictly via `customer.id`/`merchant_order_ext_ref`; never fall back to latest-active.* (Exploitability depends on whether `handleRenewalPayment` is webhook-wired.)
- **M9 — Webhook `merchant_order_ext_ref` mismatch logged but not enforced.** `WebhookController.php:91-98`: mismatch only `Log::warning`s; tier orders are created with `merchantRef=null` and upgrades use `"upgrade_{id}"` ≠ expected `"payment_{id}"`, so the tamper-detection is effectively a no-op. *Fix: set a consistent reference on every order; abort (don't just log) on mismatch.*

### AI / Fyn
- **M10 — Write-tool gating is a DENYLIST, not an allowlist.** `AdviceFyn.php:152-184,560-579`: advice mode builds the full catalogue then `array_diff`s a hardcoded `WRITE_TOOLS` list (whose comments record a history of tools retroactively discovered to write). A newly-added write tool not added to the list is silently exposed on the read-only advice surface — the exact contract violation GroundGate is meant to prevent (GroundGate is **not** on `dev` yet — confirmed). *Fix: invert to an allowlist, or derive write-ness mechanically from `CoordinatingAgent::operationFor()`; add a Pest test asserting every `operationFor()==='write'` tool is in `WRITE_TOOLS` until GroundGate lands.*
- **M11 — Verbatim financial PII in the signed audit chain, no purge path.** `CoordinatingAgent.php:1084-1103` (`summariseInput` copies every scalar arg, truncate-only) → `AuditChainService.php:73,93`. DOB, balances, postcode, gift recipients land in `ai_audit_events.input_summary` in plaintext, signed into an immutable hash chain — complicating GDPR Art. 17 erasure. *Fix: redact/hash sensitive keys (store presence/length, not value); define a chain-compatible erasure (tombstone the value, preserve the hash).*

### Injection / XSS
- **M12 — Mass assignment: advisor can reassign `advisor_id`/`client_id`.** `AdvisorController.php:174` → `ClientActivityService.php:48-58`: `updateActivity` passes `$request->all()` into `$activity->update()`; `ClientActivity` `$fillable` includes the ownership keys. The scope protects the lookup, not the write. (Sibling `storeActivity` uses a FormRequest; update doesn't.) *Fix: add `UpdateClientActivityRequest` whitelisting mutable fields; use `validated()`.*
- **M13 — Stored XSS: `DocumentArticle.html_body` bypasses HTMLPurifier on UPDATE.** `Admin/DocumentArticleController.php:55-60`: import runs `HTMLBodySanitiser`, update does not; `SanitizeInput.php:37` exempts `html_body` on the (false) assumption purification always runs downstream; rendered via `v-html` at `InsightArticlePage.vue:93` to unauthenticated public visitors. *Fix: run `HTMLBodySanitiser::sanitise()` in `update()`.*
- **M14 — Bypassable regex HTML sanitiser for Insight blocks.** `StoreInsightArticleRequest.php:102-132` strips only *quoted* `on*=` handlers and quoted `javascript:` — defeated by unquoted handlers, entity-encoded schemes, `data:`; `<a>` is allowlisted; rendered via `v-html`. The frontend backstop `resources/js/utils/sanitizeHtml.js` is *also* regex-based with the same blind spots. *Fix: HTMLPurifier server-side (`mews/purifier` already present), DOMPurify client-side.*

### /m mobile + Infra
- **M15 — `m_scaffold_token` (Sanctum bearer) stored in `localStorage`.** `resources/mobile/store.js:4,35-38`; bridged at `mScaffoldBridge.js:23-25`. Violates the sessionStorage-only token policy (desktop uses `sessionStorage` and scrubs legacy localStorage at `app.js:43`). Any same-origin XSS can exfiltrate a full bearer (Bearer-only, no CSRF needed) — and this compounds with M16 + M17/M18. *Fix: if the localStorage choice is deliberate (iOS context partitioning), document the accepted risk + ensure short-TTL rotation + tighten CSP; otherwise prefer in-memory + refresh-token.*
- **M16 — CSP `'unsafe-inline'` on `script-src` in production.** `SecurityHeaders.php:84` (flagged by both the `/m` and infra agents). Negates CSP's XSS mitigation — any same-origin HTML injection becomes script execution, turning M15 into direct token theft. (TODO to nonce-based CSP already noted in code; Revolut SDK is the blocker.) *Fix: migrate to nonce-based CSP; at minimum drop `'unsafe-inline'` from `script-src`.*
- **M17 — `TrustProxies` trusts ALL proxies (`$proxies = null`).** `TrustProxies.php:17`: `X-Forwarded-*` accepted from any upstream → `request()->ip()` spoofable, defeating the per-IP auth throttles and poisoning audit logs. *Fix: pin `$proxies` to the host's proxy CIDR.*
- **M18 — `/m/app/*` framable SAMEORIGIN unconditionally.** `SecurityHeaders.php:31-39`: framing relaxation for `/m/app` isn't gated on `Sec-Fetch-Dest` (unlike the rest of the funnel). `frame-ancestors 'self'` blocks cross-origin framing so it's not externally exploitable — defence-in-depth gap only. *Fix: gate the `/m/app` framing allowance on actual framed loads.*
- **M19 — Non-500 exception messages passed to clients.** `app/Exceptions/Handler.php:96-103`: only `500`s are masked in production; `HttpException`/domain exceptions (e.g. `FinancialCalculationException`) surface their raw message at 4xx codes. *Fix: whitelist exception types whose message may surface; mask the rest.*

---

## LOW

| ID | Domain | Finding | Location |
|----|--------|---------|----------|
| L1 | Auth | `is_advisor` is mass-assignable (not in `$guarded`); no reachable sink today (FormRequest `validated()` gates the paths) | `app/Models/User.php:53-61` |
| L2 | Auth | MFA setup secret cached **unencrypted** for 5 min (persisted form is encrypted) | `MFAController.php:52` |
| L3 | Auth | `logoutBeacon` unauthenticated, revokes a token from the body (self-DoS only; holding the plaintext already implies access) | `AuthController.php:334-371` |
| L4 | Auth | Restoration token is the cache **key** (not hashed); high-entropy + single-use + 5-min TTL so low risk | `RestoreAccountController.php:47` |
| L5 | Access | PreviewWriteInterceptor prefix/substring matching can auto-exclude *future* nested/`/analyze`-like routes from interception → latent preview-write bypass | `PreviewWriteInterceptor.php:124` |
| L6 | Access | Preview "fake success" echoes back arbitrary submitted fields (denylist drift; own data only) | `PreviewWriteInterceptor.php:210-217` |
| L7 | Access | JointAccountLog over-loads the joint owner's `email` (only `name` is rendered) | `JointAccountLogController.php:34` |
| L8 | Access | Joint investment holdings scoped to `user_id` only (over-restriction vs the joint pattern; functional, not a leak) | `InvestmentController.php:779-781,826-828` |
| L9 | Payments | No idempotency on `create-order`/`upgrade` (confirm IS idempotent) → order/Payment-row spam | `routes/api.php:1107-1111` |
| L10 | Payments | Trial-extension discount path has no cumulative cap in the controller (depends on `DiscountCodeService::validate` per-user caps — verify) | `PaymentController.php:235-252` |
| L11 | Payments | Upgrade proration can charge ~1p near period end (revenue nit, business decision) | `PaymentController.php:799-810` |
| L12 | Payments | Revolut API error responses logged with full body (PII: email/name; no PAN) | `RevolutService.php:71-76,109-113,176-181` |
| L13 | AI | `POST /conversations/{id}/action` lacks the consent gate `sendMessage` enforces | `AiChatController.php:527-601` |
| L14 | AI | `delete_record` confirmation token is deterministically derivable (protected only because `user_id` isn't in LLM context) | `CoordinatingAgent.php:4301-4308` |
| L15 | AI | Streaming error handler logs `$e->getMessage()` which may carry PII | `AiChatController.php:269-275`; `CoordinatingAgent.php:979,991` |
| L16 | Injection | `ConfirmExtractionRequest` accepts any `data.*` key → user can set any `$fillable` on their own record (ownership enforced) | `ConfirmExtractionRequest.php:22-28` |
| L17 | Injection | Contact-form subject built from unsanitised `name` (CRLF; mitigated by Symfony Mailer rejecting CR/LF) | `ContactFormController.php:54-55` |
| L18 | XSS | Insights carousel interpolates API titles into `innerHTML` without per-field escaping (mitigated by server `strip_tags`) | `public/pages/js/index.js:308-318` |
| L19 | XSS | `target="_blank"` without `rel="noopener"` on 3 static gov/FCA links | `StatePensionForm.vue:25`, `DCPensionForm.vue:550`, `PublicLayout.vue:375` |
| L20 | Infra | Staging (`csjones`) ships `APP_DEBUG=true` on an internet-facing host | `deploy/csjones-fynla/.env.production:19` |
| L21 | Infra | Public `mockup-*.html` (×8) + `public/.DS_Store` served, not robots-blocked (design-intent disclosure) | `public/` |
| L22 | Infra | CORS `supports_credentials:true` keeps `http://localhost`/`capacitor://localhost` as credentialed origins in all envs | `config/cors.php:54` |

---

## INFO / verified clean

- **No Critical/High IDOR** — every module CRUD (Property, Mortgage, Investment, Savings, Retirement, Estate, Goals, LifeEvent, Documents, Plans, BusinessInterest, Chattel, PersonalAccounts, WhatIfScenario, HolisticPlanning) scopes by `user_id` or the `user_id OR joint_owner_id` joint pattern. Every `Api/Admin/` controller is behind `permission:admin.access`; user edit/delete add `users.edit`/`users.delete`.
- **No SQL injection** — `DB::raw`/`whereRaw`/`orderByRaw` sites are parameterised or column-whitelisted (`CoordinatingAgent.php:3481-3487`). No user-controlled `orderBy`/column sink.
- **No command injection** — `AdminController` mysqldump/mysql wrap every interpolated value in `escapeshellarg`, server-generated filenames, `realpath` containment, `permission:admin.backup` + `throttle:3,1`. `ArticleScraperService` uses `Process` with an array (no shell).
- **No `unserialize`/`eval`/dynamic-class-from-request**; SSRF surfaces (postcode, AI providers, scraper) are regex/host-locked or admin-curated.
- **File upload safe** — `DocumentUploadService` magic-byte MIME allowlist, UUID filenames, private disk, 20 MB cap.
- **Secrets clean** — none committed; tracked env files are placeholder templates; `.gitignore` covers `.env*`/`storage/*.key`; git-history pickaxe found no real keys.
- **Revolut webhook exemplary** — HMAC-SHA256 over `v1.{ts}.{rawBody}`, `hash_equals`, 5-min replay window, fails closed when secret unset; route correctly unauthenticated + signature-gated.
- **Price tampering closed** — order amounts server-derived from `tier_configurations`/`SubscriptionPlan`; no client-supplied amount accepted.
- **AI IDOR closed** — conversations use `forUser()`; tool handlers re-scope to the auth user; `update_profile`/`update_record` use positive allowlists excluding `is_admin`/`email`/`password`/`user_id`/NI; handoff event consumed internally (INV-2.4.1); `MAX_ADVICE_CHAIN=6` recursion cap present.
- **`TransientToken::$id` family clean** — all `currentAccessToken()` consumers guard with `instanceof PersonalAccessToken`.
- **Client-side clean** — token storage correct (sessionStorage/Keychain), no `postMessage`, no hardcoded secrets, no token-in-URL, Insights rich-text via DOMPurify, server-rendered savetax pages never reflect GET params, `?to=` open-redirect guard sound, mobile cache keys per-user, ShareController not IDOR-able.
- **Headers/cookies** — nosniff, Referrer-Policy, context-aware X-Frame-Options + `Vary`, HSTS in prod, restrictive Permissions-Policy, COOP same-origin; cookies secure/httpOnly/lax/encrypted; `.htaccess`-vs-middleware header split correct (no duplication).
- **Rate limiting** — named `auth-N` limiters keyed by `path|ip` (no shared-bucket self-throttle); chat `throttle:20,1`; mobile dashboard/device limiters keyed per user/IP.

---

## Recommended sequencing

1. **This sprint (High):** wire `mfa.verified` (H1) → scope the refresh token (H2) → add MFA attempt cap + tighten TOTP window (H3) → add payment amount assertion (H4) → make the tier gate fail closed (H5) → patch Composer/npm CVEs (H6).
2. **Operational, now:** confirm `APP_ENV` on csjones (M5) and `APP_DEBUG=false` on staging (L20).
3. **Next:** allowlist the AI write-tool gate / add the operationFor parity test (M10), redact audit-chain PII (M11), sanitise `DocumentArticle` on update + replace regex sanitisers with HTMLPurifier/DOMPurify (M13/M14), `UpdateClientActivityRequest` (M12).
4. **Hardening backlog:** nonce CSP (M16), pin TrustProxies (M17), move m_scaffold_token off localStorage or document the accepted risk (M15), exception-message whitelist (M19), the Low items.
