---
id: W-0484
title: The browser-smoke gate is red for two unrelated reasons, and neither is the change under test
mission: persona-run-peak_earners-2026-08-20
branch: estate-copy-and-m-handoff
owner: main-inference
reviewers: [quality-lead]
status: gated
claimed_by: null
severity: high
surfaces: [web]
created: 2026-08-24T20:35:00Z
blocked_by: []
gate: null
prior_art_checked: 2026-08-24
prior_art_found: []
prior_art_outcome: none
constitution_refs: [07-quality-bar]
source: found while checking PR #713 (Phailanx, emails) whose browser-smoke had gone red
---

## Intent

`@smoke desktop landing and preview dashboard boot` fails, and **it is not the fault of
whatever PR happens to be under test.** Two independent causes, stacked:

### Cause 1 — the consent fixture satisfies the wrong mechanism (FIXED here)

Consent is remembered in **two** places:

| Mechanism | Read by |
|---|---|
| `localStorage.cookie_consent` | the SPA banner |
| cookie `fyn_cookie_consent` | the server-rendered public pages (`public/pages/js/cookie-consent.js:21`) |

`tests/E2E/fixtures/app.js` pre-accepted only the **localStorage** one. So on `/` the
server-rendered banner still rendered, and its `.cc-backdrop` covers the viewport and
intercepts pointer events — every click on the landing page retried until it timed out.

**Timeline, which is why this looked like someone's regression:** the public-page banner
landed **2026-08-22 20:22** (`d5fe9f9f7`, `afa6b4ad0`). PR #713's first CI ran the same
day at **13:11** and browser-smoke PASSED. Re-basing that PR onto current dev picked up
the banner, and the gate went red — **on a pull request that only touches email
templates.** Verified none of the 43 commits in `c5e678131..526327655` touch
`public/pages/`.

**Fixed** in this branch: the fixture sets the cookie as well. Verified locally — the
click succeeds and the test now reaches `/dashboard` and renders `main`.

### Cause 2 — insight images violate the Content Security Policy (NOT fixed)

With cause 1 out of the way the test gets further and fails on `runtimeErrors`:

```
Loading the image 'http://localhost/storage/insights/bespoke/how-much-to-retire-uk.jpg'
violates the following Content Security Policy directive: "img-src 'self' …"
```

The images are addressed at **`http://localhost`** — no port — while the page is served
from `127.0.0.1:8000`, so `'self'` does not match and the browser blocks them. Three
bespoke insight images, several times each.

**This is a real user-visible defect, not only a test problem:** any environment whose
stored image URLs disagree with its serving host shows those insights with **broken
images**, and the console fills with CSP violations. The URLs look seeded absolute
(`ExistingInsightsMetadataSeeder` / bespoke articles) rather than resolved from
`APP_URL` at render time.

## Acceptance

1. The fixture fix stands (done) and `@smoke desktop …` is green.
2. Insight image URLs resolve **relative to the serving host** rather than being stored
   absolute — or the seeder writes them relative and the view composes the host.
3. Whether CI's own `APP_URL` masks or reproduces cause 2 is stated, because the gate
   must be trustworthy on CI, not only locally.
4. A note on the **gate's credibility**: this failed for weeks-old environmental
   reasons while attributing the red to whichever PR ran next. Worth a line in the
   contributing notes.

## Working notes

- 2026-08-24 — Cause 1 fixed and verified locally. Cause 2 diagnosed, not fixed —
  different subsystem, and end of session. **PR #713 was merged with browser-smoke red**
  after proving the red is unrelated to it (all PHP suites, lint, builds, frontend-tests
  and logic-guard pass; CI is not an enforced merge gate per `CLAUDE.md`).
