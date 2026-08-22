---
id: W-0047
title: Google Analytics falls back to the hardcoded production measurement ID, so local and test runs send hits to the live property
mission: M-0002-persona-fidelity
owner: build-lead
claimed_by: fix-batch-F
status: handoff
handoff_to: quality-lead
branch: branches/fixes/F-0007-batch-f-analytics-consent.md
severity: high
surfaces: [web, m]
source: found by persona-passA2 during the onboarding sweep, 2026-08-21, while diagnosing W-0050
prior_art_checked: 2026-08-21
prior_art_outcome: extend
prior_art_found: [resources/js/utils/cookieConsent.js, resources/js/utils/awinTracking.js, public/pages/js/cookie-consent.js, deploy/fynla-org/build.sh, deploy/csjones-fynla/build.sh]
---

## Intent

`resources/js/utils/cookieConsent.js:6`:

```js
const GA_ID = import.meta.env.VITE_GA_ID || 'G-3Y8DL3QB09';
```

The fallback is the **live production measurement ID**. Any environment without
`VITE_GA_ID` set — local development, csjones staging, an automated test run, a
developer's machine — that accepts cookies loads
`googletagmanager.com/gtag/js?id=G-3Y8DL3QB09` and **sends hits to the production
Google Analytics property**.

Proven live during the persona run: accepting the cookie banner on `localhost:8000`
immediately fired that request. It was blocked at the network layer by the tester, but
nothing in the application prevented it.

## Why it matters

1. **The production analytics data is contaminated** by development and test traffic —
   every automated run that accepts cookies is a real session in the live property.
   Any metric derived from it (conversion, funnel, retention) is wrong by an unknown
   amount, and the contamination is invisible because those hits look like users.
2. **It is data sent to a third party from environments that were never meant to
   emit it**, including test runs using synthetic personal data.
3. A missing environment variable should fail closed, not silently fall back to
   production.

## Acceptance

1. **No fallback to a production identifier.** If `VITE_GA_ID` is unset, Google
   Analytics does not load at all. Absent configuration means no analytics, never
   default analytics.
2. The same treatment for the Awin MasterTag, which `acceptCookies()` loads alongside
   Google Analytics — check whether it carries the same hardcoded-fallback shape.
3. `/m` checked — `resources/mobile/` has its own bundle and may carry its own copy of
   this logic. If there are two copies, converging them is part of the fix (Rule 20).
4. Determine whether csjones staging currently sets `VITE_GA_ID`. If it does not,
   staging has been reporting into production analytics too — say so, with a date
   range if it can be established.

## Working notes

Distinct from **W-0050** (the cookie wall itself — whether consent to analytics can be
a condition of registration, which is a compliance ruling). This item is narrower and
not blocked by that ruling: whatever is decided about the wall, an unset environment
variable must never resolve to the production property.

Do not fix by editing `.env` — the fallback itself is the defect.

### 2026-08-21 — build-lead (fix-batch-F) — FIXED, handed to quality-lead

**Prior art.** One Google Analytics loader exists in the whole repository:
`loadGoogleAnalytics()` in `resources/js/utils/cookieConsent.js:144`. Verified by
grepping every `.php/.blade.php/.html/.js/.vue` outside `public/build` and
`public/m-build` for `googletagmanager` / `G-3Y8DL3QB09` — the only other hit is
the CSP allowlist at `app/Http/Middleware/SecurityHeaders.php:61-62`, which is
not a loader. `resources/js/services/analyticsService.js` is Plausible, not
Google. **No second copy to converge (Rule 20 satisfied by inspection, not
assumption).**

**Acceptance 1 — no fallback to a production identifier. DONE.**
`resources/js/utils/cookieConsent.js:22` is now
`const GA_ID = import.meta.env.VITE_GA_ID || '';`. `loadGoogleAnalytics()` already
returned early on a falsy id (`:145`), so an unconfigured environment now loads
nothing at all rather than the live property.

**The fallback was the ONLY thing setting the id anywhere.** `VITE_GA_ID` appears
in no `.env`, no `.env.example`, and neither build script — verified by a
repo-wide grep. Deleting the fallback alone would therefore have silently turned
off production analytics as well. The production measurement id has been moved to
where it belongs, the production build:
- `deploy/fynla-org/build.sh:40` — `export VITE_GA_ID=G-3Y8DL3QB09`
- `deploy/fynla-org/.env.production.example:127` — same, for completeness
- `deploy/csjones-fynla/build.sh:36-41` — a comment recording that staging is
  **deliberately** unset, so a later reader does not "fix" the omission
- `.env.example:116` — `VITE_GA_ID=` empty, with the reason

`.env` itself was not touched.

**Acceptance 2 — the Awin MasterTag. CHECKED, deliberately NOT changed.** It has
the same hardcoded-fallback *shape* (`resources/js/utils/awinTracking.js:23,25,26`
default the merchant id to `126105` and the URLs to the live hosts) but it does
**not** have the W-0047 defect, because its master switch already fails closed:
`VITE_AWIN_ENABLED` unset → `ENABLED === false` (`:24`) → both `shouldLoadAwin()`
(`:47`) and `loadMasterTag()` (`:58`) return before touching any of it. An
unconfigured environment loads no MasterTag. The merchant-id fallback is reachable
only where someone has explicitly turned Awin on, and removing it would silently
disable production affiliate tracking if the production build ever stopped
exporting `VITE_AWIN_MERCHANT_ID` (it currently does, `deploy/fynla-org/build.sh:45`).
That trade is worse than the risk it removes. **Flagged, not fixed.**

**Acceptance 3 — `/m`. CHECKED: no copy exists.** `grep -rn 'consent|gtag|analytics|awin' resources/mobile/`
returns two hits, both the unrelated AI-chat consent SSE event
(`resources/mobile/mixins/onboardingChat.js:429-431`). The mobile bundle loads no
analytics and no affiliate tag. Nothing to converge.

**Acceptance 4 — csjones staging. ANSWERED: it has never set `VITE_GA_ID`.**
`deploy/csjones-fynla/build.sh` exports ten `VITE_*` vars and `VITE_GA_ID` is not
among them, and there is no `.env` on the staging server that could supply it —
Vite bakes the value at build time on the developer's machine, not on the server.
So **every csjones build since the fallback landed has reported into the
production property**, for any visitor who accepted the banner.

**Date range: 2026-04-07 to 2026-08-21 (today).** The fallback was introduced in
commit `1ab710c1e`, "feat: cookie consent banner with GA gating and registration
block", dated 2026-04-07 — the first and only commit to touch that line
(`git log -S 'G-3Y8DL3QB09' -- resources/js/utils/cookieConsent.js`). The same
window applies to local development and every automated run that accepted cookies.
**It ends only when a rebuilt bundle is deployed** — the currently-deployed
`public/build/` still contains the old code.

**Tests.** `resources/js/__tests__/cookieConsent.spec.js` — 8 pass
(`npx vitest run`), three of them on this item: no script element when
`VITE_GA_ID` is unset, the configured id when set, nothing at all on decline.

**Not verified by me:** no browser verification — a persona-tester closes Rule 14's
loop independently, per my dispatch.
