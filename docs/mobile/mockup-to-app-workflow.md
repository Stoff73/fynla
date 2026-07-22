# HTML Mockup → Localhost Mobile App — Build & Test Workflow

A repeatable process for turning a static HTML mock-up (e.g. `/m-mockup/dashboard`,
served from `public/pages/mobile-dashboard-mockup.{php,css,js}`) into the live
mobile SPA served inside the `/m` iframe on `localhost:8000`.

## Where the mobile dashboard actually lives

The `/m` route is an **iframe shell**:

```
/m  (mobile-host.blade.php — iframe shell)
  └─ iframe src = /m/landing  →  "Get started"  →  /m/app
        └─ /m/app  (mobile-app.blade.php — boots the isolated Vue SPA)
              └─ inner Vue router  →  /dashboard
                    └─ resources/mobile/views/Dashboard.vue   ← THE DASHBOARD
```

- `mobile-host.blade.php` is **only the iframe wrapper**. Do not put dashboard
  markup there.
- The real dashboard is the Vue component `resources/mobile/views/Dashboard.vue`
  (styles in `resources/mobile/views/dashboard.css`), bundled by
  `vite.mobile.config.js` into `public/m-build/` and served by
  `mobile-app.blade.php` (which reads `public/m-build/manifest.json` at runtime).

## Conversion steps

1. **Read the mock-up in full** — markup (`*.php`), styles (`*.css`), behaviour
   (`*.js`). The mock-up is the approved design source of truth.
2. **Port the markup into `Dashboard.vue`'s `<template>`**, keeping the exact
   `md-*` BEM classes so the ported CSS applies unchanged.
3. **Copy the mock-up CSS into `resources/mobile/views/dashboard.css`** and import
   it from the component with `<style src="./dashboard.css"></style>` (non-scoped:
   the SPA is isolated and the `md-*` prefix is unique). Design tokens
   (`--raspberry-500` etc.) come from the mobile token stylesheet, not global.css.
4. **Re-implement the mock-up's vanilla JS as Vue methods/computed** (accordion
   select, complete/skip with fade, level recompute, confetti, drawer, Fyn
   overlay). Replace any hard-coded demo data with real API data.
5. **Wire real data** via `resources/mobile/api.js`
   (`apiGet`/`apiPost`/`apiStream`) against existing endpoints — never invent new
   backend contracts for a redesign.
6. **Respect Rule #16** — only functional icons. The mock-up header documents its
   own stance (hamburger / close / tick are functional-allowed); reproduce that,
   do not add new decorative glyphs.

## Build

```bash
./scripts/build-mobile.sh
```

This wraps `vite build` with the mobile config. **It passes the config as an
absolute path and `cd`s into the repo root first** — `vite build --config
vite.mobile.config.js` resolves the config relative to the process CWD, which in
a git-worktree / multi-checkout setup can silently compile *stale* source from
the wrong copy. Symptom: the output bundle hash never changes and your edits
never appear in the browser. Always build via this script (or
`npx vite build --config "$PWD/vite.mobile.config.js"`), not a bare
`npm run build:mobile`, when CWD is ambiguous.

After building, `mobile-app.blade.php` reads the fresh `manifest.json` on the
next request (the script clears Laravel's view/cache/config so no stale manifest
is served).

## Test — design/styling AND interactions (NON-NEGOTIABLE)

Testing is done in a real browser (Playwright) against
`http://localhost:8000/m/app/dashboard`, authenticated with a real Sanctum token.

### Auth for testing (local dev)

```bash
php artisan tinker --execute="\$u=App\Models\User::find(1); echo \$u->createToken('uitest')->plainTextToken;"
```

Then in the page (after navigating to `/m/app/login` first so localStorage is
same-origin):

```js
localStorage.setItem('m_scaffold_token', '<token>');
```

Use a **data-rich user with recommendations** (e.g. `john@example.com`, id 1 —
"full data"). Recommendations are computed on the fly from the module agents
(`RecommendationsAggregatorService`); a user with no financial data returns an
empty dashboard, which is correct, not a bug.

### Styling assertions (computed styles)

- Header + scroll-hero use the horizon→raspberry gradient.
- Level ring arc stroke = `rgb(232, 62, 109)` (`#E83E6D`), `stroke-linecap: round`.
- Recommendations carousel background = `rgb(253, 240, 244)` (`--light-pink-50`).
- Exactly 4 finance panels; active accordion card light-pink, inactive eggshell.

### Interaction assertions (must click/fill/submit, then verify)

- Accordion: clicking a category card / dot re-renders that category's recs.
- Complete: clicking the check ticks the row, fades it, re-orders done to the
  bottom; the level wheel advances by completed-action count
  (L1→2 = 3 actions, +2 per level, capped at 10) and a level-up fires confetti.
- Skip: removes the rec (or pulls in the next pooled one) with a fade.
- Finance card: navigates to `/m/app/module/<slug>` (ModuleDetail).
- Fyn: suggestions are questions built from the user's recommendations; sending
  one POSTs to `/api/ai-chat/conversations` then streams
  `/api/ai-chat/conversations/{id}/messages` (the existing chat backend) — verify
  via the network panel that the OLD `/api/conversations` endpoint is NOT used.

A change is only "done" when every interaction above has been exercised in the
browser on the freshly-built bundle and the result observed (DOM + network).
