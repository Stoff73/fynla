# CSJTODO — Fynla

*Last updated: 2026-06-10 — context-clear wrap, session 3. The `/m` gamification + recommendations rework is verified (all 9 acceptance criteria), six finding-fixes landed, **merged to dev (PRs #525 + #526) and live on csjones at merge head `a75af48`**. NOT on prod. 94 remote + 4 local merged branches deleted. Handover: `June/June10Updates/handover-2026-06-10-session-3-clear.md`. Prior sections preserved beneath.*

## 2026-06-10 — Session 3: verification, finding-fixes, merge to dev, branch cleanup (context-clear; dev @ a75af48)

Handover: `June/June10Updates/handover-2026-06-10-session-3-clear.md`.
- Verified the branch end-to-end on csjones (incl. session-2's open G2 bubble + G3 banner items); fixed all six findings TDD'd: stable `sha1(module|text)` recommendation IDs, retirement headroom earnings-cap (£3,600 non-earner floor, no recs from 75, no retirement-age rec for retired users), goals pluralisation + praise-rec removal, assets caption from breakdown, session-persistent bubble dismissal, retired_couple seeded onboarding-complete. Repaired 7 stale aggregator tests.
- PRs #525 + #526 merged to dev via pushed merge commits; csjones back on dev, bundles rebuilt + rsynced, verified live.
- Branch cleanup: 94 remote + 4 local merged branches deleted (kept coala, brett-dev1, email-onboarding-video, python-agent-sidecar #249, fix/coala-test-stabilisation, fix/public-pages-base-path, gamification-dashboard, rss-feed #237-closed).

### Outstanding
- [ ] **gh CLI merge endpoint 401s on this machine** — run `gh auth refresh -h github.com -s repo` before the next admin-merge (PR create/read work; merge calls never do; workaround = local merge commit + push).
- [ ] **Estate will/LPA recs for sub-NRB users** — CSJ's call (carried from session 2).
- [ ] Optional: field-specific KYC prompts for gate-open-but-empty modules (carried).
- [ ] `fix/public-pages-base-path` kept — one stray 29-May handover-doc commit; CSJ to decide whether to delete.
- [ ] Minor debt: `openRecChat` in mobile `Dashboard.vue` now unused.
- [ ] dev → main release PR when CSJ decides to ship.

## 2026-06-10 — /m gamification + recommendations rework (context-clear; branch m-gamification-recommendations, deployed + verified on csjones)

Spec/plan: `docs/superpowers/specs/2026-06-10-m-gamification-recommendations-design.md`, `docs/superpowers/plans/2026-06-10-m-gamification-recommendations.md`. Branch `m-gamification-recommendations` (off dev), 23 commits, pushed, deployed to csjones, NOT on dev/main/prod.

Built + verified on csjones: KYC-gated recommendations across all 6 modules (investment + goals added to the aggregator), unified ≤4 `focus_areas` one-card swipe carousel, planning-progress percentile, achievements + milestones panel + API, varied preview-persona gamification seeding, recommendations deep-link to module screens.

**LAWS established this session (see handover):** (1) `/m` is verified on csjones, not locally (serves built `m-build`, no HMR; the `ssh-fynla` MCP tool = PROD, don't use for csjones). (2) Every module shows real recs OR the KYC info needed — NEVER "On track"/empty. (3) Recommendations deep-link to the correct module screen, not a Fyn message.

Engine bugs fixed (all real): estate unconditional £130k trust rec → gated on IHT liability; retirement 0 recs → wired generateRecommendations; protection title-key dropped + gate didn't require profile → both fixed; investment lazy-load violation on csjones strict mode → query instead of lazy relation.

### Outstanding
- [x] **Browser-verify G2 (Fyn unlock bubble)** — verified session 3 (appears, dismisses session-persistently, tap opens pre-seeded Fyn).
- [x] **Browser-verify G3 (milestone banner lowered)** — verified session 3 (fired live for Patricia, all four sides visible).
- [ ] **Estate will/LPA recs for sub-NRB users** — carried to session-3 Outstanding above.
- [ ] Optional: field-specific KYC prompts — carried to session-3 Outstanding above.
- [x] Final review + merge `m-gamification-recommendations` → dev — merged session 3 (PR #526).
- [x] `vault-sync` — run session 3; tech-debt audit folded into the session-3 verification findings.

## 2026-06-09 — GitHub bug auto-resolver loop + Fyn resume fixes (end-of-day; dev, deployed + verified on csjones)

Handover: `June/June10Updates/handover-2026-06-10-session-1.md`. Deploy: `June/June10Updates/deploy-2026-06-10.md`. Memory: `reference_github_bug_autoresolver_loop.md` (new).
- **Bug auto-resolver loop** (#505/#506/#508/#509/#511/#514/#518) — in-app report → `GithubIssueService` GitHub issue → `.github/workflows/claude.yml` Action (on **main**, armed) fixes on `dev` → PR (policy 8a). Auth = Max OAuth token; trust gate; `bypassPermissions`; `--max-turns 25`. Proved end-to-end (autonomous PR #513).
- **#516** — "Report a problem" reachable in the Fyn chat header + captures the conversation transcript (ownership-scoped, IDOR-tested).
- **#522** — fixed duplicated "Welcome back" on startup (web resume persisted `is_resume_greeting` per open without pruning; mobile rendered all). Prune-prior fix + regression test; 350 onboarding tests green.
- **#523** — mobile resume shows summary + Continue/Something else only (not the full transcript replay); wired the resume action + action bubbles on mobile.

### Outstanding
- [ ] **Production release** (`dev → main → fynla.org`) — CSJ's call. `dev` is +187 / -15 vs `main`. #489 auth throttle still the priority reason. Runbook: `June/June10Updates/deploy-2026-06-10.md` (base `June/June9Updates/deploy-2026-06-09.md`).
- [ ] **Decide prod bug-reporter state** — `GITHUB_BUG_ISSUE_ENABLED=false` by default; enabling on prod means real users file GitHub issues / trigger the autonomous loop.
- [ ] **Rotate tokens** — `CLAUDE_CODE_OAUTH_TOKEN` + `GITHUB_BUG_ISSUE_TOKEN` passed through chat.
- [ ] Optional: remove orphaned `loadConversation()` in `resources/mobile/views/Dashboard.vue` (caller replaced in #523).

## 2026-06-09 — Base-path bug class + Fyn advice-loop fix (context-clear, session 2; dev, deployed + verified on csjones)

Handover: `June/June9Updates/handover-2026-06-09-session-2-clear.md`. Memory: `reference_onboarding_advice_autoadvance_loop.md` (new), `reference_mobile_phone_entry_responsive.md` (corrected: `/m` iframe serves the SPA, not index.php).
- **#502** — `/m` "Save tax" CTA 404'd on csjones (hardcoded `<a href="/savetax">` missing the `/fynla` base; the `/m` iframe serves the SPA `LandingPage.vue`). Browser-verified: `GET /fynla/savetax => 200`, campaign funnel loads.
- **#503** — full SPA sweep found 6 more raw root-relative navigations 404ing under `/fynla/`. New shared `resources/js/utils/basePath.js` → `withBase()` (honours `VITE_ROUTER_BASE`); applied to `WillInfoStep`, `ArticleEditor`, `preview.js`, `SitemapPage`, `NewsHubPage`, `Version`. Browser-verified sitemap/news links resolve to `/fynla/...` (200).
- **#504** — Fyn looped, persisting the same message 17,509× (~41/sec) for user 79 (conv 66). Root cause: `STATE_CAMPAIGN_ADVICE_SPOUSE` `next => itself` + advice turns auto-advance with no user input. Fixed (advances via `nextCampaignSection`) + `MAX_ADVICE_CHAIN=6` guard + regression test (34 onboarding tests green). Conv 66 cleaned: 17,488 dupes deleted, 34 real messages kept.

### Outstanding
- [ ] **Production release** (`dev → main → fynla.org`) — CSJ's call. `dev` is +165 / -7 vs `main`. **#489 auth throttle is the priority reason** — prod MFA password reset broken until released. Today's #502/#503/#504 ride along. Runbook: `June/June9Updates/deploy-2026-06-09.md`. **Note:** #504 (the Fyn loop) is a prod-relevant class — would hit prod identically.
- [ ] **Set real tier prices** in the admin Tier Configuration screen — placeholders (£4.99/£14.99/£29.99 monthly) currently live.
- [ ] Optional: align `TestUsersSeeder` to `tier='free'` (recreates `trialing` subs each `db:seed`). **Note:** interacts with the upgrade-modal verification path — re-verify the modal after.
- [ ] Optional: sweep orphaned csjones `public/build/assets` chunks (~1240 after this session's two preserve-old-chunks deploys).

## 2026-06-08 — Freemium tier-pricing modal + deploy (end-of-day, session 4; dev, deployed + verified on csjones)

Handover: `June/June9Updates/handover-2026-06-09-session-1.md`. Deploy note: `June/June9Updates/deploy-2026-06-09.md`. PRs #500 (`/m` router cold-boot) + #501 (freemium tier-pricing modal + remove trial timer) merged to `dev`, built, deployed to csjones, live-verified (`/pricing` = Free+Tier1/2/3, penny prices, billing toggle, `<title>` mojibake fixed; upgrade modal tier-sourced, no trial timer; checkout resolves Tier 2 £14.99). Resumed after a laptop freeze mid-session — freemium code was already committed; this session re-verified end-to-end + merged + deployed.

### Outstanding
- [ ] **Production release** (`dev → main → fynla.org`) — CSJ's call. `dev` is +158 / -7 vs `main`. **#489 auth throttle is the priority reason** — prod MFA password reset is broken until released. Full prod runbook + freemium adds: `June/June9Updates/deploy-2026-06-09.md` (base: `June/June7Updates/deploy-2026-06-07.md`).
- [ ] **Set real tier prices** in the admin Tier Configuration screen — `tier_configurations` currently holds placeholders (£4.99/£14.99/£29.99 monthly); modal + checkout + public `/pricing` read them live.
- [ ] Optional: align `TestUsersSeeder` to `tier='free'` — it recreates `trialing` subs on every `db:seed` (pure-freemium artifact).
- [ ] Optional: sweep orphaned csjones `public/build/assets` chunks (~1009 after preserve-old-chunks accumulation).

## 2026-06-08 — SaveTax suite + onboarding rework (context-clear, dev; deployed + verified on csjones)

Handover: `June/June8Updates/handover-2026-06-08-session-3-clear.md`. PRs #489, #490, #492, #493, #494, #495, #498, #499 merged to `dev`, deployed to csjones, verified live (savetax onboarding walked end-to-end on local + csjones; taxconfig save + /m admin-link fixes verified; dashboard blur gone). Memory added: `reference_inline_throttle_shares_per_ip_bucket.md`.

### Outstanding
- [ ] **Production release** (`dev → main → fynla.org`) — CSJ's call. `dev` is ~149 / -7 vs `main`. The **auth throttle fix #489 is genuinely prod-relevant** (MFA password reset is broken on prod until released). Savetax SPA changes need a prod build; gamification runbook still `June/June7Updates/deploy-2026-06-07.md`.
- [ ] Optional: `/m` Admin link lands on `/dashboard` not `/admin` on the first hop (auth-guard cold-boot race; reachable on next nav) — small follow-up fix if wanted.
- [ ] Optional: sweep 4 stale unreferenced `AppLayout-*.js` chunks on csjones `public/build` (accumulated from deploys; harmless).
- [ ] **chris@fynla.org existing-user pass** — still blocked: safety guard won't reset his csjones password. (Not needed 2026-06-08 — browser held his session.)

## 2026-06-07 — Save tax CTA fix + gamification test (end-of-day, dev; #488 deployed to csjones)

PR #487 (inline-capture onboarding-award unit test) and PR #488 (Save tax CTA on the real server-rendered homepage) merged to `dev`. #488 deployed to csjones + verified live. Memory: `reference_public_homepage_is_server_rendered_not_spa.md` (the public homepage is `public/pages/index.php`, NOT the Vue SPA `LandingPage.vue`).

### Outstanding
- [ ] **Production release** (`dev → main → fynla.org`) — CSJ's call. `dev` is +120 / -7 vs `main`. Gamification runbook: `June/June7Updates/deploy-2026-06-07.md`. Today's #487 (test, inert in prod) + #488 (`public/pages/index.php` + `index.css`, no-build upload) ride along in the same diff.
- [ ] **chris@fynla.org existing-user pass** — blocked: safety guard won't let me reset his csjones password. CSJ to reset, then add the chris web+/m gamification pass (john was the proxy ✅).
- [x] Inline-capture onboarding-award unit test — DONE (#487).
- [ ] Optional: purge staging test users `gamifyweb@example.com` / `gamifysavetax@example.com` (id 76) on csjones.

## 2026-06-06 — Gamification engine (end-of-day, dev; deployed to csjones)

Built the full points-and-levels gamification engine end-to-end (brainstorm → spec → plan → multi-agent workflow → live browser verification). PRs #477–485 merged to `dev`, deployed to csjones + backfilled, verified live. Patch notes: `June/June6Updates/patch-notes-2026-06-06-gamification-engine.md`. Memory: `reference_gamification_engine_architecture.md`.

### Outstanding
- [ ] **Production release** (`dev → main → fynla.org`) — CSJ's call. `dev` is +114 / -7 vs `main`. Full runbook: `June/June7Updates/deploy-2026-06-07.md` (2 new migrations, config, both rebuilt bundles, `gamification:backfill` after `migrate --force`).
- [ ] **chris@fynla.org existing-user pass** — blocked: safety guard won't let me reset his csjones password. CSJ to reset; then add the chris web+/m pass (john was the proxy ✅).
- [ ] Optional: add the inline-capture onboarding-award unit test (PR #484 verified live, not unit-tested — needs LLM-stream mock).
- [ ] Optional cleanup: staging test users `gamifyweb@example.com`, `gamifysavetax@example.com` (id 76); john carries test recs/points (L3).

### Done this session (was outstanding from 2026-06-05)
- [x] Refreshed `reference_mobile_phone_entry_responsive.md` + `savetaxFix.md` T8/T9 (deep-links via `/m?to=`, canonical auth).
- [x] Removed orphaned `store.challengeToken`/`maskedEmail` from `resources/mobile/store.js` (PR #477).

## 2026-06-05 — `/m` pathway completion (end-of-day, dev `52e5f06`; deployed to csjones)

Completed the `/m` mobile-web connection sequence (rows 1–7), made savetax reachable inside `/m`, switched `/m` to canonical auth, cleaned arch-debt, deployed to csjones, and verified the full funnel + onboarding-recap + logout/resume live. Patch notes: `June/June5Updates/patch-notes-2026-06-05-m-pathway.md`. PRs #461–#475.

### Outstanding
- [ ] **Production release** (`dev → main → fynla.org`) — CSJ's call. dev is ~77 ahead / 7 behind main. Includes the `user_milestones` migration + `public/m-build`.
- [ ] **One unbroken `/m` iframe chain re-test** — sign-out → in-frame canonical login → verify → bridge → `/m/app` → resume (verified in links this session, not one continuous click-through).
- [ ] **Refresh docs/memory** — `reference_mobile_phone_entry_responsive.md` (deep-links now preserved via `/m?to=`; canonical auth replaced scaffold) + `savetaxFix.md` T8/T9.
- [ ] Optional cleanup: staging user `mflow0605@example.com` (id 74, mid-onboarding on csjones); orphaned `store.challengeToken`/`maskedEmail` in `resources/mobile/store.js`.
- [ ] Decide: "Save tax" CTA on shared homepage (shows on desktop) vs `/m`-only.

### Tech debt deferred
- [ ] `strict_types` added to 8 untested subsystems (HeyGen/video-pipeline/article-scraper) — verified via lint + arch suite, not live runtime.

## 2026-06-04 — Mobile detail views + Fyn fixes (end-of-day, dev `06937fc`+`7dce0e2`)

Walked the mobile Fyn-dock savetax onboarding to the `/tax-strategy` terminal (single + both married/joint household modes). Fixed: tripled ack, dropped next-prompt (double-`done` SSE), dock bubble-merge, provider-blind soft-degrade (broke chat under xAI). Built real mobile Tax Strategy view (incl. household) + 10 module detail/drill-down views (Net Worth, Protection, Savings, Retirement, Investment) fetching web `/api/*` via Bearer; wired dashboard finance blocks + tax-strategy next-steps; tidied `/module/savings` → `/savings`. All browser-verified with real data. Tech-debt: `tech-debt-report.md` (3 minor, 1 fixed).

### Outstanding
- [ ] **Deploy to csjones** — per `June/June5Updates/deploy-2026-06-05.md`: `git pull origin dev`, `config:cache`, rebuild `public/m-build` for the `/fynla/` base + upload (local m-build is LOCAL-base — don't upload). CSJ's call.
- [ ] Tax-strategy next-step **CTA** click is code-verified only — no seeded user triggered a next-step recommendation. Optionally engineer a qualifying user to click it live.
- [ ] CLAUDE.md metrics drift (pre-existing): PHP Services 340→345, Models 119→123 — update if/when convenient.
- [ ] Optional: extract a shared `resources/mobile/format.js` (9 views duplicate `formatCurrency`); add mobile-view tests.
- [ ] Optional cleanup: local test users (`mobiletax`, `mobiletax2`, `mobilemarried`, `mobilemarried2`) + AiDailyUsage resets.

## 2026-06-04 — CLAUDE.md lean + Fyn two→one (session 2, dev `05b1e8e`)

Leaned all 6 CLAUDE.md files: duplication → pointers (deploy runbook → new `deploy/DEPLOY.md`; design rules → `./fynlaDesignGuide.md`; mobile/Pest → sub-files). CSJ-owned laws kept (Rule 14 verbatim; 12/15/browser-testing lightly tightened, clauses preserved). Reframed the canonical Fyn section to "one prompt, two write states, converging to one Fyn" + a "where we are vs where we're heading" note. MEMORY.md rule numbers resynced; `project_coala_phase5_progress.md` gained the 2026-06-04 one-Fyn direction.

- [ ] **When `coala` merges to dev, reconcile `April/April24Updates/spec/00-canonical.md`** — it's the source of truth CLAUDE.md points to; CLAUDE.md is ahead of it by exactly the one transition note.
- [ ] Mobile `/m` work: build single-Fyn-compatible (one shared `/api/ai-chat` dispatch; no onboarding-vs-advice split client-side).


## 2026-06-03 — Mobile funnel + Fyn onboarding (dev/csjones; NOT prod)

**Shipped (PRs #452–#460):** `/m` hosts the real responsive homepage funnel (Sec-Fetch-Dest iframe handling); authed handoff to `/m/app` (Sanctum token bridge; mobile API now Bearer-only); homepage CTA → `/savetax` funnel; funnel answers persist through registration; "Register for free" creates a real account; Fyn pre-fills employment/marital + greets + recaps + skips-known + runs the savetax campaign onboarding; mobile Fyn dock wired to onboarding (bubbles + resume) + dashboard nudge; CLAUDE.md Rules #17–19 folded.

### Outstanding (mobile/funnel)
- [x] **Walk the mobile onboarding to the `/tax-strategy` terminal** — DONE 2026-06-04 (single + married/joint), real mobile Tax Strategy view built.
- [ ] Browser-verify the **desktop** Fyn funnel recap (backend verified; desktop UI not walked).
- [ ] Refresh **`Current State/Auth.md`** (16 days stale; mobile auth/onboarding/registration changed this session).
- [ ] Optional cleanup: remove csjones staging test users `Funnel Tester` #72 + `Cleo` #73.
- [ ] `dev → main` release is CSJ's call (dev is +41 / -7 vs main; main still carries the context-watch tripwire + lacks Rules #17–19).

---

## SP1 Pass 6 (Investments) — earlier track (backlog preserved)

*Last updated: 2026-05-28 — session 2 — **SP1 Pass 6 (Investments) — PRs 1-5a SHIPPED (5 PRs)**. Write-path COMPLETE (1-4) + first read cluster + parity contract (5a). PR 1 `15f6673` (#415), PR 2 `babcd53` (#416), PR 3 `10c4603` (#417), PR 4 `df1de8f` (#418), PR 5a `5ad4a91` (#419). All InvestmentAccount writes route through the store (boundary ready to LOCK in PR 12); InvestmentReadConsumerParityTest established as the 5b-5e contract. **NEXT SESSION: PR 5b** (Goals/ModelPortfolio/Performance reads) — follow the I-1 convention (prefer `User $user` signatures over in-method `User::find`+guards; see PR #419 note). 5 pre-existing follow-ups logged below. dev tip after this session's docs commit; csjones NOT redeployed (still pre-Pass-4-PR6 per deploy gate).*

---

## Parallel work — 2026-05-29 (separate from SP1 Pass 6)

- [x] **Revolut checkout spinner — FIXED + DEPLOYED (prod + dev).** Root cause: prod ran with config uncached → `.env` intermittently dropped DB creds → `forge`/no-password 500s → widget spun. Live-fixed prod via `config:cache`; code hardening shipped (CheckoutPage retry/error, AppServiceProvider forge guard, build.sh ends on `optimize`). main `13e88ad` (PR #422), dev (PR #421). See memory `reference_prod_forge_uncached_config.md`.
- [ ] **Pure freemium signup — SPEC + PLAN WRITTEN, awaiting execution.** Branch `pureFreemium` (off dev). Spec `docs/superpowers/specs/2026-05-29-pure-freemium-signup-design.md`; plan `docs/superpowers/plans/2026-05-29-pure-freemium-signup.md` (6 PRs, TDD). NEXT: CSJ picks execution approach → PR1 (registration sets `tier='free'`, no trial). Big pieces: PR2 CheckSubscription rework (Free writable, DbTierGate caps), PR4 data-safe trial→Free migration. NO code yet.

---

## 🎯 Active track: SP1 Pass 6 (Investments)

**Plan:** `docs/superpowers/plans/2026-05-27-sub-project-1-pass-6-investments-plan.md` (768 lines, written 2026-05-27 session 5 after Pass 5 closure; CSJ-approved full Investment surface scope).

**Scope:** All 6 Investment models — InvestmentAccount, Holding, InvestmentGoal, RiskProfile, InvestmentScenario, RebalancingAction. Largest entity surface in SP1 (173 InvestmentAccount refs alone). On Pass 6 close-out: SP1 = 14/19 stores shipped.

**Execution pattern:** subagent-driven-development — Sonnet implementer → Opus spec reviewer → Opus code-quality reviewer → CSJ admin-merge per PR. Same as Pass 5.

**Branch convention:** `feat/investment-store-prN` off `dev`.

**Unique-to-Pass-6 architectural pieces (per plan §0.2):**
1. **HoldingStore is cross-module** — accepts writes from BOTH `InvestmentController` AND `DCPensionHoldingsController` (closes Pass 3 deferral documented at `PensionStore.md:40`).
2. **2 cross-store recalc listeners** — Account ← Holdings AND Pension ← Holdings (mirrors Pass 5 Mortgage → Property but doubled).
3. **3 satellite stores bundled in PR 8** — InvestmentGoalStore + RiskProfileStore + InvestmentScenarioStore.
4. **Observer entanglement** — InvestmentAccountRiskObserver + InvestmentAccountGoalObserver must keep firing on user-driven writes.
5. **Polymorphic Holdings** — `morphTo('holdable')` accepting InvestmentAccount OR DCPension.

**16 PRs planned:**
- [x] **PR #415** — Pass 6 PR 1: InvestmentAccountStore facade + boundary + normaliser + events + tier-cap (merge `15f6673`, commit `9f24bb8`). 10 files / 934 LOC. Leaner mirror of MortgageStore (no derived/snapshot logic — that's PR 10). `ENTITY_KEY='investment'` (plan's `investment_account` was a stale audit claim — existing seeder key is `'investment'=2 free/null tier1+`; no seeder change). Event signatures mirror real `app/Events/Mortgage/*` `(entity,[changes,]user,source)`, NOT the plan's stale `(entity,int $userId)` prose. updateOrCreate idempotency tuple `(user_id, account_name, account_type)`. Boundary SOFT with 7 allowlisted write sites (CoordinatingAgent/InvestmentController/PreviewController/DocumentProcessor/OnboardingService/PreviewUserSeeder/ChrisUserSeeder), each annotated with removal PR. 27 tests green (13 store + 6 events + 8 normaliser). Both Opus reviewers APPROVE clean. Dedicated tier-cap test deferred to PR 11 (mirrors Pass 5 MortgageTierCapTest→PR7).
- [x] **PR #416** — Pass 6 PR 2: HTTP form requests through InvestmentAccountStore (merge `babcd53`, commit `81e627c`). 7 files / +255-66. `InvestmentController` storeAccount/updateAccount/toggleRetirementInclusion/destroyAccount + `PreviewController::seedInvestmentAccounts` routed through store (FORM / SEEDER). StoreValidationException→422, TierLimitExceededException→403, store-`find`+ownership-404 guard. All Holding write blocks left verbatim (PR 6/7). storeAccount keeps outer DB::transaction for account+holdings atomicity. validateCanonical `account_name` loosened `required`→`sometimes|nullable` (not in StoreInvestmentAccountRequest; DB col nullable). Boundary trimmed (5 sites remain: CoordinatingAgent/DocumentProcessor/OnboardingService/PreviewUserSeeder/ChrisUserSeeder). New InvestmentAccountHttpIntegrationTest (7 cases). 264 tests green; both Opus reviewers APPROVE (3 Minor, all pre-existing/dormant-latent).
- [x] **PR #417** — Pass 6 PR 3: Fyn AI write tools through InvestmentAccountStore (merge `10c4603`, commits `9ad640d`+`519a315`). `CoordinatingAgent::handleCreateInvestmentAccount` routed through store (FYN_AI) via `InvestmentAccountNormaliser::fromFyn`, mirroring handleCreateMortgage. CoordinatingAgent removed from boundary allowlist (4 sites remain). New InvestmentAccountFynCaptureIntegrationTest. **2 real bugs fixed:** (a) handler emitted `isa_type='stocks_shares'` but canonical is `'stocks_and_shares'` (would fail store validateCanonical) — Fyn-created ISAs were silently wrong; (b) `fromFyn` injected spurious `?? null` defaults that hit NOT NULL columns — fixed by null-stripping INSIDE fromFyn only. Review-fix commit `519a315`: earlier cut put null-strip + include_in_retirement default in SHARED normalise(), regressing HTTP update clear-to-null + partial-update flag preservation; relocated to fromFyn, normalise() reverted to PR-1, +2 regression tests (verified fail-then-pass). 397 tests green; both Opus reviewers APPROVE.
- [x] **PR #418** — Pass 6 PR 4: upload + onboarding + seeders through InvestmentAccountStore (merge `df1de8f`, commits `f7f023d`+`c565aef`+`9311008`). Routes the last 4 write sites: DocumentProcessor confirmExcel (UPLOAD), OnboardingService (FORM), ChrisUserSeeder (SEEDER updateOrCreate), PreviewUserSeeder (SEEDER create). MigrateEstateToNetWorth audited — zero investment writes. Boundary now has ZERO direct writes outside the store (only PreviewUserSeeder bulk-delete allowlisted). **3 fixes surfaced by routing:** (a) `lisa` persona account_type → canonical `isa`+`isa_type=lifetime`; (b) **reseed-duplicate bug** — store's `updateOrCreate` hardcoded match on nullable `account_name` → legacy NULL-name Chris row didn't match → duplicate £95k ISA on every reseed. Fixed by switching `updateOrCreate` to explicit `(match,data,user,source)` mirroring SavingsStore/PensionStore; ChrisUserSeeder matches on `(provider,account_type)`; regression test reproduces it; (c) `create()` force-overrides `user_id` at the boundary (sibling parity). 422 tests green; both Opus reviewers APPROVE (code-quality caught the reseed bug, re-reviewed the fix).
- [x] **PR #419** — Pass 6 PR 5a: Investment-internal reads + InvestmentReadConsumerParityTest (merge `5ad4a91`, commit `b04cb49`). 5 read sites routed (4 primary-only→`forUserPrimaryOnly`: EfficientFrontierCalculator/HoldingsDataExtractor/AccountTypeRecommender/AssetLocationOptimizer; 1 joint-aware→`forUser`: TaxDragCalculator). Eager-loads via `->load('holdings')`; HoldingsDataExtractor accountIds whereIn→Collection filter. All 5 services constructor-inject the store. NO boundary/allowlist change (reads unpoliced). **9-case InvestmentReadConsumerParityTest established** (superset of Mortgage sibling — adds forUserByType) = the contract for 5b-5e. 437 tests green; both Opus reviewers APPROVE. **I-1 convention decision for 5b-5e (carried forward):** 5a resolved `User::find($userId)` in-method with null-guards (kept `int $userId` signatures); the Estate sibling instead took `User $user` in the signature. **5b-5e should PREFER signature-change-to-`User`** (resolve at the controller boundary, no null-guard — per CLAUDE.md "don't validate impossible scenarios") so the codebase converges on ONE read-resolution pattern. Optional low-pri follow-up: retrofit 5a's 3 public-method guards to match.
- [ ] **PR 5b** — Goals/ModelPortfolio/Performance reads
- [ ] **PR 5c** — Rebalancing/Recommendation/Tax reads
- [ ] **PR 5d** — Utilities + Agents reads (InvestmentAgent, GoalsAgent, RetirementAgent, EstateAgent)
- [ ] **PR 5e** — Cross-module reads (NetWorth/Mobile/CrossModule/AI/Profile/Plans/GDPR)
- [ ] **PR 6** — HoldingStore cross-module facade + 2 listeners + `PensionStore::recalculateDerivedForPensionId`
- [ ] **PR 7** — Holding routing (HTTP InvestmentController + DCPensionHoldingsController + Fyn + upload)
- [ ] **PR 8** — InvestmentGoalStore + RiskProfileStore + InvestmentScenarioStore (3 satellite stores + routing)
- [ ] **PR 9** — RebalancingActionStore + routing (single confirmed write site at RebalancingActionsController:57)
- [ ] **PR 10** — Canonical derived columns + snapshots + cross-store recalc (5-6 migrations)
- [ ] **PR 11** — Tier-cap tests (×4 entities: InvestmentAccount, Holding, InvestmentGoal, InvestmentScenario)
- [ ] **PR 12** — Lock-down + parity + audit + Store.md (×6 docs) + PensionStore.md cleanup

**Open questions (per plan §19, resolve at PR 0 / PR 1 dispatch):**
- Q1: Tier-cap defaults per entity (proposed defaults in plan)
- Q2: HoldingStore::forParent return shape
- Q6: HoldingStore location (proposed: top-level `app/Services/Stores/`)
- Q8: Currency round-trip — defer or implement (proposed: defer, GBP-only)

**Estimated execution:** 7-10 days at Pass 5 cadence.

### Follow-ups found 2026-05-28 (pre-existing — NOT Pass 6 deliverables)

- [ ] **`MortgageControllerTest` is RED on `dev`** — 7 failures / 11 pass (verified 2026-05-28). Root cause: Pass 5 PR 2 routed Mortgage create/update through `MortgageStore`→`DbTierGate`→`TierConfigurationStore::forTier()->firstOrFail()`, but `tests/Feature/Api/MortgageControllerTest.php` never seeds `TierConfigurationSeeder`, so POST-create 404s ("Endpoint not found" via the global ModelNotFoundException handler). Targeted-test runs in Pass 5 missed it (full suite not run). **Fix:** add `$this->seed(TierConfigurationSeeder::class)` (or equivalent) to that file's `beforeEach`, mirroring what Pass 6 PR 2 did for `InvestmentControllerTest`/`InvestmentModuleTest`. Trivial; do as a standalone bug-fix PR off dev.
- [ ] **Latent: preview spouse created without `is_preview_user`** (`PreviewController.php:673`) + nullable tier → resolves toward free/cap-2. Dormant today (every persona's spouse has 0 investment accounts), but a future persona JSON giving a spouse 3+ investment accounts would throw `TierLimitExceededException` mid-`DB::transaction` and abort the persona seed (now that `seedInvestmentAccounts` routes through the tier-gated store). Fix when/if it bites: set `is_preview_user => true` on the seeded spouse, OR add a seeder-context tier-cap bypass.
- [ ] **`isa_type` canonical drift — `'stocks_shares'` vs `'stocks_and_shares'`** (found Pass 6 PR 3, 2026-05-28). Investment-module canonical `isa_type` is `'stocks_and_shares'` (StoreInvestmentAccountRequest:62, UpdateInvestmentAccountRequest:68, validateCanonical). Two pre-existing sites still emit the old non-canonical `'stocks_shares'`: `app/Services/Retirement/RetirementIncomeService.php:392` (fallback `?? 'stocks_shares'` — feeds an internal projection array, not persisted/validated, harmless today) and `database/factories/Investment/InvestmentAccountFactory.php:58` (`isa()` state — would fail the store's validateCanonical if a factory-isa row is round-tripped through `InvestmentAccountStore::update`). Fix both to `'stocks_and_shares'` in a small follow-up. NOTE: the Savings module has its OWN separate `'stocks_shares'` isa_type enum — do NOT touch Savings.
- [ ] **Lifetime ISA mis-bucketed in investment ISA tracking** (found Pass 6 PR 4, 2026-05-28). `app/Services/Savings/ISATracker.php` (investment branch ~115-141) + `ISAAllowanceOptimizer.php` (~138-146) bucket investment Lifetime ISAs by `account_type === 'lifetime_isa'` — a value that does NOT exist in the canonical InvestmentAccount enum (`isa,gia,...`). They should key off `isa_type === 'lifetime'`. Consequence: after PR 4's `lisa`→`isa`+`lifetime` persona remap, a Lifetime ISA's subscription reports under `stocks_shares_isa_used` rather than `lisa_used` (net-positive vs the pre-PR4 state where it was counted NOWHERE, but still mis-categorised). Fix: read `isa_type='lifetime'` in both services. Investment-module only — the Savings-module LISA path (account_type='lisa'/isa_type IN LISA,lisa) is separate and correct.

### Deploy gate (outstanding from Pass 5)

- [ ] **csjones re-deploy** — currently at `f2b5bec1`. Needs `git pull origin dev` + `php artisan migrate --force` (5 pending migrations: 2 from Pass 4 PR 6 + 3 from Pass 5 PR 6) + `composer dump-autoload -o && php artisan optimize && cache:clear`. Then Playwright browser-smoke on `/mortgages` + `/net-worth/property` to close Pass 4 §16.1 gate 8 + Pass 5 §16.1 gate 8.

---

## ✅ Recently completed: SP1 Pass 5 (Mortgages) — DONE

**Final merge:** PR #414 → `e4d8039` (2026-05-27 session 5). All 8 PRs shipped this session via subagent-driven-development with 2-stage Opus review per PR. 8/19 entity stores complete after Pass 5 (Savings + 4 ref-data + Pensions + Properties + Mortgages).

**Deploy gate (csjones):** still at `f2b5bec1`. Now needs 5 migrations on next `git pull origin dev && php artisan migrate --force` — 2 from Pass 4 PR 6 + 3 from Pass 5 PR 6. Then run `composer dump-autoload -o && php artisan optimize && cache:clear`. Then Playwright browser-smoke on `/mortgages` to close Pass 4 §16.1 gate 8 + Pass 5 §16.1 gate 8.

### Archived: Pass 5 (Mortgages) detail

**Plan:** `docs/superpowers/plans/2026-05-27-sub-project-1-pass-5-mortgages-plan.md`

**Plan:** `docs/superpowers/plans/2026-05-27-sub-project-1-pass-5-mortgages-plan.md` (3216 lines)
**Scope decision:** Mortgages only — `App\Models\Estate\Liability` (unsecured consumer debt) deferred to Pass 5b. See plan §0.1 for rationale.
**Execution pattern:** subagent-driven-development — implementer (Sonnet) → spec reviewer (Opus) → code-quality reviewer (Opus) → CSJ admin-merge per PR
**Branch convention:** `feat/mortgage-store-prN` off `dev`

### PRs planned (8 PRs)
- [x] **PR #403** — Pass 5 PR 1: MortgageStore facade + arch boundary + normaliser + 4 events + tier-cap (merge `fe5e1a1`). 17 tests pass. Code-quality review surfaced 4 Important sibling-convention drifts (boundary regex missed updateOrCreate, event shapes diverged from Property template, update/delete took Mortgage instance instead of int id, updateOrCreate dispatched empty changes payload) — all fixed in commit `4eeb4cb`. Migrated validateCanonical to Laravel Validator + StoreValidationException + ValidationLimits::currencyRules. Approved by both reviewers.
- [x] **PR #404** — Pass 5 PR 2: HTTP form requests through MortgageStore (merge `a78ddd2`). MortgageController store/update/destroy routed through MortgageStore::create/update/delete with IngestSource::FORM. PreviewController::seedMortgages uses IngestSource::SEEDER (matches sibling seedProperties convention). Code-quality review surfaced: (a) missing TierLimitExceededException catch in store() — added with structured 403 + integration test, (b) unused Mortgage import in PreviewController removed, (c) destroy() null-guard added for consistency. Pre-existing PR 1 oversight fixed: MortgageStoreBoundaryTest needed `uses(Tests\TestCase::class)` binding. 18 tests pass.
- [ ] **PR 3** — Fyn AI write tools through MortgageStore (handleCreateMortgage). Plan §7.
- [ ] **PR 4** — Upload + onboarding + seeders + `MortgageService::createFromPropertyData` through MortgageStore. Plan §8.
- [~] **PR 5** — Read consumers, sub-clustered 5a-5e (~24 service files + `MortgageReadConsumerParityTest`). Plan §9.
  - [x] **PR #407** — Pass 5 PR 5a: Estate/IHT reads + MortgageReadConsumerParityTest (merge `49b0dd2`). 6 Estate services routed through MortgageStore (EstateAssetAggregatorService joint-aware, EstateActionDefinitionService primary-only, EstateDataReadinessService primary-only, IHTFormattingService joint-aware with property eager-load, LetterEstateValidationService primary-only, ComprehensiveEstatePlanService joint-aware). EstateAgent audited — no direct reads. ComprehensiveEstatePlanService::getDetailedLiabilities signature changed `int $userId` → `User $user` (private method, no external callers). 7-case parity test locks joint-aware vs primary-only contract for 5b-5e. 346 store tests + 198 Estate regression + 16 downstream tests all pass. Implementer hit Pint import-strip 5/6 files — pattern: add import + reference in same edit; if formatter strips, re-add and the constructor reference preserves it on second pass.
  - [x] **PR #408** — Pass 5 PR 5b: NetWorth/Mobile/CrossModule reads (merge `e653602`). 2 service files routed (CrossModuleAssetAggregator + MobileDashboardAggregator); NetWorthService confirmed to have zero direct mortgage reads (delegates entirely to CrossModuleAssetAggregator). Helper `sumMortgageJointOwnerShares(User $user, int $userId): float` introduced in MobileDashboardAggregator mirroring `sumPropertyJointOwnerShares` precedent. Both reviewers caught a unanimous CRITICAL regression in first cut: filtering `mortgageStore->forUser($user)` against itself collapsed the property-mortgage cross-link leg to an empty Collection (couple shares property + only one spouse holds mortgage scenario). Fix: revert the cross-link leg to raw `Mortgage::whereIn('property_id', ...)->whereNotIn('id', ...)->get()` matching Pass 4 PropertyStore sibling pattern (reads not policed by boundary). 8th parity case added locking the cross-link semantic so 5c/5d/5e implementers can't drop it again. 30 tests pass.
  - [x] **PR #409** — Pass 5 PR 5c: Coordination/AI/UserProfile reads (merge `fc4fe51`). 6 reads migrated across 5 production files (HouseholdPlanningService × 2 joint-aware; DuplicateAcknowledgement custom-chain hybrid Collection-on-store; PersonalAccountsService × 1 joint-aware; LetterToSpouseService × 1 primary-only HasMany; UserProfileService × 1 primary-only HasMany). 5 relationship-access keepers (AdvicePromptBuilder × 2, UserProfileService × 3 via `$property->mortgages`/`->load('mortgages')`) preserved per Pass 4 PR 5d precedent. Pass 4 sibling commit `822f54d` only migrated joint-aware Property sites in PersonalAccountsService and left `$user->properties` HasMany sites alone (`:105`, `:233`); PR 5c stays faithful by leaving `$user->mortgages` HasMany sites at PersonalAccountsService:101/229 alone. Spec reviewer flagged this as Critical but resolved as over-application after sibling check. Phase02ArchitectureTest constructor-count assertion stale 1→3 fix bundled in (Pass 4 didn't update when it added PropertyStore, so PR 5c fixes both). Code-quality fixup commit `e6653ac` dropped 3 unused `use App\Models\Mortgage;` imports + reordered MortgageStore constructor param after PropertyStore in DuplicateAcknowledgement + LetterToSpouseService. 134 tests pass; parity 8/8; pint clean.
  - [x] **PR #410** — Pass 5 PR 5d: Goals + Protection reads (merge `46dc4f2`). Plan §9.4 listed 10 files but audit found only 3 active sites across 2 files — 7 of 10 had zero mortgage reads (UserContextBuilder had `whereNotIn('liability_type', ['mortgage', ...])` on Liability table — NOT a Mortgage model read). 3 sites all `$user->mortgages` HasMany pattern migrated to `forUserPrimaryOnly` per rubric: GoalsProjectionService:265 (`$user->mortgages ?? collect()`), :268 (`$user->spouse->mortgages ?? collect()` — null guard preserved by surrounding `if ($household && $user->spouse)`), ProtectionAgent:147 (`$user->mortgages()->sum('outstanding_balance')` → Collection ->sum — equivalent because `outstanding_balance decimal(15,2) NOT NULL DEFAULT 0.00`, bounded N per user). Pre-existing dead eager-load at GoalsProjectionService:55 (`User::with(['mortgages', 'spouse.mortgages'])`) flagged by spec reviewer but explicitly deferred as out of scope. Both reviewers APPROVE clean. Commit message says "Plans/Investment" but actual diff is "Goals + Protection" — cosmetic mis-wording, not a blocker.
  - [x] **PR #411** — Pass 5 PR 5e: GDPR + Protection + RateAlerts reads (merge `e50dfde`). FINAL read cluster — closes PR 5. 3 production files changed (+21/-3): 2 sites migrated (GDPR/DataExportService:148 `$user->mortgages()->get()` → `forUserPrimaryOnly`; Protection/ProtectionDataReadinessService:357-360 optimization-preserving — kept `relationLoaded` true-branch on already-loaded Collection, migrated only the false-branch `->exists()` → `forUserPrimaryOnly($user)->isNotEmpty()`); 1 system-scope KEEP at SendMortgageRateAlerts:18 with 11-line docblock mirroring PR 5b cross-link precedent (cross-user `rate_fix_end_date` query can't go through user-scoped store; reads not policed by boundary test); 6 relationship-access sites in PropertyService + PropertyCalculationService KEPT per Pass 4 PR 5d precedent (`$property->mortgages`); MortgageService calc helpers out of scope per plan §1.7 (take `Mortgage $mortgage` parameter, not DB reads). ProtectionDataReadinessService gained fresh constructor (no prior constructor). No test constructor updates needed (both services container-resolved). Implementer stalled on Pint after first edit; SendMessage nudge resolved per handover pattern. Both reviewers APPROVE — spec reviewer called it "cleanest in the Pass 5 series". 57 in-scope tests + 8/8 parity + 1/1 boundary green.
- [x] **PR #412** — Pass 5 PR 6: canonical derived columns + snapshots + Property reconciliation (merge `8ec33c6`). 26 files, +924/-26 LOC. **3 migrations** (mortgages derived columns; mortgage_value_snapshots table; properties.outstanding_mortgage_calculated_at) — shortened snapshot index name to `mvs_mortgage_type_snapshotted_idx` to stay under MySQL's 64-char limit. New MortgageDerivedColumnCalculator + MortgageValueSnapshot model + factory + 2 snapshot policies (mortgageBalance ≥£1k OR ≥0.5%; mortgageRate ≥0.25pp). MortgageStore constructor extended (TierGate + Calculator + SnapshotPolicies); recalculateDerived hooked into create/update/updateOrCreate. New RecalculatePropertyOutstandingMortgage listener wired to all 4 Mortgage events. New PropertyStore::recalculateDerivedForPropertyId public method. PropertyDerivedColumnCalculator now reads canonical `mortgageStore->forProperty($id, null)->sum('outstanding_balance')`. **Loop prevention is defense-in-depth**: `saveQuietly` blocks Eloquent events at the persistence layer AND no listeners are registered on any Property event that could fire back. PropertyStore::recalculateDerived signature broadened (`User`→`?User`, `IngestSource`→`?IngestSource`) with snapshot writes skipped on cross-store path (null source). 2 backfill commands (`mortgages:backfill-derived-columns`, `properties:backfill-outstanding-mortgage`) using `chunkById(200)` + `forceFill+saveQuietly`. 4 new test files (15 cases) + 4 existing Property tests updated (seed step only, assertions unchanged). 344 tests pass / 875 assertions in full sweep. Pre-existing Phase03ArchitectureTest failures confirmed not introduced. Code-quality fixup commit `d258b10` dropped `final` from 4 newly-added files to align with Pass 4 sibling non-final convention + added observer-dedup docblock on PropertyStore::recalculateDerived. **csjones deploy gate updated**: now needs Pass 4 PR 6 (2 migrations) + Pass 5 PR 6 (3 migrations) = 5 pending migrations on next `git pull && php artisan migrate --force`.
- [x] **PR #413** — Pass 5 PR 7: MortgageTierCapTest (merge `ad5f777`). Single test file, 5 cases mirroring PropertyTierCapTest with mortgage adaptations (cap=10 → 11th rejected; tier1 unlimited; exception assertions; global DbTierGate binding). Plan §11 template had bugs (wrong exception class `TierCapExceededException` vs actual `TierLimitExceededException`; non-existent `Subscription::TIER_1`; missing `TierConfigurationStore::set` method; global `minimalCanonical()` helper). Implementer correctly mirrored sibling pattern + used inline arrays. Both reviewers APPROVE with zero findings. 5 tests / 8 assertions green.
- [x] **PR #414** — Pass 5 PR 8 (FINAL): boundary LOCKED + audit + parity + Store.md (merge `e4d8039`). **CLOSES SP1 Pass 5.** Boundary test rewritten to LOCKED framing — 3 allowlist entries (EncryptExistingData + ResetPreviewData + PreviewUserSeeder, mirroring Pass 4 sibling precedent). MortgageAuditIngestSourceTest: 6 cases (5 IngestSource values + audit-context-leak test added in fixup per code-quality I-2). MortgageThreeIngestParityTest: 2 cases (9-field source + 3 derived columns in fixup per I-3; tenants_in_common coercion to joint). MortgageStore.md: 230 lines mirroring PropertyStore.md (Overview, Boundary, Public API 7 reads + 5 writes, Joint-aware contract, Derived columns, Snapshot policies, Tier-cap, Cross-store recalc, Events, 11 quirks, Migration history with accurate merge SHAs per I-1, Acceptance criteria mapping). Both reviewers APPROVE; fixup commit `bc360e9` addressed I-1/I-2/I-3 (merge SHAs accurate, leak-prevention test, derived-column parity coverage). §16.1 gates 1-7 closed inline; gate 8 (Playwright browser-smoke) outstanding pending csjones deploy.

### Unique-to-Pass-5 architectural piece

**Cross-store recalc.** A write to MortgageStore for `property_id=X` triggers `PropertyStore::recalculateDerivedForPropertyId(X)` via a synchronous event listener (`RecalculatePropertyOutstandingMortgage`). PropertyDerivedColumnCalculator updated to read canonical mortgages sum (not the denormalised `properties.outstanding_mortgage` field). One-way recalc — Mortgage → Property only, no loops. Locked by `MortgagePropertyReconciliationTest` (PR 6) + documented in `MortgageStore.md` quirk #9.

This closes the deferred reconciliation flagged in Pass 4 plan §0.

### Open questions (resolve at PR 1 dispatch)

- **Q1** — Tier-cap default for `mortgage` (proposed: free=10, tier1+=null). Adjustable later.
- **Q2** — `forUserByProperty` return shape (proposed: `Collection<int, Collection<int, Mortgage>>` keyed by property_id).
- **Q5** — Keep or drop `properties.outstanding_mortgage` column (proposed: KEEP as write-only-by-recalc derived column).
- **Q7** — Estate Liabilities defer to Pass 5b (proposed: YES).

See plan §15 for the full list.

### Deploy gate

- [ ] **csjones re-deploy before PR 1 dispatch** — csjones at `f2b5bec1`, dev at `eb260fc` (Pass 4 PR 6 added 2 migrations not yet applied on csjones). Run `git pull origin dev` + `php artisan migrate --force` + cache:clear + optimize. Then Playwright browser-smoke on Properties pages to close Pass 4 §16.1 gate 8.

---

## Recently completed: SP1 Pass 4 (Properties) — DONE

**Merge:** `c972fff` (PR #402, 2026-05-27). 12 PRs total shipped 2026-05-26 → 2026-05-27 via subagent-driven-development. PropertyStore fully shipped, boundary LOCKED, derived columns + snapshots LIVE, three-ingest parity test passing, `PropertyStore.md` 195 lines.

**Spec doc updated** at commit `eb260fc` (2026-05-27 session 4) — `docs/superpowers/specs/2026-05-14-module-canonical-store-design.md` frontmatter + §0 + §15.3 + §16.2 + §21.1 + §21.3 all reflect Pass 4 close-out.

### Archived: Pass 4 (Properties) detail

**Plan:** `docs/superpowers/plans/2026-05-26-sub-project-1-pass-4-properties-plan.md`
**Execution pattern:** subagent-driven-development — implementer (Sonnet) → spec reviewer (Opus) → code-quality reviewer (Opus) → CSJ admin-merge per PR
**Branch convention:** `feat/property-store-prN` off `dev`

### PRs merged
- [x] **PR #387** — Pass 4 PR 1: PropertyStore facade + arch boundary + normaliser + 4 events (merge `9da1590`)
- [x] **PR #388** — Pass 4 PR 2: HTTP form requests + cross-store tier-limit Option A alignment (merge `b8cbec5`)
- [x] **PR #389** — Pass 4 PR 3: Fyn AI write tools + DB::transaction atomicity (merge `ba42683`)
- [x] **PR #390** — Pass 4 PR 4: upload + onboarding + seeders at PropertyStore (merge `df357e9`). Surfaced + disclosed 2 pre-existing bug fixes (MigrateEstateToNetWorth current_valuation→current_value; OnboardingService annual_rental_income drop). In-flight Minor #1 fix added PropertyNormaliser::fromForm seam in OnboardingService (commit `3074029`).
- [x] **PR #395** — Pass 4 PR 5a: Estate/IHT read consumers + PropertyReadConsumerParityTest (merge `262ad96`). Code-quality review caught a Major regression — `PropertyStore::forUser` is JOINT-AWARE (returns `user_id = ? OR joint_owner_id = ?`), silently broadening 7 sites that originally used `Property::where('user_id', $userId)`. Fix appends `->where('user_id', $user->id)` to the Collection chain for primary-only consumers. 7-case parity test locks the contract for 5b/5c/5d/5e.
- [x] **PR #396** — Pass 4 PR 5b: NetWorth/Mobile/CrossModule read consumers (merge `e718e23`). 2 primary-only sites (NetWorthService) with `->where('user_id')` filter; 5 joint-aware sites (CrossModuleAssetAggregator) using `forUserWithJointOwner` without filter; 1 helper-mediated site (MobileDashboardAggregator) via new `sumPropertyJointOwnerShares` helper mirroring savings sibling. Both reviewers APPROVE clean. PR 5a trap NOT re-introduced.
- [x] **PR #397** — Pass 4 PR 5c: Coordination/Trust read consumers + new `PropertyStore::forTrust($trustId)` (merge `97c4365`). 3 sites routed in HouseholdPlanningService (2 primary-only via `forUserByType + where(user_id)`, 1 joint-aware via `forUserWithJointOwner`), 1 polymorphic loop deferred as documented residual (`:737` `$assetTypes = [Property::class, ...]` array — refactor to JointAssetFinder service when all 5 entity stores exist). 1 trust-scoped site in TrustAssetAggregatorService via new `forTrust` method. 3 unit tests for `forTrust` (match / empty / null exclusion). Both reviewers APPROVE clean.
- [x] **PR #398** — Pass 4 PR 5d: AI + UserProfile read consumers (merge `02a9711`). 7 sites across 5 files. 1 primary-only (ProfileCompletenessChecker), 6 joint-aware including: `->load('mortgages')` lazy-eager-load pattern (AdvicePromptBuilder:819 + UserProfileService:197) and SQL `whereRaw` postcode normalisation → PHP Collection filter (DuplicateAcknowledgement:367). Implementer dispatch truncated on formatter import-removal; main thread completed the work directly. Both reviewers APPROVE clean.
- [x] **PR #399** — Pass 4 PR 5e: Tax + Documents read consumers (merge `d76e809`). Final cluster of PR 5. 1 real site (IncomeDefinitionsService:88 — buy-to-let rental income via `forUserByType`). 2 class-name-only residuals kept (DocumentTypeDetector + PropertyMapper — `Property::class` dispatch keys for the upload field-mapper registry). Handled directly without subagent dispatch given the tiny scope. **PR 5 COMPLETE.**

### PRs remaining (in order)
- [x] **PR #400** — Pass 4 PR 6: canonical derived columns + snapshot table (merge `84a55ac`). Adds `current_value_gbp` + `equity_gbp` + `loan_to_value_pct` columns + `current_value_gbp_calculated_at`/`equity_gbp_calculated_at`/`loan_to_value_pct_calculated_at` timestamps + `PropertyValueSnapshot` table + `PropertyDerivedColumnCalculator` + `BackfillPropertyDerivedColumns` artisan command + 2 snapshot policies (`propertyValue`, `propertyEquity` — £1k absolute OR 0.5% relative threshold, 2555-day retention matching Pension). `recalculateDerived` wired into create + update (transitively into updateOrCreate). Backfill uses `forceFill + saveQuietly + chunkById(200)` mirroring Savings/Pension precedent. Both reviewers APPROVE clean. **Includes 2 migrations** — csjones needs `php artisan migrate --force` on next deploy.
- [ ] **PR 7** — Tier-cap test for property. Plan §11. PropertyTierCapTest with 5 cases. Enforcement seam already wired in PR 1.
- [ ] **PR 8** — Lock-down + parity + audit + `PropertyStore.md`. Plan §12. Reword boundary to LOCKED framing, PropertyAuditIngestSourceTest, PropertyThreeIngestParityTest (incl. `tenants_in_common` case), PropertyStore.md. §16 close-out IN-LINE.

### PR 6 cosmetic minors (deferred — code-quality review)

- **M1** PropertyStore.php:198-201 — `recalculateDerived` skip-on-null comment says "null short-circuits shouldSnapshot to true". Behavior correct but rationale slightly misleading (it's OLD-null that short-circuits, not NEW-null). Same copy-paste from SavingsStore.php:231 — pre-existing pattern. Cosmetic.
- **M2** Missing class-level docblock on `BackfillPropertyDerivedColumns`. Calculator has one; console command doesn't.
- **M3** PropertyStoreTest:193-194 — `->toBeGreaterThanOrEqual(2)` could be tightened to `->toBe(2)` (current code always produces exactly 2 snapshots on first create with non-null value+mortgage). Forward-compat could justify the loose form.

### ⚠️ CRITICAL — PropertyStore::forUser is joint-aware (5a review-loop discovery)

`PropertyStore::forUser(User $user): Collection` calls `Property::forUserOrJoint($user->id)->get()` internally — returns `WHERE user_id = ? OR joint_owner_id = ?`. Same applies to `forUserByType`.

**For any consumer that originally used `Property::where('user_id', $userId)` (primary-only), chain `->where('user_id', $user->id)` onto the Collection to restore primary-only semantics.** Pattern:

```php
// Pre-PR-5a: Property::where('user_id', $userId)->sum('current_value')
// Post: $propertyStore->forUser($user)->where('user_id', $user->id)->sum('current_value')
```

For consumers that originally used `Property::forUserOrJoint($userId)` (joint-aware, typically followed by `calculateUserShare`), use `forUserWithJointOwner($user)` and DO NOT add the filter.

`PropertyReadConsumerParityTest` locks 7 cases covering both patterns.
- [ ] **PR 6** — Canonical derived columns + snapshot table. Plan §10. `current_value_gbp`, `equity_gbp`, `loan_to_value_pct` + PropertyValueSnapshot table + PropertyDerivedColumnCalculator + BackfillPropertyDerivedColumns command + 2 snapshot policies.
- [ ] **PR 7** — Tier-cap test for property. Plan §11. PropertyTierCapTest with 5 cases. Enforcement seam already wired in PR 1.
- [ ] **PR 8** — Lock-down + parity + audit + Store.md. Plan §12. Reword boundary to LOCKED framing, PropertyAuditIngestSourceTest, PropertyThreeIngestParityTest (incl. tenants_in_common case), PropertyStore.md. §16 close-out IN-LINE.

### Deploy gate
- [x] **csjones deploy** — completed at start of 2026-05-27 session 3. csjones at `aa65ab80` (matches dev pre-PR4-merge). Bundle hash verified byte-identical. Re-deploy before PR 5 dispatch (PR 4 added runtime code to OnboardingService + DocumentProcessor + MigrateEstateToNetWorth).
- [ ] **csjones re-deploy** before PR 5 starts — at minimum 2 commits behind (PR #390 merge `df357e9` + #391 mobile-landing merge `68783e3`).

---

## Sub-Project 1 overall — 7 of 19 entity stores shipped

| Pass | Entity | Status |
|---|---|---|
| 1 | Savings | DONE (locked PR 8) |
| 2 | Reference data R1-R4 | DONE (locked 26 PRs) |
| 3 | Pensions (DC/DB/State/InputHistory) | DONE (8 PRs + close-out PR #385) |
| 4 | Properties | DONE (12 PRs, merge `c972fff`, boundary LOCKED) |
| **5** | **Mortgages** | **plan written 2026-05-27, PR 1 not yet dispatched (this track)** |
| 5b (future) | Estate Liabilities (`App\Models\Estate\Liability`) | not started — separate plan; see Pass 5 plan §0.1 |
| 6 | Investments | not started — no plan |
| 7 | Income + Expenditure | not started — no plan |
| 8 | Protection | not started — no plan |
| 9 | Family members | not started — no plan |
| 10 | Goals + life events | not started — no plan |
| 11 | Chattels | not started — no plan |
| 12 | Business interests | not started — no plan |
| 13 | Trusts | not started — no plan |
| 14 | Wills + LPAs | not started — no plan |

---

## Parallel: CoALA track

CSJ shipped `fynla-coala-implementation-plan.md`, `fynla-coala-stakeholder-brief.md`, and 6 phase PRDs (`May/May27Updates/PRD-coala-phase-{1-6}-*.md`) to dev. Separate workstream from SP1 store migration. Not in this CSJTODO's scope — handled by CSJ directly.

---

## Tech debt deferred (from PR 1–3 review loop)

- [ ] **`validateCanonical($data, $partial)` vestigial parameter** — exists on SavingsStore + PensionStore validateDcCanonical. PropertyStore had it removed in PR 2 review. Either align siblings or document the reason it's kept.
- [ ] **Test file location convention drift** — Property HTTP integration test at `tests/Feature/Stores/PropertyHttpIntegrationTest.php`; Pension's at `tests/Feature/Retirement/PensionStoreHttpIntegrationTest.php`. Pick one for future passes (5+).
- [ ] **`CreateInvestmentAccountTest` failures in broad sweeps** — 2 cases (validation_failed + preview-blocks) fail in `pest tests/Feature/Api/ tests/Unit/Services/Stores/ tests/Architecture/ tests/Feature/Stores/ tests/Feature/AI/DirectWrite/` but pass in isolation. Test-ordering / DB state interference. NOT caused by Pass 4 — pre-existing. Investigate when convenient.
- [ ] **PropertyController has 5 deps by end of Pass 5** — flag for Pass 5 reviewer whether MortgageService should fold into MortgageStore at that point.

## Tech debt deferred (from PR 4 review loop)

- [ ] **Constructor injection for stores + normalisers in OnboardingService** — `OnboardingService` resolves `PropertyStore`, `SavingsStore`, `PensionStore`, `PropertyNormaliser`, `SavingsAccountNormaliser`, `PensionNormaliser` via `app(...)` instead of `private readonly` constructor DI per `app/Services/CLAUDE.md`. Pre-existing pattern (not a PR 4 regression). Address as part of PR 5/6 read-consumer cleanup or a standalone follow-up.
- [ ] **`ChrisUserSeeder` BTL property `ownership_type=joint` without `joint_owner_id`** — `database/seeders/ChrisUserSeeder.php:159-177` has `'ownership_type' => 'joint'` + `'joint_owner_name' => 'wife'` but no `joint_owner_id` (chris is `marital_status=single`). `validateCanonical` accepts this, but the canonical Joint Assets pattern (CLAUDE.md Rule #7) uses `joint_owner_id` + `ownership_percentage`. Pre-existing — flagged for visibility during a future seeder canonicalisation pass.
- [ ] **`PropertyUploadIngestTest` could lock `IngestSource::UPLOAD` audit signal** — `tests/Feature/Stores/PropertyUploadIngestTest.php` asserts row count + 2 field values but not the audit-context `ingest_source` value. Optional consistency hardening — add `AuditLog::where('ingest_source','upload')->exists()` assertion. Pass 3 PensionStore equivalent test was happy-path-only too; consistent. Hardening, not a defect.
- [ ] **Out-of-scope: `MigrateEstateToNetWorth` `current_valuation`→`current_value` bug pattern exists in sibling methods** — `migrateBusiness` (:201) and `migrateChattel` (:223) still pass `current_valuation` for `business_interests` and `chattels` tables. PR 4 only fixed the Property case. Same fix needed in Pass 11 (Chattels) and Pass 12 (Business interests) when those passes start.

---

## Known issues

- None blocking. Pass 4 PR 4 can start immediately after csjones deploy.

---

## Deploy status

- **main (fynla.org):** unchanged. Last release 22 May. ~35 commits behind dev now (Pass 4 PRs 1+2+3+4 + CoALA docs + Pass 3 close-out + Pass 4 plan + mobile-landing).
- **dev (csjones.co/fynla):** at `aa65ab80` (just before PR 4 merge). 2+ commits behind dev HEAD (PR #390 + PR #391 mobile-landing merged after deploy). Re-deploy before PR 5 dispatch.

---

## Reminders for next session

- PR 4 merged at `df357e9`. csjones still at `aa65ab80` — re-deploy before PR 5 dispatch.
- Plan §9 of `docs/superpowers/plans/2026-05-26-sub-project-1-pass-4-properties-plan.md` is the canonical spec for PR 5 (read consumers, sub-clustered, ~21 files — biggest PR of Pass 4).
- Sub-cluster strategy from Pass 3 precedent: 5a Estate/IHT → 5b NetWorth/Mobile → 5c Coordination/Trust → 5d AI/Profile → 5e Tax/Documents. One PR per cluster OR bundle in one branch with multi-commit per cluster — CSJ's call at dispatch time.
- Subagent-driven-development workflow continues: implementer (Sonnet) → spec reviewer (Opus) → code-quality reviewer (Opus) → CSJ admin-merge per cluster PR.
- PR 4 review-loop lessons (for PR 5 implementer brief):
  - **Don't skip the normaliser seam** — every store call should be `Normaliser::from*` + `Store::create/update`. PR 4 code-quality review caught this on OnboardingService's property block.
  - **Disclose pre-existing bugs in PR body** rather than silently fixing — PR 4 did this for `current_valuation` and `annual_rental_income`.
  - **TierConfigurationSeeder discipline** — every test exercising `*Store::create` must seed it in `beforeEach`.
- Don't `migrate:fresh`. Don't ship to main without csjones browser-verify first.
