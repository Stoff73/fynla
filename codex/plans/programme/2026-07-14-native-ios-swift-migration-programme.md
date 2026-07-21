# Native iPhone Swift Migration Programme

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement the child plans task-by-task. Use `superpowers:test-driven-development` for every implementation task, `security-and-hardening` for authentication, payment, privacy and external-integration tasks, `systematic-debugging` for every failure, `verification-before-completion` before closing a gate, and `verify-m` whenever a shared backend or `/m` contract changes. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a native SwiftUI iPhone application that ports the current `/m` experience, supports native registration, Face ID and StoreKit Premium, and leaves `/m` permanently intact.

**Architecture:** Desktop Vue, `/m` Vue and native SwiftUI are independent clients of the same versioned Laravel platform. Laravel remains authoritative for authentication, entitlements, Fyn, all financial calculations and every persistent write. Native-only contracts are additive under `/api/v1/native`; existing `/api` and `/api/v1/mobile` contracts remain backwards compatible.

**Tech Stack:** Swift 6, SwiftUI, Observation, URLSession, LocalAuthentication, Security/Keychain, StoreKit 2, XCTest, Swift Testing, Xcode 26.3 or later, iOS 17 minimum; Laravel 10, PHP 8, MySQL 8 and Pest for shared server contracts.

**Approved specification:** `codex/plans/programme/2026-07-14-native-ios-swift-migration-design.md`

**Economic prerequisite:** `codex/plans/programme/2026-07-14-freemium-economic-contract-remediation.md`

## Global Constraints

- Version 1 is iPhone-only, portrait-only and guarantees the iPhone 11, iPhone 11 Pro and iPhone 11 Pro Max as its oldest hardware baseline.
- iPad interface support is version 2. Do not set the version 1 target to universal or add iPad navigation compromises.
- Keep `resources/mobile/`, `/m`, `vite.mobile.config.js`, `ios/App/` and `deploy/mobile/build-ios.sh` intact. The native project is additive under `ios-native/`.
- Do not place a Fynla `WKWebView` in the native application.
- New accounts start on permanent Free. Premium is £6.99 monthly or £59.99 annually. There is no trial, introductory offer or Family Sharing.
- Swift never calculates tax, recommendations, limits, entitlement or financial outcomes. It renders server-owned values and performs presentation-only formatting.
- The native client uses the one canonical Fyn endpoint and never models onboarding and advice as different personas.
- Private financial responses stay in memory in version 1. No local financial database or offline write queue is introduced.
- No access token, refresh credential, verification code, signed StoreKit payload, App Store key or raw financial response appears in logs.
- No new decorative icons, emoji, financial-quality scores, hardcoded non-palette colours or unexplained acronyms appear in user-facing UI.
- Every shared backend change must pass desktop web and `/m` regression evidence. Native is an additional gate once its corresponding slice lands.
- Never run `migrate:fresh` or `migrate:refresh`. After a migration in a real environment, run `php artisan db:seed` as required by the repository rules.
- Do not stage, commit, push, merge or deploy work without the user's explicit instruction. The commit lines in child plans describe intended review boundaries.

## Dependency graph

```text
Package 1: economic/API readiness
    -> Package 2: SwiftUI foundation
        -> Package 3: account + Face ID
            -> Package 4: StoreKit + shared entitlements
                -> Package 5: dashboard + navigation + Fyn
                    -> Package 6: financial waves
                        -> Package 7: platform completion + release
```

Package 2 can begin locally after Package 1's response contracts are frozen, but no StoreKit production work may bypass Package 1. Package 5 requires Package 3 because Fyn and dashboard data are authenticated. Package 7 is the only plan that replaces the production Capacitor binary, and only after all preceding gates pass.

## Child plans

| Package | Plan | Exit gate |
|---|---|---|
| 1 | `codex/plans/ios/2026-07-14-ios-01-economic-api-readiness.md` | Free/Premium is canonical and existing web/`/m` contracts are frozen by tests |
| 2 | `codex/plans/ios/2026-07-14-ios-02-swiftui-foundation.md` | Native shell, environments, API/SSE clients and CI tests pass on iPhone 11 simulator |
| 3 | `codex/plans/ios/2026-07-14-ios-03-native-auth-face-id.md` | Registration, all login branches, native sessions and real-device Face ID pass |
| 4 | `codex/plans/ios/2026-07-14-ios-04-storekit-entitlements.md` | Monthly/annual Apple sandbox state reconciles across native, desktop and `/m` |
| 5 | `codex/plans/ios/2026-07-14-ios-05-dashboard-fyn.md` | New user can onboard with Fyn and use the native dashboard after Face ID unlock |
| 6 | `codex/plans/ios/2026-07-14-ios-06-financial-feature-waves.md` | Every current `/m` financial screen has an accepted native equivalent |
| 7 | `codex/plans/ios/2026-07-14-ios-07-platform-release.md` | Privacy, deletion, push, links and exact TestFlight release candidate are approved |

## File ownership map

| Area | Canonical responsibility | Owned first by |
|---|---|---|
| `codex/plans/programme/2026-07-14-freemium-economic-contract-remediation.md` | Free/Premium remediation and trial removal | Package 1 prerequisite |
| `docs/architecture/client-parity-ledger.md` | Capability-by-client release evidence | Package 1, updated by all |
| `routes/api.php`, `routes/api_v1.php` | Existing/shared and native versioned routes | Packages 1, 3, 4 |
| `app/Services/Tiers/`, `app/Services/Stores/TierConfigurationStore.php` | Server tier/capability authority | Packages 1 and 4 |
| `app/Services/Auth/`, `app/Http/Controllers/Api/V1/Native/Auth/` | Native rotating session family | Package 3 |
| `app/Services/Billing/`, `app/Http/Controllers/Api/V1/Native/Billing/` | Provider-neutral entitlement and Apple billing | Package 4 |
| `ios-native/Fynla/App/`, `ios-native/Fynla/Core/` | App lifecycle and shared native infrastructure | Package 2 |
| `ios-native/Fynla/Features/Authentication/` | Registration and login user interface | Package 3 |
| `ios-native/Fynla/Features/Subscription/` | StoreKit presentation and account subscription state | Package 4 |
| `ios-native/Fynla/Features/Dashboard/`, `Fyn/`, `Achievements/` | Native core experience | Package 5 |
| Other `ios-native/Fynla/Features/*` financial folders | Native financial presentation | Package 6 |
| `ios-native/Fynla/Features/Settings/`, `Privacy/`, platform delegates | Platform completion | Package 7 |
| `.github/workflows/ios-native.yml` | Unsigned native build/test lane | Package 2, extended by later packages |

## Parity ledger contract

Package 1 creates `docs/architecture/client-parity-ledger.md` with these statuses only:

- `required`: release-blocking on this surface.
- `not-landed`: native work has not reached this slice yet.
- `not-applicable`: platform-specific behaviour with a recorded reason.
- `green`: named automated and manual evidence is linked.

No row may say `green` without a command result and, for user journeys, browser or device evidence. The initial capability rows are:

| Capability | Desktop | `/m` | Native after landing | Backend owner |
|---|---|---|---|---|
| Register and verify | required | required through canonical funnel | Package 3 | AuthController |
| Login, verification and multi-factor authentication | required | required | Package 3 | AuthController/MFAController |
| Free/Premium entitlement | required | required | Package 4 | TierResolver/entitlement resolver |
| Dashboard and gamification | required | required | Package 5 | MobileDashboardAggregator |
| Fyn onboarding/advice/write handoff | required | required | Package 5 | AiChatController |
| Income/expenditure/net worth | required | required | Package 6 Wave A | existing module APIs |
| Savings/investment | required | required | Package 6 Wave B | existing module APIs |
| Retirement/protection | required | required | Package 6 Wave C | existing module APIs |
| Estate/goals | required | required | Package 6 Wave D | existing module APIs |
| Tax Strategy/Holistic Plan | required | required | Package 6 Wave E | existing plan APIs |
| Face ID | not-applicable | not-applicable | Package 3 | native session service |
| StoreKit purchase | not-applicable | not-applicable | Package 4 | Apple billing adapter |
| Account deletion outcome | required | required | Package 7 | GDPRController |

## Programme verification commands

Run narrow commands inside each task. At a package gate, run the package's full list. At the release-candidate gate run:

```bash
./vendor/bin/pest
npm run test
npm run build
npm run build:mobile
xcodebuild -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging -destination 'platform=iOS Simulator,name=iPhone 11' test CODE_SIGNING_ALLOWED=NO
xcodebuild -project ios-native/Fynla.xcodeproj -scheme Fynla-Production -configuration Release -destination 'generic/platform=iOS' archive -archivePath /tmp/Fynla.xcarchive
```

Expected: all commands exit 0; the archive uses `org.fynla.app`, iPhone device family only, iOS 17 minimum, and contains no Capacitor or web-view runtime.

Because the repository uses built `/m` assets on staging, browser evidence must follow the `verify-m` skill and the csjones deployment path; a local cold navigation without its token bridge is not evidence.

## Gate handoff template

Every package closes with a short entry in the parity ledger containing:

```text
Package:
Commit/PR:
Backend tests:
Swift tests:
Desktop browser evidence:
/m browser evidence:
Simulator evidence:
Physical-device evidence:
Known exclusions:
CSJ approval:
```

The next package begins only when every required line for the preceding gate is populated. A visual mock or locally decoded fixture is not a substitute for server, browser, StoreKit sandbox or real-device evidence where the child plan requires it.
