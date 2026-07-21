# Client Parity Ledger

This ledger is the release evidence record for desktop web, the `/m` mobile-web pathway and the native SwiftUI client. It records development and csjones staging evidence only. Production verification is deferred to the later batch production promotion and is not part of the current native implementation programme.

## Status vocabulary

- `required`: release-blocking evidence is still required on this surface.
- `not-landed`: the planned native slice has not landed yet.
- `not-applicable`: the capability does not apply to this surface and its platform boundary is documented.
- `green`: automated and manual evidence are both recorded in this row.

A row must not use `green` while either evidence cell is blank. User journeys require browser evidence for desktop and `/m`, and simulator or physical-device evidence for native where the package plan requires it.

## Capability matrix

| Capability | Desktop | `/m` | Native | Native package | Backend owner | Automated evidence | Manual evidence | Last verified | Approving person |
|---|---|---|---|---|---|---|---|---|---|
| Register and verify | green | green | required | Package 3 | AuthController | ClientCompatibilityContractTest registration/verification contract; Package 3 exact-source Swift and backend auth gates | csjones verified Free Chrome dashboard and `/m` journey; native signed-out shell launched on iPhone 16 Pro Max; physical iPhone 11 registration/verification remains required | 2026-07-17 | Pending CSJ |
| Login, verification and multi-factor authentication | required | required | required | Package 3 | AuthController/MFAController | Package 3 backend auth gate 345 tests/1,261 assertions; Swift host gate 176 tests/21 suites; clean Xcode 26.5 iPhone 11 gate 183 unit tests/23 suites and 28 UI tests/0 failures | Native signed-out shell launched on iPhone 16 Pro Max; current Chrome `/m` acceptance and physical-device branches remain required | 2026-07-17 | Pending CSJ |
| Free/Premium entitlement | green | green | not-landed | Package 4 | TierResolver/entitlement resolver | ClientCompatibilityContractTest Free/Premium fixtures; freemium cap and presentation suites | Verified Free account returned no Subscription row/trial; local Premium fixture acceptance recorded in freemium evidence | 2026-07-16 | Pending CSJ |
| Dashboard and gamification | green | green | not-landed | Package 5 | MobileDashboardAggregator | ClientCompatibilityContractTest dashboard contract; frontend additive-field test | Actual Chrome desktop dashboard and authenticated `/m` dashboard at 390×844 | 2026-07-16 | Pending CSJ |
| Fyn onboarding/advice/write handoff | required | green | not-landed | Package 5 | AiChatController | ClientCompatibilityContractTest Fyn envelopes; ConcurrentTurnQueueGateTest | Same verified Free account opened Fyn onboarding in `/m` with no failed Fynla response | 2026-07-16 | Pending CSJ |
| Income/expenditure/net worth | required | required | not-landed | Package 6 Wave A | existing module APIs |  |  |  |  |
| Savings/investment | required | required | not-landed | Package 6 Wave B | existing module APIs |  |  |  |  |
| Retirement/protection | required | required | not-landed | Package 6 Wave C | existing module APIs |  |  |  |  |
| Estate/goals | required | required | not-landed | Package 6 Wave D | existing module APIs |  |  |  |  |
| Tax Strategy/Holistic Plan | required | required | not-landed | Package 6 Wave E | existing plan APIs |  |  |  |  |
| Face ID | not-applicable | not-applicable | required | Package 3 | native session service | PrivacyLockController, Keychain and biometric suites; revoked-family refresh proves signed-out state and complete local credential clearing | Xcode-built native shell launched on iPhone 16 Pro Max; physical Face ID, 60-second relock and Keychain-item inspection remain required | 2026-07-17 | Pending CSJ |
| StoreKit purchase | not-applicable | not-applicable | not-landed | Package 4 | Apple billing adapter |  |  |  |  |
| Account deletion outcome | required | required | not-landed | Package 7 | GDPRController |  |  |  |  |

## Current Package 3 authentication evidence

- Native boundary: in-app registration, login, email verification, multi-factor authentication, recovery codes, password reset and account restoration now converge through one server-authoritative native session exchange before financial routes unlock.
- Session security: native access tokens are memory-only; one-time refresh credentials rotate inside a 90-day absolute family; replay, expiry, logout and explicit server revocation invalidate the family through stable errors.
- Local protection: only the refresh credential, expiry values and session UUID are encoded for service `org.fynla.app.native-session`; Xcode-compiled Security framework calls set `kSecAttrAccessibleWhenUnlockedThisDeviceOnly`, `.biometryCurrentSet` and `kSecAttrSynchronizable=false`.
- Face ID boundary: opt-in is offered only after complete authentication on Face ID hardware. Cold unlock, cancel, failed recognition, lockout, protected-item invalidation, app-switcher privacy, 60-second relock, Lock and Sign out have deterministic coverage without treating Face ID as a password replacement.
- Automated evidence: the full backend authentication/native-session/mobile gate passed 345 tests with 1,261 assertions. The exact-source Swift 6 host suite passed 176 tests across 21 suites; the bearer-gated staging health test skipped honestly because no credential was supplied. Project verification passed, and real Xcode compiled the app, unit-test and UI-test targets for generic physical arm64 iOS with Swift warnings treated as errors. Clean macOS 26/Xcode 26.5 run `29618403316` passed 183 unit tests across 23 suites and all 28 UI tests with zero failures on an iPhone 11 simulator, emitted `TEST SUCCEEDED`, and completed the unsigned Production-scheme build with `BUILD SUCCEEDED`.
- Revocation evidence: backend coverage proves revoked refresh credentials return the same stable 401 as unknown credentials. The native controller test proves the next such refresh enters signed-out state, clears access and refresh memory, deletes the protected Keychain item and disables the Face ID preference.
- Log evidence: the Laravel test log contained no known native fixture secrets, bearer values or financial JSON bodies. Native diagnostic tests accept only allowlisted scalar metadata and redact arbitrary credential names and financial JSON before the OSLog sink.
- Simulator evidence: Xcode built, installed and visibly launched the deterministic native signed-out shell on an iPhone 16 Pro Max. The local iOS 26.3 test-manager still stalls waiting for workers, but the Task 9 simulator gate is complete through clean Xcode 26.5 run `29618403316`: 183 unit tests across 23 suites and all 28 iPhone 11 UI tests passed with zero failures, followed by a successful Production-scheme build.
- Remaining evidence: the paired physical iPhone 11 is currently unavailable to Xcode developer services, and the Google Chrome control connection is unavailable despite healthy extension/native-host installation. Physical Face ID/registration/Keychain inspection, an exported device diagnostic bundle and current `/m` Chrome acceptance remain required. No Chromium substitute is permitted.

## Current Package 2 foundation evidence

- Foundation boundary: native SwiftUI environment, session/router shell, typed API and server-sent events transports, privacy-safe diagnostics, accessible design primitives, deterministic app composition and iOS CI through Task 8.
- Composition evidence: one `AppDependencies` value owns the validated environment, ephemeral HTTP transport, redacting diagnostics, access-token provider, clock, request-ID factory and typed `FeatureClients` extension point.
- Deterministic shell evidence: the staging scheme's dedicated `UITesting` TestAction alone compiles `-fynla-ui-test-mode`, accepting only `signed-out`, `unlocked` and `design-system`. Those modes use a fixed dependency graph whose transport fails every data or byte-stream request; ordinary Debug/Staging launches and Release/Production builds do not compile or inspect the parser or test doubles.
- CI evidence: `.github/workflows/ios-native.yml` uses the public `macos-26` runner, selects an installed available iOS runtime, creates and boots an iPhone 11, runs signing-disabled tests by simulator UDID, and uploads the result bundle only when the job fails.
- Authenticated staging evidence: `StagingHealthIntegrationTests` calls the compiled `https://csjones.co/fynla/api/v1/native/health` endpoint only when `FYNLA_STAGING_BEARER_TOKEN` is supplied at runtime. It accepts no base-URL override, does not log the credential, and reports an honest skip while the token is absent.
- Automated evidence: the final exact-source Swift 6 host suite passed 70 tests across 11 suites; the authenticated staging health test compiled and skipped honestly because its runtime token was absent. Coverage includes bounded and ordered live-byte handoff, typed overflow with producer cancellation, the no-decorative-symbol source rule and exhaustive non-emoji multibyte UTF-8 splits. The UI-smoke shell helper parses and the workflow YAML is syntactically valid.
- Build evidence: an unsigned, no-assets Staging `build-for-testing` compiled the app, unit-test and UI-test targets under `UITesting`, and an unsigned, no-assets Production-scheme Release build also exited 0. Asset catalogues were excluded only to isolate unrelated quarantined asset-service work.
- Clean-runner evidence: `macos-26` run `29535226609` completed the full asset-enabled build, passed 69 Swift tests across 11 suites with one honest credential-gated health skip, passed all four iPhone 11 UI tests, emitted `** TEST SUCCEEDED **`, and completed the unsigned Production-scheme build with `** BUILD SUCCEEDED **`.
- Evidence status: automated Package 2 gates are green. Manual CSJ shell approval remains required before Package 3 expands account UI, so no user-capability row is promoted to `green` yet.
- Exclusions: no production deployment, production host request, production smoke check, legacy Capacitor surface, `/m` client or existing `ios/App/` project was changed or exercised.

## Current Package 1 handoff

- Package: iOS Package 1, Economic Contract and API Readiness
- Commit/PR: PR #630 merged to dev as `95d08410ca8c18b61cd72e820959c163f0a19180`; csjones runs that exact commit
- Backend tests: freemium remediation full suite green before dev deployment; Package 1 Task 2 architecture test 1 passed with 72 assertions; Task 3 auth and entitlement suite 37 passed with 177 assertions; Task 4 Mobile, AI and client contracts 487 passed with 1851 assertions and 3 intentional capture-only skips; Task 5 Native and Mobile regression 94 passed with 337 assertions; consolidated Package 1 gate 333 passed with 1,410 assertions
- Client JavaScript tests: 54 files and 727 tests passed; `tests/frontend/mobile/Dashboard.test.js` includes unknown additive response-field tolerance
- Build evidence: desktop Vite build and `/m` Vite build both exited 0; no Package 1 changes under `ios/App/`, `resources/mobile/` or `deploy/mobile/build-ios.sh`
- Swift tests: not applicable until Package 2
- Deployment evidence: tier-collapse audit returned `safe_to_collapse=true` with zero active paid subscriptions/users, live provider agreements, retired tier rows and duplicate financial identifiers; no migrations were pending; required reseed and cache/autoload rebuild completed
- Native boundary evidence: authenticated valid native headers returned 200/v1, authenticated invalid client headers returned 400/`invalid_native_client`, and the unauthenticated request returned 401 before native-header validation
- Desktop browser evidence: actual Google Chrome at 1280×900 on csjones dev `95d08410`; a disposable registration completed verification, resolved to permanent Free with no Subscription row or trial field, and reached `/fynla/dashboard` with no retired trial presentation, application-console error, page error or failed API response; screenshot `codex/evidence/ios-package1/csjones-package1-desktop-verified-free-chrome.png`
- `/m` browser evidence: the same verified Free account used actual Google Chrome at 390×844 on csjones dev `95d08410`; `/fynla/m/app/dashboard`, Fyn onboarding and `/fynla/m/app/savings` were green with no Fynla console error, page error, failed resource or failed API response; screenshots `codex/evidence/ios-package1/csjones-package1-mobile-dashboard-chrome.png`, `codex/evidence/ios-package1/csjones-package1-mobile-fyn-chrome.png`, `codex/evidence/ios-package1/csjones-package1-mobile-savings-chrome.png`
- Test-data restoration: the disposable verified account was force-deleted (`withTrashed` count 0), all seeders reran, the application cache was cleared and csjones returned live
- Simulator evidence: not applicable until Package 2
- Physical-device evidence: not applicable until Package 3
- Known exclusions: production and App Store release work are deferred; no production checks are part of this ledger entry. The host-wide `https://csjones.co/favicon.ico` returns 404 outside the Fynla `/fynla` application and is not a Fynla console failure.
- CSJ approval: Package 1 technical gate is green; explicit pre-StoreKit approval remains pending before Package 4
