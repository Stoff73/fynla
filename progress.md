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
