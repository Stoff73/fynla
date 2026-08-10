# Contextual Fyn and Conversation History PR 2 evidence

Date: 2026-08-10
Branch: `codex/ios-mobile-contextual-fyn`
Native target: `ios-native/Fynla.xcodeproj`, `Fynla-Staging`
Simulator: Fynla iPhone 16 Pro iOS 18.6 (`B880080D-37ED-453E-A87E-3DE049902ECA`)

## Scope and trust boundary

This PR covers the contextual half of M-08 plus M-11, M-31 and M-32. Both
mobile clients send only action, resource and navigation identifiers. Laravel
ownership-filters the referenced resource, rehydrates canonical facts on every
turn and fixes the conversation mode from server-owned metadata. Existing
financial facts never come from a client-authored prompt or snapshot.

The capture/write boundary is exercised by
`ContextualCaptureWriteBoundaryTest`: creating or loading a contextual
conversation is read-only; tentative user text remains a message; explicit
capture delegates to the existing validated store and audit chain; and a
foreign resource identifier returns not found before conversation, message,
audit or capture state is created. The focused gate passed 4 tests with 28
assertions.

## Server and mobile-web automated verification

- Server selection: 1,007 passed, 5 skipped, 3,821 assertions. The command
  covered `tests/Feature/AI`, `tests/Feature/Fyn`, `tests/Feature/Onboarding`,
  the client-compatibility contract and the gate-route architecture test.
- Frontend: 74 files and 837 tests passed.
- `/m` production build: 107 modules transformed successfully.
- Existing non-blocking output is unchanged: PHP 8.5
  `ReflectionMethod::setAccessible()` deprecations, Vue test warnings,
  Browserslist age and Vite chunk-size warnings.

The request-contract suites assert exact identifier-only payloads for both
clients, reject unknown and value-bearing keys, preserve onboarding/surface
mode isolation and prove server-side resource freshness and non-disclosure.

## iOS Simulator acceptance

The deterministic user journey on the authorised iPhone 16 Pro simulator:

1. Open Bank Accounts and a savings-account detail.
2. Tap Edit details and receive contextual conversation 401.
3. Close and tap Edit details again; receive fresh conversation 402.
4. Open Conversation History and reopen conversation 401 exactly; conversation
   402 is absent from that transcript.
5. Select the fallback for unavailable conversation 499 and return safely to
   Bank Accounts.

The final post-review critical run passed 1 test with 0 failures in 35.328
seconds. It also asserts that the unavailable conversation has no Open action.
Its result bundle is:
result bundle is:

`/private/tmp/FynlaPR2AcceptanceUnavailableGate-20260810.xcresult`

Five kept screenshots were exported to:

`/private/tmp/fynla-pr2-ios-acceptance-top-level-attachments`

They cover the first contextual opening, the fresh second opening, grouped
history, exact transcript resume and the unavailable-resource fallback. Visual
inspection confirmed the final Bank Accounts fallback has its complete hero
gradient and readable title/subtitle.

Focused native verification also passed:

- contextual conversation model and history: 14 tests;
- navigation menu: 5 tests;
- router: 8 tests, including replacement of the scrolled history stack when a
  safe fallback is opened;
- the trusted Personal Information and critical contextual/history UI journeys:
  2 tests.

Final native verification used the exact authorised simulator identifier. The
unsigned `Fynla-Production` device build succeeded. The full staging result
bundle recorded 422 passed, 3 skipped and the same 6 pre-existing
StoreKitTest system-session failures; all 51 UI tests passed, with 2 live-
credential journeys skipped. The bundle is:

`/private/tmp/FynlaPR2FullPostReview-20260810.xcresult`

## Installed Google Chrome `/m` acceptance

The installed Chrome-only connector was unavailable during this PR 2 session.
The first lightweight connection and one documented retry both failed. After
user authorisation, the Chrome extension helper attempted to open a fresh
Default-profile window, but macOS Launch Services returned
`kLSNoExecutableErr`; a final connector retry still reported that the Chrome
extension browser was unavailable. The app bundle and running Chrome 151
executable were present, so this is classified as a connector/Launch Services
environment failure, not a Fynla product failure.

No Chromium, bundled Playwright browser, headless substitute or in-app browser
was used. Browser-dependent visual/network acceptance is therefore explicitly
deferred. `/m` remains covered here by its exact request/component contracts,
complete frontend gate and successful production mobile build; those are not
represented as Chrome evidence.

## Issue and retest ledger

| Surface | Observed result | Classification | Root cause | Regression/fix | Green rerun |
| --- | --- | --- | --- | --- | --- |
| Server boundary test harness | Initial streamed-response assertions and an unseeded tier caused test-mechanics failures | Test defect | The response stream was not consumed and the integration persona lacked tier configuration | Consume the stream and seed the real tier configuration; no production relaxation | 4 tests, 28 assertions passed |
| Worktree verification command | Calling the primary checkout's Pest binary made the four boundary tests lose their Laravel base class | Acceptance environment | Pest resolved the primary checkout namespace instead of the PR 2 worktree | Reused the worktree-scoped Pest wrapper and autoload mapping | 4 tests, 28 assertions passed in 21.95 seconds |
| Native Personal Information UI fixture | The new contextual Edit journey initially used live profile data under deterministic UI testing | Test-fixture regression | The feature had no deterministic Personal Information client in UI-test composition | Added a UI-test-only canonical profile client while shipping builds retain the live client | Focused Personal Information journey passed |
| Native StoreKit system session | Six StoreKitTest cases return `productUnavailable` / StoreKitTest `unknown` | Pre-existing simulator baseline | Apple StoreKitTest system-session limitation on this macOS/Xcode simulator combination | Reproduced in isolation; no StoreKit production/configuration file is touched by PR 2 | Injected StoreKit, product configuration, subscription-model and StoreKit-backed UI tests pass |
| Native unavailable-resource fallback, loop 1 | Bank Accounts title/subtitle were white on the page background after leaving a scrolled history row | Product navigation defect | The history fallback was pushed onto the already-scrolled history stack; explicit destination identity did not change that navigation semantics | Added route-identity regression as a diagnostic, proved it insufficient and removed it | Journey passed but exported screenshot still reproduced the defect |
| Native unavailable-resource fallback, loop 2 | Safe fallback inherited the preceding stack rather than opening its canonical overview | Product navigation defect | Conversation History used the generic push callback instead of the router's existing top-level `open` operation | Added a router replacement contract and wired only history fallbacks through `openTopLevel` | Router gate and 36.887-second XCUITest passed; final screenshot shows the complete gradient |
| Contextual transcript reopened after entity deletion or ownership loss | A stored contextual conversation could expose its old transcript even though fresh prompt assembly failed closed | Product trust-boundary defect | Transcript GET and turn endpoints authenticated the conversation but did not re-authorise the related resource | Re-resolve owned context in transcript, message and queued-message endpoints; return typed 410 with a canonical server-derived fallback | Deletion and ownership-loss endpoint regressions plus both clients' unavailable-state tests passed |
| Conversation History summaries | Last-message excerpts could repeat balances, account numbers or other user-entered financial text | Product privacy defect | The history projector copied and truncated raw message content | Replace excerpts with fixed mode-safe continuation copy and derive contextual titles from server-owned purpose | Sensitive-value and account-number non-disclosure assertions passed |
| Identifier and semantic-destination validation | Laravel's integer rule accepted numeric strings and the destination could be syntactically valid but semantically mismatched | Product contract defect | The request boundary did not require native JSON integers or exact resource/screen/fallback/enum coherence | Require JSON integers and exact canonical destination mappings for every supported resource | Contract matrix and hostile payload regressions passed |
| Contextual creation succeeded but transcript GET failed | `/m` and iOS could replace the trusted opening with a blank/error state and a retry could create another conversation | Product resilience defect | The two-stage client flow discarded the POST result and did not retain the created ID | Retain the server-authored opening and conversation ID; retry GET for that same ID without another POST | Vue and Swift retry regressions passed |
| Joint-owner Edit actions | Read-only joint savings, investment and goal rows could still offer contextual Edit | Product authorisation/presentation defect | The clients did not consistently consume primary-owner authority | Expose/consume `is_primary_owner` and suppress Edit while retaining read access | Laravel, Vue and Swift ownership regressions passed |
| Contextual history availability lookup | Multiple history rows caused one ownership query per conversation | Product performance defect | The projector resolved every related entity independently | Batch references and issue one ownership-scoped query per resource type | Query-budget regression proves three savings references use one savings query |
| Installed Chrome connector | Extension browser remained unavailable after authorised helper recovery | Acceptance environment | Launch Services rejected the helper launch although the signed Chrome bundle/executable was present | Followed Chrome troubleshooting and retried once; did not substitute another engine | Chrome-only portion deferred |

## Traceability

| Item | Code/contract evidence | Automated evidence | Native acceptance | Status |
| --- | --- | --- | --- | --- |
| M-08 contextual Add/Edit | strict contextual endpoint, server resolver/service, typed `/m` and Swift launchers | server request/dispatch/boundary suites, frontend contracts, Swift model tests | repeated Edit creates 401 then fresh 402 | Green for PR 2 contextual half |
| M-11 onboarding isolation | immutable conversation mode resolver at message, queued-stream and action seams | dispatch, campaign re-entry and onboarding interruption suites | exact contextual transcript remains separate from onboarding history | Green |
| M-31 client parity/trust | shared snake-case identifier contract and semantic destinations | compatibility, forbidden-key, mobile and Swift encoding tests | contextual launch and safe fallback complete without client facts | Green |
| M-32 Conversation History | safe server projection plus `/m` and native history surfaces | index, architecture, Vue and Swift history tests | grouped history, exact 401 resume and unavailable 499 fallback pass | Green; Chrome visual rerun deferred |

Portfolio/allocation/drift work is intentionally absent and remains assigned to
the approved later PR 4.
