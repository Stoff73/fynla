---
id: W-0484
title: The cookie banner's own backdrop swallows the first click on Decline
mission: M-0002-persona-fidelity
owner: build-lead
status: queued
severity: medium
surfaces: [web]
source: found during W-0001 browser verification, 2026-08-25
prior_art_checked: 2026-08-25
prior_art_outcome: none
---

## Intent

On the public landing page the consent dialog renders `.cc-overlay` containing a
`.cc-backdrop`. The backdrop sits over the dialog's own buttons and intercepts
pointer events, so the first click on **Decline Cookies** is absorbed and nothing
happens. A second click registers and sets the `fyn_cookie_consent` cookie.

Playwright reports it plainly:

    <div class="cc-backdrop"></div> from <div role="dialog" class="cc-overlay"
    aria-label="Cookie preferences"> subtree intercepts pointer events

This is a consent control that does not respond first time, and the
privacy-preserving option is the one being swallowed. A user who clicks once and
sees nothing happen may reasonably conclude declining is not offered.

## It also fails a test that has been red without being read

`tests/E2E/smoke/desktop.spec.js` times out after 60s on `locator.click` for
"See our demo" — the same backdrop. The fixture in `tests/E2E/fixtures/app.js`
tries to pre-accept consent:

    window.localStorage.setItem('cookie_consent', 'accepted');

but the application gates on the **cookie** `fyn_cookie_consent`, not on
localStorage. So the fixture never suppresses the banner, the banner blocks the
click, and the desktop smoke test has been failing for a reason unrelated to what
it set out to test.

## Acceptance

1. A single click on Decline, and on Accept, registers. The backdrop stops
   intercepting events destined for the dialog's own controls.
2. `tests/E2E/fixtures/app.js` seeds `fyn_cookie_consent` — the mechanism the
   application actually reads — rather than a localStorage key nothing consults.
3. `npm run test:e2e:smoke` desktop project passes.
4. Verified by clicking once, in a browser, on web and `/m`.
