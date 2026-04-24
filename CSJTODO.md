# CSJTODO — Fynla

*Last updated: 24 April 2026 — session 71 (context-clear: Sprint 0.1 rebase + main-test-fixes PR #235)*
*Previous session: 24 April 2026 — session 70 (Fyn v2 spec directory)*

---

## Session 71 (24 April evening) — Sprint 0 kickoff: plan directory + rebase + main-test-fixes

**Context-clear wrap (not end-of-day)** — next session same day.

### Completed

#### Plan directory `April/April24Updates/plan/` (`.gitignored`, mirrored to vault)
- [x] **10 plan files + 3 working files (task_plan.md, findings.md, progress.md)** built via `/planning-with-files`. Each plan applies the user-specified plan-slice template (Objective / Spec reference / Files affected with code citations / Acceptance test / Out of scope).
- [x] `00-canonical-plan.md` (7 slices), `01-invariants-plan.md` (35 invariants), `02-current-system-plan.md` (13 slices), `03-test-strategy-plan.md` (30 slices), `10-sprint-0-plan.md` (17 task slices), `11-sprint-1-plan.md` (10 slices), `12-sprint-2-plan.md` (20 slices), `13-sprint-3-plan.md` (6 slices), `14-sprint-4-plan.md` (14 slices), `README-plan.md` (7 slices).
- [x] Mirrored to `/Users/CSJ/Desktop/fynlaBrain/April/April24Updates/plan/` (13 files).

#### Sprint 0 Task 0.1 — rebase `feature/fyn-persona-split` onto `origin/main` (180 commits)
- [x] Dedicated branch `feature/csj/sprint0-rebase` created from `f4b0b89`.
- [x] Pre-rebase Pest baseline: **2,536 pass / 2 fail** (`SavingsAgentGoalsTest`, `AutoRiskCalculatorTest` both known flakes).
- [x] Rebase completed clean via helper script — only 4 doc-file conflicts (`LandingPage.vue` comment, `CLAUDE.md` metrics table, `CSJTODO.md` wholesale, `prd-writer/SKILL.md` wholesale). **Zero code conflicts**. 11 of 68 branch commits dropped as no-ops.
- [x] Post-rebase Pest: 2,637 pass / 22 fail (discovered main had pre-existing failures — not rebase regressions).
- [x] PR #234 opened then superseded.

#### PR #235 — main-test-fixes (22 failures → 0)
- [x] Root-caused and fixed 22 main-inherited Pest failures across 7 test classes. Branch `feature/csj/main-test-fixes` off `origin/main`, 5 atomic commits:
  1. `ac38309` — `LifecycleCampaign` added to `services-organized-by-module` arch ignore list (1 line)
  2. `05fa07b` — `RiskProfileFactory` enum values `medium_low/medium_high` → `lower_medium/upper_medium` to match DB schema (1 line)
  3. `06ae203` — `decimal:2` → `float` on Estate Asset/Liability/Gift/IHTProfile + Investment Holding per main commit `421c380` precedent (9 casts, 5 models)
  4. `b5945e6` — `InsightControllerTest` fallback test updated to match intentional no-auto-promote controller behaviour
  5. `5c40273` — 16 affected columns registered in `MonetaryCastsArchitectureTest::ALLOWED_FLOAT_COLUMNS` with detailed header comment + long-term-fix-path note (API Resource layer)
- [x] **MoneyCast class was tried and rejected** (user prefer simpler arch-exception approach) — no new classes shipped.
- [x] Full Pest on main-test-fixes: **2,370 pass / 0 fail / 10,314 assertions** / 240s.
- [x] PR #235 → `dev` admin-merged as merge `d3044e1`.

#### Deploy guide `April/April25Updates/deployInherit.md` (repo + vault)
- [x] Deploy guide for PR #235 to both dev (`csjones.co/fynla`) and production (`fynla.org`). Generated from `git diff` not memory. No migrations, no build, 9 files to upload. Rollback plan + smoke-test checklist.
- [x] Mirrored to `fynlaBrain/April/April25Updates/deployInherit.md`.

#### Sprint 0 Task 0.1 landed on `feature/fyn-persona-split`
- [x] `feature/csj/sprint0-rebase` re-rebased onto `origin/dev` (picking up PR #235's 5 fixes in one go, zero new conflicts).
- [x] Force-pushed `sprint0-rebase` tip (`c3bffdb`) onto `feature/fyn-persona-split`. PR #234 closed as superseded.
- [x] fyn-persona-split now: 0 behind dev / 57 ahead, 0 behind main / 65 ahead, all 5 test fixes present, working tree clean.

#### Scheduled routine
- [x] One-time remote agent `trig_015ggy6qz1M3axH6Shvv5Wfw` firing Sun 2026-04-26 09:00 BST — verifies the 22 main-inherited failures are resolved on main (once dev → main lands). Manage at https://claude.ai/code/routines/trig_015ggy6qz1M3axH6Shvv5Wfw.

### NOT Done — Outstanding for next session

#### Deploy PR #235 main-test-fixes to dev (pending user action)
- [ ] Upload 9 files per `April/April25Updates/deployInherit.md` to `~/www/csjones.co/public_html/fynla/`.
- [ ] SSH + cache clears on dev (no migrations needed).
- [ ] Browser smoke Estate + Holdings on `csjones.co/fynla`.
- [ ] After dev soak: CSJ opens `dev → main` PR, merges, uploads to `fynla.org`, runs SSH cache clears.
- [ ] Monitor `storage/logs/laravel.log` 10-15 min post-deploy on each environment.

#### Sprint 0 continuation — start with Task 0.2
- [ ] **Sprint 0 Task 0.2** — delete stale OpenAI config + Python sidecar (cleanup only, ~30 min). See `April/April24Updates/plan/10-sprint-0-plan.md` slice S0.2.
- [ ] **Sprint 0 Task 0.3** — two-Fyn collapse: create `AdviceFyn` + `HandoffPayloadValidator`, extend `OnboardingChatDirector::handleInlineCapture`, DELETE `FynPersonaOrchestrator`/`Invoker`/`Registry`/`DataCapturePromptBuilder`. Biggest code task in Sprint 0.
- [ ] Sprint 0 Tasks 0.4–0.17 per plan.

### Context for next session

1. **Branch**: already on `feature/fyn-persona-split` at `c3bffdb`. Working tree clean. Sprint 0.1 done.
2. **Start Sprint 0.2**: trivial 30-min cleanup — read `April/April24Updates/plan/10-sprint-0-plan.md` slice **S0.2** — delete `config/services.php:34-38` OpenAI block, `scripts/fynla_agent/`, `scripts/run_agent.py`, `app/Http/Controllers/Api/AgentInternalController.php`, `app/Http/Middleware/AgentTokenAuth.php`, `routes/api.php:1193-1199`, `app/Http/Kernel.php:81`, `.env.example` OpenAI entries. Add arch test `tests/Architecture/NoStaleReferencesTest.php`. Commit: `chore: remove stale OpenAI config + dead Python sidecar`.
3. **Sprint 0 execution mode**: subagent-driven-development (recommended) or executing-plans — your call.
4. **PR #234 closed**, PR #235 merged to dev. Routine `trig_015ggy6qz1M3axH6Shvv5Wfw` fires Sunday 9am BST.

### Deploy Status

- **Main-test-fixes (PR #235)**: on `dev` branch, not yet on `dev` deployed (awaits CSJ upload). Not on main.
- **Fyn v2 Sprint 0 work**: on `feature/fyn-persona-split` tip `c3bffdb`. No user-visible behaviour yet — just rebased foundation.
- **Scheduled agent**: runs Sun 26 Apr 09:00 BST — read-only report.

### Pest baseline (last measured)

On `feature/csj/main-test-fixes` at `5c40273`: **2,370 pass / 0 fail / 10,314 assertions** / 240s at 4GB memory_limit.
On `feature/fyn-persona-split` at `c3bffdb`: not re-run post-rebase; expect similar green (all fixes present).

### Memory-rule adherence this session

- ✅ `feedback_main_via_dev_only.md` — nothing merged to main; PR #235 went to dev.
- ✅ `feedback_never_raw_vite_build.md` — no vite builds attempted.
- ✅ `feedback_deploy_guide_completeness.md` — `deployInherit.md` generated from `git diff` not memory.
- ✅ `feedback_deploy_guides_both_locations.md` — mirrored repo + vault.
- ✅ `feedback_never_hardcode_tax_values.md` — no tax values touched.
- ✅ `feedback_never_touch_env_or_db.md` — no `.env` changes, no DB hand-inserts.
- ✅ `critical_browser_testing_law.md` — no "verified" claims for untested items; all Pest results backed by runs.
- ⚠️ `feature/fyn-persona-split` was force-pushed to land the rebased tip. User explicitly authorised in-session.

---

## Decision register snapshot (unchanged from session 70, still locked)

1. Two Fyns, no Orchestrator class. Delete orchestrator/invoker/registry/data_capture prompt builder.
2. All 17 fill_form handlers → direct-write (Q1=a).
3. Provider parity. 40 tools post-Sprint-0 (+14 batch = 54 post-Sprint-2).
4. FCA: guidance-only. Signposting: *"For regulated advice personal to your circumstances, speak to a qualified financial adviser."*
5. Out-of-remit: *"I'm able to help you with your finances. {context} is out of scope."*
6. Memory model: 3 stores + 1 index. `MemoryRetrieverService` retrieval order DB → parked → current → index.
7. SSE abort: keep partial writes, instrument.
8. Python sidecar: delete.
9. Deploy gate: local-first unambiguous.
10. Rubric-A + Rubric-B: build both.
11. Multi-entity thresholds: 95% baseline recall/precision; 100% hard-fail floors.
12. Advice response shape: new `advice_response` SSE event + `AdviceResponsePanel.vue`.
13. Recommendation engine: existing `orchestrateAnalysis` pipeline — reused, not replaced.
14. Entry-source mapping: config-driven + extensible; 4 initial mappings.
15. Document extraction: UI-only CTA flow; not an Advice Fyn tool.
16. Estate/Holding `decimal:2` casts: deferred per arch exception; API Resource layer when it lands.

---

## Outstanding — Tech Debt Deferred

Tracked in `MonetaryCastsArchitectureTest::ALLOWED_FLOAT_COLUMNS` header comment: when API Resource layer lands, remove each of the 16 exempt column entries and reinstate `decimal:2` on Estate Asset/Liability/Gift/IHTProfile + Investment Holding.
