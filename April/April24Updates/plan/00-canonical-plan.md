# Plan — `00-canonical.md` (Two-Fyn Canonical Contract)

> **Canonical contract:** [`../spec/00-canonical.md`](../spec/00-canonical.md) — two-Fyn architecture, verbatim.
> **Branch:** all implementation commits on `feature/fyn-persona-split` (or feature branches off it). Per `spec/README.md` lines 3-5.
> **Sources:**
> - Source spec: [`../spec/00-canonical.md`](../spec/00-canonical.md)
> - Audit evidence: [`../audit-evidence.md`](../audit-evidence.md)
> - Audit synthesis: [`../audit-synthesis.md`](../audit-synthesis.md)
> - Rubrics: [`../fyn-rubrics.md`](../fyn-rubrics.md)

The canonical contract is the *source of truth*. It does not itself demand code changes — every other plan in this folder exists to make the canonical statement true of the running system. This plan slice captures the contract's preservation and enforcement obligations so nothing drifts from it during implementation.

---

### CAN-01 — Verbatim canonical block at top of every workstream artefact

- **Objective:** Paste the full text of `spec/00-canonical.md` (or a verbatim link) at the top of every doc, spec, plan, PRD, task list, and handover note produced in the Fyn v2 workstream so no downstream reader misses the contract.
- **Spec reference:** `spec/00-canonical.md` lines 1-48 (the contract itself) + `spec/README.md` line 91 ("Canonical §0 must appear at the top of any new doc…").
- **Files affected:**
  - Every new doc created under `April/April24Updates/` and its fynlaBrain mirror (vault).
  - Existing workstream docs: `April/April24Updates/CSJTODO.md`, `April/April24Updates/audit-evidence.md`, `April/April24Updates/audit-synthesis.md`, `April/April24Updates/fyn-rubrics.md`, `April/April24Updates/spec/*.md`, `April/April24Updates/plan/*.md` (this folder).
  - Inline references in source: the exact strings the contract demands — *"For regulated advice personal to your circumstances, speak to a qualified financial adviser."* (FCA signposting, see `spec/01-invariants.md` INV-2.3.3) and *"I'm able to help you with your finances. {context} is out of scope."* (out-of-remit refusal, INV-2.3.4).
- **Acceptance test:** `grep -L "FYN HAS TWO STATES" April/April24Updates/**/*.md` returns zero files that are in-scope workstream docs. Every new PRD/plan/handover in the workstream carries either the full §0 block or a one-line link *to* `spec/00-canonical.md`.
- **Out of scope:** Paraphrasing the contract. Editing CLAUDE.md to absorb the contract (CLAUDE.md references exist independently and must not drift). Reformatting the contract's two paragraphs on Onboarding Fyn and Advice Fyn.

---

### CAN-02 — Single dispatch decision in the chat controller

- **Objective:** Ensure `AiChatController::sendMessage` contains exactly one branch decision — *"Is `users.onboarding_completed = false`?"* — routing to `OnboardingChatDirector::handleUserMessage` on true or `AdviceFyn::handle` on false; no third Fyn path, no feature-flag gate on the split.
- **Spec reference:** `spec/00-canonical.md` line 27 (*"One dispatch decision in `AiChatController::sendMessage`…"*); enforced at the code level by `spec/01-invariants.md` INV-2.1.1; implemented by `spec/10-sprint-0-plan.md` Task 0.3 Steps 2, 9.
- **Files affected:**
  - `app/Http/Controllers/Api/AiChatController.php` — today reads both `users.onboarding_completed` and `config('fyn.persona_split_enabled')` (three-way dispatch), per `spec/02-current-system.md` §1 line 17.
  - `app/Services/Onboarding/OnboardingChatDirector.php:1-1985` — kept; its `handleUserMessage` is branch 1.
  - `app/Services/AI/AdviceFyn.php` — new class (Sprint 0 Task 0.3 Step 6), branch 2.
  - `app/Services/AI/FynPersonaOrchestrator.php:1-415`, `FynPersonaInvoker.php:1-518`, `FynPersonaRegistry.php:1-104`, `Prompts/DataCapturePromptBuilder.php:1-110`, `config/fyn_personas.php` — all deleted (Sprint 0 Task 0.3 Step 10).
- **Acceptance test:** `grep -rn "FynPersonaOrchestrator\|FynPersonaInvoker\|FynPersonaRegistry\|DataCapturePromptBuilder" app/ config/ tests/` returns zero matches. `tests/Architecture/PersonaMachineryAbsentTest.php` (new, Sprint 0 Task 0.3 Step 1) green. `tests/Feature/Fyn/DispatchRoutingTest.php` (new) green.
- **Out of scope:** Deleting `HandoffContract` (constants kept), `EmptyDataGuard`, `CaptureContext` VO — these are per `spec/00-canonical.md` line 31. Early-return branches for token-limit / consent-required / preview-short-circuit are exempt system paths (see INV-2.4.4, INV-2.1.1).

---

### CAN-03 — Onboarding Fyn is the only writer

- **Objective:** Ensure every database-mutating tool used by Fyn is invoked only from `OnboardingChatDirector::handleUserMessage` or `OnboardingChatDirector::handleInlineCapture`; `AdviceFyn::buildToolList` must return a read-only tool set (analytics-only `create_what_if_scenario` excepted).
- **Spec reference:** `spec/00-canonical.md` lines 11 + 18 (*"Onboarding Fyn is the ONLY state that enters or edits information"*, *"The ONLY thing Advice Fyn does NOT do is enter or edit information"*); enforced by INV-2.1.2 and INV-2.5.6.
- **Files affected:**
  - `app/Services/AI/AdviceFyn.php` — new; `buildToolList(User $user): array` excludes the 26 write tools (the `WRITE_TOOLS` constant listed in `spec/10-sprint-0-plan.md` Task 0.3 Step 6).
  - `app/Services/Onboarding/OnboardingChatDirector.php` — new method `handleInlineCapture` (Sprint 0 Task 0.3 Step 8); uses `captureToolSet()` which carries the 23 write tools (listed in the same step).
  - `app/Agents/CoordinatingAgent.php::executeTool` and the 17 `handleCreate*` / `handleUpdate*` / `handleCapture*` sites — when invoked via `AdviceFyn::handle` the write tools must be absent from the tool list passed to the provider.
- **Acceptance test:** `tests/Feature/Fyn/AdviceFynToolListTest.php` (Sprint 0 Task 0.3 Step 3) asserts `array_intersect($fyn->buildToolList($user), $writeTools)` is empty on both Anthropic and xAI providers; an integration test verifies `create_what_if_scenario` remains callable via Advice Fyn (analytics exception per INV-2.5.6).
- **Out of scope:** Removing the write tools from `AiToolDefinitions`/`XaiToolDefinitions` entirely — they remain registered because Onboarding Fyn uses them. Changing `create_what_if_scenario` behaviour (the only retained `fill_form` return, per INV-2.5.6).

---

### CAN-04 — Invisibility of the handoff

- **Objective:** Guarantee that no SSE event, DOM element, CSS state, or UX affordance communicates to the user that a handoff between Onboarding Fyn and Advice Fyn occurred; specifically no `persona_state_change` SSE event, no capturing pill, and no chat-input placeholder swap.
- **Spec reference:** `spec/00-canonical.md` line 20 (*"The user never sees the handoff…"*) and line 32 (*"Zero SSE events visible to the frontend that distinguish the two states"*); enforced by INV-2.4.1, INV-2.4.2, INV-2.4.3; system-message exemptions in INV-2.4.4.
- **Files affected:**
  - `app/Services/AI/FynPersonaOrchestrator.php:382-388` — `personaStateChangeEvent` emitter; deleted with the orchestrator (`spec/02-current-system.md §6`).
  - `resources/js/store/modules/aiChat.js:511-516` — Vuex `case 'persona_state_change'` handler; delete (Sprint 0 Task 0.4 Step 1).
  - `resources/js/store/modules/aiChat.js` — remove `personaMode` state, getter, mutation, and the `SET_PERSONA_MODE` definition.
  - `resources/js/components/Shared/AiChatPanel.vue` — remove `<div v-if="personaMode === 'capturing'">` pill and replace `:placeholder="personaMode === 'capturing' ? 'Capturing...' : 'How can I help?'"` with constant placeholder (Sprint 0 Task 0.4 Step 3).
  - `OnboardingChatDirector::handleInlineCapture` generator — strip `onboarding_layout_change` and `quick_replies` during capture sub-turn (Sprint 0 Task 0.3 Step 8).
- **Acceptance test:** `tests/Feature/Fyn/HandoffInvisibilityTest.php` (new) asserts zero `persona_state_change` events, zero `quick_replies` during capture sub-turn, and unchanged DOM placeholder across an advice → inline-capture → advice turn. Browser scenarios `BS-11`, `BS-12`, `BS-13` per `spec/03-test-strategy.md`.
- **Out of scope:** System-level SSE events (`token_limit`, `preview_cta`, `consent_required`) — exempt by INV-2.4.4; they may render distinctly. `capture_complete` SSE event emission itself — kept (per INV-2.4.3), only its styling must match regular assistant bubbles.

---

### CAN-05 — Resume / memory continuity

- **Objective:** Ensure that after disconnect, log-out, or device switch, Fyn resumes the user's exact prior state (onboarding step + parked facts + conversation index) with a natural continuation, never a cold restart.
- **Spec reference:** `spec/00-canonical.md` lines 11, 38 (Onboarding Fyn resume; Advice Fyn knows the situation); enforced by INV-2.2.4 (resume greeting + Yes/No bubble), INV-2.2.6 (parked-facts flush), INV-2.11.1 (three stores + one index, queried in order), INV-2.11.2 (index schema on `ai_conversations`).
- **Files affected:**
  - `app/Services/Onboarding/OnboardingChatDirector.php:394-406` — existing `resumeSummary($stateId)` per `spec/01-invariants.md` INV-2.2.4.
  - `app/Http/Controllers/Api/AiChatController.php::startOnboarding` — emits the resume SSE turn when `onboarding_fyn_step != null` AND last `ai_messages.created_at` older than 5 minutes (Sprint 0 Task 0.15 Step 1).
  - `app/Services/AI/MemoryRetrieverService.php` — new service (Sprint 1 Task 1.4 Step 1); retrieval order DB → parked → current → index.
  - `app/Models/AiConversation.php` — gains `summary`, `topics`, `entities_mentioned`, `intents_stated`, `summarised_at` columns (Sprint 1 Task 1.3 migration).
  - `app/Jobs/ConversationSummariserJob.php` + `app/Services/AI/ConversationSummariser.php` — new (Sprint 1 Task 1.3 Steps 3-4).
  - `app/Services/AI/MemoryRetrieverService.php::fromConversationIndex` — queries `ai_conversations` index columns only when prior layers silent.
- **Acceptance test:**
  - `tests/Feature/Onboarding/ResumeAfterDisconnectTest.php` — resume greeting present, Yes/No bubble, `resumeSummary` strings match `STATE_*` constants.
  - `tests/Unit/Services/AI/MemoryRetrieverServiceTest.php` — strict fall-through order; layer N+1 queried only when layer N empty.
  - `tests/Feature/AI/ConversationIndexPopulationTest.php` — closed conversation has non-empty `summary`, `topics`, `entities_mentioned`.
  - Browser scenarios `BS-04`, `BS-06`, `BS-24` per `spec/03-test-strategy.md`.
- **Out of scope:** Cross-user memory. Summariser running a top-tier model (use cheapest configured, default `grok-4-1-fast-non-reasoning` per INV-2.11.2 — see memory `feedback_fyn_model_choice_is_deliberate.md`). Back-filling historical `ai_conversations` that predate the migration (leave `summarised_at = null`).

---

### CAN-06 — Advice Fyn answers with engine output, not free-form

- **Objective:** Ensure every recommendation-mode Advice Fyn response is a structured `advice_response` SSE event whose interpretive content traces back to `orchestrateAnalysis` / `HolisticPlanner` / `RecommendationPersonaliser` / `ActionDefinition` output — not model-generated financial reasoning — and is rendered by `AdviceResponsePanel.vue`.
- **Spec reference:** `spec/00-canonical.md` lines 13-18 (*"uses the recommendation engine, the risk module, and every other module…"*); enforced by INV-2.3.1 (three response modes), INV-2.3.2 (engine is sole source of interpretive text), INV-2.3.3 (FCA signposting on recommendation mode), INV-2.3.5 (`advice_response` SSE shape), INV-2.3.6 (engine-call granularity).
- **Files affected:**
  - `app/Agents/CoordinatingAgent.php:158-219` — `orchestrateAnalysis(int $userId)` kept as canonical engine entry point.
  - `app/Services/Coordination/HolisticPlanner.php::createHolisticPlan` — kept.
  - `app/Services/Coordination/RecommendationPersonaliser.php` — kept.
  - `app/Services/AI/AdviceFyn.php` — new methods `classifyResponseMode(string $queryType)`, `engineCallLevel(string $queryType)`, `composeHeadline()`, `extractKeyFigures()`, `extractBreakdowns()`, `mapRecommendations()`, `extractNextSteps()` (Sprint 1 Tasks 1.6, 1.8).
  - `resources/js/components/Shared/AdviceResponsePanel.vue` — new (Sprint 1 Task 1.6 Step 3).
  - `resources/js/store/modules/aiChat.js` — new `case 'advice_response'` handler (Sprint 1 Task 1.6 Step 4).
  - `resources/js/components/Shared/AiChatPanel.vue` — render branch for `msg.role === 'advice_response'` (Sprint 1 Task 1.6 Step 5).
- **Acceptance test:**
  - `tests/Feature/Fyn/AdviceResponseSseShapeTest.php` — JSON-schema validation for every recommendation scenario.
  - `tests/Unit/Services/AI/AdviceFynResponseModeTest.php` + `AdviceFynEngineCallLevelTest.php` — exhaustive over every `QuerySchemas` constant.
  - Rubric B Mode-2 `AssertionHelpers::assertInterpretiveTextMapsToEngineSource` on every `07-regulatory` scenario.
  - Browser scenarios `BS-08` (factual), `BS-09` (recommendation).
- **Out of scope:** Replacing the recommendation engine (canonical §0 decision — reuse, not replace; see `spec/README.md` line 61). Changing `ActionDefinition` / `HolisticPlanner` outputs (stable). Adding a second narrative layer — every interpretive sentence must map to an engine field.

---

### CAN-07 — Advice Fyn is a guidance tool, not a regulated adviser

- **Objective:** Frame Fyn throughout its prompts and outputs as guidance software, not a regulated financial adviser; every recommendation-mode response must end with the canonical FCA signposting and out-of-remit queries must be refused with the canonical refusal string.
- **Spec reference:** `spec/00-canonical.md` (Advice Fyn scope definition) + `spec/01-invariants.md` INV-2.10.1 (CoreIdentity rewrite), INV-2.3.3 (FCA signposting), INV-2.3.4 (out-of-remit refusal); CSJ decision 1 in `../audit-synthesis.md §8` (guidance-only, no targeted-support pursuit).
- **Files affected:**
  - `app/Services/AI/Prompts/CoreIdentity.php` — rewrite to remove *"qualified financial planner"* and any *"authorised adviser" / "regulated adviser"* framing; guidance-only per `spec/10-sprint-0-plan.md` Task 0.13 Step 2.
  - `app/Services/AI/AdvicePromptBuilder.php` — append FCA signposting suffix instruction on recommendation-mode prompts only (Task 0.13 Step 3).
  - `app/Services/AI/AdviceFyn.php::handle` — early-return for out-of-remit classifications with exact refusal string (Task 0.14 Step 4).
  - `app/Constants/QuerySchemas.php` — `OUT_OF_REMIT = 'out_of_remit'` constant (Task 0.14 Step 2).
  - `app/Services/AI/QueryClassifier.php` — classify non-financial topics with `detected_topic` (Task 0.14 Step 3).
- **Acceptance test:**
  - `tests/Architecture/CoreIdentityFramingTest.php` — `CoreIdentity.php` source does not contain `qualified financial planner`, `authorised adviser`, `regulated adviser`.
  - `tests/Feature/Fyn/FcaSignpostingTest.php` — exact string present on recommendation mode, absent on factual and out-of-remit.
  - `tests/Feature/Fyn/OutOfRemitTest.php` — exact refusal string for medical/legal/emotional/general-knowledge queries; zero tool calls.
  - Browser scenarios `BS-09` (signposting), `BS-10` (out-of-remit), `BS-21` (CoreIdentity tone).
- **Out of scope:** Changing the commercial regulatory posture (CSJ decision 1 is guidance-only). Legal opinion work (Sprint 4 Track A.1, external-calendar-bound).

---

*End of plan for `00-canonical.md`. The canonical contract is non-negotiable — any subsequent plan that drifts from it is wrong regardless of what it otherwise accomplishes.*
