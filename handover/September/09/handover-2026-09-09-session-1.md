---
type: handover
mode: session-end
date: 2026-09-09
session: 1
repo: fynla
branch: dev
---

# Session Handover — 2026-09-09, Session 1

## Where things stand

Everything from today is **live on fynla.org**: main `867fcf82b` == dev `366a9f4ea`, released twice (PR #792 at ~10:25 BST, PR #796 at ~11:00 BST); csjones runs the same tip. The Fyn wiring artifact's findings are closed except by CSJ's own choice (protection `adequacy_score` contract: leave). The legacy subscription plan catalogue is gone from the code, the schema and both servers. Tree clean (the only dirty files are the workforce cron's log lines, which belong to that automation), nothing unpushed, no worktrees.

**The next session is iOS** (CSJ, session end). The board loop and the Fyn wiring work are finished; do not pick them back up unless CSJ says so.

## Priorities for the next session

1. **BLOCKED ON CSJ — PR #773 (native billing on the web)** — open since 8 September, `ios-native/` only, mergeable state UNKNOWN on GitHub after 50 dev merges landed under it (2 commits ahead, 48 behind). The build on CSJ's iPhone 11 is #773 + #781 pointing at fynla.org. Ask first thing: has CSJ seen Settings → Plan and billing show "Upgrade on the web" (Free account) / "Manage billing on the web" (web-billed Premium), and a Fyn turn? If yes: rebase onto dev (last rebase was 8 September; expect docs conflicts only — resolve in favour of the fynla.org wording), run `ios-native/scripts/verify-project.sh`, merge with `--admin`. Native-only, no csjones step (memory `feedback_release_order_web_m_first_ios_after`).
2. **The six `deferred-ios` board items** — W-0044, W-0090, W-0243, W-0311, W-0416, W-0496. Production serves the native routes and both schemes read fynla.org, so a native cycle runs against production; every account a tester registers is a real fynla.org account. Start with `vault-context` for the native module and `ios-native/CLAUDE.md`; the parity ledger is `codex/plans/ios/2026-07-20-native-m-parity-ledger.md`.
3. **iOS harness debt** carried from 8 September: the shared UI-test typing helper cannot clear the email keyboard off the password field on an iPhone 11 (the registration regression test is simulator/CI only); the 6 red `Local StoreKit configuration` tests are a real signal (no in-app purchase products in App Store Connect; CSJ has not asked for any).
4. **Parked, non-iOS, need CSJ** (do not start unasked): W-0540 dead-component clusters and the Rule 15 lint scope (carried since 5 September); the unstyled homepage pension-check block in PR #770's description; the 34 remaining sweep findings ("decide, do not chase").

**Closed by CSJ today, do not re-raise:** the two production `.env` keys (`COMPANIES_HOUSE_API_KEY`, `GETADDRESS_API_KEY`) and the unconfigured Apple verification bridge on production. Both deferred until other work is done; they only bite if the Companies House / postcode lookups are used on production or native in-app purchase is ever sold.

## Context to load

- `ios-native/CLAUDE.md` — the native conventions and the three standing traps (both schemes read fynla.org; no IAP products; the Capacitor wrapper is gone).
- `ios-native/TESTFLIGHT.md` — the one TestFlight app (`org.fynla.app.dev` record named "Fynla" carries Production-configuration builds; never upload to `org.fynla.app` unasked).
- `handover/September/08/handover-2026-09-08-session-1.md` — the "Carried from the iOS session" list and the registration-bug record; the iOS session's own appendix is in `September/September8Updates/handover-2026-09-08-session-2-clear.md` (device build/install/launch commands by UDID, simulator wedging, the #773 rebase state).
- `codex/plans/ios/2026-07-20-native-m-parity-ledger.md` — the /m-parity record the six deferred-ios items are judged against.
- `September/September8Updates/fyn-wiring-artifact.html` — source of the Fyn wiring artifact (https://claude.ai/code/artifact/7375932e-a8e0-4920-9142-5a2db33b2d88), now the closed record of Batches A–G; read only if a Fyn regression is reported.
- `deploy/DEPLOY.md` — step 8 is new today: SiteGround serves `/.well-known/` from the site root.

## Completed this session

- **PR #785** one ranking (F2, F12): `PriorityRanker` is the one home, seeded priority wins, dashboard / `/m` / Fyn agree. Memory `feedback_one_ranking_seeded_priority_wins`.
- **PR #786** F1 pension phrasing, F7 employment list, F8 one classifier run, F9 `config/ai_chat.php`, F13 consent gate on the action endpoint + `ai_chat` not writable via GDPR, F18 tracking status merged in the aggregator; life-event line and `/m` preview 403 fixed.
- **PR #787** F19 surface-action block names the opening recommendation card.
- **PR #788** F5 one FCA block, F6 part, F10 no score constructs (tool-schema golden masters re-recorded), F11 tax recommendations from the composed plan on both engine levels, F14 expenditure categories nullable (migration; NULL = never asked).
- **PR #789** F4 the corpus workflow file is the one home for the onboarding table (`inCodeStates()` lost 294 lines; corpus required; merged table proven identical).
- **PR #790** F6 consent gate re-wired after the income advice with "No thanks"; ten phantom triggers wired to real rules (`RelevantTriggersAreSeededTest`); emergency-fund grade replaced by months of runway everywhere; David's Cash ISA contribution £250; `/api/plans/savings` routes.
- **PR #791** `/m` module-detail scaffold retired; `/module/{slug}` redirects to the real views.
- **PR #792** release 1 → fynla.org; **PR #793/#794** docs; **PR #795** legacy `subscription_plans` catalogue retired (model, seeder, table, every legacy branch); **PR #796** release 2 → fynla.org.
- Universal links: fynla.org now serves `/.well-known/apple-app-site-association` (both app ids) and `assetlinks.json` — SiteGround maps `/.well-known/` to the site root, recorded in `deploy/DEPLOY.md` and memory.
- Artifact re-checked, updated per batch and republished; evidence per batch in `September/September9Updates/`.
- Local test accounts 72 and 73 deleted. Memory: `project_release_2026_09_09` (both releases), `feedback_one_ranking_seeded_priority_wins`, SiteGround lore §4.

## Verification state

- Every batch was browser-verified locally on web and `/m` (Playwright, clicked through) before its PR; csjones and fynla.org were browser-verified through the demo chooser as the young family persona after each release (web dashboard order, `/m` top actions, Fyn sheet; plans endpoint after the catalogue drop).
- Consolidated Pest passes at the batch tips: Batch B 478; Batch C 1,530; Batch E 2,963; Batch F 1,657; Batch G 1,878 (+23 rerun); catalogue removal 407 (+139 rerun). Mobile Vitest 253 after the scaffold retirement.
- **Not verified:** the full Pest suite was not run after the last three merges (targeted families only, per Rule 17). The `/m` consent gate was verified locally with a fresh user, not on csjones/prod (no campaign user there). The iOS PR #773 build on the phone: unverified, CSJ's check.
- Production log: zero errors on 2026-09-09 after both releases.

## Decisions and dead ends

- **CSJ: the seeded priority wins** (F2/F12) — ranker reads the label, benefit and module weight only break ties.
- **CSJ: one home for the onboarding table = the corpus file**; PHP keeps only PHP-only fields. The golden master now guards against duplication instead of enforcing it.
- **CSJ: re-wire the consent gate, do not delete it**; bubble "No thanks". The review-expenditure state was deleted.
- **CSJ: keep the young family Junior ISAs** (zero-rate rule firing on them is accepted).
- **CSJ: the ten phantom triggers must be real rules** — nine aliased to existing rules, the tax-free lump sum folded into `approaching_decumulation` (already computes the capped PCLS). No new evaluator was built.
- **CSJ: replace the emergency-fund grade with months** everywhere; protection `adequacy_score` contract stays.
- **CSJ: the `/m` Fyn panel covering focus cards is not an issue.**
- **CSJ: delete the plan catalogue outright**; the two `.env` keys and the Apple bridge are deferred and closed for handover purposes.
- Rejected: building a separate `tax_free_lump_sum` rule (duplicate arithmetic); deleting `STATE_CAMPAIGN_CHARITABLE_GIVING` (a live capture-ack key, not a value).
- The test `rejects a legacy plan key for a new purchase` was red on dev since July (posted the Premium tier key); it now posts a legacy slug.

## Things that will bite you

- **Prod deploys must include `fyn-memory/` and `resources/js/data/`** now: the onboarding table requires the corpus workflow file, and `PreviewUserSeeder` reads the persona JSON from the server. `rsync` never deletes — removed classes must be `rm`'d on prod by hand (done today for the catalogue).
- **Pest refuses a file and its parent directory in one invocation** ("Test case … already uses …"); pass the directory only.
- **`npm run build:mobile` before any `/m` check** — the local server serves the built bundle; a preview persona's `/m` token gets rotated by the app's refresh flow, mint a fresh one per session.
- **The `/m` `onboardingChat` mixin greets preview personas instead of calling the start endpoint**; do not re-add a per-view guard.
- **Universal links on SiteGround live in the site-root `.well-known/`**, not `public/.well-known/`; re-copy after any change to the association files.
- `2026_09_09_090000` made twenty `users` expenditure columns nullable: readers coalesce, writers store 0.00 only on a category-form submit.

## Tech debt deferred

Full report: `docs/tech-debt-report.md`.

- `RecommendationsAggregatorService::aggregateRecommendations` is 175 lines: five raw-path rollback blocks behind `coordination.composed_module_plans`; retire the flag and they go.
- `PriorityRanker::MODULE_WEIGHTS` carries both `tax_optimisation` and `tax` because the two pipelines name the module differently; `AdvicePromptBuilder` aliases one to the other.
- `StrategyPriority` has no `Critical` case; the five adapters carry the seeded label in `extra['seeded_priority']`. Adding the case touches seeded strategy rows and the SaveTax badges.
- `CoordinatingAgent` is ~6,800 lines; `SavingsActionDefinitionService` ~3,850.
- `RevolutSubscriptionService::getSubscriptionPlan` names Revolut's plan object, not the deleted catalogue — leave it.
- The `/m` `formatCurrency` helper is copied per view; `0.00005` and the flat `0.0400` benchmark block are unnamed constants (from 8 September).

## Branch and deploy state

- Branch: dev @ `366a9f4ea`
- Unpushed commits: none
- Deploy status: fynla.org = main `867fcf82b` (== dev tip), csjones = dev `366a9f4ea`, both deployed 2026-09-09. Prod backups `~/release-backups/2026-09-09/` and `2026-09-09b/`. TestFlight unchanged (build 8 on the `org.fynla.app.dev` record).
