# iOS bugs — August 2026

One file per bug: `BUG-NN-short-slug.md`. Numbering is sequential within this
folder and does not restart.

Environment was verified end-to-end on 2026-08-17 before this folder opened —
see `../ios-environment-check-2026-08-17.md`. Every command below is one that
actually ran, not one copied from a doc.

## Filing template

```markdown
---
id: BUG-NN
raised: 2026-08-DD
surface: native | /m | web | all
scheme: Fynla-Staging | Fynla-Production
severity: blocker | major | minor
status: open | fixed | wontfix | not-a-bug
fixed_in: <commit sha, once fixed>
testflight_build: <N, once shipped>
---

## Symptom
What the user sees. Exact wording of any error.

## Reproduction
Numbered steps from a known start state. Name the account used.

## Evidence
Screenshot paths (`screenshots/YYYY-MM/`), log lines, DB rows, HTTP status +
content-type. Not "it looked wrong" — the actual observation.

## Root cause
file:line. If unknown, say unknown; do not speculate.

## Fix
What changed and why that is the cause, not a symptom.

## Verification
The simulator run that proves it. Rule 14: diagnose → fix → verify in the
running app → repeat until green.
```

## The verification loop

Backend must be up (`./dev.sh`) if the bug is server-side. The staging scheme
talks to **csjones**, not localhost — see the backend note below.

```bash
# 1. Boot (the named device, not a generic one)
xcrun simctl boot D155EBAD-2317-4E99-9C6D-7475F971B091   # Fynla iPhone SE iOS 17.5
open -a Simulator

# 2. Build
cd ios-native
xcodebuild -project Fynla.xcodeproj -scheme Fynla-Staging \
  -destination 'platform=iOS Simulator,id=D155EBAD-2317-4E99-9C6D-7475F971B091' \
  -derivedDataPath <scratch>/dd build

# 3. Install + launch
xcrun simctl install D155EBAD-2317-4E99-9C6D-7475F971B091 \
  <scratch>/dd/Build/Products/Debug-iphonesimulator/Fynla.app
xcrun simctl launch D155EBAD-2317-4E99-9C6D-7475F971B091 org.fynla.app.dev

# 4. Evidence
xcrun simctl io D155EBAD-2317-4E99-9C6D-7475F971B091 screenshot \
  screenshots/2026-08/<bug-id>-<state>.png
```

Screenshots go in `screenshots/YYYY-MM/` — never the repo root (GATE-0003).

## Simulators

| Device | Runtime | UDID |
|---|---|---|
| Fynla iPhone SE | iOS 17.5 | `D155EBAD-2317-4E99-9C6D-7475F971B091` |
| Fynla iPhone 16 Pro | iOS 18.6 | `B880080D-37ED-453E-A87E-3DE049902ECA` |

iOS 17.5 is the floor the app targets, so reproduce there first. Also available:
iPhone 15/15 Pro/15 Pro Max and iPad Pro (17.5), iPhone 16 Pro Max/16e (18.6),
iPhone 17 Pro/Pro Max/17e (26.3).

## Two traps that have already cost a day each

**The staging app reads the csjones database.** `Staging.xcconfig` hard-wires
`FYNLA_API_BASE_URL = https://csjones.co/fynla`. An account created on
**fynla.org** does not exist there — login returns 401 with audit
`reason: user_not_found`, which the UI renders as "Invalid email or password".
Register test accounts on **csjones.co/fynla**. (Cost 2026-08-13.)

**Production has no native endpoints.** Probe
`GET /api/v1/native/health` — re-verified 2026-08-17:

```
csjones  -> 400 application/json   route exists (400 = missing native headers)
fynla.org -> 200 text/html          SPA fallback = route ABSENT
```

So a `Fynla-Production` build clears `/api/auth/login` then 404s at
`/api/v1/native/auth/session/exchange`. Do not file that as a bug — it is a
`dev → main` release, and `main` is currently **719 commits behind `dev`**.

## Shipping a fix

`CURRENT_PROJECT_VERSION` is **6** (TestFlight build 6, 2026-08-12), so the next
upload is **7**. Build numbers must be unique per version per app record.
Procedure: `ios-native/TESTFLIGHT.md`.
