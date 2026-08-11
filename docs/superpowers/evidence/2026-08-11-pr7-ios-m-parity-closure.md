# PR7 iOS and `/m` parity-closure evidence — 11 August 2026

## Current status

The `/m` closure journey is green in the user's installed Google Chrome. The
native closure target compiles and links with Swift warnings as errors, but the
local CoreSimulator host failed before Fynla launched on both a clean current
runtime and a previously known-good runtime. M-01–M-34 therefore remain
`pending-ci`; they may become `green` only after the fresh macOS CI host passes
the full native UI suite, the dedicated PR7 journey at the largest Dynamic Type
size, and the Production build.

## Closure contract

PR7 replaces the obsolete package ledger with one machine-checked M-01–M-34
matrix. Every row maps to a registered Laravel, `/m`, native iOS, installed
Chrome, and PR7 execution-evidence key. The shared rules are explicit:

- Laravel rehydrates existing financial facts from canonical records.
- Clients send identifiers and proposed changes, never authoritative balances.
- Investment accounts, stocks and shares ISAs, pensions, entered portfolios,
  and recommended allocations use one canonical look-through exposure and
  drift method. Fund mixes, unclassified exposure, and provenance remain
  visible.
- Recorded history never contains projected values.
- Semantic destinations are server-authored and client-allowlisted.
- Unknown or unauthorised resources fail safely.

`./vendor/bin/pest tests/Architecture/ClientParityLedgerTest.php` initially
failed against the stale July ledger. It now passes with 722 assertions while
also refusing a `green` status unless this document contains the exact native
CI success marker required by the test.

## Installed-Google-Chrome acceptance loop

Final command (installed Google Chrome only, 390×844):

```text
PLAYWRIGHT_BASE_URL=http://localhost:8012 PLAYWRIGHT_REUSE_SERVER=1 PLAYWRIGHT_CHROME_CHANNEL=chrome npx playwright test tests/E2E/mobile/parity-closure.spec.js --project=mobile-chrome
```

The test creates one Premium persona using the canonical Laravel E2E boundary,
enters Dashboard, and traverses 17 total shared destinations through visible
drawer controls: Dashboard, Achievements, Conversation History, Income,
Expenditure, Net Worth, Bank Accounts, Investments, Retirement, Protection,
Estate Planning, Goals, Tax Strategy, Holistic Plan, Personal Information,
Subscription, and Settings. Each destination must finish loading with its
semantic heading or landmark, exact 390-pixel document width, and no runtime or
5xx error.

The same journey then proves a server-authored pension recommendation routes to
Retirement; achievement provenance, reached/in-progress/inapplicable states and
semantic action routing; retirement age bands and the 4.7% withdrawal
assumption without stale median wording; and recorded/projected Net Worth
separation with forecast save, reload persistence, and reset.

The test-red/fix loop was:

1. The first apparent green run was rejected after process inspection proved
   port 8000 belonged to the merged PR6 worktree. PR7 moved to an isolated port
   and the base URL changed to trusted `localhost` after Laravel correctly
   rejected untrusted `127.0.0.1` E2E traffic.
2. The isolated worktree lacked the ignored desktop Vite manifest. Chrome's
   ancillary request reached the desktop catch-all and returned 500 even though
   the mobile document and every API response were 200. A normal local desktop
   asset build supplied that test-server prerequisite.
3. Off-screen retirement bands were scrolled into view; bearer bootstrap now
   replaces a stale persistent-Chrome token once per tab session without
   overwriting the rotated token after reload; and the complete journey has a
   300-second outer budget while actions keep a strict 10-second timeout.
4. A level-up dialog appeared after a destination had settled and intercepted
   the next click. The user journey now dismisses the visible `Keep going`
   action both before navigation and after route settlement.
5. The combined projection persona correctly selected `Net worth £500,000`
   (`£484,000 of £500,000`, 96.8%), not the standalone achievements fixture's
   £10,000 milestone. The assertion now follows the server-owned combined
   position. Annual employment and retirement salary fields in E2E support are
   set to £60,000 so the semantic pension recommendation is deterministic and
   still produced by the real server recommendation path.
6. The route helper now waits for page loaders, network idle, and two animation
   frames and checks errors at every destination, preventing late failures from
   being hidden by the next navigation.

Final result: **1 passed**, browser journey 37.2 seconds, command 50.0 seconds,
exit 0. No Chromium, bundled browser, headless substitute, or in-app browser was
used.

## Native simulator loop and execution gate

The correct Xcode project is
`ios-native/Fynla.xcodeproj`. The expanded native journey is
`FynlaUITests/FynlaUITests/testPR7ParityClosureJourney` and covers the same 17
screen identities plus semantic dashboard routing, contextual conversation and
exact-history reopen, achievements, entered/recommended portfolio comparison,
pension holding detail, recorded/projected Net Worth persistence and reset,
the bounded Subscription state, Fyn, and native bug reporting.

Compile/link command:

```text
xcodebuild build-for-testing -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging -configuration UITesting -destination id=19E5310B-0B5D-409E-A08C-AA4B1C97FF99 -derivedDataPath /private/tmp/fynla-pr7-xcode-derived-clean2 -only-testing:FynlaUITests/FynlaUITests/testPR7ParityClosureJourney CODE_SIGNING_ALLOWED=NO CODE_SIGNING_REQUIRED=NO SWIFT_TREAT_WARNINGS_AS_ERRORS=YES
```

Result: `** TEST BUILD SUCCEEDED **`; Fynla and the UI-test runner compiled and
linked with warnings as errors. Runner:
`/private/tmp/fynla-pr7-xcode-derived-clean2/Build/Products/UITesting-iphonesimulator/FynlaUITests-Runner.app`.

Local runtime execution failed before Fynla launched. Xcode stayed at
`waiting for workers to materialize`; a diagnostic attempt reported
CoreSimulator error 405 while installing (`Mach error -308`, server died). The
same transport failure reproduced on clean iOS 26.3.1 iPhone 16 Pro
`19E5310B-0B5D-409E-A08C-AA4B1C97FF99` and previously known-good iOS 18.6
device `94F2B841-2099-4291-88AB-EDAA797ADF75`. The dedicated devices and
affected services were restarted, Xcode first-launch setup reran, and Apple's
signed `XcodeSystemResources.pkg` was reinstalled. Registry and boot recovered;
XCUITest transport did not. Diagnostic sample:
`/tmp/xcodebuild_2026-08-11_170540_Y5zO.sample.txt`.

This is explicitly a local CoreSimulator host block, not a native green result
and not a Fynla assertion failure. `.github/workflows/ios-native.yml` therefore
runs the full native UI suite and a dedicated PR7 rerun at
`accessibility-extra-extra-extra-large`, restores `large`, uploads both result
bundles on failure, and retains the existing Production build gate. PR7 cannot
merge until those CI jobs pass.

## Contract and regression gates

| Gate | Result |
|---|---|
| `./vendor/bin/pest tests/Architecture/ClientParityLedgerTest.php` | Passed: 1 test, 722 assertions. One existing PHP 8.5/Pest reflection deprecation. |
| PR7 Laravel authority selection: contextual conversation rehydration/dispatch/write boundary; portfolio exposure/analyser; retirement projection contract/service; history entitlement/snapshot coverage; mobile achievements; ledger architecture | Passed: 106 tests, 1,177 assertions in 35.73s. One existing PHP 8.5/Pest reflection deprecation. |
| `npx vitest run resources/mobile tests/frontend/mobile` under the repository's Node 20.19.5 runtime | Passed: 31 files, 182 tests in 46.56s. An initial shell-default Node run failed before collection because that runtime was incompatible with the installed jsdom stack; the pinned repository runtime is green. |
| `npm run build:mobile` | Passed: 122 modules transformed and production bundle emitted in 11.34s. Existing bundle-size advisory only. |
| `npm run lint` | Passed with no lint findings. |
| Staging `build-for-testing` with `SWIFT_TREAT_WARNINGS_AS_ERRORS=YES` | `** TEST BUILD SUCCEEDED **`. |
| `xcodebuild build -project ios-native/Fynla.xcodeproj -scheme Fynla-Production -destination generic/platform=iOS\ Simulator -derivedDataPath /private/tmp/fynla-pr7-production-derived CODE_SIGNING_ALLOWED=NO CODE_SIGNING_REQUIRED=NO SWIFT_TREAT_WARNINGS_AS_ERRORS=YES` | `** BUILD SUCCEEDED **`. |

## M-ID reconciliation

The installed-Chrome route journey directly exercises the route and
presentation side of M-01–M-34. The selected Laravel suites reconcile
contextual authority, portfolio look-through/drift, projections, history,
entitlement, ownership, semantic routing, and achievement state. The native
journey uses the same drawer ordering and stable semantic destinations. The
ledger records focused production and test evidence for every individual M-ID
rather than treating a route visit as proof of a financial calculation.

No product-code defect was found during PR7. The repaired failures were
closure-test orchestration or deterministic E2E-fixture defects. Native runtime
execution remains pending on a healthy simulator CI host and must be recorded
below before the ledger can be finalised.

<!-- The native CI success marker is added only after the required GitHub Actions run succeeds. -->
