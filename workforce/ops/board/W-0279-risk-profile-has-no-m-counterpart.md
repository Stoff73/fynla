---
id: W-0279
title: The risk profile has no /m counterpart, while the risk level it produces is shown on /m
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: product-lead
status: handoff
severity: low
surfaces: [m]
created: 2026-08-22T22:10:00Z
claimed: 2026-08-25T12:30:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: [W-0271, W-0272, W-0273]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Recorded while checking Rule 19 coverage for W-0271 / W-0272 / W-0273.

`resources/mobile/router.js` has **no** risk route. There is no `/m` equivalent of
`/risk-profile`, `/risk-profile/levels` or `/risk-profile/factor/:factor`.

The output of that engine does reach `/m`:
`resources/mobile/views/modules/Investment.vue:104` renders
`riskProfile.risk_level` as the attitude to risk shown against the portfolio. So a
mobile user is shown the **conclusion** with no route to the nine factors behind it,
no way to see which figure produced it, and no way to correct a factor that is wrong.

This is why the three fixes above needed **no** `/m` code change — there is nothing on
`/m` to change — and it is a Rule 19 gap in the product rather than in those items.
Flagged, not skipped.

## Acceptance

A decision: either `/m` gets the factor breakdown, or showing a bare risk level there
with no explanation is deliberate and recorded as such.

- [x] **DECIDED by Brett, 2026-08-25: neither — a third option.** `/m` keeps the bare
      level and gains a **route to the breakdown on the web app**, the same shape the
      estate screen took for Inheritance Tax under W-0469. `/m` is an honest summary
      that hands off; it does not become a second breakdown.
- [x] The destination is allowlisted server-side and mirrored into native in the same
      change, per the parity test that exists to catch exactly that omission.
- [x] Pinned by tests on both sides, mutation-verified.

## Working notes

(append-only)

- 2026-08-25 (Brett, working alone per CSJ's 2026-08-24 standing instruction):
  **DONE — `/m` now offers a route to the factors behind the level it prints.**

  **The decision was Brett's, and it is a third option the item did not list.** Not
  "build the breakdown on `/m`", and not "record the bare level as deliberate", but
  **hand off to the web screen that already holds it** — the same shape CSJ chose for
  the Inheritance Tax breakdown under W-0469, where `/m` is an honest summary rather
  than a second, smaller copy of a table.

  **This was a four-place change, and the fourth place is the one that bites.**
  `WebHandoffTest` carries a test whose comment says in terms: *"If this test fails
  because you added a destination, that is the point: add it to the native enum and to
  `WebHandoffClientTests` in the same change."* It exists because `estate_will` was
  added server-side and never mirrored, so **native had no route to the Will Builder at
  all**, and the Swift test that claimed to assert "the server allowlist" was itself a
  frozen copy. So:

  - `app/Enums/WebHandoffDestination.php` — `RISK_PROFILE = 'risk_profile'` → `/risk-profile`
  - `ios-native/Fynla/Core/Navigation/WebHandoffClient.swift` — `case riskProfile = "risk_profile"`
  - `ios-native/FynlaTests/WebHandoffClientTests.swift` — both mirror assertions
  - `tests/Feature/Auth/WebHandoffTest.php` — the allowlist parity assertion
  - `resources/mobile/views/modules/Investment.vue` — the card, copy and button

  The value is snake_case deliberately: Swift derives a raw value from the case name
  unless told otherwise, so a camelCase destination ships silently and is rejected as a
  422. There is a test for that too, and `risk_profile` satisfies it.

  **A finding worth recording, because it changes what the alternative would have
  cost.** `GET /api/investment` **already returns the complete nine-factor
  `factor_breakdown`** — measured on user 101: capacity for loss, time horizon,
  knowledge, dependants, employment, emergency fund, monthly surplus, age, income
  stability, each with its value, level, components and a written description. So
  "give `/m` the breakdown" was never blocked on the payload; the data has been
  arriving all along and the screen renders none of it. **If CSJ ever wants the
  factors on `/m`, it is a rendering job, not an API one.** The handoff is still the
  right call under the W-0469 precedent — but the item's framing implies the data is
  missing, and it is not.

  **Verified in the browser on `/m`** (localhost, chris@fynla.org, `/m/app/investment`):
  the card renders "Attitude to risk — Upper Medium", the sentence about the nine
  factors, and the button. Clicking it issues the handoff and navigates.

  **NOT verified end-to-end, and the reason is environmental, not the change.** The
  browser lands on `/login` rather than `/risk-profile` on this machine. Cause found
  and proved: `config('session.secure')` is **true** while `APP_URL` is plain
  **http://localhost**, so the `fynla_web_session` marker cookie is issued `secure`,
  the browser refuses to store it over HTTP, `mScaffoldBridge` never sees the marker,
  never sets the `web-session` sentinel, and the SPA guard bounces to login.

  **Proved it is not this change** by running the existing `estate_iht` destination as
  a control through the same consume endpoint. Both are identical:

  | Destination | Response | Location | Cookie |
  |---|---|---|---|
  | `risk_profile` | 302 | `/risk-profile` | `fynla_web_session=1; secure` |
  | `estate_iht` | 302 | `/estate/inheritance-tax` | `fynla_web_session=1; secure` |

  **So no web handoff of any kind can be verified end-to-end on a local HTTP dev
  environment** — a trap worth knowing before someone spends a session on it. The
  server half is correct and consistent with a destination already shipped.

  **Tests.** Four `/m` cases in
  `resources/mobile/views/__tests__/InvestmentRiskProfileHandoff.spec.js` — the button
  exists beside a printed level, it asks for the `risk_profile` destination literally,
  it says so rather than failing silently when the handoff cannot be issued, and it
  renders no card at all when the engine has produced no level. **Mutation-verified:
  stashing the component change turns 3 of the 4 red.** Two PHP cases: the destination
  issues, and it lands on `/risk-profile` — the path assertion matters because an
  allowlisted name pointing at the wrong screen is a handoff to the wrong place and
  nothing else would catch it.

  Green: `WebHandoffTest` 14 / 61 assertions; full `/m` suite 186 / 31 files. Pint and
  ESLint clean.

  **iOS: the enum and its tests are updated, but the app was NOT built, launched or
  looked at.** Native has no risk screen either, which is why it gets the destination —
  but whether anything in the Swift UI calls it is a separate piece of work and is not
  claimed here.
