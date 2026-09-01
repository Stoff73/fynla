---
id: W-0044
title: The native iOS app has no route to the Will Builder — WebHandoffClient lacks the estateWill case the PHP enum and /m both have
mission: M-0002-persona-fidelity
owner: build-lead
status: deferred-ios
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
claimed: 2026-08-21T18:20:00Z
claimed_by: fix-batch-G
branch: branches/fixes/F-0011-batch-g-native-handoff-protection-ownership.md
severity: medium
surfaces: [ios]
source: found by fix-batch-B during W-0019, 2026-08-21; reported not fixed (outside batch scope)
prior_art_checked: 2026-08-21
prior_art_outcome: extend
---

## Intent

`ios-native/.../WebHandoffClient.swift:3-8` does not carry the `estateWill` case that
the PHP handoff enum and the `/m` pathway both have. **The native app therefore has no
route to the Will Builder at all** — a user on iOS cannot reach it by any path.

## Why it matters now

W-0019 makes mirror wills the only instrument offered to a married couple, and W-0024
fixes the mirror executor swap. Both land on web and `/m`. On native, the screen those
changes govern is unreachable, so a native user cannot act on any of it.

This is a Rule 19 / Rule 20 gap of the exact shape those rules exist to catch: a
capability present on two surfaces and silently absent on the third, because the
handoff enum is duplicated per surface rather than derived from one source.

## Acceptance

1. `estateWill` exists in the native handoff client and routes to the Will Builder.
2. Check the enum for **other** missing cases rather than adding only this one — if one
   drifted, the mechanism permits drift, and the sweep is the fix.
3. Verified on the simulator via the `ios-simulator` skill. Per CSJ's plan, iOS is
   tested on dev: fix, PR to dev, test until green, and only then TestFlight.

## Working notes

- 2026-08-21 fix-batch-G (build-lead): **done.** Branch document:
  `workforce/branches/fixes/F-0011-batch-g-native-handoff-protection-ownership.md`.

  **Acceptance 1 — `estateWill` exists and routes to the Will Builder.**
  `ios-native/Fynla/Core/Navigation/WebHandoffClient.swift` gains
  `case estateWill = "estate_will"`. The route is a button in the Estate screen's
  planning card (`Features/Estate/EstateView.swift`), wired through
  `NavigationDestinationFactory` to `AppRootView.openWillPlanning()`, which follows
  the existing `openAdmin()` shape exactly — one-time handoff, then the shared Safari
  sheet. Copy is `/m`'s verbatim ("Manage your will in the full app",
  `EstateBequests.vue:105`), because it is the same handoff to the same screen.

  **The trap, which is the part worth reading.** Swift derives an enum's raw value from
  the case name. `case estateWill` would have put **`"estateWill"`** on the wire, and
  `IssueWebHandoffRequest` validates against the PHP enum whose backing value is
  `"estate_will"` — so it would have been rejected as a 422 while looking completely
  correct in Swift, and the button would have failed at runtime with the enum "present".
  The five original cases worked only because their names are single lowercase words.
  All cases now carry explicit raw values so the convention is visible rather than
  remembered.

  **Acceptance 2 — the sweep, and why the mechanism permitted the drift.**
  `estate_will` was the only missing case; the PHP enum has six, the mirror had five.
  The deeper finding is that **the test that existed to catch this was itself the same
  kind of copy**: `WebHandoffClientTests.exposesOnlyTheServerAllowlistedSemanticDestinations`
  named "the server allowlist" and asserted a frozen literal list, so it stayed green
  through the drift it was written to prevent. Fixed on both sides —
  `tests/Feature/Auth/WebHandoffTest.php` now asserts the PHP enum's values and its
  failure message points at the native mirror, so adding a case there fails a test that
  names the file to update. A second PHP test asserts every value is snake_case, and a
  third asserts the server rejects `estateWill` — the exact string an unmirrored Swift
  enum would send.

  **Overlap with W-0110, treated together as instructed.** Same enum, same absence, the
  other estate instrument. I did **not** add a Lasting Power of Attorney destination:
  it would be an enum case with no caller, because `/api/estate` returns no `lpa_info`
  block for a status row to render and the entry point is a product decision W-0110
  already routes to product-lead. What this item delivers to W-0110 is that the drift
  now fails a test, so its future case cannot go missing the same way.

  **Verification.** `xcodebuild ... Fynla-Staging -destination 'generic/platform=iOS'`
  → **BUILD SUCCEEDED**; `build-for-testing` → **TEST BUILD SUCCEEDED**, so the new
  Swift tests compile. PHP side: `tests/Feature/Auth/WebHandoffTest.php` **12 passed,
  55 assertions** under `laravel_testing_c`.

  **NOT done: the Swift tests were not RUN**, and acceptance 3's simulator verification
  was not performed — team-lead scoped this to code plus compile-level coverage and
  explicitly did not ask for it. `Fynla-Staging` points at csjones, not local, so an
  end-to-end exercise of the handoff was not available either. **I COULD NOT TEST THE
  BUTTON.** Acceptance 3 is open and belongs to whoever runs the native pass.

- 2026-09-01 board-loop: **DEFERRED — iOS.** `surfaces: [ios]`, and CSJ ruled
  2026-08-31 that the board loop is web and `/m` only. `ios-native/` is untouched.
  The gap is real and the item stays open for a native cycle; nothing here closes it.
