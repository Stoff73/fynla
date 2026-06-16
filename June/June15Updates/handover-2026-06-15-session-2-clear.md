---
type: handover
mode: context-clear
date: 2026-06-15
session: 2
branch: dev (Phase 6 built on coala-phase-6-learning, merged via #554; this handover on docs/session-2026-06-15-clear → dev)
previous_session: 2026-06-15 session 1 (end-of-day, #552 classifier fix)
---

# Context Clear Handover — 2026-06-15, Session 2

## Immediate state
**CoALA Phase 6 (gated learning actions) is fully built, reviewed, merged to dev (#554, merge `fae710c`), deployed to csjones, and live-E2E-verified with real xAI.** The **cross-module plan composer** spec is parked + approved, and Phase 6 (its substrate dependency) is now in place. **Next session: start the cross-module plan composer — write its implementation plan, then build it.**

## The thread
- Session-start auto-verified the #552 `WriteIntentClassifier` goal-precedence fix LIVE in the browser (GREEN — "add a goal to save £25,000 for a house deposit" → goal capture, Goal row written, no property deflection).
- Doc-hygiene: marked the advice-Fyn deflection item RESOLVED in CSJTODO (it was stale — #551 fixed the root cause, #552 the precedence bug).
- Brainstormed + wrote the **cross-module plan composer spec**. CSJ flagged the first draft siloed it from CoALA → rewrote it CoALA-native (live sources → pointers/handlers → semantic+procedural+DB catalogue triple → cross-module composite + episodic loop). Approved.
- CSJ: "complete phase 6 first." Brainstormed Phase 6 forks (sparse-recall-now / per-user promotion / all-three-in-one-spec) → spec → 13-task plan → **built it subagent-driven**, each task spec+code-quality reviewed, plus a final holistic review.

## What shipped (Phase 6 — on dev via #554)
- **Component A (promotion):** `ProposedFactSynthesiser` → `proposed_semantic_facts` staging (flag-gated) → admin review (`/admin/proposed-facts`) → `SemanticFactPromoter::approve` → runtime `UserSemanticStore` → `SemanticRetriever::retrieveForUser` (wired into `FynContextAssembler:114`). GDPR erase extended.
- **Component B (procedure amendments):** planner `learn store=procedural` + cap-exhaustion consult → `proposed_procedure_amendments` → review (`/admin/proposed-amendments`), approval marks accepted only (NEVER writes the corpus).
- **Component C (recall):** `SparseRecallScorer` behind a `RecallScorer` interface → relevance-ranked episodic recall in the planner.
- `FYN_LEARNING_ENABLED` flag (default OFF); no-auto-apply invariant (`NoAutonomousEditInvariantTest`); `FynSystemPrompt::text()` byte-invariant untouched. Full suite **5030 passed / 0 failed**.

## Files touched (committed)
- 15 commits on `coala-phase-6-learning` → dev via PR #554 (`app/Services/AI/{Memory,Learning,Loop}`, `Http/Controllers/Api/Admin`, `Models`, 2 migrations, `config/fyn.php`, `routes/api.php`, 2 `resources/js/views/Admin/*.vue` + 2 services + router, tests).
- This handover + CSJTODO on `docs/session-2026-06-15-clear` → dev.

## What the next Claude needs to know
- **START HERE: the cross-module plan composer.** Spec (approved): `docs/superpowers/specs/2026-06-15-cross-module-plan-composer-design.md`. CoALA-native. Next step = `superpowers:writing-plans` → build (subagent-driven, like Phase 6). It generalizes `ComposedTaxPlanService` → per-module + cross-module (affordability rank+annotate, goals/life-events), reached via CoALA pointers/handlers, with catalogue knowledge as a semantic+procedural+DB triple. Phase 6's per-user learning substrate is now available for its episodic-learning piece.
- Phase 6 state: memory `project_coala_phase6_landed_on_dev.md` (+ MEMORY.md index). Flag-gated OFF.
- **csjones has `FYN_LEARNING_ENABLED=true`** (left on as the test venue → ongoing 30-min stale-scan synthesis cost). Revert if unwanted: `ssh csjones`, `sed -i 's/^FYN_LEARNING_ENABLED=.*/FYN_LEARNING_ENABLED=false/' .env && php artisan config:cache`.
- **Local `public/build/` is csjones-configured** (rebuilt for the deploy). Any PROD deploy needs `./deploy/fynla-org/build.sh` FIRST.
- **Prod (`main`/fynla.org) is pre-CoALA AND pre-Phase-6** — last prod release was #549 (2026-06-13). dev is ahead by #550 (CoALA Phases 1–5+Track2) + #551/#552/#553 + #554 (Phase 6). A `dev → main` release is CSJ's call; Phase 6's 2 migrations + the default-off flag ride it; no corpus reindex needed.
- 3 untracked files of UNKNOWN provenance appeared this session (not from Phase 6 work, left untracked): `docs/diagrams/fyn-turn-lifecycle.excalidraw`, `June/June15Updates/2026-06-15-fyn-system-prompt-build-walkthrough.md`, `.claude/skills/excalidraw/scripts/__pycache__/`. Plus 2 long-standing pre-existing untracked: `docs/mobile/designer-brief.pdf`, `docs/security/security-review-2026-06-09.md`.
- Local dev DB has the 2 Phase 6 tables (migrated this session).
- A1 (session-end episode consolidation) deferred per CSJ — synthesiser reads the transcript directly; canonical "session is the episode" remains a future item.

## Pick up from here
1. `git checkout dev && git pull` (gets this handover + Phase 6).
2. Read `docs/superpowers/specs/2026-06-15-cross-module-plan-composer-design.md` (the approved cross-module composer spec) + skim `project_coala_phase6_landed_on_dev.md` + `feedback_coala_agent_flow_canonical.md`.
3. Invoke `superpowers:writing-plans` to turn the composer spec into an implementation plan, then build it (subagent-driven). Branch off dev (`cross-module-plan-composer` or similar).
