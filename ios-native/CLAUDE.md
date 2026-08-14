# Native iOS Conventions (SwiftUI)

Supplements root `CLAUDE.md`. **Read the Mobile Clients section there first** — especially the staging-backend warning, which is the single most common cause of "the app is broken" reports.

> **GOLDEN RULE #20 (CSJ, NEVER IGNORE):** every Fyn change — prompt, vocabulary, behaviour, rendering — is made ONCE, in ONE place, for ALL surfaces and paths. That includes this app. A Fyn fix landed only in `resources/mobile/` is not done. Full text: root `CLAUDE.md` Rule 20.

## Environment — read this before debugging any login or data problem

`AppEnvironment` (`Fynla/App/AppEnvironment.swift`) reads `FYNLA_ENVIRONMENT`, `FYNLA_API_BASE_URL` and `FYNLA_WEB_BASE_URL` from the Info.plist, fed by `Configurations/{Staging,Production}.xcconfig`.

| Scheme | Backend | Bundle ID |
|---|---|---|
| `Fynla-Staging` | `https://csjones.co/fynla` | `org.fynla.app.dev` |
| `Fynla-Production` | `https://fynla.org` | `org.fynla.app` |

**The TestFlight build is `Fynla-Staging` and reads the csjones database.** Both schemes install as an app named "Fynla" (`CFBundleDisplayName` is the same in every configuration) and nothing in the UI names the backend, so the two are indistinguishable on a phone. An account registered on fynla.org does not exist in staging: login returns 401, audit `reason: user_not_found`, UI shows "Invalid email or password". **Testers register on csjones.co/fynla.**

`Fynla-Production` cannot complete login today — production has no `/api/v1/native/*` routes. Shipping `dev → main` is the fix; nothing in this directory changes it.

URLs must be HTTPS with no user-info — `AppEnvironment.validatedURL` throws otherwise. `Local.xcconfig` holds `DEVELOPMENT_TEAM` and is gitignored; create it once per machine.

## Layering

```
Core/API              APIClient, HTTPTransport, APIEnvelope, TolerantDecoding, APIError
Core/Authentication   AuthClient protocols, APIAuthClient (actor), AuthenticationCoordinator
Core/Keychain         credential storage
Core/Biometrics       PrivacyLockController, LocalAuthenticationClient
Core/StoreKit         subscriptions + entitlements
Core/DesignSystem     FynlaColor, FynlaTypography, FynlaSpacing, FynlaButton
Features/{Area}/      one View + one Model per screen
```

- Networking goes through `APIClient` / `APIAuthClient` — never `URLSession` in a view.
- Decode with `TolerantDecoding`. The backend adds fields freely; a strict decoder turns an additive API change into a crash.
- Auth endpoints are `api/auth/*` (shared with web and `/m`) plus `api/v1/native/auth/session/*` (native only, behind `native.client` / `native.version` / `native.session` middleware).

## Parity with `/m`

Every screen must match `/m` on **detail, functionality, states, intent and design**. Ledger: `codex/plans/ios/2026-07-20-native-m-parity-ledger.md`. When `/m` changes, this app changes in the same PR — that is Rule 19 plus Rule 20, not a nice-to-have.

## Design rules

Rules 8 (no amber/orange), 10–11 (design system, CSS governance), 12 (no scores) and 15 (icons) apply here exactly as on web.

- The **Fyn avatar is always allowed**, everywhere, at any size (Rule 15).
- The `/m` dashboard gamification — level wheel, "X of Y actions complete", "ahead of X% of people" — is **approved by design**. Never strip it or flag it in an audit (Rule 12 carve-out).

## Testing and release

- `FynlaTests` — unit tests.
- `FynlaUITests` — includes `LiveJourneyTests`, which drives a real backend. Env vars must carry the `TEST_RUNNER_` prefix to reach the UI-test runner.
- `scripts/verify-project.sh` asserts the Info.plist/xcconfig wiring — run it after touching any build configuration.
- Release: `ios-native/TESTFLIGHT.md`.

**Known and never to be chased:** 6 StoreKit hosted-configuration unit tests are red locally and green in CI.
