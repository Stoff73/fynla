# Fyn v2 — Canonical Two-Fyn Contract

> **BRANCH: `feature/fyn-persona-split`.** All implementation builds on this branch.

This statement is the source of truth for every doc, spec, plan, PRD, and task list in this workstream. It appears verbatim at the top of every artefact.

---

> **FYN HAS ONE PROMPT, TWO WRITE STATES.**
>
> Fyn presents as one chat surface with one static system prompt. It has two *write states*, selected by the central `AiChatController::routesToOnboardingDirector()` predicate: the onboarding write state requires (`users.onboarding_completed === false` **or** `users.active_campaign !== null`) **and** `users.onboarding_fyn_step !== null` **and** `config('onboarding.fyn_flow_enabled', true)`; every other case routes to the read-only advice state. This means a paused user whose `onboarding_fyn_step` was nulled (e.g. via a "Something else" handoff) routes to read-only advice even when onboarding is incomplete or a campaign marker remains — this is deliberate and fail-safe: a user with no step context can never land in a write state. The boundary is enforced purely by which tools are in the turn's tool list — **not** by prompt content.
>
> **Campaign re-entry amendment (2026-07-03):** a user with `onboarding_completed = true`, a non-null `users.active_campaign`, and a non-null `onboarding_fyn_step` routes to the onboarding write state for the duration of a campaign walk. The predicate is centralised in `AiChatController::routesToOnboardingDirector()` and shared by all three dispatch seams (sendMessage, streamQueuedMessage, action) — no seam may inline its own copy. `active_campaign` is stamped only by `POST /api/ai-chat/onboarding/start` when `from=` resolves to a `campaign_map` entry with `reentry => true` (the flow-flag 503 and preview 403 gates still apply); it is cleared unconditionally at the campaign terminal and on the 'Something else' pause. Re-entry never modifies `onboarding_completed`, and completion side effects are guarded by the pre-terminal completed value so they never double-fire. The lost-`from=` fallback resolves `funnel_answers['campaign']` (string-guarded), defaulting non-empty legacy funnel rows to `savetax`.
>
> **ONBOARDING STATE** (`OnboardingChatDirector`, `onboarding_completed = false`, plus the post-onboarding `handleInlineCapture` entry point) is the **only** state whose tool list contains `create_*` / `update_*` / `delete_*` / `set_expenditure` / `capture_*`. It runs the bubble-driven onboarding flow, accepts multi-line input, persists it, has memory (already-known facts are not re-asked but resurfaced), and resumes where the user left off. It also receives handovers from the advice state for outstanding information.
>
> **ADVICE STATE** (`AdviceFyn`, `onboarding_completed = true`) answers requests using the recommendation engine, risk module, and every other engine/module. Its tool list has **every** write tool stripped (`AdviceFyn::WRITE_TOOLS`) including `navigate_to_page`. It is read-only by construction. Write intents emit `delegate_to_capture` → `AdviceFyn::wrapStream` → `OnboardingChatDirector::handleInlineCapture` → the same `CoordinatingAgent` write handlers. The synthetic `handoff` SSE event is consumed internally and never reaches the frontend (INV-2.4.1).
>
> **THE USER NEVER SEES THE HANDOFF OR FEELS THE SWITCH.** No `persona_state_change` SSE event. No capturing pill. Input placeholder invariant. No frontend signal distinguishes the states.
>
> **STREAM EVENT CONTRACT:** `capture_write_result` is internal landed/failed telemetry. `OnboardingChatDirector::handleInlineCapture` consumes it, emits deterministic plain failure text when the turn ends with an unresolved direct-write failure, and never forwards the telemetry frame to either client; a corrected retry that lands clears the matching pending failure. Each inline-capture stream emits exactly one final `done` frame after its content and confirmation events. `capture_complete` remains a public confirmation event and is consumed on desktop and `/m`, as are `token_limit`, `consent_required`, `handoff_error`, typed `error.message`, `entity_created`, `skip_link`, and `level_up`. User-visible event text contains no icons.
>
> **The system prompt no longer encodes the split.** Both states send the identical `FynSystemPrompt::text()`. What differs per state is (a) the dynamic context block (`mode: advice` vs `mode: onboarding — focus: X`) and (b) the tool list. The read/write boundary is a tool-gating + dispatch guarantee, not a prompt instruction.
>
> No `FynPersonaOrchestrator`, no invoker, no registry, no `DataCapturePromptBuilder`. `HandoffContract` constants and `CaptureContext` VO are kept. **`AdvicePromptBuilder` and `OnboardingPromptBuilder` are retained permanently** (not deleted): they remain in-tree behind `FYN_PROMPT_ARCH=legacy` so the legacy and unified prompt architectures stay switchable indefinitely for real A/B comparison (CSJ directive, 2026-05-18). The single-prompt guarantee above describes the `unified` path; parity (`unified` ≡ `legacy`) is the contract — not deletion of the legacy builders.

A single `prompts/fyn-system-prompt.md` documents the `unified` `FynSystemPrompt::text()`. The two per-state artefacts under `prompts/` (`advice-system-prompt.md`, `onboarding-system-prompt.md`) are **retained as the legacy reference**, documenting the `FYN_PROMPT_ARCH=legacy` builders that remain in-tree.

## What this means for evaluation

- `01-invariants.md` breaks this contract into ~35 falsifiable invariants. Each invariant has a specific test.
- `fyn-rubrics.md §B` contains 75 golden conversations that exercise the contract end-to-end.
- Scenario category `09-canonical-behaviour` (10 scenarios) is the core canonical-contract test set. Any regression in that category blocks merge.

## Prompt architecture flag

The prompt architecture is gated behind `FYN_PROMPT_ARCH` (`config('fyn.prompt_architecture')`, values `legacy` | `unified`, **default `unified`** post-cutover 2026-05-17; `legacy` is the emergency rollback path). `legacy` keeps the two pre-2026-05-16 per-state prompt builders (`AdvicePromptBuilder`, `OnboardingPromptBuilder`) byte-for-byte; `unified` sends `FynSystemPrompt::text()` + `FynContextAssembler` for both states. **Both architectures are retained permanently and remain switchable indefinitely** — there is **no cleanup or deletion sub-task** for the legacy builders in any sprint or post-sprint plan. The flag is a durable A/B switch to measure unified-vs-legacy improvement, not a temporary migration shim (CSJ directive, 2026-05-18). Parity record: `May/May16Updates/fyn-prompt-rework-parity.md`. Pre-cutover reference tag: `fyn-two-prompt-pre-unify`.

---

*Source of truth. Do not paraphrase when copying into other docs — paste verbatim.*
