# Financial data parity PR 4 evidence

Date: 2026-08-10
Branch: `codex/ios-m-financial-data-parity`
Native project: `ios-native/Fynla.xcodeproj`
Native schemes: `Fynla-Staging`, `Fynla-Production`
Simulator: iPhone 16 Pro, iOS 18.6 (`94F2B841-2099-4291-88AB-EDAA797ADF75`)

## Scope and authority

PR 4 completes the financial-data parity phase for protection explanations,
ISA ownership and tax-year contribution history, canonical holdings and
recorded performance, entered/recommended portfolio drift, and authoritative
freemium creation limits.

Laravel remains authoritative. Both mobile clients render the canonical API
contract without recalculating gaps, contributions, exposures, drift or
performance. The shared portfolio engine classifies direct holdings, applies
only recorded look-through mixes, exposes unclassified value and provenance,
and suppresses drift when classification coverage is unsafe. Existing
financial facts are rehydrated server-side; contextual clients continue to
send identifiers rather than balances or financial assertions.

## Automated verification

### Laravel and architecture

- Changed backend behavior: 221 tests passed with 814 assertions.
- The PR 4 store-boundary assertion, `capped financial records can only be
  created inside their canonical stores`, completed successfully with one
  assertion.
- The complete `TierConfigBoundaryTest.php` cannot execute its pre-existing
  Pest architecture expectation under this workstation's PHP 8.5.2. The
  pinned dependency accesses an uninitialised typed property before reaching
  the repository assertion. The repository pins Composer's platform PHP to
  8.3.30; the full file remains a CI gate on that supported runtime.
- The isolated application database exercised both additive migrations during
  the Pest runs. The ordinary development database was not mutated;
  `migrate:status` correctly reports
  `2026_08_10_000001_add_portfolio_context_fields` and
  `2026_08_10_000002_create_isa_contributions_table` as pending.

The changed selection covered the portfolio exposure engine and legacy
analyser regressions, investment/S&S ISA and DC-pension API parity, ISA ledger
and ownership, protection presentation, every audited freemium write boundary,
Fyn typed limit actions, and savings/store consumer regressions.

### Mobile web

- Complete `resources/mobile` Vitest selection: 23 files, 122 tests passed.
- Focused financial-parity suite: 8 tests passed.
- `npm run build:mobile`: 120 modules transformed and the production `/m`
  bundle completed successfully.
- `npm run lint`: no changed lint-target files remained.
- Existing non-blocking output is limited to the Browserslist-age notice and
  Vite's established large-chunk warning.

### Native iOS

- Final native unit run excluding only the unavailable StoreKit system-session
  fixture: 378 tests in 63 suites passed. The bearer-token staging health test
  was skipped because `FYNLA_STAGING_BEARER_TOKEN` is not present.
- The six separately reproduced `StoreKitTestTests` failures are an existing
  Apple StoreKitTest/Octane simulator-session limitation (`productUnavailable`
  and `ASOctaneSupportXPCService.ConfigurationError`). The injected StoreKit,
  product-configuration and subscription-model suites pass, and PR 4 does not
  modify StoreKit code or configuration.
- `Fynla-Production` completed an unsigned Release simulator build for arm64
  and x86_64 with Swift warnings treated as errors.
- `git diff --check` passed before and after the acceptance repairs.

## iPhone 16 Pro user journey

The final deterministic journey used the installed Xcode toolchain and the
authorised iPhone 16 Pro simulator:

1. Open Protection, expand Income replacement capital, and inspect the
   server-authored need, cover, shortfall, severity, inputs, 4.7% assumption,
   related policy and explanation.
2. Open Bank Accounts, expand ISA allowance, select tax year 2025/26, and
   reconcile the £2,500 Cash ISA contribution with its owner, ledger
   provenance and dated deposit.
3. Open the Stocks & Shares ISA/investment account, confirm holding 201,
   classified/unclassified exposure, coverage, entered and recommended
   comparators, percentage-point drift, provenance and recorded snapshots.
4. Open DC pension 31, confirm holding 301 and both portfolio comparators, then
   verify the explicit recorded-performance-unavailable state.

The final `testPR4CanonicalFinancialDataJourney` run passed one test with zero
failures in 69.521 seconds. Its result bundle is:

`/tmp/fynla-ios-parity-derived/Logs/Test/Test-Fynla-Staging-2026.08.10_16-54-09-+0100.xcresult`

After the first GitHub CI pass exposed unrelated test-harness races, the same
journey was rerun with the iPhone 16 Pro Simulator open and visible. It again
passed one test with zero failures, exercising all four screens in 286.401
seconds after a 101.5-second simulator automation-attachment delay. That
result bundle is `/tmp/FynlaPR4VisibleJourney.xcresult`.

The repaired Net Worth and SSE suites then passed 15 tests across two suites
with zero failures on the same booted simulator. Their result bundle is
`/tmp/FynlaPR4CIFix2.xcresult`.

Four kept screenshots were exported to:

`/private/tmp/fynla-pr4-ios-acceptance-attachments`

They are named in the result manifest as PR4-01 protection explanation,
PR4-02 prior-year ISA, PR4-03 investment portfolio and PR4-04 DC-pension
portfolio. Visual inspection confirmed readable financial values, headings,
provenance, drift and unavailable-state copy at the simulator's native
1206-by-2622 capture size.

## Installed Google Chrome `/m` acceptance

The `/m` pass will use only the user's installed Google Chrome through the
ChatGPT Chrome Extension and the existing signed-in profile. No Chromium,
headless Chromium, bundled Playwright browser or in-app browser is permitted.
Final route, journey, screenshots and console results will be recorded here
after the Chrome extension connection is restored.

## Issue and retest ledger

| Surface | Observed result | Classification | Root cause | Regression/fix | Green rerun |
| --- | --- | --- | --- | --- | --- |
| Native deterministic launch | Protection initially showed Retry instead of canonical data | UI-test composition defect | The new protection, investment and retirement screens still used live clients under deterministic UI testing | Add UI-test-only canonical compositions; staging and production builds retain authenticated live clients | Journey reached Protection, ISA and Investment |
| Native portfolio accessibility | Holding and comparator identifiers were absent or duplicated | Product accessibility/testability defect | Parent accessibility identifiers replaced descendant identities, while comparator IDs were applied to entire sections | Contain children at the portfolio root, combine each holding row, and identify only the comparator/history headings | Exact holding 201 and both comparators resolved uniquely |
| Simulator rerun | A rebuilt test occasionally launched the previously installed runner | Acceptance environment | The simulator retained the old app and UI-test runner between `test-without-building` attempts | Uninstall only `org.fynla.app.dev` and `org.fynla.uitests.xctrunner` before the focused rerun | Current binary and accessibility identities exercised |
| Native investment history | Success note differed from `/m` | Cross-client copy defect | Native used a paraphrase rather than the frozen canonical wording | Use `Recorded account-value snapshots only; no missing values are inferred.` on both surfaces | Investment history assertion and screenshot passed |
| Native DC history | Final card said `Dated Value History Unavailable.` | Cross-client copy defect | A generic title-humaniser generated the native fallback | Use the exact canonical `/m` sentence `Recorded performance history is unavailable.` | Final journey passed with PR4-04 evidence |
| Architecture runner | Full boundary file fails before its first repository assertion on PHP 8.5.2 | Acceptance environment | Pinned Pest architecture dependency is incompatible with the workstation runtime and the worktree autoload shim initially obscured that error | Correct the ignored worktree shim locally, run the new boundary assertion directly, and retain full supported-PHP CI gating | PR 4 boundary assertion passes; 221 application tests pass cleanly |
| Xcode sandbox | Initial simulator cleanup and Release asset-catalog build could not reach CoreSimulatorService | Acceptance environment | macOS sandbox denied Xcode simulator service access | Rerun the exact operations with approved Xcode/simulator access | Focused UI test, native suite and Release production build all succeeded |
| Xcode CI test harness | Concurrent Net Worth requests occasionally decoded the other endpoint's ordered fixture; the SSE overflow assertion depended on advisory task yields | Test-harness defects | The shared transport matched responses by call order, and newer Swift scheduling could repeatedly reselect the consumer test task | Add request-path-aware stubs for concurrent endpoints and give the SSE producer a real scheduling window | Net Worth and SSE suites: 15 tests passed; visible PR4 journey passed again |
| Feature CI ISA allowance | A form-created Cash ISA reported £0 used instead of its £8,000 subscription | Canonicalisation defect | The API accepted legacy `YYYY-YY` input, while the canonical ledger and tracker query `YYYY/YY` | Normalise form-supplied ISA tax years before the account and ledger are written; assert the canonical persisted value and allowance result | Focused normaliser proof passes locally; supported-PHP Feature CI rerun pending |
| Simulator launch | A visible `test-without-building` rerun remained on the home screen before attaching automation | Acceptance environment | CoreSimulator needed 101.5 seconds to launch the runner, load accessibility and establish the automation session | Keep the simulator visibly open, distinguish attachment time from test execution, and wait for the first recorded app action before counting the journey | Fynla launched and all four PR4 journey stages passed |
| Installed Chrome `/m` | The exact `https://csjones.co/fynla/m` tab can be enumerated, but content inspection, screenshot and a fresh-tab navigation all time out | Acceptance environment | The ChatGPT Chrome Extension exposes tab metadata but its content-control channel is not responding after prescribed reconnection and stale-tab recovery | Do not substitute `/m/app`, another browser or a headless runner; reinstall the Chrome plugin from the Codex plugin UI and rerun only the exact `/m` route | Pending; this remains a merge gate |

## Traceability

| Item | Canonical evidence | `/m` evidence | iOS evidence | Status |
| --- | --- | --- | --- | --- |
| M-09 protection explanations | Server presentation contract and ownership-scoped policies | Canonical expandable gap cards and Vitest | Expanded calculation UI and PR4-01 | Automated/native green; Chrome pending |
| M-15 freemium caps | Store/TierGate boundaries plus typed subscription action | Typed limit presentation tests | Canonical API error handling retained | Automated green; Chrome pending |
| M-16/M-17/M-18 ISA parity | Owner-aware contribution ledger and exact allowance totals | Tax-year/owner/ledger component tests | Prior-year selection and PR4-02 | Automated/native green; Chrome pending |
| M-34 portfolio method | One exposure/presentation service for investments, S&S ISAs and DC pensions | Shared holdings/drift/history rendering tests | Investment and DC journeys, PR4-03/04 | Automated/native green; Chrome pending |
