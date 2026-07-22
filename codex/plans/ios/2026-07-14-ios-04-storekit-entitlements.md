# iOS Package 4: StoreKit and Provider-Neutral Entitlements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Use `superpowers:test-driven-development`, `security-and-hardening` throughout, `systematic-debugging` for every failure, `verify-m` for shared-entitlement checkpoints, and `verification-before-completion` before the gate. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let authenticated iPhone users buy or restore Premium through StoreKit while Apple and Revolut resolve to one server-authoritative Premium entitlement on native, desktop and `/m`.

**Architecture:** StoreKit 2 performs the device purchase and local verification. Laravel independently verifies signed Apple data, persists immutable transaction evidence, derives a provider-neutral Premium grant and returns the canonical capability matrix. Existing Revolut subscriptions are adapted into the same resolver. The client finishes a StoreKit transaction only after durable server acknowledgement.

**Tech Stack:** StoreKit 2, Swift concurrency, StoreKit Test, XCTest; Laravel 10, MySQL 8, Pest, Symfony Process, Python `>=3.12,<3.13` and Apple's official `app-store-server-library==3.1.2` behind Fynla-owned PHP interfaces, App Store Server API and App Store Server Notifications V2.

## Global Constraints

- Products are exactly `org.fynla.premium.monthly` and `org.fynla.premium.annual` in one subscription group and at one subscription level.
- UK target prices are £6.99/month and £59.99/year; native display uses StoreKit-localised values.
- No trial, introductory offer, Family Sharing, promotional offer, offer code or external payment link.
- Purchase requires an authenticated Fynla user and server-issued `appAccountToken` UUID.
- Server verification checks certificate chain, signature, bundle, environment, product allowlist and account token.
- Production requires an App Store numeric app ID supplied through environment configuration; never put it in Swift.
- A current Apple or Revolut Premium grant suppresses another purchase call to action.
- Cancellation preserves access through verified period end. Expiry/revocation returns to Free without deleting data.
- Account deletion does not cancel Apple billing automatically.
- Never log or persist full signed payloads; store SHA-256 evidence and decoded allowlisted fields.
- Apple's official Python verifier is isolated behind `AppleSignedDataVerifier`; Python dependencies stay hash-locked and receive a security/licence review before deployment.
- The iOS app calls only the current Laravel backend. Python is an internal bounded process and never becomes a public client endpoint or identity store.

## File map

| Path | Responsibility |
|---|---|
| `services/apple_store_bridge/` | Hash-locked official Apple Python verifier/API bridge and tests |
| `composer.json`, `composer.lock` | PHP dependencies; the rejected PHP App Store verifier must be absent |
| `config/apple_store.php`, `.env.example` | Product, bundle, app ID and server API configuration names |
| `resources/certificates/apple/` | Apple public root certificate used by verifier |
| `database/migrations/2026_07_16_000001_add_apple_account_token_to_users_table.php` | Stable per-user UUID |
| `database/migrations/2026_07_16_000002_create_premium_entitlements_table.php` | Provider-neutral grant projection |
| `database/migrations/2026_07_16_000003_create_apple_transactions_table.php` | Verified transaction evidence |
| `database/migrations/2026_07_16_000004_create_apple_notification_logs_table.php` | Notification idempotency/audit |
| `app/Services/Billing/` | Entitlement resolver and provider adapters |
| `app/Services/Billing/Apple/` | Signed-data verification, API client and reconciliation |
| `app/Http/Controllers/Api/V1/Native/Billing/` | Authenticated native billing endpoints |
| `app/Http/Controllers/Api/Webhooks/AppleNotificationController.php` | Unauthenticated but signature-verified webhook |
| `ios-native/Fynla/Core/StoreKit/` | Product/purchase/update transport |
| `ios-native/Fynla/Features/Subscription/` | Premium screen and management |
| `ios-native/StoreKit/Fynla.storekit` | Deterministic local StoreKit products |

### Task 1: Lock and audit Apple's official Python verifier runtime

**Files:** Remove the rejected PHP verifier from `composer.json`/`composer.lock`; create the hash-locked Python runtime and security review; retain and test the checked-in Apple root certificate.

- [ ] Execute Bridge Task 1 in `codex/plans/ios/2026-07-18-ios-04-apple-python-verifier-bridge.md` exactly.
- [ ] Preserve `docs/security/apple-store-verifier-review.md` as the durable rejection record for the incomplete PHP OCSP path.
- [ ] Do not write or adopt a custom JWS/OCSP verifier.
- [ ] Do not enable Apple billing until the current development backend passes the Python/process health gate.

**Intended review boundary:** `build: use official apple server verifier`

### Task 2: Create provider-neutral billing persistence

**Files:** Create four migrations and models `PremiumEntitlement.php`, `AppleTransaction.php`, `AppleNotificationLog.php`; modify `User.php`; create schema/model tests.

- [ ] Write failing schema tests first.
- [ ] Add nullable unique UUID `apple_app_account_token` to users; hide it from normal serialization and guard it from request mass assignment.
- [ ] Create `premium_entitlements`: user ID, provider (`apple|revolut`), provider reference, nullable product ID, status (`active|grace_period|billing_retry|cancelled|expired|revoked`), `will_renew`, period start/end, nullable grace end/revoked times, `last_verified_at`, JSON provider metadata, timestamps; unique provider plus provider reference.
- [ ] Create `apple_transactions`: user ID, entitlement ID, transaction ID unique, original transaction ID indexed, app account token, product ID, environment, purchase/expiry/revocation times, ownership type, transaction reason, signed payload SHA-256, received/reconciled times, timestamps.
- [ ] Create `apple_notification_logs`: notification UUID unique, environment, notification type/subtype, signed payload SHA-256, processing status (`received|processed|duplicate|rejected|failed`), nullable error code, processed time, timestamps.
- [ ] Do not store raw signed payloads or private keys in any table.
- [ ] Add all billing tables to GDPR erasure/retention logic: user-linked projections/transactions are erased on verified erasure; notification audit retains only non-personal identifiers/hash according to the documented retention rule.
- [ ] Apply migrations safely and reseed per repository rules.

**Intended review boundary:** `feat: add provider neutral entitlement persistence`

### Task 3: Build canonical Premium resolution

**Files:** Create `app/Data/Billing/ResolvedEntitlement.php`, `PremiumEntitlementResolver.php`, `RevolutEntitlementAdapter.php`; modify `TierResolver.php` and the canonical subscription-status service from Package 1; create unit/feature tests.

- [ ] Write failing tests for Free, active Revolut, cancelled-but-in-period Revolut, active Apple, Apple Grace Period, billing retry without grace, expired, revoked and overlapping Apple/Revolut.
- [ ] Define resolver output:

```php
final readonly class ResolvedEntitlement
{
    public function __construct(
        public string $tier,
        public ?string $provider,
        public string $status,
        public bool $renews,
        public ?CarbonImmutable $periodEndsAt,
    ) {}
}
```

- [ ] `PremiumEntitlementResolver::resolve(User)` returns Premium if any provider grant currently confers access; when grants overlap, choose the access record with the furthest verified end for display but retain all audit records.
- [ ] Memoise the resolved result per user for the current request so repeated capability checks do not repeat provider queries; invalidate it after a provider event changes a grant.
- [ ] The Revolut adapter reads the existing canonical Subscription model and maps its status/period without requiring Revolut columns on Apple rows.
- [ ] Make `TierResolver` use this resolver for paid access and otherwise return Free. A stale `users.tier='premium'` without a live provider grant must not grant Premium.
- [ ] Provider event handlers may maintain `users.tier` as a query cache, but capability checks use the resolver.
- [ ] Add cross-surface tests proving Apple Premium appears through `GET /api/auth/user`, canonical subscription status and `/api/v1/mobile/dashboard` capability fields.

Run:

```bash
./vendor/bin/pest tests/Unit/Services/Billing tests/Feature/Payment tests/Feature/Contracts/ClientCompatibilityContractTest.php tests/Feature/Mobile
```

Expected: PASS.

**Intended review boundary:** `feat: resolve premium across billing providers`

### Task 4: Issue stable application account tokens

**Files:** Create `AppAccountTokenService.php`, `AppAccountTokenController.php`; modify `routes/api_v1.php`; create feature tests.

- [ ] Write failing tests for first issue, stable repeat, uniqueness, authentication, preview rejection and account isolation.
- [ ] Generate UUID v4 server-side under a database lock only when the column is null.
- [ ] Return it from `GET /api/v1/native/storekit/account-token` under `auth:sanctum`, `native.client`, ability/session checks.
- [ ] Never derive it from user ID or email and never accept a client-supplied replacement.
- [ ] Response:

```json
{"success":true,"data":{"app_account_token":"75c42f38-62f1-4d0e-94ea-f8270f5d73fd"}}
```

The example is shape-only in documentation; tests use generated UUIDs.

**Intended review boundary:** `feat: issue storekit account association tokens`

### Task 5: Verify signed Apple transactions behind a Fynla adapter

**Files:** Create `AppleSignedDataVerifier.php`, `PythonAppleSignedDataVerifier.php`, the internal bridge client, Fynla DTOs and Python verifier modules; create PHP/Python tests with official sandbox fixtures.

- [ ] Execute Bridge Tasks 2–4 in `codex/plans/ios/2026-07-18-ios-04-apple-python-verifier-bridge.md` exactly.

- [ ] Add configuration keys: bundle ID `org.fynla.app`, allowed products, runtime environment, root certificate path, numeric app ID and online-check policy. Private App Store key values are environment-only.
- [ ] Define a Fynla-owned interface so package types do not escape the Apple adapter:

```php
interface AppleSignedDataVerifier
{
    public function verifyTransaction(
        string $jws,
        string $expectedEnvironment,
        ?string $expectedAppAccountToken,
    ): VerifiedAppleTransaction;

    public function verifyNotification(
        string $jws,
        string $expectedEnvironment,
    ): VerifiedAppleNotification;
}
```

- [ ] Laravel sends signed data only through stdin to the bounded no-shell Python process; the Apple library uses the checked-in root, online checks, bundle, environment and production numeric app ID.
- [ ] After cryptographic verification, enforce product allowlist, exact bundle/environment, UUID app account token and coherent transaction/original transaction IDs/dates.
- [ ] Map library exceptions into stable internal error codes without returning certificate details to clients.
- [ ] Test valid signed sandbox fixtures and tampered signature/header/payload, wrong bundle, wrong product, wrong environment, missing/mismatched account token and expired certificate paths.
- [ ] Do not accept Xcode-local StoreKit signatures on staging/production server routes; local UI tests use an injected server acknowledgement double.

**Intended review boundary:** `feat: verify signed apple billing data`

### Task 6: Persist and reconcile submitted transactions

**Files:** Create `AppleTransactionStore.php`, `AppleEntitlementProjector.php`, `AppleTransactionController.php`, requests/routes; create feature tests.

- [ ] Write failing tests for valid purchase, duplicate transaction, another user's token, mismatches, revocation and concurrent duplicate POSTs.
- [ ] Expose `POST /api/v1/native/storekit/transactions` with JSON `signed_transaction` only, maximum size 64 KB, authenticated native session and sensitive limiter.
- [ ] Verify before opening the entitlement transaction; then `DB::transaction` and unique-key/idempotent insert.
- [ ] Require verified `appAccountToken` to equal the authenticated user's server token.
- [ ] Project/update one Apple `premium_entitlements` row keyed by original transaction ID.
- [ ] Return durable acknowledgement and canonical entitlement:

```json
{
  "success": true,
  "data": {
    "acknowledged": true,
    "transaction_id": "verified-id",
    "entitlement": {
      "tier": "premium",
      "provider": "apple",
      "status": "active",
      "renews": true,
      "current_period_end": "2026-08-15T12:00:00Z"
    }
  }
}
```

- [ ] A verified duplicate returns 200 with the existing canonical result and no duplicated side effect.
- [ ] An unverified or mismatched transaction never changes user tier/grant.

**Intended review boundary:** `feat: reconcile submitted storekit transactions`

### Task 7: Implement notifications V2 and server reconciliation

**Files:** Create `AppleNotificationController.php`, `ProcessAppleNotification.php`, `AppleNotificationProcessor.php`, `AppleStoreServerClient.php`, `PythonAppleStoreServerClient.php`, `AppleReconciliationService.php`; Python API bridge modules, routes, config and tests.

- [ ] Execute Bridge Task 5 in `codex/plans/ios/2026-07-18-ios-04-apple-python-verifier-bridge.md` as the App Store Server client implementation.

- [ ] Expose `POST /api/webhooks/apple/v2` in `routes/api.php`; csjones configures the sandbox URL and fynla.org configures production at the same path on separate hosts.
- [ ] Validate JSON contains one `signedPayload` string <=256 KB; signature is the authentication boundary.
- [ ] Verify synchronously before returning success. Persist notification UUID/hash/status. Dispatch idempotent processing only after verification.
- [ ] Handle `SUBSCRIBED`, `DID_RENEW`, `DID_CHANGE_RENEWAL_STATUS`, `DID_FAIL_TO_RENEW`, `GRACE_PERIOD_EXPIRED`, `EXPIRED`, `REFUND`, `REFUND_REVERSED`, `REVOKE`, `DID_CHANGE_RENEWAL_PREF` and `TEST`, including documented subtypes.
- [ ] Verify nested signed transaction and renewal JWS independently.
- [ ] Return 200 for verified duplicates; return 4xx for malformed/mismatched payloads and 5xx for transient processing failure so Apple retries.
- [ ] Add authenticated `POST /api/v1/native/storekit/reconcile` and `GET /api/v1/native/storekit/status`.
- [ ] Reconciliation uses original transaction ID to call App Store Server API, iterates paginated history/status, verifies every returned JWS, then projects canonical entitlement.
- [ ] Add a scheduled reconciliation command for failed/pending Apple logs without erasing notification evidence.
- [ ] Test notification transitions, out-of-order delivery and duplicate concurrency. Newer verified transaction state must not be overwritten by an older event.

Run:

```bash
./vendor/bin/pest tests/Feature/Native/Billing tests/Feature/Webhooks/Apple tests/Unit/Services/Billing
```

Expected: PASS.

**Intended review boundary:** `feat: process app store server notifications`

### Task 8: Expose canonical native entitlement

**Files:** Create `NativeEntitlementController.php`, resource/DTO; modify routes; create tests.

- [ ] Add `GET /api/v1/native/entitlement` under authenticated native session.
- [ ] Return tier, provider, status, renews, verified period, capabilities and limits from `TierConfigurationStore`; never recalculate them in Swift.
- [ ] Include billing-management discriminator `apple`, `web` or `none`; no external purchase URL.
- [ ] Ensure Apple-specific `GET /storekit/status` contains management state but is not the capability authority.
- [ ] Add Free, Apple, Revolut and overlap response tests.

**Intended review boundary:** `feat: expose canonical native entitlement`

### Task 9: Add StoreKit client and local StoreKit configuration

**Files:** Create `ios-native/StoreKit/Fynla.storekit`, `Core/StoreKit/StoreKitClient.swift`, `SystemStoreKitClient.swift`, `StoreKitModels.swift`, tests.

- [ ] In Xcode create one auto-renewable subscription group with exactly monthly/annual product IDs, no offers and Family Sharing disabled.
- [ ] Configure local UK prices to match approved targets for deterministic UI testing; production UI still reads StoreKit products.
- [ ] Define:

```swift
enum PurchaseOutcome: Sendable, Equatable {
    case verified(SignedStoreTransaction)
    case pending
    case userCancelled
}

protocol StoreKitClient: Sendable {
    func products() async throws -> [StoreProduct]
    func purchase(_ productID: String, appAccountToken: UUID) async throws -> PurchaseOutcome
    func updates() -> AsyncStream<SignedStoreTransaction>
    func sync() async throws
}
```

- [ ] `SystemStoreKitClient` uses `Product.products(for:)`, rejects unexpected product IDs, passes `.appAccountToken`, checks `VerificationResult`, and returns JWS without finishing.
- [ ] Unverified transactions do not reach Laravel and render a safe purchase error.
- [ ] Start one `Transaction.updates` listener for app lifetime and send verified updates through the same server reconciliation path.
- [ ] Write unit tests with an injected StoreKit client; use StoreKit Test for product/pending/cancel/renew/expire/refund sequences.

**Intended review boundary:** `feat: add storekit two purchase client`

### Task 10: Build Premium purchase, restore and management UI

**Files:** Create `Features/Subscription/SubscriptionView.swift`, `SubscriptionModel.swift`, `SubscriptionManagementView.swift`; integrate into session/settings; tests.

- [ ] Load server entitlement and StoreKit products concurrently, then render Free, Apple Premium, web Premium and unavailable states.
- [ ] Show localised `displayPrice` and period from StoreKit; static copy must not contain a price.
- [ ] For Free, allow monthly or annual selection and purchase. Disable repeat tap once purchase begins.
- [ ] After local verification, POST JWS; only after `acknowledged=true` call `transaction.finish()`, refresh canonical entitlement and enable Premium.
- [ ] If server acknowledgement fails, retain the unfinished transaction for `Transaction.updates`/reconciliation and never claim Premium.
- [ ] Pending/Ask to Buy displays pending without retry spam.
- [ ] Restore calls `AppStore.sync()` through the client, then server reconcile, then reloads entitlement.
- [ ] Apple Premium uses `AppStore.showManageSubscriptions(in:)`; web Premium displays provider management information without an in-app external purchase call to action.
- [ ] Existing Premium suppresses product purchase controls.
- [ ] Add deterministic UI tests for all states and StoreKit Test cases.

**Intended review boundary:** `feat: add native premium subscription experience`

### Task 11: Three-surface and Apple sandbox gate

**Files:** Update parity ledger and operational docs; no unrelated fixes.

- [ ] Execute Bridge Task 6 in `codex/plans/ios/2026-07-18-ios-04-apple-python-verifier-bridge.md` before enabling Apple billing on development.
- [ ] Run all billing, tier, mobile, auth and native Swift tests.
- [ ] Use Apple sandbox signed transactions for monthly and annual purchases on a real device/TestFlight build.
- [ ] Verify renewal, renewal-off, billing retry, Grace Period, expiry, refund, refund reversal and revoke through signed notifications/test controls.
- [ ] Verify Apple Premium immediately appears on desktop and `/m` for the same user.
- [ ] Verify a Revolut Premium fixture appears native and hides StoreKit purchase actions.
- [ ] Verify duplicate/out-of-order notification replay changes no entitlement incorrectly.
- [ ] Verify account deletion UI is not yet shipped but backend status can state Apple billing is active for Package 7 warning.
- [ ] Run dependency/security review and confirm no signed payload appears in DB logs or app diagnostics.

Commands:

```bash
./vendor/bin/pest tests/Feature/Payment tests/Feature/Native/Billing tests/Feature/Webhooks/Apple tests/Unit/Services/Billing tests/Feature/Mobile tests/Feature/Contracts
xcodebuild -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging -destination 'platform=iOS Simulator,name=iPhone 11' test CODE_SIGNING_ALLOWED=NO
```

Expected: PASS; sandbox evidence and notification IDs/hashes (not payloads) are recorded in the parity ledger.

### Package 4 exit criteria

- [ ] Exactly two StoreKit products, one entitlement level, no trial/offers/Family Sharing.
- [ ] Server verifies all Apple signed data and rejects mismatch/tamper.
- [ ] Submitted transactions and notifications are idempotent.
- [ ] Monthly/annual purchase finishes only after server acknowledgement.
- [ ] Restore and Manage Subscription work.
- [ ] Apple and Revolut both resolve through one Premium authority.
- [ ] Apple Premium is visible on desktop and `/m`; Revolut Premium is visible native.
- [ ] Expiry/revocation returns to Free without data deletion.
- [ ] CSJ approves signed sandbox evidence before Package 5 exposes Premium-gated native features.
