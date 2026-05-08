---
type: handover
mode: end-of-day
date: 2026-05-07
session: 1
branch: main
previous_session: 2026-05-06-session-5 (context-clear) + this end-of-day wrap
---

# Handover — 2026-05-07, Session 1

## Where we left off

**Local + dev + production are all in sync at `3c69ecd`.** The May 6 release is fully shipped: PR #245 (60-commit `dev → main` release including persona-split eval/tax-strategy/AI-audit, onboarding Fyn, Document Articles CMS, news/lifecycle emails, tax strategy household inputs) deployed to fynla.org with zero data loss (58 users pre = 58 post, 121 → 132 tables), then PR #246/#247 follow-up shipped a Laravel middleware (`ApiCacheHeaders`) that fixed the `/api/*` no-store cache-control header that the .htaccess approach couldn't deliver on the SiteGround prod vhost. Tomorrow morning the system is healthy and at rest.

## What shipped today

From `git log --since="midnight"`:

- `3c69ecd` Release: Cache-Control middleware fix (PR #246) — dev → main merge
- `95bb917` fix(infra): Cache-Control no-store via Laravel middleware (#246) — feature branch → dev merge
- `c361c97` fix(infra): Cache-Control no-store via Laravel middleware — feature commit
- `eddeffa` Release: dev → main — May 6 release (PR #245, 60 commits brought to main)
- `29afdfd` docs(session): context-clear handover 2026-05-06-session-5
- `53e1cea` docs(session): context-clear handover 2026-05-06-session-4 + production deploy spec
- `18558c5` docs(deploy): switch csjones from manual rsync to git-pull, document drift fix

Production deploy actions (not commits):
- Snapshotted prod DB → `~/db-snapshot-pre-deploy-20260506-131738.sql.gz` (2.9 MB gzipped, valid MySQL 8.4 dump, 5,203 lines) on the prod box
- Rsynced source dirs (`app/`, `routes/`, `config/`, `database/`, `bootstrap/`, `resources/`, `composer.{json,lock}`, `vite.config.js`) and `public/build/` and the prod-template `public/.htaccess` to fynla.org
- Production `composer install --no-dev --optimize-autoloader` ran clean (27 packages installed/upgraded — mews/purifier, ezyang/htmlpurifier, symfony/yaml are new)
- 30 migrations applied in order, zero errors
- 4 selective seeders run (TaxConfiguration, DiscountCode, SavingsActionDefinition, NewsArticle)
- Browser smoke pass: login `chris@fynla.org` → dashboard (Net Worth £618,250, all module cards, Tax 2026/27 active) → /insights (5 articles, images, categories) → zero JS console errors

## What's in flight (NOT done)

- **Revert SPA cachebuster** in `resources/js/services/insightsService.js` (`_t=Date.now()` line, ~1 line). Now redundant since `ApiCacheHeaders` middleware does the equivalent at the response layer. Requires: edit, frontend rebuild via `./deploy/fynla-org/build.sh`, upload `public/build/` to prod, `cache:clear`. Small follow-up PR.
- **Convert prod fynla.org to git checkout** tracking `origin/main` (per `deploy/csjones-fynla/BOOTSTRAP.md` §12, with `branch=main` and no `skip-worktree` since prod uses the canonical root template). After this, all three environments deploy via `git pull`. Optional, ~24h soak-period reasonable before doing it.
- **`appMapping/currentState/*.md`** — 26 docs at 2026-03-02/12 mtime. Surgical edits in repo only (no vault round-trip). Carried in CSJTODO; not blocking.
- **`Current State/DeploymentBuild.md`** — vault-sync subagent updated this today (v0.7.0 → v1.0, Feb 19 → May 6, csjones git-pull workflow noted). Could still use a once-over to add the production deploy details (composer install ordering, snapshot pattern, etc.) when convenient.
- **CLAUDE.md Vue Components count drift** — 722 actual vs 726 documented (-4). Cosmetic; update opportunistically.

## Deploy status

**Both deploys this session are LIVE on fynla.org.**

- Production HEAD: `3c69ecd` Release: Cache-Control middleware fix (PR #246)
- Build hash on prod: `app-CoVRuMpB.js`
- Smoke (re-checked 18:04 BST): `https://fynla.org/` 200 OK in 262 ms; `/api/insights` 200 OK in 265 ms with `cache-control: max-age=0, must-revalidate, no-cache, no-store, private`; `/build/manifest.json` 200 OK in 211 ms
- Backend: `app()->environment()` = production, `services.revolut.sandbox` = false, `LIFECYCLE_TEST_RECIPIENT` unset
- DB: 58 users (unchanged), 132 tables, all post-merge schema as expected (`is_eval_user` dropped, `eval_recording_sessions.preview_user_id` renamed correctly, all new tables created)
- Errors logged today: 1054 total — last error at `[2026-05-06 14:25:01]` BST (transient Mews/Purifier during the rsync→composer-install gap, 2 occurrences total). Zero errors since 14:25 BST (≈3.5h clean by EOD).
- DB snapshot retained on prod at `~/db-snapshot-pre-deploy-20260506-131738.sql.gz` for rollback

## Tech debt found this session

**Zero tech debt from this session's code.** The `tech-debt-session` skill audited the 3 files added (`ApiCacheHeaders.php`, `Kernel.php` registration, `ApiCacheHeadersTest.php`) — all clean. Helper-function pattern in the test file matches existing convention (`CheckFeatureAccessTest.php`). See `tech-debt-report.md` at repo root.

Notes that surfaced but belong to follow-up work (not this session's scope):

1. **`public/.htaccess` cache-control rules** are now functionally redundant with the middleware, but harmless on hosts where they DO fire (csjones). Could be simplified later.
2. The SPA cachebuster (`_t=Date.now()`) is the same story — redundant but harmless.

## Known issues / blockers

None. Production is green.

## Rules reinforced this session

Two new memory files added today (both promoted from vault-sync's Phase 8c suggestions):

- [feedback_siteground_prod_vhost_no_conditionals.md](memory: same-named file) — fynla.org prod vhost silently drops conditional Apache directives. Per-route response-header logic on prod must use Laravel middleware. csjones DOES support conditionals — the dev/prod vhost-level difference is real. Don't waste time debugging "why isn't my .htaccess conditional firing on fynla.org" — move it to Laravel.
- [feedback_admin_merge_pattern_for_solo_reviewer_prs.md](memory: same-named file) — `gh pr merge <N> --merge --admin` is the standing pattern when CSJ is both author and sole reviewer. Don't ask permission per-merge. Confirmed on PR #245 and #247.

## Next session should

1. **Smoke production once more after overnight soak** — `curl -sI https://fynla.org/api/insights` should still show the no-store cache-control header; landing page 200; `/insights` renders. ~30 sec.
2. **Decide on the cachebuster cleanup PR** — small one-line change in `resources/js/services/insightsService.js` removing `_t=Date.now()`, frontend rebuild, upload `public/build/` to prod, cache:clear. Can also be deferred to a future session.
3. **Decide on prod-to-git-checkout conversion** — if 24h soak is clean, run BOOTSTRAP.md §12 recipe with `branch=main` against `~/www/fynla.org/public_html/`. After: all three environments deploy via `git pull`.
4. **Optional: SiteGround Site Tools cache purge on csjones** — only if the legacy `/api/insights` poisoned-CDN entry is still observed. Manual UI step; after purge, the same `_t=Date.now()` line on csjones would also be revertable.
5. **Tail prod laravel.log briefly** if anything new lands overnight that wasn't visible by 18:04 BST today.

## Context hints

- Active branch type: mainline (on `main`)
- Local in sync with `origin/main` (0/0)
- Local in sync with `origin/dev` too — both branches at parity post-merges
- Last commit: `3c69ecd` Release: Cache-Control middleware fix (PR #246)
- `main` HEAD = `dev` HEAD logical content; both at the equivalent of post-PR-#247 state
- csjones live HEAD: not yet pulled to `3c69ecd` — it's still at `bb6458a` (last visible from session 4 records). Next time csjones gets a deploy, `git pull origin dev` will bring it forward; not blocking since prod is already at that codebase.
- Pre-recon rollback tags on origin: `pre-recon/dev` (`dc335b3`), `pre-recon/persona-split` (`1bf89e8`)
- Production rollback artefact: `~/db-snapshot-pre-deploy-20260506-131738.sql.gz` on the prod home dir
- SSH key for prod: `~/.ssh/production` (passphrase) — was loaded into agent today by CSJ via `ssh-add`. Will need re-loading next session.
- SSH key for csjones: `~/.ssh/fynlaDev` (passphrase, requires `ssh-add` per session)
- Dev servers: were running on :8000 + :5173 at session start; not explicitly stopped
- PR #245 URL: https://github.com/Stoff73/fynla/pull/245
- PR #246 URL: https://github.com/Stoff73/fynla/pull/246 (api-no-store-cache → dev)
- PR #247 URL: https://github.com/Stoff73/fynla/pull/247 (release dev → main carrying #246)
- Untracked at session end (carried, intentional): `FCA-Supercharged-Sandbox-Application-Draft.md`, `FCA/`, `FCAsuperchargeApp.md`, `Fynla-Narrative-Memo-Template.docx`, `May/May1Updates/deployFynFix.md`, `campaigns/`, `fyn/`, `personas/`, `prompts/`, `tools/`, `tech-debt-report.md` (regenerated this session, will be committed in Phase 10)
