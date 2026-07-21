---
type: handover
mode: context-clear
date: 2026-06-01
session: 1
branch: feat/coala-phase1-semantic-memory
---

# Context Clear Handover — 2026-06-01, Session 1

## Immediate state

CoALA **Phase 1 semantic substrate is built, reviewed, and pushed** (55 tests green) and the **pointer registry (Phase 4) spec + 12-task implementation plan are written, committed, and pushed.** The next session's job is to **execute the pointer registry plan with subagent-driven development, starting at Task 1 — CSJ's explicit instruction at session-end.** Branch `feat/coala-phase1-semantic-memory` is up to date with origin (`0 0`).

## Pick up from here (DO THIS — CSJ's explicit instruction)

1. **Auto-continue: execute the pointer registry plan via subagent-driven development.** Plan: `docs/superpowers/plans/2026-06-01-coala-pointer-registry-plan.md` (12 TDD tasks). Spec: `docs/superpowers/specs/2026-06-01-coala-pointer-registry-design.md`.
2. Invoke `superpowers:subagent-driven-development`, read the plan, create a TodoWrite/TaskCreate list from its 12 tasks, then **dispatch a fresh implementer subagent per task with the two-stage review (spec compliance → code quality) between each** — exactly the flow that built Phase 1 this session (it caught 5 real bugs). Do NOT ask "shall I continue?" — CSJ pre-authorised subagent-driven execution for this session.
3. Stay on `feat/coala-phase1-semantic-memory` (the registry plan bases off it — it reuses the Phase-1 loader/retriever patterns). Work directly on it (or per the plan's "confirm base at execution time" note). **CoALA PRs target `coala`, not `dev`.**
4. Per-task: implementer commits its own files; verify green + pint before marking complete; the implementer subagents have a habit of stopping just before the commit — if so, finalise (pint + commit) yourself, it reconciles cleanly.

## The thread (what this very long session covered)

- **BS browser-verification (FR-M7/M8/M9):** verified the CoALA chat queue + resumption surfaces live in Playwright as user john. Corrected the prior handover's wrong "isTrusted guard" blocker — real gate is the `fyn:inflight:{convId}` cache lock (held from tinker to trigger 202-queued). Saved `reference_coala_chat_queue_is_browser_testable.md`.
- **Prod-critical fixes → dev:** cherry-picked `2af79fe` (CheckSubscription trial app-access) + `3643cc5` (PensionStore import) onto a fix branch, **PR #438 merged to `dev`** (`origin/dev` at `7a9de6a`). **csjones deploy is still CSJ's manual step — NOT done.**
- **Mapped Phase 6 + Phase 1:** explained Phase 6 isn't started (blocked by Phase 1 semantic store); Phases 1–4 were partial/scaffold; Phase 5 done.
- **Built Phase 1 semantic substrate** (subagent-driven, two-stage review): SemanticFact + fail-closed SemanticCorpusLoader + sparse effective-date SemanticRetriever + `fyn:semantic:reindex` + additive `<knowledge>` block (with graceful corpus-error degradation + no empty-source noise) + hygiene guards. 55 tests/123 assertions green; static prompt untouched. Task 9 skipped (existing `FynSystemPromptTest` already pins byte-invariance via snapshot). **Task 10 (fca/house_view corpus CONTENT) is PAUSED for CSJ compliance review.**
- **v0.5 CANONICAL re-architecture (CSJ-directed):** "memory holds pointers, not copies." The **pointer registry is the heart of procedural memory**; semantic narrows to source-less (fca/house_view); tax/product/user figures + recommendations are reached via procedural **pointers** to live sources, never frozen. Amended master plan (`fynla-coala-implementation-plan.md` v0.5), store READMEs, phase-1/phase-4 PRDs. Reverted the frozen product corpus (`4b4c584`). Saved `project_coala_phase1_scope_decisions.md`.
- **Pointer registry (Phase 4) — brainstormed → spec → plan.** 4 decisions: formalise existing + add reach (no big-bang refactor) · both trigger modes (pre-fetch + LLM-tool, per-pointer) · named code handlers (closed whitelist; markdown routes, code executes) · lightweight provenance on `ai_messages.metadata`. v1 = full mechanism + 3 proof handlers (`tax_allowance` config / `user_financial` model / `recommendations` engine).

## What the next Claude needs to know

- **Subagent-driven is pre-authorised — just execute the registry plan.** Don't re-ask.
- **The plan has one documented gap, not a defect:** pre-fetch provenance can't record at assembler-build time (no assistant `AiMessage` row yet) — tool-mode fetches DO record. Flagged in plan Task 10 note; wire it when the loop passes the assistant message in (follow-up).
- **Tasks 6/8/11 of the registry plan carry bounded discovery steps** (confirm exact `TaxConfigService` allowance keys / `CoordinatingAgent::handleRecommendations` return shape / `AiToolDefinitions` tool-array shape) — these are specified investigations with file:line + fallback, not placeholders. The implementer must confirm the real shapes before writing the handler bodies.
- **Two deferred Phase-1 Minors (final review, non-blocking):** register `SemanticCorpusLoader` as a singleton WITH the Phase-4 mtime hot-reload (not before — staleness risk); `SemanticRetriever::snapshotId()` is the Phase-2 provenance hook (built ahead of consumer).
- **Don't touch `FynSystemPrompt::text()`** — prefix-cache byte-invariant, pinned by `FynSystemPromptTest` snapshot. The `<live_data>` block is additive per-turn only.
- **No £ figures in any pointer/semantic `.md`** — figures are fetched live via handlers (Rule #3). Guards enforce this.
- The 3 working-tree artefacts (deleted `logoMain.png`/`logoTransparent.png`, untracked `docs/mobile/designer-brief.pdf`) are **pre-existing and NOT mine** — leave them, do not commit or restore.

## Files touched (all committed + pushed on feat/coala-phase1-semantic-memory)

19 commits `a17fafc..f31d715`. New code: `app/Services/AI/Memory/{SemanticFact,SemanticCorpusLoader,SemanticRetriever}.php`, `app/Console/Commands/FynSemanticReindex.php`, `app/Services/AI/Fyn/FynContextAssembler.php` (+`<knowledge>`), `config/fyn.php`, `fyn-memory/semantic/*`, tests under `tests/Unit/Services/AI/{Memory,Fyn}`, `tests/Unit/Services/AI/SemanticCorpusContentTest.php`, `tests/Feature/Console/FynSemanticReindexTest.php`. Docs: master plan v0.5, READMEs, PRDs, the registry spec + plan.

## Open / owed (need CSJ)

- **Task 10 of Phase 1** — author `fca`/`house_view` corpus content (compliance review — CSJ's loop, not the agent's).
- **csjones deploy** of PR #438's prod fixes (manual step).
- **Two-Fyn A/B collapse** — still deferred (post-Phase-6 per the plan's own sequencing).
