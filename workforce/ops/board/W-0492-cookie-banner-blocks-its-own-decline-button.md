---
id: W-0492
title: The E2E consent fixture seeds a key nothing reads, so the banner blocks every landing-page test
mission: M-0002-persona-fidelity
owner: build-lead
status: review
severity: medium
surfaces: [web, m]
source: found during W-0001 browser verification, 2026-08-25
prior_art_checked: 2026-08-25
prior_art_outcome: none
---

## Correction — this was filed on a wrong premise

**Filed as "The cookie banner's own backdrop swallows the first click on Decline".
That is not what happens, and the title above has been changed.**

Two claims were made and both were wrong:

1. *"The first click on Decline is absorbed and nothing happens; a second click
   registers."* The decline flow is **two steps by design**. `renderInitial()`
   gives the button `data-cc="warn"`, which renders a "Limited Functionality"
   panel; the second button, "Continue Without Cookies", carries
   `data-cc="decline"` and records the decision. `CookieBanner.vue` mirrors it
   exactly with `showWarning`, and `cookie-consent.js` says so in its header —
   *"Mirror the SPA banner's copy and two-step decline flow so the experience is
   identical across surfaces."* The first click worked; it advanced a step.

2. *"The backdrop intercepts pointer events."* It does, and that is correct — the
   banner is a modal. The Playwright error quoted in the original report was from
   clicking **"See our demo", a link behind the overlay**, not a control inside it.

The premise came from watching symptoms and inferring a cause instead of reading
`public/pages/js/cookie-consent.js`. No banner code needed changing.

## What the defect actually is

`tests/E2E/fixtures/app.js` tried to pre-answer the banner with:

    window.localStorage.setItem('cookie_consent', 'accepted');

**No production code reads that key.** Consent lives in the `fyn_cookie_consent`
cookie, read by `resources/js/utils/cookieConsent.js`,
`public/pages/js/cookie-consent.js` and the server-side affiliate middleware —
and `resources/js/__tests__/cookieConsent.spec.js` pins it with a test literally
named *"reads the decision from the cookie, not from local storage"*.

So the fixture never suppressed anything. The banner appeared in every E2E run,
its backdrop correctly covered the page beneath, and any test that clicked
landing-page content timed out. **That is what kept the desktop smoke test red**,
for a reason unrelated to what it was testing.

The same wrong seed was duplicated in six more places in
`tests/E2E/journeys/user-reported-campaign-regressions.spec.js`, which build their
own contexts and so are not covered by the shared fixture.

## Fix

One exported helper, `seedCookieConsent(context)`, in `tests/E2E/fixtures/app.js`,
used by the auto-fixture and by all six journey call sites (Rule 20 — one
mechanism, one home). It writes the real cookie through an init script rather than
`context.addCookies()`, because contexts built with `browser.newContext()` have no
`baseURL` to anchor a cookie to.

Zero `localStorage.setItem('cookie_consent', ...)` calls remain under `tests/E2E/`.

## Verified

`tests/E2E/smoke/desktop.spec.js`, which had been timing out at 60s on
`locator.click`:

- Before: **fails**, `locator.click` timeout on "See our demo"
- After: **1 passed (26.8s)**

ESLint clean on both changed files.

## Gaps

- **The smoke run is intermittent under load.** After the fix the desktop test
  passed alone, then failed on repeated back-to-back runs with the persona click
  not redirecting to `/dashboard` (page stays on `/`). A full 85-minute Pest suite
  was running against the same MySQL server throughout, which CSJTODO already
  names as a source of failures "indistinguishable from real breakage". **Not
  diagnosed, and not claimed as fixed or as a product defect** — it needs a clean
  re-run on an idle machine. `POST /api/preview/login/young_family` returns 200
  when probed directly.
- **Could not run the sanctioned E2E path.** `scripts/e2e/serve.sh` refuses any
  database not named `*_e2e` or `fynla_e2e_*`, and the MySQL account here can only
  create databases matching the `fynla?main` grant wildcard. Runs used
  `PLAYWRIGHT_REUSE_SERVER=1` against the dev server instead, which is why the
  origin had to be aligned by hand — see W-0493.
- **The six journey call sites were not executed.** They need registration,
  verification codes and an E2E database, none available here. The substitution is
  mechanical and the key they replaced is read by nothing, so it cannot regress
  behaviour — but it is unrun.
