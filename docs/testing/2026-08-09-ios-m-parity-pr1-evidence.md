# iOS and `/m` parity PR 1 evidence

Date: 2026-08-09
Native target: `ios-native/Fynla.xcodeproj`, `Fynla-Staging`
Simulator: Fynla iPhone 16 Pro iOS 18.6 (`B880080D-37ED-453E-A87E-3DE049902ECA`)

## Native bug-report transition reproduction

Production code was unchanged for both baseline reproductions.

### Baseline reproduction 1

- Started: 18:15:33 Europe/London
- Failed: 18:16:25 Europe/London
- `fyn.open`: present and tapped
- `fyn.report`: present and tapped
- `bug-report.description` as a text view: absent after an 8-second wait
- Last verified screen before the transition: Fyn, via the present `fyn.report` control
- Result: failed at `FynlaUITests.swift:155`
- Xcode stalled while finalising its failure log, so no complete result bundle was retained from this run.

### Baseline reproduction 2

- Started: 18:19:18 Europe/London
- Failed: 18:20:06 Europe/London
- `fyn.open`: present and tapped
- `fyn.report`: present and tapped
- `bug-report.description` as a text view: absent after an 8-second wait
- Last verified screen before the transition: Fyn, via the present `fyn.report` control
- Requested result path: `/private/tmp/fynla-bug-report-repro-2.xcresult`
- Result: failed at `FynlaUITests.swift:155`
- Xcode again stalled while finalising the failure log; the result directory contains data records but no final `Info.plist` and is therefore not a readable result bundle.

Both unchanged runs reproduced the same boundary failure. Test-only transition logging and failure attachments were then used to distinguish a cover-dismissal failure from a lost pending route or an incorrect accessibility element type.

## Root-cause hypothesis

The bug-report route is not lost. The regression test reports a transition failure because it queries `bug-report.description` as an XCTest `TextView`, while the vertical-axis SwiftUI `TextField` is exposed as an XCTest `TextField` on the supported iOS 18.6 simulator.

Diagnostic run at 18:26:43 Europe/London observed, after tapping Report a problem:

- `fyn.screen=false`
- `fyn.report=false`
- `bug-report.screen=true`
- `bug-report.description` as `TextView=false`
- `bug-report.description` as `TextField=true`

The same run selected the observed `TextField`, completed metadata review, submitted the report, and reached `bug-report.submitted` at 18:27:39. This proves the cover dismissed and the pending `.bugReport` route was pushed correctly. No production transition change is justified; the regression fix targets the incorrect XCTest element classification and adds an explicit destination-screen assertion.

## Clean regression verification

The temporary diagnostic logging and attachments were removed before verification. Both clean runs used the Fynla iPhone 16 Pro iOS 18.6 simulator and exercised the complete user path without fixed sleeps:

1. Open Fyn.
2. Tap Report a problem.
3. Verify `bug-report.screen` is present and the Fyn screen/report control are absent.
4. Enter the report in the observed `TextField`.
5. Review the included and excluded diagnostic metadata.
6. Submit and verify `bug-report.submitted`.

- Clean run 1: passed at 18:31:48 Europe/London; 1 test, 0 failures, 33.134 seconds.
- Clean run 2: passed at 18:34:20 Europe/London; 1 test, 0 failures, 33.855 seconds.

Result: two consecutive clean passes confirm the transition is stable and the regression was an incorrect XCTest element-type assumption.

## Full native smoke triage

The first complete iPhone 16 Pro run executed 48 UI tests with 2 expected
live-credential skips and 8 failures. Six failures were device-portability
problems in the tests: subscription and sign-out controls were present below
the fold, and the Dynamic Type test encoded the former iPhone 11 frame. The
tests now scroll to those controls and assert layout containment against the
active simulator window. All six journeys passed in the next complete run.

That next run started at 19:16:07 Europe/London and executed all 48 tests. It
finished with 43 passes, 2 expected live-credential skips and 3 registration
failures. The Xcode result bundle is:

`/Users/CSJ/Library/Developer/Xcode/DerivedData/Fynla-bvnrtmwpzwohuwdgcmgungpauxbo/Logs/Test/Test-Fynla-Staging-2026.08.09_19-15-43-+0100.xcresult`

Two failures reported that `registration.password` did not accept the typed
value. The third followed the same repeated-input degradation and could not
make the first-name field hittable. The failure recording showed the iOS 18.6
simulator placing its yellow “Automatic Strong Password” cover view over the
password field. Omitting `.newPassword` in the UI-test build did not prevent
the simulator from inferring a new-password pair from the adjacent secure
fields.

The UI-test build now explicitly marks those fields as existing-password
inputs, while every shipping build continues to use `.newPassword`. A focused
rerun of the three failed journeys completed at 19:53:09 Europe/London: 3
tests, 0 failures, 191.696 seconds. The result bundle is:

`/Users/CSJ/Library/Developer/Xcode/DerivedData/Fynla-bvnrtmwpzwohuwdgcmgungpauxbo/Logs/Test/Test-Fynla-Staging-2026.08.09_19-48-45-+0100.xcresult`

## Final native smoke verification

The final complete run started at 19:54:24 Europe/London and passed at
20:17:47. It exercised all 48 UI tests on the iPhone 16 Pro iOS 18.6
simulator: 46 passed, 2 live-credential tests were expectedly skipped, and 0
failed. Xcode finalised successfully with `** TEST SUCCEEDED **`.

Result bundle:

`/Users/CSJ/Library/Developer/Xcode/DerivedData/Fynla-bvnrtmwpzwohuwdgcmgungpauxbo/Logs/Test/Test-Fynla-Staging-2026.08.09_19-54-00-+0100.xcresult`

Independent review added two user-journey regressions: an administrator opening
the drawer directly from Dashboard, and Personal Information remaining
read-only without an Edit details action. The post-review complete run started
at 21:38:46 Europe/London and finished with `** TEST SUCCEEDED **` at 22:01:13.
It executed 50 UI tests: 48 passed, 2 live-credential tests were expectedly
skipped, and 0 failed. The result bundle is:

`/private/tmp/FynlaUITests-review-fixes.xcresult`

## Server and frontend verification

The PR 1 server contract gate passed with 40 tests and 241 assertions across
the route registry, web handoff, profile, dashboard next actions, notification
preferences, tier propagation, and two-tier identity suites. PHP 8.5 emitted 12
known `ReflectionMethod::setAccessible()` deprecations; no assertion failed.

The scoped frontend gate initially passed 13 files and 63 tests. The Chrome
acceptance loop then exposed an authenticated Help/legal redirect integration
defect. Its focused red/green gate adds seven router-policy assertions and two
middleware feature tests. The final complete counts are recorded in the final
verification section below.

Both production builds pass. The mobile build transforms 105 modules. The web
build transforms 1,378 modules when run with the production asset base
`VITE_BASE_PATH=/build/`; omitting that deployment base reproduced a blank
login because lazy chunks were requested from `/assets` rather than
`/build/assets`. The corrected command is now recorded in the implementation
plan. Existing non-blocking warnings remain: Browserslist data is six months
old, `hover-blue-gradient` is not generated, some onboarding modules are both
static and dynamic imports, and some chunks exceed 500 kB.

## Native unit verification

The post-review CI-equivalent native unit gate passed 358 tests in 62 suites on
the selected iPhone 16 Pro iOS 18.6 simulator. It includes the complete SSE
suite, the updated dashboard mark-done contract, complete semantic-destination
table coverage, and the dashboard-first administrator-account regression.
Result bundle:

`/Users/CSJ/Library/Developer/Xcode/DerivedData/Fynla-bvnrtmwpzwohuwdgcmgungpauxbo/Logs/Test/Test-Fynla-Staging-2026.08.09_21-37-27-+0100.xcresult`

The staging-health test is expectedly skipped without
`FYNLA_STAGING_BEARER_TOKEN`. The Apple StoreKit system-session suite was also
run twice after a non-destructive simulator reboot. Six purchase-session tests
failed identically with `productUnavailable`,
`ASOctaneSupportXPCService.ConfigurationError`, and Apple's off-device buy
mode. The repository CI workflow explicitly excludes this suite for the same
macOS 26 simulator limitation. Product configuration, injected StoreKit,
subscription-model tests, and the StoreKit-backed UI journeys all pass. The
second diagnostic result bundle is:

`/Users/CSJ/Library/Developer/Xcode/DerivedData/Fynla-bvnrtmwpzwohuwdgcmgungpauxbo/Logs/Test/Test-Fynla-Staging-2026.08.09_20-31-35-+0100.xcresult`

## Installed Google Chrome `/m` acceptance

Chrome was connected through the installed-browser extension. No Chromium,
headless browser, Playwright browser binary, or in-app browser was used. The
isolated E2E database used the full-data admin fixture `chris@fynla.org`; no
password, bearer, or handoff token is recorded here.

The Chrome connector reported a 1265×1056 viewport and does not expose a
synthetic viewport override. The `/m` host still served its dedicated
same-origin mobile iframe, and the route/component checks were completed in
that mobile shell. Exact phone-size interaction evidence comes from the native
iPhone 16 Pro simulator run above; a non-Chrome engine was not substituted to
manufacture 390×844 browser evidence.

Verified user journey:

- `/m` renders one authenticated mobile iframe and the full-data dashboard.
- The drawer contains Dashboard, Achievements, Income, Expenditure, Net Worth,
  Bank Accounts, Investments, Retirement, Protection, Estate Planning, Goals,
  Tax Strategy, Holistic Plan, Personal Information, Subscription, Settings,
  authorised Admin Panel, Share Fynla, and Sign out.
- Dashboard recommendation copy is not truncated. Selecting the Retirement
  focus exposed the complete “Maximise Employer Pension Match” recommendation;
  following it landed on `/m/app/retirement`.
- Personal Information is server-hydrated and read-only: canonical profile,
  household, domicile, and financial-summary values rendered with zero
  editable controls and no Edit details action.
- Market Updates changed from false to true, remained true after reload, then
  was restored to false and remained false after the rollback reload.
- Subscription rendered the server-owned Free/Premium feature comparison and
  £6.99 monthly display. Compare and upgrade consumed a single-use handoff and
  landed at `/settings/subscription`; the final URL contained no handoff token.
- The authorised Admin drawer action consumed a single-use handoff and landed
  at `/admin`; the final URL contained no handoff token. Replay rejection is
  covered by `tests/Feature/Auth/WebHandoffTest.php`.
- Net Worth rendered “Valuables” and no user-facing “Chattels”.
- After the defect fix, Help, Privacy Policy, and Terms landed respectively on
  `/help`, `/privacy`, and `/terms` with their expected headings.
- The final `/m` loop again rendered the dashboard, full recommendation copy,
  and Bank Accounts label in its iframe. Chrome recorded no application console
  errors after the corrected build; only installed-extension diagnostics were
  present. No secret marker appeared in the final handoff destinations.
- After independent review, the exact E2E mobile bundle was rebuilt and a
  focused Chrome rerun reconfirmed Personal Information without Edit details.
  The authorised Admin action again landed at clean `/admin`, with no handoff
  token in the final URL.

## Acceptance defect ledger

| Surface | Step | Observed result | Expected result | Classification | Root cause | Regression/fix | Rerun |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Chrome local web build | Open sign-in | Blank SPA; lazy CSS requested from `/assets` | Sign-in renders | Acceptance environment | Web build was produced with the development `/` Vite base | Corrected the verification command to set `VITE_BASE_PATH=/build/`; no product change | Sign-in and authenticated `/m` journey passed |
| `/m` Settings + authenticated web | Open Privacy or Terms | Desktop router redirected to `/dashboard` | Legal document opens | Product integration defect (M-04) | Authenticated-public guard treated legal documents as marketing conversion pages | Added `publicRoutePolicy.js`, seven Vitest assertions, and a narrow guard exemption | `/privacy` and `/terms` passed in installed Chrome |
| `/m` Settings + authenticated web | Open Help | Server middleware redirected to `/dashboard` | Help documentation opens | Product integration defect (M-04) | `redirect.authed` treated `/help` only as a guest marketing page | Added two middleware feature tests and a `/help` account-support exception | `/help` passed in installed Chrome; marketing root still redirects |
| Chrome connector | Dismiss seeded level-up / dashboard overlay | Connector focused controls but did not activate those overlay controls | Pointer activation | Test-driver limitation; not reproduced as product failure | Connector-specific event delivery on the seeded dashboard takeover | Cleared only the isolated fixture's pending celebration/onboarding state; no product change | Remaining `/m` components, tabs, forms, drawer, links, and handoffs passed; native UI suite remained green |
| Desktop bootstrap after cross-account handoff | Existing desktop `auth_token` bearer survived a new web-session handoff | The newly authenticated server session must always win | Critical security defect | The bridge wrote `web-session` only when `auth_token` was absent | Always overwrite the desktop token slot with the non-secret session marker; never transfer that marker back into the mobile bearer slot | Focused bridge regressions and complete frontend gate passed; Chrome Admin handoff landed at clean `/admin` |
| Desktop bootstrap marker | Marker deletion could miss a parent-domain cookie until its two-minute expiry | The marker should be immediately client-deletable | Security hardening | Marker inherited `SESSION_DOMAIN` even though it is needed only on the redirect host | Issue the marker as a host-only cookie and assert this when the session uses a parent domain | Focused red/green regression and complete server gate passed |
| Mobile/native semantic registries | Resolve Personal Information, Subscription, and Settings | Some client tables omitted valid server destinations | Contract defect (M-07) | Client allowlists had drifted from the server enum | Added the missing mappings and exhaustive table assertions on both clients | Focused mobile/native suites and complete gates passed |
| `/m` Personal Information | Open the canonical profile | Inherited Edit details action remained available | Product defect (M-02) | `MobileChrome` defaults `editDetails` to true | Pass `edit-details=false` and assert the rendered prop | Unit regression passed; focused installed-Chrome rerun shows no Edit details action |
| Native administrator navigation | Open the drawer directly from Dashboard | Admin was hidden until Settings loaded the account | Product defect (M-01) | `SettingsModel` cached account state only during Settings refresh | Derive account state from the authenticated user provider | Unit regression and dashboard-first XCUITest passed; complete 50-test UI gate passed |
| Web handoff records | Retain issued/consumed records indefinitely | Expired and consumed records had no lifecycle cleanup | Operations/security defect | The handoff model was not prunable or scheduled | Added bounded `MassPrunable` retention and a daily scheduled prune | Pruning and schedule feature tests passed in the complete server gate |
| Chrome E2E bundle | Recheck `/m` Personal Information after review fix | Chrome initially rendered the prior E2E asset hash | Current source bundle must be exercised | Local E2E server reads `public/m-e2e-build`, while the normal production command writes `public/m-build` | Rebuilt with the E2E base and output directory used by `scripts/e2e/serve.sh`; no product change | Focused Chrome rerun rendered the corrected read-only profile and clean Admin handoff |

## PR 1 traceability ledger

| Item | Code evidence | Automated evidence | Chrome evidence | Native evidence | Status |
| --- | --- | --- | --- | --- | --- |
| M-01 Admin link | allowlisted `WebHandoffDestination`, hash-at-rest handoff service/controllers, mobile/native adapters | `WebHandoffTest`, mobile handoff/navigation tests, native handoff/menu tests | Authorised drawer action landed at `/admin`; final URL clean | Full UI smoke includes the dashboard-first Admin drawer visibility assertion | Green |
| M-02 Personal Information | mobile `PersonalInformation.vue`; native Profile feature and API models | mobile Personal Information tests; native `PersonalInformationTests` and settings/router tests | Canonical values rendered with zero editable fields and no Edit details action | Full UI smoke opens the profile from the drawer and asserts the Edit details action is absent | Green |
| M-03 Subscription | server `TierComparisonService`; mobile/native subscription clients and views | tier propagation/identity, mobile Subscription, native Subscription API/model tests | Free/Premium comparison and secure `/settings/subscription` handoff passed | Full UI smoke covers comparison, StoreKit prices, retry/purchase/restore | Green, subject only to documented Apple system-session limitation |
| M-04 Help/legal | shared public-web adapters plus authenticated utility-route policy and Help middleware exception | mobile Settings/web-handoff tests, public-route policy tests, middleware feature tests, native settings tests | `/help`, `/privacy`, `/terms` all passed after red/green defect loop | Full UI smoke covers legal Safari-sheet destinations | Green |
| M-05 Preferences/data controls | mobile Notifications/Settings and native Settings model/view | notification API, mobile Settings, native settings tests | preference persisted across reload and was rolled back | Full UI smoke covers notification and privacy/settings journeys | Green |
| M-06 Full dashboard text | server next-actions payload and non-truncating mobile/native presentation | next-actions feature tests, mobile Dashboard tests, native dashboard model/UI tests | complete retirement and long tax/estate recommendation copy rendered | Full UI smoke includes full recommendation assertion | Green |
| M-07 Semantic destinations | server gate registry and client semantic destination factories | GateRoutes/next-actions tests plus exhaustive mobile/native semantic mapping tables | retirement recommendation landed on `/m/app/retirement` | Full UI smoke verifies semantic recommendation destination | Green |
| M-13 Bank Accounts | presentation labels in mobile/native savings surfaces and navigation | mobile navigation/dashboard tests; native menu/dashboard tests | dashboard and drawer render Bank Accounts | Full UI smoke asserts Bank Accounts | Green |
| M-27 Valuables | presentation labels in mobile/native Net Worth | mobile Net Worth tests; native Net Worth tests | Net Worth renders Valuables and no Chattels | Full UI smoke asserts Valuables | Green |
| M-33 bug report | corrected XCTest element classification and explicit destination assertions | isolated clean test twice, diagnostic submit, full native smoke | `/m` Report a problem remains present | Final gate: 48 passed, 2 expected skips, 0 failures | Green |

All specification ledger items outside M-01–M-07, M-13, M-27, and M-33 remain
deferred to the approved later phased PRs.

## Final verification

The post-defect complete rerun is green:

- Server contracts/features: 44 passed, 253 assertions, plus the same 12 PHP
  8.5 deprecations recorded above.
- Frontend scope: 14 files, 74 tests, 0 failures.
- Mobile production build: 105 modules, success.
- Web production build with `VITE_BASE_PATH=/build/`: 1,378 modules and PWA
  generation, success.
- Native unit gate: 358 tests in 62 suites, success.
- Native UI smoke: 50 executed, 48 passed, 2 expected live-credential skips,
  0 failures.
- Native `Fynla-Production` unsigned simulator build: success for arm64 and
  x86_64 with Swift 6 warnings-as-errors enabled.
- Installed-Chrome `/m` journey: green after the M-04 and independent-review
  fixes; the final focused profile and Admin handoff rerun passed.
- PR-specific JavaScript lint and all changed PHP Pint checks: clean. The
  repository-wide JavaScript lint remains blocked by its pre-existing baseline
  (324 errors and 1 warning); the five reported `auth.js` errors reproduce
  unchanged against `HEAD`.
- `git diff --check`: clean.
- Bearer-bridge scan: the only `auth_token` writes are the deliberate
  non-secret `web-session` marker and its test fixture; no mobile bearer is
  copied into desktop session storage.

No implementation files are staged or committed pending explicit approval.
