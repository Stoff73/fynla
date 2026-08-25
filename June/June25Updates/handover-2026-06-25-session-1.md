---
type: handover
mode: end-of-day
date: 2026-06-25
session: 1
branch: main
previous_session: 2026-06-23 session 1
---

# Handover — 2026-06-25, Session 1

> Work performed 2026-06-24 (end-of-day wrap, dated to the next working session).

## Where we left off
Merged + deployed **PR #572** (external contributor Phailanx: SEO fixes + homepage Latest-news bar + SPA→PHP marketing nav handoff + fresh-checkout fix) all the way from feature branch → dev (csjones) → main (prod, fynla.org). Released via dev→main PR **#573**. During csjones verification I found + fixed one bug (news-bar links missing `FYNLA_BASE` on the subdirectory). Both environments are live and browser-verified; prod log clean. Working tree clean.

## What shipped today
- **#572** (Phailanx, base dev) — SEO mojibake repair across 24 public marketing pages; `/public/`→clean-URL **301 canonical rule** in `public/.htaccess` + `deploy/fynla-org/.htaccess`; sitemap tidy (dropped thin `/version`, linked `/help` in footer); consumer-focused homepage (`Financial Planning Software for UK Households | Fynla`) + `/features` titles; **homepage "Latest news" bar** populated from `/api/news`; **SPA→PHP marketing nav handoff** (router `beforeEach` full-loads server-rendered marketing routes, `/insights*` stays SPA); fresh-checkout `.gitignore` fix (`storage/framework/views`+`cache`). Approved the logic-guard protected-file gate (router) via `logic-change-approved` label, then admin-merged.
- **`3e9e7cb` fix(home)** — news-bar *supporting* cards now prefix `window.FYNLA_BASE` (`public/pages/js/index.js:310`). They hardcoded `/insights/<slug>` and 404'd on the `/fynla` subdirectory (csjones); the featured card already did it right. Prod-neutral (FYNLA_BASE='' on root).
- **`80aa2aa` chore(home)** — bumped `index.js?v=114→115` (cache-bust; the asset has a 1-year max-age keyed on `?v=`).
- **#573** — dev→main release of the above.
- Deployed to **BOTH** dev (csjones) and prod (fynla.org).

## What's in flight (NOT done)
- Nothing started-and-unfinished. The session's task is fully complete and live.
- **Standing optional carry-overs** (from the 2026-06-23 handover, still optional, not this session's scope): tier-2-account eyeball of the two Estate `sanitizeHtml` surfaces (LPA, Will review) on prod; confirm no un-guarded `currentAccessToken()->id` remains (TransientToken family — **already spot-checked clean this session at session-start: the only two `->id` sites, `AdminController:128` + `SessionService:58`, both carry the `instanceof PersonalAccessToken` guard**).
- **Untracked carry-overs left in place** (not this session's work, carried for several sessions): `June/June19Updates/`, `June/June15Updates/` (2 files), `docs/security/security-review-2026-06-09.md`, `docs/mobile/designer-brief.pdf`, excalidraw `__pycache__`. Deliberately not committed — they belong to other sessions / are incidental.

## Deploy status
**Deployed to BOTH environments.** Nothing pending.
- **dev (csjones):** rsync `public/build/` (326 files) + `git pull origin dev`. **Gotcha handled:** the pull aborted because `public/.htaccess` is `skip-worktree` AND locally modified (csjones's `/fynla` RewriteBase version) and #572 changes that file in the repo → preserved the local file (backup → `--no-skip-worktree` → `checkout --` → pull → restore → re-`skip-worktree`), so csjones kept its `/fynla` htaccess and the prod-only `/public/→fynla.org` rule never reached it. Cache chain ran (config:cache, no route:cache). The FYNLA_BASE fix + version bump pulled in two follow-up `git pull`s (static JS, no rebuild).
- **prod (fynla.org):** non-git manual upload. Layout: `public_html/` = Laravel root, `public_html/public/` = docroot, served htaccess = `public_html/public/.htaccess` (RewriteBase `/`). Rsync `public/build/` (326) + `public/pages/` (47 — healed some pre-existing prod page drift too) + `public/sitemap.xml` + `public/.htaccess` (verified prod's current matched repo minus exactly the PR's `/public/` rule — no clobber). `migrate --force` no-op; cache chain ending `config:cache`. **Manifest md5 local↔prod identical** (`d6526fff…`); asset 200 + correct MIME; **prod log 0 errors** post-deploy.

## Tech debt found this session
Net **clean**. tech-debt-session audit of my two authored one-liners: both debt-*removing* (the FYNLA_BASE fix aligned the only un-based URL in `index.js` with the existing pattern at lines 273/297/415/426/491/498; the `?v=` bump is the file's own cache-bust idiom). No report file written (0 issues). PR #572 itself was a reviewed external contribution, not audited here.

## Known issues / blockers
**None broken.** Prod healthy — log frozen at Jun 16 (no new errors; my live browser traffic on prod generated none). All journeys browser-verified on both envs.

## Rules reinforced this session
- **csjones `public/.htaccess` is `skip-worktree` AND locally modified** → a `git pull` that changes that file in the repo *aborts* ("local changes would be overwritten"). Must preserve+restore the local `/fynla` version around the pull. DEPLOY.md:61 documents the skip-worktree but not the abort-on-incoming-change. (Candidate memory — vault-sync agent assessing; check `reference_csjones_*` / DEPLOY.md first.)
- **The FYNLA_BASE class of bug:** any app-internal URL built in `public/pages/*.js` must use `(window.FYNLA_BASE||'')` — invisible on root-deploy prod, 404 on the `/fynla` subdirectory. Bumping `?v=` is mandatory when editing those JS files (1-year asset cache).
- **Never recommend deploy** holds, but CSJ *instructed* this prod deploy — executed their decision, didn't recommend.
- **logic-guard** is a Phailanx-only protected-file gate; approve via the `logic-change-approved` label (+ admin-merge), not by bypassing review blindly.

## Next session should
- **Nothing mandated** — PR #572 complete and live on both environments.
- Optional: a fresh-eyes prod check of the Latest-news bar / SPA→PHP handoff on a real device.
- Optional standing carry-overs above (tier-2 Estate sanitizeHtml eyeball).
- Housekeeping already done this session: stale `deadcode-cleanup` worktree + `/tmp/fynla-main-planning-backup/` removed at session-start.

## Context hints
- Active branch type: **mainline** — main = origin/main = prod (`0/0`).
- Behind origin/main by: 0.
- Uncommitted: none (tracked); only the long-standing untracked carry-overs listed above.
- Last commit: `cc8d677` Merge pull request #573 from Stoff73/dev.
- dev (origin/dev) is at `80aa2aa` — behind main only by the docs/handover commits main accrues post-release (normal).
