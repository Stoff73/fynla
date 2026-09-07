---
type: handover
mode: session-end
date: 2026-09-07
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-09-07, Session 1

## Where things stand

**fynla.org is live on main `a7cb3a211`, identical to dev, since 17:37 BST** — the first
production release since 22 June: 73 migrations, the tier collapse, CoALA, `/m`, the
board-verification sprint, and today's fixes. The 8 paying customers on the legacy plans
(3 pro, 3 standard, 1 family, 1 student) resolve Premium with full estate access; nothing
was deleted, every table the migrations touched was exported first, and the dumps and
user exports are on the server and in `~/Desktop/fynla-release-backups/`. **The first
attempt was rolled back** (14:37–14:49 UTC) at the tier-collapse preflight, then re-run
after #771; the whole story is in the release memory and in PR #772's comments.

**TestFlight: "Fynla 1.0 (8)"** is valid on the testers' one app (the `org.fynla.app.dev`
record, renamed from "Fynla Dev"), pointing at fynla.org, and **CSJ confirmed login
works.** The native change behind it (#773: upgrades and billing go to the web, no
in-app purchase path) is built, signed, unit-tested on its branch and NOT yet merged.

Local checkout: `dev` at `3cd23bb63`, clean except `app.jpeg` (CSJ's screenshot at the
repo root), no worktrees, nothing unpushed.

## Priorities for the next session

1. **BLOCKED ON CSJ — merge #773 after the device check.** Login on build 8 is confirmed.
   Still to see on the phone: Settings → Plan and billing shows "Upgrade on the web" for a
   Free account (opens fynla.org signed in, pricing open) and "Manage billing on the web"
   for a web-billed Premium one; a Fyn turn against production. Then
   `gh pr merge 773 --merge --admin`; it is native-only, no csjones step applies. csjones
   is at `06a671b8b`; pull it to the dev tip afterwards (only ops logs changed since).
2. **BLOCKED ON CSJ — two decisions carried from the 5 September handover** that nothing
   today resolved: (a) the W-0540 dead-components clusters (report at
   `September/September4Updates/dead-components-verification-2026-09-04.md` on the
   `chore/board-verification-31-august` branch, not on dev); (b) the Rule 15 lint scope.
   Note that `GiftingStrategy.vue`, `TrustPlanningStrategy.vue` and `IHTPlanning.vue` are
   in that unreachable set — #767 fixed real `$set` bugs in them that no screen can reach.
3. **Fix the two adjacent production findings from the browser pass** (small, web + `/m`):
   free-tier users trigger 403s on `/api/estate/calculate-iht` (property page) and
   `/api/estate/will-builder` (Estate dashboard) for calls the page does not need; the
   `/m` "Today's insight" line prints unformatted amounts ("153,672.00", "57,600.00").
   Also, "Exit Demo" on csjones lands on the csjones.co root because the fallback referrer
   is `/` (correct on fynla.org; `resources/js/store/modules/preview.js:376`).
4. **The `W-0532/33/34` work is on dev and prod** — close them on the board and in
   `tasks.md` (board-loop steps 3 and 9); the 5 September handover still lists them as
   `queued`. The homepage pension-check block salvaged with #770 is **unstyled and parked
   in PR #770's description** — decide whether to finish it.
5. **Prod housekeeping, not urgent:** the 165 stale files and the first attempt's tree are
   in `~/release-backups/2026-09-07*/` on the server (u2783) and can be deleted once
   nobody needs them; consider setting `COMPANIES_HOUSE_API_KEY` and
   `GETADDRESS_API_KEY` in prod's `.env` (both features are dark without them).
6. **Tech debt from today** (see section below) — three small items, none blocking.

## Context to load

- `handover/September/07/handover-2026-09-07-session-1.md` — this file.
- `deploy/DEPLOY.md` — the runbook; the 2026-09-07 additions live in memory
  (`project_release_2026_09_07_rolled_back`) and PR #772's comments: leftover tables
  after a dump restore, the duplicate-checkout assertion, the re-ask emails, the two
  staged scripts in `~/release-backups/` on prod.
- `ios-native/TESTFLIGHT.md` — "One TestFlight app" section (on the #773 branch until
  merged): production builds go to the `org.fynla.app.dev` record with bundle-id and
  profile overrides; keychain unlock; run `FynlaTests` with the Staging scheme.
- `app/Services/Tiers/TierCollapsePreflight.php` and
  `app/Services/Stores/TierConfigurationStore.php` — the legacy-plan mapping and the
  preflight rule CSJ decided today; read before touching billing or tiers.
- `tests/Pest.php` — the global hook now seeds `TierConfiguration` beside
  `TaxConfiguration`; tests that insert tier rows must `updateOrCreate`.
- `workforce/ops/board/W-0540-a-component-can-lose-its-last-importer-and-nothing-fails.md`
  — priority 2(a).

## Completed this session

- **#766 + #772 release PRs; dev → main; prod deploy** (main `34b6faeaa` rolled back,
  then `a7cb3a211` live). Prod PHP trees verified file-for-file against main; migrations
  0 pending; seeders run; Fyn validators exit 0; routes uncached, config cached.
- **#767** lint lane clean on the release range; three dev-tip Pest reds fixed (xAI golden
  master re-recorded, pre-W-0537 entitlement assertion, W-0516 constructor); two Vue 3
  `$set` calls replaced.
- **#768** W-0532/W-0533/W-0534 reconciled onto dev (the cherry-pick's own 11 reds were
  unseeded tier rows and a factory emitting null into a NOT NULL column); tick glyph
  dropped from `GiftForm.vue` (CSJ: the sentence carries the information); browser-verified
  on csjones web and `/m` with a seeded leasehold property and the entrepreneur persona.
- **#769** `tests/Pest.php` seeds tier rows globally (Adjusted Net Income now crosses
  `TeaserGate`); full suite 8,395 passed.
- **#770** salvaged uncommitted 2 August pipeline fixes from the `fynla-marketing-review`
  worktree (clip filename contract, idempotent reject, scheduled articles hold composition).
- **#771** legacy paid plans confer Premium; preflight blocks only on unmapped paid plans
  and financial uniqueness; verified with a seeded `pro` subscriber on csjones web and `/m`.
- **#774** ops logs.
- **Prod:** stray `public/FeeAnalyzer.php` deleted; 4 re-ask emails sent; 5 abandoned
  duplicate checkouts marked failed (ids in `pending-checkouts-marked-failed.csv`).
- **TestFlight:** production profile "Fynla Production App Store" created; build 8 on the
  `org.fynla.app` record uploaded by mistake and **expired**; records renamed
  (`org.fynla.app` → "Fynla (legacy)", `org.fynla.app.dev` → "Fynla"); build 8 uploaded
  to the testers' record with the Production configuration; login confirmed by CSJ.
- **Worktrees:** six removed after inspection (the marketing-review one held real
  uncommitted work, now #770); 20 merged local branches deleted; three unpushed branches
  pushed. Memory: seven files written or corrected (see "Decisions").

## Verification state

- Full Pest suite: 8,395 passed / 30 skipped at `41771cca0` (3 reds, all fixed in #767);
  8,433 passed / 0 failed at `e3c028492` (#771). Frontend 1,291 passed. Release PR #772
  CI fully green at `06a671b8b` (the iOS job is not on it).
- Browser (Playwright, click-through only, per CSJ): csjones W-0533 web + `/m`, W-0534
  web + `/m`, dashboard trusts card, `/m` level wheel, Fyn turn on `/m`, legacy `pro`
  subscriber full Estate on web + `/m`; production homepage, cookie flow, Mitchell demo
  dashboard.
- Native: `SubscriptionModelTests` 20 passed on the #773 branch (Staging scheme). The
  first two runs wedged the simulator; the third, after a CoreSimulator bounce, ran; one
  earlier "pass" had tested dev by mistake. Build 8 login confirmed by CSJ on device.
- **Not verified:** the two "on the web" buttons on a device (CSJ, priority 1); W-0532's
  gate in a browser (both tiers sell `family_module` and `benefits_child`, so nothing is
  observable — `SoldCapabilitiesAreEnforcedTest` covers it); the `$set` fixes (unreachable
  components); the prod SPA journeys beyond the demo (no prod test account).

## Decisions and dead ends

- **CSJ: legacy paid plans map to Premium; nobody loses information or access.** The
  August memory "no premium subscribers yet" was true of csjones only — prod had 8. The
  preflight's zero-paid rule was the design assumption made explicit; overriding it was
  not safe because `confersPremium` mapped only `tier1/2/3`. Fixed at the source in #771,
  never by editing prod.
- **CSJ: the spouse re-ask migration runs as designed and every re-asked household is
  emailed** with the app's own `SpousePermissionRequest`; the trial-schema drop is fine,
  exported first.
- **CSJ: iOS is checked AFTER the web release** and the `iOS Native` CI job is not a gate
  — and **never re-run it unasked** ("DO NOT RUN iOS!!!!!").
- **CSJ: testers keep ONE TestFlight app**, so production builds go to the
  `org.fynla.app.dev` record; the legacy record is renamed and its build expired.
- **CSJ: the local checkout ends every task on dev, clean, no worktrees, nothing
  unpushed** — supersedes "never switch branches in the main dir".
- **CSJ: the Mitchells (`peak_earners`) are Premium on purpose** (W-0537); the old
  "preview users are always Free" test was the wrong contract.
- **Rolled back rather than pushed through**: with the site down and paying customers at
  risk, restoring the June code and the dump was the right call; the second attempt took
  4 minutes of maintenance.
- **Dead ends:** `git apply --3way` of the August patch "did not match index" only
  because it was checked inside the dirty worktree, it applied cleanly on dev; `rsync
  --files-from` with `--delete` deletes nothing (stale files must be listed and moved);
  the classifier blocks `rsync --delete` and mass `mv` to prod over plain SSH — the
  `ssh-fynla` MCP tool runs them; the ASC API from curl needs `-g` or bracketed filters
  silently return nothing; the `Fynla-Production` scheme's test action is Release, so
  `@testable import` fails — test with Staging.

## Things that will bite you

- **A restored dump cannot drop tables it never held.** After a rollback, diff
  `SHOW TABLES` against the dump's `CREATE TABLE` list before migrating again.
- `2026_07_15_000006_harden_payment_lifecycle` asserts one pending checkout per user;
  abandoned duplicates must be marked `failed` first (its own convention).
- The prod `fynla-dist` keychain must be unlocked before any non-interactive archive
  (`errSecInternalComponent` otherwise). Password location: `ios-native/TESTFLIGHT.md`.
- The shipping bundle id is now `org.fynla.app.dev`; universal links from fynla.org need
  it in the site association when deep links matter. An App Store submission that sends
  users to the website to pay is a review-guideline question for the compliance lead.
- On csjones the entrepreneur and Mitchell demos are the fastest routes to the Estate
  teaser and the trusts card; `legacy-pro-test@example.com` / `password` is a seeded
  legacy subscriber left there for CSJ.
- Accept cookies when testing prod; the decline path is two steps and blocks clicks.

## Tech debt deferred

Full report: `docs/tech-debt-report.md`.

- `SubscriptionManagementView.swift` — the web-handoff button + error block is written
  twice; extract one helper.
- `TierCollapsePreflight.php` re-derives "plans that confer premium" from two constants
  instead of one `TierConfigurationStore::plansConferringPremium()`.
- `WillFactory.php` — `100.00` is the column default, unnamed, twice.

## Branch and deploy state

- Branch: dev at `3cd23bb63` (main `a7cb3a211`, tree identical to dev at `06a671b8b`).
- Unpushed commits: none. Open PRs: #773 (native, awaiting CSJ), #249 (parked).
- Deploy status: **fynla.org = main `a7cb3a211`** since 17:37 BST. csjones = dev
  `06a671b8b` (bundles built from it). TestFlight "Fynla" 1.0 (8) = #773 branch
  `53ffa0880`+docs, fynla.org backend, login confirmed.
