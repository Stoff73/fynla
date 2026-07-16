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
| `database/migrations/2026_07_15_000001_create_native_device_sessions_table.php` | Device-session family state |
| `database/migrations/2026_07_15_000002_create_native_refresh_tokens_table.php` | One-time refresh-token replay evidence |
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

- [ ] Write a failing schema test for both tables, indexes, unique hashes and cascade relationships.
- [ ] Create `native_device_sessions` with UUID primary key and these columns: `user_id`, `platform`, `device_label`, `app_version`, `app_build`, nullable `current_access_token_id`, `authenticated_at`, `absolute_expires_at`, nullable `last_used_at`, nullable `revoked_at`, nullable `revoke_reason`, timestamps.
- [ ] Index `user_id`, `absolute_expires_at`, `revoked_at`; foreign-key `user_id` cascade delete and `current_access_token_id` set null.
- [ ] Create `native_refresh_tokens` with bigint ID, `native_device_session_id`, unique 64-character `token_hash`, `expires_at`, nullable `used_at`, nullable `revoked_at`, timestamps.
- [ ] Index session plus expiry; cascade tokens when session is deleted.
- [ ] Add casts and relationships. Do not make token hashes mass-serializable or JSON-visible.
- [ ] Add model helpers `isActive()` and `canRotate()` that use an injected/current server clock only.

Run:

```bash
./vendor/bin/pest tests/Feature/Native/Auth/NativeSessionSchemaTest.php
```

Expected: PASS. Do not run destructive migration commands. If applying the migration to a persistent local database, follow with `php artisan db:seed`.

**Intended review boundary:** `feat: add native device session persistence`

### Task 2: Implement atomic exchange and rotation service

**Files:** Create `app/Services/Auth/NativeSessionService.php`, `app/Data/Auth/NativeSessionCredentials.php`; create `tests/Unit/Services/Auth/NativeSessionServiceTest.php`.

- [ ] Write failing tests for exchange, 15-minute access expiry, 30-day refresh expiry, 90-day absolute expiry, rotation, expired refresh, revoked family and replay.
- [ ] Generate refresh credentials with 32 cryptographically random bytes encoded base64url; hash the opaque string with SHA-256 before storage.
- [ ] Implement this service boundary:

```php
final class NativeSessionService
{
    public function exchange(User $user, PersonalAccessToken $bootstrap, NativeDeviceContext $device): NativeSessionCredentials;
    public function rotate(string $plainRefreshToken, NativeDeviceContext $device): NativeSessionCredentials;
    public function revoke(NativeDeviceSession $session, string $reason): void;
}
```

- [ ] In `exchange`, use `DB::transaction`, create the family, create a Sanctum token named `native-access:{session_uuid}` with ability `native` and `expires_at=now()+15m`, create the hashed refresh row with `expires_at=min(now()+30d, absolute expiry)`, delete matching `UserSession`, then delete bootstrap token.
- [ ] In `rotate`, hash input, lock the refresh row and session `FOR UPDATE`, reject unknown values with a generic 401, and inspect `used_at`.
- [ ] If a matching row was already used, revoke the session and all unused rows, delete the current access token, commit, and return `native_session_replayed` without issuing credentials.
- [ ] On valid rotation, mark the current row used, revoke/delete the prior access token, issue a new access token and refresh row, update `last_used_at`, then return both plaintext credentials once.
- [ ] At absolute expiry, revoke and return `native_full_login_required`.
- [ ] Ensure concurrent rotations yield exactly one success and one family revocation, never two valid refresh credentials.
- [ ] Ensure service logs contain session UUID/reason only, never plaintext or hash.

Run:

```bash
./vendor/bin/pest tests/Unit/Services/Auth/NativeSessionServiceTest.php
```

Expected: PASS including a database concurrency/replay case.

**Intended review boundary:** `feat: add rotating native session service`

### Task 3: Expose the four native session endpoints

**Files:** Create `NativeSessionExchangeRequest.php`, `NativeSessionRefreshRequest.php`, `NativeSessionController.php`; modify `routes/api_v1.php`, `app/Providers/RouteServiceProvider.php`; create `tests/Feature/Native/Auth/NativeSessionApiTest.php`.

- [ ] Write failing endpoint tests first.
- [ ] Validate `device_label` as required trimmed string, maximum 80, with control characters rejected. Platform/version/build come from validated native headers, not request JSON.
- [ ] Validate refresh input as required string maximum 512.
- [ ] Add a per-IP/per-user named limiter `native-session` at 10 attempts/minute and apply it to exchange/refresh.
- [ ] Register routes exactly:

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

- [ ] Exchange requires a `PersonalAccessToken`, rejects `TransientToken`, and rejects a token already carrying only the `native` ability.
- [ ] Show/destroy resolve the session by `current_access_token_id` and authenticated user; they cannot accept a session ID from the client.
- [ ] Return credentials only in this shape:

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

- [ ] Return stable error codes `native_session_invalid`, `native_session_expired`, `native_session_replayed`, `native_full_login_required`.
- [ ] Add session route write operations to `PreviewWriteInterceptor::EXCLUDED_ROUTES` only if preview authentication is an approved non-production path; assert production preview purchase/session behaviour remains disabled otherwise.

Run:

```bash
./vendor/bin/pest tests/Feature/Native/Auth/NativeSessionApiTest.php tests/Feature/Auth/LoginTest.php tests/Feature/Auth/RegistrationTest.php tests/Feature/Auth/MFATest.php tests/Feature/Mobile
```

Expected: all PASS; `/api/v1/auth/refresh-token` remains unchanged.

**Intended review boundary:** `feat: expose native session api`

### Task 4: Implement native auth DTOs and state coordinator

**Files:** Create `Core/Authentication/AuthModels.swift`, `AuthClient.swift`, `AuthenticationCoordinator.swift`; create fixture tests.

- [ ] Add failing decode/state tests for all current response branches: immediate bearer, `requires_verification`, `requires_mfa`, `account_deleted_restorable`, lock status, validation, resend exhaustion and mandatory password change if returned.
- [ ] Define explicit outcomes:

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

- [ ] Keep password and verification values in view-local transient state; never store in `AppSession`, UserDefaults or logs.
- [ ] After any successful full auth, immediately exchange the bootstrap bearer. If exchange fails, discard it and show full-login retry; do not retain it as the long-lived native credential.
- [ ] If Face ID is declined or unavailable, retain the current refresh credential only in memory for that process; app termination therefore requires normal full login.
- [ ] Fetch `/api/auth/user` using the new access token before entering `authenticatedUnlocked`.
- [ ] Run auth model/coordinator tests; expect PASS.

**Intended review boundary:** `feat: add native authentication coordinator`

### Task 5: Build registration and verification screens

**Files:** Create `Features/Authentication/RegistrationView.swift`, `RegistrationModel.swift`, `VerificationCodeView.swift`; UI/unit tests.

- [ ] Read `fynlaDesignGuide.md` before UI work.
- [ ] Write model tests for required first name, surname, valid email, password confirmation and current password complexity rules.
- [ ] Build fields for first name, optional middle name, surname, email, password and confirmation.
- [ ] Include plain-text links to current Terms of Service and Privacy Policy using `AppEnvironment.webBaseURL`; do not embed the pages.
- [ ] Submit exactly the current RegisterRequest keys. Do not send a tier, plan or trial field.
- [ ] Verification accepts six digits, submits `type=registration` and `pending_id`, supports the existing resend flow and handles expired/consumed registrations.
- [ ] Preserve entered non-secret registration fields after recoverable 422 errors; clear passwords on leaving the flow.
- [ ] UI tests cover success, field errors, duplicate email, wrong code, expired code and resend limit.

**Intended review boundary:** `feat: add native registration flow`

### Task 6: Build login, verification, MFA, reset and restoration screens

**Files:** Create `LoginView.swift`, `LoginModel.swift`, `MultiFactorView.swift`, `PasswordResetFlow.swift`, `RestoreAccountFlow.swift`; tests.

- [ ] Implement email/password login and the email-verification branch using current `challenge_token` fields.
- [ ] Implement six-digit time-based one-time password and recovery-code branches using `mfa_token`.
- [ ] Implement all `/api/auth/password-reset/*` steps without bypassing multi-factor authentication.
- [ ] Implement `/api/auth/restore/check` and `/api/auth/restore` using the server-issued restoration challenge; never reveal deleted-account state before correct credentials.
- [ ] Show server lockout wording and countdown from `remaining_seconds`; prevent repeated submissions while locked.
- [ ] Add accessibility identifiers for each field, submit, resend and alternate recovery action.
- [ ] Add UI tests for every branch using deterministic AuthClient doubles.
- [ ] Confirm no user-facing copy calls Face ID a password replacement.

**Intended review boundary:** `feat: complete native login branches`

### Task 7: Implement Keychain credential protection

**Files:** Create `Core/Keychain/KeychainClient.swift`, `SystemKeychainClient.swift`, `KeychainError.swift`; tests with in-memory adapter.

- [ ] Write tests for save/read/delete, missing item, cancelled interaction and biometric-set invalidation mapping.
- [ ] Define a narrow interface that accepts only `NativeRefreshCredential`, not arbitrary financial data.
- [ ] Save under service `org.fynla.app.native-session`, account equal to the server session UUID, synchronizable false.
- [ ] Build access control with `kSecAttrAccessibleWhenUnlockedThisDeviceOnly` and `.biometryCurrentSet`; require user presence when reading.
- [ ] Do not store access token, password, email verification code, MFA code or API response.
- [ ] Delete any older session item before saving a new account session.
- [ ] Surface `errSecUserCanceled`, `errSecAuthFailed` and `errSecItemNotFound` distinctly.

**Intended review boundary:** `feat: protect native refresh credential in keychain`

### Task 8: Implement Face ID opt-in and 60-second relock

**Files:** Create `Core/Biometrics/BiometricClient.swift`, `LocalAuthenticationClient.swift`, `PrivacyLockController.swift`, `Features/Authentication/FaceIDOptInView.swift`, `LockedView.swift`; tests.

- [ ] Inject an LAContext adapter so unit/UI tests can cover available, unavailable, success, cancel, failure, lockout and changed enrolment without automating system Face ID UI.
- [ ] Offer Face ID only after complete authentication and only when `biometryType == .faceID`.
- [ ] Store the opt-in flag as non-sensitive app preference; the protected Keychain item remains the security boundary.
- [ ] On cold launch, enter `authenticatedLocked`, request Face ID from an explicit unlock action, read refresh credential, rotate on the server, then fetch user and enter unlocked.
- [ ] After a successful Keychain read, keep the active refresh credential in memory for background access-token rotations and replace the protected Keychain item after each successful server rotation, without another Face ID prompt while the app remains unlocked.
- [ ] On `scenePhase` background, cover the root with an opaque privacy view immediately and record monotonic background time.
- [ ] At foreground, lock if elapsed time is 60 seconds or more and clear access/refresh credentials from memory before requesting Face ID again. Under 60 seconds, remove only the privacy cover without cancelling active work or clearing the active in-memory session.
- [ ] On cancellation, remain locked with `Sign in another way` available.
- [ ] On biometric lockout or Keychain biometric-set invalidation, delete local credential and require full login.
- [ ] `Lock` does not call the server. `Sign out` calls DELETE native session best-effort, deletes local Keychain credential and clears all in-memory feature state.

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
