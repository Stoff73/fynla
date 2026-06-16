# Progress — seeded 2026-05-19

## 2026-05-19 — session 1 (context-clear)
- Handover: [[handover-2026-05-19-session-1-clear]]
- Branch: `iFrames` (pushed to `origin/iFrames`, tracks it; off `origin/dev`)
- Commits this session: 16 (spec, plan, Tasks 1–8 + 8b, 2 plan-doc corrections)
- HEAD: `5d293659` · Working tree: clean (0 uncommitted)
- Shipped: SP3 spec + plan + Tasks 1–8 + 8b (isolated mobile iframe scaffold). Each task passed spec + code-quality review (fixes applied where flagged). Build green; SP3's 9 `MobileScaffoldTest` pass.
- Next: complete Task 8b spec + code-quality reviews (interrupted); resolve Pest 60-vs-15 baseline question (verify pre-existing DB-ordering); then Task 9 (E2E Playwright + README + spec §5.3 fix + PR `iFrames`→`dev`).

## 2026-05-20 — session 1 (end-of-day, wrap of 2026-05-19 work)
- Handover: [[handover-2026-05-20-session-1]]
- Branch: `iFrames` (HEAD `560b4107`, pushed, tracks `origin/iFrames`)
- Commits this wrap: 0 code (no new work since the context-clear); ran deferred tech-debt-session audit
- Tech debt: 0 critical, 2 warnings, 5 suggestions → `tech-debt-report.md`. Only real code item: `/m/app/` trailing-slash frame-header edge case (fold into Task 9).
- Vault absent on this machine — vault-sync skipped; planning-with-files is the continuity channel.
- Next: Task 8b reviews + settle Pest 60-vs-15 + Task 9 (E2E/README/spec §5.3/PR) + fold in the `/m/app/` SecurityHeaders fix.

## 2026-05-22 — session 1 (context-clear, SP3 fallout + SP1 Pass 2 R4)

- Handover: [[handover-2026-05-22-session-1-clear]]
- Branch: `dev` (HEAD `af48444`)
- Shipped: SP3 admin-merges (#342–#345), csjones deploy of SP3, SP1 Pass 2 plan PR (#347), PR 0 shared infra (#346), R4 track × 5 (#348–#352)
- Tripwire fired at 923k tokens before vault-sync — sync deferred to session 2 (now done).
- Next: csjones deploy of R4 + SP1 Pass 2 R3 track inline.

## 2026-05-22 — session 2 (context-clear, SP1 Pass 2 R3 + R2)

- Handover: [[handover-2026-05-22-session-2-clear]]
- Branch: `dev` (HEAD `e2bb243`)
- Commits this session: 47 total today across sessions 1+2 (per vault git history). Session 2 itself shipped 10 PRs.
- Status: clean (0 uncommitted)
- Shipped: csjones deploy of R4 → SP1 Pass 2 R3 × 5 (#354–#358) → csjones deploy of R3 → SP1 Pass 2 R2 × 5 (#359–#363). SP1 Pass 2 now **17 of 26 PRs done**.
- Deploy note for R2: `May/May22Updates/deploy-2026-05-22-r3-r2.md` (csjones holds R3 head, R2 not yet deployed).
- vault-sync ran (overdue from session 1). Metrics drift surfaced (Vue 664→667, Services 323→330, Controllers 115→118, Models 113→114, Stores 33→36) — CLAUDE.md not yet updated.
- Tech-debt-session skipped to preserve context budget; pattern was a tight mirror of yesterday's R4 work, debt risk low.
- Next: csjones deploy of R2 → R1 track (R1.0 browser-interactive, blocked) → final review + finishing-a-development-branch.

## 2026-05-22 — session 3 (end-of-day, SP1 Pass 2 R1 + csjones deploy)

- Handover: [[handover-2026-05-23-session-1]] (written to next day's folder per EOD convention)
- Branch: `dev` (HEAD `d3e1cf6`)
- Commits this session: 6 PRs + 1 direct-push hotfix (#364 → #368 plus `3506d70`)
- Status: clean (0 uncommitted; pre-existing untracked files unchanged)
- Shipped: hotfix for `ComprehensiveEstatePlanService` missing import → SP1 Pass 2 R1 × 5 (#364–#368) → csjones deploy of R1 + R2 + hotfix combined (HEAD `d3e1cf6`). SP1 Pass 2 now **22 of 26 PRs done**.
- csjones deploy verified: 4 admin endpoints (R1/R2/R3/R4) 401, root + mobile 200, no errors. DB: 4 currency rates / 6 tax configs (2026/27 active) / 44 life tables / 10 market rates.
- Tech-debt-session run on 11 R1-touched files: 0 critical, 1 warning (`TaxSettingsController::getCalculations()` hardcoded values — preserved through R1.2 rewrite per scope discipline), 4 pre-existing suggestions. Full report at `tech-debt-report.md`.
- Next: R1.0 audit (browser-blocked, needs CSJ) → R1.5 fix + roll W1 into it → final pass-wide review → finishing-a-development-branch release PR.

## 2026-05-23 — session 2 (context-handover tripwire)
- Handover: [[handover-2026-05-23-session-2-clear]]
- Branch: reviewFix
- WIP commit: none (tree was clean — last commit 77470bf)
- Pick up at: investigate 7 Pest failures (`/tmp/.../bh1bt45kt.output`), triage pre-existing vs new, then open `reviewFix → dev` PR

## 2026-05-24 — session 1 (context-handover tripwire)
- Handover: [[handover-2026-05-24-session-1-clear]]
- Branch: feature/sp1-pass-2-r1-5-b2-fix
- WIP commit: none (tree clean — last commit `0511d27` R1.5 already pushed)
- Pick up at: check PR statuses (#369 reviewFix, #370 Pass-3 plan, #371 R1.0 audit, #372 R1.5 B2 fix). After #372 merges, final pass-wide review + finishing-a-development-branch. Do NOT start Pass 3 until Pass 2 reaches main.

## 2026-05-25 — session 1 (end-of-day wrap)
- Handover: [[handover-2026-05-26-session-1]]
- Branch: dev (HEAD `72e6e5e`)
- Commits this session: 8 across 6 PRs (#373/#374/#369/#371/#372/#370 merged; #375/#376 opened)
- Status: clean
- Shipped: SP1 Pass 2 fully landed (26/26 PRs). Mobile scaffold rebuilt — JSON dump replaced with welcome + net-worth + 6 module cards + drill-downs (PR #375 open). SP1 Pass 3 PR 0 audit complete (PR #376 open) — found 20 mutation sites (vs plan's 17), `StaticTierGate::LIMITS` dead, plan §117 PR-7 needs rewrite to seed `pension_account` into `tier_configurations`.
- Next: merge #375 + #376, then start Pass 3 PR 1 (PensionStore facade + normaliser + 4 events + arch test). Csjones deploy is 12 commits behind — gates the tax-settings round-trip smoke from yesterday's handover.

## 2026-05-26 — session 2 (context-handover tripwire)
- Handover: [[handover-2026-05-26-session-2-clear]]
- Branch: feat/pension-store-pr3
- WIP commit: none (tree clean at invocation; last commit 151817e — Pass 3 PR 3 on PR #380)
- Pick up at: merge #380, branch feat/pension-store-pr4 off dev, start Pass 3 PR 4 (upload + seeders → PensionStore) per plan lines 2518+

## 2026-05-26 — session 3 (context-handover tripwire)
- Handover: [[handover-2026-05-26-session-3-clear]]
- Branch: feat/pension-store-pr6
- WIP commit: f7ba90b (amend before opening PR #383)
- Pick up at: tail /tmp/pest-pr6.log → if green, amend WIP + open PR #383

## 2026-05-26 — session 4 (end-of-day wrap, docs-only)
- Handover: [[handover-2026-05-27-session-1]]
- Branch: feat/property-store-pr3 (carrying SP1 Pass 3 + Pass 4 + today's CoALA docs)
- Commits this session: 2 (b613426 CoALA plan + brief; f5a2412 six implementation PRDs)
- Status: clean, pushed
- Shipped: CoALA implementation plan v0.4 (734 lines + 6 mermaid diagrams), non-technical stakeholder brief, two self-contained HTML render artifacts, six engineering-ready implementation PRDs (one per phase, 1,525 lines total). Docs-only — no code changes, no deploys.
- Next: embeddings-provider decision for Phase 1, Option A vs B confirm for class structure, Phase 5 cache-telemetry sub-plan as standalone PR, stakeholder review scheduled, resume SP1 Pass 4 if queue has bandwidth.

## 2026-05-27 — session 3 (context-handover tripwire)
- Handover: [[handover-2026-05-27-session-3-clear]]
- Branch: dev (tip c972fff → handover commit d2b4b1c)
- WIP commit: none (tree clean after PR 8 merge)
- Pick up at: update spec doc for Pass 4 close-out, then start Pass 5 (Liabilities) — brainstorm vs plan-write CSJ decision

## 2026-05-29 — session 1 (context-clear)
- Handover: [[handover-2026-05-29-session-1-clear]]
- Branch: pureFreemium (off dev)
- Parallel to SP1 Pass 6. Two tracks this session:
  1. Revolut checkout spinner — FIXED + DEPLOYED to prod+dev (root cause: prod config uncached → forge/no-password DB fallback). PR #421 (dev), #422 (main).
  2. Pure freemium signup — spec + plan written (`docs/superpowers/{specs,plans}/2026-05-29-pure-freemium-signup*.md`), NO code yet. Awaiting execution-approach choice.
- Commits this session: 4 (2 Revolut fix, 2 freemium docs).
- Next: CSJ picks execution approach for the freemium plan → start PR1 (registration → tier='free', no trial).

## 2026-06-03 — end-of-day (mobile funnel + Fyn onboarding)
- Handover: [[handover-2026-06-04-session-1]]
- Branch: dev (csjones @ 6d30719)
- Shipped: PRs #452–#460 — /m responsive funnel + authed handoff (token bridge) + homepage CTA → /savetax + funnel-answer persistence + real account creation from the funnel + Fyn pre-fill/greet/recap/skip-known + mobile Fyn dock onboarding (bubbles + resume) + nudge + CLAUDE.md Rules #17–19.
- Verified live on csjones end-to-end; nothing to production.
- Next: walk mobile onboarding to /tax-strategy terminal in the Fyn dock + confirm tax advice renders on mobile; browser-verify desktop recap; refresh Current State/Auth.md.

## 2026-06-04 — session 2 (context-clear)
- Handover: [[handover-2026-06-04-session-2-clear]]
- Branch: dev
- Commits this session: 1 (`05b1e8e` docs(claude): lean CLAUDE.md + Fyn two→one transition)
- Status: clean (only untracked docs/mobile/designer-brief.pdf, not ours)
- Next: resume mobile /m work — build against the single shared /api/ai-chat dispatch (Fyn converging to one); reconcile 00-canonical.md when coala merges to dev.

## 2026-06-04 — session (end-of-day)
- Handover: [[handover-2026-06-05-session-1]]
- Branch: dev
- Commits this session: 2 code (`06937fc` fix(fyn) streaming+soft-degrade, `7dce0e2` feat(mobile) tax-strategy + module drill-downs) + session-end docs
- Built: real mobile `/m/app` Tax Strategy view (incl. married/joint household) + Net Worth/Protection/Savings/Retirement/Investment overview + drill-down views; dock streaming fixes; provider-aware Fyn soft-degrade. All browser-verified.
- Status: working tree clean
- Next: deploy to csjones per June5Updates/deploy-2026-06-05.md (mobile bundle rebuild for /fynla/ base); optionally click a tax-strategy next-step CTA with a qualifying user.

## 2026-06-07 — session (end-of-day)
- Handover: [[handover-2026-06-08-session-1]] (target 2026-06-08)
- Branch: dev
- Commits this session: 4 (PRs #487 test, #488 fix)
- Status: 0 uncommitted (working tree clean)
- Shipped: #487 inline-capture onboarding-award unit test; #488 Save tax CTA on server-rendered public/pages/index.php (deployed+verified csjones)
- Next: production release decision (dev +120/-7 vs main); chris existing-user pass (blocked on password reset)

## 2026-06-09 — session 1 (end-of-day)
- Handover: [[handover-2026-06-09-session-1]]
- Branch: dev
- Commits this session: 3 (b4a8029 mojibake fix + 2 merges aa4569d/87f2e4d)
- Status: 0 uncommitted (working tree clean)
- Shipped: freemium tier-pricing modal #501 + router cold-boot #500 merged to dev, deployed + live-verified on csjones. Public /pricing rewritten to Free+Tier1/2/3, trial copy stripped, <title> mojibake fixed.
- Next: prod release (dev→main, +158/-7) is CSJ's call — #489 auth throttle is the priority reason (prod MFA reset broken). Set real tier prices in admin Tier Configuration.

## 2026-06-09 — session 2 (context-clear)
- Handover: [[handover-2026-06-09-session-2-clear]]
- Branch: dev @ faa86d6 (+165/-7 vs main)
- Commits this session: 6 (PRs #502, #503, #504 merged to dev)
- Status: clean (one pre-existing untracked PDF)
- Shipped: #502 /m Save tax CTA base-path; #503 withBase() helper + 6 base-path sites; #504 onboarding advice self-loop fix + MAX_ADVICE_CHAIN guard + conv 66 cleanup (17,488 rows deleted)
- Next: prod release (dev→main) is CSJ's call; #489 auth-throttle is the priority reason

## 2026-06-10 — session 3 (context-clear)
- Handover: [[handover-2026-06-10-session-3-clear]]
- Branch: dev @ a75af48 (PRs #525 + #526 merged via pushed merge commits; gh CLI merge endpoint 401s — needs `gh auth refresh`)
- Commits this session: 7 (5 finding-fixes + 2 PR merges); 345-test sweep green pre-push
- Status: working tree clean (two pre-existing untracked docs)
- Shipped: branch verification (9/9 acceptance criteria), six finding-fixes (stable rec IDs, retirement earnings cap + age gates, goals copy, assets caption, bubble dismissal persistence, persona onboarding seed), csjones back on dev + live-verified, 94 remote + 4 local merged branches deleted
- Next: estate will/LPA recs decision (CSJ); dev → main release when CSJ decides

## 2026-06-11 — session 4 (clear)
- Handover: [[handover-2026-06-11-session-4-clear]]
- Branch: dev @ d0f7cf6 (clean, pushed)
- Commits this session: 7 (PRs #529, #530, #531 merged)
- Status: 0 uncommitted (2 long-standing untracked docs only)
- Next: Track 2 (coala) — review spec v4 with CSJ (docs/superpowers/specs/2026-06-11-track2-coala-integration-design.md), then superpowers:writing-plans

## 2026-06-12 — session 1 (eod)
- Handover: [[handover-2026-06-13-session-1]]
- Branch: dev @ 7b9152b; coala @ 10193e2 — both pushed, trees clean
- Commits today (dev): 12; PRs merged: #532–#545 (Track 2 + follow-ups + cleanup), suite 4,963/0 on coala
- Status: 0 uncommitted (two long-standing untracked docs only)
- Next: CSJ — deploy dev→csjones (June13Updates/deploy-2026-06-13.md), Azlan re-test, gamified dashboard eyeball; then the coala→dev landing programme

## 2026-06-13 — session 2 (context-clear)
- Handover: [[handover-2026-06-13-session-2-clear]]
- Branch: dev (main @ 2905c62, dev-ahead 0); prod fynla.org current
- Shipped: #546 web dashboard parity + sidebar Tax Strategy; #547 ISA calc counts S&S ISA subs; #548 ISA tool capture + Fyn deflection carve-out (PARTIAL); #549 dev→main release deployed to prod (5 migrations, catalogue seeders), verified end-to-end web + /m. Azlan re-test GREEN on csjones.
- Status: 0 uncommitted (working tree clean except 2 long-standing untracked docs). All merged + deployed.
- Next: CSJ to pick — eval-driven #2 deflection fix / non-tax catalogue metadata / coala→dev landing.

## 2026-06-14 — session 1 (eod)
- Handover: [[handover-2026-06-15-session-1]]
- Branch: dev @ b78409a5 (pushed); local sitting on merged fix/write-intent-goal-precedence
- Commits this session: 1 (PR #552); admin-merged to dev, deployed csjones
- Status: clean except two pre-existing untracked docs + CLAUDE.md metrics refresh (committed in wrap)
- Shipped: WriteIntentClassifier goal/property keyword-precedence fix (explicit goal noun wins over incidental asset keyword; "add a goal for a house deposit" → goal, not property). TDD'd; 407 AI unit tests green. Resolves the precedence bug flagged in feedback_advice_fyn_capture_deflection_partial.
- Next: CSJ — optional live /m+web confirm of goal-capture; dev → main release when CSJ decides (this fix + #546–#551 ride the next prod release); #2 advice-Fyn deflection eval-driven fix still open

## 2026-06-15 — session 2 (context-clear)
- Handover: [[handover-2026-06-15-session-2-clear]]
- Branch: docs/session-2026-06-15-clear (Phase 6 built on coala-phase-6-learning → dev via #554)
- Shipped: CoALA Phase 6 (gated learning: promotion + procedure-amendments + sparse recall), 13 tasks subagent-driven, merged to dev (fae710c), suite 5030/0; deployed + live-E2E-verified on csjones. Cross-module composer spec written + approved (parked).
- Next: write the cross-module plan composer implementation plan (superpowers:writing-plans) from docs/superpowers/specs/2026-06-15-cross-module-plan-composer-design.md, then build.
