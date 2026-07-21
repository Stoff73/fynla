---
type: handover
mode: context-clear
date: 2026-05-19
session: 4
branch: estateTeaserWillPoa
trigger: context-handover skill (tripwire ~450k)
---

# Context Clear Handover — 2026-05-19, Session 4

## Immediate state

All work for this session is **complete, committed, pushed, and PR'd**.
Two deliverables shipped: (1) prod release #337 deployed to fynla.org &
verified GREEN; (2) the Will/POA teaser-gate fix CSJ asked for is on
branch `estateTeaserWillPoa`, committed `9ce26c1`, pushed, **PR #339
OPEN → dev**. Nothing is mid-implementation. The tripwire fired during
the post-PR wrap; the only un-actioned item is a scheduled prod-log
recheck (see "Pick up from here").

## The thread

- Auto-resumed handover-3: CSJ said "ship it" → executed the full prod
  runbook. Admin-merged **#337** (`dev→main`), built
  `./deploy/fynla-org/build.sh`, **rsynced** 156 source files +
  `public/build/` to fynla.org over the prod SSH key (CSJ ran
  `ssh-add ~/.ssh/production` so agent auth worked — MCP
  `ssh_upload_file` is text-only, would corrupt binaries; rsync was the
  correct tool). Ran 8 additive migrations, seeded
  `TierConfigurationSeeder` (4 tiers), `composer dump-autoload -o` +
  `optimize`. Browser-verified prod GREEN: pricing 4 tiers, dashboard,
  Fyn advice turn, 0 ERROR/CRITICAL in laravel.log over ~85 min.
- Diagnosed the prod console `403` on `/api/estate/calculate-iht` as
  **correct SP2 defence-in-depth** (chris grandfathered pro/active →
  `resolved_tier=free` → estate `teaser` per spec §5.2/§4.4; not a
  regression). `CassetteModelProvenanceTest` stays RED & tracked per
  CSJ "ship & track" — do NOT re-litigate.
- CSJ then reported Will-creation + Power-of-Attorney screens still
  fully usable on prod. Diagnosed (spec → code → live browser): SP2 PR7
  gated **only** IHTController/EstateController; WillController,
  WillDocumentController, LpaController + the frontend Will/POA routes
  were never gated. Legacy `feature:pro` + `featureGating.js` gate on
  the **legacy plan**, so grandfathered subs walk in. Spec §7 = no
  separate will/POA capability key (they're "Estate planning",
  teaser-gated); §10.2 = "gated server-side, not just hidden".
- CSJ decisions (AskUserQuestion): **teaser + upgrade CTA (spec §10.2
  literal)**; **standard feature branch → dev → main** (NOT hotfix).
- Implemented via TDD on `estateTeaserWillPoa` (off `origin/dev`):
  RED (+11 EstateTeaserGateTest cases) → GREEN. New
  `EnsureFullEstateAccess` route middleware (reuses canonical
  `TeaserGate`, route-level because FormRequests 422-before-403 in
  body) + `estate.full` Kernel alias + applied to
  will-builder/will/bequests/calculate-intestacy/lpa routes. Frontend
  `requireFullEstateAccess` router `beforeEnter` guard on the 3 Will/POA
  routes → redirects teaser users to `/estate` canonical teaser.
- Found & fixed an **in-loop regression** (not silenced): the new gate
  broke 24 pre-SP2 WillBuilderApiTest/LpaControllerTest cases (factory
  users tier=null→free). Confirmed against the 29-pass clean baseline
  it was mine; corrected those tests to a `tier2` acting user +
  `TierConfigurationSeeder` (the SP2 contract). 132 Tiers+Estate pass,
  Architecture green, pint clean.
- Browser-verified local dev: `john` (free) → redirected to teaser on
  both routes + live 403; `sarah` (set tier2 as fixture, then reverted
  to null) → full Will Builder wizard + full POA page, no redirect.
- Committed `9ce26c1` (7 files only — excluded unrelated SavingsStore*),
  pushed, opened **PR #339 → dev** with full body. NOT self-merged.

## Files touched this session

- **Prod deploy (no repo change):** rsynced main@`7364657` to fynla.org.
- **Branch `estateTeaserWillPoa`, commit `9ce26c1`:**
  - `app/Http/Middleware/EnsureFullEstateAccess.php` (new)
  - `app/Http/Kernel.php` (+`estate.full` alias + import)
  - `routes/api.php` (Will/POA routes wrapped in `estate.full` group)
  - `resources/js/router/index.js` (`requireFullEstateAccess` guard
    + `beforeEnter` on PowerOfAttorney/CreateLpa/WillBuilder)
  - `tests/Feature/Tiers/EstateTeaserGateTest.php` (+11 cases)
  - `tests/Feature/Estate/WillBuilderApiTest.php` (tier2 + seeder)
  - `tests/Feature/Estate/LpaControllerTest.php` (tier2 + seeder)

## WIP commit

- **No WIP snapshot commit needed** — all real work is in proper commit
  `9ce26c1` (pushed, PR #339). The only uncommitted changes were
  **pre-existing, NOT-ours** `app/Services/Stores/SavingsStore.php` +
  `tests/Unit/Services/Stores/SavingsStoreTest.php` (they were already
  modified in the working tree when this branch was created).
- **Deviation from skill (documented):** instead of WIP-committing those
  foreign changes onto `estateTeaserWillPoa` (which is PR #339's HEAD —
  a blanket commit+push would contaminate the open PR), they were
  **`git stash`'d**: `stash@{0}` = "context-handover: pre-existing
  SavingsStore* changes (NOT part of PR #339)". Tree is clean; nothing
  lost; PR #339 stays clean.

## Open decisions

1. **Scheduled prod-log recheck** (was a ScheduleWakeup, fired as this
   session's prompt): recheck fynla.org `storage/logs/laravel.log` for
   ERROR/CRITICAL since the #337 deploy, excluding the expected
   estate-teaser 403s. Default direction of travel: it was already
   0 errors over ~85 min at last check — almost certainly still clean;
   confirm and note "prod fully stable", else diagnose per Rule #15.
2. **PR #339 review/admin-merge → dev** is CSJ's call (do NOT
   self-approve — `feedback_no_self_approval`). Then it rides the next
   `dev → main` release to reach prod (prod still has the Will/POA gap
   until then — per CSJ's "standard feature branch" choice, not hotfix).
3. **PR #337 follow-up:** `CassetteModelProvenanceTest` RED rides the
   release by CSJ's explicit "ship & track" — tracked, do NOT
   re-litigate. Fix = `php artisan eval:record --providers=xai` +
   delete stale `xai/grok-4-1-fast-reasoning` dir (changes fixtures —
   needs CSJ confirm).

## Pick up from here (auto-continue contract)

1. **Recheck prod laravel.log** via the `ssh-fynla` MCP:
   `mcp__ssh-fynla__ssh_exec` →
   `cd ~/www/fynla.org/public_html && grep -E "^\[2026-05-19" storage/logs/laravel.log | grep -iE "\.(ERROR|CRITICAL|EMERGENCY)" | grep -viE "calculate-iht|Full Estate Planning requires|estate" | tail -10`
   — if empty, state "prod release #337 fully stable". If errors,
   diagnose per Rule #15 (systematic-debugging).
2. Then **stop and report** — no further new work without CSJ steer.
   PR #339 is the active deliverable awaiting CSJ review.
3. If CSJ wants to restore the stashed SavingsStore* work: it is
   `git stash@{0}` on `estateTeaserWillPoa`. It is **not ours** and
   must NOT be squashed into PR #339 — `git stash pop` only on a
   different branch or after PR #339 merges.

## What the next Claude needs to know

- **Prod SSH:** agent auth works because CSJ ran
  `ssh-add ~/.ssh/production` this session. If `ssh -p 18765 ...
  u2783-hrf1k8bpfg02@ssh.fynla.org` gives "Permission denied
  (publickey)", ask CSJ to re-run `! ssh-add ~/.ssh/production`. The
  `ssh-fynla` MCP (`mcp__ssh-fynla__ssh_exec`) works independently and
  is the reliable path for prod artisan/log commands. MCP
  `ssh_upload_file` is **text-only — never use it for binary/bulk**
  (corrupts build assets → blank page).
- **Local dev DB:** SP2 tier migrations were run + `TierConfigurationSeeder`
  seeded locally this session (was missing `tier_configurations`).
  Test users resolve: john/chris = free→teaser; sarah was set tier2
  then **reverted to null**. `php artisan db:seed` restores baseline.
- **Pest tier tests** need `DB_DATABASE=fynla_test` +
  `$this->seed(TierConfigurationSeeder::class)` in beforeEach (see
  `feedback_never_artisan_env_testing`).
- **`git stash push -m` is broken in this git build** (prints
  "or: git stash clear" usage error); plain `git stash` works.
- **Don't re-litigate:** chris's estate 403 = correct;
  CassetteModelProvenanceTest RED = tracked/ship-&-track; the
  legacy→SP2 frontend-gating rewrite (featureGating.js/AppNavbar still
  on legacy plan map app-wide) is explicitly OUT of PR #339 scope.

## Branch / deploy state

- Branch: `estateTeaserWillPoa` (off `origin/dev`)
- Behind origin: 0 · Ahead of origin: 0 (commit `9ce26c1` pushed)
- Stash: `stash@{0}` = foreign SavingsStore* changes (not ours)
- **PR #339** (`estateTeaserWillPoa → dev`): OPEN, awaiting CSJ
  review/admin-merge. NOT self-merged.
- **PR #337** (`dev → main`): MERGED — **deployed to prod (fynla.org),
  verified GREEN**. Migrations + TierConfigurationSeeder applied on prod.
- **PR #338** (`main → dev`): MERGED earlier (doc back-merge).
- **PR #317**: CLOSED (superseded by #337).
- Deploy status: **Prod = main@`7364657` LIVE & stable.** Will/POA
  teaser-gate fix NOT yet on prod (in PR #339 → dev, awaits release).
- vault-sync STILL deferred (many sessions) — for next end-of-day
  `session-end`, not this tripwire.
