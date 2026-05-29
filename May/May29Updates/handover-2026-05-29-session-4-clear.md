---
type: handover
mode: context-clear
date: 2026-05-29
session: 4
branch: fix/public-pages-base-path
trigger: context-handover skill (tripwire ~767k tokens)
---

# Context Clear Handover — 2026-05-29, Session 4

## Immediate state
Just finished browser-verifying the public-pages base-path fix on csjones (savetax funnel works end-to-end). CSJ approved two follow-ups — **(a) self-host Chart.js to clear a CSP block on the calculators page, and (b) rename the marketing `App\Models\Asset` class to remove the basename collision with `App\Models\Estate\Asset`**. The tripwire fired before starting either. **These two tasks are the literal next work.** Neither has been started.

## The thread
1. Session opened resuming from session-3 handover: removed the unprompted "Upgrade" tag from the sidebar (PR #427) → dev + csjones, verified. Then **released dev→main to prod (PR #428)** — the big freemium + SP1 Pass 4/5/6 release.
2. Prod deploy surfaced **accumulated deploy drift** (prod is a non-git manual-upload server; prior releases left files + 9 migrations missing). Reconciled via full-tree rsync of `app/database/config/resources/views` + ran the 9 pending migrations + backfills + `optimize`. Prod verified healthy (dashboard renders, auth works). Saved lesson to memory `reference_prod_accumulated_deploy_drift.md`. Ran `freemium:convert-trial-users` on prod (9 users → Free, CSJ-confirmed).
3. Investigated the prod pension-backfill crash (systematic-debugging): root cause = `PensionDerivedColumnCalculator` non-nullable `User $user` vs a soft-deleted owner's null relation. Fixed (PR #429 nullable + null-guard; PR #430 backfill `whereHas('user')`; PR #431 dev→main release). Verified on prod: 18/18 live DC pensions populated, 0/11 deleted-owner skipped. Confirmed the "orphaned" records are INTENTIONAL GDPR retention (not a bug — `AccountDeletionService`/`RetentionPurgeService`). Audited cross-user aggregates for soft-deleted leakage → CLEAN (no fix needed).
4. **Merged Phailanx PR #420** (savetax funnel + public PHP pages, +44k/243 files), closed #353 (superset). GitGuardian "secret" was a false-positive test password already on dev.
5. Deployed #420 to csjones → hit TWO real bugs in the merged PR, both fixed:
   - **assets-table collision**: #420's content-pipeline `create_assets_table` + `App\Models\Asset` both resolved to table `assets`, colliding with the core estate `assets` table. Fixed → `pipeline_assets` (PRs #432 file-rename, #433 actual content — a stash mishap dropped the content edits in #432, #433 fixed it; squash these two when reviewing).
   - **base-path breakage**: 63 public pages + 9 JS files hardcoded root-relative `/pages/`, `/images/`, internal links, and JS `fetch`/`location.href`. Broke on csjones `/fynla/` subdir. Fixed (PR #434) via `RebasePublicPageUrls` middleware + JS `window.FYNLA_BASE` prefixing.
6. Browser-verified on csjones: savetax funnel renders styled, interactive, completes Q1→Q4, redirects to `/fynla/savetax/plan`. Plan page 0 console errors. SPA login intact (middleware skips it via `id="app"`).
7. Calculators page test surfaced the **new CSP finding** → task (a).

## Files touched this session
All merged to dev (and #428/#431 to main/prod). Tree clean now. Branch `fix/public-pages-base-path` is the last (merged via #434).
- This session's net new code lives in PRs #427, #429, #430, #432, #433, #434 (dev) and the #428/#431 prod release.
- Key files for the NEXT tasks: `public/pages/calculators.php` (+ any page loading Chart.js from jsdelivr), `app/Http/Middleware/SecurityHeaders.php` (CSP — do NOT loosen if self-hosting), `app/Models/Asset.php` + `app/Models/Article.php` (hasMany Asset) + the pipeline services (`HeyGenService`, `ImageRendererService`, `FFmpegService`, `app/Console/Commands/PipelineProcess.php`) for the Asset→PipelineAsset rename.

## WIP commit
- None. Tree is clean (only untracked `docs/mobile/designer-brief.pdf` — NOT mine, leave it). No snapshot needed.

## Open decisions
- None blocking. CSJ said "a and b" — do both. Approach for (a): **self-host Chart.js** (download chart.umd.min.js into `public/pages/js/` or `public/build`-adjacent, reference locally) rather than adding jsdelivr to CSP — matches their same-server-assets philosophy and avoids loosening `script-src`. Approach for (b): rename class `App\Models\Asset` → `App\Models\PipelineAsset` (keep `$table='pipeline_assets'`), update `Article::assets()` hasMany + EventServiceProvider + any `use App\Models\Asset;` in the pipeline services. Verify estate code still uses `App\Models\Estate\Asset` (it does — untouched).

## Pick up from here (auto-continue contract)
1. **Branch off dev** (`git checkout dev && git pull && git checkout -b fix/chart-selfhost-and-asset-rename dev`).
2. **(a) Self-host Chart.js:** find every public page loading `https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js` (`grep -rn jsdelivr public/pages/`), download that file to `public/pages/js/vendor/chart.umd.min.js`, change the `<script src>` to the local path (it'll be base-path-rewritten by the middleware automatically). Verify on csjones: `/fynla/calculators` charts render, **0 console errors** (the CSP violation must be gone).
3. **(b) Rename marketing Asset class:** `App\Models\Asset` → `App\Models\PipelineAsset`. Update: the model file/namespace, `app/Models/Article.php` `assets()` hasMany (`PipelineAsset::class`), `app/Providers/EventServiceProvider.php`, `app/Models/User.php` (check which Asset it imports — should be `Estate\Asset`, leave that), pipeline services (`HeyGenService`/`ImageRendererService`/`FFmpegService`/`PipelineProcess`). Keep `protected $table = 'pipeline_assets'`. Run `php artisan tinker` to confirm `App\Models\PipelineAsset` resolves and `App\Models\Estate\Asset` still → table `assets`. Run any pipeline tests.
4. PR each (or one combined PR) → dev, admin-merge, deploy to csjones (`git pull origin dev` + cache clear; Chart.js is committed source so no rebuild), re-verify calculators charts in browser.
5. Loop until calculators page shows 0 console errors AND charts render on csjones.

## What the next Claude needs to know
- **csjones = git checkout tracking origin/dev.** Deploy = `git pull origin dev` + cache clear. Upload `public/build/` only for SPA changes (NOT needed for these PHP/JS-source tasks). SSH: `ssh -p 18765 -i ~/.ssh/fynlaDev u163-ptanegf9edny@ssh.csjones.co`, cd `~/www/csjones.co/fynla-app`. Prod SSH key (`~/.ssh/production`, `u2783-hrf1k8bpfg02@ssh.fynla.org`) is loaded in the agent — CSJ authorized scp for the prod release earlier this session.
- **The base-path middleware (`RebasePublicPageUrls`) is a no-op on prod (root) and only rewrites static pages (skips Vue SPA via `id="app"`).** Self-hosted Chart.js referenced as `/pages/js/vendor/...` will be auto-rewritten to `/fynla/...` on csjones by the middleware — so use a root-relative path, the middleware handles the base.
- **#420 is NOT on prod.** Only the pension fixes (#431) and the big freemium release (#428) reached prod this session. #420 + its fixes (#432/#433/#434 + the upcoming a/b) live on dev/csjones only. When #420 eventually goes to prod: the `pipeline_assets` migration fix is REQUIRED (prod has core `assets`); base-path middleware is a no-op there; the Chart.js CSP issue WOULD affect prod calculators (so task (a) matters for prod too).
- **Decorative `img` icons** on the savetax funnel option buttons — possible Rule #16 concern for new code (public marketing surface, not explicitly banned). Flagged, low priority, not in scope for a/b.
- Vite :5173, Laravel :8000 locally. Don't `pkill -f vite`.

## Branch / deploy state
- Branch: `fix/public-pages-base-path` (merged to dev via #434, in sync 0/0). **Next session should branch off `dev`, not this branch.**
- dev is ahead of main by #420 + #432/#433/#434 (all the public-pages work) — NOT yet released to prod (CSJ's call).
- Deploy status: **dev + csjones** have all of #420 + the three fixes, browser-tested green except the Chart.js CSP/charts item (task a). **Production untouched by #420.**
