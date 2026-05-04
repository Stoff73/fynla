---
type: handover
mode: context-clear
date: 2026-05-04
session: 2
branch: CMSFix
previous_session: 2026-05-04 session 1 (clear)
tags:
  - handover
  - may-2026
  - cms
  - deploy
---

# Context Clear Handover — 2026-05-04, Session 2

## Immediate state

PR #240 (`CMSFix → dev`) is open awaiting CSJ review and merge. Dev (csjones.co/fynla) is already running CMSFix at commit `4a55043` and was browser-verified end-to-end. Working tree clean. Branch sync'd with `origin/CMSFix`.

## The thread

1. CSJ reported "Network Error" on dragging a Word doc into `/admin/documents`. Console showed CSP `connect-src 'self'` blocked `POST http://127.0.0.1:8000/api/admin/documents`.
2. Root cause: the new `documentArticleService.js` imported the bare global `axios`, whose `bootstrap.js:27` baseURL is hardcoded to `http://127.0.0.1:8000` regardless of which host loaded the page. Page loaded at `localhost:8000` → cross-origin → CSP blocked. Every other service routes around this latent bug via `@/services/api`; the new service was the only one that didn't.
3. Fixed in `5fc22ee` (one file, +10/-10) — aligned with the project's API Services Pattern.
4. Verified locally via Playwright: login → upload `sample-with-images-and-tables.docx` → 201 → publish 200 → public render at `/articles/rich-sample-title`.
5. Wrote `May/May4Updates/deployCMS.md` (`9d50768`) — file lists from `git diff` against origin/dev (42 files, CMS-only) and origin/main (142 files, includes news/RSS/lifecycle bundle from PR #238 already on dev). Server-path corrections for csjones sibling-dir layout. Composer install step, rollback procedure, smoke checklists.
6. Pushed CMSFix.
7. CSJ said "upload to dev". Build failed on `@tiptap/extension-table` — Rollup strict ESM rejected the default-import; verified with `node -e "Object.keys(require('@tiptap/extension-table'))"` that all 10 extensions have named exports. Fixed in `4a55043` (one file, +10/-10).
8. Re-built, rsynced `public/build/` (8.8M) and 15 PHP/blade/route/migration files + `composer.json/lock` to `~/www/csjones.co/fynla-app/`.
9. SSH: `composer install --no-dev` (mews/purifier 3.4.4 installed), `migrate --force`, cache clear, merged `build.old/` chunks for in-flight sessions.
10. Browser-verified on dev: same flow as local, plus the editor view loaded cleanly (Tiptap fix works in production).
11. Opened PR #240 with branch-naming caveat flagged — `CMSFix` doesn't follow the mandatory `feature/csj/<task>` convention from CLAUDE.md.

## Files touched this session (all committed and pushed)

- `5fc22ee` — `resources/js/services/documentArticleService.js` (CSP/cross-origin fix)
- `4a55043` — `resources/js/views/Admin/Documents/DocumentEditor.vue` (Tiptap named imports)
- `9d50768` — `May/May4Updates/deployCMS.md` (new doc, 299 lines)

## What the next Claude needs to know

- **PR #240** open `CMSFix → dev` — CSJ to review/merge. Branch is `CMSFix`, not `feature/csj/...`; CLAUDE.md says non-conforming branches should be closed. Caveat flagged in PR body — CSJ's call.
- **Latent `bootstrap.js:27` hardcode** flagged but NOT fixed. One-line cleanup: `'http://127.0.0.1:8000'` → `window.location.origin`. Out of scope for CMSFix per scope discipline; worth a separate PR.
- **Tiptap v3 = named exports only** (no `default` in ESM). Default imports work in dev (esbuild CJS interop) but break Rollup's strict ESM in production. Pattern: any `import X from '@tiptap/extension-Y'` must become `import { X } from '@tiptap/extension-Y'`. Worth saving as a memory if it bites again.
- **Dev DB** now has "Rich Sample Title" published article id=1 (test fixture; deletable from `/admin/documents`).
- **`~/www/csjones.co/fynla-app/public/build.old/`** (~78M) — preserved per `feedback_warn_before_spa_rebuild.md`. Deletable once confidence high.
- **Prod merge will carry more than just CMS.** `origin/dev` is 42 commits ahead of `origin/main` because PR #238 (news/RSS/lifecycle) is already merged to dev. The eventual `dev → main` will run **three** migrations on prod, not one. Documented in `deployCMS.md`.
- **csjones dev DB verification codes** can be fetched via SSH + artisan tinker (used this session for chris@fynla.org). Pattern: `ssh ... "cd ~/www/csjones.co/fynla-app && php artisan tinker --execute=\"...\""`.
- **Editor previously broken** — `4a55043` fixed it. The deploy guide's "ship knowingly without editor" branch is now obsolete; editor works.

## Pick up from here

**Most likely next action:** CSJ reviews PR #240, decides on the branch-naming caveat (rename + reopen, or override and merge), then merges.

**If user wants to ship to prod after merge:** follow `May/May4Updates/deployCMS.md` "Deploy: production (fynla.org)" section. Three migrations will run.

**Otherwise:** open the `bootstrap.js:27` cleanup PR (one-line). Or whatever CSJ asks.

## Context hints

- Active branch type: feature
- Behind `origin/CMSFix`: 0 commits — pushed clean
- Behind `origin/main`: 35 commits ahead (entire CMSFix work + the news bundle from PR #238 on dev)
- Uncommitted: none — working tree clean (untracked stuff `campaigns/`, `fyn/`, `personas/`, `prompts/`, `tools/`, `May/May1Updates/deployFynFix.md` is CSJ's WIP, NOT touched)
- Last commit: `4a55043` fix(documents): use named imports for tiptap extensions
- Open PR: #240 `feat(cms): Document Articles CMS — drag-drop .docx import + publish` → dev
