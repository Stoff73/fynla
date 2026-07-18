# Apple Python Verifier Bridge Design

**Date:** 18 July 2026  
**Status:** Approved direction; written-spec review pending  
**Scope:** iOS Package 4 server-side Apple verification only  
**Environment:** Development only until the normal release programme authorises production

## Decision

Use Apple's official Python App Store Server Library behind the existing Laravel backend. Laravel remains the only public API, authentication authority, user store, entitlement authority and client integration point. The iOS app never calls Python directly and receives no new backend URL.

The bridge runs as a bounded local process on the Laravel host rather than as a second HTTP service. This preserves the current SiteGround-shaped deployment model, avoids a new public port or service account, and keeps every desktop, `/m` and iOS entitlement read on the same canonical Laravel path.

## Approaches considered

1. **Apple-maintained Python library through a local Laravel process bridge — selected.** This supplies Apple's complete certificate-chain and OCSP behaviour while preserving one public backend. It adds a pinned Python runtime dependency and modest process-start overhead.
2. **Private persistent Python HTTP sidecar — rejected for this package.** It could reduce process-start overhead but would add another daemon, port, health model and deployment boundary without a demonstrated load need.
3. **Wait for or patch the third-party PHP verifier — rejected.** Waiting blocks Package 4 indefinitely. A Fynla-maintained OCSP/JWS patch would create precisely the custom cryptographic implementation the package plan prohibits.

## Seamless client and entitlement flow

1. The iOS app authenticates with the existing Laravel native API and current Fynla user identity.
2. StoreKit returns a locally verified signed transaction.
3. iOS posts that signed transaction to the existing Laravel backend route.
4. Laravel checks authentication, native-client policy, size limits and the user's server-issued `appAccountToken` context.
5. Laravel invokes the internal Python verifier with the signed value through standard input. There is no shell interpolation and the signed value is never placed in command-line arguments.
6. The Apple-maintained library verifies the certificate chain, signature, OCSP status, bundle and environment. The bridge then enforces the exact product allowlist and account token.
7. Python returns a small versioned JSON result containing only allowlisted decoded fields or a stable failure code.
8. Laravel performs all idempotency, persistence and provider-neutral entitlement projection in MySQL.
9. Laravel acknowledges the transaction and returns the canonical entitlement. Only then does iOS finish the StoreKit transaction.
10. Desktop, `/m` and iOS read that same Laravel entitlement resolver, so a purchase made on iOS appears for the same user on every surface without a second account or synchronisation system.

## Component boundaries

### Laravel

- `AppleSignedDataVerifier` remains a Fynla-owned PHP interface.
- `PythonAppleSignedDataVerifier` implements it through an injected process runner.
- `AppleStoreServerClient` uses the same bridge pattern for later App Store Server API reconciliation operations.
- Laravel owns authentication, route validation, idempotency, transaction boundaries, database writes, capability resolution and API responses.
- No Apple Python package type crosses into controllers, models or entitlement services.

### Python

- A self-contained `services/apple-store-bridge/` package contains the bridge entry point, pinned dependency lock and tests.
- The entry point accepts one versioned JSON request from standard input and emits one versioned JSON response to standard output.
- Supported operations are explicit: signed transaction, signed notification, signed renewal information and the later allowlisted App Store Server API calls required by Package 4.
- Unknown operations, fields, environments and products fail closed.
- The process writes diagnostics only to standard error using stable codes; it never writes signed payloads, certificate chains, private keys or decoded personal values.

### Configuration

- Laravel supplies trusted non-secret expectations from server configuration: bundle ID, environment, allowed products, root-certificate path and production numeric app ID.
- The bridge permits deployed `SANDBOX` or `PRODUCTION` only. `XCODE` and `LOCAL_TESTING` verification bypasses are rejected at the Laravel and Python boundaries.
- App Store Server API private-key material is environment-only and stored in a server-local file outside the public root with restricted permissions. Python reads the configured file directly; Laravel does not place the key in process arguments or logs.
- The Python executable path and bridge entry-point path are server configuration. Neither is client-controlled.

## Dependency and trust policy

- Pin the official `app-store-server-library` release and every Python transitive dependency with hashes in a reviewed lock file.
- Record the Apple repository commit/release, MIT licence, supported Python version, security/update policy and deployment requirements.
- Keep `AppleRootCA-G3.cer` checked into `resources/certificates/apple/` with its subject, issuer and SHA-256 fingerprint test.
- Remove the rejected `hoels/app-store-server-library-php` dependency and its new transitives from Composer during implementation. Retain the rejection review as durable evidence.
- Before dev deployment, verify Python, process execution and required outbound OCSP/API connectivity on the current backend host. Failure blocks Apple billing but does not affect login or existing non-Apple entitlement reads.

## Process and data contract

The PHP runner starts an argv-array process, sets a strict timeout and provides the request through standard input. It supplies a minimal environment and never invokes a shell.

Successful responses include only fields required by Fynla's immutable DTOs, such as transaction ID, original transaction ID, bundle ID, environment, product ID, app account token, purchase/expiry/revocation times, ownership type and transaction reason. Notification responses likewise contain only the notification UUID/type/subtype and the independently verified nested result needed for processing.

The bridge never returns or persists the input JWS, `x5c` chain or App Store private key. Laravel stores the SHA-256 evidence hash plus the plan's allowlisted decoded fields.

Stable failures distinguish malformed input, invalid signature/chain, invalid bundle, invalid environment, invalid product, invalid account token, retryable online-verification failure, App Store API failure and verifier unavailability. Clients receive safe billing errors, not certificate or subprocess details.

## Failure behaviour

- Verification, process, timeout or JSON-contract failure cannot create or extend an entitlement.
- Laravel does not acknowledge an unverified transaction, and iOS leaves it unfinished for the normal StoreKit update/reconciliation path.
- Retryable Apple/network failures are distinct from permanent verification failures and use bounded retries in server jobs where appropriate.
- Existing canonical entitlements remain readable if the verifier process is unavailable; only new Apple state changes fail closed.
- Process concurrency and timeout limits prevent verifier calls from exhausting PHP workers.

## Testing and deployment gates

- Python unit tests exercise official sandbox fixtures plus tampered signature/header/payload, wrong bundle/environment/product/token and OCSP failure paths.
- PHP unit tests exercise interface mapping, safe process construction, standard-input transport, timeouts, malformed output and stable error mapping.
- PHP/Python contract tests run the real bridge without network where fixtures permit; online checks are covered by controlled integration/development evidence.
- Feature tests prove the iOS submission route persists only verified transactions and returns the same canonical entitlement exposed through desktop, `/m` and native APIs.
- CI installs the hashed Python lock, runs its test suite, then runs Laravel billing tests and native Swift tests.
- Development deployment verifies the current Laravel host can execute the pinned Python runtime and reach the required Apple endpoints before Apple billing is enabled.
- No production host access, configuration or activation is part of this Package 4 development work.

## Package-plan changes

Task 1 is replaced by a reviewed official-Python dependency/runtime gate and removal of the rejected PHP dependency. Tasks 2–4 remain unchanged. Tasks 5 and 7 use the Python-backed implementations of the existing Fynla interfaces. Tasks 6, 8–11 remain Laravel/Swift work and retain their existing API contracts and acceptance criteria.

This architecture does not change the iOS-to-backend contract: the current Laravel backend remains the single seamless bridge between StoreKit, Revolut, desktop, `/m` and the native app.
