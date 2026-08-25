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

# Fyn v2 — Invariants (the spec proper)

> **BRANCH: `feature/fyn-persona-split`.**
>
> Canonical contract: [`00-canonical.md`](00-canonical.md). Read first.
> Current system anchored in code: [`02-current-system.md`](02-current-system.md).
> Source documents: [`../audit-evidence.md`](../audit-evidence.md), [`../audit-synthesis.md`](../audit-synthesis.md), [`../fyn-rubrics.md`](../fyn-rubrics.md).

---

## How to read this document

Each invariant has three lines:

- **Property:** what must be true of the running system.
- **Falsifiability test:** a command, test file, or Playwright script that returns yes/no against a running build.
- **Acceptance criterion:** what PASS looks like.

A build is "correct per the spec" iff every invariant passes its test. Any invariant failure blocks merge.

Invariants are grouped into 13 sections (§2.1 – §2.13). Sprint plans implement them in order: Sprint 0 closes §2.1, §2.4, §2.5, §2.7, §2.9, §2.10; Sprint 1 closes §2.2.3, §2.3, §2.11, §2.13; Sprint 2 closes §2.8; Sprint 3-4 close operational invariants.

---

## §2.1 Two-Fyn architecture

### INV-2.1.1 Single dispatch path

- **Property:** `app/Http/Controllers/Api/AiChatController.php::sendMessage` routes every chat turn to exactly one of two code paths — `OnboardingChatDirector::handleUserMessage` when `users.onboarding_completed = false`, `AdviceFyn::handle` otherwise. No `FynPersonaOrchestrator`, no `FynPersonaInvoker`, no `FynPersonaRegistry`, no `DataCapturePromptBuilder`. A third code branch for system-level early-returns (token limit, consent required, preview short-circuit) is exempt and must not count as a Fyn path.
- **Test:** `grep -rn "FynPersonaOrchestrator\|FynPersonaInvoker\|FynPersonaRegistry\|DataCapturePromptBuilder" app/ config/ tests/` → zero matches. `tests/Feature/Fyn/DispatchRoutingTest.php` inspects the `sendMessage` method source and asserts exactly two Fyn dispatch branches.
- **Acceptance:** files deleted; controller body contains exactly one `OnboardingChatDirector` reference and one `AdviceFyn` reference plus the early-return block; `./vendor/bin/pest` passes.

### INV-2.1.2 AdviceFyn tool list is disjoint from write tools

- **Property:** `AdviceFyn::buildToolList(User $user)` returns a list of tool names whose intersection with the following set is empty: `create_savings_account`, `create_investment_account`, `create_holding`, `create_pension`, `create_property`, `create_mortgage`, `create_protection_policy`, `create_asset`, `create_liability`, `create_estate_gift`, `create_chattel`, `create_business_interest`, `create_trust`, `create_family_member`, `create_will`, `update_will`, `create_power_of_attorney`, `update_power_of_attorney`, `update_record`, `delete_record`, `update_profile`, `set_expenditure`, `capture_personal_details`, `capture_spouse_details`, `capture_dependants`, `capture_work_details`. Allowed: every `list_*`, every `get_*`, `navigate_to_page`, `create_what_if_scenario` (analytics artefact — explicit exception per INV-2.5.6), `search_conversation_index` (new, per INV-2.11.3).
- **Test:** `tests/Feature/Fyn/AdviceFynToolListTest.php` — runs on both providers (anthropic + xai).
- **Acceptance:** `expect($fyn->buildToolList($user))->toContainNone([...writeTools...])` passes on both providers.

### INV-2.1.3 Dispatch condition is observable

- **Property:** `users.onboarding_completed` is the only data gate that flips between the two Fyns. The value is set to `true` exactly once per user, when the state machine transitions to `OnboardingStateMachine::STATE_DONE`.
- **Test:** `tests/Feature/Onboarding/OnboardingCompletionFlagTest.php` — seed a mid-flow user; run the state machine to done; assert the flag flipped exactly at `STATE_DONE` transition and no earlier state-transition observer flipped it.
- **Acceptance:** one flip per user lifecycle; never from `true` back to `false`; no code path other than `STATE_DONE` transition writes the flag.

---

## §2.2 Onboarding Fyn

### INV-2.2.1 State machine drives every non-inline onboarding turn

- **Property:** When `user.onboarding_completed = false`, every chat turn goes through `OnboardingChatDirector::handleUserMessage`. The director reads `users.onboarding_fyn_step` + `ai_conversations.onboarding_parked_facts` and emits SSE per the state record's `turn_type` (`bubbles` / `grouped_extract` / `free_text` / `delegated` / `terminal`).
- **Test:** `tests/Feature/Onboarding/StateMachineWalkthroughTest.php` (exists, extend) — walk path_choice → journey_selection → base_personal → base_spouse → base_dependants → base_dependants_detail → base_employment → base_work → base_retirement_date → base_expenditure → profile_review_expenditure → asset_capture → add_more → done for each journey.
- **Acceptance:** each intermediate state emits the expected SSE event types; `users.onboarding_completed` flips true exactly once at `STATE_DONE`.

### INV-2.2.2 Multi-line grouped-extract turns write direct

- **Property:** A grouped-extract state (e.g. `base_spouse`) receiving a multi-field user input (e.g. *"Angela, DOB 12 Jan 1976, email aslater@gmail.com"*) writes all fields in one `DB::transaction` via the appropriate service (`SpouseLinkingService::linkOrCreateSpouse` for spouse; `HouseholdProvisioner::ensureFor` for household; `FamilyMember::create` for dependants). No `fill_form` SSE event is emitted from the onboarding path.
- **Test:** `tests/Feature/Onboarding/BaseSpouseDirectWriteTest.php` — feature test asserting: User row exists for spouse email; FamilyMember row exists with correct `relationship`, `first_name`, `date_of_birth`; SpousePermission row exists bidirectionally; `Mail::assertSent(SpouseAccountCreated::class)`.
- **Acceptance:** all DB rows exist post-turn; mail queued; no `fill_form` in the SSE event stream.

### INV-2.2.3 `<known_facts>` block is injected, repeat-asks are impossible

- **Property:** `OnboardingPromptBuilder::buildGroupedExtractPrompt` and `OnboardingPromptBuilder::buildAssetCapturePrompt` inject a `<known_facts>` block listing every populated field from: (a) `users.*` (profile columns), (b) `family_members` (spouse + dependants), (c) linked module tables for the current focus, (d) `ai_conversations.onboarding_parked_facts`. The block ends with the instruction *"Do not ask the user for any field above."*
- **Test:** `tests/Unit/Services/Onboarding/KnownFactsBlockTest.php` — seed a user with every onboarding field populated; build the prompt; assert every field name appears in the block; assert the instruction suffix is present. Rubric B scenario `09-03 memory-no-repeat-ask` covers the behavioural gate.
- **Acceptance:** unit assertion passes; scenario green.

### INV-2.2.4 Resume greeting + Yes/No bubble

- **Property:** For a user with `onboarding_completed = false` AND `onboarding_fyn_step != null` AND last `ai_messages.created_at` older than 5 minutes, the next session emits an opening SSE turn whose `quick_replies.prompt_text` contains the output of `OnboardingChatDirector::resumeSummary($stateId)` (existing method at `OnboardingChatDirector.php:394-406`), and whose bubbles are `[{id: 'resume', label: 'Yes, continue'}, {id: 'restart', label: 'Start over'}]`.
- **Test:** `tests/Feature/Onboarding/ResumeAfterDisconnectTest.php` — Playwright scenario per Rubric B `09-02`.
- **Acceptance:** scenario green; `resumeSummary` strings match their `STATE_*` constants.

### INV-2.2.5 Journey mapping is config-driven and extensible

- **Property:** Entry source → journey selection lives in `config/onboarding.php` under `journey_map`. Lookup happens in `AiChatController::startOnboarding` when `request->from` is set. Unknown `from` falls through to `STATE_PATH_CHOICE`. Initial map: `['budgeting' => 'budgeting', 'goals' => 'goals', 'protection' => 'protection', 'retirement' => 'retirement']`. Adding a new entry source requires only a config change — no code modification.
- **Test:** `tests/Feature/Onboarding/EntrySourceJourneyMapTest.php` — each mapping picks the correct journey; unknown source starts at `path_choice`.
- **Acceptance:** parameterised test passes for all 4 initial mappings + unknown.

### INV-2.2.6 Parked facts are flushed at commit

- **Property:** `ai_conversations.onboarding_parked_facts` is read by `OnboardingChatDirector` state handlers at commit points (end of `base_spouse` turn commits family_member + user fields; end of `base_expenditure` commits expenditure). Once committed, the corresponding keys are removed from `onboarding_parked_facts` so the known-facts block does not duplicate.
- **Test:** `tests/Feature/Onboarding/ParkedFactsFlushTest.php`.
- **Acceptance:** commit clears the parked keys; subsequent prompt builds do not list the same field twice.

---

## §2.3 Advice Fyn

### INV-2.3.1 Two response modes: factual vs recommendation

- **Property:** `AdviceFyn::classifyResponseMode(string $queryType): 'factual'|'recommendation'|'out_of_remit'` maps every `QuerySchemas` constant to one of three modes. Factual mode bypasses `orchestrateAnalysis`; recommendation mode invokes it (holistic / cross-module) OR a single-agent `analyze()` (module-scoped); out-of-remit returns the canonical refusal per INV-2.3.4 without any engine call.
- **Test:** `tests/Unit/Services/AI/AdviceFynResponseModeTest.php` — parameterised over the full 22 `QuerySchemas` constants + 3 billing types + 1 document-reference type + any `out_of_remit` classifications.
- **Acceptance:** every `QuerySchemas` constant is covered by the map; no query type is unmapped.

### INV-2.3.2 Engine is the sole source of interpretive text

- **Property:** Every sentence in an `advice_response` payload that contains interpretive markers (`suggest|consider|recommend|should|could|worth|would benefit|might want|I'd suggest|one option`) must be attributable to a field in the engine's output: `ranked_recommendations[*].recommendation_text`, `ranked_recommendations[*].rationale`, `executive_summary.overview`, `executive_summary.key_vulnerabilities`, `personalised_context[*]` from `RecommendationPersonaliser`, `ActionDefinition.description_template`, `HolisticPlanner.risk_assessment[*].description`, or the fixed signposting suffix per INV-2.3.3.
- **Test:** Rubric B Mode-2 eval regex check, enforced in `tests/Feature/Fyn/Eval/AssertionHelpers.php::assertInterpretiveTextMapsToEngineSource`.
- **Acceptance:** 100% of interpretive sentences in `07-regulatory/*` scenarios trace to a source field.

### INV-2.3.3 FCA signposting on every recommendation-mode response

- **Property:** Every Advice Fyn response whose mode is `recommendation` ends with the exact signposting string: *"For regulated advice personal to your circumstances, speak to a qualified financial adviser."* Emitted in the `advice_response.signposting` field and also rendered at the end of the response card by `AdviceResponsePanel.vue`.
- **Test:** `tests/Feature/Fyn/FcaSignpostingTest.php` — parameterised over one scenario per recommendation-type query.
- **Acceptance:** exact string match; factual-mode and out-of-remit responses do NOT append the signposting.

### INV-2.3.4 Out-of-remit responses use canonical refusal

- **Property:** For classifications where `AdviceFyn::classifyResponseMode` returns `out_of_remit`, the response is exactly: *"I'm able to help you with your finances. {context} is out of scope."* where `{context}` is the classifier's detected topic (verbatim from the classifier or "general queries" fallback). No contact details, no signposting suffix, no tool call emitted.
- **Test:** `tests/Feature/Fyn/OutOfRemitTest.php` — scenarios for medical / legal / emotional-support / general-knowledge queries.
- **Acceptance:** exact string shape; zero tool calls in the SSE stream.

### INV-2.3.5 Structured `advice_response` SSE event

- **Property:** Advice Fyn emits exactly one `advice_response` SSE event per recommendation-mode turn with the payload:

```json
{
  "type": "advice_response",
  "headline": "string",
  "key_figures": [
    {"label": "string", "value": "string", "unit": "gbp|percent|years|count|none"}
  ],
  "breakdowns": [
    {"title": "string", "rows": [{"label": "string", "value": "string"}]}
  ],
  "recommendations": [
    {
      "id": "string",
      "text": "string",
      "priority": "critical|high|medium|low",
      "timeline": "immediate|short_term|medium_term|long_term",
      "source": "action_definition|engine|holistic"
    }
  ],
  "next_steps": [{"label": "string", "route": "string"}],
  "signposting": "string"
}
```

Rendered by `resources/js/components/Shared/AdviceResponsePanel.vue` (new). Factual-mode turns emit `content` + optional `navigation` SSE events, no `advice_response`.

- **Test:** `tests/Feature/Fyn/AdviceResponseSseShapeTest.php` — JSON-schema validation against every recommendation scenario.
- **Acceptance:** every recommendation scenario emits exactly one `advice_response`; schema validates; factual scenarios emit zero `advice_response` events.

### INV-2.3.6 Engine call granularity by query type

- **Property:** Engine call level depends on classification: `holistic_*` / `cross_module_*` → `CoordinatingAgent::orchestrateAnalysis`; module-scoped (`protection_*`, `savings_*`, `investment_*`, `retirement_*`, `estate_*`, `goals_*`, `tax_*`) → single-agent `{Module}Agent::analyze`; pure-factual (`net_worth_query`, `list_records`, `tax_factual`, `billing_*`, `document_reference`) → module service direct (`NetWorthService::getOverview`, etc.) with no engine call. The mapping is enumerated in `AdviceFyn::engineCallLevel(string $queryType): string`.
- **Test:** `tests/Unit/Services/AI/AdviceFynEngineCallLevelTest.php` — asserts the map is exhaustive across every `QuerySchemas` constant.
- **Acceptance:** no query type maps to multiple levels; no query type is unmapped; holistic/cross-module queries are the only ones calling `orchestrateAnalysis`.

---

## §2.4 Handoff invisibility

### INV-2.4.1 Zero `persona_state_change` SSE events reach the frontend

- **Property:** No server code path emits a `persona_state_change` event. The Vuex store has no handler for it. `aiChat.js` does not expose a `personaMode` getter. No capturing pill renders. Chat input placeholder is invariant across handoff state.
- **Test:** `tests/Feature/Fyn/HandoffInvisibilityTest.php` — Playwright-scripted advice→capture→advice handoff; assertions: `events.filter(e => e.type === 'persona_state_change').length === 0`; DOM snapshot before/after handoff shows no new pill, input placeholder string unchanged.
- **Acceptance:** Playwright + SSE capture green.

### INV-2.4.2 Inline capture emits conversational prompts only

- **Property:** `OnboardingChatDirector::handleInlineCapture(User $user, AiConversation $conversation, string $message, CaptureContext $context, ?string $currentRoute = null): \Generator` emits `content` SSE events (conversational register matching Advice Fyn) and direct-write tool events. It does NOT emit `quick_replies`, `onboarding_layout_change`, or any event that changes chat-panel layout.
- **Test:** `tests/Feature/Onboarding/InlineCaptureSilenceTest.php` + Rubric B scenario `09-07 advice-handoff-invisible-capture`.
- **Acceptance:** `quick_replies.length === 0` during capture sub-turn; `onboarding_layout_change.length === 0` during capture sub-turn.

### INV-2.4.3 `capture_complete` bubble matches normal assistant styling

- **Property:** The `capture_complete` SSE event may still be emitted (it confirms a save to the user), but the frontend renders it as a normal assistant message. No "capture-mode" badge, no distinct border colour, no icon.
- **Test:** `tests/Feature/Fyn/CaptureCompleteStylingTest.php` — CSS-class assertion on the rendered element.
- **Acceptance:** DOM element has the same CSS class set as a regular assistant `content` bubble.

### INV-2.4.4 System-level messages are exempt

- **Property:** Token-limit, consent-required, preview-short-circuit, and maintenance messages MAY emit their own SSE event types (`token_limit`, `preview_cta`, `consent_required`) and MAY render distinctly. They are system emissions, not Fyn turns, and do not count as a handoff-invisibility violation.
- **Test:** assertion in `HandoffInvisibilityTest` — permit these event types.
- **Acceptance:** list of allowed system event types is explicit in the test; any new system event must be added.

### INV-2.4.5 Handoff payload validation

- **Property:** `delegate_to_capture` and `capture_complete` payloads are validated against a JSON schema in `app/Services/AI/HandoffPayloadValidator.php` (new). On a malformed payload, Advice Fyn emits a `handoff_error` SSE event (new) and does NOT silently stay in capturing mode. See `audit-evidence.md §19` for the failure class this prevents.
- **Test:** `tests/Feature/Fyn/HandoffPayloadValidationTest.php`.
- **Acceptance:** malformed payloads fail validation; `handoff_error` is emitted; capture mode exits.

---

## §2.5 Direct-write tool semantics

### INV-2.5.1 Every `create_*` / `update_*` / `capture_*` handler writes to DB synchronously

- **Property:** Each of the 17 currently-fill_form handlers in `app/Agents/CoordinatingAgent.php` (at lines 1510, 1549, 1595, 1742, 1809, 1887, 2018, 2065, 2132, 2165, 2205, 2244, 2861, 2923, 2978, 3021, 3142 per `audit-synthesis.md §5.2`) is rewritten to return `['success' => bool, 'entity_type' => string, 'entity_id' => int|null, 'persisted_fields' => array, 'validation_errors' => array]`. No handler returns `['action' => 'fill_form', ...]` after Sprint 0, except `create_what_if_scenario` (analytics artefact, INV-2.5.6).
- **Test:** `tests/Feature/AI/DirectWriteCoverageTest.php` — iterate handler names; call each with valid payload; assert return shape + DB row existence.
- **Acceptance:** `grep -c "'action' => 'fill_form'" app/Agents/CoordinatingAgent.php` returns exactly 1 (the retained what-if site).

### INV-2.5.2 Observer chain fires on every direct-write

- **Property:** For each direct-write tool, the corresponding Laravel observer chain fires: risk recalc (`UserRiskObserver`, `InvestmentAccountRiskObserver`, `PropertyRiskObserver`, etc.), goal contribution tracking (`SavingsAccountGoalObserver`, `InvestmentAccountGoalObserver` via the `TracksGoalContributions` trait), cache invalidation (`NetWorthCacheObserver`, `RecommendationCacheObserver`), Monte Carlo triggers (`LifeEventMonteCarloObserver`), trust-triggered gift creation (`TrustObserver`).
- **Test:** `tests/Feature/AI/DirectWriteObserverFireTest.php` — Pest observer spies per handler.
- **Acceptance:** each handler's test verifies the expected observer(s) fired.

### INV-2.5.3 Model sees real `entity_id` across turns

- **Property:** The tool-call context assembled in `HasAiChat::buildMessageHistory` (line 679) preserves `entity_id` and `entity_type` from each prior tool result within the conversation. `summariseToolResult` (line 749) is loosened to retain these two fields. The model can chain `create_property → create_mortgage(property_id: X)` with a grounded reference.
- **Test:** `tests/Unit/Traits/HasAiChatSummarisationTest.php` — two-turn conversation; turn 1 creates a record; turn 2's prompt contains the `entity_id` verbatim.
- **Acceptance:** prompt snapshot contains the ID string.

### INV-2.5.4 Audit trail matches reality

- **Property:** Every tool execution produces `ai_audit_events` rows (new table, schema in INV-2.10.2): one row at dispatch (`status = 'dispatched'`), one row at completion (`status = 'persisted'` on success with `entity_id`, OR `status = 'failed'` on exception with error summary). The file-channel `[AI-AUDIT]` log at `app/Agents/CoordinatingAgent.php:770` is removed. Handoff tools stripped from SSE log with `status = 'stripped'`.
- **Test:** `tests/Feature/AI/AuditTruthfulnessTest.php` — call each direct-write handler; assert two rows with correct statuses; induce exception; assert `failed`.
- **Acceptance:** every handler test shows exactly one dispatched + one terminal row per call.

### INV-2.5.5 Direct-write transaction boundary per handler

- **Property:** Each direct-write handler wraps its writes in `DB::transaction`. Observers fire within the transaction; cache invalidation and email queuing fire after commit (via Laravel's built-in after-commit hooks or explicit `DB::afterCommit`).
- **Test:** `tests/Feature/AI/DirectWriteTransactionRollbackTest.php` — induce a mid-write exception; assert zero rows persisted.
- **Acceptance:** transaction rollback verified per handler.

### INV-2.5.6 `create_what_if_scenario` is the only retained `fill_form`-style analytics artefact

- **Property:** This tool writes a scenario row for analytics but does not mutate user-facing financial data. Allowed on `AdviceFyn::buildToolList`. It remains the sole `fill_form` return after Sprint 0, since the UI presents scenarios via a separate modal that benefits from the existing form-fill pattern.
- **Test:** `tests/Feature/Fyn/AdviceFynToolListTest.php` (INV-2.1.2) names this as an explicit allowed-on-advice exception.
- **Acceptance:** exception noted; `grep -c "'action' => 'fill_form'" CoordinatingAgent.php` = 1.

---

## §2.6 Read completeness

### INV-2.6.1 `list_*` + `get_*` tools return complete data

- **Property:** Handlers (`handleListRecords`, `handleListGoals`, `handleListLifeEvents`) do not truncate, paginate below 100 items, or summarise lists returned to the model. `handleModuleAnalysis` returns the raw `analyze()` output for the requested module — no `summariseToolAnalysis` stripping for this handler.
- **Test:** `tests/Feature/AI/ReadCompletenessTest.php` — seed 50+ records per list tool; assert handler return length equals DB count.
- **Acceptance:** list lengths match DB.

### INV-2.6.2 `get_recommendations` returns complete `orchestrateAnalysis` output

- **Property:** `handleRecommendations` at `CoordinatingAgent.php:1390` returns the full `ranked_recommendations` array with all metadata (`priority_score`, `timeline`, `category`, `impact`, `recommendation_text`, `rationale`, `personalised_context`) — no summarisation. Response-shape assertion covers every field.
- **Test:** `tests/Feature/AI/GetRecommendationsCompletenessTest.php`.
- **Acceptance:** every field in a seeded recommendation roundtrips through the handler.

---

## §2.7 Provider parity

### INV-2.7.1 Identical tool catalogue on both providers

- **Property:** `AiToolDefinitions::getTools(false)` and `XaiToolDefinitions::getTools(false)` return the same tool-name set. Count: 40 tools (37 existing on Anthropic today + 3 billing, minus zero removed). Both with strict schemas where applicable.
- **Test:** `tests/Architecture/ToolCatalogueParityTest.php` — Pest Architecture suite; `expect($anthropicNames->sort())->toEqual($xaiNames->sort())`.
- **Acceptance:** sorted tool-name arrays are equal.

### INV-2.7.2 Billing tools present on both providers

- **Property:** Three new tools registered on both `AiToolDefinitions` and `XaiToolDefinitions`:
  - `get_subscription_status` → `{status, plan_name, trial_ends_at, current_period_end, next_charge_amount, is_cancelled}`
  - `list_invoices` → `[{invoice_id, issued_at, amount, status, pdf_url}]`
  - `get_current_plan` → `{plan_name, tier, price_gbp, features[]}`
  Source data: existing `subscriptions`, `invoices` tables (per `audit-evidence.md §22`).
- **Test:** `tests/Feature/AI/BillingToolsTest.php` — handler tests per tool + parity assertion.
- **Acceptance:** handlers return documented shapes; parity test green.

### INV-2.7.3 `update_record` schema is strict + per-entity allowlist

- **Property:** `update_record.fields` schema is `additionalProperties: false` on both providers; xAI wraps with `strict: true`. The single `fields` object is replaced by a `oneOf` schema keyed on `entity_type`, with one sub-schema per entity type listing its exact updateable fields. Enforcement at `handleUpdateRecord` uses `App\Constants\UpdateRecordAllowlist::MAP[$entityType]`. Forbidden-to-update fields (per entity): `id`, `user_id`, `joint_owner_id`, `ownership_percentage`, `Trust.settlor`, `Mortgage.start_date`, `Mortgage.mortgage_type`, `FamilyMember.relationship`, `Will.testator_id`, `LastingPowerOfAttorney.donor_id`, any `*_at` timestamp, any identity-FK column.
- **Test:** `tests/Unit/Constants/UpdateRecordAllowlistTest.php` + feature test per entity type + attempt-forbidden-field test.
- **Acceptance:** attempts to write a forbidden field return `{error: 'fields_not_allowed', disallowed_fields: [...]}`; attempts to write an allowed field succeed.

### INV-2.7.4 Preview-mode tool filtering is consistent across providers

- **Property:** `AiToolDefinitions::getTools(true)` and `XaiToolDefinitions::getTools(true)` both exclude every DB-mutating tool from the returned set. Preview users see only read/navigation tools.
- **Test:** `tests/Architecture/PreviewModeToolCatalogueTest.php`.
- **Acceptance:** preview catalogue on both providers is the intersection of (read/nav tools) and identical between providers.

---

## §2.8 Multi-entity coverage

### INV-2.8.1 Sprint 0 target: 4-focus gap-fill running on both paths

- **Property:** `AssetCaptureEntityExtractor` (existing, `app/Services/Onboarding/AssetCaptureEntityExtractor.php:1-665`) runs inside `OnboardingChatDirector::handleInlineCapture` (Sprint 0.3) AND inside the director's existing `handleAssetCapture` path. Covers protection / savings / retirement / investment with known providers. Gap-fill dedup against DB: `(user_id, entity_fingerprint, created_at > now() - 24h)` before emit.
- **Test:** `tests/Feature/AI/MultiEntityGapFillTest.php` — *"Aviva £300k and Vitality £100k"* → 2 protection policies persisted; retry same message → no doubles.
- **Acceptance:** `ProtectionPolicy::where('user_id', $user->id)->count() === 2` after first run; `=== 2` (not 4) after retry.

### INV-2.8.2 Sprint 2 target: batch-shaped tools for all 18+ entity types

- **Property:** New tools (Sprint 2 plan): `capture_protection_policies(policies: [...])`, `capture_savings_accounts`, `capture_pensions`, `capture_investment_accounts`, `capture_properties_mortgages`, `capture_trusts`, `capture_family_members`, `capture_goals`, `capture_life_events`, `capture_chattels`, `capture_business_interests`, `capture_liabilities`, `capture_estate_gifts`, `capture_holdings`. Strict JSON schema per tool (`additionalProperties: false` on both providers). Each tool persists all items or none (single `DB::transaction`). Retires `AssetCaptureEntityExtractor` when gap-fill fire rate sustained <2% over a 2-week eval window.
- **Test:** scenarios `tests/Feature/Fyn/Eval/scenarios/03-multi-entity/*.yaml` (Rubric B).
- **Acceptance:** all 10 multi-entity scenarios pass with direct batch-tool emission (zero gap-fill synthesised events).

### INV-2.8.3 Hard-fail floors per Rubric B

- **Property:** Every eval run computes per-tool: entity validity, entity-count recall, field precision, monetary value accuracy, cross-entity consistency, fabrication rate. Hard-fail floors (non-tunable): 100% validity, 100% value accuracy, 100% cross-entity consistency, 0% fabrication. Tunable baselines: 95% recall, 95% precision per focus.
- **Test:** `tests/Feature/Fyn/Eval/EvalRunner.php::enforceHardFailFloors` raises on violation.
- **Acceptance:** any floor violation blocks PR merge.

---

## §2.9 Reliability

### INV-2.9.1 Atomic token budget

- **Property:** New `ai_daily_usage` table with unique `(user_id, usage_date)` constraint. `HasAiGuardrails::consume` uses `DB::transaction` + `SELECT ... FOR UPDATE`. The `Cache::remember($cacheKey, 300, …)` call at `app/Traits/HasAiGuardrails.php:221` is removed.
- **Test:** `tests/Feature/AI/TokenBudgetConcurrencyTest.php` — two parallel requests at budget boundary; second returns `token_limit` without consuming double.
- **Acceptance:** concurrent test green; user at 1.95M/2M with two 40k requests ends at 1.99M, second request 429s.

### INV-2.9.2 SSE-abort policy: keep partial writes, instrument

- **Property:** When `connection_aborted()` is detected mid-generator, in-flight writes are committed (keep). An `ai_abort_events` row is inserted with `conversation_id`, `last_tool_call`, `partial_write_count`. `aiChat.js` reconnect path reads fresh DB state — no special reconciliation logic.
- Decision source: CSJ 24 April — "keep, but note to test and update as needed".
- **Test:** `tests/Feature/AI/SseAbortKeepWritesTest.php` — induce abort mid-create; assert record persisted + `ai_abort_events` row.
- **Acceptance:** abort-induced test shows record in DB; abort event logged.

### INV-2.9.3 Idempotency key on `POST /conversations/{id}/messages`

- **Property:** New `IdempotencyKeyMiddleware` reads the `Idempotency-Key` header; duplicate keys within 24h return the cached response. `ai_request_idempotency` table: `(idempotency_key, user_id, response_hash, created_at)`. TTL cleanup via `AiIdempotencyCleanupJob` scheduled daily.
- **Test:** `tests/Feature/AI/IdempotencyKeyTest.php`.
- **Acceptance:** repeat POST with same key → 200 with cached body; no duplicate DB rows.

### INV-2.9.4 Provider-swap write lock

- **Property:** Each chat-loop iteration reads `ai_provider` once at entry via `HasAiGuardrails::getAiProviderForLoop()`; the captured value holds for the loop even if admin toggles mid-conversation. A version counter on `ai_provider` cache key + per-request snapshot ensures mid-loop swap is detected and the current iteration completes on the original provider.
- **Test:** `tests/Feature/AI/ProviderSwapLockTest.php`.
- **Acceptance:** admin toggle mid-loop does not mix Anthropic cache markers into an xAI request.

### INV-2.9.5 Gap-fill dedup against DB

- **Property:** Before `AssetCaptureEntityExtractor` emits a synthesised fill-in for a detected entity, it queries the target module table for `(user_id, provider | account_name | policy_type_group, created_at > now() - 24h)` and skips matches.
- **Test:** `tests/Feature/AI/GapFillDedupTest.php`.
- **Acceptance:** retry of same user message emits zero gap-fill events if the records already exist.

### INV-2.9.6 `generateTitle` sanitised

- **Property:** `HasAiChat::generateTitle` runs `strip_tags($message)` then `mb_substr(..., 0, 100)` before sending to the LLM AND before writing to `ai_conversations.title`. HTML/script tags cannot reach the sidebar renderer via this path.
- **Test:** `tests/Unit/Traits/GenerateTitleSanitisationTest.php` — inputs: `<script>alert(1)</script>`, `"><img src=x>`, long benign strings.
- **Acceptance:** output never contains `<`, `>`, or raw HTML; length ≤ 100 chars.

---

## §2.10 Compliance

### INV-2.10.1 CoreIdentity rewrite

- **Property:** `app/Services/AI/Prompts/CoreIdentity.php` does not contain the phrase *"qualified financial planner"* or any equivalent professional-role framing ("certified", "authorised adviser", "regulated adviser" applied to Fyn itself). It frames Fyn as a guidance tool only.
- **Test:** `tests/Architecture/CoreIdentityFramingTest.php` — `expect(file_get_contents(base_path('app/Services/AI/Prompts/CoreIdentity.php')))->not->toContain('qualified financial planner')`.
- **Acceptance:** string absence; guidance-only framing reads clearly in source.

### INV-2.10.2 Hash-chain audit

- **Schema for `ai_audit_events` migration:**

```sql
id                BIGINT PRIMARY KEY,
user_id           BIGINT FOREIGN KEY,
conversation_id   BIGINT FOREIGN KEY NULL,
tool_name         VARCHAR(64),
operation         ENUM('read','write','handoff','classify'),
status            ENUM('dispatched','persisted','failed','stripped'),
input_summary     JSON,
result_summary    JSON,
entity_type       VARCHAR(32) NULL,
entity_id         BIGINT NULL,
prev_hash         CHAR(64),
row_hash          CHAR(64),
signed_at         TIMESTAMP,
signature         CHAR(64),
created_at        TIMESTAMP
```

- `row_hash = sha256(prev_hash || serialised(fields_except_hashes) || signed_at)`.
- `signature = hmac_sha256(row_hash, env('AI_AUDIT_HMAC_KEY'))`.
- `php artisan ai:audit:verify-chain` walks the table, asserts `row_hash[i] == sha256(row_hash[i-1] || ...)` for every row, returns JSON `{chain_valid, tip_hash, row_count}`.
- Retention: 7 years for rows where `operation='write'` OR `tool_name LIKE 'get_recommendations'`; 2 years for others. `AiAuditRetentionJob` handles pseudonymisation (swap PII with hash-preserving tokens; chain remains verifiable).

- **Test:** `tests/Feature/Audit/HashChainTest.php`, `tests/Feature/Audit/HmacSigningTest.php`, `tests/Feature/Audit/ChainTamperDetectionTest.php`, `tests/Feature/Audit/RetentionPseudonymisationTest.php`.
- **Acceptance:** chain verification green; tamper detection catches manual edits; retention job preserves chain.

### INV-2.10.3 Runtime consent check

- **Property:** `AiChatController::sendMessage` and `AiChatController::startOnboarding` both call `ConsentService::hasConsent($user, 'ai_chat')` at entry. On `false`: HTTP 403 JSON `{error: 'consent_required', required: 'ai_chat'}`. Consent withdrawn mid-conversation (via `/api/user/consent` DELETE) triggers a `consent_required` SSE event that closes the stream. Consent type `ai_chat` is added to `user_consents.type` enum via migration.
- **Test:** `tests/Feature/AI/ConsentRuntimeCheckTest.php`.
- **Acceptance:** 403 returned; SSE close on mid-stream withdrawal; frontend renders consent gate.

### INV-2.10.4 User-controlled prompt field sanitisation + structural separation

- **Property:** Every user-controlled field (`first_name`, `surname`, `employer`, `occupation`, `goal_name`, family-member names, policy account names, etc.) passed into prompt builders is stripped to `[A-Za-z0-9\s'.,\-]` before interpolation. User-content within prompts is wrapped in `<user_provided>...</user_provided>` markers. System instructions never mix with user-provided strings.
- **Test:** `tests/Unit/Services/AI/Prompts/UserContentSanitisationTest.php` + `tests/Feature/Fyn/Eval/scenarios/06-prompt-injection/*.yaml` — 10 scenarios with known injection strings.
- **Acceptance:** sanitiser strips known-attack chars; injection scenarios pass without the LLM following injected instructions.

---

## §2.11 Memory model

### INV-2.11.1 Three stores + one index, queried in order

- **Property:** `MemoryRetrieverService::retrieve(User $user, string $queryType, array $fields_needed)` returns the first populated layer:
  1. Authoritative DB state (users.*, family_members, linked module tables).
  2. `ai_conversations.onboarding_parked_facts` for the current conversation.
  3. Current conversation's `ai_messages` (already in context via `buildMessageHistory`).
  4. Conversation index — queried only when layers 1–3 are silent.

Prompt builders inject the retrieved facts as a `<known_facts>` block.

- **Test:** `tests/Unit/Services/AI/MemoryRetrieverServiceTest.php` — parameterised over each layer; assert fall-through order.
- **Acceptance:** fall-through is strict; layer N+1 only queried when layer N empty.

### INV-2.11.2 Conversation index schema (Option A: columns on `ai_conversations`)

- **Property:** `ai_conversations` gains four nullable columns via migration:
  - `summary TEXT` — one-paragraph human-readable summary.
  - `topics JSON` — array of canonical topic strings.
  - `entities_mentioned JSON` — array of `{type, id}` records the conversation touched.
  - `intents_stated JSON` — array of stated preferences that don't map to a DB column.

  Populated by `ConversationSummariserJob` dispatched: (a) on `STATE_DONE` transition for onboarding conversations; (b) on conversation-inactivity > 30 minutes for advice conversations (scheduled scan). Summariser uses the cheapest configured model (default `grok-4-1-fast-non-reasoning`) with a structured-output prompt.

- **Test:** `tests/Feature/AI/ConversationIndexPopulationTest.php` + Rubric B `09-09 index-populated-on-close`.
- **Acceptance:** every closed conversation has non-empty `summary`, `topics`, `entities_mentioned`.

### INV-2.11.3 `search_conversation_index` tool

- **Property:** New tool exposed to Advice Fyn. Input `{topic_keywords: string[], entity_types: string[]}`. Output `[{conversation_id, summary, topics, entities_mentioned, intents_stated, last_message_at}]` matching any keyword or entity. Advice Fyn calls this tool only when the `<known_facts>` block does not contain the needed fact.
- **Test:** `tests/Feature/AI/SearchConversationIndexTest.php` + Rubric B `09-10 cross-conversation-surface`.
- **Acceptance:** cross-conversation preference referenced by scenario 09-10 is surfaced without re-asking.

---

## §2.12 System-level messages

Covered in INV-2.4.4 — exempt from handoff-invisibility. No new invariant needed beyond the allowlist of system event types.

---

## §2.13 Eval harness (Rubric B)

### INV-2.13.1 Scenario floor

- **Property:** `tests/Feature/Fyn/Eval/scenarios/` contains at minimum 75 YAML scenarios organised into 9 categories per `fyn-rubrics.md §B`. Mode 1 (mocked, 100% required on every PR) runs in CI. Mode 2 (real providers, ≥97% required) runs weekly + on-release.
- **Test:** `tests/Architecture/EvalScenarioCountTest.php` — count YAML files per category directory; assert minima.
- **Acceptance:** directory-count assertion passes.

### INV-2.13.2 Hard-fail floors

- **Property:** Per `fyn-rubrics.md §B`: 100% entity validity, 100% value accuracy, 100% cross-entity consistency, 0% fabrication. PR blocked on any violation regardless of other scores.
- **Test:** `EvalRunner::enforceHardFailFloors` runs after every scenario.
- **Acceptance:** any floor violation exits non-zero from the CI job.

### INV-2.13.3 Per-tool scorecard published per run

- **Property:** Mode 1 and Mode 2 runs write `storage/eval-scorecards/YYYY-MM-DD-{mode}.md` with per-tool columns: Validity, Recall, Precision, ValueAcc, Consistency, Fabrication. CI uploads as artefact.
- **Test:** `tests/Feature/Fyn/Eval/EvalReport.php` unit test verifies scorecard shape.
- **Acceptance:** scorecard file exists post-run; all 6 columns populated per tool row.

### INV-2.13.4 Thresholds in `config/fyn_eval.php`

- **Property:** Per-tool `recall_floor`, `precision_floor`, with `reason`, `reviewed_by`, `next_review` for any below-100% floor. Thresholds can be RAISED freely; LOWERING requires explicit commit message tag `EVAL_FLOOR_LOWER: ...`.
- **Test:** `tests/Architecture/EvalFloorIntegrityTest.php` — parses git log for `EVAL_FLOOR_LOWER` tags and matches against floor changes in `config/fyn_eval.php`.
- **Acceptance:** no unexplained floor lowering.

---

## Verification (how to check a build against this spec)

**Two test layers are required for every invariant.** See [`03-test-strategy.md`](03-test-strategy.md) for the full scenario catalogue, per-invariant mapping, and click-through discipline (no fabricated URLs, login through the UI, assertions read DOM + SSE + screenshots).

After each sprint:

1. **Full Pest suite:** `./vendor/bin/pest` — all tests pass (includes new Fyn tests added per sprint).
2. **Architecture tests:** `./vendor/bin/pest --testsuite=Architecture` — tool parity, CoreIdentity framing, eval scenario count, eval floor integrity, preview-mode catalogue parity all pass.
3. **Rubric-B Mode 1 (post-Sprint-1):** `./vendor/bin/pest tests/Feature/Fyn/Eval/ --testsuite=Eval` — 100% pass on 30 scenarios (Sprint 1), 75 scenarios (Sprint 2+).
4. **Rubric-B Mode 2 (weekly):** `FYN_EVAL_PROVIDER=real ./vendor/bin/pest tests/Feature/Fyn/Eval/` — ≥97%.
5. **Browser matrix:** `./vendor/bin/pest --testsuite=Browser --filter=BS-` — every sprint's required BS-NN scenarios PASS. Screenshots saved to `docs/sprint-<n>-verification/BS-NN/`.
6. **Audit chain:** `php artisan ai:audit:verify-chain` — `{chain_valid: true, ...}`.
7. **Rubric-A re-score:** walk each D1–D10 test per `fyn-rubrics.md §A`; publish delta.

**Per-sprint Browser matrix requirements:**

| Sprint | BS-NN scenarios required | Total runs |
|---|---|---|
| 0 | BS-01, 02, 04, 05, 06, 07, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23 | 20 |
| 1 | + BS-03, 08, 09, 24 (+ regress Sprint 0) | 24 |
| 2 | + BS-17 extended across 14 batch-tool variants | 24 base + 14 variants = 38 |
| 3 | Full matrix on local AND canonical subset (BS-01, 07, 09, 11, 14, 17) on `csjones.co/fynla` | 38 local + 6 dev = 44 |
| 4 | + BS-25 failover + full matrix on `https://fynla.org` | 39 production |

A sprint is NOT "done" until all Pest tests pass AND all required BS-NN scenarios pass AND evidence (screenshots, transcripts) is committed. See [`03-test-strategy.md §Non-negotiables when reporting "testing complete"`](03-test-strategy.md).

Expected rubric progression:

| Point | Rubric A | Band |
|---|---|---|
| Spec time (24 April 2026) | 4-5/40 | 🔴 Pre-launch |
| Post Sprint 0 | 13-15/40 | 🔴 Pre-launch (still) |
| Post Sprint 1 | 17-18/40 | 🟠 Limited beta — dev deploy gate |
| Post Sprint 2 | ~22/40 | 🟠 Limited beta (upper) |
| Post Sprint 3 | ~24/40 | 🟡 Commercial-ready (just) |
| Post Sprint 4 | ~28-30/40 | 🟡 Commercial-ready (solid) |

---

*Spec proper. Every invariant is the authoritative definition of "correct". Sprint plans in [`10-sprint-0-plan.md`](10-sprint-0-plan.md) through [`14-sprint-4-plan.md`](14-sprint-4-plan.md) convert these invariants into TDD tasks.*
