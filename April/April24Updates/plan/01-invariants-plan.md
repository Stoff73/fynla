# Fyn v2 — Canonical Two-Fyn Contract

> **BRANCH: `feature/fyn-persona-split`.** All implementation builds on this branch.

This statement is the source of truth for every doc, spec, plan, PRD, and task list in this workstream. It appears verbatim at the top of every artefact.

---

**FYN HAS TWO STATES.**

**ONBOARDING FYN** takes a user through the onboarding flow using bubbles for the user to choose the path, and guides them through the flow they choose. It accepts multi-line information and **SAVES AND WRITES** it to the database so user information is persisted. It has memory: any additional information already entered is not asked about again, but is resurfaced to the user at the right time to give a view of intelligence. If a user leaves at any point in the conversation, the next time they log in Onboarding Fyn picks up from where they left off (example only, not the whole scope: *"Good afternoon CSJ — last time we were busy entering your family details, you told me about X. Do you want to continue from where we left off?"* Yes / No bubble). Journeys are mapped according to what the user wants and where they enter onboarding from. Onboarding Fyn also receives handovers from Advice Fyn for any outstanding information needed to produce guidance. **Onboarding Fyn is the ONLY state that enters or edits information.**

**ADVICE FYN** takes a user request, fetches the user's information, and answers that request using the recommendation engine, the risk module, and every other module or system in the app as needed. Examples only, not the whole scope:

- *"Where's my invoice?"* → Advice Fyn checks subscription status and navigates to the subscription page, confirming the subscription.
- *"Should I contribute more to my ISA?"* → Advice Fyn uses the recommendation engine to surface the guidance the engine produces and navigates to the portfolio page.

Advice Fyn covers tax optimisation (income tax, asset splitting between spouses, etc.), and all other guidance across every module as per the financial planning remit, classification system, recommendation engine, and all the investment, retirement, protection, estate engines and modules. **The ONLY thing Advice Fyn does NOT do is enter or edit information** — that is Onboarding Fyn's job.

**THE USER NEVER SEES THE HANDOFF, OR FEELS THE SWITCH**, between the two states.

---

## What this means for code

- One dispatch decision in `AiChatController::sendMessage`: onboarding or advice, based on `users.onboarding_completed`.
- Onboarding Fyn = the existing `OnboardingChatDirector` (promoted) with a new `handleInlineCapture` entry point for post-onboarding captures.
- Advice Fyn = a new `AdviceFyn` class wrapping the advice-side prompt + chat loop + read-only tool list.
- No `FynPersonaOrchestrator`, no `FynPersonaInvoker`, no `FynPersonaRegistry`, no `DataCapturePromptBuilder`.
- `HandoffContract` constants and `CaptureContext` VO are kept.
- Zero SSE events visible to the frontend that distinguish the two states. No `persona_state_change` event. No capturing pill. Input placeholder invariant.

## What this means for the user

- Onboarding feels like a friendly guided flow with clickable choices and open-text questions.
- Advice feels like a conversational assistant that knows their situation, answers with real data + engine-generated guidance, and navigates them to the right module page.
- When Advice Fyn needs more information to answer something, the request for that information arrives as a natural continuation of the conversation — no "switching to capture mode" preamble, no sudden bubbles.
- Resuming on a new device / session / after a disconnect picks up exactly where the user left off.

## What this means for evaluation

- `01-invariants.md` breaks this contract into ~35 falsifiable invariants. Each invariant has a specific test.
- `fyn-rubrics.md §B` contains 75 golden conversations that exercise the contract end-to-end.
- Scenario category `09-canonical-behaviour` (10 scenarios) is the core canonical-contract test set. Any regression in that category blocks merge.

---

*Source of truth. Do not paraphrase when copying into other docs — paste verbatim.*

---

# Plan — `01-invariants.md` (35 invariants across 13 groups)

> **Canonical contract:** [`../spec/00-canonical.md`](../spec/00-canonical.md).
> **Branch:** all implementation commits on `feature/fyn-persona-split`.
> **Sources:**
> - Source spec: [`../spec/01-invariants.md`](../spec/01-invariants.md)
> - Audit evidence: [`../audit-evidence.md`](../audit-evidence.md)
> - Audit synthesis: [`../audit-synthesis.md`](../audit-synthesis.md)
> - Rubrics: [`../fyn-rubrics.md`](../fyn-rubrics.md)

One plan-slice per invariant. The "Spec reference" field in each slice points at the exact INV-X.Y.Z in `spec/01-invariants.md`. Code citations are on `feature/fyn-persona-split` and sourced from `spec/02-current-system.md` + `audit-evidence.md`.

Sprint ownership per `spec/01-invariants.md` line 21:
- Sprint 0: §2.1, §2.4, §2.5, §2.7, §2.9, §2.10
- Sprint 1: §2.2.3, §2.3, §2.11, §2.13
- Sprint 2: §2.8
- Sprint 3-4: operational

---

## §2.1 Two-Fyn architecture

### INV-2.1.1 — Single dispatch path

- **Objective:** Make `AiChatController::sendMessage` dispatch exactly once — onboarding or advice — with no orchestrator/invoker/registry/data-capture machinery left in the codebase.
- **Spec reference:** `spec/01-invariants.md` INV-2.1.1 (lines 27-31).
- **Files affected:**
  - `app/Http/Controllers/Api/AiChatController.php` — current 3-way dispatch per `spec/02-current-system.md §1` line 17.
  - DELETE: `app/Services/AI/FynPersonaOrchestrator.php:1-415`, `FynPersonaInvoker.php:1-518`, `FynPersonaRegistry.php:1-104`, `Prompts/DataCapturePromptBuilder.php:1-110`, `config/fyn_personas.php`.
  - CREATE: `app/Services/AI/AdviceFyn.php` (`spec/10-sprint-0-plan.md` Task 0.3 Step 6).
  - CREATE: `tests/Feature/Fyn/DispatchRoutingTest.php`, `tests/Architecture/PersonaMachineryAbsentTest.php`.
- **Acceptance test:** `grep -rn "FynPersonaOrchestrator\|FynPersonaInvoker\|FynPersonaRegistry\|DataCapturePromptBuilder" app/ config/ tests/` → 0 matches. `./vendor/bin/pest tests/Feature/Fyn/DispatchRoutingTest.php tests/Architecture/PersonaMachineryAbsentTest.php` green.
- **Out of scope:** Early-return branches for token-limit / consent-required / preview-short-circuit (system paths, exempt per INV-2.4.4). Removing the `CoordinatingAgent::chat` legacy entrypoint — both Fyns delegate to `chatWithPromptOverride` on it.

### INV-2.1.2 — AdviceFyn tool list disjoint from write tools

- **Objective:** `AdviceFyn::buildToolList(User $user)` returns a tool-name set whose intersection with the 26-tool write list is empty on both Anthropic and xAI providers; `create_what_if_scenario` permitted as analytics exception.
- **Spec reference:** `spec/01-invariants.md` INV-2.1.2 (lines 33-37) + INV-2.5.6.
- **Files affected:**
  - `app/Services/AI/AdviceFyn.php::buildToolList` + `WRITE_TOOLS` constant (`spec/10-sprint-0-plan.md` Task 0.3 Step 6).
  - `app/Services/AI/AiToolDefinitions.php`, `XaiToolDefinitions.php` — source tool catalogues.
  - `tests/Feature/Fyn/AdviceFynToolListTest.php` — new; runs on both providers (Task 0.3 Step 3).
- **Acceptance test:** `./vendor/bin/pest tests/Feature/Fyn/AdviceFynToolListTest.php` — `array_intersect` empty; `create_what_if_scenario` present; both providers.
- **Out of scope:** Removing the 26 write tools from the provider catalogues (Onboarding Fyn uses them). Re-adding any write tool to Advice Fyn under a flag.

### INV-2.1.3 — Dispatch condition observable

- **Objective:** `users.onboarding_completed` is the single gate between Fyns; the flag flips exactly once per user lifecycle, at `OnboardingStateMachine::STATE_DONE` transition, and never back.
- **Spec reference:** `spec/01-invariants.md` INV-2.1.3 (lines 39-43).
- **Files affected:**
  - `app/Services/Onboarding/OnboardingStateMachine.php:1-713` — `STATE_DONE` transition sets the flag.
  - `app/Services/Onboarding/OnboardingChatDirector.php` — state handlers; no other path writes the flag.
  - `tests/Feature/Onboarding/OnboardingCompletionFlagTest.php` — new.
- **Acceptance test:** `./vendor/bin/pest tests/Feature/Onboarding/OnboardingCompletionFlagTest.php` — walk state machine to done; assert flag flipped exactly at `STATE_DONE`, no earlier; flag never reverts.
- **Out of scope:** Allowing admin-initiated "redo onboarding" (out of spec). Splitting the flag into `onboarding_completed_at` (kept as a boolean per spec).

---

## §2.2 Onboarding Fyn

### INV-2.2.1 — State machine drives every non-inline onboarding turn

- **Objective:** When `users.onboarding_completed = false`, every turn goes through `OnboardingChatDirector::handleUserMessage`; SSE `turn_type` matches the state record (`bubbles / grouped_extract / free_text / delegated / terminal`).
- **Spec reference:** `spec/01-invariants.md` INV-2.2.1 (lines 49-53).
- **Files affected:**
  - `app/Services/Onboarding/OnboardingChatDirector.php:1-1985`.
  - `app/Services/Onboarding/OnboardingStateMachine.php:1-713`.
  - `tests/Feature/Onboarding/StateMachineWalkthroughTest.php` — extend (exists).
  - Browser scenario `BS-01`.
- **Acceptance test:** Pest walkthrough green; `users.onboarding_completed` flips true exactly once at `STATE_DONE`. Browser `BS-01` PASS.
- **Out of scope:** Changing state handlers beyond adding `handleInlineCapture`. Emitting SSE types outside the five `turn_type` values.

### INV-2.2.2 — Grouped-extract direct-write

- **Objective:** A grouped-extract turn (e.g. `base_spouse`) writes every field of the user's multi-field input in one `DB::transaction` via `SpouseLinkingService`/`HouseholdProvisioner`/`FamilyMember::create`; no `fill_form` SSE from onboarding path.
- **Spec reference:** `spec/01-invariants.md` INV-2.2.2 (lines 55-59).
- **Files affected:**
  - `app/Services/Onboarding/OnboardingChatDirector.php` (grouped-extract handlers).
  - `app/Services/Onboarding/SpouseLinkingService.php:1-367`.
  - `app/Services/Onboarding/HouseholdProvisioner.php:1-61`.
  - `tests/Feature/Onboarding/BaseSpouseDirectWriteTest.php` — new.
  - Browser `BS-02`.
- **Acceptance test:** Test asserts `User` row for spouse email, `FamilyMember` + `SpousePermission` rows bidirectional, `Mail::assertSent(SpouseAccountCreated::class)`, zero `fill_form` events.
- **Out of scope:** Changing `SpouseLinkingService` behaviour (canonical spouse flow). Adding a confirmation step for each field.

### INV-2.2.3 — `<known_facts>` block injected

- **Objective:** Every `OnboardingPromptBuilder::buildGroupedExtractPrompt` + `buildAssetCapturePrompt` output begins with a `<known_facts>` block listing every populated field from DB + parked + history; ends with *"Do not ask the user for any field above."*
- **Spec reference:** `spec/01-invariants.md` INV-2.2.3 (lines 62-65).
- **Files affected:**
  - `app/Services/AI/MemoryRetrieverService.php` — new (Sprint 1 Task 1.4 Step 1).
  - `app/Services/Onboarding/OnboardingPromptBuilder.php` — inject block (Task 1.4 Step 2).
  - `tests/Unit/Services/Onboarding/KnownFactsBlockTest.php` — new.
  - Rubric B `09-03 memory-no-repeat-ask` scenario.
  - Browser `BS-03`.
- **Acceptance test:** Pest unit seeds user with every onboarding field populated; built prompt contains every field name + the exact instruction suffix. Rubric B scenario green. Browser `BS-03` PASS.
- **Out of scope:** Adding `<known_facts>` to non-onboarding prompt builders (Advice Fyn uses the retriever directly per Task 1.4 Step 3, but the prompt-block format is onboarding-specific).

### INV-2.2.4 — Resume greeting + Yes/No bubble

- **Objective:** User with `onboarding_completed=false` AND `onboarding_fyn_step != null` AND last message > 5 min ago sees opening SSE turn whose `quick_replies.prompt_text` = `OnboardingChatDirector::resumeSummary($stateId)` + bubbles `[{id: 'resume', label: 'Yes, continue'}, {id: 'restart', label: 'Start over'}]`.
- **Spec reference:** `spec/01-invariants.md` INV-2.2.4 (lines 67-71).
- **Files affected:**
  - `app/Services/Onboarding/OnboardingChatDirector.php:394-406` — existing `resumeSummary`.
  - `app/Http/Controllers/Api/AiChatController.php::startOnboarding` — emit resume SSE turn on the 5-min + non-null-step condition (Sprint 0 Task 0.15 Step 1).
  - `tests/Feature/Onboarding/ResumeAfterDisconnectTest.php` — new.
  - Browser `BS-04`.
- **Acceptance test:** Pest greeting string matches; two bubbles present; Browser `BS-04` confirms UI flow.
- **Out of scope:** Changing `resumeSummary` text (kept verbatim). Adding a third "Delete progress" option.

### INV-2.2.5 — Journey mapping config-driven

- **Objective:** `config/onboarding.php::journey_map` — 4 initial entry sources (`budgeting`, `goals`, `protection`, `retirement`) → journey selections; unknown `from` → `STATE_PATH_CHOICE`; adding a source requires only a config change.
- **Spec reference:** `spec/01-invariants.md` INV-2.2.5 (lines 73-77).
- **Files affected:**
  - `config/onboarding.php` — add `journey_map` array (Sprint 0 Task 0.15 Step 2).
  - `app/Http/Controllers/Api/AiChatController.php::startOnboarding` — read `request->from`, look up map, set `onboarding_fyn_step = STATE_JOURNEY_SELECTION` with pre-selected journey.
  - `tests/Feature/Onboarding/EntrySourceJourneyMapTest.php` — new; parameterised over 4 known sources + 1 unknown.
  - Browser `BS-05`.
- **Acceptance test:** Pest parameterised; Browser `BS-05` covers 5 sub-scenarios.
- **Out of scope:** Adding more than the 4 initial entry sources in Sprint 0.

### INV-2.2.6 — Parked facts flushed at commit

- **Objective:** After a grouped-extract commit (base_spouse, base_expenditure, etc.) the corresponding keys are removed from `ai_conversations.onboarding_parked_facts`; subsequent `<known_facts>` build does not duplicate.
- **Spec reference:** `spec/01-invariants.md` INV-2.2.6 (lines 79-83).
- **Files affected:**
  - `app/Services/Onboarding/OnboardingChatDirector.php` — commit points flush keys from `onboarding_parked_facts` JSON column.
  - `tests/Feature/Onboarding/ParkedFactsFlushTest.php` — new.
  - Browser `BS-06`.
- **Acceptance test:** Pest seeds parked `{first_name: 'X'}`; runs commit; asserts parked-keyset excludes `first_name`. Browser `BS-06` confirms UI.
- **Out of scope:** Changing the JSON-column schema. Flushing at every turn (only at commit points).

---

## §2.3 Advice Fyn

### INV-2.3.1 — Two response modes (+ out-of-remit)

- **Objective:** `AdviceFyn::classifyResponseMode(string $queryType): 'factual'|'recommendation'|'out_of_remit'` exhaustively covers every `QuerySchemas` constant + billing + document-reference types.
- **Spec reference:** `spec/01-invariants.md` INV-2.3.1 (lines 89-93).
- **Files affected:**
  - `app/Services/AI/AdviceFyn.php::classifyResponseMode` — new (Sprint 1 Task 1.8 Step 2).
  - `app/Constants/QuerySchemas.php` — source enumeration.
  - `tests/Unit/Services/AI/AdviceFynResponseModeTest.php` — new, parameterised over all 22 `QuerySchemas` + 3 billing + 1 document-reference + out-of-remit.
  - Browser `BS-08` (factual), `BS-09` (recommendation).
- **Acceptance test:** Every `QuerySchemas` constant covered by the map; no constant unmapped.
- **Out of scope:** Adding a fourth mode. Dynamic classification beyond the static map.

### INV-2.3.2 — Engine is the sole source of interpretive text

- **Property:** Every interpretive sentence in `advice_response` must trace to `ranked_recommendations[*]`, `executive_summary.*`, `personalised_context[*]`, `ActionDefinition.description_template`, `HolisticPlanner.risk_assessment[*].description`, or the fixed signposting.
- **Spec reference:** `spec/01-invariants.md` INV-2.3.2 (lines 95-99).
- **Files affected:**
  - `app/Services/AI/AdviceFyn.php` helper methods (`composeHeadline`, `extractBreakdowns`, `mapRecommendations`) only read engine output — Sprint 1 Task 1.6 Step 2.
  - `tests/Feature/Fyn/Eval/AssertionHelpers.php::assertInterpretiveTextMapsToEngineSource` — new (Sprint 1 Task 1.1 Step 4).
  - Rubric B `07-regulatory/*` scenarios.
- **Acceptance test:** 100% of interpretive sentences in `07-regulatory/*` trace to engine field. Mode-2 regex check enforces.
- **Out of scope:** Adding a narrative layer to `HolisticPlanner`. Allowing AdviceFyn to generate commentary beyond engine output.

### INV-2.3.3 — FCA signposting on every recommendation-mode response

- **Objective:** Every `advice_response` (mode = recommendation) ends with *"For regulated advice personal to your circumstances, speak to a qualified financial adviser."* in `advice_response.signposting`; factual and out-of-remit do NOT append.
- **Spec reference:** `spec/01-invariants.md` INV-2.3.3 (lines 101-105).
- **Files affected:**
  - `app/Services/AI/AdviceFyn.php` recommendation-mode emission (Sprint 1 Task 1.6 Step 2).
  - `app/Services/AI/AdvicePromptBuilder.php` — suffix instruction for recommendation-only prompts (Sprint 0 Task 0.13 Step 3).
  - `resources/js/components/Shared/AdviceResponsePanel.vue` — renders signposting at card end.
  - `tests/Feature/Fyn/FcaSignpostingTest.php` — new.
  - Browser `BS-09`.
- **Acceptance test:** Exact string match; absence on factual + out-of-remit.
- **Out of scope:** Translating the signposting string. Changing wording per CSJ decision.

### INV-2.3.4 — Out-of-remit canonical refusal

- **Objective:** For `out_of_remit` classifications, response is exactly *"I'm able to help you with your finances. {context} is out of scope."*; `{context}` from classifier `detected_topic` or fallback "general queries"; no tool calls.
- **Spec reference:** `spec/01-invariants.md` INV-2.3.4 (lines 107-111).
- **Files affected:**
  - `app/Services/AI/AdviceFyn.php::handle` early-return (Sprint 0 Task 0.14 Step 4).
  - `app/Services/AI/QueryClassifier.php` — extended to return `out_of_remit` with `detected_topic`.
  - `app/Constants/QuerySchemas.php::OUT_OF_REMIT`.
  - `tests/Feature/Fyn/OutOfRemitTest.php` — new, parameterised over medical/legal/emotional/general-knowledge.
  - Browser `BS-10`.
- **Acceptance test:** Exact string shape; zero tool calls in SSE.
- **Out of scope:** Adding contact details in the refusal. Adding more categories beyond classifier coverage.

### INV-2.3.5 — `advice_response` SSE shape

- **Objective:** Recommendation-mode turn emits exactly one `advice_response` event with the shape in `spec/01-invariants.md` lines 117-139 (`headline`, `key_figures[]`, `breakdowns[]`, `recommendations[]`, `next_steps[]`, `signposting`); rendered by `AdviceResponsePanel.vue`. Factual-mode emits zero.
- **Spec reference:** `spec/01-invariants.md` INV-2.3.5 (lines 113-144).
- **Files affected:**
  - `app/Services/AI/AdviceFyn.php` emission logic (Sprint 1 Task 1.6 Step 2).
  - `resources/js/components/Shared/AdviceResponsePanel.vue` — new.
  - `resources/js/store/modules/aiChat.js` — new `case 'advice_response'` handler.
  - `resources/js/components/Shared/AiChatPanel.vue` — render branch.
  - `tests/Feature/Fyn/AdviceResponseSseShapeTest.php` — JSON-schema validation.
  - Browser `BS-09`.
- **Acceptance test:** Every recommendation scenario emits exactly one event matching schema; factual scenarios emit 0.
- **Out of scope:** Adding fields beyond those in the spec (`additionalProperties: false` by convention).

### INV-2.3.6 — Engine call granularity by query type

- **Objective:** `AdviceFyn::engineCallLevel(string $queryType): 'holistic'|'module'|'factual'` — holistic/cross-module → `CoordinatingAgent::orchestrateAnalysis`; module-scoped → single-agent `analyze`; pure-factual → module service direct (e.g. `NetWorthService::getOverview`) with no engine call.
- **Spec reference:** `spec/01-invariants.md` INV-2.3.6 (lines 146-150).
- **Files affected:**
  - `app/Services/AI/AdviceFyn.php::engineCallLevel` (Sprint 1 Task 1.8 Step 3).
  - `tests/Unit/Services/AI/AdviceFynEngineCallLevelTest.php` — new, exhaustive over `QuerySchemas`.
- **Acceptance test:** No query type maps to multiple levels; none unmapped; holistic/cross-module are only callers of `orchestrateAnalysis`.
- **Out of scope:** Adding a `sub_module` level. Allowing holistic for module-scoped queries.

---

## §2.4 Handoff invisibility

### INV-2.4.1 — Zero `persona_state_change` events

- **Objective:** No server code path emits `persona_state_change`; Vuex store has no handler; no `personaMode` getter; no capturing pill; input placeholder constant.
- **Spec reference:** `spec/01-invariants.md` INV-2.4.1 (lines 156-160); `spec/02-current-system.md §6`.
- **Files affected:**
  - DELETE emitter at `app/Services/AI/FynPersonaOrchestrator.php:382-388` (with the whole file).
  - `resources/js/store/modules/aiChat.js:511-516` — delete handler + `personaMode` state.
  - `resources/js/components/Shared/AiChatPanel.vue` — delete pill + placeholder swap.
  - `tests/Feature/Fyn/HandoffInvisibilityTest.php` — new.
  - Browser `BS-11`.
- **Acceptance test:** Pest + Playwright assert 0 `persona_state_change` events across advice→capture→advice turn; DOM snapshot before/after identical.
- **Out of scope:** Removing `capture_complete` emission (kept per INV-2.4.3).

### INV-2.4.2 — Inline-capture conversational only

- **Objective:** `OnboardingChatDirector::handleInlineCapture` emits only `content` + direct-write tool events; never `quick_replies`, `onboarding_layout_change`.
- **Spec reference:** `spec/01-invariants.md` INV-2.4.2 (lines 162-166).
- **Files affected:**
  - `app/Services/Onboarding/OnboardingChatDirector.php::handleInlineCapture` — strips those event types (Sprint 0 Task 0.3 Step 8).
  - `tests/Feature/Onboarding/InlineCaptureSilenceTest.php` — new.
  - Rubric B `09-07 advice-handoff-invisible-capture`.
  - Browser `BS-11`.
- **Acceptance test:** `quick_replies.length === 0` + `onboarding_layout_change.length === 0` during capture sub-turn.
- **Out of scope:** Suppressing these event types outside inline-capture (onboarding path still uses them).

### INV-2.4.3 — `capture_complete` matches assistant styling

- **Objective:** `capture_complete` SSE event still emitted; frontend renders as normal assistant bubble — no capture-mode badge, distinct border, or icon.
- **Spec reference:** `spec/01-invariants.md` INV-2.4.3 (lines 168-172).
- **Files affected:**
  - `resources/js/components/Shared/AiChatPanel.vue` render branch for `capture_complete` — uses same classes as `content` bubbles.
  - `tests/Feature/Fyn/CaptureCompleteStylingTest.php` — new (Sprint 0 Task 0.15 Step 4).
  - Browser `BS-12`.
- **Acceptance test:** `browser_evaluate` returns identical `classList` sets (minus timestamp whitelist).
- **Out of scope:** Removing `capture_complete` emission.

### INV-2.4.4 — System-level messages exempt

- **Objective:** `token_limit`, `preview_cta`, `consent_required`, maintenance SSE types may render distinctly; they are system emissions, not Fyn turns; explicitly allowlisted in invisibility tests.
- **Spec reference:** `spec/01-invariants.md` INV-2.4.4 (lines 174-178).
- **Files affected:**
  - `tests/Feature/Fyn/HandoffInvisibilityTest.php` — allowlist constant naming the permitted system types.
  - Browser `BS-13` covers `token_limit` rendering.
- **Acceptance test:** Allowlist explicit in source; new system events must be added to it.
- **Out of scope:** Rendering system events as regular bubbles.

### INV-2.4.5 — Handoff payload validation

- **Objective:** `delegate_to_capture` + `capture_complete` payloads validated by `app/Services/AI/HandoffPayloadValidator.php` (new); on malformed, emit `handoff_error` SSE event and exit capture mode.
- **Spec reference:** `spec/01-invariants.md` INV-2.4.5 (lines 180-184); failure class per `audit-evidence.md §19`.
- **Files affected:**
  - `app/Services/AI/HandoffPayloadValidator.php` — new (Sprint 0 Task 0.3 Step 7).
  - `app/Services/AI/AdviceFyn.php` + `OnboardingChatDirector::handleInlineCapture` — call validator; emit `handoff_error` on failure.
  - `tests/Feature/Fyn/HandoffPayloadValidationTest.php` — new.
- **Acceptance test:** Malformed payloads produce `handoff_error`; capture mode exits; no silent-mode-stay.
- **Out of scope:** Auto-repairing malformed payloads.

---

## §2.5 Direct-write tool semantics

### INV-2.5.1 — `create_*` / `update_*` / `capture_*` write synchronously

- **Objective:** 16 of 17 current fill_form handlers return `{success, entity_type, entity_id, persisted_fields, validation_errors}` after writing in a `DB::transaction`; `create_what_if_scenario` retains fill_form (INV-2.5.6).
- **Spec reference:** `spec/01-invariants.md` INV-2.5.1 (lines 190-194); 17 handler sites at `spec/02-current-system.md §3.2`.
- **Files affected:**
  - `app/Agents/CoordinatingAgent.php` — rewrite handlers at lines 1510, 1549, 1595, 1742, 1809, 1887, 2018, 2065, 2132, 2165, 2205, 2244, 2861, 2923, 2978, 3021, 3142 (Sprint 0 Task 0.5 sub-tasks a-p).
  - Per-handler `tests/Feature/AI/DirectWrite/Create*Test.php` — 16 new.
  - `tests/Feature/AI/DirectWriteCoverageTest.php` — new.
  - Browser `BS-14` sample.
- **Acceptance test:** `grep -c "'action' => 'fill_form'" app/Agents/CoordinatingAgent.php` = 1.
- **Out of scope:** Touching the 13 already-direct-write handlers. Removing `create_what_if_scenario`.

### INV-2.5.2 — Observer chain fires on every direct-write

- **Objective:** Each direct-write handler's write fires `UserRiskObserver`, `*RiskObserver`, goal-contribution observers via `TracksGoalContributions` trait, `NetWorthCacheObserver`, `RecommendationCacheObserver`, `LifeEventMonteCarloObserver`, `TrustObserver` as appropriate.
- **Spec reference:** `spec/01-invariants.md` INV-2.5.2 (lines 196-200).
- **Files affected:**
  - `app/Observers/*` — unchanged (just invoked).
  - `tests/Feature/AI/DirectWriteObserverFireTest.php` — new; observer spies per handler.
- **Acceptance test:** Per-handler test asserts expected `Created::class` event dispatched.
- **Out of scope:** Adding new observers.

### INV-2.5.3 — Model sees real `entity_id` across turns

- **Objective:** `HasAiChat::buildMessageHistory` at line 679 preserves `entity_id` + `entity_type` in prior-tool-result context; `summariseToolResult` at line 749 is loosened to keep these two fields.
- **Spec reference:** `spec/01-invariants.md` INV-2.5.3 (lines 202-206).
- **Files affected:**
  - `app/Traits/HasAiChat.php:679` (buildMessageHistory).
  - `app/Traits/HasAiChat.php:749` (summariseToolResult).
  - `tests/Unit/Traits/HasAiChatSummarisationTest.php` — new.
- **Acceptance test:** Two-turn conversation prompt snapshot contains the entity_id from turn 1 verbatim.
- **Out of scope:** Preserving full tool-result bodies in history (only the two ID fields).

### INV-2.5.4 — Audit trail matches reality

- **Objective:** Every tool execution produces 2 `ai_audit_events` rows: `dispatched` at entry, `persisted` (with `entity_id`) OR `failed` at exit; handoff tools stripped with `status='stripped'`; file-channel `[AI-AUDIT]` log at line 770 removed.
- **Spec reference:** `spec/01-invariants.md` INV-2.5.4 (lines 208-212).
- **Files affected:**
  - `app/Agents/CoordinatingAgent.php:770` — delete log call.
  - `app/Services/AI/AuditChainService.php` — new (Sprint 0 Task 0.12).
  - `app/Agents/CoordinatingAgent.php::executeTool` — insert `append` calls at dispatch/success/failure.
  - `tests/Feature/AI/AuditTruthfulnessTest.php` — new.
  - Browser `BS-15`.
- **Acceptance test:** Per handler test shows exactly one `dispatched` + one terminal row per call.
- **Out of scope:** Retroactively back-filling the new table (empty at migration time is fine).

### INV-2.5.5 — Direct-write transaction per handler

- **Objective:** Each handler wraps writes in `DB::transaction`; observers fire within; cache invalidation + email queuing use `DB::afterCommit`.
- **Spec reference:** `spec/01-invariants.md` INV-2.5.5 (lines 214-218).
- **Files affected:** Handler bodies (Sprint 0 Task 0.5). `tests/Feature/AI/DirectWriteTransactionRollbackTest.php` — new.
- **Acceptance test:** Mid-write exception leaves 0 rows.
- **Out of scope:** Nesting transactions across multiple models in unrelated tables.

### INV-2.5.6 — `create_what_if_scenario` analytics exception

- **Objective:** `create_what_if_scenario` retains `fill_form` return shape; allowed on `AdviceFyn` tool list; sole remaining `fill_form` call site.
- **Spec reference:** `spec/01-invariants.md` INV-2.5.6 (lines 220-224).
- **Files affected:** `app/Agents/CoordinatingAgent.php::handleCreateWhatIfScenario`. `tests/Feature/Fyn/AdviceFynToolListTest.php` whitelist.
- **Acceptance test:** `grep -c "'action' => 'fill_form'" app/Agents/CoordinatingAgent.php` = 1.
- **Out of scope:** Converting this handler to direct-write (analytics UI flow depends on form-fill).

---

## §2.6 Read completeness

### INV-2.6.1 — `list_*` + `get_*` return complete data

- **Objective:** `handleListRecords`, `handleListGoals`, `handleListLifeEvents`, `handleModuleAnalysis` return complete DB rows — no truncation, no pagination below 100, no summarisation.
- **Spec reference:** `spec/01-invariants.md` INV-2.6.1 (lines 230-234).
- **Files affected:**
  - `app/Agents/CoordinatingAgent.php` — list handlers.
  - `tests/Feature/AI/ReadCompletenessTest.php` — new (Sprint 0 Task 0.15 Step 5).
- **Acceptance test:** Seeded 50+ records; handler return count equals DB count.
- **Out of scope:** Paginating for pagination's sake. Summarising lists.

### INV-2.6.2 — `get_recommendations` completeness

- **Objective:** `handleRecommendations` at `CoordinatingAgent.php:1390` returns full `ranked_recommendations` with every metadata field (`priority_score`, `timeline`, `category`, `impact`, `recommendation_text`, `rationale`, `personalised_context`).
- **Spec reference:** `spec/01-invariants.md` INV-2.6.2 (lines 236-240).
- **Files affected:**
  - `app/Agents/CoordinatingAgent.php:1390`.
  - `tests/Feature/AI/GetRecommendationsCompletenessTest.php` — new (Sprint 0 Task 0.15 Step 6).
- **Acceptance test:** Every field round-trips through the handler.
- **Out of scope:** Changing `orchestrateAnalysis` return shape.

---

## §2.7 Provider parity

### INV-2.7.1 — Identical tool catalogue

- **Objective:** `AiToolDefinitions::getTools(false)` and `XaiToolDefinitions::getTools(false)` return identical 40-name sets (37 + 3 billing, Sprint 0); 54-name sets (Sprint 2 + 14 batch).
- **Spec reference:** `spec/01-invariants.md` INV-2.7.1 (lines 246-250).
- **Files affected:**
  - `app/Services/AI/AiToolDefinitions.php`, `XaiToolDefinitions.php`.
  - `tests/Architecture/ToolCatalogueParityTest.php` — new.
- **Acceptance test:** Sorted name arrays equal.
- **Out of scope:** Feature-gating per-provider tools.

### INV-2.7.2 — Billing tools on both providers

- **Objective:** 3 new tools (`get_subscription_status`, `list_invoices`, `get_current_plan`) registered on both providers with documented shapes.
- **Spec reference:** `spec/01-invariants.md` INV-2.7.2 (lines 252-260); data sources at `audit-evidence.md §22`.
- **Files affected:**
  - `app/Services/AI/AiToolDefinitions.php::billingTools()`, `XaiToolDefinitions.php` wrapped.
  - `app/Agents/CoordinatingAgent.php::executeTool` — 3 handlers (Sprint 0 Task 0.6 Step 4).
  - `tests/Feature/AI/BillingToolsTest.php` — new.
  - Browser `BS-16`.
- **Acceptance test:** Handlers return documented shapes; parity test green.
- **Out of scope:** Mutating subscription state via tools.

### INV-2.7.3 — `update_record` strict + per-entity allowlist

- **Objective:** `update_record.fields` schema = `oneOf` keyed on `entity_type` with explicit allowed fields per entity; xAI `strict: true`; handler enforces `UpdateRecordAllowlist::MAP`.
- **Spec reference:** `spec/01-invariants.md` INV-2.7.3 (lines 262-266).
- **Files affected:**
  - `app/Constants/UpdateRecordAllowlist.php` — new.
  - `app/Agents/CoordinatingAgent.php::handleUpdateRecord` ~3134.
  - `app/Services/AI/AiToolDefinitions.php` + `XaiToolDefinitions.php` — new `update_record` schema.
  - `tests/Unit/Constants/UpdateRecordAllowlistTest.php`, `tests/Feature/AI/UpdateRecordSecurityTest.php` — new.
- **Acceptance test:** Forbidden field attempt → `{error: 'fields_not_allowed', disallowed_fields: [...]}`; allowed field writes succeed.
- **Out of scope:** Adding fields to the allowlist beyond those in `spec/01-invariants.md` INV-2.7.3. Loosening strictness on xAI.

### INV-2.7.4 — Preview mode tool filtering consistent

- **Objective:** `getTools(true)` on both providers excludes every DB-mutating tool; identical sets.
- **Spec reference:** `spec/01-invariants.md` INV-2.7.4 (lines 268-272).
- **Files affected:**
  - `app/Services/AI/AiToolDefinitions.php::getTools(true)`, `XaiToolDefinitions.php::getTools(true)`.
  - `tests/Architecture/PreviewModeToolCatalogueTest.php` — new (Sprint 0 Task 0.15 Step 7).
- **Acceptance test:** Intersection with write tools = empty; both providers identical.
- **Out of scope:** Allowing any write tool in preview.

---

## §2.8 Multi-entity coverage

### INV-2.8.1 — Sprint 0: 4-focus gap-fill

- **Objective:** `AssetCaptureEntityExtractor` runs inside `OnboardingChatDirector::handleInlineCapture` (Sprint 0.3) + existing `handleAssetCapture`; covers protection/savings/retirement/investment; dedup `(user_id, entity_fingerprint, 24h)` before emit.
- **Spec reference:** `spec/01-invariants.md` INV-2.8.1 (lines 278-282).
- **Files affected:**
  - `app/Services/Onboarding/AssetCaptureEntityExtractor.php:1-665` — add dedup in `findMissing` (Sprint 0 Task 0.11 Step 5).
  - `app/Services/Onboarding/OnboardingChatDirector.php::emitGapFillFromCaptureContext` — port from invoker (Sprint 0 Task 0.3 Step 8).
  - `tests/Feature/AI/MultiEntityGapFillTest.php` — new.
  - Browser `BS-17` (4-focus).
- **Acceptance test:** 2 policies persisted first run; 2 (not 4) after identical retry.
- **Out of scope:** All 18 entity types (Sprint 2).

### INV-2.8.2 — Sprint 2: batch-shaped tools for 18+ entity types

- **Objective:** 14 new `capture_<entity>_batch` tools registered on both providers with strict JSON schemas; each persists all-or-none via single `DB::transaction`; `AssetCaptureEntityExtractor` retired when sustained gap-fill fire rate <2% over 2 weeks.
- **Spec reference:** `spec/01-invariants.md` INV-2.8.2 (lines 284-288); Sprint 2 Tasks 2.1-2.18.
- **Files affected:**
  - `app/Services/AI/AiToolDefinitions.php`, `XaiToolDefinitions.php` — 14 tool defs.
  - `app/Agents/CoordinatingAgent.php::executeTool` — 14 handlers.
  - `tests/Feature/AI/BatchCapture/*Test.php` — per tool.
  - `tests/Feature/Fyn/Eval/scenarios/03-multi-entity/*` — 10 scenarios.
  - Browser `BS-17` extended to 14 variants.
- **Acceptance test:** All 10 multi-entity scenarios pass with direct batch-tool emission (0 gap-fill synthesised).
- **Out of scope:** Retiring extractor before fire-rate gate met.

### INV-2.8.3 — Hard-fail floors per Rubric B

- **Objective:** Eval run computes per-tool entity validity / recall / precision / value accuracy / cross-entity consistency / fabrication rate; non-tunable floors 100% validity, 100% value, 100% consistency, 0% fabrication; tunable 95% recall/precision.
- **Spec reference:** `spec/01-invariants.md` INV-2.8.3 (lines 290-294); `fyn-rubrics.md §B`.
- **Files affected:**
  - `config/fyn_eval.php::hard_fail_floors`.
  - `tests/Feature/Fyn/Eval/EvalRunner.php::enforceHardFailFloors` (Sprint 1 Task 1.1).
- **Acceptance test:** Any floor violation exits non-zero from CI job.
- **Out of scope:** Lowering hard-fail floors. Adding a second rubric.

---

## §2.9 Reliability

### INV-2.9.1 — Atomic token budget

- **Objective:** New `ai_daily_usage` table with `(user_id, usage_date)` unique constraint; `HasAiGuardrails::consume` uses `DB::transaction` + `SELECT ... FOR UPDATE`; `Cache::remember(..., 300, …)` at line 221 removed.
- **Spec reference:** `spec/01-invariants.md` INV-2.9.1 (lines 300-304).
- **Files affected:**
  - `database/migrations/2026_04_25_000003_create_ai_daily_usage_table.php` — new.
  - `app/Models/AiDailyUsage.php` — new.
  - `app/Traits/HasAiGuardrails.php:221` — rewrite.
  - `tests/Feature/AI/TokenBudgetConcurrencyTest.php` — new.
- **Acceptance test:** 2 parallel requests at budget boundary; second → `token_limit` without double-consuming.
- **Out of scope:** Per-org budget (Sprint 4 Task 4.4).

### INV-2.9.2 — SSE abort keep-writes + instrument

- **Objective:** `connection_aborted()` mid-generator → commit in-flight writes, insert `ai_abort_events` row with `{conversation_id, last_tool_call, partial_write_count}`; reconnect reads fresh DB.
- **Spec reference:** `spec/01-invariants.md` INV-2.9.2 (lines 306-312); CSJ 24 April (keep, instrument).
- **Files affected:**
  - `database/migrations/2026_04_25_000005_create_ai_abort_events_table.php` — new.
  - `app/Models/AiAbortEvent.php` — new.
  - `app/Traits/HasAiChat.php` — abort polling (Sprint 0 Task 0.11 Step 2).
  - `tests/Feature/AI/SseAbortKeepWritesTest.php` — new.
  - Browser `BS-18`.
- **Acceptance test:** Mid-abort leaves record + logged event.
- **Out of scope:** Rolling back writes.

### INV-2.9.3 — Idempotency key

- **Objective:** `POST /conversations/{id}/messages` honours `Idempotency-Key` header; duplicate within 24h → cached response; `ai_request_idempotency` table + `AiIdempotencyCleanupJob` daily.
- **Spec reference:** `spec/01-invariants.md` INV-2.9.3 (lines 314-318).
- **Files affected:**
  - `database/migrations/2026_04_25_000004_create_ai_request_idempotency_table.php` — new.
  - `app/Models/AiRequestIdempotency.php` — new.
  - `app/Http/Middleware/IdempotencyKeyMiddleware.php` — new.
  - `routes/api.php` — attach.
  - `app/Jobs/AiIdempotencyCleanupJob.php` + schedule — new.
  - `tests/Feature/AI/IdempotencyKeyTest.php` — new.
- **Acceptance test:** Repeat POST with same key → 200 cached body; no duplicate DB rows.
- **Out of scope:** Cross-user idempotency.

### INV-2.9.4 — Provider-swap lock

- **Objective:** `HasAiGuardrails::getAiProviderForLoop()` captures provider once per chat call; versioned cache key + snapshot detects mid-loop swaps; current iteration completes on original provider.
- **Spec reference:** `spec/01-invariants.md` INV-2.9.4 (lines 320-324).
- **Files affected:**
  - `app/Traits/HasAiGuardrails.php` — new method.
  - `app/Http/Controllers/Api/AdminController.php` — versioned `ai_provider` cache.
  - `tests/Feature/AI/ProviderSwapLockTest.php` — new.
- **Acceptance test:** Admin toggle mid-loop does not leak cache markers across providers.
- **Out of scope:** Live provider-swap within a single turn (deferred to Sprint 4 Task 4.1 failover).

### INV-2.9.5 — Gap-fill dedup against DB

- **Objective:** `AssetCaptureEntityExtractor::findMissing` queries module tables for matches in last 24h before synthesising fill-in events.
- **Spec reference:** `spec/01-invariants.md` INV-2.9.5 (lines 326-330).
- **Files affected:**
  - `app/Services/Onboarding/AssetCaptureEntityExtractor.php::findMissing`.
  - `tests/Feature/AI/GapFillDedupTest.php` — new.
  - Browser `BS-19`.
- **Acceptance test:** Retry of same message → 0 gap-fill events.
- **Out of scope:** Cross-conversation dedup beyond 24h.

### INV-2.9.6 — `generateTitle` sanitised

- **Objective:** `HasAiChat::generateTitle` at line 704 → `strip_tags($message)` + `mb_substr(..., 0, 100)` before LLM call AND before writing to `ai_conversations.title`.
- **Spec reference:** `spec/01-invariants.md` INV-2.9.6 (lines 332-336).
- **Files affected:**
  - `app/Traits/HasAiChat.php:704`.
  - `tests/Unit/Traits/GenerateTitleSanitisationTest.php` — new.
  - Browser `BS-20`.
- **Acceptance test:** Output has no `<`, `>`, or raw HTML; ≤100 chars.
- **Out of scope:** Sanitising all user text (per-field sanitisation is INV-2.10.4).

---

## §2.10 Compliance

### INV-2.10.1 — CoreIdentity rewrite

- **Objective:** `app/Services/AI/Prompts/CoreIdentity.php` contains no "qualified financial planner", "authorised adviser", "regulated adviser" framing; guidance-tool only.
- **Spec reference:** `spec/01-invariants.md` INV-2.10.1 (lines 342-346).
- **Files affected:**
  - `app/Services/AI/Prompts/CoreIdentity.php` — rewrite (Sprint 0 Task 0.13 Step 2).
  - `tests/Architecture/CoreIdentityFramingTest.php` — new.
  - Browser `BS-21`.
- **Acceptance test:** `file_get_contents(...)` does not contain banned phrases.
- **Out of scope:** Legal opinion on new framing (Sprint 4 Track A.1).

### INV-2.10.2 — Hash-chain audit

- **Objective:** `ai_audit_events` table per schema in `spec/01-invariants.md` lines 350-367; `row_hash = sha256(prev_hash || serialised(fields) || signed_at)`; `signature = hmac_sha256(row_hash, env('AI_AUDIT_HMAC_KEY'))`; `php artisan ai:audit:verify-chain` walks and returns JSON; 7-year / 2-year retention.
- **Spec reference:** `spec/01-invariants.md` INV-2.10.2 (lines 348-375).
- **Files affected:**
  - `database/migrations/2026_04_25_000006_create_ai_audit_events_table.php` — new.
  - `app/Models/AiAuditEvent.php` — new.
  - `app/Services/AI/AuditChainService.php` — new.
  - `app/Console/Commands/AiAuditVerifyChainCommand.php` — new.
  - `app/Jobs/AiAuditRetentionJob.php` — new (pseudonymisation in separate export view, not source rows).
  - `tests/Feature/Audit/HashChainTest.php`, `HmacSigningTest.php`, `ChainTamperDetectionTest.php`, `RetentionPseudonymisationTest.php` — new.
  - Browser `BS-15`.
  - `resources/js/components/Admin/AiAudit.vue` — chain-view tab.
- **Acceptance test:** Chain verify green; tamper detects break; retention preserves chain verifiability.
- **Out of scope:** Encrypting row contents. Migrating `ai_messages` into the chain.

### INV-2.10.3 — Runtime consent check

- **Objective:** `AiChatController::sendMessage` + `startOnboarding` call `ConsentService::hasConsent($user, 'ai_chat')` at entry; 403 on false; mid-stream withdrawal triggers `consent_required` SSE + stream close; `ai_chat` added to `user_consents.type`.
- **Spec reference:** `spec/01-invariants.md` INV-2.10.3 (lines 377-381).
- **Files affected:**
  - `app/Services/GDPR/ConsentService.php::TYPE_AI_CHAT`.
  - `app/Http/Controllers/Api/AiChatController.php` — guard clauses.
  - `database/migrations/2026_04_25_000002_add_ai_chat_consent_types.php` — new.
  - `resources/js/store/modules/aiChat.js` — `consent_required` handler.
  - `tests/Feature/AI/ConsentRuntimeCheckTest.php` — new.
  - Browser `BS-22`.
- **Acceptance test:** 403 on missing; SSE close on withdrawal; frontend renders consent gate.
- **Out of scope:** Implementing a consent-recapture flow (handled by existing consent modal).

### INV-2.10.4 — User-controlled prompt field sanitisation

- **Objective:** Every user-controlled field (`first_name`, `surname`, `employer`, `occupation`, `goal_name`, family-member names, account names, etc.) stripped to `[A-Za-z0-9\s'.,\-]`; wrapped in `<user_provided>...</user_provided>` markers; system instructions never mix with user strings.
- **Spec reference:** `spec/01-invariants.md` INV-2.10.4 (lines 383-387).
- **Files affected:**
  - `app/Services/AI/Prompts/UserContentSanitiser.php` — new.
  - `app/Services/AI/AdvicePromptBuilder.php`, `OnboardingPromptBuilder.php` — wrap every interpolation.
  - `tests/Unit/Services/AI/Prompts/UserContentSanitisationTest.php` — new.
  - Rubric B `06-prompt-injection/*.yaml` — 10 scenarios.
  - Browser `BS-23`.
- **Acceptance test:** Sanitiser strips known-attack chars; injection scenarios pass without the LLM following injected instructions.
- **Out of scope:** Sanitising English text that contains apostrophes legitimately — regex allows `'`.

---

## §2.11 Memory model

### INV-2.11.1 — Three stores + one index, queried in order

- **Objective:** `MemoryRetrieverService::retrieve(User $user, string $queryType, array $fields_needed)` returns first populated layer in order: (1) authoritative DB (2) parked facts (3) current messages (4) index (only when missing); prompt builders inject retrieved facts as `<known_facts>` block.
- **Spec reference:** `spec/01-invariants.md` INV-2.11.1 (lines 393-403).
- **Files affected:**
  - `app/Services/AI/MemoryRetrieverService.php` — new.
  - Both prompt builders — inject block.
  - `tests/Unit/Services/AI/MemoryRetrieverServiceTest.php` — new.
  - Browser `BS-03` (behavioural).
- **Acceptance test:** Strict fall-through; layer N+1 queried only when N empty.
- **Out of scope:** Changing layer order. Using a cache across users.

### INV-2.11.2 — Conversation index schema (Option A)

- **Objective:** `ai_conversations` gains `summary TEXT`, `topics JSON`, `entities_mentioned JSON`, `intents_stated JSON`, `summarised_at TIMESTAMP`; populated by `ConversationSummariserJob` on `STATE_DONE` + inactivity > 30 min.
- **Spec reference:** `spec/01-invariants.md` INV-2.11.2 (lines 405-417).
- **Files affected:**
  - `database/migrations/2026_05_02_000001_add_conversation_index_columns.php` — new (Sprint 1 Task 1.3 Step 1).
  - `app/Models/AiConversation.php` — fillable + casts.
  - `app/Jobs/ConversationSummariserJob.php` — new.
  - `app/Services/AI/ConversationSummariser.php` — new; cheapest model.
  - `tests/Feature/AI/ConversationIndexPopulationTest.php` — new.
  - Rubric B `09-09 index-populated-on-close`.
- **Acceptance test:** Every closed conversation has non-empty `summary`, `topics`, `entities_mentioned`.
- **Out of scope:** Moving to a separate `conversation_index` table (Option B). Vector search.

### INV-2.11.3 — `search_conversation_index` tool

- **Objective:** New tool on both providers: input `{topic_keywords: string[], entity_types: string[]}`; output matching conversations' summaries; Advice Fyn calls only when `<known_facts>` silent on the needed fact.
- **Spec reference:** `spec/01-invariants.md` INV-2.11.3 (lines 419-423).
- **Files affected:**
  - `app/Services/AI/AiToolDefinitions.php`, `XaiToolDefinitions.php` — +1 tool.
  - `app/Agents/CoordinatingAgent.php::handleSearchConversationIndex` (Sprint 1 Task 1.5 Step 2).
  - `tests/Feature/AI/SearchConversationIndexTest.php` — new.
  - Rubric B `09-10 cross-conversation-surface`.
  - Browser `BS-24`.
- **Acceptance test:** Tool returns relevant subset; prior stated intent surfaces without re-asking.
- **Out of scope:** Full-text search. Fuzzy matching beyond `whereJsonContains`.

---

## §2.12 System-level messages

No new invariants — `INV-2.4.4` already defines the allowlist for exempt system SSE events (`token_limit`, `preview_cta`, `consent_required`, maintenance). Any new system event must be added to that allowlist. Plan slice `INV-2.4.4` above is the implementation owner.

---

## §2.13 Eval harness (Rubric B)

### INV-2.13.1 — Scenario floor

- **Objective:** `tests/Feature/Fyn/Eval/scenarios/` contains ≥75 YAML scenarios organised into 9 categories per `fyn-rubrics.md §B`; Mode 1 on every PR; Mode 2 weekly + on-release.
- **Spec reference:** `spec/01-invariants.md` INV-2.13.1 (lines 435-439).
- **Files affected:**
  - 9 category directories under `tests/Feature/Fyn/Eval/scenarios/`.
  - `config/fyn_eval.php::scenario_minima`.
  - `tests/Architecture/EvalScenarioCountTest.php` — new.
- **Acceptance test:** Per-category minimum met; total ≥75.
- **Out of scope:** Cross-category scenarios (each YAML belongs to one category).

### INV-2.13.2 — Hard-fail floors

- **Objective:** Per `fyn-rubrics.md §B`: 100% entity validity, 100% value accuracy, 100% cross-entity consistency, 0% fabrication. PR blocked on any violation.
- **Spec reference:** `spec/01-invariants.md` INV-2.13.2 (lines 441-445).
- **Files affected:** `tests/Feature/Fyn/Eval/EvalRunner.php::enforceHardFailFloors`.
- **Acceptance test:** Any floor violation exits non-zero from CI.
- **Out of scope:** Exempting specific scenarios from floors.

### INV-2.13.3 — Per-tool scorecard per run

- **Objective:** Mode 1 and Mode 2 runs write `storage/eval-scorecards/YYYY-MM-DD-{mode}.md` with per-tool columns (Validity, Recall, Precision, ValueAcc, Consistency, Fabrication); CI uploads as artefact.
- **Spec reference:** `spec/01-invariants.md` INV-2.13.3 (lines 447-451).
- **Files affected:**
  - `tests/Feature/Fyn/Eval/EvalReport.php` — new.
  - `.github/workflows/*` or CI config — artefact upload (existing CI has a hook).
- **Acceptance test:** Scorecard file exists post-run; all 6 columns populated per tool row.
- **Out of scope:** Uploading scorecards to external systems.

### INV-2.13.4 — Thresholds in `config/fyn_eval.php`

- **Objective:** Per-tool `recall_floor` / `precision_floor` tracked in config with `reason`, `reviewed_by`, `next_review` metadata for any below-100% floor; LOWERING requires `EVAL_FLOOR_LOWER: …` commit-message tag.
- **Spec reference:** `spec/01-invariants.md` INV-2.13.4 (lines 453-457).
- **Files affected:**
  - `config/fyn_eval.php` — threshold map.
  - `tests/Architecture/EvalFloorIntegrityTest.php` — new; parses git log for `EVAL_FLOOR_LOWER` tags.
- **Acceptance test:** No unexplained floor lowering.
- **Out of scope:** Automating floor-raise decisions.

---

## Verification

### INV-V — Per-sprint verification rollup

- **Objective:** Enforce the 7-step verification in `spec/01-invariants.md §verification` (lines 461-497) after each sprint: Pest full, Architecture, Rubric-B Mode 1, Mode 2 (weekly), Browser matrix, audit chain, Rubric-A re-score.
- **Spec reference:** `spec/01-invariants.md §verification`.
- **Files affected:**
  - `docs/sprint-<n>-verification/` per sprint.
  - Sprint plan verification sections (Sprint 0 §Sprint-0-verification, Sprint 1 §Sprint-1-verification, etc.).
- **Acceptance test:** Each sprint's verification doc contains all 7 checks + evidence links. Rubric-A targets: Sprint 0 → 13-15, Sprint 1 → 17-18 🟠, Sprint 2 → ~22, Sprint 3 → ~24 🟡, Sprint 4 → 28-30 🟡.
- **Out of scope:** Skipping any step in favour of "test suite green is enough" — the strategy is two-layer per `spec/03-test-strategy.md`.

---

*End of plan for `01-invariants.md`. Every invariant is the authoritative definition of "correct"; sprint plans convert each into TDD tasks.*
