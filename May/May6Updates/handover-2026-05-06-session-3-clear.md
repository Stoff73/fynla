---
type: handover
mode: context-clear
date: 2026-05-06
session: 3
branch: dev
previous_session: 2026-05-06-session-2 (context-clear)
---

# Context Clear Handover — 2026-05-06, Session 3

## Immediate state

Bug-fix loop closed: CSJ's reported "click publish, nothing happens, no article shows up on /insights" is fully resolved on csjones live. Three layered defects fixed (cache invalidation, CDN poisoning, storage 403). Two commits pushed to `dev`. Production templates updated in source but **not** deployed to fynla.org — CSJ to ship at the next `dev → main` release.

## The thread

- Session-start ran clean — branch up-to-date, dev server up on :8000 + :5173, DB seeded.
- CSJ confirmed drag-and-drop works locally (deferred from session 2). Built csjones bundle (`app-DFcwXVfE.js`), CSJ loaded `~/.ssh/fynlaDev`, deployed via preserve-old-chunks pattern, cache cleared. Drag-only DropZone now live on csjones — that part of session 2's plan is complete.
- CSJ then reported the publish bug. Systematic-debugging Phase 1 traced through: `DocumentEditor.vue` → `documentArticles/publish` Vuex action → `POST /api/admin/documents/{id}/publish` → `DocumentArticleController::publish` (sets `status='published'`, `published_at=now()`). Backend correctly persisted the publish. Bug was downstream.
- Found root cause #1: `Public/InsightController::index` caches the merged InsightArticle + DocumentArticle list for 10 min keyed on `insights.list_version`. Only `InsightArticleObserver` busts the version. **No observer existed for DocumentArticle.** Fixed by mirroring `InsightArticleObserver` for DocumentArticle and registering in `AppServiceProvider`. Verified locally via tinker (cache version 8→9→10→11 across create/publish/delete) and end-to-end controller simulation (stale cache → publish → fresh query returns new article).
- Deployed observer + AppServiceProvider mod to csjones. `/api/insights?_cb=N` now returned the doc article (`nootropic_stack`) at top of list.
- But the SPA on `/insights` still showed only legacy bespoke articles. Root cause #2 surfaced: **CDN edge poisoning.** SiteGround's nginx proxy had a stale `text/html` response cached for the bare `/api/insights` URL — content was a "Workflow – Immerse in the AI Era" page from a different host (`host-header: 8441280b...`). axios got HTML back, parsed badly, `state.insights.list` ended up `undefined`, page fell back to hardcoded `legacyArticles`. Adding `_t=Date.now()` to `insightsService.list()` made every SPA call URL-unique → cache miss → fresh JSON.
- After CSJ pushed back ("why would I need to purge every time an article is uploaded, this is not a solution"), implemented the proper Apache-layer fix: `RewriteRule ^api/ - [E=FYNLA_API:1]` placed in the main mod_rewrite block BEFORE the front-controller `[L]` rule (otherwise `[L]` terminates the rewrite phase first), then `Header always set Cache-Control "no-store..." env=FYNLA_API` (and matching `env=REDIRECT_FYNLA_API` for the post-rewrite phase). Verified: `/api/insights?_=N` now returns `cache-control: no-store, no-cache, private, must-revalidate, max-age=0`; `/` (SPA) still returns Laravel's `private, must-revalidate` (no over-broad scope).
- While diagnosing, the 8 console errors on /insights (all 403s on `/storage/insights/bespoke/*.jpg`) led to root cause #3: SiteGround restricts symlink traversal regardless of `+FollowSymLinks` or `+SymLinksIfOwnerMatch`. Test: a real file at `public/storage-real-test/test.txt` returned 200; the symlinked path 403'd. Fix was a Laravel `Route::get('/storage/{path}')` route (before SPA catch-all) that streams from `Storage::disk('public')` with 1-year browser cache + `..` traversal rejection. Removed the wrongly-blanket `RedirectMatch 403 ^/storage/` from all three .htaccess templates.
- Final csjones verification: `nootropic_stack` renders as Featured hero on /insights, `Rich Sample Title` in side panel, all 8 bespoke insights in Browse all (7) grid, article body loads at `/insights/nootropic-stack`, **zero console errors**.

## Files touched

### New
- `app/Observers/DocumentArticleObserver.php` — mirrors `InsightArticleObserver`'s `bustCaches()` (forget `insights.featured` + increment `insights.list_version`)

### Modified
- `app/Providers/AppServiceProvider.php` — registers `DocumentArticleObserver`
- `resources/js/services/insightsService.js` — adds `_t=Date.now()` to `list()` (belt-and-braces while legacy CDN entry expires)
- `routes/web.php` — new `/storage/{path}` route before SPA catch-all
- `deploy/csjones-fynla/.htaccess` — removed `RedirectMatch 403 ^/fynla/storage/`; added `RewriteRule ^api/ - [E=FYNLA_API:1]` to main rewrite block; added scoped `Header always set Cache-Control "no-store..." env=FYNLA_API`/`env=REDIRECT_FYNLA_API`
- `deploy/fynla-org/.htaccess` — same shape as csjones-fynla but `/api/` (no `/fynla/` prefix)
- `public/.htaccess` — same as fynla-org template (production root)

### Created (docs)
- `May/May6Updates/deploy-2026-05-06-session-3.md` — deploy note for csjones

### Commits (this session, on dev)
- `92ac8ae` `fix(insights): bust merged cache when DocumentArticles publish + SPA cachebuster`
- `574ba5f` `fix(infra): Laravel-served /storage route + scoped Cache-Control no-store on /api/*`
- (pending session-end commit — handover + CSJTODO + deploy note)

## Memory files written this session

- `~/.claude/projects/-Users-CSJ-Desktop-fynla/memory/feedback_siteground_hosting_lore.md` (NEW, by vault-sync subagent) — three SiteGround patterns: symlink traversal 403 + Laravel-route workaround, `.htaccess` env-var ordering before `[L]` flag, CDN cache poisoning (foreign `host-header`) + permanent `Cache-Control: no-store` fix. `MEMORY.md` index updated.

## What the next Claude needs to know

1. **Production fynla.org has the same three latent bugs.** The .htaccess `RedirectMatch 403 ^/storage/` is in `deploy/fynla-org/.htaccess` and `public/.htaccess` — it'll bite the moment any DocumentArticle is published with a cover image OR any user uploads anything to `storage/app/public/`. The CDN cache-poisoning prevention is also currently csjones-only. Source templates are fixed; production deploy is pending the next `dev → main` release. Do **not** push these to fynla.org without CSJ explicit go-ahead — the prior session's "no deploy recommendations" memory still stands.

2. **The SPA cachebuster on `insightsService.list()` is intentionally temporary.** With `Cache-Control: no-store` now in force on `/api/*`, no NEW cached entries can form. The legacy poisoned `/api/insights` entry will TTL out on its own (or CSJ purges once via SiteGround Site Tools → Speed → Caching → Dynamic Cache → Purge). After that, the cachebuster can be reverted (one-line removal in `resources/js/services/insightsService.js`). Until then, leaving it in keeps the SPA bulletproof for the three admins uploading from different locations.

3. **The `public/storage` symlink on csjones is REMOVED, intentionally.** Don't re-run `php artisan storage:link` on csjones — Apache 403s the resulting symlink anyway, and the Laravel `/storage/{path}` route handles requests directly. `BOOTSTRAP.md` step on the storage symlink should be flagged as csjones-incompatible (note for next session). Local and fynla.org keep their symlinks (Apache there serves them fine; the new route is a no-op fallback).

4. **The `RewriteRule ^api/ - [E=FYNLA_API:1]` placement is load-bearing.** It MUST be inside the main `<IfModule mod_rewrite.c>` block, BEFORE the front-controller `RewriteRule ^ index.php [L]`. The `[L]` flag terminates the entire rewrite phase, not just one rule — putting the env-set in a separate `<IfModule mod_rewrite.c>` block (as I tried first) makes it never fire. The `Header always set ... env=FYNLA_API` lines also have to match `env=REDIRECT_FYNLA_API` because Apache exposes the var differently across rewrite phases.

5. **CLAUDE.md metric drift.** Vue Components is now 722 (CLAUDE.md says 726). Drift was already present before this session — none of today's changes touched Vue files in a way that affects count. Update opportunistically (not blocking).

## Pick up from here

If next session continues bug-fix work or CSJ has new requests, just ask. If next session is to ship today's work to fynla.org, the sequence is:

1. CSJ opens PR `dev → main` (origin/dev is now ~57 commits ahead of origin/main).
2. After merge: `git checkout main && git pull`, `./deploy/fynla-org/build.sh`.
3. Upload `public/build/` + the changed PHP files (`app/Observers/DocumentArticleObserver.php` (new), `app/Providers/AppServiceProvider.php` (mod), `routes/web.php` (mod)) and the new `public/.htaccess` to `~/www/fynla.org/public_html/`.
4. SSH to fynla.org and run `composer dump-autoload -o && php artisan cache:clear && php artisan optimize`.
5. Smoke test https://fynla.org/insights and confirm `cache-control: no-store` on `/api/*`.
6. Note: fynla.org may already have its own `public/storage` symlink working — leave it. The new Laravel route is a no-op fallback when the symlink resolves.

If the legacy SiteGround proxy cache for csjones `/api/insights` (no query) is still serving the "Workflow" HTML page, ask CSJ to one-time purge via Site Tools.

## Outstanding (carries from earlier sessions, lower priority)

- `dev → main` release PR (origin/dev now ~57 commits ahead of origin/main; defer until ~24h csjones soak under preview-mode use)
- `appMapping/currentState/*.md` refresh sweep — 26 docs at 2026-03-02/12 mtime
- `ProtectionDashboard.vue` — 7 Vue render warnings (pre-existing one-file PR)
- `deploy/csjones-fynla/BOOTSTRAP.md` — add `--exclude='/public/.htaccess'` to rsync example AND remove the `php artisan storage:link` step (csjones-incompatible)
- Future PR bodies must use absolute repo paths, not vault-only paths
- CLAUDE.md metric drift: Vue Components 722 actual vs 726 documented

## Known issues / blockers

- **Legacy CDN entry on csjones for bare `/api/insights` URL.** Still serves stale text/html "Workflow" page (`x-proxy-cache: HIT`). The SPA cachebuster sidesteps this for users; one-time SiteGround Site Tools purge clears it permanently. Not blocking — admins can keep publishing.
- **Production fynla.org untouched today.** Same .htaccess template bugs latent there but no current user impact (no public DocumentArticles published with cover images, presumably).

## Untracked at session end (carried, intentional)

```
FCA-Supercharged-Sandbox-Application-Draft.md
FCA/
FCAsuperchargeApp.md
Fynla-Narrative-Memo-Template.docx
May/May1Updates/deployFynFix.md
campaigns/   fyn/   personas/   prompts/   tools/
```

May 1 Fyn AI prompt-engineering scratch dirs and FCA application drafts. Not part of any current work.

## Context hints

- Active branch type: mainline (on `dev`)
- Behind origin/main by: ~57 ahead of origin/main, in sync with origin/dev
- Uncommitted (pre session-end commit): handover + CSJTODO + deploy note
- Last session-3 commit: `574ba5f` `fix(infra): Laravel-served /storage route + scoped Cache-Control no-store on /api/*`
- csjones live JS hash: `app-DvOc0GPe.js` (rebuild includes the SPA cachebuster on `insightsService.list()`)
- Pre-recon rollback tags on origin: `pre-recon/dev` (`dc335b3`), `pre-recon/persona-split` (`1bf89e8`)
- SSH key for csjones: `~/.ssh/fynlaDev` (passphrase, requires `ssh-add` per session)
- Dev servers running: Laravel `:8000` + Vite `:5173`
