# W-0044 — build-lead → quality-lead

Branch document: `workforce/branches/fixes/F-0011-batch-g-native-handoff-protection-ownership.md` §1.

## Done

- `estateWill` added to the native `WebHandoffDestination`, **with an explicit raw value
  of `"estate_will"`**, and a route to it: a button in the Estate planning card wired
  through `NavigationDestinationFactory` to `AppRootView.openWillPlanning()`, following
  the existing `openAdmin()` shape.
- Acceptance 2's sweep: `estate_will` was the only missing case, and the mechanism that
  permitted the drift is now closed from the PHP side.
- 4 new PHP tests, 2 new Swift tests, 1 Swift test corrected.
- `xcodebuild` **BUILD SUCCEEDED** and **TEST BUILD SUCCEEDED**; PHP 12 passed / 55
  assertions under `laravel_testing_c`.

## Not done, and why

- **The Swift tests were not run, and the button has never been pressed.** Team-lead
  scoped this to code plus compile-level coverage and explicitly did not ask for
  simulator work. `Fynla-Staging` points at csjones, not local, so no end-to-end
  exercise was available. **I COULD NOT TEST THIS.** Acceptance 3 is open.
- No Lasting Power of Attorney destination added (W-0110) — it would be a case with no
  caller; see the branch document.
- No commit, no PR, no deploy.

## What you need that isn't obvious from the artefacts

1. **The interesting failure mode is the raw value, not the missing case.** Swift derives
   an enum's raw value from the case name, so `case estateWill` would have sent
   `"estateWill"` and the server would have 422'd — with the Swift looking correct. If
   you review one line, review `case estateWill = "estate_will"`. `WebHandoffTest`'s
   `it rejects a camelCase destination` exists to pin exactly that.
2. **The old Swift test was not a safety net.** It asserted a frozen literal list while
   calling itself "the server allowlist", and stayed green through the whole drift. The
   real guard is now on the PHP side, because that is where a new destination is added
   first.
3. **The Estate screen is a transcription of `/m`'s** (see `EstateView.swift`'s header
   comment), so the button's copy is `/m`'s verbatim from `EstateBequests.vue:105`. If
   you think the wording should change, it needs changing in both.
4. Rule 15: estate detail views are icon-banned, so the button is text only — deliberate,
   not an oversight.

## Assumptions I made

- That the Estate planning card is the right home for the entry point. `/m` puts the same
  handoff on the *bequests* sub-screen, which native does not have, so I placed it on the
  only estate surface native has. A reviewer could reasonably want it elsewhere.
- That `@MainActor` on the closure is correct for this codebase's concurrency settings.
  It compiles, and it matches how `openAdmin` reaches `browserItem`.
- That the raw values of the five original cases were always intended to equal their case
  names. They do today; I made that explicit rather than inferred.

## Surfaces covered / not covered

- **iOS native — code covered, unverified at runtime.**
- **Backend — covered and tested** (the enum, the endpoint, the drift guard).
- **Web and `/m` — unchanged.** `/m` already had this handoff; that is what made the
  native absence visible.
