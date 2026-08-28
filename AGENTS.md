# AGENTS.md

**The rules for this repository live in [`CLAUDE.md`](./CLAUDE.md). Read it before doing anything.** This file is a pointer, not a copy — it used to be a fork of `CLAUDE.md` and drifted, which is exactly the failure Rule 20 exists to prevent.

Also read the nested `CLAUDE.md` in whichever directory you are working in: `app/Http/`, `app/Services/`, `database/`, `tests/`, `resources/js/`, `ios-native/`.

Deeper context is kept in skills under `.claude/skills/` rather than loaded every session — notably `fyn-architecture` (the Fyn AI contract), `data-integrity-traps` (validation, columns, money bases, values that never reach the screen) and `test-failure-forensics` (why a test cannot fail, and why a green suite goes red).

## The four that must not wait for a second read

- **NEVER `migrate:fresh` or `migrate:refresh`** — they drop all tables. Reseed with `php artisan db:seed`.
- **NEVER `php artisan optimize` or `route:cache`** on this app — the compiled matcher lets the SPA catch-all shadow `/`. Use `route:clear`; re-cache config only.
- **NEVER `--env=testing`** with artisan — it resolves to the dev database and wipes it.
- **Never deploy a dev build to production or a prod build to dev.** The build scripts set different `VITE_BASE_PATH`/`RewriteBase`; the wrong one is a blank page or 404 loop with no error.
