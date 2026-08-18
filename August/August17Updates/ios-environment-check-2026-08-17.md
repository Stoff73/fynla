---
type: environment-verification
date: 2026-08-17
scope: Xcode, simulators, signing, App Store Connect, staging backend
verdict: GREEN for build → simulator → verify. Archive → upload untested by design.
---

# iOS environment check — 17 August 2026

Run before opening the iOS debugging session. Every line below is an observed
result, not a doc citation.

## Verdict

**The diagnose → fix → verify loop works today.** Built `Fynla-Staging`, installed
it on a booted simulator, launched it, and captured the sign-in screen rendering.
Screenshot: `screenshots/2026-08/env-check-sim-launch.png`.

**The upload half is verified as far as it can be without consuming a build
number.** Certificate, provisioning profile, API key and both app records all
check out; the archive-and-upload itself has not been run, because doing so
publishes to real TestFlight and burns build 7. That is CSJ's call, not a
health check.

## Toolchain

| Check | Result |
|---|---|
| Xcode | **26.3** (17C529) |
| `xcode-select -p` | `/Applications/Xcode.app/Contents/Developer` |
| First-launch status | OK — no pending license or component install |
| Schemes | `Fynla-Staging`, `Fynla-Production` (both present) |
| Configurations | Debug, UITesting, Staging, Release, Production |
| Targets | Fynla, FynlaTests, FynlaUITests |
| `Configurations/Local.xcconfig` | present, carries `DEVELOPMENT_TEAM` (gitignored, as intended) |

## Simulators

**The 23 July wedge is gone.** `project_ios_sim_wedged_2026-07-23` recorded
CoreSimulator hung host-wide with `LiveJourneyTests` staged for re-run after a
reboot. `Fynla iPhone SE iOS 17.5` booted first try and stayed up.

Runtimes available: **iOS 17.5, 18.6, 26.3**. (iOS 15.0 is present but
unavailable — unsupported on this macOS, harmless.)

Named devices, both pre-existing:

| Device | Runtime | UDID |
|---|---|---|
| Fynla iPhone SE | 17.5 | `D155EBAD-2317-4E99-9C6D-7475F971B091` |
| Fynla iPhone 16 Pro | 18.6 | `B880080D-37ED-453E-A87E-3DE049902ECA` |

## The loop, proven

```
xcodebuild -scheme Fynla-Staging -destination '...id=D155EBAD...' build
  → ** BUILD SUCCEEDED **        (exit 0)

xcrun simctl install D155EBAD... Fynla.app
  → INSTALLED

xcrun simctl launch D155EBAD... org.fynla.app.dev
  → org.fynla.app.dev: 96847

xcrun simctl spawn D155EBAD... launchctl list | grep fynla
  → 96847  0  UIKitApplication:org.fynla.app.dev[cc68][rb-legacy]
```

Still resident after launch, so it rendered rather than crashed — confirmed
visually by the screenshot.

## Signing

```
security find-identity -v -p codesigning
  1) Apple Distribution: CHRISTOPHER JOHN SLATER-JONES (99S3M8JLLF)
  2) Apple Development:  c.jones@csjones.co (2L85795N39)
     2 valid identities found
```

Provisioning profile: **Fynla Dev App Store**, expires **2027-07-21** — 11
months of headroom, no renewal pressure.

Team `99S3M8JLLF` matches the `teamID` in `TESTFLIGHT.md`'s export options.

## App Store Connect

Key `AuthKey_683FKHT7SL.p8` present at
`~/.appstoreconnect/private_keys/`, matching the recorded pipeline (key
`683FKHT7SL`, issuer `8fad68f9-…62d60`).

Authenticated read-only call succeeded — `xcrun altool --list-apps` returned
both records:

| App | Bundle ID | ASC ID | State |
|---|---|---|---|
| Fynla Dev | `org.fynla.app.dev` | 6793193337 | PREPARE_FOR_SUBMISSION, v1.0 |
| Fynla | `org.fynla.app` | — | the legacy production record |

So the credential chain reaches App Store Connect and can see the target app.
The App Manager role limitation still stands: it can archive with
`-allowProvisioningUpdates` but cannot mint distribution certificates via
Xcode cloud signing. Irrelevant here — a real distribution identity is already
installed locally (identity 1 above).

## Backend the app talks to

`Staging.xcconfig`: `FYNLA_API_BASE_URL = https://csjones.co/fynla`,
`FYNLA_ENVIRONMENT = staging`, `ASSOCIATED_DOMAIN = csjones.co`,
`APS_ENVIRONMENT = development`, bundle `org.fynla.app.dev`.

Re-probed both environments today:

```
csjones.co/fynla/api/v1/native/health -> 400 application/json   route present
fynla.org/api/v1/native/health        -> 200 text/html           route ABSENT (SPA fallback)
```

Unchanged from the 13 August diagnosis. **Test accounts must be registered on
csjones.co/fynla.** A `Fynla-Production` build still cannot log in, and that is
a release problem — `main` is 719 commits behind `dev`.

## Build numbers

`MARKETING_VERSION = 1.0`, `CURRENT_PROJECT_VERSION = 6`. TestFlight build 6
shipped 2026-08-12, so **the next upload must be 7**.

## Gaps — stated, not papered over

1. **Archive → export → upload not executed.** Every prerequisite verified; the
   run itself would publish to TestFlight and consume build 7. Needs CSJ's go.
2. **`FynlaTests` / `FynlaUITests` not run.** The simulator is healthy now, so
   `LiveJourneyTests` and the `FynEventReducerTests` that the 23 July wedge
   blocked are finally runnable — including the pair from
   `codex/ios-package7-platform-release` merged in earlier today, which has
   never been tested. Not run yet: it is work, not a health check.
3. **No physical device checked** — simulator only.
4. **StoreKit hosted-config tests** are known-red locally and green in CI
   (`ios-native/CLAUDE.md`). Not investigated; do not chase them.
