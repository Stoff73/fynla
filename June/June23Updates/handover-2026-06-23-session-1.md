---
type: handover
mode: end-of-day
date: 2026-06-23
session: 1
branch: main
previous_session: 2026-06-19 session 1 (deadcode-cleanup)
---

# Handover — 2026-06-23, Session 1

> Work performed 2026-06-22; wrapped just after midnight into 06-23 (hence this handover is dated for the next working session).

## Where we left off
Picked up the 2026-06-19 `deadcode-cleanup` handover and carried it all the way to **prod**. Tested + pushed the `deadcode-cleanup` branch (#569), actioned all three deferred audit tiers as #570 (DOMPurify + dead notifications + unused exports + diagram), then released dev→main (#571) and deployed to **both** dev (csjones) and prod (fynla.org). Everything merged, deployed, verified; prod log clean post-deploy. Working tree clean except CSJ's planning files.

## What shipped today
- **#569** — zero-risk dead code: orphan console commands, deprecated `SavingsController` goal methods (legacy `/savings/goals`, no routes), `RetirementIncomeService` dead methods, Savings goal Request classes, unread config keys. (Branch was committed-not-pushed from 06-19; this session tested + pushed + merged it.)
- **#570** — `sanitizeHtml`→DOMPurify (closes a latent XSS; regex sanitisers are bypassable); 7 dead Notification classes removed (push path via `PushNotificationService` confirmed intended/active; 8 active notification classes untouched); 4 unused frontend exports removed (`featureGating.PLAN_LABELS`, `tierAccess.MODULE_LABELS`, `sourceCapture.ALLOWED_SIGNUP_SOURCES`, `awinTracking.isEnabled`); draw.io service-layers diagram + `gen_service_layers.py`.
- **#571** — dev→main release of the above.
- Deployed to **dev (csjones.co/fynla)** and **prod (fynla.org)**. Net **−1,095 lines**, **no migrations**.

## What's in flight (NOT done — deliberate)
- **taxConfig Vuex getters NOT removed.** The 06-19 handover's "~18 dead getters" estimate was unreliable — the store is heavily consumed (`mapGetters('taxConfig', ['ihtNilRateBand'])`, `store.getters['taxConfig/hicbcThreshold']`, `rootGetters['taxConfig/isaAnnualAllowance']` across Estate/Savings/Investment/UserProfile), automated dead-detection misfired ("all 55 dead"), and it's a coherent UK-tax API under Rule #2 with negligible bundle payoff. **CSJ confirmed: leave them.** `dateFormatter.getTaxYearEnd`/`getCalendarTaxYear` also left in (tree-shaken; `getTaxYearEnd` is the documented pair of the in-use `getTaxYearStart`).
- **Two tier-2-gated Estate `sanitizeHtml` surfaces NOT directly clicked** — `LpaDetailView` (Power of Attorney) and `WillBuilderReviewStep`. No tier-2 test user locally (john + all preview personas show the upgrade gate); would not DB-edit to force it. Verified instead via the shared-function + render-path proof (the function and `innerHTML`-as-`v-html` path proven on document-shaped HTML). Now live on prod — eyeball with a tier-2 prod account if desired.

## Deploy status
**Deployed to BOTH environments.**
- **dev (csjones):** `git pull origin dev` (ff `ea5c348`→`441e504`, brought #568+#569+#570) + `composer dump-autoload` + rsync `public/build/` + `public/m-build/` + cache sequence (`config:cache`, no `route:cache`). Manifest md5 matched. Smoke green.
- **prod (fynla.org):** non-git manual upload — `rm` of the 16 deleted PHP files, rsync of the 8 modified PHP files, `composer dump-autoload -o` (8067 classes), rsync `public/build/` + `public/m-build/`, `cache:clear→config:clear→view:clear→route:clear→config:cache`. Manifest md5 verified local↔prod (`11b02be8…`). Smoke: homepage 200 **server-rendered** (not SPA shell), fresh asset 200, `/m` 200, `/login` 200. **Prod log: 0 errors post-deploy** (10-min monitor window).

## Tech debt found this session
Session was net debt **removal** (−1,095 lines); no new debt. One pre-existing item surfaced from the prod log worth a future pass: the **`Sanctum\TransientToken::$id`** bug family (~117 historical hits, last 2026-05-11 — appears resolved since it stopped firing; confirm no un-guarded `currentAccessToken()->id` sites remain — see `reference_transient_token_family_bugs.md`).

## Known issues / blockers
**None broken. Prod healthy** (0 errors in the 10-min post-deploy window; last log entry pre-dates the deploy). The prod `laravel.log` errors are all **historical** (tapered off after May): the biggest category is the SiteGround uncached-config race (`forge`/`APP_KEY`, ~728 hits, operational — mitigated by ending every deploy with `config:cache`). The 2026-06-16 `ai_conversations.status='paused'` truncation is **RESOLVED** — the enum was migrated to `('active','archived','paused')`; 67 rows store `'paused'` fine; the error was a transient pre-migration occurrence.

## Rules reinforced this session
- No new memory files written. Reinforced: **never edit the prod DB to work around** (investigated the status-column "defect" CSJ asked me to fix → found it already resolved, made no change); **prod is non-git manual-upload** requiring explicit `rm` of deleted files + `composer dump-autoload -o` (`reference_prod_accumulated_deploy_drift.md`); **`config:cache` mandatory at deploy end** else `forge` fallback 500s (`reference_prod_forge_uncached_config.md`); **flag a wrong spec rather than blindly execute** (Rule #16 — stopped the taxConfig getter batch-delete when investigation contradicted the estimate); **never `route:cache`/`optimize`** on servers (`reference_route_cache_shadows_homepage.md`).

## Next session should
- **Nothing mandated** — the cleanup programme is complete and live on prod.
- Optional: tier-2-account eyeball of the two Estate `sanitizeHtml` surfaces (LPA, Will review) on prod.
- Optional future: confirm no un-guarded `currentAccessToken()->id` remains (TransientToken family).
- **Housekeeping:** remove the stale worktree `/Users/CSJ/Desktop/fynla-deadcode-cleanup` (branch merged). This git version lacks `git worktree remove` — use `rm -rf` the dir then `git worktree prune` then `git branch -D deadcode-cleanup`. Also `/tmp/fynla-main-planning-backup/` holds CSJTODO/progress backups, no longer needed.

## Context hints
- Active branch type: **mainline** — main = origin/main = prod (`0/0`).
- Behind origin/main by: 0.
- Uncommitted at wrap: `CSJTODO.md` + `progress.md` (planning docs — committed as part of this wrap).
- Last commit: `7389d7d` Merge pull request #571 from Stoff73/dev.
- Stale worktree: `/Users/CSJ/Desktop/fynla-deadcode-cleanup` (merged `deadcode-cleanup`, safe to remove).
- Untracked carry-over (not this session's): `June/June19Updates/` (the 06-19 handover, never committed), `docs/security/security-review-2026-06-09.md`, `docs/mobile/designer-brief.pdf`, excalidraw `__pycache__`.
