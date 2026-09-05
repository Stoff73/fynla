# 4 September 2026: dead-component verification

- `dead-components-verification-2026-09-04.md` — the report. Read section 1, 6 and 10 first.
- `reach.mjs` — walks real imports from the Vite entries and lists unreachable components.
  Run from the repo root: `OUT=unreachable.txt node reach.mjs resources/js/app.js resources/mobile/main.js`
- `unreg.mjs` — lists PascalCase tags used in reached `.vue` files with no import or registration.
  Run: `node unreg.mjs reached.txt` (set `REACHED=reached.txt` on the reach run first).
- `unreachable-components.txt` — the 152, relative to `resources/js/components/`.
- `guard-flagged-components.txt` — the 79 the current Pest guard reports.

Sort both lists with `LC_ALL=C sort` before `comm`. Mixed collations produced a false result once.
