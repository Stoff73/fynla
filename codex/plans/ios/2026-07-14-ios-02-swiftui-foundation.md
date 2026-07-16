# iOS Package 2: SwiftUI Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Use `superpowers:test-driven-development`, `systematic-debugging` for failures, `verification-before-completion` before the package gate, and `verify-m` if any shared backend contract changes. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create the clean native iPhone project, environment schemes, app/session shell, design tokens, API transport, server-sent events parser, diagnostics and unsigned CI lane needed by all later feature packages.

**Architecture:** A new `ios-native/Fynla.xcodeproj` lives beside the preserved Capacitor project. SwiftUI uses a small composition root, actor-isolated transports and protocol-injected feature clients. This package can decode fixtures and call authenticated native health, but it does not implement account screens or financial screens.

**Tech Stack:** Swift 6 strict concurrency, SwiftUI, Observation, Foundation, URLSession, OSLog, Swift Testing, XCTest UI testing, Xcode 26.3+, iOS 17.

## Global Constraints

- Do not edit `ios/App/`, `resources/mobile/` or `deploy/mobile/build-ios.sh`.
- The target device family is iPhone only; iPad is version 2.
- Minimum deployment is iOS 17; portrait only.
- Production bundle identifier is `org.fynla.app`; staging is `org.fynla.app.dev`.
- No CocoaPods, Capacitor, third-party networking or third-party state-management package.
- No Fynla web view.
- Use light appearance for version 1 until a separate dark-mode design is approved.
- Use dependency injection for URL loading, clock and diagnostics so tests never call production.
- Swift warnings, concurrency warnings and sendability warnings are package failures.

## File map

| Path | Responsibility |
|---|---|
| `ios-native/Fynla.xcodeproj/` | Native Xcode project, targets and schemes |
| `ios-native/Configurations/Base.xcconfig` | Shared build settings |
| `ios-native/Configurations/Staging.xcconfig` | csjones API/web identity |
| `ios-native/Configurations/Production.xcconfig` | fynla.org API/web identity |
| `ios-native/Fynla/App/` | App composition, session state and typed router |
| `ios-native/Fynla/Core/API/` | JSON request/response transport |
| `ios-native/Fynla/Core/Streaming/` | Incremental SSE parsing and transport |
| `ios-native/Fynla/Core/Diagnostics/` | Redacted logging and correlation identifiers |
| `ios-native/Fynla/Core/DesignSystem/` | Approved native tokens and shared primitives |
| `ios-native/FynlaTests/` | Swift unit and fixture tests |
| `ios-native/FynlaUITests/` | Deterministic shell UI tests |
| `.github/workflows/ios-native.yml` | Unsigned simulator build/test lane |

### Task 1: Create the iPhone-only Xcode project and schemes

**Files:** Create `ios-native/Fynla.xcodeproj`, `ios-native/Fynla/`, `ios-native/FynlaTests/`, `ios-native/FynlaUITests/`, `ios-native/Configurations/`, shared schemes `Fynla-Staging` and `Fynla-Production`.

- [x] In Xcode, create an iOS App project named `Fynla` at `ios-native/`, interface SwiftUI, language Swift, tests enabled, Core Data and CloudKit disabled.
- [x] Set Swift language mode to Swift 6 and strict concurrency to Complete for app and test targets.
- [x] Set `IPHONEOS_DEPLOYMENT_TARGET = 17.0`, `TARGETED_DEVICE_FAMILY = 1`, and portrait iPhone orientations only.
- [x] Set staging product bundle identifier to `org.fynla.app.dev` and production to `org.fynla.app` through `.xcconfig` files, not user-specific project settings.
- [x] Share both schemes. `Fynla-Staging` uses Debug/Staging; `Fynla-Production` uses Release/Production.
- [x] Keep signing automatic but do not hardcode a personal development-team identifier in source.
- [x] Copy the existing approved app icon source images from `ios/App/App/Assets.xcassets/AppIcon.appiconset/` into the new asset catalogue; do not alter the preserved source files.
- [x] Add `SUPPORTED_PLATFORMS = iphoneos iphonesimulator` and exclude Mac Catalyst, visionOS and iPad destinations.

`Base.xcconfig`:

```text
IPHONEOS_DEPLOYMENT_TARGET = 17.0
SWIFT_VERSION = 6.0
SWIFT_STRICT_CONCURRENCY = complete
TARGETED_DEVICE_FAMILY = 1
SUPPORTS_MACCATALYST = NO
GENERATE_INFOPLIST_FILE = YES
INFOPLIST_KEY_UISupportedInterfaceOrientations = UIInterfaceOrientationPortrait
INFOPLIST_KEY_UIUserInterfaceStyle = Light
```

`Staging.xcconfig`:

```text
#include "Base.xcconfig"
PRODUCT_BUNDLE_IDENTIFIER = org.fynla.app.dev
FYNLA_API_BASE_URL = https:/$()/csjones.co/fynla
FYNLA_WEB_BASE_URL = https:/$()/csjones.co/fynla
FYNLA_ENVIRONMENT = staging
```

`Production.xcconfig`:

```text
#include "Base.xcconfig"
PRODUCT_BUNDLE_IDENTIFIER = org.fynla.app
FYNLA_API_BASE_URL = https:/$()/fynla.org
FYNLA_WEB_BASE_URL = https:/$()/fynla.org
FYNLA_ENVIRONMENT = production
```

The `$()` split prevents xcconfig from treating `//` as a comment.

- [x] Add Info.plist keys mapping the three `FYNLA_*` variables into the app bundle. Xcode 26.3 ignores unknown `INFOPLIST_KEY_*` values during automatic generation, so the app target uses a checked-in minimal plist template while test targets remain generated.
- [x] Build both schemes with code signing disabled.

```bash
xcodebuild -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging -destination 'generic/platform=iOS Simulator' build CODE_SIGNING_ALLOWED=NO
xcodebuild -project ios-native/Fynla.xcodeproj -scheme Fynla-Production -destination 'generic/platform=iOS Simulator' build CODE_SIGNING_ALLOWED=NO
```

Expected: both exit 0; build settings show device family `1`, deployment target `17.0`, and distinct bundle identifiers.

Recorded 2026-07-16 on Xcode 26.3: both unsigned simulator builds and the staging `build-for-testing` action exited 0. Built plists contain device family `[1]`, deployment target `17.0`, portrait-only orientation, light appearance, and the expected distinct bundle/environment values.

**Intended review boundary:** `build: create native swiftui iphone project`

### Task 2: Build immutable environment configuration

**Files:** Create `ios-native/Fynla/App/AppEnvironment.swift`; create `ios-native/FynlaTests/AppEnvironmentTests.swift`.

- [x] Write failing tests that load staging/production dictionaries and reject missing, non-HTTPS or user-info-bearing URLs.
- [x] Implement an immutable, sendable environment model:

```swift
struct AppEnvironment: Sendable, Equatable {
    enum Name: String, Sendable { case staging, production }

    let name: Name
    let apiBaseURL: URL
    let webBaseURL: URL
    let clientName = "ios"
    let productIdentifiers: Set<String> = [
        "org.fynla.premium.monthly",
        "org.fynla.premium.annual"
    ]

    static func bundle(_ bundle: Bundle = .main) throws -> Self { /* validated mapping */ }
}
```

- [x] Make `bundle(_:)` throw `ConfigurationError` before the UI renders if values are absent or invalid.
- [x] Do not include tokens, Apple keys, Revolut data or secrets.
- [ ] Run `xcodebuild ... -only-testing:FynlaTests/AppEnvironmentTests test`; expect PASS.

Recorded 2026-07-16: TDD red failed to compile because `ConfigurationError` did not exist. The implementation then passed the staging `build-for-testing` action under Swift 6 strict concurrency and warnings-as-errors. A disposable host Swift 6 package symlinked the exact repository source and test files; all 8 `AppEnvironmentTests` passed in one suite. The local iOS 26.3 simulator completed a clean first boot and installed the ad-hoc app, but both normal app launch and the XCTest-host launch stalled before creating an app process. Keep the simulator checkbox open until this suite executes on the CI runner or a healthy local CoreSimulator runtime.

**Intended review boundary:** `feat: add validated native environments`

### Task 3: Create the app session state machine and typed router

**Files:** Create `ios-native/Fynla/App/FynlaApp.swift`, `AppRootView.swift`, `AppSession.swift`, `AppRouter.swift`; create `ios-native/FynlaTests/AppSessionTests.swift`, `AppRouterTests.swift`.

- [x] Write state-transition tests for `launching`, `signedOut`, `authenticating`, `verificationRequired`, `multiFactorRequired`, `authenticatedLocked`, `authenticatedUnlocked` and `deletingAccount`.
- [x] Assert financial routes are rejected unless the session is `authenticatedUnlocked`.
- [x] Define routes using identifiers rather than payloads:

```swift
enum AppRoute: Hashable, Sendable {
    case dashboard
    case achievements
    case module(String)
    case income
    case expenditure
    case netWorth(category: String?)
    case protection(policyType: String?, id: Int?)
    case savings(accountID: Int?)
    case investment(accountID: Int?)
    case retirement(pensionType: String?, id: Int?)
    case estate
    case goals
    case taxStrategy
    case holisticPlan
    case settings
}
```

- [x] Implement `@MainActor @Observable final class AppSession` and `@MainActor @Observable final class AppRouter`.
- [x] Root rendering must cover every session state with a privacy-safe launch, signed-out, locked or unlocked shell; no financial feature exists yet.
- [x] Add UI accessibility identifiers `app.launching`, `auth.signedOut`, `app.locked`, `app.unlocked`.
- [x] Run AppSession/AppRouter tests; expect PASS.

Recorded 2026-07-16: the TDD red run failed because `AppSession`, `AppRoute` and `AppRouter` did not exist. The exact repository environment, session and router sources then passed 17 Swift 6 host tests across three suites. A direct iOS Simulator SDK typecheck of all five app-composition source files passed with strict concurrency and warnings-as-errors. An Xcode `build-for-testing` of the app, unit-test and corrected `app.launching` UI-test targets also passed with asset catalogues excluded to isolate the known local asset-service fault. The full generic integration build was stopped after macOS asset-catalog tooling stalled before Swift compilation, matching the separately recorded CoreSimulator issue; no simulator was launched.

**Intended review boundary:** `feat: add native session and route shell`

### Task 4: Implement typed API transport

**Files:** Create `Core/API/APIClient.swift`, `APIRequest.swift`, `APIEnvelope.swift`, `APIError.swift`, `HTTPTransport.swift`; create matching tests and JSON fixtures under `FynlaTests/Fixtures/API/`.

- [x] First write failing tests for a success envelope, 422 field errors, 401, 403, upgrade-required, 409, 429 with `Retry-After`, 500 and invalid JSON.
- [x] Define the request boundary:

```swift
protocol HTTPTransport: Sendable {
    func data(for request: URLRequest) async throws -> (Data, HTTPURLResponse)
    func byteStream(for request: URLRequest) async throws -> HTTPByteStream
}

struct HTTPByteStream: Sendable {
    let response: HTTPURLResponse
    let bytes: AsyncThrowingStream<UInt8, Error>
}

struct APIEnvelope<Value: Decodable & Sendable>: Decodable, Sendable {
    let success: Bool
    let data: Value
}

enum APIError: Error, Sendable, Equatable {
    case validation([String: [String]])
    case unauthenticated
    case forbidden(message: String?)
    case upgradeRequired(message: String)
    case rateLimited(retryAfter: Duration?)
    case conflict(message: String?)
    case offline
    case server(status: Int, requestID: String?)
    case decoding(requestID: String?)
}
```

- [x] Implement `actor APIClient` using an ephemeral `URLSessionConfiguration`; set `urlCache=nil`, `requestCachePolicy=.reloadIgnoringLocalCacheData`, `httpShouldSetCookies=false`.
- [x] Add headers `Accept`, `X-Fynla-Client`, `X-Fynla-Version`, `X-Fynla-Build`, `X-Request-ID`; add `Authorization` only through an injected access-token provider.
- [x] Permit exactly one refresh attempt for an idempotent request after 401. Do not replay POST/PATCH/PUT/DELETE automatically.
- [x] Decode field validation from the existing Laravel envelope and preserve server copy.
- [x] Treat decoding failure as visible error, never an empty object or zero.
- [x] Add fixture tests for `/api/v1/mobile/dashboard`, `/api/auth/user` and `/api/v1/mobile/modules/savings` using sanitised repository response shapes.
- [x] Run all API tests; expect PASS.

Recorded 2026-07-16: the TDD red run failed because the API boundary types did not exist. The exact repository sources then passed 31 Swift 6 host tests across five suites, including status/error mapping, injected native headers, offline handling, numeric and HTTP-date `Retry-After`, a single GET refresh, no replay for POST/PATCH/PUT/DELETE, and all three sanitised contract fixtures. Xcode `build-for-testing` compiled the app, unit-test and UI-test targets with warnings-as-errors and asset catalogues excluded to isolate the known local asset-service fault; the fixture JSON files were present in the built iOS test bundle. No remote endpoint was called.

**Intended review boundary:** `feat: add typed native api client`

### Task 5: Implement redacted diagnostics

**Files:** Create `Core/Diagnostics/DiagnosticEvent.swift`, `DiagnosticsClient.swift`, `RedactingDiagnosticsClient.swift`; create tests.

- [x] Write failing tests proving header names `Authorization`, `Cookie`, verification fields, password fields, `signedTransaction`, `signedPayload` and arbitrary financial JSON values never reach the sink.
- [x] Use an allowlisted event, not a generic dictionary logger:

```swift
struct DiagnosticEvent: Sendable, Equatable {
    let category: Category
    let operation: String
    let statusCode: Int?
    let requestID: String?
    let durationMilliseconds: Int?
}
```

- [x] Use `Logger` privacy annotations for these allowlisted scalar fields only.
- [x] Ensure network bodies are not accepted by the diagnostics protocol at compile time.
- [x] Run diagnostics tests; expect PASS.

Recorded 2026-07-16: the TDD red run failed because the diagnostics boundary did not exist. The protocol now accepts only `DiagnosticEvent`; the private OSLog sink can be constructed only through the redacting client, and operation names are fixed to a closed allowlist. Unsafe operations, request identifiers, credential/header names and financial JSON are removed before the sink. All 36 exact-source Swift 6 host tests across six suites passed, followed by an Xcode `build-for-testing` pass under warnings-as-errors with asset catalogues excluded to isolate the known local asset-service fault.

**Intended review boundary:** `feat: add privacy-safe native diagnostics`

### Task 6: Implement the incremental SSE parser and transport boundary

**Files:** Create `Core/Streaming/SSEParser.swift`, `SSEEvent.swift`, `SSEClient.swift`; create `SSEParserTests.swift`, `SSEClientTests.swift`, fixtures.

- [ ] Write parser tests before implementation for CRLF/LF framing, multiple `data:` lines, comments, event IDs, UTF-8 split across byte chunks, multiple frames per chunk and a final frame without trailing blank line.
- [ ] For every fixture byte array, feed the parser at every possible single split point and assert identical events.
- [ ] Define:

```swift
struct SSEEvent: Sendable, Equatable {
    let id: String?
    let event: String?
    let data: String
}

struct SSEParser: Sendable {
    mutating func append(_ bytes: some Sequence<UInt8>) throws -> [SSEEvent]
    mutating func finish() throws -> [SSEEvent]
}
```

- [ ] Keep event decoding separate from transport parsing; unknown typed Fyn frames must remain decodable as raw JSON in Package 5.
- [ ] Define `SSEClient.stream(_:) -> AsyncThrowingStream<SSEEvent, Error>` using URLSession bytes and cooperative cancellation.
- [ ] Branch on status 202 before reading a stream and return a typed queued result containing the server message identifier.
- [ ] Do not stop at a Fyn `done` event; only end when the HTTP body ends or a terminal transport error occurs.
- [ ] Run all SSE tests; expect PASS.

**Intended review boundary:** `feat: add byte-safe server sent events client`

### Task 7: Add native design tokens and accessible primitives

**Files:** Create `Core/DesignSystem/FynlaColor.swift`, `FynlaTypography.swift`, `FynlaSpacing.swift`, `FynlaButton.swift`, `LoadingView.swift`, `ErrorView.swift`; tests/previews as appropriate.

- [ ] Read `fynlaDesignGuide.md` immediately before this UI task.
- [ ] Map only approved palette tokens into the asset catalogue with named light-mode colours; no hardcoded feature-level colours.
- [ ] Use system font metrics and Dynamic Type. Do not force Segoe UI, which is not an iOS system font.
- [ ] Provide primary, secondary and destructive text buttons without decorative icons.
- [ ] Ensure a 44-point minimum interactive target and VoiceOver label for every control.
- [ ] Honour Reduce Motion in loading/transition primitives.
- [ ] Add an XXL Dynamic Type shell UI test proving primary controls remain reachable without clipped text on iPhone 11.

**Intended review boundary:** `feat: add accessible native design system`

### Task 8: Add deterministic app composition and CI

**Files:** Create `AppDependencies.swift`, `Testing/TestAppDependencies.swift`; modify app entry; create `.github/workflows/ios-native.yml` and shell UI smoke test.

- [ ] Inject environment, HTTP transport, diagnostics, token provider, clock and later feature clients through one `AppDependencies` value.
- [ ] Add launch argument `-fynla-ui-test-mode` that selects only compiled test doubles in UI-test builds; production must not accept a URL override or arbitrary fixture path.
- [ ] UI test signed-out and unlocked shell states without network.
- [ ] Add CI on pull requests affecting `ios-native/**`, shared API files or the workflow.
- [ ] CI selects an installed iPhone 11 simulator runtime dynamically, boots it, runs tests with signing disabled and stores `.xcresult` on failure.

Workflow command:

```bash
xcodebuild -project ios-native/Fynla.xcodeproj \
  -scheme Fynla-Staging \
  -destination 'platform=iOS Simulator,name=iPhone 11' \
  -resultBundlePath /tmp/FynlaTests.xcresult \
  test CODE_SIGNING_ALLOWED=NO
```

- [ ] Run the command locally; expect `** TEST SUCCEEDED **`.
- [ ] Build production scheme unsigned; expect `** BUILD SUCCEEDED **`.
- [ ] Call `/api/v1/native/health` from a small integration test using staging credentials supplied at runtime; never put credentials in source or CI logs.
- [ ] Update `docs/architecture/client-parity-ledger.md` with foundation evidence.

### Package 2 exit criteria

- [ ] Both schemes build and use the correct base URL and bundle identifier.
- [ ] The application is iPhone-only, iOS 17 minimum and portrait-only.
- [ ] iPhone 11 simulator unit/UI tests pass.
- [ ] API and SSE transports pass fixture, error and byte-boundary tests.
- [ ] No private response caching or body logging exists.
- [ ] The new target contains no Capacitor, CocoaPods or `WKWebView` dependency.
- [ ] Existing Capacitor and `/m` files are unchanged.
- [ ] CSJ approves the native shell before Package 3 account UI expands it.
