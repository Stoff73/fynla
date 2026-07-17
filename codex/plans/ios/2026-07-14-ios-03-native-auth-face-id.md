# iOS Package 3: Native Authentication, Device Sessions and Face ID Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Use `superpowers:test-driven-development`, `security-and-hardening` for every task, `systematic-debugging` for every failure, `verification-before-completion` at the gate, and `verify-m` for shared-auth regression. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement complete in-app registration and login, a rotating server-revocable native session family, secure Keychain storage and opt-in Face ID without altering `/m` authentication.

**Architecture:** Existing public auth endpoints produce a short bootstrap Sanctum bearer after registration/email/MFA verification. An additive native exchange endpoint consumes that bearer and issues a 15-minute access token plus a one-time 30-day refresh credential inside an absolute 90-day session family. Refresh-token rows are hashed and retained long enough to detect replay. Swift stores only the refresh credential in a non-synchronising, biometric-protected Keychain item; access tokens remain in memory.

**Tech Stack:** Laravel Sanctum, PHP 8, MySQL 8, Pest; SwiftUI, Observation, LocalAuthentication, Security/Keychain, Swift Testing, XCTest UI tests.

## Global Constraints

- Preserve `POST /api/auth/register`, login, verification, multi-factor authentication, restore and password-reset behaviour for desktop and `/m`.
- Exchange accepts bearer-authenticated `PersonalAccessToken` only; cookie `TransientToken` fails closed.
- Access lifetime is 15 minutes, refresh lifetime is 30 days, absolute family lifetime is 90 days from complete authentication.
- Refresh rotates access and refresh credentials atomically. Reuse of a consumed refresh credential revokes the whole native session.
- Store only SHA-256 refresh-token hashes on the server.
- Access tokens live only in memory on iOS.
- Keychain uses `kSecAttrAccessibleWhenUnlockedThisDeviceOnly`, `.biometryCurrentSet`, no iCloud synchronisation.
- Face ID is opt-in after full authentication. It does not bypass password, email verification or multi-factor authentication.
- Background time of 60 seconds or more locks financial content. Every background transition covers the app-switcher snapshot immediately.
- Sign out revokes the native session; Lock retains it.
- Do not advertise Touch ID as a version 1 feature.

## File map

| Path | Responsibility |
|---|---|
| `database/migrations/2026_07_17_000000_create_native_device_sessions_table.php` | Device-session family state |
| `database/migrations/2026_07_17_000001_create_native_refresh_tokens_table.php` | One-time refresh-token replay evidence |
| `app/Models/NativeDeviceSession.php`, `NativeRefreshToken.php` | Native session persistence |
| `app/Services/Auth/NativeSessionService.php` | Exchange, rotation, replay revocation and logout |
| `app/Http/Requests/V1/Native/Auth/` | Exchange/refresh boundary validation |
| `app/Http/Controllers/Api/V1/Native/Auth/NativeSessionController.php` | Four native-session endpoints |
| `routes/api_v1.php` | Additive native auth routes |
| `ios-native/Fynla/Core/Authentication/` | Auth DTOs, AuthClient, credential/session coordination |
| `ios-native/Fynla/Core/Keychain/` | Protected refresh credential store |
| `ios-native/Fynla/Core/Biometrics/` | Face ID adapter and lock policy |
| `ios-native/Fynla/Features/Authentication/` | Registration, login, verification, MFA, reset, restore |

### Task 1: Persist native device-session families

**Files:** Create the two migrations, models, factories and `tests/Feature/Native/Auth/NativeSessionSchemaTest.php`.

Migration note: the original `2026_07_15_000001` and `000002` draft names collided with migrations merged to `dev` before Package 3 began. Package 3 therefore reserves fresh `2026_07_17_000000` and `000001` names without altering migration order already shipped on `dev`.

- [x] Write a failing schema test for both tables, indexes, unique hashes and cascade relationships.
- [x] Create `native_device_sessions` with UUID primary key and these columns: `user_id`, `platform`, `device_label`, `app_version`, `app_build`, nullable `current_access_token_id`, `authenticated_at`, `absolute_expires_at`, nullable `last_used_at`, nullable `revoked_at`, nullable `revoke_reason`, timestamps.
- [x] Index `user_id`, `absolute_expires_at`, `revoked_at`; foreign-key `user_id` cascade delete and `current_access_token_id` set null.
- [x] Create `native_refresh_tokens` with bigint ID, `native_device_session_id`, unique 64-character `token_hash`, `expires_at`, nullable `used_at`, nullable `revoked_at`, timestamps.
- [x] Index session plus expiry; cascade tokens when session is deleted.
- [x] Add casts and relationships. Do not make token hashes mass-serializable or JSON-visible.
- [x] Add model helpers `isActive()` and `canRotate()` that use an injected/current server clock only.

Run:

```bash
./vendor/bin/pest tests/Feature/Native/Auth/NativeSessionSchemaTest.php
```

Expected: PASS. Do not run destructive migration commands. If applying the migration to a persistent local database, follow with `php artisan db:seed`.

Recorded 2026-07-17: the schema red run failed on the absent tables and relationships, and the model red run failed on the absent persistence classes. The final focused suite passed 5 tests with 42 assertions. The broader native/parity regression passed 17 tests with 145 assertions after the isolated worktree generated its local Laravel package-discovery cache. PHP syntax, Pint and diff checks passed. No migration was applied to a persistent development database.

**Intended review boundary:** `feat: add native device session persistence`

### Task 2: Implement atomic exchange and rotation service

**Files:** Create `app/Services/Auth/NativeSessionService.php`, `app/Data/Auth/NativeSessionCredentials.php`; create `tests/Unit/Services/Auth/NativeSessionServiceTest.php`.

- [x] Write failing tests for exchange, 15-minute access expiry, 30-day refresh expiry, 90-day absolute expiry, rotation, expired refresh, revoked family and replay.
- [x] Generate refresh credentials with 32 cryptographically random bytes encoded base64url; hash the opaque string with SHA-256 before storage.
- [x] Implement this service boundary:

```php
final class NativeSessionService
{
    public function exchange(User $user, PersonalAccessToken $bootstrap, NativeDeviceContext $device): NativeSessionCredentials;
    public function rotate(string $plainRefreshToken, NativeDeviceContext $device): NativeSessionCredentials;
    public function revoke(NativeDeviceSession $session, string $reason): void;
}
```

- [x] In `exchange`, use `DB::transaction`, create the family, create a Sanctum token named `native-access:{session_uuid}` with ability `native` and `expires_at=now()+15m`, create the hashed refresh row with `expires_at=min(now()+30d, absolute expiry)`, delete matching `UserSession`, then delete bootstrap token.
- [x] In `rotate`, hash input, lock the refresh row and session `FOR UPDATE`, reject unknown values with a generic 401, and inspect `used_at`.
- [x] If a matching row was already used, revoke the session and all unused rows, delete the current access token, commit, and return `native_session_replayed` without issuing credentials.
- [x] On valid rotation, mark the current row used, revoke/delete the prior access token, issue a new access token and refresh row, update `last_used_at`, then return both plaintext credentials once.
- [x] At absolute expiry, revoke and return `native_full_login_required`.
- [x] Ensure concurrent rotations yield exactly one success and one family revocation, never two valid refresh credentials.
- [x] Ensure service logs contain session UUID/reason only, never plaintext or hash.

Run:

```bash
./vendor/bin/pest tests/Unit/Services/Auth/NativeSessionServiceTest.php
```

Expected: PASS including a database concurrency/replay case.

Recorded 2026-07-17: RED evidence reproduced missing service behavior, duplicate bootstrap exchange, unsafe replay/current-refresh lock ordering, authentication-time drift and access-token lifetime beyond the absolute family deadline. The final focused suite passed 15 tests with 111 assertions, including three independent-process MySQL contention cases. The broader native persistence, auth-service and existing `/m` regression suite passed 178 tests with 589 assertions. Pint, repository-wide PHP syntax and diff checks passed. Two independent post-fix reviews reported no blocking findings; the frozen-commit controller review approved both specification compliance and task quality with no Critical, Important or Minor findings.

**Intended review boundary:** `feat: add rotating native session service`

### Task 3: Expose the four native session endpoints

**Files:** Create `NativeSessionExchangeRequest.php`, `NativeSessionRefreshRequest.php`, `NativeSessionController.php`; modify `routes/api_v1.php`, `app/Providers/RouteServiceProvider.php`; create `tests/Feature/Native/Auth/NativeSessionApiTest.php`.

- [x] Write failing endpoint tests first.
- [x] Validate `device_label` as required trimmed string, maximum 80, with control characters rejected. Platform/version/build come from validated native headers, not request JSON.
- [x] Validate refresh input as required string maximum 512.
- [x] Add a per-IP/per-user named limiter `native-session` at 10 attempts/minute and apply it to exchange/refresh.
- [x] Register routes exactly:

```php
Route::prefix('/native/auth')->middleware('native.client')->group(function () {
    Route::post('/session/refresh', [NativeSessionController::class, 'refresh'])
        ->middleware('throttle:native-session');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/session/exchange', [NativeSessionController::class, 'exchange'])
            ->middleware('throttle:native-session');
        Route::get('/session', [NativeSessionController::class, 'show']);
        Route::delete('/session', [NativeSessionController::class, 'destroy']);
    });
});
```

- [x] Exchange requires a `PersonalAccessToken`, rejects `TransientToken`, and rejects a token already carrying only the `native` ability.
- [x] Show/destroy resolve the session by `current_access_token_id` and authenticated user; they cannot accept a session ID from the client.
- [x] Return credentials only in this shape:

```json
{
  "success": true,
  "data": {
    "access_token": "opaque",
    "access_expires_at": "2026-07-15T12:15:00Z",
    "refresh_token": "opaque",
    "refresh_expires_at": "2026-08-14T12:00:00Z",
    "absolute_expires_at": "2026-10-13T12:00:00Z",
    "session_id": "UUID"
  }
}
```

- [x] Return stable error codes `native_session_invalid`, `native_session_expired`, `native_session_replayed`, `native_full_login_required`.
- [x] Add session route write operations to `PreviewWriteInterceptor::EXCLUDED_ROUTES` only if preview authentication is an approved non-production path; assert production preview purchase/session behaviour remains disabled otherwise.

Run:

```bash
./vendor/bin/pest tests/Feature/Native/Auth/NativeSessionApiTest.php tests/Feature/Auth/LoginTest.php tests/Feature/Auth/RegistrationTest.php tests/Feature/Auth/MFATest.php tests/Feature/Mobile
```

Expected: all PASS; `/api/v1/auth/refresh-token` remains unchanged.

Recorded 2026-07-17: the initial endpoint RED run produced 21 expected failures and one passing legacy refresh assertion because the four routes, controller and limiter did not yet exist. Further RED runs reproduced control characters hidden by global input normalization and nested/form/query credentials echoed by preview interception. The completed endpoint suite passed 33 tests with 229 assertions. The exact login, registration, MFA and existing `/m` regression suite passed 157 tests with 685 assertions; the native session service/concurrency suite passed 15 tests with 111 assertions. Native preview writes remain intercepted, return no submitted data and create no credentials or session mutation. Pint, PHP syntax and diff checks passed. The final frozen two-commit review approved specification compliance and task quality with no Critical or Important findings; only non-blocking duplicate request-rule cleanup remains for future consolidation.

**Intended review boundary:** `feat: expose native session api`

### Task 4: Implement native auth DTOs and state coordinator

**Files:** Create `Core/Authentication/AuthModels.swift`, `AuthClient.swift`, `AuthenticationCoordinator.swift`; create fixture tests.

- [x] Add failing decode/state tests for all current response branches: immediate bearer, `requires_verification`, `requires_mfa`, `account_deleted_restorable`, lock status, validation, resend exhaustion and mandatory password change if returned.
- [x] Define explicit outcomes:

```swift
enum LoginOutcome: Sendable, Equatable {
    case verification(challengeToken: String, maskedEmail: String)
    case multiFactor(token: String, maskedEmail: String)
    case restorable(RestorationChallenge)
    case authenticated(bootstrapAccessToken: String)
}

protocol AuthClient: Sendable {
    func register(_ input: RegistrationInput) async throws -> RegistrationChallenge
    func verifyRegistration(_ input: RegistrationVerificationInput) async throws -> String
    func login(email: String, password: String) async throws -> LoginOutcome
    func verifyLogin(code: String, challengeToken: String) async throws -> String
    func verifyMFA(code: String, token: String) async throws -> String
    func useRecoveryCode(_ code: String, token: String) async throws -> String
    func exchange(bootstrapToken: String, deviceLabel: String) async throws -> NativeCredentials
}
```

- [x] Keep password and verification values in view-local transient state; never store in `AppSession`, UserDefaults or logs.
- [x] After any successful full auth, immediately exchange the bootstrap bearer. If exchange fails, discard it and show full-login retry; do not retain it as the long-lived native credential.
- [x] If Face ID is declined or unavailable, retain the current refresh credential only in memory for that process; app termination therefore requires normal full login.
- [x] Fetch `/api/auth/user` using the new access token before entering `authenticatedUnlocked`.
- [x] Run auth model/coordinator tests; expect PASS.

Recorded 2026-07-17: 13 initial sanitized fixtures and focused model/client/coordinator tests failed at compile time because the native authentication boundary did not exist. The implemented client preserves the exact current Laravel registration, verification, MFA, recovery, restoration, lock, validation, resend and native-exchange contracts; typed completion methods retain `must_change_password` without a second request or token encoding. Frozen review then reproduced three state/error/ownership gaps: password-change users unlocked financial routes, message-only 401/422 wording was lost, and a pre-sign-out request could clear a newer login. Separate RED→GREEN remediation added a route-blocking password-change state, lossless typed failures, monotonic attempt ownership and typed offline/transport errors. The final exact-source Swift 6 host suite passed 92 tests across 14 suites with one credential-gated staging test skipped because no bearer was supplied. Project verification passed, and the unsigned generic-iOS-simulator build compiled app, unit-test and UI-test targets under warnings-as-errors without launching a simulator. The final frozen two-commit review approved specification compliance and task quality with no Critical, Important or Minor findings. No remote endpoint, browser or production system was accessed.

**Intended review boundary:** `feat: add native authentication coordinator`

### Task 5: Build registration and verification screens

**Files:** Create `Features/Authentication/RegistrationView.swift`, `RegistrationModel.swift`, `VerificationCodeView.swift`; UI/unit tests.

- [x] Read `fynlaDesignGuide.md` before UI work.
- [x] Write model tests for required first name, surname, valid email, password confirmation and current password complexity rules.
- [x] Build fields for first name, optional middle name, surname, email, password and confirmation.
- [x] Include plain-text links to current Terms of Service and Privacy Policy using `AppEnvironment.webBaseURL`; do not embed the pages.
- [x] Submit exactly the current RegisterRequest keys. Do not send a tier, plan or trial field.
- [x] Verification accepts six digits, submits `type=registration` and `pending_id`, supports the existing resend flow and handles expired/consumed registrations.
- [x] Preserve entered non-secret registration fields after recoverable 422 errors; clear passwords on leaving the flow.
- [x] UI tests cover success, field errors, duplicate email, wrong code, expired code and resend limit.

Recorded 2026-07-17: registration and verification were implemented against the exact existing Laravel request keys, with ASCII password-rule parity, transient secret/code ownership, cancellable attempt ownership, 44-point legal targets derived from `webBaseURL`, Dynamic Type/VoiceOver coverage and deterministic offline UI branches. Stable additive `registration_unavailable` responses now cover missing, consumed, expired and resend-expired pending registrations so the native flow can offer a safe start-over action without parsing human wording; existing web consumers retain the same status and message. The final exact-source Swift suite passed 109 tests across 15 suites before the focused resend regression was added; the complete app/unit/UI warnings-as-errors build then compiled the new API-client mapping test for both simulator architectures without launching a simulator. The registration PHP suite passed 25 tests with 90 assertions, the focused resend RED reproduced the missing machine code and its GREEN run passed, and PHP syntax, project structure and diff checks passed. The final independent remediation review reported no Critical, Important or Minor findings and approved specification compliance, quality and readiness. No remote endpoint, browser, production system or persistent migration was accessed.

**Intended review boundary:** `feat: add native registration flow`

### Task 6: Build login, verification, MFA, reset and restoration screens

**Files:** Create `LoginView.swift`, `LoginModel.swift`, `MultiFactorView.swift`, `PasswordResetFlow.swift`, `RestoreAccountFlow.swift`; tests.

- [x] Implement email/password login and the email-verification branch using current `challenge_token` fields.
- [x] Implement six-digit time-based one-time password and recovery-code branches using `mfa_token`.
- [x] Implement all `/api/auth/password-reset/*` steps without bypassing multi-factor authentication.
- [x] Implement `/api/auth/restore/check` and `/api/auth/restore` using the server-issued restoration challenge; never reveal deleted-account state before correct credentials.
- [x] Show server lockout wording and countdown from `remaining_seconds`; prevent repeated submissions while locked.
- [x] Add accessibility identifiers for each field, submit, resend and alternate recovery action.
- [x] Add UI tests for every branch using deterministic AuthClient doubles.
- [x] Confirm no user-facing copy calls Face ID a password replacement.

Recorded 2026-07-17: the live signed-out shell now owns the complete login, verification, TOTP, recovery-code, password-reset and account-restoration branches, with deterministic UI-test scenarios and exact client fixtures for every server contract. Secrets and codes remain view-local; cancellation ownership prevents late responses from mutating a newer attempt; lockouts gate every continuation and render a stable countdown. Restoration state is exposed only after credential verification and uses a dedicated `AppSession.completeRestoration()` gate, preserving the existing fail-closed rule that generic authentication completion cannot unlock a pending restoration. Frozen review then reproduced three integration gaps: IP lockouts without `remaining_seconds` could retry, one-time MFA challenges were restored after a server 401, and the restoration UI double bypassed the real coordinator. Separate RED→GREEN remediation added indefinite lockout gating, returned consumed MFA/recovery challenges to full sign-in, and routed every login UI scenario through a deterministic `AuthCompletionClient` and the production coordinator; reset MFA controls now freeze during submission and the reset-token fixture matches the server's 64-character contract. The final exact-source Swift 6 host suite passed 135 tests across 18 suites with one credential-gated staging test skipped because no bearer was supplied. Project verification passed, and the unsigned generic iOS device build compiled the app, unit-test and UI-test targets for arm64 under warnings-as-errors without launching a simulator. No browser, remote endpoint or production system was accessed.

**Intended review boundary:** `feat: complete native login branches`

### Task 7: Implement Keychain credential protection

**Files:** Create `Core/Keychain/KeychainClient.swift`, `SystemKeychainClient.swift`, `KeychainError.swift`; tests with in-memory adapter.

- [x] Write tests for save/read/delete, corrupt data, missing item, cancelled interaction and safe fallback when the system reports a protected item as unavailable.
- [x] Define a narrow interface that accepts only `NativeRefreshCredential`, not arbitrary financial data.
- [x] Save under service `org.fynla.app.native-session`, account equal to the server session UUID, synchronizable false.
- [x] Build access control with `kSecAttrAccessibleWhenUnlockedThisDeviceOnly` and `.biometryCurrentSet`; require user presence when reading.
- [x] Do not store access token, password, email verification code, MFA code or API response.
- [x] Delete any older session item before saving a new account session.
- [x] Surface `errSecUserCanceled`, `errSecAuthFailed` and `errSecItemNotFound` distinctly.

Recorded 2026-07-17: the Keychain boundary accepts only `NativeRefreshCredential` and stores only the refresh token, expiry values and server session UUID under service `org.fynla.app.native-session`, account equal to that UUID and synchronizable false. Replacement saves delete all older service items first. The system adapter applies `kSecAttrAccessibleWhenUnlockedThisDeviceOnly` with `.biometryCurrentSet`, and its closed read-authentication type always supplies a validated user-presence reason through `LAContext`. The TDD RED run failed because the boundary and adapter did not yet exist; GREEN passed nine focused tests covering save/read/delete, status mappings, protected-item unavailability, malformed JSON and account/payload mismatch. Apple does not expose a reliable distinct status for `.biometryCurrentSet` invalidation, so the implementation does not invent one: `errSecItemNotFound` means the protected credential is unavailable and Task 8 requires full login. The final exact-source Swift 6 suite passed 144 tests across 19 suites, with the bearer-gated staging test skipped because no token was supplied and no endpoint contacted. Project verification passed, and an unsigned generic iOS-device build compiled the app, unit-test and UI-test targets for arm64 under warnings-as-errors without launching a simulator. Frozen re-review found no Critical, Important or Minor issues. No browser, remote endpoint or production system was accessed.

**Intended review boundary:** `feat: protect native refresh credential in keychain`

### Task 8: Implement Face ID opt-in and 60-second relock

**Files:** Create `Core/Biometrics/BiometricClient.swift`, `LocalAuthenticationClient.swift`, `PrivacyLockController.swift`, `Features/Authentication/FaceIDOptInView.swift`, `LockedView.swift`; tests.

- [x] Inject an LAContext adapter so unit/UI tests can cover available, unavailable, success, cancel, failure, lockout and changed enrolment without automating system Face ID UI.
- [x] Offer Face ID only after complete authentication and only when `biometryType == .faceID`.
- [x] Store the opt-in flag as non-sensitive app preference; the protected Keychain item remains the security boundary.
- [x] On cold launch, enter `authenticatedLocked`, request Face ID from an explicit unlock action, read refresh credential, rotate on the server, then fetch user and enter unlocked.
- [x] After a successful Keychain read, keep the active refresh credential in memory for background access-token rotations and replace the protected Keychain item after each successful server rotation, without another Face ID prompt while the app remains unlocked.
- [x] On `scenePhase` background, cover the root with an opaque privacy view immediately and record monotonic background time.
- [x] At foreground, lock if elapsed time is 60 seconds or more and clear access/refresh credentials from memory before requesting Face ID again. Under 60 seconds, remove only the privacy cover without cancelling active work or clearing the active in-memory session.
- [x] On cancellation, remain locked with `Sign in another way` available.
- [x] On biometric lockout or an unavailable protected Keychain item (including system-reported biometric-set invalidation), delete local credential and require full login; do not rely on a distinct invalidation status that Apple does not provide.
- [x] `Lock` does not call the server. `Sign out` calls DELETE native session best-effort, deletes local Keychain credential and clears all in-memory feature state.

Unit tests use an injected continuous clock:

```swift
@Test func locksAtSixtySeconds() async {
    let clock = TestClock()
    let controller = PrivacyLockController(clock: clock)
    controller.didEnterBackground()
    await clock.advance(by: .seconds(60))
    #expect(controller.shouldRequireUnlockOnForeground())
}
```

**Intended review boundary:** `feat: add opt in face id privacy lock`

Recorded 2026-07-17: implemented opt-in Face ID, a biometric-protected cold-unlock flow, in-memory access-token refresh and immediate app-switcher privacy with a monotonic 60-second relock. Native-session snapshots now own each one-time rotation by generation, preventing a delayed account-A refresh from replacing account-B credentials. Lock/relock fence an in-flight rotation without cancelling it and preserve any returned protected credential; opt-in and decline share a mutation gate; sign-out atomically captures and clears the latest session, clears local state immediately, and revokes both the captured token and any later stale rotation result. The locked UI exposes Face ID only when the stored preference and hardware state permit it, while production lockout and unavailable Keychain states converge on full login. Deterministic UI compositions cover opt-in, success, cancellation, failed recognition, lockout and Keychain invalidation without automating system Face ID. Final evidence: 175 Swift tests passed across 21 suites, with one bearer-gated staging integration skipped because no token was supplied; project structure verification passed; an unsigned generic physical-iOS arm64 build compiled the app, unit-test and UI-test targets with Swift warnings treated as errors; and the final frozen review reported no Critical, Important or Minor findings. Xcode was then opened on this exact Package 3 project and compiled the app successfully for an iPhone 16 Pro Max simulator with warnings treated as errors. After clearing a stalled CoreSimulator/XCTest runner state, the Xcode-built development app installed and launched on that device; a deterministic signed-out launch rendered the native email/password, password-recovery and create-account controls. XCTest's runner handshake remained blocked inside the installed iOS 26.3 simulator runtime, so this is recorded as launch/visual evidence rather than a simulator UI-test pass. No browser, remote endpoint or production system was used.

### Task 9: End-to-end security and regression gate

**Files:** Update tests and `docs/architecture/client-parity-ledger.md`; no opportunistic feature changes.

- [ ] Run backend auth/native-session tests and all existing auth regressions.
- [ ] Use `verify-m` to prove `/m` login, token rotation and logout still work.
- [ ] Run Swift unit and UI tests on iPhone 11 simulator.
- [ ] On a physical Face ID iPhone, verify opt-in, cold unlock, cancel, failed Face ID, 60-second background relock, Lock, Sign out and full-login fallback.
- [ ] On an iPhone 11-family device, repeat registration, email verification, dashboard shell launch and relock.
- [ ] Inspect Keychain accessibility with Xcode/device tools; prove no credential is synchronizable.
- [ ] Inspect Laravel logs and an exported diagnostic bundle; prove no secrets or financial bodies are present.
- [ ] Revoke a native session server-side, then prove the next refresh returns signed-out state and clears memory.

Commands:

```bash
./vendor/bin/pest tests/Feature/Auth tests/Feature/Native/Auth tests/Unit/Services/Auth tests/Feature/Mobile
xcodebuild -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging -destination 'platform=iOS Simulator,name=iPhone 11' test CODE_SIGNING_ALLOWED=NO
```

Expected: all PASS and physical-device evidence is recorded.

### Package 3 exit criteria

- [ ] New user registration and verification complete in-app and create Free.
- [ ] Email verification, multi-factor authentication, recovery, reset and restoration branches work.
- [ ] Native access/refresh lifetimes and 90-day absolute expiry are server-authoritative.
- [ ] Refresh replay revokes the family.
- [ ] Face ID is optional, Keychain-protected and recoverable through full login.
- [ ] App-switcher privacy and 60-second relock work on real hardware.
- [ ] Sign out revokes server state and clears local state.
- [ ] Desktop and `/m` authentication remain green.
- [ ] CSJ approves Package 3 before subscription purchase UI is enabled.
