# iOS Package 7: Settings, Privacy, Push, Universal Links and App Store Release Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Use `superpowers:test-driven-development`, `security-and-hardening` for settings/privacy/push/link work, `systematic-debugging` for every failure, `verification-before-completion` before release claims, `deploy-checklist` before any production deployment, and `verify-m` for shared backend regression. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the native platform responsibilities, pass real-device/TestFlight/App Review gates, and release the SwiftUI binary as the next version of `org.fynla.app` while `/m` remains live and independent.

**Architecture:** Settings composes existing account, consent, export and erasure APIs. Push uses Apple Push Notification service registration plus existing device endpoints. Universal links map an allowlist to typed native routes after authentication/unlock and otherwise fall back to web/`/m`. Release scripts archive the clean native target; the preserved Capacitor archive remains rollback evidence.

**Tech Stack:** SwiftUI, UserNotifications, UIKit app delegate bridge, LocalAuthentication/Keychain, StoreKit management, URLSession download/share, Associated Domains, Xcode archive/TestFlight; Laravel GDPR, device and notification APIs.

## Global Constraints

- Account deletion is easy to find and can be initiated/confirmed in-app.
- Apple subscription billing is not silently cancelled by account deletion; warn and offer Apple's management interface.
- Push lock-screen payloads contain no financial values, account identifiers, Fyn transcript or sensitive recommendation text.
- Permission prompts occur in context, not on first launch.
- Universal links never bypass sign-in/Face ID and never accept arbitrary routes.
- Admin, `/api`, webhook and unsupported links open the external browser or remain web-only; they do not enter a native error dead end.
- `PrivacyInfo.xcprivacy`, App Privacy answers and privacy policy must match actual code and provider behaviour.
- No analytics/advertising SDK is introduced in version 1.
- Production cutover replaces only the App Store binary. `/m`, `resources/mobile/` and its deployment stay intact.
- iPad remains version 2; do not enable universal-device support during App Store configuration.
- No production deployment, App Store submission or purchase activation occurs without explicit CSJ approval.

## File map

| Path | Responsibility |
|---|---|
| `ios-native/Fynla/Features/Settings/` | Account, Face ID, subscription and device controls |
| `ios-native/Fynla/Features/Privacy/` | Consent, export and account deletion |
| `ios-native/Fynla/Core/Push/` | Notification authorisation, token registration and routing |
| `ios-native/Fynla/Core/DeepLinks/` | Universal-link allowlist/parser |
| `ios-native/Fynla/Core/Migration/LegacyCapacitorCleanup.swift` | One-time stale hybrid credential/data cleanup |
| `ios-native/Fynla/Resources/PrivacyInfo.xcprivacy` | Actual data/required-reason declaration |
| `ios-native/Fynla/Fynla.entitlements` | Push and associated domains |
| `public/.well-known/apple-app-site-association` | Production universal-link association |
| `deploy/mobile-native/` | Reproducible native build/archive/check scripts |
| `docs/app-store/native-v1-release-checklist.md` | App Store/TestFlight evidence and operational ownership |

### Task 1: Build account and security settings

**Files:** Create `Features/Settings/SettingsView.swift`, `SettingsModel.swift`, `AccountSummaryView.swift`, `SecuritySettingsView.swift`; tests.

- [ ] Read `fynlaDesignGuide.md` before UI work.
- [ ] Show account name/email from authenticated user, canonical Free/Premium state, provider-appropriate subscription action, Face ID enabled state, notification settings, privacy/data, help/legal and sign out.
- [ ] Do not show raw native session UUID, app account token or device token.
- [ ] Face ID toggle-off deletes protected refresh credential only after user confirmation and requires full login next launch; do not revoke the server session unless the user signs out.
- [ ] Provide Lock and Sign out as distinct text actions.
- [ ] Subscription row opens Package 4 management/restore view. Apple and web provider wording remains distinct.
- [ ] Legal/support links use validated HTTPS URLs from `AppEnvironment` and open a system browser sheet, not an embedded Fynla web view.
- [ ] Add deterministic UI tests for Free, Apple Premium, web Premium, Face ID on/off, offline and signed-out transitions.

**Intended review boundary:** `feat: add native account and security settings`

### Task 2: Implement consent and data export

**Files:** Create `Features/Privacy/ConsentModels.swift`, `PrivacyClient.swift`, `PrivacySettingsView.swift`, `DataExportView.swift`, `TemporaryExportStore.swift`; tests.

- [ ] Decode current consent state/history from `/api/auth/gdpr/consents` and update through PUT only after explicit user action.
- [ ] Do not relabel required versus optional consent or enable Fyn after withdrawn artificial-intelligence consent without server confirmation.
- [ ] Request export through `POST /api/auth/gdpr/export`, poll the existing status endpoint with bounded backoff and cancellation, then download the authenticated file.
- [ ] Save an export only to an app-owned temporary file, present the native share sheet, and delete it after share dismissal or 24-hour cleanup.
- [ ] Protect the export screen behind unlocked session and cover it in app switcher.
- [ ] Do not log filename contents, downloaded bytes or signed download details.
- [ ] Test requested/processing/ready/expired/rate-limited/download-failed states and temporary-file deletion.

**Intended review boundary:** `feat: add native consent and data export`

### Task 3: Implement complete in-app account deletion

**Files:** Create `Features/Privacy/AccountDeletionModels.swift`, `AccountDeletionModel.swift`, `AccountDeletionView.swift`; update AppSession cleanup; tests.

- [ ] Use the current three-step endpoints: initiate, verify code, execute; implement resend and cancel-scheduled operations.
- [ ] Before initiation load canonical entitlement. If provider is Apple and active/renewing, explain that deleting Fynla does not cancel App Store billing and offer Manage Subscription.
- [ ] Display server retention/scheduled deletion facts accurately; do not promise immediate erasure where the regulated retention service schedules it.
- [ ] Require explicit destructive confirmation and six-digit verification. Do not ask the user to contact support or leave the app to initiate deletion.
- [ ] On execute acknowledgement, revoke native session best-effort, delete Keychain credentials, push registration, in-memory data and temporary exports, then enter signed-out/deleted state.
- [ ] Ensure Apple notification processing can continue after local deletion and cannot recreate a deleted user entitlement; retain only legally required provider audit evidence.
- [ ] Add backend tests for Apple billing warning state and erasure of Package 3/4 user-linked rows.
- [ ] Add UI tests for Free deletion, Apple warning, web Premium, wrong/expired/resend code, scheduled state, cancellation and successful cleanup.

Run:

```bash
./vendor/bin/pest tests/Feature/Auth/GDPRApiTest.php tests/Feature/Account tests/Unit/Services/Account tests/Feature/Native/Billing
```

Expected: PASS.

**Intended review boundary:** `feat: add complete native account deletion`

### Task 4: Add push registration and privacy-safe notification routing

**Files:** Create `Core/Push/PushClient.swift`, `SystemPushClient.swift`, `PushRegistrationCoordinator.swift`, `NotificationRouter.swift`, `FynlaAppDelegate.swift`; modify app entry/entitlements; create tests; reuse Laravel device/preference endpoints.

- [ ] Add Push Notifications entitlement and `aps-environment` through signing configuration, not a hardcoded production value.
- [ ] Ask for notification permission only from Settings or an approved in-context prompt after explaining value.
- [ ] Bridge `didRegisterForRemoteNotificationsWithDeviceToken` and failures through a sendable coordinator.
- [ ] Encode the token as lowercase hex and register at `POST /api/v1/mobile/devices` using native session UUID as `device_id`, platform `ios`, generic device name `iPhone`, app version and OS version.
- [ ] On token rotation, update the same device row. On sign out/deletion, DELETE the device ID before clearing access where possible.
- [ ] Manage preference through existing `/api/v1/mobile/notifications/preferences` routes.
- [ ] Server notification payloads contain a generic title/body and an allowlisted route key only; financial detail loads after unlock.
- [ ] A tapped notification stores a pending typed route, completes sign-in/Face ID, then routes. Unknown/admin routes are ignored/opened externally.
- [ ] Test denied/provisional/authorised, token update, logout removal, cold/warm notification, locked app and malformed payload.
- [ ] Verify delivery on physical iPhone 11-family and current Face ID iPhone; simulator is not release evidence.

**Intended review boundary:** `feat: add privacy safe native push notifications`

### Task 5: Add universal links without disturbing phone web routing

**Files:** Create `Core/DeepLinks/DeepLinkParser.swift`, `PendingDeepLinkCoordinator.swift`; modify entitlements and app root; create `public/.well-known/apple-app-site-association`; tests.

- [ ] Add `applinks:fynla.org` to production and `applinks:csjones.co` to staging entitlements.
- [ ] Allow only HTTPS hosts matching the active environment.
- [ ] Map exact supported path families to typed routes: dashboard, achievements, income, expenditure, net worth/category, protection/policy, savings/account, investment/account, retirement/pension, estate, goals, tax strategy, holistic plan and settings.
- [ ] Explicitly reject `/admin/*`, `/api/*`, `/.well-known/*`, payment/webhook paths and unknown IDs/types.
- [ ] Store a pending supported link while signed out/locked and route only after authentication and Face ID.
- [ ] Generate the production AASA application identifier from the signed archive's `application-identifier` entitlement during the release step; do not commit a fake Team ID. The checked-in production file must contain the real identifier before deployment.
- [ ] AASA components include only supported public/native route patterns and exclusions; staging association scopes to `/fynla/*`.
- [ ] Serve without redirects, over HTTPS, with JSON content type from both environment hosts.
- [ ] With app absent, verify the same URL loads web and phone detection reaches `/m`. With app installed, verify cold/warm native routing. Unsupported/admin URLs remain browser-based.

Commands after deployment approval:

```bash
curl -i https://fynla.org/.well-known/apple-app-site-association
curl -i https://csjones.co/.well-known/apple-app-site-association
```

Expected: 200, no redirect, valid JSON and correct real application identifiers.

**Intended review boundary:** `feat: add safe native universal links`

### Task 6: Remove stale Capacitor local credentials on the native upgrade

**Files:** Create `Core/Migration/LegacyCapacitorCleanup.swift`, tests; modify composition root and privacy manifest inventory.

- [ ] On first native-version launch only, clear default `WKWebsiteDataStore` cookies/local storage/cache so legacy `/m` bearer data cannot remain in the upgraded app container.
- [ ] Delete legacy Capgo biometric `kSecClassInternetPassword` entries only for the known Fynla server attributes `fynla.org` and `csjones.co`; the new credential uses a distinct generic-password service.
- [ ] Never import a Capacitor bearer into the native session.
- [ ] Do not delete unrelated Keychain items or user documents.
- [ ] Record only a boolean completion marker in UserDefaults after every cleanup operation has succeeded or safely returned not-found.
- [ ] Test idempotency, partial failure/retry and protection of the new `org.fynla.app.native-session` item.
- [ ] Perform a real upgrade install over the last accepted Capacitor build: old login state must not unlock native; complete login and Face ID must work afterwards.

**Intended review boundary:** `fix: clean legacy capacitor credentials on upgrade`

### Task 7: Enforce native version policy and purchase kill switch

**Files:** Create `config/native.php`, `app/Http/Middleware/EnforceNativeVersion.php`; update native route group; create tests; add native unsupported-version view.

- [ ] Configure minimum semantic version/build separately for staging/production and a server-controlled `storekit_purchase_enabled` flag.
- [ ] Compare validated native headers only on `/api/v1/native`; web and `/m` are unaffected.
- [ ] Unsupported native builds receive HTTP 426 with code `native_update_required`, minimum version and public App Store URL.
- [ ] Purchase-disabled state hides/disables new purchase initiation but continues entitlement reads, transaction acknowledgement, restore/reconcile and Apple notifications.
- [ ] Swift presents a blocking update state before financial data when 426 occurs.
- [ ] Add middleware/Swift tests and rollback exercise.

**Intended review boundary:** `feat: add native version and purchase controls`

### Task 8: Create and validate the privacy manifest and disclosures

**Files:** Create `ios-native/Fynla/Resources/PrivacyInfo.xcprivacy`, `docs/app-store/native-v1-privacy-inventory.md`; update privacy policy content only through its normal project path after CSJ/legal review.

- [ ] Inventory every native/server data category: account/contact data, financial data, identifiers, purchases, diagnostics, user content/Fyn messages, consent/export/deletion, push token and provider transaction metadata.
- [ ] Mark linkage/use accurately and declare no tracking if code contains no tracking.
- [ ] Inventory required-reason APIs from the final archive. Declare UserDefaults reason `CA92.1` for app-only preferences; add another category/reason only when final source/archive proves it is used and the Apple-approved reason matches actual use.
- [ ] Include `PrivacyInfo.xcprivacy` at the app bundle root and validate with Xcode's privacy report.
- [ ] Reconcile manifest, App Store Connect App Privacy answers and public privacy policy line by line.
- [ ] Ensure privacy policy describes retention/deletion and Apple/Revolut billing-provider processing.
- [ ] Confirm no third-party native SDK privacy manifest is required because version 1 has no third-party native runtime dependency.

Base manifest required-reason entry:

```xml
<key>NSPrivacyAccessedAPITypes</key>
<array>
  <dict>
    <key>NSPrivacyAccessedAPIType</key>
    <string>NSPrivacyAccessedAPICategoryUserDefaults</string>
    <key>NSPrivacyAccessedAPITypeReasons</key>
    <array><string>CA92.1</string></array>
  </dict>
</array>
```

- [ ] Do not copy this entry blindly if final code no longer uses UserDefaults; the source/archive inventory controls.

**Intended review boundary:** `docs: align native privacy disclosures`

### Task 9: Add reproducible native archive and release checks

**Files:** Create `deploy/mobile-native/build.sh`, `archive.sh`, `verify-archive.sh`, `README.md`; create `docs/app-store/native-v1-release-checklist.md`.

- [ ] Build scripts select `Fynla-Production`, Release and generic iOS destination; they do not run Capacitor/Vite.
- [ ] Archive script accepts version/build through approved Xcode settings, writes outside the repo and never embeds credentials.
- [ ] Verification script fails unless bundle ID is `org.fynla.app`, deployment target is 17, device family is iPhone only, privacy manifest exists, Face ID purpose string exists, associated domains/push entitlements are signed and no `Capacitor`, `cordova`, `WKWebView` class reference or web bundle is present.
- [ ] Verify no staging host, debug menu, test fixture, `.storekit` file or test certificate is in production app bundle.
- [ ] Keep last accepted Capacitor source commit/archive identifier in the release checklist; do not delete its source.

Commands:

```bash
./deploy/mobile-native/build.sh
./deploy/mobile-native/archive.sh
./deploy/mobile-native/verify-archive.sh /tmp/Fynla.xcarchive
```

Expected: all exit 0 and archive verification prints the production identity only.

**Intended review boundary:** `build: add native app store archive pipeline`

### Task 10: Complete Store/App Store Connect operational configuration

**Files:** Update release checklist and secure App Store Connect fields; do not store credentials in repo.

- [ ] Confirm Apple Developer agreements, tax and banking are active and the approved Revolut bank account is configured as Apple's payout account.
- [ ] Preserve the existing App Store app record/bundle identity `org.fynla.app` so native ships as an update.
- [ ] Configure one subscription group and exactly the approved monthly/annual products; prices, no trial, no Family Sharing, review screenshots and localisation all complete.
- [ ] Configure production and sandbox Notification V2 URLs and successfully send TEST notifications.
- [ ] Provide final app name/subtitle/description/keywords/category/support/privacy/marketing URLs with no placeholder copy.
- [ ] Capture current required iPhone screenshot sizes from the accepted App Store Connect UI and produce truthful native screenshots from the exact release candidate.
- [ ] Complete export-compliance and App Privacy answers from the final inventory.
- [ ] Put review credentials and current verification-code instructions only in secure App Review fields, not source/docs. Provide Premium test instructions and explain one Fyn surface.
- [ ] Ensure support can trace transaction/original transaction IDs without card data or full signed payload.

### Task 11: Run complete release-candidate verification

**Files:** Tests, release checklist and parity ledger; fix failures at their root under the owning package.

- [ ] Run full Pest, frontend, desktop build, mobile build and native tests.
- [ ] Verify desktop and `/m` remain green on csjones after shared backend deployment using the official build/deploy and `verify-m` workflow.
- [ ] Install the release candidate on physical iPhone 11-family and current Face ID iPhone.
- [ ] Execute every journey: register, verify, login, MFA, Face ID, Free dashboard, Fyn onboarding/advice/write, every module/detail, monthly/annual sandbox purchase, restore/manage, push, universal links, export and deletion.
- [ ] Test offline/poor network, background 60 seconds, force quit, memory pressure, update from Capacitor, revoked session and mandatory update.
- [ ] Verify VoiceOver, XXL Dynamic Type, Reduce Motion, app-switcher privacy and portrait layouts.
- [ ] Archive/upload internal TestFlight only after local gate. Repeat real sandbox purchases and notification handling on TestFlight.
- [ ] Obtain CSJ approval of the exact build number before external TestFlight/App Review.

Commands:

```bash
./vendor/bin/pest
npm run test
npm run build
npm run build:mobile
xcodebuild -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging -destination 'platform=iOS Simulator,name=iPhone 11' test CODE_SIGNING_ALLOWED=NO
./deploy/mobile-native/archive.sh
./deploy/mobile-native/verify-archive.sh /tmp/Fynla.xcarchive
```

Expected: all exit 0 and every required parity-ledger cell is green with evidence.

### Task 12: Submit, monitor and retain rollback

**Files:** Release checklist and operational monitoring only.

- [ ] Submit the CSJ-approved build and both subscription products together as required.
- [ ] Keep native purchase kill switch initially available; do not disable existing entitlement reads/notifications.
- [ ] Monitor native session failures/replay, Apple verification/notification failures, entitlement changes, crash/launch failures, Fyn errors and shared desktop/`/m` API errors.
- [ ] Verify the live App Store update on iPhone 11 and current iPhone after approval.
- [ ] Keep `/m` live and independently deployable throughout review and after launch.
- [ ] Trigger rollback/purchase disable for incorrect Premium changes, unverified Apple events, unrecoverable auth lockout, sensitive local/log exposure, false Fyn write success, shared web/`/m` regression or deletion/management crash.
- [ ] Do not roll back/delete Apple transaction or entitlement audit history when rolling back a client.
- [ ] Conduct the post-release review before retiring only the old signed Capacitor rollback window; never retire `/m`.

### Package 7 exit criteria

- [ ] Settings, consent, export and in-app account deletion pass.
- [ ] Apple billing warning and management are present in deletion flow.
- [ ] Push is privacy-safe and verified on real devices.
- [ ] Universal links work installed/uninstalled without breaking `/m` fallback.
- [ ] Legacy Capacitor credentials are not trusted and are cleaned safely.
- [ ] Privacy manifest, App Privacy and policy agree with final code.
- [ ] Production archive is iPhone-only, iOS 17+, SwiftUI-native and free of Capacitor/web UI.
- [ ] Full iPhone 11 and current Face ID device matrices pass.
- [ ] Internal TestFlight and exact build receive CSJ approval.
- [ ] `/m` remains green, intact and permanent after App Store cutover.

## Current Apple primary references

- [App Review Guidelines](https://developer.apple.com/app-store/review/guidelines/)
- [Offering account deletion](https://developer.apple.com/support/offering-account-deletion-in-your-app)
- [Privacy manifest files](https://developer.apple.com/documentation/bundleresources/privacy-manifest-files)
- [Required-reason APIs](https://developer.apple.com/documentation/bundleresources/describing-use-of-required-reason-api)
- [Restoring purchases](https://developer.apple.com/documentation/storekit/restoring-purchased-products)
