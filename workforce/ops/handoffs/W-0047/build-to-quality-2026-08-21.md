# W-0047 — build-lead (fix-batch-F) → quality-lead

## Done

Google Analytics no longer falls back to the production measurement id.

- `resources/js/utils/cookieConsent.js:22` — `import.meta.env.VITE_GA_ID || ''`.
  `loadGoogleAnalytics()` already returned early on a falsy id (`:145`), so an
  unconfigured environment now loads nothing rather than the live property.
- `deploy/fynla-org/build.sh:40` — `export VITE_GA_ID=G-3Y8DL3QB09`. The
  production id now lives in the production build and nowhere else.
- `deploy/fynla-org/.env.production.example:127` — same value, documented.
- `deploy/csjones-fynla/build.sh:36-41` — a comment recording that staging is
  deliberately unset.
- `.env.example:116` — empty, with the reason.

Tests: `resources/js/__tests__/cookieConsent.spec.js`, 8 pass under
`npx vitest run`; three assert this item directly.

## Not done, and why

**The Awin MasterTag was checked and deliberately left alone.** It has the same
hardcoded-fallback shape (`resources/js/utils/awinTracking.js:23,25,26`) but not
the same defect: `VITE_AWIN_ENABLED` unset means `ENABLED === false` (`:24`) and
both `shouldLoadAwin()` (`:47`) and `loadMasterTag()` (`:58`) return before
reaching any of those constants. The merchant-id fallback is only reachable where
Awin has been explicitly turned on; removing it would silently disable production
affiliate tracking if the production build ever stopped exporting
`VITE_AWIN_MERCHANT_ID`. Flagged rather than changed.

**No browser verification.** My dispatch reserves Rule 14's loop for a
persona-tester.

## What you need that isn't obvious from the artefacts

**Removing the fallback changes production behaviour unless the build script is
used.** `VITE_GA_ID` was set nowhere in the repository before today — no `.env`,
no `.env.example`, neither build script. The fallback was the only thing that
ever set the id, production included. Anyone who builds production by hand rather
than through `deploy/fynla-org/build.sh` will now ship a bundle with no analytics
and no error. That is the intended failure direction, but it is a change worth
knowing about before a release.

**The contamination has not stopped yet.** The deployed `public/build/` still
contains the old code. It stops when a rebuilt bundle is deployed, not when this
merges.

## Assumptions I made

- **I am assuming `G-3Y8DL3QB09` is the correct and current production
  measurement id.** I took it from the fallback that was in the code; I did not
  verify it against the Google Analytics account, and I have no access to do so.
  If it is stale, the production build script now carries a stale id.
- I am assuming staging genuinely has no measurement property of its own, since
  nothing in the repository references a second id.
- I am assuming a hand-built production bundle is not a supported path, so the
  build script is a sufficient home for the value.

## Surfaces covered / not covered

- **web** — covered. The one GA loader in the repository is on this surface.
- **`/m`** — checked, nothing to change: `resources/mobile/` contains no analytics
  code at all (`grep -rn 'gtag|analytics|googletagmanager' resources/mobile/`
  returns only the unrelated AI-chat consent event at
  `resources/mobile/mixins/onboardingChat.js:429`).
- **iOS** — out of scope; the native app carries no web analytics bundle.

## The date-range answer the item asked for

csjones staging has **never** set `VITE_GA_ID`, so every staging build has
reported into the production property. The fallback landed in `1ab710c1e` on
**2026-04-07**; the range is 2026-04-07 → today, and it closes on the next deploy
of a rebuilt bundle, not before.
