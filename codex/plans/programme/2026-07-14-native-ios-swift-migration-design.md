# Fynla Native iPhone Swift Migration Design

**Date:** 14 July 2026

**Status:** Approved by CSJ on 14 July 2026; implementation plans generated separately

**Owner:** CSJ

**Primary dependency:** `codex/plans/programme/2026-07-14-freemium-economic-contract-remediation.md`

**Release path:** feature branch -> `dev` -> csjones verification -> TestFlight -> App Store submission

## 1. Goal

Build a genuine native Fynla iPhone application in SwiftUI from the current `/m` product while keeping the `/m` mobile-web pathway fully intact and supported.

The native application is a third client of the existing Laravel platform. It does not replace the desktop Vue application, the `/m` Vue application, the Laravel financial engines, or the shared Fyn conversation endpoint.

Version 1 succeeds when an iPhone user can:

- Register and verify a new Fynla account entirely inside the native application.
- Begin and continue Fyn onboarding through the same server-owned flow used by the web clients.
- Sign in with the existing email verification or multi-factor authentication requirements.
- Opt into Face ID after a complete first authentication and use it securely on later launches.
- Use the current `/m` dashboard, navigation, gamification, Fyn chat, financial summaries and detail pathways as native SwiftUI screens.
- Start on the Free tier and buy Premium inside the application through StoreKit.
- Restore and manage an Apple subscription.
- Receive Premium access purchased through either Apple or the existing Revolut web pathway without buying twice.
- Export data, manage privacy, and initiate complete account deletion inside the application.
- Move between native screens through universal links without embedding the web application in a web view.

The migration must be incremental, testable and reversible. The existing Capacitor project remains available during the transition, and `/m` remains a permanent supported product after the native application launches.

## 2. Approved product decisions

The following decisions are fixed for version 1:

1. The client is a native SwiftUI application, not a Capacitor or `WKWebView` wrapper.
2. Version 1 supports iPhone only, with the iPhone 11, iPhone 11 Pro and iPhone 11 Pro Max as the guaranteed oldest hardware baseline.
3. iPad user-interface support is deferred to version 2.
4. `/m` remains fully intact, deployable and supported.
5. The desktop web application and `/m` remain subject to the repository's web-plus-`/m` parity rule.
6. Native parity becomes an additional release gate for every native feature declared complete.
7. Face ID is supported through native LocalAuthentication and Keychain controls.
8. New users can register and subscribe without leaving the application.
9. Apple digital subscription purchases use StoreKit.
10. Fynla has exactly two economic tiers: Free and Premium.
11. Premium costs GBP 6.99 monthly or GBP 59.99 annually in the United Kingdom storefront.
12. There is no free trial or introductory offer.
13. There are currently no paid customer accounts, so the provider-neutral entitlement model can be introduced before live subscription migration is required.
14. Laravel remains authoritative for financial calculations, tax rules, recommendations, Fyn dispatch, data writes, limits and entitlements.
15. Swift must not reproduce financial or tier logic that already exists on the server.
16. Family Sharing is disabled for version 1.
17. The application is online-first. Version 1 does not queue financial writes offline.
18. Version 1 does not persist financial response payloads as an offline database on the device.

## 3. Current-state evidence

This design is grounded in repository and vault inspection performed on 14 July 2026.

### 3.1 Existing Apple target

The existing Apple project under `ios/App/` is a Capacitor 6 application:

- `ios/App/App/AppDelegate.swift` contains only the Capacitor application delegate.
- The user interface is the `resources/mobile/` Vue bundle displayed by `WKWebView`.
- `deploy/mobile/build-ios.sh` builds `public/m-build/` and runs `npx cap sync ios`.
- The bundle identifier is `org.fynla.app`.
- The current target supports both device families 1 and 2, despite version 1 now being explicitly iPhone-only.
- The current application deployment target is iOS 16.
- The workspace has Xcode 26.3 and Swift 6.2.4 installed.
- The existing project includes push, associated-domain and Keychain entitlements.
- The historical Capacitor Face ID implementation belongs to a retired mobile client and is not present in the current `resources/mobile/` surface.

### 3.2 Existing `/m` surface

`resources/mobile/` is an isolated mobile-web application with approximately 9,480 source lines and 21 Vue view files. It includes:

- Login and email verification.
- Dashboard and approved gamification.
- Fyn onboarding and advice chat.
- Achievements and activity.
- Income and expenditure.
- Net worth and category detail.
- Protection and policy detail.
- Savings and account detail.
- Investment and account detail.
- Retirement and pension detail.
- Estate planning.
- Goals.
- Tax strategy.
- Holistic plan.
- Bug reporting.

The `/m` application is served by its own routes, Vite configuration and output bundle. Phone browser traffic continues to be redirected to `/m`; this behaviour is independent from native universal links.

### 3.3 Shared backend contracts

The current mobile product already consumes server-owned contracts including:

- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/verify-code`
- `POST /api/auth/resend-code`
- `POST /api/auth/mfa/verify`
- `POST /api/auth/mfa/recovery`
- `GET /api/auth/user`
- `POST /api/auth/logout`
- `POST /api/v1/auth/refresh-token`
- `GET /api/v1/mobile/dashboard`
- `GET /api/v1/mobile/modules/{module}`
- `GET /api/v1/mobile/achievements`
- `GET /api/v1/mobile/achievements/completed`
- `GET /api/v1/mobile/insights/daily`
- `POST /api/ai-chat/conversations`
- `GET /api/ai-chat/conversations/{id}`
- `POST /api/ai-chat/conversations/{id}/messages`
- `POST /api/ai-chat/conversations/{id}/action`
- `GET /api/ai-chat/conversations/{id}/messages/{messageId}/stream`
- Module and detail endpoints already used by `resources/mobile/`.

The backend currently provides bearer-token authentication, mobile dashboard aggregation, typed Fyn server-sent events, push-device registration and share metadata. These contracts are reusable but require native-session and Apple-entitlement additions.

### 3.4 Current economic boundary

The canonical economic model is defined by `Fynla_Pricing_Page.html` and the economic remediation plan. Native StoreKit work must not begin against the legacy Student, Standard, Pro, Family or `tier1` to `tier3` state.

The StoreKit production gate therefore depends on completion of the remediation plan's canonical Free and Premium migrations, count caps, capability matrix, tier resolver, trial removal and regression coverage.

## 4. Considered approaches

### 4.1 Selected: clean SwiftUI project beside the current clients

Create a new native project under `ios-native/` while leaving `ios/App/`, `resources/mobile/` and `/m` untouched.

Benefits:

- Clean native architecture without Capacitor, CocoaPods or web-build assumptions.
- Existing Capacitor build remains a rollback and behaviour reference during development.
- `/m` can continue shipping independently.
- Native work can proceed in vertical slices without destabilising the live mobile-web product.
- Swift Package Manager can be used if a dependency becomes necessary.
- The App Store release can retain `org.fynla.app` and therefore preserve the application identity.

Cost:

- The client interface is implemented separately in Swift.
- Three clients must be kept compatible with shared backend contracts.

This is the approved approach.

### 4.2 Rejected: convert `ios/App/` in place

Replacing the existing Capacitor target directly would reduce the number of project folders but remove the safest rollback point, mix native and generated project state, and make it difficult to compare native behaviour with the current packaged client.

### 4.3 Rejected: native shell around `/m`

Embedding `/m` in a native shell would be quicker for the first screen, but it would retain web storage, navigation, streaming and accessibility constraints. It would also fail the requirement to create an actual native Apple application.

## 5. Target system architecture

```text
Desktop Vue application -----+
                              |
/m mobile-web application ----+--> Versioned Laravel API
                              |       |
Native SwiftUI iPhone app ----+       +--> Authentication and device sessions
                                      +--> Tier and entitlement resolver
                                      +--> Financial agents and services
                                      +--> Mobile dashboard aggregation
                                      +--> One Fyn conversation endpoint
                                      +--> Apple and Revolut billing adapters
```

The clients share business behaviour through server contracts, not through copied source code.

### 5.1 Proposed native project structure

```text
ios-native/
  Fynla.xcodeproj/
  Fynla/
    App/
      FynlaApp.swift
      AppEnvironment.swift
      AppSession.swift
      AppRouter.swift
    Core/
      API/
      Authentication/
      Biometrics/
      Keychain/
      StoreKit/
      Streaming/
      DesignSystem/
      Diagnostics/
    Features/
      Authentication/
      Registration/
      Dashboard/
      Fyn/
      Achievements/
      Income/
      Expenditure/
      NetWorth/
      Protection/
      Savings/
      Investment/
      Retirement/
      Estate/
      Goals/
      TaxStrategy/
      HolisticPlan/
      Subscription/
      Settings/
      Privacy/
      BugReport/
    Resources/
      Assets.xcassets/
      Localizable.xcstrings
      PrivacyInfo.xcprivacy
  FynlaTests/
  FynlaUITests/
  StoreKit/
    Fynla.storekit
```

Feature folders own their views, view state, models and endpoint adapters. Shared infrastructure lives under `Core/`. A feature must not import another feature's private view model.

### 5.2 Build identities

- Production bundle identifier: `org.fynla.app`.
- Development bundle identifier: `org.fynla.app.dev`.
- Staging scheme points at csjones.
- Production scheme points at fynla.org.
- Endpoint bases are supplied through checked-in build configuration files containing no secrets.
- Production and staging StoreKit environments are selected by signed transaction environment, not by trusting a client flag.
- The production App Store binary replaces the Capacitor binary only after the complete native acceptance gate passes.

### 5.3 Platform baseline

- SwiftUI application lifecycle.
- Swift 6 language mode with strict concurrency enabled from the start.
- iOS 17 minimum deployment target.
- iPhone device family only for version 1.
- The guaranteed hardware baseline is the complete iPhone 11 family. No version 1 feature may require hardware introduced after iPhone 11.
- Earlier iPhones that can install iOS 17 may remain technically installable, but they are outside the guaranteed performance and acceptance matrix.
- The current App Store-required iOS 26 SDK or later for submission.
- Version 1 supports portrait orientation on iPhone. Native landscape layouts are deferred with iPad interface work to version 2 unless CSJ approves a separate design change.
- No third-party runtime dependency is introduced until an identified requirement cannot be met safely with Apple frameworks.

## 6. Native application components

### 6.1 `AppEnvironment`

Owns immutable environment configuration:

- API base URL.
- Public web base URL.
- Native client identifier and semantic version.
- Apple product identifiers.
- Logging policy.

It contains no access token, App Store private key, Revolut credential or other server secret.

### 6.2 `AppSession`

A `@MainActor @Observable` state machine controls the root experience:

```text
launching
  -> signedOut
  -> authenticating
  -> verificationRequired
  -> multiFactorRequired
  -> authenticatedLocked
  -> authenticatedUnlocked
  -> deletingAccount
```

Only `authenticatedUnlocked` can present financial data. A 401 or revoked device session returns the state to `signedOut`, clears in-memory financial state and removes invalid native credentials.

### 6.3 `AppRouter`

Uses typed, lightweight route values with `NavigationStack`. Route values contain identifiers and screen intent, not financial model payloads.

The native information architecture initially mirrors `/m`:

- Dashboard is the authenticated root.
- A native navigation menu mirrors the current `/m` groups and routes.
- Fyn is always available through an approved persistent entry point and opens as a native full-screen conversation surface.
- Detail views push onto the current navigation stack.
- Universal links translate into typed native routes only when the destination is part of the shipped native scope.

The migration does not invent a new tab architecture or reorganise financial modules. A future navigation redesign requires its own approved design.

### 6.4 `APIClient`

The shared actor-based API client provides:

- `URLSession` requests using `async` and `await`.
- Bearer-token injection from the active native session.
- JSON envelope decoding.
- Request identifiers and native client-version headers.
- Cancellation when a view task ends.
- A single authenticated refresh attempt after a 401.
- Typed validation, authentication, entitlement, rate-limit, connectivity and server errors.
- `Retry-After` support for 429 responses.
- No automatic replay of non-idempotent writes.
- Redaction of tokens, verification codes, financial payloads and signed StoreKit data from logs.

Private API responses use an ephemeral URL session cache policy unless a specific endpoint contract requires an in-memory ETag. Version 1 does not save financial JSON responses to a device database.

### 6.5 `SSEClient`

The native server-sent events actor uses `URLSession` byte streaming and an incremental parser. It must support:

- Arbitrarily divided network chunks.
- Multiple events in one network chunk.
- UTF-8 text split across chunks.
- Event cancellation when the user leaves or stops a turn.
- Typed event dispatch.
- A `202` queued-message JSON response instead of a stream.
- A later queued-reply stream.
- Events emitted after the assistant `done` event, including `level_up`.
- User-visible error frames.
- Bubble choices, capture confirmation, navigation and tool-status frames.
- Reconnection only through explicit server-supported resume identifiers.

The client sends every message to the same Fyn endpoint. It does not expose or reproduce the server's onboarding-versus-advice dispatch decision.

### 6.6 Design system

The Swift design system maps the approved Fynla palette, typography, spacing and component semantics into native tokens. It must preserve:

- The current approved `/m` gamification layer.
- British English.
- No user-facing financial-quality scores.
- No new decorative icons.
- Only functionally necessary or explicitly approved mobile icons.
- Dynamic Type and VoiceOver semantics.
- Minimum Apple touch targets.
- Reduced-motion behaviour.
- Light-mode support for version 1 unless a separate dark-mode design is approved.

The Swift client reproduces the intent and hierarchy of `/m`; it does not mechanically translate CSS dimensions into points.

## 7. Authentication and device-session design

### 7.1 Registration

Registration remains server-owned and uses the existing public endpoints:

1. Collect first name, optional middle name, surname, email, password and password confirmation.
2. Display and link the current Terms of Service and Privacy Policy.
3. Submit `POST /api/auth/register`.
4. Retain only the pending registration identifier and masked email in transient application state.
5. Collect the six-digit registration code.
6. Submit `POST /api/auth/verify-code` with `type=registration`.
7. Exchange the bootstrap bearer credential for a native device session.
8. Load `/api/auth/user` and start or resume Fyn onboarding.

Registration never asks for a trial or a legacy plan. New users receive Free from the server. Premium purchase is a separate authenticated action.

The native client handles existing-email, restorable-account, expired-registration, resend-limit and lockout responses explicitly.

### 7.2 Login and multi-factor authentication

The client implements all current login branches:

- Preview-user immediate login in non-production testing contexts.
- Email verification challenge.
- Time-based one-time-password challenge.
- Recovery-code challenge.
- Locked account and locked IP responses.
- Restorable deleted account.
- Password reset.
- Mandatory password change if returned by the server.

The native application must not simplify or bypass a server-required authentication factor.

### 7.3 Native device session

The current `/api/v1/auth/refresh-token` revokes and replaces one bearer token, but it does not provide the separation required for secure Face ID convenience. Native version 1 adds a parallel device-session contract without changing `/m`:

- `POST /api/v1/native/auth/session/exchange`
- `POST /api/v1/native/auth/session/refresh`
- `DELETE /api/v1/native/auth/session`
- `GET /api/v1/native/auth/session`

Exchange accepts the short-lived bootstrap bearer created by successful registration, email verification or multi-factor verification. In one transaction it:

- Validates the bootstrap token and authenticated user.
- Records an opaque native device session.
- Revokes the bootstrap token.
- Returns a short-lived access token and one-time refresh credential.
- Returns access and refresh expiry timestamps.

Refresh rotates both credentials. Reuse of an already-rotated refresh credential revokes the device-session family and requires full login. Refresh credentials are stored as hashes on the server.

The version 1 session contract uses a 15-minute access-token lifetime, a 30-day rotating refresh-credential lifetime and an absolute 90-day limit from the last complete password-plus-verification or multi-factor authentication. Reaching the absolute limit requires complete login. The server clock and server configuration are authoritative for all expiry decisions.

Session records include user, device-session identifier, platform, application version, created time, last-used time, expiry, revocation time and a non-sensitive device label. They do not store a user-supplied device name without validation.

### 7.4 Keychain and Face ID

- Access tokens live in memory and expire quickly.
- The refresh credential is stored in Keychain with `kSecAttrAccessibleWhenUnlockedThisDeviceOnly` and `biometryCurrentSet` access control.
- The Keychain item is not synchronised through iCloud Keychain.
- Face ID is offered only after a successful complete authentication.
- No LocalAuthentication or protected Keychain read occurs before the user opts in.
- A cold launch with Face ID enabled presents Face ID before refreshing the native session.
- Returning from the background for 60 seconds or longer locks financial content and requires Face ID again.
- A shorter interruption obscures the application-switcher snapshot but does not interrupt an active form or Fyn stream.
- A Face ID cancellation leaves the user at a locked screen with normal sign-in available.
- A biometric enrolment change invalidates the Keychain credential and requires full login.
- Biometric failure counting and lockout are delegated to iOS. A biometric-lockout result requires full Fynla login.
- Real sign-out revokes the native session and deletes the Keychain item.
- A separate Lock action retains the server session but requires Face ID to reopen.
- Face ID is never described as replacing the account password or server authentication factors.

On an iPhone without Face ID, the user can use normal login. Touch ID is not advertised as a version 1 product feature.

### 7.5 Local privacy controls

- Financial views are covered before the application snapshot enters the app switcher.
- Clipboard copy is explicit and limited to user-selected values.
- Verification codes and tokens never appear in logs or diagnostics.
- Bug reports include an allowlisted diagnostic envelope, not raw request or response bodies.

## 8. StoreKit and entitlement design

### 8.1 Apple products

One App Store subscription group contains two equivalent Premium products:

| Product | Product identifier | Duration | UK price |
|---|---|---:|---:|
| Premium Monthly | `org.fynla.premium.monthly` | 1 month | GBP 6.99 |
| Premium Annual | `org.fynla.premium.annual` | 1 year | GBP 59.99 |

Both products unlock the same Premium capabilities. They differ only by billing duration and price. They belong to the same subscription level so a user cannot hold both simultaneously.

There is no introductory offer, free trial, Family Sharing or consumable purchase in version 1.

The app displays StoreKit's localized product name, price and period. Static Swift copy cannot be the price authority.

### 8.2 Account association

An authenticated endpoint returns a stable random UUID for the current Fynla user. The UUID:

- Is used as StoreKit's `appAccountToken` purchase option.
- Contains no user identifier, email or other personal data.
- Is unique across Fynla accounts.
- Is returned by Apple in subsequent transaction and renewal information.
- Is not generated independently by the client.

Anonymous purchase is not supported. Registration or login occurs before StoreKit purchase so every transaction can be assigned deterministically.

### 8.3 Purchase sequence

1. Load products from StoreKit.
2. Load the server's current canonical entitlement.
3. If the user is already Premium through Apple or Revolut, do not offer a second purchase.
4. Start StoreKit purchase with the server-provided `appAccountToken`.
5. Verify the StoreKit transaction locally before using its signed representation.
6. Send signed transaction data to the Fynla server.
7. Verify the Apple signature and payload on the server.
8. Check bundle identifier, product identifier, application account token and environment.
9. Persist the transaction idempotently.
10. Reconcile the user's provider-neutral Premium entitlement.
11. Return the canonical server entitlement.
12. Finish the StoreKit transaction only after durable server acknowledgement.
13. Refresh the application session's tier and capability data.

The client never upgrades `users.tier`, enables a Premium screen, or infers expiry from local time without server confirmation.

### 8.4 Provider-neutral subscription model

The economic remediation establishes Free and Premium. StoreKit then introduces a provider-neutral billing boundary:

- `SubscriptionProvider`: `apple` or `revolut`.
- `EntitlementTier`: `free` or `premium`.
- `PremiumEntitlementResolver`: resolves all active provider grants for a user.
- `AppleTransactionStore`: records verified Apple transactions and renewal state.
- `AppleNotificationStore`: provides notification idempotency and audit evidence.
- Existing Revolut services remain the web payment adapter.

The existing subscription schema may retain Revolut-specific columns during transition, but new entitlement code must not assume that every subscription has a Revolut identifier.

Apple transaction records include:

- Unique transaction identifier.
- Original transaction identifier.
- Fynla user and `appAccountToken`.
- Product identifier.
- Environment.
- Purchase, expiry and revocation times.
- Ownership and transaction reasons where supplied.
- Signed-payload hash for audit and duplicate detection.
- Server-received and last-reconciled times.

Raw signed payload retention follows the documented security and data-retention policy. Logs contain identifiers and hashes, not full payloads.

### 8.5 Server verification

Laravel verifies signed Apple transaction and renewal data using Apple's supported App Store Server Library or an equivalently complete JWS verifier. Verification must include:

- Certificate-chain and signature verification.
- Bundle identifier.
- Product allowlist.
- Environment.
- Application account token.
- Transaction and original-transaction consistency.
- Purchase, expiry and revocation dates.
- Idempotency on transaction and notification identifiers.

The server can use the App Store Server API to obtain transaction history and current subscription status when local state is missing or disputed.

### 8.6 App Store Server Notifications V2

Separate sandbox and production notification URLs feed one verified handler. It processes at least:

- New subscription.
- Renewal.
- Renewal-status change.
- Billing retry.
- Billing Grace Period.
- Expiry.
- Refund.
- Refund reversal.
- Revocation.
- Product change.
- Test notification.

The endpoint acknowledges a verified duplicate without replaying entitlement side effects. An invalid signature or mismatched bundle/environment changes no customer entitlement.

The server stores notification processing status and supports reconciliation of missed or failed notifications through Apple's notification history and subscription-status APIs.

### 8.7 Restore and management

- The application derives normal access from current entitlements without requiring a Restore button.
- A visible Restore Purchases action calls `AppStore.sync()` and then requests server reconciliation.
- Manage Subscription opens Apple's native subscription management interface.
- Refund requests use Apple's supported flow where appropriate.
- An Apple-billed user is not sent to the Revolut cancellation endpoint.
- A Revolut-billed Premium user sees Premium access and web-provider management information without an in-app external purchase call to action.

### 8.8 Cross-provider rules

- One user receives one canonical Premium entitlement even if provider events overlap.
- A current Premium grant suppresses other purchase calls to action.
- Web Premium is recognised in the native app.
- Apple Premium is recognised on desktop and `/m` through the shared resolver.
- Cancellation disables renewal but preserves access through the verified paid period.
- Billing Grace Period follows the verified Apple status and the approved Fynla entitlement policy.
- Expiry or revocation returns the user to Free without deleting financial data.
- Account deletion does not silently imply Apple subscription cancellation.

### 8.9 Native billing API boundary

The implementation uses explicit native billing routes:

- `GET /api/v1/native/entitlement`: return the canonical Free or Premium entitlement, provider, capability matrix and verified period state.
- `GET /api/v1/native/storekit/account-token`: return the authenticated user's stable application account UUID.
- `POST /api/v1/native/storekit/transactions`: submit a locally verified signed transaction for durable server verification and reconciliation.
- `POST /api/v1/native/storekit/reconcile`: request reconciliation after `AppStore.sync()` or a disputed local state.
- `GET /api/v1/native/storekit/status`: return Apple-specific subscription management state without making it the capability authority.
- `POST /api/webhooks/apple/v2`: receive signed App Store Server Notifications V2 without Sanctum authentication; Apple signature verification is its authentication boundary.

These routes return the standard API envelope and typed errors. They do not expose App Store private keys, raw server credentials or another user's transaction identifiers.

## 9. Fyn native design

### 9.1 One server-owned Fyn

The native client uses the canonical conversation endpoints. It does not contain separate onboarding and advice models, prompts, tool catalogues or client-side mode labels.

The server remains responsible for:

- The onboarding-state predicate.
- Advice write-tool stripping or GroundGate enforcement.
- `delegate_to_capture` handoff.
- Persistent writes.
- Recommendation, tax and module engines.
- Conversation history and resumption.

### 9.2 Conversation state

The Fyn feature owns:

- Current conversation identifier.
- Persisted transcript loaded from the server.
- One active send task.
- Queued-message state.
- Streaming assistant message state.
- Bubble replies.
- Tool-status presentation.
- Navigation requests.
- Capture confirmations.
- Level-up events.

Client-generated temporary identifiers are presentation-only and are reconciled with server message identifiers.

### 9.3 Streaming invariants

- A single user gesture creates at most one message request.
- Repeated taps are disabled while submission is being accepted.
- A network retry does not automatically replay a message POST.
- `done` completes the assistant message but does not close the stream before allowed trailing events are read.
- A `202` queued response is presented as queued, not as failure.
- A queued stream resumes from the server-provided message identifier.
- Capture success is displayed only when the server event proves the write.
- Unknown event types are recorded safely and ignored without terminating known text events.
- Stream errors preserve the user's submitted message and present a retry that creates no duplicate write.

### 9.4 Fyn parity

Native acceptance uses the same scenario contracts as web and `/m` for:

- Starting onboarding.
- Resuming onboarding.
- Bubble selection.
- Free-text capture.
- Advice answers.
- Delegated writes.
- Verify-and-edit navigation.
- Conversation continuation.
- Queued turns.
- Errors and recoverability.
- Gamification events.

## 10. Native feature scope

Version 1 ports the current user-facing `/m` product and adds the native account and subscription requirements.

### 10.1 Foundation slice

- Application shell and launch state.
- Staging and production environments.
- Design tokens.
- API and server-sent events clients.
- Diagnostics and redaction.
- Typed navigation and universal-link routing.
- App-version policy and mandatory-update response.

### 10.2 Account slice

- Registration.
- Registration verification and resend.
- Login verification and resend.
- Multi-factor authentication and recovery code.
- Password reset.
- Deleted-account restoration.
- Native device sessions.
- Face ID setup, lock, unlock and removal.
- Sign-out.

### 10.3 Subscription slice

- Free and Premium status.
- Premium monthly and annual purchase.
- Restore Purchases.
- Manage Apple Subscription.
- Cross-provider Premium recognition.
- Billing and entitlement states.
- Account-deletion billing warning.

### 10.4 Core experience slice

- Dashboard.
- Approved Level wheel, action progress and percentile.
- Focus areas and recommendations.
- Fyn insight.
- Navigation menu.
- Full native Fyn conversation surface.
- Onboarding verification actions.
- Achievements and activity history.

### 10.5 Financial slices

- Income.
- Expenditure.
- Net Worth and category detail.
- Protection and policy detail.
- Savings and account detail.
- Investment and account detail.
- Retirement and pension detail.
- Estate Planning.
- Goals.
- Tax Strategy.
- Holistic Plan.

Each feature handles loading, populated, empty, unavailable, validation, offline, authentication-expired, upgrade-required and server-error states before it is marked complete.

### 10.6 Settings and platform slice

- Account and profile entry points required by the native scope.
- Subscription management.
- Privacy and consent controls.
- Data export.
- Account deletion.
- Push-device registration and preferences.
- Universal links.
- Native share sheet.
- Bug reporting.
- Accessibility labels and Dynamic Type verification.

Admin remains outside the native application. An administrator uses the desktop web application.

## 11. Data flow and error handling

### 11.1 Read flow

```text
SwiftUI view
  -> feature model
  -> API client
  -> Laravel endpoint
  -> server service or agent
  -> typed response DTO
  -> feature view state
  -> SwiftUI rendering
```

Financial output is displayed from server responses. Swift may calculate presentation-only values such as safe progress-bar width from an explicit server percentage, but it must not independently calculate tax, adequacy, entitlement or financial recommendations.

### 11.2 Write flow

```text
User action
  -> local form validation
  -> authenticated API request
  -> server validation and transaction
  -> canonical response
  -> refetch affected summary
  -> render confirmed state
```

A local optimistic update is allowed only for reversible presentation state. Financial record creation, deletion, subscription and Fyn capture success require server confirmation.

### 11.3 Error model

The API layer exposes typed categories:

- `validation`: display server field messages.
- `unauthenticated`: attempt one safe refresh, then sign out.
- `forbidden`: display access wording without retry loops.
- `upgradeRequired`: show canonical Free/Premium explanation and native subscription entry point.
- `rateLimited`: respect `Retry-After` and prevent repeated submission.
- `conflict`: refetch canonical state before allowing another write.
- `offline`: preserve unsent form input but do not claim it was saved.
- `server`: provide a safe retry and bug-report correlation identifier.
- `decoding`: fail visibly, capture redacted schema diagnostics and do not substitute fabricated zero values.

No catch block may convert an unknown financial failure into a valid-looking empty or zero state.

### 11.4 Cache policy

- Authentication credentials use Keychain and memory only.
- Financial payloads remain in memory for the active application process.
- Endpoint ETags may be retained in memory and used for conditional requests.
- Images and public static assets may use normal URL caching.
- Version 1 does not provide offline financial edits or an offline financial database.
- App termination clears in-memory financial state.

## 12. `/m` preservation and compatibility contract

### 12.1 Permanent support

The native migration must not:

- Delete or redirect `/m` application routes.
- Remove `resources/mobile/`.
- Remove `vite.mobile.config.js` or the `/m` build from environment build scripts.
- Replace `/m` bearer authentication with a native-only session requirement.
- Remove `/m` Fyn typed events or mobile dashboard fields.
- Require a native application to complete a mobile-web journey.
- Stop verifying user-facing web changes on `/m`.

### 12.2 Additive API evolution

- Existing `/api` and `/api/v1/mobile` response fields remain compatible.
- New native-only session routes live under `/api/v1/native`.
- Shared response additions are optional to older clients until all deployed clients understand them.
- A field is not renamed or retyped in place.
- Native requests send client platform and semantic version headers.
- The server can reject an unsupported native application version with a typed upgrade response without affecting web or `/m`.
- Every breaking change requires a new versioned endpoint and a documented client migration window.

### 12.3 Three-surface parity ledger

The implementation plan maintains a ledger with one row per user capability:

| Capability | Desktop web | `/m` mobile web | Native iPhone | Shared backend | Evidence |
|---|---|---|---|---|---|
| Example: Fyn message | required | required | required after slice lands | one endpoint | tests and browser/device proof |

Rules:

- Desktop and `/m` remain required for every repository-scoped user-facing change unless CSJ explicitly excludes a surface.
- Native is required once the corresponding native slice is marked shipped.
- A native-only platform feature such as Face ID or StoreKit is marked not applicable on web surfaces, with its shared entitlement outcome still verified on web and `/m`.
- `/m` is not treated as a temporary oracle to delete after native release.

### 12.4 Universal-link relationship

- Phone browser visits continue through the existing `/m` detection and routing.
- Universal links opened by iOS can enter the installed native application when the destination is supported.
- If the native application is absent, the web URL continues to load and phone detection reaches `/m`.
- The native app does not embed `/m` as a fallback.
- Unsupported authenticated admin links open the external browser and remain desktop-oriented.

## 13. Testing strategy

### 13.1 Swift unit tests

Unit tests cover:

- API envelope and financial DTO decoding fixtures.
- Error mapping.
- Session-state transitions.
- Refresh rotation and reuse handling.
- Keychain abstraction behaviour.
- Face ID success, cancellation, lockout and biometric-change outcomes through injected LocalAuthentication adapters.
- Typed routing and universal-link parsing.
- Incremental server-sent events parsing at every byte boundary.
- `202` queued reply handling and post-`done` events.
- StoreKit product and entitlement state mapping.
- Localized currency and date presentation.

### 13.2 Backend contract tests

Laravel feature tests cover:

- Existing web and `/m` authentication remaining unchanged.
- Native session exchange, rotation, expiry, revocation and replay detection.
- Native session authorization boundaries.
- Application account token issue and account ownership.
- Apple JWS verification success and failure.
- Product, bundle and environment mismatch rejection.
- Transaction and notification idempotency.
- Subscription renew, retry, grace, expiry, refund and revocation transitions.
- Cross-provider resolution.
- Free/Premium capability consistency on desktop, `/m` and native responses.
- Account deletion and Apple billing-warning state.

### 13.3 StoreKit tests

- A committed `.storekit` configuration represents exactly the two Premium products and no trial.
- Xcode StoreKit tests exercise purchase, cancel, renew, expire, refund, restore, interrupted purchase and Ask to Buy pending results.
- Apple sandbox tests verify real signed transactions and App Store Server Notifications V2.
- Production product identifiers and prices receive an App Store Connect preflight before TestFlight release.

### 13.4 Swift UI tests

UI tests use injected deterministic API and StoreKit clients for:

- Registration through verification.
- Login through verification.
- Multi-factor authentication.
- Face ID opt-in and later unlock.
- Dashboard and navigation.
- Fyn streaming, queued turn and error recovery.
- Free-to-Premium purchase.
- Existing web Premium recognition.
- Restore Purchases.
- Account deletion.
- Every financial screen's populated, empty and error states.
- Dynamic Type and core accessibility identifiers.

The automated device matrix includes an iPhone 11 simulator on the oldest supported iOS runtime available to the release Xcode version and a current Face ID iPhone simulator on the latest supported runtime.

### 13.5 `/m` and web regression

Every shared backend work package runs:

- Relevant Pest tests.
- Relevant Vitest tests.
- Desktop browser verification.
- `/m` verification using the repository `verify-m` workflow.
- Native unit or UI tests for already-landed native slices.

No backend change is accepted solely because the native client works.

### 13.6 Real-device and TestFlight gates

Simulator tests cannot prove Face ID, push delivery, universal links, Keychain behaviour across installations, application switching or real App Store sandbox behaviour. Before external TestFlight:

- Test on at least one physical iPhone 11-family device and one current Face ID iPhone. The iPhone 11 run covers cold launch, dashboard scrolling, Fyn streaming, module navigation, memory pressure, StoreKit presentation and background relock.
- Verify cold launch and background relock.
- Verify network interruption during Fyn streaming and purchase reconciliation.
- Verify sign-out revokes the device session.
- Verify application upgrade from the current Capacitor bundle to the native bundle without treating stale local Capacitor storage as native credentials.
- Verify push token registration and removal.
- Verify production associated domains.

## 14. Delivery and migration gates

### Gate 0: economic contract

- Complete the Free/Premium remediation plan.
- Remove trial behaviour from live registration, middleware, copy and tests.
- Establish the canonical capability and count-cap contract.
- Prove Free and Premium on desktop and `/m`.

**Exit:** StoreKit can map to one canonical `premium` entitlement without legacy tier ambiguity.

### Gate 1: native foundation

- Create the new SwiftUI project and schemes.
- Establish build settings, design tokens, API client, diagnostics and test targets.
- Add CI `xcodebuild` lanes that do not require signing.
- Add the parity ledger.

**Exit:** a signed development build launches on an iPhone and deterministic unit tests pass.

### Gate 2: account and Face ID

- Implement complete registration and login branches.
- Add native device sessions.
- Add Keychain and Face ID.
- Add account restoration and password reset.

**Exit:** first login, later Face ID unlock, revocation, expiry and full-login fallback pass on a real device without changing `/m`.

### Gate 3: StoreKit and shared entitlement

- Add provider-neutral entitlement resolution.
- Add StoreKit products and client.
- Add server transaction verification.
- Add App Store Server Notifications V2 and reconciliation.
- Add restore and subscription management.

**Exit:** Apple sandbox monthly and annual subscriptions grant and remove the same Premium capabilities observed on native, desktop and `/m`.

### Gate 4: dashboard and Fyn vertical slice

- Port the native shell, dashboard, approved gamification and Fyn.
- Complete typed stream parity including onboarding and delegated writes.
- Add bug reporting and core accessibility.

**Exit:** a new user can register, unlock, onboard through Fyn, reach the dashboard, ask advice and return later through Face ID.

### Gate 5: financial feature waves

Port independent feature slices in bounded waves:

1. Income, Expenditure and Net Worth.
2. Savings and Investments.
3. Retirement and Protection.
4. Estate Planning and Goals.
5. Tax Strategy and Holistic Plan.
6. Achievements, settings, privacy, push and universal links.

Each wave closes unit, contract, UI, real-device and web-plus-`/m` regression evidence before the next wave is declared complete.

### Gate 6: TestFlight release candidate

- Archive with the production bundle identifier and accepted Xcode/SDK.
- Run static analysis and release build tests.
- Upload internal TestFlight.
- Verify account, Face ID, StoreKit sandbox, Fyn and all module pathways.
- Complete App Privacy, privacy manifest, subscription metadata, screenshots and review notes.
- Expand to external TestFlight only after internal acceptance.

**Exit:** CSJ approves the exact native build for App Store submission.

### Gate 7: App Store cutover

- Retain the last accepted Capacitor archive and source commit as rollback evidence.
- Submit the native binary as the next version of `org.fynla.app`.
- Keep `/m` and the web release independent throughout review.
- Monitor native session, StoreKit notification, entitlement, crash and API error telemetry after release.

**Exit:** the native application is live, Premium is reconciled correctly, `/m` remains green, and no rollback trigger fires.

## 15. Rollback design

- A native feature flag can disable purchase entry points without removing existing entitlements.
- Server-native session endpoints can be disabled for new exchanges while existing web and `/m` authentication remains available.
- Apple notifications continue to be accepted during a client rollback so billing state is not lost.
- The previous Capacitor source and signed archive remain reproducible until the native release passes its post-release review.
- A client rollback never rolls back provider transaction records or deletes entitlement audit history.
- `/m` remains the supported phone-browser route regardless of native release state.

Rollback triggers include:

- Incorrect Premium grants or removals.
- Unverifiable Apple notifications or transaction signatures.
- Repeatable authentication or Face ID lockout without full-login recovery.
- Financial data exposure in local storage or logs.
- Fyn write claims without persisted writes.
- A shared backend regression on desktop or `/m`.
- A crash or navigation defect blocking account deletion or subscription management.

## 16. App Store and operational requirements

Before submission:

- App Store Connect contains one subscription group and the two approved products.
- The Account Holder has accepted current paid-app agreements and configured the payout bank account.
- Apple remits proceeds to the payout bank account configured in App Store Connect. Using Fynla's Revolut account for that payout is an operational App Store Connect banking configuration, not a StoreKit payment route.
- Application metadata explains the Free and Premium model clearly.
- Subscription screens disclose title, duration, localized price, renewal and management terms.
- The application contains Restore Purchases and Manage Subscription.
- Account deletion is easy to find and can be initiated in-app.
- Deletion copy explains that Apple billing continues until cancelled and offers Apple's management interface.
- Privacy nutrition labels and `PrivacyInfo.xcprivacy` match actual collection and third-party SDK behaviour.
- Export-compliance answers match the application's network encryption use.
- Review notes provide a working review account, verification path, Premium test instructions and Fyn explanation.
- The server has separate sandbox and production Apple notification URLs and monitored failure queues.
- Customer support can trace a StoreKit transaction without seeing payment-card data.

## 17. Explicit version 1 exclusions

- Native iPad layouts or iPad-specific navigation.
- Android.
- Apple Watch, widgets, Live Activities or App Clips.
- Sign in with Apple unless a separate social-login decision introduces it.
- Offline financial writes or conflict resolution.
- A local financial database.
- Native administrator screens.
- New financial calculations or recommendations implemented in Swift.
- A broad desktop or `/m` redesign.
- A new native navigation system that differs materially from the approved `/m` information architecture.
- Family Sharing, free trials, introductory offers, promotional offers or offer codes.
- External payment links inside the iPhone application.
- Replacement or retirement of `/m`.

## 18. Primary risks and controls

| Risk | Control |
|---|---|
| Swift duplicates financial logic and diverges | Server remains authoritative; DTO and contract tests prohibit client-owned calculations |
| Apple and Revolut grant different access | One provider-neutral resolver and three-surface entitlement tests |
| A purchase is unlocked before verification | Server acknowledgement required before transaction finish and Premium refresh |
| StoreKit notification is replayed | Unique notification and transaction identifiers plus idempotent processing |
| Face ID becomes a permanent bearer-token vault | Short access token, rotating refresh family, Keychain biometric protection and real revocation |
| `/m` degrades during native work | Additive APIs, explicit parity ledger and mandatory `/m` regression |
| Fyn streaming loses typed events | Byte-boundary parser tests, `202` handling and post-`done` event tests |
| Native work becomes one unreviewable rewrite | Vertical feature slices with independent exit evidence |
| iPad work leaks into version 1 | iPhone target family only and iPad listed as version 2 |
| New SwiftUI work performs poorly on iPhone 11 | iPhone 11 hardware baseline, simulator lane, physical-device gate and no newer-hardware-only dependency |
| App Review rejects registration or billing | Native registration, StoreKit, restore, management and in-app deletion preflight |
| Sensitive data is cached or logged | Ephemeral private requests, no local financial database and redacted diagnostics |
| Existing Capacitor users receive a broken upgrade | Explicit upgrade test and no trust in old web storage |

## 19. Acceptance definition

The native programme is complete only when:

- The exact Free and Premium economic contract is live and tested.
- `/m` remains fully functional and deployable.
- The native application is a SwiftUI client with no embedded Fynla web view.
- The complete scoped application passes on an iPhone 11-family device without a newer-hardware requirement.
- iPhone registration, verification, login, multi-factor authentication and restoration work end to end.
- Face ID protects a revocable native session and has a normal-login recovery path.
- Monthly and annual Apple sandbox purchases reconcile to server Premium.
- Apple renewal, retry, grace, expiry, refund and revocation states are handled idempotently.
- Revolut Premium is recognised natively and Apple Premium is recognised on web and `/m`.
- Every scoped `/m` screen has an accepted native equivalent.
- Fyn onboarding, advice, delegated writes, queued turns and gamification events satisfy shared acceptance scenarios.
- In-app data export and account deletion work.
- Unit, contract, StoreKit, UI, real-device, desktop-browser and `/m` gates are green.
- CSJ approves the exact TestFlight build submitted to Apple.
- iPad remains explicitly scheduled for version 2 rather than implicitly unsupported.

## 20. Implementation-plan decomposition

This programme is intentionally too large for one implementation plan. After CSJ approves this written specification, the writing-plans phase will create a programme index and bounded plans in dependency order:

1. Economic contract prerequisite and native API compatibility gate.
2. SwiftUI project foundation, build schemes, API client and test infrastructure.
3. Registration, complete authentication, native device sessions and Face ID.
4. StoreKit client, Apple server integration and provider-neutral entitlements.
5. Dashboard, navigation, approved gamification and Fyn vertical slice.
6. Financial feature waves, each with its own small execution plan and parity rows.
7. Settings, privacy, account deletion, push, universal links and App Store release.

Each child plan names exact files, tests, commands and acceptance evidence. No child plan may begin past a dependency gate merely because its visual client code can be mocked.

## 21. Primary source references

- Apple App Review Guidelines: <https://developer.apple.com/app-store/review/guidelines/>
- Apple submission requirements: <https://developer.apple.com/app-store/submitting/>
- StoreKit 2: <https://developer.apple.com/storekit/>
- StoreKit application account token: <https://developer.apple.com/documentation/storekit/product/purchaseoption/appaccounttoken(_:)>
- App Store Server API: <https://developer.apple.com/documentation/appstoreserverapi>
- App Store Server Notifications: <https://developer.apple.com/documentation/appstoreservernotifications>
- Apple account deletion guidance: <https://developer.apple.com/support/offering-account-deletion-in-your-app>
- Keychain biometric current set: <https://developer.apple.com/documentation/security/secaccesscontrolcreateflags/biometrycurrentset>
- SwiftUI Observation: <https://developer.apple.com/documentation/swiftui/managing-model-data-in-your-app>
- Apple subscription configuration: <https://developer.apple.com/help/app-store-connect/manage-subscriptions/offer-auto-renewable-subscriptions/>
- Apple iOS 26 compatible iPhone models: <https://support.apple.com/guide/iphone/iphone-models-compatible-with-ios-26-iphe3fa5df43/26>
