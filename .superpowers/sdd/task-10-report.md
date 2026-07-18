# Task 10 report — Native premium subscription experience

## Outcome

Implemented the native iOS subscription experience for the free, Apple-premium,
web-premium, pending, and unavailable states. The implementation uses the native
entitlement API as the only source of truth and StoreKit 2 only for product,
purchase, restore, transaction-update, and Apple subscription-management work.

## Implementation

- Added a session-scoped `SubscriptionModel` with one transaction-update listener.
- Loaded canonical entitlement and StoreKit products concurrently, while allowing
  an existing premium entitlement to render when product loading fails.
- Added localized StoreKit monthly/annual choices using `displayPrice` and the
  product subscription period; production UI contains no hard-coded price.
- Added guarded purchase handling for verified, pending, and user-cancelled
  outcomes. A verified transaction is acknowledged by the server before it is
  finished, then canonical entitlement is reloaded.
- Added restore handling that syncs StoreKit, filters locally verified current
  entitlements by the stable app-account token and known product identifiers,
  sends the original transaction ID to reconciliation, and reloads canonical
  entitlement without performing local entitlement arithmetic.
- Added safe transaction-update processing through the same acknowledgement path.
- Added Apple-managed and web-managed premium presentations. Apple management
  opens the system subscription sheet; web-managed premium provides information
  without an in-app purchase or management CTA.
- Integrated Settings → Premium into the existing unlocked shell while retaining
  the existing lock and sign-out contracts.
- Added deterministic UI-test compositions for free, Apple premium, web premium,
  unavailable, purchase success, purchase pending, purchase cancellation, and
  restore success.

## `/m` parity decisions

Reviewed:

- `resources/js/views/Settings/SubscriptionSettings.vue`
- `resources/js/components/UserProfile/SubscriptionManagement.vue`
- `resources/js/utils/subscriptionPresentation.js`

Kept the `/m` plan-management hierarchy and plain-language distinction between
free, Apple-billed premium, and web-billed premium. Native purchase controls use
StoreKit product metadata instead of web prices. Premium states suppress purchase
and restore controls. Apple-billed users receive only the native system-management
action, while web-billed users receive management information without an in-app
billing CTA.

## Native API contract used

- `GET /api/v1/native/entitlement`
- `GET /api/v1/native/storekit/account-token`
- `POST /api/v1/native/storekit/transactions` with `signed_transaction`
- `POST /api/v1/native/storekit/reconcile` with `original_transaction_id`

All requests use the existing authenticated API client.

## TDD evidence

Valid exact-device RED builds were observed before each implementation slice:

1. Missing canonical subscription model/API types.
2. Missing product selection, purchase, transaction-update, and restore behavior.
3. Missing live native subscription API implementation.
4. Missing deterministic subscription UI-test composition.

Each slice was followed by an exact-device signed `build-for-testing` GREEN.
Tests cover concurrent loading, entitlement-owned presentation, localized product
selection, acknowledgement-before-finish ordering, acknowledgement rejection,
pending and cancelled purchases, transaction updates, restore/reconciliation,
authenticated endpoint paths and JSON payloads, all visible UI states, and
deterministic purchase/restore flows.

## Verification

- Final mandatory signed exact-device build:
  `xcodebuild build-for-testing -project Fynla.xcodeproj -scheme Fynla-Staging -destination 'platform=iOS Simulator,id=2FCE7BF1-85F1-4956-A7B8-F1F676DD244C' -parallel-testing-enabled NO`
  — exit 0, `** TEST BUILD SUCCEEDED **`, signing identity `Sign to Run Locally`.
- Native project verifier: `./ios-native/scripts/verify-project.sh`
  — exit 0, `native project structure verified`.
- `git diff --check` — clean.
- `project.pbxproj` review — Xcode normalization churn removed; final diff empty.
- Production subscription sources contain no hard-coded price strings. Deterministic
  UI fixtures intentionally use localized sample StoreKit display prices.

## Capped runtime attempt

One exact-device serial `test-without-building` attempt targeted the subscription
model/API suites and all eight deterministic subscription UI tests. XCTest printed
`Testing started`, then launched no test case and emitted no further test output for
73.083 seconds. Per the task constraint, the known worker-launch stall was
interrupted once and was not retried.

- Exit: 75, `** TEST EXECUTE INTERRUPTED **`
- Result bundle:
  `/Users/CSJ/Library/Developer/Xcode/DerivedData/Fynla-bpitrkqdkqukesaoavhwgibvbzci/Logs/Test/Test-Fynla-Staging-2026.07.18_21-23-56-+0100.xcresult`

## Concern

Runtime XCTest assertions did not begin because the simulator XCTest worker
stalled. Compile-time test discovery, signed build-for-testing, project integrity,
and static checks passed; runtime execution remains unobserved in this environment.

The requested direct smoke check used the already-signed app with bundle ID
`org.fynla.app.dev` on the exact simulator. `simctl install` emitted no output and
stalled for 60 seconds before a single termination (exit 130); the one subsequent
`simctl launch` attempt also emitted no output and stalled for 30 seconds before
termination (exit 130). Neither command was retried.
